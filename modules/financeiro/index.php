<?php
require_once '../../config/config.php';
require_once '../../includes/financeiro.php';
requirePermission(['master']);

$page_title = 'Contas a Receber';
$db = getDB();
ensureFinanceiroSchema($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    try {
        if ($acao === 'salvar_caixa') {
            $id = (int) ($_POST['id'] ?? 0);
            $nome = sanitize($_POST['nome'] ?? '');
            $categoria = sanitize($_POST['categoria'] ?? 'outro');
            $taxa = max(0, (float) ($_POST['taxa_padrao_antecipacao'] ?? 0));
            $ativo = isset($_POST['ativo']) ? 1 : 0;

            if ($nome === '') {
                throw new Exception('Nome do tipo de caixa é obrigatório.');
            }

            if ($id > 0) {
                $stmt = $db->prepare("UPDATE tipos_caixa SET nome=?, categoria=?, taxa_padrao_antecipacao=?, ativo=? WHERE id=?");
                $stmt->execute([$nome, $categoria, $taxa, $ativo, $id]);
                setSuccess('Tipo de caixa atualizado.');
            } else {
                $stmt = $db->prepare("INSERT INTO tipos_caixa (nome, categoria, taxa_padrao_antecipacao, ativo) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nome, $categoria, $taxa, $ativo]);
                setSuccess('Tipo de caixa cadastrado.');
            }
        }

        if ($acao === 'baixar_conta') {
            $conta_id = (int) ($_POST['conta_id'] ?? 0);
            $valor_pago = (float) ($_POST['valor_pago'] ?? 0);
            $forma = sanitize($_POST['forma_pagamento'] ?? 'recebimento');
            $observacao = sanitize($_POST['observacao'] ?? '');

            if ($conta_id <= 0 || $valor_pago <= 0) {
                throw new Exception('Dados inválidos para baixa.');
            }

            $db->beginTransaction();
            $stmt = $db->prepare("SELECT id, valor_liquido, valor_recebido, status FROM contas_receber WHERE id = ? FOR UPDATE");
            $stmt->execute([$conta_id]);
            $conta = $stmt->fetch();
            if (!$conta) {
                throw new Exception('Conta não encontrada.');
            }
            if (in_array($conta['status'], ['PAGO', 'CANCELADO'], true)) {
                throw new Exception('Conta já está finalizada.');
            }

            $novo_recebido = round(((float) $conta['valor_recebido']) + $valor_pago, 2);
            $total = (float) $conta['valor_liquido'];
            $status = ($novo_recebido + 0.0001 >= $total) ? 'PAGO' : 'PENDENTE';

            $stmt = $db->prepare("
                UPDATE contas_receber
                SET valor_recebido = ?, status = ?, data_pagamento = CASE WHEN ? = 'PAGO' THEN NOW() ELSE data_pagamento END
                WHERE id = ?
            ");
            $stmt->execute([$novo_recebido, $status, $status, $conta_id]);

            $stmt = $db->prepare("
                INSERT INTO pagamentos (conta_receber_id, usuario_id, valor_pago, forma_pagamento, observacao)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$conta_id, $_SESSION['usuario_id'], $valor_pago, $forma, $observacao ?: null]);

            $db->commit();
            setSuccess('Baixa financeira registrada.');
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        setError('Erro: ' . $e->getMessage());
    }

    header('Location: index.php');
    exit;
}

atualizarStatusFinanceiro($db);

$tipos_caixa = $db->query("SELECT * FROM tipos_caixa ORDER BY nome")->fetchAll();
$contas = $db->query("
    SELECT cr.*, c.razao_social, v.numero as venda_numero, tc.nome as caixa_nome
    FROM contas_receber cr
    INNER JOIN clientes c ON c.id = cr.cliente_id
    INNER JOIN vendas v ON v.id = cr.venda_id
    LEFT JOIN tipos_caixa tc ON tc.id = cr.tipo_caixa_id
    ORDER BY
        FIELD(cr.status, 'ATRASADO', 'PENDENTE', 'PAGO', 'CANCELADO'),
        cr.data_vencimento ASC
")->fetchAll();

$resumo = $db->query("
    SELECT
        COALESCE(SUM(CASE WHEN status IN ('PENDENTE', 'ATRASADO') THEN valor_liquido - valor_recebido ELSE 0 END), 0) as aberto,
        COALESCE(SUM(CASE WHEN status = 'PAGO' THEN valor_recebido ELSE 0 END), 0) as recebido
    FROM contas_receber
")->fetch();

include '../../includes/header_vendedor.php';
?>

<div class="vend-layout">
    <aside class="vend-sidebar">
        <div class="vend-sidebar-logo">
            <div class="vend-logo-icon"><i class="fas fa-fire"></i></div>
            <div><div class="vend-logo-text">Cozinca Inox</div><div class="vend-logo-sub">Financeiro</div></div>
        </div>
        <div class="vend-nav-group">
            <span class="vend-nav-label">Principal</span>
            <a href="../vendas/dashboard_vendedor.php" class="vend-nav-item"><i class="fas fa-th-large"></i> Dashboard</a>
            <a href="index.php" class="vend-nav-item active"><i class="fas fa-arrow-down"></i> Contas a Receber</a>
            <a href="contas_pagar.php" class="vend-nav-item"><i class="fas fa-arrow-up"></i> Contas a Pagar</a>
            <a href="faturamento.php" class="vend-nav-item"><i class="fas fa-file-invoice-dollar"></i> Faturamento</a>
        </div>
        <hr class="vend-nav-divider">
        <div class="vend-nav-group">
            <span class="vend-nav-label">Vendas</span>
            <a href="../vendas/nova_venda.php" class="vend-nav-item"><i class="fas fa-shopping-cart"></i> Nova venda</a>
            <a href="../orcamentos/criar_orcamento.php" class="vend-nav-item"><i class="fas fa-file-invoice"></i> Novo orçamento</a>
            <a href="../os/nova_os_independente.php" class="vend-nav-item"><i class="fas fa-plus-square"></i> Lançar O.S.</a>
        </div>
        <hr class="vend-nav-divider">
        <div class="vend-nav-group">
            <span class="vend-nav-label">Cadastros</span>
            <a href="../cadastros/clientes.php" class="vend-nav-item"><i class="fas fa-users"></i> Clientes</a>
            <a href="../cadastros/produtos.php" class="vend-nav-item"><i class="fas fa-box"></i> Produtos</a>
        </div>
    </aside>
    <div class="vend-main">
        <div class="vend-page-head"><h1 class="vend-page-title">Contas a Receber</h1></div>

        <div class="vend-content">
            <div class="vend-table-wrap">
                <table class="vend-table">
                    <thead>
                        <tr>
                            <th>Venda</th>
                            <th>Cliente</th>
                            <th>Parcela</th>
                            <th>Vencimento</th>
                            <th>Valor Bruto</th>
                            <th>Valor Líquido</th>
                            <th>Status</th>
                            <th>Caixa</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($contas)): ?>
                            <tr><td colspan="9" class="text-center">Nenhuma conta a receber.</td></tr>
                        <?php else: ?>
                            <?php foreach ($contas as $cr): ?>
                                <?php
                                    $saldo = max(0, (float) $cr['valor_liquido'] - (float) $cr['valor_recebido']);
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($cr['venda_numero']); ?></td>
                                    <td><?php echo htmlspecialchars($cr['razao_social']); ?></td>
                                    <td><?php echo (int) $cr['parcela_numero']; ?>/<?php echo (int) $cr['total_parcelas']; ?></td>
                                    <td><?php echo formatDate($cr['data_vencimento']); ?></td>
                                    <td><?php echo formatMoney($cr['valor_bruto']); ?></td>
                                    <td><?php echo formatMoney($cr['valor_liquido']); ?></td>
                                    <td><span class="vvbadge <?php echo $cr['status'] === 'PAGO' ? 'vbadge-ok' : ($cr['status'] === 'ATRASADO' ? 'vbadge-warn' : 'vbadge-info'); ?>"><?php echo $cr['status']; ?></span></td>
                                    <td><?php echo htmlspecialchars($cr['caixa_nome'] ?? '-'); ?></td>
                                    <td>
                                        <?php if (in_array($cr['status'], ['PENDENTE', 'ATRASADO'], true)): ?>
                                            <button class="vbtn-sm btn-success" onclick="abrirModalBaixa(<?php echo (int) $cr['id']; ?>, <?php echo number_format($saldo, 2, '.', ''); ?>, '<?php echo htmlspecialchars($cr['forma_pagamento'], ENT_QUOTES, 'UTF-8'); ?>')">
                                                <i class="fas fa-dollar-sign"></i> Baixar
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="vend-card" style="margin-top:24px">
            <div class="vend-card-head"><div class="vend-card-title">Tipos de Caixa</div><button class="vbtn-sm" onclick="abrirModalCaixa()"><i class="fas fa-plus"></i> Novo Tipo</button></div>
            <div>
                <table class="vend-table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Categoria</th>
                            <th>Taxa padrão antecipação</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tipos_caixa as $tc): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($tc['nome']); ?></td>
                                <td><?php echo htmlspecialchars($tc['categoria']); ?></td>
                                <td><?php echo number_format($tc['taxa_padrao_antecipacao'], 2, ',', '.'); ?>%</td>
                                <td><?php echo $tc['ativo'] ? 'Ativo' : 'Inativo'; ?></td>
                                <td>
                                    <button class="vbtn-sm btn-primary" onclick='editarCaixa(<?php echo json_encode($tc, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

<div id="modalCaixa" class="modal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,.5);">
    <div class="modal-content" style="background:#fff; margin:6% auto; padding:20px; width:95%; max-width:560px; border-radius:8px;">
        <h3 id="tituloModalCaixa">Novo Tipo de Caixa</h3>
        <form method="POST">
            <input type="hidden" name="acao" value="salvar_caixa">
            <input type="hidden" name="id" id="caixa_id">
            <div class="form-group">
                <label>Nome *</label>
                <input type="text" class="form-control" name="nome" id="caixa_nome" required>
            </div>
            <div class="form-row" style="display:flex; gap:10px;">
                <div class="form-group" style="flex:1;">
                    <label>Categoria *</label>
                    <select class="form-control" name="categoria" id="caixa_categoria" required>
                        <option value="dinheiro">Dinheiro</option>
                        <option value="pix">PIX</option>
                        <option value="cartao_credito">Cartão de Crédito</option>
                        <option value="cartao_debito">Cartão de Débito</option>
                        <option value="boleto">Boleto</option>
                        <option value="transferencia">Transferência</option>
                        <option value="outro">Outro</option>
                    </select>
                </div>
                <div class="form-group" style="flex:1;">
                    <label>Taxa padrão antecipação (%)</label>
                    <input type="number" min="0" step="0.01" class="form-control" name="taxa_padrao_antecipacao" id="caixa_taxa" value="0">
                </div>
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="ativo" id="caixa_ativo" checked> Ativo</label>
            </div>
            <div style="text-align:right;">
                <button type="button" class="vbtn-sm btn-secondary" onclick="fecharModalCaixa()">Cancelar</button>
                <button type="submit" class="vbtn-sm btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<div id="modalBaixa" class="modal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,.5);">
    <div class="modal-content" style="background:#fff; margin:8% auto; padding:20px; width:95%; max-width:520px; border-radius:8px;">
        <h3>Baixa Financeira</h3>
        <form method="POST">
            <input type="hidden" name="acao" value="baixar_conta">
            <input type="hidden" name="conta_id" id="baixa_conta_id">
            <div class="form-group">
                <label>Valor pago *</label>
                <input type="number" min="0.01" step="0.01" class="form-control" name="valor_pago" id="baixa_valor_pago" required>
            </div>
            <div class="form-group">
                <label>Forma de pagamento</label>
                <input type="text" class="form-control" name="forma_pagamento" id="baixa_forma_pagamento">
            </div>
            <div class="form-group">
                <label>Observação</label>
                <textarea class="form-control" name="observacao" rows="3"></textarea>
            </div>
            <div style="text-align:right;">
                <button type="button" class="vbtn-sm btn-secondary" onclick="fecharModalBaixa()">Cancelar</button>
                <button type="submit" class="vbtn-sm btn-success">Confirmar Baixa</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalCaixa() {
    document.getElementById('tituloModalCaixa').textContent = 'Novo Tipo de Caixa';
    document.getElementById('caixa_id').value = '';
    document.getElementById('caixa_nome').value = '';
    document.getElementById('caixa_categoria').value = 'dinheiro';
    document.getElementById('caixa_taxa').value = '0';
    document.getElementById('caixa_ativo').checked = true;
    document.getElementById('modalCaixa').style.display = 'block';
}

function editarCaixa(caixa) {
    document.getElementById('tituloModalCaixa').textContent = 'Editar Tipo de Caixa';
    document.getElementById('caixa_id').value = caixa.id || '';
    document.getElementById('caixa_nome').value = caixa.nome || '';
    document.getElementById('caixa_categoria').value = caixa.categoria || 'outro';
    document.getElementById('caixa_taxa').value = caixa.taxa_padrao_antecipacao || 0;
    document.getElementById('caixa_ativo').checked = parseInt(caixa.ativo, 10) === 1;
    document.getElementById('modalCaixa').style.display = 'block';
}

function fecharModalCaixa() {
    document.getElementById('modalCaixa').style.display = 'none';
}

function abrirModalBaixa(contaId, saldo, forma) {
    document.getElementById('baixa_conta_id').value = contaId;
    document.getElementById('baixa_valor_pago').value = saldo.toFixed(2);
    document.getElementById('baixa_forma_pagamento').value = forma || 'recebimento';
    document.getElementById('modalBaixa').style.display = 'block';
}

function fecharModalBaixa() {
    document.getElementById('modalBaixa').style.display = 'none';
}

window.onclick = function(event) {
    const modalCaixa = document.getElementById('modalCaixa');
    const modalBaixa = document.getElementById('modalBaixa');
    if (event.target === modalCaixa) fecharModalCaixa();
    if (event.target === modalBaixa) fecharModalBaixa();
};
</script>

<?php include '../../includes/footer_vendedor.php'; ?>

