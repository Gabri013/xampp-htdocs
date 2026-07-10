<?php
require_once '../../config/config.php';
require_once '../../includes/financeiro.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$motivo = sanitize($_POST['motivo'] ?? '');
$usuario = getCurrentUser();

if ($id <= 0 || $motivo === '') {
    echo json_encode(['success' => false, 'message' => 'ID e motivo são obrigatórios']);
    exit;
}

try {
    $db = getDB();
    ensureFinanceiroSchema($db);
    $db->beginTransaction();

    cancelarContasReceberPorVenda($db, $id, $usuario['id'], $motivo);
    $db->prepare("UPDATE ordens_servico SET status='cancelada' WHERE venda_id = ?")->execute([$id]);
    $db->prepare("UPDATE vendas SET status='cancelada' WHERE id = ?")->execute([$id]);

    $db->commit();
    echo json_encode(['success' => true, 'message' => 'Venda cancelada com sucesso']);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
