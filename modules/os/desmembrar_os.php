<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

$os_id = (int)($_POST['os_id'] ?? 0);
$setor = $_POST['setor'] ?? '';

$setor_validos = ['corte', 'dobra', 'solda', 'refrigeracao', 'acabamento', 'montagem'];

if ($os_id <= 0 || !in_array($setor, $setor_validos)) {
    echo json_encode(['success' => false, 'error' => 'Dados inválidos.']);
    exit;
}

$usuario = getCurrentUser();
if (!hasPermission(['master', 'projetista', 'gerente'])) {
    echo json_encode(['success' => false, 'error' => 'Sem permissão.']);
    exit;
}

$db = getDB();

$stmt = $db->prepare("UPDATE ordens_servico SET etapa_atual=?, status='em_producao' WHERE id=?");
$stmt->execute([$setor, $os_id]);

echo json_encode(['success' => true]);