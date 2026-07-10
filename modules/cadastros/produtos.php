<?php
require_once '../../config/config.php';
require_once '../../includes/engenharia.php';

requirePermission(['master', 'vendedor']);

$page_title = 'Cadastro de Produtos';
$db = getDB();
ensureEngenhariaSchema($db);

function parseMoneyValue($value): float
{
    $value = preg_replace('/[^0-9,.-]/', '', (string) $value);
    if ($value === '' || $value === null) {
        return 0.0;
    }

    if (strpos($value, ',') !== false) {
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
    }

    return (float) $value;
}

function buildComponentesPayload(array $post): array
{
    $campos = ['insumo_id', 'codigo', 'nome', 'fornecedor', 'unidade', 'quantidade', 'custo_unitario'];
    $linhas = [];
    $total = 0;

    foreach ($campos as $campo) {
        $total = max($total, count($post['componentes'][$campo] ?? []));
    }

    for ($i = 0; $i < $total; $i++) {
        $nome = trim((string) ($post['componentes']['nome'][$i] ?? ''));
        $quantidade = parseMoneyValue($post['componentes']['quantidade'][$i] ?? 0);

        if ($nome === '' && $quantidade <= 0) {
            continue;
        }

        $linhas[] = [
            'insumo_id' => (int) ($post['componentes']['insumo_id'][$i] ?? 0),
            'codigo' => sanitize($post['componentes']['codigo'][$i] ?? ''),
            'nome' => sanitize($nome),
            'fornecedor' => sanitize($post['componentes']['fornecedor'][$i] ?? ''),
            'unidade' => sanitize($post['componentes']['unidade'][$i] ?? 'un'),
            'quantidade' => $quantidade,
            'custo_unitario' => parseMoneyValue($post['componentes']['custo_unitario'][$i] ?? 0),
        ];
    }

    return $linhas;
}

function buildInsumoPayload(array $post): array
{
    return [
        'id' => (int) ($post['insumo_id'] ?? 0),
        'codigo' => sanitize($post['insumo_codigo'] ?? ''),
        'nome' => sanitize($post['insumo_nome'] ?? ''),
        'fornecedor' => sanitize($post['insumo_fornecedor'] ?? ''),
        'unidade' => sanitize($post['insumo_unidade'] ?? 'un'),
        'custo_unitario' => parseMoneyValue($post['insumo_custo_unitario'] ?? 0),
        'observacoes' => sanitize($post['insumo_observacoes'] ?? ''),
    ];
}

function buildProdutosLotePayload(array $post): array
{
    $linhas = [];
    $codigos = $post['lote']['codigo'] ?? [];
    $descricoes = $post['lote']['descricao'] ?? [];
    $medidas = $post['lote']['medidas_preco'] ?? [];
    $valores = $post['lote']['valor'] ?? [];
    $total = max(count($codigos), count($descricoes), count($medidas), count($valores));

    for ($i = 0; $i < $total; $i++) {
        $codigo = sanitize($codigos[$i] ?? '');
        $descricao = sanitize($descricoes[$i] ?? '');
        $medidasPreco = sanitize($medidas[$i] ?? '');
        $valorVenda = parseMoneyValue($valores[$i] ?? 0);

        if ($codigo === '' && $descricao === '' && $medidasPreco === '' && $valorVenda <= 0) {
            continue;
        }

        $linhas[] = [
            'codigo' => $codigo,
            'descricao' => $descricao,
            'medidas_preco' => $medidasPreco,
            'valor' => $valorVenda,
        ];
    }

    return $linhas;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar') {
        $id = !empty($_POST['id']) ? (int) $_POST['id'] : null;
        $codigo = sanitize($_POST['codigo'] ?? '');
        $nome = sanitize($_POST['nome'] ?? '');
        $descricao = sanitize($_POST['descricao'] ?? '');
        $unidadeMedida = sanitize($_POST['unidade_medida'] ?? 'un');
        $categoriaId = (int) ($_POST['categoria_id'] ?? 0);
        $medidasPreco = sanitize($_POST['medidas_preco'] ?? '');
        $observacoesPreco = sanitize($_POST['observacoes_preco'] ?? '');
        $valorVenda = parseMoneyValue($_POST['valor'] ?? 0);
        $precoEmbalagem = parseMoneyValue($_POST['preco_embalagem'] ?? 0);
        $estoque = (int) ($_POST['estoque'] ?? 0);
        $status = sanitize($_POST['status'] ?? 'ativo');
        $custoMaoObra = parseMoneyValue($_POST['custo_mao_obra'] ?? 0);
        $custoIndireto = parseMoneyValue($_POST['custo_indireto'] ?? 0);
        $margemLucro = parseMoneyValue($_POST['margem_lucro'] ?? 0);
        $componentes = buildComponentesPayload($_POST);

        if ($codigo === '' || $nome === '') {
            setError('Informe codigo e nome do produto.');
        } elseif ($categoriaId <= 0) {
            setError('Selecione a categoria do produto.');
        } else {
            try {
                $db->beginTransaction();
                $foto = null;
                $insumoIdsAfetados = array_filter(array_map('intval', array_column($componentes, 'insumo_id')));

                if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                    $upload = uploadFile($_FILES['foto'], 'produtos');
                    if ($upload['success']) {
                        $foto = $upload['filename'];
                    }
                }

                if ($id) {
                    if ($foto) {
                        $stmt = $db->prepare('
                            UPDATE produtos
                            SET codigo = ?, nome = ?, descricao = ?, unidade_medida = ?, categoria_id = ?, medidas_preco = ?, observacoes_preco = ?, foto = ?, valor = ?, preco_embalagem = ?, estoque = ?, status = ?, custo_mao_obra = ?, custo_indireto = ?, margem_lucro = ?
                            WHERE id = ?
                        ');
                        $stmt->execute([$codigo, $nome, $descricao, $unidadeMedida ?: 'un', $categoriaId, $medidasPreco, $observacoesPreco, $foto, $valorVenda, $precoEmbalagem, $estoque, $status, $custoMaoObra, $custoIndireto, $margemLucro, $id]);
                    } else {
                        $stmt = $db->prepare('
                            UPDATE produtos
                            SET codigo = ?, nome = ?, descricao = ?, unidade_medida = ?, categoria_id = ?, medidas_preco = ?, observacoes_preco = ?, valor = ?, preco_embalagem = ?, estoque = ?, status = ?, custo_mao_obra = ?, custo_indireto = ?, margem_lucro = ?
                            WHERE id = ?
                        ');
                        $stmt->execute([$codigo, $nome, $descricao, $unidadeMedida ?: 'un', $categoriaId, $medidasPreco, $observacoesPreco, $valorVenda, $precoEmbalagem, $estoque, $status, $custoMaoObra, $custoIndireto, $margemLucro, $id]);
                    }
                } else {
                    $stmt = $db->prepare('
                        INSERT INTO produtos (codigo, nome, descricao, unidade_medida, categoria_id, medidas_preco, observacoes_preco, foto, valor, preco_embalagem, estoque, status, custo_mao_obra, custo_indireto, margem_lucro, custo_total, preco_sugerido)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0)
                    ');
                    $stmt->execute([$codigo, $nome, $descricao, $unidadeMedida ?: 'un', $categoriaId, $medidasPreco, $observacoesPreco, $foto, $valorVenda, $precoEmbalagem, $estoque, $status, $custoMaoObra, $custoIndireto, $margemLucro]);
                    $id = (int) $db->lastInsertId();
                }

                salvarComponentesProduto($db, (int) $id, $componentes);
                $custosPorProduto = atualizarCustosProdutosAfetados($db, $insumoIdsAfetados, [(int) $id]);
                $custos = $custosPorProduto[(int) $id] ?? atualizarCustosProduto($db, (int) $id);
                $db->commit();

                $mensagem = 'Produto salvo com sucesso! '; 
                $mensagem .= 'Custo total: ' . formatMoney($custos['custo_total']) . ' | Preco sugerido: ' . formatMoney($custos['preco_sugerido']);
                setSuccess($mensagem);
                header('Location: produtos.php');
                exit;
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                setError('Erro ao salvar produto: ' . $e->getMessage());
            }
        }
    } elseif ($acao === 'salvar_insumo') {
        $dadosInsumo = buildInsumoPayload($_POST);

        if ($dadosInsumo['nome'] === '') {
            setError('Informe o nome do insumo.');
        } else {
            try {
                $db->beginTransaction();
                $insumoId = upsertInsumo($db, $dadosInsumo);
                $produtosAfetados = atualizarCustosProdutosAfetados($db, [$insumoId]);
                $db->commit();

                $mensagem = 'Insumo salvo com sucesso!';
                if (!empty($produtosAfetados)) {
                    $mensagem .= ' ' . count($produtosAfetados) . ' produto(s) tiveram o custo recalculado automaticamente.';
                }

                setSuccess($mensagem);
                header('Location: produtos.php');
                exit;
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                setError('Erro ao salvar insumo: ' . $e->getMessage());
            }
        }
    } elseif ($acao === 'salvar_categoria') {
        $categoriaId = (int) ($_POST['categoria_id'] ?? 0);
        $categoriaNome = sanitize($_POST['categoria_nome'] ?? '');
        $categoriaDescricao = sanitize($_POST['categoria_descricao'] ?? '');
        $categoriaStatus = sanitize($_POST['categoria_status'] ?? 'ativo');

        if ($categoriaNome === '') {
            setError('Informe o nome da categoria.');
        } else {
            try {
                if ($categoriaId > 0) {
                    $stmt = $db->prepare('UPDATE produto_categorias SET nome = ?, descricao = ?, status = ? WHERE id = ?');
                    $stmt->execute([$categoriaNome, $categoriaDescricao, $categoriaStatus, $categoriaId]);
                    setSuccess('Categoria atualizada com sucesso!');
                } else {
                    $stmt = $db->prepare('INSERT INTO produto_categorias (nome, descricao, status) VALUES (?, ?, ?)');
                    $stmt->execute([$categoriaNome, $categoriaDescricao, $categoriaStatus]);
                    setSuccess('Categoria cadastrada com sucesso!');
                }

                header('Location: produtos.php');
                exit;
            } catch (Exception $e) {
                setError('Erro ao salvar categoria: ' . $e->getMessage());
            }
        }
    } elseif ($acao === 'salvar_lote') {
        $categoriaId = (int) ($_POST['lote_categoria_id'] ?? 0);
        $nomeLote = sanitize($_POST['lote_nome'] ?? '');
        $unidadeMedida = sanitize($_POST['lote_unidade_medida'] ?? 'un');
        $status = sanitize($_POST['lote_status'] ?? 'ativo');
        $percentualEmbalagem = parseMoneyValue($_POST['lote_percentual_embalagem'] ?? 0);
        $produtosLote = buildProdutosLotePayload($_POST);

        if ($categoriaId <= 0) {
            setError('Selecione a categoria para o cadastro em lote.');
        } elseif ($nomeLote === '') {
            setError('Informe o nome principal do produto para o cadastro em lote.');
        } elseif (empty($produtosLote)) {
            setError('Informe ao menos um produto no cadastro em lote.');
        } elseif (!isset($_FILES['lote_foto']) || ($_FILES['lote_foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            setError('Selecione a foto que será usada para todos os produtos do lote.');
        } else {
            try {
                $upload = uploadFile($_FILES['lote_foto'], 'produtos');
                if (!$upload['success']) {
                    throw new RuntimeException($upload['message'] ?? 'Não foi possível enviar a foto do lote.');
                }

                $foto = $upload['filename'];
                $db->beginTransaction();
                $stmt = $db->prepare('
                    INSERT INTO produtos (
                        codigo, nome, descricao, unidade_medida, categoria_id, medidas_preco, observacoes_preco, foto, valor,
                        percentual_embalagem, preco_embalagem, estoque, status, custo_mao_obra, custo_indireto, margem_lucro, custo_total, preco_sugerido
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 0, 0, 30, 0, 0)
                ');

                $totalInseridos = 0;
                foreach ($produtosLote as $itemLote) {
                    if ($itemLote['codigo'] === '' || $itemLote['descricao'] === '') {
                        continue;
                    }

                    $precoEmbalagem = $itemLote['valor'] > 0
                        ? round($itemLote['valor'] * ($percentualEmbalagem / 100), 2)
                        : 0;

                    $stmt->execute([
                        $itemLote['codigo'],
                        $nomeLote,
                        $itemLote['descricao'],
                        $unidadeMedida ?: 'un',
                        $categoriaId,
                        $itemLote['medidas_preco'],
                        $itemLote['descricao'],
                        $foto,
                        $itemLote['valor'],
                        $percentualEmbalagem,
                        $precoEmbalagem,
                        $status,
                    ]);
                    $totalInseridos++;
                }

                if ($totalInseridos === 0) {
                    throw new RuntimeException('Preencha pelo menos código e descrição em uma linha do lote.');
                }

                $db->commit();
                setSuccess($totalInseridos . ' produto(s) cadastrado(s) em lote com a mesma foto.');
                header('Location: produtos.php');
                exit;
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                setError('Erro ao salvar lote de produtos: ' . $e->getMessage());
            }
        }
    } elseif ($acao === 'excluir') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $stmt = $db->prepare('DELETE FROM produtos WHERE id = ?');
            $stmt->execute([$id]);
            setSuccess('Produto excluido com sucesso!');
            header('Location: produtos.php');
            exit;
        } catch (Exception $e) {
            setError('Erro ao excluir produto: ' . $e->getMessage());
        }
    } elseif ($acao === 'excluir_lote') {
        $ids = array_values(array_filter(array_map('intval', $_POST['ids'] ?? [])));

        if (empty($ids)) {
            setError('Selecione ao menos um produto para excluir.');
        } else {
            try {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $db->prepare("DELETE FROM produtos WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $totalExcluidos = $stmt->rowCount();
                setSuccess($totalExcluidos . ' produto(s) excluido(s) com sucesso!');
                header('Location: produtos.php');
                exit;
            } catch (Exception $e) {
                setError('Erro ao excluir produtos: ' . $e->getMessage());
            }
        }
    } elseif ($acao === 'excluir_insumo') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $stmtProdutosAfetados = $db->prepare("
                SELECT DISTINCT ep.produto_id
                FROM componentes_produto cp
                INNER JOIN estrutura_produto ep ON ep.id = cp.estrutura_id
                WHERE cp.insumo_id = ?
            ");
            $stmtProdutosAfetados->execute([$id]);
            $produtosAfetados = array_values(array_filter(array_map('intval', $stmtProdutosAfetados->fetchAll(PDO::FETCH_COLUMN))));

            $db->beginTransaction();
            $stmt = $db->prepare('DELETE FROM insumos WHERE id = ?');
            $stmt->execute([$id]);

            foreach ($produtosAfetados as $produtoAfetadoId) {
                atualizarCustosProduto($db, $produtoAfetadoId);
            }

            $db->commit();
            setSuccess('Insumo excluido com sucesso!');
            header('Location: produtos.php');
            exit;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            setError('Erro ao excluir insumo: ' . $e->getMessage());
        }
    } elseif ($acao === 'excluir_insumo_lote') {
        $ids = array_values(array_filter(array_map('intval', $_POST['ids_insumos'] ?? [])));

        if (empty($ids)) {
            setError('Selecione ao menos um insumo para excluir.');
        } else {
            try {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmtProdutosAfetados = $db->prepare("
                    SELECT DISTINCT ep.produto_id
                    FROM componentes_produto cp
                    INNER JOIN estrutura_produto ep ON ep.id = cp.estrutura_id
                    WHERE cp.insumo_id IN ($placeholders)
                ");
                $stmtProdutosAfetados->execute($ids);
                $produtosAfetados = array_values(array_filter(array_map('intval', $stmtProdutosAfetados->fetchAll(PDO::FETCH_COLUMN))));

                $db->beginTransaction();
                $stmt = $db->prepare("DELETE FROM insumos WHERE id IN ($placeholders)");
                $stmt->execute($ids);

                foreach ($produtosAfetados as $produtoAfetadoId) {
                    atualizarCustosProduto($db, $produtoAfetadoId);
                }

                $db->commit();
                $totalExcluidos = $stmt->rowCount();
                setSuccess($totalExcluidos . ' insumo(s) excluido(s) com sucesso!');
                header('Location: produtos.php');
                exit;
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                setError('Erro ao excluir insumos: ' . $e->getMessage());
            }
        }
    }
}

$categorias = $db->query("SELECT * FROM produto_categorias ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

$busca = trim($_GET['busca'] ?? '');
$categoriaFiltro = (int) ($_GET['categoria_id'] ?? 0);
$sqlProdutos = "
    SELECT p.*, pc.nome AS categoria_nome, pc.status AS categoria_status
    FROM produtos p
    LEFT JOIN produto_categorias pc ON pc.id = p.categoria_id
    WHERE 1 = 1
";
$paramsProdutos = [];

if ($busca !== '') {
    $sqlProdutos .= ' AND (p.nome LIKE ? OR p.codigo LIKE ?)';
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

$componentesPorProduto = [];
foreach ($produtos as &$produto) {
    $custos = atualizarCustosProduto($db, (int) $produto['id']);
    $produto['custo_total'] = $custos['custo_total'];
    $produto['preco_sugerido'] = $custos['preco_sugerido'];
    $produto['custo_materiais'] = $custos['custo_materiais'];

    $componentesPorProduto[(int) $produto['id']] = $custos['componentes'];
}
unset($produto);

$stmtInsumos = $db->query("
    SELECT
        i.*,
        COUNT(DISTINCT ep.produto_id) AS total_produtos
    FROM insumos i
    LEFT JOIN componentes_produto cp ON cp.insumo_id = i.id
    LEFT JOIN estrutura_produto ep ON ep.id = cp.estrutura_id
    GROUP BY i.id
    ORDER BY i.nome ASC, i.fornecedor ASC
");
$insumos = $stmtInsumos->fetchAll(PDO::FETCH_ASSOC);
$insumosResumo = [
    'total' => count($insumos),
    'custo_medio' => 0,
];

if ($insumosResumo['total'] > 0) {
    $insumosResumo['custo_medio'] = array_sum(array_map(static function (array $insumo): float {
        return (float) ($insumo['custo_unitario'] ?? 0);
    }, $insumos)) / $insumosResumo['total'];
}

include '../../includes/header_vendedor.php';
?>

<style>
.produtos-layout .card-header,
.produtos-layout .card-body {
    padding: 18px 22px;
}

.produtos-layout .btn {
    border-radius: 8px;
}

.produtos-layout table {
    width: 100%;
    border-collapse: collapse;
}

.produtos-layout thead th {
    padding: 11px 10px;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #667085;
    white-space: nowrap;
}

.produtos-layout tbody td {
    padding: 12px 10px;
    font-size: 14px;
    border-top: 1px solid #edf2f7;
    vertical-align: middle;
}

.produtos-layout .vbadge {
    display: inline-flex;
    align-items: center;
    min-height: 24px;
    padding: 4px 9px;
    border-radius: 999px;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
}

.produtos-layout .foto-thumb,
.produtos-layout .foto-placeholder {
    width: 38px;
    height: 38px;
    border-radius: 8px;
    object-fit: cover;
}

.produtos-layout .foto-placeholder {
    background: #eef2f7;
}

.bom-modal {
    background: #fff;
    margin: 2% auto;
    width: 96%;
    max-width: 1180px;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 24px 80px rgba(15, 23, 42, 0.22);
}

.bom-grid {
    display: grid;
    grid-template-columns: 1.2fr 0.9fr;
    gap: 22px;
}

.form-grid-2,
.form-grid-3,
.form-grid-4 {
    display: grid;
    gap: 12px;
}

.form-grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.form-grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.form-grid-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }

.form-group {
    margin-bottom: 14px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-size: 13px;
    font-weight: 600;
    color: #344054;
}

.summary-cards {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 18px;
}

.summary-card {
    padding: 14px 16px;
    border: 1px solid #e4e7ec;
    border-radius: 12px;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
}

.summary-card .label {
    display: block;
    font-size: 12px;
    color: #667085;
    margin-bottom: 6px;
}

.summary-card .value {
    display: block;
    font-size: 22px;
    font-weight: 700;
    color: #101828;
}

.componentes-box {
    border: 1px solid #e4e7ec;
    border-radius: 14px;
    overflow: hidden;
}

.componentes-header,
.componentes-row {
    display: grid;
    grid-template-columns: 100px 1.5fr 1.2fr 90px 90px 120px 120px 50px;
    gap: 10px;
    align-items: center;
}

.componentes-header {
    padding: 12px 14px;
    background: #f8fafc;
    font-size: 12px;
    font-weight: 700;
    color: #475467;
}

.componentes-body {
    max-height: 360px;
    overflow: auto;
    padding: 12px 14px;
}

.componentes-row {
    padding: 10px 0;
    border-bottom: 1px solid #f1f5f9;
}

.componentes-row:last-child {
    border-bottom: none;
}

.componentes-row input {
    width: 100%;
}

.componentes-row .componente-total {
    font-size: 12px;
    font-weight: 700;
    color: #101828;
    text-align: right;
}

.insumos-layout {
    margin-top: 24px;
}

.insumos-layout .insumos-topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 18px;
    flex-wrap: wrap;
}

.insumos-table td small {
    display: block;
    margin-top: 4px;
    color: #667085;
}

.alerta-bom {
    margin-top: 12px;
    padding: 12px 14px;
    border-radius: 10px;
    background: #eef6ff;
    color: #1d4f91;
    font-size: 13px;
}

.top-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.categoria-tag {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 999px;
    background: #e0f2fe;
    color: #075985;
    font-size: 12px;
    font-weight: 700;
}

.print-options-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    margin-top: 14px;
}

.print-option {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    border: 1px solid #dbe4ee;
    border-radius: 10px;
    background: #f8fafc;
}

.print-option input {
    width: 18px;
    height: 18px;
}

@media (max-width: 1100px) {
    .bom-grid,
    .form-grid-2,
    .form-grid-3,
    .form-grid-4,
    .summary-cards {
        grid-template-columns: 1fr;
    }

    .componentes-header,
    .componentes-row {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

.componentes-row .componente-total {
        text-align: left;
    }
}

.acoes-lote {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}

.selecionados-info {
    font-size: 13px;
    color: #667085;
}
</style>

<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-page-head"><h1 class="vend-page-title">Produtos</h1></div>
        <div class="vend-content">
            <div class="vend-card produtos-layout"><div style="padding:24px;">
    <div class="vend-card-header" style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
        <div>
            <h3>Produtos Cadastrados</h3>
            <p style="margin-top:6px;color:#667085;">Cadastro com BOM, custo de fabricacao, preco sugerido e preco de venda.</p>
        </div>
        <div class="top-actions">
            <button class="vbtn-sm btn-secondary" type="button" onclick="abrirModalCategoria()">
                <i class="fas fa-tags"></i> Cadastrar Categoria
            </button>
            <button class="vbtn-sm btn-secondary" type="button" onclick="abrirModalLote()">
                <i class="fas fa-layer-group"></i> Cadastrar em Lote
            </button>
            <button class="vbtn-sm btn-secondary" type="button" onclick="imprimirTabelaProdutos()">
                <i class="fas fa-print"></i> Imprimir Tabela
            </button>
            <button class="vbtn-sm btn-primary" type="button" onclick="abrirModal()">
                <i class="fas fa-plus"></i> Novo Produto
            </button>
        </div>
    </div>
    <div class="vend-card-body">
        <form method="GET" class="mb-20">
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <input type="text" name="busca" class="form-control" placeholder="Buscar por nome ou codigo..." value="<?php echo htmlspecialchars($busca); ?>">
                <select name="categoria_id" class="form-control" style="max-width:260px;">
                    <option value="0">Todas as categorias</option>
                    <?php foreach ($categorias as $categoria): ?>
                        <option value="<?php echo (int) $categoria['id']; ?>" <?php echo $categoriaFiltro === (int) $categoria['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($categoria['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="vbtn-sm btn-primary">Buscar</button>
                <?php if ($busca !== '' || $categoriaFiltro > 0): ?>
                    <a href="produtos.php" class="vbtn-sm btn-secondary">Limpar</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="acoes-lote">
            <div class="selecionados-info">
                <strong id="contadorSelecionados">0</strong> produto(s) selecionado(s)
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button type="button" class="vbtn-sm btn-danger" id="btnExcluirSelecionados" onclick="confirmarExclusaoLote()" disabled>
                    <i class="fas fa-trash"></i> Excluir Selecionados
                </button>
            </div>
        </div>

        <form method="POST" id="formExclusaoLote">
            <input type="hidden" name="acao" value="excluir_lote">
            <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:44px;text-align:center;">
                            <input type="checkbox" id="selecionarTodosProdutos" onchange="toggleSelecionarTodosProdutos(this)">
                        </th>
                        <th>Codigo</th>
                        <th>Foto</th>
                        <th>Produto</th>
                        <th>Categoria</th>
                        <th>Custo</th>
                        <th>Preco Sugerido</th>
                        <th>Preco Venda</th>
                        <th>BOM</th>
                        <th>Status</th>
                        <th>Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($produtos)): ?>
                        <tr><td colspan="11" class="text-center">Nenhum produto encontrado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($produtos as $produto): ?>
                            <?php $produtoPayload = $produto; ?>
                            <?php $produtoPayload['componentes'] = $componentesPorProduto[(int) $produto['id']] ?? []; ?>
                            <tr>
                                <td style="text-align:center;">
                                    <input type="checkbox" name="ids[]" value="<?php echo (int) $produto['id']; ?>" class="produto-checkbox" onchange="atualizarSelecaoProdutos()">
                                </td>
                                <td><?php echo htmlspecialchars($produto['codigo']); ?></td>
                                <td>
                                    <?php if (!empty($produto['foto'])): ?>
                                        <img class="foto-thumb" src="<?php echo SITE_URL; ?>/assets/uploads/produtos/<?php echo htmlspecialchars($produto['foto']); ?>" alt="Produto">
                                    <?php else: ?>
                                        <div class="foto-placeholder"></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($produto['nome']); ?></strong>
                                    <div style="font-size:12px;color:#667085;margin-top:4px;">
                                        <?php echo htmlspecialchars($produto['unidade_medida'] ?? 'un'); ?>
                                        <?php if (!empty($produto['descricao'])): ?>
                                            | <?php echo htmlspecialchars($produto['descricao']); ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($produto['categoria_nome'])): ?>
                                        <span class="categoria-tag"><?php echo htmlspecialchars($produto['categoria_nome']); ?></span>
                                    <?php else: ?>
                                        <span style="color:#98a2b3;">Sem categoria</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatMoney($produto['custo_total'] ?? 0); ?></td>
                                <td><?php echo formatMoney($produto['preco_sugerido'] ?? 0); ?></td>
                                <td><?php echo formatMoney($produto['valor'] ?? 0); ?></td>
                                <td><?php echo count($produtoPayload['componentes']); ?> itens</td>
                                <td>
                                    <span class="badge" style="background: <?php echo ($produto['status'] ?? 'ativo') === 'ativo' ? '#16a34a' : '#64748b'; ?>;">
                                        <?php echo ucfirst($produto['status'] ?? 'ativo'); ?>
                                    </span>
                                </td>
                                <td style="white-space:nowrap;">
                                    <button class="vbtn-sm btn-sm btn-primary" type="button" onclick='editarProduto(<?php echo json_encode($produtoPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="vbtn-sm btn-sm btn-danger" type="button" onclick="excluirProduto(<?php echo (int) $produto['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </form>
    </div>
</div>

<div class="vend-card produtos-layout insumos-layout">
    <div class="vend-card-header">
        <div class="insumos-topbar">
            <div>
                <h3>Catalogo de Insumos</h3>
                <p style="margin-top:6px;color:#667085;">Atualize o custo do fornecedor uma unica vez para refletir automaticamente nos produtos que usam o mesmo componente.</p>
            </div>
            <button class="vbtn-sm btn-primary" type="button" onclick="abrirModalInsumo()">
                <i class="fas fa-boxes"></i> Novo Insumo
            </button>
        </div>

        <div class="summary-cards" style="margin-bottom:0;">
            <div class="summary-card">
                <span class="label">Total de Insumos</span>
                <span class="value"><?php echo number_format($insumosResumo['total'], 0, ',', '.'); ?></span>
            </div>
            <div class="summary-card">
                <span class="label">Custo Medio</span>
                <span class="value"><?php echo formatMoney($insumosResumo['custo_medio']); ?></span>
            </div>
        </div>
    </div>
    <div class="vend-card-body">
        <div class="table-responsive">
            <div class="acoes-lote">
                <div class="selecionados-info">
                    <strong id="contadorSelecionadosInsumos">0</strong> insumo(s) selecionado(s)
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <button type="button" class="vbtn-sm btn-danger" id="btnExcluirSelecionadosInsumos" onclick="confirmarExclusaoLoteInsumos()" disabled>
                        <i class="fas fa-trash"></i> Excluir Selecionados
                    </button>
                </div>
            </div>

            <form method="POST" id="formExclusaoLoteInsumos">
                <input type="hidden" name="acao" value="excluir_insumo_lote">
            <table class="table insumos-table">
                <thead>
                    <tr>
                        <th style="width:44px;text-align:center;">
                            <input type="checkbox" id="selecionarTodosInsumos" onchange="toggleSelecionarTodosInsumos(this)">
                        </th>
                        <th>Codigo</th>
                        <th>Insumo</th>
                        <th>Fornecedor</th>
                        <th>Unidade</th>
                        <th>Custo Atual</th>
                        <th>Uso na BOM</th>
                        <th>Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($insumos)): ?>
                        <tr><td colspan="8" class="text-center">Nenhum insumo cadastrado ainda.</td></tr>
                    <?php else: ?>
                        <?php foreach ($insumos as $insumo): ?>
                            <tr>
                                <td style="text-align:center;">
                                    <input type="checkbox" name="ids_insumos[]" value="<?php echo (int) $insumo['id']; ?>" class="insumo-checkbox" onchange="atualizarSelecaoInsumos()">
                                </td>
                                <td><?php echo htmlspecialchars($insumo['codigo'] ?: '-'); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($insumo['nome']); ?></strong>
                                    <?php if (!empty($insumo['observacoes'])): ?>
                                        <small><?php echo htmlspecialchars($insumo['observacoes']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($insumo['fornecedor'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($insumo['unidade'] ?: 'un'); ?></td>
                                <td><?php echo formatMoney($insumo['custo_unitario'] ?? 0); ?></td>
                                <td><?php echo (int) ($insumo['total_produtos'] ?? 0); ?> produto(s)</td>
                                <td>
                                    <button class="vbtn-sm btn-sm btn-primary" type="button" onclick='editarInsumo(<?php echo json_encode($insumo, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="vbtn-sm btn-sm btn-danger" type="button" onclick="excluirInsumo(<?php echo (int) $insumo['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            </form>
        </div>
    </div>
</div>
</div>

</div>
</div>

<div id="modalImpressao" class="modal" style="display:none;position:fixed;z-index:1002;left:0;top:0;width:100%;height:100%;padding:16px;background:rgba(15,23,42,0.64);overflow:auto;">
    <div class="bom-modal" style="max-width:760px;">
        <div class="vend-card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <h3>Imprimir Tabela de Produtos</h3>
                <p style="margin-top:6px;color:#667085;">Escolha quais informações devem aparecer na impressão.</p>
            </div>
            <button type="button" class="vbtn-sm btn-secondary" onclick="fecharModalImpressao()">Fechar</button>
        </div>
        <div class="vend-card-body">
            <div class="form-group">
                <label>Senha de Impressão *</label>
                <input type="password" id="senha_impressao_produtos" class="form-control" placeholder="Digite a senha">
            </div>

            <div class="form-group">
                <label>Selecionar os tipos de opções a serem impressos</label>
                <div class="print-options-grid">
                    <label class="print-option"><input type="checkbox" name="print_cols" value="codigo" checked> Codigo</label>
                    <label class="print-option"><input type="checkbox" name="print_cols" value="foto" checked> Foto</label>
                    <label class="print-option"><input type="checkbox" name="print_cols" value="produto" checked> Produto</label>
                    <label class="print-option"><input type="checkbox" name="print_cols" value="categoria" checked> Categoria</label>
                    <label class="print-option"><input type="checkbox" name="print_cols" value="custo" checked> Custo</label>
                    <label class="print-option"><input type="checkbox" name="print_cols" value="preco_sugerido" checked> Preco Sugerido</label>
                    <label class="print-option"><input type="checkbox" name="print_cols" value="preco_venda" checked> Preco Venda</label>
                </div>
            </div>

            <div class="alerta-bom">
                A senha para liberar a impressão é <strong>1234</strong>.
            </div>

            <div style="margin-top:24px;text-align:right;display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" class="vbtn-sm btn-secondary" onclick="fecharModalImpressao()">Cancelar</button>
                <button type="button" class="vbtn-sm btn-primary" onclick="confirmarImpressaoProdutos()">Gerar Impressão</button>
            </div>
        </div>
    </div>
</div>

<div id="modalProduto" class="modal" style="display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;padding:16px;background:rgba(15,23,42,0.64);overflow:auto;">
    <div class="bom-modal">
        <div class="vend-card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <h3 id="modalTitulo">Novo Produto</h3>
                <p style="margin-top:6px;color:#667085;">Monte a estrutura de componentes para recalcular automaticamente o custo do produto.</p>
            </div>
            <button type="button" class="vbtn-sm btn-secondary" onclick="fecharModal()">Fechar</button>
        </div>
        <div class="vend-card-body">
            <form method="POST" enctype="multipart/form-data" id="formProduto">
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id" id="produtoId">

                <div class="bom-grid">
                    <div>
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label>Codigo *</label>
                                <input type="text" id="codigo" name="codigo" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Unidade</label>
                                <input type="text" id="unidade_medida" name="unidade_medida" class="form-control" value="un">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Tipo de Produto / Categoria *</label>
                            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                                <select id="categoria_id" name="categoria_id" class="form-control" required>
                                    <option value="">Selecione a categoria...</option>
                                    <?php foreach ($categorias as $categoria): ?>
                                        <option value="<?php echo (int) $categoria['id']; ?>">
                                            <?php echo htmlspecialchars($categoria['nome']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="vbtn-sm btn-secondary" onclick="abrirModalCategoria()">
                                    Nova categoria
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Nome *</label>
                            <input type="text" id="nome" name="nome" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label>Descricao</label>
                            <textarea id="descricao" name="descricao" class="form-control" rows="3"></textarea>
                        </div>

                        <div class="form-grid-2">
                            <div class="form-group">
                                <label>Medidas para Tabela de Preco</label>
                                <input type="text" id="medidas_preco" name="medidas_preco" class="form-control" placeholder="Ex.: 835x450x850">
                            </div>
                            <div class="form-group">
                                <label>Preco Embalagem</label>
                                <input type="text" id="preco_embalagem" name="preco_embalagem" class="form-control money" placeholder="0,00">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Observacoes para Tabela de Preco</label>
                            <textarea id="observacoes_preco" name="observacoes_preco" class="form-control" rows="2" placeholder="Ex.: 2 bocas 30 encosto horiz."></textarea>
                        </div>

                        <div class="form-grid-4">
                            <div class="form-group">
                                <label>Preco de Venda</label>
                                <input type="text" id="valor" name="valor" class="form-control money" placeholder="0,00">
                            </div>
                            <div class="form-group">
                                <label>Estoque</label>
                                <input type="number" id="estoque" name="estoque" class="form-control" min="0" value="0">
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="ativo">Ativo</option>
                                    <option value="inativo">Inativo</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Foto do Produto</label>
                                <input type="file" name="foto" class="form-control" accept="image/*">
                            </div>
                        </div>

                        <div class="form-grid-3">
                            <div class="form-group">
                                <label>Mao de Obra</label>
                                <input type="text" id="custo_mao_obra" name="custo_mao_obra" class="form-control money calc-field" placeholder="0,00">
                            </div>
                            <div class="form-group">
                                <label>Custos Indiretos</label>
                                <input type="text" id="custo_indireto" name="custo_indireto" class="form-control money calc-field" placeholder="0,00">
                            </div>
                            <div class="form-group">
                                <label>Margem Sugerida (%)</label>
                                <input type="number" step="0.01" min="0" id="margem_lucro" name="margem_lucro" class="form-control calc-field" value="30">
                            </div>
                        </div>

                        <div class="componentes-box">
                            <div class="componentes-header">
                                <span>Codigo</span>
                                <span>Componente</span>
                                <span>Fornecedor</span>
                                <span>Unidade</span>
                                <span>Qtd.</span>
                                <span>Custo Unit.</span>
                                <span>Total</span>
                                <span></span>
                            </div>
                            <div class="componentes-body" id="componentesBody"></div>
                        </div>

                        <div style="margin-top:12px;display:flex;gap:10px;justify-content:space-between;align-items:center;flex-wrap:wrap;">
                            <div class="alerta-bom">Se o custo de um componente for atualizado em outro produto usando o mesmo insumo e fornecedor, o custo deste produto sera recalculado automaticamente.</div>
                            <button type="button" class="vbtn-sm btn-secondary" onclick="adicionarLinhaComponente()">
                                <i class="fas fa-plus"></i> Adicionar Componente
                            </button>
                        </div>
                    </div>

                    <div>
                        <div class="summary-cards">
                            <div class="summary-card">
                                <span class="label">Custo de Materiais</span>
                                <span class="value" id="resumoMateriais">R$ 0,00</span>
                            </div>
                            <div class="summary-card">
                                <span class="label">Custo Total</span>
                                <span class="value" id="resumoTotal">R$ 0,00</span>
                            </div>
                            <div class="summary-card">
                                <span class="label">Preco Sugerido</span>
                                <span class="value" id="resumoSugerido">R$ 0,00</span>
                            </div>
                            <div class="summary-card">
                                <span class="label">Diferenca x Venda</span>
                                <span class="value" id="resumoDiferenca">R$ 0,00</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Preco Sugerido Calculado</label>
                            <input type="text" id="preco_sugerido_visual" class="form-control" readonly>
                        </div>
                        <div class="form-group">
                            <label>Custo Total Calculado</label>
                            <input type="text" id="custo_total_visual" class="form-control" readonly>
                        </div>

                        <div class="alerta-bom" style="background:#f8fafc;color:#475467;">
                            O preco de venda continua sendo o campo comercial do sistema. O preco sugerido e apenas calculado com base na BOM, mao de obra, custos indiretos e margem definida.
                        </div>
                    </div>
                </div>

                <div style="margin-top:24px;text-align:right;display:flex;justify-content:flex-end;gap:10px;">
                    <button type="button" class="vbtn-sm btn-secondary" onclick="fecharModal()">Cancelar</button>
                    <button type="submit" class="vbtn-sm btn-primary">Salvar Produto</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="modalProdutoLote" class="modal" style="display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;padding:16px;background:rgba(15,23,42,0.64);overflow:auto;">
    <div class="bom-modal" style="max-width:1100px;">
        <div class="vend-card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <h3>Cadastro em Lote</h3>
                <p style="margin-top:6px;color:#667085;">Use uma única foto para todos os itens e altere apenas código, descrição, medidas e valor em cada linha.</p>
            </div>
            <button type="button" class="vbtn-sm btn-secondary" onclick="fecharModalLote()">Fechar</button>
        </div>
        <div class="vend-card-body">
            <form method="POST" enctype="multipart/form-data" id="formProdutoLote">
                <input type="hidden" name="acao" value="salvar_lote">

                <div class="form-grid-3">
                    <div class="form-group">
                        <label>Nome principal do produto *</label>
                        <input type="text" id="lote_nome" name="lote_nome" class="form-control" placeholder="Ex.: Mesa lisa com prateleira perfurada inferior" required>
                    </div>
                    <div class="form-group">
                        <label>Categoria *</label>
                        <select id="lote_categoria_id" name="lote_categoria_id" class="form-control" required>
                            <option value="">Selecione a categoria...</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?php echo (int) $categoria['id']; ?>">
                                    <?php echo htmlspecialchars($categoria['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Unidade</label>
                        <input type="text" id="lote_unidade_medida" name="lote_unidade_medida" class="form-control" value="un">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select id="lote_status" name="lote_status" class="form-control">
                            <option value="ativo">Ativo</option>
                            <option value="inativo">Inativo</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Percentual da Embalagem (%)</label>
                        <input type="text" id="lote_percentual_embalagem" name="lote_percentual_embalagem" class="form-control money" placeholder="0,00">
                    </div>
                    <div class="form-group">
                        <label>Foto única para todos os produtos *</label>
                        <input type="file" name="lote_foto" id="lote_foto" class="form-control" accept="image/*" required>
                    </div>
                </div>

                <div class="alerta-bom">
                    A foto enviada aqui será vinculada em todos os produtos criados neste lote. O preço da embalagem será calculado automaticamente com base no percentual informado.
                </div>

                <div class="componentes-box" style="margin-top:18px;">
                    <div class="componentes-header" style="grid-template-columns: 160px 1.8fr 1.2fr 140px 50px;">
                        <span>Código</span>
                        <span>Descrição</span>
                        <span>Medidas</span>
                        <span>Valor</span>
                        <span></span>
                    </div>
                    <div class="componentes-body" id="produtosLoteBody"></div>
                </div>

                <div style="margin-top:12px;display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                    <div class="alerta-bom">Cada linha gera uma variação do mesmo produto principal, mantendo a mesma foto e mudando apenas código, descrição, medidas e valor.</div>
                    <button type="button" class="vbtn-sm btn-secondary" onclick="adicionarLinhaProdutoLote()">
                        <i class="fas fa-plus"></i> Adicionar Linha
                    </button>
                </div>

                <div style="margin-top:24px;text-align:right;display:flex;justify-content:flex-end;gap:10px;">
                    <button type="button" class="vbtn-sm btn-secondary" onclick="fecharModalLote()">Cancelar</button>
                    <button type="submit" class="vbtn-sm btn-primary">Salvar Lote</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="modalExclusaoLote" class="modal" style="display:none;position:fixed;z-index:1003;left:0;top:0;width:100%;height:100%;padding:16px;background:rgba(15,23,42,0.72);overflow:auto;">
    <div class="bom-modal" style="max-width:640px;">
        <div class="vend-card-header" style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
            <div>
                <h3>Confirmar exclusão</h3>
                <p style="margin-top:6px;color:#667085;">Revise antes de continuar.</p>
            </div>
            <button type="button" class="vbtn-sm btn-secondary" onclick="fecharModalExclusaoLote()">Fechar</button>
        </div>
        <div class="vend-card-body">
            <div class="alerta-bom" style="border-left-color:#dc2626;background:#fef2f2;color:#991b1b;">
                Uma vez que os itens forem excluídos, não haverá como recuperar essas informações pelo sistema.
            </div>
            <p style="margin-top:16px;color:#344054;">
                Você está prestes a excluir <strong id="totalExclusaoLote">0</strong> <strong id="tipoExclusaoLote">produto(s)</strong>. Deseja continuar?
            </p>
            <div style="margin-top:24px;text-align:right;display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" class="vbtn-sm btn-secondary" onclick="fecharModalExclusaoLote()">Cancelar</button>
                <button type="button" class="vbtn-sm btn-danger" onclick="submeterExclusaoLote()">Excluir definitivamente</button>
            </div>
        </div>
    </div>
</div>

<div id="modalCategoria" class="modal" style="display:none;position:fixed;z-index:1001;left:0;top:0;width:100%;height:100%;padding:16px;background:rgba(15,23,42,0.64);overflow:auto;">
    <div class="bom-modal" style="max-width:720px;">
        <div class="vend-card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <h3 id="modalCategoriaTitulo">Cadastrar Categoria</h3>
                <p style="margin-top:6px;color:#667085;">Crie o tipo de produto para organizar o cadastro e facilitar a selecao na venda.</p>
            </div>
            <button type="button" class="vbtn-sm btn-secondary" onclick="fecharModalCategoria()">Fechar</button>
        </div>
        <div class="vend-card-body">
            <form method="POST" id="formCategoria">
                <input type="hidden" name="acao" value="salvar_categoria">
                <input type="hidden" name="categoria_id" id="categoria_form_id">

                <div class="form-group">
                    <label>Nome da Categoria *</label>
                    <input type="text" name="categoria_nome" id="categoria_nome" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Descricao</label>
                    <textarea name="categoria_descricao" id="categoria_descricao" class="form-control" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="categoria_status" id="categoria_status" class="form-control">
                        <option value="ativo">Ativo</option>
                        <option value="inativo">Inativo</option>
                    </select>
                </div>

                <div class="alerta-bom">
                    Use a categoria para separar linhas como coifas, bancadas, mesas, refrigeracao, acessorios ou qualquer outra familia de produto.
                </div>

                <div style="margin-top:24px;text-align:right;display:flex;justify-content:flex-end;gap:10px;">
                    <button type="button" class="vbtn-sm btn-secondary" onclick="fecharModalCategoria()">Cancelar</button>
                    <button type="submit" class="vbtn-sm btn-primary">Salvar Categoria</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="modalInsumo" class="modal" style="display:none;position:fixed;z-index:1001;left:0;top:0;width:100%;height:100%;padding:16px;background:rgba(15,23,42,0.64);overflow:auto;">
    <div class="bom-modal" style="max-width:760px;">
        <div class="vend-card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <h3 id="modalInsumoTitulo">Novo Insumo</h3>
                <p style="margin-top:6px;color:#667085;">O custo informado aqui sera reaproveitado por todos os produtos que usam este insumo.</p>
            </div>
            <button type="button" class="vbtn-sm btn-secondary" onclick="fecharModalInsumo()">Fechar</button>
        </div>
        <div class="vend-card-body">
            <form method="POST" id="formInsumo">
                <input type="hidden" name="acao" value="salvar_insumo">
                <input type="hidden" name="insumo_id" id="insumo_id">

                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Codigo</label>
                        <input type="text" name="insumo_codigo" id="insumo_codigo" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Unidade</label>
                        <input type="text" name="insumo_unidade" id="insumo_unidade" class="form-control" value="un">
                    </div>
                </div>

                <div class="form-group">
                    <label>Nome *</label>
                    <input type="text" name="insumo_nome" id="insumo_nome" class="form-control" required>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Fornecedor</label>
                        <input type="text" name="insumo_fornecedor" id="insumo_fornecedor" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Custo Unitario</label>
                        <input type="text" name="insumo_custo_unitario" id="insumo_custo_unitario" class="form-control money" placeholder="0,00">
                    </div>
                </div>

                <div class="form-group">
                    <label>Observacoes</label>
                    <textarea name="insumo_observacoes" id="insumo_observacoes" class="form-control" rows="3"></textarea>
                </div>

                <div class="alerta-bom">
                    Ao atualizar o preco de um parafuso, chapa, tinta ou qualquer outro insumo, os produtos ligados a ele terao o custo de fabricacao recalculado automaticamente.
                </div>

                <div style="margin-top:24px;text-align:right;display:flex;justify-content:flex-end;gap:10px;">
                    <button type="button" class="vbtn-sm btn-secondary" onclick="fecharModalInsumo()">Cancelar</button>
                    <button type="submit" class="vbtn-sm btn-primary">Salvar Insumo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<datalist id="listaInsumosBom">
    <?php foreach ($insumos as $insumo): ?>
        <option
            value="<?php echo htmlspecialchars($insumo['nome']); ?>"
            data-id="<?php echo (int) $insumo['id']; ?>"
            data-codigo="<?php echo htmlspecialchars($insumo['codigo'] ?? '', ENT_QUOTES); ?>"
            data-fornecedor="<?php echo htmlspecialchars($insumo['fornecedor'] ?? '', ENT_QUOTES); ?>"
            data-unidade="<?php echo htmlspecialchars($insumo['unidade'] ?? 'un', ENT_QUOTES); ?>"
            data-custo="<?php echo htmlspecialchars((string) ($insumo['custo_unitario'] ?? '0'), ENT_QUOTES); ?>"
        ></option>
    <?php endforeach; ?>
</datalist>

<script>
function parseMoneyBR(value) {
    if (!value) return 0;
    const cleaned = String(value).replace(/[^0-9,.-]/g, '');
    if (!cleaned) return 0;
    if (cleaned.includes(',')) {
        return parseFloat(cleaned.replace(/\./g, '').replace(',', '.')) || 0;
    }
    return parseFloat(cleaned) || 0;
}

function formatMoneyBR(value) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value || 0));
}

function parseDecimalBR(value) {
    return parseMoneyBR(value);
}

function formatQuantidadeBR(value) {
    const numero = Number(value || 0);
    if (!numero) return '';

    return numero.toLocaleString('pt-BR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 4
    });
}

function preencherMoeda(input, value) {
    input.value = Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function escapeHtml(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function localizarInsumoPorNome(nome) {
    const alvo = String(nome || '').trim().toLowerCase();
    if (!alvo) return null;

    return Array.from(document.querySelectorAll('#listaInsumosBom option')).find((option) => {
        return String(option.value || '').trim().toLowerCase() === alvo;
    }) || null;
}

function sincronizarLinhaComInsumo(row) {
    if (!row) return;

    const nome = row.querySelector('input[name="componentes[nome][]"]').value || '';
    const option = localizarInsumoPorNome(nome);
    const inputId = row.querySelector('input[name="componentes[insumo_id][]"]');

    if (!option) {
        inputId.value = '';
        return;
    }

    inputId.value = option.dataset.id || '';
    row.querySelector('input[name="componentes[codigo][]"]').value = option.dataset.codigo || '';
    row.querySelector('input[name="componentes[fornecedor][]"]').value = option.dataset.fornecedor || '';
    row.querySelector('input[name="componentes[unidade][]"]').value = option.dataset.unidade || 'un';
    preencherMoeda(row.querySelector('input[name="componentes[custo_unitario][]"]'), option.dataset.custo || 0);
}

function linhaComponenteTemplate(item = {}) {
    const qtd = Number(item.quantidade || 0);
    const custo = Number(item.custo_unitario || 0);
    return `
        <div class="componentes-row">
            <input type="hidden" name="componentes[insumo_id][]" value="${item.insumo_id || ''}">
            <input type="text" name="componentes[codigo][]" class="form-control" value="${escapeHtml(item.insumo_codigo || item.codigo || '')}" placeholder="Cod.">
            <input type="text" name="componentes[nome][]" class="form-control componente-nome" list="listaInsumosBom" value="${escapeHtml(item.componente_nome || item.nome || '')}" placeholder="Componente">
            <input type="text" name="componentes[fornecedor][]" class="form-control" value="${escapeHtml(item.fornecedor || '')}" placeholder="Fornecedor">
            <input type="text" name="componentes[unidade][]" class="form-control" value="${escapeHtml(item.unidade || 'un')}" placeholder="un">
            <input type="text" inputmode="decimal" name="componentes[quantidade][]" class="form-control componente-qtd calc-field" value="${formatQuantidadeBR(item.quantidade || '')}" placeholder="0">
            <input type="text" name="componentes[custo_unitario][]" class="form-control money calc-field" value="${Number(item.custo_unitario || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}" placeholder="0,00">
            <span class="componente-total">${formatMoneyBR(qtd * custo)}</span>
            <button type="button" class="vbtn-sm btn-sm btn-danger" onclick="removerLinhaComponente(this)"><i class="fas fa-trash"></i></button>
        </div>
    `;
}

function adicionarLinhaComponente(item = {}) {
    const body = document.getElementById('componentesBody');
    body.insertAdjacentHTML('beforeend', linhaComponenteTemplate(item));
    bindMoneyMasks(body.lastElementChild);
    bindComponentRow(body.lastElementChild);
    recalcularResumo();
}

function removerLinhaComponente(button) {
    const row = button.closest('.componentes-row');
    if (row) {
        row.remove();
        recalcularResumo();
    }
}

function bindMoneyMasks(scope = document) {
    scope.querySelectorAll('.money').forEach((campo) => {
        if (campo.dataset.masked === '1') return;
        campo.dataset.masked = '1';
        campo.addEventListener('input', function() {
            let valor = this.value.replace(/\D/g, '');
            valor = (Number(valor || 0) / 100).toFixed(2) + '';
            valor = valor.replace('.', ',');
            valor = valor.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
            this.value = valor;
            recalcularResumo();
        });
    });
}

function bindComponentRow(row) {
    if (!row || row.dataset.bound === '1') return;
    row.dataset.bound = '1';

    const nomeInput = row.querySelector('input[name="componentes[nome][]"]');
    const qtyInput = row.querySelector('input[name="componentes[quantidade][]"]');
    const costInput = row.querySelector('input[name="componentes[custo_unitario][]"]');

    if (nomeInput) {
        ['change', 'blur'].forEach((eventName) => {
            nomeInput.addEventListener(eventName, function() {
                sincronizarLinhaComInsumo(row);
                recalcularResumo();
            });
        });
    }

    [qtyInput, costInput].forEach((input) => {
        if (!input) return;
        input.addEventListener('input', function() {
            recalcularResumo();
        });
    });

    if (qtyInput) {
        qtyInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9,.\-]/g, '');
        });
    }
}

function limparFormularioProduto() {
    document.getElementById('modalTitulo').textContent = 'Novo Produto';
    document.getElementById('produtoId').value = '';
    document.getElementById('formProduto').reset();
    document.getElementById('unidade_medida').value = 'un';
    document.getElementById('categoria_id').value = '';
    document.getElementById('medidas_preco').value = '';
    document.getElementById('observacoes_preco').value = '';
    document.getElementById('margem_lucro').value = '30';
    document.getElementById('componentesBody').innerHTML = '';
    adicionarLinhaComponente();
    preencherMoeda(document.getElementById('valor'), 0);
    preencherMoeda(document.getElementById('preco_embalagem'), 0);
    preencherMoeda(document.getElementById('custo_mao_obra'), 0);
    preencherMoeda(document.getElementById('custo_indireto'), 0);
    recalcularResumo();
}

function linhaProdutoLoteTemplate(item = {}) {
    return `
        <div class="componentes-row" style="grid-template-columns: 160px 1.8fr 1.2fr 140px 50px;">
            <input type="text" name="lote[codigo][]" class="form-control" value="${escapeHtml(item.codigo || '')}" placeholder="Código">
            <input type="text" name="lote[descricao][]" class="form-control" value="${escapeHtml(item.descricao || '')}" placeholder="Descrição do produto">
            <input type="text" name="lote[medidas_preco][]" class="form-control" value="${escapeHtml(item.medidas_preco || '')}" placeholder="Ex.: 800x700x900">
            <input type="text" name="lote[valor][]" class="form-control money" value="${item.valor ? Number(item.valor).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : ''}" placeholder="0,00">
            <button type="button" class="vbtn-sm btn-sm btn-danger" onclick="removerLinhaProdutoLote(this)"><i class="fas fa-trash"></i></button>
        </div>
    `;
}

function adicionarLinhaProdutoLote(item = {}) {
    const body = document.getElementById('produtosLoteBody');
    body.insertAdjacentHTML('beforeend', linhaProdutoLoteTemplate(item));
    bindMoneyMasks(body.lastElementChild);
}

function removerLinhaProdutoLote(button) {
    const row = button.closest('.componentes-row');
    if (row) {
        row.remove();
    }
}

function limparFormularioLote() {
    const form = document.getElementById('formProdutoLote');
    form.reset();
    document.getElementById('lote_nome').value = '';
    document.getElementById('lote_unidade_medida').value = 'un';
    document.getElementById('lote_status').value = 'ativo';
    preencherMoeda(document.getElementById('lote_percentual_embalagem'), 0);
    document.getElementById('produtosLoteBody').innerHTML = '';
    adicionarLinhaProdutoLote();
    adicionarLinhaProdutoLote();
    adicionarLinhaProdutoLote();
}

function abrirModalLote() {
    limparFormularioLote();
    document.getElementById('modalProdutoLote').style.display = 'block';
}

function fecharModalLote() {
    document.getElementById('modalProdutoLote').style.display = 'none';
}

function abrirModal() {
    limparFormularioProduto();
    document.getElementById('modalProduto').style.display = 'block';
}

function abrirModalImpressao() {
    document.getElementById('senha_impressao_produtos').value = '';
    document.querySelectorAll('input[name="print_cols"]').forEach((input) => {
        input.checked = true;
    });
    document.getElementById('modalImpressao').style.display = 'block';
}

function fecharModalImpressao() {
    document.getElementById('modalImpressao').style.display = 'none';
}

function fecharModal() {
    document.getElementById('modalProduto').style.display = 'none';
}

function limparFormularioCategoria() {
    document.getElementById('modalCategoriaTitulo').textContent = 'Cadastrar Categoria';
    document.getElementById('formCategoria').reset();
    document.getElementById('categoria_form_id').value = '';
    document.getElementById('categoria_status').value = 'ativo';
}

function abrirModalCategoria() {
    limparFormularioCategoria();
    document.getElementById('modalCategoria').style.display = 'block';
}

function fecharModalCategoria() {
    document.getElementById('modalCategoria').style.display = 'none';
}

function limparFormularioInsumo() {
    document.getElementById('modalInsumoTitulo').textContent = 'Novo Insumo';
    document.getElementById('formInsumo').reset();
    document.getElementById('insumo_id').value = '';
    document.getElementById('insumo_unidade').value = 'un';
    preencherMoeda(document.getElementById('insumo_custo_unitario'), 0);
}

function abrirModalInsumo() {
    limparFormularioInsumo();
    document.getElementById('modalInsumo').style.display = 'block';
}

function fecharModalInsumo() {
    document.getElementById('modalInsumo').style.display = 'none';
}

function editarInsumo(insumo) {
    limparFormularioInsumo();
    document.getElementById('modalInsumoTitulo').textContent = 'Editar Insumo';
    document.getElementById('insumo_id').value = insumo.id || '';
    document.getElementById('insumo_codigo').value = insumo.codigo || '';
    document.getElementById('insumo_nome').value = insumo.nome || '';
    document.getElementById('insumo_fornecedor').value = insumo.fornecedor || '';
    document.getElementById('insumo_unidade').value = insumo.unidade || 'un';
    document.getElementById('insumo_observacoes').value = insumo.observacoes || '';
    preencherMoeda(document.getElementById('insumo_custo_unitario'), insumo.custo_unitario || 0);
    document.getElementById('modalInsumo').style.display = 'block';
}

function editarProduto(produto) {
    limparFormularioProduto();
    document.getElementById('modalTitulo').textContent = 'Editar Produto';
    document.getElementById('produtoId').value = produto.id || '';
    document.getElementById('codigo').value = produto.codigo || '';
    document.getElementById('nome').value = produto.nome || '';
    document.getElementById('descricao').value = produto.descricao || '';
    document.getElementById('unidade_medida').value = produto.unidade_medida || 'un';
    document.getElementById('categoria_id').value = produto.categoria_id || '';
    document.getElementById('medidas_preco').value = produto.medidas_preco || '';
    document.getElementById('observacoes_preco').value = produto.observacoes_preco || '';
    preencherMoeda(document.getElementById('valor'), produto.valor || 0);
    preencherMoeda(document.getElementById('preco_embalagem'), produto.preco_embalagem || 0);
    document.getElementById('estoque').value = produto.estoque || 0;
    document.getElementById('status').value = produto.status || 'ativo';
    preencherMoeda(document.getElementById('custo_mao_obra'), produto.custo_mao_obra || 0);
    preencherMoeda(document.getElementById('custo_indireto'), produto.custo_indireto || 0);
    document.getElementById('margem_lucro').value = produto.margem_lucro || 30;

    const body = document.getElementById('componentesBody');
    body.innerHTML = '';
    if (Array.isArray(produto.componentes) && produto.componentes.length) {
        produto.componentes.forEach((item) => adicionarLinhaComponente(item));
    } else {
        adicionarLinhaComponente();
    }

    document.getElementById('modalProduto').style.display = 'block';
    recalcularResumo();
}

function recalcularResumo() {
    let custoMateriais = 0;
    document.querySelectorAll('#componentesBody .componentes-row').forEach((row) => {
        const qtd = parseDecimalBR(row.querySelector('input[name="componentes[quantidade][]"]').value || '0');
        const custo = parseMoneyBR(row.querySelector('input[name="componentes[custo_unitario][]"]').value || '0');
        const totalLinha = qtd * custo;
        const totalNode = row.querySelector('.componente-total');
        if (totalNode) {
            totalNode.textContent = formatMoneyBR(totalLinha);
        }
        custoMateriais += totalLinha;
    });

    const maoDeObra = parseMoneyBR(document.getElementById('custo_mao_obra').value || '0');
    const custoIndireto = parseMoneyBR(document.getElementById('custo_indireto').value || '0');
    const margem = parseFloat(document.getElementById('margem_lucro').value || '0') || 0;
    const precoVenda = parseMoneyBR(document.getElementById('valor').value || '0');

    const custoTotal = custoMateriais + maoDeObra + custoIndireto;
    const precoSugerido = custoTotal * (1 + (margem / 100));
    const diferenca = precoVenda - precoSugerido;

    document.getElementById('resumoMateriais').textContent = formatMoneyBR(custoMateriais);
    document.getElementById('resumoTotal').textContent = formatMoneyBR(custoTotal);
    document.getElementById('resumoSugerido').textContent = formatMoneyBR(precoSugerido);
    document.getElementById('resumoDiferenca').textContent = formatMoneyBR(diferenca);
    document.getElementById('preco_sugerido_visual').value = formatMoneyBR(precoSugerido);
    document.getElementById('custo_total_visual').value = formatMoneyBR(custoTotal);
}

function excluirProduto(id) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    form.innerHTML = `<input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="${id}">`;
    document.body.appendChild(form);
    abrirModalExclusaoLote({
        total: 1,
        form: form,
        tipo: 'produto',
    });
}

function getCheckboxesProdutos() {
    return Array.from(document.querySelectorAll('.produto-checkbox'));
}

function atualizarSelecaoProdutos() {
    const checkboxes = getCheckboxesProdutos();
    const selecionados = checkboxes.filter((checkbox) => checkbox.checked).length;
    const contador = document.getElementById('contadorSelecionados');
    const botaoExcluir = document.getElementById('btnExcluirSelecionados');
    const selecionarTodos = document.getElementById('selecionarTodosProdutos');

    if (contador) {
        contador.textContent = String(selecionados);
    }

    if (botaoExcluir) {
        botaoExcluir.disabled = selecionados === 0;
    }

    if (selecionarTodos) {
        selecionarTodos.checked = checkboxes.length > 0 && selecionados === checkboxes.length;
        selecionarTodos.indeterminate = selecionados > 0 && selecionados < checkboxes.length;
    }
}

function toggleSelecionarTodosProdutos(origem) {
    getCheckboxesProdutos().forEach((checkbox) => {
        checkbox.checked = origem.checked;
    });
    atualizarSelecaoProdutos();
}

function confirmarExclusaoLote() {
    const form = document.getElementById('formExclusaoLote');
    const selecionados = getCheckboxesProdutos().filter((checkbox) => checkbox.checked).length;
    if (!form || selecionados === 0) {
        alert('Selecione ao menos um produto para excluir.');
        return;
    }
    abrirModalExclusaoLote({
        total: selecionados,
        form: form,
        tipo: 'produto',
    });
}

function abrirModalExclusaoLote(config) {
    const modal = document.getElementById('modalExclusaoLote');
    const totalNode = document.getElementById('totalExclusaoLote');
    const tipoNode = document.getElementById('tipoExclusaoLote');
    if (!modal || !totalNode) return;

    const total = Number(config.total || 0);
    const form = config.form || null;
    const tipo = config.tipo === 'insumo' ? 'insumo(s)' : 'produto(s)';

    totalNode.textContent = String(total);
    if (tipoNode) {
        tipoNode.textContent = tipo;
    }
    modal.dataset.formTarget = form.id || '';
    if (!form.id) {
        const tempId = 'formExclusaoTemp';
        form.id = tempId;
        modal.dataset.formTarget = tempId;
    }
    modal.style.display = 'block';
}

function fecharModalExclusaoLote() {
    const modal = document.getElementById('modalExclusaoLote');
    if (!modal) return;
    modal.style.display = 'none';
}

function submeterExclusaoLote() {
    const modal = document.getElementById('modalExclusaoLote');
    if (!modal) return;
    const formId = modal.dataset.formTarget || '';
    const form = formId ? document.getElementById(formId) : null;
    if (!form) return;
    form.submit();
}

function getCheckboxesInsumos() {
    return Array.from(document.querySelectorAll('.insumo-checkbox'));
}

function atualizarSelecaoInsumos() {
    const checkboxes = getCheckboxesInsumos();
    const selecionados = checkboxes.filter((checkbox) => checkbox.checked).length;
    const contador = document.getElementById('contadorSelecionadosInsumos');
    const botaoExcluir = document.getElementById('btnExcluirSelecionadosInsumos');
    const selecionarTodos = document.getElementById('selecionarTodosInsumos');

    if (contador) {
        contador.textContent = String(selecionados);
    }

    if (botaoExcluir) {
        botaoExcluir.disabled = selecionados === 0;
    }

    if (selecionarTodos) {
        selecionarTodos.checked = checkboxes.length > 0 && selecionados === checkboxes.length;
        selecionarTodos.indeterminate = selecionados > 0 && selecionados < checkboxes.length;
    }
}

function toggleSelecionarTodosInsumos(origem) {
    getCheckboxesInsumos().forEach((checkbox) => {
        checkbox.checked = origem.checked;
    });
    atualizarSelecaoInsumos();
}

function confirmarExclusaoLoteInsumos() {
    const form = document.getElementById('formExclusaoLoteInsumos');
    const selecionados = getCheckboxesInsumos().filter((checkbox) => checkbox.checked).length;
    if (!form || selecionados === 0) {
        alert('Selecione ao menos um insumo para excluir.');
        return;
    }
    abrirModalExclusaoLote({
        total: selecionados,
        form: form,
        tipo: 'insumo',
    });
}

function excluirInsumo(id) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    form.innerHTML = `<input type="hidden" name="acao" value="excluir_insumo"><input type="hidden" name="id" value="${id}">`;
    document.body.appendChild(form);
    abrirModalExclusaoLote({
        total: 1,
        form: form,
        tipo: 'insumo',
    });
}

function imprimirTabelaProdutos() {
    abrirModalImpressao();
}

function confirmarImpressaoProdutos() {
    const senha = document.getElementById('senha_impressao_produtos').value;
    if (senha !== '1234') {
        alert('Senha inválida.');
        return;
    }

    const colunas = Array.from(document.querySelectorAll('input[name="print_cols"]:checked')).map((input) => input.value);
    if (!colunas.length) {
        alert('Selecione pelo menos uma opção para imprimir.');
        return;
    }

    const params = new URLSearchParams(window.location.search);
    params.set('senha', senha);
    params.set('cols', colunas.join(','));
    fecharModalImpressao();
    window.open('imprimir_tabela_produtos.php?' + params.toString(), '_blank');
}

document.addEventListener('DOMContentLoaded', function() {
    bindMoneyMasks(document);
    document.addEventListener('input', function(event) {
        if (event.target.classList.contains('calc-field')) {
            recalcularResumo();
        }
    });
    adicionarLinhaComponente();
    recalcularResumo();
    atualizarSelecaoProdutos();
    atualizarSelecaoInsumos();
});

window.addEventListener('click', function(event) {
    if (event.target === document.getElementById('modalImpressao')) {
        fecharModalImpressao();
    }
    if (event.target === document.getElementById('modalProduto')) {
        fecharModal();
    }
    if (event.target === document.getElementById('modalProdutoLote')) {
        fecharModalLote();
    }
    if (event.target === document.getElementById('modalCategoria')) {
        fecharModalCategoria();
    }
    if (event.target === document.getElementById('modalInsumo')) {
        fecharModalInsumo();
    }
    if (event.target === document.getElementById('modalExclusaoLote')) {
        fecharModalExclusaoLote();
    }
});
</script>

<?php include '../../includes/footer_vendedor.php'; ?>

