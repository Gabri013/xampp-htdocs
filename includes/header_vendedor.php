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
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/nomus-theme.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/nomus-dashboards.css?v=<?php echo @filemtime(BASE_PATH . '/assets/css/nomus-dashboards.css') ?: '1'; ?>">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/nomus-utilities.css?v=<?php echo @filemtime(BASE_PATH . '/assets/css/nomus-utilities.css') ?: '1'; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<?php if (function_exists('isImpersonating') && isImpersonating()): ?>
<div style="background:#b45309;color:#fff;padding:8px 16px;display:flex;align-items:center;justify-content:center;gap:12px;flex-wrap:wrap;font-size:13px;font-weight:600">
    <span><i class="fas fa-user-secret"></i> Você está acessando como <strong><?= htmlspecialchars($_SESSION['usuario_nome'] ?? '') ?></strong> (<?= htmlspecialchars(getTipoUsuarioNome($_SESSION['usuario_tipo'] ?? '')) ?>) — visão do usuário para teste.</span>
    <a href="<?= SITE_URL ?>/modules/auth/impersonar.php?acao=sair" style="background:#fff;color:#b45309;padding:4px 12px;border-radius:6px;text-decoration:none;font-weight:700"><i class="fas fa-arrow-left"></i> Voltar para minha conta</a>
</div>
<?php endif; ?>
<div id="czSidebarBackdrop" class="cz-sidebar-backdrop"></div>
<div class="cz-topbar" style="position:sticky;top:0;left:0;right:0;height:60px;background:#fff;border-bottom:1px solid #e9ecef;z-index:100;display:flex;align-items:center;padding:0 20px;gap:12px">
    <button id="czMobileMenuBtn" class="cz-mobile-menu-btn" type="button" aria-label="Abrir menu">
        <i class="fas fa-bars"></i>
    </button>
    <div style="flex:1"></div>
    <?php
    // Ponto (expediente): quem opera etapas de produção precisa de expediente
    // aberto para apontar — o chip mostra o estado e permite bater o ponto.
    $tiposComPonto = ['master', 'gerente', 'producao', 'projetista', 'engenharia', 'programacao', 'corte', 'dobra', 'tubo', 'solda', 'mobiliario', 'coccao', 'refrigeracao', 'acabamento', 'montagem', 'embalagem', 'finalizacao'];
    if (in_array($_SESSION['usuario_tipo'] ?? '', $tiposComPonto, true)):
        require_once BASE_PATH . '/includes/expediente.php';
        try {
            $expChip = getStatusExpedienteHoje(getDB(), (int) ($_SESSION['usuario_id'] ?? 0));
        } catch (Throwable $e) { $expChip = ['status' => 'nao_iniciado']; }
        $expStatus = $expChip['status'] ?? 'nao_iniciado';
    ?>
        <?php if ($expStatus === 'em_trabalho'): ?>
            <button type="button" class="vbtn-sm" onclick="baterPonto('encerrar')" title="Expediente aberto desde <?php echo !empty($expChip['expediente']['iniciado_em']) ? date('H:i', strtotime($expChip['expediente']['iniciado_em'])) : ''; ?> — clique para encerrar" style="background:#e7f6ec;color:#16a34a;border:1px solid #b7e4c7"><i class="fas fa-user-clock"></i> Expediente aberto</button>
        <?php elseif ($expStatus === 'encerrado'): ?>
            <button type="button" class="vbtn-sm" onclick="baterPonto('iniciar')" title="Expediente encerrado — clique para reabrir (a pausa não conta como tempo trabalhado)" style="background:#f1f5f9;color:#475569;border:1px solid #cbd5e1"><i class="fas fa-user-clock"></i> Reabrir expediente</button>
        <?php else: ?>
            <button type="button" class="vbtn-sm" onclick="baterPonto('iniciar')" title="Você precisa iniciar o expediente para apontar produção" style="background:#fef3c7;color:#b45309;border:1px solid #fde68a"><i class="fas fa-user-clock"></i> Iniciar expediente</button>
        <?php endif; ?>
        <script>
        async function baterPonto(acao) {
            if (acao === 'encerrar' && !confirm('Encerrar seu expediente de hoje?')) return;
            const fd = new FormData();
            fd.append('acao', acao);
            const r = await fetch('<?php echo SITE_URL; ?>/api/expediente.php', {method: 'POST', body: fd});
            const d = await r.json();
            if (d.success) location.reload(); else alert(d.error || d.message || 'Erro ao registrar o ponto.');
        }
        </script>
    <?php endif; ?>
    <?php if ($qtd_notificacoes_nao_lidas > 0): ?>
        <a href="<?php echo SITE_URL; ?>/modules/notificacoes/index.php" class="vbtn-sm vbtn-brand"><i class="fas fa-bell"></i> <?php echo $qtd_notificacoes_nao_lidas; ?></a>
    <?php endif; ?>
    <span class="vend-user-pill"><span class="vend-user-avatar"><?php echo strtoupper(substr($usuario['nome'] ?? 'U', 0, 1)); ?></span> <?php echo htmlspecialchars($usuario['nome'] ?? ''); ?></span>
</div>
