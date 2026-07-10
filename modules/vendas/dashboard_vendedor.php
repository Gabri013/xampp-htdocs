<?php
require_once '../../config/config.php';
require_once '../../includes/engenharia.php';
require_once '../../includes/auth.php';
requirePermission(['master', 'vendedor']);

$page_title = 'Dashboard do Vendedor';
$db = getDB();
$usuario = getCurrentUser();
ensureOrdensServicoIndependentesSchema($db);

$periodo = in_array($_GET['periodo'] ?? '', ['hoje', 'semana', 'mes', 'acumulado']) ? $_GET['periodo'] : 'mes';

$filtro_data_sql = match ($periodo) {
    'hoje'      => "AND DATE(v.created_at) = CURDATE()",
    'semana'    => "AND v.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
    'mes'       => "AND v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
    'acumulado' => "",
};

try {
    $stmt = $db->prepare("SELECT COUNT(DISTINCT v.id) AS total_vendas, COALESCE(SUM(v.valor_total), 0) AS total_valor, COUNT(DISTINCT CASE WHEN v.status = 'concluida' THEN v.id END) AS vendas_concluidas, COUNT(DISTINCT os.id) AS total_os, COUNT(DISTINCT CASE WHEN os.status = 'em_producao' THEN os.id END) AS os_producao FROM usuarios u LEFT JOIN vendas v ON v.usuario_id = u.id $filtro_data_sql LEFT JOIN ordens_servico os ON os.venda_id = v.id WHERE u.id = ?");
    $stmt->execute([$usuario['id']]);
    $metricas = $stmt->fetch();
} catch (Exception $e) {
    $metricas = ['total_vendas' => 0, 'total_valor' => 0, 'vendas_concluidas' => 0, 'total_os' => 0, 'os_producao' => 0];
}

try {
    $stmt = $db->prepare("SELECT v.*, c.razao_social FROM vendas v INNER JOIN clientes c ON v.cliente_id = c.id WHERE v.usuario_id = ? ORDER BY v.id DESC LIMIT 5");
    $stmt->execute([$usuario['id']]);
    $vendas_recentes = $stmt->fetchAll();
} catch (Exception $e) { $vendas_recentes = []; }

try {
    $stmt = $db->prepare("SELECT os.*, c.razao_social FROM ordens_servico os INNER JOIN clientes c ON os.cliente_id = c.id LEFT JOIN vendas v ON os.venda_id = v.id WHERE (v.usuario_id = ? OR os.venda_id IS NULL) AND os.status NOT IN ('concluida', 'cancelada') ORDER BY os.data_termino ASC, os.id DESC LIMIT 5");
    $stmt->execute([$usuario['id']]);
    $os_atencao = $stmt->fetchAll();
} catch (Exception $e) { $os_atencao = []; }

include '../../includes/header_vendedor.php';
?>

<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">

    <div class="vend-page-head">
      <div><h1 class="vend-page-title">Dashboard</h1><p class="vend-page-sub">Bem-vindo, <?php echo htmlspecialchars($usuario['nome']); ?></p></div>
    </div>

    <div class="vend-period-tabs">
      <?php foreach (['hoje' => 'Hoje', 'semana' => 'Esta semana', 'mes' => 'Este mês', 'acumulado' => 'Acumulado'] as $k => $lbl): ?>
        <a href="?periodo=<?php echo $k; ?>" class="vend-period-tab <?php echo $periodo === $k ? 'active' : ''; ?>"><?php echo $lbl; ?></a>
      <?php endforeach; ?>
    </div>

    <div class="vend-metrics">
      <div class="vend-metric accent"><div class="vend-metric-label">Vendas realizadas</div><div class="vend-metric-val"><?php echo (int)$metricas['total_vendas']; ?></div><div class="vend-metric-sub">no período</div></div>
      <div class="vend-metric"><div class="vend-metric-label">Valor faturado</div><div class="vend-metric-val"><?php echo formatMoney($metricas['total_valor']); ?></div><div class="vend-metric-sub">acumulado no período</div></div>
      <div class="vend-metric"><div class="vend-metric-label">O.S. em produção</div><div class="vend-metric-val"><?php echo (int)$metricas['os_producao']; ?></div><div class="vend-metric-sub">em andamento agora</div></div>
      <div class="vend-metric"><div class="vend-metric-label">Vendas concluídas</div><div class="vend-metric-val"><?php echo (int)$metricas['vendas_concluidas']; ?></div><div class="vend-metric-sub">finalizadas no período</div></div>
    </div>

    <div class="vend-actions">
      <a href="<?php echo SITE_URL; ?>/modules/orcamentos/index.php" class="vend-action"><div class="vend-action-icon"><i class="fas fa-file-invoice"></i></div><div><div class="vend-action-label">Novo orçamento</div><div class="vend-action-sub">Criar para cliente</div></div></a>
      <a href="nova_venda.php" class="vend-action"><div class="vend-action-icon"><i class="fas fa-shopping-cart"></i></div><div><div class="vend-action-label">Nova venda</div><div class="vend-action-sub">Registrar venda direta</div></div></a>
      <a href="<?php echo SITE_URL; ?>/modules/os/nova_os_independente.php" class="vend-action"><div class="vend-action-icon"><i class="fas fa-clipboard-list"></i></div><div><div class="vend-action-label">Nova O.S.</div><div class="vend-action-sub">Ordem independente</div></div></a>
      <a href="<?php echo SITE_URL; ?>/modules/cadastros/clientes.php" class="vend-action"><div class="vend-action-icon"><i class="fas fa-user-plus"></i></div><div><div class="vend-action-label">Novo cliente</div><div class="vend-action-sub">Cadastrar</div></div></a>
      <a href="<?php echo SITE_URL; ?>/modules/financeiro/faturamento.php" class="vend-action"><div class="vend-action-icon"><i class="fas fa-file-invoice-dollar"></i></div><div><div class="vend-action-label">Faturamento</div><div class="vend-action-sub">Gerar contas a receber</div></div></a>
      <a href="<?php echo SITE_URL; ?>/modules/relatorios/index.php" class="vend-action"><div class="vend-action-icon"><i class="fas fa-chart-bar"></i></div><div><div class="vend-action-label">Meu desempenho</div><div class="vend-action-sub">Ver relatórios</div></div></a>
    </div>

    <div class="vend-two-col">
      <div class="vend-card">
        <div class="vend-card-head"><span class="vend-card-title">Vendas recentes</span><a href="<?php echo SITE_URL; ?>/modules/vendas/index.php" class="vend-card-link">Ver todas →</a></div>
        <?php if (empty($vendas_recentes)): ?>
          <div class="vend-empty">Nenhuma venda registrada no período.</div>
        <?php else: foreach ($vendas_recentes as $v): ?>
          <div class="vend-list-row">
            <span class="vend-list-num"><?php echo htmlspecialchars($v['numero'] ?? '#'); ?></span>
            <div class="vend-list-client"><?php echo htmlspecialchars($v['razao_social']); ?></div>
            <span class="vend-list-val"><?php echo formatMoney($v['valor_total']); ?></span>
            <span class="vbadge <?php echo $v['status'] === 'concluida' ? 'vbadge-ok' : 'vbadge-warn'; ?>"><?php echo $v['status'] === 'concluida' ? 'Concluída' : 'Em andamento'; ?></span>
            <a href="detalhes_venda.php?id=<?php echo $v['id']; ?>" class="vbtn-sm"><i class="fas fa-eye"></i></a>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <div class="vend-card">
        <div class="vend-card-head"><span class="vend-card-title">O.S. que precisam de atenção</span><a href="<?php echo SITE_URL; ?>/modules/os/vendedor.php" class="vend-card-link">Ver todas →</a></div>
        <?php if (empty($os_atencao)): ?>
          <div class="vend-empty">Nenhuma O.S. em aberto.</div>
        <?php else: foreach ($os_atencao as $os):
            $prazo_str = '';
            if (!empty($os['data_termino'])) {
                $hoje = new DateTime(); $prazo = new DateTime($os['data_termino']); $diff = (int) $hoje->diff($prazo)->days; $past = $prazo < $hoje;
                $prazo_str = $past ? '<span style="color:#b71c1c">Atrasada</span>' : ($diff === 0 ? '<span style="color:#e65100">Hoje</span>' : ($diff === 1 ? '<span style="color:#f57f17">Amanhã</span>' : 'em ' . $diff . ' dias'));
            }
            $statusMap = ['em_producao'=>['vbadge-prod','Produção'],'em_revisao'=>['vbadge-rev','Revisão'],'aguardando'=>['vbadge-info','Aguardando'],'default'=>['vbadge-warn',ucfirst($os['status'])]];
            $s = $statusMap[$os['status']] ?? $statusMap['default'];
        ?>
          <div class="vend-list-row">
            <span class="vend-list-num"><?php echo htmlspecialchars($os['numero'] ?? '#'); ?></span>
            <div class="vend-list-client"><?php echo htmlspecialchars($os['razao_social']); ?><?php if ($prazo_str): ?><div class="vend-list-client-sub"><?php echo $prazo_str; ?></div><?php endif; ?></div>
            <span class="vbadge <?php echo $s[0]; ?>"><?php echo $s[1]; ?></span>
            <a href="<?php echo SITE_URL; ?>/modules/os/os_detalhes.php?os_id=<?php echo $os['id']; ?>" class="vbtn-sm"><i class="fas fa-eye"></i></a>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    </div>
</div>

<?php include '../../includes/footer_vendedor.php'; ?>