<?php
// Retorna JSON de clientes para autocomplete (id, nome, documento, telefone, email, endereco)
require_once dirname(__DIR__) . '/config/config.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');

$db = getDB();
$q = trim($_GET['q'] ?? '');
if ($q === '' || mb_strlen($q) < 2) { echo json_encode([]); exit; }

$sql = "SELECT id, razao_social as nome, documento, telefone, email, endereco FROM clientes WHERE razao_social LIKE ? OR documento LIKE ? OR email LIKE ? OR telefone LIKE ? ORDER BY razao_social LIMIT 20";
$stmt = $db->prepare($sql);
$like = "%$q%";
$stmt->execute([$like, $like, $like, $like]);
$out = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($out as &$r) {
    $r['id'] = (int)$r['id'];
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
