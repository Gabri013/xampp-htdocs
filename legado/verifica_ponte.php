<?php
/**
 * Script de verificação da ponte Laravel + Legado
 * Execute: php legado/verifica_ponte.php
 */

require_once __DIR__ . '/../config/config.php';

$errors = [];

// Verifica se as constantes estão definidas
if (!defined('PONTE_SECRET_KEY') || empty(PONTE_SECRET_KEY)) {
    $errors[] = 'PONTE_SECRET_KEY não está definida. Adicione em config/config.local.php';
}

// Verifica se a função existe
if (!function_exists('gerarTokenPonte')) {
    $errors[] = 'Função gerarTokenPonte() não carregada. Verifique se legado/ponte_auth.php existe';
}

if (!function_exists('destruirTokenPonte')) {
    $errors[] = 'Função destruirTokenPonte() não carregada.';
}

// Testa geração do token
$testToken = gerarTokenPonte(999, 'vendedor');
echo "Token de teste gerado (usuário 999):\n";
echo $_COOKIE['cozinca_ponte'] ?? '(cookie não setado - pode ser devido a headers_sent)';

if (!empty($errors)) {
    echo "\n\nErros encontrados:\n";
    foreach ($errors as $e) {
        echo " - $e\n";
    }
} else {
    echo "\n\n✅ Verificação concluída - ponte configurada corretamente\n";
}