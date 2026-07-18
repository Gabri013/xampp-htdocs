<?php
/**
 * API de Produtos — listagem para selects/autocomplete.
 *
 * GET /api/produtos.php?acao=listar[&q=texto]
 * Retorno: { sucesso: true, produtos: [ { id, nome, codigo } ] }
 *
 * Acesso: qualquer usuário logado.
 */

require_once '../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'erro' => 'Não autorizado']);
    exit;
}

$db = getDB();
$acao = $_GET['acao'] ?? $_POST['acao'] ?? 'listar';

try {
    if ($acao === 'listar') {
        $q = trim($_GET['q'] ?? '');
        if ($q !== '') {
            $stmt = $db->prepare(
                "SELECT id, nome, codigo FROM produtos
                 WHERE status = 'ativo' AND (nome LIKE ? OR codigo LIKE ?)
                 ORDER BY nome LIMIT 100"
            );
            $like = "%$q%";
            $stmt->execute([$like, $like]);
        } else {
            $stmt = $db->query(
                "SELECT id, nome, codigo FROM produtos
                 WHERE status = 'ativo' ORDER BY nome LIMIT 500"
            );
        }
        $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($produtos as &$p) {
            $p['id'] = (int) $p['id'];
        }
        echo json_encode(['sucesso' => true, 'produtos' => $produtos], JSON_UNESCAPED_UNICODE);
        exit;
    }

    throw new Exception('Ação não encontrada: ' . $acao);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}
