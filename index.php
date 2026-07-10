<?php
require_once 'config/config.php';
requireLogin();

$page_title = 'Dashboard';

$db = getDB();
$usuario = getCurrentUser();
$tipo_usuario = $usuario['tipo'];

// Para vendedores e projetistas, redirecionar para dashboards específicos
if ($tipo_usuario === 'vendedor') {
    header('Location: modules/vendas/dashboard_vendedor.php');
    exit;
}
if ($tipo_usuario === 'projetista') {
    header('Location: modules/projetista/index.php');
    exit;
}

ensureNotificacoesSchema($db);

// Estatísticas para master/gerente
$stats = [];
$stmt = $db->query("SELECT COUNT(*) as total FROM clientes");
$stats['clientes'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM produtos WHERE status = 'ativo'");
$stats['produtos'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total, SUM(valor_total) as valor FROM vendas WHERE MONTH(data_venda) = MONTH(CURDATE()) AND YEAR(data_venda) = YEAR(CURDATE())");
$vendas_mes = $stmt->fetch();
$stats['vendas_mes'] = $vendas_mes['total'];
$stats['valor_vendas_mes'] = $vendas_mes['valor'] ?? 0;

$stmt = $db->query("SELECT COUNT(*) as total FROM ordens_servico WHERE status IN ('pendente', 'em_projeto', 'em_revisao', 'em_producao')");
$stats['os_andamento'] = $stmt->fetch()['total'];

include 'includes/header_vendedor.php';
?>

<div class="vend-layout">
    <?php include 'includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-metrics">
            <div class="vend-metric">
                <div class="vend-metric-label">Clientes</div>
                <div class="vend-metric-val"><?php echo $stats['clientes']; ?></div>
            </div>
            <div class="vend-metric">
                <div class="vend-metric-label">Produtos</div>
                <div class="vend-metric-val"><?php echo $stats['produtos']; ?></div>
            </div>
            <div class="vend-metric">
                <div class="vend-metric-label">Vendas (Mês)</div>
                <div class="vend-metric-val"><?php echo $stats['vendas_mes']; ?></div>
                <div class="vend-metric-sub"><?php echo formatMoney($stats['valor_vendas_mes']); ?></div>
            </div>
            <div class="vend-metric">
                <div class="vend-metric-label">O.S. em Produção</div>
                <div class="vend-metric-val"><?php echo $stats['os_andamento']; ?></div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer_vendedor.php'; ?>
