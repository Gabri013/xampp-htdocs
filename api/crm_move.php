<?php
// api/crm_move.php — move oportunidade de estágio no pipeline (drag-drop)
require_once '../config/config.php';
require_once '../includes/crm.php';
header('Content-Type: application/json');

if (!isLoggedIn() || !hasPermission(['master', 'vendedor', 'gerente'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$estagio = (string)($_POST['estagio'] ?? '');
$motivo = sanitize($_POST['motivo'] ?? '');

$estagios = array_keys(getCrmEstagios());
if ($id <= 0 || !in_array($estagio, $estagios, true)) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

if ($estagio === 'perdido' && $motivo === '') {
    echo json_encode(['success' => false, 'message' => 'Informe o motivo da perda']);
    exit;
}

try {
    $db = getDB();
    ensureCrmSchema($db);
    $usuario = getCurrentUser();

    // Vendedor só move as próprias oportunidades
    [$filtroSql, $filtroParams] = crmFiltroResponsavel($usuario);
    $stmt = $db->prepare("SELECT id, estagio FROM crm_oportunidades o WHERE o.id = ? $filtroSql");
    $stmt->execute(array_merge([$id], $filtroParams));
    $op = $stmt->fetch();
    if (!$op) {
        echo json_encode(['success' => false, 'message' => 'Oportunidade não encontrada ou sem permissão']);
        exit;
    }

    $stmt = $db->prepare("UPDATE crm_oportunidades SET estagio = ?, motivo_perda = ? WHERE id = ?");
    $stmt->execute([$estagio, $estagio === 'perdido' ? $motivo : null, $id]);

    // Registro na timeline
    $labels = getCrmEstagios();
    $tituloAtv = 'Movida de ' . ($labels[$op['estagio']]['label'] ?? $op['estagio']) . ' para ' . ($labels[$estagio]['label'] ?? $estagio);
    if ($estagio === 'perdido') $tituloAtv .= ' — ' . $motivo;
    $stmt = $db->prepare("INSERT INTO crm_atividades (oportunidade_id, tipo, titulo, usuario_id, concluida) VALUES (?, 'nota', ?, ?, 1)");
    $stmt->execute([$id, $tituloAtv, $usuario['id']]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno']);
}
