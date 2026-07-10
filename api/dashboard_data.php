<?php
// api/dashboard_data.php
require_once '../config/config.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$db = getDB();
$usuario = getCurrentUser();
$periodo = $_GET['periodo'] ?? 'mes';

// Métricas do vendedor
$metricas = [];
try {
    $stmt = $db->prepare("
        SELECT
            COUNT(DISTINCT v.id) AS total_vendas,
            COALESCE(SUM(v.valor_total), 0) AS total_valor,
            COUNT(DISTINCT CASE WHEN v.status = 'concluida' THEN v.id END) AS vendas_concluidas
        FROM vendas v
        WHERE v.usuario_id = ?
    ");
    $stmt->execute([$usuario['id']]);
    $metricas = $stmt->fetch();
} catch (Exception $e) {
    $metricas = ['total_vendas' => 0, 'total_valor' => 0, 'vendas_concluidas' => 0];
}

// Dados para gráfico de vendas por mês
$chartVendas = [];
try {
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(v.created_at, '%Y-%m') as mes,
            COUNT(*) as total,
            SUM(v.valor_total) as valor
        FROM vendas v
        WHERE v.usuario_id = ? AND v.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(v.created_at, '%Y-%m')
        ORDER BY mes
    ");
    $stmt->execute([$usuario['id']]);
    $rows = $stmt->fetchAll();
    foreach ($rows as $row) {
        $chartVendas[$row['mes']] = $row['valor'];
    }
} catch (Exception $e) {}

echo json_encode([
    'metricas' => $metricas,
    'chart_vendas' => $chartVendas,
]);