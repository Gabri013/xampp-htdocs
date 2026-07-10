<?php
session_start();


require 'db.php';

// Inserção de novo usuário
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['criar_usuario'])) {
    $nome = $_POST['nome'];
    $usuario = $_POST['usuario'];
    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
    $tipo = $_POST['tipo'];

    $stmt = $conn->prepare("INSERT INTO usuarios (nome, usuario, senha, tipo) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $nome, $usuario, $senha, $tipo);
    $stmt->execute();
}

// Exclusão de usuário
if (isset($_GET['excluir']) && $_GET['excluir'] != $_SESSION['id_usuario']) {
    $id = intval($_GET['excluir']);
    $conn->query("DELETE FROM usuarios WHERE id = $id");
}

// Lista de usuários
$result = $conn->query("SELECT * FROM usuarios ORDER BY nome");
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Admin - Cozinca Inox</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
  <div class="text-center mb-4">
    <img src="assets/images/logo_cozinca.png" width="200" alt="Logo Cozinca">
    <h2 class="mt-3">Gerenciamento de Usuários</h2>
    <a href="dashboard.php" class="btn btn-secondary mt-2">← Voltar ao painel</a>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <h5 class="card-title">Novo Usuário</h5>
      <form method="POST">
        <div class="row g-2">
          <div class="col-md-3">
            <input type="text" name="nome" class="form-control" placeholder="Nome" required>
          </div>
          <div class="col-md-3">
            <input type="text" name="usuario" class="form-control" placeholder="Usuário" required>
          </div>
          <div class="col-md-3">
            <input type="password" name="senha" class="form-control" placeholder="Senha" required>
          </div>
          <div class="col-md-2">
            <select name="tipo" class="form-select" required>
              <option value="vendedor">Vendedor</option>
              <option value="admin">Administrador</option>
            </select>
          </div>
          <div class="col-md-1">
            <button type="submit" name="criar_usuario" class="btn btn-primary w-100">+</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <h5 class="card-title">Usuários Cadastrados</h5>
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Nome</th>
            <th>Usuário</th>
            <th>Tipo</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($row['nome']) ?></td>
            <td><?= htmlspecialchars($row['usuario']) ?></td>
            <td><?= $row['tipo'] == 'admin' ? 'Administrador' : 'Vendedor' ?></td>
            <td>
              <?php if ($row['id'] != $_SESSION['id_usuario']): ?>
                <a href="?excluir=<?= $row['id'] ?>" onclick="return confirm('Deseja excluir este usuário?')" class="btn btn-sm btn-danger">Excluir</a>
              <?php else: ?>
                <span class="text-muted">Você</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
