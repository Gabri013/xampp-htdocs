<?php
/**
 * Configurações Gerais do Sistema - Otimizado para Produção
 */

// Iniciar sessão se ainda não foi iniciada
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Carrega overrides locais, se existir
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// Configurações do sistema
if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'SISTEMA COZINCA');
}

if (!defined('SITE_URL')) {
    $protocol = 'http';
    if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) {
        $protocol = 'https';
    }
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    $host = trim($host);

    $sitePath = '';
    if (!empty($_SERVER['DOCUMENT_ROOT']) && defined('BASE_PATH')) {
        $docRoot = realpath(rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR));
        $projectRoot = realpath(BASE_PATH);
        if ($docRoot !== false && $projectRoot !== false && strpos($projectRoot, $docRoot) === 0) {
            $sitePath = str_replace('\\', '/', substr($projectRoot, strlen($docRoot)));
            if ($sitePath === '') {
                $sitePath = '';
            }
        }
    }

    $sitePath = '/' . trim($sitePath, '/');
    if ($sitePath === '/') {
        $sitePath = '';
    }

    define('SITE_URL', $protocol . '://' . $host . $sitePath);
}

/**
 * AJUSTE CRÍTICO: Garantir que o BASE_PATH use caminhos absolutos limpos.
 * O realpath() resolve links simbólicos e problemas de barras (\ vs /) entre Windows (local) e Linux (hospedagem).
 */
if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(dirname(__DIR__))); 
}

// Configurações de upload
if (!defined('UPLOAD_PATH')) {
    define('UPLOAD_PATH', BASE_PATH . '/assets/uploads/');
}
if (!defined('UPLOAD_MAX_SIZE')) {
    define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
}

if (!defined('UPLOAD_URL')) {
    define('UPLOAD_URL', SITE_URL . '/assets/uploads/');
}
if (!defined('ALLOWED_EXTENSIONS')) {
    define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'dwg', 'dxf']);
}

// Configurações de paginação
if (!defined('ITEMS_PER_PAGE')) {
    define('ITEMS_PER_PAGE', 20);
}

// Configurações de Monitoramento de Atrasos
define('LIMITE_OS_ATRASADAS_CRITICO', 5);  // Número de O.S. críticas para disparar alerta
define('LIMITE_OS_ATRASADAS_URGENTE', 10); // Número de O.S. urgentes para disparar alerta
define('LIMITE_OS_ATRASADAS_TOTAL', 15);   // Número total de O.S. atrasadas para disparar alerta

// Timezone
date_default_timezone_set('America/Sao_Paulo');

/**
 * Ajuste nos requires: Usar o operador de caminhos correto do sistema operacional
 * ajuda a evitar erros de "File not found" em servidores Linux.
 */
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/notificacoes.php';

// Verificação de autenticação
require_once BASE_PATH . '/includes/auth.php';
