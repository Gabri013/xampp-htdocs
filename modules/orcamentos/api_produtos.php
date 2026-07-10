<?php
// API de produtos para autocomplete
require_once dirname(__DIR__) . '/config/config.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');

$db = getDB();
$q = trim($_GET['q'] ?? '');
if ($q === '' || mb_strlen($q) < 2) { echo json_encode([]); exit; }

$sql = "SELECT id, nome, descricao, preco_base, unidade, sku FROM produtos WHERE nome LIKE ? OR sku LIKE ? ORDER BY nome LIMIT 20";
$stmt = $db->prepare($sql);
$like = "%$q%";
$stmt->execute([$like, $like]);
$out = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
