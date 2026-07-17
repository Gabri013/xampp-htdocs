<?php
/**
 * Painel do Projetista — setor único (projetista = engenharia, unificado).
 *
 * Duas áreas na mesma tela:
 *  1. Apontamento: O.S. em produção na etapa do projetista (chave interna
 *     "engenharia") — iniciar/finalizar cronômetro, enviar ao próximo setor,
 *     retornar etapa.
 *  2. Fila de projeto: O.S. pré-produção aguardando desenho/aprovação, com
 *     encaminhamento para os setores.
 */
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/engenharia.php';
require_once '../../includes/workflow.php';
require_once '../../includes/expediente.php';

requirePermission(['master', 'projetista', 'gerente', 'producao', 'engenharia']);

$usuario = getCurrentUser();
$tipo_usuario = $usuario['tipo'];

$GLOBALS['modulo_tipo'] = 'projetista';
$page_title = 'Painel do Projetista';

$db = getDB();
ensureOrdensServicoIndependentesSchema($db);
ensureEngenhariaSchema($db);
ensureExpedienteSchema($db);

$etapa_projetista = 'engenharia'; // chave interna no banco (setor exibido: Projetista)

// ---------- 1) O.S. em produção na etapa do projetista (apontamento) ----------
$stmt = $db->prepare("
    SELECT os.*, c.razao_social, COALESCE(v.numero, 'Independente') as venda_numero,
           ep.status as etapa_status, ep.data_inicio as etapa_inicio, ep.usuario_id as etapa_usuario_id
    FROM ordens_servico os
    INNER JOIN clientes c ON os.cliente_id = c.id
    LEFT JOIN vendas v ON os.venda_id = v.id
    LEFT JOIN os_etapas_producao ep ON os.id = ep.os_id AND os.etapa_atual = ep.etapa
    WHERE os.status = 'em_producao' AND os.etapa_atual = ?
    ORDER BY CASE os.prioridade WHEN 'vermelho' THEN 1 WHEN 'amarelo' THEN 2 WHEN 'verde' THEN 3 END,
             os.data_inicio ASC
");
$stmt->execute([$etapa_projetista]);
$ordens_apontamento = $stmt->fetchAll();

// Próximas etapas planejadas por O.S. (modal Finalizar e Enviar)
$fluxo = getValidOSEtapas();
$proximasPorOS = [];
if (!empty($ordens_apontamento)) {
    $ids = array_map('intval', array_column($ordens_apontamento, 'id'));
    $planejadas = $db->query("SELECT os_id, etapa FROM os_etapas_producao WHERE os_id IN (" . implode(',', $ids) . ")")->fetchAll(PDO::FETCH_ASSOC);
    $mapaPlan = [];
    foreach ($planejadas as $p) {
        $mapaPlan[(int) $p['os_id']][] = $p['etapa'];
    }
    foreach ($ordens_apontamento as $o) {
        $osId = (int) $o['id'];
        $posAtual = array_search($o['etapa_atual'], $fluxo, true);
        $opts = [];
        foreach ($fluxo as $i => $etapa) {
            if ($i <= $posAtual || $etapa === 'autorizacao') continue;
            $ehPlanejada = isset($mapaPlan[$osId]) && in_array($etapa, $mapaPlan[$osId], true);
            if ($ehPlanejada || $etapa === 'concluida') {
                $opts[] = $etapa;
            }
        }
        if (empty($opts) && $posAtual !== false && isset($fluxo[$posAtual + 1])) {
            $opts[] = $fluxo[$posAtual + 1];
        }
        $proximasPorOS[$osId] = $opts;
    }
}

$podeApontar = validateUserCanOperateEtapa($etapa_projetista, $tipo_usuario)['valid'] ?? false;

// ---------- 2) Fila de projeto (pré-produção) ----------
$setores_permitidos = [];
$setor_permissoes = [
    'corte' => ['master', 'gerente', 'producao', 'corte', 'projetista', 'engenharia'],
    'dobra' => ['master', 'gerente', 'producao', 'dobra', 'projetista', 'engenharia'],
    'solda' => ['master', 'gerente', 'producao', 'solda', 'projetista', 'engenharia'],
    'refrigeracao' => ['master', 'gerente', 'producao', 'refrigeracao', 'projetista', 'engenharia'],
    'acabamento' => ['master', 'gerente', 'producao', 'acabamento', 'projetista', 'engenharia'],
    'montagem' => ['master', 'gerente', 'producao', 'montagem', 'projetista', 'engenharia'],
];
foreach ($setor_permissoes as $setor => $tipos) {
    if (in_array($tipo_usuario, $tipos)) {
        $setores_permitidos[] = $setor;
    }
}

$stmt = $db->query("
    SELECT os.*, c.razao_social,
           COALESCE(v.numero, 'Independente') as venda_numero,
           COALESCE(u.nome, '-') as vendedor_nome,
           COALESCE(v.observacoes, '') as observacoes
    FROM ordens_servico os
    INNER JOIN clientes c ON os.cliente_id = c.id
    LEFT JOIN vendas v ON os.venda_id = v.id
    LEFT JOIN usuarios u ON v.usuario_id = u.id
    WHERE os.status IN ('pendente', 'em_projeto', 'em_revisao', 'proposta')
    ORDER BY os.data_inicio ASC
");
$ordens = $stmt->fetchAll();

include '../../includes/header_vendedor.php';
?>

<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-page-head">
            <div><h1 class="vend-page-title"><i class="fas fa-drafting-compass"></i> Painel do Projetista</h1></div>
            <div>Em apontamento: <?= count($ordens_apontamento) ?> | Aguardando projeto: <?= count($ordens) ?></div>
        </div>

        <!-- ===== 1. Apontamento: O.S. na etapa do Projetista ===== -->
        <div class="vend-card" style="margin-bottom:20px">
            <div class="vend-card-head">
                <span class="vend-card-title"><i class="fas fa-stopwatch"></i> Produção no Projetista (apontamento)</span>
            </div>
            <div style="padding:0 16px 16px">
                <?php if (empty($ordens_apontamento)): ?>
                    <div class="vend-empty" style="padding:20px">Nenhuma O.S. em produção na etapa do Projetista.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="vend-table">
                        <thead><tr><th>O.S</th><th>Cliente</th><th>Entrega</th><th>Prioridade</th><th>Status Etapa</th><th>Ações</th></tr></thead>
                        <tbody>
                        <?php foreach ($ordens_apontamento as $os): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($os['numero']) ?></strong> <?= renderBolinhasOS(getBolinhasOS($db, $os), 10) ?></td>
                                <td><?= htmlspecialchars($os['razao_social']) ?></td>
                                <td><?= formatDate($os['data_termino'] ?? null) ?></td>
                                <td><?= getPrioridadeBadge($os['prioridade']) ?></td>
                                <td><?php if ($os['etapa_status'] === 'em_andamento'): ?><span class="vbadge-warning"><i class="fas fa-clock"></i> Em Andamento</span><?php else: ?><span class="vbadge-secondary">Aguardando Início</span><?php endif; ?></td>
                                <td>
                                    <?php if ($podeApontar && $os['etapa_status'] !== 'em_andamento'): ?>
                                        <button class="vbtn-sm btn-success" onclick="gerenciarEtapa(<?= (int) $os['id'] ?>, '<?= $etapa_projetista ?>', 'iniciar')"><i class="fas fa-play"></i> Iniciar Trabalho</button>
                                    <?php elseif ($podeApontar && $os['etapa_status'] === 'em_andamento'): ?>
                                        <button class="vbtn-sm btn-danger" onclick='abrirModalEnviar(<?= (int) $os['id'] ?>, "<?= $etapa_projetista ?>", <?= htmlspecialchars(json_encode($proximasPorOS[(int) $os['id']] ?? []), ENT_QUOTES) ?>, "<?= htmlspecialchars($os['numero']) ?>")'><i class="fas fa-stop"></i> Finalizar e Enviar</button>
                                    <?php endif; ?>
                                    <button class="vbtn-sm btn-warning" onclick='abrirModalRetorno(<?= htmlspecialchars(json_encode(['id' => (int) $os['id'], 'numero' => $os['numero'], 'etapa_atual' => $os['etapa_atual']]), ENT_QUOTES) ?>)' title="Retornar etapa"><i class="fas fa-undo"></i></button>
                                    <a href="<?= SITE_URL ?>/modules/os/os_detalhes.php?os_id=<?= (int) $os['id'] ?>" class="vbtn-sm btn-primary" title="Detalhes, anexos e envio parcial de itens"><i class="fas fa-eye"></i> Detalhes</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="font-size:12px;color:#666;margin-top:8px"><i class="fas fa-info-circle"></i> Para enviar <strong>itens individualmente</strong> (envio parcial) conforme conclui cada desenho, abra os <strong>Detalhes</strong> da O.S. e use a coluna Setor.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== 2. Fila de projeto (pré-produção) ===== -->
        <div class="vend-card">
            <div class="vend-card-head">
                <span class="vend-card-title"><i class="fas fa-pencil-ruler"></i> Aguardando Projeto / Aprovação</span>
            </div>
            <div style="padding:0 16px 16px">
                <?php if (!empty($ordens)): ?>
                <div class="table-responsive">
                    <table class="vend-table">
                        <thead>
                            <tr><th>O.S.</th><th>Cliente</th><th>Venda</th><th>Status</th><th>Prazo</th><th>Ação</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ordens as $os): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($os['numero']) ?></strong> <?= renderBolinhasOS(getBolinhasOS($db, $os), 10) ?></td>
                                    <td><?= htmlspecialchars($os['razao_social']) ?></td>
                                    <td><?= htmlspecialchars($os['venda_numero']) ?></td>
                                    <td>
                                        <span class="vbadge vbadge-<?= $os['status']=='proposta'?'warn':($os['status']=='em_revisao'?'info':'prod') ?>">
                                            <?= $os['status']=='proposta'?'Proposta':ucfirst(str_replace('_', ' ', $os['status'])) ?>
                                        </span>
                                    </td>
                                    <td><?= !empty($os['data_termino']) ? date('d/m/Y', strtotime($os['data_termino'])) : '—' ?></td>
                                    <td>
                                        <a href="<?= SITE_URL ?>/modules/os/os_detalhes.php?os_id=<?= $os['id'] ?>" class="vbtn-sm" title="Detalhes"><i class="fas fa-eye"></i></a>
                                        <?php if (!empty($setores_permitidos)): ?>
                                        <button class="vbtn-sm btn-success" onclick="abrirModalSetor(<?= $os['id'] ?>, <?= htmlspecialchars(json_encode($setores_permitidos)) ?>)" title="Encaminhar para setor"><i class="fas fa-share"></i></button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="vend-empty" style="padding:20px">Nenhuma O.S. pendente de projeto.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Encaminhar (fila de projeto) -->
<div id="modalSetor" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3>Encaminhar O.S.</h3>
            <button class="close" onclick="fecharModalSetor()">&times;</button>
        </div>
        <div class="modal-body">
            <p style="margin-bottom: 12px;">Selecione o setor de destino:</p>
            <div id="opcoes-setor" style="display: grid; gap: 8px;"></div>
        </div>
    </div>
</div>

<!-- Modal Retornar Etapa -->
<div id="modalRetorno" class="modal"><div class="modal-content" style="max-width:500px;"><div class="modal-header"><h3>Retornar Etapa</h3><button class="close" onclick="fecharModalRetorno()">&times;</button></div><form id="formRetorno"><div class="modal-body"><input type="hidden" name="acao" value="retornar_etapa"><input type="hidden" name="os_id" id="os_id_retorno"><input type="hidden" name="etapa_atual" id="etapa_atual_retorno"><div class="form-group"><label><strong>O.S:</strong> <span id="os_numero_retorno"></span></label></div><div class="form-group"><label><strong>Retornar para qual etapa? *</strong></label><div id="etapas_retorno_container" style="background:#f8f9fa;padding:10px;border-radius:5px;border:1px solid #ddd;"></div></div><div class="form-group"><label for="justificativa"><strong>Justificativa do Retorno *</strong></label><textarea id="justificativa" name="justificativa" class="form-control" rows="4" placeholder="Explique o motivo do retorno..." required></textarea></div></div><div class="modal-footer"><button type="button" class="vbtn-sm" onclick="fecharModalRetorno()">Cancelar</button><button type="submit" class="vbtn-sm"><i class="fas fa-undo"></i> Confirmar Retorno</button></div></form></div></div>

<!-- Modal Finalizar e Enviar -->
<div id="modalEnviar" class="modal"><div class="modal-content" style="max-width:460px"><div class="modal-header"><h3><i class="fas fa-paper-plane"></i> Finalizar e Enviar</h3><button class="close" onclick="document.getElementById('modalEnviar').style.display='none'">&times;</button></div><div class="modal-body"><p style="margin-bottom:10px">Finalizar o trabalho do <strong>Projetista</strong> na <strong id="enviar_os_num"></strong> e enviar para:</p><div id="enviar_opcoes" style="display:flex;flex-direction:column;gap:6px"></div></div><div class="modal-footer"><button type="button" class="vbtn-sm" onclick="document.getElementById('modalEnviar').style.display='none'">Cancelar</button><button type="button" class="vbtn-sm btn-success" id="btnConfirmarEnviar"><i class="fas fa-check"></i> Confirmar e Enviar</button></div></div></div>

<script>
const fluxo_etapas = <?= json_encode(getValidOSEtapas()) ?>;
const etapasDestinoDisponiveis = <?= json_encode(array_values(array_diff(getValidOSEtapas(), ['autorizacao']))) ?>;
const etapaLabels = <?= json_encode(getEtapasLabels(), JSON_UNESCAPED_UNICODE) ?>;
const rotuloEtapa = e => e === 'concluida' ? 'Concluir O.S. (última etapa)' : (etapaLabels[e] || e.charAt(0).toUpperCase() + e.slice(1));

// ---- Fila de projeto: encaminhar O.S. ----
function abrirModalSetor(osId, setores) {
    const container = document.getElementById('opcoes-setor');
    container.innerHTML = '';
    setores.forEach(setor => {
        const btn = document.createElement('button');
        btn.className = 'vbtn-sm btn-primary';
        btn.style = 'width: 100%; justify-content: center;';
        btn.innerHTML = '<i class="fas fa-arrow-right"></i> ' + rotuloEtapa(setor);
        btn.onclick = () => confirmarEncaminhar(osId, setor);
        container.appendChild(btn);
    });
    document.getElementById('modalSetor').classList.add('show');
}
function fecharModalSetor() { document.getElementById('modalSetor').classList.remove('show'); }
function confirmarEncaminhar(osId, setor) {
    if (confirm('Encaminhar O.S. para ' + rotuloEtapa(setor) + '?')) {
        fetch('<?= SITE_URL ?>/modules/os/desmembrar_os.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'os_id=' + osId + '&setor=' + setor
        }).then(r => r.json()).then(d => {
            if (d.success) location.reload(); else alert(d.error || 'Erro ao encaminhar.');
        }).catch(() => alert('Erro de comunicação ao encaminhar a O.S.'));
    }
}

// ---- Apontamento ----
async function gerenciarEtapa(os_id, etapa, acao, etapaDestino) {
    const formData = new FormData();
    formData.append('os_id', os_id);
    formData.append('etapa', etapa);
    formData.append('acao', acao === 'iniciar' ? 'iniciar_etapa' : 'finalizar_etapa');
    if (acao === 'finalizar') formData.append('etapa_destino', etapaDestino || '');
    const res = await fetch('<?= SITE_URL ?>/api/producao.php', {method:'POST', body:formData});
    const data = await res.json();
    if (data.success) location.reload(); else alert('Erro: ' + data.error);
}

function abrirModalEnviar(os_id, etapa, proximas, osNum) {
    document.getElementById('enviar_os_num').textContent = osNum;
    const cont = document.getElementById('enviar_opcoes');
    cont.innerHTML = '';
    const lista = (proximas && proximas.length) ? proximas : etapasDestinoDisponiveis.filter(e => e !== etapa);
    lista.forEach((et, i) => {
        const div = document.createElement('div');
        div.className = 'etapa-checkbox-item';
        div.style.cssText = 'background:#f8f9fa;padding:8px 10px;border-radius:6px;border:1px solid #e9ecef;display:flex;gap:8px;align-items:center';
        div.innerHTML = `<input type="radio" name="enviar_destino" value="${et}" id="env_${et}" ${i===0?'checked':''}><label for="env_${et}" style="margin:0;cursor:pointer;flex:1">${rotuloEtapa(et)}</label>`;
        cont.appendChild(div);
    });
    document.getElementById('btnConfirmarEnviar').onclick = () => {
        const sel = document.querySelector('input[name="enviar_destino"]:checked');
        if (!sel) { alert('Selecione o setor de destino.'); return; }
        gerenciarEtapa(os_id, etapa, 'finalizar', sel.value);
    };
    document.getElementById('modalEnviar').style.display = 'block';
}

function abrirModalRetorno(os) {
    document.getElementById('os_id_retorno').value = os.id;
    document.getElementById('etapa_atual_retorno').value = os.etapa_atual;
    document.getElementById('os_numero_retorno').textContent = os.numero;
    const container = document.getElementById('etapas_retorno_container');
    container.innerHTML = '';
    // No painel do Projetista, "retornar" = devolver para a fase comercial
    // (a O.S. sai da produção e cai em "Aguardando Projeto" / Em Revisão)
    const opcoesRetorno = [{valor:'projetista', label:'Devolver para revisão do Vendedor (sai da produção)'}];
    const pos_atual = fluxo_etapas.indexOf(os.etapa_atual);
    if (pos_atual > 0) {
        for (let i = 1; i < pos_atual; i++) {
            const etapa = fluxo_etapas[i];
            opcoesRetorno.push({valor: etapa, label: rotuloEtapa(etapa)});
        }
    }
    opcoesRetorno.forEach((opcao, index) => {
        const div = document.createElement('div');
        div.className = 'etapa-checkbox-item';
        div.innerHTML = `<input type="radio" name="etapa_destino" value="${opcao.valor}" id="etapa_${opcao.valor}" ${index===0?'checked':''} required><label for="etapa_${opcao.valor}" style="margin:0;cursor:pointer;flex:1;">${opcao.label}</label>`;
        container.appendChild(div);
    });
    document.getElementById('modalRetorno').style.display = 'block';
}
function fecharModalRetorno() { document.getElementById('modalRetorno').style.display = 'none'; }
document.getElementById('formRetorno').onsubmit = async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const res = await fetch('<?= SITE_URL ?>/api/producao.php', {method:'POST', body:formData});
    const data = await res.json();
    if (data.success) location.reload(); else alert('Erro: ' + data.error);
};

window.onclick = function(event) {
    if (event.target.id === 'modalSetor') fecharModalSetor();
    else if (event.target.className === 'modal') event.target.style.display = 'none';
};
</script>

<?php include '../../includes/footer_vendedor.php'; ?>
