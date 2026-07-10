<?php
// salvar_edicao_venda.php — atualiza venda e regrava itens
session_start();
require 'includes/db.php';

if (!isset($_SESSION['usuario'])) { header("Location: login.php"); exit; }

function toFloat($v): float {
  if ($v === null || $v === '') return 0.0;
  $v = str_replace(['.', ','], ['', '.'], $v);
  return (float)$v;
}

try{
  if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception('Método inválido.');

  $id_venda = (int)($_POST['id_venda'] ?? 0);
  if ($id_venda<=0) throw new Exception('ID da venda inválido.');

  // Carrega venda para validar existência
  $chk = $conn->prepare("SELECT id FROM vendas WHERE id = ?");
  $chk->bind_param("i", $id_venda);
  $chk->execute();
  if (!$chk->get_result()->fetch_assoc()) throw new Exception('Venda não encontrada.');

  // Cabeçalho
  $status       = trim($_POST['status'] ?? 'aberta');
  $desconto     = toFloat($_POST['desconto'] ?? 0);
  $frete        = toFloat($_POST['frete'] ?? 0);
  $nome_cliente = trim($_POST['nome_cliente'] ?? '');
  $telefone     = trim($_POST['telefone'] ?? '');
  $email        = trim($_POST['email'] ?? '');
  $endereco     = trim($_POST['endereco'] ?? '');
  $cnpj         = trim($_POST['cnpj'] ?? '');
  $pagamento    = trim($_POST['pagamento'] ?? '');
  $entrega      = trim($_POST['entrega'] ?? '');
  $assinatura   = trim($_POST['assinatura'] ?? '');

  // Itens
  $items  = $_POST['item'] ?? [];
  $descs  = $_POST['descricao'] ?? [];
  $qtds   = $_POST['quantidade'] ?? [];
  $units  = $_POST['preco_unitario'] ?? [];
  $setors = $_POST['setor'] ?? [];
  $imgsAt = $_POST['imagem_atual'] ?? [];

  if (!is_array($items)) throw new Exception('Itens inválidos.');

  // Recalcula total produtos
  $linhas=[]; $totalProdutos=0.0;
  $N = max(count($items),count($descs),count($qtds),count($units),count($setors),count($imgsAt));
  for ($i=0; $i<$N; $i++){
    $item = trim($items[$i] ?? '');
    $desc = trim($descs[$i] ?? '');
    $q    = toFloat($qtds[$i] ?? 0);
    $u    = toFloat($units[$i] ?? 0);
    $set  = trim($setors[$i] ?? '');
    $imgAnt = $imgsAt[$i] ?? null;

    // upload opcional
    $img = $imgAnt;
    if (isset($_FILES['foto']['name'][$i]) && $_FILES['foto']['name'][$i] !== '') {
      $nomeArq = basename($_FILES['foto']['name'][$i]);
      $nomeArq = preg_replace('/[^A-Za-z0-9_.-]/', '_', $nomeArq);
      if (!is_dir('uploads')) { @mkdir('uploads', 0777, true); }
      $dest = 'uploads/'.time().'_'.$nomeArq;
      if (move_uploaded_file($_FILES['foto']['tmp_name'][$i], $dest)) { $img = $dest; }
    }

    // ignora linha vazia
    if ($item==='' && $desc==='' && $q<=0 && $u<=0 && !$img) continue;

    $sub = max(0,$q)*max(0,$u);
    $totalProdutos += $sub;

    $linhas[] = [
      'item'=>$item,'descricao'=>$desc,'quantidade'=>$q,'preco_unitario'=>$u,
      'preco_total'=>$sub,'imagem'=>$img,'setor'=>$set
    ];
  }

  if (!$linhas) throw new Exception('Inclua pelo menos um item.');

  $base       = $totalProdutos + $frete;
  $valorDesc  = $base * ($desconto/100.0);
  $totalFinal = $base - $valorDesc;

  // Atualiza cabeçalho
  $up = $conn->prepare("UPDATE vendas SET
      nome_cliente=?, telefone=?, email=?, endereco=?, cnpj=?,
      pagamento=?, entrega=?, assinatura=?, desconto=?, frete=?,
      total_produtos=?, total_final=?, status=?
      WHERE id=?");
  // tipos: s s s s s s s s d d d d s i  => "ssssssssddddsi"
  $up->bind_param(
    "ssssssssddddsi",
    $nome_cliente, $telefone, $email, $endereco, $cnpj,
    $pagamento, $entrega, $assinatura, $desconto, $frete,
    $totalProdutos, $totalFinal, $status, $id_venda
  );
  if (!$up->execute()) throw new Exception('Falha ao atualizar venda: '.$up->error);

  // Regrava itens
  $del = $conn->prepare("DELETE FROM venda_itens WHERE id_venda = ?");
  $del->bind_param("i", $id_venda);
  $del->execute();

  $ins = $conn->prepare("INSERT INTO venda_itens
    (id_venda, item, descricao, quantidade, preco_unitario, preco_total, imagem, setor)
    VALUES (?,?,?,?,?,?,?,?)");
  // tipos: i s s d d d s s => "issdddss"
  foreach ($linhas as $L) {
    $ins->bind_param(
      "issdddss",
      $id_venda,
      $L['item'], $L['descricao'],
      $L['quantidade'], $L['preco_unitario'], $L['preco_total'],
      $L['imagem'], $L['setor']
    );
    if (!$ins->execute()) throw new Exception('Falha ao inserir item: '.$ins->error);
  }

  $_SESSION['mensagem_sucesso'] = 'Venda atualizada com sucesso.';
  header("Location: vendas.php");
  exit;

}catch(Throwable $e){
  $_SESSION['mensagem_erro'] = 'Erro ao salvar edição: '.$e->getMessage();
  header("Location: vendas.php");
  exit;
}
