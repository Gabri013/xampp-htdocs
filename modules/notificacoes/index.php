<?php
require_once '../../config/config.php';
requireLogin();

$page_title = 'Notificações';
$db = getDB();
ensureNotificacoesSchema($db);

$usuario = getCurrentUser();
$GLOBALS['modulo_tipo'] = in_array($usuario['tipo'] ?? '', ['projetista', 'producao'], true) ? $usuario['tipo'] : 'vendedor';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'marcar_lida') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE notificacoes SET lida = 1 WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$id, $usuario['id']]);
        }
    }

    if ($acao === 'marcar_todas_lidas') {
        $stmt = $db->prepare("UPDATE notificacoes SET lida = 1 WHERE usuario_id = ? AND lida = 0");
        $stmt->execute([$usuario['id']]);
    }

    if ($acao === 'processar_agora' && in_array($usuario['tipo'], ['master', 'gerente'], true)) {
        processarMotorNotificacoes($db);
        setSuccess('Motor de notificações processado.');
    }

    header('Location: index.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM notificacoes WHERE usuario_id = ? ORDER BY id DESC LIMIT 200");
$stmt->execute([$usuario['id']]);
$notificacoes = $stmt->fetchAll();

include '../../includes/header_vendedor.php';
?>

<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-page-head">
            <div><h1 class="vend-page-title">Central de Notificações</h1></div>
            <div>
                <?php if (in_array($usuario['tipo'], ['master', 'gerente'], true)): ?>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="acao" value="processar_agora">
                        <button type="submit" class="vbtn-sm btn-primary"><i class="fas fa-sync"></i> Processar Agora</button>
                    </form>
                <?php endif; ?>
                <form method="POST" style="display:inline;margin-left:6px">
                    <input type="hidden" name="acao" value="marcar_todas_lidas">
                    <button type="submit" class="vbtn-sm"><i class="fas fa-check-double"></i> Marcar todas</button>
                </form>
            </div>
        </div>
        
        <div class="vend-table-wrap">
            <table class="vend-table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Tipo</th>
                        <th>Título</th>
                        <th>Mensagem</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
<?php if (empty($notificacoes)): ?>
                         <tr><td colspan="6" class="vend-empty"><i class="fas fa-bell-slash"></i> Nenhuma notificação encontrada</td></tr>
                     <?php else: foreach ($notificacoes as $n): ?>
                         <tr style="<?php echo (int) $n['lida'] === 0 ? 'background:#FEF0EA;' : ''; ?>">
                             <td>
                                 <?php if ((int) $n['lida'] === 0): ?>
                                     <span class="vbadge vbadge-warn"><i class="fas fa-circle"></i> NOVA</span>
                                 <?php else: ?>
                                     <span class="vbadge vbadge-info"><i class="fas fa-check"></i> LIDA</span>
                                 <?php endif; ?>
                             </td>
                             <td><span class="vbadge vbadge-<?php echo $n['tipo'] === 'venda_aguardando_pagamento' ? 'info' : 'prod'; ?>"><?php echo ucfirst(str_replace('_', ' ', $n['tipo'])); ?></span></td>
                             <td><strong><?php echo htmlspecialchars($n['titulo'] ?: 'Notificação'); ?></strong></td>
                             <td><?php echo htmlspecialchars($n['mensagem']); ?></td>
                             <td><small><?php echo formatDateTime($n['created_at']); ?></small></td>
                             <td>
                                 <?php if ((int) $n['lida'] === 0): ?>
                                     <form method="POST" style="display:inline">
                                         <input type="hidden" name="acao" value="marcar_lida">
                                         <input type="hidden" name="id" value="<?php echo (int) $n['id']; ?>">
                                         <button type="submit" class="vbtn-sm btn-success" title="Marcar como lida"><i class="fas fa-check"></i></button>
                                     </form>
                                 <?php endif; ?>
                             </td>
                         </tr>
                     <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer_vendedor.php'; ?>