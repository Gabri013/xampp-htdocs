<?php
require_once '../../config/config.php';
require_once '../../includes/financeiro.php';
requirePermission(['master', 'vendedor']);

$page_title = 'Faturamento';
$db = getDB();
ensureFinanceiroSchema($db);

$usuario = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'faturar_venda') {
    $venda_id = (int) ($_POST['venda_id'] ?? 0);

    try {
        if ($venda_id <= 0) {
            throw new Exception('Venda inválida.');
        }

        if ($usuario['tipo'] === 'vendedor') {
            $stmt = $db->prepare("SELECT id FROM vendas WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$venda_id, $usuario['id']]);
            if (!$stmt->fetch()) {
                throw new Exception('Você não pode faturar esta venda.');
            }
        }

        $db->beginTransaction();
        faturarVenda($db, $venda_id, $usuario['id']);
        $db->commit();
        setSuccess('Venda faturada e financeiro gerado com sucesso.');
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        setError('Erro no faturamento: ' . $e->getMessage());
    }

    header('Location: faturamento.php');
    exit;
}

$sql = "
    SELECT v.*, c.razao_social, u.nome as vendedor_nome, tc.nome as caixa_nome, tc.categoria as caixa_categoria
    FROM vendas v
    INNER JOIN clientes c ON c.id = v.cliente_id
    INNER JOIN usuarios u ON u.id = v.usuario_id
    LEFT JOIN tipos_caixa tc ON tc.id = v.caixa_tipo_id
    WHERE v.status <> 'cancelada' AND v.faturado_em IS NULL
";
$params = [];
if ($usuario['tipo'] === 'vendedor') {
    $sql .= " AND v.usuario_id = ?";
    $params[] = $usuario['id'];
}
$sql .= " ORDER BY v.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$vendas = $stmt->fetchAll();

$stmt = $db->prepare("
    SELECT v.*, c.razao_social, u.nome as vendedor_nome
    FROM vendas v
    INNER JOIN clientes c ON c.id = v.cliente_id
    INNER JOIN usuarios u ON u.id = v.usuario_id
    WHERE v.status <> 'cancelada' AND v.faturado_em IS NOT NULL
    ORDER BY v.faturado_em DESC
    LIMIT 20
");
$stmt->execute();
$ultimos_faturados = $stmt->fetchAll();

include '../../includes/header_vendedor.php';
?>

<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-page-head">
            <div><h1 class="vend-page-title">Faturamento de Pedidos</h1><p class="vend-page-sub">Contas a receber são geradas automaticamente ao faturar</p></div>
        </div>
        
        <div class="vend-card" style="margin-bottom:24px">
            <div class="vend-card-head"><div class="vend-card-title">Pedidos Pendentes</div></div>
            <div>
                <table class="vend-table" style="margin:0">
                    <thead>
                        <tr>
                            <th>Venda</th>
                            <th>Cliente</th>
                            <th>Valor</th>
                            <th>Caixa</th>
                            <th>Receber em</th>
                            <th>Parcelas</th>
                            <th>Vendedor</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($vendas)): ?>
                            <tr><td colspan="8" class="text-center">Nenhum pedido pendente de faturamento.</td></tr>
                        <?php else: foreach ($vendas as $v): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($v['numero']); ?></strong></td>
                                <td><?php echo htmlspecialchars($v['razao_social']); ?></td>
                                <td><?php echo formatMoney($v['valor_total']); ?></td>
                                <td><?php echo htmlspecialchars($v['caixa_nome'] ?? '-'); ?></td>
                                <td>
                                    <?php
                                    if (($v['forma_pagamento'] ?? '') === 'boleto' && !empty($v['data_recebimento_prevista'])) {
                                        echo formatDate($v['data_recebimento_prevista']);
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td><?php echo (int) ($v['num_parcelas'] ?? 1); ?>x</td>
                                <td><?php echo htmlspecialchars($v['vendedor_nome']); ?></td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="acao" value="faturar_venda">
                                        <input type="hidden" name="venda_id" value="<?php echo (int) $v['id']; ?>">
                                        <button type="submit" class="vbtn-sm" style="border-color:#28a745;color:#28a745"><i class="fas fa-file-invoice-dollar"></i> Faturar</button>
                                    </form>
                                    <a class="vbtn-sm btn-info" href="<?php echo SITE_URL; ?>/modules/vendas/detalhes_venda.php?id=<?php echo (int) $v['id']; ?>"><i class="fas fa-eye"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="vend-card">
            <div class="vend-card-head"><div class="vend-card-title">Últimos Faturados</div></div>
            <div>
                <table class="vend-table" style="margin:0">
                    <thead>
                        <tr>
                            <th>Venda</th>
                            <th>Cliente</th>
                            <th>Valor</th>
                            <th>Faturado em</th>
                            <th>Vendedor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ultimos_faturados)): ?>
                            <tr><td colspan="5" class="text-center">Nenhum faturamento recente.</td></tr>
                        <?php else: foreach ($ultimos_faturados as $v): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($v['numero']); ?></td>
                                <td><?php echo htmlspecialchars($v['razao_social']); ?></td>
                                <td><?php echo formatMoney($v['valor_total']); ?></td>
                                <td><?php echo formatDateTime($v['faturado_em']); ?></td>
                                <td><?php echo htmlspecialchars($v['vendedor_nome']); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer_vendedor.php'; ?>