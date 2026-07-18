<?php
require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../includes/engenharia.php";

header("Content-Type: application/json");

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Sessão expirada."]);
    exit;
}

// Gerar/gerenciar OP é função de gestão, comercial e projeto — setores não
if (!hasPermission(['master', 'vendedor', 'projetista', 'gerente', 'producao'])) {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "Sem permissão para esta ação."]);
    exit;
}

// ── POST: gerar OP individual ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'gerar_op_individual') {
    $os_id = (int)($_POST['os_id'] ?? 0);
    if ($os_id <= 0) {
        echo json_encode(["success" => false, "error" => "ID da O.S. não fornecido."]);
        exit;
    }

    try {
        $db = getDB();
        ensureOrdensProducaoSchema($db);
        $db->beginTransaction();

        // Verificar se já existe OP para esta OS
        $stmt = $db->prepare("SELECT id FROM ordens_producao WHERE os_id = ? LIMIT 1");
        $stmt->execute([$os_id]);
        $opExistente = $stmt->fetch(PDO::FETCH_ASSOC);

        // Buscar dados da OS
        $stmt = $db->prepare("SELECT * FROM ordens_servico WHERE id = ?");
        $stmt->execute([$os_id]);
        $os = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$os) {
            echo json_encode(["success" => false, "error" => "O.S. não encontrada."]);
            exit;
        }

        // Buscar itens da OS
        $stmt = $db->prepare("SELECT * FROM os_itens WHERE os_id = ?");
        $stmt->execute([$os_id]);
        $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Gerar número da OP
        $numero_op = $os['numero']; // nº da OP = nº da O.S.

        // Inserir OP
        if ($opExistente) {
            $op_id = (int) $opExistente['id'];
        } else {
            $stmt = $db->prepare("INSERT INTO ordens_producao (os_id, numero, status, criado_em) VALUES (?, ?, 'pendente', NOW())");
            $stmt->execute([$os_id, $numero_op]);
            $op_id = $db->lastInsertId();
        }

        // Liberar a O.S. para produção (primeira etapa do roteiro planejado)
        $etapaInicial = getPrimeiraEtapaPlanejada($db, (int) $os_id);
        $stmt = $db->prepare("UPDATE ordens_servico SET status = 'em_producao', etapa_atual = ? WHERE id = ?");
        $stmt->execute([$etapaInicial, $os_id]);

        $stmt = $db->prepare("INSERT INTO os_historico_status (os_id, status_anterior, status_novo, usuario_id, observacao) VALUES (?, ?, 'em_producao', ?, ?)");
        $stmt->execute([$os_id, 'pendente', $_SESSION['usuario_id'], 'Ordem de produção gerada e liberada para ' . $etapaInicial]);

        // Inserir itens da OP
        foreach ($itens as $item) {
            $stmt = $db->prepare("INSERT INTO ordens_producao_itens (op_id, os_item_id, quantidade, valor_unitario) VALUES (?, ?, ?, ?)");
            $stmt->execute([$op_id, $item['id'], $item['quantidade'], $item['valor_unitario']]);
        }

        $db->commit();
        echo json_encode(["success" => true, "op_id" => $op_id, "numero_op" => $numero_op]);
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit;
}

// ── POST: gerar OP em massa ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'gerar_op_massa') {
    $os_ids = $_POST['os_ids'] ?? '';
    if (empty($os_ids)) {
        echo json_encode(["success" => false, "error" => "Nenhuma O.S. fornecida."]);
        exit;
    }

    $os_ids_array = array_map('intval', explode(',', $os_ids));
    $geradas = 0;
    $erros = [];

    try {
        $db = getDB();
        ensureOrdensProducaoSchema($db);
        $db->beginTransaction();

        foreach ($os_ids_array as $os_id) {
            if ($os_id <= 0) continue;

            // Verificar se já existe OP
            $stmt = $db->prepare("SELECT id FROM ordens_producao WHERE os_id = ? LIMIT 1");
            $stmt->execute([$os_id]);
            $opExistente = $stmt->fetch(PDO::FETCH_ASSOC);

            // Buscar dados da OS
            $stmt = $db->prepare("SELECT * FROM ordens_servico WHERE id = ?");
            $stmt->execute([$os_id]);
            $os = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$os) {
                $erros[] = "O.S. {$os_id} não encontrada";
                continue;
            }

            // Buscar itens
            $stmt = $db->prepare("SELECT * FROM os_itens WHERE os_id = ?");
            $stmt->execute([$os_id]);
            $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Gerar número da OP
            $numero_op = $os['numero']; // nº da OP = nº da O.S.

            // Inserir OP
            if ($opExistente) {
                $op_id = (int) $opExistente['id'];
            } else {
                $stmt = $db->prepare("INSERT INTO ordens_producao (os_id, numero, status, criado_em) VALUES (?, ?, 'pendente', NOW())");
                $stmt->execute([$os_id, $numero_op]);
                $op_id = $db->lastInsertId();
            }

            // Liberar a O.S. para produção (primeira etapa do roteiro planejado)
            $etapaInicial = getPrimeiraEtapaPlanejada($db, (int) $os_id);
            $stmt = $db->prepare("UPDATE ordens_servico SET status = 'em_producao', etapa_atual = ? WHERE id = ?");
            $stmt->execute([$etapaInicial, $os_id]);

            $stmt = $db->prepare("INSERT INTO os_historico_status (os_id, status_anterior, status_novo, usuario_id, observacao) VALUES (?, ?, 'em_producao', ?, ?)");
            $stmt->execute([$os_id, 'pendente', $_SESSION['usuario_id'], 'Ordem de produção gerada e liberada para ' . $etapaInicial]);

            // Inserir itens
            foreach ($itens as $item) {
                $stmt = $db->prepare("INSERT INTO ordens_producao_itens (op_id, os_item_id, quantidade, valor_unitario) VALUES (?, ?, ?, ?)");
                $stmt->execute([$op_id, $item['id'], $item['quantidade'], $item['valor_unitario']]);
            }

            $geradas++;
        }

        $db->commit();
        echo json_encode(["success" => true, "geradas" => $geradas, "erros" => $erros]);
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit;
}

// ── GET: buscar dados da OS ───────────────────────────────────────────────────────
$id = $_GET["id"] ?? null;

if (!$id) {
    echo json_encode(["success" => false, "error" => "ID da O.S. não fornecido."]);
    exit;
}

try {
    $db = getDB();
    ensureOrdensServicoIndependentesSchema($db);
    ensureEngenhariaSchema($db);

    // Buscar detalhes da OS e informações do cliente/venda
    $stmt = $db->prepare("
        SELECT os.*, c.razao_social,
               COALESCE(v.numero, 'O.S. Independente') as venda_numero,
               COALESCE(v.observacoes, '') as venda_observacoes
        FROM ordens_servico os
        INNER JOIN clientes c ON os.cliente_id = c.id
        LEFT JOIN vendas v ON os.venda_id = v.id
        WHERE os.id = ?
    ");
    $stmt->execute([$id]);
    $os = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$os) {
        echo json_encode(["success" => false, "error" => "O.S. não encontrada."]);
        exit;
    }

    $itens = getItensComerciaisOS($db, (int) $os['id'], (int) ($os['venda_id'] ?? 0));

    // Buscar arquivos
    $stmt_arq = $db->prepare("SELECT * FROM os_arquivos WHERE os_id = ?");
    $stmt_arq->execute([$id]);
    $arquivos = $stmt_arq->fetchAll(PDO::FETCH_ASSOC);

    $stmtEtapas = $db->prepare("
        SELECT etapa, status, data_inicio, data_fim, tempo_total_segundos
        FROM os_etapas_producao
        WHERE os_id = ?
        ORDER BY FIELD(etapa, 'corte', 'dobra', 'solda', 'refrigeracao', 'acabamento', 'finalizacao', 'montagem')
    ");
    $stmtEtapas->execute([$id]);
    $etapasPlanejadas = $stmtEtapas->fetchAll(PDO::FETCH_ASSOC);

    if (empty($etapasPlanejadas)) {
        $etapasPlanejadas = getPlanejamentoEtapasPorOS($db, (int) $os['id'], (int) ($os['venda_id'] ?? 0));
    }

    $componentesVenda = getComponentesPorOS($db, (int) $os['id'], (int) ($os['venda_id'] ?? 0));

    $stmtRecall = $db->prepare("
        SELECT
            lr.os_id,
            lr.justificativa,
            lr.created_at,
            lr.etapa_anterior,
            lr.etapa_retornada,
            u.nome AS usuario_nome
        FROM logs_retorno_etapa lr
        LEFT JOIN usuarios u ON u.id = lr.usuario_id
        WHERE lr.os_id = ?
        ORDER BY lr.id DESC
        LIMIT 1
    ");
    $stmtRecall->execute([$id]);
    $ultimoRecall = $stmtRecall->fetch(PDO::FETCH_ASSOC) ?: null;

    // Buscar checkups de qualidade já realizados (schema novo e legado)
    $checkups = [];
    $tem_qc_novo = (bool) $db->query("SHOW TABLES LIKE 'qualidade_checklist'")->fetchColumn();
    if ($tem_qc_novo) {
        $stmt_check = $db->prepare("SELECT * FROM qualidade_checklist WHERE os_id = ? ORDER BY id DESC");
        $stmt_check->execute([$id]);
        $checkups = $stmt_check->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $tem_qc_legado = (bool) $db->query("SHOW TABLES LIKE 'os_checkup_qualidade'")->fetchColumn();
        if ($tem_qc_legado) {
            $stmt_check = $db->prepare("SELECT * FROM os_checkup_qualidade WHERE os_id = ?");
            $stmt_check->execute([$id]);
            $checkups = $stmt_check->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    $historico = [];
    $temHistorico = (bool) $db->query("SHOW TABLES LIKE 'os_historico_status'")->fetchColumn();
    if ($temHistorico) {
        $colunasHistorico = $db->query("SHOW COLUMNS FROM os_historico_status")->fetchAll(PDO::FETCH_COLUMN);
        $colunasHistorico = array_map('strtolower', $colunasHistorico);

        $colunaDataHistorico = 'id';
        if (in_array('criado_em', $colunasHistorico, true)) {
            $colunaDataHistorico = 'criado_em';
        } elseif (in_array('created_at', $colunasHistorico, true)) {
            $colunaDataHistorico = 'created_at';
        } elseif (in_array('data_alteracao', $colunasHistorico, true)) {
            $colunaDataHistorico = 'data_alteracao';
        }

        $sqlHistorico = "
            SELECT
                h.id,
                h.status_anterior,
                h.status_novo,
                h.observacao,
                h.{$colunaDataHistorico} AS criado_em,
                u.nome AS usuario_nome
            FROM os_historico_status h
            LEFT JOIN usuarios u ON u.id = h.usuario_id
            WHERE h.os_id = ?
            ORDER BY h.{$colunaDataHistorico} DESC, h.id DESC
        ";

        $stmtHistorico = $db->prepare($sqlHistorico);
        $stmtHistorico->execute([$id]);
        $historico = $stmtHistorico->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        "success" => true,
        "os" => $os,
        "itens" => $itens,
        "arquivos" => $arquivos,
        "etapas_planejadas" => $etapasPlanejadas,
        "componentes_venda" => $componentesVenda,
        "ultimo_recall" => $ultimoRecall,
        "checkups" => $checkups,
        "historico" => $historico
    ]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
