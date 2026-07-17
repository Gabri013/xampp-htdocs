<?php
/**
 * Dashboard MRP - Planejamento Inteligente de Produção
 * Interface visual para análise de demanda, sugestões de produção e alertas
 *
 * Features:
 * - Análise em tempo real de demanda vs estoque
 * - Sugestões automáticas de ordens de produção
 * - Previsão de materiais por produto
 * - Otimização de cronograma
 * - Alertas críticos com ações rápidas
 *
 * Acesso: master, gerente, producao
 */

require_once '../../config/config.php';
require_once '../../includes/workflow.php';

$db = getDB();
requirePermission(['master', 'gerente', 'producao']);

$usuario_setor = $_SESSION['usuario_setor'] ?? 'producao';
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📊 Dashboard MRP - Cozinka ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/css/nomus-theme.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body class="bg-slate-50 text-slate-900">

<!-- ===== HEADER ===== -->
<header class="bg-white border-b-2 border-blue-500 shadow-sm sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-blue-600">📊 MRP Dashboard</h1>
            <p class="text-sm text-slate-600">Planejamento inteligente de produção</p>
        </div>
        <div class="text-right">
            <p class="text-sm font-semibold"><?= htmlspecialchars($usuario_nome) ?></p>
            <p class="text-xs text-slate-500"><?= date('d/m/Y H:i') ?></p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="bg-slate-100 border-t">
        <div class="max-w-7xl mx-auto px-4 flex gap-4">
            <button onclick="abaativa('demanda')" class="tab-btn font-semibold py-3 px-4 border-b-2 border-blue-500 text-blue-600">
                🎯 Demanda
            </button>
            <button onclick="abaativa('sugestoes')" class="tab-btn font-semibold py-3 px-4 text-slate-600 hover:text-blue-600 hover:border-blue-300">
                💡 Sugestões
            </button>
            <button onclick="abaativa('materiais')" class="tab-btn font-semibold py-3 px-4 text-slate-600 hover:text-blue-600 hover:border-blue-300">
                📦 Materiais
            </button>
            <button onclick="abaativa('cronograma')" class="tab-btn font-semibold py-3 px-4 text-slate-600 hover:text-blue-600 hover:border-blue-300">
                ⏱️ Cronograma
            </button>
            <button onclick="abaativa('alertas')" class="tab-btn font-semibold py-3 px-4 text-slate-600 hover:text-blue-600 hover:border-blue-300">
                🚨 Alertas
            </button>
        </div>
    </div>
</header>

<!-- ===== CONTEÚDO ===== -->
<div class="max-w-7xl mx-auto px-4 py-6">

    <!-- ABA: DEMANDA -->
    <div id="aba-demanda" class="aba-mrp">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <!-- KPI Card: Total Faltando -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-red-500">
                <p class="text-sm text-slate-600 mb-2">📉 Produtos Faltando</p>
                <p id="kpi-faltando" class="text-4xl font-bold text-red-600">0</p>
                <p class="text-xs text-slate-500 mt-2">Ação necessária</p>
            </div>

            <!-- KPI Card: Urgência -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-orange-500">
                <p class="text-sm text-slate-600 mb-2">⚡ Críticos</p>
                <p id="kpi-criticos" class="text-4xl font-bold text-orange-600">0</p>
                <p class="text-xs text-slate-500 mt-2">Urgência máxima</p>
            </div>

            <!-- KPI Card: Valor -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
                <p class="text-sm text-slate-600 mb-2">💰 Valor Faltante</p>
                <p id="kpi-valor" class="text-3xl font-bold text-green-600">R$ 0</p>
                <p class="text-xs text-slate-500 mt-2">Análise completa</p>
            </div>
        </div>

        <!-- Lista de Demanda -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 bg-blue-50 border-b-2 border-blue-200">
                <h2 class="text-lg font-bold text-blue-900">📋 Análise de Demanda</h2>
                <p class="text-sm text-blue-700">Vendas confirmadas vs estoque atual</p>
            </div>
            <div id="demanda-list" class="divide-y max-h-96 overflow-y-auto">
                <div class="p-6 text-center text-slate-500">Carregando demanda...</div>
            </div>
        </div>
    </div>

    <!-- ABA: SUGESTÕES -->
    <div id="aba-sugestoes" class="aba-mrp hidden">
        <div class="bg-blue-50 border-2 border-blue-200 rounded-lg p-4 mb-6">
            <p class="text-blue-900 font-semibold">💡 O sistema analisou a demanda e preparou sugestões inteligentes</p>
            <p class="text-sm text-blue-700">Clique em "Criar O.S." para iniciar produção</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="bg-red-50 border-l-4 border-red-500 rounded p-4">
                <p class="text-red-900 font-bold">🔴 Críticas</p>
                <p id="sugestoes-criticas" class="text-3xl font-bold text-red-600">0</p>
            </div>
            <div class="bg-orange-50 border-l-4 border-orange-500 rounded p-4">
                <p class="text-orange-900 font-bold">🟠 Altas</p>
                <p id="sugestoes-altas" class="text-3xl font-bold text-orange-600">0</p>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 bg-green-50 border-b-2 border-green-200">
                <h2 class="text-lg font-bold text-green-900">🎯 Sugestões de Produção</h2>
            </div>
            <div id="sugestoes-list" class="divide-y max-h-96 overflow-y-auto">
                <div class="p-6 text-center text-slate-500">Carregando sugestões...</div>
            </div>
        </div>
    </div>

    <!-- ABA: MATERIAIS -->
    <div id="aba-materiais" class="aba-mrp hidden">
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <label class="block text-sm font-semibold mb-3">Selecione um produto para ver BOM necessário:</label>
            <div class="flex gap-2 items-end">
                <div class="flex-1">
                    <label class="text-xs text-slate-600">Produto</label>
                    <select id="produto-select" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Carregando produtos...</option>
                    </select>
                </div>
                <div class="w-24">
                    <label class="text-xs text-slate-600">Qtd.</label>
                    <input type="number" id="quantidade-input" value="1" min="1" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <button onclick="preverMateriais()" class="bg-blue-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-blue-700">
                    ✅ Prever
                </button>
            </div>
        </div>

        <div id="materiais-preview" class="bg-white rounded-lg shadow-md overflow-hidden hidden">
            <div class="px-6 py-4 bg-purple-50 border-b-2 border-purple-200">
                <h2 class="text-lg font-bold text-purple-900">📦 Materiais Necessários</h2>
            </div>
            <div id="materiais-list" class="divide-y max-h-96 overflow-y-auto">
            </div>
        </div>
    </div>

    <!-- ABA: CRONOGRAMA -->
    <div id="aba-cronograma" class="aba-mrp hidden">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 bg-indigo-50 border-b-2 border-indigo-200">
                <h2 class="text-lg font-bold text-indigo-900">⏱️ Otimização de Cronograma</h2>
                <p class="text-sm text-indigo-700">Ordem recomendada de produção</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 p-6 border-b">
                <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded">
                    <p class="text-red-900 font-semibold text-sm">🚀 ACELERAR</p>
                    <p id="crono-acelerar" class="text-3xl font-bold text-red-600">0</p>
                </div>
                <div class="bg-orange-50 border-l-4 border-orange-500 p-4 rounded">
                    <p class="text-orange-900 font-semibold text-sm">🎯 FOCAR</p>
                    <p id="crono-focar" class="text-3xl font-bold text-orange-600">0</p>
                </div>
                <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded">
                    <p class="text-green-900 font-semibold text-sm">✅ EM TEMPO</p>
                    <p id="crono-tempo" class="text-3xl font-bold text-green-600">0</p>
                </div>
            </div>

            <div id="cronograma-list" class="divide-y max-h-96 overflow-y-auto">
                <div class="p-6 text-center text-slate-500">Carregando cronograma...</div>
            </div>
        </div>
    </div>

    <!-- ABA: ALERTAS -->
    <div id="aba-alertas" class="aba-mrp hidden">
        <div id="alertas-list" class="space-y-3">
            <div class="p-6 text-center text-slate-500">Carregando alertas...</div>
        </div>
    </div>

</div>

<!-- ===== MODAL: PREVISÃO DETALHADA ===== -->
<div id="modal-previsao" class="modal hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-96 overflow-y-auto">
        <div class="sticky top-0 bg-purple-50 border-b p-4 flex justify-between items-center">
            <h2 class="text-lg font-bold text-purple-900">📦 Previsão de Materiais</h2>
            <button onclick="fecharModal('modal-previsao')" class="text-slate-500 hover:text-slate-700">✕</button>
        </div>
        <div id="modal-previsao-content" class="p-6"></div>
    </div>
</div>

<script>
// ==== FUNÇÃO: Abrir abas ====
function abaativa(aba) {
    document.querySelectorAll('.aba-mrp').forEach(e => e.classList.add('hidden'));
    document.getElementById('aba-' + aba).classList.remove('hidden');

    document.querySelectorAll('.tab-btn').forEach(e => {
        e.classList.remove('border-blue-500', 'text-blue-600');
        e.classList.add('text-slate-600');
    });
    event.target.classList.add('border-blue-500', 'text-blue-600');

    // Carrega dados quando abre aba
    if (aba === 'demanda') carregarDemanda();
    if (aba === 'sugestoes') carregarSugestoes();
    if (aba === 'cronograma') carregarCronograma();
    if (aba === 'alertas') carregarAlertas();
}

// ==== FUNÇÃO: Carregar Demanda ====
async function carregarDemanda() {
    try {
        const response = await fetch('/api/mrp.php?acao=analisar_demanda');
        const data = await response.json();

        if (!data.sucesso) throw new Error(data.erro);

        document.getElementById('kpi-faltando').textContent = data.total;
        document.getElementById('kpi-criticos').textContent = data.criticas;

        let html = '';
        data.demanda.forEach(item => {
            const cor = item.status_urgencia === 'crítica' ? 'red' :
                       item.status_urgencia === 'alta' ? 'orange' : 'green';
            const icon = item.status_urgencia === 'crítica' ? '🔴' :
                        item.status_urgencia === 'alta' ? '🟠' : '🟢';

            html += `
                <div class="p-4 hover:bg-slate-50 border-l-4 border-${cor}-500">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <p class="font-semibold text-slate-900">${icon} ${item.produto_nome}</p>
                            <p class="text-xs text-slate-600">${item.cliente} - Venda ${item.venda_numero}</p>
                        </div>
                        <span class="px-2 py-1 bg-${cor}-100 text-${cor}-900 text-xs font-bold rounded">
                            ${item.status_urgencia.toUpperCase()}
                        </span>
                    </div>
                    <div class="grid grid-cols-4 gap-2 text-xs">
                        <div class="bg-blue-50 p-2 rounded">
                            <p class="text-slate-600">Solicitado</p>
                            <p class="font-bold text-blue-600">${item.quantidade_solicitada}</p>
                        </div>
                        <div class="bg-green-50 p-2 rounded">
                            <p class="text-slate-600">Estoque</p>
                            <p class="font-bold text-green-600">${item.estoque_atual}</p>
                        </div>
                        <div class="bg-red-50 p-2 rounded">
                            <p class="text-slate-600">Faltante</p>
                            <p class="font-bold text-red-600">${item.faltante} (${item.percentual_falta}%)</p>
                        </div>
                        <div class="bg-orange-50 p-2 rounded">
                            <p class="text-slate-600">Entrega</p>
                            <p class="font-bold text-orange-600">${item.dias_para_entrega}d</p>
                        </div>
                    </div>
                </div>
            `;
        });

        document.getElementById('demanda-list').innerHTML = html || '<div class="p-6 text-center text-green-600">✅ Nenhum faltante!</div>';
    } catch (err) {
        console.error(err);
        document.getElementById('demanda-list').innerHTML = `<div class="p-6 text-center text-red-600">❌ Erro: ${err.message}</div>`;
    }
}

// ==== FUNÇÃO: Carregar Sugestões ====
async function carregarSugestoes() {
    try {
        const response = await fetch('/api/mrp.php?acao=sugerir_ordens');
        const data = await response.json();

        if (!data.sucesso) throw new Error(data.erro);

        document.getElementById('sugestoes-criticas').textContent = data.criticas;
        document.getElementById('sugestoes-altas').textContent = data.altas;

        let html = '';
        data.sugestoes.forEach(item => {
            const cor = item.prioridade === 'crítica' ? 'red' :
                       item.prioridade === 'alta' ? 'orange' : 'green';
            const icon = item.prioridade === 'crítica' ? '🔴' :
                        item.prioridade === 'alta' ? '🟠' : '🟢';

            html += `
                <div class="p-4 hover:bg-slate-50 border-l-4 border-${cor}-500">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <p class="font-semibold text-slate-900">${icon} ${item.produto_nome}</p>
                            <p class="text-xs text-slate-600">${item.acao_recomendada}</p>
                        </div>
                        <button onclick="criarOSsugestao(${item.produto_id}, ${item.quantidade_sugerida})"
                                class="px-3 py-1 bg-blue-600 text-white text-xs font-bold rounded hover:bg-blue-700">
                            ➕ Criar O.S.
                        </button>
                    </div>
                    <div class="grid grid-cols-4 gap-2 text-xs">
                        <div>
                            <p class="text-slate-600">Estoque</p>
                            <p class="font-bold">${item.estoque_atual}</p>
                        </div>
                        <div>
                            <p class="text-slate-600">Necessário</p>
                            <p class="font-bold text-red-600">${item.necessario}</p>
                        </div>
                        <div>
                            <p class="text-slate-600">Sugerido</p>
                            <p class="font-bold text-green-600">${item.quantidade_sugerida}</p>
                        </div>
                        <div>
                            <p class="text-slate-600">Margem</p>
                            <p class="font-bold">${item.margem_seguranca}%</p>
                        </div>
                    </div>
                </div>
            `;
        });

        document.getElementById('sugestoes-list').innerHTML = html || '<div class="p-6 text-center text-green-600">✅ Sem sugestões!</div>';
    } catch (err) {
        console.error(err);
    }
}

// ==== FUNÇÃO: Prever Materiais ====
async function preverMateriais() {
    const produtoId = document.getElementById('produto-select').value;
    const quantidade = document.getElementById('quantidade-input').value;

    if (!produtoId) {
        alert('Selecione um produto');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('acao', 'prever_materiais');
        formData.append('produto_id', produtoId);
        formData.append('quantidade', quantidade);

        const response = await fetch('/api/mrp.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (!data.sucesso) throw new Error(data.erro);

        let html = '<div class="space-y-2">';
        data.materiais.forEach(m => {
            const status = m.status === 'falta' ? '❌' : '✅';
            html += `
                <div class="flex justify-between items-center p-3 hover:bg-slate-50 border rounded">
                    <div class="flex-1">
                        <p class="font-semibold">${status} ${m.material_nome}</p>
                        <p class="text-xs text-slate-600">Necessário: ${m.quantidade_necessaria} ${m.unidade}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-slate-600">Estoque: ${m.estoque_atual}</p>
                        <p class="text-sm font-bold ${m.faltante > 0 ? 'text-red-600' : 'text-green-600'}">
                            ${m.faltante > 0 ? 'Falta: ' + m.faltante : 'OK'}
                        </p>
                    </div>
                </div>
            `;
        });
        html += '</div>';

        document.getElementById('modal-previsao-content').innerHTML = html;
        document.getElementById('modal-previsao').classList.remove('hidden');
        document.getElementById('materiais-preview').classList.remove('hidden');
    } catch (err) {
        alert('Erro: ' + err.message);
    }
}

// ==== FUNÇÃO: Carregar Cronograma ====
async function carregarCronograma() {
    try {
        const response = await fetch('/api/mrp.php?acao=otimizar_cronograma');
        const data = await response.json();

        document.getElementById('crono-acelerar').textContent = data.acelerar;
        document.getElementById('crono-focar').textContent = data.focar;
        document.getElementById('crono-tempo').textContent = data.total_os - data.acelerar - data.focar;

        let html = '';
        data.cronograma.forEach(item => {
            const cores = {
                'ACELERAR': 'red',
                'FOCAR': 'orange',
                'EM TEMPO': 'green'
            };
            const cor = cores[item.recomendacao];

            html += `
                <div class="p-4 hover:bg-slate-50 border-l-4 border-${cor}-500">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <p class="font-semibold">${item.os_numero} - ${item.cliente}</p>
                            <p class="text-xs text-slate-600">Entrega: ${item.data_prevista}</p>
                        </div>
                        <span class="px-2 py-1 bg-${cor}-100 text-${cor}-900 text-xs font-bold rounded">
                            ${item.recomendacao}
                        </span>
                    </div>
                    <div class="w-full bg-slate-200 rounded-full h-2 mb-2">
                        <div class="bg-blue-600 h-2 rounded-full" style="width: ${item.progresso_percentual}%"></div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-xs">
                        <div>Progresso: ${item.progresso_percentual}%</div>
                        <div>Dias: ${item.dias_faltando}</div>
                        <div>Etapas: ${item.etapas_concluidas}/${item.etapas_totais}</div>
                    </div>
                </div>
            `;
        });

        document.getElementById('cronograma-list').innerHTML = html;
    } catch (err) {
        console.error(err);
    }
}

// ==== FUNÇÃO: Carregar Alertas ====
async function carregarAlertas() {
    try {
        const response = await fetch('/api/mrp.php?acao=alertas');
        const data = await response.json();

        let html = '';
        data.alertas.forEach(alerta => {
            const cores = {
                'crítica': 'red',
                'alta': 'orange',
                'média': 'yellow'
            };
            const cor = cores[alerta.severidade];

            html += `
                <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-${cor}-500 hover:shadow-lg">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <p class="text-lg font-bold text-slate-900">${alerta.icon} ${alerta.titulo}</p>
                            <p class="text-sm text-slate-600 mt-1">${alerta.descricao}</p>
                        </div>
                        <button class="px-4 py-2 bg-${cor}-600 text-white text-sm font-bold rounded hover:bg-${cor}-700">
                            ⚡ Agir
                        </button>
                    </div>
                </div>
            `;
        });

        document.getElementById('alertas-list').innerHTML = html;
    } catch (err) {
        console.error(err);
    }
}

// ==== FUNÇÃO: Fechar Modal ====
function fecharModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

// ==== INICIALIZAÇÃO ====
window.addEventListener('load', async () => {
    // Carrega produtos para o select
    try {
        const response = await fetch('/api/produtos.php?acao=listar');
        const data = await response.json();

        if (data.sucesso) {
            let html = '<option value="">Selecione um produto...</option>';
            data.produtos.forEach(p => {
                html += `<option value="${p.id}">${p.nome}</option>`;
            });
            document.getElementById('produto-select').innerHTML = html;
        }
    } catch (err) {
        console.error('Erro ao carregar produtos:', err);
    }

    // Carrega demanda inicial
    carregarDemanda();
});

// Auto-refresh a cada 2 minutos
setInterval(() => {
    if (!document.getElementById('aba-demanda').classList.contains('hidden')) carregarDemanda();
    if (!document.getElementById('aba-sugestoes').classList.contains('hidden')) carregarSugestoes();
    if (!document.getElementById('aba-cronograma').classList.contains('hidden')) carregarCronograma();
    if (!document.getElementById('aba-alertas').classList.contains('hidden')) carregarAlertas();
}, 120000);
</script>

</body>
</html>
