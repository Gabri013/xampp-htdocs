<?php
/**
 * Funções Auxiliares do Sistema
 */

/**
 * Formata valor monetário
 */
function formatMoney($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

/**
 * Formata data para exibição
 */
function formatDate($data) {
    if (empty($data)) return '-';
    $timestamp = strtotime($data);
    return date('d/m/Y', $timestamp);
}

/**
 * Formata data e hora para exibição
 */
function formatDateTime($data) {
    if (empty($data)) return '-';
    $timestamp = strtotime($data);
    return date('d/m/Y H:i', $timestamp);
}

/**
 * Converte data do formato brasileiro para MySQL
 */
function dateToMysql($data) {
    if (empty($data)) return null;
    $partes = explode('/', $data);
    if (count($partes) === 3) {
        return $partes[2] . '-' . $partes[1] . '-' . $partes[0];
    }
    return $data;
}

/**
 * Gera próximo número sequencial
 */
function getNextNumber($tabela, $prefixo) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT numero FROM $tabela ORDER BY id DESC");
        $stmt->execute();

        $regex = '/^' . preg_quote($prefixo, '/') . '(\d+)$/';
        $numero = 1;

        while ($ultimo = $stmt->fetch()) {
            if (preg_match($regex, (string) ($ultimo['numero'] ?? ''), $matches)) {
                $numero = ((int) $matches[1]) + 1;
                break;
            }
        }

        return $prefixo . str_pad($numero, 4, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        error_log("Erro ao gerar número: " . $e->getMessage());
        return $prefixo . '0001';
    }
}

function shouldRunSchemaSync(string $chave, int $ttl = 86400): bool
{
    static $cacheMemoria = [];

    if (defined('ENABLE_SCHEMA_SYNC') && ENABLE_SCHEMA_SYNC === false) {
        return false;
    }

    $ttl = max(60, $ttl);
    $agora = time();
    $cacheKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $chave);

    if (isset($cacheMemoria[$cacheKey]) && ($agora - $cacheMemoria[$cacheKey]) < $ttl) {
        return false;
    }

    $arquivo = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sistema_os_schema_' . $cacheKey . '.cache';
    if (is_file($arquivo)) {
        $modificadoEm = (int) @filemtime($arquivo);
        if ($modificadoEm > 0 && ($agora - $modificadoEm) < $ttl) {
            $cacheMemoria[$cacheKey] = $modificadoEm;
            return false;
        }
    }

    $cacheMemoria[$cacheKey] = $agora;
    @touch($arquivo);
    return true;
}

function ensureIndexIfMissing(PDO $db, string $table, string $indexName, string $indexSql): void
{
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND INDEX_NAME = ?
    ");
    $stmt->execute([$table, $indexName]);
    if ((int) $stmt->fetchColumn() > 0) {
        return;
    }

    $db->exec($indexSql);
}

function ensureOrdensServicoIndependentesSchema(PDO $db): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;

    if (!shouldRunSchemaSync('ordens_servico_independentes', 86400)) {
        return;
    }

    $stmtColuna = $db->query("
        SELECT IS_NULLABLE
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'ordens_servico'
          AND COLUMN_NAME = 'venda_id'
        LIMIT 1
    ");
    $nullable = $stmtColuna ? $stmtColuna->fetchColumn() : null;

    if ($nullable === 'YES') {
        return;
    }

    $stmtFk = $db->query("
        SELECT CONSTRAINT_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'ordens_servico'
          AND COLUMN_NAME = 'venda_id'
          AND REFERENCED_TABLE_NAME = 'vendas'
        LIMIT 1
    ");
    $constraintName = $stmtFk ? $stmtFk->fetchColumn() : null;

    if ($constraintName) {
        $db->exec("ALTER TABLE ordens_servico DROP FOREIGN KEY `{$constraintName}`");
    }

    $db->exec("ALTER TABLE ordens_servico MODIFY COLUMN venda_id INT NULL");
    $db->exec("
        ALTER TABLE ordens_servico
        ADD CONSTRAINT fk_ordens_servico_venda_independente
        FOREIGN KEY (venda_id) REFERENCES vendas(id) ON DELETE RESTRICT
    ");
}

/**
 * Faz upload de arquivo
 */
function uploadFile($file, $pasta) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Erro no upload do arquivo.'];
    }
    
    // Verificar tamanho
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        return ['success' => false, 'message' => 'Arquivo muito grande. Máximo: ' . (UPLOAD_MAX_SIZE / 1024 / 1024) . 'MB'];
    }
    
    // Aceita qualquer extensão; mantém apenas o limite de tamanho.
    $extensao = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Criar pasta se não existir
    $caminho_pasta = rtrim(UPLOAD_PATH, '/') . '/' . $pasta;
    if (!file_exists($caminho_pasta)) {
        if (!mkdir($caminho_pasta, 0777, true)) {
            return ['success' => false, 'message' => 'Não foi possível criar a pasta de destino: ' . $caminho_pasta];
        }
    }
    
    // Tentar garantir que a pasta tenha permissão de escrita
    if (!is_writable($caminho_pasta)) {
        @chmod($caminho_pasta, 0777);
    }
    
    if (!is_writable($caminho_pasta)) {
        return ['success' => false, 'message' => 'A pasta de destino não tem permissão de escrita: ' . $caminho_pasta . '. Por favor, altere as permissões da pasta assets/uploads para 777.'];
    }
    
    // Gerar nome único
    $sufixoExtensao = $extensao !== '' ? '.' . $extensao : '';
    $nome_arquivo = uniqid() . '_' . time() . $sufixoExtensao;
    $caminho_completo = $caminho_pasta . '/' . $nome_arquivo;
    
    // Mover arquivo
    if (move_uploaded_file($file['tmp_name'], $caminho_completo)) {
        return [
            'success' => true,
            'filename' => $nome_arquivo,
            'path' => $caminho_completo,
            'original_name' => $file['name']
        ];
    }
    
    $error_msg = error_get_last();
    return ['success' => false, 'message' => 'Erro ao salvar arquivo: ' . ($error_msg['message'] ?? 'Erro desconhecido no move_uploaded_file')];
}

/**
 * Remove arquivo
 */
function deleteFile($caminho) {
    if (file_exists($caminho)) {
        return unlink($caminho);
    }
    return false;
}

/**
 * Sanitiza string
 */
function sanitize($string) {
    return htmlspecialchars(strip_tags(trim($string)), ENT_QUOTES, 'UTF-8');
}

/**
 * Valida email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Gera mensagem de sucesso
 */
function setSuccess($mensagem) {
    $_SESSION['sucesso'] = $mensagem;
}

/**
 * Gera mensagem de erro
 */
function setError($mensagem) {
    $_SESSION['erro'] = $mensagem;
}

/**
 * Obtém e limpa mensagem de sucesso
 */
function getSuccess() {
    if (isset($_SESSION['sucesso'])) {
        $msg = $_SESSION['sucesso'];
        unset($_SESSION['sucesso']);
        return $msg;
    }
    return null;
}

/**
 * Obtém e limpa mensagem de erro
 */
function getError() {
    if (isset($_SESSION['erro'])) {
        $msg = $_SESSION['erro'];
        unset($_SESSION['erro']);
        return $msg;
    }
    return null;
}

/**
 * Retorna cor da prioridade
 */
function getPrioridadeCor($prioridade) {
    $cores = [
        'verde' => '#28a745',
        'amarelo' => '#ffc107',
        'vermelho' => '#dc3545'
    ];
    return $cores[$prioridade] ?? '#6c757d';
}

/**
 * Retorna nome da prioridade
 */
function getPrioridadeNome($prioridade) {
    $nomes = [
        'verde' => 'Normal',
        'amarelo' => 'Emergente',
        'vermelho' => 'Urgente'
    ];
    return $nomes[$prioridade] ?? 'Não definida';
}

/**
 * Retorna badge HTML da prioridade
 */
function getPrioridadeBadge($prioridade) {
    $cor = getPrioridadeCor($prioridade);
    $nome = getPrioridadeNome($prioridade);
    return "<span class='badge' style='background-color: $cor; color: white;'>$nome</span>";
}

/**
 * Retorna nome do status da O.S
 */
function getStatusOSNome($status) {
    $nomes = [
        'pendente' => 'Pendente',
        'em_projeto' => 'Em Projeto',
        'proposta' => 'Em Proposta',
        'em_revisao' => 'Em Revisão',
        'em_producao' => 'Em Produção',
        'concluida' => 'Concluída',
        'cancelada' => 'Cancelada'
    ];
    return $nomes[$status] ?? 'Desconhecido';
}

/**
 * Retorna cor do status da O.S
 */
function getStatusOSCor($status) {
    $cores = [
        'pendente' => '#6c757d',
        'em_projeto' => '#007bff',
        'proposta' => '#17a2b8',
        'em_revisao' => '#17a2b8',
        'em_producao' => '#ffc107',
        'concluida' => '#28a745',
        'cancelada' => '#dc3545'
    ];
    return $cores[$status] ?? '#6c757d';
}

/**
 * Retorna badge HTML do status da O.S
 */
function getStatusOSBadge($status) {
    $cor = getStatusOSCor($status);
    $nome = getStatusOSNome($status);
    return "<span class='badge' style='background-color: $cor; color: white;'>$nome</span>";
}

/**
 * Registra log de atividade
 */
function logActivity($tabela, $acao, $registro_id, $descricao = '') {
    try {
        $db = getDB();
        $usuario_id = $_SESSION['usuario_id'] ?? null;
        
        $stmt = $db->prepare("INSERT INTO logs (usuario_id, tabela, acao, registro_id, descricao) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$usuario_id, $tabela, $acao, $registro_id, $descricao]);
        
        return true;
    } catch (Exception $e) {
        error_log("Erro ao registrar log: " . $e->getMessage());
        return false;
    }
}

/**
 * Paginação
 */
function paginate($total_items, $current_page = 1, $items_per_page = ITEMS_PER_PAGE) {
    $total_pages = ceil($total_items / $items_per_page);
    $current_page = max(1, min($current_page, $total_pages));
    $offset = ($current_page - 1) * $items_per_page;
    
    return [
        'total_items' => $total_items,
        'total_pages' => $total_pages,
        'current_page' => $current_page,
        'items_per_page' => $items_per_page,
        'offset' => $offset
    ];
}

/**
 * Procura cliente já cadastrado com o mesmo CNPJ/CPF (comparando apenas os
 * dígitos) ou com a mesma razão social. Evita cadastro duplicado.
 */
function encontrarClienteDuplicado(PDO $db, string $razao_social, string $cnpj_cpf = ''): ?array {
    $docDigitos = preg_replace('/\D+/', '', $cnpj_cpf);
    if ($docDigitos !== '') {
        $stmt = $db->prepare("
            SELECT id, razao_social, cnpj_cpf FROM clientes
            WHERE REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(cnpj_cpf,''), '.', ''), '-', ''), '/', ''), ' ', '') = ?
            LIMIT 1
        ");
        $stmt->execute([$docDigitos]);
        $c = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($c) return $c;
    }

    $razao = trim($razao_social);
    if ($razao !== '') {
        $stmt = $db->prepare("SELECT id, razao_social, cnpj_cpf FROM clientes WHERE LOWER(TRIM(razao_social)) = LOWER(?) LIMIT 1");
        $stmt->execute([$razao]);
        $c = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($c) return $c;
    }

    return null;
}

/**
 * Extensões de modelos 3D aceitas (SolidWorks e formatos de intercâmbio).
 * As com visualização no navegador (visualizar_3d.php) estão em getExtensoes3DVisualizaveis().
 */
function getExtensoes3D(): array {
    return ['step', 'stp', 'obj', 'stl', 'iges', 'igs', '3mf', 'glb', 'gltf', 'ply', 'fbx', '3ds', 'brep', 'sldprt', 'sldasm'];
}

function getExtensoes3DVisualizaveis(): array {
    // Formatos que o visualizador (Online 3D Viewer) abre no navegador
    return ['step', 'stp', 'obj', 'stl', 'iges', 'igs', '3mf', 'glb', 'gltf', 'ply', 'fbx', '3ds', 'brep'];
}

function isArquivo3D(string $nomeArquivo): bool {
    $ext = strtolower(pathinfo($nomeArquivo, PATHINFO_EXTENSION));
    return in_array($ext, getExtensoes3D(), true);
}

function isArquivo3DVisualizavel(string $nomeArquivo): bool {
    $ext = strtolower(pathinfo($nomeArquivo, PATHINFO_EXTENSION));
    return in_array($ext, getExtensoes3DVisualizaveis(), true);
}
