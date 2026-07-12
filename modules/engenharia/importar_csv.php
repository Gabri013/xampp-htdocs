<?php
/**
 * Script de importação de CSV para lista de materiais e processos de produção
 * 
 * O CSV deve conter colunas:
 * - Nº (número do item)
 * - QTD (quantidade)
 * - X, Y (dimensões)
 * - MATERIAL
 * - DESCRIÇÃO
 * - CÓDIGO
 * - PROCESSO (ALMOXARIFADO, CORTE, LASER, GUILHOTINA, DOBRA, SOLDA, ACABAMENTO, MONTAGEM)
 * 
 * Este arquivo pode ser:
 * 1. Acessado diretamente (página completa) - para compatibilidade retroativa
 * 2. Incluído como modal no index.php
 */

require_once '../../config/config.php';
require_once '../../includes/engenharia.php';
requirePermission(['master', 'gerente', 'producao', 'projetista', 'engenharia']);

$db = getDB();
ensureEngenhariaSchema($db);

// Garantir que a tabela de materiais da OS existe
$db->exec("
    CREATE TABLE IF NOT EXISTS os_materiais (
        id INT AUTO_INCREMENT PRIMARY KEY,
        os_id INT NOT NULL,
        numero_item INT NOT NULL,
        quantidade DECIMAL(12,4) NOT NULL DEFAULT 1,
        dimensao_x DECIMAL(12,2) NULL,
        dimensao_y DECIMAL(12,2) NULL,
        material VARCHAR(200) NULL,
        descricao TEXT NULL,
        codigo VARCHAR(50) NULL,
        processo VARCHAR(50) NULL,
        quantidade_total DECIMAL(12,4) NULL,
        usuario_importacao_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE,
        FOREIGN KEY (usuario_importacao_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
        INDEX idx_os (os_id),
        INDEX idx_processo (processo)
    ) ENGINE=InnoDB
");

$erros = [];
$sucessos = [];
$mensagem = '';

// Processar o upload do CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'importar_csv') {
    
    $osId = (int) ($_POST['os_id'] ?? 0);
    
    if ($osId <= 0) {
        $erros[] = 'Selecione uma ordem de produção válida.';
    }
    
    // Verificar se a OS existe
    if ($osId > 0) {
        $stmt = $db->prepare("SELECT id, numero FROM ordens_servico WHERE id = ?");
        $stmt->execute([$osId]);
        $os = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$os) {
            $erros[] = 'Ordem de produção não encontrada.';
        }
    }
    
    // Verificar se arquivo foi enviado
    if (!isset($_FILES['arquivo_csv']) || $_FILES['arquivo_csv']['error'] !== UPLOAD_ERR_OK) {
        $erros[] = 'Erro ao fazer upload do arquivo. Selecione um arquivo CSV válido.';
    } else {
        $arquivo = $_FILES['arquivo_csv'];
        $nomeArquivo = $arquivo['name'];
        $extensao = strtolower(pathinfo($nomeArquivo, PATHINFO_EXTENSION));
        
        if ($extensao !== 'csv') {
            $erros[] = 'O arquivo deve ter extensão .csv';
        }
        
        if (empty($erros)) {
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
                // Pular linhas vazias
                $linha = trim($linha);
                if (empty($linha)) {
                    continue;
                }
                
                // Pular linhas de cabeçalho (começam com ;)
                if (strpos($linha, ';Nº') !== false || strpos($linha, ';N�') !== false) {
                    continue;
                }
                
                // Pular linhas de informação (linha 1 e 2 do formato)
                if (strpos($linha, ';Nº ORDEM:') !== false || strpos($linha, ';N� ORDEM:') !== false) {
                    continue;
                }
                if (strpos($linha, ';EQUIP.') !== false || strpos($linha, ';EQUIP') !== false) {
                    continue;
                }
                
                // Parse da linha CSV (separador ;)
                $dados = parseCsvLinha($linha);
                
                // Verificar se tem dados suficientes
                if (count($dados) < 8) {
                    continue;
                }
                
                // Extrair campos (índices baseados no formato do CSV)
                $numeroItem = isset($dados[0]) ? preg_replace('/[^0-9]/', '', $dados[0]) : '';
                $quantidade = isset($dados[1]) ? (float) str_replace(',', '.', str_replace('.', '', $dados[1])) : 1;
                $dimensaoX = isset($dados[2]) ? (float) str_replace(',', '.', str_replace('.', '', $dados[2])) : null;
                $dimensaoY = isset($dados[3]) ? (float) str_replace(',', '.', str_replace('.', '', $dados[3])) : null;
                $material = isset($dados[4]) ? trim($dados[4]) : '';
                $descricao = isset($dados[5]) ? trim($dados[5]) : '';
                $codigo = isset($dados[6]) ? trim($dados[6]) : '';
                $processo = isset($dados[7]) ? trim($dados[7]) : '';
                $quantidadeTotal = isset($dados[8]) ? (float) str_replace(',', '.', str_replace('.', '', $dados[8])) : null;
                
                // Validar dados mínimos
                if (empty($numeroItem) && $indice > 3) {
                    continue;
                }
                
                // Normalizar o número do item
                $numeroItem = (int) $numeroItem;
                if ($numeroItem <= 0) {
                    continue;
                }
                
                // Normalizar processo
                $processoNormalizado = normalizarProcesso($processo);
                
                // Inserir no banco de dados
                try {
                    $stmt = $db->prepare("
                        INSERT INTO os_materiais (
                            os_id, numero_item, quantidade, dimensao_x, dimensao_y,
                            material, descricao, codigo, processo, quantidade_total,
                            usuario_importacao_id
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                        )
                    ");
                    
                    $stmt->execute([
                        $osId,
                        $numeroItem,
                        $quantidade > 0 ? $quantidade : 1,
                        $dimensaoX,
                        $dimensaoY,
                        $material ?: null,
                        $descricao ?: null,
                        $codigo ?: null,
                        $processoNormalizado,
                        $quantidadeTotal,
                        $_SESSION['usuario_id']
                    ]);
                    
                    $itensImportados++;
                    
                    // Agrupar por processo para resumo
                    if ($processoNormalizado) {
                        if (!isset($itensProcessos[$processoNormalizado])) {
                            $itensProcessos[$processoNormalizado] = 0;
                        }
                        $itensProcessos[$processoNormalizado]++;
                    }
                    
                } catch (Exception $e) {
                    $erros[] = "Erro ao importar item $numeroItem: " . $e->getMessage();
                }
            }
            
            if ($itensImportados > 0) {
                $sucessos[] = "$itensImportados itens importados com sucesso!";
                
                if (!empty($itensProcessos)) {
                    $resumoProcessos = [];
                    foreach ($itensProcessos as $processo => $quantidade) {
                        $resumoProcessos[] = "$processo: $quantidade";
                    }
                    $sucessos[] = "Processos: " . implode(', ', $resumoProcessos);
                }
            } else {
                $erros[] = 'Nenhum item válido encontrado no arquivo CSV.';
            }
        }
    }
}

/**
 * Normaliza o nome do processo para formato consistente
 */
function normalizarProcesso(?string $processo): ?string
{
    if (empty($processo)) {
        return null;
    }
    
    $processo = trim(mb_strtoupper($processo));
    
    $mapa = [
        'ALMOXARIFADO' => 'ALMOXARIFADO',
        'ALMOXARIFAGEM' => 'ALMOXARIFADO',
        'CORTE' => 'CORTE',
        'LASER' => 'LASER',
        'GUILHOTINA' => 'GUILHOTINA',
        'DOBRA' => 'DOBRA',
        'DOBRAGEM' => 'DOBRA',
        'SOLDA' => 'SOLDA',
        'SOLDAGEM' => 'SOLDA',
        'ACABAMENTO' => 'ACABAMENTO',
        'MONTAGEM' => 'MONTAGEM',
    ];
    
    return $mapa[$processo] ?? $processo;
}

/**
 * Parse de uma linha CSV com separador ;
 */
function parseCsvLinha(string $linha): array
{
    $resultado = [];
    $campo = '';
    $aspas = false;
    
    for ($i = 0; $i < strlen($linha); $i++) {
        $char = $linha[$i];
        
        if ($char === '"') {
            $aspas = !$aspas;
        } elseif ($char === ';' && !$aspas) {
            $resultado[] = $campo;
            $campo = '';
        } else {
            $campo .= $char;
        }
    }
    
    $resultado[] = $campo;
    
    return $resultado;
}

// Buscar ordens de produção para o select
$stmt = $db->query("
    SELECT id, numero, status, prioridade, data_inicio
    FROM ordens_servico
    ORDER BY id DESC
    LIMIT 50
");
$ordensProducao = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verificar se está sendo acessado diretamente ou incluído como modal
$isModal = defined('IS_MODAL') && IS_MODAL === true;

// Se está sendo acessado diretamente (não incluído), mostrar página completa
if (!$isModal) {
    // Página completa - modo legacy para compatibilidade
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Lista de Materiais - Engenharia</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .import-container {
            max-width: 900px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .import-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 20px;
        }
        
        .import-card h2 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #4a90d9;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }
        
        .form-group input[type="file"],
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .btn-primary {
            background: #4a90d9;
            color: white;
        }
        
        .btn-primary:hover {
            background: #357abd;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .materiais-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .materiais-table th,
        .materiais-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .materiais-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
        }
        
        .materiais-table tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-almoxarifado { background: #17a2b8; color: white; }
        .badge-corte { background: #ffc107; color: #333; }
        .badge-laser { background: #6610f2; color: white; }
        .badge-guilhotina { background: #fd7e14; color: white; }
        .badge-dobra { background: #20c997; color: white; }
        .badge-solda { background: #dc3545; color: white; }
        .badge-acabamento { background: #6f42c1; color: white; }
        .badge-montagem { background: #28a745; color: white; }
        
        .voltar-link {
            display: inline-block;
            margin-bottom: 15px;
            color: #4a90d9;
            text-decoration: none;
        }
        
        .voltar-link:hover {
            text-decoration: underline;
        }
        
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #4a90d9;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .info-box h4 {
            margin-top: 0;
            color: #333;
        }
        
        .info-box ul {
            margin-bottom: 0;
            padding-left: 20px;
        }
        
        .info-box li {
            margin-bottom: 5px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="import-container">
        <a href="index.php" class="voltar-link">← Voltar para Engenharia</a>
        
        <div class="import-card">
            <h2>Importar Lista de Materiais (CSV)</h2>
            
            <?php if (!empty($erros)): ?>
                <div class="alert alert-danger">
                    <strong>Erros:</strong>
                    <ul>
                        <?php foreach ($erros as $erro): ?>
                            <li><?= htmlspecialchars($erro) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($sucessos)): ?>
                <div class="alert alert-success">
                    <?php foreach ($sucessos as $msg): ?>
                        <div><?= htmlspecialchars($msg) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div class="info-box">
                <h4>Formato esperado do arquivo CSV:</h4>
                <ul>
                    <li>Arquivo deve ter extensão .csv</li>
                    <li>Utilize ponto e vírgula (;) como separador</li>
                    <li>Colunas: Nº, QTD, X, Y, MATERIAL, DESCRIÇÃO, CÓDIGO, PROCESSO</li>
                    <li>Processos válidos: ALMOXARIFADO, CORTE, LASER, GUILHOTINA, DOBRA, SOLDA, ACABAMENTO, MONTAGEM</li>
                </ul>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="acao" value="importar_csv">
                
                <div class="form-group">
                    <label for="os_id">Ordem de Produção:</label>
                    <select name="os_id" id="os_id" required>
                        <option value="">Selecione uma OS...</option>
                        <?php foreach ($ordensProducao as $os): ?>
                            <option value="<?= $os['id'] ?>">
                                <?= htmlspecialchars($os['numero']) ?> 
                                (<?= htmlspecialchars($os['status']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="arquivo_csv">Arquivo CSV:</label>
                    <input type="file" name="arquivo_csv" id="arquivo_csv" accept=".csv" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="vbtn-sm">Importar Materiais</button>
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
<?php
    return; // Fim do modo página completa
}

// ============================================
// MODO MODAL - Conteúdo para ser incluído
// ============================================
?>

<!-- Conteúdo do Modal de Importação CSV -->
<div class="modal-header">
    <h5 class="modal-title">Importar Lista de Materiais CSV</h5>
    <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
        <span aria-hidden="true">&times;</span>
    </button>
</div>

<form method="POST" enctype="multipart/form-data" id="form-import-csv">
    <input type="hidden" name="acao" value="importar_csv">
    
    <div class="modal-body">
        <?php if (!empty($erros)): ?>
            <div class="alert alert-danger">
                <strong>Erros:</strong>
                <ul class="mb-0">
                    <?php foreach ($erros as $erro): ?>
                        <li><?= htmlspecialchars($erro) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($sucessos)): ?>
            <div class="alert alert-success">
                <?php foreach ($sucessos as $msg): ?>
                    <div><?= htmlspecialchars($msg) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="form-group">
            <label for="modal_os_id">Ordem de Produção:</label>
            <select name="os_id" id="modal_os_id" class="form-control" required>
                <option value="">Selecione uma OS...</option>
                <?php foreach ($ordensProducao as $os): ?>
                    <option value="<?= $os['id'] ?>">
                        <?= htmlspecialchars($os['numero']) ?> 
                        (<?= htmlspecialchars($os['status']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="modal_arquivo_csv">Arquivo CSV:</label>
            <input type="file" name="arquivo_csv" id="modal_arquivo_csv" accept=".csv" class="form-control" required>
        </div>
        
        <div class="alert alert-info">
            <strong>Formato esperado:</strong>
            <ul class="mb-0 mt-2">
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

