<?php
// API de produtos para autocomplete
require_once dirname(__DIR__) . '/config/config.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');
$db = getDB();

$q = $_GET['q'] ?? '';
$limit = (int)($_GET['limit'] ?? 10);

if (mb_strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT p.id, p.nome, p.descricao, p.preco_base, p.sku, p.imagem, c.nome as categoria FROM produtos p LEFT JOIN categorias c ON c.id = p.categoria_id WHERE p.nome LIKE ? OR p.descricao LIKE ? OR p.sku LIKE ? ORDER BY p.nome ASC LIMIT ?";

$stmt = $db->prepare($sql);
$term = "%$q%";
$stmt->execute([$term, $term, $term, $limit]);
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($produtos as &$p) {
    $p['id'] = (int)$p['id'];
    $p['preco_base'] = (float)$p['preco_base'];
}
echo json_encode($produtos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
