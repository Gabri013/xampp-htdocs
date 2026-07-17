<?php
/**
 * API de Estoque - Entrada/Saída/Ajuste de produtos
 *
 * POST /api/estoque_movimentacoes.php
 * - acao=entrada → registra entrada de material
 * - acao=saida → registra saída/consumo
 * - acao=ajuste → ajuste de inventário
 * - acao=listar → histórico de movimentações
 * - acao=obter_saldo → saldo atual de um produto
 */

require_once '../config/config.php';
require_once '../includes/workflow.php';

header('Content-Type: application/json');
$db = getDB();
requirePermission(['master', 'gerente', 'producao', 'dashboard_producao']);

// Criar tabelas se não existirem
$db->exec("CREATE TABLE IF NOT EXISTS estoque_saldos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL,
    quantidade_total DECIMAL(10,2) DEFAULT 0,
    quantidade_minima DECIMAL(10,2) DEFAULT 0,
    quantidade_maxima DECIMAL(10,2) DEFAULT 0,
    localizacao VARCHAR(100),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_produto (produto_id),
    INDEX idx_quantidade (quantidade_total)
)");

$db->exec("CREATE TABLE IF NOT EXISTS estoque_movimentacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL,
    tipo ENUM('entrada', 'saida', 'ajuste', 'devolucao') DEFAULT 'entrada',
    quantidade DECIMAL(10,2) NOT NULL,
    referencia VARCHAR(50),
    os_id INT,
    usuario_id INT,
    observacao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE,
    FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE SET NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_produto (produto_id),
    INDEX idx_data (created_at),
    INDEX idx_os (os_id)
)");

$acao = $_POST['acao'] ?? $_GET['acao'] ?? null;

// ===== REGISTRAR MOVIMENTAÇÃO =====
if (in_array($acao, ['entrada', 'saida', 'ajuste', 'devolucao'])) {
    $produto_id = (int)($_POST['produto_id'] ?? 0);
    $quantidade = (float)($_POST['quantidade'] ?? 0);
    $referencia = sanitize($_POST['referencia'] ?? '');
    $os_id = (int)($_POST['os_id'] ?? 0);
    $observacao = sanitize($_POST['observacao'] ?? '');

    if (!$produto_id || !$quantidade) {
        http_response_code(400);
        echo json_encode(['erro' => 'produto_id e quantidade são obrigatórios']);
        exit;
    }

    // Verificar se produto existe
    $stmt = $db->prepare("SELECT id, nome FROM produtos WHERE id = ?");
    $stmt->execute([$produto_id]);
    $produto = $stmt->fetch();

    if (!$produto) {
        http_response_code(404);
        echo json_encode(['erro' => 'Produto não encontrado']);
        exit;
    }

    // Registrar movimentação
    $stmt = $db->prepare("INSERT INTO estoque_movimentacoes
        (produto_id, tipo, quantidade, referencia, os_id, usuario_id, observacao)
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$produto_id, $acao, $quantidade, $referencia, $os_id ?: null, $_SESSION['usuario_id'], $observacao]);

    // Atualizar saldo
    atualizar_saldo_estoque($db, $produto_id);

    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Movimentação registrada com sucesso',
        'movimento_id' => $db->lastInsertId(),
        'saldo_atual' => obter_saldo_estoque($db, $produto_id)
    ]);
    exit;
}

// ===== LISTAR MOVIMENTAÇÕES =====
if ($acao === 'listar') {
    $produto_id = (int)($_GET['produto_id'] ?? $_POST['produto_id'] ?? 0);
    $dias = (int)($_GET['dias'] ?? 30);

    $query = "SELECT em.*, p.nome, u.nome as usuario_nome
        FROM estoque_movimentacoes em
        INNER JOIN produtos p ON em.produto_id = p.id
        LEFT JOIN usuarios u ON em.usuario_id = u.id
        WHERE 1=1";

    $params = [];

    if ($produto_id) {
        $query .= " AND em.produto_id = ?";
        $params[] = $produto_id;
    }

    $query .= " AND em.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ORDER BY em.created_at DESC
        LIMIT 100";
    $params[] = $dias;

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $movimentacoes = $stmt->fetchAll();

    echo json_encode([
        'sucesso' => true,
        'total' => count($movimentacoes),
        'movimentacoes' => $movimentacoes
    ]);
    exit;
}

// ===== OBTER SALDO ATUAL =====
if ($acao === 'obter_saldo') {
    $produto_id = (int)($_GET['produto_id'] ?? $_POST['produto_id'] ?? 0);

    if (!$produto_id) {
        http_response_code(400);
        echo json_encode(['erro' => 'produto_id é obrigatório']);
        exit;
    }

    $saldo = obter_saldo_estoque($db, $produto_id);

    echo json_encode([
        'sucesso' => true,
        'produto_id' => $produto_id,
        'saldo' => $saldo
    ]);
    exit;
}

// ===== LISTAR TODOS OS PRODUTOS COM SALDO =====
if ($acao === 'listar_saldos') {
    $ordenar_por = $_GET['ordenar'] ?? 'nome';
    $filtro_nome = sanitize($_GET['filtro'] ?? '');

    $query = "SELECT p.*, es.quantidade_total, es.quantidade_minima, es.quantidade_maxima
        FROM produtos p
        LEFT JOIN estoque_saldos es ON p.id = es.produto_id
        WHERE 1=1";

    $params = [];

    if ($filtro_nome) {
        $query .= " AND p.nome LIKE ?";
        $params[] = "%$filtro_nome%";
    }

    if ($ordenar_por === 'critico') {
        $query .= " AND (es.quantidade_total IS NULL OR es.quantidade_total <= es.quantidade_minima)";
    }

    $query .= " ORDER BY p.nome ASC LIMIT 200";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $produtos = $stmt->fetchAll();

    foreach ($produtos as &$p) {
        if (!$p['quantidade_total']) {
            atualizar_saldo_estoque($db, $p['id']);
            $p['quantidade_total'] = obter_saldo_estoque($db, $p['id']);
        }
    }

    echo json_encode([
        'sucesso' => true,
        'total' => count($produtos),
        'produtos' => $produtos
    ]);
    exit;
}

// ===== AJUSTAR MÍNIMO/MÁXIMO =====
if ($acao === 'configurar_limites') {
    $produto_id = (int)($_POST['produto_id'] ?? 0);
    $minima = (float)($_POST['quantidade_minima'] ?? 0);
    $maxima = (float)($_POST['quantidade_maxima'] ?? 0);

    if (!$produto_id) {
        http_response_code(400);
        echo json_encode(['erro' => 'produto_id é obrigatório']);
        exit;
    }

    // Atualizar ou inserir
    $stmt = $db->prepare("INSERT INTO estoque_saldos (produto_id, quantidade_minima, quantidade_maxima)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE quantidade_minima = VALUES(quantidade_minima), quantidade_maxima = VALUES(quantidade_maxima)");
    $stmt->execute([$produto_id, $minima, $maxima]);

    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Limites configurados com sucesso'
    ]);
    exit;
}

// ===== FUNÇÕES AUXILIARES =====

function obter_saldo_estoque($db, $produto_id) {
    $stmt = $db->prepare("SELECT
        COALESCE(SUM(CASE WHEN tipo IN ('entrada', 'devolucao') THEN quantidade ELSE 0 END), 0) -
        COALESCE(SUM(CASE WHEN tipo IN ('saida') THEN quantidade ELSE 0 END), 0) +
        COALESCE(SUM(CASE WHEN tipo = 'ajuste' THEN quantidade ELSE 0 END), 0) as saldo
        FROM estoque_movimentacoes
        WHERE produto_id = ?");
    $stmt->execute([$produto_id]);
    $result = $stmt->fetch();
    return max(0, (float)($result['saldo'] ?? 0));
}

function atualizar_saldo_estoque($db, $produto_id) {
    $saldo = obter_saldo_estoque($db, $produto_id);

    $stmt = $db->prepare("INSERT INTO estoque_saldos (produto_id, quantidade_total)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE quantidade_total = VALUES(quantidade_total), updated_at = NOW()");
    $stmt->execute([$produto_id, $saldo]);
}

http_response_code(400);
echo json_encode(['erro' => 'Ação não especificada']);
