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

    // 1) Acesso base pelo CARGO (tipo) do usuário
    if (in_array($_SESSION['usuario_tipo'] ?? '', $tipos_permitidos, true)) {
        return true;
    }

    // 2) Acessos EXTRAS concedidos por usuário (aditivo, carregados no login).
    //    Um extra é um tipo/setor que o master liberou para este usuário além
    //    do cargo dele — nunca reduz acesso, só amplia.
    $extras = $_SESSION['acessos_extras'] ?? [];
    if (!empty($extras)) {
        foreach ($tipos_permitidos as $t) {
            if (in_array($t, $extras, true)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Retorna os acessos extras concedidos a um usuário (lista de tipos/setores).
 * Defensivo: se a tabela ainda não existe, devolve vazio.
 */
function getAcessosExtrasUsuario($usuarioId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT acesso FROM usuario_acessos_extras WHERE usuario_id = ?");
        $stmt->execute([(int) $usuarioId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * "Acessar como" (impersonação para teste/suporte). O master vê o sistema
 * exatamente como o usuário-alvo, sem sair da própria conta. A identidade
 * original fica guardada em $_SESSION['impersonator'] para o retorno.
 */
function isImpersonating(): bool {
    return !empty($_SESSION['impersonator']);
}

/**
 * Tipo REAL de quem operou o login (ignora a impersonação atual).
 */
function tipoReal(): string {
    return $_SESSION['impersonator']['tipo'] ?? ($_SESSION['usuario_tipo'] ?? '');
}

/**
 * Inicia (ou re-aponta) a impersonação. Só o master REAL pode. Não permite
 * impersonar a si mesmo. Retorna [ok=>bool, erro=>string].
 */
function iniciarImpersonacao($targetUserId): array {
    if (tipoReal() !== 'master') {
        return ['ok' => false, 'erro' => 'Apenas o master pode acessar como outro usuário.'];
    }
    $targetUserId = (int) $targetUserId;
    $realId = $_SESSION['impersonator']['id'] ?? ($_SESSION['usuario_id'] ?? 0);
    if ($targetUserId === (int) $realId) {
        return ['ok' => false, 'erro' => 'Você já está na sua própria conta.'];
    }
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, nome, email, tipo FROM usuarios WHERE id = ? AND status = 'ativo'");
        $stmt->execute([$targetUserId]);
        $alvo = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return ['ok' => false, 'erro' => 'Erro ao carregar usuário.'];
    }
    if (!$alvo) {
        return ['ok' => false, 'erro' => 'Usuário não encontrado ou inativo.'];
    }
    // Guarda o master original só na primeira vez (re-apontar mantém o original)
    if (empty($_SESSION['impersonator'])) {
        $_SESSION['impersonator'] = [
            'id'    => $_SESSION['usuario_id'] ?? null,
            'nome'  => $_SESSION['usuario_nome'] ?? '',
            'email' => $_SESSION['usuario_email'] ?? '',
            'tipo'  => $_SESSION['usuario_tipo'] ?? '',
        ];
    }
    $_SESSION['usuario_id']     = $alvo['id'];
    $_SESSION['usuario_nome']   = $alvo['nome'];
    $_SESSION['usuario_email']  = $alvo['email'];
    $_SESSION['usuario_tipo']   = $alvo['tipo'];
    $_SESSION['acessos_extras'] = getAcessosExtrasUsuario($alvo['id']);
    return ['ok' => true, 'erro' => ''];
}

/**
 * Encerra a impersonação e restaura a conta original do master.
 */
function encerrarImpersonacao(): void {
    if (empty($_SESSION['impersonator'])) {
        return;
    }
    $m = $_SESSION['impersonator'];
    $_SESSION['usuario_id']     = $m['id'];
    $_SESSION['usuario_nome']   = $m['nome'];
    $_SESSION['usuario_email']  = $m['email'];
    $_SESSION['usuario_tipo']   = $m['tipo'];
    $_SESSION['acessos_extras'] = getAcessosExtrasUsuario($m['id']);
    unset($_SESSION['impersonator']);
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
            // Acessos extras concedidos pelo master (cargo + extras); ficam na
            // sessão para o hasPermission() não bater no banco a cada página.
            $_SESSION['acessos_extras'] = getAcessosExtrasUsuario($usuario['id']);

            // Registrar último acesso
            $stmt = $db->prepare("UPDATE usuarios SET updated_at = NOW() WHERE id = ?");
            $stmt->execute([$usuario['id']]);
            
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
        'financeiro' => 'Financeiro',
        'estoque' => 'Estoque',
        'expedicao' => 'Expedição',
        'sac' => 'SAC / Atendimento',
        'engenharia' => 'Setor de Engenharia',
        'programacao' => 'Setor de Programação',
        'corte' => 'Setor de Corte',
        'dobra' => 'Setor de Dobra',
        'tubo' => 'Setor de Tubo',
        'solda' => 'Setor de Solda',
        'mobiliario' => 'Setor de Mobiliário',
        'coccao' => 'Setor de Cocção',
        'refrigeracao' => 'Setor de Refrigeração',
        'acabamento' => 'Setor de Acabamento',
        'montagem' => 'Setor de Montagem',
        'embalagem' => 'Setor de Embalagem',
        'finalizacao' => 'Setor de Finalização',
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
