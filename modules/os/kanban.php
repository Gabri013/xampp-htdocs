<?php
require_once '../../config/config.php';
require_once '../../includes/workflow.php';
requirePermission(['master', 'gerente', 'producao', 'vendedor', 'projetista']);

$page_title = 'Kanban de O.S.';
$db = getDB();
$usuario = getCurrentUser();

// Colunas do quadro = status do fluxo comercial/produtivo
$colunas = [
    'pendente'    => ['label' => 'Pendente',     'cor' => '#64748b'],
    'em_projeto'  => ['label' => 'Em Projeto',   'cor' => '#0284c7'],
    'proposta'    => ['label' => 'Proposta',     'cor' => '#7c3aed'],
    'em_revisao'  => ['label' => 'Em Revisão',   'cor' => '#d97706'],
    'em_producao' => ['label' => 'Em Produção',  'cor' => '#D85A30'],
    'concluida'   => ['label' => 'Concluída',    'cor' => '#16a34a'],
];

// Vendedor vê apenas as próprias O.S.; gestão vê todas
$sqlBase = "
    SELECT os.id, os.numero, os.status, os.etapa_atual, os.prioridade, os.data_termino,
           c.razao_social,
           COALESCE(v.numero, 'Independente') AS venda_numero,
           v.usuario_id AS vendedor_id
    FROM ordens_servico os
    INNER JOIN clientes c ON c.id = os.cliente_id
    LEFT JOIN vendas v ON v.id = os.venda_id
    WHERE os.status <> 'cancelada'
";
$params = [];
if (!hasPermission(['master', 'gerente', 'producao', 'projetista'])) {
    $sqlBase .= " AND (v.usuario_id = ? OR os.venda_id IS NULL)";
    $params[] = $usuario['id'];
}
$sqlBase .= " ORDER BY FIELD(os.prioridade, 'vermelho', 'amarelo', 'verde'), os.data_termino IS NULL, os.data_termino ASC";

$stmt = $db->prepare($sqlBase);
$stmt->execute($params);
$todas = $stmt->fetchAll();

// Concluídas: só as 15 mais recentes para não poluir o quadro
$ordens = [];
$concluidas = 0;
foreach ($todas as $o) {
    if ($o['status'] === 'concluida') {
        $concluidas++;
        if ($concluidas > 15) continue;
    }
    $ordens[] = $o;
}

$hoje = date('Y-m-d');
include '../../includes/header_vendedor.php';
?>
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/kanban.css?v=<?php echo @filemtime(BASE_PATH . '/assets/css/kanban.css') ?: '1'; ?>">

<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-page-head">
            <div>
                <h1 class="vend-page-title">Kanban de O.S.</h1>
                <p class="vend-page-sub">Arraste um cartão para mudar o status — as regras do fluxo são validadas</p>
            </div>
            <a href="vendedor.php" class="vbtn-sm"><i class="fas fa-table"></i> Ver em tabela</a>
        </div>

        <div class="vend-kanban" id="vendKanban">
            <div class="vend-kanban-board" id="vendKanbanBoard">
                <?php foreach ($colunas as $statusKey => $col):
                    $cards = array_values(array_filter($ordens, fn($o) => $o['status'] === $statusKey));
                ?>
                <div class="vend-kanban-column" data-status="<?php echo $statusKey; ?>">
                    <div class="vend-kanban-header" style="border-top:3px solid <?php echo $col['cor']; ?>">
                        <span class="vend-kanban-title"><?php echo $col['label']; ?></span>
                        <span class="vend-kanban-count"><?php echo count($cards); ?></span>
                    </div>
                    <div class="vend-kanban-items">
                        <?php foreach ($cards as $o):
                            $prazoClass = '';
                            $prazoTxt = '';
                            if (!empty($o['data_termino']) && $o['status'] !== 'concluida') {
                                $prazoTxt = formatDate($o['data_termino']);
                                if ($o['data_termino'] < $hoje) $prazoClass = 'kb-prazo-atrasado';
                                elseif ($o['data_termino'] <= date('Y-m-d', strtotime('+3 days'))) $prazoClass = 'kb-prazo-perto';
                            }
                        ?>
                        <div class="vend-kanban-card" draggable="true" data-id="<?php echo (int)$o['id']; ?>">
                            <div class="kb-card-top">
                                <span class="kb-num"><?php echo htmlspecialchars($o['numero']); ?></span>
                                <span class="kb-prio kb-prio-<?php echo htmlspecialchars($o['prioridade'] ?: 'verde'); ?>" title="Prioridade <?php echo htmlspecialchars($o['prioridade'] ?: 'verde'); ?>"></span>
                            </div>
                            <div class="vend-kanban-card-title"><?php echo htmlspecialchars($o['razao_social']); ?></div>
                            <div class="kb-card-meta">
                                <?php if ($o['status'] === 'em_producao' && !empty($o['etapa_atual'])): ?>
                                    <span class="kb-etapa"><i class="fas fa-industry"></i> <?php echo htmlspecialchars(getEtapaLabel($o['etapa_atual'] ?? '')); ?></span>
                                <?php endif; ?>
                                <?php if ($prazoTxt !== ''): ?>
                                    <span class="kb-prazo <?php echo $prazoClass; ?>"><i class="far fa-calendar"></i> <?php echo $prazoTxt; ?></span>
                                <?php endif; ?>
                            </div>
                            <a class="kb-card-link" href="os_detalhes.php?os_id=<?php echo (int)$o['id']; ?>" title="Abrir O.S."><i class="fas fa-external-link-alt"></i></a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer_vendedor.php'; ?>
