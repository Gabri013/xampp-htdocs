<?php
// vendas_detalhe.php — retorna JSON com a venda e seus itens
session_start();
require 'includes/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
  echo json_encode(['ok'=>false,'msg'=>'Não autenticado.']); exit;
}
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  echo json_encode(['ok'=>false,'msg'=>'ID inválido.']); exit;
}
$id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT * FROM vendas WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$venda = $stmt->get_result()->fetch_assoc();

if (!$venda) { echo json_encode(['ok'=>false,'msg'=>'Venda não encontrada.']); exit; }

$itens = [];
$sti = $conn->prepare("SELECT id, item, descricao, quantidade, preco_unitario, preco_total, imagem, setor FROM venda_itens WHERE id_venda = ? ORDER BY id");
$sti->bind_param("i", $id);
$sti->execute();
$resi = $sti->get_result();
while($row = $resi->fetch_assoc()){ $itens[]=$row; }

echo json_encode([
  'ok'=>true,
  'venda'=>$venda,
  'itens'=>$itens
], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
