<?php
require_once '../../config/config.php';
require_once '../../includes/workflow.php';
require_once '../../includes/expediente.php';

$setor_atual = 'mobiliario';
$setor_label = 'Mobiliário';

requirePermission(['master', 'gerente', 'projetista', 'producao', $setor_atual]);

$page_title = "Painel do Setor: $setor_label";

$db = getDB();
ensureOrdensServicoIndependentesSchema($db);
ensureExpedienteSchema($db);

$stmt = $db->prepare("
    SELECT os.*, c.razao_social, v.numero as venda_numero,
           ep.status as etapa_status, ep.data_inicio as etapa_inicio, ep.usuario_id as etapa_usuario_id
    FROM ordens_servico os
    INNER JOIN clientes c ON os.cliente_id = c.id
    LEFT JOIN vendas v ON os.venda_id = v.id
    LEFT JOIN os_etapas_producao ep ON os.id = ep.os_id AND os.etapa_atual = ep.etapa
    WHERE os.status = 'em_producao' AND os.etapa_atual = ?
    ORDER BY 
        CASE os.prioridade 
            WHEN 'vermelho' THEN 1 
            WHEN 'amarelo' THEN 2 
            WHEN 'verde' THEN 3 
        END,
        os.data_inicio ASC
");
$stmt->execute([$setor_atual]);
$ordens = $stmt->fetchAll();

// Para cada O.S., quais são as PRÓXIMAS etapas planejadas (posteriores à atual,
// presentes em os_etapas_producao) — usado no modal "Finalizar e Enviar".
$fluxo = getValidOSEtapas();
$proximasPorOS = [];
if (!empty($ordens)) {
    $ids = array_map('intval', array_column($ordens, 'id'));
    $in = implode(',', $ids);
    $planejadas = $db->query("SELECT os_id, etapa FROM os_etapas_producao WHERE os_id IN ($in)")->fetchAll(PDO::FETCH_ASSOC);
    $mapaPlan = [];
    foreach ($planejadas as $p) {
        $mapaPlan[(int) $p['os_id']][] = $p['etapa'];
    }
    foreach ($ordens as $o) {
        $osId = (int) $o['id'];
        $posAtual = array_search($o['etapa_atual'], $fluxo, true);
        $opts = [];
        foreach ($fluxo as $i => $etapa) {
            if ($i <= $posAtual || $etapa === 'autorizacao') continue;
            // só etapas planejadas da O.S. (ou 'concluida' para encerrar)
            $ehPlanejada = isset($mapaPlan[$osId]) && in_array($etapa, $mapaPlan[$osId], true);
            if ($ehPlanejada || $etapa === 'concluida') {
                $opts[] = $etapa;
            }
        }
        // fallback: se não houver planejamento, oferece a próxima do fluxo + concluída
        if (empty($opts) && $posAtual !== false && isset($fluxo[$posAtual + 1])) {
            $opts[] = $fluxo[$posAtual + 1];
        }
        $proximasPorOS[$osId] = $opts;
    }
}

include '../../includes/header_vendedor.php';
?>
<div class="vend-layout">
    <?php $GLOBALS['modulo_tipo'] = 'producao'; include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main"><div class="vend-page-head"><h1 class="vend-page-title"><?php echo htmlspecialchars($setor_label); ?></h1></div><div class="vend-content">
    <div class="card">
        <div class="card-header"><h3>Painel de Produção - Setor: <?php echo htmlspecialchars($setor_label); ?></h3></div>
        <div class="card-body">
            <div class="alert alert-info"><strong>Instruções:</strong> Inicie o cronômetro ao começar o trabalho nesta etapa e finalize ao terminar para enviar para o próximo setor.</div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>O.S</th><th>Cliente</th><th>Entrega</th><th>Prioridade</th><th>Status Etapa</th><th>Tempo Decorrido</th><th>Ações</th></tr></thead>
                    <tbody>
                        <?php if (empty($ordens)): ?><tr><td colspan="7" class="text-center">Nenhuma ordem de serviço pendente para este setor</td></tr><?php else: ?>
                            <?php foreach ($ordens as $os): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($os['numero']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($os['razao_social']); ?></td>
                                    <td><?php echo formatDate($os['data_termino'] ?? null); ?></td>
                                    <td><?php echo getPrioridadeBadge($os['prioridade']); ?></td>
                                    <td><?php if ($os['etapa_status'] === 'em_andamento'): ?><span class="vbadge-warning"><i class="fas fa-clock"></i> Em Andamento</span><?php else: ?><span class="vbadge-secondary">Aguardando Início</span><?php endif; ?></td>
                                    <td class="timer" data-segundos="0" data-ativo="0"><?php echo ($os['etapa_status'] ?? '') === 'em_andamento' ? '--:--:--' : '--:--:--'; ?></td>
                                    <td>
                                        <?php if (validateUserCanOperateEtapa($setor_atual, $_SESSION['usuario_tipo'] ?? '')['valid'] && $os['etapa_status'] !== 'em_andamento'): ?>
                                            <button class="vbtn-sm btn-success" onclick="gerenciarEtapa(<?php echo $os['id']; ?>, '<?php echo $setor_atual; ?>', 'iniciar')"><i class="fas fa-play"></i> Iniciar Trabalho</button>
                                        <?php elseif (validateUserCanOperateEtapa($setor_atual, $_SESSION['usuario_tipo'] ?? '')['valid'] && $os['etapa_status'] === 'em_andamento'): ?>
                                            <button class="vbtn-sm btn-danger" onclick='abrirModalEnviar(<?php echo (int) $os["id"]; ?>, "<?php echo $setor_atual; ?>", <?php echo htmlspecialchars(json_encode($proximasPorOS[(int) $os["id"]] ?? []), ENT_QUOTES); ?>, "<?php echo htmlspecialchars($os["numero"]); ?>")'><i class="fas fa-stop"></i> Finalizar e Enviar</button>
                                        <?php endif; ?>
                                        <button class="vbtn-sm btn-warning" onclick='abrirModalRetorno(<?php echo json_encode($os); ?>)' title="Retornar etapa"><i class="fas fa-undo"></i> Retornar</button>
                                        <button class="vbtn-sm btn-info" onclick="abrirModal(<?php echo htmlspecialchars(json_encode($os)); ?>)" title="Ver resumo"><i class="fas fa-info-circle"></i></button>
                                        <a href="os_detalhes.php?os_id=<?php echo $os['id']; ?>" class="vbtn-sm btn-primary" title="Ver detalhes completos"><i class="fas fa-eye"></i> Detalhes</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    </div></div>
</div>

<div id="modalRetorno" class="modal"><div class="modal-content" style="max-width:500px;"><div class="modal-header"><h3>Retornar Etapa</h3><button class="close" onclick="fecharModalRetorno()">&times;</button></div><form id="formRetorno"><div class="modal-body"><input type="hidden" name="acao" value="retornar_etapa"><input type="hidden" name="os_id" id="os_id_retorno"><input type="hidden" name="etapa_atual" id="etapa_atual_retorno"><div class="form-group"><label><strong>O.S:</strong> <span id="os_numero_retorno"></span></label></div><div class="form-group"><label><strong>Etapa Atual:</strong> <span id="etapa_nome_retorno"></span></label></div><div class="form-group"><label><strong>Retornar para qual etapa? *</strong></label><div id="etapas_retorno_container" style="background:#f8f9fa;padding:10px;border-radius:5px;border:1px solid #ddd;"></div></div><div class="form-group"><label for="justificativa"><strong>Justificativa do Retorno *</strong></label><textarea id="justificativa" name="justificativa" class="form-control" rows="4" placeholder="Explique o motivo do retorno..." required></textarea></div></div><div class="modal-footer"><button type="button" class="vbtn-sm" onclick="fecharModalRetorno()">Cancelar</button><button type="submit" class="vbtn-sm"><i class="fas fa-undo"></i> Confirmar Retorno</button></div></form></div></div>

<div id="modalOS" class="modal"><div class="modal-content"><div class="modal-header"><h3 id="modalTitulo">Detalhes da O.S</h3><button class="close" onclick="fecharModal()">&times;</button></div><div class="modal-body" id="detalhesOS"></div></div></div>

<div id="modalEnviar" class="modal"><div class="modal-content" style="max-width:460px"><div class="modal-header"><h3><i class="fas fa-paper-plane"></i> Finalizar e Enviar</h3><button class="close" onclick="document.getElementById('modalEnviar').style.display='none'">&times;</button></div><div class="modal-body"><p style="margin-bottom:10px">Finalizar a etapa <strong><?php echo htmlspecialchars($setor_label); ?></strong> da <strong id="enviar_os_num"></strong> e enviar para:</p><div id="enviar_opcoes" style="display:flex;flex-direction:column;gap:6px"></div></div><div class="modal-footer"><button type="button" class="vbtn-sm" onclick="document.getElementById('modalEnviar').style.display='none'">Cancelar</button><button type="button" class="vbtn-sm btn-success" id="btnConfirmarEnviar"><i class="fas fa-check"></i> Confirmar e Enviar</button></div></div></div>

<script>
const fluxo_etapas = <?php echo json_encode(getValidOSEtapas()); ?>;
const etapasDestinoDisponiveis = <?php echo json_encode(array_values(array_diff(getValidOSEtapas(), ['autorizacao']))); ?>;

function abrirModalRetorno(os) {
    document.getElementById('os_id_retorno').value = os.id;
    document.getElementById('etapa_atual_retorno').value = os.etapa_atual;
    document.getElementById('os_numero_retorno').textContent = os.numero;
    document.getElementById('etapa_nome_retorno').textContent = os.etapa_atual;
    const container = document.getElementById('etapas_retorno_container');
    container.innerHTML = '';
    const opcoesRetorno = [{valor:'projetista',label:'Projetista (avaliar alteracoes)'}];
    const pos_atual = fluxo_etapas.indexOf(os.etapa_atual);
    if (pos_atual > 0) {
        for (let i = 1; i < pos_atual; i++) {
            const etapa = fluxo_etapas[i];
            opcoesRetorno.push({valor:etapa,label:rotuloEtapa(etapa)});
        }
    }
    opcoesRetorno.forEach((opcao,index) => {
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
    const res = await fetch('<?php echo SITE_URL; ?>/api/producao.php', {method:'POST', body:formData});
    const data = await res.json();
    if (data.success) location.reload(); else alert('Erro: ' + data.error);
};
const etapaLabels = <?php echo json_encode(getEtapasLabels(), JSON_UNESCAPED_UNICODE); ?>;
const rotuloEtapa = e => e === 'concluida' ? 'Concluir O.S. (última etapa)' : (etapaLabels[e] || e.charAt(0).toUpperCase() + e.slice(1));

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

async function gerenciarEtapa(os_id, etapa, acao, etapaDestino) {
    const formData = new FormData();
    formData.append('os_id', os_id);
    formData.append('etapa', etapa);
    formData.append('acao', acao === 'iniciar' ? 'iniciar_etapa' : 'finalizar_etapa');
    if (acao === 'finalizar') formData.append('etapa_destino', etapaDestino || '');
    const res = await fetch('<?php echo SITE_URL; ?>/api/producao.php', {method:'POST', body:formData});
    const data = await res.json();
    if (data.success) location.reload(); else alert('Erro: ' + data.error);
}
function abrirModal(os) {
    document.getElementById('modalTitulo').textContent = 'O.S ' + os.numero;
    document.getElementById('detalhesOS').innerHTML = 'Carregando detalhes...';
    document.getElementById('modalOS').style.display = 'block';
    fetch('<?php echo SITE_URL; ?>/api/os.php?id=' + os.id).then(r=>r.json()).then(data=>{
        let html = `<p><strong>Cliente:</strong> ${os.razao_social}</p><p><strong>Venda:</strong> ${os.venda_numero}</p>`;
        if (data.itens) { html += '<h4>Itens:</h4><ul>'; data.itens.forEach(item=>{ html += `<li>${item.descricao_manual||item.produto_nome} - Qtd: ${item.quantidade}</li>`; }); html += '</ul>'; }
        document.getElementById('detalhesOS').innerHTML = html;
    });
}
function fecharModal() { document.getElementById('modalOS').style.display = 'none'; }
window.onclick = function(event) { if (event.target.className==='modal') event.target.style.display='none'; };
</script>
<?php include '../../includes/footer_vendedor.php'; ?>
