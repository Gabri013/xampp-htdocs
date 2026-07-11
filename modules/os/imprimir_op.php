<?php
require_once '../../config/config.php';
require_once '../../includes/engenharia.php';
require_once '../../includes/workflow.php';

requirePermission(['master', 'projetista', 'gerente', 'producao']);

$db = getDB();
ensureOrdensServicoIndependentesSchema($db);
ensureEngenhariaSchema($db);

$osId = (int)($_GET['os_id'] ?? 0);
$itemId = (int)($_GET['item_id'] ?? 0);
$itemType = $_GET['item_type'] ?? 'os'; // os ou venda

if ($osId <= 0 && $itemId <= 0) {
    http_response_code(400);
    die('O.S. ou Item invalido.');
}

// Se tem item_id, busca OS pela item (de os_itens ou vendas_itens)
if ($itemId > 0) {
    $stmtOs = $db->prepare("SELECT os_id FROM os_itens WHERE id = ?");
    $stmtOs->execute([$itemId]);
    $row = $stmtOs->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        // Tenta buscar em vendas_itens
        $stmtVenda = $db->prepare("SELECT venda_id FROM vendas_itens WHERE id = ?");
        $stmtVenda->execute([$itemId]);
        $rowVenda = $stmtVenda->fetch(PDO::FETCH_ASSOC);
        if ($rowVenda && $rowVenda['venda_id']) {
            $stmtOs = $db->prepare("SELECT id FROM ordens_servico WHERE venda_id = ? LIMIT 1");
            $stmtOs->execute([$rowVenda['venda_id']]);
            $rowOs = $stmtOs->fetch(PDO::FETCH_ASSOC);
            $osId = (int)($rowOs['id'] ?? 0);
            $itemType = 'venda';
        }
    } else {
        $osId = (int)($row['os_id'] ?? 0);
        $itemType = 'os';
    }
}

$stmt = $db->prepare("
    SELECT
        os.id, os.numero, os.venda_id, os.data_inicio, os.data_termino, os.prioridade,
        os.observacoes_gerais, os.observacoes_corte_dobra, os.observacoes_solda,
        c.razao_social,
        COALESCE(v.numero, 'Independente') AS venda_numero,
        v.data_venda,
        COALESCE(u.nome, '-') AS vendedor_nome
    FROM ordens_servico os
    INNER JOIN clientes c ON c.id = os.cliente_id
    LEFT JOIN vendas v ON v.id = os.venda_id
    LEFT JOIN usuarios u ON u.id = v.usuario_id
    WHERE os.id = ?
    LIMIT 1
");
$stmt->execute([$osId]);
$os = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$os) {
    http_response_code(404);
    die('O.S. nao encontrada.');
}

// Buscar OP existente da OS
$stmtOp = $db->prepare("SELECT numero, status, criado_em FROM ordens_producao WHERE os_id = ? ORDER BY id DESC LIMIT 1");
$stmtOp->execute([$osId]);
$op = $stmtOp->fetch(PDO::FETCH_ASSOC);

$numeroOp = $op['numero'] ?? 'OP-' . date('Y') . '-' . str_pad((string)$osId, 5, '0', STR_PAD_LEFT);
$dataEmissaoOp = !empty($op['criado_em']) ? formatDate($op['criado_em']) : date('d/m/Y');
$dataEmissaoPedido = !empty($os['data_venda']) ? formatDate($os['data_venda']) : formatDate($os['data_inicio']);

// Buscar itens (item selecionado ou todos) — uma folha de OP por item
if ($itemId > 0) {
    $itens = getItensComerciaisOS($db, $osId, (int)($os['venda_id'] ?? 0), $itemId);
} else {
    $itens = getItensComerciaisOS($db, (int)$os['id'], (int)($os['venda_id'] ?? 0));
}
if (empty($itens)) {
    $itens = [[
        'produto_codigo' => '-',
        'produto_nome' => 'Sem itens registrados nesta O.S.',
        'descricao_manual' => '',
        'quantidade' => 0,
    ]];
}

// Processos na ordem canônica do fluxo (workflow.php), sem autorização/conclusão
$labelsEtapas = [
    'engenharia'   => 'Engenharia',
    'programacao'  => 'Programação',
    'corte'        => 'Corte',
    'dobra'        => 'Dobra',
    'tubo'         => 'Tubo',
    'solda'        => 'Solda',
    'mobiliario'   => 'Mobiliário',
    'coccao'       => 'Cocção',
    'refrigeracao' => 'Refrigeração',
    'acabamento'   => 'Acabamento',
    'montagem'     => 'Montagem',
    'embalagem'    => 'Embalagem',
    'finalizacao'  => 'Finalização',
];
$processos = [];
foreach (getValidOSEtapas() as $etapaFluxo) {
    if (in_array($etapaFluxo, ['autorizacao', 'concluida'], true)) continue;
    $processos[] = $labelsEtapas[$etapaFluxo] ?? ucfirst($etapaFluxo);
}

$totalPaginas = count($itens);

// Data/hora de impressão em pt-BR (ex.: Qua, 4 fev 2026 09:51:53)
$diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
$mesesAbrev = [1 => 'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
$agora = new DateTime('now');
$dataImpressao = $diasSemana[(int)$agora->format('w')] . ', ' . $agora->format('j') . ' ' . $mesesAbrev[(int)$agora->format('n')] . ' ' . $agora->format('Y H:i:s');

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ordem de Produção <?= htmlspecialchars($numeroOp) ?></title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;font-family:Arial,Helvetica,sans-serif;color:#000}
        body{background:#f5f5f5}
        .op-page{background:#fff;width:210mm;min-height:297mm;margin:0 auto 10px;padding:8mm 9mm 10mm;position:relative;display:flex;flex-direction:column}

        /* ── Grade superior contínua ─────────────────────────────── */
        .grade{border:1px solid #000}
        .row{display:flex}
        .cell{border-right:1px solid #000;border-bottom:1px solid #000;padding:1mm 1.6mm;position:relative}
        .cell:last-child{border-right:none}
        .row:last-child > .cell{border-bottom:none}
        .lbl{font-size:7.5px;line-height:1.25}
        .val{font-size:10px;font-weight:bold;line-height:1.25}

        /* Cabeçalho */
        .hd-logo{width:52mm;display:flex;align-items:center;justify-content:center;padding:1.5mm 2mm}
        .hd-logo img{width:100%;max-height:19mm;object-fit:contain}
        .hd-title{flex:1;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:bold}
        .hd-num{width:44mm;padding:1mm 1.6mm}
        .hd-num .lbl{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .hd-num .num{font-size:15px;font-weight:bold;margin-top:1.5mm}

        /* Descrição + barcode */
        .desc-cell{flex:1;min-height:16mm}
        .desc-cell .texto{font-size:12px;font-weight:bold;text-transform:uppercase;margin-top:1mm;line-height:1.3}
        .bc-cell{width:44mm;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:1mm}
        .bc-cell svg{width:38mm;height:9mm}
        .bc-cell .bc-num{font-size:8px;margin-top:.5mm}

        /* Linha de campos: código / ns / liberação / prazo / qtde */
        .c-codigo{width:34mm}
        .c-ns{width:22mm}
        .c-lib{flex:1}
        .c-prazo{width:30mm}
        .c-qtde{width:30mm;display:flex;align-items:flex-start;gap:2mm}
        .c-qtde .bloco{flex:1}
        .c-qtde .qtd-num{font-size:13px;font-weight:bold;text-align:center}
        .c-qtde .un{font-size:9px;font-weight:bold;align-self:center}

        /* Pedido / emissão */
        .c-pedido{flex:1.4}
        .c-pedido .val{text-align:center}
        .c-emissao{flex:1}

        /* Inform adicional + complementar (célula direita com 2 linhas de altura) */
        .col-esq{flex:1.2;display:flex;flex-direction:column}
        .col-esq .cell{border-right:1px solid #000}
        .col-esq .cell:last-child{border-bottom:none}
        .col-dir{flex:1;padding:1mm 1.6mm}

        .inline-lv{display:flex;gap:2mm;align-items:baseline}
        .inline-lv .lbl{font-size:8px}
        .inline-lv .val{font-size:10px}

        /* ── Tabela de processos ─────────────────────────────────── */
        .processos{margin-top:3mm}
        .processos table{width:100%;border-collapse:collapse}
        .processos th{font-size:8px;font-weight:bold;border:1px solid #000;padding:1.2mm 1mm;text-transform:uppercase;background:#fff;text-align:center}
        .processos td{border:1px solid #000;padding:1.6mm 1.2mm;font-size:9px}
        .processos td.n{width:5mm;text-align:left;padding-left:1.5mm}
        .processos td.proc{width:32mm}
        .processos th.c-ini{width:20mm}
        .processos th.c-ter{width:20mm}
        .processos th.c-obs{width:46mm}
        .processos th.c-resp{width:30mm}
        .processos th.c-lider{width:22mm}

        /* ── Controle de revisão ─────────────────────────────────── */
        .revisao{margin-top:3.5mm}
        .revisao .titulo-sec{font-size:8.5px;font-weight:bold;text-transform:uppercase;margin-bottom:1.2mm}
        .revisao table{width:100%;border-collapse:collapse}
        .revisao th{font-size:8px;font-weight:bold;border:1px solid #000;padding:1.2mm 1mm;text-transform:uppercase;background:#fff;text-align:center}
        .revisao td{border:1px solid #000;padding:1.8mm 1.2mm;font-size:9px}
        .revisao td.n{width:22mm;text-align:center}
        .revisao th.c-data{width:24mm}
        .revisao th.c-prazo{width:28mm}

        /* ── Observação com linhas pautadas ──────────────────────── */
        .obs-final{margin-top:4mm;flex:1;display:flex;flex-direction:column}
        .obs-final .titulo-sec{font-size:8.5px;font-weight:bold;text-transform:uppercase;margin-bottom:2mm}
        .obs-final .linhas{flex:1;display:flex;flex-direction:column;justify-content:flex-start;gap:6.5mm;padding-top:4mm}
        .obs-final .linha{border-bottom:1px solid #000;height:0}

        /* ── Rodapé ──────────────────────────────────────────────── */
        .op-footer{margin-top:4mm;display:flex;justify-content:space-between;align-items:flex-end;font-size:7px;color:#333}
        .op-footer .doc{text-align:right;line-height:1.5}

        .printbar{position:sticky;top:0;background:#111827;color:#fff;padding:8px 12px;display:flex;gap:8px;z-index:10}
        .printbar button{background:#16a34a;border:0;color:#fff;font-weight:700;padding:7px 10px;border-radius:6px;cursor:pointer;font-size:12px}
        .printbar a{color:#bfdbfe;align-self:center;font-size:12px}

        @media print{
            .printbar{display:none!important}
            body{background:#fff}
            .op-page{width:100%;min-height:auto;margin:0;padding:6mm 8mm;page-break-after:always}
            .op-page:last-child{page-break-after:auto}
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
</head>
<body>
    <div class="printbar">
        <button type="button" onclick="window.print()">Imprimir / Salvar PDF</button>
        <a href="javascript:window.close()">Fechar</a>
    </div>

    <?php foreach ($itens as $idx => $item):
        $numItem = str_pad((string)($idx + 1), 2, '0', STR_PAD_LEFT);
        $descricaoItem = trim((string)($item['descricao_manual'] ?: ($item['produto_nome'] ?? '')));
        $qtdItem = (float)($item['quantidade'] ?? 0);
        $numOpItem = $numeroOp . ($totalPaginas > 1 ? '-' . $numItem : '');
    ?>
    <div class="op-page">
        <div class="grade">
            <!-- Cabeçalho: logo | título | nº -->
            <div class="row">
                <div class="cell hd-logo">
                    <img src="<?= SITE_URL ?>/assets/img/logo_cozinca_op.png" alt="Cozinca Inox">
                </div>
                <div class="cell hd-title">ORDEM DE PRODUÇÃO</div>
                <div class="cell hd-num">
                    <div class="lbl">Nº da Ordem de produção</div>
                    <div class="num"><?= htmlspecialchars($numOpItem) ?></div>
                </div>
            </div>

            <!-- Descrição do produto + barcode -->
            <div class="row">
                <div class="cell desc-cell">
                    <div class="lbl">Descrição do produto</div>
                    <div class="texto"><?= htmlspecialchars($descricaoItem !== '' ? $descricaoItem : '-') ?></div>
                </div>
                <div class="cell bc-cell">
                    <svg class="barcode" data-code="<?= htmlspecialchars($numOpItem) ?>"></svg>
                    <div class="bc-num"><?= htmlspecialchars($numOpItem) ?></div>
                </div>
            </div>

            <!-- Código / N.S. / Liberação OP / Prazo / Qtde -->
            <div class="row">
                <div class="cell c-codigo">
                    <div class="lbl">Código do Produto</div>
                    <div class="val"><?= htmlspecialchars((string)($item['produto_codigo'] ?? '-') ?: '-') ?></div>
                </div>
                <div class="cell c-ns">
                    <div class="lbl">N.S.:</div>
                    <div class="val">&nbsp;</div>
                </div>
                <div class="cell c-lib">
                    <div class="lbl">Liberação OP</div>
                    <div class="val">Data da emissão: <?= htmlspecialchars($dataEmissaoOp) ?></div>
                </div>
                <div class="cell c-prazo">
                    <div class="lbl">Prazo</div>
                    <div class="val"><?= htmlspecialchars(formatDate($os['data_termino']) ?: '-') ?></div>
                </div>
                <div class="cell c-qtde">
                    <div class="bloco">
                        <div class="lbl">Qtde</div>
                        <div class="qtd-num"><?= number_format($qtdItem, 0, ',', '.') ?></div>
                    </div>
                    <div class="un">UN</div>
                </div>
            </div>

            <!-- Nº do pedido / Emissão do pedido -->
            <div class="row">
                <div class="cell c-pedido">
                    <div class="inline-lv"><span class="lbl">Nº do pedido:</span></div>
                    <div class="val" style="text-align:center;margin-top:-2.5mm"><?= htmlspecialchars($os['venda_numero']) ?></div>
                </div>
                <div class="cell c-emissao">
                    <div class="inline-lv"><span class="lbl">Emissão do pedido:</span> <span class="val"><?= htmlspecialchars($dataEmissaoPedido) ?></span></div>
                </div>
            </div>

            <!-- Cliente -->
            <div class="row">
                <div class="cell" style="flex:1">
                    <div class="inline-lv"><span class="lbl">Cliente:</span> <span class="val"><?= htmlspecialchars($os['razao_social']) ?></span></div>
                </div>
            </div>

            <!-- Inform. Adicion / Observação | Informação complementar do item -->
            <div class="row">
                <div class="col-esq">
                    <div class="cell" style="border-bottom:1px solid #000">
                        <div class="inline-lv"><span class="lbl">Inform. Adicion:</span> <span class="val">ITEM <?= $numItem ?><?= $os['venda_numero'] !== 'Independente' ? '' : '' ?></span></div>
                    </div>
                    <div class="cell" style="flex:1;border-bottom:none">
                        <div class="inline-lv"><span class="lbl">Observação:</span> <span class="val" style="font-weight:normal;font-size:9px"><?= htmlspecialchars((string)($os['observacoes_gerais'] ?? '') ?: '') ?></span></div>
                    </div>
                </div>
                <div class="cell col-dir" style="border-bottom:none">
                    <div class="lbl">Informação complementar do item</div>
                </div>
            </div>
        </div>

        <!-- Tabela de processos -->
        <div class="processos">
            <table>
                <thead>
                    <tr>
                        <th style="width:5mm"></th>
                        <th>Processo</th>
                        <th class="c-ini">Início</th>
                        <th class="c-ter">Término</th>
                        <th class="c-obs">OBS</th>
                        <th class="c-resp">Responsável</th>
                        <th class="c-lider">Lider</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($processos as $i => $proc): ?>
                    <tr>
                        <td class="n"><?= $i + 1 ?></td>
                        <td class="proc"><?= htmlspecialchars($proc) ?></td>
                        <td></td><td></td><td></td><td></td><td></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Controle de revisão de prazo -->
        <div class="revisao">
            <div class="titulo-sec">Controle de Revisão de Prazo O.P.</div>
            <table>
                <thead>
                    <tr>
                        <th style="width:22mm">Revisão</th>
                        <th class="c-data">Data</th>
                        <th class="c-prazo">Novo Prazo</th>
                        <th>Motivo / Justificativa</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td class="n">1</td><td></td><td></td><td></td></tr>
                    <tr><td class="n">2</td><td></td><td></td><td></td></tr>
                </tbody>
            </table>
        </div>

        <!-- Observação (linhas pautadas) -->
        <div class="obs-final">
            <div class="titulo-sec">Observação</div>
            <div class="linhas">
                <?php for ($l = 0; $l < 10; $l++): ?><div class="linha"></div><?php endfor; ?>
            </div>
        </div>

        <div class="op-footer">
            <div><?= htmlspecialchars($dataImpressao) ?></div>
            <div class="doc">FR-028 - REV 16<br>Página <?= $idx + 1 ?> de <?= $totalPaginas ?></div>
        </div>
    </div>
    <?php endforeach; ?>

    <script>
        window.addEventListener('load', function () {
            if (typeof JsBarcode !== 'undefined') {
                document.querySelectorAll('svg.barcode').forEach(function (el) {
                    try {
                        JsBarcode(el, el.dataset.code, { format: 'CODE128', displayValue: false, height: 34, margin: 0 });
                    } catch (e) {}
                });
            }
            setTimeout(function () { try { window.print(); } catch (e) {} }, 600);
        });
    </script>
</body>
</html>
