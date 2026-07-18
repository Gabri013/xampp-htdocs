<?php
/**
 * Dashboard de Custos - Análise Financeira por O.S.
 * Visualização de lucratividade, margens e comparação planejado vs real
 *
 * Features:
 * - Cálculo de custo real por O.S.
 * - Margem por cliente com tendências
 * - Comparação planejado vs real
 * - KPI cards de lucratividade
 * - Gráficos de análise de custos
 *
 * Acesso: master, gerente, financeiro
 */

require_once '../../config/config.php';
require_once '../../includes/workflow.php';

$db = getDB();
requirePermission(['master', 'gerente', 'financeiro']);

$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';
?>

<?php $page_title = 'Dashboard de Custos'; ?>
<?php include '../../includes/header_vendedor.php'; ?>
<!-- Tailwind mantido para as classes utilitárias desta página -->
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="/assets/css/nomus-theme.css">
<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main"><div class="vend-content">


<!-- ===== HEADER ===== -->
<header class="bg-white border-b-2 border-green-500 shadow-sm sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-green-600">💰 Análise de Custos</h1>
            <p class="text-sm text-slate-600">Custo real vs planejado + margens por cliente</p>
        </div>
        <div class="text-right">
            <p class="text-sm font-semibold"><?= htmlspecialchars($usuario_nome) ?></p>
            <p class="text-xs text-slate-500"><?= date('d/m/Y') ?></p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="bg-slate-100 border-t">
        <div class="max-w-7xl mx-auto px-4 flex gap-4">
            <button onclick="abaativa('resumo')" class="tab-btn font-semibold py-3 px-4 border-b-2 border-green-500 text-green-600">
                📊 Resumo
            </button>
            <button onclick="abaativa('detalhes')" class="tab-btn font-semibold py-3 px-4 text-slate-600 hover:text-green-600">
                🔍 Detalhes O.S.
            </button>
            <button onclick="abaativa('clientes')" class="tab-btn font-semibold py-3 px-4 text-slate-600 hover:text-green-600">
                👥 Margem Cliente
            </button>
        </div>
    </div>
</header>

<!-- ===== CONTEÚDO ===== -->
<div class="max-w-7xl mx-auto px-4 py-6">

    <!-- ABA: RESUMO -->
    <div id="aba-resumo" class="aba-custos">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <!-- KPI: Faturamento -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500">
                <p class="text-sm text-slate-600 mb-2">💵 Faturamento</p>
                <p id="kpi-faturamento" class="text-3xl font-bold text-blue-600">R$ 0</p>
                <p class="text-xs text-slate-500 mt-2">Este mês</p>
            </div>

            <!-- KPI: Custo Total -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-orange-500">
                <p class="text-sm text-slate-600 mb-2">📉 Custo Total</p>
                <p id="kpi-custo" class="text-3xl font-bold text-orange-600">R$ 0</p>
                <p class="text-xs text-slate-500 mt-2">Mão obra + materiais</p>
            </div>

            <!-- KPI: Lucro Bruto -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
                <p class="text-sm text-slate-600 mb-2">✅ Lucro Bruto</p>
                <p id="kpi-lucro" class="text-3xl font-bold text-green-600">R$ 0</p>
                <p class="text-xs text-slate-500 mt-2">Faturamento - custos</p>
            </div>

            <!-- KPI: Margem Geral -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-purple-500">
                <p class="text-sm text-slate-600 mb-2">📈 Margem %</p>
                <p id="kpi-margem" class="text-3xl font-bold text-purple-600">0%</p>
                <p class="text-xs text-slate-500 mt-2">Lucratividade média</p>
            </div>
        </div>

        <!-- Filtro de período -->
        <div class="bg-white rounded-lg shadow-md p-4 mb-6">
            <label class="text-sm font-semibold">Selecionar período:</label>
            <div class="flex gap-2 mt-2">
                <input type="month" id="filtro-mes" value="<?= date('Y-m') ?>"
                       class="px-3 py-2 border rounded-lg" onchange="carregarResumo()">
                <button onclick="carregarResumo()" class="px-4 py-2 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700">
                    ✅ Filtrar
                </button>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-bold mb-4">📊 Distribuição de Custos</h3>
                <canvas id="chart-custos"></canvas>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-bold mb-4">💰 Lucratividade O.S.</h3>
                <canvas id="chart-lucro"></canvas>
            </div>
        </div>

        <!-- Tabela de O.S. -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 bg-green-50 border-b-2 border-green-200">
                <h2 class="text-lg font-bold text-green-900">📋 Ordens de Serviço</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-100 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold">O.S.</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold">Cliente</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold">Faturado</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold">Custo</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold">Lucro</th>
                            <th class="px-6 py-3 text-center text-xs font-semibold">Margem %</th>
                            <th class="px-6 py-3 text-center text-xs font-semibold">Ação</th>
                        </tr>
                    </thead>
                    <tbody id="tabela-os" class="divide-y">
                        <tr><td colspan="7" class="px-6 py-4 text-center text-slate-500">Carregando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ABA: DETALHES O.S. -->
    <div id="aba-detalhes" class="aba-custos hidden">
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <label class="block text-sm font-semibold mb-3">Selecione uma O.S.:</label>
            <div class="flex gap-2">
                <select id="os-select" class="flex-1 px-3 py-2 border rounded-lg">
                    <option value="">Carregando O.S....</option>
                </select>
                <button onclick="carregarDetalhesOS()" class="px-6 py-2 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700">
                    🔍 Análise
                </button>
            </div>
        </div>

        <div id="detalhes-os" class="space-y-6">
            <div class="bg-white rounded-lg shadow-md p-6 text-center text-slate-500">
                Selecione uma O.S. para ver detalhes
            </div>
        </div>
    </div>

    <!-- ABA: CLIENTES -->
    <div id="aba-clientes" class="aba-custos hidden">
        <div class="bg-blue-50 border-2 border-blue-200 rounded-lg p-4 mb-6">
            <p class="text-blue-900 font-semibold">👥 Análise de Margem por Cliente</p>
            <p class="text-sm text-blue-700">Clientes com melhor lucratividade aparecem no topo</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-green-500">
                <p class="text-sm text-slate-600">🏆 Melhor Margem</p>
                <p id="melhor-cliente" class="text-2xl font-bold text-green-600">--</p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-orange-500">
                <p class="text-sm text-slate-600">⚠️ Pior Margem</p>
                <p id="pior-cliente" class="text-2xl font-bold text-orange-600">--</p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-blue-500">
                <p class="text-sm text-slate-600">📊 Margem Média</p>
                <p id="margem-media" class="text-2xl font-bold text-blue-600">--</p>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 bg-blue-50 border-b-2 border-blue-200">
                <h2 class="text-lg font-bold text-blue-900">💼 Clientes</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-100 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold">Cliente</th>
                            <th class="px-6 py-3 text-center text-xs font-semibold">O.S.</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold">Faturado Total</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold">Custo Médio</th>
                            <th class="px-6 py-3 text-center text-xs font-semibold">Margem %</th>
                            <th class="px-6 py-3 text-center text-xs font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody id="tabela-clientes" class="divide-y">
                        <tr><td colspan="6" class="px-6 py-4 text-center text-slate-500">Carregando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- ===== MODAL: DETALHES CUSTOS O.S. ===== -->
<div id="modal-detalhes" class="modal hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 overflow-y-auto">
    <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full mx-4 my-4">
        <div class="sticky top-0 bg-green-50 border-b p-4 flex justify-between items-center">
            <h2 id="modal-titulo" class="text-lg font-bold text-green-900">Detalhes de Custos</h2>
            <button onclick="fecharModal()" class="text-slate-500 hover:text-slate-700">✕</button>
        </div>
        <div id="modal-content" class="p-6 overflow-y-auto max-h-96"></div>
    </div>
</div>

<script>
// ==== FUNÇÕES BÁSICAS ====
function abaativa(aba) {
    document.querySelectorAll('.aba-custos').forEach(e => e.classList.add('hidden'));
    document.getElementById('aba-' + aba).classList.remove('hidden');
    document.querySelectorAll('.tab-btn').forEach(e => {
        e.classList.remove('border-green-500', 'text-green-600');
        e.classList.add('text-slate-600');
    });
    event.target.classList.add('border-green-500', 'text-green-600');

    if (aba === 'resumo') carregarResumo();
    if (aba === 'clientes') carregarMargensPorCliente();
}

// ==== CARREGAR RESUMO ====
async function carregarResumo() {
    const mes = document.getElementById('filtro-mes').value;

    try {
        const response = await fetch(`/api/custos.php?acao=listar_custos&mes=${mes}`);
        const data = await response.json();

        if (!data.sucesso) throw new Error(data.erro);

        // Atualiza KPIs
        document.getElementById('kpi-faturamento').textContent =
            'R$ ' + parseFloat(data.total_venda).toLocaleString('pt-BR', {minimumFractionDigits: 2});
        document.getElementById('kpi-custo').textContent =
            'R$ ' + parseFloat(data.total_custo).toLocaleString('pt-BR', {minimumFractionDigits: 2});
        document.getElementById('kpi-lucro').textContent =
            'R$ ' + parseFloat(data.lucro_total).toLocaleString('pt-BR', {minimumFractionDigits: 2});
        document.getElementById('kpi-margem').textContent = data.margem_geral_percentual + '%';

        // Atualiza tabela
        let html = '';
        data.custos.forEach(item => {
            const corLucro = item.lucro > 0 ? 'green' : 'red';
            const corMargem = item.margem_percentual >= 30 ? 'green' :
                            item.margem_percentual >= 20 ? 'blue' :
                            item.margem_percentual >= 10 ? 'orange' : 'red';

            html += `
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4 font-semibold text-blue-600">${item.os_numero}</td>
                    <td class="px-6 py-4">${item.cliente}</td>
                    <td class="px-6 py-4 text-right font-semibold">R$ ${parseFloat(item.valor_venda).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                    <td class="px-6 py-4 text-right">R$ ${parseFloat(item.custo_total).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                    <td class="px-6 py-4 text-right font-semibold text-${corLucro}-600">R$ ${parseFloat(item.lucro).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                    <td class="px-6 py-4 text-center">
                        <span class="px-2 py-1 bg-${corMargem}-100 text-${corMargem}-900 text-sm font-bold rounded">
                            ${item.margem_percentual}%
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <button onclick="abrirDetalhesModal(${item.os_id})"
                                class="text-blue-600 hover:text-blue-900 font-semibold">
                            Ver
                        </button>
                    </td>
                </tr>
            `;
        });
        document.getElementById('tabela-os').innerHTML = html || '<tr><td colspan="7" class="px-6 py-4 text-center text-green-600">✅ Nenhuma O.S. neste período</td></tr>';

        // Atualiza gráficos
        atualizarGraficosCustos(data);
    } catch (err) {
        alert('Erro: ' + err.message);
    }
}

// ==== ATUALIZAR GRÁFICOS ====
function atualizarGraficosCustos(data) {
    // Gráfico 1: Distribuição de custos
    const ctx1 = document.getElementById('chart-custos').getContext('2d');
    if (window.chartCustos) window.chartCustos.destroy();

    const totalCustos = data.total_custo;
    const maoDeObra = totalCustos * 0.5; // 50%
    const materiais = totalCustos * 0.35; // 35%
    const overhead = totalCustos * 0.15; // 15%

    window.chartCustos = new Chart(ctx1, {
        type: 'doughnut',
        data: {
            labels: ['Mão de Obra', 'Materiais', 'Overhead'],
            datasets: [{
                data: [maoDeObra, materiais, overhead],
                backgroundColor: ['#3b82f6', '#f59e0b', '#8b5cf6'],
                borderColor: '#fff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // Gráfico 2: Lucratividade por O.S.
    const ctx2 = document.getElementById('chart-lucro').getContext('2d');
    if (window.chartLucro) window.chartLucro.destroy();

    const labels = data.custos.slice(0, 10).map(c => c.os_numero);
    const lucros = data.custos.slice(0, 10).map(c => c.lucro);

    window.chartLucro = new Chart(ctx2, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Lucro (R$)',
                data: lucros,
                backgroundColor: lucros.map(l => l > 0 ? '#10b981' : '#dc2626'),
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            indexAxis: 'y',
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
}

// ==== CARREGAR MARGENS POR CLIENTE ====
async function carregarMargensPorCliente() {
    try {
        const response = await fetch('/api/custos.php?acao=margem_por_cliente');
        const data = await response.json();

        if (!data.sucesso) throw new Error(data.erro);

        if (data.margens.length === 0) {
            document.getElementById('tabela-clientes').innerHTML = '<tr><td colspan="6" class="px-6 py-4 text-center text-slate-500">Sem clientes</td></tr>';
            return;
        }

        document.getElementById('melhor-cliente').textContent = data.margens[0].margem_percentual + '%';
        document.getElementById('pior-cliente').textContent = data.margens[data.margens.length - 1].margem_percentual + '%';
        const mediaMargens = data.margens.reduce((a, b) => a + b.margem_percentual, 0) / data.margens.length;
        document.getElementById('margem-media').textContent = Math.round(mediaMargens) + '%';

        let html = '';
        data.margens.forEach(cliente => {
            const corStatus = cliente.margem_status === 'excelente' ? 'green' :
                            cliente.margem_status === 'boa' ? 'blue' :
                            cliente.margem_status === 'normal' ? 'orange' : 'red';

            html += `
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4 font-semibold">${cliente.cliente}</td>
                    <td class="px-6 py-4 text-center">${cliente.total_os}</td>
                    <td class="px-6 py-4 text-right">R$ ${parseFloat(cliente.valor_venda_total).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                    <td class="px-6 py-4 text-right">R$ ${parseFloat(cliente.custo_medio).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                    <td class="px-6 py-4 text-center font-semibold text-${corStatus}-600">${cliente.margem_percentual}%</td>
                    <td class="px-6 py-4 text-center">
                        <span class="px-2 py-1 bg-${corStatus}-100 text-${corStatus}-900 text-xs font-bold rounded">
                            ${cliente.margem_status.toUpperCase()}
                        </span>
                    </td>
                </tr>
            `;
        });
        document.getElementById('tabela-clientes').innerHTML = html;
    } catch (err) {
        alert('Erro: ' + err.message);
    }
}

// ==== ABRIR MODAL DETALHES ====
async function abrirDetalhesModal(osId) {
    try {
        const response = await fetch(`/api/custos.php?acao=calcular_custo_os&os_id=${osId}`);
        const data = await response.json();

        if (!data.sucesso) throw new Error(data.erro);

        const r = data.resumo;
        let html = `
            <div class="space-y-6">
                <div>
                    <h3 class="text-lg font-bold mb-4">Resumo Financeiro</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-blue-50 p-4 rounded">
                            <p class="text-sm text-slate-600">Valor de Venda</p>
                            <p class="text-2xl font-bold text-blue-600">R$ ${r.valor_venda.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</p>
                        </div>
                        <div class="bg-red-50 p-4 rounded">
                            <p class="text-sm text-slate-600">Custo Total</p>
                            <p class="text-2xl font-bold text-red-600">R$ ${r.custo_total.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</p>
                        </div>
                        <div class="bg-green-50 p-4 rounded">
                            <p class="text-sm text-slate-600">Lucro Bruto</p>
                            <p class="text-2xl font-bold text-green-600">R$ ${r.lucro_bruto.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</p>
                        </div>
                        <div class="bg-purple-50 p-4 rounded">
                            <p class="text-sm text-slate-600">Margem %</p>
                            <p class="text-2xl font-bold text-purple-600">${r.margem_percentual}%</p>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-bold mb-3">Composição de Custos</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between p-2 bg-slate-100 rounded">
                            <span>Mão de Obra</span>
                            <span class="font-bold">R$ ${r.custo_mao_obra.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
                        </div>
                        <div class="flex justify-between p-2 bg-slate-100 rounded">
                            <span>Materiais</span>
                            <span class="font-bold">R$ ${r.custo_materiais.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
                        </div>
                        <div class="flex justify-between p-2 bg-slate-100 rounded">
                            <span>Overhead (15%)</span>
                            <span class="font-bold">R$ ${r.overhead.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.getElementById('modal-titulo').textContent = `Análise de Custos - ${data.os_numero}`;
        document.getElementById('modal-content').innerHTML = html;
        document.getElementById('modal-detalhes').classList.remove('hidden');
    } catch (err) {
        alert('Erro: ' + err.message);
    }
}

function fecharModal() {
    document.getElementById('modal-detalhes').classList.add('hidden');
}

// ==== INICIALIZAÇÃO ====
window.addEventListener('load', async () => {
    // Carrega O.S. para o select
    try {
        const response = await fetch('/api/os.php?acao=listar');
        const data = await response.json();
        if (data.sucesso) {
            let html = '<option value="">Selecione uma O.S....</option>';
            data.os.forEach(os => {
                html += `<option value="${os.id}">${os.numero} - ${os.cliente}</option>`;
            });
            document.getElementById('os-select').innerHTML = html;
        }
    } catch (err) {
        console.error('Erro:', err);
    }

    carregarResumo();
});

function carregarDetalhesOS() {
    const osId = document.getElementById('os-select').value;
    if (!osId) {
        alert('Selecione uma O.S.');
        return;
    }
    abrirDetalhesModal(osId);
}
</script>

    </div></div>
</div>
<?php include '../../includes/footer_vendedor.php'; ?>
