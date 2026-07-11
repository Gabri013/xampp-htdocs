<?php
/**
 * Template para criação das páginas de cada setor de produção
 * Substituir 'acabamento' pelo nome do setor (ex: corte, dobra, solda)
 */
require_once '../../config/config.php';
require_once '../../includes/workflow.php';
require_once '../../includes/expediente.php';

// Definir o setor desta página
$setor_atual = 'acabamento'; 
$setor_label = ucfirst($setor_atual);

// Verificar permissão (master, gerente ou o próprio setor)
requirePermission(['master', 'gerente', 'projetista', 'producao', 'vendedor', $setor_atual]);

$page_title = "Painel do Setor: $setor_label";

$db = getDB();
ensureOrdensServicoIndependentesSchema($db);
ensureExpedienteSchema($db);

// Buscar O.S que estão nesta etapa
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

include '../../includes/header_vendedor.php';
?>
<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main"><div class="vend-page-head"><h1 class="vend-page-title">Acabamento</h1></div><div class="vend-content">
<style>
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}
.modal.show {
    display: block;
}
.modal-content {
    background-color: white;
    margin: 10% auto;
    padding: 20px;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #ddd;
    padding-bottom: 10px;
    margin-bottom: 15px;
}
.close {
    font-size: 24px;
    cursor: pointer;
    border: none;
    background: none;
}
.etapa-checkbox-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px;
    border-bottom: 1px solid #eee;
}
.etapa-checkbox-item:last-child { border-bottom: none; }
.etapa-checkbox-item label { margin: 0; cursor: pointer; flex: 1; }
.thumb-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; margin-top: 10px; }
.thumb-card { border: 1px solid #ddd; border-radius: 8px; padding: 8px; background: #fff; text-align: center; }
.thumb-card img { width: 100%; height: 90px; object-fit: cover; border-radius: 4px; border: 1px solid #eee; cursor: zoom-in; }
.thumb-name { font-size: 11px; margin-top: 6px; word-break: break-word; color: #333; }
.lightbox-modal { display: none; position: fixed; z-index: 10001; inset: 0; background: rgba(0,0,0,0.88); align-items: center; justify-content: center; padding: 20px; }
.lightbox-modal.show { display: flex; }
.lightbox-content { max-width: 95vw; max-height: 90vh; border-radius: 6px; }
.lightbox-close { position: absolute; top: 16px; right: 20px; color: #fff; font-size: 34px; border: 0; background: transparent; cursor: pointer; }
.lightbox-caption { position: absolute; bottom: 16px; left: 0; right: 0; text-align: center; color: #fff; font-size: 14px; }
</style>

<div class="card">
    <div class="card-header">
        <h3>Painel de Produção - Setor: <?php echo $setor_label; ?></h3>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <strong>Instruções:</strong> Inicie o cronômetro ao começar o trabalho nesta etapa e finalize ao terminar para enviar para o próximo setor.
        </div>
        
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>O.S</th>
                        <th>Cliente</th>
                        <th>Entrega</th>
                        <th>Prioridade</th>
                        <th>Status Etapa</th>
                        <th>Tempo Decorrido</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ordens)): ?>
                        <tr>
                            <td colspan="7" class="text-center">Nenhuma ordem de serviço pendente para este setor</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ordens as $os): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($os['numero']); ?></strong></td>
                                <td><?php echo htmlspecialchars($os['razao_social']); ?></td>
                                <td><?php echo formatDate($os['data_termino'] ?? null); ?></td>
                                <td><?php echo getPrioridadeBadge($os['prioridade']); ?></td>
                                <td>
                                    <?php if ($os['etapa_status'] === 'em_andamento'): ?>
                                        <span class="vbadge-warning"><i class="fas fa-clock"></i> Em Andamento</span>
                                    <?php else: ?>
                                        <span class="vbadge-secondary">Aguardando Início</span>
                                    <?php endif; ?>
                                </td>
                                <?php
                                $segundosEtapa = 0;
                                $timerAtivo = false;
                                if (($os['etapa_status'] ?? '') === 'em_andamento' && !empty($os['etapa_inicio']) && !empty($os['etapa_usuario_id'])) {
                                    $segundosEtapa = getTempoTrabalhadoEtapaEmAndamento($db, $os['etapa_inicio'], (int) $os['etapa_usuario_id']);
                                    $statusExpedienteEtapa = getStatusExpedienteHoje($db, (int) $os['etapa_usuario_id']);
                                    $timerAtivo = ($statusExpedienteEtapa['status'] ?? '') === 'em_trabalho';
                                }
                                ?>
                                <td class="timer" data-segundos="<?php echo (int) $segundosEtapa; ?>" data-ativo="<?php echo $timerAtivo ? '1' : '0'; ?>">
                                    <?php echo ($os['etapa_status'] ?? '') === 'em_andamento' ? formatarSegundosExpediente((int) $segundosEtapa) : '--:--:--'; ?>
</td>
                                <td>
                                    <?php $isProjetista = ($_SESSION['usuario_tipo'] ?? '') === 'projetista'; ?>
                                    <?php if (!$isProjetista && $os['etapa_status'] !== 'em_andamento'): ?>
                                        <button class="vbtn-sm btn-success" onclick="gerenciarEtapa(<?php echo $os['id']; ?>, '<?php echo $setor_atual; ?>', 'iniciar')">
                                            <i class="fas fa-play"></i> Iniciar Trabalho
                                        </button>
                                    <?php elseif (!$isProjetista && $os['etapa_status'] === 'em_andamento'): ?>
                                        <button class="vbtn-sm btn-danger" onclick="gerenciarEtapa(<?php echo $os['id']; ?>, '<?php echo $setor_atual; ?>', 'finalizar')">
                                            <i class="fas fa-stop"></i> Finalizar e Enviar
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if (!$isProjetista && $os['etapa_atual'] !== 'autorizacao'): ?>
                                        <button class="vbtn-sm btn-warning" onclick='abrirModalRetorno(<?php echo json_encode($os); ?>)' title="Retornar etapa">
                                            <i class="fas fa-undo"></i> Retornar
                                        </button>
                                    <?php endif; ?>
                                    <button class="vbtn-sm btn-info" onclick="abrirModal(<?php echo htmlspecialchars(json_encode($os)); ?>)" title="Ver Resumo">
                                        <i class="fas fa-info-circle"></i>
                                    </button>
                                    <a href="os_detalhes.php?os_id=<?php echo $os['id']; ?>" class="vbtn-sm btn-primary" title="Ver Detalhes Completos">
                                        <i class="fas fa-eye"></i> Detalhes
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
</tbody>
            </table>
        </div>
    </div>
</div>
    </div>
</div>
</div>

<!-- Modal de Retorno de Etapa (FLEXÍVEL) -->
<div id="modalRetorno" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Retornar Etapa</h3>
            <button class="close" onclick="fecharModalRetorno()">&times;</button>
        </div>
        <form id="formRetorno">
            <div class="modal-body">
                <input type="hidden" name="acao" value="retornar_etapa">
                <input type="hidden" name="os_id" id="os_id_retorno">
                <input type="hidden" name="etapa_atual" id="etapa_atual_retorno">
                
                <div class="form-group">
                    <label><strong>O.S:</strong> <span id="os_numero_retorno"></span></label>
                </div>
                
                <div class="form-group">
                    <label><strong>Etapa Atual:</strong> <span id="etapa_nome_retorno"></span></label>
                </div>

                <div class="form-group">
                    <label><strong>Retornar para qual etapa? *</strong></label>
                    <div id="etapas_retorno_container" style="background: #f8f9fa; padding: 10px; border-radius: 5px; border: 1px solid #ddd;">
                        <!-- Checkboxes serão inseridos via JS -->
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="justificativa"><strong>Justificativa do Retorno *</strong></label>
                    <textarea id="justificativa" name="justificativa" class="form-control" rows="4" placeholder="Explique o motivo do retorno..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="vbtn-sm" onclick="fecharModalRetorno()">Cancelar</button>
                <button type="submit" class="vbtn-sm"><i class="fas fa-undo"></i> Confirmar Retorno</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Detalhes -->
<div id="modalOS" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitulo">Detalhes da O.S</h3>
            <button class="close" onclick="fecharModal()">&times;</button>
        </div>
        <div class="modal-body" id="detalhesOS"></div>
    </div>
</div>

<div id="lightboxModal" class="lightbox-modal">
    <button type="button" class="lightbox-close" onclick="fecharLightbox()">&times;</button>
    <img id="lightboxImage" class="lightbox-content" src="" alt="Visualizacao ampliada">
    <div id="lightboxCaption" class="lightbox-caption"></div>
</div>

<script>

const fluxo_etapas = <?php echo json_encode(getValidOSEtapas()); ?>;
const etapasDestinoDisponiveis = <?php echo json_encode(array_values(array_diff(getValidOSEtapas(), ['autorizacao']))); ?>;

function isImagemArquivo(nomeArquivo) {
    if (!nomeArquivo) return false;
    return /\.(jpg|jpeg|png|gif|webp)$/i.test(nomeArquivo);
}

function escapeHtml(texto) {
    const div = document.createElement('div');
    div.textContent = texto || '';
    return div.innerHTML;
}

function formatDateEntregaBr(data) {
    if (!data) return 'Não informada';
    const somenteData = String(data).split(' ')[0];
    const partes = somenteData.split('-');
    if (partes.length === 3) {
        return `${partes[2]}/${partes[1]}/${partes[0]}`;
    }
    return data;
}

function abrirLightbox(url, legenda) {
    document.getElementById('lightboxImage').src = url;
    document.getElementById('lightboxCaption').textContent = legenda || '';
    document.getElementById('lightboxModal').classList.add('show');
}

function fecharLightbox() {
    const modal = document.getElementById('lightboxModal');
    modal.classList.remove('show');
    document.getElementById('lightboxImage').src = '';
    document.getElementById('lightboxCaption').textContent = '';
}

function abrirModalRetorno(os) {
    document.getElementById('os_id_retorno').value = os.id;
    document.getElementById('etapa_atual_retorno').value = os.etapa_atual;
    document.getElementById('os_numero_retorno').textContent = os.numero;
    document.getElementById('etapa_nome_retorno').textContent = os.etapa_atual;
    
    const container = document.getElementById('etapas_retorno_container');
    container.innerHTML = '';
    
    const opcoesRetorno = [{
        valor: 'projetista',
        label: 'Projetista (avaliar alteracoes)'
    }];
    const pos_atual = fluxo_etapas.indexOf(os.etapa_atual);

    if (pos_atual > 0) {
        for (let i = 1; i < pos_atual; i++) {
            const etapa = fluxo_etapas[i];
            opcoesRetorno.push({
                valor: etapa,
                label: etapa.charAt(0).toUpperCase() + etapa.slice(1)
            });
        }
    }

    opcoesRetorno.forEach((opcao, index) => {
        const div = document.createElement('div');
        div.className = 'etapa-checkbox-item';
        div.style.display = 'flex';
        div.style.alignItems = 'center';
        div.style.gap = '10px';
        div.style.padding = '5px';
        div.style.borderBottom = '1px solid #eee';
        div.innerHTML = `
            <input type="radio" name="etapa_destino" value="${opcao.valor}" id="etapa_${opcao.valor}" ${index === 0 ? 'checked' : ''} required>
            <label for="etapa_${opcao.valor}" style="margin:0; cursor:pointer; flex:1;">${opcao.label}</label>
        `;
        container.appendChild(div);
    });
    
    document.getElementById('modalRetorno').classList.add('show');
}

function fecharModalRetorno() {
    document.getElementById('modalRetorno').classList.remove('show');
}

document.getElementById('formRetorno').onsubmit = function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('<?php echo SITE_URL; ?>/api/producao.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erro: ' + data.error);
        }
    });
};

function selecionarEtapaDestino(etapaAtual) {
    const opcoes = etapasDestinoDisponiveis.filter(e => e !== etapaAtual);
    return new Promise(resolve => {
        const overlay = document.createElement('div');
        overlay.style.position = 'fixed';
        overlay.style.inset = '0';
        overlay.style.background = 'rgba(0, 0, 0, 0.45)';
        overlay.style.zIndex = '10002';
        overlay.style.display = 'flex';
        overlay.style.alignItems = 'center';
        overlay.style.justifyContent = 'center';

        const card = document.createElement('div');
        card.style.background = '#fff';
        card.style.borderRadius = '8px';
        card.style.padding = '16px';
        card.style.width = '360px';
        card.style.maxWidth = '92vw';
        card.innerHTML = `
            <h4 style="margin:0 0 12px 0;">Enviar O.S para qual etapa?</h4>
            <select id="etapaDestinoSelect" class="form-control">
                ${opcoes.map(op => `<option value="${op}">${op.toUpperCase()}</option>`).join('')}
            </select>
            <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:12px;">
                <button type="button" id="etapaDestinoCancelar" class="vbtn-sm btn-sm">Cancelar</button>
                <button type="button" id="etapaDestinoConfirmar" class="vbtn-sm">Confirmar</button>
            </div>
        `;

        overlay.appendChild(card);
        document.body.appendChild(overlay);

        const cleanup = (valor) => {
            overlay.remove();
            resolve(valor);
        };

        card.querySelector('#etapaDestinoCancelar').addEventListener('click', () => cleanup(null));
        card.querySelector('#etapaDestinoConfirmar').addEventListener('click', () => cleanup(card.querySelector('#etapaDestinoSelect').value));
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) cleanup(null);
        });
    });
}

async function gerenciarEtapa(os_id, etapa, acao) {
    let etapaDestino = '';
    if (acao === 'finalizar') {
        etapaDestino = await selecionarEtapaDestino(etapa);
        if (!etapaDestino) return;
    }

    const formData = new FormData();
    formData.append('os_id', os_id);
    formData.append('etapa', etapa);
    formData.append('acao', acao === 'iniciar' ? 'iniciar_etapa' : 'finalizar_etapa');
    if (acao === 'finalizar') {
        formData.append('etapa_destino', etapaDestino);
    }

    fetch('<?php echo SITE_URL; ?>/api/producao.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erro: ' + data.error);
        }
    });
}

function atualizarTimers() {
    document.querySelectorAll('.timer[data-ativo="1"]').forEach(td => {
        const segundos = (parseInt(td.dataset.segundos || '0', 10) || 0) + 1;
        td.dataset.segundos = String(segundos);

        const h = Math.floor(segundos / 3600).toString().padStart(2, '0');
        const m = Math.floor((segundos % 3600) / 60).toString().padStart(2, '0');
        const s = (segundos % 60).toString().padStart(2, '0');

        td.textContent = `${h}:${m}:${s}`;
    });
}

setInterval(atualizarTimers, 1000);

function abrirModal(os) {
    document.getElementById('modalTitulo').textContent = 'O.S ' + os.numero;
    
    fetch('<?php echo SITE_URL; ?>/api/os.php?id=' + os.id)
        .then(response => response.json())
        .then(data => {
            const blocoRecall = data.ultimo_recall && data.ultimo_recall.justificativa
                ? `
                    <div style="margin-bottom:12px;padding:12px 14px;border-left:4px solid #dc3545;background:#fff1f2;color:#7f1d1d;border-radius:6px;">
                        <strong>Justificativa do retorno:</strong><br>
                        ${escapeHtml(data.ultimo_recall.justificativa)}
                    </div>
                `
                : '';
            let html = `
                <p><strong>Cliente:</strong> ${os.razao_social}</p>
                <p><strong>Venda:</strong> ${os.venda_numero}</p>
                <p><strong>Data de Entrega:</strong> ${formatDateEntregaBr(os.data_termino)}</p>
                ${blocoRecall}
                <hr>
                <h5>Observações Técnicas:</h5>
                <p><strong>Corte/Dobra:</strong> ${os.observacoes_corte_dobra || 'Nenhuma'}</p>
                <p><strong>Solda:</strong> ${os.observacoes_solda || 'Nenhuma'}</p>
                <hr>
            `;
            
            if (os.arquivo_projeto) {
                const projetoUrl = `<?php echo SITE_URL; ?>/assets/uploads/projetos/${os.arquivo_projeto}`;
                html += `<p><a href="${projetoUrl}" target="_blank" class="vbtn-sm btn-primary"><i class="fas fa-download"></i> Baixar Projeto</a></p>`;
            }

            const imagensProjeto = (data.arquivos || []).filter(arq => arq.tipo === 'projeto' && isImagemArquivo(arq.nome_arquivo));
            if (imagensProjeto.length > 0) {
                html += '<div style="margin-top:10px;"><strong>Imagem anexada pelo projetista:</strong><div class="thumb-grid">';
                imagensProjeto.forEach(arq => {
                    const arquivoUrl = `<?php echo SITE_URL; ?>/assets/uploads/projetos/${arq.nome_arquivo}`;
                    const nomeOriginal = escapeHtml(arq.nome_original || arq.nome_arquivo);
                    html += `
                        <div class="thumb-card">
                            <img src="${arquivoUrl}" alt="${nomeOriginal}" onclick="abrirLightbox('${arquivoUrl}', '${nomeOriginal}')">
                            <div class="thumb-name">${nomeOriginal}</div>
                        </div>
                    `;
                });
                html += '</div></div>';
            }
            
            document.getElementById('detalhesOS').innerHTML = html;
        });
        
    document.getElementById('modalOS').classList.add('show');
}

function fecharModal() {
    document.getElementById('modalOS').classList.remove('show');
}

function getPrioridadeBadge(prioridade) {
    const cores = {'verde': '#28a745', 'amarelo': '#ffc107', 'vermelho': '#dc3545'};
    return `<span class="badge" style="background-color: ${cores[prioridade]}; color: white;">${prioridade.toUpperCase()}</span>`;
}

window.onclick = function(event) {
    const modalOS = document.getElementById('modalOS');
    const modalRetorno = document.getElementById('modalRetorno');
    const lightboxModal = document.getElementById('lightboxModal');
    if (event.target === modalOS) fecharModal();
    if (event.target === modalRetorno) fecharModalRetorno();
    if (event.target === lightboxModal) fecharLightbox();
}

</script>

<?php include '../../includes/footer_vendedor.php'; ?>




