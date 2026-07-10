<?php
// vendas.php — Listagem de vendas + criação manual + EDIÇÃO via modal
session_start();
if (!isset($_SESSION['usuario'])) { header("Location: login.php"); exit; }
require 'includes/db.php';

$tipoUsuario = $_SESSION['tipo'] ?? 'user';
$idUsuario   = (int)($_SESSION['id_usuario'] ?? 0);

date_default_timezone_set('America/Sao_Paulo');

$de   = $_GET['de']   ?? date('Y-m-01');
$ate  = $_GET['ate']  ?? date('Y-m-d');
$user = ($_GET['usuario_id'] ?? 'all');

$where  = "v.criado_em >= ? AND v.criado_em < DATE_ADD(?, INTERVAL 1 DAY)";
$params = [$de, $ate];
$types  = "ss";

if ($tipoUsuario !== 'admin') {
  $where .= " AND v.id_usuario = ?";
  $params[] = $idUsuario; $types .= "i";
} else {
  if ($user !== 'all') {
    $where .= " AND v.id_usuario = ?";
    $params[] = (int)$user; $types .= "i";
  }
}

// Carrega usuários (admin)
$usuarios = [];
if ($tipoUsuario === 'admin') {
  $res = $conn->query("SELECT id, COALESCE(nome, usuario) AS nome FROM usuarios ORDER BY nome, usuario");
  while($u=$res->fetch_assoc()){ $usuarios[]=$u; }
}

// Carrega clientes e produtos para “Nova Venda”
$clientes = [];
$cq = $conn->query("SELECT id, nome, COALESCE(documento,'') AS doc, COALESCE(telefone,'') telefone, COALESCE(email,'') email, COALESCE(endereco,'') endereco FROM clientes ORDER BY nome");
while($c=$cq->fetch_assoc()){ $clientes[]=$c; }

$produtos = [];
$pq = $conn->query("SELECT id, nome, COALESCE(descricao,'') AS descricao, COALESCE(preco_base,0) AS preco_base, COALESCE(unidade,'un') AS unidade, COALESCE(sku,'') AS sku FROM produtos ORDER BY nome");
while($p=$pq->fetch_assoc()){ $produtos[]=$p; }

// Busca vendas
$sql = "SELECT v.*, COALESCE(u.nome, u.usuario) AS vendedor 
        FROM vendas v
        JOIN usuarios u ON u.id = v.id_usuario
        WHERE $where
        ORDER BY v.criado_em DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$vendas = $stmt->get_result();

// Flash
$flash=''; $tipo='';
if (!empty($_SESSION['mensagem_sucesso'])) { $flash=$_SESSION['mensagem_sucesso']; $tipo='success'; unset($_SESSION['mensagem_sucesso']); }
if (!empty($_SESSION['mensagem_erro']))     { $flash=$_SESSION['mensagem_erro'];     $tipo='danger';  unset($_SESSION['mensagem_erro']); }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Vendas - Cozinca Inox</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"/>
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
  .select2-container .select2-selection--single{ height: 38px; }
  .select2-container--default .select2-selection--single .select2-selection__rendered{ line-height: 38px; }
  .select2-container--default .select2-selection--single .select2-selection__arrow{ height: 38px; }
  .thumb{ height:36px; border-radius:6px; }
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
    <div class="d-flex align-items-center justify-content-between">
      <h2 class="page-title mb-3">Vendas</h2>
      <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#modalNovaVenda">
        <i class="bi bi-cart-plus me-1"></i> Nova Venda
      </button>
    </div>
    <p class="text-muted">Liste, filtre, crie e <strong>edite</strong> vendas.</p>

    <?php if ($flash): ?>
      <div class="alert alert-<?= $tipo ?>"><?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>

    <div class="card card-elev mb-3">
      <div class="card-header">Filtros</div>
      <div class="card-body">
        <form class="row g-3" method="GET">
          <div class="col-sm-3">
            <label class="form-label">De</label>
            <input type="date" class="form-control" name="de" value="<?= htmlspecialchars($de) ?>">
          </div>
          <div class="col-sm-3">
            <label class="form-label">Até</label>
            <input type="date" class="form-control" name="ate" value="<?= htmlspecialchars($ate) ?>">
          </div>
          <?php if ($tipoUsuario === 'admin'): ?>
            <div class="col-sm-3">
              <label class="form-label">Usuário</label>
              <select class="form-select" name="usuario_id">
                <option value="all" <?= ($user==='all'?'selected':'') ?>>Todos</option>
                <?php foreach ($usuarios as $u): ?>
                  <option value="<?= (int)$u['id'] ?>" <?= ($user==(string)$u['id']?'selected':'') ?>>
                    <?= htmlspecialchars($u['nome']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>
          <div class="col-sm-3 d-flex align-items-end">
            <button class="btn btn-dark w-100" type="submit">Aplicar</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card card-elev">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Vendas do período</span>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead>
              <tr>
                <th>Código</th>
                <th>Data</th>
                <th>Cliente</th>
                <th>Vendedor</th>
                <th class="text-end">Total</th>
                <th>Status</th>
                <th style="width:180px"></th>
              </tr>
            </thead>
            <tbody>
              <?php if ($vendas->num_rows > 0): ?>
                <?php while ($v = $vendas->fetch_assoc()): ?>
                  <tr>
                    <td><?= htmlspecialchars($v['codigo_venda']) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($v['criado_em'])) ?></td>
                    <td><?= htmlspecialchars($v['nome_cliente']) ?></td>
                    <td><?= htmlspecialchars($v['vendedor']) ?></td>
                    <td class="text-end">R$ <?= number_format((float)$v['total_final'], 2, ',', '.') ?></td>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($v['status']) ?></span></td>
                    <td class="text-end">
                      <button class="btn btn-sm btn-outline-primary btnEditar" data-id="<?= (int)$v['id'] ?>"><i class="bi bi-pencil-square me-1"></i> Editar</button>
                      <a class="btn btn-sm btn-dark" target="_blank" href="imprimir_venda.php?id=<?= (int)$v['id'] ?>">Imprimir</a>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="7" class="text-center text-muted">Nenhuma venda no período.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- MODAL NOVA VENDA (já existente) -->
<div class="modal fade" id="modalNovaVenda" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <form class="modal-content" action="salvar_venda_manual.php" method="POST" id="formNovaVenda" enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title">Criar Nova Venda</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Cliente</label>
            <select name="cliente_id" id="cliente_id" class="form-select">
              <option value="">Selecione...</option>
              <?php foreach ($clientes as $c): ?>
                <option value="<?= (int)$c['id'] ?>"
                        data-tel="<?= htmlspecialchars($c['telefone']) ?>"
                        data-email="<?= htmlspecialchars($c['email']) ?>"
                        data-endereco="<?= htmlspecialchars($c['endereco']) ?>"
                        data-doc="<?= htmlspecialchars($c['doc']) ?>">
                  <?= htmlspecialchars($c['nome']) ?> <?= $c['doc'] ? ' — '.htmlspecialchars($c['doc']) : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted">Se quiser, preencha manualmente os campos abaixo.</small>
          </div>
          <div class="col-md-6">
            <label class="form-label">Forma de Pagamento (opcional)</label>
            <input type="text" name="forma_pagamento" class="form-control" placeholder="Ex.: Pix à vista, cartão 3x, etc.">
          </div>

          <div class="col-md-3">
            <label class="form-label">Nome do Cliente</label>
            <input type="text" name="nome_cliente" class="form-control">
          </div>
          <div class="col-md-3">
            <label class="form-label">Telefone</label>
            <input type="text" name="telefone" class="form-control">
          </div>
          <div class="col-md-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control">
          </div>
          <div class="col-md-3">
            <label class="form-label">CPF/CNPJ</label>
            <input type="text" name="cnpj" class="form-control">
          </div>
          <div class="col-md-12">
            <label class="form-label">Endereço</label>
            <input type="text" name="endereco" class="form-control">
          </div>

          <div class="col-md-6">
            <label class="form-label">Frete (R$)</label>
            <input type="number" step="0.01" min="0" name="frete" id="nv_frete" class="form-control" value="0">
          </div>
          <div class="col-md-6">
            <label class="form-label">Desconto (%)</label>
            <input type="number" step="0.01" min="0" name="desconto" id="nv_desconto" class="form-control" value="0">
          </div>
        </div>

        <hr>

        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="mb-0">Itens da Venda</h6>
          <button type="button" class="btn btn-sm btn-dark" id="btnAddLinha"><i class="bi bi-plus-lg me-1"></i> Adicionar Item</button>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle" id="tblItensVenda">
            <thead>
              <tr>
                <th style="width:28%">Produto</th>
                <th>Descrição</th>
                <th style="width:10%">Qtd</th>
                <th style="width:12%">Unitário (R$)</th>
                <th style="width:12%" class="text-end">Subtotal</th>
                <th style="width:12%">Imagem</th>
                <th style="width:6%"></th>
              </tr>
            </thead>
            <tbody></tbody>
            <tfoot>
              <tr>
                <th colspan="4" class="text-end">Total Produtos:</th>
                <th class="text-end" id="nv_total_prod">R$ 0,00</th>
                <th colspan="2"></th>
              </tr>
              <tr>
                <th colspan="4" class="text-end">Total + Frete:</th>
                <th class="text-end" id="nv_total_geral">R$ 0,00</th>
                <th colspan="2"></th>
              </tr>
              <tr>
                <th colspan="4" class="text-end">Desconto (<span id="nv_lab_desc">0,00%</span>):</th>
                <th class="text-end text-danger" id="nv_total_desc">- R$ 0,00</th>
                <th colspan="2"></th>
              </tr>
              <tr>
                <th colspan="4" class="text-end">Total Final:</th>
                <th class="text-end fw-bold" id="nv_total_final">R$ 0,00</th>
                <th colspan="2"></th>
              </tr>
            </tfoot>
          </table>
        </div>

      </div>
      <div class="modal-footer">
        <button class="btn btn-dark" type="submit"><i class="bi bi-check2-circle me-1"></i> Salvar Venda</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL EDITAR VENDA -->
<div class="modal fade" id="modalEditarVenda" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <form class="modal-content" action="salvar_edicao_venda.php" method="POST" id="formEditarVenda" enctype="multipart/form-data">
      <input type="hidden" name="id_venda" id="ev_id_venda">
      <div class="modal-header">
        <h5 class="modal-title">Editar Venda <span class="text-muted" id="ev_codigo_span"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Código</label>
            <input type="text" class="form-control" id="ev_codigo" disabled>
          </div>
          <div class="col-md-3">
            <label class="form-label">Status</label>
            <select name="status" id="ev_status" class="form-select">
              <option value="aberta">aberta</option>
              <option value="faturada">faturada</option>
              <option value="cancelada">cancelada</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Desconto (%)</label>
            <input type="number" step="0.01" name="desconto" id="ev_desconto" class="form-control" value="0">
          </div>
          <div class="col-md-3">
            <label class="form-label">Frete (R$)</label>
            <input type="number" step="0.01" name="frete" id="ev_frete" class="form-control" value="0">
          </div>

          <div class="col-md-6">
            <label class="form-label">Nome do Cliente</label>
            <input type="text" name="nome_cliente" id="ev_nome_cliente" class="form-control">
          </div>
          <div class="col-md-3">
            <label class="form-label">Telefone</label>
            <input type="text" name="telefone" id="ev_telefone" class="form-control">
          </div>
          <div class="col-md-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" id="ev_email" class="form-control">
          </div>
          <div class="col-md-12">
            <label class="form-label">Endereço</label>
            <input type="text" name="endereco" id="ev_endereco" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label">CPF/CNPJ</label>
            <input type="text" name="cnpj" id="ev_cnpj" class="form-control">
          </div>
          <div class="col-md-3">
            <label class="form-label">Pagamento</label>
            <input type="text" name="pagamento" id="ev_pagamento" class="form-control">
          </div>
          <div class="col-md-3">
            <label class="form-label">Entrega</label>
            <input type="text" name="entrega" id="ev_entrega" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label">Assinatura</label>
            <input type="text" name="assinatura" id="ev_assinatura" class="form-control">
          </div>
        </div>

        <hr>
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="mb-0">Itens</h6>
          <button type="button" class="btn btn-sm btn-dark" id="ev_btnAddLinha"><i class="bi bi-plus-lg me-1"></i> Adicionar Item</button>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle" id="ev_tblItens">
            <thead>
              <tr>
                <th style="width:24%">Item</th>
                <th>Descrição</th>
                <th style="width:10%">Qtd</th>
                <th style="width:12%">Unitário (R$)</th>
                <th style="width:12%" class="text-end">Subtotal</th>
                <th style="width:14%">Imagem</th>
                <th style="width:10%">Setor</th>
                <th style="width:6%"></th>
              </tr>
            </thead>
            <tbody></tbody>
            <tfoot>
              <tr>
                <th colspan="4" class="text-end">Total Produtos:</th>
                <th class="text-end" id="ev_total_prod">R$ 0,00</th>
                <th colspan="3"></th>
              </tr>
              <tr>
                <th colspan="4" class="text-end">Total + Frete:</th>
                <th class="text-end" id="ev_total_geral">R$ 0,00</th>
                <th colspan="3"></th>
              </tr>
              <tr>
                <th colspan="4" class="text-end">Desconto (<span id="ev_lab_desc">0,00%</span>):</th>
                <th class="text-end text-danger" id="ev_total_desc">- R$ 0,00</th>
                <th colspan="3"></th>
              </tr>
              <tr>
                <th colspan="4" class="text-end">Total Final:</th>
                <th class="text-end fw-bold" id="ev_total_final">R$ 0,00</th>
                <th colspan="3"></th>
              </tr>
            </tfoot>
          </table>
        </div>

      </div>
      <div class="modal-footer">
        <button class="btn btn-dark" type="submit"><i class="bi bi-check2-circle me-1"></i> Salvar Alterações</button>
      </div>
    </form>
  </div>
</div>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
  // PHP -> JS
  const PRODUTOS = <?= json_encode($produtos, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;

  // Helpers
  const fmtBR = v => 'R$ ' + (Number(v||0).toFixed(2)).replace('.', ',');
  const escapeHtml = s => String(s||'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;');

  // ===== Nova Venda =====
  const ensureSelect2 = () => { if (window.$) { $('#cliente_id').select2({ dropdownParent: $('#modalNovaVenda'), width:'100%' }); } };

  function nv_recalc(){
    let total=0;
    document.querySelectorAll('#tblItensVenda tbody tr').forEach(tr=>{
      const q = parseFloat(tr.querySelector('.qtd').value)||0;
      const u = parseFloat(tr.querySelector('.unit').value)||0;
      const sub = q*u; total+=sub;
      tr.querySelector('.subtotal').textContent = fmtBR(sub);
    });
    const frete = parseFloat(document.getElementById('nv_frete').value)||0;
    const descP = parseFloat(document.getElementById('nv_desconto').value)||0;
    const geral = total + frete;
    const vDesc = geral*(descP/100);
    const final = geral - vDesc;
    document.getElementById('nv_total_prod').textContent = fmtBR(total);
    document.getElementById('nv_total_geral').textContent = fmtBR(geral);
    document.getElementById('nv_total_desc').textContent = '- '+fmtBR(vDesc);
    document.getElementById('nv_total_final').textContent = fmtBR(final);
    document.getElementById('nv_lab_desc').textContent = (descP||0).toFixed(2)+'%';
  }

  function nv_addLinha(){
    const tr = document.createElement('tr');
    const options = ['<option value="">Selecione…</option>'].concat(
      PRODUTOS.map(p => `<option value="${p.id}" data-preco="${p.preco_base}" data-desc="${escapeHtml(p.descricao||'')}">${escapeHtml(p.nome)}${p.sku?(' — '+escapeHtml(p.sku)) : ''}</option>`)
    ).join('');
    tr.innerHTML = `
      <td><select class="form-select sel-prod">${options}</select><input type="hidden" name="item[]" class="txt-item"></td>
      <td><textarea name="descricao[]" class="form-control desc" rows="1" placeholder="Descrição"></textarea></td>
      <td><input type="number" name="quantidade[]" class="form-control qtd" step="1" min="0" value="1"></td>
      <td><input type="number" name="preco_unitario[]" class="form-control unit" step="0.01" min="0" value="0"></td>
      <td class="text-end subtotal">R$ 0,00</td>
      <td>
        <input type="file" name="foto[]" accept="image/*" class="form-control form-control-sm">
      </td>
      <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btnDel"><i class="bi bi-trash"></i></button></td>
    `;
    document.querySelector('#tblItensVenda tbody').appendChild(tr);
    const sel = tr.querySelector('.sel-prod');
    const txtItem = tr.querySelector('.txt-item');
    const txtDesc = tr.querySelector('.desc');
    const unit = tr.querySelector('.unit');
    const qtd = tr.querySelector('.qtd');
    sel.addEventListener('change', ()=>{
      const opt = sel.options[sel.selectedIndex];
      txtItem.value = sel.value ? opt.textContent.split(' — ')[0] : '';
      if (!txtDesc.value) txtDesc.value = opt.dataset.desc || '';
      if (!unit.value || unit.value === '0') unit.value = parseFloat(opt.dataset.preco || '0').toFixed(2);
      nv_recalc();
    });
    [unit,qtd].forEach(el=> el.addEventListener('input', nv_recalc));
    tr.querySelector('.btnDel').addEventListener('click', ()=>{ tr.remove(); nv_recalc(); });
    nv_recalc();
    ensureSelect2();
    if (window.$) $(sel).select2({ dropdownParent: $('#modalNovaVenda'), width:'100%' });
  }

  document.getElementById('btnAddLinha').addEventListener('click', nv_addLinha);
  document.getElementById('nv_frete').addEventListener('input', nv_recalc);
  document.getElementById('nv_desconto').addEventListener('input', nv_recalc);
  document.getElementById('modalNovaVenda').addEventListener('shown.bs.modal', ()=>{
    if (!document.querySelector('#tblItensVenda tbody tr')) nv_addLinha();
    ensureSelect2();
  });
  // Preenchimento rápido do cliente selecionado
  document.getElementById('cliente_id').addEventListener('change', ()=>{
    const opt = document.getElementById('cliente_id').selectedOptions[0];
    if (!opt) return;
    const form = document.getElementById('formNovaVenda');
    form.nome_cliente.value = opt.textContent.split(' — ')[0] || '';
    form.telefone.value     = opt.dataset.tel || '';
    form.email.value        = opt.dataset.email || '';
    form.endereco.value     = opt.dataset.endereco || '';
    form.cnpj.value         = opt.dataset.doc || '';
  });

  // ===== Editar Venda =====
  const evModal = new bootstrap.Modal(document.getElementById('modalEditarVenda'));
  document.querySelectorAll('.btnEditar').forEach(btn=>{
    btn.addEventListener('click', async ()=>{
      const id = btn.dataset.id;
      // limpa tabela
      document.querySelector('#ev_tblItens tbody').innerHTML = '';
      // busca dados
      const resp = await fetch('vendas_detalhe.php?id='+encodeURIComponent(id), {credentials:'same-origin'});
      const data = await resp.json();
      if (!data || data.ok !== true) { alert(data?.msg || 'Erro ao carregar venda.'); return; }

      // header
      document.getElementById('ev_id_venda').value   = data.venda.id;
      document.getElementById('ev_codigo').value     = data.venda.codigo_venda;
      document.getElementById('ev_codigo_span').textContent = '#'+data.venda.codigo_venda;
      document.getElementById('ev_status').value     = data.venda.status || 'aberta';
      document.getElementById('ev_desconto').value   = data.venda.desconto || 0;
      document.getElementById('ev_frete').value      = data.venda.frete || 0;

      document.getElementById('ev_nome_cliente').value = data.venda.nome_cliente || '';
      document.getElementById('ev_telefone').value     = data.venda.telefone || '';
      document.getElementById('ev_email').value        = data.venda.email || '';
      document.getElementById('ev_endereco').value     = data.venda.endereco || '';
      document.getElementById('ev_cnpj').value         = data.venda.cnpj || '';

      document.getElementById('ev_pagamento').value    = data.venda.pagamento || '';
      document.getElementById('ev_entrega').value      = data.venda.entrega || '';
      document.getElementById('ev_assinatura').value   = data.venda.assinatura || '';

      // itens
      (data.itens || []).forEach(addEvLinha);
      ev_recalc();
      evModal.show();
    });
  });

  function addEvLinha(L){
    const tr = document.createElement('tr');
    const imgPreview = L.imagem ? `<img src="${escapeHtml(L.imagem)}" class="thumb me-2" alt="">` : `<span class="text-muted small">—</span>`;
    tr.innerHTML = `
      <td>
        <input type="text" name="item[]" class="form-control" value="${escapeHtml(L.item||'')}" placeholder="Nome do produto">
      </td>
      <td><textarea name="descricao[]" class="form-control" rows="1" placeholder="Descrição">${escapeHtml(L.descricao||'')}</textarea></td>
      <td><input type="number" name="quantidade[]" class="form-control ev_qtd" step="1" min="0" value="${Number(L.quantidade||0)}"></td>
      <td><input type="number" name="preco_unitario[]" class="form-control ev_unit" step="0.01" min="0" value="${Number(L.preco_unitario||0).toFixed(2)}"></td>
      <td class="text-end subtotal">R$ 0,00</td>
      <td>
        <div class="d-flex align-items-center">${imgPreview}</div>
        <input type="file" name="foto[]" accept="image/*" class="form-control form-control-sm mt-1">
        <input type="hidden" name="imagem_atual[]" value="${escapeHtml(L.imagem||'')}">
      </td>
      <td><input type="text" name="setor[]" class="form-control" value="${escapeHtml(L.setor||'')}" placeholder="Setor"></td>
      <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger ev_del"><i class="bi bi-trash"></i></button></td>
    `;
    document.querySelector('#ev_tblItens tbody').appendChild(tr);
    tr.querySelector('.ev_qtd').addEventListener('input', ev_recalc);
    tr.querySelector('.ev_unit').addEventListener('input', ev_recalc);
    tr.querySelector('.ev_del').addEventListener('click', ()=>{ tr.remove(); ev_recalc(); });
  }

  document.getElementById('ev_btnAddLinha').addEventListener('click', ()=> addEvLinha({quantidade:1, preco_unitario:0}));

  function ev_recalc(){
    let total=0;
    document.querySelectorAll('#ev_tblItens tbody tr').forEach(tr=>{
      const q = parseFloat(tr.querySelector('.ev_qtd').value)||0;
      const u = parseFloat(tr.querySelector('.ev_unit').value)||0;
      const sub = q*u; total+=sub;
      tr.querySelector('.subtotal').textContent = fmtBR(sub);
    });
    const frete = parseFloat(document.getElementById('ev_frete').value)||0;
    const descP = parseFloat(document.getElementById('ev_desconto').value)||0;
    const geral = total + frete;
    const vDesc = geral*(descP/100);
    const final = geral - vDesc;
    document.getElementById('ev_total_prod').textContent = fmtBR(total);
    document.getElementById('ev_total_geral').textContent = fmtBR(geral);
    document.getElementById('ev_total_desc').textContent = '- '+fmtBR(vDesc);
    document.getElementById('ev_total_final').textContent = fmtBR(final);
    document.getElementById('ev_lab_desc').textContent = (descP||0).toFixed(2)+'%';
  }

</script>
</body>
</html>
