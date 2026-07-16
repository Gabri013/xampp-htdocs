<?php
/**
 * Envio parcial (desmembramento) de um item da O.S. para um setor.
 *
 * Regras:
 * - Permitido para O.S. pré-produção (pendente/em_projeto/proposta/em_revisao)
 *   e para O.S. em produção NA ETAPA DE ENGENHARIA (o projetista envia os
 *   itens à fábrica conforme conclui o desenho de produção de cada um).
 * - Se restarem outros itens pendentes, o item enviado vira uma O.S. FILHA
 *   (ex.: OS-0099.1) no setor destino, com anexos e etapas copiados; a O.S.
 *   original continua onde está com os demais itens.
 * - Se for o último item pendente, a própria O.S. vai para o setor.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/workflow.php';

header('Content-Type: application/json');

$item_id = (int)($_POST['item_id'] ?? 0);
$os_id_post = (int)($_POST['os_id'] ?? 0);
$setor = $_POST['setor'] ?? '';

$setores_validos = getEtapasBancada();

if ($item_id <= 0 || !in_array($setor, $setores_validos, true)) {
    echo json_encode(['success' => false, 'error' => 'Dados inválidos.']);
    exit;
}

$usuario = getCurrentUser();
if (!hasPermission(['master', 'projetista', 'gerente'])) {
    echo json_encode(['success' => false, 'error' => 'Sem permissão.']);
    exit;
}

$db = getDB();

// Rastreio de itens já despachados (cria a tabela se não existir)
$db->exec("CREATE TABLE IF NOT EXISTS os_desmembramentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    os_pai_id INT NOT NULL,
    os_filho_id INT NULL,
    origem VARCHAR(20) NOT NULL,
    item_id INT NOT NULL,
    setor VARCHAR(30) NOT NULL,
    usuario_id INT NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_item (origem, item_id),
    KEY idx_pai (os_pai_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Localiza a O.S. e o item (os_itens para O.S. independente; vendas_itens
// para O.S. vinda de venda — o detalhe da O.S. lista uma OU outra origem).
$os = null;
$origem = null;
$itemDados = null;

if ($os_id_post > 0) {
    $stmt = $db->prepare("SELECT * FROM ordens_servico WHERE id = ?");
    $stmt->execute([$os_id_post]);
    $os = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($os && !empty($os['venda_id'])) {
    $stmt = $db->prepare("SELECT vi.id, vi.produto_id, vi.descricao_manual, vi.quantidade, COALESCE(p.nome, vi.descricao_manual) AS descricao
        FROM vendas_itens vi LEFT JOIN produtos p ON vi.produto_id = p.id
        WHERE vi.id = ? AND vi.venda_id = ?");
    $stmt->execute([$item_id, (int) $os['venda_id']]);
    $itemDados = $stmt->fetch(PDO::FETCH_ASSOC);
    $origem = 'vendas_itens';
}

if (!$itemDados) {
    $stmt = $db->prepare("SELECT oi.id, oi.os_id, oi.produto_id, oi.descricao_manual, oi.quantidade, COALESCE(p.nome, oi.descricao_manual) AS descricao
        FROM os_itens oi LEFT JOIN produtos p ON oi.produto_id = p.id
        WHERE oi.id = ?" . ($os_id_post > 0 ? " AND oi.os_id = " . $os_id_post : ""));
    $stmt->execute([$item_id]);
    $itemDados = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($itemDados) {
        $origem = 'os_itens';
        if (!$os) {
            $stmt = $db->prepare("SELECT * FROM ordens_servico WHERE id = ?");
            $stmt->execute([(int) $itemDados['os_id']]);
            $os = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
}

if (!$os || !$itemDados) {
    echo json_encode(['success' => false, 'error' => 'Item não encontrado nesta O.S.']);
    exit;
}

// Status permitido: pré-produção, ou em produção na etapa de engenharia
$preProducao = in_array($os['status'], ['pendente', 'em_projeto', 'proposta', 'em_revisao'], true);
$engenhariaProduzindo = ($os['status'] === 'em_producao' && ($os['etapa_atual'] ?? '') === 'engenharia');
if (!$preProducao && !$engenhariaProduzindo) {
    echo json_encode(['success' => false, 'error' => 'Envio parcial só é permitido antes da produção ou enquanto a O.S. está na engenharia. Depois disso, use o retorno de etapa (com justificativa).']);
    exit;
}

// Item já despachado?
$stmt = $db->prepare("SELECT d.*, f.numero AS filho_numero FROM os_desmembramentos d LEFT JOIN ordens_servico f ON f.id = d.os_filho_id WHERE d.origem = ? AND d.item_id = ?");
$stmt->execute([$origem, $item_id]);
$jaDespachado = $stmt->fetch(PDO::FETCH_ASSOC);
if ($jaDespachado) {
    echo json_encode(['success' => false, 'error' => 'Este item já foi enviado para ' . $jaDespachado['setor'] . ($jaDespachado['filho_numero'] ? ' (' . $jaDespachado['filho_numero'] . ')' : '') . '.']);
    exit;
}

// Total de itens da O.S. e quantos ainda não foram despachados
if ($origem === 'vendas_itens') {
    $stmt = $db->prepare("SELECT COUNT(*) FROM vendas_itens WHERE venda_id = ?");
    $stmt->execute([(int) $os['venda_id']]);
} else {
    $stmt = $db->prepare("SELECT COUNT(*) FROM os_itens WHERE os_id = ?");
    $stmt->execute([(int) $os['id']]);
}
$totalItens = (int) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM os_desmembramentos WHERE os_pai_id = ?");
$stmt->execute([(int) $os['id']]);
$jaDespachados = (int) $stmt->fetchColumn();
$pendentes = $totalItens - $jaDespachados;

$db->beginTransaction();
try {
    $osFilhoId = null;
    $filhoNumero = null;

    if ($pendentes > 1) {
        // ------- Cria a O.S. FILHA com este item -------
        $seq = $jaDespachados + 1;
        $filhoNumero = $os['numero'] . '.' . $seq;
        // Garante numero único (colisões improváveis, mas seguras)
        $chk = $db->prepare("SELECT COUNT(*) FROM ordens_servico WHERE numero = ?");
        while (true) {
            $chk->execute([$filhoNumero]);
            if ((int) $chk->fetchColumn() === 0) break;
            $seq++;
            $filhoNumero = $os['numero'] . '.' . $seq;
        }

        $stmt = $db->prepare("INSERT INTO ordens_servico
            (numero, venda_id, cliente_id, data_inicio, data_termino, prioridade, status, etapa_atual, tipo, observacoes_gerais)
            VALUES (?, ?, ?, NOW(), ?, ?, 'em_producao', ?, ?, ?)");
        $stmt->execute([
            $filhoNumero,
            !empty($os['venda_id']) ? (int) $os['venda_id'] : null,
            (int) $os['cliente_id'],
            $os['data_termino'] ?: null,
            $os['prioridade'] ?? 'normal',
            $setor,
            $os['tipo'] ?? 'normal',
            trim('Desmembrada da ' . $os['numero'] . ' — item: ' . ($itemDados['descricao'] ?? ('#' . $item_id))),
        ]);
        $osFilhoId = (int) $db->lastInsertId();

        // Item da filha vive em os_itens (independente da origem do pai)
        $db->prepare("INSERT INTO os_itens (os_id, produto_id, descricao_manual, quantidade) VALUES (?, ?, ?, ?)")
           ->execute([$osFilhoId, $itemDados['produto_id'] ?: null, $itemDados['descricao_manual'] ?? null, $itemDados['quantidade'] ?? 1]);

        // Copia anexos da O.S. (mesmos arquivos em disco) — necessários para avançar etapas
        $db->prepare("INSERT INTO os_arquivos (os_id, tipo, nome_original, nome_arquivo, descricao, usuario_id)
            SELECT ?, tipo, nome_original, nome_arquivo, descricao, usuario_id FROM os_arquivos WHERE os_id = ?")
           ->execute([$osFilhoId, (int) $os['id']]);

        // Copia o planejamento de etapas pendentes do pai (o roteiro do produto)
        $db->prepare("INSERT INTO os_etapas_producao (os_id, etapa, status)
            SELECT ?, etapa, 'pendente' FROM os_etapas_producao WHERE os_id = ? AND status = 'pendente'")
           ->execute([$osFilhoId, (int) $os['id']]);

        // OP da filha — o nº da OP é o nº da O.S.
        $db->prepare("INSERT INTO ordens_producao (os_id, numero, status, criado_em) VALUES (?, ?, 'em_producao', NOW())")
           ->execute([$osFilhoId, $filhoNumero]);

        $db->prepare("INSERT INTO os_historico_status (os_id, status_anterior, status_novo, usuario_id, observacao) VALUES (?, ?, 'em_producao', ?, ?)")
           ->execute([$osFilhoId, $os['status'], $usuario['id'], 'Criada por desmembramento da ' . $os['numero'] . ' (item enviado para ' . $setor . ' por ' . $usuario['nome'] . ')']);
    } else {
        // ------- Último item: a própria O.S. vai para o setor -------
        $db->prepare("UPDATE ordens_servico SET etapa_atual = ?, status = 'em_producao' WHERE id = ?")
           ->execute([$setor, (int) $os['id']]);
        $db->prepare("INSERT INTO os_historico_status (os_id, status_anterior, status_novo, usuario_id, observacao) VALUES (?, ?, 'em_producao', ?, ?)")
           ->execute([(int) $os['id'], $os['status'], $usuario['id'], 'Último item enviado para ' . $setor . ' por ' . $usuario['nome'] . ' — O.S. movida.']);
    }

    // Garante que a O.S. (pai) tem OP — nº da OP = nº da O.S.
    $stmtOp = $db->prepare("SELECT id FROM ordens_producao WHERE os_id = ? LIMIT 1");
    $stmtOp->execute([(int) $os['id']]);
    if (!$stmtOp->fetch()) {
        $db->prepare("INSERT INTO ordens_producao (os_id, numero, status, criado_em) VALUES (?, ?, 'pendente', NOW())")
           ->execute([(int) $os['id'], $os['numero']]);
    }

    // Registra o despacho do item
    $db->prepare("INSERT INTO os_desmembramentos (os_pai_id, os_filho_id, origem, item_id, setor, usuario_id) VALUES (?, ?, ?, ?, ?, ?)")
       ->execute([(int) $os['id'], $osFilhoId, $origem, $item_id, $setor, (int) $usuario['id']]);

    $db->commit();
    echo json_encode([
        'success' => true,
        'os_filho' => $filhoNumero,
        'message' => $filhoNumero
            ? 'Item enviado para ' . $setor . ' na O.S. ' . $filhoNumero . '. Os demais itens continuam na ' . $os['numero'] . '.'
            : 'Último item enviado — a ' . $os['numero'] . ' foi para ' . $setor . '.',
    ]);
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('desmembrar_item: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao desmembrar o item.']);
}
