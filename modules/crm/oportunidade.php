<?php
require_once '../../config/config.php';
require_once '../../includes/crm.php';
requirePermission(['master', 'vendedor', 'gerente']);

$db = getDB();
ensureCrmSchema($db);
$usuario = getCurrentUser();
$estagios = getCrmEstagios();
$tiposAtv = getCrmTiposAtividade();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: index.php'); exit; }

// ── Ações ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'nova_atividade') {
        $tipo = array_key_exists($_POST['tipo'] ?? '', $tiposAtv) ? $_POST['tipo'] : 'nota';
        $titulo = sanitize($_POST['titulo'] ?? '');
        $descricao = sanitize($_POST['descricao'] ?? '');
        $data_prevista = $_POST['data_prevista'] ?: null;
        if ($titulo !== '') {
            $stmt = $db->prepare("SELECT cliente_id, contato_id FROM crm_oportunidades WHERE id = ?");
            $stmt->execute([$id]);
            $vinc = $stmt->fetch() ?: ['cliente_id' => null, 'contato_id' => null];
            $stmt = $db->prepare("INSERT INTO crm_atividades (oportunidade_id, cliente_id, contato_id, tipo, titulo, descricao, data_prevista, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id, $vinc['cliente_id'], $vinc['contato_id'], $tipo, $titulo, $descricao ?: null, $data_prevista, $usuario['id']]);
            setSuccess('Atividade registrada.');
        }
        header('Location: oportunidade.php?id=' . $id);
        exit;
    }

    if ($acao === 'concluir_tarefa') {
        $atvId = (int)($_POST['atividade_id'] ?? 0);
        $db->prepare("UPDATE crm_atividades SET concluida = 1 WHERE id = ? AND oportunidade_id = ?")->execute([$atvId, $id]);
        header('Location: oportunidade.php?id=' . $id);
        exit;
    }

    if ($acao === 'editar_oportunidade') {
        $titulo = sanitize($_POST['titulo'] ?? '');
        $cliente_id = (int)($_POST['cliente_id'] ?? 0) ?: null;
        $contato_id = (int)($_POST['contato_id'] ?? 0) ?: null;
        $valor = (float)($_POST['valor_estimado'] ?? 0);
        $previsao = $_POST['previsao_fechamento'] ?: null;
        $obs = sanitize($_POST['observacoes'] ?? '');
        if ($titulo !== '') {
            $stmt = $db->prepare("UPDATE crm_oportunidades SET titulo=?, cliente_id=?, contato_id=?, valor_estimado=?, previsao_fechamento=?, observacoes=? WHERE id=?");
            $stmt->execute([$titulo, $cliente_id, $contato_id, $valor, $previsao, $obs ?: null, $id]);
            setSuccess('Oportunidade atualizada.');
        }
        header('Location: oportunidade.php?id=' . $id);
        exit;
    }
}

// ── Carregar dados ───────────────────────────────────────────────────
$stmt = $db->prepare("
    SELECT o.*, c.razao_social, ct.nome AS contato_nome, ct.telefone AS contato_tel, ct.email AS contato_email,
           u.nome AS responsavel_nome, orc.numero AS orcamento_numero, v.numero AS venda_numero
    FROM crm_oportunidades o
    LEFT JOIN clientes c ON c.id = o.cliente_id
    LEFT JOIN crm_contatos ct ON ct.id = o.contato_id
    LEFT JOIN usuarios u ON u.id = o.responsavel_id
    LEFT JOIN orcamentos orc ON orc.id = o.orcamento_id
    LEFT JOIN vendas v ON v.id = o.venda_id
    WHERE o.id = ?
");
$stmt->execute([$id]);
$op = $stmt->fetch();
if (!$op) { setError('Oportunidade não encontrada.'); header('Location: index.php'); exit; }

$stmt = $db->prepare("
    SELECT a.*, u.nome AS usuario_nome
    FROM crm_atividades a
    LEFT JOIN usuarios u ON u.id = a.usuario_id
    WHERE a.oportunidade_id = ?
    ORDER BY a.concluida ASC, COALESCE(a.data_prevista, a.created_at) DESC, a.id DESC
");
$stmt->execute([$id]);
$atividades = $stmt->fetchAll();

$clientes = $db->query("SELECT id, razao_social FROM clientes ORDER BY razao_social")->fetchAll();
$contatos = $db->query("SELECT id, nome FROM crm_contatos ORDER BY nome")->fetchAll();

$est = $estagios[$op['estagio']] ?? $estagios['lead'];
$page_title = 'CRM — ' . $op['titulo'];
include '../../includes/header_vendedor.php';
?>

<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-page-head">
            <div>
                <h1 class="vend-page-title">#<?php echo (int)$op['id']; ?> — <?php echo htmlspecialchars($op['titulo']); ?></h1>
                <p class="vend-page-sub">
                    <span class="vbadge" style="background:<?php echo $est['cor']; ?>;color:#fff"><?php echo $est['label']; ?></span>
                    <?php if (!empty($op['razao_social'])): ?> &nbsp;<i class="far fa-building"></i> <?php echo htmlspecialchars($op['razao_social']); ?><?php endif; ?>
                    <?php if ((float)$op['valor_estimado'] > 0): ?> &nbsp;<strong style="color:#16a34a"><?php echo formatMoney($op['valor_estimado']); ?></strong><?php endif; ?>
                </p>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <?php if ($op['estagio'] === 'ganho' && empty($op['orcamento_id']) && !empty($op['cliente_id'])): ?>
                    <a href="<?php echo SITE_URL; ?>/modules/orcamentos/criar_orcamento.php" class="vbtn-sm btn-success" title="Gerar orçamento para este cliente"><i class="fas fa-file-invoice"></i> Gerar Orçamento</a>
                <?php endif; ?>
                <?php if (!empty($op['orcamento_numero'])): ?>
                    <span class="vbadge vbadge-info">Orçamento <?php echo htmlspecialchars($op['orcamento_numero']); ?></span>
                <?php endif; ?>
                <a href="index.php" class="vbtn-sm"><i class="fas fa-columns"></i> Pipeline</a>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1.4fr;gap:16px;align-items:start" class="crm-grid">
            <!-- Dados da oportunidade -->
            <div class="vend-card">
                <div class="vend-card-head"><div class="vend-card-title"><i class="fas fa-bullseye"></i> Dados</div></div>
                <form method="POST" style="padding:16px">
                    <input type="hidden" name="acao" value="editar_oportunidade">
                    <div class="form-group" style="margin-bottom:10px">
                        <label class="form-label">Título</label>
                        <input type="text" name="titulo" class="form-control" value="<?php echo htmlspecialchars($op['titulo']); ?>" required>
                    </div>
                    <div class="form-group" style="margin-bottom:10px">
                        <label class="form-label">Empresa</label>
                        <select name="cliente_id" class="form-control">
                            <option value="">—</option>
                            <?php foreach ($clientes as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo $c['id'] == $op['cliente_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['razao_social']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:10px">
                        <label class="form-label">Contato</label>
                        <select name="contato_id" class="form-control">
                            <option value="">—</option>
                            <?php foreach ($contatos as $ct): ?>
                                <option value="<?php echo $ct['id']; ?>" <?php echo $ct['id'] == $op['contato_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($ct['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                        <div class="form-group">
                            <label class="form-label">Valor (R$)</label>
                            <input type="number" step="0.01" name="valor_estimado" class="form-control" value="<?php echo (float)$op['valor_estimado']; ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Previsão</label>
                            <input type="date" name="previsao_fechamento" class="form-control" value="<?php echo htmlspecialchars($op['previsao_fechamento'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-group" style="margin:10px 0">
                        <label class="form-label">Observações</label>
                        <textarea name="observacoes" class="form-control" rows="3"><?php echo htmlspecialchars($op['observacoes'] ?? ''); ?></textarea>
                    </div>
                    <?php if (!empty($op['motivo_perda'])): ?>
                        <div class="vend-alert warning" style="margin-bottom:10px"><i class="fas fa-times-circle"></i> <div>Motivo da perda: <?php echo htmlspecialchars($op['motivo_perda']); ?></div></div>
                    <?php endif; ?>
                    <div style="text-align:right"><button type="submit" class="vbtn-sm vbtn-brand"><i class="fas fa-save"></i> Salvar</button></div>
                </form>
            </div>

            <!-- Timeline de atividades -->
            <div class="vend-card">
                <div class="vend-card-head"><div class="vend-card-title"><i class="fas fa-stream"></i> Atividades e Timeline</div></div>
                <div style="padding:16px">
                    <form method="POST" style="border:1px solid #e9ecef;border-radius:10px;padding:12px;margin-bottom:16px;background:#fafafa">
                        <input type="hidden" name="acao" value="nova_atividade">
                        <div style="display:grid;grid-template-columns:130px 1fr;gap:8px;margin-bottom:8px">
                            <select name="tipo" class="form-control">
                                <?php foreach ($tiposAtv as $tk => $t): ?>
                                    <option value="<?php echo $tk; ?>"><?php echo $t['label']; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="titulo" class="form-control" placeholder="Ex.: Ligar para confirmar medidas" required>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 200px auto;gap:8px;align-items:start">
                            <textarea name="descricao" class="form-control" rows="1" placeholder="Detalhes (opcional)"></textarea>
                            <input type="datetime-local" name="data_prevista" class="form-control" title="Quando (para tarefas/reuniões)">
                            <button type="submit" class="vbtn-sm vbtn-brand"><i class="fas fa-plus"></i></button>
                        </div>
                    </form>

                    <?php if (empty($atividades)): ?>
                        <p class="text-muted" style="text-align:center;padding:20px">Nenhuma atividade ainda. Registre a primeira interação acima.</p>
                    <?php else: foreach ($atividades as $a):
                        $t = $tiposAtv[$a['tipo']] ?? $tiposAtv['nota'];
                        $atrasada = $a['tipo'] === 'tarefa' && !$a['concluida'] && $a['data_prevista'] && $a['data_prevista'] < date('Y-m-d H:i:s');
                    ?>
                    <div style="display:flex;gap:10px;padding:10px 4px;border-bottom:1px solid #f1f3f5;<?php echo $a['concluida'] ? 'opacity:.55' : ''; ?>">
                        <div style="width:32px;height:32px;border-radius:50%;background:<?php echo $atrasada ? '#fee2e2' : '#FEF0EA'; ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="fas <?php echo $t['icon']; ?>" style="color:<?php echo $atrasada ? '#dc2626' : '#D85A30'; ?>;font-size:13px"></i>
                        </div>
                        <div style="flex:1;min-width:0">
                            <div style="font-weight:600;font-size:13px;<?php echo $a['concluida'] ? 'text-decoration:line-through' : ''; ?>"><?php echo htmlspecialchars($a['titulo']); ?></div>
                            <?php if (!empty($a['descricao'])): ?><div style="font-size:12px;color:#666"><?php echo nl2br(htmlspecialchars($a['descricao'])); ?></div><?php endif; ?>
                            <div style="font-size:11px;color:#999;margin-top:2px">
                                <?php echo $t['label']; ?> • <?php echo htmlspecialchars($a['usuario_nome'] ?? '-'); ?> • <?php echo formatDateTime($a['created_at']); ?>
                                <?php if ($a['data_prevista']): ?> • <span style="<?php echo $atrasada ? 'color:#dc2626;font-weight:700' : ''; ?>">prazo <?php echo formatDateTime($a['data_prevista']); ?></span><?php endif; ?>
                            </div>
                        </div>
                        <?php if ($a['tipo'] === 'tarefa' && !$a['concluida']): ?>
                        <form method="POST" style="align-self:center">
                            <input type="hidden" name="acao" value="concluir_tarefa">
                            <input type="hidden" name="atividade_id" value="<?php echo (int)$a['id']; ?>">
                            <button type="submit" class="vbtn-sm btn-success" title="Concluir tarefa"><i class="fas fa-check"></i></button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>@media (max-width: 900px){ .crm-grid{grid-template-columns:1fr !important} }</style>

<?php include '../../includes/footer_vendedor.php'; ?>
