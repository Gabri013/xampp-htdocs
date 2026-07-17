<?php
/**
 * API de Dados do Painel de Gestão em Real-Time
 * Retorna JSON com KPIs e gráficos para atualização sem reload
 */

require_once '../config/config.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$db = getDB();

// KPI 1: O.S. em Produção
$kpi1 = $db->query("SELECT COUNT(*) FROM ordens_servico WHERE status = 'em_producao'")->fetchColumn() ?? 0;

// KPI 2: Concluídas este mês
$kpi2 = $db->query("
    SELECT COUNT(*) FROM ordens_servico
    WHERE status = 'concluida'
    AND MONTH(updated_at) = MONTH(CURDATE())
    AND YEAR(updated_at) = YEAR(CURDATE())
")->fetchColumn() ?? 0;

// KPI 3: Atrasadas críticas
$kpi3 = $db->query("
    SELECT COUNT(*) FROM ordens_servico
    WHERE status NOT IN ('concluida', 'cancelada')
    AND data_termino IS NOT NULL
    AND DATE(data_termino) < CURDATE()
    AND DATEDIFF(CURDATE(), DATE(data_termino)) >= 7
")->fetchColumn() ?? 0;

// KPI 3b: Urgentes
$kpi3b = $db->query("
    SELECT COUNT(*) FROM ordens_servico
    WHERE status NOT IN ('concluida', 'cancelada')
    AND data_termino IS NOT NULL
    AND DATE(data_termino) < CURDATE()
    AND DATEDIFF(CURDATE(), DATE(data_termino)) BETWEEN 3 AND 6
")->fetchColumn() ?? 0;

// KPI 3c: Avisos
$kpi3c = $db->query("
    SELECT COUNT(*) FROM ordens_servico
    WHERE status NOT IN ('concluida', 'cancelada')
    AND data_termino IS NOT NULL
    AND DATE(data_termino) < CURDATE()
    AND DATEDIFF(CURDATE(), DATE(data_termino)) < 3
")->fetchColumn() ?? 0;

// KPI 4: Cumprimento de prazos %
$prazo_data = $db->query("
    SELECT
        COUNT(CASE WHEN data_termino >= CURDATE() THEN 1 END) as no_prazo,
        COUNT(*) as total
    FROM ordens_servico
    WHERE status NOT IN ('cancelada')
")->fetch();
$prazos_total = ($prazo_data['total'] ?? 1);
$kpi4 = $prazos_total > 0 ? round(($prazo_data['no_prazo'] ?? 0) * 100 / $prazos_total, 1) : 0;

// KPI 5: Vendas este mês
$kpi5 = $db->query("
    SELECT COALESCE(SUM(valor_total), 0) as total
    FROM vendas
    WHERE MONTH(created_at) = MONTH(CURDATE())
    AND YEAR(created_at) = YEAR(CURDATE())
    AND status != 'cancelada'
")->fetch()['total'] ?? 0;

// KPI 6: Produção hoje
$kpi6 = $db->query("
    SELECT COUNT(*) FROM os_etapas_producao
    WHERE DATE(data_inicio) = CURDATE()
")->fetchColumn() ?? 0;

// Gráfico 1: Produção por setor
$grafico1 = [];
$stmt = $db->query("
    SELECT etapa_atual, COUNT(*) as total
    FROM ordens_servico
    WHERE status = 'em_producao'
    GROUP BY etapa_atual
    ORDER BY total DESC
");
foreach ($stmt->fetchAll() as $row) {
    $grafico1[] = ['label' => $row['etapa_atual'], 'valor' => $row['total']];
}

// Gráfico 2: Status O.S.
$grafico2 = [];
$stmt = $db->query("
    SELECT status, COUNT(*) as total
    FROM ordens_servico
    WHERE status NOT IN ('cancelada')
    GROUP BY status
");
foreach ($stmt->fetchAll() as $row) {
    $grafico2[] = ['label' => $row['status'], 'valor' => $row['total']];
}

// Alertas: Atrasadas
$alertas = [];
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
foreach ($stmt->fetchAll() as $row) {
    $alertas[] = [
        'numero' => $row['numero'],
        'cliente' => substr($row['razao_social'], 0, 30),
        'data' => $row['data_termino'],
        'dias' => $row['dias_atraso']
    ];
}

// Vencendo amanhã
$vencendo_amanha = [];
$stmt = $db->query("
    SELECT os.id, os.numero, os.data_termino, c.razao_social
    FROM ordens_servico os
    INNER JOIN clientes c ON os.cliente_id = c.id
    WHERE os.status NOT IN ('concluida', 'cancelada')
    AND os.data_termino = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
    ORDER BY os.numero ASC
    LIMIT 10
");
foreach ($stmt->fetchAll() as $row) {
    $vencendo_amanha[] = [
        'numero' => $row['numero'],
        'cliente' => substr($row['razao_social'], 0, 35),
        'data' => $row['data_termino']
    ];
}

echo json_encode([
    'success' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'kpis' => [
        'producao' => $kpi1,
        'concluidas' => $kpi2,
        'criticas' => $kpi3,
        'urgentes' => $kpi3b,
        'avisos' => $kpi3c,
        'prazos' => $kpi4,
        'vendas' => $kpi5,
        'hoje' => $kpi6
    ],
    'graficos' => [
        'setor' => $grafico1,
        'status' => $grafico2
    ],
    'alertas' => $alertas,
    'vencendo_amanha' => $vencendo_amanha
], JSON_PRETTY_PRINT);
?>
