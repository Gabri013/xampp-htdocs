<?php
require_once '../../config/config.php';
require_once '../../includes/engenharia.php';
require_once '../../includes/workflow.php';

requirePermission(['master', 'projetista', 'gerente', 'producao', 'programacao', 'engenharia']);

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

// Nº da Ordem de Produção = nº da O.S.
$numeroOp = $op['numero'] ?? $os['numero'];
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
    'engenharia'   => 'Projetista',
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

// Setores condicionais desta O.S. (bolinha colorida na OP):
// mobiliário = verde, cocção = amarelo, refrigeração = azul
$coresSetores = [
    'mobiliario'   => ['cor' => '#16a34a', 'label' => 'Mobiliário'],
    'coccao'       => ['cor' => '#d9a406', 'label' => 'Cocção'],
    'refrigeracao' => ['cor' => '#0284c7', 'label' => 'Refrigeração'],
];
$stmtPlan = $db->prepare("SELECT DISTINCT etapa FROM os_etapas_producao WHERE os_id = ? AND etapa IN ('mobiliario', 'coccao', 'refrigeracao')");
$stmtPlan->execute([$osId]);
$setoresCondicionais = $stmtPlan->fetchAll(PDO::FETCH_COLUMN);
usort($setoresCondicionais, fn($a, $b) => array_search($a, array_keys($coresSetores)) <=> array_search($b, array_keys($coresSetores)));

// Bolinhas da OP: urgência (rosa) + setores condicionais + cor da linha
$dotsOP = [];
if (($os['prioridade'] ?? '') === 'vermelho') {
    $dotsOP[] = ['cor' => '#ec4899', 'label' => 'URGÊNCIA'];
}
foreach ($setoresCondicionais as $sc) {
    $dotsOP[] = $coresSetores[$sc];
}
try {
    if (!empty($os['venda_id'])) {
        $stmtLinha = $db->prepare("SELECT DISTINCT pc.cor, pc.nome FROM vendas_itens vi
            INNER JOIN produtos p ON p.id = vi.produto_id
            INNER JOIN produto_categorias pc ON pc.id = p.categoria_id
            WHERE vi.venda_id = ? AND pc.cor IS NOT NULL AND pc.cor != ''");
        $stmtLinha->execute([(int) $os['venda_id']]);
    } else {
        $stmtLinha = $db->prepare("SELECT DISTINCT pc.cor, pc.nome FROM os_itens oi
            INNER JOIN produtos p ON p.id = oi.produto_id
            INNER JOIN produto_categorias pc ON pc.id = p.categoria_id
            WHERE oi.os_id = ? AND pc.cor IS NOT NULL AND pc.cor != ''");
        $stmtLinha->execute([$osId]);
    }
    $coresJa = array_column($dotsOP, 'cor');
    foreach ($stmtLinha->fetchAll(PDO::FETCH_ASSOC) as $linhaCat) {
        if (!in_array($linhaCat['cor'], $coresJa, true)) {
            $dotsOP[] = ['cor' => $linhaCat['cor'], 'label' => 'Linha ' . $linhaCat['nome']];
            $coresJa[] = $linhaCat['cor'];
        }
    }
} catch (Exception $e) { /* coluna cor pode não existir */ }

// Materiais solicitados pelo projetista (matéria-prima/insumos)
$materiaisOP = [];
try {
    $stmtMatOP = $db->prepare("SELECT descricao, quantidade, unidade, observacao FROM os_materiais_solicitados WHERE os_id = ? ORDER BY id");
    $stmtMatOP->execute([$osId]);
    $materiaisOP = $stmtMatOP->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { /* tabela pode não existir */ }

// Folha técnica do produto (verso de cada folha de OP)
$folhaPorProduto = [];
$produtoIdsFolha = array_values(array_unique(array_filter(array_map(fn($it) => (int) ($it['produto_id'] ?? 0), $itens))));
if (!empty($produtoIdsFolha)) {
    try {
        $inIds = implode(',', $produtoIdsFolha);
        $stmtFolha = $db->query("SELECT p.id, p.codigo, p.nome, p.tipo_produto, p.medida_a, p.medida_b, p.medida_c, p.medida_d,
                p.caracteristicas, p.codificacao_legenda, p.opcoes_folha, p.observacoes_folha, p.garantia_folha, p.perspectiva,
                pc.nome AS linha_nome
            FROM produtos p LEFT JOIN produto_categorias pc ON pc.id = p.categoria_id
            WHERE p.id IN ($inIds)");
        foreach ($stmtFolha->fetchAll(PDO::FETCH_ASSOC) as $fRow) {
            $folhaPorProduto[(int) $fRow['id']] = $fRow;
        }
    } catch (Exception $e) { /* colunas podem não existir em banco antigo */ }
}
// Desenho técnico da O.S. (imagem anexada pelo projetista) para o VERSO.
// Só usa se o arquivo existir no disco — registros mesclados do servidor antigo
// podem apontar para arquivos que não vieram junto. Ordem: projeto_foto > projeto.
$desenhoOsImg = '';
try {
    $stmtDes = $db->prepare("SELECT nome_arquivo FROM os_arquivos
        WHERE os_id = ? AND (LOWER(nome_arquivo) LIKE '%.jpg' OR LOWER(nome_arquivo) LIKE '%.jpeg'
            OR LOWER(nome_arquivo) LIKE '%.png' OR LOWER(nome_arquivo) LIKE '%.webp' OR LOWER(nome_arquivo) LIKE '%.gif')
        ORDER BY FIELD(tipo, 'projeto_foto', 'projeto') DESC, id DESC");
    $stmtDes->execute([$osId]);
    foreach ($stmtDes->fetchAll(PDO::FETCH_COLUMN) as $nomeArq) {
        foreach (['projetos', 'desenhos'] as $pasta) {
            if (is_file(BASE_PATH . "/assets/uploads/$pasta/" . $nomeArq)) {
                $desenhoOsImg = SITE_URL . "/assets/uploads/$pasta/" . rawurlencode($nomeArq);
                break 2;
            }
        }
    }
} catch (Exception $e) { /* os_arquivos pode não existir */ }

$obsFolhaPadrao = "Todas as instalações devem obedecer às normas da ABNT;\nMedidas em milímetros;\nDesenho sem escala;\nProduto embalado conforme a distância a ser percorrida.";
$garantiaFolhaPadrao = 'Todos os produtos fabricados pela Cozinca são testados e garantidos pela fábrica e por seus representantes autorizados. A assistência técnica coberta pela garantia é prestada de segunda a sexta-feira. Para mais informações, consulte o suporte técnico Cozinca.';
$linhasTexto = fn(?string $t) => array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $t))));

$totalPaginas = count($itens) * 2; // frente (OP) + verso (folha técnica) por item

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

        /* Descrição + barcode + QR */
        .desc-cell{flex:1;min-height:16mm}
        .desc-cell .texto{font-size:12px;font-weight:bold;text-transform:uppercase;margin-top:1mm;line-height:1.3}
        .bc-cell{width:44mm;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:1mm}
        .bc-cell svg{width:38mm;height:9mm}
        .bc-cell .bc-num{font-size:8px;margin-top:.5mm}
        .qr-cell{width:20mm;display:flex;align-items:center;justify-content:center;padding:1mm}
        .qr-cell .qr-box{width:17mm;height:17mm}
        .qr-cell .qr-box img, .qr-cell .qr-box canvas{width:100%!important;height:100%!important}

        /* Bolinhas de setor condicional (mobiliário/cocção/refrigeração) */
        .setores-dots{display:flex;align-items:center;gap:2.5mm;margin-top:1.5mm}
        .setores-dots .dot{display:inline-flex;align-items:center;gap:1.2mm;font-size:8px;font-weight:bold}
        .setores-dots .dot i{width:4mm;height:4mm;border-radius:50%;display:inline-block;border:.3mm solid #000;-webkit-print-color-adjust:exact;print-color-adjust:exact}

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

        /* ===== Verso: Folha Técnica do Produto ===== */
        .ft-head{display:flex;align-items:center;justify-content:space-between;border-bottom:2.5px solid #E8901A;padding-bottom:3mm;margin-bottom:4mm}
        .ft-head img{height:14mm}
        .ft-head .tit{text-align:right}
        .ft-head .tit .linha-nome{font-size:26px;font-weight:800;color:#3a2c1e;letter-spacing:1px;text-transform:uppercase}
        .ft-head .tit .tipo{font-size:11px;letter-spacing:4px;color:#555;text-transform:uppercase;margin-top:1mm}
        .ft-body{display:flex;gap:4mm}
        .ft-col-esq{flex:1.35;display:flex;flex-direction:column;gap:3mm}
        .ft-col-dir{flex:1;display:flex;flex-direction:column;gap:3mm}
        .ft-persp{border:1px solid #444;min-height:105mm;display:flex;align-items:center;justify-content:center;position:relative;padding:3mm}
        .ft-persp img{max-width:100%;max-height:100mm}
        .ft-persp .rotulo{position:absolute;left:3mm;bottom:2mm;font-size:10px;font-weight:700;letter-spacing:2px;color:#666}
        .ft-sec .barra{background:#c9c9c9;font-size:9.5px;font-weight:800;letter-spacing:1px;text-transform:uppercase;padding:1.4mm 2mm}
        .ft-sec .conteudo{border:1px solid #444;border-top:0;padding:2mm;font-size:9.5px;min-height:8mm}
        .ft-cod .codigo-grande{font-size:15px;font-weight:800;letter-spacing:2px;margin-bottom:2mm}
        .ft-cod .leg{font-size:8.5px;line-height:1.7}
        .ft-cod .leg div{border-bottom:0;padding-left:2mm}
        .ft-campos{border:1px solid #444;padding:2.5mm;display:flex;flex-direction:column;gap:2.4mm;font-size:10px}
        .ft-campos .cl{display:flex;align-items:baseline;gap:2mm}
        .ft-campos .cl .l{font-weight:700;min-width:16mm}
        .ft-campos .cl .v{flex:1;border-bottom:1px solid #999;padding:0 1mm;min-height:4mm}
        .ft-desc .conteudo{font-size:13px;font-weight:700;color:#8a6d3b;min-height:10mm;display:flex;align-items:center}
        .ft-med .conteudo{display:flex;flex-direction:column;gap:1.8mm;padding:2.5mm 2mm}
        .ft-med .m{display:flex;align-items:center;gap:2mm;font-size:10.5px}
        .ft-med .m .rot{font-weight:800;width:8mm}
        .ft-med .m .cx{border:1px solid #999;background:#f2f2f2;flex:1;text-align:center;padding:1mm;font-weight:700;min-height:5mm}
        .ft-med .m .un{font-size:9px;color:#555;width:8mm}
        .ft-li{margin:0;padding-left:4mm;font-size:9px;line-height:1.55}
        .ft-li li{margin-bottom:1mm}
        .ft-li li::marker{color:#E8901A}
        .ft-gar{font-size:8.5px;line-height:1.5}
        .ft-rodape{border-top:2px solid #333;margin-top:auto;padding-top:1.5mm;display:flex;justify-content:space-between;font-size:8.5px}
        .ft-rodape strong{font-size:9.5px}
        .ft-lateral{position:absolute;right:2mm;top:50%;transform:translateY(-50%);writing-mode:vertical-rl;font-size:6.5px;letter-spacing:1px;color:#888;text-transform:uppercase}
        .ft-page{position:relative;display:flex;flex-direction:column}

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
    <!-- Barcode Code128 e QR gerados no servidor (SVG/data-URI autocontidos) -->
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

            <!-- Descrição do produto + setores + barcode + QR -->
            <div class="row">
                <div class="cell desc-cell">
                    <div class="lbl">Descrição do produto</div>
                    <div class="texto"><?= htmlspecialchars($descricaoItem !== '' ? $descricaoItem : '-') ?></div>
                    <?php if (!empty($dotsOP)): ?>
                    <div class="setores-dots">
                        <?php foreach ($dotsOP as $info): ?>
                        <span class="dot"><i style="background:<?= $info['cor'] ?>"></i> <?= $info['label'] ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="cell bc-cell">
                    <div class="barcode" style="height:34px"><?= gerarCode128Svg($numOpItem, 40) ?></div>
                    <div class="bc-num"><?= htmlspecialchars($numOpItem) ?></div>
                </div>
                <div class="cell qr-cell">
                    <div class="qr-box"><img src="<?= gerarQrDataUri(SITE_URL . '/modules/os/scan.php?code=' . urlencode($numOpItem), 200) ?>" alt="QR"></div>
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

        <?php if (!empty($materiaisOP)): ?>
        <!-- Matéria-prima / insumos solicitados pelo projetista -->
        <div class="revisao">
            <div class="titulo-sec">Matéria-Prima / Insumos Solicitados</div>
            <table>
                <thead>
                    <tr>
                        <th style="width:5mm"></th>
                        <th>Material</th>
                        <th style="width:14mm">Qtd</th>
                        <th style="width:10mm">Un</th>
                        <th>Observação / Medida (melhor aproveitamento)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($materiaisOP as $iMat => $mat): ?>
                    <tr>
                        <td class="n"><?= $iMat + 1 ?></td>
                        <td style="text-align:left;padding-left:1.5mm"><?= htmlspecialchars($mat['descricao']) ?></td>
                        <td><?= rtrim(rtrim(number_format((float) $mat['quantidade'], 2, ',', '.'), '0'), ',') ?></td>
                        <td><?= htmlspecialchars($mat['unidade']) ?></td>
                        <td style="text-align:left;padding-left:1.5mm"><?= htmlspecialchars($mat['observacao'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

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
            <div class="doc">FR-028 - REV 16<br>Página <?= $idx * 2 + 1 ?> de <?= $totalPaginas ?></div>
        </div>
    </div>

    <?php
    // ===== VERSO: Folha Técnica do Produto =====
    $folha = $folhaPorProduto[(int) ($item['produto_id'] ?? 0)] ?? null;
    $ftLinhaNome = mb_strtoupper(trim((string) ($folha['linha_nome'] ?? '')), 'UTF-8') ?: 'PRODUTO';
    $ftTipo = trim((string) ($folha['tipo_produto'] ?? ''));
    $ftCodigo = trim((string) ($folha['codigo'] ?? ($item['produto_codigo'] ?? ''))) ?: '-';
    $ftCaracs = $linhasTexto($folha['caracteristicas'] ?? '');
    $ftCodLeg = $linhasTexto($folha['codificacao_legenda'] ?? '');
    $ftOpcoes = $linhasTexto($folha['opcoes_folha'] ?? '');
    $ftObs = $linhasTexto(($folha['observacoes_folha'] ?? '') ?: $obsFolhaPadrao);
    $ftGarantia = trim((string) (($folha['garantia_folha'] ?? '') ?: $garantiaFolhaPadrao));
    $ftPerspectiva = trim((string) ($folha['perspectiva'] ?? ''));
    $ftMedidas = ['A' => $folha['medida_a'] ?? '', 'B' => $folha['medida_b'] ?? '', 'C' => $folha['medida_c'] ?? '', 'D' => $folha['medida_d'] ?? ''];
    ?>
    <div class="op-page ft-page">
        <div class="ft-lateral">A Cozinca reserva-se o direito de alterar as características técnicas e estéticas de seus produtos sem aviso prévio</div>
        <div class="ft-head">
            <img src="<?= SITE_URL ?>/assets/img/logo_cozinca_op.png" alt="Cozinca Inox">
            <div class="tit">
                <div class="linha-nome"><?= htmlspecialchars($ftLinhaNome) ?></div>
                <div class="tipo"><?= htmlspecialchars($ftTipo ?: 'TIPO DE PRODUTO') ?></div>
            </div>
        </div>
        <div class="ft-body">
            <div class="ft-col-esq">
                <div class="ft-persp">
                    <?php if ($desenhoOsImg !== ''): ?>
                        <img src="<?= $desenhoOsImg ?>" alt="Desenho técnico">
                        <span class="rotulo">DESENHO TÉCNICO</span>
                    <?php elseif ($ftPerspectiva !== ''): ?>
                        <img src="<?= SITE_URL ?>/assets/uploads/produtos/<?= htmlspecialchars($ftPerspectiva) ?>" alt="Perspectiva">
                        <span class="rotulo">PERSPECTIVA</span>
                    <?php else: ?>
                        <span style="color:#bbb;font-size:11px;letter-spacing:2px">‹ DESENHO TÉCNICO / PERSPECTIVA ›</span>
                        <span class="rotulo">DESENHO TÉCNICO</span>
                    <?php endif; ?>
                </div>
                <div class="ft-sec ft-cod">
                    <div class="barra">Codificação:</div>
                    <div class="conteudo">
                        <div class="codigo-grande"><?= htmlspecialchars($ftCodigo) ?></div>
                        <div class="leg">
                            <?php if (!empty($ftCodLeg)): foreach ($ftCodLeg as $leg): ?>
                                <div>— <?= htmlspecialchars($leg) ?></div>
                            <?php endforeach; else: for ($l = 0; $l < 4; $l++): ?>
                                <div style="border-bottom:1px solid #ccc;height:4mm"></div>
                            <?php endfor; endif; ?>
                        </div>
                    </div>
                </div>
                <div class="ft-sec">
                    <div class="barra">Opções:</div>
                    <div class="conteudo">
                        <?php if (!empty($ftOpcoes)): ?>
                            <ul class="ft-li"><?php foreach ($ftOpcoes as $op): ?><li><?= htmlspecialchars($op) ?></li><?php endforeach; ?></ul>
                        <?php else: for ($l = 0; $l < 3; $l++): ?>
                            <div style="border-bottom:1px solid #ccc;height:5mm"></div>
                        <?php endfor; endif; ?>
                    </div>
                </div>
            </div>
            <div class="ft-col-dir">
                <div class="ft-campos">
                    <div class="cl"><span class="l">Qtd. :</span><span class="v"><?= htmlspecialchars((string) ($item['quantidade'] ?? '')) ?></span></div>
                    <div class="cl"><span class="l">Item :</span><span class="v"><?= $numItem ?></span></div>
                    <div class="cl"><span class="l">Pedido :</span><span class="v"><?= htmlspecialchars((string) ($os['venda_numero'] ?? '')) ?></span></div>
                    <div class="cl"><span class="l">Cliente :</span><span class="v"><?= htmlspecialchars((string) ($os['razao_social'] ?? '')) ?></span></div>
                </div>
                <div class="ft-sec ft-desc">
                    <div class="barra">Descrição:</div>
                    <div class="conteudo"><?= htmlspecialchars($ftCodigo) ?></div>
                </div>
                <div class="ft-sec ft-med">
                    <div class="barra">Medidas :</div>
                    <div class="conteudo">
                        <?php foreach ($ftMedidas as $rotMed => $valMed): ?>
                            <div class="m"><span class="rot"><?= $rotMed ?> =</span><span class="cx"><?= htmlspecialchars((string) $valMed) ?></span><span class="un">mm</span></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="ft-sec">
                    <div class="barra">Características :</div>
                    <div class="conteudo">
                        <ul class="ft-li">
                            <?php if (!empty($ftCaracs)): foreach ($ftCaracs as $c): ?>
                                <li><?= htmlspecialchars(rtrim($c, ';')) ?>;</li>
                            <?php endforeach; else: ?>
                                <li>Construção em aço inox;</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                <div class="ft-sec">
                    <div class="barra">Observações :</div>
                    <div class="conteudo">
                        <ul class="ft-li"><?php foreach ($ftObs as $ob): ?><li><?= htmlspecialchars(rtrim($ob, ';')) ?>;</li><?php endforeach; ?></ul>
                    </div>
                </div>
                <div class="ft-sec">
                    <div class="barra">Garantia :</div>
                    <div class="conteudo ft-gar"><?= htmlspecialchars($ftGarantia) ?></div>
                </div>
            </div>
        </div>
        <div class="ft-rodape">
            <div><strong>COZINCA INOX</strong> — Brasil</div>
            <div>O.P. <?= htmlspecialchars($numOpItem) ?> · Página <?= $idx * 2 + 2 ?> de <?= $totalPaginas ?></div>
        </div>
    </div>
    <?php endforeach; ?>

    <script>
        // Barcode/QR já vêm prontos do servidor — só espera imagens e imprime.
        window.addEventListener('load', function () {
            setTimeout(function () { try { window.print(); } catch (e) {} }, 400);
        });
    </script>
</body>
</html>
