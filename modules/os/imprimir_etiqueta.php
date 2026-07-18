<?php
require_once '../../config/config.php';
require_once '../../includes/engenharia.php';

requirePermission(['master', 'gerente', 'producao', 'finalizacao']);
$db = getDB();
ensureEngenhariaSchema($db);

$os_id = isset($_GET['os_id']) ? (int) $_GET['os_id'] : 0;
if ($os_id <= 0) {
    die('O.S inválida.');
}

$stmt = $db->prepare("
    SELECT
        os.id,
        os.numero,
        os.venda_id,
        os.data_inicio,
        c.razao_social,
        qc.responsavel_qc
    FROM ordens_servico os
    INNER JOIN clientes c ON c.id = os.cliente_id
    LEFT JOIN qualidade_checklist qc ON qc.os_id = os.id
    WHERE os.id = ?
    ORDER BY qc.id DESC
    LIMIT 1
");
$stmt->execute([$os_id]);
$os = $stmt->fetch();

if (!$os) {
    die('O.S não encontrada.');
}

$itens_os = getItensComerciaisOS($db, $os_id, (int) ($os['venda_id'] ?? 0));
$produto = $itens_os[0] ?? null;
$produto_nome = '-';
if ($produto) {
    $produto_nome = $produto['produto_nome'] ?: $produto['descricao_manual'];
}

$data_producao = formatDate($os['data_inicio']);
$responsavel = $os['responsavel_qc'] ?: ($_SESSION['usuario_nome'] ?? '-');
$numero_limpo = preg_replace('/[^0-9]/', '', $os['numero']);
$url_rastreio = 'https://sistema.cozinca.com/os/' . ($numero_limpo !== '' ? $numero_limpo : $os['numero']);
$qr_url = gerarQrDataUri($url_rastreio, 120);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Etiqueta <?php echo htmlspecialchars($os['numero']); ?></title>
    <style>
        @page { size: 100mm 70mm; margin: 3mm; }
        body { font-family: Arial, sans-serif; margin: 0; }
        .label { border: 2px solid #000; padding: 8px; display: grid; grid-template-columns: 1fr 120px; gap: 10px; align-items: center; }
        .brand { font-size: 22px; font-weight: 800; margin-bottom: 8px; }
        .line { font-size: 14px; margin: 2px 0; }
        .qr-box { text-align: center; font-size: 10px; }
        .qr-box img { width: 120px; height: 120px; display: block; margin: 0 auto 4px; }
    </style>
</head>
<body>
    <div class="label">
        <div>
            <div class="brand">COZINCA INOX</div>
            <div class="line"><strong>OS:</strong> <?php echo htmlspecialchars($os['numero']); ?></div>
            <div class="line"><strong>Cliente:</strong> <?php echo htmlspecialchars($os['razao_social']); ?></div>
            <div class="line"><strong>Produto:</strong> <?php echo htmlspecialchars($produto_nome); ?></div>
            <div class="line"><strong>Data produção:</strong> <?php echo htmlspecialchars($data_producao); ?></div>
            <div class="line"><strong>Responsável QC:</strong> <?php echo htmlspecialchars($responsavel); ?></div>
        </div>
        <div class="qr-box">
            <img src="<?php echo htmlspecialchars($qr_url); ?>" alt="QR Code">
            <?php echo htmlspecialchars($url_rastreio); ?>
        </div>
    </div>
    <script>
        window.onload = function () { window.print(); };
    </script>
</body>
</html>

