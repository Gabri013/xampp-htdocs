<?php
require_once '../../config/config.php';

requirePermission(['master', 'gerente', 'finalizacao', 'vendedor']);
$db = getDB();
$page_title = 'Finalizacao - Controle de Qualidade';

function ensureQualidadeSchema(PDO $db) {
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;

    if (!shouldRunSchemaSync('qualidade', 86400)) {
        return;
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS qualidade_checklist (
            id INT AUTO_INCREMENT PRIMARY KEY,
            os_id INT NOT NULL,
            usuario_id INT NOT NULL,
            responsavel_qc VARCHAR(120) NOT NULL,
            data_check DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            aprovado TINYINT(1) NOT NULL DEFAULT 0,
            observacoes TEXT NULL,
            motivo_reprovacao TEXT NULL,
            setor_retorno ENUM('solda', 'acabamento', 'montagem') NULL,
            INDEX idx_qc_os (os_id),
            INDEX idx_qc_data (data_check),
            CONSTRAINT fk_qc_os FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE,
            CONSTRAINT fk_qc_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS qualidade_itens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            checklist_id INT NOT NULL,
            item VARCHAR(120) NOT NULL,
            status ENUM('ok', 'erro') NOT NULL DEFAULT 'ok',
            INDEX idx_qci_checklist (checklist_id),
            CONSTRAINT fk_qci_checklist FOREIGN KEY (checklist_id) REFERENCES qualidade_checklist(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");

    $colunas = $db->query("SHOW COLUMNS FROM ordens_servico")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('qualidade_status', $colunas, true)) {
        $db->exec("ALTER TABLE ordens_servico ADD COLUMN qualidade_status ENUM('pendente','aprovada','reprovada') DEFAULT 'pendente' AFTER etapa_atual");
    }
    if (!in_array('qualidade_usuario_id', $colunas, true)) {
        $db->exec("ALTER TABLE ordens_servico ADD COLUMN qualidade_usuario_id INT NULL AFTER qualidade_status");
    }
    if (!in_array('qualidade_data', $colunas, true)) {
        $db->exec("ALTER TABLE ordens_servico ADD COLUMN qualidade_data DATETIME NULL AFTER qualidade_usuario_id");
    }
    if (!in_array('status_producao', $colunas, true)) {
        $db->exec("ALTER TABLE ordens_servico ADD COLUMN status_producao TINYINT NOT NULL DEFAULT 2 COMMENT '1-criada,2-em producao,3-aguardando qualidade,4-qualidade aprovada,5-finalizada,6-expedida' AFTER qualidade_status");
    }
}
ensureQualidadeSchema($db);

$stmt = $db->query("
    SELECT
        os.id,
        os.numero,
        os.status,
        os.etapa_atual,
        os.data_inicio,
        os.prioridade,
        os.qualidade_status,
        os.status_producao,
        c.razao_social
    FROM ordens_servico os
    INNER JOIN clientes c ON c.id = os.cliente_id
    WHERE os.etapa_atual = 'finalizacao'
    ORDER BY os.created_at DESC
");
$ordens = $stmt->fetchAll();

function badgeQualidade($status) {
    if ($status === 'aprovada') {
        return '<span class="vbadge-success">Aprovado</span>';
    }
    if ($status === 'reprovada') {
        return '<span class="vbadge-danger">Reprovado</span>';
    }
    return '<span class="vbadge-warning">Aguardando qualidade</span>';
}

include '../../includes/header_vendedor.php';
?>
<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main"><div class="vend-page-head"><h1 class="vend-page-title">Finalizacao - Qualidade</h1></div><div class="vend-content">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="m-0"><i class="fas fa-clipboard-check"></i> Finalizacao - Qualidade</h3>
                <span class="vbadge-secondary"><?php echo count($ordens); ?> O.S</span>
            </div>
            <div class="card-body">
                <?php if (empty($ordens)): ?>
                    <p class="text-muted mb-0">Nenhuma O.S disponível para o setor de finalização.</p>
                <?php else: ?>
                    <div class="vend-table-responsive">
                        <table class="vend-table">
                            <thead>
                                <tr>
                                    <th>O.S</th>
                                    <th>Cliente</th>
                                    <th>Entrega</th>
                                    <th>Status da Qualidade</th>
                                    <th>Status Geral</th>
                                    <th class="text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ordens as $os): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($os['numero']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($os['razao_social']); ?></td>
                                        <td><?php echo formatDate($os['data_termino'] ?? null); ?></td>
                                        <td><?php echo badgeQualidade($os['qualidade_status'] ?? 'pendente'); ?></td>
                                        <td><?php echo getStatusOSBadge($os['status']); ?></td>
                                        <td class="text-right">
                                            <a class="vbtn-sm btn-sm" href="checkup.php?os=<?php echo urlencode($os['numero']); ?>">
                                                <i class="fas fa-search"></i> INSPECIONAR
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
</table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include '../../includes/footer_vendedor.php'; ?>



