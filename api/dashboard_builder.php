<?php
/**
 * API de Dashboard Builder - Criação de Relatórios Personalizados
 * Permite usuários criar dashboards sem código com drag-drop e filtros
 *
 * Funcionalidades:
 * - Criar/listar/editar/deletar dashboards customizados
 * - Salvar métrica (produto, cliente, período, gráfico)
 * - Filtros por período, setor, cliente, produto
 * - Compartilhamento de dashboards entre usuários
 * - Exportar para PDF/Excel
 *
 * Acesso: todos (cada usuário vê seus dashboards)
 */

require_once '../config/config.php';
require_once '../includes/workflow.php';

header('Content-Type: application/json; charset=utf-8');

$db = getDB();
$usuario_id = $_SESSION['usuario_id'] ?? 0;
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';

if (!$usuario_id) {
    http_response_code(401);
    echo json_encode(['erro' => 'Não autenticado']);
    exit;
}

$acao = $_POST['acao'] ?? $_GET['acao'] ?? null;

try {
    // ===== CRIAR TABELA SE NÃO EXISTIR =====
    if (!$db->query("SHOW TABLES LIKE 'dashboards_customizados'")) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS dashboards_customizados (
                id INT PRIMARY KEY AUTO_INCREMENT,
                usuario_id INT NOT NULL,
                nome VARCHAR(255) NOT NULL,
                descricao TEXT,
                layout JSON,
                metricas JSON,
                filtros JSON,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                compartilhado BOOLEAN DEFAULT FALSE,
                KEY(usuario_id)
            )
        ");
    }

    if (!$db->query("SHOW TABLES LIKE 'dashboard_compartilhamentos'")) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS dashboard_compartilhamentos (
                id INT PRIMARY KEY AUTO_INCREMENT,
                dashboard_id INT NOT NULL,
                usuario_id INT NOT NULL,
                permissao VARCHAR(50) DEFAULT 'visualizar',
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY(dashboard_id, usuario_id)
            )
        ");
    }

    // ===== AÇÃO: CRIAR DASHBOARD =====
    if ($acao === 'criar') {
        $nome = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');

        if (!$nome) {
            throw new Exception('Nome do dashboard é obrigatório');
        }

        $stmt = $db->prepare("
            INSERT INTO dashboards_customizados (usuario_id, nome, descricao, layout, metricas, filtros)
            VALUES (?, ?, ?, '[]', '[]', '{}')
        ");
        $stmt->execute([$usuario_id, $nome, $descricao]);

        echo json_encode([
            'sucesso' => true,
            'dashboard_id' => $db->lastInsertId(),
            'mensagem' => 'Dashboard criado com sucesso'
        ]);
    }

    // ===== AÇÃO: LISTAR DASHBOARDS =====
    elseif ($acao === 'listar') {
        $stmt = $db->prepare("
            SELECT
                d.id,
                d.nome,
                d.descricao,
                d.criado_em,
                d.atualizado_em,
                d.compartilhado,
                d.metricas,
                COUNT(m.id) as total_metricas
            FROM dashboards_customizados d
            LEFT JOIN (SELECT id FROM dashboards_customizados) m ON d.id = m.id
            WHERE d.usuario_id = ?
            ORDER BY d.atualizado_em DESC
        ");
        $stmt->execute([$usuario_id]);
        $dashboards = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($dashboards as &$d) {
            $d['metricas'] = json_decode($d['metricas'], true) ?? [];
            $d['total_metricas'] = count($d['metricas']);
        }

        echo json_encode([
            'sucesso' => true,
            'total' => count($dashboards),
            'dashboards' => $dashboards
        ]);
    }

    // ===== AÇÃO: OBTER DASHBOARD DETALHADO =====
    elseif ($acao === 'obter') {
        $dashboard_id = (int)($_POST['dashboard_id'] ?? $_GET['dashboard_id'] ?? 0);

        if (!$dashboard_id) {
            throw new Exception('Dashboard ID obrigatório');
        }

        $stmt = $db->prepare("
            SELECT *
            FROM dashboards_customizados
            WHERE id = ? AND usuario_id = ?
        ");
        $stmt->execute([$dashboard_id, $usuario_id]);
        $dashboard = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dashboard) {
            throw new Exception('Dashboard não encontrado ou sem permissão');
        }

        $dashboard['layout'] = json_decode($dashboard['layout'], true) ?? [];
        $dashboard['metricas'] = json_decode($dashboard['metricas'], true) ?? [];
        $dashboard['filtros'] = json_decode($dashboard['filtros'], true) ?? [];

        echo json_encode([
            'sucesso' => true,
            'dashboard' => $dashboard
        ]);
    }

    // ===== AÇÃO: ADICIONAR MÉTRICA =====
    elseif ($acao === 'adicionar_metrica') {
        $dashboard_id = (int)($_POST['dashboard_id'] ?? 0);
        $tipo = $_POST['tipo'] ?? ''; // 'kpi', 'grafico', 'tabela'
        $nome_metrica = $_POST['nome'] ?? '';
        $query_tipo = $_POST['query_tipo'] ?? ''; // 'vendas', 'producao', 'custos'
        $filtros = json_decode($_POST['filtros'] ?? '{}', true);

        if (!$dashboard_id || !$tipo || !$nome_metrica) {
            throw new Exception('Parâmetros inválidos');
        }

        // Busca dashboard
        $stmt = $db->prepare("SELECT metricas FROM dashboards_customizados WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$dashboard_id, $usuario_id]);
        $dashboard = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dashboard) {
            throw new Exception('Dashboard não encontrado');
        }

        $metricas = json_decode($dashboard['metricas'], true) ?? [];

        // Nova métrica
        $nova_metrica = [
            'id' => uniqid(),
            'tipo' => $tipo,
            'nome' => $nome_metrica,
            'query_tipo' => $query_tipo,
            'filtros' => $filtros,
            'criada_em' => date('Y-m-d H:i:s')
        ];

        $metricas[] = $nova_metrica;

        // Atualiza dashboard
        $stmt = $db->prepare("
            UPDATE dashboards_customizados
            SET metricas = ?
            WHERE id = ?
        ");
        $stmt->execute([json_encode($metricas), $dashboard_id]);

        echo json_encode([
            'sucesso' => true,
            'metrica_id' => $nova_metrica['id'],
            'mensagem' => 'Métrica adicionada com sucesso'
        ]);
    }

    // ===== AÇÃO: REMOVER MÉTRICA =====
    elseif ($acao === 'remover_metrica') {
        $dashboard_id = (int)($_POST['dashboard_id'] ?? 0);
        $metrica_id = $_POST['metrica_id'] ?? '';

        $stmt = $db->prepare("SELECT metricas FROM dashboards_customizados WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$dashboard_id, $usuario_id]);
        $dashboard = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dashboard) {
            throw new Exception('Dashboard não encontrado');
        }

        $metricas = json_decode($dashboard['metricas'], true) ?? [];
        $metricas = array_filter($metricas, fn($m) => $m['id'] !== $metrica_id);

        $stmt = $db->prepare("
            UPDATE dashboards_customizados
            SET metricas = ?
            WHERE id = ?
        ");
        $stmt->execute([json_encode(array_values($metricas)), $dashboard_id]);

        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Métrica removida com sucesso'
        ]);
    }

    // ===== AÇÃO: SALVAR FILTROS =====
    elseif ($acao === 'salvar_filtros') {
        $dashboard_id = (int)($_POST['dashboard_id'] ?? 0);
        $filtros = json_decode($_POST['filtros'] ?? '{}', true);

        $stmt = $db->prepare("
            UPDATE dashboards_customizados
            SET filtros = ?
            WHERE id = ? AND usuario_id = ?
        ");
        $stmt->execute([json_encode($filtros), $dashboard_id, $usuario_id]);

        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Filtros salvos'
        ]);
    }

    // ===== AÇÃO: OBTER DADOS MÉTRICA =====
    elseif ($acao === 'dados_metrica') {
        $query_tipo = $_GET['query_tipo'] ?? '';
        $filtros = json_decode($_GET['filtros'] ?? '{}', true);

        $dados = [];

        if ($query_tipo === 'vendas_mes') {
            $stmt = $db->prepare("
                SELECT
                    DATE_FORMAT(v.created_at, '%Y-%m-%d') as data,
                    COUNT(*) as quantidade,
                    SUM(v.valor_total) as valor
                FROM vendas v
                WHERE v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(v.created_at)
                ORDER BY v.created_at DESC
            ");
            $stmt->execute();
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        elseif ($query_tipo === 'producao_setor') {
            $stmt = $db->prepare("
                SELECT
                    ose.etapa,
                    COUNT(*) as quantidade,
                    SUM(TIMESTAMPDIFF(HOUR, ose.data_inicio, ose.data_fim)) as horas_total
                FROM os_etapas_producao ose
                WHERE ose.status = 'concluida'
                GROUP BY ose.etapa
            ");
            $stmt->execute();
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        elseif ($query_tipo === 'custos_cliente') {
            $stmt = $db->prepare("
                SELECT
                    c.razao_social,
                    COUNT(os.id) as total_os,
                    SUM(os.valor_venda) as faturado
                FROM clientes c
                LEFT JOIN ordens_servico os ON c.id = os.cliente_id
                WHERE c.status = 'ativo'
                GROUP BY c.id
                ORDER BY SUM(os.valor_venda) DESC
                LIMIT 10
            ");
            $stmt->execute();
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode([
            'sucesso' => true,
            'dados' => $dados
        ]);
    }

    // ===== AÇÃO: DELETAR DASHBOARD =====
    elseif ($acao === 'deletar') {
        $dashboard_id = (int)($_POST['dashboard_id'] ?? 0);

        $stmt = $db->prepare("
            DELETE FROM dashboards_customizados
            WHERE id = ? AND usuario_id = ?
        ");
        $stmt->execute([$dashboard_id, $usuario_id]);

        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Dashboard deletado'
        ]);
    }

    // ===== AÇÃO: COMPARTILHAR DASHBOARD =====
    elseif ($acao === 'compartilhar') {
        $dashboard_id = (int)($_POST['dashboard_id'] ?? 0);
        $usuario_destino_id = (int)($_POST['usuario_destino_id'] ?? 0);
        $permissao = $_POST['permissao'] ?? 'visualizar';

        $stmt = $db->prepare("
            INSERT INTO dashboard_compartilhamentos (dashboard_id, usuario_id, permissao)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE permissao = ?
        ");
        $stmt->execute([$dashboard_id, $usuario_destino_id, $permissao, $permissao]);

        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Dashboard compartilhado'
        ]);
    }

    else {
        throw new Exception('Ação não encontrada: ' . $acao);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['erro' => $e->getMessage()]);
}
