<?php
require_once '../config/config.php';
require_once '../includes/financeiro.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

$usuario_logado = getCurrentUser();
if (!$usuario_logado || !in_array($usuario_logado['tipo'], ['master', 'vendedor'])) {
    echo json_encode(['success' => false, 'message' => 'Permissão negada']);
    exit;
}

$id = $_POST['id'] ?? null;
$motivo = sanitize($_POST['motivo'] ?? '');

if (!$id || !$motivo) {
    echo json_encode(['success' => false, 'message' => 'ID e motivo são obrigatórios']);
    exit;
}

try {
    $db = getDB();
    ensureFinanceiroSchema($db);
    
    // Buscar dados da venda antes de excluir
    $stmt = $db->prepare("SELECT * FROM vendas WHERE id = ?");
    $stmt->execute([$id]);
    $venda = $stmt->fetch();
    
    if (!$venda) {
        echo json_encode(['success' => false, 'message' => 'Venda não encontrada']);
        exit;
    }
    
    // Restrição para vendedores
    if ($usuario_logado['tipo'] === 'vendedor' && $venda['usuario_id'] != $usuario_logado['id']) {
        echo json_encode(['success' => false, 'message' => 'Você não tem permissão para excluir esta venda']);
        exit;
    }
    
    // Buscar itens para o log
    $stmt = $db->prepare("SELECT * FROM vendas_itens WHERE venda_id = ?");
    $stmt->execute([$id]);
    $itens = $stmt->fetchAll();
    
    $venda_dados = [
        'venda' => $venda,
        'itens' => $itens
    ];
    
    $db->beginTransaction();
    
    // Salvar log de exclusão
    $stmt = $db->prepare("INSERT INTO logs_exclusao_vendas (venda_numero, venda_dados_json, motivo, usuario_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$venda['numero'], json_encode($venda_dados), $motivo, $usuario_logado['id']]);
    
    // 1. Cancelar financeiro automaticamente
    cancelarContasReceberPorVenda($db, $id, $usuario_logado['id'], $motivo);

    // 2. Cancelar O.S relacionada
    $stmt = $db->prepare("UPDATE ordens_servico SET status='cancelada' WHERE venda_id = ?");
    $stmt->execute([$id]);
    
    // 3. Cancelar venda (preserva rastreabilidade)
    $stmt = $db->prepare("UPDATE vendas SET status='cancelada' WHERE id = ?");
    $stmt->execute([$id]);
    
    $db->commit();
    echo json_encode(['success' => true, 'message' => 'Venda cancelada e financeiro cancelado com sucesso']);
    
} catch (Exception $e) {
    if(isset($db)) $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir: ' . $e->getMessage()]);
}
