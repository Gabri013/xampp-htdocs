<?php
/**
 * Script de Análise de Design dos 8 Módulos
 * Gera relatório em JSON sobre estrutura, componentes e padrões
 */

require_once '../config/config.php';

$modulos = [
    'vendas' => ['cor' => '#3b82f6', 'setor' => 'Vendas'],
    'sac' => ['cor' => '#ec4899', 'setor' => 'SAC'],
    'engenharia' => ['cor' => '#8b5cf6', 'setor' => 'Engenharia'],
    'producao' => ['cor' => '#f59e0b', 'setor' => 'Produção'],
    'estoque' => ['cor' => '#10b981', 'setor' => 'Estoque'],
    'expedicao' => ['cor' => '#06b6d4', 'setor' => 'Expedição'],
    'financeiro' => ['cor' => '#6366f1', 'setor' => 'Financeiro'],
];

$resultado = [];

foreach ($modulos as $chave => $info) {
    $dir = "../modules/$chave";

    if (!is_dir($dir)) {
        continue;
    }

    $arquivos = array_diff(scandir($dir), ['.', '..']);
    $php_files = array_filter($arquivos, function($f) {
        return substr($f, -4) === '.php' && $f !== 'api';
    });

    $analise = [
        'modulo' => $chave,
        'setor' => $info['setor'],
        'cor_hex' => $info['cor'],
        'total_arquivos' => count($php_files),
        'arquivos' => array_values($php_files),
        'padroes_encontrados' => [],
        'problemas_identificados' => [],
    ];

    // Analisar cada arquivo
    foreach ($php_files as $arquivo) {
        $caminho = "$dir/$arquivo";
        $conteudo = file_get_contents($caminho);

        // Verificar padrões
        if (strpos($conteudo, "include '../../includes/header_vendedor.php'") !== false) {
            $analise['padroes_encontrados'][] = 'header_vendedor';
        }
        if (strpos($conteudo, "include '../../includes/vend_sidebar.php'") !== false) {
            $analise['padroes_encontrados'][] = 'vend_sidebar';
        }
        if (strpos($conteudo, 'vend-layout') !== false) {
            $analise['padroes_encontrados'][] = 'vend-layout';
        }
        if (strpos($conteudo, 'vend-main') !== false) {
            $analise['padroes_encontrados'][] = 'vend-main';
        }
        if (strpos($conteudo, 'vend-page-head') !== false) {
            $analise['padroes_encontrados'][] = 'vend-page-head';
        }
        if (strpos($conteudo, "include '../../includes/footer_vendedor.php'") !== false) {
            $analise['padroes_encontrados'][] = 'footer_vendedor';
        }

        // Verificar se tem CSS inline (problema)
        if (preg_match('/<style[^>]*>.*?<\/style>/s', $conteudo)) {
            $analise['problemas_identificados'][] = "$arquivo: CSS inline (deveria ser em arquivo externo)";
        }

        // Verificar responsividade
        if (strpos($conteudo, '@media') === false && strpos($conteudo, 'mobile') === false) {
            if (strlen($conteudo) > 2000) { // apenas arquivos maiores (não template pequenos)
                $analise['problemas_identificados'][] = "$arquivo: Possível falta de responsividade";
            }
        }
    }

    $resultado[] = $analise;
}

// Remover duplicatas de padrões
foreach ($resultado as &$item) {
    $item['padroes_encontrados'] = array_unique($item['padroes_encontrados']);
}

header('Content-Type: application/json');
echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
