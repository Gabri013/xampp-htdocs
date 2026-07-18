<?php
/**
 * API de MRP (Material Requirements Planning)
 * Planejamento automático de produção com análise inteligente
 *
 * Funcionalidades:
 * - Análise demanda vs estoque (comparação real-time)
 * - Sugestão automática de ordens (baseado em faltantes)
 * - Previsão de matérias-primas (cálculo BOM × quantidade)
 * - Otimização de cronograma (sequenciação inteligente)
 * - Alertas de falta de material (antes de faltar)
 *
 * Acesso: master, gerente, producao
 */

require_once '../config/config.php';
require_once '../includes/workflow.php';

header('Content-Type: application/json; charset=utf-8');

$db = getDB();
requirePermission(['master', 'gerente', 'producao']);

// Garante as tabelas que o MRP consulta (mesmas defs de estoque_movimentacoes.php
// e bom.php). Este ERP é make-to-order: estoque_saldos costuma ficar vazio, então
// o estoque aparece como 0 — comportamento correto para produção sob demanda.
$db->exec("CREATE TABLE IF NOT EXISTS estoque_saldos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL,
    quantidade_total DECIMAL(10,2) DEFAULT 0,
    quantidade_minima DECIMAL(10,2) DEFAULT 0,
    quantidade_maxima DECIMAL(10,2) DEFAULT 0,
    localizacao VARCHAR(100),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_produto (produto_id)
) ENGINE=InnoDB");
$db->exec("CREATE TABLE IF NOT EXISTS produtos_bom (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_principal_id INT NOT NULL,
    material_id INT NOT NULL,
    quantidade DECIMAL(10,3) NOT NULL DEFAULT 1,
    unidade VARCHAR(20) DEFAULT 'un',
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_bom_principal (produto_principal_id)
) ENGINE=InnoDB");

$acao = $_POST['acao'] ?? $_GET['acao'] ?? null;

try {
    // ===== AÇÃO: ANALISAR DEMANDA =====
    if ($acao === 'analisar_demanda') {
        // Pega todas as vendas não transformadas em O.S. + O.S. em andamento
        $stmt = $db->prepare("
            SELECT
                v.id as venda_id,
                v.numero as venda_numero,
                v.valor_total,
                COALESCE(v.data_recebimento_prevista, v.data_venda) as data_prevista,
                c.razao_social,
                p.id as produto_id,
                p.nome as produto_nome,
                vi.quantidade as quantidade_solicitada,
                (SELECT COALESCE(SUM(quantidade_total), 0)
                 FROM estoque_saldos
                 WHERE produto_id = p.id) as estoque_atual
            FROM vendas v
            JOIN clientes c ON v.cliente_id = c.id
            JOIN vendas_itens vi ON v.id = vi.venda_id
            JOIN produtos p ON vi.produto_id = p.id
            WHERE v.status = 'em_andamento'
            ORDER BY data_prevista ASC, v.id DESC
        ");
        $stmt->execute();
        $vendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calcula faltantes e score de urgência
        $demanda_analise = [];
        foreach ($vendas as $v) {
            $faltante = $v['quantidade_solicitada'] - $v['estoque_atual'];
            if ($faltante > 0) {
                $dias_para_entrega = (new DateTime($v['data_prevista']))->diff(new DateTime())->days;
                $urgencia_score = (100 * $faltante) / $v['quantidade_solicitada']; // % de falta
                $urgencia_score += (10 * max(0, 3 - $dias_para_entrega)); // bônus se urgente

                $demanda_analise[] = [
                    'venda_id' => $v['venda_id'],
                    'venda_numero' => $v['venda_numero'],
                    'cliente' => $v['razao_social'],
                    'produto_id' => $v['produto_id'],
                    'produto_nome' => $v['produto_nome'],
                    'quantidade_solicitada' => $v['quantidade_solicitada'],
                    'estoque_atual' => $v['estoque_atual'],
                    'faltante' => $faltante,
                    'percentual_falta' => round(($faltante / $v['quantidade_solicitada']) * 100, 1),
                    'dias_para_entrega' => $dias_para_entrega,
                    'urgencia_score' => round($urgencia_score, 2),
                    'status_urgencia' => $urgencia_score > 50 ? 'crítica' : ($urgencia_score > 20 ? 'alta' : 'normal')
                ];
            }
        }

        usort($demanda_analise, fn($a, $b) => $b['urgencia_score'] <=> $a['urgencia_score']);

        echo json_encode([
            'sucesso' => true,
            'total' => count($demanda_analise),
            'total_valor_faltante' => count($demanda_analise), // produtos faltando
            'criticas' => count(array_filter($demanda_analise, fn($d) => $d['status_urgencia'] === 'crítica')),
            'demanda' => $demanda_analise
        ]);
    }

    // ===== AÇÃO: SUGERIR ORDENS =====
    elseif ($acao === 'sugerir_ordens') {
        // Pega demanda + calcula sugestões inteligentes
        $stmt = $db->prepare("
            SELECT
                vi.produto_id,
                p.nome as produto_nome,
                0 as estoque_minimo,
                0 as estoque_maximo,
                SUM(vi.quantidade) as quantidade_total_vendas,
                (SELECT COALESCE(SUM(quantidade_total), 0) FROM estoque_saldos WHERE produto_id = p.id) as estoque_atual
            FROM vendas_itens vi
            JOIN produtos p ON vi.produto_id = p.id
            JOIN vendas v ON vi.venda_id = v.id
            WHERE v.status = 'em_andamento'
            GROUP BY p.id
        ");
        $stmt->execute();
        $produtos_demanda = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sugestoes = [];
        foreach ($produtos_demanda as $p) {
            $necessario = max(0, $p['quantidade_total_vendas'] - $p['estoque_atual']);

            if ($necessario > 0) {
                // Quantidade otimizada (com margem de segurança)
                $quantidade_producao = ceil($necessario * 1.15); // +15% de margem

                // Prioridade baseada em urgência + importância
                $prioridade = 'normal';
                if ($necessario > $p['estoque_minimo']) {
                    if ($p['estoque_atual'] == 0) $prioridade = 'crítica';
                    else $prioridade = 'alta';
                }

                $sugestoes[] = [
                    'produto_id' => $p['produto_id'],
                    'produto_nome' => $p['produto_nome'],
                    'estoque_atual' => (float)$p['estoque_atual'],
                    'estoque_minimo' => (float)$p['estoque_minimo'],
                    'quantidade_vendas' => (float)$p['quantidade_total_vendas'],
                    'necessario' => (float)$necessario,
                    'quantidade_sugerida' => (float)$quantidade_producao,
                    'margem_seguranca' => 15,
                    'prioridade' => $prioridade,
                    'acao_recomendada' => 'Criar O.S. de produção'
                ];
            }
        }

        // Ordena por prioridade
        $prioridade_order = ['crítica' => 0, 'alta' => 1, 'normal' => 2];
        usort($sugestoes, fn($a, $b) =>
            ($prioridade_order[$a['prioridade']] <=> $prioridade_order[$b['prioridade']]) ?:
            ($b['quantidade_sugerida'] <=> $a['quantidade_sugerida'])
        );

        echo json_encode([
            'sucesso' => true,
            'total_sugestoes' => count($sugestoes),
            'criticas' => count(array_filter($sugestoes, fn($s) => $s['prioridade'] === 'crítica')),
            'altas' => count(array_filter($sugestoes, fn($s) => $s['prioridade'] === 'alta')),
            'sugestoes' => $sugestoes
        ]);
    }

    // ===== AÇÃO: PREVISÃO MATERIAIS =====
    elseif ($acao === 'prever_materiais') {
        $produto_id = (int)($_POST['produto_id'] ?? 0);
        $quantidade = (float)($_POST['quantidade'] ?? 0);

        if (!$produto_id || $quantidade <= 0) {
            throw new Exception('Produto ou quantidade inválidos');
        }

        // Busca BOM do produto
        $stmt = $db->prepare("
            SELECT
                pb.material_id,
                p.nome as material_nome,
                pb.quantidade as qtd_bom,
                pb.unidade,
                (SELECT COALESCE(SUM(es.quantidade_total), 0) FROM estoque_saldos es WHERE es.produto_id = pb.material_id) as estoque_material
            FROM produtos_bom pb
            JOIN produtos p ON pb.material_id = p.id
            WHERE pb.produto_principal_id = ?
        ");
        $stmt->execute([$produto_id]);
        $bom = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $previsao = [];
        foreach ($bom as $item) {
            $qtd_necessaria = $item['qtd_bom'] * $quantidade;
            $faltante = max(0, $qtd_necessaria - $item['estoque_material']);

            $previsao[] = [
                'material_id' => $item['material_id'],
                'material_nome' => $item['material_nome'],
                'qtd_bom' => (float)$item['qtd_bom'],
                'unidade' => $item['unidade'],
                'quantidade_necessaria' => (float)$qtd_necessaria,
                'estoque_atual' => (float)$item['estoque_material'],
                'faltante' => (float)$faltante,
                'status' => $faltante > 0 ? 'falta' : 'ok'
            ];
        }

        echo json_encode([
            'sucesso' => true,
            'produto_id' => $produto_id,
            'quantidade_producao' => $quantidade,
            'total_materiais' => count($previsao),
            'materiais_faltando' => count(array_filter($previsao, fn($p) => $p['status'] === 'falta')),
            'materiais' => $previsao
        ]);
    }

    // ===== AÇÃO: OTIMIZAR CRONOGRAMA =====
    elseif ($acao === 'otimizar_cronograma') {
        // Busca O.S. em produção + prazos
        $stmt = $db->prepare("
            SELECT
                os.id,
                os.numero,
                COALESCE(os.data_termino, os.data_inicio) as data_prevista,
                os.prioridade,
                c.razao_social,
                COUNT(DISTINCT ose.id) as total_etapas,
                SUM(CASE WHEN ose.status = 'concluida' THEN 1 ELSE 0 END) as etapas_concluidas
            FROM ordens_servico os
            JOIN clientes c ON os.cliente_id = c.id
            LEFT JOIN os_etapas_producao ose ON os.id = ose.os_id
            WHERE os.status = 'em_producao'
            GROUP BY os.id
            ORDER BY data_prevista ASC
        ");
        $stmt->execute();
        $os_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $cronograma = [];
        foreach ($os_list as $os) {
            $dias_para_entrega = (new DateTime($os['data_prevista']))->diff(new DateTime())->days;
            $percentual_conclusao = $os['total_etapas'] > 0 ?
                round(($os['etapas_concluidas'] / $os['total_etapas']) * 100, 0) : 0;

            // Score: quanto menor, mais urgente
            $score_urgencia = $dias_para_entrega * (100 - $percentual_conclusao);

            $cronograma[] = [
                'os_id' => $os['id'],
                'os_numero' => $os['numero'],
                'cliente' => $os['razao_social'],
                'data_prevista' => $os['data_prevista'],
                'dias_faltando' => $dias_para_entrega,
                'prioridade' => $os['prioridade'],
                'progresso_percentual' => $percentual_conclusao,
                'etapas_totais' => $os['total_etapas'],
                'etapas_concluidas' => $os['etapas_concluidas'],
                'score_urgencia' => (float)$score_urgencia,
                'recomendacao' => $dias_para_entrega <= 1 ? 'ACELERAR' :
                                  ($dias_para_entrega <= 3 ? 'FOCAR' : 'EM TEMPO')
            ];
        }

        usort($cronograma, fn($a, $b) => $a['score_urgencia'] <=> $b['score_urgencia']);

        echo json_encode([
            'sucesso' => true,
            'total_os' => count($cronograma),
            'acelerar' => count(array_filter($cronograma, fn($c) => $c['recomendacao'] === 'ACELERAR')),
            'focar' => count(array_filter($cronograma, fn($c) => $c['recomendacao'] === 'FOCAR')),
            'cronograma' => $cronograma
        ]);
    }

    // ===== AÇÃO: ALERTAS CRÍTICOS =====
    elseif ($acao === 'alertas') {
        $alertas = [];

        // Alerta 1: Produtos sem estoque
        // Só alerta produtos com controle de estoque que zeraram. Neste ERP
        // make-to-order, estoque_saldos costuma ficar vazio (produção sob
        // demanda) — nesse caso, sem alertas falsos de "sem estoque".
        $stmt = $db->prepare("
            SELECT p.id, p.nome, es.quantidade_total as quantidade
            FROM produtos p
            INNER JOIN estoque_saldos es ON p.id = es.produto_id
            WHERE p.status = 'ativo' AND es.quantidade_total <= 0
            LIMIT 20
        ");
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $alertas[] = [
                'tipo' => 'produto_sem_estoque',
                'severidade' => 'crítica',
                'titulo' => "Produto sem estoque: {$p['nome']}",
                'descricao' => 'Produto não tem quantidade no estoque',
                'produto_id' => $p['id'],
                'icon' => '🚨'
            ];
        }

        // Alerta 2: O.S. atrasadas
        $stmt = $db->prepare("
            SELECT os.id, os.numero, c.razao_social,
                   DATEDIFF(CURDATE(), os.data_termino) as dias_atraso
            FROM ordens_servico os
            JOIN clientes c ON os.cliente_id = c.id
            WHERE os.status = 'em_producao'
            AND os.data_termino IS NOT NULL AND os.data_termino < CURDATE()
            ORDER BY os.data_termino ASC
            LIMIT 10
        ");
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $os) {
            $severidade = $os['dias_atraso'] > 7 ? 'crítica' : ($os['dias_atraso'] > 3 ? 'alta' : 'média');
            $alertas[] = [
                'tipo' => 'os_atrasada',
                'severidade' => $severidade,
                'titulo' => "O.S. atrasada: {$os['numero']}",
                'descricao' => "Atraso de {$os['dias_atraso']} dias - Cliente: {$os['razao_social']}",
                'os_id' => $os['id'],
                'icon' => '⚠️'
            ];
        }

        usort($alertas, fn($a, $b) =>
            ['crítica' => 0, 'alta' => 1, 'média' => 2][$a['severidade']] <=>
            ['crítica' => 0, 'alta' => 1, 'média' => 2][$b['severidade']]
        );

        echo json_encode([
            'sucesso' => true,
            'total' => count($alertas),
            'criticas' => count(array_filter($alertas, fn($a) => $a['severidade'] === 'crítica')),
            'alertas' => $alertas
        ]);
    }

    else {
        throw new Exception('Ação não encontrada: ' . $acao);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['erro' => $e->getMessage()]);
}
