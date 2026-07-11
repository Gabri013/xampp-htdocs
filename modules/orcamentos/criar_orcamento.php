<?php
require_once '../../config/config.php';
requirePermission(['master', 'vendedor']);

$page_title = 'Criar Orçamento';
$db = getDB();

// Busca clientes e produtos
$clientes = $db->query("SELECT id, razao_social FROM clientes ORDER BY razao_social")->fetchAll();
$produtos = $db->query("SELECT id, codigo, nome, valor FROM produtos WHERE status = 'ativo' ORDER BY nome")->fetchAll();

// Processar salvamento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = $_POST['cliente_id'] ?: null;
    $nome_cliente = sanitize($_POST['nome_cliente'] ?? '');
    $endereco = sanitize($_POST['endereco'] ?? '');
    $telefone = sanitize($_POST['telefone'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $cnpj = sanitize($_POST['cnpj'] ?? '');
    $forma_pagamento = sanitize($_POST['forma_pagamento'] ?? '');
    $condicoes_entrega = sanitize($_POST['condicoes_entrega'] ?? '');
    $frete = (float) ($_POST['frete'] ?? 0);
    $desconto = (float) ($_POST['desconto'] ?? 0);
    $itens_json = $_POST['itens_json'] ?? '[]';
    $itens = json_decode($itens_json, true);

    try {
        if (empty($cliente_id)) {
            throw new Exception('Selecione um cliente cadastrado. Caso o cliente ainda não exista, cadastre-o primeiro em Cadastros > Clientes.');
        }

        $db->beginTransaction();
        $numero = getNextNumber('orcamentos', 'ORC-');

        $total = 0;
        foreach ($itens as $item) {
            $total += ($item['quantidade'] ?? 0) * ($item['valor_unitario'] ?? 0);
        }
        $total_final = $total * (1 - $desconto / 100) + $frete;

        // A tabela orcamentos não possui colunas para os dados manuais do cliente,
        // forma de pagamento, prazo de entrega e frete — preservados em observacoes.
        $observacoes_partes = [];
        if ($nome_cliente) $observacoes_partes[] = "Cliente (manual): $nome_cliente";
        if ($endereco) $observacoes_partes[] = "Endereço: $endereco";
        if ($telefone) $observacoes_partes[] = "Telefone: $telefone";
        if ($email) $observacoes_partes[] = "Email: $email";
        if ($cnpj) $observacoes_partes[] = "CNPJ: $cnpj";
        if ($forma_pagamento) $observacoes_partes[] = "Forma de pagamento: $forma_pagamento";
        if ($condicoes_entrega) $observacoes_partes[] = "Prazo para entrega: $condicoes_entrega";
        if ($frete > 0) $observacoes_partes[] = "Frete: R$ " . number_format($frete, 2, ',', '.');
        $observacoes = $observacoes_partes ? implode("\n", $observacoes_partes) : null;

        $stmt = $db->prepare("INSERT INTO orcamentos (numero, cliente_id, usuario_id, data_orcamento, valor_total, desconto, status, observacoes) VALUES (?, ?, ?, CURDATE(), ?, ?, 'pendente', ?)");
        $stmt->execute([$numero, $cliente_id, $_SESSION['usuario_id'], $total_final, $desconto, $observacoes]);
        $orcamento_id = $db->lastInsertId();

        foreach ($itens as $item) {
            $stmt = $db->prepare("INSERT INTO orcamentos_itens (orcamento_id, produto_id, descricao_manual, quantidade, valor_unitario, valor_total) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $orcamento_id,
                !empty($item['produto_id']) ? $item['produto_id'] : null,
                $item['descricao'] ?? '',
                $item['quantidade'] ?? 1,
                $item['valor_unitario'] ?? 0,
                ($item['quantidade'] ?? 1) * ($item['valor_unitario'] ?? 0)
            ]);
        }

        $db->commit();
        setSuccess("Orçamento $numero criado com sucesso!");
        header('Location: index.php');
        exit;
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        setError("Erro ao salvar: " . $e->getMessage());
    }
}

include '../../includes/header_vendedor.php';
?>

<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-page-head">
            <div><h1 class="vend-page-title">Criar Orçamento</h1></div>
            <a href="index.php" class="vbtn-sm"><i class="fas fa-list"></i> Listar Orçamentos</a>
        </div>
        
        <form method="POST" id="formOrc">
            <div class="vend-card">
                <div class="vend-card-head"><div class="vend-card-title">Dados do Cliente</div></div>
                <div style="padding:20px">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Cliente (selecionado)</label>
                            <select name="cliente_id" id="cliente_id" class="form-control">
                                <option value="">Selecione...</option>
                                <?php foreach ($clientes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['razao_social']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nome do Cliente (se não cadastrado)</label>
                            <input type="text" name="nome_cliente" id="nome_cliente" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Endereço</label>
                            <input type="text" name="endereco" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Telefone</label>
                            <input type="text" name="telefone" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">CNPJ</label>
                            <input type="text" name="cnpj" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Forma de Pagamento</label>
                            <input type="text" name="forma_pagamento" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Prazo para Entrega</label>
                            <input type="text" name="condicoes_entrega" class="form-control">
                        </div>
                    </div>
                </div>
            </div>

            <div class="vend-card" style="margin-top:20px">
                <div class="vend-card-head"><div class="vend-card-title">Produtos / Itens</div></div>
                <div style="padding:20px">
                    <div id="itens-container"></div>
                    <button type="button" id="add-item" class="vbtn-sm btn-success"><i class="fas fa-plus"></i> Adicionar Item</button>
                </div>
            </div>

            <div class="vend-card" style="margin-top:20px">
                <div class="vend-card-head"><div class="vend-card-title">Totais</div></div>
                <div style="padding:20px">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Frete (R$)</label>
                            <input type="number" step="0.01" name="frete" id="frete" class="form-control" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Desconto (%)</label>
                            <input type="number" step="0.01" name="desconto" id="desconto" class="form-control" value="0">
                        </div>
                    </div>
                    <div class="mt-3">
                        <h5>Total Final: <span id="total-final">R$ 0,00</span></h5>
                    </div>
                </div>
            </div>

            <input type="hidden" name="itens_json" id="itens_json">
            <div class="text-end mt-4">
                <button type="submit" class="vbtn-sm vbtn-brand"><i class="fas fa-save"></i> Salvar Orçamento</button>
            </div>
        </form>
    </div>
</div>

<script>
let itens = [];

function renderItens() {
    const container = document.getElementById('itens-container');
    container.innerHTML = '';
    itens.forEach((item, i) => {
        const div = document.createElement('div');
        div.className = 'item-row row g-3 mb-2';
        div.innerHTML = `
            <div class="col-md-5">
                <select class="form-control" onchange="updateItem(${i}, 'produto_id', this.value)">
                    <option value="">Selecione...</option>
                    <?php foreach ($produtos as $p): ?>
                        <option value="<?php echo $p['id']; ?>" data-valor="<?php echo $p['valor']; ?>"><?php echo htmlspecialchars($p['nome']); ?></option>
                    <?php endforeach; ?>
                    <option value="0">Produto manual</option>
                </select>
            </div>
            <div class="col-md-4"><input type="text" class="form-control" placeholder="Descrição" value="${(item.descricao || '').replace(/"/g, '&quot;')}" onchange="updateItem(${i}, 'descricao', this.value)"></div>
            <div class="col-md-2"><input type="number" step="0.01" class="form-control" placeholder="Qtd" value="${item.quantidade || ''}" onchange="updateItem(${i}, 'quantidade', this.value)"></div>
            <div class="col-md-2"><input type="number" step="0.01" class="form-control" placeholder="Valor" value="${item.valor_unitario || ''}" onchange="updateItem(${i}, 'valor_unitario', this.value)"></div>
        `;
        div.querySelector('select').value = item.produto_id || '';
        container.appendChild(div);
    });
    atualizarTotal();
}

function updateItem(i, field, value) {
    itens[i][field] = value;
    atualizarTotal();
}

function atualizarTotal() {
    let total = 0;
    itens.forEach(item => {
        total += (parseFloat(item.quantidade) || 0) * (parseFloat(item.valor_unitario) || 0);
    });
    const desconto = parseFloat(document.getElementById('desconto').value) || 0;
    const frete = parseFloat(document.getElementById('frete').value) || 0;
    const final = total * (1 - desconto / 100) + frete;
    document.getElementById('total-final').textContent = 'R$ ' + final.toLocaleString('pt-BR', {minimumFractionDigits: 2});
}

document.getElementById('add-item').addEventListener('click', () => {
    itens.push({produto_id: '', descricao: '', quantidade: 1, valor_unitario: 0});
    renderItens();
});

document.addEventListener('change', e => {
    if (e.target.id === 'frete' || e.target.id === 'desconto') {
        atualizarTotal();
    }
});

document.getElementById('formOrc').addEventListener('submit', () => {
    document.getElementById('itens_json').value = JSON.stringify(itens);
});
</script>

<?php include '../../includes/footer_vendedor.php'; ?>