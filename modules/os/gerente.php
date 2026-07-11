<?php
require_once '../../config/config.php';
require_once '../../includes/workflow.php';
require_once '../../includes/engenharia.php';
requirePermission(['master', 'gerente']);

$page_title = 'Ordens de Serviço - Gerente de Produção';
$db = getDB();
ensureEngenhariaSchema($db);
ensureOrdensServicoIndependentesSchema($db);

// --- 1. PROCESSAR AÇÕES (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'liberar_producao') {
    $os_id = $_POST['os_id'] ?? null;
    $etapa_inicial = normalizarEtapaEngenharia($_POST['etapa_inicial'] ?? '') ?? 'corte';
    $observacao = sanitize($_POST['observacao'] ?? '');

    if ($os_id) {
        try {
            $db->beginTransaction();

            $stmtVenda = $db->prepare("SELECT venda_id FROM ordens_servico WHERE id = ?");
            $stmtVenda->execute([$os_id]);
            $venda_id = (int) $stmtVenda->fetchColumn();

            $etapasPlanejadas = sincronizarPlanejamentoOS($db, (int) $os_id, max(0, $venda_id));
            $etapasPermitidas = array_column($etapasPlanejadas, 'etapa');
            if (!in_array($etapa_inicial, $etapasPermitidas, true)) {
                $etapa_inicial = $etapasPermitidas[0] ?? 'corte';
            }
            
            // Atualizar status e etapa inicial da O.S
            $stmt = $db->prepare("UPDATE ordens_servico SET status='em_producao', etapa_atual=? WHERE id=?");
            $stmt->execute([$etapa_inicial, $os_id]);
            
            // Registrar histórico
            $stmt = $db->prepare("INSERT INTO os_historico_status (os_id, status_anterior, status_novo, usuario_id, observacao) VALUES (?, 'em_revisao', 'em_producao', ?, ?)");
            $obs_hist = "Pedido liberado para produção na etapa: " . ucfirst($etapa_inicial);
            $stmt->execute([$os_id, $_SESSION['usuario_id'], $obs_hist]);
            
            // Registrar observação se houver
            if (!empty($observacao)) {
                $stmt = $db->prepare("INSERT INTO os_observacoes (os_id, tipo_setor, observacao, usuario_id) VALUES (?, 'gerente', ?, ?)");
                $stmt->execute([$os_id, $observacao, $_SESSION['usuario_id']]);
            }
            
            $db->commit();
            setSuccess('Pedido liberado para produção na etapa ' . ucfirst($etapa_inicial) . '!');
            header('Location: gerente.php');
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            setError('Erro ao liberar pedido: ' . $e->getMessage());
        }
    }
}

// --- 2. BUSCAR ORDENS ---
// Buscar ordens em revisão (para liberação)
$stmt = $db->query("
    SELECT os.*, c.razao_social,
           COALESCE(v.numero, 'Independente') as venda_numero,
           COALESCE(u.nome, '-') as vendedor_nome
    FROM ordens_servico os
    INNER JOIN clientes c ON os.cliente_id = c.id
    LEFT JOIN vendas v ON os.venda_id = v.id
    LEFT JOIN usuarios u ON v.usuario_id = u.id
    WHERE os.status = 'em_revisao'
    ORDER BY 
        CASE os.prioridade 
            WHEN 'vermelho' THEN 1 
            WHEN 'amarelo' THEN 2 
            WHEN 'verde' THEN 3 
        END,
        os.data_inicio ASC
");
$ordens_revisao = $stmt->fetchAll();

// Buscar ordens em produção (para acompanhamento e retorno)
$stmt = $db->query("
    SELECT os.*, c.razao_social,
           COALESCE(v.numero, 'Independente') as venda_numero,
           COALESCE(u.nome, '-') as vendedor_nome
    FROM ordens_servico os
    INNER JOIN clientes c ON os.cliente_id = c.id
    LEFT JOIN vendas v ON os.venda_id = v.id
    LEFT JOIN usuarios u ON v.usuario_id = u.id
    WHERE os.status = 'em_producao'
    ORDER BY os.data_inicio DESC
");
$ordens_producao = $stmt->fetchAll();

$data_inicio_agenda = date('Y-m-d');
$agenda_parte1 = [];
$agenda_parte2 = [];

for ($i = 0; $i < 30; $i++) {
    $dataAgenda = date('Y-m-d', strtotime("+$i days"));
    if ($i < 15) {
        $agenda_parte1[$dataAgenda] = [];
    } else {
        $agenda_parte2[$dataAgenda] = [];
    }
}

$data_fim_agenda = date('Y-m-d', strtotime('+29 days'));
$stmtAgendaPanorama = $db->prepare("
    SELECT os.id, os.numero, os.data_termino, c.razao_social
    FROM ordens_servico os
    INNER JOIN clientes c ON os.cliente_id = c.id
    WHERE os.data_termino BETWEEN ? AND ?
      AND os.status NOT IN ('concluida', 'cancelada')
    ORDER BY os.data_termino ASC
");
$stmtAgendaPanorama->execute([$data_inicio_agenda, $data_fim_agenda]);
$entregasAgendaPanorama = $stmtAgendaPanorama->fetchAll(PDO::FETCH_ASSOC);

foreach ($entregasAgendaPanorama as $entregaAgenda) {
    $dtAgenda = $entregaAgenda['data_termino'];
    if (isset($agenda_parte1[$dtAgenda])) {
        $agenda_parte1[$dtAgenda][] = $entregaAgenda;
    } elseif (isset($agenda_parte2[$dtAgenda])) {
        $agenda_parte2[$dtAgenda][] = $entregaAgenda;
    }
}

$stmtAgenda = $db->query("
    SELECT 
        os.id,
        os.numero,
        os.data_inicio,
        os.data_termino,
        os.prioridade,
        os.status,
        os.etapa_atual,
        c.razao_social,
        CASE
            WHEN os.data_termino IS NULL THEN 'sem_data'
            WHEN os.data_termino < CURDATE() THEN 'atrasada'
            WHEN os.data_termino = CURDATE() THEN 'hoje'
            WHEN os.data_termino = DATE_ADD(CURDATE(), INTERVAL 1 DAY) THEN 'amanha'
            ELSE 'proxima'
        END AS agenda_status
    FROM ordens_servico os
    INNER JOIN clientes c ON c.id = os.cliente_id
    WHERE os.status NOT IN ('concluida', 'cancelada')
      AND (
          os.data_termino IS NULL
          OR os.data_termino <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
      )
    ORDER BY
        CASE
            WHEN os.data_termino IS NULL THEN 4
            WHEN os.data_termino < CURDATE() THEN 0
            WHEN os.data_termino = CURDATE() THEN 1
            WHEN os.data_termino = DATE_ADD(CURDATE(), INTERVAL 1 DAY) THEN 2
            ELSE 3
        END,
        os.data_termino ASC,
        CASE os.prioridade
            WHEN 'vermelho' THEN 1
            WHEN 'amarelo' THEN 2
            ELSE 3
        END,
        os.numero ASC
    LIMIT 12
");
$agenda_ordens = $stmtAgenda->fetchAll(PDO::FETCH_ASSOC);

$agenda_resumo = [
    'atrasadas' => 0,
    'hoje' => 0,
    'amanha' => 0,
    'proximas' => 0,
];

foreach ($agenda_ordens as $ordemAgenda) {
    if (($ordemAgenda['agenda_status'] ?? '') === 'atrasada') {
        $agenda_resumo['atrasadas']++;
    } elseif (($ordemAgenda['agenda_status'] ?? '') === 'hoje') {
        $agenda_resumo['hoje']++;
    } elseif (($ordemAgenda['agenda_status'] ?? '') === 'amanha') {
        $agenda_resumo['amanha']++;
    } else {
        $agenda_resumo['proximas']++;
    }
}

$stmtAlertasEntrega = $db->query("
    SELECT 
        os.id,
        os.numero,
        os.data_termino,
        c.razao_social,
        CASE
            WHEN os.data_termino < CURDATE() THEN 'atrasada'
            WHEN os.data_termino = DATE_ADD(CURDATE(), INTERVAL 1 DAY) THEN 'vence_amanha'
            ELSE ''
        END AS tipo_alerta
    FROM ordens_servico os
    INNER JOIN clientes c ON c.id = os.cliente_id
    WHERE os.status NOT IN ('concluida', 'cancelada')
      AND os.data_termino IS NOT NULL
      AND (
          os.data_termino < CURDATE()
          OR os.data_termino = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
      )
    ORDER BY 
        CASE WHEN os.data_termino < CURDATE() THEN 0 ELSE 1 END,
        os.data_termino ASC,
        os.numero ASC
");
$ordens_alerta_entrega = $stmtAlertasEntrega->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header_vendedor.php';
?>
<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-page-head"><h1 class="vend-page-title">Agenda do Gerente</h1></div>
        <div class="vend-content">

<style>
    .modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow-y: auto; }
    .modal.show { display: block; }
    .modal-content { background: white; margin: 2% auto; padding: 20px; border-radius: 8px; width: 90%; max-width: 900px; }
    .mt-20 { margin-top: 20px; }
    .etapa-checkbox-item { display: flex; align-items: center; gap: 10px; padding: 8px; border-bottom: 1px solid #eee; }
    .etapa-checkbox-item:last-child { border-bottom: none; }
    .etapa-checkbox-item label { margin: 0; cursor: pointer; flex: 1; }
    .thumb-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 12px; margin-top: 10px; }
    .thumb-card { border: 1px solid #ddd; border-radius: 8px; padding: 8px; background: #fff; text-align: center; }
    .thumb-card img { width: 100%; height: 95px; object-fit: cover; border-radius: 4px; cursor: zoom-in; border: 1px solid #eee; }
    .thumb-name { font-size: 11px; margin-top: 6px; word-break: break-word; color: #333; }
    .lightbox-modal { display: none; position: fixed; z-index: 10000; inset: 0; background: rgba(0, 0, 0, 0.88); align-items: center; justify-content: center; padding: 20px; }
    .lightbox-modal.show { display: flex; }
    .lightbox-content { max-width: 95vw; max-height: 90vh; border-radius: 6px; box-shadow: 0 8px 30px rgba(0,0,0,0.35); }
    .lightbox-close { position: absolute; top: 16px; right: 20px; color: #fff; font-size: 34px; border: 0; background: transparent; cursor: pointer; }
    .lightbox-caption { position: absolute; bottom: 16px; left: 0; right: 0; text-align: center; color: #fff; font-size: 14px; }
    .alerta-entrega-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); z-index: 10001; align-items: center; justify-content: center; padding: 20px; }
    .alerta-entrega-overlay.show { display: flex; }
    .alerta-entrega-card { width: 100%; max-width: 720px; background: #fff; border-radius: 14px; box-shadow: 0 20px 60px rgba(0,0,0,0.25); overflow: hidden; }
    .alerta-entrega-header { background: linear-gradient(135deg, #f59e0b, #ef4444); color: #fff; padding: 18px 22px; display: flex; justify-content: space-between; align-items: center; }
    .alerta-entrega-body { padding: 20px 22px; }
    .alerta-entrega-body ul { margin: 0; padding-left: 20px; }
    .alerta-entrega-body li + li { margin-top: 8px; }
    .alerta-entrega-close { border: 0; background: rgba(255,255,255,0.15); color: #fff; width: 36px; height: 36px; border-radius: 999px; cursor: pointer; font-size: 22px; line-height: 1; }
    .agenda-topo-card { margin-bottom: 20px; }
    .agenda-resumo-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 18px; }
    .agenda-resumo-item { border-radius: 12px; padding: 16px; color: #fff; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08); }
    .agenda-resumo-item strong { display: block; font-size: 26px; line-height: 1; margin-top: 8px; }
    .agenda-resumo-item span { font-size: 12px; opacity: 0.9; text-transform: uppercase; letter-spacing: 0.04em; }
    .agenda-lista { display: grid; gap: 10px; }
    .agenda-item { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 14px 16px; border: 1px solid #e5e7eb; border-radius: 12px; background: #fff; }
    .agenda-item-info strong { display: block; color: #111827; }
    .agenda-item-info small { display: block; margin-top: 4px; color: #6b7280; }
    .agenda-item-meta { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }
    .agenda-pill { display: inline-flex; align-items: center; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
    .agenda-pill.atrasada { background: #fee2e2; color: #b91c1c; }
    .agenda-pill.hoje { background: #fef3c7; color: #b45309; }
    .agenda-pill.amanha { background: #dbeafe; color: #1d4ed8; }
    .agenda-pill.proxima, .agenda-pill.sem_data { background: #e5e7eb; color: #374151; }
    .table-agenda { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .table-agenda th, .table-agenda td { border: 1px solid #ddd; text-align: center; padding: 8px 3px; vertical-align: top; min-width: 70px; }
    .table-agenda th { background-color: #f8f9fa; }
    .table-agenda th.fds, .table-agenda td.fds { background-color: #fff5f5; }
    .dia-semana { font-size: 0.7rem; text-transform: uppercase; color: #666; }
    .dia-mes { font-size: 0.9rem; font-weight: bold; }
    .cliente-item { background-color: #e3f2fd; border-left: 3px solid #2196f3; margin-bottom: 4px; padding: 3px; font-size: 0.75rem; border-radius: 2px; word-wrap: break-word; text-align: left; line-height: 1.1; }
    .cliente-item a { text-decoration: none; color: #1976d2; display: block; }
    .cliente-item:hover { background-color: #bbdefb; }
    .vazio-agenda { color: #d0d5dd; font-size: 0.8rem; }
    @media (max-width: 960px) {
        .agenda-resumo-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .agenda-item { flex-direction: column; align-items: flex-start; }
        .agenda-item-meta { justify-content: flex-start; }
    }
</style>

<div class="vend-card">
    <div class="vend-card-head">
        <h3><i class="fas fa-calendar-day"></i> Agenda do Panorama de Produção (Próximos 15 dias)</h3>
    </div>
    <div class="vend-card-body">
        <div class="table-responsive">
            <table class="table-agenda">
                <thead>
                    <tr>
                        <?php foreach ($agenda_parte1 as $dataAgenda => $clientesAgenda): ?>
                            <th class="<?php echo (date('N', strtotime($dataAgenda)) >= 6) ? 'fds' : ''; ?>">
                                <div class="dia-semana">
                                    <?php $diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']; echo $diasSemana[date('w', strtotime($dataAgenda))]; ?>
                                </div>
                                <div class="dia-mes"><?php echo date('d/m', strtotime($dataAgenda)); ?></div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <?php foreach ($agenda_parte1 as $dataAgenda => $clientesAgenda): ?>
                            <td class="<?php echo (date('N', strtotime($dataAgenda)) >= 6) ? 'fds' : ''; ?>">
                                <?php if (empty($clientesAgenda)): ?>
                                    <span class="vazio-agenda">-</span>
                                <?php else: ?>
                                    <?php foreach ($clientesAgenda as $clienteAgenda): ?>
                                        <div class="cliente-item" title="O.S: <?php echo htmlspecialchars($clienteAgenda['numero']); ?>">
                                            <a href="os_detalhes.php?os_id=<?php echo (int) $clienteAgenda['id']; ?>">
                                                <?php echo htmlspecialchars(mb_strimwidth($clienteAgenda['razao_social'], 0, 12, '..')); ?>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mt-20">
    <div class="vend-card-head">
        <h3><i class="fas fa-calendar-alt"></i> Agenda do Panorama de Produção (16 a 30 dias)</h3>
    </div>
    <div class="vend-card-body">
        <div class="table-responsive">
            <table class="table-agenda">
                <thead>
                    <tr>
                        <?php foreach ($agenda_parte2 as $dataAgenda => $clientesAgenda): ?>
                            <th class="<?php echo (date('N', strtotime($dataAgenda)) >= 6) ? 'fds' : ''; ?>">
                                <div class="dia-semana">
                                    <?php $diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']; echo $diasSemana[date('w', strtotime($dataAgenda))]; ?>
                                </div>
                                <div class="dia-mes"><?php echo date('d/m', strtotime($dataAgenda)); ?></div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <?php foreach ($agenda_parte2 as $dataAgenda => $clientesAgenda): ?>
                            <td class="<?php echo (date('N', strtotime($dataAgenda)) >= 6) ? 'fds' : ''; ?>">
                                <?php if (empty($clientesAgenda)): ?>
                                    <span class="vazio-agenda">-</span>
                                <?php else: ?>
                                    <?php foreach ($clientesAgenda as $clienteAgenda): ?>
                                        <div class="cliente-item" title="O.S: <?php echo htmlspecialchars($clienteAgenda['numero']); ?>">
                                            <a href="os_detalhes.php?os_id=<?php echo (int) $clienteAgenda['id']; ?>">
                                                <?php echo htmlspecialchars(mb_strimwidth($clienteAgenda['razao_social'], 0, 12, '..')); ?>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card agenda-topo-card">
    <div class="vend-card-head">
        <h3>Agenda do Gerente de Produção</h3>
    </div>
    <div class="vend-card-body">
        <div class="agenda-resumo-grid">
            <div class="agenda-resumo-item" style="background: linear-gradient(135deg, #dc2626, #ef4444);">
                <span>Atrasadas</span>
                <strong><?php echo (int) $agenda_resumo['atrasadas']; ?></strong>
            </div>
            <div class="agenda-resumo-item" style="background: linear-gradient(135deg, #d97706, #f59e0b);">
                <span>Entregas Hoje</span>
                <strong><?php echo (int) $agenda_resumo['hoje']; ?></strong>
            </div>
            <div class="agenda-resumo-item" style="background: linear-gradient(135deg, #2563eb, #3b82f6);">
                <span>Vencem Amanhã</span>
                <strong><?php echo (int) $agenda_resumo['amanha']; ?></strong>
            </div>
            <div class="agenda-resumo-item" style="background: linear-gradient(135deg, #475569, #64748b);">
                <span>Próximas / Sem Data</span>
                <strong><?php echo (int) $agenda_resumo['proximas']; ?></strong>
            </div>
        </div>

        <?php if (empty($agenda_ordens)): ?>
            <div class="alert alert-info" style="margin-bottom:0;">
                Nenhuma O.S. com entrega próxima para acompanhar no momento.
            </div>
        <?php else: ?>
            <div class="agenda-lista">
                <?php foreach ($agenda_ordens as $ordemAgenda): ?>
                    <?php
                    $statusAgenda = $ordemAgenda['agenda_status'] ?? 'proxima';
                    $rotuloAgenda = 'Próxima';
                    if ($statusAgenda === 'atrasada') {
                        $rotuloAgenda = 'Atrasada';
                    } elseif ($statusAgenda === 'hoje') {
                        $rotuloAgenda = 'Entrega hoje';
                    } elseif ($statusAgenda === 'amanha') {
                        $rotuloAgenda = 'Vence amanhã';
                    } elseif ($statusAgenda === 'sem_data') {
                        $rotuloAgenda = 'Sem data';
                    }
                    ?>
                    <div class="agenda-item">
                        <div class="agenda-item-info">
                            <strong><?php echo htmlspecialchars($ordemAgenda['numero']); ?> · <?php echo htmlspecialchars($ordemAgenda['razao_social']); ?></strong>
                            <small>
                                Etapa atual: <?php echo htmlspecialchars(ucfirst((string) ($ordemAgenda['etapa_atual'] ?? '-'))); ?>
                                <?php if (!empty($ordemAgenda['data_termino'])): ?>
                                    | Entrega: <?php echo formatDate($ordemAgenda['data_termino']); ?>
                                <?php endif; ?>
                            </small>
                        </div>
                        <div class="agenda-item-meta">
                            <span class="agenda-pill <?php echo htmlspecialchars($statusAgenda); ?>"><?php echo htmlspecialchars($rotuloAgenda); ?></span>
                            <a href="os_detalhes.php?os_id=<?php echo (int) $ordemAgenda['id']; ?>" class="vbtn-sm btn-info">
                                <i class="fas fa-eye"></i> Abrir O.S.
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="vend-card">
    <div class="vend-card-head">
        <h3>Painel do Gerente de Produção - Revisão e Liberação</h3>
        <a href="nova_os_independente.php" class="vbtn-sm">
            <i class="fas fa-plus"></i> Nova O.S. Independente
        </a>
    </div>
    <div class="vend-card-body">
        <div class="alert alert-info">
            <strong>Instruções:</strong> Revise os projetos e selecione em qual etapa a produção deve iniciar.
        </div>
        
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Número O.S</th>
                        <th>Cliente</th>
                        <th>Venda</th>
                        <th>Início</th>
                        <th>Prioridade</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ordens_revisao)): ?>
                        <tr><td colspan="7" class="text-center">Nenhuma ordem para revisão</td></tr>
                    <?php else: ?>
                        <?php foreach ($ordens_revisao as $os): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($os['numero']); ?></strong></td>
                                <td><?= htmlspecialchars($os['razao_social']); ?></td>
                                <td><?= htmlspecialchars($os['venda_numero']); ?></td>
                                <td><?= formatDate($os['data_inicio']); ?></td>
                                <td>
                                    <?php
                                    $cores = ['verde' => '#28a745', 'amarelo' => '#ffc107', 'vermelho' => '#dc3545'];
                                    $nomes = ['verde' => 'Normal', 'amarelo' => 'Emergente', 'vermelho' => 'Urgente'];
                                    ?>
                                    <span class="badge" style="background-color: <?= $cores[$os['prioridade']]; ?>; color: white;">
                                        <?= $nomes[$os['prioridade']]; ?>
                                    </span>
                                </td>
                                <td><?= getStatusOSBadge($os['status']); ?></td>
                                <td>
                                    <button type="button" class="vbtn-sm btn-primary" 
                                            onclick='abrirModalGerente(<?= htmlspecialchars(json_encode($os), ENT_QUOTES, "UTF-8"); ?>)'>
                                        <i class="fas fa-folder-open"></i> Acessar
                                    </button>
                                    <a href="os_detalhes.php?os_id=<?= $os['id']; ?>" class="vbtn-sm btn-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mt-20">
    <div class="vend-card-head">
        <h3>Acompanhamento de Produção e Retornos</h3>
    </div>
    <div class="vend-card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Número O.S</th>
                        <th>Cliente</th>
                        <th>Etapa Atual</th>
                        <th>Prioridade</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ordens_producao)): ?>
                        <tr><td colspan="5" class="text-center">Nenhuma ordem em produção no momento</td></tr>
                    <?php else: ?>
                        <?php foreach ($ordens_producao as $os): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($os['numero']); ?></strong></td>
                                <td><?= htmlspecialchars($os['razao_social']); ?></td>
                                <td><span class="vbadge-info"><?= ucfirst($os['etapa_atual']); ?></span></td>
                                <td>
                                    <?php
                                    $cores = ['verde' => '#28a745', 'amarelo' => '#ffc107', 'vermelho' => '#dc3545'];
                                    ?>
                                    <span class="badge" style="background-color: <?= $cores[$os['prioridade']]; ?>; color: white;">
                                        <?= strtoupper($os['prioridade']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($os['etapa_atual'] !== 'autorizacao'): ?>
                                        <button class="vbtn-sm btn-warning" onclick='abrirModalRetorno(<?= json_encode($os); ?>)' title="Retornar etapa">
                                            <i class="fas fa-undo"></i> Retornar
                                        </button>
                                    <?php endif; ?>
                                    <a href="os_detalhes.php?os_id=<?= $os['id']; ?>" class="vbtn-sm btn-info">
                                        <i class="fas fa-eye"></i> Detalhes
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal de Liberação -->
<div id="modalOS" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitulo">Detalhes da O.S</h3>
            <button type="button" class="close" onclick="fecharModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="detalhesOS"></div>
            
            <hr>
            
            <form method="POST" id="formLiberar">
                <input type="hidden" name="acao" value="liberar_producao">
                <input type="hidden" name="os_id" id="os_id">
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label><b>Iniciar na Etapa:</b></label>
                        <select name="etapa_inicial" id="etapa_inicial" class="form-control" required style="border: 2px solid #27ae60;">
                            <option value="corte">Corte</option>
                            <option value="dobra">Dobra</option>
                            <option value="solda">Solda</option>
                            <option value="refrigeracao">Refrigeração</option>
                            <option value="acabamento">Acabamento</option>
                            <option value="finalizacao">Finalização</option>
                            <option value="montagem">Montagem</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><b>Observações do Gerente (opcional)</b></label>
                    <textarea name="observacao" class="form-control" rows="3" placeholder="Instruções adicionais para a produção..."></textarea>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="vbtn-sm" onclick="fecharModal()">Fechar</button>
                    <button type="submit" class="vbtn-sm">
                        <i class="fas fa-check"></i> Liberar para Produção
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Retorno de Etapa (FLEXÍVEL) -->
<div id="modalRetorno" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Retornar Etapa (Gerente)</h3>
            <button class="close" onclick="fecharModalRetorno()">&times;</button>
        </div>
        <form id="formRetorno">
            <div class="modal-body">
                <input type="hidden" name="acao" value="retornar_etapa">
                <input type="hidden" name="os_id" id="os_id_retorno">
                <input type="hidden" name="etapa_atual" id="etapa_atual_retorno">
                
                <div class="form-group">
                    <label><strong>O.S:</strong> <span id="os_numero_retorno"></span></label>
                </div>
                
                <div class="form-group">
                    <label><strong>Etapa Atual:</strong> <span id="etapa_nome_retorno"></span></label>
                </div>

                <div class="form-group">
                    <label><strong>Retornar para qual etapa? *</strong></label>
                    <div id="etapas_retorno_container" style="background: #f8f9fa; padding: 10px; border-radius: 5px; border: 1px solid #ddd;">
                        <!-- Checkboxes serão inseridos via JS -->
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="justificativa"><strong>Justificativa do Retorno *</strong></label>
                    <textarea id="justificativa" name="justificativa" class="form-control" rows="4" placeholder="Explique o motivo do retorno..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="vbtn-sm" onclick="fecharModalRetorno()">Cancelar</button>
                <button type="submit" class="vbtn-sm"><i class="fas fa-undo"></i> Confirmar Retorno</button>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($ordens_alerta_entrega)): ?>
<div id="alertaEntregaOverlay" class="alerta-entrega-overlay show">
    <div class="alerta-entrega-card">
        <div class="alerta-entrega-header">
            <div>
                <h3 style="margin:0;">Alerta de Entregas da O.S.</h3>
                <p style="margin:6px 0 0 0; font-size:13px;">Verifique as ordens atrasadas e as que vencem amanhã para agir com prioridade.</p>
            </div>
            <button type="button" class="alerta-entrega-close" onclick="fecharAlertaEntrega()">&times;</button>
        </div>
        <div class="alerta-entrega-body">
            <ul>
                <?php foreach ($ordens_alerta_entrega as $ordemAlerta): ?>
                    <li>
                        <strong><?php echo htmlspecialchars($ordemAlerta['numero']); ?></strong>
                        | Status: 
                        <strong style="color: <?php echo $ordemAlerta['tipo_alerta'] === 'atrasada' ? '#dc2626' : '#b45309'; ?>;">
                            <?php echo $ordemAlerta['tipo_alerta'] === 'atrasada' ? 'Atrasada' : 'Vence amanhã'; ?>
                        </strong>
                        | Cliente: <?php echo htmlspecialchars($ordemAlerta['razao_social']); ?>
                        | Entrega: <?php echo formatDate($ordemAlerta['data_termino']); ?>
                        | <a href="os_detalhes.php?os_id=<?php echo (int) $ordemAlerta['id']; ?>">abrir O.S.</a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div style="margin-top:16px; display:flex; justify-content:flex-end;">
                <button type="button" class="vbtn-sm" onclick="fecharAlertaEntrega()">Fechar alerta</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div id="lightboxModal" class="lightbox-modal">
    <button type="button" class="lightbox-close" onclick="fecharLightbox()">&times;</button>
    <img id="lightboxImage" class="lightbox-content" src="" alt="Visualizacao ampliada">
    <div id="lightboxCaption" class="lightbox-caption"></div>
</div>

<script src="<?= SITE_URL; ?>/assets/js/main.js"></script>
<script>
const fluxo_etapas = <?php echo json_encode(getValidOSEtapas()); ?>;

function isImagemArquivo(nomeArquivo) {
    if (!nomeArquivo) return false;
    return /\.(jpg|jpeg|png|gif|webp)$/i.test(nomeArquivo);
}

function escapeHtml(texto) {
    const div = document.createElement('div');
    div.textContent = texto || '';
    return div.innerHTML;
}

function formatarNomeEtapa(etapa) {
    if (!etapa) return '';
    return etapa.charAt(0).toUpperCase() + etapa.slice(1).replace('_', ' ');
}

function preencherEtapasPlanejadas(etapas) {
    const select = document.getElementById('etapa_inicial');
    if (!select) return;

    const etapasNormalizadas = Array.isArray(etapas) && etapas.length
        ? etapas.map(item => item.etapa || item).filter(Boolean)
        : ['corte', 'dobra', 'solda', 'refrigeracao', 'acabamento', 'finalizacao', 'montagem'];

    select.innerHTML = etapasNormalizadas.map(etapa => (
        `<option value="${etapa}">${formatarNomeEtapa(etapa)}</option>`
    )).join('');
}

function abrirLightbox(url, legenda) {
    document.getElementById('lightboxImage').src = url;
    document.getElementById('lightboxCaption').textContent = legenda || '';
    document.getElementById('lightboxModal').classList.add('show');
}

function fecharLightbox() {
    const modal = document.getElementById('lightboxModal');
    modal.classList.remove('show');
    document.getElementById('lightboxImage').src = '';
    document.getElementById('lightboxCaption').textContent = '';
}

function fecharAlertaEntrega() {
    const overlay = document.getElementById('alertaEntregaOverlay');
    if (overlay) {
        overlay.classList.remove('show');
    }
}

function abrirModalGerente(os) {
    document.getElementById('os_id').value = os.id;
    document.getElementById('modalTitulo').innerText = 'Revisão O.S ' + os.numero + ' - ' + os.razao_social;
    
    const detalhesDiv = document.getElementById('detalhesOS');
    detalhesDiv.innerHTML = '<p><i class="fas fa-spinner fa-spin"></i> Carregando projeto e itens...</p>';

    const modal = document.getElementById('modalOS');
    modal.style.display = 'block';
    modal.classList.add('show');

    fetch('<?= SITE_URL; ?>/api/os.php?id=' + os.id)
        .then(response => response.json())
        .then(data => {
            preencherEtapasPlanejadas(data.etapas_planejadas || []);

            let html = `
                <div style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                    <p><strong>Cliente:</strong> ${os.razao_social}</p>
                    <p><strong>Venda Original:</strong> ${os.venda_numero}</p>
                    <p><strong>Vendedor:</strong> ${os.vendedor_nome || 'N/A'}</p>
                </div>
            `;

            if (os.arquivo_projeto) {
                const projetoUrl = `<?= SITE_URL; ?>/assets/uploads/projetos/${os.arquivo_projeto}`;
                html += `
                <div class="alert alert-warning">
                    <strong>PROJETO ENVIADO:</strong><br>
                    <a href="${projetoUrl}" target="_blank" class="vbtn-sm mt-10">
                        <i class="fas fa-file-pdf"></i> Visualizar Desenho Técnico / Projeto
                    </a>
                </div>`;

                if (isImagemArquivo(os.arquivo_projeto)) {
                    const nomeProjeto = escapeHtml(os.arquivo_projeto);
                    html += `
                    <div class="mt-10">
                        <strong>Miniatura do projeto:</strong>
                        <div class="thumb-grid">
                            <div class="thumb-card">
                                <img src="${projetoUrl}" alt="${nomeProjeto}" onclick="abrirLightbox('${projetoUrl}', '${nomeProjeto}')">
                                <div class="thumb-name">${nomeProjeto}</div>
                            </div>
                        </div>
                    </div>`;
                }
            } else {
                html += `<div class="alert alert-danger">Atenção: Nenhum arquivo de projeto anexado!</div>`;
            }

            if (data.arquivos && data.arquivos.length > 0) {
                html += '<h4 class="mt-20">ARQUIVOS DA O.S</h4>';
                html += '<div class="thumb-grid">';
                data.arquivos.forEach(arquivo => {
                    const arquivoUrl = `<?= SITE_URL; ?>/assets/uploads/projetos/${arquivo.nome_arquivo}`;
                    const nomeOriginal = escapeHtml(arquivo.nome_original || arquivo.nome_arquivo);
                    if (isImagemArquivo(arquivo.nome_arquivo)) {
                        html += `
                            <div class="thumb-card">
                                <img src="${arquivoUrl}" alt="${nomeOriginal}" onclick="abrirLightbox('${arquivoUrl}', '${nomeOriginal}')">
                                <div class="thumb-name">${nomeOriginal}</div>
                            </div>
                        `;
                    } else {
                        html += `
                            <div class="thumb-card">
                                <a href="${arquivoUrl}" target="_blank" class="vbtn-sm btn-outline-secondary">
                                    <i class="fas fa-file"></i> Abrir arquivo
                                </a>
                                <div class="thumb-name">${nomeOriginal}</div>
                            </div>
                        `;
                    }
                });
                html += '</div>';
            }

            if (data.itens && data.itens.length > 0) {
                html += '<h4 class="mt-20">PRODUTOS A SEREM FABRICADOS</h4>';
                html += '<table class="table table-bordered table-sm" style="width: 100%;">';
                html += '<thead class="bg-light"><tr><th>Descrição</th><th class="text-center">Qtd</th></tr></thead><tbody>';
                data.itens.forEach(item => {
                    const desc = item.produto_id ? (item.produto_codigo + ' - ' + item.produto_nome) : item.descricao_manual;
                    html += `<tr>
                        <td>${desc}</td>
                        <td class="text-center"><strong>${item.quantidade}</strong></td>
                    </tr>`;
                });
                html += '</tbody></table>';
            }
            
            detalhesDiv.innerHTML = html;
        })
        .catch(err => {
            console.error(err);
            preencherEtapasPlanejadas([]);
            detalhesDiv.innerHTML = '<p class="text-danger">Erro ao carregar itens da API.</p>';
        });
}

function fecharModal() {
    const modal = document.getElementById('modalOS');
    modal.style.display = 'none';
    modal.classList.remove('show');
}

function abrirModalRetorno(os) {
    document.getElementById('os_id_retorno').value = os.id;
    document.getElementById('etapa_atual_retorno').value = os.etapa_atual;
    document.getElementById('os_numero_retorno').textContent = os.numero;
    document.getElementById('etapa_nome_retorno').textContent = os.etapa_atual;
    
    const container = document.getElementById('etapas_retorno_container');
    container.innerHTML = '';
    
    const opcoesRetorno = [{
        valor: 'projetista',
        label: 'Projetista (avaliar alteracoes)'
    }];
    const pos_atual = fluxo_etapas.indexOf(os.etapa_atual);

    if (pos_atual > 0) {
        for (let i = 1; i < pos_atual; i++) {
            const etapa = fluxo_etapas[i];
            opcoesRetorno.push({
                valor: etapa,
                label: etapa.charAt(0).toUpperCase() + etapa.slice(1)
            });
        }
    }

    opcoesRetorno.forEach((opcao, index) => {
        const div = document.createElement('div');
        div.className = 'etapa-checkbox-item';
        div.innerHTML = `
            <input type="radio" name="etapa_destino" value="${opcao.valor}" id="etapa_${opcao.valor}" ${index === 0 ? 'checked' : ''} required>
            <label for="etapa_${opcao.valor}">${opcao.label}</label>
        `;
        container.appendChild(div);
    });
    
    document.getElementById('modalRetorno').classList.add('show');
}

function fecharModalRetorno() {
    document.getElementById('modalRetorno').classList.remove('show');
}

document.getElementById('formRetorno').onsubmit = function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('<?= SITE_URL; ?>/api/producao.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erro: ' + data.error);
        }
    });
};

window.onclick = function(event) {
    const modalOS = document.getElementById('modalOS');
    const modalRetorno = document.getElementById('modalRetorno');
    const lightboxModal = document.getElementById('lightboxModal');
    const alertaEntrega = document.getElementById('alertaEntregaOverlay');
    if (event.target === modalOS) fecharModal();
    if (event.target === modalRetorno) fecharModalRetorno();
    if (event.target === lightboxModal) fecharLightbox();
    if (event.target === alertaEntrega) fecharAlertaEntrega();
}
</script>

</div>
    </div>
    </div>
    <?php include '../../includes/footer_vendedor.php'; ?>


