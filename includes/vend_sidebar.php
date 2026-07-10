<?php
// includes/vend_sidebar.php
// Sidebar reutilizável para todos os módulos do ERP

$tipo_usuario = $_SESSION['usuario_tipo'] ?? 'vendedor';
$mostrar_projetista = in_array($tipo_usuario, ['master', 'projetista']);
$mostrar_dashboard = in_array($tipo_usuario, ['master', 'vendedor']);
$mostrar_vendas = in_array($tipo_usuario, ['master', 'vendedor']);
$mostrar_orcamentos = in_array($tipo_usuario, ['master', 'vendedor']);
$mostrar_os = in_array($tipo_usuario, ['master', 'vendedor', 'projetista', 'gerente']);
$mostrar_cadastros = in_array($tipo_usuario, ['master', 'vendedor']);
$mostrar_financeiro = in_array($tipo_usuario, ['master', 'vendedor']);
$mostrar_relatorios = in_array($tipo_usuario, ['master', 'vendedor']);

$usuario = getCurrentUser();
$qtd_notificacoes_nao_lidas = $GLOBALS['qtd_notificacoes_nao_lidas'] ?? 0;

if ($usuario && !empty($usuario['id'])) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND lida = 0");
        $stmt->execute([$usuario['id']]);
        $qtd_notificacoes_nao_lidas = (int) $stmt->fetchColumn();
    } catch (Exception $e) {}
}

$modulo_tipo = $GLOBALS['modulo_tipo'] ?? '';
$current_path = $_SERVER['PHP_SELF'] ?? '';

$current_module = '';
if (strpos($current_path, '/projetista/') !== false) $current_module = 'projetista';
elseif (strpos($current_path, '/vendas/') !== false && strpos($current_path, '/dashboard') !== false) $current_module = 'dashboard';
elseif (strpos($current_path, '/vendas/') !== false) $current_module = 'vendas';
elseif (strpos($current_path, '/orcamentos/') !== false) $current_module = 'orcamentos';
elseif (strpos($current_path, '/os/') !== false && basename($current_path) === 'vendedor.php') $current_module = 'os';
elseif (strpos($current_path, '/os/') !== false) $current_module = 'os_sector';
elseif (strpos($current_path, '/cadastros/') !== false) $current_module = 'cadastros';
elseif (strpos($current_path, '/financeiro/') !== false) $current_module = 'financeiro';
elseif (strpos($current_path, '/relatorios/') !== false) $current_module = 'relatorios';
elseif (strpos($current_path, '/notificacoes/') !== false) $current_module = 'notificacoes';
elseif (basename($current_path) === 'index.php' && dirname($current_path) === '/') $current_module = 'dashboard';

$dashboard_active = ($current_module === 'dashboard');

$logo_sub = 'Módulo vendedor';
if ($modulo_tipo === 'projetista') $logo_sub = 'Projetista';
elseif ($modulo_tipo === 'producao') $logo_sub = 'Produção';
?>
<aside class="vend-sidebar" id="czSidebar">
    <button class="vend-sidebar-toggle" id="czSidebarToggle" title="Colapsar/Expandir" style="position:absolute;top:10px;right:-12px;background:#fff;border:1px solid #e9ecef;border-radius:50%;width:24px;height:24px;display:flex;align-items:center;justify-content:center;cursor:pointer"><i class="fas fa-chevron-left" style="font-size:10px"></i></button>
    <div class="vend-sidebar-logo">
        <div class="vend-logo-icon"><i class="fas fa-fire"></i></div>
        <div>
            <div class="vend-logo-text">Cozinca Inox</div>
            <div class="vend-logo-sub"><?php echo $logo_sub; ?></div>
        </div>
    </div>

    <div class="vend-nav-group">
        <?php if ($mostrar_dashboard && $tipo_usuario !== 'projetista'): ?>
        <span class="vend-nav-label">Principal</span>
        <a href="<?php echo SITE_URL; ?>/modules/vendas/dashboard_vendedor.php" class="vend-nav-item <?php echo $dashboard_active ? 'active' : ''; ?>"><i class="fas fa-th-large"></i> Dashboard</a>
        <?php endif; ?>
        <?php if ($mostrar_projetista): ?>
        <a href="<?php echo SITE_URL; ?>/modules/projetista/index.php" class="vend-nav-item <?php echo ($current_module === 'projetista') ? 'active' : ''; ?>"><i class="fas fa-drafting-compass"></i> Projetista</a>
        <?php endif; ?>
        <?php if ($mostrar_os): ?>
        <a href="<?php echo SITE_URL; ?>/modules/os/vendedor.php" class="vend-nav-item <?php echo ($current_module === 'os') ? 'active' : ''; ?>"><i class="fas fa-clipboard-list"></i> O.S.</a>
        <?php endif; ?>
    </div>

    <hr class="vend-nav-divider">
    <div class="vend-nav-group">
        <span class="vend-nav-label">Setores</span>
        <?php if (in_array($tipo_usuario, ['master', 'gerente', 'producao', 'corte', 'projetista'])): ?>
        <a href="<?php echo SITE_URL; ?>/modules/os/corte.php" class="vend-nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'corte.php' ? 'active' : ''; ?>"><i class="fas fa-cut"></i> Corte</a>
        <?php endif; ?>
        <?php if (in_array($tipo_usuario, ['master', 'gerente', 'producao', 'dobra', 'projetista'])): ?>
        <a href="<?php echo SITE_URL; ?>/modules/os/dobra.php" class="vend-nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dobra.php' ? 'active' : ''; ?>"><i class="fas fa-dharmachakra"></i> Dobra</a>
        <?php endif; ?>
        <?php if (in_array($tipo_usuario, ['master', 'gerente', 'producao', 'solda', 'projetista'])): ?>
        <a href="<?php echo SITE_URL; ?>/modules/os/solda.php" class="vend-nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'solda.php' ? 'active' : ''; ?>"><i class="fas fa-fire"></i> Solda</a>
        <?php endif; ?>
        <?php if (in_array($tipo_usuario, ['master', 'gerente', 'producao', 'montagem', 'projetista'])): ?>
        <a href="<?php echo SITE_URL; ?>/modules/os/montagem.php" class="vend-nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'montagem.php' ? 'active' : ''; ?>"><i class="fas fa-tools"></i> Montagem</a>
        <?php endif; ?>
        <?php if (in_array($tipo_usuario, ['master', 'gerente', 'producao', 'acabamento', 'projetista'])): ?>
        <a href="<?php echo SITE_URL; ?>/modules/os/acabamento.php" class="vend-nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'acabamento.php' ? 'active' : ''; ?>"><i class="fas fa-paint-roller"></i> Acabamento</a>
        <?php endif; ?>
        <?php if (in_array($tipo_usuario, ['master', 'gerente', 'producao', 'refrigeracao', 'projetista'])): ?>
        <a href="<?php echo SITE_URL; ?>/modules/os/refrigeracao.php" class="vend-nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'refrigeracao.php' ? 'active' : ''; ?>"><i class="fas fa-snowflake"></i> Refrigeracao</a>
        <?php endif; ?>
    </div>

    <hr class="vend-nav-divider">
    <div class="vend-nav-group">
        <span class="vend-nav-label">Ações</span>
        <?php if ($mostrar_os): ?>
        <a href="<?php echo SITE_URL; ?>/modules/os/nova_os_independente.php" class="vend-nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'nova_os_independente.php' ? 'active' : ''; ?>"><i class="fas fa-plus-square"></i> Lançar O.S.</a>
        <?php endif; ?>
    </div>

    <?php if ($mostrar_cadastros): ?>
    <hr class="vend-nav-divider">
    <div class="vend-nav-group">
        <span class="vend-nav-label">Cadastros</span>
        <a href="<?php echo SITE_URL; ?>/modules/cadastros/clientes.php" class="vend-nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'clientes.php' ? 'active' : ''; ?>"><i class="fas fa-users"></i> Clientes</a>
        <a href="<?php echo SITE_URL; ?>/modules/cadastros/produtos.php" class="vend-nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'produtos.php' ? 'active' : ''; ?>"><i class="fas fa-box"></i> Produtos</a>
    </div>
    <?php endif; ?>

    <?php if ($mostrar_financeiro || $mostrar_relatorios): ?>
    <hr class="vend-nav-divider">
    <div class="vend-nav-group">
        <span class="vend-nav-label">Financeiro</span>
        <?php if ($mostrar_financeiro): ?>
        <a href="<?php echo SITE_URL; ?>/modules/financeiro/faturamento.php" class="vend-nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'faturamento.php' ? 'active' : ''; ?>"><i class="fas fa-file-invoice-dollar"></i> Faturamento</a>
        <?php endif; ?>
        <?php if ($mostrar_relatorios): ?>
        <a href="<?php echo SITE_URL; ?>/modules/relatorios/index.php" class="vend-nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php' && strpos($_SERVER['PHP_SELF'], 'relatorios') !== false) ? 'active' : ''; ?>"><i class="fas fa-chart-bar"></i> Relatórios</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <hr class="vend-nav-divider">

    <div class="vend-nav-group">
        <span class="vend-nav-label">Alertas</span>
        <a href="<?php echo SITE_URL; ?>/modules/notificacoes/index.php" class="vend-nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php' && strpos($_SERVER['PHP_SELF'], 'notificacoes') !== false) ? 'active' : ''; ?>">
            <i class="fas fa-bell"></i> Notificações
            <?php if ($qtd_notificacoes_nao_lidas > 0): ?>
                <span class="vend-nav-badge"><?php echo $qtd_notificacoes_nao_lidas; ?></span>
            <?php endif; ?>
        </a>
    </div>

    <hr class="vend-nav-divider">

    <div class="vend-nav-group">
        <a href="<?php echo SITE_URL; ?>/logout.php" class="vend-nav-item"><i class="fas fa-sign-out-alt"></i> Sair</a>
    </div>
</aside>