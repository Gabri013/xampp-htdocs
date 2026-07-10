<?php
session_start();
require 'includes/db.php';
if (!isset($_SESSION['usuario'])) { header("Location: login.php"); exit; }

$nome = $_POST['nome'] ?? '';
$descricao = $_POST['descricao'] ?? '';
$preco_base = isset($_POST['preco_base']) ? (float)$_POST['preco_base'] : 0;
$unidade = $_POST['unidade'] ?? 'un';
$sku = $_POST['sku'] ?? null;
$img_path = null;

try {
  // upload imagem (opcional)
  if (isset($_FILES['imagem']) && $_FILES['imagem']['name'] !== '') {
    if (!is_dir('uploads_produtos')) { mkdir('uploads_produtos', 0777, true); }
    $nome_arquivo = preg_replace('/[^A-Za-z0-9_.-]/', '_', basename($_FILES['imagem']['name']));
    $dest = 'uploads_produtos/' . time() . '_' . $nome_arquivo;
    if (move_uploaded_file($_FILES['imagem']['tmp_name'], $dest)) {
      $img_path = $dest;
    }
  }

  $stmt = $conn->prepare("INSERT INTO produtos (nome, descricao, preco_base, unidade, sku, imagem) VALUES (?, ?, ?, ?, ?, ?)");
  $stmt->bind_param("ssdsss", $nome, $descricao, $preco_base, $unidade, $sku, $img_path);
  $stmt->execute();

  $_SESSION['mensagem_sucesso'] = "Produto cadastrado com sucesso!";
} catch (Exception $e) {
  $_SESSION['mensagem_erro'] = "Erro ao salvar produto: " . $e->getMessage();
}
header("Location: cadastro.php?tab=produtos");
exit;
