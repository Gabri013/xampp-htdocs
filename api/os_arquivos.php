<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

$db = getDB();

$os_id = isset($_GET['os_id']) ? (int)$_GET['os_id'] : 0;
$tipo = trim((string)($_GET['tipo'] ?? ''));

if ($os_id <= 0) {
    echo json_encode([]);
    exit;
}

try {
    if ($tipo === '' || $tipo === 'todos') {
        $stmt = $db->prepare("
            SELECT id, tipo, nome_original, nome_arquivo, created_at
            FROM os_arquivos
            WHERE os_id = ?
            ORDER BY id DESC
        ");
        $stmt->execute([$os_id]);
    } else {
        $stmt = $db->prepare("
            SELECT id, tipo, nome_original, nome_arquivo, created_at
            FROM os_arquivos
            WHERE os_id = ? AND tipo = ?
            ORDER BY id DESC
        ");
        $stmt->execute([$os_id, $tipo]);
    }

    $arquivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($arquivos as &$arquivo) {
        $arquivo['tamanho'] = 0;
        if (defined('UPLOAD_PATH')) {
            $caminho = UPLOAD_PATH . 'projetos/' . $arquivo['nome_arquivo'];
            if (file_exists($caminho)) {
                $arquivo['tamanho'] = filesize($caminho);
            }
        }
    }
    unset($arquivo);

    echo json_encode($arquivos);
} catch (Exception $e) {
    http_response_code(500);
    error_log('Erro api/os_arquivos.php: ' . $e->getMessage());
    echo json_encode([]);
}
