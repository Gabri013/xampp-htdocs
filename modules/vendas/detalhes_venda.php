<?php
require_once '../../config/config.php';
require_once '../../includes/financeiro.php';
requirePermission(['master', 'vendedor']);

$page_title = 'Detalhes da Venda';

$id = $_GET['id'] ?? null;

if (!$id) {
    setError('Venda não encontrada.');
    header('Location: index.php');
    exit;
}

$db = getDB();
ensureFinanceiroSchema($db);

// Buscar venda
$stmt = $db->prepare("
    SELECT v.*, c.razao_social, c.cnpj_cpf, c.telefone, c.email, u.nome as usuario_nome
    FROM vendas v
    INNER JOIN clientes c ON v.cliente_id = c.id
    INNER JOIN usuarios u ON v.usuario_id = u.id
    WHERE v.id = ?
");
$stmt->execute([$id]);
$venda = $stmt->fetch();

if (!$venda) {
    setError('Venda não encontrada.');
    header('Location: index.php');
    exit;
}

$tipos_caixa = $db->query("SELECT id, nome, categoria, taxa_padrao_antecipacao FROM tipos_caixa WHERE ativo = 1 ORDER BY nome")->fetchAll();
$resolver_financeiro_auto_open = isset($_GET['resolver_financeiro']) && $_GET['resolver_financeiro'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'resolver_financeiro') {
    $caixa_tipo_id = (int) ($_POST['caixa_tipo_id'] ?? 0);
    $num_parcelas = max(1, (int) ($_POST['num_parcelas'] ?? 1));
    $taxa_antecipacao_percent = max(0, (float) ($_POST['taxa_antecipacao_percent'] ?? 0));
    $data_recebimento_prevista = !empty($_POST['data_recebimento_prevista']) ? $_POST['data_recebimento_prevista'] : null;
    $resolver_agora = isset($_POST['resolver_agora']) ? 1 : 0;
    $tipo_entrada = sanitize($_POST['tipo_entrada'] ?? '');
    $valor_entrada = max(0, (float) ($_POST['valor_entrada'] ?? 0));
    $data_entrada = !empty($_POST['data_entrada']) ? $_POST['data_entrada'] : null;
    $desconto_financeiro_tipo = sanitize($_POST['desconto_financeiro_tipo'] ?? '');
    $desconto_financeiro_valor = max(0, (float) ($_POST['desconto_financeiro_valor'] ?? 0));
    $juros_percent = isset($_POST['aplicar_juros']) ? max(0, (float) ($_POST['juros_percent'] ?? 0)) : 0;
    $taxa_fixa = isset($_POST['aplicar_taxa_fixa']) ? max(0, (float) ($_POST['taxa_fixa'] ?? 0)) : 0;
    $documento_financeiro = sanitize($_POST['documento_financeiro'] ?? '');
    $numero_documento_financeiro = sanitize($_POST['numero_documento_financeiro'] ?? '');
    $palavra_chave_financeira = sanitize($_POST['palavra_chave_financeira'] ?? '');

    try {
        if ($venda['status'] === 'cancelada') {
            throw new Exception('Venda cancelada não pode ter financeiro alterado.');
        }

        if ($caixa_tipo_id <= 0) {
            throw new Exception('Selecione a forma de pagamento.');
        }

        $stmtTipo = $db->prepare("SELECT id, nome, categoria, ativo, taxa_padrao_antecipacao FROM tipos_caixa WHERE id = ?");
        $stmtTipo->execute([$caixa_tipo_id]);
        $tipo_caixa = $stmtTipo->fetch();

        if (!$tipo_caixa || (int) $tipo_caixa['ativo'] !== 1) {
            throw new Exception('Tipo de caixa inválido.');
        }

        $forma_pagamento = mapFormaPagamentoByCategoria($tipo_caixa['categoria']);
        if ($tipo_caixa['categoria'] === 'cartao_credito') {
            $taxa_antecipacao_percent = max(0, (float) ($tipo_caixa['taxa_padrao_antecipacao'] ?? 0));
        } else {
            $num_parcelas = 1;
            $taxa_antecipacao_percent = 0;
        }
        if ($tipo_caixa['categoria'] === 'boleto' && empty($data_recebimento_prevista)) {
            throw new Exception('Informe a data para receber o boleto.');
        }
        if ($tipo_caixa['categoria'] !== 'boleto') {
            $data_recebimento_prevista = null;
        }
        if ($valor_entrada > 0 && empty($data_entrada)) {
            throw new Exception('Informe a data da entrada.');
        }
        if ($valor_entrada <= 0) {
            $tipo_entrada = null;
            $data_entrada = null;
        }
        if (!in_array($desconto_financeiro_tipo, ['valor', 'percentual'], true)) {
            $desconto_financeiro_tipo = 'valor';
        }

        $simulacao = calcularResumoFinanceiroVenda([
            'valor_total' => $venda['valor_total'],
            'valor_entrada' => $valor_entrada,
            'desconto_financeiro_tipo' => $desconto_financeiro_tipo,
            'desconto_financeiro_valor' => $desconto_financeiro_valor,
            'juros_percent' => $juros_percent,
            'taxa_fixa' => $taxa_fixa
        ]);
        if ($simulacao['total_financeiro'] <= 0) {
            throw new Exception('O total financeiro precisa ser maior que zero.');
        }

        $db->beginTransaction();
        $stmtUpdate = $db->prepare("
            UPDATE vendas
            SET caixa_tipo_id = ?, forma_pagamento = ?, num_parcelas = ?, taxa_antecipacao_percent = ?, data_recebimento_prevista = ?,
                tipo_entrada = ?, valor_entrada = ?, data_entrada = ?, desconto_financeiro_tipo = ?, desconto_financeiro_valor = ?,
                juros_percent = ?, taxa_fixa = ?, documento_financeiro = ?, numero_documento_financeiro = ?, palavra_chave_financeira = ?
            WHERE id = ?
        ");
        $stmtUpdate->execute([
            $caixa_tipo_id,
            $forma_pagamento,
            $num_parcelas,
            $taxa_antecipacao_percent,
            $data_recebimento_prevista,
            $tipo_entrada ?: null,
            $valor_entrada,
            $data_entrada,
            $desconto_financeiro_tipo,
            $desconto_financeiro_valor,
            $juros_percent,
            $taxa_fixa,
            $documento_financeiro ?: null,
            $numero_documento_financeiro ?: null,
            $palavra_chave_financeira ?: null,
            $venda['id']
        ]);

        if ($resolver_agora) {
            if (!empty($venda['faturado_em'])) {
                throw new Exception('Esta venda já está faturada.');
            }
            faturarVenda($db, $venda['id'], $_SESSION['usuario_id']);
        }

        $db->commit();
        setSuccess($resolver_agora ? 'Financeiro resolvido e venda faturada com sucesso.' : 'Configurações financeiras salvas com sucesso.');
        header('Location: detalhes_venda.php?id=' . $venda['id']);
        exit;
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        setError('Erro no financeiro: ' . $e->getMessage());
    }

    $stmt = $db->prepare("
        SELECT v.*, c.razao_social, c.cnpj_cpf, c.telefone, c.email, u.nome as usuario_nome
        FROM vendas v
        INNER JOIN clientes c ON v.cliente_id = c.id
        INNER JOIN usuarios u ON v.usuario_id = u.id
        WHERE v.id = ?
    ");
    $stmt->execute([$id]);
    $venda = $stmt->fetch();
}

$resumo_financeiro = calcularResumoFinanceiroVenda($venda);

// Buscar itens
$stmt = $db->prepare("SELECT * FROM vendas_itens WHERE venda_id = ?");
$stmt->execute([$id]);
$itens = $stmt->fetchAll();

// Buscar O.S relacionada
$stmt = $db->prepare("SELECT * FROM ordens_servico WHERE venda_id = ?");
$stmt->execute([$id]);
$os = $stmt->fetch();

include '../../includes/header_vendedor.php';
?>

<style>
.venda-detalhes-itens table {
    table-layout: fixed;
    width: 100%;
}

.venda-detalhes-itens td,
.venda-detalhes-itens th {
    white-space: normal;
    vertical-align: top;
}

.venda-detalhes-itens .col-descricao {
    width: 50%;
}

.venda-detalhes-itens .col-qtd,
.venda-detalhes-itens .col-unit,
.venda-detalhes-itens .col-total {
    width: 16.66%;
}

.venda-detalhes-itens .descricao-coluna {
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
                <h3>Detalhes da Venda <?php echo htmlspecialchars($venda['numero']); ?></h3>
                <div>
                    <?php if ($venda['status'] !== 'cancelada'): ?>
                    <button type="button" class="vbtn-sm btn-success" onclick="abrirModalFinanceiro()">
                        <i class="fas fa-wallet"></i> Resolver Financeiro
                    </button>
                    <?php endif; ?>
                    <a href="imprimir_venda.php?id=<?php echo $venda['id']; ?>" target="_blank" class="vbtn-sm btn-primary">
                        <i class="fas fa-print"></i> Imprimir Venda
                    </a>
                    <?php if ($os): ?>
                    <a href="../os/vendedor.php?os=<?php echo $os['numero']; ?>" class="vbtn-sm btn-info">
                        <i class="fas fa-tasks"></i> Ver O.S <?php echo htmlspecialchars($os['numero']); ?>
                    </a>
                    <?php endif; ?>
                    <a href="index.php" class="vbtn-sm btn-secondary">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                </div>
            </div>
<div class="vend-card-body">
                <div class="form-row">
                    <div style="flex: 1;">
                <h4>Informações da Venda</h4>
                <table style="width: 100%;">
                    <tr>
                        <th>Número:</th>
                        <td><strong><?php echo htmlspecialchars($venda['numero']); ?></strong></td>
                    </tr>
                    <tr>
                        <th>Data:</th>
                        <td><?php echo formatDate($venda['data_venda']); ?></td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td>
                            <?php
                            $cores = [
                                'em_andamento' => 'var(--warning-color)',
                                'concluida' => 'var(--success-color)',
                                'cancelada' => 'var(--danger-color)'
                            ];
                            $nomes = [
                                'em_andamento' => 'Em Andamento',
                                'concluida' => 'Concluída',
                                'cancelada' => 'Cancelada'
                            ];
                            ?>
                            <span class="badge" style="background-color: <?php echo $cores[$venda['status']]; ?>; color: white;">
                                <?php echo $nomes[$venda['status']]; ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Vendedor:</th>
                        <td><?php echo htmlspecialchars($venda['usuario_nome']); ?></td>
                    </tr>
                    <tr>
                        <th>Forma de Pagamento:</th>
                        <td>
                            <?php 
                            $pagamentos = ['avista' => 'À Vista', 'cartao' => 'Cartão', 'boleto' => 'Boleto'];
                            echo $pagamentos[$venda['forma_pagamento']] ?? 'Não informada';
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Status Financeiro:</th>
                        <td>
                            <?php if ($venda['status'] === 'cancelada'): ?>
                                <span class="badge" style="background:#dc3545; color:#fff;">Cancelado</span>
                            <?php elseif (!empty($venda['faturado_em'])): ?>
                                <span class="badge" style="background:#28a745; color:#fff;">Resolvido / Faturado</span>
                            <?php else: ?>
                                <span class="badge" style="background:#f39c12; color:#fff;">Pendente</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if (!empty($venda['data_recebimento_prevista'])): ?>
                        <tr>
                            <th>Receber em:</th>
                            <td><?php echo formatDate($venda['data_recebimento_prevista']); ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ((float) ($venda['valor_entrada'] ?? 0) > 0): ?>
                        <tr>
                            <th>Entrada:</th>
                            <td><?php echo formatMoney($venda['valor_entrada']); ?> em <?php echo formatDate($venda['data_entrada']); ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Total Financeiro:</th>
                        <td><?php echo formatMoney($resumo_financeiro['total_financeiro']); ?></td>
                    </tr>
                    <?php if ($venda['orcamento_id']): ?>
                        <tr>
                            <th>Origem:</th>
                            <td>Convertida de Orçamento</td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
            
            <div style="flex: 1;">
                <h4>Informações do Cliente</h4>
                <table style="width: 100%;">
                    <tr>
                        <th>Razão Social:</th>
                        <td><?php echo htmlspecialchars($venda['razao_social']); ?></td>
                    </tr>
                    <tr>
                        <th>CNPJ/CPF:</th>
                        <td><?php echo htmlspecialchars($venda['cnpj_cpf']); ?></td>
                    </tr>
                    <tr>
                        <th>Telefone:</th>
                        <td><?php echo htmlspecialchars($venda['telefone']); ?></td>
                    </tr>
                    <tr>
                        <th>Email:</th>
                        <td><?php echo htmlspecialchars($venda['email']); ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <?php if ($os): ?>
            <div class="alert alert-info mt-20">
                <strong><i class="fas fa-info-circle"></i> Ordem de Serviço:</strong> 
                <?php echo htmlspecialchars($os['numero']); ?> - 
                Status: <?php echo getStatusOSBadge($os['status']); ?>
                <?php if ($os['prioridade']): ?>
                    - Prioridade: <?php echo getPrioridadeBadge($os['prioridade']); ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($venda['observacoes_venda'])): ?>
            <h4 class="mt-20">Observações da Venda</h4>
            <p><?php echo nl2br(htmlspecialchars($venda['observacoes_venda'])); ?></p>
        <?php endif; ?>

        <?php if ($venda['observacoes']): ?>
            <h4 class="mt-20">Observações para Produção/O.S.</h4>
            <p><?php echo nl2br(htmlspecialchars($venda['observacoes'])); ?></p>
        <?php endif; ?>
        
        <?php
        // Buscar arquivos/fotos da venda
        if ($os) {
            $stmt = $db->prepare("SELECT * FROM os_arquivos WHERE os_id = ? AND tipo = 'venda'");
            $stmt->execute([$os['id']]);
            $arquivos = $stmt->fetchAll();
            
            if (!empty($arquivos)):
        ?>
            <h4 class="mt-20">Fotos e Arquivos Anexados</h4>
            <div class="form-row" style="display: flex; flex-wrap: wrap; gap: 15px;">
                <?php foreach ($arquivos as $arq): ?>
                    <?php 
                    $ext = strtolower(pathinfo($arq['nome_arquivo'], PATHINFO_EXTENSION));
                    $is_img = in_array($ext, ['jpg', 'jpeg', 'png', 'gif']);
                    ?>
                    <div style="width: 150px; border: 1px solid #ddd; border-radius: 5px; padding: 5px; text-align: center;">
                        <?php if ($is_img): ?>
                            <a href="<?php echo SITE_URL; ?>/assets/uploads/projetos/<?php echo $arq['nome_arquivo']; ?>" target="_blank">
                                <img src="<?php echo SITE_URL; ?>/assets/uploads/projetos/<?php echo $arq['nome_arquivo']; ?>" 
                                     style="width: 100%; height: 100px; object-fit: cover; border-radius: 3px;">
                            </a>
                        <?php else: ?>
                            <div style="height: 100px; display: flex; align-items: center; justify-content: center; background: #f8f9fa;">
                                <i class="fas fa-file-alt fa-3x" style="color: #6c757d;"></i>
                            </div>
                        <?php endif; ?>
                        <div style="font-size: 11px; margin-top: 5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($arq['nome_original']); ?>">
                            <?php echo htmlspecialchars($arq['nome_original']); ?>
                        </div>
                        <a href="<?php echo SITE_URL; ?>/assets/uploads/projetos/<?php echo $arq['nome_arquivo']; ?>" target="_blank" class="vbtn-sm btn-sm btn-secondary" style="width: 100%; margin-top: 5px; font-size: 10px;">
                            <i class="fas fa-download"></i> Baixar
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php 
            endif;
        } 
        ?>

        <h4 class="mt-20">Itens da Venda</h4>
        <div class="table-responsive venda-detalhes-itens">
            <table>
                <colgroup>
                    <col class="col-descricao">
                    <col class="col-qtd">
                    <col class="col-unit">
                    <col class="col-total">
                </colgroup>
                <thead>
                    <tr>
                        <th>Descrição</th>
                        <th>Quantidade</th>
                        <th>Valor Unitário</th>
                        <th>Valor Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($itens as $item): ?>
                        <tr>
                            <td class="descricao-coluna"><?php echo nl2br(htmlspecialchars($item['descricao_manual'])); ?></td>
                            <td><?php echo number_format($item['quantidade'], 2, ',', '.'); ?></td>
                            <td><?php echo formatMoney($item['valor_unitario']); ?></td>
                            <td><?php echo formatMoney($item['valor_total']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" class="text-right">Subtotal:</th>
                        <th><?php echo formatMoney($venda['valor_total'] + $venda['desconto']); ?></th>
                    </tr>
                    <?php if ($venda['desconto'] > 0): ?>
                    <tr>
                        <th colspan="3" class="text-right" style="color: var(--danger-color);">Desconto:</th>
                        <th style="color: var(--danger-color);">- <?php echo formatMoney($venda['desconto']); ?></th>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th colspan="3" class="text-right">Total Final:</th>
                        <th style="font-size: 1.2em; color: var(--primary-color);"><?php echo formatMoney($venda['valor_total']); ?></th>
                    </tr>
                    <?php if ($venda['desconto'] > 0): ?>
                    <tr>
                        <td colspan="4" class="text-right" style="font-size: 12px; color: #28a745; font-weight: bold;">
                            Forma de pagamento: <?php echo $pagamentos[$venda['forma_pagamento']] ?? ''; ?> com desconto no valor de <?php echo formatMoney($venda['desconto']); ?>
                        </td>
</tr>
                    <?php endif; ?>
                </tfoot>
</table>
            </div>
        </div>
    </div>
</div>

<style>
.finance-resolver-modal {
    display:none;
    position:fixed;
    z-index:9999;
    inset:0;
    background:rgba(15,23,42,.62);
    padding:24px;
    overflow-y:auto;
}

.finance-resolver-panel {
    width:min(1800px, 96vw);
    margin:0 auto;
    background:#fff;
    box-shadow:0 20px 60px rgba(0,0,0,.35);
}

.finance-resolver-header {
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:18px 38px;
    color:#fff;
    background:linear-gradient(90deg, #2f7d32 0%, #2f7d32 70%, #246628 100%);
}

.finance-resolver-header h3 {
    margin:0;
    font-size:22px;
    font-weight:800;
    text-transform:uppercase;
}

.finance-resolver-close {
    background:transparent;
    border:0;
    color:#fff;
    font-size:24px;
    cursor:pointer;
    border-left:1px solid rgba(255,255,255,.45);
    padding-left:22px;
}

.finance-resolver-body {
    padding:22px 24px 10px;
}

.finance-resolver-grid {
    display:grid;
    grid-template-columns:2fr .65fr 1.05fr .65fr;
    gap:10px;
}

.finance-box {
    background:#fff;
    border:1px solid #d1d5db;
    box-shadow:0 4px 12px rgba(15, 23, 42, 0.15);
    padding:8px;
}

.finance-box label {
    display:block;
    margin-bottom:4px;
    font-size:13px;
    color:#7a7a7a;
}

.finance-box .form-control {
    border:0;
    border-radius:0;
    min-height:44px;
    box-shadow:none;
    padding:6px 0;
}

.finance-resolver-row {
    display:grid;
    grid-template-columns:2fr .65fr 1.75fr;
    gap:10px;
    margin-top:10px;
    align-items:start;
}

.finance-resolver-summary {
    color:#6c6c6c;
    font-size:18px;
    font-weight:700;
    padding-top:4px;
}

.finance-resolver-options {
    margin-top:16px;
    display:flex;
    flex-direction:column;
    gap:10px;
    font-size:16px;
}

.finance-resolver-divider {
    border-top:1px dashed #9ca3af;
    margin:24px 0 18px;
}

.finance-resolver-inline {
    display:flex;
    align-items:center;
    gap:10px;
    color:#0f6a19;
    font-size:18px;
}

.finance-resolver-table {
    width:100%;
    border-collapse:collapse;
    margin-top:8px;
}

.finance-resolver-table th {
    background:#adadad;
    color:#fff;
    font-size:17px;
    text-align:left;
    padding:14px 12px;
    border:1px solid #8d8d8d;
}

.finance-resolver-table td {
    padding:6px;
    border:1px solid #c5c5c5;
}

.finance-resolver-table .form-control {
    border-radius:0;
    min-height:42px;
}

.finance-resolver-total {
    font-size:18px;
    font-weight:700;
    margin:18px 0;
}

.finance-resolver-footer {
    display:flex;
    justify-content:flex-end;
    gap:10px;
    padding:22px 24px 24px;
    border-top:1px dashed #9ca3af;
}

@media (max-width: 980px) {
    .finance-resolver-grid,
    .finance-resolver-row {
        grid-template-columns:1fr;
    }
}
</style>

<div id="modalResolverFinanceiro" class="finance-resolver-modal">
.finance-resolver-modal {
    display:none;
    position:fixed;
    z-index:9999;
    inset:0;
    background:rgba(15,23,42,.62);
    padding:24px;
    overflow-y:auto;
}

.finance-resolver-panel {
    width:min(1800px, 96vw);
    margin:0 auto;
    background:#fff;
    box-shadow:0 20px 60px rgba(0,0,0,.35);
}

.finance-resolver-header {
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:18px 38px;
    color:#fff;
    background:linear-gradient(90deg, #2f7d32 0%, #2f7d32 70%, #246628 100%);
}

.finance-resolver-header h3 {
    margin:0;
    font-size:22px;
    font-weight:800;
    text-transform:uppercase;
}

.finance-resolver-close {
    background:transparent;
    border:0;
    color:#fff;
    font-size:24px;
    cursor:pointer;
    border-left:1px solid rgba(255,255,255,.45);
    padding-left:22px;
}

.finance-resolver-body {
    padding:22px 24px 10px;
}

.finance-resolver-grid {
    display:grid;
    grid-template-columns:2fr .65fr 1.05fr .65fr;
    gap:10px;
}

.finance-box {
    background:#fff;
    border:1px solid #d1d5db;
    box-shadow:0 4px 12px rgba(15, 23, 42, 0.15);
    padding:8px;
}

.finance-box label {
    display:block;
    margin-bottom:4px;
    font-size:13px;
    color:#7a7a7a;
}

.finance-box .form-control {
    border:0;
    border-radius:0;
    min-height:44px;
    box-shadow:none;
    padding:6px 0;
}

.finance-resolver-row {
    display:grid;
    grid-template-columns:2fr .65fr 1.75fr;
    gap:10px;
    margin-top:10px;
    align-items:start;
}

.finance-resolver-summary {
    color:#6c6c6c;
    font-size:18px;
    font-weight:700;
    padding-top:4px;
}

.finance-resolver-options {
    margin-top:16px;
    display:flex;
    flex-direction:column;
    gap:10px;
    font-size:16px;
}

.finance-resolver-divider {
    border-top:1px dashed #9ca3af;
    margin:24px 0 18px;
}

.finance-resolver-inline {
    display:flex;
    align-items:center;
    gap:10px;
    color:#0f6a19;
    font-size:18px;
}

.finance-resolver-table {
    width:100%;
    border-collapse:collapse;
    margin-top:8px;
}

.finance-resolver-table th {
    background:#adadad;
    color:#fff;
    font-size:17px;
    text-align:left;
    padding:14px 12px;
    border:1px solid #8d8d8d;
}

.finance-resolver-table td {
    padding:6px;
    border:1px solid #c5c5c5;
}

.finance-resolver-table .form-control {
    border-radius:0;
    min-height:42px;
}

.finance-resolver-total {
    font-size:18px;
    font-weight:700;
    margin:18px 0;
}

.finance-resolver-footer {
    display:flex;
    justify-content:flex-end;
    gap:10px;
    padding:22px 24px 24px;
    border-top:1px dashed #9ca3af;
}

@media (max-width: 980px) {
    .finance-resolver-grid,
    .finance-resolver-row {
        grid-template-columns:1fr;
    }
}
</style>

<?php if ($venda['status'] !== 'cancelada'): ?>

<div id="modalResolverFinanceiro" class="finance-resolver-modal">
    <div class="finance-resolver-panel">
        <div class="finance-resolver-header">
            <h3>Definindo a Forma de Pagamento</h3>
            <button type="button" class="finance-resolver-close" onclick="fecharModalFinanceiro()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="acao" value="resolver_financeiro">
            <div class="finance-resolver-body">
                <div class="finance-resolver-grid">
                    <div class="finance-box">
                        <label>Forma de pagamento</label>
                        <select class="form-control" name="caixa_tipo_id" id="financeiro_caixa_tipo_id" <?php echo !empty($venda['faturado_em']) ? 'disabled' : ''; ?>>
                            <option value="">Selecione...</option>
                            <?php foreach ($tipos_caixa as $tipo): ?>
                                <option value="<?php echo (int) $tipo['id']; ?>"
                                        data-categoria="<?php echo htmlspecialchars($tipo['categoria']); ?>"
                                        data-taxa="<?php echo htmlspecialchars($tipo['taxa_padrao_antecipacao']); ?>"
                                        <?php echo (int) $venda['caixa_tipo_id'] === (int) $tipo['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tipo['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($venda['faturado_em'])): ?>
                            <input type="hidden" name="caixa_tipo_id" value="<?php echo (int) $venda['caixa_tipo_id']; ?>">
                        <?php endif; ?>
                    </div>
                    <div class="finance-box">
                        <label>Parcelas</label>
                        <select class="form-control" name="num_parcelas" id="financeiro_num_parcelas" <?php echo !empty($venda['faturado_em']) ? 'disabled' : ''; ?>>
                            <?php for ($p = 1; $p <= 12; $p++): ?>
                                <option value="<?php echo $p; ?>" <?php echo (int) ($venda['num_parcelas'] ?: 1) === $p ? 'selected' : ''; ?>>
                                    <?php echo $p === 1 ? 'A vista' : $p . 'x'; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        <?php if (!empty($venda['faturado_em'])): ?>
                            <input type="hidden" name="num_parcelas" value="<?php echo (int) ($venda['num_parcelas'] ?: 1); ?>">
                        <?php endif; ?>
                    </div>
                    <div class="finance-box">
                        <label>Tipo de entrada</label>
                        <select class="form-control" name="tipo_entrada" id="financeiro_tipo_entrada" <?php echo !empty($venda['faturado_em']) ? 'disabled' : ''; ?>>
                            <option value="">Sem entrada</option>
                            <option value="dinheiro" <?php echo ($venda['tipo_entrada'] ?? '') === 'dinheiro' ? 'selected' : ''; ?>>Dinheiro</option>
                            <option value="pix" <?php echo ($venda['tipo_entrada'] ?? '') === 'pix' ? 'selected' : ''; ?>>PIX</option>
                            <option value="cartao_debito" <?php echo ($venda['tipo_entrada'] ?? '') === 'cartao_debito' ? 'selected' : ''; ?>>Cartão de Débito</option>
                            <option value="transferencia" <?php echo ($venda['tipo_entrada'] ?? '') === 'transferencia' ? 'selected' : ''; ?>>Transferência</option>
                        </select>
                        <?php if (!empty($venda['faturado_em'])): ?>
                            <input type="hidden" name="tipo_entrada" value="<?php echo htmlspecialchars($venda['tipo_entrada'] ?? ''); ?>">
                        <?php endif; ?>
                    </div>
                    <div class="finance-box">
                        <label>Valor entrada ($)</label>
                        <input type="number" min="0" step="0.01" class="form-control" name="valor_entrada" id="financeiro_valor_entrada" value="<?php echo htmlspecialchars(number_format((float) ($venda['valor_entrada'] ?? 0), 2, '.', '')); ?>" <?php echo !empty($venda['faturado_em']) ? 'disabled' : ''; ?>>
                        <?php if (!empty($venda['faturado_em'])): ?>
                            <input type="hidden" name="valor_entrada" value="<?php echo htmlspecialchars(number_format((float) ($venda['valor_entrada'] ?? 0), 2, '.', '')); ?>">
                        <?php endif; ?>
                    </div>
                </div>

                <div class="finance-resolver-row">
                    <div class="finance-box">
                        <label>Tipo de desconto</label>
                        <select class="form-control" name="desconto_financeiro_tipo" id="financeiro_desconto_tipo" <?php echo !empty($venda['faturado_em']) ? 'disabled' : ''; ?>>
                            <option value="valor" <?php echo ($venda['desconto_financeiro_tipo'] ?? 'valor') === 'valor' ? 'selected' : ''; ?>>Valor fixo</option>
                            <option value="percentual" <?php echo ($venda['desconto_financeiro_tipo'] ?? '') === 'percentual' ? 'selected' : ''; ?>>Percentual</option>
                        </select>
                        <?php if (!empty($venda['faturado_em'])): ?>
                            <input type="hidden" name="desconto_financeiro_tipo" value="<?php echo htmlspecialchars($venda['desconto_financeiro_tipo'] ?? 'valor'); ?>">
                        <?php endif; ?>
                    </div>
                    <div class="finance-box">
                        <label>Valor desconto</label>
                        <input type="number" min="0" step="0.01" class="form-control" name="desconto_financeiro_valor" id="financeiro_desconto_valor" value="<?php echo htmlspecialchars(number_format((float) ($venda['desconto_financeiro_valor'] ?? 0), 2, '.', '')); ?>" <?php echo !empty($venda['faturado_em']) ? 'disabled' : ''; ?>>
                        <?php if (!empty($venda['faturado_em'])): ?>
                            <input type="hidden" name="desconto_financeiro_valor" value="<?php echo htmlspecialchars(number_format((float) ($venda['desconto_financeiro_valor'] ?? 0), 2, '.', '')); ?>">
                        <?php endif; ?>
                    </div>
                    <div class="finance-resolver-summary" id="financeiro_resumo"></div>
                </div>

                <div class="finance-resolver-options">
                    <label><input type="checkbox" name="aplicar_juros" id="financeiro_aplicar_juros" value="1" <?php echo (float) ($venda['juros_percent'] ?? 0) > 0 ? 'checked' : ''; ?> <?php echo !empty($venda['faturado_em']) ? 'disabled' : ''; ?>> Juros</label>
                    <input type="number" min="0" step="0.01" class="form-control" name="juros_percent" id="financeiro_juros_percent" value="<?php echo htmlspecialchars(number_format((float) ($venda['juros_percent'] ?? 0), 2, '.', '')); ?>" style="max-width:180px;" <?php echo !empty($venda['faturado_em']) ? 'disabled' : ''; ?>>
                    <label><input type="checkbox" name="aplicar_taxa_fixa" id="financeiro_aplicar_taxa_fixa" value="1" <?php echo (float) ($venda['taxa_fixa'] ?? 0) > 0 ? 'checked' : ''; ?> <?php echo !empty($venda['faturado_em']) ? 'disabled' : ''; ?>> Taxa fixa</label>
                    <input type="number" min="0" step="0.01" class="form-control" name="taxa_fixa" id="financeiro_taxa_fixa" value="<?php echo htmlspecialchars(number_format((float) ($venda['taxa_fixa'] ?? 0), 2, '.', '')); ?>" style="max-width:180px;" <?php echo !empty($venda['faturado_em']) ? 'disabled' : ''; ?>>
                    <?php if (!empty($venda['faturado_em'])): ?>
                        <input type="hidden" name="juros_percent" value="<?php echo htmlspecialchars(number_format((float) ($venda['juros_percent'] ?? 0), 2, '.', '')); ?>">
                        <input type="hidden" name="taxa_fixa" value="<?php echo htmlspecialchars(number_format((float) ($venda['taxa_fixa'] ?? 0), 2, '.', '')); ?>">
                    <?php endif; ?>
                </div>

                <div class="finance-resolver-divider"></div>

                <label class="finance-resolver-inline">
                    <input type="checkbox" name="resolver_agora" id="financeiro_resolver_agora" value="1" <?php echo !empty($venda['faturado_em']) ? 'checked disabled' : 'checked'; ?>>
                    Desejo resolver o financeiro agora
                </label>
                <?php if (!empty($venda['faturado_em'])): ?>
                    <input type="hidden" name="resolver_agora" value="1">
                <?php endif; ?>

                <table class="finance-resolver-table">
                    <thead>
                        <tr>
                            <th>Valor a vista ($)</th>
                            <th>Data entrada</th>
                            <th>Destino</th>
                            <th>Documento</th>
                            <th>Número do doc.</th>
                            <th>Palavra-chave</th>
                            <th>Custo int.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><input type="text" class="form-control" id="financeiro_valor_total" value="<?php echo number_format((float) $resumo_financeiro['total_financeiro'], 2, ',', '.'); ?>" disabled></td>
                            <td><input type="date" class="form-control" name="data_entrada" id="financeiro_data_entrada" value="<?php echo htmlspecialchars($venda['data_entrada'] ?: $venda['data_venda']); ?>" <?php echo !empty($venda['faturado_em']) ? 'disabled' : ''; ?>></td>
                            <td><select class="form-control" disabled><option>Financeiro</option></select></td>
                            <td>
                                <select class="form-control" name="documento_financeiro" id="financeiro_documento_financeiro" <?php echo !empty($venda['faturado_em']) ? 'disabled' : ''; ?>>
                                    <option value="">Selecione...</option>
                                    <option value="venda" <?php echo ($venda['documento_financeiro'] ?? '') === 'venda' ? 'selected' : ''; ?>>Venda</option>
                                    <option value="boleto" <?php echo ($venda['documento_financeiro'] ?? '') === 'boleto' ? 'selected' : ''; ?>>Boleto</option>
                                    <option value="pix" <?php echo ($venda['documento_financeiro'] ?? '') === 'pix' ? 'selected' : ''; ?>>PIX</option>
                                </select>
                            </td>
                            <td><input type="text" class="form-control" name="numero_documento_financeiro" id="financeiro_numero_documento_financeiro" value="<?php echo htmlspecialchars($venda['numero_documento_financeiro'] ?: $venda['numero']); ?>" <?php echo !empty($venda['faturado_em']) ? 'disabled' : ''; ?>></td>
                            <td><input type="text" class="form-control" name="palavra_chave_financeira" id="financeiro_palavra_chave_financeira" value="<?php echo htmlspecialchars($venda['palavra_chave_financeira'] ?: $venda['razao_social']); ?>" <?php echo !empty($venda['faturado_em']) ? 'disabled' : ''; ?>></td>
                            <td><input type="text" class="form-control" value="0,00" disabled></td>
                        </tr>
                    </tbody>
                </table>
                <div class="finance-box" style="margin-top:10px;">
                    <label>Data para receber</label>
                    <input type="date" class="form-control" name="data_recebimento_prevista" id="financeiro_data_recebimento_prevista" value="<?php echo htmlspecialchars($venda['data_recebimento_prevista'] ?: $venda['data_venda']); ?>" <?php echo !empty($venda['faturado_em']) ? 'disabled' : ''; ?>>
                    <?php if (!empty($venda['faturado_em'])): ?>
                        <input type="hidden" name="data_recebimento_prevista" value="<?php echo htmlspecialchars($venda['data_recebimento_prevista']); ?>">
                        <input type="hidden" name="documento_financeiro" value="<?php echo htmlspecialchars($venda['documento_financeiro'] ?? ''); ?>">
                        <input type="hidden" name="numero_documento_financeiro" value="<?php echo htmlspecialchars($venda['numero_documento_financeiro'] ?? ''); ?>">
                        <input type="hidden" name="palavra_chave_financeira" value="<?php echo htmlspecialchars($venda['palavra_chave_financeira'] ?? ''); ?>">
                        <input type="hidden" name="data_entrada" value="<?php echo htmlspecialchars($venda['data_entrada'] ?? ''); ?>">
                    <?php endif; ?>
                </div>

                <div class="finance-resolver-total" id="financeiro_total_rodape">Total: <?php echo formatMoney($resumo_financeiro['total_financeiro']); ?></div>
            </div>

            <div class="finance-resolver-footer">
                <?php if (empty($venda['faturado_em'])): ?>
                    <button type="submit" class="vbtn-sm">Salvar</button>
                <?php endif; ?>
                <button type="button" class="vbtn-sm btn-secondary" onclick="fecharModalFinanceiro()">Fechar</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalFinanceiro() {
    document.getElementById('modalResolverFinanceiro').style.display = 'block';
    atualizarResumoFinanceiro();
}

function fecharModalFinanceiro() {
    document.getElementById('modalResolverFinanceiro').style.display = 'none';
}

function atualizarResumoFinanceiro() {
    const selectCaixa = document.getElementById('financeiro_caixa_tipo_id');
    const selectParcelas = document.getElementById('financeiro_num_parcelas');
    const inputDataRecebimento = document.getElementById('financeiro_data_recebimento_prevista');
    const selectTipoEntrada = document.getElementById('financeiro_tipo_entrada');
    const inputValorEntrada = document.getElementById('financeiro_valor_entrada');
    const selectDescontoTipo = document.getElementById('financeiro_desconto_tipo');
    const inputDescontoValor = document.getElementById('financeiro_desconto_valor');
    const checkJuros = document.getElementById('financeiro_aplicar_juros');
    const inputJuros = document.getElementById('financeiro_juros_percent');
    const checkTaxaFixa = document.getElementById('financeiro_aplicar_taxa_fixa');
    const inputTaxaFixa = document.getElementById('financeiro_taxa_fixa');
    const resumo = document.getElementById('financeiro_resumo');
    const rodape = document.getElementById('financeiro_total_rodape');
    const totalBase = <?php echo json_encode((float) $venda['valor_total']); ?>;

    const option = selectCaixa ? selectCaixa.options[selectCaixa.selectedIndex] : null;
    const categoria = option && option.dataset ? option.dataset.categoria : '';
    const nomeForma = option ? option.text : '- não selecionada -';
    const parcelas = selectParcelas ? parseInt(selectParcelas.value || '1', 10) : 1;
    const valorEntrada = inputValorEntrada ? Math.max(0, parseFloat(inputValorEntrada.value || '0')) : 0;
    const descontoTipo = selectDescontoTipo ? selectDescontoTipo.value : 'valor';
    const descontoValorInput = inputDescontoValor ? Math.max(0, parseFloat(inputDescontoValor.value || '0')) : 0;
    const descontoValor = descontoTipo === 'percentual'
        ? Math.min(totalBase, totalBase * (descontoValorInput / 100))
        : Math.min(totalBase, descontoValorInput);
    const subtotal = Math.max(0, totalBase - descontoValor);
    const jurosPercent = checkJuros && checkJuros.checked ? Math.max(0, parseFloat(inputJuros.value || '0')) : 0;
    const jurosValor = subtotal * (jurosPercent / 100);
    const taxaFixa = checkTaxaFixa && checkTaxaFixa.checked ? Math.max(0, parseFloat(inputTaxaFixa.value || '0')) : 0;
    const totalFinanceiro = Math.max(0, subtotal + jurosValor + taxaFixa);
    const saldoReceber = Math.max(0, totalFinanceiro - Math.min(valorEntrada, totalFinanceiro));
    const formatMoney = (valor) => valor.toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'});

    if (categoria === 'cartao_credito') {
        selectParcelas.disabled = false;
    } else if (selectParcelas && !selectParcelas.hasAttribute('disabled')) {
        selectParcelas.value = '1';
    }

    if (inputDataRecebimento) {
        inputDataRecebimento.required = categoria === 'boleto';
        inputDataRecebimento.style.display = 'block';
    }
    if (inputJuros) {
        inputJuros.disabled = !(checkJuros && checkJuros.checked) || inputJuros.hasAttribute('data-locked');
    }
    if (inputTaxaFixa) {
        inputTaxaFixa.disabled = !(checkTaxaFixa && checkTaxaFixa.checked) || inputTaxaFixa.hasAttribute('data-locked');
    }
    if (selectTipoEntrada && !selectTipoEntrada.hasAttribute('disabled')) {
        selectTipoEntrada.required = valorEntrada > 0;
    }

    const descricaoParcelas = parcelas > 1 ? parcelas + ' parcelas' : 'A vista';
    resumo.innerHTML =
        'Forma de pgto: ' + nomeForma +
        '<br>' + descricaoParcelas + ', saldo de ' + formatMoney(saldoReceber) +
        (valorEntrada > 0 ? '<br>Entrada: ' + formatMoney(Math.min(valorEntrada, totalFinanceiro)) : '') +
        '<br>Total financeiro: ' + formatMoney(totalFinanceiro);
    rodape.textContent = 'Total: ' + formatMoney(totalFinanceiro);
    const valorInput = document.getElementById('financeiro_valor_total');
    if (valorInput) {
        valorInput.value = totalFinanceiro.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const selectCaixa = document.getElementById('financeiro_caixa_tipo_id');
    const selectParcelas = document.getElementById('financeiro_num_parcelas');
    const selectTipoEntrada = document.getElementById('financeiro_tipo_entrada');
    const inputValorEntrada = document.getElementById('financeiro_valor_entrada');
    const selectDescontoTipo = document.getElementById('financeiro_desconto_tipo');
    const inputDescontoValor = document.getElementById('financeiro_desconto_valor');
    const checkJuros = document.getElementById('financeiro_aplicar_juros');
    const inputJuros = document.getElementById('financeiro_juros_percent');
    const checkTaxaFixa = document.getElementById('financeiro_aplicar_taxa_fixa');
    const inputTaxaFixa = document.getElementById('financeiro_taxa_fixa');
    if (selectCaixa) selectCaixa.addEventListener('change', atualizarResumoFinanceiro);
    if (selectParcelas) selectParcelas.addEventListener('change', atualizarResumoFinanceiro);
    if (selectTipoEntrada) selectTipoEntrada.addEventListener('change', atualizarResumoFinanceiro);
    if (inputValorEntrada) inputValorEntrada.addEventListener('input', atualizarResumoFinanceiro);
    if (selectDescontoTipo) selectDescontoTipo.addEventListener('change', atualizarResumoFinanceiro);
    if (inputDescontoValor) inputDescontoValor.addEventListener('input', atualizarResumoFinanceiro);
    if (checkJuros) checkJuros.addEventListener('change', atualizarResumoFinanceiro);
    if (inputJuros) inputJuros.addEventListener('input', atualizarResumoFinanceiro);
    if (checkTaxaFixa) checkTaxaFixa.addEventListener('change', atualizarResumoFinanceiro);
    if (inputTaxaFixa) inputTaxaFixa.addEventListener('input', atualizarResumoFinanceiro);
    <?php if (!empty($venda['faturado_em'])): ?>
    if (inputJuros) inputJuros.setAttribute('data-locked', '1');
    if (inputTaxaFixa) inputTaxaFixa.setAttribute('data-locked', '1');
    <?php endif; ?>
    atualizarResumoFinanceiro();

    window.addEventListener('click', function(event) {
        const modal = document.getElementById('modalResolverFinanceiro');
        if (event.target === modal) {
            fecharModalFinanceiro();
        }
    });

    <?php if ($resolver_financeiro_auto_open): ?>
    abrirModalFinanceiro();
    <?php endif; ?>
</script>

<?php endif; ?>

<?php include '../../includes/footer_vendedor.php'; ?>


