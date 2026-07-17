<?php
/**
 * Dashboard Expedição - Despacho de Produtos
 *
 * Padrão Nomus: Preparar, conferir e despachar O.S. concluídas
 * Acesso: master, gerente, expedicao
 */

require_once '../../config/config.php';
require_once '../../includes/workflow.php';
require_once '../../includes/components/buttons.nomus.php';

$page_title = 'Expedição - Despacho';
$db = getDB();
requirePermission(['master', 'gerente', 'expedicao', 'dashboard_producao']);

// Buscar O.S. concluídas para criar expedição
$stmt = $db->query("SELECT os.*, c.razao_social FROM ordens_servico os
    INNER JOIN clientes c ON os.cliente_id = c.id
    WHERE os.status = 'concluida'
    ORDER BY os.data_termino DESC
    LIMIT 20");
$os_lista = $stmt->fetchAll();

include '../../includes/header_vendedor.php';
?>

<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-page-head">
            <h1 class="vend-page-title">📮 Expedição - Despacho</h1>
        </div>
        <div class="vend-content">

<style>
    .exp-container {
        display: grid;
        grid-template-columns: 1fr 3fr;
        gap: 24px;
    }

    .exp-sidebar {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        height: fit-content;
        position: sticky;
        top: 20px;
    }

    .exp-titulo {
        font-size: 14px;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 12px;
    }

    .exp-form-grupo {
        margin-bottom: 12px;
    }

    .exp-form-label {
        font-size: 11px;
        font-weight: 600;
        color: #666;
        display: block;
        margin-bottom: 4px;
    }

    .exp-form-input {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 13px;
    }

    .exp-form-input:focus {
        border-color: #0891b2;
        outline: none;
    }

    .exp-botao {
        width: 100%;
        padding: 10px;
        background: #0891b2;
        color: white;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .exp-botao:hover {
        background: #0e7490;
        transform: translateY(-2px);
    }

    /* LISTA DE EXPEDIÇÕES */
    .exp-main {
        display: grid;
        gap: 16px;
    }

    .exp-stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        margin-bottom: 16px;
    }

    .exp-stat-card {
        background: white;
        padding: 12px;
        border-radius: 8px;
        text-align: center;
        border-left: 4px solid #0891b2;
    }

    .exp-stat-numero {
        font-size: 20px;
        font-weight: 700;
        color: #0891b2;
    }

    .exp-stat-label {
        font-size: 11px;
        color: #666;
        margin-top: 4px;
    }

    .exp-filtros {
        display: flex;
        gap: 12px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }

    .exp-filtro-botao {
        padding: 8px 12px;
        border: 1px solid #ddd;
        background: white;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
        transition: all 0.2s;
    }

    .exp-filtro-botao:hover,
    .exp-filtro-botao.ativo {
        background: #0891b2;
        color: white;
        border-color: #0891b2;
    }

    .exp-lista {
        display: grid;
        gap: 12px;
    }

    .exp-item {
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 16px;
        cursor: pointer;
        transition: all 0.2s;
        border-left: 4px solid #ddd;
    }

    .exp-item:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border-left-color: #0891b2;
    }

    .exp-item.preparando { border-left-color: #f59e0b; background: #fffbeb; }
    .exp-item.conferido { border-left-color: #8b5cf6; background: #faf5ff; }
    .exp-item.pronto { border-left-color: #3b82f6; background: #eff6ff; }
    .exp-item.despachado { border-left-color: #10b981; background: #f0fdf4; }
    .exp-item.entregue { border-left-color: #0891b2; background: #ecf9ff; }

    .exp-item-header {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr auto;
        gap: 12px;
        align-items: center;
        margin-bottom: 8px;
    }

    .exp-item-numero {
        font-weight: 700;
        color: #1f2937;
        font-size: 12px;
    }

    .exp-item-cliente {
        font-size: 11px;
        color: #666;
    }

    .exp-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 600;
    }

    .exp-badge-preparando { background: #fef3c7; color: #92400e; }
    .exp-badge-conferido { background: #f3e8ff; color: #6b21a8; }
    .exp-badge-pronto { background: #dbeafe; color: #0c4a6e; }
    .exp-badge-despachado { background: #dcfce7; color: #15803d; }
    .exp-badge-entregue { background: #cffafe; color: #164e63; }

    /* MODAL DE DETALHES */
    .exp-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 999;
        align-items: center;
        justify-content: center;
    }

    .exp-modal.ativo {
        display: flex;
    }

    .exp-modal-content {
        background: white;
        border-radius: 12px;
        padding: 24px;
        max-width: 700px;
        width: 90%;
        max-height: 85vh;
        overflow-y: auto;
    }

    .exp-modal-header {
        border-bottom: 2px solid #f3f4f6;
        padding-bottom: 16px;
        margin-bottom: 16px;
    }

    .exp-modal-numero {
        font-size: 12px;
        color: #666;
        margin-bottom: 4px;
    }

    .exp-modal-titulo {
        font-size: 18px;
        font-weight: 700;
        color: #1f2937;
    }

    .exp-modal-info {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 16px;
    }

    .exp-modal-info-item {
        border: 1px solid #e5e7eb;
        padding: 12px;
        border-radius: 6px;
    }

    .exp-modal-info-label {
        font-size: 10px;
        font-weight: 600;
        color: #666;
    }

    .exp-modal-info-valor {
        font-size: 13px;
        font-weight: 600;
        color: #1f2937;
        margin-top: 4px;
    }

    .exp-itens {
        background: #f9fafb;
        border-radius: 8px;
        padding: 12px;
        margin-bottom: 16px;
    }

    .exp-item-lista {
        background: white;
        padding: 8px;
        border-radius: 4px;
        margin-bottom: 6px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .exp-item-nome {
        font-weight: 600;
        font-size: 12px;
    }

    .exp-item-conferido {
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 3px;
        background: #dcfce7;
        color: #15803d;
    }

    .exp-acoes {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    @media (max-width: 1024px) {
        .exp-container {
            grid-template-columns: 1fr;
        }

        .exp-sidebar {
            position: static;
        }

        .exp-item-header {
            grid-template-columns: 1fr;
        }

        .exp-stats {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<div class="exp-container">
    <!-- SIDEBAR: Criar Expedição -->
    <div class="exp-sidebar">
        <div class="exp-titulo">📮 Criar Expedição</div>

        <div class="exp-form-grupo">
            <label class="exp-form-label">O.S. Concluída</label>
            <select class="exp-form-input" id="exp-os">
                <option value="">Selecione...</option>
                <?php foreach ($os_lista as $o): ?>
                    <option value="<?= $o['id'] ?>">OS <?= htmlspecialchars($o['numero']) ?> - <?= htmlspecialchars($o['razao_social']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="exp-form-grupo">
            <label class="exp-form-label">Transportadora</label>
            <select class="exp-form-input" id="exp-transportadora">
                <option value="">Selecione...</option>
                <option value="Próprio">Próprio</option>
                <option value="Sedex">Sedex</option>
                <option value="PAC">PAC</option>
                <option value="Jadlog">Jadlog</option>
                <option value="Outro">Outro</option>
            </select>
        </div>

        <div class="exp-form-grupo">
            <label class="exp-form-label">Peso Total (kg)</label>
            <input type="number" class="exp-form-input" id="exp-peso" placeholder="0,00" step="0.01">
        </div>

        <div class="exp-form-grupo">
            <label class="exp-form-label">Volume Total</label>
            <input type="text" class="exp-form-input" id="exp-volume" placeholder="Ex: 2 caixas">
        </div>

        <button class="exp-botao" onclick="criarExpedicao()">📦 Criar Expedição</button>

        <hr style="margin: 20px 0;">

        <div class="exp-titulo">📊 Filtros</div>
        <div class="exp-filtros" id="exp-filtros-container">
            <button class="exp-filtro-botao ativo" onclick="filtrarExpedicoes('todos')">Todos</button>
            <button class="exp-filtro-botao" onclick="filtrarExpedicoes('preparando')">📦 Preparando</button>
            <button class="exp-filtro-botao" onclick="filtrarExpedicoes('despachado')">✈️ Despachado</button>
            <button class="exp-filtro-botao" onclick="filtrarExpedicoes('entregue')">✅ Entregue</button>
        </div>
    </div>

    <!-- MAIN: Lista de Expedições -->
    <div class="exp-main">
        <!-- Stats -->
        <div class="exp-stats">
            <div class="exp-stat-card" style="border-left-color: #f59e0b;">
                <div class="exp-stat-numero" style="color: #f59e0b;">—</div>
                <div class="exp-stat-label">Preparando</div>
            </div>
            <div class="exp-stat-card" style="border-left-color: #3b82f6;">
                <div class="exp-stat-numero" style="color: #3b82f6;">—</div>
                <div class="exp-stat-label">Prontos</div>
            </div>
            <div class="exp-stat-card" style="border-left-color: #10b981;">
                <div class="exp-stat-numero" style="color: #10b981;">—</div>
                <div class="exp-stat-label">Despachados</div>
            </div>
            <div class="exp-stat-card" style="border-left-color: #0891b2;">
                <div class="exp-stat-numero" style="color: #0891b2;">—</div>
                <div class="exp-stat-label">Entregues</div>
            </div>
        </div>

        <!-- Lista -->
        <div id="exp-lista"></div>
    </div>
</div>

<!-- MODAL DE DETALHES -->
<div class="exp-modal" id="exp-modal">
    <div class="exp-modal-content">
        <div class="exp-modal-header">
            <div class="exp-modal-numero" id="exp-modal-numero"></div>
            <div class="exp-modal-titulo" id="exp-modal-titulo"></div>
        </div>

        <div class="exp-modal-info">
            <div class="exp-modal-info-item">
                <div class="exp-modal-info-label">CLIENTE</div>
                <div class="exp-modal-info-valor" id="exp-modal-cliente"></div>
            </div>
            <div class="exp-modal-info-item">
                <div class="exp-modal-info-label">O.S. ORIGEM</div>
                <div class="exp-modal-info-valor" id="exp-modal-os"></div>
            </div>
            <div class="exp-modal-info-item">
                <div class="exp-modal-info-label">STATUS</div>
                <div class="exp-modal-info-valor" id="exp-modal-status"></div>
            </div>
            <div class="exp-modal-info-item">
                <div class="exp-modal-info-label">TRANSPORTADORA</div>
                <div class="exp-modal-info-valor" id="exp-modal-transportadora"></div>
            </div>
        </div>

        <div class="exp-itens">
            <div style="font-weight: 700; margin-bottom: 8px;">📦 Itens a Enviar</div>
            <div id="exp-modal-itens"></div>
        </div>

        <div class="exp-acoes">
            <select id="exp-novo-status" style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 6px;">
                <option value="">Alterar status...</option>
                <option value="preparando">📦 Preparando</option>
                <option value="conferido">✓ Conferido</option>
                <option value="pronto">✈️ Pronto</option>
                <option value="despachado">✈️ Despachado</option>
                <option value="entregue">✅ Entregue</option>
            </select>
            <button class="exp-botao" onclick="atualizarStatus()" style="width: auto;">Atualizar</button>
        </div>
    </div>
</div>

</div>
        </div>
    </div>
</div>

<script>
    let filtroAtivo = 'todos';
    let expedicaoAtualId = null;

    function carregarExpedicoes() {
        let filtro = filtroAtivo === 'todos' ? '' : `&status=${filtroAtivo}`;

        fetch(`<?= SITE_URL ?>/api/expedicao.php?acao=listar${filtro}`)
            .then(r => r.json())
            .then(data => {
                if (data.sucesso && data.expedicoes) {
                    renderizarExpedicoes(data.expedicoes);
                }
            });
    }

    function renderizarExpedicoes(expedicoes) {
        if (expedicoes.length === 0) {
            document.getElementById('exp-lista').innerHTML = '<p style="text-align: center; color: #999; padding: 40px;">Nenhuma expedição encontrada</p>';
            return;
        }

        let html = '<div class="exp-lista">';
        expedicoes.forEach(e => {
            const statusClass = `exp-badge-${e.status}`;

            html += `<div class="exp-item ${e.status}" onclick="abrirDetalhes(${e.id})">
                <div class="exp-item-header">
                    <div>
                        <div class="exp-item-numero">${e.numero}</div>
                        <div class="exp-item-cliente">OS ${e.os_numero} - ${e.razao_social}</div>
                    </div>
                    <span class="exp-badge ${statusClass}">${e.status.toUpperCase()}</span>
                    <span>${e.numero_rastreamento ? '🔗' : '○'}</span>
                </div>
            </div>`;
        });
        html += '</div>';

        document.getElementById('exp-lista').innerHTML = html;
    }

    function filtrarExpedicoes(filtro) {
        filtroAtivo = filtro;
        document.querySelectorAll('.exp-filtro-botao').forEach(b => b.classList.remove('ativo'));
        event.target.classList.add('ativo');
        carregarExpedicoes();
    }

    function criarExpedicao() {
        const osId = document.getElementById('exp-os').value;
        const transportadora = document.getElementById('exp-transportadora').value;
        const peso = document.getElementById('exp-peso').value;
        const volume = document.getElementById('exp-volume').value;

        if (!osId) {
            alert('Selecione uma O.S.');
            return;
        }

        const formData = new FormData();
        formData.append('acao', 'criar');
        formData.append('os_id', osId);
        formData.append('transportadora', transportadora);
        formData.append('peso_total', peso);
        formData.append('volume_total', volume);

        fetch('<?= SITE_URL ?>/api/expedicao.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.sucesso) {
                alert('✅ Expedição criada: ' + data.numero);
                document.getElementById('exp-os').value = '';
                carregarExpedicoes();
            }
        });
    }

    function abrirDetalhes(expedicaoId) {
        expedicaoAtualId = expedicaoId;
        fetch(`<?= SITE_URL ?>/api/expedicao.php?acao=obter&expedicao_id=${expedicaoId}`)
            .then(r => r.json())
            .then(data => {
                if (data.sucesso) {
                    const e = data.expedicao;
                    document.getElementById('exp-modal-numero').innerText = e.numero;
                    document.getElementById('exp-modal-titulo').innerText = 'Expedição';
                    document.getElementById('exp-modal-cliente').innerText = e.razao_social;
                    document.getElementById('exp-modal-os').innerText = 'OS ' + e.os_numero;
                    document.getElementById('exp-modal-status').innerText = e.status.toUpperCase();
                    document.getElementById('exp-modal-transportadora').innerText = e.transportadora || '—';

                    let html = '';
                    e.itens.forEach(i => {
                        html += `<div class="exp-item-lista">
                            <span class="exp-item-nome">${i.nome} (${i.quantidade} un)</span>
                            <span class="exp-item-conferido">${i.conferido ? '✓ OK' : '○ Pendente'}</span>
                        </div>`;
                    });
                    document.getElementById('exp-modal-itens').innerHTML = html;

                    document.getElementById('exp-modal').classList.add('ativo');
                }
            });
    }

    function atualizarStatus() {
        const status = document.getElementById('exp-novo-status').value;
        if (!status) return;

        const formData = new FormData();
        formData.append('acao', 'atualizar_status');
        formData.append('expedicao_id', expedicaoAtualId);
        formData.append('status', status);

        fetch('<?= SITE_URL ?>/api/expedicao.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.sucesso) {
                abrirDetalhes(expedicaoAtualId);
                carregarExpedicoes();
            }
        });
    }

    // Fechar modal
    document.getElementById('exp-modal').onclick = function(e) {
        if (e.target === this) this.classList.remove('ativo');
    }

    // Carregar ao abrir
    carregarExpedicoes();
    setInterval(carregarExpedicoes, 60000);
</script>

<?php include '../../includes/footer_vendedor.php'; ?>
