<?php
/**
 * Dashboard de Custos — Análise financeira por O.S. (padrão Nomus).
 * Faturamento vs custo, lucro/margem por O.S. e por cliente.
 *
 * Acesso: master, gerente, financeiro
 */

require_once '../../config/config.php';
require_once '../../includes/workflow.php';

$db = getDB();
requirePermission(['master', 'gerente', 'financeiro']);

$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';
$page_title = 'Dashboard de Custos';
include '../../includes/header_vendedor.php';
?>
<div class="vend-layout">
    <?php $GLOBALS['modulo_tipo'] = 'financeiro'; include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main"><div class="vend-content">

    <div class="dash-head">
        <div>
            <h1 class="dash-head-title"><span class="dash-head-ic" style="background:var(--dash-green)"><i class="fas fa-coins"></i></span> Análise de Custos</h1>
            <p class="dash-head-sub">Custo real vs faturamento e margens por cliente</p>
        </div>
        <div class="dash-head-meta"><strong><?= htmlspecialchars($usuario_nome) ?></strong><?= date('d/m/Y') ?></div>
    </div>

    <div class="dash-tabs">
        <button type="button" class="dash-tab active" onclick="abaativa('resumo', this)"><i class="fas fa-chart-pie"></i> Resumo</button>
        <button type="button" class="dash-tab" onclick="abaativa('detalhes', this)"><i class="fas fa-magnifying-glass-dollar"></i> Detalhes O.S.</button>
        <button type="button" class="dash-tab" onclick="abaativa('clientes', this)"><i class="fas fa-users"></i> Margem por Cliente</button>
    </div>

    <!-- ABA: RESUMO -->
    <div id="aba-resumo" class="aba-custos">
        <div class="dash-kpis">
            <div class="dash-kpi blue"><div class="dash-kpi-label"><i class="fas fa-money-bill-wave"></i> Faturamento</div><div class="dash-kpi-val" id="kpi-faturamento">R$ 0</div><div class="dash-kpi-sub">No período</div></div>
            <div class="dash-kpi amber"><div class="dash-kpi-label"><i class="fas fa-arrow-trend-down"></i> Custo total</div><div class="dash-kpi-val" id="kpi-custo">R$ 0</div><div class="dash-kpi-sub">Mão de obra + materiais</div></div>
            <div class="dash-kpi green"><div class="dash-kpi-label"><i class="fas fa-sack-dollar"></i> Lucro bruto</div><div class="dash-kpi-val" id="kpi-lucro">R$ 0</div><div class="dash-kpi-sub">Faturamento − custos</div></div>
            <div class="dash-kpi purple"><div class="dash-kpi-label"><i class="fas fa-percent"></i> Margem</div><div class="dash-kpi-val" id="kpi-margem">0%</div><div class="dash-kpi-sub">Lucratividade média</div></div>
        </div>

        <div class="dash-section">
            <div class="dash-section-body">
                <div class="dash-form-row">
                    <div class="dash-field"><label>Período</label>
                        <input type="month" id="filtro-mes" value="<?= date('Y-m') ?>" class="dash-input" onchange="carregarResumo()"></div>
                    <button type="button" class="dash-btn green" onclick="carregarResumo()"><i class="fas fa-filter"></i> Filtrar</button>
                </div>
            </div>
        </div>

        <div class="dash-grid cols-2" style="margin-bottom:20px">
            <div class="dash-section" style="margin:0"><div class="dash-section-head"><h2><i class="fas fa-chart-pie"></i> Distribuição de Custos</h2></div><div class="dash-section-body"><canvas id="chart-custos" height="220"></canvas></div></div>
            <div class="dash-section" style="margin:0"><div class="dash-section-head"><h2><i class="fas fa-chart-column"></i> Lucratividade por O.S.</h2></div><div class="dash-section-body"><canvas id="chart-lucro" height="220"></canvas></div></div>
        </div>

        <div class="dash-section">
            <div class="dash-section-head"><h2><i class="fas fa-clipboard-list"></i> Ordens de Serviço</h2></div>
            <div class="dash-table-wrap">
                <table class="dash-table">
                    <thead><tr><th>O.S.</th><th>Cliente</th><th style="text-align:right">Faturado</th><th style="text-align:right">Custo</th><th style="text-align:right">Lucro</th><th style="text-align:center">Margem</th><th style="text-align:center">Ação</th></tr></thead>
                    <tbody id="tabela-os"><tr><td colspan="7" class="dash-empty">Carregando...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ABA: DETALHES O.S. -->
    <div id="aba-detalhes" class="aba-custos" hidden>
        <div class="dash-section">
            <div class="dash-section-body">
                <div class="dash-form-row">
                    <div class="dash-field grow"><label>Ordem de Serviço</label>
                        <select id="os-select" class="dash-select"><option value="">Carregue o Resumo primeiro…</option></select></div>
                    <button type="button" class="dash-btn green" onclick="carregarDetalhesOS()"><i class="fas fa-magnifying-glass"></i> Análise</button>
                </div>
            </div>
        </div>
        <div class="dash-empty" id="detalhes-hint">Selecione uma O.S. para ver a composição de custos.</div>
    </div>

    <!-- ABA: CLIENTES -->
    <div id="aba-clientes" class="aba-custos" hidden>
        <div class="dash-kpis">
            <div class="dash-kpi green"><div class="dash-kpi-label"><i class="fas fa-trophy"></i> Melhor margem</div><div class="dash-kpi-val" id="melhor-cliente">--</div></div>
            <div class="dash-kpi amber"><div class="dash-kpi-label"><i class="fas fa-triangle-exclamation"></i> Pior margem</div><div class="dash-kpi-val" id="pior-cliente">--</div></div>
            <div class="dash-kpi blue"><div class="dash-kpi-label"><i class="fas fa-chart-simple"></i> Margem média</div><div class="dash-kpi-val" id="margem-media">--</div></div>
        </div>
        <div class="dash-section">
            <div class="dash-section-head"><h2><i class="fas fa-briefcase"></i> Clientes</h2><p>Ordenados por lucratividade</p></div>
            <div class="dash-table-wrap">
                <table class="dash-table">
                    <thead><tr><th>Cliente</th><th style="text-align:center">O.S.</th><th style="text-align:right">Faturado</th><th style="text-align:right">Custo médio</th><th style="text-align:center">Margem</th><th style="text-align:center">Status</th></tr></thead>
                    <tbody id="tabela-clientes"><tr><td colspan="6" class="dash-empty">Carregando...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    </div></div>
</div>

<!-- MODAL -->
<div id="modal-detalhes" class="dash-modal">
    <div class="dash-modal-card">
        <div class="dash-modal-head"><h2 id="modal-titulo">Detalhes de Custos</h2>
            <button type="button" class="dash-modal-close" onclick="fecharModal()">&times;</button></div>
        <div id="modal-content" class="dash-modal-body"></div>
    </div>
</div>

<script>
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const money = v => 'R$ ' + parseFloat(v || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
let ultimaListaOS = [];

function abaativa(aba, btn) {
    document.querySelectorAll('.aba-custos').forEach(e => e.hidden = true);
    document.getElementById('aba-' + aba).hidden = false;
    document.querySelectorAll('.dash-tab').forEach(e => e.classList.remove('active'));
    if (btn) btn.classList.add('active');
    if (aba === 'resumo') carregarResumo();
    if (aba === 'clientes') carregarMargensPorCliente();
}

function corMargem(m) { return m >= 30 ? 'green' : m >= 20 ? 'blue' : m >= 10 ? 'amber' : 'red'; }

async function carregarResumo() {
    const mes = document.getElementById('filtro-mes').value;
    try {
        const data = await (await fetch(`/api/custos.php?acao=listar_custos&mes=${mes}`)).json();
        if (!data.sucesso) throw new Error(data.erro);
        document.getElementById('kpi-faturamento').textContent = money(data.total_venda);
        document.getElementById('kpi-custo').textContent = money(data.total_custo);
        document.getElementById('kpi-lucro').textContent = money(data.lucro_total);
        document.getElementById('kpi-margem').textContent = data.margem_geral_percentual + '%';

        ultimaListaOS = data.custos;
        const sel = document.getElementById('os-select');
        sel.innerHTML = '<option value="">Selecione uma O.S.…</option>';
        data.custos.forEach(o => { const op = document.createElement('option'); op.value = o.os_id; op.textContent = `${o.os_numero} — ${o.cliente}`; sel.appendChild(op); });

        let html = '';
        data.custos.forEach(item => {
            const cl = item.lucro > 0 ? 'green' : 'red';
            const cm = corMargem(item.margem_percentual);
            html += `<tr>
                <td style="font-weight:700;color:var(--dash-brand)">${esc(item.os_numero)}</td>
                <td>${esc(item.cliente)}</td>
                <td style="text-align:right;font-weight:600">${money(item.valor_venda)}</td>
                <td style="text-align:right">${money(item.custo_total)}</td>
                <td style="text-align:right;font-weight:700" class="dash-mini"><span class="v ${cl}">${money(item.lucro)}</span></td>
                <td style="text-align:center"><span class="dash-chip ${cm}">${item.margem_percentual}%</span></td>
                <td style="text-align:center"><button type="button" class="dash-btn sm ghost" onclick="abrirDetalhesModal(${Number(item.os_id)})">Ver</button></td>
            </tr>`;
        });
        document.getElementById('tabela-os').innerHTML = html || '<tr><td colspan="7" class="dash-empty ok">Nenhuma O.S. neste período</td></tr>';
        atualizarGraficos(data);
    } catch (err) { document.getElementById('tabela-os').innerHTML = `<tr><td colspan="7" class="dash-empty err">Erro: ${esc(err.message)}</td></tr>`; }
}

function atualizarGraficos(data) {
    if (typeof Chart === 'undefined') return;
    const ctx1 = document.getElementById('chart-custos').getContext('2d');
    if (window.chartCustos) window.chartCustos.destroy();
    const t = data.total_custo || 0;
    window.chartCustos = new Chart(ctx1, {
        type: 'doughnut',
        data: { labels: ['Mão de obra', 'Materiais', 'Overhead'],
            datasets: [{ data: [t*0.5, t*0.35, t*0.15], backgroundColor: ['#2563eb', '#d97706', '#7c3aed'], borderColor: '#fff', borderWidth: 2 }] },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });
    const ctx2 = document.getElementById('chart-lucro').getContext('2d');
    if (window.chartLucro) window.chartLucro.destroy();
    const top = data.custos.slice(0, 10);
    window.chartLucro = new Chart(ctx2, {
        type: 'bar',
        data: { labels: top.map(c => c.os_numero),
            datasets: [{ label: 'Lucro (R$)', data: top.map(c => c.lucro), backgroundColor: top.map(c => c.lucro > 0 ? '#16a34a' : '#dc2626'), borderRadius: 4 }] },
        options: { responsive: true, indexAxis: 'y', plugins: { legend: { display: false } } }
    });
}

async function carregarMargensPorCliente() {
    try {
        const data = await (await fetch('/api/custos.php?acao=margem_por_cliente')).json();
        if (!data.sucesso) throw new Error(data.erro);
        if (!data.margens.length) { document.getElementById('tabela-clientes').innerHTML = '<tr><td colspan="6" class="dash-empty">Sem clientes</td></tr>'; return; }
        document.getElementById('melhor-cliente').textContent = data.margens[0].margem_percentual + '%';
        document.getElementById('pior-cliente').textContent = data.margens[data.margens.length - 1].margem_percentual + '%';
        document.getElementById('margem-media').textContent = Math.round(data.margens.reduce((a, b) => a + b.margem_percentual, 0) / data.margens.length) + '%';
        let html = '';
        data.margens.forEach(cli => {
            const cs = cli.margem_status === 'excelente' ? 'green' : cli.margem_status === 'boa' ? 'blue' : cli.margem_status === 'normal' ? 'amber' : 'red';
            html += `<tr>
                <td style="font-weight:600">${esc(cli.cliente)}</td>
                <td style="text-align:center">${esc(cli.total_os)}</td>
                <td style="text-align:right">${money(cli.valor_venda_total)}</td>
                <td style="text-align:right">${money(cli.custo_medio)}</td>
                <td style="text-align:center;font-weight:700"><span class="dash-chip ${cs}">${cli.margem_percentual}%</span></td>
                <td style="text-align:center"><span class="dash-chip ${cs}">${esc(String(cli.margem_status).toUpperCase())}</span></td>
            </tr>`;
        });
        document.getElementById('tabela-clientes').innerHTML = html;
    } catch (err) { document.getElementById('tabela-clientes').innerHTML = `<tr><td colspan="6" class="dash-empty err">Erro: ${esc(err.message)}</td></tr>`; }
}

async function abrirDetalhesModal(osId) {
    try {
        const data = await (await fetch(`/api/custos.php?acao=calcular_custo_os&os_id=${osId}`)).json();
        if (!data.sucesso) throw new Error(data.erro);
        const r = data.resumo;
        document.getElementById('modal-titulo').textContent = `Custos — ${data.os_numero}`;
        document.getElementById('modal-content').innerHTML = `
            <div class="dash-kpis" style="margin-bottom:16px">
                <div class="dash-kpi blue"><div class="dash-kpi-label">Valor de venda</div><div class="dash-kpi-val" style="font-size:22px">${money(r.valor_venda)}</div></div>
                <div class="dash-kpi red"><div class="dash-kpi-label">Custo total</div><div class="dash-kpi-val" style="font-size:22px">${money(r.custo_total)}</div></div>
                <div class="dash-kpi green"><div class="dash-kpi-label">Lucro bruto</div><div class="dash-kpi-val" style="font-size:22px">${money(r.lucro_bruto)}</div></div>
                <div class="dash-kpi purple"><div class="dash-kpi-label">Margem</div><div class="dash-kpi-val" style="font-size:22px">${r.margem_percentual}%</div></div>
            </div>
            <div class="dash-section" style="margin:0"><div class="dash-section-head"><h2>Composição de custos</h2></div>
                <div class="dash-list">
                    <div class="dash-row"><div class="dash-row-top"><span class="dash-row-title">Mão de obra</span><strong>${money(r.custo_mao_obra)}</strong></div></div>
                    <div class="dash-row"><div class="dash-row-top"><span class="dash-row-title">Materiais</span><strong>${money(r.custo_materiais)}</strong></div></div>
                    <div class="dash-row"><div class="dash-row-top"><span class="dash-row-title">Overhead (15%)</span><strong>${money(r.overhead)}</strong></div></div>
                </div>
            </div>`;
        document.getElementById('modal-detalhes').classList.add('open');
    } catch (err) { alert('Erro: ' + err.message); }
}

function carregarDetalhesOS() {
    const osId = document.getElementById('os-select').value;
    if (!osId) { alert('Selecione uma O.S.'); return; }
    abrirDetalhesModal(osId);
}
function fecharModal() { document.getElementById('modal-detalhes').classList.remove('open'); }

window.addEventListener('load', carregarResumo);
</script>
<?php include '../../includes/footer_vendedor.php'; ?>
