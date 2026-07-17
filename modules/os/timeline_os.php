<?php
/**
 * Timeline/Gantt Visual - Ver Progresso de O.S. Graficamente
 *
 * Inspirado no Nomus - Mostrar timeline horizontal com blocos por setor
 * Reutiliza: Chart.js, os_etapas_producao, cores do dashboard
 *
 * Acesso: master, gerente, dashboard_producao, producao
 */

require_once '../../config/config.php';
require_once '../../includes/workflow.php';

$page_title = 'Timeline de Produção';
$db = getDB();
ensureOrdensServicoIndependentesSchema($db);
requirePermission(['master', 'gerente', 'dashboard_producao', 'producao']);

// ===== CORES E LABELS =====
$cores_etapas = [
    'autorizacao' => '#6c757d',
    'corte' => '#007bff',
    'dobra' => '#6610f2',
    'tubo' => '#17a2b8',
    'solda' => '#fd7e14',
    'mobiliario' => '#20c997',
    'coccao' => '#0ea5e9',
    'refrigeracao' => '#0ea5e9',
    'acabamento' => '#20c997',
    'montagem' => '#17a2b8',
    'embalagem' => '#6c757d',
    'programacao' => '#6610f2',
    'engenharia' => '#007bff',
    'finalizacao' => '#28a745',
];

$labels_etapas = [
    'autorizacao' => 'Aut.',
    'engenharia' => 'Eng.',
    'programacao' => 'Prog.',
    'corte' => 'Corte',
    'dobra' => 'Dobra',
    'tubo' => 'Tubo',
    'solda' => 'Solda',
    'mobiliario' => 'Mob.',
    'coccao' => 'Coç.',
    'refrigeracao' => 'Refr.',
    'acabamento' => 'Acab.',
    'montagem' => 'Mont.',
    'embalagem' => 'Emb.',
    'finalizacao' => 'Final.',
];

// ===== BUSCAR O.S. COM HISTÓRICO DE ETAPAS =====
$os_id = $_GET['os_id'] ?? null;

if (!$os_id) {
    // Mostrar últimas 10 O.S. em produção
    $stmt = $db->query("
        SELECT os.*, c.razao_social
        FROM ordens_servico os
        INNER JOIN clientes c ON os.cliente_id = c.id
        WHERE os.status = 'em_producao'
        ORDER BY os.data_inicio DESC
        LIMIT 10
    ");
    $os_lista = $stmt->fetchAll();
    $os_atual = null;
} else {
    // Buscar O.S. específica com histórico
    $stmt = $db->prepare("
        SELECT os.*, c.razao_social
        FROM ordens_servico os
        INNER JOIN clientes c ON os.cliente_id = c.id
        WHERE os.id = ?
    ");
    $stmt->execute([(int)$os_id]);
    $os_atual = $stmt->fetch();
    $os_lista = [];

    if ($os_atual) {
        // Buscar histórico de etapas
        $stmt = $db->prepare("
            SELECT oep.*,
                   COALESCE(oep.tempo_total_segundos, 0) as duracao,
                   DATE_FORMAT(oep.data_inicio, '%H:%i') as hora_inicio,
                   DATE_FORMAT(oep.data_fim, '%H:%i') as hora_fim,
                   DATEDIFF(oep.data_fim, oep.data_inicio) as dias_decorridos
            FROM os_etapas_producao oep
            WHERE oep.os_id = ?
            ORDER BY oep.data_inicio ASC
        ");
        $stmt->execute([(int)$os_id]);
        $etapas_historico = $stmt->fetchAll();
    }
}

// Calcular timeline (se temos O.S. atual)
if ($os_atual && !empty($etapas_historico)) {
    $data_inicio_global = $etapas_historico[0]['data_inicio'];
    $data_fim_global = end($etapas_historico)['data_fim'] ?? date('Y-m-d H:i:s');
    $duracao_total = strtotime($data_fim_global) - strtotime($data_inicio_global);
}

include '../../includes/header_vendedor.php';
?>

<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-page-head">
            <h1 class="vend-page-title">📊 Timeline de Produção</h1>
        </div>
        <div class="vend-content">

<style>
    .timeline-container {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .timeline-os-selector {
        background: white;
        padding: 16px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 12px;
    }

    .timeline-os-item {
        padding: 12px;
        border: 2px solid #ddd;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        color: inherit;
        background: #fafafa;
    }

    .timeline-os-item:hover {
        border-color: #D85A30;
        background: #fef0ea;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(216,90,48,0.15);
    }

    .timeline-os-numero {
        font-size: 16px;
        font-weight: 700;
        color: #D85A30;
    }

    .timeline-os-cliente {
        font-size: 12px;
        color: #666;
        margin-top: 4px;
    }

    /* GANTT CHART STYLES */
    .gantt-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow-x: auto;
        padding: 20px;
    }

    .gantt-header {
        display: flex;
        gap: 8px;
        margin-bottom: 20px;
        align-items: center;
    }

    .gantt-os-info {
        font-size: 18px;
        font-weight: 700;
    }

    .gantt-os-cliente {
        font-size: 14px;
        color: #666;
    }

    .gantt-timeline {
        display: flex;
        gap: 0;
        position: relative;
        min-height: 60px;
        align-items: center;
        margin-bottom: 30px;
    }

    .gantt-etapa {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 12px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 12px;
        color: white;
        white-space: nowrap;
        position: relative;
        min-width: 80px;
        justify-content: center;
    }

    .gantt-etapa::after {
        content: '→';
        position: absolute;
        right: -12px;
        font-size: 18px;
        color: #ddd;
        z-index: 1;
    }

    .gantt-etapa:last-child::after {
        display: none;
    }

    .gantt-etapa-label {
        display: block;
        font-size: 11px;
        margin-top: 4px;
        opacity: 0.9;
    }

    .gantt-duracao {
        font-size: 10px;
        opacity: 0.8;
        display: block;
        margin-top: 2px;
    }

    /* PROGRESS BAR */
    .gantt-progress {
        position: relative;
        width: 100%;
        height: 8px;
        background: #e0e0e0;
        border-radius: 4px;
        margin-top: 15px;
        overflow: hidden;
    }

    .gantt-progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #D85A30, #ef4444);
        border-radius: 4px;
        transition: width 0.3s;
    }

    .gantt-progress-label {
        font-size: 12px;
        color: #666;
        margin-top: 8px;
    }

    /* STATS */
    .gantt-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 12px;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 2px solid #eee;
    }

    .gantt-stat {
        text-align: center;
        padding: 12px;
        background: #f9f9f9;
        border-radius: 8px;
    }

    .gantt-stat-label {
        font-size: 12px;
        color: #666;
        font-weight: 600;
        text-transform: uppercase;
    }

    .gantt-stat-valor {
        font-size: 20px;
        font-weight: 700;
        color: #D85A30;
        margin-top: 4px;
    }

    /* LISTA DE ETAPAS */
    .etapas-lista {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow: hidden;
    }

    .etapas-lista-header {
        background: #f9f9f9;
        padding: 16px;
        border-bottom: 2px solid #eee;
        font-weight: 700;
        font-size: 14px;
    }

    .etapas-lista-item {
        padding: 16px;
        border-bottom: 1px solid #eee;
        display: grid;
        grid-template-columns: 120px 1fr 100px 100px;
        gap: 16px;
        align-items: center;
    }

    .etapas-lista-item:last-child {
        border-bottom: none;
    }

    .etapas-lista-etapa {
        font-weight: 700;
        color: #333;
    }

    .etapas-lista-duracao {
        font-size: 12px;
        color: #666;
    }

    .etapas-lista-status {
        font-size: 12px;
        padding: 4px 8px;
        border-radius: 4px;
        font-weight: 600;
        text-align: center;
    }

    .etapas-lista-status.concluida {
        background: #dbeafe;
        color: #0369a1;
    }

    .etapas-lista-status.em-andamento {
        background: #fef3c7;
        color: #b45309;
    }

    @media (max-width: 768px) {
        .timeline-os-selector {
            grid-template-columns: 1fr;
        }

        .gantt-timeline {
            flex-wrap: wrap;
        }

        .gantt-etapa {
            min-width: 60px;
            padding: 8px;
            font-size: 11px;
        }

        .etapas-lista-item {
            grid-template-columns: 1fr;
            gap: 8px;
        }
    }
</style>

<?php if (!$os_atual): ?>
    <!-- SELETOR DE O.S. -->
    <div class="vend-card">
        <div class="vend-card-head">
            <h3>📋 Selecione uma O.S. para Ver a Timeline</h3>
        </div>
        <div class="vend-card-body">
            <?php if (!empty($os_lista)): ?>
                <div class="timeline-os-selector">
                    <?php foreach ($os_lista as $os): ?>
                        <a href="?os_id=<?= $os['id'] ?>" class="timeline-os-item">
                            <div class="timeline-os-numero">OS <?= htmlspecialchars($os['numero']) ?></div>
                            <div class="timeline-os-cliente"><?= htmlspecialchars(substr($os['razao_social'], 0, 30)) ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #999;">
                    <p>Nenhuma O.S. em produção</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>
    <!-- TIMELINE/GANTT CHART -->
    <div class="timeline-container">
        <div class="gantt-container">
            <div class="gantt-header">
                <div>
                    <div class="gantt-os-info">OS <?= htmlspecialchars($os_atual['numero']) ?></div>
                    <div class="gantt-os-cliente"><?= htmlspecialchars($os_atual['razao_social']) ?></div>
                </div>
            </div>

            <!-- GANTT TIMELINE -->
            <div class="gantt-timeline">
                <?php foreach ($etapas_historico as $etapa): ?>
                    <div class="gantt-etapa" style="background-color: <?= $cores_etapas[$etapa['etapa']] ?? '#999' ?>;">
                        <span><?= $labels_etapas[$etapa['etapa']] ?? substr($etapa['etapa'], 0, 3) ?></span>
                        <span class="gantt-duracao">
                            <?php
                            $horas = intval($etapa['duracao'] / 3600);
                            $minutos = intval(($etapa['duracao'] % 3600) / 60);
                            echo $horas > 0 ? "{$horas}h" : "{$minutos}m";
                            ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- PROGRESS BAR -->
            <div class="gantt-progress">
                <div class="gantt-progress-bar" style="width: 100%;"></div>
            </div>
            <div class="gantt-progress-label">
                ✓ Progresso: <?= count($etapas_historico) ?> etapas
                | ⏱️ Total:
                <?php
                $horas_total = intval($duracao_total / 3600);
                $dias_total = intval($horas_total / 24);
                echo $dias_total > 0 ? "{$dias_total}d " : "";
                echo ($horas_total % 24) . "h";
                ?>
            </div>

            <!-- STATS -->
            <div class="gantt-stats">
                <div class="gantt-stat">
                    <div class="gantt-stat-label">Etapas Concluídas</div>
                    <div class="gantt-stat-valor"><?= count($etapas_historico) ?></div>
                </div>
                <div class="gantt-stat">
                    <div class="gantt-stat-label">Tempo Total</div>
                    <div class="gantt-stat-valor">
                        <?php
                        $horas = intval($duracao_total / 3600);
                        $dias = intval($horas / 24);
                        echo $dias > 0 ? "{$dias}d" : "{$horas}h";
                        ?>
                    </div>
                </div>
                <div class="gantt-stat">
                    <div class="gantt-stat-label">Etapa Atual</div>
                    <div class="gantt-stat-valor">
                        <?= $labels_etapas[$os_atual['etapa_atual']] ?? $os_atual['etapa_atual'] ?>
                    </div>
                </div>
                <div class="gantt-stat">
                    <div class="gantt-stat-label">Status</div>
                    <div class="gantt-stat-valor">
                        <?= ucfirst(str_replace('_', ' ', $os_atual['status'])) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- LISTA DETALHADA -->
        <div class="etapas-lista">
            <div class="etapas-lista-header">
                📋 Histórico Detalhado de Etapas
            </div>
            <?php foreach ($etapas_historico as $etapa): ?>
                <div class="etapas-lista-item">
                    <div class="etapas-lista-etapa">
                        <?= $labels_etapas[$etapa['etapa']] ?? $etapa['etapa'] ?>
                    </div>
                    <div class="etapas-lista-duracao">
                        📅 <?= date('d/m H:i', strtotime($etapa['data_inicio'])) ?>
                        <?php if ($etapa['data_fim']): ?>
                            → <?= date('H:i', strtotime($etapa['data_fim'])) ?>
                        <?php endif; ?>
                    </div>
                    <div class="etapas-lista-duracao">
                        <?php
                        if ($etapa['duracao'] > 0) {
                            $h = intval($etapa['duracao'] / 3600);
                            $m = intval(($etapa['duracao'] % 3600) / 60);
                            echo $h > 0 ? "{$h}h {$m}m" : "{$m}m";
                        } else {
                            echo "Em andamento...";
                        }
                        ?>
                    </div>
                    <div class="etapas-lista-status <?= strtolower(str_replace('_', '-', $etapa['status'])) ?>">
                        <?= ucfirst($etapa['status']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        // Auto-refresh a cada 30 segundos
        setInterval(() => { location.reload(); }, 30000);
    </script>

<?php endif; ?>

</div>
        </div>
    </div>

<?php include '../../includes/footer_vendedor.php'; ?>
