<?php
session_start();
require 'includes/db.php';
if (!isset($_SESSION['usuario'])) { header("Location: login.php"); exit; }

try {
  $stmt = $conn->prepare("INSERT INTO clientes (nome, documento, telefone, email, endereco) VALUES (?, ?, ?, ?, ?)");
  $stmt->bind_param("sssss",
    $_POST['nome'],
    $_POST['documento'],
    $_POST['telefone'],
    $_POST['email'],
    $_POST['endereco']
  );
  $stmt->execute();
  $_SESSION['mensagem_sucesso'] = "Cliente cadastrado com sucesso!";
} catch (Exception $e) {
  $_SESSION['mensagem_erro'] = "Erro ao salvar cliente: " . $e->getMessage();
}
header("Location: cadastro.php?tab=clientes");
exit;
