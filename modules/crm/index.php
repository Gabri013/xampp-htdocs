<?php
require_once '../../config/config.php';
require_once '../../includes/crm.php';
requirePermission(['master', 'vendedor', 'gerente']);

$page_title = 'CRM — Pipeline';
$db = getDB();
ensureCrmSchema($db);
$usuario = getCurrentUser();
$estagios = getCrmEstagios();

// ── Ações ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'nova_oportunidade') {
        $titulo = sanitize($_POST['titulo'] ?? '');
        $cliente_id = (int)($_POST['cliente_id'] ?? 0) ?: null;
        $contato_id = (int)($_POST['contato_id'] ?? 0) ?: null;
        $valor = (float)($_POST['valor_estimado'] ?? 0);
        $origem = sanitize($_POST['origem'] ?? '');
        $previsao = $_POST['previsao_fechamento'] ?: null;

        if ($titulo === '') {
            setError('Informe o título da oportunidade.');
        } else {
            $stmt = $db->prepare("INSERT INTO crm_oportunidades (titulo, cliente_id, contato_id, valor_estimado, estagio, origem, responsavel_id, previsao_fechamento) VALUES (?, ?, ?, ?, 'lead', ?, ?, ?)");
            $stmt->execute([$titulo, $cliente_id, $contato_id, $valor, $origem ?: null, $usuario['id'], $previsao]);
            setSuccess('Oportunidade criada no pipeline!');
            header('Location: oportunidade.php?id=' . $db->lastInsertId());
            exit;
        }
        header('Location: index.php');
        exit;
    }
}

// ── Dados do pipeline ────────────────────────────────────────────────
[$filtroSql, $filtroParams] = crmFiltroResponsavel($usuario);
$stmt = $db->prepare("
    SELECT o.*, c.razao_social, ct.nome AS contato_nome, u.nome AS responsavel_nome,
           (SELECT COUNT(*) FROM crm_atividades a WHERE a.oportunidade_id = o.id AND a.tipo = 'tarefa' AND a.concluida = 0) AS tarefas_abertas
    FROM crm_oportunidades o
    LEFT JOIN clientes c ON c.id = o.cliente_id
    LEFT JOIN crm_contatos ct ON ct.id = o.contato_id
    LEFT JOIN usuarios u ON u.id = o.responsavel_id
    WHERE 1=1 $filtroSql
    ORDER BY o.updated_at DESC
");
$stmt->execute($filtroParams);
$oportunidades = $stmt->fetchAll();

$clientes = $db->query("SELECT id, razao_social FROM clientes ORDER BY razao_social")->fetchAll();
$contatos = $db->query("SELECT id, nome FROM crm_contatos ORDER BY nome")->fetchAll();

include '../../includes/header_vendedor.php';
?>
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/kanban.css?v=<?php echo @filemtime(BASE_PATH . '/assets/css/kanban.css') ?: '1'; ?>">

<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-page-head">
            <div>
                <h1 class="vend-page-title">CRM — Pipeline de Vendas</h1>
                <p class="vend-page-sub">Arraste a oportunidade entre os estágios do funil</p>
            </div>
            <div style="display:flex;gap:8px">
                <a href="contatos.php" class="vbtn-sm"><i class="fas fa-address-book"></i> Contatos</a>
                <button type="button" class="vbtn-sm vbtn-brand" onclick="document.getElementById('modalNovaOp').style.display='flex'"><i class="fas fa-plus"></i> Nova Oportunidade</button>
            </div>
        </div>

        <div class="vend-kanban" id="vendKanban">
            <div class="vend-kanban-board" id="crmKanbanBoard">
                <?php foreach ($estagios as $estKey => $est):
                    $cards = array_values(array_filter($oportunidades, fn($o) => $o['estagio'] === $estKey));
                    $soma = array_sum(array_map(fn($o) => (float)$o['valor_estimado'], $cards));
                ?>
                <div class="vend-kanban-column" data-estagio="<?php echo $estKey; ?>">
                    <div class="vend-kanban-header" style="border-top:3px solid <?php echo $est['cor']; ?>">
                        <span class="vend-kanban-title"><?php echo $est['label']; ?></span>
                        <span style="display:flex;flex-direction:column;align-items:flex-end;gap:1px">
                            <span class="vend-kanban-count"><?php echo count($cards); ?></span>
                            <span style="font-size:10px;color:#16a34a;font-weight:700"><?php echo $soma > 0 ? formatMoney($soma) : ''; ?></span>
                        </span>
                    </div>
                    <div class="vend-kanban-items">
                        <?php foreach ($cards as $op): ?>
                        <div class="vend-kanban-card" draggable="true" data-id="<?php echo (int)$op['id']; ?>">
                            <div class="kb-card-top">
                                <span class="kb-num">#<?php echo (int)$op['id']; ?></span>
                                <?php if ((int)$op['tarefas_abertas'] > 0): ?>
                                    <span class="vbadge vbadge-warn" style="font-size:9px" title="Tarefas abertas"><i class="fas fa-check-square"></i> <?php echo (int)$op['tarefas_abertas']; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="vend-kanban-card-title"><?php echo htmlspecialchars($op['titulo']); ?></div>
                            <?php if (!empty($op['razao_social'])): ?>
                                <div class="vend-kanban-card-subtitle"><i class="far fa-building"></i> <?php echo htmlspecialchars($op['razao_social']); ?></div>
                            <?php endif; ?>
                            <div class="kb-card-meta">
                                <?php if ((float)$op['valor_estimado'] > 0): ?>
                                    <span class="vend-kanban-card-value"><?php echo formatMoney($op['valor_estimado']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($op['previsao_fechamento'])): ?>
                                    <span class="kb-prazo <?php echo $op['previsao_fechamento'] < date('Y-m-d') && !in_array($estKey, ['ganho','perdido']) ? 'kb-prazo-atrasado' : ''; ?>"><i class="far fa-calendar"></i> <?php echo formatDate($op['previsao_fechamento']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($op['responsavel_nome'])): ?>
                                    <span class="kb-etapa" title="Responsável"><i class="far fa-user"></i> <?php echo htmlspecialchars(explode(' ', $op['responsavel_nome'])[0]); ?></span>
                                <?php endif; ?>
                            </div>
                            <a class="kb-card-link" href="oportunidade.php?id=<?php echo (int)$op['id']; ?>" title="Abrir oportunidade"><i class="fas fa-external-link-alt"></i></a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal nova oportunidade -->
<div id="modalNovaOp" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1060;align-items:center;justify-content:center;padding:16px">
    <div style="background:#fff;border-radius:14px;max-width:480px;width:100%;padding:22px;max-height:90vh;overflow:auto">
        <h3 style="margin-bottom:14px"><i class="fas fa-bullseye" style="color:#D85A30"></i> Nova Oportunidade</h3>
        <form method="POST">
            <input type="hidden" name="acao" value="nova_oportunidade">
            <div class="form-group" style="margin-bottom:10px">
                <label class="form-label">Título *</label>
                <input type="text" name="titulo" class="form-control" required placeholder="Ex.: Cozinha industrial — Restaurante X">
            </div>
            <div class="form-group" style="margin-bottom:10px">
                <label class="form-label">Empresa (cliente)</label>
                <select name="cliente_id" class="form-control">
                    <option value="">— Sem vínculo ainda —</option>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['razao_social']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:10px">
                <label class="form-label">Contato (pessoa)</label>
                <select name="contato_id" class="form-control">
                    <option value="">—</option>
                    <?php foreach ($contatos as $ct): ?>
                        <option value="<?php echo $ct['id']; ?>"><?php echo htmlspecialchars($ct['nome']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <div class="form-group">
                    <label class="form-label">Valor estimado (R$)</label>
                    <input type="number" step="0.01" name="valor_estimado" class="form-control" value="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Previsão de fechamento</label>
                    <input type="date" name="previsao_fechamento" class="form-control">
                </div>
            </div>
            <div class="form-group" style="margin:10px 0">
                <label class="form-label">Origem</label>
                <select name="origem" class="form-control">
                    <option value="">—</option>
                    <option>Indicação</option><option>Site</option><option>WhatsApp</option>
                    <option>Instagram</option><option>Telefone</option><option>Feira/Evento</option><option>Carteira</option>
                </select>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px">
                <button type="button" class="vbtn-sm" onclick="document.getElementById('modalNovaOp').style.display='none'">Cancelar</button>
                <button type="submit" class="vbtn-sm vbtn-brand"><i class="fas fa-save"></i> Criar</button>
            </div>
        </form>
    </div>
</div>

<script>
// Drag-drop do pipeline: move oportunidade de estágio via api/crm_move.php
(function () {
    const board = document.getElementById('crmKanbanBoard');
    if (!board) return;
    let dragged = null;

    board.addEventListener('dragstart', e => {
        if (e.target.classList.contains('vend-kanban-card')) { dragged = e.target; e.target.classList.add('dragging'); }
    });
    board.addEventListener('dragend', e => {
        if (e.target.classList.contains('vend-kanban-card')) { e.target.classList.remove('dragging'); dragged = null; }
    });

    board.querySelectorAll('.vend-kanban-items').forEach(zone => {
        zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
        zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
        zone.addEventListener('drop', e => {
            e.preventDefault();
            zone.classList.remove('drag-over');
            if (!dragged) return;
            const card = dragged, origem = card.parentElement, prox = card.nextElementSibling;
            const estagio = zone.closest('.vend-kanban-column').dataset.estagio;

            let motivo = '';
            if (estagio === 'perdido') {
                motivo = prompt('Motivo da perda:') || '';
                if (motivo === '') return; // cancelou
            }
            zone.appendChild(card);

            fetch('<?php echo SITE_URL; ?>/api/crm_move.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + card.dataset.id + '&estagio=' + encodeURIComponent(estagio) + '&motivo=' + encodeURIComponent(motivo)
            }).then(r => r.json()).then(d => {
                if (d.success) { showToast('Oportunidade movida', 'success'); location.reload(); }
                else {
                    if (prox) origem.insertBefore(card, prox); else origem.appendChild(card);
                    showToast(d.message || 'Não foi possível mover', 'danger');
                }
            }).catch(() => {
                if (prox) origem.insertBefore(card, prox); else origem.appendChild(card);
                showToast('Erro de comunicação', 'danger');
            });
        });
    });
})();
</script>

<?php include '../../includes/footer_vendedor.php'; ?>
