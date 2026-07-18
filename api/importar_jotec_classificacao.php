<?php
/**
 * API de Importacao JOTEC - Classificacao Completa
 *
 * Importa todos os 2137 codigos do JOTEC com classificacao
 * (INSUMO, PRODUTO, LEGADO) para a tabela jotec_classificacao
 *
 * POST /api/importar_jotec_classificacao.php
 * {
 *   "acao": "importar",
 *   "origem": "json"  // "json" ou "manual"
 * }
 */

require_once '../config/config.php';

// Seguranca
requirePermission(['master', 'gerente', 'estoque']);

$db = getDB();
$acao = $_POST['acao'] ?? $_GET['acao'] ?? null;

// ==================== MAPEAMENTO DE CLASSIFICACAO ====================

// Definir ranges com classificacao
$ranges_jotec = [
    ['min' => 992, 'max' => 999, 'tipo' => 'LEGADO', 'aba' => 'PRODUTOS ACABADOS', 'categoria' => 'Legado'],
    ['min' => 1000000, 'max' => 1000058, 'tipo' => 'INSUMO', 'aba' => 'MATERIAIS', 'categoria' => 'Materia Prima'],
    ['min' => 1001501, 'max' => 1001504, 'tipo' => 'INSUMO', 'aba' => 'GERAL', 'categoria' => 'Geral'],
    ['min' => 1003001, 'max' => 1003009, 'tipo' => 'INSUMO', 'aba' => 'GERAL', 'categoria' => 'Geral'],
    ['min' => 1004501, 'max' => 1004507, 'tipo' => 'INSUMO', 'aba' => 'GERAL', 'categoria' => 'Geral'],
    ['min' => 1004508, 'max' => 1004508, 'tipo' => 'INSUMO', 'aba' => 'GERAL', 'categoria' => 'Geral'],
    ['min' => 1006001, 'max' => 1006489, 'tipo' => 'INSUMO', 'aba' => 'INSUMOS DIRETOS', 'categoria' => 'Componente'],
    ['min' => 1007503, 'max' => 1007530, 'tipo' => 'INSUMO', 'aba' => 'GERAL', 'categoria' => 'Geral'],
    ['min' => 1010501, 'max' => 1010529, 'tipo' => 'INSUMO', 'aba' => 'GERAL', 'categoria' => 'Geral'],
    ['min' => 1012001, 'max' => 1012012, 'tipo' => 'INSUMO', 'aba' => 'GERAL', 'categoria' => 'Geral'],
    ['min' => 1013501, 'max' => 1013508, 'tipo' => 'INSUMO', 'aba' => 'GERAL', 'categoria' => 'Geral'],
    ['min' => 1500000, 'max' => 1500155, 'tipo' => 'PRODUTO', 'aba' => 'REVENDA', 'categoria' => 'Revenda'],
    ['min' => 3000000, 'max' => 3000149, 'tipo' => 'INSUMO', 'aba' => 'INSUMOS INDIRETOS', 'categoria' => 'Consumo'],
    ['min' => 3001501, 'max' => 3001512, 'tipo' => 'INSUMO', 'aba' => 'INSUMOS INDIRETOS', 'categoria' => 'Consumo'],
    ['min' => 3003001, 'max' => 3003008, 'tipo' => 'INSUMO', 'aba' => 'INSUMOS INDIRETOS', 'categoria' => 'Consumo'],
    ['min' => 3004501, 'max' => 3004517, 'tipo' => 'INSUMO', 'aba' => 'INSUMOS INDIRETOS', 'categoria' => 'Consumo'],
    ['min' => 3500001, 'max' => 3500498, 'tipo' => 'PRODUTO', 'aba' => 'ATIVO', 'categoria' => 'Ativo Fixo'],
    ['min' => 4000000, 'max' => 4000003, 'tipo' => 'INSUMO', 'aba' => 'MATERIAL DE CONSUMO', 'categoria' => 'Consumivel'],
    ['min' => 4001501, 'max' => 4001552, 'tipo' => 'INSUMO', 'aba' => 'MATERIAL DE CONSUMO', 'categoria' => 'Consumivel'],
    ['min' => 4003001, 'max' => 4003498, 'tipo' => 'INSUMO', 'aba' => 'MATERIAL DE CONSUMO', 'categoria' => 'Consumivel'],
];

// ==================== ACAO: IMPORTAR ====================

if ($acao === 'importar') {
    try {
        // Ler arquivo JSON com os codigos
        $arquivo_json = __DIR__ . '/../scripts/codigos_jotec_reais.json';

        if (!file_exists($arquivo_json)) {
            throw new Exception("Arquivo JSON nao encontrado: $arquivo_json");
        }

        $json_data = json_decode(file_get_contents($arquivo_json), true);
        if (!$json_data) {
            throw new Exception("Erro ao decodificar JSON");
        }

        // Limpar dados anteriores (opcional)
        // $db->prepare("DELETE FROM jotec_classificacao")->execute();

        // Converter codigos para inteiros
        $codigos = array_map(function($c) { return (int)$c; }, $json_data['codigos']);
        $codigos = array_unique($codigos); // Remover duplicatas
        sort($codigos);

        // Processar cada codigo
        $sql_insert = "INSERT INTO jotec_classificacao
                      (codigo_jotec, tipo, aba, categoria, descricao, range_inicio, range_fim, status, observacoes)
                      VALUES (?, ?, ?, ?, ?, ?, ?, 'ativo', ?)";

        $stmt = $db->prepare($sql_insert);
        $inseridos = 0;
        $atualizados = 0;
        $erros = 0;

        foreach ($codigos as $codigo) {
            $classificacao = classificarCodigo($codigo, $ranges_jotec);

            try {
                // Verificar se ja existe
                $verif = $db->prepare("SELECT id FROM jotec_classificacao WHERE codigo_jotec = ?");
                $verif->execute([$codigo]);

                if ($verif->rowCount() > 0) {
                    // Atualizar
                    $upd = $db->prepare(
                        "UPDATE jotec_classificacao SET tipo = ?, aba = ?, categoria = ?, range_inicio = ?, range_fim = ?
                         WHERE codigo_jotec = ?"
                    );
                    $upd->execute([
                        $classificacao['tipo'],
                        $classificacao['aba'],
                        $classificacao['categoria'],
                        $classificacao['range_inicio'],
                        $classificacao['range_fim'],
                        $codigo
                    ]);
                    $atualizados++;
                } else {
                    // Inserir novo
                    $descricao = "Codigo JOTEC {$codigo}";
                    $stmt->execute([
                        $codigo,
                        $classificacao['tipo'],
                        $classificacao['aba'],
                        $classificacao['categoria'],
                        $descricao,
                        $classificacao['range_inicio'],
                        $classificacao['range_fim'],
                        "Importado em " . date('Y-m-d H:i:s')
                    ]);
                    $inseridos++;
                }
            } catch (Exception $e) {
                $erros++;
                error_log("Erro ao processar codigo $codigo: " . $e->getMessage());
            }
        }

        // Verificar integridade
        $total = $db->prepare("SELECT COUNT(*) as cnt FROM jotec_classificacao")->fetchAll();
        $total_registros = $total[0]['cnt'];

        $contagem_tipo = $db->prepare(
            "SELECT tipo, COUNT(*) as cnt FROM jotec_classificacao GROUP BY tipo"
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Importacao concluida com sucesso!',
            'resumo' => [
                'total_codigos_processados' => count($codigos),
                'registros_inseridos' => $inseridos,
                'registros_atualizados' => $atualizados,
                'erros' => $erros,
                'total_no_banco' => $total_registros,
                'contagem_por_tipo' => $contagem_tipo
            ],
            'data_importacao' => date('Y-m-d H:i:s')
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'sucesso' => false,
            'erro' => $e->getMessage()
        ]);
    }
}

// ==================== ACAO: STATUS ====================

elseif ($acao === 'status') {
    try {
        // Contar registros
        $total = $db->prepare("SELECT COUNT(*) as cnt FROM jotec_classificacao")->fetch();
        $contagem_tipo = $db->prepare(
            "SELECT tipo, COUNT(*) as cnt FROM jotec_classificacao GROUP BY tipo ORDER BY tipo"
        )->fetchAll();
        $contagem_aba = $db->prepare(
            "SELECT aba, COUNT(*) as cnt FROM jotec_classificacao GROUP BY aba ORDER BY aba"
        )->fetchAll();

        echo json_encode([
            'sucesso' => true,
            'total_registros' => $total['cnt'],
            'contagem_por_tipo' => $contagem_tipo,
            'contagem_por_aba' => $contagem_aba,
            'esperado' => [
                'INSUMO' => 1305,
                'PRODUTO' => 669,
                'LEGADO' => 8,
                'TOTAL' => 2137
            ]
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
    }
}

// ==================== ACAO: VERIFICAR ====================

elseif ($acao === 'verificar') {
    $codigo = intval($_POST['codigo'] ?? $_GET['codigo'] ?? 0);

    try {
        if ($codigo <= 0) {
            throw new Exception("Codigo invalido");
        }

        $stmt = $db->prepare(
            "SELECT * FROM jotec_classificacao WHERE codigo_jotec = ?"
        );
        $stmt->execute([$codigo]);

        if ($stmt->rowCount() > 0) {
            $registro = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode([
                'sucesso' => true,
                'encontrado' => true,
                'dados' => $registro
            ]);
        } else {
            // Tentar classificar dinamicamente
            $classificacao = classificarCodigo($codigo, $ranges_jotec);
            echo json_encode([
                'sucesso' => true,
                'encontrado' => false,
                'classificacao_dinamica' => $classificacao
            ]);
        }

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
    }
}

// ==================== PADRAO ====================

else {
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Acao nao informada',
        'acoes_disponiveis' => ['importar', 'status', 'verificar']
    ]);
}

// ==================== FUNCOES AUXILIARES ====================

/**
 * Classifica um codigo JOTEC baseado nos ranges
 */
function classificarCodigo($codigo, $ranges) {
    foreach ($ranges as $range) {
        if ($codigo >= $range['min'] && $codigo <= $range['max']) {
            return [
                'tipo' => $range['tipo'],
                'aba' => $range['aba'],
                'categoria' => $range['categoria'],
                'range_inicio' => $range['min'],
                'range_fim' => $range['max']
            ];
        }
    }

    // Se nao encontrou, retornar desconhecido
    return [
        'tipo' => 'DESCONHECIDO',
        'aba' => 'OUTROS',
        'categoria' => 'Nao classificado',
        'range_inicio' => $codigo,
        'range_fim' => $codigo
    ];
}

?>
