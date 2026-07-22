<?php
/**
 * Autenticação por TOKEN da API REST externa (estilo Nomus api.nomus.com.br).
 * Tokens são guardados só como hash SHA-256 — o valor cheio só aparece uma vez,
 * na criação. Cliente envia "Authorization: Bearer <token>" (ou X-API-Key).
 */

function ensureApiTokensSchema(PDO $db): void
{
    $db->exec("
        CREATE TABLE IF NOT EXISTS api_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(120) NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            prefixo VARCHAR(16) NOT NULL,
            escopo ENUM('leitura','completo') NOT NULL DEFAULT 'leitura',
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            ultimo_uso DATETIME NULL,
            criado_por INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token_hash (token_hash)
        ) ENGINE=InnoDB
    ");
}

/**
 * Gera um novo token. Retorna ['token' (cheio, mostrar 1x), 'hash', 'prefixo'].
 */
function gerarApiToken(): array
{
    $random = bin2hex(random_bytes(24)); // 48 hex
    $token = 'czk_' . $random;
    return [
        'token'   => $token,
        'hash'    => hash('sha256', $token),
        'prefixo' => substr($token, 0, 12) . '…',
    ];
}

/**
 * Lê o token do header. Retorna a string ou ''.
 */
function lerTokenApi(): string
{
    $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($hdr === '' && function_exists('apache_request_headers')) {
        $all = apache_request_headers();
        foreach ($all as $k => $v) {
            if (strcasecmp($k, 'Authorization') === 0) { $hdr = $v; break; }
        }
    }
    if (stripos($hdr, 'Bearer ') === 0) {
        return trim(substr($hdr, 7));
    }
    if (!empty($_SERVER['HTTP_X_API_KEY'])) {
        return trim($_SERVER['HTTP_X_API_KEY']);
    }
    return '';
}

/**
 * Valida o token. Retorna a linha do token (id, nome, escopo) ou null.
 */
function autenticarApi(PDO $db): ?array
{
    $token = lerTokenApi();
    if ($token === '') return null;
    $stmt = $db->prepare("SELECT id, nome, escopo FROM api_tokens WHERE token_hash = ? AND ativo = 1 LIMIT 1");
    $stmt->execute([hash('sha256', $token)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    try { $db->prepare("UPDATE api_tokens SET ultimo_uso = NOW() WHERE id = ?")->execute([$row['id']]); } catch (Throwable $e) {}
    return $row;
}
