<?php
session_start();
require 'db.php';

if (!isset($_SESSION['usuario'])) { header('Location: login.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo_orcamento = $_POST['codigo_orcamento'] ?? '';
    $id_usuario = $_SESSION['id_usuario'];
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
    $data = date('Y-m-d H:i:s');
    $codigo_unico = uniqid('ORC');

    try {
        
        $stmt = $conn->prepare("INSERT INTO orcamentos (codigo_orcamento, codigo, id_usuario, nome_cliente, endereco, telefone, email, cnpj, pagamento, entrega, assinatura, desconto, frete, data_criacao) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssissssssssdds', $codigo_orcamento, $codigo_unico, $id_usuario, $nome, $endereco, $telefone, $email, $cnpj, $pagamento, $entrega, $assinatura, $desconto, $frete, $data);
        $stmt->execute();
        $id_orcamento = $conn->insert_id;

        if (isset($_POST['item']) && is_array($_POST['item'])) {
            foreach ($_POST['item'] as $i => $item) {
                $item = trim($item);
                $qtd = isset($_POST['quantidade'][$i]) ? (int)$_POST['quantidade'][$i] : 0;
                $desc = $_POST['descricao'][$i] ?? '';
                $preco_unit = isset($_POST['preco_unitario'][$i]) ? (float)$_POST['preco_unitario'][$i] : 0;
                $preco_total = $preco_unit * max(0,$qtd);
                $setor = $_POST['setor'][$i] ?? null; // pode vir vazio

                // Upload da foto
                $path = null;
                if (isset($_FILES['foto']['name'][$i]) && $_FILES['foto']['name'][$i] !== '') {
                    $foto = basename($_FILES['foto']['name'][$i]);
                    $foto = preg_replace('/[^A-Za-z0-9_.-]/', '_', $foto);
                    if (!is_dir('uploads')) { mkdir('uploads', 0777, true); }
                    $dest = 'uploads/' . time() . '_' . $foto;
                    if (move_uploaded_file($_FILES['foto']['tmp_name'][$i], $dest)) {
                        $path = $dest;
                    }
                }

                if ($item !== '' || $desc !== '' || $qtd > 0 || $preco_unit > 0 || $path) {
                    $stmt_item = $conn->prepare("INSERT INTO orcamento_itens (id_orcamento, item, quantidade, descricao, preco_unitario, preco_total, imagem, setor) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt_item->bind_param('isissdss', $id_orcamento, $item, $qtd, $desc, $preco_unit, $preco_total, $path, $setor);
                    $stmt_item->execute();
                }
            }
        }

        $_SESSION['mensagem_sucesso'] = 'Orçamento criado com sucesso!';
        header('Location: listar_orcamentos.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['mensagem_erro'] = 'Erro ao criar o orçamento: ' . $e->getMessage();
        header('Location: listar_orcamentos.php');
        exit;
    }
} else {
    $_SESSION['mensagem_erro'] = 'Acesso inválido.';
    header('Location: listar_orcamentos.php');
    exit;
}
?>
