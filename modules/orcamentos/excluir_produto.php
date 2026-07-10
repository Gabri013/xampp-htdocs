<?php
session_start();
require 'includes/db.php';
if (!isset($_SESSION['usuario'])) { header("Location: login.php"); exit; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id > 0) {
  try {
    // apagar imagem do disco se existir
    $q = $conn->prepare("SELECT imagem FROM produtos WHERE id = ?");
    $q->bind_param("i", $id);
    $q->execute();
    $img = $q->get_result()->fetch_assoc();
    if ($img && !empty($img['imagem']) && file_exists($img['imagem'])) {
      @unlink($img['imagem']);
    }

    $stmt = $conn->prepare("DELETE FROM produtos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $_SESSION['mensagem_sucesso'] = "Produto excluído.";
  } catch (Exception $e) {
    $_SESSION['mensagem_erro'] = "Erro ao excluir: " . $e->getMessage();
  }
}
header("Location: cadastro.php?tab=produtos");
exit;
