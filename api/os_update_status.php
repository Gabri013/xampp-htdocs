<?php
// api/os_update_status.php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/workflow.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = (int)($data['id'] ?? 0);
$status = $data['status'] ?? '';

if (!$id || !$status) {
    echo json_encode(['success' => false, 'message' => 'ID e status são obrigatórios']);
    exit;
}

$validStatuses = getValidOSStatuses();
if (!in_array($status, $validStatuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Status inválido']);
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT status FROM ordens_servico WHERE id = ?");
$stmt->execute([$id]);
$current = (string) $stmt->fetchColumn();

$validation = validateOSStatusTransition($current, $status, $_SESSION['usuario_tipo'] ?? '');
if (!$validation['valid']) {
    echo json_encode(['success' => false, 'message' => $validation['message']]);
    exit;
}

$stmt = $db->prepare("UPDATE ordens_servico SET status = ?, atualizado_em = NOW() WHERE id = ?");
$stmt->execute([$status, $id]);

echo json_encode(['success' => true, 'message' => 'Status atualizado com sucesso']);