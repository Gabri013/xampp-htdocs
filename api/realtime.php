<?php
// api/realtime.php
// Endpoint leve de polling: badge de notificações do usuário logado e
// "fingerprint" do estado do sistema para auto-atualização dos painéis.
require_once '../config/config.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

try {
    $db = getDB();

    $stmt = $db->prepare("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND lida = 0");
    $stmt->execute([$_SESSION['usuario_id']]);
    $notif = (int) $stmt->fetchColumn();

    // Fingerprint barato: muda sempre que OS, vendas, apontamentos de
    // produção ou financeiro mudam. O cliente recarrega o painel quando
    // o valor divergir do que tinha ao carregar a página.
    $fp = (string) $db->query("
        SELECT MD5(CONCAT_WS('|',
            (SELECT COUNT(*) FROM ordens_servico),
            (SELECT COALESCE(MAX(updated_at), '') FROM ordens_servico),
            (SELECT COUNT(*) FROM vendas),
            (SELECT COALESCE(MAX(updated_at), '') FROM vendas),
            (SELECT COUNT(*) FROM os_etapas_producao),
            (SELECT COALESCE(MAX(id), 0) FROM os_etapas_producao),
            (SELECT COALESCE(SUM(status = 'em_andamento'), 0) FROM os_etapas_producao),
            (SELECT COALESCE(SUM(status = 'concluida'), 0) FROM os_etapas_producao),
            (SELECT COALESCE(MAX(data_inicio), '') FROM os_etapas_producao),
            (SELECT COALESCE(MAX(data_fim), '') FROM os_etapas_producao),
            (SELECT COALESCE(MAX(updated_at), '') FROM contas_receber),
            (SELECT COALESCE(MAX(id), 0) FROM logs_retorno_etapa)
        ))
    ")->fetchColumn();

    echo json_encode(['success' => true, 'notif' => $notif, 'fp' => $fp]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno']);
}
