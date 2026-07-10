<?php
require_once '../../config/config.php';
require_once '../../includes/expediente.php';

requirePermission(['master', 'gerente']);

$page_title = 'Controle de Expediente';
$db = getDB();
ensureExpedienteSchema($db);

$tiposProducao = ['corte', 'dobra', 'solda', 'refrigeracao', 'montagem', 'finalizacao', 'acabamento', 'gerente'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'resetar_expediente') {
    $usuarioId = (int) ($_POST['usuario_id'] ?? 0);

    if ($usuarioId <= 0) {
        setError('Usuário inválido para reset do expediente.');
    } else {
        try {
            $resultado = resetarExpedienteHoje($db, $usuarioId, getCurrentUser() ?? []);
            if (!empty($resultado['success'])) {
                setSuccess($resultado['message']);
            } else {
                setError($resultado['message'] ?? 'Não foi possível resetar o expediente.');
            }
        } catch (Throwable $e) {
            setError('Erro ao resetar expediente: ' . $e->getMessage());
        }
    }

    header('Location: controle_expediente.php');
    exit;
}

$placeholders = implode(',', array_fill(0, count($tiposProducao), '?'));

$stmt = $db->prepare("
    SELECT
        u.id,
        u.nome,
        u.email,
        u.tipo,
        ue.id AS expediente_id,
        ue.status AS expediente_status,
        ue.iniciado_em,
        ue.finalizado_em
    FROM usuarios u
    LEFT JOIN usuarios_expedientes ue
        ON ue.usuario_id = u.id
       AND ue.data_referencia = CURDATE()
    WHERE u.tipo IN ($placeholders)
    ORDER BY
        CASE u.tipo
            WHEN 'gerente' THEN 1
            WHEN 'corte' THEN 2
            WHEN 'dobra' THEN 3
            WHEN 'solda' THEN 4
            WHEN 'refrigeracao' THEN 5
            WHEN 'acabamento' THEN 6
            WHEN 'finalizacao' THEN 7
            WHEN 'montagem' THEN 8
            ELSE 9
        END,
        u.nome ASC
");
$stmt->execute($tiposProducao);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header_vendedor.php';
?>
<div class="vend-layout">
    <aside class="vend-sidebar">
        <div class="vend-sidebar-logo">
            <div class="vend-logo-icon"><i class="fas fa-clock"></i></div>
            <div><div class="vend-logo-text">Cozinca Inox</div><div class="vend-logo-sub">Produção</div></div>
        </div>
        <div class="vend-nav-group"><span class="vend-nav-label">Principal</span><a href="../vendas/dashboard_vendedor.php" class="vend-nav-item"><i class="fas fa-th-large"></i> Dashboard</a><a href="producao.php" class="vend-nav-item"><i class="fas fa-industry"></i> Produção</a></div>
        <div class="vend-nav-group"><span class="vend-nav-label">Setores</span><a href="corte.php" class="vend-nav-item"><i class="fas fa-cut"></i> Corte</a><a href="dobra.php" class="vend-nav-item"><i class="fas fa-dharmachakra"></i> Dobra</a><a href="solda.php" class="vend-nav-item"><i class="fas fa-fire"></i> Solda</a><a href="montagem.php" class="vend-nav-item"><i class="fas fa-tools"></i> Montagem</a><a href="acabamento.php" class="vend-nav-item"><i class="fas fa-paint-roller"></i> Acabamento</a><a href="refrigeracao.php" class="vend-nav-item"><i class="fas fa-snowflake"></i> Refrigeracao</a></div>
        <hr class="vend-nav-divider" />
    </aside>
    <div class="vend-main"><div class="vend-page-head"><h1 class="vend-page-title">Controle de Expediente</h1></div><div class="vend-content">
        <div class="vend-card">
            <div class="vend-card-header">
                <h3>Controle de Expediente da Produção</h3>
                <p style="margin:10px 0 0 0; font-size:12px; color:#666;">
                    Use o reset apenas quando um usuário encerrar o expediente por engano e precisar iniciar novamente no mesmo dia.
                </p>
            </div>
    <div class="vend-card-body">
        <div class="alert alert-warning">
            <strong>Atenção:</strong> resetar o expediente apaga o expediente do dia para o usuário selecionado. Depois disso, ele poderá clicar em <strong>Iniciar</strong> novamente.
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>Função</th>
                        <th>Email</th>
                        <th>Status Hoje</th>
                        <th>Início</th>
                        <th>Fim</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($usuarios)): ?>
                        <tr>
                            <td colspan="7" class="text-center">Nenhum usuário de produção encontrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($usuarios as $usuarioLinha): ?>
                            <?php
                            $status = $usuarioLinha['expediente_status'] ?? '';
                            $labelStatus = 'Não iniciado';
                            $corStatus = '#64748b';

                            if ($status === 'em_trabalho') {
                                $labelStatus = 'Em trabalho';
                                $corStatus = '#16a34a';
                            } elseif ($status === 'encerrado') {
                                $labelStatus = 'Finalizado';
                                $corStatus = '#f59e0b';
                            }
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($usuarioLinha['nome']); ?></strong></td>
                                <td><?php echo ucfirst(htmlspecialchars($usuarioLinha['tipo'])); ?></td>
                                <td><?php echo htmlspecialchars($usuarioLinha['email'] ?? '-'); ?></td>
                                <td>
                                    <span class="badge" style="background-color: <?php echo $corStatus; ?>; color:#fff;">
                                        <?php echo $labelStatus; ?>
                                    </span>
                                </td>
                                <td><?php echo !empty($usuarioLinha['iniciado_em']) ? formatDateTime($usuarioLinha['iniciado_em']) : '-'; ?></td>
                                <td><?php echo !empty($usuarioLinha['finalizado_em']) ? formatDateTime($usuarioLinha['finalizado_em']) : '-'; ?></td>
                                <td>
                                    <?php if (!empty($usuarioLinha['expediente_id'])): ?>
                                        <form method="POST" onsubmit="return confirm('Resetar o expediente de hoje deste usuário?');" style="margin:0;">
                                            <input type="hidden" name="acao" value="resetar_expediente">
                                            <input type="hidden" name="usuario_id" value="<?php echo (int) $usuarioLinha['id']; ?>">
                                            <button type="submit" class="vbtn-sm btn-warning">
                                                <i class="fas fa-undo"></i> Resetar
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="font-size:12px; color:#94a3b8;">Sem expediente hoje</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer_vendedor.php'; ?>


