<?php
/**
 * API de Custos - Gestão de Custos Reais por O.S.
 * Cálculo inteligente de custo de mão de obra, materiais e overhead
 *
 * Funcionalidades:
 * - Custo de mão de obra (por etapa + tempo real)
 * - Custo de materiais (consumo real vs BOM planejado)
 * - Overhead calculado por O.S.
 * - Margens por cliente (vendido vs custo)
 * - Comparação planejado vs real em tempo real
 * - Lucratividade por pedido com análise de tendências
 *
 * Acesso: master, gerente, financeiro
 */

require_once '../config/config.php';
require_once '../includes/workflow.php';

header('Content-Type: application/json; charset=utf-8');

$db = getDB();
requirePermission(['master', 'gerente', 'financeiro']);

$acao = $_POST['acao'] ?? $_GET['acao'] ?? null;

try {
    // ===== AÇÃO: CALCULAR CUSTO O.S. =====
    if ($acao === 'calcular_custo_os') {
        $os_id = (int)($_POST['os_id'] ?? $_GET['os_id'] ?? 0);

        if (!$os_id) {
            throw new Exception('O.S. ID obrigatório');
        }

        // Busca dados da O.S.
        $stmt = $db->prepare("
            SELECT
                os.id, os.numero, os.valor_venda, os.prioridade,
                c.razao_social, c.id as cliente_id,
                COUNT(DISTINCT ose.id) as total_etapas
            FROM ordens_servico os
            JOIN clientes c ON os.cliente_id = c.id
            LEFT JOIN os_etapas_producao ose ON os.id = ose.os_id
            WHERE os.id = ?
            GROUP BY os.id
        ");
        $stmt->execute([$os_id]);
        $os = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$os) {
            throw new Exception('O.S. não encontrada');
        }

        // ==== 1. CUSTO DE MÃO DE OBRA ====
        $stmt = $db->prepare("
            SELECT
                ose.id,
                ose.etapa,
                ose.usuario_id,
                u.nome as usuario_nome,
                u.valor_hora_producao,
                ose.data_inicio,
                ose.data_fim,
                (SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, data_inicio, data_fim)), 0)
                 FROM os_etapas_producao
                 WHERE os_id = ? AND etapa = ose.etapa) as tempo_total_segundos
            FROM os_etapas_producao ose
            LEFT JOIN usuarios u ON ose.usuario_id = u.id
            WHERE ose.os_id = ? AND ose.status = 'concluida'
        ");
        $stmt->execute([$os_id, $os_id]);
        $etapas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $custo_mao_obra = 0;
        $detalhes_mao_obra = [];

        foreach ($etapas as $etapa) {
            $valor_hora = $etapa['valor_hora_producao'] ?? 50; // padrão se não houver
            $tempo_horas = $etapa['tempo_total_segundos'] / 3600;
            $custo_etapa = $tempo_horas * $valor_hora;
            $custo_mao_obra += $custo_etapa;

            $detalhes_mao_obra[] = [
                'etapa' => $etapa['etapa'],
                'usuario' => $etapa['usuario_nome'] ?? 'Não atribuído',
                'tempo_horas' => round($tempo_horas, 2),
                'valor_hora' => $valor_hora,
                'custo' => $custo_etapa
            ];
        }

        // ==== 2. CUSTO DE MATERIAIS ====
        $stmt = $db->prepare("
            SELECT
                rm.produto_id,
                p.nome as produto_nome,
                rm.quantidade_planejada,
                SUM(em.quantidade) as quantidade_consumida,
                p.valor_custo_unitario,
                rm.requisicao_id
            FROM requisicoes_materiais rm
            JOIN produtos p ON rm.produto_id = p.id
            LEFT JOIN estoque_movimentacoes em ON rm.requisicao_id = em.referencia_id AND em.tipo = 'saida'
            WHERE rm.os_id = ?
            GROUP BY rm.requisicao_id
        ");
        $stmt->execute([$os_id]);
        $materiais = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $custo_materiais = 0;
        $custo_consumo_real = 0;
        $detalhes_materiais = [];

        foreach ($materiais as $mat) {
            $custo_planejado = $mat['quantidade_planejada'] * ($mat['valor_custo_unitario'] ?? 0);
            $consumido = $mat['quantidade_consumida'] ?? 0;
            $custo_real = $consumido * ($mat['valor_custo_unitario'] ?? 0);

            $custo_materiais += $custo_planejado;
            $custo_consumo_real += $custo_real;

            $variacao = $custo_real - $custo_planejado;
            $variacao_percentual = $custo_planejado > 0 ? ($variacao / $custo_planejado) * 100 : 0;

            $detalhes_materiais[] = [
                'produto' => $mat['produto_nome'],
                'quantidade_planejada' => (float)$mat['quantidade_planejada'],
                'quantidade_consumida' => (float)$consumido,
                'valor_unitario' => (float)$mat['valor_custo_unitario'],
                'custo_planejado' => $custo_planejado,
                'custo_real' => $custo_real,
                'variacao' => $variacao,
                'variacao_percentual' => round($variacao_percentual, 2),
                'status' => abs($variacao_percentual) <= 5 ? 'dentro_previsto' : 'fora_previsto'
            ];
        }

        // ==== 3. OVERHEAD ====
        // Overhead = 15% dos custos diretos
        $overhead = ($custo_mao_obra + $custo_consumo_real) * 0.15;

        // ==== 4. CUSTO TOTAL ====
        $custo_total = $custo_mao_obra + $custo_consumo_real + $overhead;

        // ==== 5. MARGEM E LUCRATIVIDADE ====
        $valor_venda = (float)$os['valor_venda'];
        $lucro = $valor_venda - $custo_total;
        $margem_percentual = $valor_venda > 0 ? ($lucro / $valor_venda) * 100 : 0;
        $margem_unitaria = $valor_venda - $custo_total;

        // Classificação de lucratividade
        $status_lucratividade = 'excelente';
        if ($margem_percentual < 10) $status_lucratividade = 'crítica';
        elseif ($margem_percentual < 20) $status_lucratividade = 'baixa';
        elseif ($margem_percentual < 35) $status_lucratividade = 'normal';

        $resposta = [
            'sucesso' => true,
            'os_id' => $os_id,
            'os_numero' => $os['numero'],
            'cliente' => $os['razao_social'],
            'resumo' => [
                'valor_venda' => round($valor_venda, 2),
                'custo_mao_obra' => round($custo_mao_obra, 2),
                'custo_materiais' => round($custo_consumo_real, 2),
                'overhead' => round($overhead, 2),
                'custo_total' => round($custo_total, 2),
                'lucro_bruto' => round($lucro, 2),
                'margem_percentual' => round($margem_percentual, 2),
                'status_lucratividade' => $status_lucratividade
            ],
            'detalhes_mao_obra' => $detalhes_mao_obra,
            'detalhes_materiais' => $detalhes_materiais
        ];

        echo json_encode($resposta);
    }

    // ===== AÇÃO: LISTAR CUSTOS (TODAS AS O.S.) =====
    elseif ($acao === 'listar_custos') {
        $filtro_cliente = $_GET['cliente_id'] ?? null;
        $filtro_mes = $_GET['mes'] ?? date('Y-m');

        // Query base (simplificada para performance)
        $where = "WHERE DATE_FORMAT(os.created_at, '%Y-%m') = ?";
        $params = [$filtro_mes];

        if ($filtro_cliente) {
            $where .= " AND os.cliente_id = ?";
            $params[] = $filtro_cliente;
        }

        $stmt = $db->prepare("
            SELECT
                os.id,
                os.numero,
                c.razao_social,
                os.valor_venda,
                os.status,
                os.data_prevista,
                (SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, data_inicio, data_fim)), 0) * 50 / 3600
                 FROM os_etapas_producao
                 WHERE os_id = os.id AND status = 'concluida') as custo_mao_obra_estimado,
                (SELECT COALESCE(SUM(quantidade), 0) * 10
                 FROM estoque_movimentacoes
                 WHERE tipo = 'saida' AND created_at >= os.created_at AND created_at <= IFNULL(os.data_termino, NOW())) as custo_materiais_estimado
            FROM ordens_servico os
            JOIN clientes c ON os.cliente_id = c.id
            $where
            ORDER BY os.data_prevista DESC
        ");

        $stmt->execute($params);
        $os_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_venda = 0;
        $total_custo = 0;
        $custos_detail = [];

        foreach ($os_list as $os) {
            $custo_mao_obra = (float)$os['custo_mao_obra_estimado'] ?? 0;
            $custo_materiais = (float)$os['custo_materiais_estimado'] ?? 0;
            $overhead = ($custo_mao_obra + $custo_materiais) * 0.15;
            $custo_total = $custo_mao_obra + $custo_materiais + $overhead;
            $valor_venda = (float)$os['valor_venda'];
            $lucro = $valor_venda - $custo_total;
            $margem = $valor_venda > 0 ? ($lucro / $valor_venda) * 100 : 0;

            $total_venda += $valor_venda;
            $total_custo += $custo_total;

            $custos_detail[] = [
                'os_id' => $os['id'],
                'os_numero' => $os['numero'],
                'cliente' => $os['razao_social'],
                'valor_venda' => round($valor_venda, 2),
                'custo_total' => round($custo_total, 2),
                'lucro' => round($lucro, 2),
                'margem_percentual' => round($margem, 2),
                'status' => $os['status'],
                'data_prevista' => $os['data_prevista']
            ];
        }

        $lucro_total = $total_venda - $total_custo;
        $margem_geral = $total_venda > 0 ? ($lucro_total / $total_venda) * 100 : 0;

        echo json_encode([
            'sucesso' => true,
            'periodo' => $filtro_mes,
            'total_os' => count($custos_detail),
            'total_venda' => round($total_venda, 2),
            'total_custo' => round($total_custo, 2),
            'lucro_total' => round($lucro_total, 2),
            'margem_geral_percentual' => round($margem_geral, 2),
            'custos' => $custos_detail
        ]);
    }

    // ===== AÇÃO: MARGEM POR CLIENTE =====
    elseif ($acao === 'margem_por_cliente') {
        $stmt = $db->prepare("
            SELECT
                c.id,
                c.razao_social,
                COUNT(os.id) as total_os,
                SUM(os.valor_venda) as valor_total_venda,
                COALESCE(SUM(TIMESTAMPDIFF(SECOND, ose.data_inicio, ose.data_fim)), 0) * 50 / 3600 as custo_mao_obra
            FROM clientes c
            LEFT JOIN ordens_servico os ON c.id = os.cliente_id
            LEFT JOIN os_etapas_producao ose ON os.id = ose.os_id AND ose.status = 'concluida'
            WHERE c.status = 'ativo'
            GROUP BY c.id
            HAVING total_os > 0
            ORDER BY valor_total_venda DESC
            LIMIT 20
        ");
        $stmt->execute();
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $margens = [];
        foreach ($clientes as $cliente) {
            $valor_venda = (float)$cliente['valor_total_venda'];
            $custo_mao_obra = (float)$cliente['custo_mao_obra'] ?? 0;
            $overhead = $custo_mao_obra * 0.15;
            $custo_total = $custo_mao_obra + $overhead;

            $margem = $valor_venda > 0 ? (($valor_venda - $custo_total) / $valor_venda) * 100 : 0;

            $margens[] = [
                'cliente_id' => $cliente['id'],
                'cliente' => $cliente['razao_social'],
                'total_os' => $cliente['total_os'],
                'valor_venda_total' => round($valor_venda, 2),
                'custo_medio' => round($custo_total / $cliente['total_os'], 2),
                'margem_percentual' => round($margem, 2),
                'margem_status' => $margem > 30 ? 'excelente' : ($margem > 20 ? 'boa' : ($margem > 10 ? 'normal' : 'crítica'))
            ];
        }

        usort($margens, fn($a, $b) => $b['margem_percentual'] <=> $a['margem_percentual']);

        echo json_encode([
            'sucesso' => true,
            'total_clientes' => count($margens),
            'margens' => $margens
        ]);
    }

    // ===== AÇÃO: ANÁLISE VARIAÇÃO (PLANEJADO vs REAL) =====
    elseif ($acao === 'variacao_planejado_real') {
        $os_id = (int)($_GET['os_id'] ?? 0);

        if (!$os_id) {
            throw new Exception('O.S. ID obrigatório');
        }

        // Busca informações de planejamento vs real
        $stmt = $db->prepare("
            SELECT
                'Mão de Obra' as categoria,
                (SELECT COUNT(*) FROM os_etapas_producao WHERE os_id = ?) as quantidade_planejada,
                (SELECT COUNT(*) FROM os_etapas_producao WHERE os_id = ? AND status = 'concluida') as quantidade_real
            UNION ALL
            SELECT
                'Materiais' as categoria,
                (SELECT COUNT(*) FROM requisicoes_materiais WHERE os_id = ?) as quantidade_planejada,
                (SELECT COUNT(*) FROM estoque_movimentacoes WHERE tipo = 'saida') as quantidade_real
        ");
        $stmt->execute([$os_id, $os_id, $os_id]);
        $variacao_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'sucesso' => true,
            'os_id' => $os_id,
            'variacao' => $variacao_data
        ]);
    }

    else {
        throw new Exception('Ação não encontrada: ' . $acao);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['erro' => $e->getMessage()]);
}
