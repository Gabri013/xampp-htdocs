<?php
// editar_venda.php - Formulário para editar uma venda existente
session_start();
if (!isset($_SESSION['usuario'])) { header("Location: login.php"); exit; }
require 'includes/db.php';

$idUsuario   = (int)($_SESSION['id_usuario'] ?? 0);
$tipoUsuario = $_SESSION['tipo'] ?? 'user';

date_default_timezone_set('America/Sao_Paulo');

// --- Busca a venda a ser editada ---
$venda = null;
$id_venda = (int)($_GET['id'] ?? 0);

if ($id_venda > 0) {
    $sql = "SELECT v.*, u.nome AS vendedor
            FROM vendas v
            JOIN usuarios u ON u.id = v.id_usuario
            WHERE v.id = ?";
    
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
    $_SESSION['mensagem_erro'] = "Venda não encontrada ou você não tem permissão para editá-la.";
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

// --------- Clientes / Produtos para o formulário ----------
$clientes = [];
$cq = $conn->query("SELECT id, nome, COALESCE(documento,'') AS doc FROM clientes ORDER BY nome");
while($c=$cq->fetch_assoc()){ $clientes[]=$c; }

$produtos = [];
$pq = $conn->query("SELECT id, nome, COALESCE(descricao,'') AS descricao, COALESCE(preco_base,0) AS preco_base, COALESCE(unidade,'un') AS unidade, COALESCE(sku,'') AS sku FROM produtos ORDER BY nome");
while($p=$pq->fetch_assoc()){ $produtos[]=$p; }

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Editar Venda #<?= htmlspecialchars($venda['codigo_venda']) ?></title>
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
  /* Select2 */
  .select2-container .select2-selection--single{ height: 38px; }
  .select2-container--default .select2-selection--single .select2-selection__rendered{ line-height: 38px; }
  .select2-container--default .select2-selection--single .select2-selection__arrow{ height: 38px; }
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
      <h2 class="page-title mb-3">Editar Venda #<?= htmlspecialchars($venda['codigo_venda']) ?></h2>
      <a href="detalhes_venda.php?id=<?= (int)$id_venda ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar para Detalhes
      </a>
    </div>

    <form action="salvar_edicao_venda.php" method="POST" id="formEditarVenda" enctype="multipart/form-data">
        <input type="hidden" name="id_venda" value="<?= (int)$id_venda ?>">

        <div class="card card-elev mb-3">
            <div class="card-header">Informações Principais</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Cliente</label>
                        <select name="cliente_id" id="cliente_id" class="form-select" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($clientes as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= ((int)$venda['id_cliente'] === (int)$c['id'] ? 'selected' : '') ?>>
                                    <?= htmlspecialchars($c['nome']) ?> <?= $c['doc'] ? ' — '.htmlspecialchars($c['doc']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Forma de Pagamento (opcional)</label>
                        <input type="text" name="forma_pagamento" class="form-control" value="<?= htmlspecialchars($venda['pagamento']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Frete (R$)</label>
                        <input type="number" step="0.01" min="0" name="frete" id="nv_frete" class="form-control" value="<?= htmlspecialchars($venda['frete']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Desconto (%)</label>
                        <input type="number" step="0.01" min="0" name="desconto" id="nv_desconto" class="form-control" value="<?= htmlspecialchars($venda['desconto']) ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-elev">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Itens da Venda</span>
                <button type="button" class="btn btn-sm btn-dark" id="btnAddLinha"><i class="bi bi-plus-lg me-1"></i> Adicionar Item</button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="tblItensVenda">
                        <thead>
                            <tr>
                                <th style="width:28%">Produto</th>
                                <th>Descrição</th>
                                <th style="width:10%">Qtd</th>
                                <th style="width:12%">Unitário (R$)</th>
                                <th style="width:12%" class="text-end">Subtotal</th>
                                <th style="width:6%"></th>
                            </tr>
                        </thead>
                        <tbody>
                            </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-end">Total Produtos:</th>
                                <th class="text-end" id="nv_total_prod">R$ 0,00</th>
                                <th></th>
                            </tr>
                            <tr>
                                <th colspan="4" class="text-end">Total + Frete:</th>
                                <th class="text-end" id="nv_total_geral">R$ 0,00</th>
                                <th></th>
                            </tr>
                            <tr>
                                <th colspan="4" class="text-end">Desconto (<span id="nv_lab_desc">0,00%</span>):</th>
                                <th class="text-end text-danger" id="nv_total_desc">- R$ 0,00</th>
                                <th></th>
                            </tr>
                            <tr>
                                <th colspan="4" class="text-end">Total Final:</th>
                                <th class="text-end fw-bold" id="nv_total_final">R$ 0,00</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="card-footer text-end">
                <button class="btn btn-dark" type="submit"><i class="bi bi-check2-circle me-1"></i> Salvar Alterações</button>
            </div>
        </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
  // Dados PHP -> JS
  const PRODUTOS = <?= json_encode($produtos, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
  const ITENS_VENDA = <?= json_encode($itens_venda, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;

  // Select2
  const ensureSelect2 = () => {
    if (window.$) {
      $('#cliente_id').select2({ width:'100%' });
      document.querySelectorAll('.sel-produto').forEach(sel=>{
        if (!sel.dataset.enhanced) {
          $(sel).select2({ width:'100%' });
          sel.dataset.enhanced = '1';
        }
      });
    }
  };

  function fmtBR(n){ return 'R$ ' + (Number(n||0).toFixed(2)).replace('.', ','); }
  function recalcTotals(){
    let totalProd = 0;
    document.querySelectorAll('#tblItensVenda tbody tr').forEach(tr=>{
      const q = parseFloat(tr.querySelector('.qtd').value) || 0;
      const p = parseFloat(tr.querySelector('.unit').value) || 0;
      const sub = q*p; totalProd += sub;
      tr.querySelector('.subtotal').textContent = fmtBR(sub);
    });
    const frete = parseFloat(document.getElementById('nv_frete').value) || 0;
    const descP = parseFloat(document.getElementById('nv_desconto').value) || 0;
    const geral = totalProd + frete;
    const vDesc = geral * (descP/100);
    const final = geral - vDesc;
    document.getElementById('nv_total_prod').textContent  = fmtBR(totalProd);
    document.getElementById('nv_total_geral').textContent = fmtBR(geral);
    document.getElementById('nv_total_desc').textContent  = '- ' + fmtBR(vDesc);
    document.getElementById('nv_total_final').textContent = fmtBR(final);
    document.getElementById('nv_lab_desc').textContent    = (descP||0).toFixed(2) + '%';
  }

  function addLinha(item={}){
    const tr = document.createElement('tr');
    const options = ['<option value="">Selecione…</option>'].concat(
      PRODUTOS.map(p => `<option value="${p.id}" data-preco="${p.preco_base}" data-desc="${escapeHtml(p.descricao||'')}">${escapeHtml(p.nome)}${p.sku?(' — '+escapeHtml(p.sku)) : ''}</option>`)
    ).join('');
    tr.innerHTML = `
      <td>
        <select name="produto_id[]" class="form-select sel-produto">
          ${options}
        </select>
        <input type="hidden" name="item[]" value="${escapeHtml(item.item || '')}">
      </td>
      <td><textarea name="descricao[]" class="form-control desc" rows="1" placeholder="Descrição do item">${escapeHtml(item.descricao || '')}</textarea></td>
      <td><input type="number" min="0" step="1" name="quantidade[]" class="form-control qtd" value="${item.quantidade || 0}"></td>
      <td><input type="number" min="0" step="0.01" name="preco_unitario[]" class="form-control unit" value="${item.preco_unitario || 0}"></td>
      <td class="text-end subtotal">R$ 0,00</td>
      <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btnDel"><i class="bi bi-trash"></i></button></td>
    `;
    document.querySelector('#tblItensVenda tbody').appendChild(tr);

    const sel = tr.querySelector('.sel-produto');
    const txt = tr.querySelector('.desc');
    const qtdEl = tr.querySelector('.qtd');
    const unitEl = tr.querySelector('.unit');
    const itemHidden = tr.querySelector('input[name="item[]"]');

    if (item.produto_id) sel.value = String(item.produto_id);

    sel.addEventListener('change', ()=>{
      const opt = sel.options[sel.selectedIndex];
      const preco = parseFloat(opt?.dataset?.preco || '0') || 0;
      const d     = opt?.dataset?.desc || '';
      
      itemHidden.value = opt.textContent.trim(); // Atualiza o nome do item
      if (!unitEl.value || unitEl.value === '0') unitEl.value = preco.toFixed(2);
      if (!txt.value) txt.value = d;
      recalcTotals();
    });

    [qtdEl, unitEl].forEach(el=> el.addEventListener('input', recalcTotals));
    tr.querySelector('.btnDel').addEventListener('click', ()=>{ tr.remove(); recalcTotals(); });

    recalcTotals();
    ensureSelect2();
  }

  function escapeHtml(s){ return String(s||'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;'); }

  // Adiciona os itens da venda ao carregar a página
  document.addEventListener('DOMContentLoaded', ()=>{
    ITENS_VENDA.forEach(item => addLinha(item));
    recalcTotals();
    ensureSelect2();
  });
  
  document.getElementById('btnAddLinha').addEventListener('click', ()=> addLinha());
  document.getElementById('nv_frete').addEventListener('input', recalcTotals);
  document.getElementById('nv_desconto').addEventListener('input', recalcTotals);
</script>
</body>
</html>
