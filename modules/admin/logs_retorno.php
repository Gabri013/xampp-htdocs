<?php
require_once '../../config/config.php';
require_once '../../includes/expediente.php';
requirePermission(['master']);

$page_title = 'Logs do Sistema';

$db = getDB();
ensureExpedienteSchema($db);

// Filtros opcionais
$filtro_os = $_GET['os'] ?? '';
$filtro_data = $_GET['data'] ?? '';
$filtro_usuario = $_GET['usuario'] ?? '';

// Construir query com filtros
$query = "
    SELECT lre.*, os.numero as os_numero, c.razao_social, u.nome as usuario_nome
    FROM logs_retorno_etapa lre
    INNER JOIN ordens_servico os ON lre.os_id = os.id
    INNER JOIN clientes c ON os.cliente_id = c.id
    INNER JOIN usuarios u ON lre.usuario_id = u.id
    WHERE 1=1
";

$params = [];

if (!empty($filtro_os)) {
    $query .= " AND os.numero LIKE ?";
    $params[] = '%' . $filtro_os . '%';
}

if (!empty($filtro_data)) {
    $query .= " AND DATE(lre.created_at) = ?";
    $params[] = $filtro_data;
}

if (!empty($filtro_usuario)) {
    $query .= " AND lre.usuario_id = ?";
    $params[] = $filtro_usuario;
}

$query .= " ORDER BY lre.created_at DESC LIMIT 500";

$stmt = $db->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$queryExpediente = "
    SELECT
        ue.data_referencia,
        uel.registrado_em,
        uel.tipo,
        ue.iniciado_em,
        ue.finalizado_em,
        ue.status,
        u.nome AS usuario_nome,
        u.email AS usuario_email
    FROM usuarios_expediente_logs uel
    INNER JOIN usuarios_expedientes ue ON ue.id = uel.expediente_id
    INNER JOIN usuarios u ON u.id = uel.usuario_id
    WHERE 1=1
";

$paramsExpediente = [];

if (!empty($filtro_data)) {
    $queryExpediente .= " AND ue.data_referencia = ?";
    $paramsExpediente[] = $filtro_data;
}

if (!empty($filtro_usuario)) {
    $queryExpediente .= " AND uel.usuario_id = ?";
    $paramsExpediente[] = $filtro_usuario;
}

$queryExpediente .= " ORDER BY ue.data_referencia DESC, uel.registrado_em DESC LIMIT 500";

$stmtExpediente = $db->prepare($queryExpediente);
$stmtExpediente->execute($paramsExpediente);
$logsExpediente = $stmtExpediente->fetchAll();

// Buscar lista de usuários para filtro
$stmt_usuarios = $db->query("SELECT id, nome FROM usuarios ORDER BY nome");
$usuarios = $stmt_usuarios->fetchAll();

include '../../includes/header_vendedor.php';
?>

<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-page-head"><h1 class="vend-page-title">Logs do Sistema</h1></div>

        <div class="vend-content">
    <div class="card-header">
        <h3>Logs de Auditoria</h3>
        <p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">Consulte os retornos de etapa e também os registros diários de início e fim de expediente.</p>
    </div>
    <div class="card-body">
        <!-- Filtros -->
        <div style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #eee;">
            <h4 style="margin-top: 0;">Filtros</h4>
            <form method="GET" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;">
                <div style="flex: 1; min-width: 150px;">
                    <label for="os">Número O.S</label>
                    <input type="text" id="os" name="os" class="form-control" value="<?php echo htmlspecialchars($filtro_os); ?>" placeholder="Ex: OS-001">
                </div>
                <div style="flex: 1; min-width: 150px;">
                    <label for="data">Data</label>
                    <input type="date" id="data" name="data" class="form-control" value="<?php echo htmlspecialchars($filtro_data); ?>">
                </div>
                <div style="flex: 1; min-width: 150px;">
                    <label for="usuario">Usuário</label>
                    <select id="usuario" name="usuario" class="form-control">
                        <option value="">Todos</option>
                        <?php foreach ($usuarios as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo $filtro_usuario == $u['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($u['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Filtrar
                </button>
                <a href="logs_retorno.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Limpar
                </a>
            </form>
        </div>

        <!-- Tabela de Logs -->
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>O.S</th>
                        <th>Cliente</th>
                        <th>Etapa Anterior</th>
                        <th>Etapa Retornada</th>
                        <th>Usuário Responsável</th>
                        <th>Justificativa</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="7" class="text-center">Nenhum log de retorno encontrado</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <small><?php echo formatDateTime($log['created_at']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($log['os_numero']); ?></strong>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($log['razao_social']); ?>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?php echo ucfirst($log['etapa_anterior']); ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-warning"><?php echo ucfirst($log['etapa_retornada']); ?></span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($log['usuario_nome']); ?>
                                </td>
                                <td>
                                    <div style="max-width: 300px; white-space: normal;">
                                        <small><?php echo nl2br(htmlspecialchars($log['justificativa'])); ?></small>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top: 20px; padding: 10px; background: #f0f8ff; border-left: 4px solid #007bff; border-radius: 3px;">
            <strong>Total de registros:</strong> <?php echo count($logs); ?> log(s) encontrado(s)
        </div>

        <div style="margin-top:30px;">
            <h4 style="margin-bottom:15px;">Logs de Expediente Diário</h4>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Dia de Trabalho</th>
                            <th>Data/Hora do Registro</th>
                            <th>Tipo</th>
                            <th>Usuário</th>
                            <th>Email</th>
                            <th>Início do Dia</th>
                            <th>Fim do Dia</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logsExpediente)): ?>
                            <tr>
                                <td colspan="8" class="text-center">Nenhum log de expediente encontrado</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logsExpediente as $logExpediente): ?>
                                <tr>
                                    <td><strong><?php echo formatDate($logExpediente['data_referencia']); ?></strong></td>
                                    <td><small><?php echo formatDateTime($logExpediente['registrado_em']); ?></small></td>
                                    <td>
                                        <span class="badge" style="background-color: <?php echo $logExpediente['tipo'] === 'inicio' ? '#16a34a' : '#64748b'; ?>; color: #fff;">
                                            <?php echo $logExpediente['tipo'] === 'inicio' ? 'Início' : 'Fim'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($logExpediente['usuario_nome']); ?></td>
                                    <td><?php echo htmlspecialchars($logExpediente['usuario_email']); ?></td>
                                    <td><?php echo !empty($logExpediente['iniciado_em']) ? formatDateTime($logExpediente['iniciado_em']) : '-'; ?></td>
                                    <td><?php echo !empty($logExpediente['finalizado_em']) ? formatDateTime($logExpediente['finalizado_em']) : '-'; ?></td>
                                    <td>
                                        <span class="badge" style="background-color: <?php echo ($logExpediente['status'] ?? '') === 'em_trabalho' ? '#f59e0b' : '#16a34a'; ?>; color: #fff;">
                                            <?php echo ($logExpediente['status'] ?? '') === 'em_trabalho' ? 'Em trabalho' : 'Encerrado'; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 20px; padding: 10px; background: #f6ffed; border-left: 4px solid #16a34a; border-radius: 3px;">
                <strong>Total de registros de expediente:</strong> <?php echo count($logsExpediente); ?> log(s) encontrado(s)
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer_vendedor.php'; ?>
