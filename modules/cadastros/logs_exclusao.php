<?php
require_once '../../config/config.php';
requirePermission(['master']);

$page_title = 'Logs de Exclusão de Vendas';

$db = getDB();
$sql = "SELECT l.*, u.nome as usuario_nome 
        FROM logs_exclusao_vendas l 
        INNER JOIN usuarios u ON l.usuario_id = u.id 
        ORDER BY l.created_at DESC";

$logs = $db->query($sql)->fetchAll();

include '../../includes/header_vendedor.php';
?>

<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main"><div class="vend-content">

<div class="card">
    <div class="card-header">
        <h3>Logs de Exclusão de Vendas</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Nº Venda</th>
                        <th>Usuário</th>
                        <th>Motivo</th>
                        <th>Dados da Venda</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="5" class="text-center">Nenhum log encontrado</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($log['venda_numero']); ?></strong></td>
                                <td><?php echo htmlspecialchars($log['usuario_nome']); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($log['motivo'])); ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-secondary btn-ver-dados" data-json='<?php echo htmlspecialchars($log['venda_dados_json'], ENT_QUOTES, 'UTF-8'); ?>'>
                                        <i class="fas fa-search"></i> Ver Dados
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Dados -->
<div id="modalDados" class="modal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3>Dados da Venda Excluída</h3>
            <button type="button" class="close" id="btn_fechar_dados">&times;</button>
        </div>
        <div class="modal-body">
            <pre id="json_display" style="background: #f8f9fa; padding: 15px; border-radius: 5px; overflow: auto; max-height: 400px; font-size: 12px;"></pre>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="btn_fechar_modal_dados">Fechar</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('modalDados');
    const display = document.getElementById('json_display');
    
    document.querySelectorAll('.btn-ver-dados').forEach(btn => {
        btn.onclick = function() {
            const data = JSON.parse(this.dataset.json);
            display.textContent = JSON.stringify(data, null, 4);
            modal.classList.add('show');
        };
    });

    const fechar = () => modal.classList.remove('show');
    document.getElementById('btn_fechar_dados').onclick = fechar;
    document.getElementById('btn_fechar_modal_dados').onclick = fechar;
});
</script>

    </div></div>
</div>
<?php include '../../includes/footer_vendedor.php'; ?>
