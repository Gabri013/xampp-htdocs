<?php
session_start();
require 'db.php';
if (!isset($_SESSION['usuario'])) { header('Location: login.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id_orc = (int)($_POST['id_orcamento'] ?? 0);
  if (!$id_orc) { die('ID inválido'); }

  $nome = $_POST['nome_cliente'] ?? '';
  $endereco = $_POST['endereco'] ?? '';
  $telefone = $_POST['telefone'] ?? '';
  $email = $_POST['email'] ?? '';
  $cnpj = $_POST['cnpj'] ?? '';
  $pagamento = $_POST['forma_pagamento'] ?? '';
  $entrega = $_POST['condicoes_entrega'] ?? '';
  $assinatura = $_POST['assinatura_vendedor'] ?? '';
  $desconto = isset($_POST['desconto']) ? (float)$_POST['desconto'] : 0;
  $frete = isset($_POST['frete']) ? (float)$_POST['frete'] : 0;

  // Atualiza orçamento principal
  $stmt = $conn->prepare("UPDATE orcamentos SET nome_cliente=?, endereco=?, telefone=?, email=?, cnpj=?, pagamento=?, entrega=?, assinatura=?, desconto=?, frete=? WHERE id=?");
  $stmt->bind_param('ssssssssddi', $nome, $endereco, $telefone, $email, $cnpj, $pagamento, $entrega, $assinatura, $desconto, $frete, $id_orc);
  $stmt->execute();

  // Limpa itens antigos
  $del = $conn->prepare("DELETE FROM orcamento_itens WHERE id_orcamento = ?");
  $del->bind_param('i', $id_orc);
  $del->execute();

  // Reinsere itens
  if (isset($_POST['item']) && is_array($_POST['item'])) {
    foreach ($_POST['item'] as $i => $nomeItem) {
      $nomeItem = trim($nomeItem);
      $qtd = isset($_POST['quantidade'][$i]) ? (int)$_POST['quantidade'][$i] : 0;
      $desc = $_POST['descricao'][$i] ?? '';
      $unit = isset($_POST['preco_unitario'][$i]) ? (float)$_POST['preco_unitario'][$i] : 0;
      $total = $unit * max(0,$qtd);
      $setor = $_POST['setor'][$i] ?? null;

      $imagem_atual = $_POST['imagem_atual'][$i] ?? null;
      $path = $imagem_atual; // mantém se não houver upload novo
      if (isset($_FILES['foto']['name'][$i]) && $_FILES['foto']['name'][$i] !== '') {
        $foto = basename($_FILES['foto']['name'][$i]);
        $foto = preg_replace('/[^A-Za-z0-9_.-]/', '_', $foto);
        if (!is_dir('uploads')) { mkdir('uploads', 0777, true); }
        $dest = 'uploads/' . time() . '_' . $foto;
        if (move_uploaded_file($_FILES['foto']['tmp_name'][$i], $dest)) { $path = $dest; }
      }

      if ($nomeItem !== '' || $desc !== '' || $qtd > 0 || $unit > 0 || $path) {
        $ins = $conn->prepare("INSERT INTO orcamento_itens (id_orcamento, item, quantidade, descricao, preco_unitario, preco_total, imagem, setor) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $ins->bind_param('isissdss', $id_orc, $nomeItem, $qtd, $desc, $unit, $total, $path, $setor);
        $ins->execute();
      }
    }
  }

  $_SESSION['mensagem_sucesso'] = 'Orçamento atualizado!';
  header('Location: listar_orcamentos.php');
  exit;
} else {
  header('Location: listar_orcamentos.php');
  exit;
}
?>
