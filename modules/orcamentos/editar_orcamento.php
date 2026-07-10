<?php
// editar_orcamento.php — Edição com setores e preenchimento confiável via JSON (descrição mais larga/alta)
session_start();
require 'db.php';

if (!isset($_SESSION['usuario'])) { header('Location: login.php'); exit; }
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) { die('ID inválido.'); }

$id_orcamento = (int)$_GET['id'];

// Carrega orçamento
$stmt = $conn->prepare("SELECT * FROM orcamentos WHERE id = ?");
$stmt->bind_param('i', $id_orcamento);
$stmt->execute();
$orc = $stmt->get_result()->fetch_assoc();
if (!$orc) { die('Orçamento não encontrado.'); }

// Carrega itens
$itens = [];
$stmt_i = $conn->prepare("SELECT id, item, quantidade, descricao, preco_unitario, preco_total, imagem, setor FROM orcamento_itens WHERE id_orcamento = ? ORDER BY COALESCE(NULLIF(setor,''), 'zzz'), id");
$stmt_i->bind_param('i', $id_orcamento);
$stmt_i->execute();
$res_i = $stmt_i->get_result();
while ($row = $res_i->fetch_assoc()) { $itens[] = $row; }

$itens_json = json_encode($itens, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Editar Orçamento — <?= htmlspecialchars($orc['codigo_orcamento']) ?></title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"/>
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
  .btn-cozinca { background:var(--cozinca); color:#fff; border:0; }
  .btn-cozinca:hover { background:#e04a0c; color:#fff; }
  .tag-setor { background:#fff2e8; border:1px dashed var(--cozinca); color:#c3420b; padding:.25rem .5rem; border-radius:8px; font-weight:600; }
  .total-card { background:#fff; border:1px solid #eee; border-left:6px solid var(--cozinca); border-radius:12px; padding:12px 14px; }
  .total-card .label{ color:#666; font-size:.9rem }
  .total-card .value{ font-weight:800; font-size:1.2rem; color:#111 }
  .table thead th{ background:#1c1c1c; color:#fff; border:0; }
  .table tbody td { vertical-align: middle; }
  .thumb { height:42px; border-radius:6px; display:block; }

  /* >>> Novo: textarea de descrição mais largo/alto e confortável */
  .desc-field{
    width: 100%;
    min-height: 110px;
    resize: vertical;
    line-height: 1.3;
  }
</style>
</head>
<body>
<div class="d-flex">
  <!-- MENU LATERAL -->
  <div class="sidebar p-3">
    <img src="imagens/cozincainox.png" class="logo" alt="Cozinca">
    <a href="dashboard.php">🏠 Painel</a>
    <a href="criar_orcamento.php">📝 Criar Orçamento</a>
    <a href="listar_orcamentos.php">📄 Listar Orçamentos</a>
    <a href="relatorios.php">📊 Relatórios</a>
    <?php if (($_SESSION['tipo'] ?? 'user') === 'admin'): ?>
      <a href="admin.php">⚙️ Gerenciar Usuários</a>
    <?php endif; ?>
    <a href="logout.php">🚪 Sair</a>
  </div>

  <!-- CONTEÚDO -->
  <div class="flex-grow-1 p-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h2 class="page-title mb-0">Editar Orçamento</h2>
      <span class="badge text-bg-light border">Código: <strong><?= htmlspecialchars($orc['codigo_orcamento']) ?></strong></span>
    </div>

    <form action="salvar_edicao_orcamento.php" method="POST" enctype="multipart/form-data" id="formOrc">
      <input type="hidden" name="id_orcamento" value="<?= (int)$id_orcamento ?>">

      <!-- DADOS DO CLIENTE -->
      <div class="card card-elev mb-4">
        <div class="card-header">Dados do Cliente</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Nome do Cliente</label>
              <input type="text" name="nome_cliente" class="form-control" value="<?= htmlspecialchars($orc['nome_cliente']) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Telefone</label>
              <input type="text" name="telefone" class="form-control" value="<?= htmlspecialchars($orc['telefone']) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($orc['email']) ?>">
            </div>
            <div class="col-md-8">
              <label class="form-label">Endereço</label>
              <input type="text" name="endereco" class="form-control" value="<?= htmlspecialchars($orc['endereco']) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">CPF/CNPJ</label>
              <input type="text" name="cnpj" class="form-control" value="<?= htmlspecialchars($orc['cnpj']) ?>">
            </div>
          </div>
        </div>
      </div>

      <!-- ITENS / SETORES -->
      <div class="card card-elev mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span>Produtos e Setores</span>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-cozinca" id="btnAddItemSolto"><i class="bi bi-plus-lg"></i> Produto sem setor</button>
            <button type="button" class="btn btn-sm btn-outline-dark" id="btnAddSetor"><i class="bi bi-layout-text-sidebar-reverse"></i> Adicionar Setor</button>
          </div>
        </div>
        <div class="card-body">
          <div id="setoresWrap" class="mb-3"></div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="tblItens">
              <thead>
                <tr>
                  <th style="width: 14rem;">Produto</th>
                  <!-- >>> Novo: dar mais espaço para descrição -->
                  <th style="min-width: 28rem; width: 40%;">Descrição</th>
                  <th style="width: 6rem;">Qtd</th>
                  <th style="width: 10rem;">Unitário (R$)</th>
                  <th style="width: 10rem;">Subtotal</th>
                  <th style="width: 12rem;">Foto</th>
                  <th style="width: 8rem;">Setor</th>
                  <th style="width: 3rem;"></th>
                </tr>
              </thead>
              <tbody id="tbodyItens"></tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- TOTAIS -->
      <div class="row g-3 mb-4">
        <div class="col-md-3">
          <div class="total-card">
            <div class="label">Total Produtos</div>
            <div class="value" id="vTotalProdutos">R$ 0,00</div>
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Frete (R$)</label>
          <input type="text" class="form-control" id="frete" name="frete" value="<?= number_format((float)$orc['frete'], 2, ',', '.') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Desconto (%)</label>
          <input type="text" class="form-control" id="desconto" name="desconto" value="<?= number_format((float)$orc['desconto'], 2, ',', '.') ?>">
        </div>
        <div class="col-md-3">
          <div class="total-card">
            <div class="label">Total Final</div>
            <div class="value" id="vTotalFinal">R$ 0,00</div>
          </div>
        </div>
      </div>

      <!-- CONDIÇÕES -->
      <div class="card card-elev mb-4">
        <div class="card-header">Condições da Proposta</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Forma de Pagamento</label>
              <input type="text" name="forma_pagamento" class="form-control" value="<?= htmlspecialchars($orc['pagamento']) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Prazo para Entrega</label>
              <input type="text" name="condicoes_entrega" class="form-control" value="<?= htmlspecialchars($orc['entrega']) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Assinatura</label>
              <input type="text" name="assinatura_vendedor" class="form-control" value="<?= htmlspecialchars($orc['assinatura']) ?>">
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex justify-content-end">
        <button type="submit" class="btn btn-cozinca btn-lg px-4">
          <i class="bi bi-check2-circle me-1"></i> Salvar Alterações
        </button>
      </div>
    </form>
  </div>
</div>

<script>
  // Dados vindos do PHP
  window.EXISTING_ITEMS = <?= $itens_json ?>;
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
/* ===== Helpers ===== */
const fmtMoney = (v) => 'R$ ' + Number(v||0).toFixed(2).replace('.', ',');
const parseMoney = (str) => Number(String(str||'').replace(/\./g,'').replace(',','.')) || 0;

const tbody = document.getElementById('tbodyItens');
const setoresWrap = document.getElementById('setoresWrap');

document.getElementById('btnAddItemSolto').addEventListener('click', ()=> addRow({}));
document.getElementById('btnAddSetor').addEventListener('click', ()=>{
  const nome = prompt('Nome do setor (ex.: Cozinha 1, Copa, etc.)');
  if (!nome) return;
  ensureSetorTag(nome);
  addRow({ setor: nome });
});

function ensureSetorTag(nome){
  // não duplica
  if ([...setoresWrap.querySelectorAll('.tag-setor')].some(t => t.textContent.includes(nome))) return;
  const tag = document.createElement('div');
  tag.className = 'mb-2';
  tag.innerHTML = `<span class="tag-setor"><i class="bi bi-collection me-1"></i>Setor: ${nome}</span>
                   <button type="button" class="btn btn-sm btn-link text-danger ms-1 p-0 align-baseline" onclick="this.parentElement.remove()">remover</button>`;
  setoresWrap.appendChild(tag);
}

function addRow({id='', item='', descricao='', quantidade='', preco_unitario='', imagem='', setor=''}) {
  const qtdv  = quantidade !== '' ? String(quantidade).replace('.', ',') : '';
  const unitv = preco_unitario !== '' ? Number(preco_unitario).toFixed(2).replace('.', ',') : '';
  const sub   = (parseFloat(quantidade||0) * parseFloat(preco_unitario||0)) || 0;

  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><input type="text" name="item[]" class="form-control form-control-sm" value="${escapeHtml(item)}" placeholder="Produto"></td>
    <!-- >>> Novo: textarea maior e mais confortável -->
    <td><textarea name="descricao[]" class="form-control form-control-sm desc-field" rows="4" placeholder="Descrição">${escapeHtml(descricao)}</textarea></td>
    <td style="max-width:6rem;"><input type="text" name="quantidade[]" class="form-control form-control-sm qtd" value="${qtdv}" placeholder="0"></td>
    <td style="max-width:10rem;"><input type="text" name="preco_unitario[]" class="form-control form-control-sm money-unit" value="${unitv}" placeholder="0,00"></td>
    <td class="text-end fw-bold subtotal">${fmtMoney(sub)}</td>
    <td>
      ${imagem ? `<img src="${escapeAttr(imagem)}" class="thumb mb-1" alt="img">` : `<span class="text-muted small">sem foto</span>`}
      <input type="file" name="foto[]" accept="image/*" class="form-control form-control-sm mt-1">
      <input type="hidden" name="imagem_atual[]" value="${escapeAttr(imagem||'')}">
    </td>
    <td>
      <input type="text" class="form-control form-control-sm setor-visivel" value="${escapeAttr(setor||'')}" placeholder="Setor">
      <input type="hidden" name="setor[]" value="${escapeAttr(setor||'')}">
    </td>
    <td class="text-center">
      <button type="button" class="btn btn-sm btn-outline-danger" title="Remover"><i class="bi bi-trash"></i></button>
    </td>
  `;
  // eventos
  tr.querySelector('button.btn-outline-danger').addEventListener('click', ()=>{ tr.remove(); recalcTotals(); });
  tr.querySelector('.qtd').addEventListener('input', ()=> recalcRow(tr));
  tr.querySelector('.money-unit').addEventListener('input', ()=> recalcRow(tr));
  const setorTxt = tr.querySelector('.setor-visivel');
  const setorHid = tr.querySelector('input[name="setor[]"]');
  setorTxt.addEventListener('input', ()=> setorHid.value = setorTxt.value);

  // se houver setor, garante tag visível
  if (setor && setor.trim() !== '') ensureSetorTag(setor);

  tbody.appendChild(tr);
  recalcRow(tr);
}

function recalcRow(tr){
  const qtd  = parseMoney(tr.querySelector('input[name="quantidade[]"]').value);
  const unit = parseMoney(tr.querySelector('input[name="preco_unitario[]"]').value);
  tr.querySelector('.subtotal').textContent = fmtMoney(qtd*unit);
  recalcTotals();
}

function recalcTotals(){
  let totalProdutos = 0;
  document.querySelectorAll('#tbodyItens .subtotal').forEach(td=>{
    totalProdutos += parseMoney(td.textContent.replace('R$',''));
  });
  document.getElementById('vTotalProdutos').textContent = fmtMoney(totalProdutos);

  const frete = parseMoney(document.getElementById('frete').value);
  const descP = parseMoney(document.getElementById('desconto').value);
  const base  = totalProdutos + frete;
  const vDesc = base * (descP/100);
  const final = base - vDesc;
  document.getElementById('vTotalFinal').textContent = fmtMoney(final);
}

function escapeHtml(s){ return String(s||'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;'); }
function escapeAttr(s){ return String(s||'').replaceAll('"','&quot;'); }

/* ========= Carregar itens existentes ========= */
document.addEventListener('DOMContentLoaded', ()=>{
  const itens = (window.EXISTING_ITEMS || []);
  if (itens.length === 0) {
    // adiciona 1 linha vazia para começar
    addRow({});
  } else {
    // Primeiro, garantir tags de todos os setores existentes
    const setores = new Set();
    itens.forEach(it => { const s = (it.setor||'').trim(); if (s) setores.add(s); });
    setores.forEach(s => ensureSetorTag(s));

    // Inserir as linhas (itens sem setor e com setor)
    itens.forEach(it => addRow({
      id: it.id,
      item: it.item,
      descricao: it.descricao,
      quantidade: it.quantidade,
      preco_unitario: it.preco_unitario,
      imagem: it.imagem || '',
      setor: (it.setor||'')
    }));
  }

  // Recalcula quando frete/desc mudarem
  document.getElementById('frete').addEventListener('input', recalcTotals);
  document.getElementById('desconto').addEventListener('input', recalcTotals);
  recalcTotals();
});
</script>
</body>
</html>
