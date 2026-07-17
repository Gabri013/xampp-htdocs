<?php
/**
 * API de BOM (Bill of Materials) - Lista de Materiais por Produto
 *
 * POST /api/bom.php
 * - acao=adicionar_item → adiciona material à BOM do produto
 * - acao=remover_item → remove material da BOM
 * - acao=listar → lista todos os itens da BOM
 * - acao=obter_bom_produto → obtém BOM completa de um produto
 */

require_once '../config/config.php';
require_once '../includes/workflow.php';

header('Content-Type: application/json');
$db = getDB();
requirePermission(['master', 'gerente', 'producao', 'dashboard_producao']);

// Criar tabelas se não existirem
$db->exec("CREATE TABLE IF NOT EXISTS produtos_bom (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_principal_id INT NOT NULL,
    material_id INT NOT NULL,
    quantidade DECIMAL(10,2) NOT NULL,
    unidade VARCHAR(20) DEFAULT 'un',
    sequencia INT DEFAULT 0,
    ativo TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produto_principal_id) REFERENCES produtos(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES produtos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_bom (produto_principal_id, material_id),
    INDEX idx_produto_principal (produto_principal_id)
)");

$db->exec("CREATE TABLE IF NOT EXISTS requisicoes_materiais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    os_id INT NOT NULL,
    material_id INT NOT NULL,
    quantidade_solicitada DECIMAL(10,2) NOT NULL,
    quantidade_consumida DECIMAL(10,2) DEFAULT 0,
    quantidade_devolvida DECIMAL(10,2) DEFAULT 0,
    status ENUM('pendente', 'separado', 'entregue', 'consumido') DEFAULT 'pendente',
    data_solicitacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_entrega TIMESTAMP NULL,
    usuario_id INT,
    observacao TEXT,
    FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES produtos(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_os (os_id),
    INDEX idx_status (status),
    INDEX idx_material (material_id)
)");

$acao = $_POST['acao'] ?? $_GET['acao'] ?? null;

// ===== ADICIONAR ITEM À BOM =====
if ($acao === 'adicionar_item') {
    $produto_principal_id = (int)($_POST['produto_principal_id'] ?? 0);
    $material_id = (int)($_POST['material_id'] ?? 0);
    $quantidade = (float)($_POST['quantidade'] ?? 0);
    $unidade = sanitize($_POST['unidade'] ?? 'un');

    if (!$produto_principal_id || !$material_id || !$quantidade) {
        http_response_code(400);
        echo json_encode(['erro' => 'Campos obrigatórios: produto_principal_id, material_id, quantidade']);
        exit;
    }

    try {
        $stmt = $db->prepare("INSERT INTO produtos_bom (produto_principal_id, material_id, quantidade, unidade)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE quantidade = VALUES(quantidade), unidade = VALUES(unidade)");
        $stmt->execute([$produto_principal_id, $material_id, $quantidade, $unidade]);

        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Item adicionado à BOM',
            'id' => $db->lastInsertId()
        ]);
    } catch (\Exception $e) {
        http_response_code(400);
        echo json_encode(['erro' => 'Erro ao adicionar: ' . $e->getMessage()]);
    }
    exit;
}

// ===== REMOVER ITEM DA BOM =====
if ($acao === 'remover_item') {
    $item_id = (int)($_POST['item_id'] ?? 0);

    if (!$item_id) {
        http_response_code(400);
        echo json_encode(['erro' => 'item_id é obrigatório']);
        exit;
    }

    $stmt = $db->prepare("DELETE FROM produtos_bom WHERE id = ?");
    $stmt->execute([$item_id]);

    echo json_encode(['sucesso' => true, 'mensagem' => 'Item removido da BOM']);
    exit;
}

// ===== LISTAR BOM DE UM PRODUTO =====
if ($acao === 'obter_bom_produto') {
    $produto_principal_id = (int)($_GET['produto_id'] ?? $_POST['produto_id'] ?? 0);

    if (!$produto_principal_id) {
        http_response_code(400);
        echo json_encode(['erro' => 'produto_id é obrigatório']);
        exit;
    }

    $stmt = $db->prepare("SELECT pb.*, p.nome as material_nome
        FROM produtos_bom pb
        INNER JOIN produtos p ON pb.material_id = p.id
        WHERE pb.produto_principal_id = ? AND pb.ativo = 1
        ORDER BY pb.sequencia ASC");
    $stmt->execute([$produto_principal_id]);
    $itens = $stmt->fetchAll();

    echo json_encode([
        'sucesso' => true,
        'total' => count($itens),
        'itens' => $itens
    ]);
    exit;
}

// ===== LISTAR TODAS AS BOMs =====
if ($acao === 'listar') {
    $stmt = $db->query("SELECT pb.*, pp.nome as produto_principal, p.nome as material_nome
        FROM produtos_bom pb
        INNER JOIN produtos pp ON pb.produto_principal_id = pp.id
        INNER JOIN produtos p ON pb.material_id = p.id
        WHERE pb.ativo = 1
        ORDER BY pp.nome, pb.sequencia
        LIMIT 100");
    $boms = $stmt->fetchAll();

    echo json_encode([
        'sucesso' => true,
        'total' => count($boms),
        'boms' => $boms
    ]);
    exit;
}

// ===== REQUISITAR MATERIAIS AUTOMATICAMENTE (por BOM) =====
if ($acao === 'requisitar_por_bom') {
    $os_id = (int)($_POST['os_id'] ?? 0);
    $produto_id = (int)($_POST['produto_id'] ?? 0);
    $quantidade_os = (float)($_POST['quantidade'] ?? 1);

    if (!$os_id || !$produto_id) {
        http_response_code(400);
        echo json_encode(['erro' => 'os_id e produto_id são obrigatórios']);
        exit;
    }

    try {
        $db->beginTransaction();

        // Obter BOM do produto
        $stmt = $db->prepare("SELECT * FROM produtos_bom WHERE produto_principal_id = ? AND ativo = 1");
        $stmt->execute([$produto_id]);
        $itens_bom = $stmt->fetchAll();

        $requisicoes = [];

        // Criar requisições para cada item da BOM
        foreach ($itens_bom as $item) {
            $quantidade_necessaria = $item['quantidade'] * $quantidade_os;

            $stmt = $db->prepare("INSERT INTO requisicoes_materiais
                (os_id, material_id, quantidade_solicitada, status, usuario_id)
                VALUES (?, ?, ?, 'pendente', ?)");
            $stmt->execute([$os_id, $item['material_id'], $quantidade_necessaria, $_SESSION['usuario_id']]);

            $requisicoes[] = [
                'material_id' => $item['material_id'],
                'quantidade' => $quantidade_necessaria,
                'requisicao_id' => $db->lastInsertId()
            ];
        }

        $db->commit();

        echo json_encode([
            'sucesso' => true,
            'mensagem' => count($requisicoes) . ' materiais requisitados',
            'requisicoes' => $requisicoes
        ]);
    } catch (\Exception $e) {
        $db->rollBack();
        http_response_code(400);
        echo json_encode(['erro' => 'Erro ao requisitar: ' . $e->getMessage()]);
    }
    exit;
}

// ===== REGISTRAR CONSUMO DE MATERIAL =====
if ($acao === 'registrar_consumo') {
    $requisicao_id = (int)($_POST['requisicao_id'] ?? 0);
    $quantidade_consumida = (float)($_POST['quantidade'] ?? 0);

    if (!$requisicao_id || !$quantidade_consumida) {
        http_response_code(400);
        echo json_encode(['erro' => 'requisicao_id e quantidade são obrigatórios']);
        exit;
    }

    try {
        $db->beginTransaction();

        // Obter requisição
        $stmt = $db->prepare("SELECT * FROM requisicoes_materiais WHERE id = ?");
        $stmt->execute([$requisicao_id]);
        $req = $stmt->fetch();

        if (!$req) {
            throw new \Exception('Requisição não encontrada');
        }

        // Registrar consumo (não pode exceder solicitado)
        $consumo_total = $req['quantidade_consumida'] + $quantidade_consumida;
        if ($consumo_total > $req['quantidade_solicitada']) {
            throw new \Exception('Consumo não pode exceder quantidade solicitada');
        }

        // Atualizar requisição
        $stmt = $db->prepare("UPDATE requisicoes_materiais
            SET quantidade_consumida = ?, status = 'consumido'
            WHERE id = ?");
        $stmt->execute([$consumo_total, $requisicao_id]);

        // Registrar saída no estoque
        $stmt = $db->prepare("INSERT INTO estoque_movimentacoes
            (produto_id, tipo, quantidade, referencia, os_id, usuario_id)
            VALUES (?, 'saida', ?, ?, ?, ?)");
        $stmt->execute([$req['material_id'], $quantidade_consumida, 'REQ-' . $requisicao_id, $req['os_id'], $_SESSION['usuario_id']]);

        $db->commit();

        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Consumo registrado e estoque atualizado',
            'consumo_total' => $consumo_total
        ]);
    } catch (\Exception $e) {
        $db->rollBack();
        http_response_code(400);
        echo json_encode(['erro' => $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['erro' => 'Ação não especificada']);
