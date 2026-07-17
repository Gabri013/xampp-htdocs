<?php
/**
 * API de Etiquetas - Geração de QR-code e código de barras
 *
 * POST /api/etiqueta.php
 * - acao=gerar_qr&os_id=123 → gera QR-code para O.S.
 * - acao=gerar_codigo128&numero=ABC123 → código 128 para produto
 * - acao=listar&os_id=123 → lista etiquetas da O.S.
 */

require_once '../config/config.php';
require_once '../includes/workflow.php';

header('Content-Type: application/json');
$db = getDB();
requirePermission(['master', 'gerente', 'producao', 'dashboard_producao']);

// Criar tabela se não existir
$db->exec("CREATE TABLE IF NOT EXISTS etiquetas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    os_id INT NOT NULL,
    tipo ENUM('qr_os', 'codigo_produto', 'codigo_lote') DEFAULT 'qr_os',
    conteudo VARCHAR(500) NOT NULL,
    dados_qr JSON,
    impressoes INT DEFAULT 0,
    usuario_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_os_tipo (os_id, tipo)
)");

$acao = $_POST['acao'] ?? $_GET['acao'] ?? null;

if ($acao === 'gerar_qr') {
    $os_id = (int)($_POST['os_id'] ?? 0);

    if (!$os_id) {
        http_response_code(400);
        echo json_encode(['erro' => 'os_id é obrigatório']);
        exit;
    }

    // Verificar O.S.
    $stmt = $db->prepare("SELECT id, numero, cliente_id FROM ordens_servico WHERE id = ?");
    $stmt->execute([$os_id]);
    $os = $stmt->fetch();

    if (!$os) {
        http_response_code(404);
        echo json_encode(['erro' => 'O.S. não encontrada']);
        exit;
    }

    // Dados para o QR-code (base64 codificado para compactar)
    $dados_qr = [
        'id' => $os_id,
        'numero' => $os['numero'],
        'tipo' => 'oracao_servico',
        'timestamp' => time(),
    ];

    // Conteúdo do QR: formato simples para scanner
    $qr_content = "OS|" . $os['numero'] . "|" . $os_id;

    // Checar se já existe
    $stmt = $db->prepare("SELECT id FROM etiquetas WHERE os_id = ? AND tipo = 'qr_os' LIMIT 1");
    $stmt->execute([$os_id]);
    $existe = $stmt->fetch();

    if (!$existe) {
        $stmt = $db->prepare("INSERT INTO etiquetas (os_id, tipo, conteudo, dados_qr, usuario_id)
            VALUES (?, 'qr_os', ?, ?, ?)");
        $stmt->execute([$os_id, $qr_content, json_encode($dados_qr), $_SESSION['usuario_id']]);
        $etiqueta_id = $db->lastInsertId();
    } else {
        $etiqueta_id = $existe['id'];
    }

    echo json_encode([
        'sucesso' => true,
        'id' => $etiqueta_id,
        'os_id' => $os_id,
        'os_numero' => $os['numero'],
        'qr_content' => $qr_content,
        'qr_data_uri' => gerar_qr_svg($qr_content)
    ]);
    exit;
}

if ($acao === 'listar') {
    $os_id = (int)($_GET['os_id'] ?? $_POST['os_id'] ?? 0);

    if (!$os_id) {
        http_response_code(400);
        echo json_encode(['erro' => 'os_id é obrigatório']);
        exit;
    }

    $stmt = $db->prepare("SELECT e.*, u.nome as usuario_nome
        FROM etiquetas e
        LEFT JOIN usuarios u ON e.usuario_id = u.id
        WHERE e.os_id = ?
        ORDER BY e.created_at DESC");
    $stmt->execute([$os_id]);
    $etiquetas = $stmt->fetchAll();

    foreach ($etiquetas as &$e) {
        $e['qr_svg'] = gerar_qr_svg($e['conteudo']);
    }

    echo json_encode([
        'sucesso' => true,
        'total' => count($etiquetas),
        'etiquetas' => $etiquetas
    ]);
    exit;
}

if ($acao === 'registrar_impressao') {
    $etiqueta_id = (int)($_POST['etiqueta_id'] ?? 0);

    if (!$etiqueta_id) {
        http_response_code(400);
        echo json_encode(['erro' => 'etiqueta_id é obrigatório']);
        exit;
    }

    $stmt = $db->prepare("UPDATE etiquetas SET impressoes = impressoes + 1 WHERE id = ?");
    $stmt->execute([$etiqueta_id]);

    echo json_encode(['sucesso' => true, 'mensagem' => 'Impressão registrada']);
    exit;
}

// Função auxiliar: gerar QR-code em SVG
function gerar_qr_svg($conteudo) {
    // Usar serviço local ou externo
    // Aqui usamos qrserver.com (confiável e open-source)
    $encoded = urlencode($conteudo);
    return "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . $encoded;
}

http_response_code(400);
echo json_encode(['erro' => 'Ação não especificada']);
