<?php
/**
 * Painel de Gestão Completo da Produção
 * Dashboard visual moderno com KPIs, gráficos e alertas
 *
 * Acesso: master, gerente, dashboard_producao, producao
 */

require_once '../../config/config.php';
require_once '../../includes/workflow.php';
require_once '../../includes/engenharia.php';
require_once '../../includes/os_atrasadas.php';

$page_title = 'Painel de Gestão da Produção';
$db = getDB();
ensureEngenhariaSchema($db);
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
    'concluida' => '#28a745'
];

$labels_etapas = [
    'autorizacao' => 'Autorização',
    'engenharia' => 'Engenharia',
    'programacao' => 'Programação',
    'corte' => 'Corte',
    'dobra' => 'Dobra',
    'tubo' => 'Tubo',
    'solda' => 'Solda',
    'mobiliario' => 'Mobiliário',
    'coccao' => 'Cocção',
    'refrigeracao' => 'Refrigeração',
    'acabamento' => 'Acabamento',
    'montagem' => 'Montagem',
    'embalagem' => 'Embalagem',
    'finalizacao' => 'Finalização',
    'concluida' => 'Concluída'
];

// ===== KPI 1: O.S. EM PRODUÇÃO =====
$stmt = $db->query("SELECT COUNT(*) FROM ordens_servico WHERE status = 'em_producao'");
$kpi_os_producao = $stmt->fetchColumn() ?? 0;

// ===== KPI 2: CONCLUÍDAS ESTE MÊS =====
$stmt = $db->query("
    SELECT COUNT(*) FROM ordens_servico
    WHERE status = 'concluida'
    AND MONTH(updated_at) = MONTH(CURDATE())
    AND YEAR(updated_at) = YEAR(CURDATE())
");
$kpi_concluidas_mes = $stmt->fetchColumn() ?? 0;

// ===== KPI 3: ATRASADAS (CRÍTICAS) =====
$stmt = $db->query("
    SELECT COUNT(*) FROM ordens_servico
    WHERE status NOT IN ('concluida', 'cancelada')
    AND data_termino IS NOT NULL
    AND DATE(data_termino) < CURDATE()
    AND DATEDIFF(CURDATE(), DATE(data_termino)) >= 7
");
$kpi_criticas = $stmt->fetchColumn() ?? 0;

$stmt = $db->query("
    SELECT COUNT(*) FROM ordens_servico
    WHERE status NOT IN ('concluida', 'cancelada')
    AND data_termino IS NOT NULL
    AND DATE(data_termino) < CURDATE()
    AND DATEDIFF(CURDATE(), DATE(data_termino)) BETWEEN 3 AND 6
");
$kpi_urgentes = $stmt->fetchColumn() ?? 0;

$stmt = $db->query("
    SELECT COUNT(*) FROM ordens_servico
    WHERE status NOT IN ('concluida', 'cancelada')
    AND data_termino IS NOT NULL
    AND DATE(data_termino) < CURDATE()
    AND DATEDIFF(CURDATE(), DATE(data_termino)) < 3
");
$kpi_atrasadas = $stmt->fetchColumn() ?? 0;

// ===== KPI 4: CUMPRIMENTO DE PRAZOS % =====
$stmt = $db->query("
    SELECT
        COUNT(CASE WHEN data_termino IS NULL THEN 1 END) as sem_data,
        COUNT(CASE WHEN data_termino >= CURDATE() THEN 1 END) as no_prazo,
        COUNT(CASE WHEN data_termino < CURDATE() THEN 1 END) as atrasado,
        COUNT(*) as total
    FROM ordens_servico
    WHERE status NOT IN ('cancelada')
");
$prazo_data = $stmt->fetch();
$prazos_total = ($prazo_data['total'] ?? 1);
$prazos_cumprimento = $prazos_total > 0 ? round(($prazo_data['no_prazo'] ?? 0) * 100 / $prazos_total, 1) : 0;

// ===== KPI 5: VENDAS ESTE MÊS =====
$stmt = $db->query("
    SELECT COALESCE(SUM(valor_total), 0) as total
    FROM vendas
    WHERE MONTH(created_at) = MONTH(CURDATE())
    AND YEAR(created_at) = YEAR(CURDATE())
    AND status != 'cancelada'
");
$kpi_vendas_mes = $stmt->fetch()['total'] ?? 0;

// ===== KPI 6: PRODUÇÃO HOJE (ETAPAS INICIADAS) =====
$stmt = $db->query("
    SELECT COUNT(*) FROM os_etapas_producao
    WHERE DATE(data_inicio) = CURDATE()
");
$kpi_producao_hoje = $stmt->fetchColumn() ?? 0;

// ===== GRÁFICO 1: PRODUÇÃO POR SETOR =====
$stmt = $db->query("
    SELECT etapa_atual, COUNT(*) as total
    FROM ordens_servico
    WHERE status = 'em_producao'
    GROUP BY etapa_atual
    ORDER BY total DESC
");
$grafico_setor_labels = [];
$grafico_setor_valores = [];
$grafico_setor_cores = [];
foreach ($stmt->fetchAll() as $row) {
    $grafico_setor_labels[] = $labels_etapas[$row['etapa_atual']] ?? $row['etapa_atual'];
    $grafico_setor_valores[] = $row['total'];
    $grafico_setor_cores[] = $cores_etapas[$row['etapa_atual']] ?? '#adb5bd';
}

// ===== GRÁFICO 2: STATUS O.S. =====
$stmt = $db->query("
    SELECT status, COUNT(*) as total
    FROM ordens_servico
    WHERE status NOT IN ('cancelada')
    GROUP BY status
");
$grafico_status_labels = [];
$grafico_status_valores = [];
$grafico_status_cores = [];
$status_cores = ['em_revisao' => '#ffc107', 'em_producao' => '#007bff', 'concluida' => '#28a745'];
$status_nomes = ['em_revisao' => 'Em Revisão', 'em_producao' => 'Em Produção', 'concluida' => 'Concluída'];
foreach ($stmt->fetchAll() as $row) {
    $grafico_status_labels[] = $status_nomes[$row['status']] ?? ucfirst($row['status']);
    $grafico_status_valores[] = $row['total'];
    $grafico_status_cores[] = $status_cores[$row['status']] ?? '#adb5bd';
}

// ===== GRÁFICO 3: ATRASADAS POR CLIENTE =====
$stmt = $db->query("
    SELECT c.razao_social, COUNT(*) as total
    FROM ordens_servico os
    INNER JOIN clientes c ON os.cliente_id = c.id
    WHERE os.status NOT IN ('concluida', 'cancelada')
    AND os.data_termino IS NOT NULL
    AND DATE(os.data_termino) < CURDATE()
    GROUP BY c.razao_social
    ORDER BY total DESC
    LIMIT 8
");
$grafico_cliente_labels = [];
$grafico_cliente_valores = [];
foreach ($stmt->fetchAll() as $row) {
    $grafico_cliente_labels[] = substr($row['razao_social'], 0, 20);
    $grafico_cliente_valores[] = $row['total'];
}

// ===== GRÁFICO 4: TENDÊNCIA SEMANAL =====
$stmt = $db->query("
    SELECT
        DATE(updated_at) as data,
        COUNT(*) as total
    FROM ordens_servico
    WHERE status = 'concluida'
    AND updated_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(updated_at)
    ORDER BY data ASC
");
$grafico_semana_labels = [];
$grafico_semana_valores = [];
foreach ($stmt->fetchAll() as $row) {
    $grafico_semana_labels[] = date('D', strtotime($row['data']));
    $grafico_semana_valores[] = $row['total'];
}

// ===== ALERTAS CRÍTICOS =====
$stmt = $db->query("
    SELECT os.id, os.numero, os.data_termino, c.razao_social,
           DATEDIFF(CURDATE(), DATE(os.data_termino)) as dias_atraso
    FROM ordens_servico os
    INNER JOIN clientes c ON os.cliente_id = c.id
    WHERE os.status NOT IN ('concluida', 'cancelada')
    AND os.data_termino IS NOT NULL
    AND DATE(os.data_termino) < CURDATE()
    ORDER BY os.data_termino ASC
    LIMIT 15
");
$alertas_atrasadas = $stmt->fetchAll();

// ===== PRÓXIMAS ENTREGAS (7 DIAS) =====
$stmt = $db->query("
    SELECT os.id, os.numero, os.data_termino, c.razao_social
    FROM ordens_servico os
    INNER JOIN clientes c ON os.cliente_id = c.id
    WHERE os.status NOT IN ('concluida', 'cancelada')
    AND os.data_termino BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY os.data_termino ASC
    LIMIT 10
");
$entregas_proximas = $stmt->fetchAll();

include '../../includes/header_vendedor.php';
?>
<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-page-head">
            <h1 class="vend-page-title">📊 Painel de Gestão</h1>
        </div>
        <div class="vend-content">

<style>
    .painel-kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 30px;
    }

    .painel-kpi-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        border-left: 6px solid #D85A30;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .painel-kpi-card.blue { border-left-color: #007bff; }
    .painel-kpi-card.green { border-left-color: #28a745; }
    .painel-kpi-card.red { border-left-color: #dc3545; }
    .painel-kpi-card.orange { border-left-color: #fd7e14; }

    .painel-kpi-numero {
        font-size: 48px;
        font-weight: bold;
        line-height: 1;
        margin-bottom: 8px;
        color: #1a1a1a;
    }

    .painel-kpi-card.blue .painel-kpi-numero { color: #007bff; }
    .painel-kpi-card.green .painel-kpi-numero { color: #28a745; }
    .painel-kpi-card.red .painel-kpi-numero { color: #dc3545; }
    .painel-kpi-card.orange .painel-kpi-numero { color: #fd7e14; }

    .painel-kpi-label {
        font-size: 13px;
        color: #666;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .painel-kpi-sub {
        font-size: 11px;
        color: #999;
        margin-top: 6px;
    }

    .painel-graficos-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .painel-grafico-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .painel-grafico-titulo {
        font-size: 16px;
        font-weight: 700;
        margin-bottom: 15px;
        color: #1a1a1a;
    }

    .painel-grafico-container {
        position: relative;
        height: 300px;
    }

    .painel-alertas {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .painel-alerta-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .painel-alerta-titulo {
        font-size: 16px;
        font-weight: 700;
        margin-bottom: 15px;
        color: #1a1a1a;
    }

    .painel-alerta-item {
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 8px;
        font-size: 13px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .painel-alerta-critico {
        background: #ffe5e5;
        border-left: 4px solid #dc3545;
        color: #721c24;
    }

    .painel-alerta-urgente {
        background: #fff3cd;
        border-left: 4px solid #fd7e14;
        color: #856404;
    }

    .painel-alerta-aviso {
        background: #cfe2ff;
        border-left: 4px solid #007bff;
        color: #084298;
    }

    .painel-tabela-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid #eee;
        font-size: 13px;
    }

    .painel-tabela-item:last-child {
        border-bottom: none;
    }

    .painel-tabela-numero {
        font-weight: 700;
        color: #D85A30;
        min-width: 60px;
    }

    .painel-tabela-cliente {
        flex: 1;
        color: #333;
    }

    .painel-tabela-data {
        color: #999;
        font-size: 12px;
    }

    @media (max-width: 1200px) {
        .painel-graficos-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .painel-kpi-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .painel-kpi-numero {
            font-size: 36px;
        }

        .painel-grafico-container {
            height: 250px;
        }
    }
</style>

<!-- ===== KPIs ===== -->
<div class="painel-kpi-grid">
    <div class="painel-kpi-card blue">
        <div class="painel-kpi-numero"><?= $kpi_os_producao ?></div>
        <div class="painel-kpi-label">O.S. em Produção</div>
    </div>

    <div class="painel-kpi-card green">
        <div class="painel-kpi-numero"><?= $kpi_concluidas_mes ?></div>
        <div class="painel-kpi-label">Concluídas Este Mês</div>
    </div>

    <div class="painel-kpi-card red">
        <div class="painel-kpi-numero"><?= $kpi_criticas ?></div>
        <div class="painel-kpi-label">Atrasadas Críticas</div>
        <div class="painel-kpi-sub">
            🟠 <?= $kpi_urgentes ?> Urgentes | ⚠️ <?= $kpi_atrasadas ?> Avisos
        </div>
    </div>

    <div class="painel-kpi-card green">
        <div class="painel-kpi-numero"><?= $prazos_cumprimento ?>%</div>
        <div class="painel-kpi-label">Cumprimento de Prazos</div>
    </div>

    <div class="painel-kpi-card orange">
        <div class="painel-kpi-numero"><?= 'R$ ' . number_format($kpi_vendas_mes, 0, ',', '.') ?></div>
        <div class="painel-kpi-label">Vendas Este Mês</div>
    </div>

    <div class="painel-kpi-card blue">
        <div class="painel-kpi-numero"><?= $kpi_producao_hoje ?></div>
        <div class="painel-kpi-label">Etapas Iniciadas Hoje</div>
    </div>
</div>

<!-- ===== GRÁFICOS ===== -->
<div class="painel-graficos-grid">
    <!-- Gráfico 1: Produção por Setor -->
    <div class="painel-grafico-card">
        <div class="painel-grafico-titulo">📊 Produção por Setor</div>
        <div class="painel-grafico-container">
            <canvas id="grafico1"></canvas>
        </div>
    </div>

    <!-- Gráfico 2: Status O.S. -->
    <div class="painel-grafico-card">
        <div class="painel-grafico-titulo">📈 Status das O.S.</div>
        <div class="painel-grafico-container">
            <canvas id="grafico2"></canvas>
        </div>
    </div>

    <!-- Gráfico 3: Atrasadas por Cliente -->
    <div class="painel-grafico-card">
        <div class="painel-grafico-titulo">⚠️ Top Clientes com Atrasos</div>
        <div class="painel-grafico-container">
            <canvas id="grafico3"></canvas>
        </div>
    </div>

    <!-- Gráfico 4: Tendência Semanal -->
    <div class="painel-grafico-card">
        <div class="painel-grafico-titulo">📅 Produção Concluída (7 dias)</div>
        <div class="painel-grafico-container">
            <canvas id="grafico4"></canvas>
        </div>
    </div>
</div>

<!-- ===== ALERTAS E DETALHES ===== -->
<div class="painel-alertas">
    <!-- ALERTAS CRÍTICOS -->
    <div class="painel-alerta-card">
        <div class="painel-alerta-titulo">🚨 Pedidos Atrasados (<?= count($alertas_atrasadas) ?>)</div>
        <?php if (empty($alertas_atrasadas)): ?>
            <div style="color: #999; text-align: center; padding: 20px;">Nenhum pedido atrasado ✓</div>
        <?php else: ?>
            <?php foreach ($alertas_atrasadas as $alerta): ?>
                <?php
                $dias = $alerta['dias_atraso'] ?? 0;
                if ($dias >= 7) $classe = 'painel-alerta-critico';
                elseif ($dias >= 3) $classe = 'painel-alerta-urgente';
                else $classe = 'painel-alerta-aviso';
                ?>
                <div class="painel-alerta-item <?= $classe ?>">
                    <div>
                        <strong>OS <?= $alerta['numero'] ?></strong> - <?= substr($alerta['razao_social'], 0, 30) ?>
                        <br><small><?= date('d/m/Y', strtotime($alerta['data_termino'])) ?></small>
                    </div>
                    <div style="font-weight: bold;">-<?= $dias ?>d</div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- PRÓXIMAS ENTREGAS -->
    <div class="painel-alerta-card">
        <div class="painel-alerta-titulo">📅 Próximas Entregas (7 dias) — <?= count($entregas_proximas) ?></div>
        <?php if (empty($entregas_proximas)): ?>
            <div style="color: #999; text-align: center; padding: 20px;">Nenhuma entrega prevista</div>
        <?php else: ?>
            <?php foreach ($entregas_proximas as $entrega): ?>
                <div class="painel-tabela-item">
                    <div>
                        <strong style="color: #D85A30;">OS <?= $entrega['numero'] ?></strong><br>
                        <small><?= substr($entrega['razao_social'], 0, 35) ?></small>
                    </div>
                    <div class="painel-tabela-data">
                        <?= date('d/m', strtotime($entrega['data_termino'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

</div>
        </div>
    </div>

<script>
const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: { backgroundColor: '#1a1a1a', titleColor: '#fff', bodyColor: '#ddd' }
    },
    scales: {
        x: { grid: { display: false }, ticks: { color: '#888' } },
        y: { grid: { color: 'rgba(0,0,0,.05)' }, ticks: { color: '#888' } }
    }
};

// Gráfico 1: Produção por Setor (Barra)
new Chart(document.getElementById('grafico1'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($grafico_setor_labels) ?>,
        datasets: [{
            data: <?= json_encode($grafico_setor_valores) ?>,
            backgroundColor: <?= json_encode($grafico_setor_cores) ?>,
            borderRadius: 4
        }]
    },
    options: { ...chartOptions, indexAxis: 'y' }
});

// Gráfico 2: Status O.S. (Pizza)
new Chart(document.getElementById('grafico2'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($grafico_status_labels) ?>,
        datasets: [{
            data: <?= json_encode($grafico_status_valores) ?>,
            backgroundColor: <?= json_encode($grafico_status_cores) ?>,
            borderColor: '#fff',
            borderWidth: 2
        }]
    },
    options: { plugins: { legend: { display: true, position: 'bottom' } } }
});

// Gráfico 3: Atrasadas por Cliente (Barra)
new Chart(document.getElementById('grafico3'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($grafico_cliente_labels) ?>,
        datasets: [{
            data: <?= json_encode($grafico_cliente_valores) ?>,
            backgroundColor: '#dc3545',
            borderRadius: 4
        }]
    },
    options: { ...chartOptions, indexAxis: 'y', scales: { y: { grid: { display: false } } } }
});

// Gráfico 4: Tendência Semanal (Linha)
new Chart(document.getElementById('grafico4'), {
    type: 'line',
    data: {
        labels: <?= json_encode($grafico_semana_labels) ?>,
        datasets: [{
            label: 'O.S. Concluídas',
            data: <?= json_encode($grafico_semana_valores) ?>,
            borderColor: '#D85A30',
            backgroundColor: 'rgba(216,90,48,.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#D85A30',
            pointRadius: 5
        }]
    },
    options: chartOptions
});

// Auto-refresh a cada 60 segundos
setInterval(() => { location.reload(); }, 60000);
</script>

<?php include '../../includes/footer_vendedor.php'; ?>
