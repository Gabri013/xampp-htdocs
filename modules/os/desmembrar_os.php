<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/workflow.php';

header('Content-Type: application/json');

$os_id = (int)($_POST['os_id'] ?? 0);
$setor = $_POST['setor'] ?? '';

// Liberação parcial: O.S. pode ser liberada para qualquer setor de bancada
$setor_validos = getEtapasBancada();

if ($os_id <= 0 || !in_array($setor, $setor_validos)) {
    echo json_encode(['success' => false, 'error' => 'Dados inválidos.']);
    exit;
}

$usuario = getCurrentUser();
if (!hasPermission(['master', 'projetista', 'gerente'])) {
    echo json_encode(['success' => false, 'error' => 'Sem permissão.']);
    exit;
}

$db = getDB();

// Liberação parcial é só para O.S. que AINDA NÃO entrou em produção.
// O.S. em produção/concluída volta apenas pelo fluxo de retorno de etapa
// (com justificativa) — nunca por aqui.
$stmt = $db->prepare("SELECT status, etapa_atual FROM ordens_servico WHERE id = ?");
$stmt->execute([$os_id]);
$os = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$os) {
    echo json_encode(['success' => false, 'error' => 'O.S. não encontrada.']);
    exit;
}
if (!in_array($os['status'], ['pendente', 'em_projeto', 'proposta', 'em_revisao'], true)) {
    echo json_encode(['success' => false, 'error' => 'O.S. já está em produção ou finalizada — use o fluxo de retorno de etapa (com justificativa) em vez da liberação.']);
    exit;
}

$stmt = $db->prepare("UPDATE ordens_servico SET etapa_atual=?, status='em_producao' WHERE id=?");
$stmt->execute([$setor, $os_id]);

// Auditoria da liberação
$stmt = $db->prepare("INSERT INTO os_historico_status (os_id, status_anterior, status_novo, usuario_id, observacao) VALUES (?, ?, 'em_producao', ?, ?)");
$stmt->execute([$os_id, $os['status'], $usuario['id'], 'Liberação parcial: O.S. enviada para ' . $setor . ' por ' . $usuario['nome']]);

echo json_encode(['success' => true]);