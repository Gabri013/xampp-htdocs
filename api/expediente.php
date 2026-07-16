<?php
/**
 * Bater ponto (expediente) — iniciar ou encerrar o expediente do dia.
 * Qualquer usuário logado pode registrar o próprio ponto; o apontamento
 * de etapas (api/producao.php) exige expediente aberto.
 */
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/expediente.php';

header('Content-Type: application/json');

if (empty($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método inválido.']);
    exit;
}

$db = getDB();
ensureExpedienteSchema($db);
$usuario = getCurrentUser();
$acao = $_POST['acao'] ?? '';

try {
    if ($acao === 'iniciar') {
        $res = registrarInicioExpediente($db, $usuario);
    } elseif ($acao === 'encerrar') {
        $res = registrarFimExpediente($db, $usuario);
    } else {
        echo json_encode(['success' => false, 'error' => 'Ação inválida.']);
        exit;
    }
    if (empty($res['success'])) {
        $res['error'] = $res['message'] ?? 'Não foi possível registrar o ponto.';
    }
    echo json_encode($res);
} catch (Exception $e) {
    error_log('expediente: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao registrar o ponto.']);
}
