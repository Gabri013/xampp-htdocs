<?php
require_once '../../config/config.php';
require_once '../../includes/engenharia.php';

requirePermission(['master', 'gerente', 'producao', 'producao_geral']);

$page_title = 'Engenharia de Produto';
$db = getDB();
ensureEngenhariaSchema($db);

$etapasPadrao = ['Corte', 'Dobra', 'Solda', 'Acabamento', 'Montagem', 'Finalizacao'];

// Variáveis para o modal de importação CSV
$importErros = [];
$importSucessos = [];
$showImportModal = false;

// Processar importação CSV quando submetida do modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'importar_csv') {
    $osId = (int) ($_POST['os_id'] ?? 0);
    
    if ($osId <= 0) {
        $importErros[] = 'Selecione uma ordem de produção válida.';
    }
    
    // Verificar se a OS existe
    if ($osId > 0) {
        $stmt = $db->prepare("SELECT id, numero FROM ordens_servico WHERE id = ?");
        $stmt->execute([$osId]);
        $os = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$os) {
            $importErros[] = 'Ordem de produção não encontrada.';
        }
    }
    
    // Verificar se arquivo foi enviado
    if (!isset($_FILES['arquivo_csv']) || $_FILES['arquivo_csv']['error'] !== UPLOAD_ERR_OK) {
        $importErros[] = 'Erro ao fazer upload do arquivo. Selecione um arquivo CSV válido.';
    } else {
        $arquivo = $_FILES['arquivo_csv'];
        $nomeArquivo = $arquivo['name'];
        $extensao = strtolower(pathinfo($nomeArquivo, PATHINFO_EXTENSION));
        
        if ($extensao !== 'csv') {
            $importErros[] = 'O arquivo deve ter extensão .csv';
        }
        
        if (empty($importErros)) {
            // Detectar encoding do arquivo
            $conteudo = file_get_contents($arquivo['tmp_name']);
            
            // Tentar detectar e converter encoding
            $encoding = mb_detect_encoding($conteudo, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
            
            if ($encoding && $encoding !== 'UTF-8') {
                $conteudo = mb_convert_encoding($conteudo, 'UTF-8', $encoding);
            }
            
            // Limpar conteúdo (remover BOM se existir)
            $conteudo = str_replace("\xEF\xBB\xBF", '', $conteudo);
            
            // Processar linhas do CSV
            $linhas = explode("\n", $conteudo);
            
            $itensImportados = 0;
            $itensProcessos = [];
            
            foreach ($linhas as $indice => $linha) {
                $linha = trim($linha);
                if (empty($linha)) continue;
                
                // Pular linhas de cabeçalho
                if (strpos($linha, ';Nº') !== false || strpos($linha, ';N�') !== false) continue;
                if (strpos($linha, ';Nº ORDEM:') !== false || strpos($linha, ';N� ORDEM:') !== false) continue;
                if (strpos($linha, ';EQUIP.') !== false || strpos($linha, ';EQUIP') !== false) continue;
                
                $dados = str_getcsv($linha, ';');
                
                if (count($dados) < 8) continue;
                
                $numeroItem = isset($dados[0]) ? preg_replace('/[^0-9]/', '', $dados[0]) : '';
                $quantidade = isset($dados[1]) ? (float) str_replace(',', '.', str_replace('.', '', $dados[1])) : 1;
                $dimensaoX = isset($dados[2]) ? (float) str_replace(',', '.', str_replace('.', '', $dados[2])) : null;
                $dimensaoY = isset($dados[3]) ? (float) str_replace(',', '.', str_replace('.', '', $dados[3])) : null;
                $material = isset($dados[4]) ? trim($dados[4]) : '';
                $descricao = isset($dados[5]) ? trim($dados[5]) : '';
                $codigo = isset($dados[6]) ? trim($dados[6]) : '';
                $processo = isset($dados[7]) ? trim($dados[7]) : '';
                $quantidadeTotal = isset($dados[8]) ? (float) str_replace(',', '.', str_replace('.', '', $dados[8])) : null;
                
                if (empty($numeroItem) && $indice > 3) continue;
                
                $numeroItem = (int) $numeroItem;
                if ($numeroItem <= 0) continue;
                
                // Normalizar processo
                $processoNormalizado = strtoupper(trim($processo));
                $mapaProcessos = [
                    'ALMOXARIFADO' => 'ALMOXARIFADO', 'ALMOXARIFAGEM' => 'ALMOXARIFADO',
                    'CORTE' => 'CORTE', 'LASER' => 'LASER', 'GUILHOTINA' => 'GUILHOTINA',
                    'DOBRA' => 'DOBRA', 'DOBRAGEM' => 'DOBRA',
                    'SOLDA' => 'SOLDA', 'SOLDAGEM' => 'SOLDA',
                    'ACABAMENTO' => 'ACABAMENTO', 'MONTAGEM' => 'MONTAGEM'
                ];
                $processoNormalizado = $mapaProcessos[$processoNormalizado] ?? $processoNormalizado;
                
                try {
                    $stmt = $db->prepare("
                        INSERT INTO os_materiais (
                            os_id, numero_item, quantidade, dimensao_x, dimensao_y,
                            material, descricao, codigo, processo, quantidade_total,
                            usuario_importacao_id
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $osId, $numeroItem, $quantidade > 0 ? $quantidade : 1,
                        $dimensaoX, $dimensaoY, $material ?: null, $descricao ?: null,
                        $codigo ?: null, $processoNormalizado, $quantidadeTotal,
                        $_SESSION['usuario_id']
                    ]);
                    
                    $itensImportados++;
                    
                    if ($processoNormalizado) {
                        if (!isset($itensProcessos[$processoNormalizado])) {
                            $itensProcessos[$processoNormalizado] = 0;
                        }
                        $itensProcessos[$processoNormalizado]++;
                    }
                    
                } catch (Exception $e) {
                    $importErros[] = "Erro ao importar item $numeroItem: " . $e->getMessage();
                }
            }
            
            if ($itensImportados > 0) {
                $importSucessos[] = "$itensImportados itens importados com sucesso!";
                
                if (!empty($itensProcessos)) {
                    $resumoProcessos = [];
                    foreach ($itensProcessos as $processo => $quantidade) {
                        $resumoProcessos[] = "$processo: $quantidade";
                    }
                    $importSucessos[] = "Processos: " . implode(', ', $resumoProcessos);
                }
            } else {
                $importErros[] = 'Nenhum item válido encontrado no arquivo CSV.';
            }
        }
    }
    
    // Mostrar o modal após submit
    $showImportModal = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $produtoId = (int) ($_POST['produto_id'] ?? 0);

    try {
        if ($produtoId <= 0) {
            throw new RuntimeException('Selecione um produto para editar a engenharia.');
        }

        if ($acao === 'salvar_estrutura') {
            $estrutura = getOrCreateEstruturaProduto($db, $produtoId);
            $versao = sanitize($_POST['versao'] ?? 'v1');
            $observacoes = trim($_POST['observacoes'] ?? '');

            $stmt = $db->prepare("
                UPDATE estrutura_produto
                SET versao = ?, observacoes = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $versao !== '' ? $versao : 'v1',
                $observacoes !== '' ? $observacoes : null,
                $estrutura['id'],
            ]);

            setSuccess('Estrutura técnica atualizada.');
        } elseif ($acao === 'adicionar_componente') {
            $estrutura = getOrCreateEstruturaProduto($db, $produtoId);
            $componente = sanitize($_POST['componente_nome'] ?? '');
            $quantidade = (float) ($_POST['quantidade'] ?? 0);
            $unidade = sanitize($_POST['unidade'] ?? 'un');

            if ($componente === '' || $quantidade <= 0) {
                throw new RuntimeException('Informe componente e quantidade válidos.');
            }

            $stmt = $db->prepare("
                INSERT INTO componentes_produto (estrutura_id, componente_nome, quantidade, unidade)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$estrutura['id'], $componente, $quantidade, $unidade !== '' ? $unidade : 'un']);

            setSuccess('Componente adicionado à estrutura.');
        } elseif ($acao === 'excluir_componente') {
            $componenteId = (int) ($_POST['componente_id'] ?? 0);
            $stmt = $db->prepare("
                DELETE cp
                FROM componentes_produto cp
                INNER JOIN estrutura_produto ep ON ep.id = cp.estrutura_id
                WHERE cp.id = ? AND ep.produto_id = ?
            ");
            $stmt->execute([$componenteId, $produtoId]);
            setSuccess('Componente removido.');
        } elseif ($acao === 'adicionar_tempo') {
            $etapa = sanitize($_POST['etapa'] ?? '');
            $minutos = (int) ($_POST['minutos_estimados'] ?? 0);

            if ($etapa === '' || $minutos <= 0) {
                throw new RuntimeException('Informe etapa e tempo válidos.');
            }

            $stmt = $db->prepare("
                INSERT INTO tempo_producao (produto_id, etapa, minutos_estimados)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$produtoId, $etapa, $minutos]);

            setSuccess('Processo produtivo adicionado.');
        } elseif ($acao === 'excluir_tempo') {
            $tempoId = (int) ($_POST['tempo_id'] ?? 0);
            $stmt = $db->prepare("
                DELETE FROM tempo_producao
                WHERE id = ? AND produto_id = ?
            ");
            $stmt->execute([$tempoId, $produtoId]);
            setSuccess('Processo removido.');
        }

        header('Location: index.php?produto_id=' . $produtoId);
        exit;
    } catch (Exception $e) {
        setError('Erro na engenharia: ' . $e->getMessage());
    }
}

$busca = trim($_GET['busca'] ?? '');
$produtoId = (int) ($_GET['produto_id'] ?? 0);

if ($busca !== '') {
    $stmtProdutos = $db->prepare("
        SELECT id, codigo, nome, status
        FROM produtos
        WHERE nome LIKE ? OR codigo LIKE ?
        ORDER BY nome
    ");
    $stmtProdutos->execute(["%{$busca}%", "%{$busca}%"]);
} else {
    $stmtProdutos = $db->query("
        SELECT id, codigo, nome, status
        FROM produtos
        ORDER BY nome
    ");
}
$produtos = $stmtProdutos->fetchAll(PDO::FETCH_ASSOC);

if ($produtoId <= 0 && !empty($produtos)) {
    $produtoId = (int) $produtos[0]['id'];
}

$produtoAtual = null;
$estrutura = null;
$componentes = [];
$tempos = [];
$resumo = [
    'total_componentes' => 0,
    'total_quantidade_componentes' => 0,
    'total_etapas' => 0,
    'total_minutos' => 0,
];

if ($produtoId > 0) {
    $stmtProduto = $db->prepare("
        SELECT id, codigo, nome, descricao, valor, status
        FROM produtos
        WHERE id = ?
        LIMIT 1
    ");
    $stmtProduto->execute([$produtoId]);
    $produtoAtual = $stmtProduto->fetch(PDO::FETCH_ASSOC);

    if ($produtoAtual) {
        $resumo = getResumoEngenhariaProduto($db, $produtoId);
        $estrutura = $resumo['estrutura'];

        $stmtComponentes = $db->prepare("
            SELECT *
            FROM componentes_produto
            WHERE estrutura_id = ?
            ORDER BY id ASC
        ");
        $stmtComponentes->execute([$estrutura['id']]);
        $componentes = $stmtComponentes->fetchAll(PDO::FETCH_ASSOC);

        $stmtTempos = $db->prepare("
            SELECT *
            FROM tempo_producao
            WHERE produto_id = ?
            ORDER BY id ASC
        ");
        $stmtTempos->execute([$produtoId]);
        $tempos = $stmtTempos->fetchAll(PDO::FETCH_ASSOC);
    }
}

include '../../includes/header_vendedor.php';
?>

<style>
.engenharia-layout {
    display: grid;
    grid-template-columns: 320px minmax(0, 1fr);
    gap: 18px;
}

.engenharia-sidebar .card,
.engenharia-main .card {
    margin-bottom: 18px;
}

.engenharia-sidebar .vend-card-body,
.engenharia-main .vend-card-body,
.engenharia-main .vend-card-head {
    padding: 18px 20px;
}

.engenharia-search {
    display: flex;
    gap: 10px;
    margin-bottom: 14px;
}

.produto-lista {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-height: 560px;
    overflow: auto;
}

.produto-item {
    display: block;
    padding: 12px 14px;
    border: 1px solid #e3e8ef;
    border-radius: 10px;
    text-decoration: none;
    color: #243447;
    background: #fff;
}

.produto-item:hover,
.produto-item.ativo {
    border-color: #1f7a31;
    background: #f4fbf4;
}

.produto-item strong,
.produto-item span {
    display: block;
}

.produto-item strong {
    font-size: 14px;
}

.produto-item span {
    margin-top: 3px;
    font-size: 12px;
    color: #667085;
}

.engenharia-resumo {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}

.resumo-card {
    padding: 14px 16px;
    border-radius: 12px;
    background: linear-gradient(180deg, #ffffff 0%, #f7fafc 100%);
    border: 1px solid #e5e7eb;
}

.resumo-card .label {
    display: block;
    font-size: 12px;
    color: #64748b;
    margin-bottom: 6px;
}

.resumo-card .valor {
    display: block;
    font-size: 24px;
    font-weight: 700;
    color: #152536;
}

.engenharia-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.engenharia-form-grid {
    display: grid;
    grid-template-columns: 180px minmax(0, 1fr);
    gap: 12px;
    align-items: end;
}

.engenharia-inline-form {
    display: grid;
    grid-template-columns: minmax(0, 1.6fr) 120px 90px 130px;
    gap: 10px;
    margin-bottom: 16px;
    align-items: end;
}

.tempo-form {
    grid-template-columns: minmax(0, 1.4fr) 140px 130px;
}

.engenharia-table {
    width: 100%;
    border-collapse: collapse;
}

.engenharia-table th,
.engenharia-table td {
    padding: 10px 8px;
    border-bottom: 1px solid #edf2f7;
    text-align: left;
    vertical-align: middle;
}

.engenharia-table th {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #6b7280;
}

.engenharia-table td {
    font-size: 14px;
}

.planejamento-box {
    padding: 16px;
    border: 1px dashed #cbd5e1;
    border-radius: 12px;
    background: #f8fafc;
}

.etapas-lista {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
}

.etapas-lista span {
    padding: 6px 10px;
    border-radius: 999px;
    background: #eaf3ea;
    color: #1f7a31;
    font-size: 12px;
    font-weight: 600;
}

@media (max-width: 1200px) {
    .engenharia-layout,
    .engenharia-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 860px) {
    .engenharia-resumo,
    .engenharia-form-grid,
    .engenharia-inline-form,
    .tempo-form {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="vend-layout">
    <aside class="vend-sidebar">
        <div class="vend-sidebar-logo">
            <div class="vend-logo-icon"><i class="fas fa-fire"></i></div>
            <div><div class="vend-logo-text">Cozinca Inox</div><div class="vend-logo-sub">Engenharia</div></div>
        </div>
        <div class="vend-nav-group">
            <span class="vend-nav-label">Principal</span>
            <a href="../vendas/dashboard_vendedor.php" class="vend-nav-item"><i class="fas fa-th-large"></i> Dashboard</a>
            <a href="../vendas/index.php" class="vend-nav-item"><i class="fas fa-shopping-cart"></i> Vendas</a>
            <a href="../orcamentos/index.php" class="vend-nav-item"><i class="fas fa-file-invoice"></i> Orçamentos</a>
            <a href="../os/vendedor.php" class="vend-nav-item"><i class="fas fa-clipboard-list"></i> O.S.</a>
        </div>
        <hr class="vend-nav-divider">
        <div class="vend-nav-group">
            <span class="vend-nav-label">Cadastros</span>
            <a href="clientes.php" class="vend-nav-item"><i class="fas fa-users"></i> Clientes</a>
            <a href="../cadastros/produtos.php" class="vend-nav-item"><i class="fas fa-box"></i> Produtos</a>
        </div>
        <hr class="vend-nav-divider">
        <div class="vend-nav-group">
            <span class="vend-nav-label">Financeiro</span>
            <a href="../financeiro/faturamento.php" class="vend-nav-item"><i class="fas fa-file-invoice-dollar"></i> Faturamento</a>
            <a href="../relatorios/index.php" class="vend-nav-item"><i class="fas fa-chart-bar"></i> Relatórios</a>
        </div>
    </aside>
    <div class="vend-main">
        <div class="vend-page-head"><h1 class="vend-page-title">Engenharia de Produto</h1></div>
        <div class="engenharia-layout">
            <aside class="engenharia-sidebar">
        <div class="vend-card">
            <div class="vend-card-head" style="display: flex; justify-content: space-between; align-items: center;">
                <h3>Produtos</h3>
                <button type="button" class="vbtn-sm" style="background: #28a745; color: white; padding: 5px 10px; font-size: 12px; border-radius: 4px; border: none; cursor: pointer;" data-toggle="modal" data-target="#modalImportarCsv">
                    <i class="fas fa-upload"></i> Importar CSV
                </button>
            </div>
            <div class="vend-card-body">
                <form method="GET" class="engenharia-search">
                    <input type="text" name="busca" class="form-control" placeholder="Buscar produto..." value="<?php echo htmlspecialchars($busca); ?>">
                    <button type="submit" class="vbtn-sm">Buscar</button>
                </form>

                <div class="produto-lista">
                    <?php if (empty($produtos)): ?>
                        <p class="text-center">Nenhum produto encontrado.</p>
                    <?php else: ?>
                        <?php foreach ($produtos as $produto): ?>
                            <a class="produto-item <?php echo (int) $produto['id'] === $produtoId ? 'ativo' : ''; ?>" href="index.php?produto_id=<?php echo (int) $produto['id']; ?><?php echo $busca !== '' ? '&busca=' . urlencode($busca) : ''; ?>">
                                <strong><?php echo htmlspecialchars($produto['nome']); ?></strong>
                                <span><?php echo htmlspecialchars($produto['codigo']); ?> | <?php echo ucfirst($produto['status']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </aside>

    <section class="engenharia-main">
        <?php if (!$produtoAtual): ?>
            <div class="vend-card">
                <div class="vend-card-body">
                    <p>Selecione um produto para montar a engenharia.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="vend-card">
                <div class="vend-card-head">
                    <div>
                        <h3><?php echo htmlspecialchars($produtoAtual['nome']); ?></h3>
                        <p style="margin-top:6px; color:#64748b;">
                            Código: <?php echo htmlspecialchars($produtoAtual['codigo']); ?>
                            <?php if (!empty($produtoAtual['descricao'])): ?>
                                | <?php echo htmlspecialchars($produtoAtual['descricao']); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <div class="vend-card-body">
                    <div class="engenharia-resumo">
                        <div class="resumo-card">
                            <span class="label">Componentes na BOM</span>
                            <span class="valor"><?php echo $resumo['total_componentes']; ?></span>
                        </div>
                        <div class="resumo-card">
                            <span class="label">Qtd. total de insumos</span>
                            <span class="valor"><?php echo number_format($resumo['total_quantidade_componentes'], 0, ',', '.'); ?></span>
                        </div>
                        <div class="resumo-card">
                            <span class="label">Etapas produtivas</span>
                            <span class="valor"><?php echo $resumo['total_etapas']; ?></span>
                        </div>
                        <div class="resumo-card">
                            <span class="label">Tempo estimado</span>
                            <span class="valor"><?php echo $resumo['total_minutos']; ?> min</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="vend-card">
                <div class="vend-card-head">
                    <h3>Estrutura Técnica</h3>
                </div>
                <div class="vend-card-body">
                    <form method="POST">
                        <input type="hidden" name="acao" value="salvar_estrutura">
                        <input type="hidden" name="produto_id" value="<?php echo (int) $produtoAtual['id']; ?>">

                        <div class="engenharia-form-grid">
                            <div class="form-group">
                                <label>Versão da engenharia</label>
                                <input type="text" name="versao" class="form-control" value="<?php echo htmlspecialchars($estrutura['versao'] ?? 'v1'); ?>">
                            </div>
                            <div class="form-group">
                                <label>Observações técnicas</label>
                                <textarea name="observacoes" class="form-control" rows="3" placeholder="Ex.: montagem soldada, acabamento escovado, atenção ao nivelamento."><?php echo htmlspecialchars($estrutura['observacoes'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div style="margin-top: 14px; text-align: right;">
                            <button type="submit" class="vbtn-sm">Salvar Estrutura</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="engenharia-grid">
                <div class="vend-card">
                    <div class="vend-card-head">
                        <h3>BOM / Componentes</h3>
                    </div>
                    <div class="vend-card-body">
                        <form method="POST" class="engenharia-inline-form">
                            <input type="hidden" name="acao" value="adicionar_componente">
                            <input type="hidden" name="produto_id" value="<?php echo (int) $produtoAtual['id']; ?>">

                            <div class="form-group">
                                <label>Componente</label>
                                <input type="text" name="componente_nome" class="form-control" placeholder="Ex.: Chapa inox 0.8" required>
                            </div>
                            <div class="form-group">
                                <label>Quantidade</label>
                                <input type="number" step="0.01" min="0.01" name="quantidade" class="form-control" value="1" required>
                            </div>
                            <div class="form-group">
                                <label>Unidade</label>
                                <input type="text" name="unidade" class="form-control" value="un" required>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="vbtn-sm" style="width:100%;">Adicionar</button>
                            </div>
                        </form>

                        <table class="engenharia-table">
                            <thead>
                                <tr>
                                    <th>Componente</th>
                                    <th>Qtd.</th>
                                    <th>Un.</th>
                                    <th style="width:70px;">Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($componentes)): ?>
                                    <tr><td colspan="4" class="text-center">Nenhum componente cadastrado.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($componentes as $componente): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($componente['componente_nome']); ?></td>
                                            <td><?php echo number_format((float) $componente['quantidade'], 2, ',', '.'); ?></td>
                                            <td><?php echo htmlspecialchars($componente['unidade']); ?></td>
                                            <td>
                                                <form method="POST" onsubmit="return confirm('Remover este componente?');">
                                                    <input type="hidden" name="acao" value="excluir_componente">
                                                    <input type="hidden" name="produto_id" value="<?php echo (int) $produtoAtual['id']; ?>">
                                                    <input type="hidden" name="componente_id" value="<?php echo (int) $componente['id']; ?>">
                                                    <button type="submit" class="vbtn-sm btn-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="vend-card">
                    <div class="vend-card-head">
                        <h3>Processos e Tempo de Produção</h3>
                    </div>
                    <div class="vend-card-body">
                        <form method="POST" class="engenharia-inline-form tempo-form">
                            <input type="hidden" name="acao" value="adicionar_tempo">
                            <input type="hidden" name="produto_id" value="<?php echo (int) $produtoAtual['id']; ?>">

                            <div class="form-group">
                                <label>Etapa</label>
                                <input list="etapasPadrao" name="etapa" class="form-control" placeholder="Ex.: Solda" required>
                                <datalist id="etapasPadrao">
                                    <?php foreach ($etapasPadrao as $etapa): ?>
                                        <option value="<?php echo htmlspecialchars($etapa); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            <div class="form-group">
                                <label>Minutos estimados</label>
                                <input type="number" min="1" name="minutos_estimados" class="form-control" value="30" required>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="vbtn-sm" style="width:100%;">Adicionar</button>
                            </div>
                        </form>

                        <table class="engenharia-table">
                            <thead>
                                <tr>
                                    <th>Etapa</th>
                                    <th>Tempo</th>
                                    <th style="width:70px;">Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tempos)): ?>
                                    <tr><td colspan="3" class="text-center">Nenhuma etapa cadastrada.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($tempos as $tempo): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($tempo['etapa']); ?></td>
                                            <td><?php echo (int) $tempo['minutos_estimados']; ?> min</td>
                                            <td>
                                                <form method="POST" onsubmit="return confirm('Remover esta etapa?');">
                                                    <input type="hidden" name="acao" value="excluir_tempo">
                                                    <input type="hidden" name="produto_id" value="<?php echo (int) $produtoAtual['id']; ?>">
                                                    <input type="hidden" name="tempo_id" value="<?php echo (int) $tempo['id']; ?>">
                                                    <button type="submit" class="vbtn-sm btn-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="vend-card">
                <div class="vend-card-head">
                    <h3>Planejamento Automático</h3>
                </div>
                <div class="vend-card-body">
                    <div class="planejamento-box">
                        <p>
                            Esta engenharia já deixa o produto pronto para planejamento automático de produção:
                            a BOM define os insumos e os processos definem a rota e o tempo estimado.
                        </p>
                        <div class="etapas-lista">
                            <?php if (empty($tempos)): ?>
                                <span>Cadastre etapas para gerar a rota produtiva</span>
                            <?php else: ?>
                                <?php foreach ($tempos as $tempo): ?>
                                    <span><?php echo htmlspecialchars($tempo['etapa']); ?> · <?php echo (int) $tempo['minutos_estimados']; ?> min</span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </section>
</div>

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- jQuery e Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Modal de Importação CSV -->
<div class="modal fade" id="modalImportarCsv" tabindex="-1" role="dialog" aria-labelledby="modalImportarCsvLabel" aria-hidden="true" <?= $showImportModal ? 'style="display: block;"' : '' ?>>
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalImportarCsvLabel">Importar Lista de Materiais CSV</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            
            <form method="POST" enctype="multipart/form-data" id="form-import-csv">
                <input type="hidden" name="acao" value="importar_csv">
                
                <div class="modal-body">
                    <?php if (!empty($importErros)): ?>
                        <div class="alert alert-danger">
                            <strong>Erros:</strong>
                            <ul class="mb-0">
                                <?php foreach ($importErros as $erro): ?>
                                    <li><?= htmlspecialchars($erro) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($importSucessos)): ?>
                        <div class="alert alert-success">
                            <?php foreach ($importSucessos as $msg): ?>
                                <div><?= htmlspecialchars($msg) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php
                    // Buscar ordens de produção para o select
                    $stmtOS = $db->query("SELECT id, numero, status FROM ordens_servico ORDER BY id DESC LIMIT 50");
                    $ordensProducao = $stmtOS->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    
                    <div class="form-group">
                        <label for="modal_os_id">Ordem de Produção:</label>
                        <select name="os_id" id="modal_os_id" class="form-control" required>
                            <option value="">Selecione uma OS...</option>
                            <?php foreach ($ordensProducao as $os): ?>
                                <option value="<?= $os['id'] ?>">
                                    <?= htmlspecialchars($os['numero']) ?> (<?= htmlspecialchars($os['status']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="modal_arquivo_csv">Arquivo CSV:</label>
                        <input type="file" name="arquivo_csv" id="modal_arquivo_csv" accept=".csv" class="form-control" required>
                    </div>
                    
                    <div class="alert alert-info mb-0">
                        <strong>Formato esperado:</strong>
                        <ul class="mt-2 mb-0">
                            <li>Arquivo com extensão .csv</li>
                            <li>Separador: ponto e vírgula (;)</li>
                            <li>Colunas: Nº, QTD, X, Y, MATERIAL, DESCRIÇÃO, CÓDIGO, PROCESSO</li>
                        </ul>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                    <button type="submit" class="vbtn-sm">Importar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($showImportModal): ?>
<script>
$(document).ready(function() {
    $('#modalImportarCsv').modal('show');
});
</script>
<?php endif; ?>
    </div>
</div>
</div>

<?php include '../../includes/footer_vendedor.php'; ?>


