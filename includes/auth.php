<?php
/**
 * Funções de Autenticação e Controle de Acesso
 */

/**
 * Verifica se o usuário está logado
 */
function isLoggedIn() {
    return isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id']);
}

/**
 * Verifica se o usuário tem permissão para acessar determinado recurso
 */
function hasPermission($tipos_permitidos = []) {
    if (!isLoggedIn()) {
        return false;
    }
    
    // Se não especificou tipos, apenas verifica se está logado
    if (empty($tipos_permitidos)) {
        return true;
    }
    
    // Verifica se o tipo do usuário está na lista de permitidos
    return in_array($_SESSION['usuario_tipo'], $tipos_permitidos);
}

/**
 * Redireciona para login se não estiver autenticado
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/modules/auth/login.php');
        exit;
    }
}

/**
 * Requer permissão específica, senão redireciona
 */
function requirePermission($tipos_permitidos = []) {
    requireLogin();
    
    if (!hasPermission($tipos_permitidos)) {
        $_SESSION['erro'] = 'Você não tem permissão para acessar esta página.';
        header('Location: ' . SITE_URL . '/index.php');
        exit;
    }
}

/**
 * Realiza o login do usuário
 */
function login($email, $senha) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, nome, email, senha, tipo, status FROM usuarios WHERE email = ? AND status = 'ativo'");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();
        
        if ($usuario && password_verify($senha, $usuario['senha'])) {
            // Login bem-sucedido
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_email'] = $usuario['email'];
            $_SESSION['usuario_tipo'] = $usuario['tipo'];
            
            // Registrar último acesso
            $stmt = $db->prepare("UPDATE usuarios SET updated_at = NOW() WHERE id = ?");
            $stmt->execute([$usuario['id']]);
            
            // PONTE LARAVEL: gerar cookie para autenticação no módulo novo
            if (function_exists('gerarTokenPonte')) {
                gerarTokenPonte($usuario['id'], $usuario['tipo']);
            }
            
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Erro no login: " . $e->getMessage());
        return false;
    }
}

/**
 * Realiza o logout do usuário
 */
function logout() {
    // PONTE LARAVEL: destruir cookie de autenticação no módulo novo
    if (function_exists('destruirTokenPonte')) {
        destruirTokenPonte();
    }
    
    // Limpar todas as variáveis de sessão
    $_SESSION = array();
    
    // Destruir a sessão
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    
    // Redirecionar para login
    header('Location: ' . SITE_URL . '/modules/auth/login.php');
    exit;
}

/**
 * Obtém informações do usuário logado
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['usuario_id'],
        'nome' => $_SESSION['usuario_nome'],
        'email' => $_SESSION['usuario_email'],
        'tipo' => $_SESSION['usuario_tipo']
    ];
}

/**
 * Obtém o nome do tipo de usuário
 */
function getTipoUsuarioNome($tipo) {
    $tipos = [
        'master' => 'Administrador',
        'vendedor' => 'Vendedor',
        'projetista' => 'Projetista',
        'gerente' => 'Gerente de Produção',
        'producao' => 'Produção Geral',
        'corte' => 'Setor de Corte',
        'dobra' => 'Setor de Dobra',
        'solda' => 'Setor de Solda',
        'refrigeracao' => 'Setor de Refrigeração',
        'acabamento' => 'Setor de Acabamento',
        'finalizacao' => 'Setor de Finalização',
        'montagem' => 'Setor de Montagem',
        'dashboard_producao' => 'Dashboard de Produção'
    ];
    
    return $tipos[$tipo] ?? 'Desconhecido';
}

/**
 * Verifica se o usuário é master
 */
function isMaster() {
    return isLoggedIn() && $_SESSION['usuario_tipo'] === 'master';
}

/**
 * Verifica se o usuário é vendedor
 */
function isVendedor() {
    return isLoggedIn() && $_SESSION['usuario_tipo'] === 'vendedor';
}

/**
 * Verifica se o usuário é projetista
 */
function isProjetista() {
    return isLoggedIn() && $_SESSION['usuario_tipo'] === 'projetista';
}

/**
 * Verifica se o usuário é gerente
 */
function isGerente() {
    return isLoggedIn() && $_SESSION['usuario_tipo'] === 'gerente';
}

/**
 * Verifica se o usuário é da produção
 */
function isProducao() {
    return isLoggedIn() && $_SESSION['usuario_tipo'] === 'producao';
}

/**
 * Verifica se o usuário é do dashboard de produção
 */
function isDashboardProducao() {
    return isLoggedIn() && $_SESSION['usuario_tipo'] === 'dashboard_producao';
}
