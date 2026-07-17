<?php
/**
 * API de Expedição - Preparação e Despacho de Produtos
 *
 * POST /api/expedicao.php
 * - acao=criar → nova expedição (a partir de O.S. concluída)
 * - acao=atualizar_status → muda status
 * - acao=listar → lista expedições
 * - acao=obter → detalhes de uma expedição
 * - acao=gerar_romaneio → cria documento de envio
 */

require_once '../config/config.php';
require_once '../includes/workflow.php';

header('Content-Type: application/json');
$db = getDB();
requirePermission(['master', 'gerente', 'expedicao', 'dashboard_producao']);

// Criar tabelas se não existirem
$db->exec("CREATE TABLE IF NOT EXISTS expedicoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(20) UNIQUE NOT NULL,
    os_id INT NOT NULL,
    cliente_id INT NOT NULL,
    status ENUM('preparando', 'conferido', 'pronto', 'despachado', 'entregue', 'devolvido') DEFAULT 'preparando',
    data_preparo TIMESTAMP NULL,
    data_despacho TIMESTAMP NULL,
    data_entrega TIMESTAMP NULL,
    transportadora VARCHAR(100),
    numero_rastreamento VARCHAR(50),
    peso_total DECIMAL(10,2),
    volume_total VARCHAR(100),
    observacao TEXT,
    usuario_id_criador INT,
    usuario_id_despacho INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id_criador) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (usuario_id_despacho) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_os (os_id),
    INDEX idx_cliente (cliente_id),
    INDEX idx_status (status),
    INDEX idx_data (created_at)
)");

$db->exec("CREATE TABLE IF NOT EXISTS expedicoes_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expedicao_id INT NOT NULL,
    produto_id INT NOT NULL,
    quantidade INT NOT NULL,
    conferido INT DEFAULT 0,
    FOREIGN KEY (expedicao_id) REFERENCES expedicoes(id) ON DELETE CASCADE,
    FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE,
    INDEX idx_expedicao (expedicao_id)
)");

$acao = $_POST['acao'] ?? $_GET['acao'] ?? null;

// ===== CRIAR EXPEDIÇÃO (a partir de O.S. concluída) =====
if ($acao === 'criar') {
    $os_id = (int)($_POST['os_id'] ?? 0);

    if (!$os_id) {
        http_response_code(400);
        echo json_encode(['erro' => 'os_id é obrigatório']);
        exit;
    }

    try {
        $db->beginTransaction();

        // Verificar O.S.
        $stmt = $db->prepare("SELECT o.*, c.id as cliente_id FROM ordens_servico o
            INNER JOIN clientes c ON o.cliente_id = c.id WHERE o.id = ?");
        $stmt->execute([$os_id]);
        $os = $stmt->fetch();

        if (!$os) {
            throw new \Exception('O.S. não encontrada');
        }

        // Gerar número sequencial
        $stmt = $db->query("SELECT COUNT(*) as total FROM expedicoes");
        $total = $stmt->fetch()['total'] + 1;
        $numero = 'EXP-' . str_pad(date('Y') . str_pad($total, 5, '0', STR_PAD_LEFT), 10, '0', STR_PAD_LEFT);

        // Criar expedição
        $stmt = $db->prepare("INSERT INTO expedicoes
            (numero, os_id, cliente_id, status, usuario_id_criador)
            VALUES (?, ?, ?, 'preparando', ?)");
        $stmt->execute([$numero, $os_id, $os['cliente_id'], $_SESSION['usuario_id']]);

        $expedicao_id = $db->lastInsertId();

        // Adicionar itens da O.S.
        $stmt = $db->prepare("SELECT produto_id, 1 as quantidade FROM ordens_servico WHERE id = ?");
        $stmt->execute([$os_id]);
        $itens = $stmt->fetchAll();

        foreach ($itens as $item) {
            $stmt = $db->prepare("INSERT INTO expedicoes_itens (expedicao_id, produto_id, quantidade)
                VALUES (?, ?, ?)");
            $stmt->execute([$expedicao_id, $item['produto_id'], $item['quantidade']]);
        }

        $db->commit();

        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Expedição criada',
            'expedicao_id' => $expedicao_id,
            'numero' => $numero
        ]);
    } catch (\Exception $e) {
        $db->rollBack();
        http_response_code(400);
        echo json_encode(['erro' => $e->getMessage()]);
    }
    exit;
}

// ===== ATUALIZAR STATUS =====
if ($acao === 'atualizar_status') {
    $expedicao_id = (int)($_POST['expedicao_id'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['preparando', 'conferido', 'pronto', 'despachado', 'entregue', 'devolvido']) ? $_POST['status'] : null;

    if (!$expedicao_id || !$status) {
        http_response_code(400);
        echo json_encode(['erro' => 'expedicao_id e status são obrigatórios']);
        exit;
    }

    $updates = "status = ?";
    $params = [$status, $expedicao_id];

    if ($status === 'despachado') {
        $updates .= ", data_despacho = NOW(), usuario_id_despacho = ?";
        array_splice($params, 1, 0, [$_SESSION['usuario_id']]);
    } elseif ($status === 'entregue') {
        $updates .= ", data_entrega = NOW()";
    }

    $stmt = $db->prepare("UPDATE expedicoes SET $updates, updated_at = NOW() WHERE id = ?");
    $stmt->execute($params);

    echo json_encode(['sucesso' => true, 'mensagem' => 'Status atualizado']);
    exit;
}

// ===== LISTAR EXPEDIÇÕES =====
if ($acao === 'listar') {
    $status = $_GET['status'] ?? $_POST['status'] ?? null;
    $os_id = (int)($_GET['os_id'] ?? $_POST['os_id'] ?? 0);
    $dias = (int)($_GET['dias'] ?? 30);

    $query = "SELECT e.*, c.razao_social, o.numero as os_numero
        FROM expedicoes e
        INNER JOIN clientes c ON e.cliente_id = c.id
        INNER JOIN ordens_servico o ON e.os_id = o.id
        WHERE e.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";

    $params = [$dias];

    if ($status) {
        $query .= " AND e.status = ?";
        $params[] = $status;
    }

    if ($os_id) {
        $query .= " AND e.os_id = ?";
        $params[] = $os_id;
    }

    $query .= " ORDER BY e.created_at DESC LIMIT 100";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $expedicoes = $stmt->fetchAll();

    echo json_encode([
        'sucesso' => true,
        'total' => count($expedicoes),
        'expedicoes' => $expedicoes
    ]);
    exit;
}

// ===== OBTER DETALHES =====
if ($acao === 'obter') {
    $expedicao_id = (int)($_GET['expedicao_id'] ?? $_POST['expedicao_id'] ?? 0);

    if (!$expedicao_id) {
        http_response_code(400);
        echo json_encode(['erro' => 'expedicao_id é obrigatório']);
        exit;
    }

    $stmt = $db->prepare("SELECT e.*, c.razao_social, o.numero as os_numero
        FROM expedicoes e
        INNER JOIN clientes c ON e.cliente_id = c.id
        INNER JOIN ordens_servico o ON e.os_id = o.id
        WHERE e.id = ?");
    $stmt->execute([$expedicao_id]);
    $expedicao = $stmt->fetch();

    if (!$expedicao) {
        http_response_code(404);
        echo json_encode(['erro' => 'Expedição não encontrada']);
        exit;
    }

    // Buscar itens
    $stmt = $db->prepare("SELECT ei.*, p.nome FROM expedicoes_itens ei
        INNER JOIN produtos p ON ei.produto_id = p.id
        WHERE ei.expedicao_id = ?");
    $stmt->execute([$expedicao_id]);
    $expedicao['itens'] = $stmt->fetchAll();

    echo json_encode([
        'sucesso' => true,
        'expedicao' => $expedicao
    ]);
    exit;
}

// ===== CONFERIR ITEM =====
if ($acao === 'conferir_item') {
    $item_id = (int)($_POST['item_id'] ?? 0);

    if (!$item_id) {
        http_response_code(400);
        echo json_encode(['erro' => 'item_id é obrigatório']);
        exit;
    }

    $stmt = $db->prepare("UPDATE expedicoes_itens SET conferido = 1 WHERE id = ?");
    $stmt->execute([$item_id]);

    echo json_encode(['sucesso' => true, 'mensagem' => 'Item conferido']);
    exit;
}

http_response_code(400);
echo json_encode(['erro' => 'Ação não especificada']);
