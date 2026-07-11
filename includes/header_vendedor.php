<?php
if (!defined('BASE_PATH')) {
    die('Acesso negado');
}

require_once BASE_PATH . '/includes/expediente.php';

$usuario = getCurrentUser();
$qtd_notificacoes_nao_lidas = 0;

if ($usuario && !empty($usuario['id'])) {
    $db = getDB();
    
    // Contar orçamentos abertos
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM orcamentos WHERE (status = 'pendente' OR status IS NULL) AND (validade IS NULL OR validade >= CURDATE())");
        $orcamentos_abertos = (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        $orcamentos_abertos = 0;
    }
    
    // Contar OS em andamento
    try {
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT os.id) 
            FROM ordens_servico os
            JOIN vendas v ON os.venda_id = v.id
            WHERE v.usuario_id = ? AND os.status = 'em_producao'
        ");
        $stmt->execute([$usuario['id']]);
        $os_abertas = (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        $os_abertas = 0;
    }
    
    // Contar notificações não lidas
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND lida = 0");
        $stmt->execute([$usuario['id']]);
        $qtd_notificacoes_nao_lidas = (int) $stmt->fetchColumn();
    } catch (Exception $e) {}
} else {
    $orcamentos_abertos = 0;
    $os_abertas = 0;
}

$css_version = @filemtime(BASE_PATH . '/assets/css/style.css') ?: '1';

// Inicializar variáveis se não definidas
if (!isset($orcamentos_abertos)) $orcamentos_abertos = 0;
if (!isset($os_abertas)) $os_abertas = 0;
if (!isset($qtd_notificacoes_nao_lidas)) $qtd_notificacoes_nao_lidas = 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#D85A30">
    <title><?php echo $page_title ?? SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css?v=<?php echo $css_version; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div id="czSidebarBackdrop" class="cz-sidebar-backdrop"></div>
<div class="cz-topbar" style="position:sticky;top:0;left:0;right:0;height:60px;background:#fff;border-bottom:1px solid #e9ecef;z-index:100;display:flex;align-items:center;padding:0 20px;gap:12px">
    <button id="czMobileMenuBtn" class="cz-mobile-menu-btn" type="button" aria-label="Abrir menu">
        <i class="fas fa-bars"></i>
    </button>
    <div style="flex:1"></div>
    <?php if ($qtd_notificacoes_nao_lidas > 0): ?>
        <a href="<?php echo SITE_URL; ?>/modules/notificacoes/index.php" class="vbtn-sm" style="border-color:#D85A30;color:#D85A30"><i class="fas fa-bell"></i> <?php echo $qtd_notificacoes_nao_lidas; ?></a>
    <?php endif; ?>
    <span class="vend-user-pill"><span class="vend-user-avatar"><?php echo strtoupper(substr($usuario['nome'] ?? 'U', 0, 1)); ?></span> <?php echo htmlspecialchars($usuario['nome'] ?? ''); ?></span>
</div>
