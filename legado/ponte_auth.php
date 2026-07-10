<?php
/**
 * PONTE DE AUTENTICAÇÃO LEGADO → LARAVEL
 *
 * Onde adicionar:
 * 1) Em config/config.php (ou arquivo de constantes), adicione:
 *      define('PONTE_SECRET_KEY', 'MESMA_CHAVE_DO_.ENV_LARAVEL');
 *    Gere a chave uma única vez com: openssl rand -hex 32
 *
 * 2) Em includes/auth.php, dentro da função login($email, $senha),
 *    logo APÓS validar a senha e antes do "return true;", chame:
 *      gerarTokenPonte($usuario['id'], $usuario['tipo']);
 *
 * 3) Na função de logout, chame destruirTokenPonte() para apagar o cookie
 *    junto com a destruição da sessão PHP.
 */

function gerarTokenPonte(int $usuario_id, string $usuario_tipo): void
{
    if (!defined('PONTE_SECRET_KEY') || empty(PONTE_SECRET_KEY)) {
        return;
    }

    $payload = base64_encode(json_encode([
        'uid'  => $usuario_id,
        'tipo' => $usuario_tipo,
        'exp'  => time() + (3600 * 8),
    ]));

    $assinatura = hash_hmac('sha256', $payload, PONTE_SECRET_KEY);
    $token = $payload . '.' . $assinatura;

    $domain = defined('SESSION_DOMAIN') ? SESSION_DOMAIN : '.seudominio.com';

    setcookie('cozinca_ponte', $token, [
        'expires'  => time() + (3600 * 8),
        'path'     => '/',
        'domain'   => $domain,
        'httponly' => true,
        'secure'   => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443),
        'samesite' => 'Lax',
    ]);
}

function destruirTokenPonte(): void
{
    $domain = defined('SESSION_DOMAIN') ? SESSION_DOMAIN : '.seudominio.com';

    setcookie('cozinca_ponte', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'domain'   => $domain,
        'httponly' => true,
        'secure'   => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443),
        'samesite' => 'Lax',
    ]);
}