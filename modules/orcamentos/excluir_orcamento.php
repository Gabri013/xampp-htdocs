<?php
// excluir_orcamento.php - Exclui orçamento e seus itens
session_start();
require 'db.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    $_SESSION['mensagem_erro'] = "ID do orçamento não informado.";
    header("Location: listar_orcamentos.php");
    exit;
}

$id_orcamento = intval($_GET['id']);

try {
    // Primeiro excluir os itens relacionados
    $stmt_itens = $conn->prepare("DELETE FROM orcamento_itens WHERE id_orcamento = ?");
    $stmt_itens->bind_param("i", $id_orcamento);
    $stmt_itens->execute();

    // Excluir o orçamento
    $stmt = $conn->prepare("DELETE FROM orcamentos WHERE id = ?");
    $stmt->bind_param("i", $id_orcamento);
    $stmt->execute();

    $_SESSION['mensagem_sucesso'] = "Orçamento excluído com sucesso!";
    header("Location: listar_orcamentos.php");
    exit;
} catch (Exception $e) {
    $_SESSION['mensagem_erro'] = "Erro ao excluir o orçamento.";
    header("Location: listar_orcamentos.php");
    exit;
}
?>
