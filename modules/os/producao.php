<?php
require_once '../../config/config.php';
require_once '../../includes/workflow.php';
requirePermission(['master', 'gerente', 'producao', 'projetista', 'corte', 'dobra', 'solda', 'refrigeracao', 'acabamento', 'finalizacao', 'montagem', 'programacao', 'mobiliario', 'coccao', 'embalagem', 'tubo', 'engenharia', 'vendedor']);

$page_title = 'Painel de Produção - Fluxo de Etapas';

$db = getDB();
ensureOrdensServicoIndependentesSchema($db);

$usuario_tipo = $_SESSION['usuario_tipo'];
$etapa_usuario = $usuario_tipo;
if ($usuario_tipo === 'producao' || $usuario_tipo === 'master') {
    $etapa_usuario = null;
}

$fluxo_etapas = ['autorizacao', 'corte', 'dobra', 'solda', 'refrigeracao', 'acabamento', 'finalizacao', 'montagem'];
$ordens_por_etapa = [];

foreach ($fluxo_etapas as $etapa) {
    $stmt = $db->query("
        SELECT os.*, c.razao_social, COALESCE(v.numero, 'Independente') as venda_numero,
               (SELECT ep.status FROM os_etapas_producao ep WHERE ep.os_id = os.id AND ep.etapa = os.etapa_atual) as etapa_status,
               (SELECT ep.data_inicio FROM os_etapas_producao ep WHERE ep.os_id = os.id AND ep.etapa = os.etapa_atual) as etapa_inicio
        FROM ordens_servico os
        INNER JOIN clientes c ON os.cliente_id = c.id
        LEFT JOIN vendas v ON os.venda_id = v.id
        WHERE os.etapa_atual = '$etapa' AND os.status = 'em_producao'
        ORDER BY 
            CASE os.prioridade 
                WHEN 'vermelho' THEN 1 
                WHEN 'amarelo' THEN 2 
                WHEN 'verde' THEN 3 
            END,
            os.data_inicio ASC
    ");
    $ordens_por_etapa[$etapa] = $stmt->fetchAll();
}

include '../../includes/header_vendedor.php';
?>

<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-page-head"><h1 class="vend-page-title">Painel Kanban de Produção</h1></div>

        <div class="vend-content">
    <div class="card-header">
        <h3>📊 Painel Kanban de Produção - Visão Panorâmica</h3>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <strong>Instruções:</strong> Visualize todas as etapas de produção em tempo real. Clique em "Iniciar" para começar a trabalhar e em "Finalizar" para enviar para a próxima etapa. Use o botão de retorno para devolver a O.S. para qualquer etapa anterior.
        </div>
        
        <!-- Painel Kanban Panorâmico -->
        <div class="kanban-container">
            <?php foreach ($fluxo_etapas as $etapa): ?>
                <div class="kanban-column">
                    <div class="kanban-column-header">
                        <h4><?php echo getEtapaLabel($etapa); ?></h4>
                        <span class="kanban-count"><?php echo count($ordens_por_etapa[$etapa]); ?></span>
                    </div>
                    <div class="kanban-cards">
                        <?php if (empty($ordens_por_etapa[$etapa])): ?>
                            <div class="kanban-empty">Sem ordens</div>
                        <?php else: ?>
                            <?php foreach ($ordens_por_etapa[$etapa] as $os): ?>
                                <div class="kanban-card kanban-card-<?php echo $os['prioridade']; ?>">
                                    <div class="kanban-card-header">
                                        <strong><?php echo htmlspecialchars($os['numero']); ?></strong>
                                        <span class="kanban-priority-badge"><?php echo ucfirst($os['prioridade']); ?></span>
                                    </div>
                                    <div class="kanban-card-body">
                                        <p class="kanban-client"><?php echo htmlspecialchars(substr($os['razao_social'], 0, 20)); ?></p>
                                        <p class="kanban-venda">Venda: <?php echo htmlspecialchars($os['venda_numero']); ?></p>
                                        <?php if ($os['etapa_status'] === 'em_andamento'): ?>
                                            <p class="kanban-status"><span class="vbadge badge-warning">Em Andamento</span></p>
                                        <?php else: ?>
                                            <p class="kanban-status"><span class="vbadge badge-secondary">Pendente</span></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="kanban-card-footer">
                                        <?php if ($os['etapa_status'] !== 'em_andamento'): ?>
                                            <button class="vbtn--xs btn-success" onclick="gerenciarEtapa(<?php echo $os['id']; ?>, '<?php echo $os['etapa_atual']; ?>', 'iniciar')" title="Iniciar trabalho">
                                                <i class="fas fa-play"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="vbtn--xs btn-danger" onclick="gerenciarEtapa(<?php echo $os['id']; ?>, '<?php echo $os['etapa_atual']; ?>', 'finalizar')" title="Finalizar e enviar">
                                                <i class="fas fa-stop"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($os['etapa_atual'] !== 'autorizacao'): ?>
                                        <button class="vbtn--xs btn-warning" onclick='abrirModalRetorno(<?php echo json_encode($os); ?>)' title="Retornar etapa">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                        <?php endif; ?>

                                        <button class="vbtn--xs btn-info" onclick='abrirModal(<?php echo json_encode($os); ?>)' title="Ver resumo">
                                            <i class="fas fa-info-circle"></i>
                                        </button>
                                        <a href="os_detalhes.php?os_id=<?php echo $os['id']; ?>" class="vbtn--xs btn-primary" title="Ver detalhes completos">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
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
                <button type="button" class="vbtn--secondary" onclick="fecharModalRetorno()">Cancelar</button>
                <button type="submit" class="vbtn--warning"><i class="fas fa-undo"></i> Confirmar Retorno</button>
            </div>
        </form>
    </div>
</div>

<style>
.kanban-container {
    display: grid;
    grid-template-columns: repeat(8, 1fr);
    gap: 4px;
    overflow-x: auto;
    padding: 6px;
    background-color: #f5f5f5;
    border-radius: 8px;
    width: 100%;
    box-sizing: border-box;
    margin: 0;
}

.kanban-column {
    background-color: #e8e8e8;
    border-radius: 6px;
    padding: 6px;
    min-height: 300px;
    max-height: 600px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}

.kanban-column-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
    padding-bottom: 4px;
    border-bottom: 1px solid #999;
    position: sticky;
    top: 0;
    background-color: #e8e8e8;
    z-index: 10;
}

.kanban-column-header h4 {
    margin: 0;
    font-size: 10px;
    font-weight: bold;
    color: #333;
}

.kanban-count {
    background-color: #3498db;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 9px;
}

.kanban-cards {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.kanban-card {
    background-color: white;
    border-radius: 3px;
    padding: 4px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    border-left: 3px solid #3498db;
    font-size: 9px;
}

.kanban-card-vermelho { border-left-color: #e74c3c; }
.kanban-card-amarelo { border-left-color: #f39c12; }
.kanban-card-verde { border-left-color: #27ae60; }

.kanban-card-header {
    display: flex;
    justify-content: space-between;
    font-weight: bold;
    margin-bottom: 2px;
}

.kanban-priority-vbadge {
    font-size: 7px;
    padding: 1px 3px;
    border-radius: 2px;
    background-color: #eee;
}

.kanban-client { font-weight: bold; margin: 0; }
.kanban-venda { color: #666; margin: 0; }

.kanban-card-footer {
    display: flex;
    gap: 2px;
    margin-top: 4px;
}

.btn-xs {
    padding: 2px 4px;
    font-size: 8px;
}

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

.modal-content {
    background-color: white;
    margin: 10% auto;
    padding: 20px;
    border-radius: 8px;
    width: 90%;
    max-width: 800px;
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
    padding: 5px;
    border-bottom: 1px solid #eee;
}

.etapa-checkbox-item:last-child { border-bottom: none; }
.etapa-checkbox-item label { margin: 0; cursor: pointer; flex: 1; }

@media (max-width: 1200px) {
    .kanban-container { grid-template-columns: repeat(4, 1fr); }
}
</style>

<script>
const fluxo_etapas = <?php echo json_encode(getValidOSEtapas()); ?>;
const etapasDestinoDisponiveis = <?php echo json_encode(array_values(array_diff(getValidOSEtapas(), ['autorizacao']))); ?>;

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
                <button type="button" id="etapaDestinoCancelar" class="vbtn--secondary btn-sm">Cancelar</button>
                <button type="button" id="etapaDestinoConfirmar" class="vbtn--primary btn-sm">Confirmar</button>
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
        for (let i = 0; i < pos_atual; i++) {
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
        div.innerHTML = `
            <input type="radio" name="etapa_destino" value="${opcao.valor}" id="etapa_${opcao.valor}" ${index === 0 ? 'checked' : ''} required>
            <label for="etapa_${opcao.valor}">${opcao.label}</label>
        `;
        container.appendChild(div);
    });
    
    document.getElementById('modalRetorno').style.display = 'block';
}

function fecharModalRetorno() {
    document.getElementById('modalRetorno').style.display = 'none';
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

function abrirModal(os) {
    document.getElementById('modalTitulo').textContent = 'O.S ' + os.numero;
    document.getElementById('detalhesOS').innerHTML = 'Carregando detalhes...';
    document.getElementById('modalOS').style.display = 'block';

    fetch('<?php echo SITE_URL; ?>/api/os.php?id=' + os.id)
        .then(res => res.json())
        .then(data => {
            let html = `<p><strong>Cliente:</strong> ${os.razao_social}</p>`;
            html += `<p><strong>Venda:</strong> ${os.venda_numero}</p>`;
            
            if (data.itens) {
                html += '<h4>Itens:</h4><ul>';
                data.itens.forEach(item => {
                    html += `<li>${item.descricao_manual || item.produto_nome} - Qtd: ${item.quantidade}</li>`;
                });
                html += '</ul>';
            }
            document.getElementById('detalhesOS').innerHTML = html;
        });
}

function fecharModal() {
    document.getElementById('modalOS').style.display = 'none';
}

window.onclick = function(event) {
    if (event.target.className === 'modal') {
        event.target.style.display = 'none';
    }
};
</script>

    </div>
</div>

<?php include '../../includes/footer_vendedor.php'; ?>



