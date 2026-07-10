<?php
// salvar_orcamento.php - Armazena orçamento e produtos no banco
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();
    require 'db.php';

    $codigo = $_POST['codigo_orcamento'];
    $id_usuario = $_SESSION['id_usuario'];
    $nome = $_POST['nome_cliente'];
    $endereco = $_POST['endereco'];
    $telefone = $_POST['telefone'];
    $email = $_POST['email'];
    $cnpj = $_POST['cnpj'];
    $pagamento = $_POST['forma_pagamento'];
    $entrega = $_POST['condicoes_entrega'];
    $assinatura = $_POST['assinatura_vendedor'];
    $data = date("Y-m-d H:i:s");

    $conn->query("INSERT INTO orcamentos (codigo_orcamento, id_usuario, nome_cliente, endereco, telefone, email, cnpj, forma_pagamento, condicoes_entrega, assinatura_vendedor, data_criacao)
        VALUES ('$codigo', '$id_usuario', '$nome', '$endereco', '$telefone', '$email', '$cnpj', '$pagamento', '$entrega', '$assinatura', '$data')");

    $id_orcamento = $conn->insert_id;

    foreach ($_POST['item'] as $i => $item) {
        $qtd = $_POST['quantidade'][$i];
        $desc = $_POST['descricao'][$i];
        $preco_unit = $_POST['preco_unitario'][$i];
        $foto = $_FILES['foto']['name'][$i];
        $foto_tmp = $_FILES['foto']['tmp_name'][$i];
        $path = "uploads/" . basename($foto);
        move_uploaded_file($foto_tmp, $path);
        $preco_total = $preco_unit * $qtd;

        $conn->query("INSERT INTO itens_orcamento (id_orcamento, item, quantidade, foto_url, descricao, preco_unitario, preco_total)
            VALUES ('$id_orcamento', '$item', '$qtd', '$path', '$desc', '$preco_unit', '$preco_total')");
    }

    header("Location: dashboard.php");
    exit;
}
?>
