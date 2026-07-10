<?php
// listar_orcamentos.php - Lista de orçamentos com design moderno e paginação estilizada
session_start();
require 'db.php';
$timezone = new DateTimeZone('America/Sao_Paulo');

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

$mensagem = "";
$classe_mensagem = "";
if (isset($_SESSION['mensagem_sucesso'])) {
    $mensagem = $_SESSION['mensagem_sucesso'];
    $classe_mensagem = "mensagem sucesso";
    unset($_SESSION['mensagem_sucesso']);
}
if (isset($_SESSION['mensagem_erro'])) {
    $mensagem = $_SESSION['mensagem_erro'];
    $classe_mensagem = "mensagem erro";
    unset($_SESSION['mensagem_erro']);
}

$id_usuario = $_SESSION['id_usuario'];
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';

// Paginação
$registros_por_pagina = isset($_GET['registros']) ? (int)$_GET['registros'] : 15;
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_atual - 1) * $registros_por_pagina;

// Contar total de registros
if ($busca !== '') {
    $like = "%$busca%";
    $stmt_count = $conn->prepare("SELECT COUNT(*) as total FROM orcamentos o JOIN usuarios u ON o.id_usuario = u.id WHERE o.id_usuario = ? AND (o.nome_cliente LIKE ? OR o.codigo_orcamento LIKE ?)");
    $stmt_count->bind_param("iss", $id_usuario, $like, $like);
} else {
    $stmt_count = $conn->prepare("SELECT COUNT(*) as total FROM orcamentos o JOIN usuarios u ON o.id_usuario = u.id WHERE o.id_usuario = ?");
    $stmt_count->bind_param("i", $id_usuario);
}
$stmt_count->execute();
$total_registros = $stmt_count->get_result()->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Buscar registros
if ($busca !== '') {
    $stmt = $conn->prepare("SELECT o.id, o.codigo_orcamento, o.nome_cliente, o.data_criacao, u.usuario FROM orcamentos o JOIN usuarios u ON o.id_usuario = u.id WHERE o.id_usuario = ? AND (o.nome_cliente LIKE ? OR o.codigo_orcamento LIKE ?) ORDER BY o.data_criacao DESC LIMIT ?, ?");
    $like = "%$busca%";
    $stmt->bind_param("issii", $id_usuario, $like, $like, $offset, $registros_por_pagina);
} else {
    $stmt = $conn->prepare("SELECT o.id, o.codigo_orcamento, o.nome_cliente, o.data_criacao, u.usuario FROM orcamentos o JOIN usuarios u ON o.id_usuario = u.id WHERE o.id_usuario = ? ORDER BY o.data_criacao DESC LIMIT ?, ?");
    $stmt->bind_param("iii", $id_usuario, $offset, $registros_por_pagina);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Lista de Orçamentos - Cozinca Inox</title>
<style>
    body { font-family: 'Segoe UI', Roboto, sans-serif; background: #f7f8fa; margin: 0; padding: 0; }
    .menu { background: #1c1c1c; display: flex; justify-content: space-between; align-items: center; padding: 10px 20px; }
    .menu img { height: 50px; }
    .menu-links a { color: #ff530d; text-decoration: none; font-weight: bold; margin-left: 15px; }
    .menu-links a:hover { color: #ffcb0c; }
    .container { padding: 20px; margin-left: auto; margin-right: auto; max-width: 1100px; }
    .mensagem { text-align: center; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-weight: bold; }
    .sucesso { background: #d4edda; color: #155724; }
    .erro { background: #f8d7da; color: #721c24; }
    .busca { text-align: center; margin: 20px 0; }
    .busca input[type=text] { width: 40%; padding: 10px; border-radius: 8px; border: 1px solid #ccc; font-size: 1em; }
    .busca button { padding: 10px 20px; border: none; border-radius: 8px; background: #ff530d; color: white; font-weight: bold; cursor: pointer; }
    .busca button:hover { background: #ffcb0c; color: #1c1c1c; }
    table { width: 100%; border-collapse: collapse; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
    th, td { padding: 12px; text-align: left; font-size: 1em; }
    th { background: #1c1c1c; color: #fff; }
    tr:nth-child(even) { background: #f2f2f2; }
    tr:hover { background: #fffbf2; }
    a.acao { text-decoration: none; font-weight: bold; color: #ff530d; margin: 0 5px; }
    a.acao:hover { color: #ffcb0c; }
    .paginacao { display: flex; justify-content: space-between; align-items: center; margin-top: 15px; font-size: 0.9em; }
    .paginacao select { padding: 5px; border-radius: 5px; border: 1px solid #ccc; }
    .paginacao a { padding: 6px 10px; border: 1px solid #ff530d; margin: 0 2px; text-decoration: none; color: #ff530d; border-radius: 5px; font-weight: bold; }
    .paginacao a.ativo { background: #ff530d; color: #fff; }
    .paginacao a:hover { background: #ffcb0c; color: #1c1c1c; }
</style>
</head>
<body>
<div class="menu">
    <img src="imagens/cozincainox.png" alt="Logo Cozinca Inox">
    <div class="menu-links">
        <a href="dashboard.php">🏠 Painel</a>
        <a href="criar_orcamento.php">➕ Novo Orçamento</a>
        <a href="listar_orcamentos.php">📄 Listar Orçamentos</a>
    </div>
</div>
<div class="container">
    <?php if ($mensagem): ?>
        <div class="<?= $classe_mensagem ?>"><?= $mensagem ?></div>
    <?php endif; ?>
    <div class="busca">
        <form method="GET" action="">
            <input type="text" name="busca" placeholder="Buscar por nome ou código" value="<?= htmlspecialchars($busca) ?>">
            <button type="submit">🔍 Buscar</button>
            <?php if ($busca !== ''): ?>
                <a href="listar_orcamentos.php">Limpar</a>
            <?php endif; ?>
        </form>
    </div>
    <table>
        <tr>
            <th>Código</th>
            <th>Cliente</th>
            <th>Data</th>
            <th>Usuário</th>
            <th>Ações</th>
        </tr>
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
            <?php 
                $data = new DateTime($row['data_criacao']);
                $data->setTimezone($timezone);
            ?>
            <tr>
                <td><?= $row['codigo_orcamento'] ?></td>
                <td><?= $row['nome_cliente'] ?></td>
                <td><?= $data->format('d/m/Y H:i') ?></td>
                <td><?= $row['usuario'] ?></td>
                <td>
                    <a class="btn btn-sm btn-success"
   href="transformar_em_venda.php?id=<?= (int)$row['id'] ?>"
   onclick="return confirm('Transformar este orçamento em venda?');">
  Transformar em venda
</a>
                    <a class="acao" href="gerar_pdf.php?id=<?= $row['id'] ?>" target="_blank">🖨 Imprimir</a>
                    <a class="acao" href="editar_orcamento.php?id=<?= $row['id'] ?>">✏️ Editar</a>
                    <a class="acao" href="excluir_orcamento.php?id=<?= $row['id'] ?>" onclick="return confirm('Deseja realmente excluir este orçamento?')">🗑 Excluir</a>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="5" style="text-align:center; font-weight:bold; color:#ff530d;">Nenhum orçamento encontrado.</td></tr>
        <?php endif; ?>
    </table>

    <div class="paginacao">
        <div>
            Mostrar
            <form method="GET" action="" style="display:inline;">
                <select name="registros" onchange="this.form.submit()">
                    <option value="10" <?= $registros_por_pagina == 10 ? 'selected' : '' ?>>10</option>
                    <option value="15" <?= $registros_por_pagina == 15 ? 'selected' : '' ?>>15</option>
                    <option value="25" <?= $registros_por_pagina == 25 ? 'selected' : '' ?>>25</option>
                    <option value="50" <?= $registros_por_pagina == 50 ? 'selected' : '' ?>>50</option>
                </select>
                registros por página
            </form>
        </div>
        <div>
            <?php if ($pagina_atual > 1): ?>
                <a href="?pagina=1&registros=<?= $registros_por_pagina ?>&busca=<?= $busca ?>"><< Primeira</a>
                <a href="?pagina=<?= $pagina_atual - 1 ?>&registros=<?= $registros_por_pagina ?>&busca=<?= $busca ?>">< Anterior</a>
            <?php endif; ?>
            <span><strong><?= $pagina_atual ?>/<?= $total_paginas ?></strong></span>
            <?php if ($pagina_atual < $total_paginas): ?>
                <a href="?pagina=<?= $pagina_atual + 1 ?>&registros=<?= $registros_por_pagina ?>&busca=<?= $busca ?>">Próxima ></a>
                <a href="?pagina=<?= $total_paginas ?>&registros=<?= $registros_por_pagina ?>&busca=<?= $busca ?>">Última >></a>
            <?php endif; ?>
        </div>
        <div>
            Mostrando <?= $offset + 1 ?> até <?= min($offset + $registros_por_pagina, $total_registros) ?> de <?= $total_registros ?> registros encontrados.
        </div>
    </div>
</div>
</body>
</html>
