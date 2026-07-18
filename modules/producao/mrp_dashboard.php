<?php
/**
 * Dashboard MRP - Planejamento Inteligente de Produção
 * Análise de demanda, sugestões de produção, previsão de materiais,
 * otimização de cronograma e alertas — no padrão Nomus (classes .dash-*).
 *
 * Acesso: master, gerente, producao
 */

require_once '../../config/config.php';
require_once '../../includes/workflow.php';

$db = getDB();
requirePermission(['master', 'gerente', 'producao']);

$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';
$page_title = 'MRP - Planejamento de Materiais';
include '../../includes/header_vendedor.php';
?>
<div class="vend-layout">
    <?php $GLOBALS['modulo_tipo'] = 'producao'; include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main"><div class="vend-content">

    <div class="dash-head">
        <div>
            <h1 class="dash-head-title"><span class="dash-head-ic"><i class="fas fa-boxes"></i></span> MRP — Planejamento de Materiais</h1>
            <p class="dash-head-sub">Planejamento inteligente de produção: demanda, sugestões e alertas</p>
        </div>
        <div class="dash-head-meta"><strong><?= htmlspecialchars($usuario_nome) ?></strong><?= date('d/m/Y H:i') ?></div>
    </div>

    <div class="dash-tabs">
        <button type="button" class="dash-tab active" data-aba="demanda" onclick="abaativa('demanda', this)"><i class="fas fa-bullseye"></i> Demanda</button>
        <button type="button" class="dash-tab" data-aba="sugestoes" onclick="abaativa('sugestoes', this)"><i class="fas fa-lightbulb"></i> Sugestões</button>
        <button type="button" class="dash-tab" data-aba="materiais" onclick="abaativa('materiais', this)"><i class="fas fa-cubes"></i> Materiais</button>
        <button type="button" class="dash-tab" data-aba="cronograma" onclick="abaativa('cronograma', this)"><i class="fas fa-stopwatch"></i> Cronograma</button>
        <button type="button" class="dash-tab" data-aba="alertas" onclick="abaativa('alertas', this)"><i class="fas fa-triangle-exclamation"></i> Alertas</button>
    </div>

    <!-- ABA: DEMANDA -->
    <div id="aba-demanda" class="aba-mrp">
        <div class="dash-kpis">
            <div class="dash-kpi red"><div class="dash-kpi-label"><i class="fas fa-arrow-trend-down"></i> Produtos faltando</div><div class="dash-kpi-val" id="kpi-faltando">0</div><div class="dash-kpi-sub">Ação necessária</div></div>
            <div class="dash-kpi amber"><div class="dash-kpi-label"><i class="fas fa-bolt"></i> Críticos</div><div class="dash-kpi-val" id="kpi-criticos">0</div><div class="dash-kpi-sub">Urgência máxima</div></div>
            <div class="dash-kpi green"><div class="dash-kpi-label"><i class="fas fa-list-check"></i> Itens analisados</div><div class="dash-kpi-val" id="kpi-valor">0</div><div class="dash-kpi-sub">Total na análise</div></div>
        </div>
        <div class="dash-section">
            <div class="dash-section-head"><h2><i class="fas fa-clipboard-list"></i> Análise de Demanda</h2><p>Vendas em andamento vs estoque atual</p></div>
            <div id="demanda-list" class="dash-list dash-scroll"><div class="dash-empty">Carregando demanda...</div></div>
        </div>
    </div>

    <!-- ABA: SUGESTÕES -->
    <div id="aba-sugestoes" class="aba-mrp" hidden>
        <div class="dash-kpis">
            <div class="dash-kpi red"><div class="dash-kpi-label"><i class="fas fa-circle-exclamation"></i> Críticas</div><div class="dash-kpi-val" id="sugestoes-criticas">0</div></div>
            <div class="dash-kpi amber"><div class="dash-kpi-label"><i class="fas fa-circle-arrow-up"></i> Altas</div><div class="dash-kpi-val" id="sugestoes-altas">0</div></div>
        </div>
        <div class="dash-section">
            <div class="dash-section-head"><h2><i class="fas fa-lightbulb"></i> Sugestões de Produção</h2><p>Baseadas na demanda em aberto</p></div>
            <div id="sugestoes-list" class="dash-list dash-scroll"><div class="dash-empty">Carregando sugestões...</div></div>
        </div>
    </div>

    <!-- ABA: MATERIAIS -->
    <div id="aba-materiais" class="aba-mrp" hidden>
        <div class="dash-section">
            <div class="dash-section-body">
                <div class="dash-form-row">
                    <div class="dash-field grow"><label>Produto</label>
                        <select id="produto-select" class="dash-select"><option value="">Carregando produtos...</option></select></div>
                    <div class="dash-field" style="width:120px"><label>Qtd.</label>
                        <input type="number" id="quantidade-input" value="1" min="1" class="dash-input"></div>
                    <button type="button" class="dash-btn" onclick="preverMateriais()"><i class="fas fa-calculator"></i> Prever</button>
                </div>
            </div>
        </div>
        <div id="materiais-preview" class="dash-section" hidden>
            <div class="dash-section-head"><h2><i class="fas fa-cubes"></i> Materiais Necessários</h2></div>
            <div id="materiais-list" class="dash-list dash-scroll"></div>
        </div>
    </div>

    <!-- ABA: CRONOGRAMA -->
    <div id="aba-cronograma" class="aba-mrp" hidden>
        <div class="dash-kpis">
            <div class="dash-kpi red"><div class="dash-kpi-label"><i class="fas fa-gauge-high"></i> Acelerar</div><div class="dash-kpi-val" id="crono-acelerar">0</div></div>
            <div class="dash-kpi amber"><div class="dash-kpi-label"><i class="fas fa-crosshairs"></i> Focar</div><div class="dash-kpi-val" id="crono-focar">0</div></div>
            <div class="dash-kpi green"><div class="dash-kpi-label"><i class="fas fa-check"></i> Em tempo</div><div class="dash-kpi-val" id="crono-tempo">0</div></div>
        </div>
        <div class="dash-section">
            <div class="dash-section-head"><h2><i class="fas fa-stopwatch"></i> Otimização de Cronograma</h2><p>Ordem recomendada de produção</p></div>
            <div id="cronograma-list" class="dash-list dash-scroll"><div class="dash-empty">Carregando cronograma...</div></div>
        </div>
    </div>

    <!-- ABA: ALERTAS -->
    <div id="aba-alertas" class="aba-mrp" hidden>
        <div id="alertas-list" class="dash-grid"><div class="dash-empty">Carregando alertas...</div></div>
    </div>

    </div></div>
</div>

<!-- MODAL: previsão detalhada -->
<div id="modal-previsao" class="dash-modal">
    <div class="dash-modal-card">
        <div class="dash-modal-head"><h2><i class="fas fa-cubes"></i> Previsão de Materiais</h2>
            <button type="button" class="dash-modal-close" onclick="fecharModal('modal-previsao')">&times;</button></div>
        <div id="modal-previsao-content" class="dash-modal-body"></div>
    </div>
</div>

<script>
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const corStatus = s => ({'crítica':'red','critica':'red','alta':'amber','normal':'green','média':'amber','media':'amber'}[s] || 'blue');

function abaativa(aba, btn) {
    document.querySelectorAll('.aba-mrp').forEach(e => e.hidden = true);
    document.getElementById('aba-' + aba).hidden = false;
    document.querySelectorAll('.dash-tab').forEach(e => e.classList.remove('active'));
    if (btn) btn.classList.add('active');
    if (aba === 'demanda') carregarDemanda();
    if (aba === 'sugestoes') carregarSugestoes();
    if (aba === 'cronograma') carregarCronograma();
    if (aba === 'alertas') carregarAlertas();
}

async function carregarDemanda() {
    try {
        const data = await (await fetch('/api/mrp.php?acao=analisar_demanda')).json();
        if (!data.sucesso) throw new Error(data.erro);
        document.getElementById('kpi-faltando').textContent = data.total;
        document.getElementById('kpi-criticos').textContent = data.criticas;
        document.getElementById('kpi-valor').textContent = data.total;
        let html = '';
        data.demanda.forEach(item => {
            const cor = corStatus(item.status_urgencia);
            html += `
                <div class="dash-row ${cor}">
                    <div class="dash-row-top">
                        <div><div class="dash-row-title">${esc(item.produto_nome)}</div>
                            <div class="dash-row-sub">${esc(item.cliente)} · Venda ${esc(item.venda_numero)}</div></div>
                        <span class="dash-chip ${cor}">${esc(String(item.status_urgencia).toUpperCase())}</span>
                    </div>
                    <div class="dash-mini">
                        <div><div class="k">Solicitado</div><div class="v blue">${esc(item.quantidade_solicitada)}</div></div>
                        <div><div class="k">Estoque</div><div class="v green">${esc(item.estoque_atual)}</div></div>
                        <div><div class="k">Faltante</div><div class="v red">${esc(item.faltante)} (${esc(item.percentual_falta)}%)</div></div>
                        <div><div class="k">Entrega</div><div class="v amber">${esc(item.dias_para_entrega)}d</div></div>
                    </div>
                </div>`;
        });
        document.getElementById('demanda-list').innerHTML = html || '<div class="dash-empty ok"><i class="fas fa-circle-check"></i> Nenhum faltante!</div>';
    } catch (err) {
        document.getElementById('demanda-list').innerHTML = `<div class="dash-empty err"><i class="fas fa-circle-xmark"></i> Erro: ${esc(err.message)}</div>`;
    }
}

async function carregarSugestoes() {
    try {
        const data = await (await fetch('/api/mrp.php?acao=sugerir_ordens')).json();
        if (!data.sucesso) throw new Error(data.erro);
        document.getElementById('sugestoes-criticas').textContent = data.criticas;
        document.getElementById('sugestoes-altas').textContent = data.altas;
        let html = '';
        data.sugestoes.forEach(item => {
            const cor = corStatus(item.prioridade);
            html += `
                <div class="dash-row ${cor}">
                    <div class="dash-row-top">
                        <div><div class="dash-row-title">${esc(item.produto_nome)}</div>
                            <div class="dash-row-sub">${esc(item.acao_recomendada)}</div></div>
                        <button type="button" class="dash-btn sm" onclick="criarOSsugestao(${Number(item.produto_id)}, ${Number(item.quantidade_sugerida)})"><i class="fas fa-plus"></i> Criar O.S.</button>
                    </div>
                    <div class="dash-mini">
                        <div><div class="k">Estoque</div><div class="v">${esc(item.estoque_atual)}</div></div>
                        <div><div class="k">Necessário</div><div class="v red">${esc(item.necessario)}</div></div>
                        <div><div class="k">Sugerido</div><div class="v green">${esc(item.quantidade_sugerida)}</div></div>
                        <div><div class="k">Margem</div><div class="v">${esc(item.margem_seguranca)}%</div></div>
                    </div>
                </div>`;
        });
        document.getElementById('sugestoes-list').innerHTML = html || '<div class="dash-empty ok"><i class="fas fa-circle-check"></i> Sem sugestões!</div>';
    } catch (err) {
        document.getElementById('sugestoes-list').innerHTML = `<div class="dash-empty err">Erro: ${esc(err.message)}</div>`;
    }
}

async function preverMateriais() {
    const produtoId = document.getElementById('produto-select').value;
    const quantidade = document.getElementById('quantidade-input').value;
    if (!produtoId) { alert('Selecione um produto'); return; }
    try {
        const fd = new FormData();
        fd.append('acao', 'prever_materiais'); fd.append('produto_id', produtoId); fd.append('quantidade', quantidade);
        const data = await (await fetch('/api/mrp.php', { method: 'POST', body: fd })).json();
        if (!data.sucesso) throw new Error(data.erro);
        let html = '<div class="dash-list">';
        data.materiais.forEach(m => {
            const falta = Number(m.faltante) > 0;
            html += `
                <div class="dash-row ${falta ? 'red' : 'green'}">
                    <div class="dash-row-top">
                        <div><div class="dash-row-title">${falta ? '<i class="fas fa-circle-xmark"></i>' : '<i class="fas fa-circle-check"></i>'} ${esc(m.material_nome)}</div>
                            <div class="dash-row-sub">Necessário: ${esc(m.quantidade_necessaria)} ${esc(m.unidade)}</div></div>
                        <span class="dash-chip ${falta ? 'red' : 'green'}">${falta ? 'Falta ' + esc(m.faltante) : 'OK'}</span>
                    </div>
                    <div class="dash-row-sub">Estoque atual: ${esc(m.estoque_atual)}</div>
                </div>`;
        });
        html += '</div>';
        document.getElementById('modal-previsao-content').innerHTML = html || '<div class="dash-empty">Sem BOM cadastrado para este produto.</div>';
        document.getElementById('modal-previsao').classList.add('open');
        document.getElementById('materiais-preview').hidden = false;
        document.getElementById('materiais-list').innerHTML = html;
    } catch (err) { alert('Erro: ' + err.message); }
}

async function carregarCronograma() {
    try {
        const data = await (await fetch('/api/mrp.php?acao=otimizar_cronograma')).json();
        if (!data.sucesso) throw new Error(data.erro);
        document.getElementById('crono-acelerar').textContent = data.acelerar;
        document.getElementById('crono-focar').textContent = data.focar;
        document.getElementById('crono-tempo').textContent = Math.max(0, data.total_os - data.acelerar - data.focar);
        const cores = { 'ACELERAR': 'red', 'FOCAR': 'amber', 'EM TEMPO': 'green' };
        let html = '';
        data.cronograma.forEach(item => {
            const cor = cores[item.recomendacao] || 'blue';
            html += `
                <div class="dash-row ${cor}">
                    <div class="dash-row-top">
                        <div><div class="dash-row-title">${esc(item.os_numero)} · ${esc(item.cliente)}</div>
                            <div class="dash-row-sub">Entrega: ${esc(item.data_prevista)}</div></div>
                        <span class="dash-chip ${cor}">${esc(item.recomendacao)}</span>
                    </div>
                    <div class="dash-progress"><span style="width:${Number(item.progresso_percentual)}%"></span></div>
                    <div class="dash-mini">
                        <div><div class="k">Progresso</div><div class="v">${esc(item.progresso_percentual)}%</div></div>
                        <div><div class="k">Dias</div><div class="v">${esc(item.dias_faltando)}</div></div>
                        <div><div class="k">Etapas</div><div class="v">${esc(item.etapas_concluidas)}/${esc(item.etapas_totais)}</div></div>
                    </div>
                </div>`;
        });
        document.getElementById('cronograma-list').innerHTML = html || '<div class="dash-empty ok">Sem O.S. em produção.</div>';
    } catch (err) {
        document.getElementById('cronograma-list').innerHTML = `<div class="dash-empty err">Erro: ${esc(err.message)}</div>`;
    }
}

async function carregarAlertas() {
    try {
        const data = await (await fetch('/api/mrp.php?acao=alertas')).json();
        if (!data.sucesso) throw new Error(data.erro);
        let html = '';
        data.alertas.forEach(a => {
            const cor = corStatus(a.severidade);
            html += `
                <div class="dash-section" style="margin:0">
                    <div class="dash-row ${cor}" style="border-top:none">
                        <div class="dash-row-top">
                            <div><div class="dash-row-title">${esc(a.titulo)}</div>
                                <div class="dash-row-sub">${esc(a.descricao)}</div></div>
                            <span class="dash-chip ${cor}">${esc(String(a.severidade).toUpperCase())}</span>
                        </div>
                    </div>
                </div>`;
        });
        document.getElementById('alertas-list').innerHTML = html || '<div class="dash-empty ok"><i class="fas fa-circle-check"></i> Nenhum alerta!</div>';
    } catch (err) {
        document.getElementById('alertas-list').innerHTML = `<div class="dash-empty err">Erro: ${esc(err.message)}</div>`;
    }
}

function criarOSsugestao(produtoId, qtd) {
    alert('Para produzir, gere a O.S. a partir da venda correspondente no painel de Produção.');
}

function fecharModal(id) { document.getElementById(id).classList.remove('open'); }

window.addEventListener('load', async () => {
    try {
        const data = await (await fetch('/api/produtos.php?acao=listar')).json();
        if (data.sucesso) {
            const sel = document.getElementById('produto-select');
            sel.innerHTML = '<option value="">Selecione um produto...</option>';
            data.produtos.forEach(p => {
                const o = document.createElement('option');
                o.value = p.id; o.textContent = p.nome; sel.appendChild(o);
            });
        }
    } catch (err) { console.error('Erro ao carregar produtos:', err); }
    carregarDemanda();
});

setInterval(() => {
    const vis = id => { const el = document.getElementById(id); return el && !el.hidden; };
    if (vis('aba-demanda')) carregarDemanda();
    if (vis('aba-sugestoes')) carregarSugestoes();
    if (vis('aba-cronograma')) carregarCronograma();
    if (vis('aba-alertas')) carregarAlertas();
}, 120000);
</script>
<?php include '../../includes/footer_vendedor.php'; ?>
