<?php
/**
 * API de Chamados SAC - Atendimento ao Cliente
 *
 * POST /api/chamados.php
 * - acao=criar → novo chamado
 * - acao=atualizar_status → muda status
 * - acao=atualizar_prioridade → muda prioridade
 * - acao=listar → lista chamados com filtros
 * - acao=obter → detalhes de um chamado
 * - acao=adicionar_resposta → responde ao cliente
 */

require_once '../config/config.php';
require_once '../includes/workflow.php';

header('Content-Type: application/json');
$db = getDB();
requirePermission(['master', 'gerente', 'sac', 'dashboard_producao']);

// Criar tabelas se não existirem
$db->exec("CREATE TABLE IF NOT EXISTS chamados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(20) UNIQUE NOT NULL,
    cliente_id INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT NOT NULL,
    prioridade ENUM('baixa', 'media', 'alta', 'critica') DEFAULT 'media',
    categoria VARCHAR(50),
    status ENUM('novo', 'aberto', 'aguardando_cliente', 'em_atendimento', 'resolvido', 'fechado') DEFAULT 'novo',
    usuario_responsavel_id INT,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    data_resolucao TIMESTAMP NULL,
    tempo_resposta_horas INT,
    usuario_id_criador INT,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_responsavel_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (usuario_id_criador) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_cliente (cliente_id),
    INDEX idx_status (status),
    INDEX idx_prioridade (prioridade),
    INDEX idx_data (data_criacao)
)");

$db->exec("CREATE TABLE IF NOT EXISTS chamados_respostas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chamado_id INT NOT NULL,
    usuario_id INT,
    mensagem TEXT NOT NULL,
    tipo ENUM('interno', 'cliente') DEFAULT 'interno',
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chamado_id) REFERENCES chamados(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_chamado (chamado_id)
)");

$acao = $_POST['acao'] ?? $_GET['acao'] ?? null;

// ===== CRIAR NOVO CHAMADO =====
if ($acao === 'criar') {
    $cliente_id = (int)($_POST['cliente_id'] ?? 0);
    $titulo = sanitize($_POST['titulo'] ?? '');
    $descricao = sanitize($_POST['descricao'] ?? '');
    $prioridade = in_array($_POST['prioridade'] ?? '', ['baixa', 'media', 'alta', 'critica']) ? $_POST['prioridade'] : 'media';
    $categoria = sanitize($_POST['categoria'] ?? '');

    if (!$cliente_id || !$titulo || !$descricao) {
        http_response_code(400);
        echo json_encode(['erro' => 'cliente_id, titulo e descricao são obrigatórios']);
        exit;
    }

    // Gerar número sequencial
    $stmt = $db->query("SELECT COUNT(*) as total FROM chamados");
    $total = $stmt->fetch()['total'] + 1;
    $numero = 'CHA-' . str_pad(date('Y') . str_pad($total, 5, '0', STR_PAD_LEFT), 10, '0', STR_PAD_LEFT);

    try {
        $stmt = $db->prepare("INSERT INTO chamados
            (numero, cliente_id, titulo, descricao, prioridade, categoria, status, usuario_id_criador)
            VALUES (?, ?, ?, ?, ?, ?, 'novo', ?)");
        $stmt->execute([$numero, $cliente_id, $titulo, $descricao, $prioridade, $categoria, $_SESSION['usuario_id']]);

        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Chamado criado com sucesso',
            'chamado_id' => $db->lastInsertId(),
            'numero' => $numero
        ]);
    } catch (\Exception $e) {
        http_response_code(400);
        echo json_encode(['erro' => $e->getMessage()]);
    }
    exit;
}

// ===== ATUALIZAR STATUS =====
if ($acao === 'atualizar_status') {
    $chamado_id = (int)($_POST['chamado_id'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['novo', 'aberto', 'aguardando_cliente', 'em_atendimento', 'resolvido', 'fechado']) ? $_POST['status'] : null;

    if (!$chamado_id || !$status) {
        http_response_code(400);
        echo json_encode(['erro' => 'chamado_id e status são obrigatórios']);
        exit;
    }

    $data_resolucao = '';
    if ($status === 'resolvido') {
        $data_resolucao = ', data_resolucao = NOW()';
    }

    $stmt = $db->prepare("UPDATE chamados SET status = ? $data_resolucao, data_atualizacao = NOW() WHERE id = ?");
    $stmt->execute([$status, $chamado_id]);

    echo json_encode(['sucesso' => true, 'mensagem' => 'Status atualizado']);
    exit;
}

// ===== ATUALIZAR PRIORIDADE =====
if ($acao === 'atualizar_prioridade') {
    $chamado_id = (int)($_POST['chamado_id'] ?? 0);
    $prioridade = in_array($_POST['prioridade'] ?? '', ['baixa', 'media', 'alta', 'critica']) ? $_POST['prioridade'] : null;

    if (!$chamado_id || !$prioridade) {
        http_response_code(400);
        echo json_encode(['erro' => 'chamado_id e prioridade são obrigatórios']);
        exit;
    }

    $stmt = $db->prepare("UPDATE chamados SET prioridade = ?, data_atualizacao = NOW() WHERE id = ?");
    $stmt->execute([$prioridade, $chamado_id]);

    echo json_encode(['sucesso' => true, 'mensagem' => 'Prioridade atualizada']);
    exit;
}

// ===== ATRIBUIR RESPONSÁVEL =====
if ($acao === 'atribuir') {
    $chamado_id = (int)($_POST['chamado_id'] ?? 0);
    $usuario_id = (int)($_POST['usuario_id'] ?? 0);

    if (!$chamado_id || !$usuario_id) {
        http_response_code(400);
        echo json_encode(['erro' => 'chamado_id e usuario_id são obrigatórios']);
        exit;
    }

    $stmt = $db->prepare("UPDATE chamados SET usuario_responsavel_id = ?, status = 'aberto', data_atualizacao = NOW() WHERE id = ?");
    $stmt->execute([$usuario_id, $chamado_id]);

    echo json_encode(['sucesso' => true, 'mensagem' => 'Responsável atribuído']);
    exit;
}

// ===== LISTAR CHAMADOS COM FILTROS =====
if ($acao === 'listar') {
    $status = $_GET['status'] ?? $_POST['status'] ?? null;
    $prioridade = $_GET['prioridade'] ?? $_POST['prioridade'] ?? null;
    $cliente_id = (int)($_GET['cliente_id'] ?? $_POST['cliente_id'] ?? 0);
    $dias = (int)($_GET['dias'] ?? 30);

    $query = "SELECT c.*, cl.razao_social, u.nome as usuario_responsavel
        FROM chamados c
        INNER JOIN clientes cl ON c.cliente_id = cl.id
        LEFT JOIN usuarios u ON c.usuario_responsavel_id = u.id
        WHERE c.data_criacao >= DATE_SUB(NOW(), INTERVAL ? DAY)";

    $params = [$dias];

    if ($status) {
        $query .= " AND c.status = ?";
        $params[] = $status;
    }

    if ($prioridade) {
        $query .= " AND c.prioridade = ?";
        $params[] = $prioridade;
    }

    if ($cliente_id) {
        $query .= " AND c.cliente_id = ?";
        $params[] = $cliente_id;
    }

    $query .= " ORDER BY
        CASE WHEN c.prioridade = 'critica' THEN 1
             WHEN c.prioridade = 'alta' THEN 2
             WHEN c.prioridade = 'media' THEN 3
             ELSE 4 END,
        c.data_criacao DESC
        LIMIT 100";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $chamados = $stmt->fetchAll();

    // Calcular tempo de resposta
    foreach ($chamados as &$c) {
        if ($c['data_resolucao']) {
            $inicio = new DateTime($c['data_criacao']);
            $fim = new DateTime($c['data_resolucao']);
            $intervalo = $inicio->diff($fim);
            $c['tempo_resolucao'] = $intervalo->format('%h horas %i min');
        }
    }

    echo json_encode([
        'sucesso' => true,
        'total' => count($chamados),
        'chamados' => $chamados
    ]);
    exit;
}

// ===== OBTER DETALHES =====
if ($acao === 'obter') {
    $chamado_id = (int)($_GET['chamado_id'] ?? $_POST['chamado_id'] ?? 0);

    if (!$chamado_id) {
        http_response_code(400);
        echo json_encode(['erro' => 'chamado_id é obrigatório']);
        exit;
    }

    $stmt = $db->prepare("SELECT c.*, cl.razao_social, u.nome as usuario_responsavel
        FROM chamados c
        INNER JOIN clientes cl ON c.cliente_id = cl.id
        LEFT JOIN usuarios u ON c.usuario_responsavel_id = u.id
        WHERE c.id = ?");
    $stmt->execute([$chamado_id]);
    $chamado = $stmt->fetch();

    if (!$chamado) {
        http_response_code(404);
        echo json_encode(['erro' => 'Chamado não encontrado']);
        exit;
    }

    // Obter respostas
    $stmt = $db->prepare("SELECT cr.*, u.nome as usuario_nome
        FROM chamados_respostas cr
        LEFT JOIN usuarios u ON cr.usuario_id = u.id
        WHERE cr.chamado_id = ?
        ORDER BY cr.data_criacao DESC");
    $stmt->execute([$chamado_id]);
    $chamado['respostas'] = $stmt->fetchAll();

    echo json_encode([
        'sucesso' => true,
        'chamado' => $chamado
    ]);
    exit;
}

// ===== ADICIONAR RESPOSTA =====
if ($acao === 'adicionar_resposta') {
    $chamado_id = (int)($_POST['chamado_id'] ?? 0);
    $mensagem = sanitize($_POST['mensagem'] ?? '');
    $tipo = in_array($_POST['tipo'] ?? '', ['interno', 'cliente']) ? $_POST['tipo'] : 'interno';

    if (!$chamado_id || !$mensagem) {
        http_response_code(400);
        echo json_encode(['erro' => 'chamado_id e mensagem são obrigatórios']);
        exit;
    }

    try {
        $stmt = $db->prepare("INSERT INTO chamados_respostas (chamado_id, usuario_id, mensagem, tipo)
            VALUES (?, ?, ?, ?)");
        $stmt->execute([$chamado_id, $_SESSION['usuario_id'], $mensagem, $tipo]);

        // Se resposta é ao cliente, atualizar status
        if ($tipo === 'cliente') {
            $stmt = $db->prepare("UPDATE chamados SET status = 'aguardando_cliente', data_atualizacao = NOW() WHERE id = ?");
            $stmt->execute([$chamado_id]);
        }

        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Resposta adicionada',
            'resposta_id' => $db->lastInsertId()
        ]);
    } catch (\Exception $e) {
        http_response_code(400);
        echo json_encode(['erro' => $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['erro' => 'Ação não especificada']);
