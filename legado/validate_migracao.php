<?php
/**
 * Script de validação completa da migração Laravel
 * Execute: php legado/validate_migracao.php
 */

require_once __DIR__ . '/../config/config.php';

$errors = [];
$warnings = [];
$success = [];

// 1. Verificar constantes de ponte
echo "=== Validação da Ponte de Autenticação ===\n\n";

if (defined('PONTE_SECRET_KEY') && !empty(PONTE_SECRET_KEY)) {
    $len = strlen(PONTE_SECRET_KEY);
    if ($len >= 32) {
        $success[] = "PONTE_SECRET_KEY definida ({$len} chars)";
    } else {
        $warnings[] = "PONTE_SECRET_KEY curta ({$len} chars) - use openssl rand -hex 32";
    }
} else {
    $errors[] = 'PONTE_SECRET_KEY não definida. Adicione em config/config.local.php';
}

if (defined('SESSION_DOMAIN')) {
    $success[] = 'SESSION_DOMAIN definido: ' . SESSION_DOMAIN;
} else {
    $warnings[] = 'SESSION_DOMAIN não definido - usando default';
}

// 2. Verificar funções de ponte
if (function_exists('gerarTokenPonte')) {
    $success[] = 'gerarTokenPonte() carregada';
} else {
    $errors[] = 'gerarTokenPonte() não existe';
}

if (function_exists('destruirTokenPonte')) {
    $success[] = 'destruirTokenPonte() carregada';
} else {
    $errors[] = 'destruirTokenPonte() não existe';
}

// 3. Verificar tabelas existentes
echo "\n=== Validação de Tabelas ===\n\n";
$db = getDB();

$requiredTables = [
    'usuarios' => 'Tabela de usuários',
    'ordens_servico' => 'Ordens de serviço',
    'notificacoes' => 'Notificações',
    'vendas' => 'Vendas',
    'clientes' => 'Clientes',
    'contas_receber' => 'Contas a receber',
    'os_arquivos' => 'Arquivos de OS',
    'os_etapas_producao' => 'Etapas de produção',
    'os_historico_status' => 'Histórico de status',
];

foreach ($requiredTables as $table => $desc) {
    $stmt = $db->query("SHOW TABLES LIKE '$table'");
    if ($stmt->fetch()) {
        $success[] = "Tabela '{$table}' existe ({$desc})";
    } else {
        $warnings[] = "Tabela '{$table}' não existe ({$desc})";
    }
}

// 4. Verificar colunas críticas na tabela usuarios
echo "\n=== Validação de Colunas ===\n\n";
$stmt = $db->query("SHOW COLUMNS FROM usuarios");
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);

$criticalCols = ['id', 'nome', 'email', 'tipo', 'senha', 'status'];
foreach ($criticalCols as $col) {
    if (in_array($col, $cols, true)) {
        $success[] = "Coluna 'usuarios.{$col}' existe";
    } else {
        $errors[] = "Coluna 'usuarios.{$col}' faltando";
    }
}

// 5. Verificar arquivos Laravel
echo "\n=== Validação de Arquivos Laravel ===\n\n";
$laravelFiles = [
    'cozinca-novo/app/Http/Middleware/AutenticarViaLegado.php',
    'cozinca-novo/app/Models/Usuario.php',
    'cozinca-novo/app/Models/OrdemServico.php',
    'cozinca-novo/app/Models/Notificacao.php',
    'cozinca-novo/config/services.php',
    'cozinca-novo/tailwind.config.js',
    'cozinca-novo/.env.example',
];

foreach ($laravelFiles as $file) {
    if (file_exists(__DIR__ . '/../' . $file)) {
        $success[] = "Arquivo Laravel existe: {$file}";
    } else {
        $errors[] = "Arquivo Laravel faltando: {$file}";
    }
}

// 6. Verificar status válidos
echo "\n=== Validação do Workflow ===\n\n";
$validStatuses = ['pendente', 'em_projeto', 'proposta', 'em_revisao', 'em_producao', 'concluida', 'cancelada'];
$stmt = $db->query("SELECT DISTINCT status FROM ordens_servico");
$existingStatuses = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($validStatuses as $status) {
    if (in_array($status, $existingStatuses, true) || empty($existingStatuses)) {
        $success[] = "Status '{$status}' válido no workflow";
    }
}

// Output
echo "\n=== RESULTADO ===\n\n";

if (!empty($errors)) {
    echo "ERRORES (".$count = count($errors)."):\n";
    foreach ($errors as $e) { echo "  - $e\n"; }
}

if (!empty($warnings)) {
    echo "\nAVISOS (".count($warnings)."):\n";
    foreach ($warnings as $w) { echo "  - $w\n"; }
}

if (!empty($success)) {
    echo "\nSUCESSOS (".count($success)."):\n";
    foreach ($success as $s) { echo "  ✓ $s\n"; }
}

echo "\n=== CONCLUSÃO ===\n";
if (empty($errors)) {
    echo "Migração VALIDADA com ".count($warnings)." avisos.\n";
    exit(0);
} else {
    echo "Migração com ERROS - corrigir antes do deploy.\n";
    exit(1);
}