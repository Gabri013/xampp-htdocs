<?php
// cadastro.php — Cadastro de Clientes e Produtos com busca instantânea, paginação e EDIÇÃO inline via modal (AJAX no próprio arquivo)
session_start();
if (!isset($_SESSION['usuario'])) {
  header("Location: login.php");
  exit;
}
require 'includes/db.php';

$nomeUsuario = $_SESSION['usuario'];
$tipoUsuario = $_SESSION['tipo'] ?? 'user';

/* ==========================
   Helpers
   ========================== */
function normInt($v, $def, $min=1, $max=10000){ $n = (int)($v ?? $def); return max($min, min($max, $n)); }
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function file_exists_safe($p){ return $p && is_string($p) && is_file($p); }

/* ==========================
   Rotas AJAX (no mesmo arquivo)
   ========================== */
if (isset($_POST['ajax'])) {
  header('Content-Type: application/json; charset=UTF-8');

  // Segurança básica
  if (!isset($_SESSION['usuario'])) {
    echo json_encode(['ok'=>false,'msg'=>'Sessão expirada']);
    exit;
  }

  $action = $_POST['ajax'];

  if ($action === 'update_cliente') {
    $id        = (int)($_POST['id'] ?? 0);
    $nome      = trim($_POST['nome'] ?? '');
    $documento = trim($_POST['documento'] ?? '');
    $telefone  = trim($_POST['telefone'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $endereco  = trim($_POST['endereco'] ?? '');

    if ($id<=0 || $nome==='') { echo json_encode(['ok'=>false,'msg'=>'Dados inválidos']); exit; }

    $stmt = $conn->prepare("UPDATE clientes SET nome=?, documento=?, telefone=?, email=?, endereco=? WHERE id=?");
    if (!$stmt) { echo json_encode(['ok'=>false,'msg'=>$conn->error]); exit; }
    $stmt->bind_param('sssssi', $nome, $documento, $telefone, $email, $endereco, $id);
    $ok = $stmt->execute();
    echo json_encode(['ok'=>$ok, 'msg'=>$ok?'Cliente atualizado':'Erro ao atualizar']);
    exit;
  }

  if ($action === 'update_produto') {
    $id          = (int)($_POST['id'] ?? 0);
    $nome        = trim($_POST['nome'] ?? '');
    $descricao   = trim($_POST['descricao'] ?? '');
    $preco_base  = (float)str_replace(',', '.', $_POST['preco_base'] ?? '0');
    $unidade     = trim($_POST['unidade'] ?? '');
    $sku         = trim($_POST['sku'] ?? '');
    $img_atual   = trim($_POST['imagem_atual'] ?? '');

    if ($id<=0 || $nome==='') { echo json_encode(['ok'=>false,'msg'=>'Dados inválidos']); exit; }

    // Upload (opcional)
    $imagemPath = $img_atual;
    if (isset($_FILES['imagem']) && !empty($_FILES['imagem']['name'])) {
      $orig = basename($_FILES['imagem']['name']);
      $safe = preg_replace('/[^A-Za-z0-9_.-]/','_', $orig);
      if (!is_dir('uploads_produtos')) mkdir('uploads_produtos', 0777, true);
      $dest = 'uploads_produtos/'.time().'_'.$safe;
      if (move_uploaded_file($_FILES['imagem']['tmp_name'], $dest)) {
        $imagemPath = $dest;
      }
    }

    $stmt = $conn->prepare("UPDATE produtos SET nome=?, descricao=?, preco_base=?, unidade=?, sku=?, imagem=? WHERE id=?");
    if (!$stmt) { echo json_encode(['ok'=>false,'msg'=>$conn->error]); exit; }
    $stmt->bind_param('ssdsssi', $nome, $descricao, $preco_base, $unidade, $sku, $imagemPath, $id);
    $ok = $stmt->execute();
    echo json_encode(['ok'=>$ok, 'msg'=>$ok?'Produto atualizado':'Erro ao atualizar']);
    exit;
  }

  echo json_encode(['ok'=>false,'msg'=>'Ação inválida']);
  exit;
}

/* ==========================
   Estado das abas e filtros
   ========================== */
$tab = $_GET['tab'] ?? 'clientes';

/* CLIENTES */
$c_search   = trim($_GET['c_search'] ?? '');
$c_page     = normInt($_GET['c_page'] ?? 1, 1);
$c_per_page = normInt($_GET['c_per_page'] ?? 10, 10, 5, 100);

/* PRODUTOS */
$p_search   = trim($_GET['p_search'] ?? '');
$p_page     = normInt($_GET['p_page'] ?? 1, 1);
$p_per_page = normInt($_GET['p_per_page'] ?? 10, 10, 5, 100);

/* ==========================
   Query CLIENTES
   ========================== */
$c_where = "1=1";
$c_params = [];
$c_types  = "";
if ($c_search !== '') {
  $c_where .= " AND (nome LIKE ? OR documento LIKE ? OR telefone LIKE ? OR email LIKE ? OR endereco LIKE ?)";
  $like = '%'.$c_search.'%';
  $c_params = [$like,$like,$like,$like,$like];
  $c_types  = "sssss";
}
$c_sql_count = "SELECT COUNT(*) AS total FROM clientes WHERE $c_where";
$stmt = $conn->prepare($c_sql_count);
if ($c_types) { $stmt->bind_param($c_types, ...$c_params); }
$stmt->execute();
$c_total = (int)$stmt->get_result()->fetch_assoc()['total'];
$c_pages = max(1, (int)ceil($c_total / $c_per_page));
$c_page  = min($c_page, $c_pages);
$c_offset = ($c_page - 1) * $c_per_page;
$c_sql = "SELECT * FROM clientes WHERE $c_where ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($c_sql);
if ($c_types) {
  $c_types_lim = $c_types . "ii";
  $c_params_lim = array_merge($c_params, [$c_per_page, $c_offset]);
  $stmt->bind_param($c_types_lim, ...$c_params_lim);
} else { $stmt->bind_param("ii", $c_per_page, $c_offset); }
$stmt->execute();
$clientes = $stmt->get_result();

/* ==========================
   Query PRODUTOS
   ========================== */
$p_where = "1=1";
$p_params = [];
$p_types  = "";
if ($p_search !== '') {
  $p_where .= " AND (nome LIKE ? OR descricao LIKE ? OR sku LIKE ?)";
  $like = '%'.$p_search.'%';
  $p_params = [$like,$like,$like];
  $p_types  = "sss";
}
$p_sql_count = "SELECT COUNT(*) AS total FROM produtos WHERE $p_where";
$stmt = $conn->prepare($p_sql_count);
if ($p_types) { $stmt->bind_param($p_types, ...$p_params); }
$stmt->execute();
$p_total = (int)$stmt->get_result()->fetch_assoc()['total'];
$p_pages = max(1, (int)ceil($p_total / $p_per_page));
$p_page  = min($p_page, $p_pages);
$p_offset = ($p_page - 1) * $p_per_page;
$p_sql = "SELECT * FROM produtos WHERE $p_where ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($p_sql);
if ($p_types) {
  $p_types_lim = $p_types . "ii";
  $p_params_lim = array_merge($p_params, [$p_per_page, $p_offset]);
  $stmt->bind_param($p_types_lim, ...$p_params_lim);
} else { $stmt->bind_param("ii", $p_per_page, $p_offset); }
$stmt->execute();
$produtos = $stmt->get_result();

/* ==========================
   Flash
   ========================== */
$flash = '';
$flash_tipo = '';
if (!empty($_SESSION['mensagem_sucesso'])) { $flash = $_SESSION['mensagem_sucesso']; $flash_tipo = 'success'; unset($_SESSION['mensagem_sucesso']); }
if (!empty($_SESSION['mensagem_erro']))     { $flash = $_SESSION['mensagem_erro'];     $flash_tipo = 'danger'; unset($_SESSION['mensagem_erro']); }

/* ==========================
   Renders parciais
   ========================== */
function render_clientes_block($c_total, $clientes, $c_search, $c_per_page, $c_page, $c_pages){
  $base = "cadastro.php?tab=clientes&c_search=".urlencode($c_search)."&c_per_page=".$c_per_page;
  ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-2">
      <thead>
        <tr>
          <th>Nome</th><th>Documento</th><th>Telefone</th><th>Email</th><th>Endereço</th><th style="width:110px"></th>
        </tr>
      </thead>
      <tbody>
      <?php if ($c_total > 0): ?>
        <?php while ($c = $clientes->fetch_assoc()): ?>
          <tr>
            <td><?= esc($c['nome']) ?></td>
            <td><?= esc($c['documento']) ?></td>
            <td><?= esc($c['telefone']) ?></td>
            <td><?= esc($c['email']) ?></td>
            <td><?= esc($c['endereco']) ?></td>
            <td class="text-end">
              <button type="button"
                      class="btn btn-sm btn-outline-primary btn-edit-cliente"
                      data-id="<?= (int)$c['id'] ?>"
                      data-nome="<?= esc($c['nome']) ?>"
                      data-documento="<?= esc($c['documento']) ?>"
                      data-telefone="<?= esc($c['telefone']) ?>"
                      data-email="<?= esc($c['email']) ?>"
                      data-endereco="<?= esc($c['endereco']) ?>">
                Editar
              </button>
              <a class="btn btn-sm btn-outline-danger" href="excluir_cliente.php?id=<?= (int)$c['id'] ?>" onclick="return confirm('Excluir este cliente?')"><i class="bi bi-trash"></i></a>
            </td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
          <tr><td colspan="6" class="text-center text-muted">Nenhum cliente encontrado.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <nav>
    <ul class="pagination pagination-sm mb-0">
      <li class="page-item <?= $c_page<=1?'disabled':'' ?>">
        <a class="page-link ajax-page" href="<?= $c_page<=1?'#':$base.'&c_page='.($c_page-1) ?>">«</a>
      </li>
      <?php
        $start = max(1, $c_page-2);
        $end   = min($c_pages, $c_page+2);
        for ($i=$start; $i<=$end; $i++):
      ?>
        <li class="page-item <?= $i===$c_page?'active':'' ?>">
          <a class="page-link ajax-page" href="<?= $base.'&c_page='.$i ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
      <li class="page-item <?= $c_page>=$c_pages?'disabled':'' ?>">
        <a class="page-link ajax-page" href="<?= $c_page>=$c_pages?'#':$base.'&c_page='.($c_page+1) ?>">»</a>
      </li>
    </ul>
    <small class="text-muted ms-2">Total: <?= $c_total ?> registro(s)</small>
  </nav>
  <?php
}

function render_produtos_block($p_total, $produtos, $p_search, $p_per_page, $p_page, $p_pages){
  $base = "cadastro.php?tab=produtos&p_search=".urlencode($p_search)."&p_per_page=".$p_per_page;
  ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-2">
      <thead>
        <tr>
          <th></th><th>Nome</th><th>SKU</th><th>Preço</th><th>Unid.</th><th style="width:140px"></th>
        </tr>
      </thead>
      <tbody>
      <?php if ($p_total > 0): ?>
        <?php while ($p = $produtos->fetch_assoc()): ?>
          <tr>
            <td>
              <?php if (!empty($p['imagem']) && file_exists_safe($p['imagem'])): ?>
                <img src="<?= esc($p['imagem']) ?>" class="img-thumb" alt="produto">
              <?php else: ?>
                <span class="text-muted small">—</span>
              <?php endif; ?>
            </td>
            <td><?= esc($p['nome']) ?></td>
            <td><?= esc($p['sku']) ?></td>
            <td>R$ <?= number_format((float)$p['preco_base'], 2, ',', '.') ?></td>
            <td><?= esc($p['unidade']) ?></td>
            <td class="text-end">
              <button type="button"
                      class="btn btn-sm btn-outline-primary btn-edit-produto"
                      data-id="<?= (int)$p['id'] ?>"
                      data-nome="<?= esc($p['nome']) ?>"
                      data-sku="<?= esc($p['sku']) ?>"
                      data-preco="<?= number_format((float)$p['preco_base'], 2, ',', '.') ?>"
                      data-unidade="<?= esc($p['unidade']) ?>"
                      data-descricao="<?= esc($p['descricao']) ?>"
                      data-imagem="<?= esc($p['imagem']) ?>">
                Editar
              </button>
              <a class="btn btn-sm btn-outline-danger" href="excluir_produto.php?id=<?= (int)$p['id'] ?>" onclick="return confirm('Excluir este produto?')"><i class="bi bi-trash"></i></a>
            </td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
          <tr><td colspan="6" class="text-center text-muted">Nenhum produto encontrado.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <nav>
    <ul class="pagination pagination-sm mb-0">
      <li class="page-item <?= $p_page<=1?'disabled':'' ?>">
        <a class="page-link ajax-page" href="<?= $p_page<=1?'#':$base.'&p_page='.($p_page-1) ?>">«</a>
      </li>
      <?php
        $start = max(1, $p_page-2);
        $end   = min($p_pages, $p_page+2);
        for ($i=$start; $i<=$end; $i++):
      ?>
        <li class="page-item <?= $i===$p_page?'active':'' ?>">
          <a class="page-link ajax-page" href="<?= $base.'&p_page='.$i ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
      <li class="page-item <?= $p_page>=$p_pages?'disabled':'' ?>">
        <a class="page-link ajax-page" href="<?= $p_page>=$p_pages?'#':$base.'&p_page='.($p_page+1) ?>">»</a>
      </li>
    </ul>
    <small class="text-muted ms-2">Total: <?= $p_total ?> registro(s)</small>
  </nav>
  <?php
}

/* ==========================
   Saída parcial (AJAX GET)
   ========================== */
if (isset($_GET['partial'])) {
  header('Content-Type: text/html; charset=UTF-8');
  if ($_GET['partial'] === 'clientes') {
    render_clientes_block($c_total, $clientes, $c_search, $c_per_page, $c_page, $c_pages);
  } elseif ($_GET['partial'] === 'produtos') {
    render_produtos_block($p_total, $produtos, $p_search, $p_per_page, $p_page, $p_pages);
  }
  exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Cadastro - Cozinca Inox</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
<style>
  :root { --cozinca:#ff530d; --cozinca2:#ffcb0c; --ink:#1c1c1c; }
  body { background:#f7f8fa; }
  .sidebar{ min-height:100vh; background:#212529; position:sticky; top:0 }
  .sidebar a{ color:#fff; text-decoration:none; display:block; padding:12px 16px; border-radius:8px; margin:4px 0 }
  .sidebar a:hover{ background:#343a40 }
  .logo{ max-width:160px; margin:20px auto; display:block }
  .page-title{ color:var(--cozinca); font-weight:700 }
  .card-elev { border:0; border-radius:14px; box-shadow:0 2px 10px rgba(0,0,0,.06); }
  .card-elev .card-header { background:#fff; border-bottom:1px solid #eee; font-weight:700; }
  .table thead th{ background:#1c1c1c; color:#fff; border:0; }
  .img-thumb{ height:44px; border-radius:6px; }
  .mini-controls{ gap:.5rem; display:flex; align-items:center; justify-content:flex-end; flex-wrap:wrap }
  .pagination .page-link{ color:#1c1c1c }
  .pagination .active .page-link{ background:#1c1c1c; border-color:#1c1c1c }
  .modal .form-control, .modal textarea { font-size:.95rem }
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
    <a href="relatorios.php">📊 Relatórios</a>
    <a href="cadastro.php" style="background:#343a40">📇 Cadastro</a>
    <?php if (($tipoUsuario ?? 'user') === 'admin'): ?>
      <a href="admin.php">⚙️ Gerenciar Usuários</a>
    <?php endif; ?>
    <a href="logout.php">🚪 Sair</a>
  </div>

  <!-- CONTEÚDO -->
  <div class="flex-grow-1 p-4">
    <h2 class="page-title mb-3">Cadastro</h2>
    <p class="text-muted">Gerencie <strong>Clientes</strong> e <strong>Produtos</strong> com busca instantânea, paginação e <strong>edição inline</strong>.</p>

    <?php if ($flash): ?>
      <div class="alert alert-<?= $flash_tipo ?>"><?= esc($flash) ?></div>
    <?php endif; ?>

    <ul class="nav nav-pills mb-3">
      <li class="nav-item">
        <a class="nav-link <?= $tab==='clientes'?'active':'' ?>" href="?tab=clientes">Clientes</a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $tab==='produtos'?'active':'' ?>" href="?tab=produtos">Produtos</a>
      </li>
    </ul>

    <?php if ($tab === 'clientes'): ?>
      <!-- ===== CLIENTES ===== -->
      <div class="row g-4">
        <div class="col-lg-4">
          <div class="card card-elev">
            <div class="card-header">Novo Cliente</div>
            <div class="card-body">
              <form action="salvar_cliente.php" method="POST">
                <div class="mb-2">
                  <label class="form-label">Nome</label>
                  <input type="text" name="nome" class="form-control" required>
                </div>
                <div class="mb-2">
                  <label class="form-label">CPF/CNPJ</label>
                  <input type="text" name="documento" class="form-control">
                </div>
                <div class="mb-2">
                  <label class="form-label">Telefone</label>
                  <input type="text" name="telefone" class="form-control">
                </div>
                <div class="mb-2">
                  <label class="form-label">Email</label>
                  <input type="email" name="email" class="form-control">
                </div>
                <div class="mb-3">
                  <label class="form-label">Endereço</label>
                  <input type="text" name="endereco" class="form-control">
                </div>
                <button class="btn btn-dark w-100" type="submit">
                  <i class="bi bi-person-plus me-1"></i> Salvar Cliente
                </button>
              </form>
            </div>
          </div>
        </div>

        <div class="col-lg-8">
          <div class="card card-elev">
            <div class="card-header d-flex justify-content-between align-items-center">
              <span>Clientes Cadastrados</span>
              <form class="mini-controls" method="GET" id="formClientes">
                <input type="hidden" name="tab" value="clientes">
                <input type="text" class="form-control form-control-sm" name="c_search" id="c_search" placeholder="Buscar..." value="<?= esc($c_search) ?>">
                <select name="c_per_page" id="c_per_page" class="form-select form-select-sm">
                  <?php foreach ([10,25,50,100] as $opt): ?>
                    <option value="<?= $opt ?>" <?= $c_per_page===$opt?'selected':'' ?>><?= $opt ?>/página</option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-outline-dark" type="submit">Aplicar</button>
              </form>
            </div>
            <div class="card-body" id="clientesList">
              <?php render_clientes_block($c_total, $clientes, $c_search, $c_per_page, $c_page, $c_pages); ?>
            </div>
          </div>
        </div>
      </div>

    <?php else: ?>
      <!-- ===== PRODUTOS ===== -->
      <div class="row g-4">
        <div class="col-lg-4">
          <div class="card card-elev">
            <div class="card-header">Novo Produto</div>
            <div class="card-body">
              <form action="salvar_produto.php" method="POST" enctype="multipart/form-data">
                <div class="mb-2">
                  <label class="form-label">Nome do Produto</label>
                  <input type="text" name="nome" class="form-control" required>
                </div>
                <div class="mb-2">
                  <label class="form-label">Descrição</label>
                  <textarea name="descricao" class="form-control" rows="3"></textarea>
                </div>
                <div class="mb-2">
                  <label class="form-label">Preço base (R$)</label>
                  <input type="number" step="0.01" min="0" name="preco_base" class="form-control" value="0.00">
                </div>
                <div class="mb-2">
                  <label class="form-label">Unidade</label>
                  <input type="text" name="unidade" class="form-control" value="un" placeholder="un, kg, m...">
                </div>
                <div class="mb-2">
                  <label class="form-label">SKU/Código</label>
                  <input type="text" name="sku" class="form-control">
                </div>
                <div class="mb-3">
                  <label class="form-label">Imagem</label>
                  <input type="file" name="imagem" accept="image/*" class="form-control">
                </div>
                <button class="btn btn-dark w-100" type="submit">
                  <i class="bi bi-box-seam me-1"></i> Salvar Produto
                </button>
              </form>
            </div>
          </div>
        </div>

        <div class="col-lg-8">
          <div class="card card-elev">
            <div class="card-header d-flex justify-content-between align-items-center">
              <span>Produtos Cadastrados</span>
              <form class="mini-controls" method="GET" id="formProdutos">
                <input type="hidden" name="tab" value="produtos">
                <input type="text" class="form-control form-control-sm" name="p_search" id="p_search" placeholder="Buscar..." value="<?= esc($p_search) ?>">
                <select name="p_per_page" id="p_per_page" class="form-select form-select-sm">
                  <?php foreach ([10,25,50,100] as $opt): ?>
                    <option value="<?= $opt ?>" <?= $p_per_page===$opt?'selected':'' ?>><?= $opt ?>/página</option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-outline-dark" type="submit">Aplicar</button>
              </form>
            </div>
            <div class="card-body" id="produtosList">
              <?php render_produtos_block($p_total, $produtos, $p_search, $p_per_page, $p_page, $p_pages); ?>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

  </div>
</div>

<!-- MODAL EDITAR CLIENTE -->
<div class="modal fade" id="modalCliente" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" id="formEditCliente">
      <div class="modal-header">
        <h5 class="modal-title">Editar Cliente</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="ajax" value="update_cliente">
        <input type="hidden" name="id" id="cli_id">
        <div class="mb-2"><label class="form-label">Nome</label><input type="text" class="form-control" name="nome" id="cli_nome" required></div>
        <div class="mb-2"><label class="form-label">CPF/CNPJ</label><input type="text" class="form-control" name="documento" id="cli_documento"></div>
        <div class="mb-2"><label class="form-label">Telefone</label><input type="text" class="form-control" name="telefone" id="cli_telefone"></div>
        <div class="mb-2"><label class="form-label">Email</label><input type="email" class="form-control" name="email" id="cli_email"></div>
        <div class="mb-2"><label class="form-label">Endereço</label><input type="text" class="form-control" name="endereco" id="cli_endereco"></div>
        <div id="cli_feedback" class="small mt-1"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-dark" type="submit">Salvar</button>
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Fechar</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL EDITAR PRODUTO -->
<div class="modal fade" id="modalProduto" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" id="formEditProduto" enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title">Editar Produto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="ajax" value="update_produto">
        <input type="hidden" name="id" id="prod_id">
        <input type="hidden" name="imagem_atual" id="prod_img_atual">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Nome</label><input type="text" class="form-control" name="nome" id="prod_nome" required></div>
          <div class="col-md-3"><label class="form-label">SKU</label><input type="text" class="form-control" name="sku" id="prod_sku"></div>
          <div class="col-md-3"><label class="form-label">Unidade</label><input type="text" class="form-control" name="unidade" id="prod_unidade"></div>
          <div class="col-md-4"><label class="form-label">Preço base (R$)</label><input type="text" class="form-control" name="preco_base" id="prod_preco"></div>
          <div class="col-md-8"><label class="form-label">Descrição</label><textarea class="form-control" rows="3" name="descricao" id="prod_descricao"></textarea></div>
          <div class="col-md-6">
            <label class="form-label">Imagem atual</label>
            <div id="prod_preview" class="border rounded p-2 text-center">
              <span class="text-muted small">— sem imagem —</span>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Trocar imagem</label>
            <input type="file" class="form-control" accept="image/*" name="imagem" id="prod_imagem">
          </div>
        </div>
        <div id="prod_feedback" class="small mt-2"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-dark" type="submit">Salvar</button>
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Fechar</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Debounce
function debounce(fn, delay=300){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), delay); }; }

// ====== CLIENTES: busca instantânea e paginação via AJAX ======
const cSearch = document.getElementById('c_search');
const cPer    = document.getElementById('c_per_page');
const cList   = document.getElementById('clientesList');
async function loadClientes(url=null){
  if(!url){
    const params = new URLSearchParams();
    params.set('tab','clientes'); params.set('partial','clientes');
    params.set('c_search', cSearch.value||''); params.set('c_per_page', cPer.value||'10'); params.set('c_page', '1');
    url = 'cadastro.php?'+params.toString();
  } else { const u = new URL(url, location.href); u.searchParams.set('partial','clientes'); url = u.toString(); }
  cList.innerHTML = '<div class="text-center text-muted">Carregando…</div>';
  const resp = await fetch(url, {credentials:'same-origin'});
  cList.innerHTML = await resp.text();
}
if (cSearch) cSearch.addEventListener('input', debounce(()=> loadClientes(), 300));
if (cPer)    cPer.addEventListener('change', ()=> loadClientes());
if (cList)   cList.addEventListener('click', (e)=>{
  const a = e.target.closest('a.ajax-page'); if (a){ e.preventDefault(); loadClientes(a.href); }
  const btn = e.target.closest('.btn-edit-cliente');
  if (btn) openClienteModal(btn.dataset);
});

// ====== PRODUTOS: busca instantânea e paginação via AJAX ======
const pSearch = document.getElementById('p_search');
const pPer    = document.getElementById('p_per_page');
const pList   = document.getElementById('produtosList');
async function loadProdutos(url=null){
  if(!url){
    const params = new URLSearchParams();
    params.set('tab','produtos'); params.set('partial','produtos');
    params.set('p_search', pSearch.value||''); params.set('p_per_page', pPer.value||'10'); params.set('p_page', '1');
    url = 'cadastro.php?'+params.toString();
  } else { const u = new URL(url, location.href); u.searchParams.set('partial','produtos'); url = u.toString(); }
  pList.innerHTML = '<div class="text-center text-muted">Carregando…</div>';
  const resp = await fetch(url, {credentials:'same-origin'});
  pList.innerHTML = await resp.text();
}
if (pSearch) pSearch.addEventListener('input', debounce(()=> loadProdutos(), 300));
if (pPer)    pPer.addEventListener('change', ()=> loadProdutos());
if (pList)   pList.addEventListener('click', (e)=>{
  const a = e.target.closest('a.ajax-page'); if (a){ e.preventDefault(); loadProdutos(a.href); }
  const btn = e.target.closest('.btn-edit-produto');
  if (btn) openProdutoModal(btn.dataset);
});

// ====== Modal Cliente ======
const modalCliente = new bootstrap.Modal(document.getElementById('modalCliente'));
function openClienteModal(d){
  document.getElementById('cli_id').value        = d.id||'';
  document.getElementById('cli_nome').value      = d.nome||'';
  document.getElementById('cli_documento').value = d.documento||'';
  document.getElementById('cli_telefone').value  = d.telefone||'';
  document.getElementById('cli_email').value     = d.email||'';
  document.getElementById('cli_endereco').value  = d.endereco||'';
  document.getElementById('cli_feedback').textContent = '';
  modalCliente.show();
}
document.getElementById('formEditCliente').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const fd = new FormData(e.target);
  const resp = await fetch('cadastro.php', {method:'POST', body:fd, credentials:'same-origin'});
  const json = await resp.json();
  const fb = document.getElementById('cli_feedback');
  if (json.ok){ fb.textContent='Salvo com sucesso.'; fb.className='small text-success'; await loadClientes(); setTimeout(()=>modalCliente.hide(), 500); }
  else { fb.textContent=json.msg||'Erro'; fb.className='small text-danger'; }
});

// ====== Modal Produto ======
const modalProduto = new bootstrap.Modal(document.getElementById('modalProduto'));
function openProdutoModal(d){
  document.getElementById('prod_id').value        = d.id||'';
  document.getElementById('prod_nome').value      = d.nome||'';
  document.getElementById('prod_sku').value       = d.sku||'';
  document.getElementById('prod_unidade').value   = d.unidade||'';
  document.getElementById('prod_preco').value     = d.preco||'0,00';
  document.getElementById('prod_descricao').value = d.descricao||'';
  document.getElementById('prod_img_atual').value = d.imagem||'';
  const prev = document.getElementById('prod_preview');
  if (d.imagem){ prev.innerHTML = '<img src="'+d.imagem+'" class="img-thumb" alt="img">'; } else { prev.innerHTML = '<span class="text-muted small">— sem imagem —</span>'; }
  document.getElementById('prod_imagem').value = '';
  document.getElementById('prod_feedback').textContent = '';
  modalProduto.show();
}
document.getElementById('formEditProduto').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const fd = new FormData(e.target);
  const resp = await fetch('cadastro.php', {method:'POST', body:fd, credentials:'same-origin'});
  const json = await resp.json();
  const fb = document.getElementById('prod_feedback');
  if (json.ok){ fb.textContent='Salvo com sucesso.'; fb.className='small text-success'; await loadProdutos(); setTimeout(()=>modalProduto.hide(), 500); }
  else { fb.textContent=json.msg||'Erro'; fb.className='small text-danger'; }
});
</script>
</body>
</html>
