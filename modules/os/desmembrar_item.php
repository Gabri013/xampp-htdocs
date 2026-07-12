<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/workflow.php';

header('Content-Type: application/json');

$item_id = (int)($_POST['item_id'] ?? 0);
$setor = $_POST['setor'] ?? '';

// Liberação parcial: item pode ser liberado para qualquer setor de bancada
$setores_validos = getEtapasBancada();

if ($item_id <= 0 || !in_array($setor, $setores_validos)) {
    echo json_encode(['success' => false, 'error' => 'Dados inválidos.']);
    exit;
}

$usuario = getCurrentUser();
if (!hasPermission(['master', 'projetista', 'gerente'])) {
    echo json_encode(['success' => false, 'error' => 'Sem permissão.']);
    exit;
}

$db = getDB();

// Busca o item e sua OS
$stmt = $db->prepare("SELECT os_id, produto_id FROM os_itens WHERE id = ?");
$stmt->execute([$item_id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    echo json_encode(['success' => false, 'error' => 'Item não encontrado.']);
    exit;
}

// Verifica se OS está em status que permite desmembramento
$stmt = $db->prepare("SELECT status FROM ordens_servico WHERE id = ?");
$stmt->execute([$item['os_id']]);
$os = $stmt->fetch(PDO::FETCH_ASSOC);

// Liberação parcial é só para O.S. que AINDA NÃO entrou em produção
if (!$os || !in_array($os['status'], ['pendente', 'em_projeto', 'proposta', 'em_revisao'], true)) {
    echo json_encode(['success' => false, 'error' => 'O.S. já está em produção ou finalizada — use o fluxo de retorno de etapa (com justificativa).']);
    exit;
}

// Gerar OP única para esta OS se não existir
$stmtOp = $db->prepare("SELECT id FROM ordens_producao WHERE os_id = ? LIMIT 1");
$stmtOp->execute([$item['os_id']]);
$opExiste = $stmtOp->fetch(PDO::FETCH_ASSOC);

if (!$opExiste) {
    $db->beginTransaction();
    try {
        // Gerar OP pela OS
        $numero_op = 'OP-' . date('Y') . '-' . str_pad($item['os_id'], 6, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("INSERT INTO ordens_producao (os_id, numero, status, criado_em) VALUES (?, ?, 'pendente', NOW())");
        $stmt->execute([$item['os_id'], $numero_op]);
        $db->commit();
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
    }
}

// Se for apenas gerar OP, não altera etapa da OS
if ($setor !== 'gerar_op') {
    $db->prepare("UPDATE ordens_servico SET etapa_atual = ?, status = 'em_producao' WHERE id = ?")
       ->execute([$setor, $item['os_id']]);

    // Auditoria da liberação parcial do item
    $db->prepare("INSERT INTO os_historico_status (os_id, status_anterior, status_novo, usuario_id, observacao) VALUES (?, ?, 'em_producao', ?, ?)")
       ->execute([$item['os_id'], $os['status'], $usuario['id'], 'Liberação parcial: item #' . $item_id . ' enviado para ' . $setor . ' por ' . $usuario['nome']]);
}

echo json_encode(['success' => true]);