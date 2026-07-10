<?php
// Estoque_check.php - Verifica estoque disponível para produto
header('Content-Type: application/json');

// Carrega dados de estoque (em produção, viria do banco de dados)
$estoqueData = include __DIR__ . '/estoque_data.php';

$produto_id = isset($_GET['produto_id']) ? intval($_GET['produto_id']) : 0;
$quantidade = isset($_GET['qtd']) ? floatval($_GET['qtd']) : 0;

if($produto_id <= 0 || $quantidade <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Parâmetros inválidos.'
    ]);
    exit;
}

if(!isset($estoqueData[$produto_id])) {
    echo json_encode([
        'success' => false,
        'message' => 'Produto não encontrado.'
    ]);
    exit;
}

$estoqueTotal = $estoqueData[$produto_id]['estoque'];
$reservado = $estoqueData[$produto_id]['reservado'];
$estoqueDisponivel = max(0, $estoqueTotal - $reservado);

// Se a quantidade solicitada for maior que o estoque disponível, retorna o disponível
if($quantidade > $estoqueDisponivel) {
    echo json_encode([
        'success' => true,
        'estoque_disponivel' => $estoqueDisponivel,
        'suficiente' => false
    ]);
} else {
    echo json_encode([
        'success' => true,
        'estoque_disponivel' => $estoqueDisponivel,
        'suficiente' => true
    ]);
}
?>