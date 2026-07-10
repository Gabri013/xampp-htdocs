<?php
require_once '../../config/config.php';

requirePermission(['master', 'vendedor']);

$db = getDB();
$usuarioLogado = getCurrentUser();

$inicio = $_GET['inicio'] ?? date('Y-m-01');
$fim = $_GET['fim'] ?? date('Y-m-d');
$clienteId = (int) ($_GET['cliente_id'] ?? 0);
$produtoId = (int) ($_GET['produto_id'] ?? 0);
$vendedorIdFiltro = (int) ($_GET['vendedor_id'] ?? 0);

$inicioDate = DateTime::createFromFormat('Y-m-d', $inicio) ?: new DateTime('first day of this month');
$fimDate = DateTime::createFromFormat('Y-m-d', $fim) ?: new DateTime();

$inicio = $inicioDate->format('Y-m-d');
$fim = $fimDate->format('Y-m-d');

$paramsBase = [$inicio, $fim];
$whereBase = " WHERE v.data_venda BETWEEN ? AND ? AND v.status <> 'cancelada' ";

if (($usuarioLogado['tipo'] ?? '') === 'vendedor') {
    $whereBase .= " AND v.usuario_id = ? ";
    $paramsBase[] = (int) $usuarioLogado['id'];
} elseif ($vendedorIdFiltro > 0) {
    $whereBase .= " AND v.usuario_id = ? ";
    $paramsBase[] = $vendedorIdFiltro;
}

if ($clienteId > 0) {
    $whereBase .= " AND v.cliente_id = ? ";
    $paramsBase[] = $clienteId;
}

$whereItens = $whereBase;
$paramsItens = $paramsBase;
if ($produtoId > 0) {
    $whereItens .= " AND vi.produto_id = ? ";
    $paramsItens[] = $produtoId;
}

$stmtResumo = $db->prepare("
    SELECT
        COUNT(DISTINCT v.id) AS total_vendas,
        COALESCE(SUM(v.valor_total), 0) AS valor_total_vendido
    FROM vendas v
    $whereBase
");
$stmtResumo->execute($paramsBase);
$resumo = $stmtResumo->fetch(PDO::FETCH_ASSOC) ?: [];

$stmtAbc = $db->prepare("
    SELECT
        COALESCE(CAST(vi.produto_id AS CHAR), CONCAT('manual:', MD5(COALESCE(vi.descricao_manual, '')))) AS agrupador,
        vi.produto_id,
        p.codigo AS produto_codigo,
        p.nome AS produto_nome,
        COALESCE(NULLIF(TRIM(vi.descricao_manual), ''), p.nome, 'Item manual') AS descricao_base,
        COALESCE(SUM(vi.quantidade), 0) AS quantidade_total,
        COALESCE(SUM(vi.valor_total), 0) AS faturamento_total
    FROM vendas v
    INNER JOIN vendas_itens vi ON vi.venda_id = v.id
    LEFT JOIN produtos p ON p.id = vi.produto_id
    $whereItens
    GROUP BY agrupador, vi.produto_id, p.codigo, p.nome, descricao_base
    ORDER BY faturamento_total DESC, quantidade_total DESC
");
$stmtAbc->execute($paramsItens);
$abcBruto = $stmtAbc->fetchAll(PDO::FETCH_ASSOC);

$faturamentoAbcTotal = array_reduce($abcBruto, static function ($carry, $item) {
    return $carry + (float) ($item['faturamento_total'] ?? 0);
}, 0.0);

$abcProdutos = [];
$acumulado = 0.0;
foreach ($abcBruto as $linha) {
    $faturamento = (float) ($linha['faturamento_total'] ?? 0);
    $participacao = $faturamentoAbcTotal > 0 ? ($faturamento / $faturamentoAbcTotal) * 100 : 0;
    $acumulado += $participacao;

    $classe = 'C';
    if ($acumulado <= 80) {
        $classe = 'A';
    } elseif ($acumulado <= 95) {
        $classe = 'B';
    }

    $descricao = trim((string) ($linha['descricao_base'] ?? ''));
    if (!empty($linha['produto_codigo'])) {
        $descricao = trim($linha['produto_codigo'] . ' - ' . $descricao);
    }

    $abcProdutos[] = [
        'descricao' => $descricao !== '' ? $descricao : 'Item sem descrição',
        'quantidade_total' => (float) ($linha['quantidade_total'] ?? 0),
        'faturamento_total' => $faturamento,
        'participacao' => $participacao,
        'acumulado' => $acumulado,
        'classe' => $classe,
    ];
}

$stmtDetalhado = $db->prepare("
    SELECT
        v.id AS venda_id,
        v.numero,
        v.data_venda,
        v.valor_total AS venda_total,
        c.razao_social AS cliente_nome,
        u.nome AS vendedor_nome,
        vi.quantidade,
        vi.valor_unitario,
        vi.valor_total AS item_total,
        COALESCE(NULLIF(TRIM(vi.descricao_manual), ''), CONCAT(COALESCE(p.codigo, ''), CASE WHEN p.codigo IS NOT NULL AND p.codigo <> '' THEN ' - ' ELSE '' END, COALESCE(p.nome, 'Item sem produto'))) AS descricao_item
    FROM vendas v
    INNER JOIN clientes c ON c.id = v.cliente_id
    INNER JOIN usuarios u ON u.id = v.usuario_id
    INNER JOIN vendas_itens vi ON vi.venda_id = v.id
    LEFT JOIN produtos p ON p.id = vi.produto_id
    $whereItens
    ORDER BY v.data_venda DESC, v.id DESC, vi.id ASC
");
$stmtDetalhado->execute($paramsItens);
$vendasDetalhadas = $stmtDetalhado->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatórios Comerciais</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 0; padding: 24px; font-size: 12px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #111827; padding-bottom: 12px; margin-bottom: 20px; }
        .titulo h1 { margin: 0 0 6px 0; font-size: 24px; }
        .titulo p { margin: 0; color: #6b7280; }
        .resumo { margin-bottom: 20px; }
        .resumo p { margin: 4px 0; }
        .secao { margin-top: 28px; }
        .secao h2 { margin: 0 0 10px 0; font-size: 18px; border-bottom: 1px solid #d1d5db; padding-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-size: 11px; text-transform: uppercase; }
        .text-right { text-align: right; }
        .badge { display: inline-block; min-width: 20px; text-align: center; padding: 3px 8px; border-radius: 999px; color: #fff; font-weight: bold; }
        .badge-a { background: #16a34a; }
        .badge-b { background: #f59e0b; }
        .badge-c { background: #6b7280; }
        .controls { position: fixed; right: 24px; bottom: 24px; background: #fff; border: 1px solid #d1d5db; border-radius: 10px; padding: 12px; box-shadow: 0 8px 24px rgba(0,0,0,.12); }
        .controls button { background: #111827; color: #fff; border: none; border-radius: 8px; padding: 10px 14px; cursor: pointer; font-weight: bold; }
        @media print {
            .controls { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="controls">
        <button type="button" onclick="window.print()">Imprimir / Salvar PDF</button>
    </div>

    <div class="header">
        <div class="titulo">
            <h1>Relatórios Comerciais</h1>
            <p>Período de <?php echo formatDate($inicio); ?> até <?php echo formatDate($fim); ?></p>
        </div>
        <div class="resumo">
            <p><strong>Total de vendas:</strong> <?php echo (int) ($resumo['total_vendas'] ?? 0); ?></p>
            <p><strong>Valor vendido:</strong> <?php echo formatMoney((float) ($resumo['valor_total_vendido'] ?? 0)); ?></p>
            <p><strong>Emitido em:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
        </div>
    </div>

    <div class="secao">
        <h2>ABC de Produtos Vendidos</h2>
        <table>
            <thead>
                <tr>
                    <th>Classe</th>
                    <th>Produto</th>
                    <th>Qtd. vendida</th>
                    <th>Faturamento</th>
                    <th>% participação</th>
                    <th>% acumulado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($abcProdutos)): ?>
                    <tr><td colspan="6">Nenhum item encontrado para os filtros informados.</td></tr>
                <?php else: ?>
                    <?php foreach ($abcProdutos as $linha): ?>
                        <tr>
                            <td><span class="badge badge-<?php echo strtolower($linha['classe']); ?>"><?php echo htmlspecialchars($linha['classe']); ?></span></td>
                            <td><?php echo htmlspecialchars($linha['descricao']); ?></td>
                            <td><?php echo number_format($linha['quantidade_total'], 2, ',', '.'); ?></td>
                            <td class="text-right"><?php echo formatMoney($linha['faturamento_total']); ?></td>
                            <td class="text-right"><?php echo number_format($linha['participacao'], 2, ',', '.'); ?>%</td>
                            <td class="text-right"><?php echo number_format($linha['acumulado'], 2, ',', '.'); ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="secao">
        <h2>Relatório de Vendas Detalhadas</h2>
        <table>
            <thead>
                <tr>
                    <th>Venda</th>
                    <th>Data</th>
                    <th>Cliente</th>
                    <th>Vendedor</th>
                    <th>Produto / Item</th>
                    <th>Qtd.</th>
                    <th>Unitário</th>
                    <th>Total Item</th>
                    <th>Total Venda</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($vendasDetalhadas)): ?>
                    <tr><td colspan="9">Nenhuma venda encontrada para os filtros informados.</td></tr>
                <?php else: ?>
                    <?php foreach ($vendasDetalhadas as $linha): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($linha['numero']); ?></td>
                            <td><?php echo formatDate($linha['data_venda']); ?></td>
                            <td><?php echo htmlspecialchars($linha['cliente_nome']); ?></td>
                            <td><?php echo htmlspecialchars($linha['vendedor_nome']); ?></td>
                            <td><?php echo htmlspecialchars($linha['descricao_item']); ?></td>
                            <td class="text-right"><?php echo number_format((float) $linha['quantidade'], 2, ',', '.'); ?></td>
                            <td class="text-right"><?php echo formatMoney((float) $linha['valor_unitario']); ?></td>
                            <td class="text-right"><?php echo formatMoney((float) $linha['item_total']); ?></td>
                            <td class="text-right"><?php echo formatMoney((float) $linha['venda_total']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
