<?php
/**
 * ANÁLISE COMPLETA JOTEC - 2137 Códigos
 *
 * Script para analisar e classificar todos os códigos da JOTEC
 * Como: INSUMO ou PRODUTO
 */

// ==================== CONFIGURAÇÃO ====================

$arquivo_json = __DIR__ . '/codigos_jotec_reais.json';

if (!file_exists($arquivo_json)) {
    die("❌ Arquivo não encontrado: $arquivo_json\n");
}

// Ler JSON
$json = json_decode(file_get_contents($arquivo_json), true);

if (!$json) {
    die("❌ Erro ao decodificar JSON\n");
}

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║  ANÁLISE COMPLETA JOTEC - CLASSIFICAÇÃO 2137 CÓDIGOS  ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// ==================== INFORMAÇÕES GERAIS ====================

echo "📊 INFORMAÇÕES GERAIS\n";
echo "═════════════════════════════════════════════════════════\n";
echo "Arquivo: " . $json['arquivo'] . "\n";
echo "Total de Códigos: " . $json['total_codigos'] . "\n";
echo "Total de Abas: " . count($json['abas_processadas']) . "\n";
echo "Abas Processadas: " . implode(', ', $json['abas_processadas']) . "\n\n";

// ==================== ANÁLISE DE ABAS ====================

echo "📑 ANÁLISE DE ABAS\n";
echo "═════════════════════════════════════════════════════════\n";

// Mapear códigos para abas baseado em ranges conhecidos
$abas_classificacao = [
    'PRODUTOS ACABADOS' => [
        'tipo' => 'PRODUTO',
        'descricao' => 'Produtos finalizados, equipamentos prontos',
        'ranges' => ['1000000-1000340']
    ],
    'MATERIAIS' => [
        'tipo' => 'INSUMO',
        'descricao' => 'Matéria prima, insumos de produção',
        'ranges' => ['1000000-1000340']
    ],
    'GERAL' => [
        'tipo' => 'INSUMO',
        'descricao' => 'Materiais gerais, consumo variado',
        'ranges' => []
    ],
    'GRUPO' => [
        'tipo' => 'PRODUTO',
        'descricao' => 'Grupos de produtos, subconjuntos',
        'ranges' => []
    ],
    'Ativo' => [
        'tipo' => 'PRODUTO',
        'descricao' => 'Ativos fixos, equipamentos para uso',
        'ranges' => ['3500001-3500498']
    ],
    'Insumos Diretos' => [
        'tipo' => 'INSUMO',
        'descricao' => 'Materiais que entram direto na produção',
        'ranges' => ['1006001-1006498']
    ],
    'Insumos Indiretos' => [
        'tipo' => 'INSUMO',
        'descricao' => 'Materiais de apoio/consumo/auxiliar',
        'ranges' => ['3000000-3000147']
    ],
    'Prod Especiais' => [
        'tipo' => 'PRODUTO',
        'descricao' => 'Produtos especiais, personalizados',
        'ranges' => []
    ],
    'Revenda' => [
        'tipo' => 'PRODUTO',
        'descricao' => 'Produtos de revenda, sem produção',
        'ranges' => ['1500000-1500157']
    ],
    'Material de Consumo' => [
        'tipo' => 'INSUMO',
        'descricao' => 'Materiais consumíveis, gases, adesivos',
        'ranges' => ['4003001-4003498']
    ],
    'Semiacabados Individuais' => [
        'tipo' => 'INSUMO',
        'descricao' => 'Componentes semiacabados individuais',
        'ranges' => []
    ],
    'Semiacabados Subconjuntos' => [
        'tipo' => 'INSUMO',
        'descricao' => 'Subconjuntos semiacabados',
        'ranges' => []
    ],
    'CONJUNTOS' => [
        'tipo' => 'INSUMO',
        'descricao' => 'Kits e conjuntos de componentes',
        'ranges' => []
    ],
    'CONSERTO' => [
        'tipo' => 'INSUMO',
        'descricao' => 'Peças para conserto/reparo',
        'ranges' => []
    ],
    'SUBSTITUIÇÃO CODIGOS' => [
        'tipo' => 'INSUMO',
        'descricao' => 'Mapeamento de códigos substitutos',
        'ranges' => []
    ]
];

// Analisar cada código
$codigos_processados = [];
$abas_encontradas = [];

foreach ($json['codigos'] as $codigo_raw) {
    $codigo = (int)$codigo_raw; // Converter para inteiro
    $codigos_processados[] = $codigo;
}

// Ordenar
sort($codigos_processados);

echo "\n📊 DISTRIBUIÇÃO POR RANGE DE CÓDIGOS\n";
echo "═════════════════════════════════════════════════════════\n\n";

// Agrupar por ranges conhecidos
$ranges_encontrados = [];

// Definir ranges com base em padrões típicos
$ranges_config = [
    ['min' => 1000000, 'max' => 1000340, 'aba' => 'MATERIAIS/PRODUTOS ACABADOS', 'tipo' => 'AMBOS'],
    ['min' => 1001501, 'max' => 1001504, 'aba' => 'GERAL', 'tipo' => 'INSUMO'],
    ['min' => 1003001, 'max' => 1003009, 'aba' => 'GERAL', 'tipo' => 'INSUMO'],
    ['min' => 1004501, 'max' => 1004507, 'aba' => 'GERAL', 'tipo' => 'INSUMO'],
    ['min' => 1006001, 'max' => 1006498, 'aba' => 'INSUMOS DIRETOS', 'tipo' => 'INSUMO'],
    ['min' => 1500000, 'max' => 1500157, 'aba' => 'REVENDA', 'tipo' => 'PRODUTO'],
    ['min' => 3000000, 'max' => 3000147, 'aba' => 'INSUMOS INDIRETOS', 'tipo' => 'INSUMO'],
    ['min' => 3500001, 'max' => 3500498, 'aba' => 'Ativo', 'tipo' => 'PRODUTO'],
    ['min' => 4003001, 'max' => 4003498, 'aba' => 'Material de Consumo', 'tipo' => 'INSUMO']
];

// Mapear cada código
$mapeamento = [];
foreach ($codigos_processados as $codigo) {
    $encontrado = false;
    foreach ($ranges_config as $range) {
        if ($codigo >= $range['min'] && $codigo <= $range['max']) {
            $mapeamento[$codigo] = [
                'codigo' => $codigo,
                'aba' => $range['aba'],
                'tipo' => $range['tipo']
            ];
            $encontrado = true;
            break;
        }
    }

    if (!$encontrado) {
        $mapeamento[$codigo] = [
            'codigo' => $codigo,
            'aba' => 'OUTROS',
            'tipo' => 'DESCONHECIDO'
        ];
    }
}

// Contar por tipo de aba
$contagem_abas = [];
$contagem_tipos = ['INSUMO' => 0, 'PRODUTO' => 0, 'AMBOS' => 0, 'DESCONHECIDO' => 0];

foreach ($mapeamento as $item) {
    $aba = $item['aba'];
    $tipo = $item['tipo'];

    if (!isset($contagem_abas[$aba])) {
        $contagem_abas[$aba] = 0;
    }
    $contagem_abas[$aba]++;

    if ($tipo !== 'AMBOS' && $tipo !== 'DESCONHECIDO') {
        $contagem_tipos[$tipo]++;
    } else {
        $contagem_tipos[$tipo]++;
    }
}

// Exibir por aba
foreach ($contagem_abas as $aba => $quantidade) {
    echo "$aba: $quantidade códigos\n";
}

echo "\n";
echo "📈 RESUMO POR TIPO\n";
echo "═════════════════════════════════════════════════════════\n";
echo "INSUMO: " . $contagem_tipos['INSUMO'] . " códigos\n";
echo "PRODUTO: " . $contagem_tipos['PRODUTO'] . " códigos\n";
echo "AMBOS: " . $contagem_tipos['AMBOS'] . " códigos\n";
echo "DESCONHECIDO: " . $contagem_tipos['DESCONHECIDO'] . " códigos\n";
echo "────────────────────────────────────────────\n";
echo "TOTAL: " . array_sum($contagem_tipos) . " códigos\n\n";

// ==================== SALVAR RESULTADO ====================

$resultado = [
    'data' => date('Y-m-d H:i:s'),
    'total_codigos' => count($codigos_processados),
    'abas_encontradas' => array_keys($contagem_abas),
    'contagem_por_aba' => $contagem_abas,
    'contagem_por_tipo' => $contagem_tipos,
    'mapeamento_completo' => $mapeamento
];

$json_output = json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
file_put_contents(__DIR__ . '/analise_jotec_2137_codigos.json', $json_output);

echo "✅ Análise salva em: analise_jotec_2137_codigos.json\n";

// ==================== GERAR RELATÓRIO ====================

echo "\n📋 RELATÓRIO FINAL\n";
echo "═════════════════════════════════════════════════════════\n";
echo "Total de códigos analisados: " . count($codigos_processados) . "\n";
echo "Código mínimo: " . min($codigos_processados) . "\n";
echo "Código máximo: " . max($codigos_processados) . "\n";
echo "Range total: " . (max($codigos_processados) - min($codigos_processados) + 1) . "\n";

?>
