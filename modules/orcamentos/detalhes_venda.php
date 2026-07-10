<?php
// detalhes_venda.php - Exibe os detalhes de uma venda específica
session_start();
if (!isset($_SESSION['usuario'])) { header("Location: login.php"); exit; }
require 'includes/db.php';

$idUsuario   = (int)($_SESSION['id_usuario'] ?? 0);
$tipoUsuario = $_SESSION['tipo'] ?? 'user';

date_default_timezone_set('America/Sao_Paulo');

// --- Busca a venda no banco de dados ---
$venda = null;
$id_venda = (int)($_GET['id'] ?? 0);

if ($id_venda > 0) {
    $sql = "SELECT v.*, COALESCE(u.nome, u.usuario) AS vendedor
            FROM vendas v
            JOIN usuarios u ON u.id = v.id_usuario
            WHERE v.id = ?";
    
    // Se o usuário não é admin, garante que ele só veja suas próprias vendas
    if ($tipoUsuario !== 'admin') {
        $sql .= " AND v.id_usuario = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $id_venda, $idUsuario);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_venda);
    }
    
    $stmt->execute();
    $res_venda = $stmt->get_result();
    $venda = $res_venda->fetch_assoc();
}

if (!$venda) {
    $_SESSION['mensagem_erro'] = "Venda não encontrada ou você não tem permissão para acessá-la.";
    header("Location: vendas.php");
    exit;
}

// --- Busca os itens da venda ---
$itens_venda = [];
$sql_itens = "SELECT * FROM venda_itens WHERE id_venda = ?";
$stmt_itens = $conn->prepare($sql_itens);
$stmt_itens->bind_param("i", $id_venda);
$stmt_itens->execute();
$res_itens = $stmt_itens->get_result();
while($item = $res_itens->fetch_assoc()){
    $itens_venda[] = $item;
}

function exibeCodigoVenda($cod) {
  if (ctype_digit((string)$cod)) {
    return str_pad((string)$cod, 6, '0', STR_PAD_LEFT);
  }
  return htmlspecialchars((string)$cod);
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Detalhes da Venda #<?= exibeCodigoVenda($venda['codigo_venda']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
<style>
  :root { --cozinca:#ff530d; --ink:#1c1c1c; }
  body { background:#f7f8fa; }
  .sidebar{ min-height:100vh; background:#212529; position:sticky; top:0 }
  .sidebar a{ color:#fff; text-decoration:none; display:block; padding:12px 16px; border-radius:8px; margin:4px 0 }
  .sidebar a:hover{ background:#343a40 }
  .logo{ max-width:160px; margin:20px auto; display:block }
  .page-title{ color:var(--cozinca); font-weight:700 }
  .card-elev { border:0; border-radius:14px; box-shadow:0 2px 10px rgba(0,0,0,.06); }
  .table thead th{ background:#1c1c1c; color:#fff; border:0; }
</style>
</head>
<body>
<div class="d-flex">
  <div class="sidebar p-3">
    <img src="imagens/cozincainox.png" class="logo" alt="Cozinca">
    <a href="dashboard.php">🏠 Painel</a>
    <a href="criar_orcamento.php">📝 Criar Orçamento</a>
    <a href="listar_orcamentos.php">📄 Listar Orçamentos</a>
    <a href="relatorios.php">📊 Relatórios</a>
    <a href="cadastro.php">📇 Cadastro</a>
    <a href="vendas.php" style="background:#343a40">🛒 Vendas</a>
    <?php if (($tipoUsuario ?? 'user') === 'admin'): ?>
      <a href="admin.php">⚙️ Gerenciar Usuários</a>
    <?php endif; ?>
    <a href="logout.php">🚪 Sair</a>
  </div>

  <div class="flex-grow-1 p-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h2 class="page-title">Detalhes da Venda #<?= exibeCodigoVenda($venda['codigo_venda']) ?></h2>
      <div>
        <a href="vendas.php" class="btn btn-secondary me-2"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
        <a href="editar_venda.php?id=<?= (int)$venda['id'] ?>" class="btn btn-primary me-2"><i class="bi bi-pencil-square me-1"></i> Editar Venda</a>
        <a href="imprimir_venda.php?id=<?= (int)$venda['id'] ?>" target="_blank" class="btn btn-dark"><i class="bi bi-printer me-1"></i> Imprimir</a>
      </div>
    </div>
    <p class="text-muted">Detalhes completos da venda realizada.</p>

    <div class="card card-elev mb-4">
        <div class="card-header">Informações da Venda</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Vendedor:</strong> <?= htmlspecialchars($venda['vendedor']) ?></p>
                    <p><strong>Data da Venda:</strong> <?= date('d/m/Y H:i', strtotime($venda['criado_em'])) ?></p>
                    <p><strong>Forma de Pagamento:</strong> <?= htmlspecialchars($venda['pagamento']) ?: 'Não informado' ?></p>
                    <p><strong>Condições de Entrega:</strong> <?= htmlspecialchars($venda['entrega']) ?: 'Não informado' ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Cliente:</strong> <?= htmlspecialchars($venda['nome_cliente']) ?></p>
                    <p><strong>Telefone:</strong> <?= htmlspecialchars($venda['telefone']) ?: 'Não informado' ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($venda['email']) ?: 'Não informado' ?></p>
                    <p><strong>Endereço:</strong> <?= htmlspecialchars($venda['endereco']) ?: 'Não informado' ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-elev mb-4">
        <div class="card-header">Itens da Venda</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Descrição</th>
                            <th class="text-center">Qtd</th>
                            <th class="text-end">Unitário (R$)</th>
                            <th class="text-end">Subtotal (R$)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itens_venda as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['item']) ?></td>
                            <td><?= nl2br(htmlspecialchars($item['descricao'])) ?></td>
                            <td class="text-center"><?= htmlspecialchars(number_format((float)$item['quantidade'], 0, ',', '.')) ?></td>
                            <td class="text-end">R$ <?= number_format((float)$item['preco_unitario'], 2, ',', '.') ?></td>
                            <td class="text-end">R$ <?= number_format((float)$item['preco_total'], 2, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card card-elev">
        <div class="card-body">
            <div class="row text-end">
                <div class="col-12">
                    <p><strong>Total Produtos:</strong> R$ <?= number_format((float)$venda['total_produtos'], 2, ',', '.') ?></p>
                    <p><strong>Frete:</strong> R$ <?= number_format((float)$venda['frete'], 2, ',', '.') ?></p>
                    <p><strong>Desconto (<?= number_format((float)$venda['desconto'], 2, ',', '.') ?>%):</strong> <span class="text-danger">- R$ <?= number_format((float)($venda['total_produtos']+$venda['frete'])*($venda['desconto']/100), 2, ',', '.') ?></span></p>
                    <h4 class="fw-bold">Total Final: R$ <?= number_format((float)$venda['total_final'], 2, ',', '.') ?></h4>
                </div>
            </div>
        </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
