<?php
/**
 * API de Geração de QR-code e Etiquetas para O.S. e O.P.
 *
 * Endpoints:
 * - POST /api/etiqueta_qrcode.php?acao=gerar_qr_svg&os_id=123
 * - POST /api/etiqueta_qrcode.php?acao=gerar_qr_svg_op&op_numero=123-01
 * - POST /api/etiqueta_qrcode.php?acao=gerar_codigo128&texto=ABC123
 * - POST /api/etiqueta_qrcode.php?acao=listar_etiquetas&os_id=123
 * - POST /api/etiqueta_qrcode.php?acao=registrar_impressao&etiqueta_id=456
 * - POST /api/etiqueta_qrcode.php?acao=gerar_pdf_etiquetas&os_id=123&formato=10x15
 */

require_once '../config/config.php';
require_once '../includes/workflow.php';

header('Content-Type: application/json; charset=utf-8');
$db = getDB();

// Verificar autenticação
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'erro' => 'Não autenticado']);
    exit;
}

// Verificar permissão
if (!hasPermission(['master', 'gerente', 'producao', 'dashboard_producao', 'projetista'])) {
    http_response_code(403);
    echo json_encode(['sucesso' => false, 'erro' => 'Sem permissão']);
    exit;
}

// ───────────────────────────────────────────────────────────────
// Criar tabelas se não existirem
// ───────────────────────────────────────────────────────────────

$db->exec("CREATE TABLE IF NOT EXISTS etiquetas_impressas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    os_id INT NOT NULL,
    op_numero VARCHAR(50) UNIQUE,
    tipo ENUM('qr_os', 'qr_op', 'codigo128', 'etiqueta_impressa') DEFAULT 'qr_os',
    conteudo VARCHAR(500) NOT NULL,
    dados_qr JSON,
    impressoes INT DEFAULT 0,
    usuario_id INT,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_ultima_impressao TIMESTAMP NULL,
    FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_os_tipo (os_id, tipo),
    INDEX idx_op_numero (op_numero)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// ───────────────────────────────────────────────────────────────
// Ações disponíveis
// ───────────────────────────────────────────────────────────────

$acao = $_POST['acao'] ?? $_GET['acao'] ?? null;

// ───────────────────────────────────────────────────────────────
// AÇÃO: Gerar QR-code SVG para O.S.
// ───────────────────────────────────────────────────────────────
if ($acao === 'gerar_qr_svg') {
    $os_id = (int)($_POST['os_id'] ?? $_GET['os_id'] ?? 0);

    if ($os_id <= 0) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'erro' => 'os_id inválido']);
        exit;
    }

    try {
        // Verificar O.S.
        $stmt = $db->prepare("SELECT id, numero, cliente_id FROM ordens_servico WHERE id = ?");
        $stmt->execute([$os_id]);
        $os = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$os) {
            http_response_code(404);
            echo json_encode(['sucesso' => false, 'erro' => 'O.S. não encontrada']);
            exit;
        }

        // Dados do QR-code
        $qr_content = "OS|" . $os['numero'] . "|" . $os_id;
        $dados_qr = [
            'id' => $os_id,
            'numero' => $os['numero'],
            'tipo' => 'ordem_servico',
            'timestamp' => time(),
            'url' => SITE_URL . '/modules/os/scan.php?code=' . urlencode($qr_content)
        ];

        // Verificar se já existe
        $stmt = $db->prepare("SELECT id FROM etiquetas_impressas WHERE os_id = ? AND tipo = 'qr_os' LIMIT 1");
        $stmt->execute([$os_id]);
        $existe = $stmt->fetch(PDO::FETCH_ASSOC);

        $etiqueta_id = null;
        if (!$existe) {
            $stmt = $db->prepare("INSERT INTO etiquetas_impressas (os_id, tipo, conteudo, dados_qr, usuario_id)
                VALUES (?, 'qr_os', ?, ?, ?)");
            $stmt->execute([$os_id, $qr_content, json_encode($dados_qr), $_SESSION['usuario_id'] ?? null]);
            $etiqueta_id = $db->lastInsertId();
        } else {
            $etiqueta_id = $existe['id'];
        }

        // Gerar QR-code via serviço externo
        $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qr_content);

        echo json_encode([
            'sucesso' => true,
            'etiqueta_id' => $etiqueta_id,
            'os_id' => $os_id,
            'os_numero' => $os['numero'],
            'qr_content' => $qr_content,
            'qr_url' => $qr_url,
            'dados_qr' => $dados_qr
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

// ───────────────────────────────────────────────────────────────
// AÇÃO: Gerar QR-code para O.P. (Ordem de Produção)
// ───────────────────────────────────────────────────────────────
if ($acao === 'gerar_qr_svg_op') {
    $op_numero = trim($_POST['op_numero'] ?? $_GET['op_numero'] ?? '');
    $os_id = (int)($_POST['os_id'] ?? $_GET['os_id'] ?? 0);

    if (empty($op_numero) || $os_id <= 0) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'erro' => 'op_numero e os_id são obrigatórios']);
        exit;
    }

    try {
        // Verificar O.S.
        $stmt = $db->prepare("SELECT id, numero FROM ordens_servico WHERE id = ?");
        $stmt->execute([$os_id]);
        $os = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$os) {
            http_response_code(404);
            echo json_encode(['sucesso' => false, 'erro' => 'O.S. não encontrada']);
            exit;
        }

        // Conteúdo do QR para O.P.
        $qr_content = "OP|" . $op_numero . "|" . $os_id;
        $dados_qr = [
            'numero_op' => $op_numero,
            'os_id' => $os_id,
            'os_numero' => $os['numero'],
            'tipo' => 'ordem_producao',
            'timestamp' => time(),
            'url' => SITE_URL . '/modules/os/scan.php?code=' . urlencode($qr_content)
        ];

        // Registrar etiqueta
        $stmt = $db->prepare("SELECT id FROM etiquetas_impressas WHERE op_numero = ? LIMIT 1");
        $stmt->execute([$op_numero]);
        $existe = $stmt->fetch(PDO::FETCH_ASSOC);

        $etiqueta_id = null;
        if (!$existe) {
            $stmt = $db->prepare("INSERT INTO etiquetas_impressas (os_id, op_numero, tipo, conteudo, dados_qr, usuario_id)
                VALUES (?, ?, 'qr_op', ?, ?, ?)");
            $stmt->execute([$os_id, $op_numero, $qr_content, json_encode($dados_qr), $_SESSION['usuario_id'] ?? null]);
            $etiqueta_id = $db->lastInsertId();
        } else {
            $etiqueta_id = $existe['id'];
        }

        $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qr_content);

        echo json_encode([
            'sucesso' => true,
            'etiqueta_id' => $etiqueta_id,
            'op_numero' => $op_numero,
            'qr_url' => $qr_url,
            'dados_qr' => $dados_qr
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

// ───────────────────────────────────────────────────────────────
// AÇÃO: Gerar código de barras 128
// ───────────────────────────────────────────────────────────────
if ($acao === 'gerar_codigo128') {
    $texto = trim($_POST['texto'] ?? $_GET['texto'] ?? '');
    $os_id = (int)($_POST['os_id'] ?? $_GET['os_id'] ?? 0);

    if (empty($texto)) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'erro' => 'texto é obrigatório']);
        exit;
    }

    try {
        // Registrar código de barras
        if ($os_id > 0) {
            $stmt = $db->prepare("INSERT INTO etiquetas_impressas (os_id, tipo, conteudo, usuario_id)
                VALUES (?, 'codigo128', ?, ?)");
            $stmt->execute([$os_id, $texto, $_SESSION['usuario_id'] ?? null]);
            $etiqueta_id = $db->lastInsertId();
        } else {
            $etiqueta_id = null;
        }

        // Código 128 via barcodebakery
        $barcode_url = "https://www.aspose.cloud/v3.0/barcode/generate?Type=Code128&Text=" . urlencode($texto);

        echo json_encode([
            'sucesso' => true,
            'etiqueta_id' => $etiqueta_id,
            'codigo' => $texto,
            'barcode_url' => $barcode_url,
            'tipo' => 'CODE128'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

// ───────────────────────────────────────────────────────────────
// AÇÃO: Listar todas as etiquetas de uma O.S.
// ───────────────────────────────────────────────────────────────
if ($acao === 'listar_etiquetas') {
    $os_id = (int)($_GET['os_id'] ?? $_POST['os_id'] ?? 0);

    if ($os_id <= 0) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'erro' => 'os_id inválido']);
        exit;
    }

    try {
        $stmt = $db->prepare("SELECT e.*, u.nome as usuario_nome
            FROM etiquetas_impressas e
            LEFT JOIN usuarios u ON e.usuario_id = u.id
            WHERE e.os_id = ?
            ORDER BY e.data_criacao DESC");
        $stmt->execute([$os_id]);
        $etiquetas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Adicionar URLs de QR e barcode
        foreach ($etiquetas as &$e) {
            if ($e['tipo'] === 'qr_os') {
                $e['url_qr'] = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($e['conteudo']);
            } elseif ($e['tipo'] === 'qr_op') {
                $e['url_qr'] = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($e['conteudo']);
            } elseif ($e['tipo'] === 'codigo128') {
                $e['url_barcode'] = "https://www.aspose.cloud/v3.0/barcode/generate?Type=Code128&Text=" . urlencode($e['conteudo']);
            }
            if ($e['dados_qr']) {
                $e['dados_qr'] = json_decode($e['dados_qr'], true);
            }
        }

        echo json_encode([
            'sucesso' => true,
            'total' => count($etiquetas),
            'etiquetas' => $etiquetas
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

// ───────────────────────────────────────────────────────────────
// AÇÃO: Registrar impressão de etiqueta
// ───────────────────────────────────────────────────────────────
if ($acao === 'registrar_impressao') {
    $etiqueta_id = (int)($_POST['etiqueta_id'] ?? 0);

    if ($etiqueta_id <= 0) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'erro' => 'etiqueta_id inválido']);
        exit;
    }

    try {
        $stmt = $db->prepare("UPDATE etiquetas_impressas SET impressoes = impressoes + 1, data_ultima_impressao = NOW() WHERE id = ?");
        $stmt->execute([$etiqueta_id]);

        echo json_encode(['sucesso' => true, 'mensagem' => 'Impressão registrada']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

// ───────────────────────────────────────────────────────────────
// AÇÃO: Listar estatísticas de impressão
// ───────────────────────────────────────────────────────────────
if ($acao === 'stats_impressoes') {
    $os_id = (int)($_GET['os_id'] ?? $_POST['os_id'] ?? 0);

    try {
        $where = $os_id > 0 ? "WHERE os_id = ?" : "WHERE 1=1";
        $params = $os_id > 0 ? [$os_id] : [];

        $stmt = $db->prepare("SELECT tipo, COUNT(*) as total, SUM(impressoes) as impressoes_totais
            FROM etiquetas_impressas $where GROUP BY tipo");
        $stmt->execute($params);
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['sucesso' => true, 'stats' => $stats]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

// ───────────────────────────────────────────────────────────────
// AÇÃO: Excluir etiqueta
// ───────────────────────────────────────────────────────────────
if ($acao === 'excluir_etiqueta') {
    $etiqueta_id = (int)($_POST['etiqueta_id'] ?? 0);

    if ($etiqueta_id <= 0) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'erro' => 'etiqueta_id inválido']);
        exit;
    }

    try {
        $stmt = $db->prepare("DELETE FROM etiquetas_impressas WHERE id = ?");
        $stmt->execute([$etiqueta_id]);

        echo json_encode(['sucesso' => true, 'mensagem' => 'Etiqueta removida']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

// ───────────────────────────────────────────────────────────────
// Erro padrão
// ───────────────────────────────────────────────────────────────
http_response_code(400);
echo json_encode(['sucesso' => false, 'erro' => 'Ação não especificada ou inválida']);
