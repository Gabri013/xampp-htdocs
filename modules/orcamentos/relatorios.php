<?php
// relatorios.php — Painel de relatórios com filtros, cards e gráficos extras
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}
require 'db.php';

$nomeUsuario = $_SESSION['usuario'];
$tipoUsuario = $_SESSION['tipo'] ?? 'user';
$idUsuario   = $_SESSION['id_usuario'] ?? 0;

// Carrega usuários (para filtro) se admin
$usuarios = [];
if ($tipoUsuario === 'admin') {
    $res = $conn->query("SELECT id, COALESCE(nome, usuario) AS nome FROM usuarios ORDER BY nome, usuario");
    while ($u = $res->fetch_assoc()) { $usuarios[] = $u; }
}

// Datas padrão = mês vigente
$inicio = new DateTime('first day of this month', new DateTimeZone('America/Sao_Paulo'));
$fim    = new DateTime('last day of this month 23:59:59', new DateTimeZone('America/Sao_Paulo'));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Relatórios - Cozinca Inox</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<style>
  :root { --cozinca:#ff530d; --cozinca2:#ffcb0c; --ink:#1c1c1c; }
  body { background:#f7f8fa; }
  .sidebar{ min-height:100vh; background:#212529; position:sticky; top:0 }
  .sidebar a{ color:#fff; text-decoration:none; display:block; padding:12px 16px; border-radius:8px; margin:4px 0 }
  .sidebar a:hover{ background:#343a40 }
  .logo{ max-width:160px; margin:20px auto; display:block }
  .page-title{ color:var(--cozinca); font-weight:700 }
  .card-metric{ border:0; box-shadow:0 2px 8px rgba(0,0,0,.06); border-left:6px solid var(--cozinca) }
  .card-metric h5{ margin:0; font-size:0.95rem; color:#555 }
  .card-metric .value{ font-size:1.35rem; font-weight:800; color:#111 }
  .filter-bar{ background:#fff; border-radius:12px; padding:14px; box-shadow:0 2px 8px rgba(0,0,0,.06) }
  .chart-box{ background:#fff; border-radius:12px; padding:16px; box-shadow:0 2px 8px rgba(0,0,0,.06) }
  .tbl-box{ background:#fff; border-radius:12px; padding:16px; box-shadow:0 2px 8px rgba(0,0,0,.06) }
  .table thead th{ background:#1c1c1c; color:#fff; border:0 }
</style>
</head>
<body>
<div class="d-flex">
  <!-- MENU LATERAL -->
  <div class="sidebar p-3">
    <img src="imagens/cozincainox.png" class="logo" alt="Cozinca">
    <a href="dashboard.php">🏠 Painel</a>
    <a href="criar_orcamento.php">📝 Criar Orçamento</a>
    <a href="vendas.php">🛒 Vendas</a>
    <a href="listar_orcamentos.php">📄 Listar Orçamentos</a>
    <a href="relatorios.php" style="background:#343a40">📊 Relatórios</a>
    <?php if (($tipoUsuario ?? 'user') === 'admin'): ?>
      <a href="admin.php">⚙️ Gerenciar Usuários</a>
    <?php endif; ?>
    <a href="logout.php">🚪 Sair</a>
  </div>

  <!-- CONTEÚDO -->
  <div class="flex-grow-1 p-4">
    <h2 class="page-title mb-3">Relatórios</h2>
    <p class="text-muted">Indicadores e análises dos orçamentos com filtros por período e usuário.</p>

    <!-- FILTROS -->
    <div class="filter-bar mb-4">
      <form id="filtros" class="row g-3">
        <div class="col-sm-3">
          <label class="form-label">Início</label>
          <input type="date" class="form-control" id="inicio" name="inicio" value="<?= $inicio->format('Y-m-d') ?>">
        </div>
        <div class="col-sm-3">
          <label class="form-label">Fim</label>
          <input type="date" class="form-control" id="fim" name="fim" value="<?= (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d') ?>">
        </div>
        <?php if ($tipoUsuario === 'admin'): ?>
          <div class="col-sm-3">
            <label class="form-label">Usuário</label>
            <select class="form-select" id="usuario_id" name="usuario_id">
              <option value="all">Todos</option>
              <?php foreach ($usuarios as $u): ?>
                <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nome']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php else: ?>
          <input type="hidden" id="usuario_id" name="usuario_id" value="<?= (int)$idUsuario ?>">
        <?php endif; ?>
        <div class="col-sm-3 d-flex align-items-end">
          <button type="button" class="btn btn-dark w-100" onclick="carregar()">Aplicar filtros</button>
        </div>
      </form>
    </div>

    <!-- MÉTRICAS -->
    <div class="row g-3 mb-4">
      <div class="col-md-3"><div class="card card-metric p-3"><h5>Valor total no período</h5><div class="value" id="m_total">R$ 0,00</div></div></div>
      <div class="col-md-3"><div class="card card-metric p-3"><h5>Quantidade de orçamentos</h5><div class="value" id="m_qtd">0</div></div></div>
      <div class="col-md-3"><div class="card card-metric p-3"><h5>Ticket médio</h5><div class="value" id="m_ticket">R$ 0,00</div></div></div>
      <div class="col-md-3"><div class="card card-metric p-3"><h5>Descontos aplicados</h5><div class="value" id="m_desc">R$ 0,00</div></div></div>
    </div>
    <div class="row g-3 mb-4">
      <div class="col-md-3"><div class="card card-metric p-3"><h5>Fretes no período</h5><div class="value" id="m_frete">R$ 0,00</div></div></div>
    </div>

    <!-- GRÁFICOS LINHA & BARRAS -->
    <div class="row g-4 mb-4">
      <div class="col-lg-7">
        <div class="chart-box">
          <h6 class="mb-3">Total por dia</h6>
          <canvas id="chartDia" height="150"></canvas>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="chart-box">
          <h6 class="mb-3">Top clientes (valor)</h6>
          <canvas id="chartClientes" height="150"></canvas>
        </div>
      </div>
    </div>

    <!-- GRÁFICOS EXTRAS -->
    <div class="row g-4 mb-4">
      <div class="col-lg-4">
        <div class="chart-box">
          <h6 class="mb-3">Forma de pagamento (participação)</h6>
          <canvas id="chartPagamento" height="220"></canvas>
        </div>
      </div>
      <div class="col-lg-8">
        <div class="chart-box">
          <h6 class="mb-3">Total (produtos) por setor</h6>
          <canvas id="chartSetor" height="220"></canvas>
        </div>
      </div>
    </div>

    <div class="row g-4 mb-4">
      <div class="col-lg-12">
        <div class="chart-box">
          <h6 class="mb-3">Top produtos (por nome do item)</h6>
          <canvas id="chartProdutos" height="160"></canvas>
        </div>
      </div>
    </div>

    <!-- TABELA: ÚLTIMOS ORÇAMENTOS -->
    <div class="tbl-box">
      <h6 class="mb-3">Últimos orçamentos do período</h6>
      <div class="table-responsive">
        <table class="table table-hover align-middle" id="tblUltimos">
          <thead><tr><th>Código</th><th>Cliente</th><th>Data</th><th>Total final</th><th></th></tr></thead>
          <tbody></tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<script>
let cDia, cClientes, cPgto, cSetor, cProd;

function fmtBR(v){ return 'R$ ' + (Number(v||0).toFixed(2)).replace('.',','); }

async function carregar(){
  const params = new URLSearchParams(new FormData(document.getElementById('filtros')));
  const resp = await fetch('relatorios_dados.php?'+params.toString(), {credentials:'same-origin'});
  const data = await resp.json();

  // Métricas
  document.getElementById('m_total').textContent  = fmtBR(data.total_final || 0);
  document.getElementById('m_qtd').textContent    = data.qtd || 0;
  document.getElementById('m_ticket').textContent = fmtBR(data.ticket_medio || 0);
  document.getElementById('m_desc').textContent   = fmtBR(data.total_desconto || 0);
  document.getElementById('m_frete').textContent  = fmtBR(data.total_frete || 0);

  // Por dia
  const labelsDia = (data.series_dia || []).map(x=>x.dia);
  const valoresDia = (data.series_dia || []).map(x=>x.valor);
  if (cDia) cDia.destroy();
  cDia = new Chart(document.getElementById('chartDia'), {
    type:'line',
    data:{ labels:labelsDia, datasets:[{ label:'Total diário', data:valoresDia }] },
    options:{ plugins:{legend:{display:false}}, scales:{ y:{ ticks:{ callback:v=>fmtBR(v) } } } }
  });

  // Top clientes
  const labelsCli = (data.top_clientes || []).map(x=>x.nome);
  const valoresCli = (data.top_clientes || []).map(x=>x.valor);
  if (cClientes) cClientes.destroy();
  cClientes = new Chart(document.getElementById('chartClientes'), {
    type:'bar',
    data:{ labels:labelsCli, datasets:[{ label:'Valor', data:valoresCli }] },
    options:{ plugins:{legend:{display:false}}, scales:{ y:{ ticks:{ callback:v=>fmtBR(v) } } } }
  });

  // Forma de pagamento (pizza)
  const labelsPg = (data.pagamento_pizza || []).map(x=>x.pagamento||'—');
  const valoresPg = (data.pagamento_pizza || []).map(x=>x.valor);
  if (cPgto) cPgto.destroy();
  cPgto = new Chart(document.getElementById('chartPagamento'), {
    type:'pie',
    data:{ labels:labelsPg, datasets:[{ data:valoresPg }] },
    options:{ plugins:{legend:{position:'bottom'}} }
  });

  // Total por setor (barras horizontais) — soma de produtos por setor
  const labelsSet = (data.total_por_setor || []).map(x=>x.setor);
  const valoresSet = (data.total_por_setor || []).map(x=>x.valor);
  if (cSetor) cSetor.destroy();
  cSetor = new Chart(document.getElementById('chartSetor'), {
    type:'bar',
    data:{ labels:labelsSet, datasets:[{ label:'Produtos (R$)', data:valoresSet }] },
    options:{ indexAxis:'y', plugins:{legend:{display:false}}, scales:{ x:{ ticks:{ callback:v=>fmtBR(v) } } } }
  });

  // Top produtos
  const labelsProd = (data.top_produtos || []).map(x=>x.item);
  const valoresProd = (data.top_produtos || []).map(x=>x.valor);
  if (cProd) cProd.destroy();
  cProd = new Chart(document.getElementById('chartProdutos'), {
    type:'bar',
    data:{ labels:labelsProd, datasets:[{ label:'Produtos (R$)', data:valoresProd }] },
    options:{ plugins:{legend:{display:false}}, scales:{ y:{ ticks:{ callback:v=>fmtBR(v) } } } }
  });

  // Tabela: últimos
  const tbody = document.querySelector('#tblUltimos tbody');
  tbody.innerHTML = '';
  (data.ultimos || []).forEach(l => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${l.codigo}</td>
      <td>${l.cliente}</td>
      <td>${l.data}</td>
      <td>${fmtBR(l.total)}</td>
      <td><a href="gerar_pdf.php?id=${l.id}" target="_blank" class="btn btn-sm btn-dark">ver</a></td>
    `;
    tbody.appendChild(tr);
  });
}

// inicial
carregar();
</script>
</body>
</html>
