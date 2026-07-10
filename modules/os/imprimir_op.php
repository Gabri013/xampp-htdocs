<?php
require_once '../../config/config.php';
require_once '../../includes/engenharia.php';

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

$stmt = $db->prepare("\n    SELECT\n        os.id, os.numero, os.venda_id, os.data_inicio, os.data_termino, os.prioridade, os.observacoes_corte_dobra, os.observacoes_solda,\n        c.razao_social,\n        COALESCE(v.numero, 'Independente') AS venda_numero,\n        COALESCE(u.nome, '-') AS vendedor_nome\n    FROM ordens_servico os\n    INNER JOIN clientes c ON c.id = os.cliente_id\n    LEFT JOIN vendas v ON v.id = os.venda_id\n    LEFT JOIN usuarios u ON u.id = v.usuario_id\n    WHERE os.id = ?\n    LIMIT 1\n");
$stmt->execute([$osId]);
$os = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$os) {
    http_response_code(404);
    die('O.S. nao encontrada.');
}

// Buscar OP para o item ou OS
if ($itemId > 0) {
    // Para item específico, usar OP existente ou gerar
    $stmtOp = $db->prepare("
        SELECT numero, id as op_id 
        FROM ordens_producao 
        WHERE os_id = ? 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmtOp->execute([$osId]);
    $op = $stmtOp->fetch(PDO::FETCH_ASSOC);
} else {
    $stmtOp = $db->prepare("SELECT numero, status, criado_em FROM ordens_producao WHERE os_id = ? ORDER BY id DESC LIMIT 1");
    $stmtOp->execute([$osId]);
    $op = $stmtOp->fetch(PDO::FETCH_ASSOC);
}

$numeroOp = $op['numero'] ?? 'OP-' . date('Y') . '-' . str_pad((string)$osId, 5, '0', STR_PAD_LEFT);

// Buscar itens (setem selecionado ou todos)
if ($itemId > 0) {
    $itens = getItensComerciaisOS($db, $osId, (int)($os['venda_id'] ?? 0), $itemId);
} else {
    $itens = getItensComerciaisOS($db, (int)$os['id'], (int)($os['venda_id'] ?? 0));
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Impressao OP <?= htmlspecialchars($numeroOp) ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Libre+Barcode+39&display=swap');
        *{margin:0;padding:0;box-sizing:border-box;font-family:Arial,sans-serif}
        body{background:#f5f5f5;padding:0}
        .op-container{background:#fff;width:210mm;min-height:297mm;margin:0 auto;padding:15mm;border:1px solid #000}
        .header{text-align:center;border-bottom:2px solid #000;padding-bottom:8px;margin-bottom:12px}
        .header h1{font-size:20px;margin:0}
        .top-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px}
        .field{border:1px solid #000;padding:5px;font-size:11px}
        .label{font-size:10px;font-weight:bold;color:#444}
        .value{margin-top:2px;font-size:12px;font-weight:bold}
        .barcode{font-family:'Libre Barcode 39',cursive;font-size:24px;letter-spacing:1px;text-align:center;padding:3px 0;margin-top:5px}
        .qr-code{width:50px;height:50px;margin:3px auto;display:block}
        .product-box{border:1px solid #000;margin-bottom:12px;font-size:10px}
        .product-box table{width:100%;border-collapse:collapse}
        .product-box th,.product-box td{border:1px solid #000;padding:6px;font-size:10px}
        .observacao{border:1px solid #000;padding:8px;margin-bottom:12px;min-height:50px;font-size:10px}
        .processo table{width:100%;border-collapse:collapse;font-size:9px}
        .processo th,.processo td{border:1px solid #000;padding:4px;text-align:center}
        .processo th{background:#efefef;font-size:9px}
        .revisao{margin-top:15px;font-size:9px}
        .revisao table{width:100%;border-collapse:collapse}
        .revisao th,.revisao td{border:1px solid #000;padding:4px;font-size:9px}
        .footer{position:fixed;bottom:5mm;left:15mm;right:15mm;display:flex;justify-content:space-between;font-size:9px}
        .printbar{position:sticky;top:0;background:#111827;color:#fff;padding:8px 12px;display:flex;gap:8px}
        .printbar button{background:#22c55e;border:0;color:#fff;font-weight:700;padding:7px 10px;border-radius:6px;cursor:pointer;font-size:12px}
        @media print{
            .printbar{display:none!important}
            body{background:#fff}
            .op-container{width:100%;min-height:auto;padding:10mm;border:none;margin:0}
        }
    </style>
    <script defer src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
</head>
<body>
    <div class="printbar">
        <button type="button" onclick="window.print()">Imprimir / Salvar PDF</button>
        <a href="javascript:window.close()" style="color:#bfdbfe;align-self:center">Fechar</a>
    </div>

    <div class="op-container">
        <div class="header">
            <h1>ORDEM DE PRODUÇÃO</h1>
        </div>

        <div class="top-grid">
            <div class="field">
                <div class="label">Nº Ordem Produção</div>
                <div class="value"><?= htmlspecialchars($numeroOp) ?></div>
                <svg class="barcode" id="barcode-<?= $osId ?>"></svg>
                <img class="qr-code" id="qrcode-<?= $osId ?>" alt="QR Code">
            </div>
            <div class="field">
                <div class="label">Pedido</div>
                <div class="value"><?= htmlspecialchars($os['venda_numero'] ?? '-') ?></div>
            </div>
            <div class="field">
                <div class="label">Emissão Pedido</div>
                <div class="value"><?= htmlspecialchars(formatDate($os['data_inicio'])) ?></div>
            </div>
            <div class="field">
                <div class="label">Cliente</div>
                <div class="value"><?= htmlspecialchars($os['razao_social']) ?></div>
            </div>
            <div class="field">
                <div class="label">Nº Pedido</div>
                <div class="value"><?= htmlspecialchars($os['numero']) ?></div>
            </div>
            <div class="field">
                <div class="label">Data Emissão</div>
                <div class="value"><?= date('d/m/Y') ?></div>
            </div>
        </div>
        
        <script>
        window.addEventListener('load', function() {
            // Código de barras
            if (typeof JsBarcode !== 'undefined') {
                JsBarcode('#barcode-<?= $osId ?>', '<?= $numeroOp ?>', {format:'CODE128',displayValue:true,fontSize:12,height:40});
            }
            // QR Code usando API externa
            document.getElementById('qrcode-<?= $osId ?>').src = 'https://api.qrserver.com/v1/create-qr-code/?size=60x60&data=' + encodeURIComponent('<?= $numeroOp ?>');
        });
        </script>

        <div class="product-box">
            <table>
                <thead>
                    <tr>
                        <th>Código Produto</th>
                        <th>Descrição</th>
                        <th>Prazo</th>
                        <th>Qtde</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($itens)): ?>
                        <tr><td colspan="4">Nenhum item registrado nesta O.S.</td></tr>
                    <?php else: foreach ($itens as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)($item['produto_codigo'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars((string)($item['descricao_manual'] ?: ($item['produto_nome'] ?? ''))) ?></td>
                            <td><?= htmlspecialchars(formatDate($os['data_termino'])) ?></td>
                            <td><?= htmlspecialchars((string)number_format((float)($item['quantidade'] ?? 0), 0, ',', '.')) ?> UN</td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <div class="observacao">
            <strong>Observação:</strong><br>
            <?= htmlspecialchars((string)($os['observacoes_gerais'] ?? '-')) ?>
        </div>

        <div class="processo">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Processo</th>
                        <th>Início</th>
                        <th>Término</th>
                        <th>Obs.</th>
                        <th>Responsável</th>
                        <th>Líder</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>1</td><td>Programação</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr><td>2</td><td>Corte</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr><td>3</td><td>Mobiliário</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr><td>4</td><td>Cocção</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr><td>5</td><td>Refrigeração</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr><td>6</td><td>Embalagem</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr><td>7</td><td>Engenharia</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr><td>8</td><td>Dobra</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr><td>9</td><td>Tubo</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr><td>10</td><td>Solda</td><td></td><td></td><td></td><td></td><td></td></tr>
                </tbody>
            </table>
        </div>

        <div class="revisao">
            <table>
                <thead>
                    <tr>
                        <th>Revisão</th>
                        <th>Data</th>
                        <th>Novo Prazo</th>
                        <th>Motivo / Justificativa</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td></td><td></td><td></td><td></td></tr>
                    <tr><td></td><td></td><td></td><td></td></tr>
                </tbody>
            </table>
        </div>

        <div class="footer">
            <div>FR-028 - REV 16</div>
            <div>Página 1 de 1</div>
        </div>
    </div>
</body>
<script>
        window.addEventListener('load', function() {
            // QR Code usando API externa
            var qrImg = document.getElementById('qrcode-<?= $osId ?>');
            if (qrImg) {
                qrImg.src = 'https://api.qrserver.com/v1/create-qr-code/?size=60x60&data=' + encodeURIComponent('<?= $numeroOp ?>');
            }
            // Auto print após carregar QR
            setTimeout(function(){try{window.print();}catch(e){}}, 800);
        });
    </script>
</body>
</html>

