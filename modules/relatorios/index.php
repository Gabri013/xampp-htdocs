<?php
require_once '../../config/config.php';
requirePermission(['master', 'vendedor']);

$page_title = 'Relatórios';

$db = getDB();

// Período padrão = mês vigente
$mes_atual = date('Y-m');
$inicio_padrao = $mes_atual . '-01';
$fim_padrao = date('Y-m-t');

$inicio = $_GET['inicio'] ?? $inicio_padrao;
$fim = $_GET['fim'] ?? $fim_padrao;

// Consulta ABC Produtos
$stmtABC = $db->prepare("
    SELECT 
        p.id,
        p.codigo,
        p.nome as descricao,
        SUM(vi.quantidade) as quantidade_total,
        SUM(vi.valor_total) as faturamento_total
    FROM vendas_itens vi
    JOIN vendas v ON vi.venda_id = v.id
    JOIN produtos p ON vi.produto_id = p.id
    WHERE v.data_venda BETWEEN ? AND ?
    GROUP BY p.id, p.codigo, p.nome
    ORDER BY faturamento_total DESC
");
$stmtABC->execute([$inicio, $fim]);
$abcProdutos = $stmtABC->fetchAll();

$totalFaturamento = array_sum(array_column($abcProdutos, 'faturamento_total'));
$acumulado = 0;
foreach ($abcProdutos as &$prod) {
    $acumulado += $prod['faturamento_total'];
    $prod['participacao'] = $totalFaturamento > 0 ? ($prod['faturamento_total'] / $totalFaturamento * 100) : 0;
    $prod['acumulado'] = $totalFaturamento > 0 ? ($acumulado / $totalFaturamento * 100) : 0;
    if ($prod['acumulado'] <= 80) $prod['classe'] = 'A';
    elseif ($prod['acumulado'] <= 95) $prod['classe'] = 'B';
    else $prod['classe'] = 'C';
}

// Consulta vendas detalhadas
$stmtVendas = $db->prepare("
    SELECT 
        v.id as venda_id,
        v.numero,
        v.data_venda,
        c.razao_social as cliente_nome,
        u.nome as vendedor_nome,
        vi.descricao_manual as descricao_item,
        vi.quantidade,
        vi.valor_unitario,
        vi.valor_total as item_total,
        v.valor_total as venda_total
    FROM vendas v
    JOIN clientes c ON v.cliente_id = c.id
    JOIN usuarios u ON v.usuario_id = u.id
    JOIN vendas_itens vi ON v.id = vi.venda_id
    WHERE v.data_venda BETWEEN ? AND ?
    ORDER BY v.data_venda DESC, v.numero
");
$stmtVendas->execute([$inicio, $fim]);
$vendasDetalhadas = $stmtVendas->fetchAll();

include '../../includes/header_vendedor.php';
?>

<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-page-head">
            <div><h1 class="vend-page-title">Relatórios</h1><p class="vend-page-sub">Análise de vendas e produtos</p></div>
            <div>
                <form method="GET" class="vend-filters" style="margin:0">
                    <div class="vend-filters-grid">
                        <div class="vend-filter-item">
                            <label class="vend-filter-label">Período Inicial</label>
                            <input type="date" name="inicio" value="<?php echo htmlspecialchars($inicio); ?>" class="vend-filter-input">
                        </div>
                        <div class="vend-filter-item">
                            <label class="vend-filter-label">Período Final</label>
                            <input type="date" name="fim" value="<?php echo htmlspecialchars($fim); ?>" class="vend-filter-input">
                        </div>
                        <div class="vend-filter-item" style="align-self:flex-end;display:flex;gap:8px">
                            <button type="submit" class="vbtn-sm"><i class="fas fa-search"></i> Filtrar</button>
                            <a href="imprimir.php?inicio=<?php echo htmlspecialchars($inicio); ?>&fim=<?php echo htmlspecialchars($fim); ?>" target="_blank" class="vbtn-sm btn-secondary"><i class="fas fa-print"></i> Imprimir</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="vend-two-col">
            <div class="vend-card">
                <div class="vend-card-head">
                    <div class="vend-card-title"><i class="fas fa-chart-bar"></i> Curva ABC - Produtos</div>
                    <div class="vend-card-link"><?php echo count($abcProdutos); ?> itens</div>
                </div>
                <div class="vend-table-wrap" style="max-height:400px;overflow-y:auto">
                    <table class="vend-table">
                        <thead>
                            <tr>
                                <th>Classe</th>
                                <th>Descrição</th>
                                <th>Faturamento</th>
                                <th>% participação</th>
                                <th>% acumulado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($abcProdutos)): ?>
                                <tr><td colspan="5" class="vend-empty"><i class="fas fa-box-open"></i> Nenhum item encontrado no período</td></tr>
                            <?php else: foreach ($abcProdutos as $linha): ?>
                                <tr>
                                    <td><span class="vbadge vbadge-<?php echo strtolower($linha['classe']) === 'a' ? 'ok' : (strtolower($linha['classe']) === 'b' ? 'warn' : 'info'); ?>"><?php echo $linha['classe']; ?></span></td>
                                    <td><?php echo htmlspecialchars($linha['descricao']); ?></td>
                                    <td><strong><?php echo formatMoney($linha['faturamento_total']); ?></strong></td>
                                    <td><?php echo number_format($linha['participacao'], 2, ',', '.'); ?>%</td>
                                    <td><?php echo number_format($linha['acumulado'], 2, ',', '.'); ?>%</td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="vend-card">
                <div class="vend-card-head">
                    <div class="vend-card-title"><i class="fas fa-shopping-cart"></i> Vendas Detalhadas</div>
                    <div class="vend-card-link"><?php echo count($vendasDetalhadas); ?> itens</div>
                </div>
                <div class="vend-table-wrap" style="max-height:400px;overflow-y:auto">
                    <table class="vend-table" style="margin:0">
                        <thead>
                            <tr>
                                <th>Venda</th><th>Data</th><th>Cliente</th><th>Vendedor</th><th>Produto</th><th>Qtd.</th><th>Unitário</th><th>Total Item</th><th>Total Venda</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($vendasDetalhadas)): ?>
                                <tr><td colspan="9" class="vend-empty"><i class="fas fa-receipt"></i> Nenhuma venda encontrada</td></tr>
                            <?php else: foreach ($vendasDetalhadas as $linha): ?>
                                <tr>
                                    <td><a href="<?php echo SITE_URL; ?>/modules/vendas/detalhes_venda.php?id=<?php echo (int) $linha['venda_id']; ?>" class="vbtn-sm btn-primary"><strong><?php echo htmlspecialchars($linha['numero']); ?></strong></a></td>
                                    <td><small><?php echo formatDate($linha['data_venda']); ?></small></td>
                                    <td><small><?php echo htmlspecialchars($linha['cliente_nome']); ?></small></td>
                                    <td><small><?php echo htmlspecialchars($linha['vendedor_nome']); ?></small></td>
                                    <td><small><?php echo htmlspecialchars($linha['descricao_item']); ?></small></td>
                                    <td><small><?php echo number_format((float) $linha['quantidade'], 2, ',', '.'); ?></small></td>
                                    <td><small><?php echo formatMoney((float) $linha['valor_unitario']); ?></small></td>
                                    <td><small><strong><?php echo formatMoney((float) $linha['item_total']); ?></strong></small></td>
                                    <td><small><?php echo formatMoney((float) $linha['venda_total']); ?></small></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer_vendedor.php'; ?>