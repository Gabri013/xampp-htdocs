<?php
// API de produtos para autocomplete
require_once dirname(__DIR__) . '/config/config.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');
$db = getDB();

$q = $_GET['q'] ?? '';
$limit = max(1, min(50, (int)($_GET['limit'] ?? 10)));

if (mb_strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT p.id, p.nome, p.descricao, p.valor as preco_base, p.codigo as sku, p.foto as imagem, c.nome as categoria FROM produtos p LEFT JOIN produto_categorias c ON c.id = p.categoria_id WHERE p.nome LIKE ? OR p.descricao LIKE ? OR p.codigo LIKE ? ORDER BY p.nome ASC LIMIT $limit";

$stmt = $db->prepare($sql);
$term = "%$q%";
$stmt->execute([$term, $term, $term]);
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($produtos as &$p) {
    $p['id'] = (int)$p['id'];
    $p['preco_base'] = (float)$p['preco_base'];
}
echo json_encode($produtos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
