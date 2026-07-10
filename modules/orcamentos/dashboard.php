<?php
// dashboard.php - Painel de controle com relatórios dinâmicos e menu lateral
session_start();
require 'db.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

$nomeUsuario = $_SESSION['usuario'];
$tipoUsuario = $_SESSION['tipo'];

// Mês atual
$mes_atual = date('m/Y');

// Total de orçamentos do mês vigente
$stmt_total = $conn->prepare("SELECT COUNT(DISTINCT o.id) as qtd, COALESCE(SUM(oi.preco_total),0) as valor_total FROM orcamentos o LEFT JOIN orcamento_itens oi ON o.id = oi.id_orcamento WHERE MONTH(o.data_criacao) = MONTH(CURRENT_DATE()) AND YEAR(o.data_criacao) = YEAR(CURRENT_DATE())");
$stmt_total->execute();
$result_total = $stmt_total->get_result()->fetch_assoc();

$qtd_orcamentos = $result_total['qtd'];
$valor_total = $result_total['valor_total'];

// Orçamentos por mês (últimos 6 meses)
$stmt_mensal = $conn->prepare("SELECT DATE_FORMAT(o.data_criacao, '%m/%Y') as mes, COUNT(DISTINCT o.id) as qtd, COALESCE(SUM(oi.preco_total),0) as valor FROM orcamentos o LEFT JOIN orcamento_itens oi ON o.id = oi.id_orcamento GROUP BY mes ORDER BY o.data_criacao DESC LIMIT 6");
$stmt_mensal->execute();
$result_mensal = $stmt_mensal->get_result();

$labels = [];
$valores = [];
$qtd_mensal = [];
while ($row = $result_mensal->fetch_assoc()) {
    $labels[] = $row['mes'];
    $valores[] = $row['valor'];
    $qtd_mensal[] = $row['qtd'];
}
$labels = array_reverse($labels);
$valores = array_reverse($valores);
$qtd_mensal = array_reverse($qtd_mensal);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Painel - Cozinca Inox</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body { min-height: 100vh; background-color: #f8f9fa; margin: 0; }
    .sidebar { position: fixed; top: 0; left: 0; height: 100%; background-color: #212529; width: 220px; }
    .sidebar a { color: #ffffff; text-decoration: none; display: block; padding: 12px 20px; }
    .sidebar a:hover { background-color: #343a40; }
    .logo { max-width: 160px; margin: 20px auto; display: block; }
    .cards { display: flex; justify-content: space-around; margin: 20px 0; }
    .card-dashboard { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); width: 40%; text-align: center; }
    .card-dashboard h3 { margin: 0; color: #1c1c1c; }
    .card-dashboard p { font-size: 1.4em; font-weight: bold; margin-top: 10px; }
    .main-content { margin-left: 220px; padding: 20px; }
    canvas { background: #fff; border-radius: 10px; padding: 15px; margin-bottom: 30px; max-height: 200px; }
    .mes-atual { text-align: center; font-weight: bold; color: #ff530d; margin-bottom: 10px; }
  </style>
</head>
<body>
<div class="sidebar p-3">
    <img src="imagens/cozincainox.png" alt="Cozinca" class="logo">
    <a href="criar_orcamento.php">📝 Criar Orçamento</a>
    <a href="listar_orcamentos.php">✏️ Editar Orçamento</a>
    <a href="vendas.php">🛒 Vendas</a>
    <a href="cadastro.php">📇 Cadastro</a>
    <a href="relatorios.php">📊 Relatórios</a>
    <?php if ($tipoUsuario === 'admin'): ?>
      <a href="admin.php">⚙️ Gerenciar Usuários</a>
    <?php endif; ?>
    <a href="logout.php">🚪 Sair</a>
</div>

<div class="main-content">
    <h2 class="mb-4">Bem-vindo, <?= ucfirst($nomeUsuario) ?>!</h2>
    <div class="mes-atual">Resumo referente ao mês: <?= $mes_atual ?></div>
    <div class="cards">
      <div class="card-dashboard">
        <h3>Quantidade de Orçamentos (Mês Atual)</h3>
        <p><?= $qtd_orcamentos ?></p>
      </div>
      <div class="card-dashboard">
        <h3>Valor Total em Orçamentos (Mês Atual)</h3>
        <p>R$ <?= number_format($valor_total,2,',','.') ?></p>
      </div>
    </div>
    <canvas id="graficoValor"></canvas>
    <canvas id="graficoQtd"></canvas>
</div>

<script>
const labels = <?= json_encode($labels) ?>;
const valores = <?= json_encode($valores) ?>;
const qtdMensal = <?= json_encode($qtd_mensal) ?>;

new Chart(document.getElementById('graficoValor').getContext('2d'), {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: 'Valor Total Mensal (R$)',
            data: valores,
            borderColor: '#ff530d',
            backgroundColor: 'rgba(255,83,13,0.2)',
            fill: true,
            tension: 0.3
        }]
    },
    options: { responsive: true, plugins: { legend: { display: true } } }
});

new Chart(document.getElementById('graficoQtd').getContext('2d'), {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [{
            label: 'Quantidade de Orçamentos',
            data: qtdMensal,
            backgroundColor: '#1c1c1c'
        }]
    },
    options: { responsive: true, plugins: { legend: { display: true } }, scales: { y: { beginAtZero: true } } }
});
</script>
</body>
</html>
