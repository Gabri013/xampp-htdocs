<?php
// salvar_venda_manual.php — cria venda manual a partir do modal
session_start();

// AJUSTE o caminho conforme seu projeto:
require 'includes/db.php';

if (!isset($_SESSION['usuario'])) {
  header("Location: login.php");
  exit;
}

date_default_timezone_set('America/Sao_Paulo');

function gerarCodigoVenda(mysqli $conn): string {
  $sql = "SELECT id FROM vendas ORDER BY id DESC LIMIT 1";
  $st  = $conn->prepare($sql);
  if (!$st) { throw new Exception("Prepare falhou: ".$conn->error); }
  $st->execute();
  $res = $st->get_result()->fetch_assoc();
  $ultimo_id = $res ? (int)$res['id'] : 0;
  $novo_id = $ultimo_id + 1;
  return (string)$novo_id;
}


function toFloat($v): float {
  // aceita "1.234,56" / "1234,56" / "1234.56" / "1234"
  if (is_null($v) || $v === '') return 0.0;
  $v = str_replace(['.', ','], ['', '.'], $v); // remove milhar, normaliza decimal
  return (float)$v;
}

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new Exception('Método inválido.');
  }

  $idUsuario = (int)($_SESSION['id_usuario'] ?? 0);
  $usuario   = (string)($_SESSION['usuario'] ?? '');

  // --- Cliente (por ID ou manual) ---
  $cliente_id = isset($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : 0;
  $nome_cliente = trim($_POST['nome_cliente'] ?? '');
  $telefone     = trim($_POST['telefone'] ?? '');
  $email        = trim($_POST['email'] ?? '');
  $endereco     = trim($_POST['endereco'] ?? '');
  $cnpj         = trim($_POST['cnpj'] ?? '');

  if ($cliente_id > 0) {
    $stc = $conn->prepare("SELECT nome, telefone, email, endereco, documento AS cnpj FROM clientes WHERE id = ?");
    if (!$stc) { throw new Exception("Prepare cliente falhou: ".$conn->error); }
    $stc->bind_param("i", $cliente_id);
    $stc->execute();
    $cli = $stc->get_result()->fetch_assoc();
    if ($cli) {
      $nome_cliente = $cli['nome'] ?? $nome_cliente;
      $telefone     = $cli['telefone'] ?? $telefone;
      $email        = $cli['email'] ?? $email;
      $endereco     = $cli['endereco'] ?? $endereco;
      $cnpj         = $cli['cnpj'] ?? $cnpj;
    }
  }

  if ($nome_cliente === '') {
    throw new Exception('Informe o cliente ou selecione um cliente cadastrado.');
  }

  // --- Condições ---
  $pagamento  = trim($_POST['forma_pagamento'] ?? '');
  $entrega    = trim($_POST['condicoes_entrega'] ?? '');
  $assinatura = trim($_POST['assinatura_vendedor'] ?? '');
  $descontoP  = toFloat($_POST['desconto'] ?? 0);
  $frete      = toFloat($_POST['frete'] ?? 0);

  // --- Itens ---
  $itens       = $_POST['item']            ?? [];
  $descricoes  = $_POST['descricao']       ?? [];
  $qtds        = $_POST['quantidade']      ?? [];
  $precos      = $_POST['preco_unitario']  ?? [];
  $setores     = $_POST['setor']           ?? [];

  if (!is_array($itens) || !is_array($qtds) || !is_array($precos)) {
    throw new Exception('Itens inválidos.');
  }

  $totalProdutos = 0.0;
  $linhas = [];

  $count = max(count($itens), count($descricoes), count($qtds), count($precos), count($setores));
  for ($i=0; $i<$count; $i++) {
    $nomeItem = trim($itens[$i] ?? '');
    $desc     = trim($descricoes[$i] ?? '');
    $qtd      = (float)toFloat($qtds[$i] ?? 0);
    $preco    = (float)toFloat($precos[$i] ?? 0);
    $setor    = trim($setores[$i] ?? '');

    // upload opcional
    $imagem = null;
    if (isset($_FILES['foto']['name'][$i]) && $_FILES['foto']['name'][$i] !== '') {
      $nomeArq = basename($_FILES['foto']['name'][$i]);
      $nomeArq = preg_replace('/[^A-Za-z0-9_.-]/', '_', $nomeArq);
      if (!is_dir('uploads')) { @mkdir('uploads', 0777, true); }
      $dest = 'uploads/'.time().'_'.$nomeArq;
      if (move_uploaded_file($_FILES['foto']['tmp_name'][$i], $dest)) {
        $imagem = $dest;
      }
    }

    // ignora linhas totalmente vazias
    if ($nomeItem === '' && $desc === '' && $qtd <= 0 && $preco <= 0 && !$imagem) {
      continue;
    }

    $subtotal = max(0, $qtd) * max(0, $preco);
    $totalProdutos += $subtotal;

    $linhas[] = [
      'item' => $nomeItem,
      'descricao' => $desc,
      'quantidade' => $qtd,
      'preco_unitario' => $preco,
      'preco_total' => $subtotal,
      'imagem' => $imagem,
      'setor' => $setor,
    ];
  }

  if (empty($linhas)) {
    throw new Exception('Inclua pelo menos um item na venda.');
  }

  $totalGeral   = $totalProdutos + $frete;
  $valorDesc    = $totalGeral * ($descontoP/100);
  $totalFinal   = $totalGeral - $valorDesc;

  // --- Inserir venda ---
  $codigo_venda = gerarCodigoVenda($conn);
  $status       = 'aberta';
  $agora        = date('Y-m-d H:i:s');

  $sqlVenda = "INSERT INTO vendas
    (codigo_venda, id_usuario, nome_cliente, telefone, email, endereco, cnpj,
     pagamento, entrega, assinatura, desconto, frete, total_produtos, total_final,
     status, criado_em)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

  $st = $conn->prepare($sqlVenda);
  if (!$st) { throw new Exception("Prepare venda falhou: ".$conn->error); }

  // tipos: s i s s s s s s s s d d d d s s  => "sissssssssddddss"
  $st->bind_param(
    "sissssssssddddss",
    $codigo_venda,
    $idUsuario,
    $nome_cliente,
    $telefone,
    $email,
    $endereco,
    $cnpj,
    $pagamento,
    $entrega,
    $assinatura,
    $descontoP,
    $frete,
    $totalProdutos,
    $totalFinal,
    $status,
    $agora
  );

  if (!$st->execute()) {
    throw new Exception("Falha ao inserir venda: ".$st->error);
  }

  $id_venda = $conn->insert_id;

  // --- Inserir itens ---
  $sqlItem = "INSERT INTO venda_itens
    (id_venda, item, descricao, quantidade, preco_unitario, preco_total, imagem, setor)
    VALUES (?,?,?,?,?,?,?,?)";
  $sti = $conn->prepare($sqlItem);
  if (!$sti) { throw new Exception("Prepare itens falhou: ".$conn->error); }

  foreach ($linhas as $L) {
    // tipos: i s s d d d s s  => "issdddss"
    $sti->bind_param(
      "issdddss",
      $id_venda,
      $L['item'],
      $L['descricao'],
      $L['quantidade'],
      $L['preco_unitario'],
      $L['preco_total'],
      $L['imagem'],
      $L['setor']
    );
    if (!$sti->execute()) {
      throw new Exception("Falha ao inserir item: ".$sti->error);
    }
  }

  $_SESSION['mensagem_sucesso'] = "Venda #{$codigo_venda} criada com sucesso.";
  header("Location: vendas.php");
  exit;

} catch (Throwable $e) {
  // Mostra erro amigável e guarda detalhes em sessão
  $_SESSION['mensagem_erro'] = "Erro ao salvar venda: ".$e->getMessage();
  header("Location: vendas.php");
  exit;
}
