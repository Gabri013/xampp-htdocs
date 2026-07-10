<?php
// includes/validators.php
// Centraliza validações de negócio para reutilização em todos os módulos

/**
 * Verifica disponibilidade de estoque
 * @param int $produtoId ID do produto
 * @param int $qtd Quantidade solicitada
 * @return array [disponivel=>int, suficiente=>bool]
 */
function getAvailableStock(int $produtoId, int $qtd): array {
    $estoqueFile = BASE_PATH . '/api/estoque_data.php';
    if (!file_exists($estoqueFile)) {
        return ['disponivel' => 0, 'suficiente' => false];
    }
    $estoqueData = include $estoqueFile;
    $disponivel = $estoqueData[$produtoId]['estoque'] ?? 0;
    $suficiente = $qtd <= $disponivel;
    return ['disponivel' => $disponivel, 'suficiente' => $suficiente];
}

/**
 * Valida dimensões do projeto
 * @param array $proj Dados do projeto
 * @return array [valid=>bool, mensagem=>string]
 */
function validateProjectDimensions(array $proj): array {
    $required = ['largura', 'altura', 'espessura'];
    foreach ($required as $dim) {
        if (empty($proj[$dim]) || $proj[$dim] <= 0) {
            return ['valid' => false, 'mensagem' => 'Campo "' . $dim . '" obrigatório e maior que zero.'];
        }
    }
    return ['valid' => true, 'mensagem' => 'Dimensões válidas.'];
}

/**
 * Envia notificação por e-mail (uso genérico)
 */
function sendNotification(string $to, string $subject, string $body): bool {
    $headers = "From: noreply@cozinca.com\r\n";
    return mail($to, $subject, $body, $headers);
}

/**
 * Registra evento na tabela audit_log
 */
function logEvent(int $userId, string $action, string $details) {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO audit_log (user_id, action, details, created_at) VALUES (?,?,?,NOW())");
        $stmt->execute([$userId, $action, $details]);
    } catch (Exception $e) {
        error_log('Audit log error: ' . $e->getMessage());
    }
}
?>