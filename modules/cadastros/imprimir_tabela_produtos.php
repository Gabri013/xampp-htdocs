<?php
require_once '../../config/config.php';
require_once '../../includes/engenharia.php';

requirePermission(['master', 'vendedor']);

$senha = (string) ($_GET['senha'] ?? '');
if ($senha !== '1234') {
    http_response_code(403);
    die('Senha inválida para impressão da tabela de preços.');
}

$db = getDB();
ensureEngenhariaSchema($db);

function slugCategoriaPreco(string $nome): string
{
    $nome = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome);
    $nome = strtolower((string) $nome);
    $nome = preg_replace('/[^a-z0-9]+/', '-', $nome);
    return trim((string) $nome, '-');
}

function getPaletaCategoriaPreco(string $categoria, int $indice = 0): array
{
    $paletas = [
        ['base' => '#b84c4c', 'soft' => '#ecd1d1', 'line' => '#d69090'],
        ['base' => '#2f6f87', 'soft' => '#d6e8ef', 'line' => '#8cb8c8'],
        ['base' => '#5c7c3a', 'soft' => '#e2ecd6', 'line' => '#adc98a'],
        ['base' => '#8a5a2b', 'soft' => '#f0dfcf', 'line' => '#d2ae85'],
        ['base' => '#6f4c9b', 'soft' => '#e6dcf2', 'line' => '#b9a2d8'],
        ['base' => '#944f78', 'soft' => '#f1dce8', 'line' => '#d8a3c0'],
    ];

    $slug = slugCategoriaPreco($categoria);
    $mapa = [
        'coccao' => 0,
        'refrigeracao' => 1,
        'distribuicao' => 2,
        'bar' => 3,
        'hospitalar' => 4,
        'mobiliario' => 5,
        'construcao-civil' => 2,
    ];

    $idx = $mapa[$slug] ?? ($indice % count($paletas));
    return $paletas[$idx];
}

function formatarTextoImpressao(?string $texto): string
{
    $texto = trim((string) $texto);
    if ($texto === '') {
        return '-';
    }
    return preg_replace('/\R/u', "\n", $texto);
}

$busca = trim((string) ($_GET['busca'] ?? ''));
$categoriaFiltro = (int) ($_GET['categoria_id'] ?? 0);

$sqlProdutos = "
    SELECT p.*, pc.nome AS categoria_nome
    FROM produtos p
    LEFT JOIN produto_categorias pc ON pc.id = p.categoria_id
    WHERE p.status = 'ativo'
";
$paramsProdutos = [];

if ($busca !== '') {
    $sqlProdutos .= ' AND (p.nome LIKE ? OR p.codigo LIKE ? OR p.observacoes_preco LIKE ?)';
    $paramsProdutos[] = "%{$busca}%";
    $paramsProdutos[] = "%{$busca}%";
    $paramsProdutos[] = "%{$busca}%";
}

if ($categoriaFiltro > 0) {
    $sqlProdutos .= ' AND p.categoria_id = ?';
    $paramsProdutos[] = $categoriaFiltro;
}

$sqlProdutos .= ' ORDER BY COALESCE(pc.nome, "Sem categoria") ASC, p.nome ASC, p.id DESC';
$stmt = $db->prepare($sqlProdutos);
$stmt->execute($paramsProdutos);
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$produtosPorCategoria = [];
foreach ($produtos as $produto) {
    $categoria = trim((string) ($produto['categoria_nome'] ?? ''));
    if ($categoria === '') {
        $categoria = 'Sem categoria';
    }
    if (!isset($produtosPorCategoria[$categoria])) {
        $produtosPorCategoria[$categoria] = [];
    }
    $nomeGrupo = trim((string) ($produto['nome'] ?? ''));
    if ($nomeGrupo === '') {
        $nomeGrupo = 'Produto sem nome';
    }
    if (!isset($produtosPorCategoria[$categoria][$nomeGrupo])) {
        $produtosPorCategoria[$categoria][$nomeGrupo] = [];
    }
    $produtosPorCategoria[$categoria][$nomeGrupo][] = $produto;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Tabela de Preços</title>
    <style>
        @page { size: A4 portrait; margin: 8mm; }
        body { font-family: Arial, sans-serif; margin: 0; background: #f5f5f5; color: #101828; }
        .page { max-width: 100%; margin: 0 auto; background: #fff; padding: 10px 12px 16px 12px; }
        .controls { position: sticky; top: 0; padding: 14px 0 18px 0; background: #fff; z-index: 2; }
        .btn-print { background: #111827; color: #fff; border: 0; border-radius: 8px; padding: 10px 16px; cursor: pointer; font-size: 14px; }
        .header { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 12px; align-items: center; padding: 12px 14px; background: #f2dede; border: 1px solid #e5c8c8; margin-bottom: 10px; }
        .header h1 { margin: 0; font-size: 18px; letter-spacing: 0.02em; }
        .header p { margin: 4px 0 0 0; font-size: 11px; }
        .header-meta { display: flex; gap: 14px; flex-wrap: wrap; margin-top: 10px; font-weight: 700; font-size: 10px; }
        .brand { text-align: right; }
        .brand .marca { font-size: 24px; font-weight: 800; color: #4b5563; letter-spacing: 0.06em; }
        .brand .submarca { margin-top: 4px; font-size: 10px; color: #6b7280; }
        .categoria-section { margin-top: 12px; }
        .categoria-header { padding: 8px 12px; border-radius: 10px 10px 0 0; color: #fff; }
        .categoria-header h2 { margin: 0; font-size: 15px; text-transform: uppercase; }
        .categoria-header p { margin: 3px 0 0 0; font-size: 10px; color: rgba(255,255,255,0.88); }
        .categoria-body { border: 1px solid #111827; border-top: 0; background: #fff; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #111827; padding: 4px 6px; font-size: 10px; vertical-align: middle; line-height: 1.15; }
        th { color: #111827; text-transform: uppercase; font-size: 10px; }
        .compact-table { table-layout: fixed; }
        .compact-table th.col-codigo, .compact-table td.codigo { width: 10%; }
        .compact-table th.col-foto, .compact-table td.foto { width: 11%; }
        .compact-table th.col-medidas, .compact-table td.medidas { width: 14%; }
        .compact-table th.col-observacoes, .compact-table td.observacoes { width: 35%; }
        .compact-table th.col-preco, .compact-table td.preco { width: 15%; }
        .compact-table th.col-embalagem, .compact-table td.embalagem { width: 15%; }
        .produto-titulo-row td { background: #000; color: #fff; font-size: 11px; font-weight: 800; text-transform: uppercase; padding: 5px 7px; letter-spacing: 0.02em; }
        td.codigo, td.medidas { text-align: center; }
        td.observacoes { word-wrap: break-word; word-break: break-word; white-space: pre-wrap; }
        td.preco, td.embalagem { font-size: 11px; font-weight: 800; text-align: right; white-space: nowrap; }
        td.foto { text-align: center; vertical-align: top; padding: 6px 4px; }
        .foto-box {
            width: 100%;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            overflow: hidden;
        }
        .foto-mini-print {
            max-width: 100%;
            width: auto;
            height: auto;
            object-fit: contain;
            display: block;
        }
        .foto-vazia {
            display: block;
            width: 100%;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
        }
        .footer-note { margin-top: 10px; font-size: 10px; color: #6b7280; text-align: right; }
        .vazio { padding: 30px; border: 1px dashed #cbd5e1; text-align: center; color: #64748b; }
        @media print {
            body { background: #fff; }
            .controls { display: none; }
            .page { max-width: none; padding: 0; }
            .categoria-section { page-break-inside: auto; }
            .categoria-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .produto-titulo-row td,
            .compact-table thead tr { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="controls">
            <button class="btn-print" onclick="window.print()">Imprimir tabela de preços</button>
        </div>

        <div class="header">
            <div>
                <h1>TABELA DE PREÇOS</h1>
                <p>Produtos agrupados por categoria com layout visual para uso comercial.</p>
                <div class="header-meta">
                    <span><?php echo date('M/y'); ?></span>
                    <span>Validade: 30 dias</span>
                    <?php if ($busca !== ''): ?><span>Filtro: <?php echo htmlspecialchars($busca); ?></span><?php endif; ?>
                </div>
            </div>
            <div class="brand">
                <div class="marca">COZINCA</div>
                <div class="submarca">Tabela oficial para consulta comercial</div>
            </div>
        </div>

        <?php if (empty($produtosPorCategoria)): ?>
            <div class="vazio">Nenhum produto encontrado para imprimir.</div>
        <?php else: ?>
            <?php $indiceCategoria = 0; ?>
            <?php foreach ($produtosPorCategoria as $categoriaNome => $gruposCategoria): ?>
                <?php $paleta = getPaletaCategoriaPreco($categoriaNome, $indiceCategoria); ?>
                <section class="categoria-section">
                    <div class="categoria-header" style="background: <?php echo htmlspecialchars($paleta['base']); ?>;">
                        <h2><?php echo htmlspecialchars($categoriaNome); ?></h2>
                        <p>Família de produtos organizada para impressão comercial.</p>
                    </div>
                    <div class="categoria-body" style="background: <?php echo htmlspecialchars($paleta['soft']); ?>;">
                        <table class="compact-table">
                            <thead>
                                <tr style="background: <?php echo htmlspecialchars($paleta['line']); ?>;">
                                    <th class="col-codigo">Código</th>
                                    <th class="col-foto">Foto</th>
                                    <th class="col-medidas">Medidas</th>
                                    <th class="col-observacoes">Observações</th>
                                    <th class="col-preco">Preço</th>
                                    <th class="col-embalagem">Embalagem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($gruposCategoria as $nomeGrupo => $variacoes): ?>
                                    <?php
                                    $totalVariacoes = count($variacoes);
                                    $alturaFotoGrupo = max(56, min(110, ($totalVariacoes * 34)));
                                    ?>
                                    <tr class="produto-titulo-row">
                                        <td colspan="6"><?php echo htmlspecialchars($nomeGrupo); ?></td>
                                    </tr>
                                    <?php foreach ($variacoes as $indiceVariacao => $produto): ?>
                                        <tr>
                                            <td class="codigo"><?php echo htmlspecialchars($produto['codigo'] ?: '-'); ?></td>
                                            <?php if ($indiceVariacao === 0): ?>
                                                <td class="foto" rowspan="<?php echo count($variacoes); ?>">
                                                    <div class="foto-box" style="height: <?php echo $alturaFotoGrupo; ?>px;">
                                                        <?php if (!empty($produto['foto'])): ?>
                                                            <img class="foto-mini-print" src="<?php echo SITE_URL; ?>/assets/uploads/produtos/<?php echo htmlspecialchars($produto['foto']); ?>" alt="<?php echo htmlspecialchars($nomeGrupo); ?>" style="max-height: <?php echo $alturaFotoGrupo; ?>px;">
                                                        <?php else: ?>
                                                            <span class="foto-vazia" style="height: <?php echo $alturaFotoGrupo; ?>px;"></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            <?php endif; ?>
                                            <td class="medidas"><?php echo htmlspecialchars($produto['medidas_preco'] ?: '-'); ?></td>
                                            <td class="observacoes"><?php echo htmlspecialchars(formatarTextoImpressao($produto['observacoes_preco'] ?: ($produto['descricao'] ?: '-'))); ?></td>
                                            <td class="preco"><?php echo formatMoney($produto['valor'] ?? 0); ?></td>
                                            <td class="embalagem"><?php echo formatMoney($produto['preco_embalagem'] ?? 0); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                <?php $indiceCategoria++; ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="footer-note">Gerado em <?php echo date('d/m/Y H:i'); ?></div>
    </div>
</body>
</html>
