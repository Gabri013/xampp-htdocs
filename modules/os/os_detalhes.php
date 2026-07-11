<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/engenharia.php';
// Leitura liberada também para gestão e setores de produção (ver detalhes/etapas da O.S.)
requirePermission(['master', 'vendedor', 'projetista', 'gerente', 'producao', 'producao_geral', 'engenharia', 'programacao', 'corte', 'dobra', 'tubo', 'solda', 'mobiliario', 'coccao', 'refrigeracao', 'acabamento', 'montagem', 'embalagem', 'finalizacao']);

// Ações de escrita (gerar OP, propostas, anexos) continuam restritas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hasPermission(['master', 'vendedor', 'projetista', 'gerente'])) {
    setError('Você não tem permissão para executar esta ação.');
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$page_title = 'Detalhes da O.S.';
$db = getDB();
ensureOrdensServicoIndependentesSchema($db);
ensureEngenhariaSchema($db);

function getTipoArquivoProducao(string $nomeArquivo): string
{
    if (preg_match('/\.(dxf)$/i', $nomeArquivo)) {
        return 'projeto_dxf';
    }
    if (preg_match('/\.(pdf)$/i', $nomeArquivo)) {
        return 'projeto_pdf';
    }
    if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $nomeArquivo)) {
        return 'projeto_foto';
    }
    return 'projeto';
}

// Primeira etapa do planejamento da O.S. (os_etapas_producao); usa o fluxo
// canônico como ordem e 'corte' apenas como último recurso.
function getPrimeiraEtapaPlanejada(PDO $db, int $osId): string
{
    require_once __DIR__ . '/../../includes/workflow.php';
    $stmtVenda = $db->prepare("SELECT venda_id FROM ordens_servico WHERE id = ?");
    $stmtVenda->execute([$osId]);
    $vendaId = (int) $stmtVenda->fetchColumn();

    $etapasPlanejadas = sincronizarPlanejamentoOS($db, $osId, max(0, $vendaId));
    $etapas = array_column($etapasPlanejadas, 'etapa');
    if (!empty($etapas)) {
        foreach (getEtapaFluxo() as $etapaFluxo) {
            if (in_array($etapaFluxo, $etapas, true)) {
                return $etapaFluxo;
            }
        }
    }
    return 'corte';
}

function garantirOrdemProducao(PDO $db, int $osId, int $usuarioId): array
{
    require_once __DIR__ . '/../../includes/workflow.php';
    $stmt = $db->prepare("SELECT id, numero, status FROM ordens_producao WHERE os_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$osId]);
    $op = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($op) {
        return ['created' => false, 'op' => $op];
    }

    $numeroOp = 'OP-' . date('Y') . '-' . str_pad((string) $osId, 6, '0', STR_PAD_LEFT);
    $stmt = $db->prepare("INSERT INTO ordens_producao (os_id, numero, status, criado_em) VALUES (?, ?, 'pendente', NOW())");
    $stmt->execute([$osId, $numeroOp]);

    $stmtStatus = $db->prepare("SELECT status FROM ordens_servico WHERE id = ? LIMIT 1");
    $stmtStatus->execute([$osId]);
    $statusAtual = (string) $stmtStatus->fetchColumn();
    $validation = validateOSStatusTransition($statusAtual, 'em_producao', $_SESSION['usuario_tipo'] ?? '');
    if (!$validation['valid']) {
        throw new RuntimeException($validation['message']);
    }

    $etapaInicial = getPrimeiraEtapaPlanejada($db, $osId);
    $stmt = $db->prepare("UPDATE ordens_servico SET status = 'em_producao', etapa_atual = ? WHERE id = ?");
    $stmt->execute([$etapaInicial, $osId]);

    $stmt = $db->prepare("INSERT INTO os_historico_status (os_id, status_anterior, status_novo, usuario_id, observacao) VALUES (?, ?, 'em_producao', ?, ?)");
    $stmt->execute([$osId, $statusAtual ?: 'pendente', $usuarioId, 'Ordem de produção gerada e liberada para ' . $etapaInicial]);

    return [
        'created' => true,
        'op' => [
            'id' => (int) $db->lastInsertId(),
            'numero' => $numeroOp,
            'status' => 'pendente',
        ],
    ];
}

$os_id = isset($_GET['os_id']) ? (int)$_GET['os_id'] : 0;
if ($os_id <= 0) {
    header('Location: projetista.php');
    exit;
}

$os = null;
$op_atual = null;

// Carregar dados da OS primeiro para permitir ações de produção.
$s = $db->prepare("SELECT o.*, c.razao_social, c.nome_fantasia, v.numero as venda_numero, v.data_venda, COALESCE(uv.nome,'-') as vendedor_nome, COALESCE(v.observacoes,'') as obs_vendedor FROM ordens_servico o LEFT JOIN clientes c ON o.cliente_id = c.id LEFT JOIN vendas v ON o.venda_id = v.id LEFT JOIN usuarios uv ON v.usuario_id = uv.id WHERE o.id = ?");
$s->execute([$os_id]);
$os = $s->fetch(PDO::FETCH_ASSOC);
if (!$os) {
    header('Location: projetista.php');
    exit;
}

$stmtOpAtual = $db->prepare("SELECT id, numero, status FROM ordens_producao WHERE os_id = ? ORDER BY id DESC LIMIT 1");
$stmtOpAtual->execute([$os_id]);
$op_atual = $stmtOpAtual->fetch(PDO::FETCH_ASSOC) ?: null;

// POST: gerar OP em lote para a O.S.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'gerar_op_lote') {
    try {
        $db->beginTransaction();
        $opInfo = garantirOrdemProducao($db, $os_id, (int) $_SESSION['usuario_id']);

        $stmtStatus = $db->prepare("SELECT status FROM ordens_servico WHERE id = ? LIMIT 1");
        $stmtStatus->execute([$os_id]);
        $statusAtual = (string) $stmtStatus->fetchColumn();
        if ($statusAtual !== 'em_producao') {
            $etapaInicial = getPrimeiraEtapaPlanejada($db, (int) $os_id);
            $stmt = $db->prepare("UPDATE ordens_servico SET status = 'em_producao', etapa_atual = ? WHERE id = ?");
            $stmt->execute([$etapaInicial, $os_id]);

            $stmt = $db->prepare("INSERT INTO os_historico_status (os_id, status_anterior, status_novo, usuario_id, observacao) VALUES (?, ?, 'em_producao', ?, ?)");
            $stmt->execute([$os_id, $statusAtual ?: 'pendente', (int) $_SESSION['usuario_id'], 'Ordem de produção liberada para ' . $etapaInicial]);
        }
        $db->commit();
        setSuccess($opInfo['created'] ? 'Ordem de produção gerada para a O.S.' : 'Esta O.S. já possuía ordem de produção.');
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        setError('Erro ao gerar OP: ' . $e->getMessage());
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// POST: gerar OP individual do item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'gerar_op_item') {
    $itemId = (int) ($_POST['os_item_id'] ?? 0);
    if ($itemId <= 0) {
        setError('Item inválido.');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    try {
        $db->beginTransaction();
        $opInfo = garantirOrdemProducao($db, $os_id, (int) $_SESSION['usuario_id']);

        $stmtStatus = $db->prepare("SELECT status FROM ordens_servico WHERE id = ? LIMIT 1");
        $stmtStatus->execute([$os_id]);
        $statusAtual = (string) $stmtStatus->fetchColumn();
        if ($statusAtual !== 'em_producao') {
            $etapaInicial = getPrimeiraEtapaPlanejada($db, (int) $os_id);
            $stmt = $db->prepare("UPDATE ordens_servico SET status = 'em_producao', etapa_atual = ? WHERE id = ?");
            $stmt->execute([$etapaInicial, $os_id]);

            $stmt = $db->prepare("INSERT INTO os_historico_status (os_id, status_anterior, status_novo, usuario_id, observacao) VALUES (?, ?, 'em_producao', ?, ?)");
            $stmt->execute([$os_id, $statusAtual ?: 'pendente', (int) $_SESSION['usuario_id'], 'Ordem de produção liberada para ' . $etapaInicial]);
        }
        $db->commit();
        setSuccess($opInfo['created'] ? 'Ordem de produção gerada para o item.' : 'Item vinculado a uma OP já existente.');
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        setError('Erro ao gerar OP do item: ' . $e->getMessage());
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// POST: anexar arquivo por item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'anexar_arquivo_item') {
    $osId = (int)($_POST['os_id'] ?? 0);
    $itemId = (int)($_POST['os_item_id'] ?? 0);
    $arqNome = $_FILES['arquivo']['name'] ?? '';
    $tipoArquivo = getTipoArquivoProducao($arqNome);
    
    if ($osId > 0 && $itemId > 0 && !empty($_SESSION['usuario_id'])) {
        $up = uploadFile($_FILES['arquivo'], 'projetos');
        if ($up['success'] ?? false) {
            $db->prepare("INSERT INTO os_itens_arquivos (os_id, os_item_id, tipo, nome_original, nome_arquivo, usuario_id) VALUES (?, ?, ?, ?, ?, ?)")
               ->execute([$osId, $itemId, $tipoArquivo, $_FILES['arquivo']['name'], $up['filename'], $_SESSION['usuario_id']]);
            $db->prepare("INSERT INTO os_arquivos (os_id, tipo, nome_original, nome_arquivo, usuario_id) VALUES (?, ?, ?, ?, ?)")
               ->execute([$osId, $tipoArquivo, $_FILES['arquivo']['name'], $up['filename'], $_SESSION['usuario_id']]);
            setSuccess('Arquivo anexado ao item com sucesso.');
        } else {
            setError('Erro ao anexar arquivo: ' . ($up['message'] ?? 'arquivo inválido'));
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// POST: salvar proposta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar_proposta') {
    require_once __DIR__ . '/../../includes/workflow.php';
    $uid = (int)$_SESSION['usuario_id'];
    $statusAtual = (string) ($os['status'] ?? '');
    $validation = validateOSStatusTransition($statusAtual, 'proposta', $_SESSION['usuario_tipo'] ?? '');
    if (!$validation['valid']) {
        setError($validation['message']);
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $alteracoes = trim($_POST['alteracoes'] ?? '');
    $up = uploadFile($_FILES['proposta_pdf'], 'propostas');
    if ($up['success'] ?? false) {
        $db->prepare("UPDATE ordens_servico SET tipo='projeto', alteracoes_projeto=?, status='proposta' WHERE id=?")
           ->execute([$alteracoes, $os_id]);
        $db->prepare("INSERT INTO os_arquivos (os_id, tipo, nome_original, nome_arquivo, usuario_id) VALUES (?, 'projeto_pdf', ?, ?, ?)")
           ->execute([$os_id, $_FILES['proposta_pdf']['name'], $up['filename'], $uid]);
        $db->prepare("INSERT INTO os_historico_status (os_id, status_anterior, status_novo, usuario_id, observacao) VALUES (?, ?, 'proposta', ?, 'Proposta enviada para aprovação')")
           ->execute([$os_id, $statusAtual, $uid]);
        setSuccess('Proposta enviada com sucesso!');
    } else { setError('Erro ao enviar proposta: ' . ($up['message'] ?? '')); }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// POST: aprovar proposta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'aprovar_proposta') {
    require_once __DIR__ . '/../../includes/workflow.php';
    $uid = (int)$_SESSION['usuario_id'];
    $validation = validateCanApproveProposal($_SESSION['usuario_tipo'] ?? '');
    if (!$validation['valid']) {
        setError($validation['message']);
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $statusAtual = (string) ($os['status'] ?? '');
    $validation = validateOSStatusTransition($statusAtual, 'em_producao', $_SESSION['usuario_tipo'] ?? '');
    if (!$validation['valid']) {
        setError($validation['message']);
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $etapaInicialAprov = getPrimeiraEtapaPlanejada($db, (int) $os_id);
    $db->prepare("UPDATE ordens_servico SET status='em_producao', etapa_atual=? WHERE id=?")->execute([$etapaInicialAprov, $os_id]);
    $db->prepare("INSERT INTO os_historico_status (os_id, status_anterior, status_novo, usuario_id, observacao) VALUES (?, ?, 'em_producao', ?, 'Proposta aprovada')")
       ->execute([$os_id, $statusAtual, $uid]);
    setSuccess('Proposta aprovada!');
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// POST: devolver proposta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'devolver_proposta') {
    require_once __DIR__ . '/../../includes/workflow.php';
    $uid = (int)$_SESSION['usuario_id'];
    $motivo = trim($_POST['motivo'] ?? '');
    $statusAtual = (string) ($os['status'] ?? '');
    $validation = validateOSStatusTransition($statusAtual, 'em_projeto', $_SESSION['usuario_tipo'] ?? '');
    if (!$validation['valid']) {
        setError($validation['message']);
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $db->prepare("UPDATE ordens_servico SET status='em_projeto', etapa_atual=NULL WHERE id=?")->execute([$os_id]);
    $db->prepare("INSERT INTO os_historico_status (os_id, status_anterior, status_novo, usuario_id, observacao) VALUES (?, ?, 'em_projeto', ?, ?)")
       ->execute([$os_id, $statusAtual, $uid, $motivo ?: 'Proposta devolvida para ajustes']);
    setSuccess('Proposta devolvida!');
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Itens
if (!empty($os['venda_id'])) {
    $si = $db->prepare("SELECT vi.id, vi.produto_id, vi.descricao_manual, vi.quantidade, p.codigo, COALESCE(p.nome,vi.descricao_manual) as descricao FROM vendas_itens vi LEFT JOIN produtos p ON vi.produto_id=p.id WHERE vi.venda_id=? ORDER BY vi.id");
} else {
    $si = $db->prepare("SELECT oi.id, oi.produto_id, oi.descricao_manual, oi.quantidade, p.codigo, COALESCE(p.nome,oi.descricao_manual) as descricao FROM os_itens oi LEFT JOIN produtos p ON oi.produto_id=p.id WHERE oi.os_id=? ORDER BY oi.id");
}
$si->execute([$os['venda_id'] ? $os['venda_id'] : $os_id]);
$itens = $si->fetchAll(PDO::FETCH_ASSOC);

// Arquivos por item
$arquivosPorItem = [];
if (!empty($itens)) {
    $stmtArqItem = $db->prepare("SELECT * FROM os_itens_arquivos WHERE os_id = ? ORDER BY id DESC");
    $stmtArqItem->execute([$os_id]);
    foreach ($stmtArqItem->fetchAll(PDO::FETCH_ASSOC) as $arqItem) {
        $arquivosPorItem[(int) $arqItem['os_item_id']][] = $arqItem;
    }
}

include '../../includes/header_vendedor.php';
?>
<div class="vend-layout">
    <?php $GLOBALS['modulo_tipo'] = 'projetista'; include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-page-head">
            <div><h1 class="vend-page-title"><?= htmlspecialchars($os['numero']) ?></h1></div>
            <a href="imprimir_op.php?os_id=<?= $os_id ?>" target="_blank" class="vbtn-sm" title="Imprimir O.S."><i class="fas fa-print"></i> Imprimir</a>
        </div>
        
        <div class="vend-card">
            <div class="vend-card-head"><span class="vend-card-title">Detalhes da Ordem</span></div>
            <div class="vend-metrics">
                <div class="vend-metric"><div class="vend-metric-label">Cliente</div><div class="vend-metric-val"><?= htmlspecialchars($os['razao_social']) ?></div></div>
                <div class="vend-metric"><div class="vend-metric-label">Status</div><div class="vend-metric-val"><span class="vbadge <?= $os['status']==='em_producao'?'vbadge-prod':($os['status']==='proposta'?'vbadge-warn':'vbadge-info') ?>"><?= getStatusOSNome($os['status']) ?></span></div></div>
                <div class="vend-metric"><div class="vend-metric-label">Venda</div><div class="vend-metric-val"><?= $os['venda_numero'] ? htmlspecialchars($os['venda_numero']) : 'Independente' ?></div></div>
                <div class="vend-metric"><div class="vend-metric-label">Vendedor</div><div class="vend-metric-val"><?= htmlspecialchars($os['vendedor_nome']) ?></div></div>
            </div>
        </div>

        <div class="vend-card" style="margin-top:20px;">
            <div class="vend-card-head">
                <span class="vend-card-title">Ações de Produção</span>
                <?php if ($op_atual): ?>
                    <span class="vbadge vbadge-info"><?= htmlspecialchars($op_atual['numero']) ?> · <?= htmlspecialchars(ucfirst($op_atual['status'] ?? 'pendente')) ?></span>
                <?php endif; ?>
            </div>
            <div class="vend-card-body" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <form method="POST" style="display:inline">
                    <input type="hidden" name="acao" value="gerar_op_lote">
                    <button type="submit" class="vbtn-sm btn-primary"><i class="fas fa-layer-group"></i> Gerar OP em lote</button>
                </form>
                <?php if ($op_atual): ?>
                    <a href="imprimir_op.php?os_id=<?= $os_id ?>" target="_blank" class="vbtn-sm"><i class="fas fa-print"></i> Abrir OP</a>
                <?php endif; ?>
                <span class="vend-page-sub">Use os botões por item para anexar PDF/DXF e gerar a OP individual quando necessário.</span>
            </div>
        </div>

        <?php if ($os['status'] === 'em_projeto'): ?>
        <div class="vend-card">
            <div class="vend-card-head"><span class="vend-card-title">Enviar Proposta</span></div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="acao" value="salvar_proposta">
                <div class="form-group"><label>Alterações do Projeto</label><textarea name="alteracoes" class="form-control" rows="3" placeholder="Descrição das alterações..."></textarea></div>
                <div class="form-group"><label>Arquivo PDF da Proposta</label><input type="file" name="proposta_pdf" accept="application/pdf" class="form-control" required></div>
                <button type="submit" class="vbtn-sm btn-success"><i class="fas fa-paper-plane"></i> Enviar para Aprovação</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($os['status'] === 'proposta'): ?>
        <div class="vend-card">
            <div class="vend-card-head"><span class="vend-card-title">Proposta em Análise</span></div>
            <?php if (!empty($arquivos)): foreach ($arquivos as $arq): ?>
                <a href="<?= SITE_URL ?>/assets/uploads/propostas/<?= $arq['nome_arquivo'] ?>" class="vbadge vbadge-info" target="_blank"><i class="fas fa-file-pdf"></i> <?= htmlspecialchars($arq['nome_original']) ?></a>
            <?php endforeach; endif; ?>
            <form method="POST" style="margin-top:12px">
                <input type="hidden" name="acao" value="aprovar_proposta">
                <button type="submit" class="vbtn-sm btn-success"><i class="fas fa-check"></i> Aprovar Proposta</button>
                <button type="button" class="vbtn-sm" onclick="document.getElementById('motivo-devolucao').style.display='block'"><i class="fas fa-undo"></i> Devolver</button>
            </form>
            <div id="motivo-devolucao" style="display:none;margin-top:12px">
                <form method="POST">
                    <input type="hidden" name="acao" value="devolver_proposta">
                    <textarea name="motivo" class="form-control" rows="2" placeholder="Motivo da devolução..." required></textarea>
                    <button type="submit" class="vbtn-sm btn-danger" style="margin-top:8px">Confirmar Devolução</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="vend-card">
            <div class="vend-card-head"><span class="vend-card-title">Itens da O.S.</span></div>
            <div class="vend-table-wrap">
                <table class="vend-table">
                    <thead><tr><th>#</th><th>Código</th><th>Descrição</th><th>Qtd</th><th>Setor</th><th>Ações</th></tr></thead>
                    <tbody>
                        <?php if (empty($itens)): ?>
                            <tr><td colspan="6" class="vend-empty">Nenhum item encontrado.</td></tr>
                        <?php else: foreach ($itens as $item): ?>
                            <tr>
                                <td><?= $item['id'] ?></td>
                                <td><?= htmlspecialchars($item['codigo'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($item['descricao']) ?></td>
                                <td><?= $item['quantidade'] ?></td>
<td>
                                    <?php if ($os['status'] === 'pendente' || $os['status'] === 'em_projeto' || $os['status'] === 'proposta'): ?>
                                        <select onchange="enviarItemSetor(<?= $item['id'] ?>, this.value)">
                                            <option value="">-- Setor --</option>
                                            <option value="corte">Corte</option>
                                            <option value="dobra">Dobra</option>
                                            <option value="solda">Solda</option>
                                            <option value="refrigeracao">Refrigeração</option>
                                            <option value="acabamento">Acabamento</option>
                                            <option value="montagem">Montagem</option>
                                        </select>
                                    <?php else: ?>
                                        <span class="vbadge vbadge-info"><?= ucfirst($os['etapa_atual'] ?? 'N/A') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display:flex;gap:4px;flex-wrap:wrap;align-items:center;">
                                        <?php if ($os['status'] === 'pendente' || $os['status'] === 'em_projeto' || $os['status'] === 'proposta' || $os['status'] === 'em_producao'): ?>
                                            <a href="imprimir_op.php?os_id=<?= $os_id ?>&item_id=<?= $item['id'] ?>" target="_blank" class="vbtn-sm" title="Abrir OP do item"><i class="fas fa-print"></i></a>
                                            <form method="POST" style="display:inline">
                                                <input type="hidden" name="acao" value="gerar_op_item">
                                                <input type="hidden" name="os_item_id" value="<?= $item['id'] ?>">
                                                <button type="submit" class="vbtn-sm btn-primary" title="Gerar OP do item"><i class="fas fa-cubes"></i></button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($os['status'] === 'pendente' || $os['status'] === 'em_projeto' || $os['status'] === 'proposta' || $os['status'] === 'em_revisao'): ?>
                                            <form method="POST" enctype="multipart/form-data" style="display:inline" id="form-pdf-<?= $item['id'] ?>">
                                                <input type="hidden" name="acao" value="anexar_arquivo_item">
                                                <input type="hidden" name="os_id" value="<?= $os_id ?>">
                                                <input type="hidden" name="os_item_id" value="<?= $item['id'] ?>">
                                                <input type="file" name="arquivo" accept=".pdf" onchange="document.getElementById('form-pdf-<?= $item['id'] ?>').submit()" style="display:none" id="upload-pdf-<?= $item['id'] ?>">
                                                <label for="upload-pdf-<?= $item['id'] ?>" class="vbtn-sm" title="Anexar PDF"><i class="fas fa-file-pdf"></i></label>
                                            </form>
                                            <form method="POST" enctype="multipart/form-data" style="display:inline" id="form-dxf-<?= $item['id'] ?>">
                                                <input type="hidden" name="acao" value="anexar_arquivo_item">
                                                <input type="hidden" name="os_id" value="<?= $os_id ?>">
                                                <input type="hidden" name="os_item_id" value="<?= $item['id'] ?>">
                                                <input type="file" name="arquivo" accept=".dxf" onchange="document.getElementById('form-dxf-<?= $item['id'] ?>').submit()" style="display:none" id="upload-dxf-<?= $item['id'] ?>">
                                                <label for="upload-dxf-<?= $item['id'] ?>" class="vbtn-sm" title="Anexar DXF"><i class="fas fa-drafting-compass"></i></label>
                                            </form>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($arquivosPorItem[$item['id']])): ?>
                                        <div style="margin-top:6px;display:flex;gap:4px;flex-wrap:wrap;">
                                            <?php foreach ($arquivosPorItem[$item['id']] as $arqItem): ?>
                                                <a href="<?= SITE_URL ?>/assets/uploads/projetos/<?= htmlspecialchars($arqItem['nome_arquivo']) ?>" target="_blank" class="vbadge vbadge-info"><?= htmlspecialchars($arqItem['tipo']) ?></a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Arquivos Anexados -->
        <div class="vend-card" style="margin-top:20px;">
            <div class="vend-card-head"><span class="vend-card-title">Arquivos Anexados</span></div>
            <div class="vend-card-body">
                <?php
                $arquivosOS = $db->prepare("SELECT * FROM os_arquivos WHERE os_id = ? AND tipo IN ('projeto_pdf', 'projeto_dxf', 'projeto_foto') ORDER BY id DESC");
                $arquivosOS->execute([$os_id]);
                $arqsOS = $arquivosOS->fetchAll();
                if (!empty($arqsOS)): 
                    foreach ($arqsOS as $arq): 
                        $url = SITE_URL . '/assets/uploads/projetos/' . htmlspecialchars($arq['nome_arquivo']);
                ?>
                    <a href="<?= $url ?>" class="vbadge vbadge-info" target="_blank" style="margin:2px;">
                        <i class="fas fa-file-pdf"></i> <?= htmlspecialchars($arq['nome_original']) ?>
                    </a>
                <?php endforeach; else: ?>
                    <p class="text-muted">Nenhum arquivo anexado.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function enviarItemSetor(itemId, setor) {
    if (!setor) return;
    if (confirm('Enviar item #' + itemId + ' para ' + setor + '?')) {
        fetch('desmembrar_item.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'item_id=' + itemId + '&setor=' + setor
        }).then(r => r.json()).then(d => {
            if (d.success) location.reload();
        });
    }
}
</script>

<?php include '../../includes/footer_vendedor.php'; ?>