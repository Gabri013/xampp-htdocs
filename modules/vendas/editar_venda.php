<?php
require_once '../../config/config.php';
requirePermission(['master', 'vendedor']);

$id = $_GET['id'] ?? null;
if (!$id) {
    setError('Venda não encontrada.');
    header('Location: index.php');
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM vendas WHERE id = ?");
$stmt->execute([$id]);
$venda = $stmt->fetch();

if (!$venda) {
    setError('Venda não encontrada.');
    header('Location: index.php');
    exit;
}

// Restrição para vendedores: editar apenas suas próprias vendas
$usuario_logado = getCurrentUser();
if ($usuario_logado['tipo'] === 'vendedor' && $venda['usuario_id'] != $usuario_logado['id']) {
    setError('Você não tem permissão para editar esta venda.');
    header('Location: index.php');
    exit;
}

$page_title = 'Editar Venda ' . $venda['numero'];

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = $_POST['cliente_id'];
    $data_venda = dateToMysql($_POST['data_venda']);
    $data_termino = !empty($_POST['data_termino']) ? dateToMysql($_POST['data_termino']) : null;
    $prioridade = $_POST['prioridade'] ?? 'verde';
    $forma_pagamento = $_POST['forma_pagamento'] ?? null;
    $desconto = floatval($_POST['desconto_final'] ?? 0);
    $observacoes = sanitize($_POST['observacoes']);
    $observacoes_venda = sanitize($_POST['observacoes_venda'] ?? '');
    $itens_json = $_POST['itens_json'] ?? '[]';
    $itens = json_decode($itens_json, true);
    
    if (empty($itens)) {
        setError('Adicione pelo menos um item à venda.');
    } else {
        try {
            $db->beginTransaction();
            
            $subtotal = 0;
            foreach ($itens as $item) { $subtotal += $item['valor_total']; }
            $valor_total = $subtotal - $desconto;
            
            // Atualizar venda
            $stmt = $db->prepare("UPDATE vendas SET cliente_id = ?, data_venda = ?, valor_total = ?, desconto = ?, forma_pagamento = ?, observacoes = ?, observacoes_venda = ? WHERE id = ?");
            $stmt->execute([$cliente_id, $data_venda, $valor_total, $desconto, $forma_pagamento, $observacoes, $observacoes_venda, $id]);
            
            // Atualizar itens (remover antigos e inserir novos)
            $stmt = $db->prepare("DELETE FROM vendas_itens WHERE venda_id = ?");
            $stmt->execute([$id]);
            
            $stmt = $db->prepare("INSERT INTO vendas_itens (venda_id, produto_id, descricao_manual, quantidade, valor_unitario, valor_total) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($itens as $item) {
                $produto_id = !empty($item['produto_id']) ? $item['produto_id'] : null;
                $stmt->execute([$id, $produto_id, $item['descricao'], $item['quantidade'], $item['valor_unitario'], $item['valor_total']]);
            }
            
            // Atualizar prioridade na O.S relacionada
            $stmt = $db->prepare("UPDATE ordens_servico SET cliente_id = ?, prioridade = ?, data_termino = ? WHERE venda_id = ?");
            $stmt->execute([$cliente_id, $prioridade, $data_termino, $id]);
            
            $db->commit();
            setSuccess('Venda atualizada com sucesso!');
            header('Location: detalhes_venda.php?id=' . $id);
            exit;
        } catch (Exception $e) {
            if(isset($db)) $db->rollBack();
            setError('Erro: ' . $e->getMessage());
        }
    }
}

$clientes = $db->query("SELECT id, razao_social FROM clientes ORDER BY razao_social")->fetchAll();
$produtos = $db->query("SELECT id, codigo, nome, valor FROM produtos WHERE status = 'ativo' ORDER BY nome")->fetchAll();

// Buscar itens atuais
$stmt = $db->prepare("SELECT vi.*, p.nome as produto_nome FROM vendas_itens vi LEFT JOIN produtos p ON vi.produto_id = p.id WHERE vi.venda_id = ?");
$stmt->execute([$id]);
$itens_atuais = $stmt->fetchAll();

$itens_formatados = [];
foreach ($itens_atuais as $i) {
    $itens_formatados[] = [
        'uid' => time() . rand(1000, 9999),
        'produto_id' => $i['produto_id'],
        'descricao' => $i['produto_id'] ? $i['produto_nome'] : $i['descricao_manual'],
        'quantidade' => (float)$i['quantidade'],
        'valor_unitario' => (float)$i['valor_unitario'],
        'valor_total' => (float)$i['valor_total']
    ];
}

// Buscar dados da O.S
$stmt = $db->prepare("SELECT prioridade, data_termino FROM ordens_servico WHERE venda_id = ?");
$stmt->execute([$id]);
$os_info = $stmt->fetch();
$prioridade_atual = $os_info['prioridade'] ?? 'verde';
$data_termino_atual = $os_info['data_termino'] ?? '';

include '../../includes/header_vendedor.php';
?>

<style>
.venda-itens-table {
    table-layout: fixed;
    width: 100%;
}

.venda-itens-table td,
.venda-itens-table th {
    white-space: normal;
    vertical-align: top;
}

.venda-itens-table .col-descricao {
    width: 50%;
}

.venda-itens-table .col-qtd,
.venda-itens-table .col-unit,
.venda-itens-table .col-total {
    width: 14%;
}

.venda-itens-table .col-acao {
    width: 8%;
}

.venda-desc-cell {
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    word-break: break-word;
}
</style>

<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-card">
            <div class="vend-card-head">
                <h3>Editar Venda <?php echo $venda['numero']; ?></h3>
                <a href="detalhes_venda.php?id=<?php echo $id; ?>" class="vbtn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
            </div>
            <div style="padding:24px">
                <form method="POST" id="formVenda" enctype="multipart/form-data">
            <div class="form-row">
                <div class="form-group">
                    <label>Cliente *</label>
                    <select id="cliente_id" name="cliente_id" class="form-control" required>
                        <?php foreach ($clientes as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $c['id'] == $venda['cliente_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['razao_social']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Data Venda *</label>
                    <input type="date" name="data_venda" class="form-control" value="<?php echo $venda['data_venda']; ?>" required>
                </div>
                <div class="form-group">
                    <label>Data Término</label>
                    <input type="date" name="data_termino" class="form-control" value="<?php echo htmlspecialchars($data_termino_atual); ?>">
                </div>
                <div class="form-group">
                    <label>Prioridade O.S</label>
                    <select name="prioridade" class="form-control">
                        <option value="verde" <?php echo $prioridade_atual == 'verde' ? 'selected' : ''; ?>>🟢 Verde</option>
                        <option value="amarelo" <?php echo $prioridade_atual == 'amarelo' ? 'selected' : ''; ?>>🟡 Amarelo</option>
                        <option value="vermelho" <?php echo $prioridade_atual == 'vermelho' ? 'selected' : ''; ?>>🔴 Vermelho</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Forma de Pagamento</label>
                    <select name="forma_pagamento" id="forma_pagamento" class="form-control">
                        <option value="avista" <?php echo $venda['forma_pagamento'] == 'avista' ? 'selected' : ''; ?>>À Vista</option>
                        <option value="cartao" <?php echo $venda['forma_pagamento'] == 'cartao' ? 'selected' : ''; ?>>Cartão</option>
                        <option value="boleto" <?php echo $venda['forma_pagamento'] == 'boleto' ? 'selected' : ''; ?>>Boleto</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Observações da Venda</label>
                <textarea name="observacoes_venda" class="form-control" rows="2" placeholder="Informações comerciais visíveis apenas no módulo de vendas..."><?php echo htmlspecialchars($venda['observacoes_venda'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label>Observações para Produção/O.S.</label>
                <textarea name="observacoes" class="form-control" rows="2"><?php echo htmlspecialchars($venda['observacoes']); ?></textarea>
            </div>
            
            <hr>
            <h4>Itens da Venda</h4>
            
            <div class="form-row" style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <div class="form-group" style="flex: 2;">
                    <label>Produto</label>
                    <div style="display: flex; gap: 10px;">
                        <select id="sel_tipo" class="form-control" style="width: 120px;">
                            <option value="P">Cadastrado</option>
                            <option value="M">Manual</option>
                        </select>
                        <div id="div_prod" style="flex: 1;">
                            <select id="sel_prod" class="form-control">
                                <option value="">Selecione...</option>
                                <?php foreach ($produtos as $p): ?>
                                    <option value="<?php echo $p['id']; ?>" data-nome="<?php echo htmlspecialchars($p['nome']); ?>" data-valor="<?php echo $p['valor']; ?>">
                                        <?php echo htmlspecialchars($p['codigo'] . ' - ' . $p['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="div_manual" style="flex: 1; display:none;">
                            <input type="text" id="inp_manual" class="form-control" placeholder="Descrição...">
                        </div>
                    </div>
                </div>
                <div class="form-group" style="flex: 0.5;"><label>Qtd</label><input type="number" id="inp_qtd" class="form-control" value="1" step="0.01"></div>
                <div class="form-group" style="flex: 1;"><label>Valor</label><input type="text" id="inp_vlr" class="form-control"></div>
                <div class="form-group" style="flex: 0.5;"><label>&nbsp;</label><button type="button" class="vbtn-sm btn-block" id="btn_add_item"><i class="fas fa-plus"></i></button></div>
            </div>
            
            <table class="table venda-itens-table">
                <colgroup>
                    <col class="col-descricao">
                    <col class="col-qtd">
                    <col class="col-unit">
                    <col class="col-total">
                    <col class="col-acao">
                </colgroup>
                <thead><tr><th>Descrição</th><th>Qtd</th><th>Unit.</th><th>Total</th><th></th></tr></thead>
                <tbody id="corpo_tabela"></tbody>
            </table>

            <div class="form-row mt-20" style="background: #e9ecef; padding: 15px; border-radius: 5px;">
                <div class="form-group" style="flex: 1;">
                    <label>Desconto (%)</label>
                    <input type="number" id="desc_porc" class="form-control" value="0" step="0.01">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Desconto (R$)</label>
                    <input type="text" id="desc_valor" class="form-control" value="<?php echo number_format($venda['desconto'], 2, ',', '.'); ?>">
                </div>
                <div class="form-group" style="flex: 2; text-align: right;">
                    <div style="font-size: 14px; color: #666;">Subtotal: <span id="txt_subtotal">R$ 0,00</span></div>
                    <div style="font-size: 14px; color: #d9534f;" id="resumo_desconto">Desconto: R$ 0,00</div>
                    <div style="font-size: 20px; font-weight: bold; color: var(--primary-color);">Total: <span id="txt_total">R$ 0,00</span></div>
                    <div id="info_pagamento" style="font-size: 12px; color: #28a745; font-weight: bold;"></div>
                </div>
            </div>
            
            <input type="hidden" name="itens_json" id="itens_json" value='<?php echo json_encode($itens_formatados); ?>'>
            <input type="hidden" name="desconto_final" id="desconto_final" value="<?php echo $venda['desconto']; ?>">
            
            <div class="mt-20">
                <button type="submit" class="vbtn-sm btn-lg">Salvar Alterações</button>
                <a href="detalhes_venda.php?id=<?php echo $id; ?>" class="btn btn-secondary btn-lg">Cancelar</a>
</div>
            </form>
        </div>
    </div>
    </div>
</div>

<script>
function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

(function() {
    let itens = <?php echo json_encode($itens_formatados); ?>;
    let subtotal = 0;
    let descontoFinal = <?php echo (float)$venda['desconto']; ?>;

    const btnAdd = document.getElementById('btn_add_item');
    const corpoTabela = document.getElementById('corpo_tabela');
    const selTipo = document.getElementById('sel_tipo');
    const selProd = document.getElementById('sel_prod');
    const inpManual = document.getElementById('inp_manual');
    const inpQtd = document.getElementById('inp_qtd');
    const inpVlr = document.getElementById('inp_vlr');
    const txtSubtotal = document.getElementById('txt_subtotal');
    const txtTotal = document.getElementById('txt_total');
    const descPorc = document.getElementById('desc_porc');
    const descValor = document.getElementById('desc_valor');
    const resumoDesconto = document.getElementById('resumo_desconto');
    const infoPagamento = document.getElementById('info_pagamento');
    const selPagamento = document.getElementById('forma_pagamento');
    const hiddenJson = document.getElementById('itens_json');
    const hiddenDesconto = document.getElementById('desconto_final');

    selTipo.onchange = function() {
        document.getElementById('div_prod').style.display = this.value === 'P' ? 'block' : 'none';
        document.getElementById('div_manual').style.display = this.value === 'M' ? 'block' : 'none';
    };

    selProd.onchange = function() {
        const opt = this.options[this.selectedIndex];
        if(opt.value) inpVlr.value = opt.dataset.valor.replace('.', ',');
    };

    btnAdd.onclick = function() {
        let desc = '';
        let prod_id = null;
        if(selTipo.value === 'P') {
            const opt = selProd.options[selProd.selectedIndex];
            if(!opt.value) return alert('Selecione um produto');
            prod_id = opt.value;
            desc = opt.dataset.nome;
        } else {
            desc = inpManual.value.trim();
            if(!desc) return alert('Informe a descrição');
        }
        const qtd = parseFloat(inpQtd.value) || 0;
        const vlr = parseFloat(inpVlr.value.replace('.', '').replace(',', '.')) || 0;
        if(qtd <= 0) return alert('Qtd inválida');
        itens.push({ uid: Date.now(), produto_id: prod_id, descricao: desc, quantidade: qtd, valor_unitario: vlr, valor_total: qtd * vlr });
        render();
        selProd.value = ''; inpManual.value = ''; inpVlr.value = ''; inpQtd.value = '1';
    };

    corpoTabela.onclick = function(e) {
        const btn = e.target.closest('.btn-del');
        if(btn) {
            const uid = btn.dataset.uid;
            itens = itens.filter(i => i.uid != uid);
            render();
        }
    };

    descPorc.oninput = function() {
        const p = parseFloat(this.value) || 0;
        descontoFinal = (subtotal * p) / 100;
        descValor.value = descontoFinal.toLocaleString('pt-BR', {minimumFractionDigits: 2});
        calcularTotal();
    };

    descValor.oninput = function() {
        descontoFinal = parseFloat(this.value.replace('.', '').replace(',', '.')) || 0;
        const p = subtotal > 0 ? (descontoFinal / subtotal) * 100 : 0;
        descPorc.value = p.toFixed(2);
        calcularTotal();
    };

    selPagamento.onchange = calcularTotal;

    function calcularTotal() {
        const total = subtotal - descontoFinal;
        txtTotal.textContent = 'R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits: 2});
        resumoDesconto.textContent = 'Desconto: R$ ' + descontoFinal.toLocaleString('pt-BR', {minimumFractionDigits: 2});
        hiddenDesconto.value = descontoFinal;
        const pagNome = selPagamento.options[selPagamento.selectedIndex].text;
        infoPagamento.textContent = `Forma de pagamento: ${pagNome} com desconto no valor de R$ ${descontoFinal.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
    }

    function render() {
        corpoTabela.innerHTML = '';
        subtotal = 0;
        if(itens.length === 0) {
            corpoTabela.innerHTML = '<tr><td colspan="5" class="text-center">Nenhum item</td></tr>';
        } else {
            itens.forEach(i => {
                subtotal += i.valor_total;
                const tr = document.createElement('tr');
                tr.innerHTML = `<td class="venda-desc-cell">${escapeHtml(i.descricao)}</td><td>${i.quantidade}</td><td>R$ ${i.valor_unitario.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td><td>R$ ${i.valor_total.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td><td><button type="button" class="vbtn-sm btn-sm btn-del" data-uid="${i.uid}"><i class="fas fa-times"></i></button></td>`;
                corpoTabela.appendChild(tr);
            });
        }
        txtSubtotal.textContent = 'R$ ' + subtotal.toLocaleString('pt-BR', {minimumFractionDigits: 2});
        
        // Se for a primeira carga, calcular a porcentagem do desconto vindo do banco
        if (subtotal > 0 && descPorc.value == 0 && descontoFinal > 0) {
            descPorc.value = ((descontoFinal / subtotal) * 100).toFixed(2);
        }
        
        calcularTotal();
        hiddenJson.value = JSON.stringify(itens);
    }

render();
})();
</script>

<?php include '../../includes/footer_vendedor.php'; ?>

