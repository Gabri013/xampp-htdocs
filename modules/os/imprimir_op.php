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
        *{margin:0;padding:0;box-sizing:border-box;font-family:Arial,Helvetica,sans-serif}
        body{background:#f5f5f5}
        .op-page{background:#fff;width:210mm;min-height:297mm;margin:0 auto 10px;padding:10mm 12mm;position:relative;display:flex;flex-direction:column}

        /* Cabeçalho: logo | título | nº da OP */
        .op-header{display:flex;align-items:stretch;border:1px solid #000}
        .op-header .logo-box{width:52mm;display:flex;align-items:center;justify-content:center;padding:3mm;border-right:1px solid #000}
        .op-header .logo-box img{max-width:100%;max-height:18mm;object-fit:contain}
        .op-header .titulo{flex:1;display:flex;align-items:center;justify-content:center;font-size:17px;font-weight:bold;letter-spacing:.5px}
        .op-header .num-box{width:52mm;border-left:1px solid #000;padding:2mm 3mm;text-align:center}
        .op-header .num-box .lbl{font-size:8px;font-weight:bold}
        .op-header .num-box .num{font-size:16px;font-weight:bold;margin-top:1mm}

        /* Descrição do produto */
        .sec-label{font-size:9px;font-weight:bold;padding:1.5mm 2mm;border:1px solid #000;border-top:none;background:#efefef}
        .desc-produto{border:1px solid #000;border-top:none;padding:3mm 2mm;font-size:14px;font-weight:bold;min-height:14mm;text-transform:uppercase}

        /* Grade de campos */
        .campos{display:grid;grid-template-columns:1.2fr .7fr 1.4fr 1fr .9fr;border-left:1px solid #000;border-right:1px solid #000}
        .campo{border-right:1px solid #000;border-bottom:1px solid #000;padding:1.5mm 2mm;min-height:11mm}
        .campo:last-child{border-right:none}
        .campo .lbl{font-size:8px;font-weight:bold;color:#222}
        .campo .val{font-size:11px;font-weight:bold;margin-top:1mm}
        .campos-2{display:grid;grid-template-columns:2fr 1.1fr;border-left:1px solid #000;border-right:1px solid #000}
        .campos-1{display:grid;grid-template-columns:1fr;border-left:1px solid #000;border-right:1px solid #000}

        /* Tabela de processos */
        .processos{margin-top:4mm}
        .processos table{width:100%;border-collapse:collapse}
        .processos th{background:#efefef;font-size:9px;border:1px solid #000;padding:1.5mm;text-transform:uppercase}
        .processos td{border:1px solid #000;padding:2.4mm 1.5mm;font-size:10px}
        .processos td.n{width:6mm;text-align:center}
        .processos td.proc{width:34mm;font-weight:bold}
        .processos th.c-ini,.processos th.c-ter{width:18mm}
        .processos th.c-obs{width:26mm}
        .processos th.c-resp{width:32mm}
        .processos th.c-lider{width:26mm}

        /* Controle de revisão de prazo */
        .revisao{margin-top:4mm}
        .revisao .titulo-sec{font-size:10px;font-weight:bold;border:1px solid #000;border-bottom:none;background:#efefef;padding:1.5mm 2mm;text-transform:uppercase}
        .revisao table{width:100%;border-collapse:collapse}
        .revisao th{background:#f7f7f7;font-size:9px;border:1px solid #000;padding:1.5mm;text-transform:uppercase}
        .revisao td{border:1px solid #000;padding:2.6mm 1.5mm;font-size:10px}
        .revisao td.n{width:16mm;text-align:center;font-weight:bold}
        .revisao th.c-data{width:26mm}
        .revisao th.c-prazo{width:30mm}

        /* Observação final */
        .obs-final{margin-top:4mm;flex:1;display:flex;flex-direction:column}
        .obs-final .titulo-sec{font-size:10px;font-weight:bold;border:1px solid #000;border-bottom:none;background:#efefef;padding:1.5mm 2mm;text-transform:uppercase}
        .obs-final .area{border:1px solid #000;flex:1;min-height:22mm;padding:2mm;font-size:10px}

        /* Rodapé */
        .op-footer{margin-top:3mm;display:flex;justify-content:space-between;font-size:8px;color:#333}
        .op-footer .doc{text-align:right}

        .printbar{position:sticky;top:0;background:#111827;color:#fff;padding:8px 12px;display:flex;gap:8px;z-index:10}
        .printbar button{background:#16a34a;border:0;color:#fff;font-weight:700;padding:7px 10px;border-radius:6px;cursor:pointer;font-size:12px}
        .printbar a{color:#bfdbfe;align-self:center;font-size:12px}

        @media print{
            .printbar{display:none!important}
            body{background:#fff}
            .op-page{width:100%;min-height:auto;margin:0;padding:8mm 10mm;page-break-after:always}
            .op-page:last-child{page-break-after:auto}
        }
    </style>
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
    ?>
    <div class="op-page">
        <div class="op-header">
            <div class="logo-box">
                <img src="<?= SITE_URL ?>/assets/img/logo_cozinca_impressao.png" alt="Cozinca Inox">
            </div>
            <div class="titulo">ORDEM DE PRODUÇÃO</div>
            <div class="num-box">
                <div class="lbl">Nº da Ordem de produção</div>
                <div class="num"><?= htmlspecialchars($numeroOp) ?><?= $totalPaginas > 1 ? '-' . $numItem : '' ?></div>
            </div>
        </div>

        <div class="sec-label">Descrição do produto</div>
        <div class="desc-produto"><?= htmlspecialchars($descricaoItem !== '' ? $descricaoItem : '-') ?></div>

        <div class="campos">
            <div class="campo">
                <div class="lbl">Código do Produto</div>
                <div class="val"><?= htmlspecialchars((string)($item['produto_codigo'] ?? '-') ?: '-') ?></div>
            </div>
            <div class="campo">
                <div class="lbl">N.S.:</div>
                <div class="val">&nbsp;</div>
            </div>
            <div class="campo">
                <div class="lbl">Liberação OP</div>
                <div class="val">Data da emissão: <?= htmlspecialchars($dataEmissaoOp) ?></div>
            </div>
            <div class="campo">
                <div class="lbl">Prazo</div>
                <div class="val"><?= htmlspecialchars(formatDate($os['data_termino']) ?: '-') ?></div>
            </div>
            <div class="campo">
                <div class="lbl">Qtde</div>
                <div class="val"><?= number_format($qtdItem, 0, ',', '.') ?> UN</div>
            </div>
        </div>

        <div class="campos-2">
            <div class="campo">
                <div class="lbl">Nº do pedido:</div>
                <div class="val"><?= htmlspecialchars($os['venda_numero']) ?> &nbsp;&nbsp;(O.S. <?= htmlspecialchars($os['numero']) ?>)</div>
            </div>
            <div class="campo" style="border-right:none">
                <div class="lbl">Emissão do pedido:</div>
                <div class="val"><?= htmlspecialchars($dataEmissaoPedido) ?></div>
            </div>
        </div>

        <div class="campos-1">
            <div class="campo" style="border-right:none">
                <div class="lbl">Cliente:</div>
                <div class="val"><?= htmlspecialchars($os['razao_social']) ?></div>
            </div>
        </div>

        <div class="campos-2">
            <div class="campo">
                <div class="lbl">Inform. Adicion:</div>
                <div class="val">ITEM <?= $numItem ?></div>
            </div>
            <div class="campo" style="border-right:none">
                <div class="lbl">Informação complementar do item</div>
                <div class="val">&nbsp;</div>
            </div>
        </div>

        <div class="campos-1">
            <div class="campo" style="border-right:none">
                <div class="lbl">Observação:</div>
                <div class="val"><?= nl2br(htmlspecialchars((string)($os['observacoes_gerais'] ?? '') ?: ' ')) ?></div>
            </div>
        </div>

        <div class="processos">
            <table>
                <thead>
                    <tr>
                        <th></th>
                        <th>Processo</th>
                        <th class="c-ini">Início</th>
                        <th class="c-ter">Término</th>
                        <th class="c-obs">OBS</th>
                        <th class="c-resp">Responsável</th>
                        <th class="c-lider">Líder</th>
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

        <div class="revisao">
            <div class="titulo-sec">Controle de Revisão de Prazo O.P.</div>
            <table>
                <thead>
                    <tr>
                        <th>Revisão</th>
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

        <div class="obs-final">
            <div class="titulo-sec">Observação</div>
            <div class="area"></div>
        </div>

        <div class="op-footer">
            <div><?= htmlspecialchars($dataImpressao) ?></div>
            <div class="doc">FR-028 - REV 16<br>Página <?= $idx + 1 ?> de <?= $totalPaginas ?></div>
        </div>
    </div>
    <?php endforeach; ?>

    <script>
        window.addEventListener('load', function () {
            setTimeout(function () { try { window.print(); } catch (e) {} }, 500);
        });
    </script>
</body>
</html>
