<?php
require_once '../config/config.php';
header('Content-Type: application/json');

if (!isLoggedIn() || !in_array($_SESSION['usuario_tipo'] ?? '', ['master', 'gerente'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permissão negada']);
    exit;
}

try {
    $db = getDB();
    processarMotorNotificacoes($db);
    echo json_encode(['success' => true, 'message' => 'Notificações processadas']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
