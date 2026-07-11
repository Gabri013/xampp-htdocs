<?php
require_once '../../config/config.php';
require_once '../../includes/financeiro.php';
requirePermission(['master']);

$page_title = 'Contas a Pagar';
$db = getDB();
ensureFinanceiroSchema($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    try {
        if ($acao === 'salvar_conta_pagar') {
            $id = (int) ($_POST['id'] ?? 0);
            $descricao = sanitize($_POST['descricao'] ?? '');
            $fornecedor = sanitize($_POST['fornecedor'] ?? '');
            $centro_custo_id = (int) ($_POST['centro_custo_id'] ?? 0);
            $valor = (float) ($_POST['valor'] ?? 0);
            $data_vencimento = $_POST['data_vencimento'] ?? '';
            $observacoes = sanitize($_POST['observacoes'] ?? '');

            if ($descricao === '' || $valor <= 0 || $data_vencimento === '') {
                throw new Exception('Preencha descrição, valor e vencimento.');
            }

            if ($id > 0) {
                $stmt = $db->prepare("
                    UPDATE contas_pagar
                    SET descricao = ?, fornecedor = ?, centro_custo_id = ?, valor = ?, data_vencimento = ?, observacoes = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $descricao,
                    $fornecedor ?: null,
                    $centro_custo_id > 0 ? $centro_custo_id : null,
                    $valor,
                    $data_vencimento,
                    $observacoes ?: null,
                    $id
                ]);
                setSuccess('Conta a pagar atualizada.');
            } else {
                $stmt = $db->prepare("
                    INSERT INTO contas_pagar (descricao, fornecedor, centro_custo_id, valor, data_vencimento, observacoes)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $descricao,
                    $fornecedor ?: null,
                    $centro_custo_id > 0 ? $centro_custo_id : null,
                    $valor,
                    $data_vencimento,
                    $observacoes ?: null
                ]);
                setSuccess('Conta a pagar cadastrada.');
            }
        }

        if ($acao === 'baixar_conta_pagar') {
            $conta_id = (int) ($_POST['conta_id'] ?? 0);
            $data_pagamento = $_POST['data_pagamento'] ?? date('Y-m-d');
            $observacao_baixa = sanitize($_POST['observacao_baixa'] ?? '');

            if ($conta_id <= 0) {
                throw new Exception('Conta inválida para baixa.');
            }

            $stmt = $db->prepare("SELECT id, status, observacoes FROM contas_pagar WHERE id = ?");
            $stmt->execute([$conta_id]);
            $conta = $stmt->fetch();
            if (!$conta) {
                throw new Exception('Conta não encontrada.');
            }
            if (in_array($conta['status'], ['PAGO', 'CANCELADO'], true)) {
                throw new Exception('Conta já está finalizada.');
            }

            $novaObs = $conta['observacoes'] ?? '';
            if ($observacao_baixa !== '') {
                $novaObs = trim($novaObs . "\nBaixa: " . $observacao_baixa);
            }

            $stmt = $db->prepare("
                UPDATE contas_pagar
                SET status = 'PAGO', data_pagamento = ?, observacoes = ?
                WHERE id = ?
            ");
            $stmt->execute([$data_pagamento . ' 00:00:00', $novaObs ?: null, $conta_id]);
            setSuccess('Baixa da conta a pagar registrada.');
        }
    } catch (Exception $e) {
        setError('Erro: ' . $e->getMessage());
    }

    header('Location: contas_pagar.php');
    exit;
}

atualizarStatusFinanceiro($db);

$centros_custo = $db->query("SELECT id, nome FROM centro_custo WHERE ativo = 1 ORDER BY nome")->fetchAll();
$contas = $db->query("
    SELECT cp.*, cc.nome as centro_custo_nome
    FROM contas_pagar cp
    LEFT JOIN centro_custo cc ON cc.id = cp.centro_custo_id
    ORDER BY
        FIELD(cp.status, 'ATRASADO', 'PENDENTE', 'PAGO', 'CANCELADO'),
        cp.data_vencimento ASC,
        cp.id DESC
")->fetchAll();

$resumo = $db->query("
    SELECT
        COALESCE(SUM(CASE WHEN status IN ('PENDENTE', 'ATRASADO') THEN valor ELSE 0 END), 0) as aberto,
        COALESCE(SUM(CASE WHEN status = 'PAGO' THEN valor ELSE 0 END), 0) as pago
    FROM contas_pagar
")->fetch();

include '../../includes/header_vendedor.php';
?>

<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-page-head"><h1 class="vend-page-title">Contas a Pagar</h1></div>
        <div class="vend-content">

<style>
.finance-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.finance-toolbar-group {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.finance-action {
    min-width: 150px;
    justify-content: center;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.02em;
}

.finance-action.btn-success {
    background: #2f6e1e;
    border-color: #2f6e1e;
}

.finance-action.btn-danger {
    background: #8f2d22;
    border-color: #8f2d22;
}

.finance-action.btn-dark {
    background: #2f3134;
    border-color: #2f3134;
    color: #fff;
}

.finance-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 12px;
    margin-bottom: 18px;
}

.finance-summary-card {
    padding: 16px 18px;
    border: 1px solid #d8dee5;
    border-radius: 10px;
    background: linear-gradient(180deg, #ffffff 0%, #f5f7fa 100%);
    box-shadow: 0 10px 24px rgba(31, 41, 55, 0.06);
}

.finance-summary-card strong {
    display: block;
    color: #5a6472;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.finance-summary-card .value {
    margin-top: 6px;
    font-size: 28px;
    font-weight: 700;
    color: #16202a;
}

.finance-modal {
    display: none;
    position: fixed;
    z-index: 9999;
    inset: 0;
    background: rgba(15, 23, 42, 0.62);
    backdrop-filter: blur(2px);
    padding: 28px;
    overflow-y: auto;
}

.finance-modal-panel {
    width: min(1610px, 96vw);
    margin: 0 auto;
    background: #fff;
    border-radius: 0;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.35);
    overflow: hidden;
}

.finance-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: linear-gradient(90deg, #2f7d32 0%, #2c7a2f 70%, #25682a 100%);
    color: #fff;
    padding: 14px 24px;
}

.finance-modal-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 800;
    text-transform: uppercase;
}

.finance-modal-close {
    border: 0;
    background: rgba(255,255,255,0.08);
    color: #fff;
    width: 42px;
    height: 42px;
    font-size: 24px;
    cursor: pointer;
}

.finance-modal-tabs {
    display: flex;
    border-bottom: 1px solid #909090;
    background: #e8e8e8;
}

.finance-modal-tab {
    padding: 18px 36px;
    border-right: 1px solid #909090;
    font-size: 16px;
    color: #666;
    background: #dcdcdc;
}

.finance-modal-tab.active {
    background: #fff;
    font-weight: 700;
}

.finance-modal-body {
    padding: 20px;
    background: #f7f7f7;
}

.finance-form-grid {
    display: grid;
    grid-template-columns: 1.1fr 1.1fr 1.1fr 1.15fr;
    gap: 10px;
}

.finance-field {
    background: #fff;
    border: 1px solid #d1d5db;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.12);
    padding: 8px;
}

.finance-field label {
    display: block;
    margin-bottom: 4px;
    font-size: 13px;
    color: #757575;
}

.finance-field .form-control {
    border: 0;
    border-radius: 0;
    box-shadow: none;
    min-height: 42px;
    padding: 6px 0;
    background: transparent;
}

.finance-field textarea.form-control {
    min-height: 250px;
    resize: vertical;
}

.finance-field--wide {
    grid-column: span 2;
}

.finance-field--notes {
    grid-column: 4;
    grid-row: span 3;
}

.finance-form-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 14px 20px 18px;
    border-top: 1px dashed #9ca3af;
    background: #fff;
}

@media (max-width: 1100px) {
    .finance-form-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .finance-field--notes,
    .finance-field--wide {
        grid-column: auto;
        grid-row: auto;
    }
}

@media (max-width: 720px) {
    .finance-modal {
        padding: 10px;
    }

    .finance-modal-panel {
        width: 100%;
    }

    .finance-modal-tabs {
        flex-direction: column;
    }

    .finance-form-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="vend-card">
    <div class="vend-card-header">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
            <h3>Financeiro - Contas a Pagar</h3>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <a class="vbtn-sm btn-secondary btn-sm" href="index.php"><i class="fas fa-arrow-down"></i> Contas a Receber</a>
                <a class="vbtn-sm btn-primary btn-sm" href="contas_pagar.php"><i class="fas fa-arrow-up"></i> Contas a Pagar</a>
                <a class="vbtn-sm btn-info btn-sm" href="faturamento.php"><i class="fas fa-file-invoice-dollar"></i> Faturamento</a>
                <button class="vbtn-sm btn-success btn-sm" type="button" onclick="abrirModalContaPagar()"><i class="fas fa-plus"></i> Nova Conta</button>
            </div>
        </div>
    </div>
    <div class="vend-card-body">
        <div class="finance-toolbar">
            <div class="finance-toolbar-group">
                <button class="vbtn-sm btn-success finance-action" type="button" onclick="abrirModalContaPagar()"><i class="fas fa-plus"></i> Nova Conta</button>
                <button class="vbtn-sm btn-danger finance-action" type="button" onclick="alert('Remoção ainda não implementada nesta tela.')"><i class="fas fa-trash"></i> Remover Conta</button>
                <button class="vbtn-sm btn-dark finance-action" type="button" onclick="alert('Selecione uma conta pendente para pagar.')"><i class="fas fa-check"></i> Pagar Conta(s)</button>
            </div>
            <button class="vbtn-sm btn-light finance-action" type="button" onclick="window.print()"><i class="fas fa-print"></i> Imprimir</button>
        </div>

        <div class="finance-summary">
            <div class="finance-summary-card">
                <strong>Em Aberto</strong>
                <div class="value"><?php echo formatMoney($resumo['aberto']); ?></div>
            </div>
            <div class="finance-summary-card">
                <strong>Pago</strong>
                <div class="value"><?php echo formatMoney($resumo['pago']); ?></div>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Descrição</th>
                        <th>Fornecedor</th>
                        <th>Centro de Custo</th>
                        <th>Vencimento</th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($contas)): ?>
                        <tr><td colspan="7" class="text-center">Nenhuma conta a pagar cadastrada.</td></tr>
                    <?php else: ?>
                        <?php foreach ($contas as $conta): ?>
                            <?php
                            $cor = '#6c757d';
                            if ($conta['status'] === 'PENDENTE') $cor = '#ffc107';
                            if ($conta['status'] === 'ATRASADO') $cor = '#dc3545';
                            if ($conta['status'] === 'PAGO') $cor = '#28a745';
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($conta['descricao']); ?></strong>
                                    <?php if (!empty($conta['observacoes'])): ?>
                                        <small style="display:block; color:#666;"><?php echo nl2br(htmlspecialchars($conta['observacoes'])); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($conta['fornecedor'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($conta['centro_custo_nome'] ?: '-'); ?></td>
                                <td><?php echo formatDate($conta['data_vencimento']); ?></td>
                                <td><?php echo formatMoney($conta['valor']); ?></td>
                                <td><span class="badge" style="background: <?php echo $cor; ?>; color:#fff;"><?php echo $conta['status']; ?></span></td>
                                <td>
                                    <button class="vbtn-sm btn-sm btn-primary" type="button" onclick='editarContaPagar(<?php echo json_encode($conta, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if (in_array($conta['status'], ['PENDENTE', 'ATRASADO'], true)): ?>
                                        <button class="vbtn-sm btn-sm btn-success" type="button" onclick="abrirModalBaixaPagar(<?php echo (int) $conta['id']; ?>)">
                                            <i class="fas fa-check"></i>
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
</div>
</div>
</div>

<div id="modalContaPagar" class="finance-modal">
    <div class="finance-modal-panel">
        <div class="finance-modal-header">
            <h3 id="tituloModalContaPagar">Cadastro de Nova Conta a Pagar</h3>
            <button type="button" class="finance-modal-close" onclick="fecharModalContaPagar()">&times;</button>
        </div>
        <div class="finance-modal-tabs">
            <div class="finance-modal-tab active">Dados Gerais</div>
        </div>
        <form method="POST">
            <input type="hidden" name="acao" value="salvar_conta_pagar">
            <input type="hidden" name="id" id="conta_pagar_id">
            <div class="finance-modal-body">
                <div class="finance-form-grid">
                    <div class="finance-field">
                        <label>Descrição *</label>
                        <input type="text" class="form-control" name="descricao" id="conta_pagar_descricao" required>
                    </div>
                    <div class="finance-field">
                        <label>Data do lançamento</label>
                        <input type="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" disabled>
                    </div>
                    <div class="finance-field">
                        <label>Data de vencimento *</label>
                        <input type="date" class="form-control" name="data_vencimento" id="conta_pagar_data_vencimento" required>
                    </div>
                    <div class="finance-field finance-field--notes">
                        <label>Observações</label>
                        <textarea class="form-control" name="observacoes" id="conta_pagar_observacoes" rows="3"></textarea>
                    </div>
                    <div class="finance-field">
                        <label>Valor cobrado *</label>
                        <input type="number" min="0.01" step="0.01" class="form-control" name="valor" id="conta_pagar_valor" required>
                    </div>
                    <div class="finance-field">
                        <label>Fornecedor</label>
                        <input type="text" class="form-control" name="fornecedor" id="conta_pagar_fornecedor">
                    </div>
                    <div class="finance-field">
                        <label>Nro. do documento</label>
                        <input type="text" class="form-control" id="conta_pagar_documento" placeholder="Opcional">
                    </div>
                    <div class="finance-field">
                        <label>Centro de custo</label>
                        <select class="form-control" name="centro_custo_id" id="conta_pagar_centro_custo_id">
                            <option value="">Selecione...</option>
                            <?php foreach ($centros_custo as $centro): ?>
                                <option value="<?php echo (int) $centro['id']; ?>"><?php echo htmlspecialchars($centro['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="finance-field">
                        <label>Plano de contas</label>
                        <select class="form-control">
                            <option>Selecione...</option>
                            <option>Administrativo</option>
                            <option>Operacional</option>
                            <option>Tributos</option>
                        </select>
                    </div>
                    <div class="finance-field">
                        <label>Conta de origem</label>
                        <select class="form-control">
                            <option>Selecione...</option>
                            <option>Conta principal</option>
                            <option>Caixa empresa</option>
                        </select>
                    </div>
                    <div class="finance-field">
                        <label><input type="checkbox"> Apenas previsão</label>
                    </div>
                    <div class="finance-field">
                        <label>Cadastrar mais de uma vez</label>
                        <select class="form-control">
                            <option>Apenas uma vez</option>
                            <option>Mensalmente</option>
                            <option>Semanalmente</option>
                        </select>
                    </div>
                    <div class="finance-field">
                        <label>Repetir no seguinte intervalo</label>
                        <select class="form-control">
                            <option>Meses</option>
                            <option>Semanas</option>
                            <option>Dias</option>
                        </select>
                    </div>
                    <div class="finance-field">
                        <label><input type="checkbox"> Pagar agora</label>
                    </div>
                    <div class="finance-field">
                        <label>Valor do pagamento</label>
                        <input type="number" min="0" step="0.01" class="form-control" placeholder="0,00">
                    </div>
                    <div class="finance-field">
                        <label>Data do pagamento</label>
                        <input type="date" class="form-control">
                    </div>
                    <div class="finance-field">
                        <label>Palavras-chave (tags)</label>
                        <input type="text" class="form-control" placeholder="Opcional">
                    </div>
                </div>
            </div>
            <div class="finance-form-footer">
                <button type="submit" class="vbtn-sm btn-success">Salvar</button>
                <button type="button" class="vbtn-sm btn-secondary" onclick="fecharModalContaPagar()">Fechar</button>
            </div>
        </form>
    </div>
</div>

<div id="modalBaixaPagar" class="modal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,.5);">
    <div class="modal-content" style="background:#fff; margin:8% auto; padding:20px; width:95%; max-width:520px; border-radius:8px;">
        <h3>Baixar Conta a Pagar</h3>
        <form method="POST">
            <input type="hidden" name="acao" value="baixar_conta_pagar">
            <input type="hidden" name="conta_id" id="baixa_pagar_conta_id">
            <div class="form-group">
                <label>Data do pagamento *</label>
                <input type="date" class="form-control" name="data_pagamento" id="baixa_pagar_data_pagamento" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="form-group">
                <label>Observação</label>
                <textarea class="form-control" name="observacao_baixa" rows="3"></textarea>
            </div>
            <div style="text-align:right;">
                <button type="button" class="vbtn-sm btn-secondary" onclick="fecharModalBaixaPagar()">Cancelar</button>
                <button type="submit" class="vbtn-sm btn-success">Confirmar Baixa</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalContaPagar() {
    document.getElementById('tituloModalContaPagar').textContent = 'Nova Conta a Pagar';
    document.getElementById('conta_pagar_id').value = '';
    document.getElementById('conta_pagar_descricao').value = '';
    document.getElementById('conta_pagar_fornecedor').value = '';
    document.getElementById('conta_pagar_centro_custo_id').value = '';
    document.getElementById('conta_pagar_valor').value = '';
    document.getElementById('conta_pagar_data_vencimento').value = '';
    document.getElementById('conta_pagar_observacoes').value = '';
    document.getElementById('modalContaPagar').style.display = 'block';
}

function editarContaPagar(conta) {
    document.getElementById('tituloModalContaPagar').textContent = 'Editar Conta a Pagar';
    document.getElementById('conta_pagar_id').value = conta.id || '';
    document.getElementById('conta_pagar_descricao').value = conta.descricao || '';
    document.getElementById('conta_pagar_fornecedor').value = conta.fornecedor || '';
    document.getElementById('conta_pagar_centro_custo_id').value = conta.centro_custo_id || '';
    document.getElementById('conta_pagar_valor').value = conta.valor || '';
    document.getElementById('conta_pagar_data_vencimento').value = conta.data_vencimento || '';
    document.getElementById('conta_pagar_observacoes').value = conta.observacoes || '';
    document.getElementById('modalContaPagar').style.display = 'block';
}

function fecharModalContaPagar() {
    document.getElementById('modalContaPagar').style.display = 'none';
}

function abrirModalBaixaPagar(contaId) {
    document.getElementById('baixa_pagar_conta_id').value = contaId;
    document.getElementById('baixa_pagar_data_pagamento').value = '<?php echo date('Y-m-d'); ?>';
    document.getElementById('modalBaixaPagar').style.display = 'block';
}

function fecharModalBaixaPagar() {
    document.getElementById('modalBaixaPagar').style.display = 'none';
}

window.onclick = function(event) {
    const modalContaPagar = document.getElementById('modalContaPagar');
    const modalBaixaPagar = document.getElementById('modalBaixaPagar');
    if (event.target === modalContaPagar) fecharModalContaPagar();
    if (event.target === modalBaixaPagar) fecharModalBaixaPagar();
};
</script>

<?php include '../../includes/footer_vendedor.php'; ?>

