<?php
session_start();
require 'includes/db.php';
if (!isset($_SESSION['usuario'])) { header("Location: login.php"); exit; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id > 0) {
  try {
    $stmt = $conn->prepare("DELETE FROM clientes WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $_SESSION['mensagem_sucesso'] = "Cliente excluído.";
  } catch (Exception $e) {
    $_SESSION['mensagem_erro'] = "Erro ao excluir: " . $e->getMessage();
  }
}
header("Location: cadastro.php?tab=clientes");
exit;
