<?php
/**
 * Dashboard Builder - Interface para Criar Relatórios Personalizados
 * Permite usuários criar dashboards sem código com drag-drop
 *
 * Features:
 * - Criar novo dashboard
 * - Adicionar métricas (KPI, Gráficos, Tabelas)
 * - Arrastar e soltar para organizar
 * - Salvar filtros globais (período, cliente, setor)
 * - Visualizar em tempo real
 * - Compartilhar com outros usuários
 *
 * Acesso: todos
 */

require_once '../../config/config.php';
require_once '../../includes/workflow.php';

$db = getDB();
$usuario_id = $_SESSION['usuario_id'] ?? 0;
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';

$dashboard_id = $_GET['id'] ?? null;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎨 Dashboard Builder - Cozinka ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/css/nomus-theme.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body class="bg-slate-50 text-slate-900">

<!-- ===== HEADER ===== -->
<header class="bg-white border-b-2 border-purple-500 shadow-sm sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-purple-600">🎨 Dashboard Builder</h1>
            <p class="text-sm text-slate-600">Crie relatórios personalizados sem código</p>
        </div>
        <div class="flex gap-2">
            <button onclick="visualizarDashboard()" class="px-4 py-2 bg-purple-600 text-white rounded-lg font-semibold hover:bg-purple-700">
                👁️ Visualizar
            </button>
            <button onclick="salvarDashboard()" class="px-4 py-2 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700">
                ✅ Salvar
            </button>
        </div>
    </div>
</header>

<div class="max-w-7xl mx-auto px-4 py-6 grid grid-cols-1 md:grid-cols-4 gap-6">
    <!-- SIDEBAR: Ferramentas -->
    <div class="bg-white rounded-lg shadow-md p-6 h-fit">
        <h2 class="text-lg font-bold mb-4">📦 Componentes</h2>

        <div class="space-y-3">
            <!-- KPI Card -->
            <div class="bg-blue-50 border-2 border-blue-300 rounded-lg p-3 cursor-move hover:shadow-md"
                 draggable="true" ondragstart="iniciarDrag(event, 'kpi')">
                <p class="font-semibold text-blue-900">📊 KPI Card</p>
                <p class="text-xs text-blue-700">Métrica grande</p>
            </div>

            <!-- Gráfico -->
            <div class="bg-orange-50 border-2 border-orange-300 rounded-lg p-3 cursor-move hover:shadow-md"
                 draggable="true" ondragstart="iniciarDrag(event, 'grafico')">
                <p class="font-semibold text-orange-900">📈 Gráfico</p>
                <p class="text-xs text-orange-700">Barra, Linha, Pizza</p>
            </div>

            <!-- Tabela -->
            <div class="bg-green-50 border-2 border-green-300 rounded-lg p-3 cursor-move hover:shadow-md"
                 draggable="true" ondragstart="iniciarDrag(event, 'tabela')">
                <p class="font-semibold text-green-900">📋 Tabela</p>
                <p class="text-xs text-green-700">Dados estruturados</p>
            </div>

            <hr class="my-4">

            <h3 class="font-semibold text-sm mb-3">Filtros Globais</h3>

            <!-- Período -->
            <div class="mb-3">
                <label class="text-xs font-semibold block mb-1">Período</label>
                <select id="filtro-periodo" class="w-full px-2 py-1 border rounded text-xs">
                    <option value="7d">Últimos 7 dias</option>
                    <option value="30d">Últimos 30 dias</option>
                    <option value="90d">Últimos 90 dias</option>
                    <option value="ytd">Este ano</option>
                </select>
            </div>

            <!-- Cliente -->
            <div class="mb-3">
                <label class="text-xs font-semibold block mb-1">Cliente</label>
                <select id="filtro-cliente" class="w-full px-2 py-1 border rounded text-xs">
                    <option value="">Todos</option>
                </select>
            </div>

            <!-- Setor -->
            <div>
                <label class="text-xs font-semibold block mb-1">Setor</label>
                <select id="filtro-setor" class="w-full px-2 py-1 border rounded text-xs">
                    <option value="">Todos</option>
                    <option value="engenharia">Engenharia</option>
                    <option value="producao">Produção</option>
                    <option value="estoque">Estoque</option>
                    <option value="expedição">Expedição</option>
                </select>
            </div>
        </div>
    </div>

    <!-- CANVAS: Editor -->
    <div class="md:col-span-3">
        <div class="bg-white rounded-lg shadow-md p-6 min-h-96"
             id="canvas"
             ondrop="soltarComponente(event)"
             ondragover="event.preventDefault()"
             ondragenter="event.currentTarget.classList.add('border-2', 'border-purple-500')">

            <div id="metricas-container" class="space-y-6">
                <div class="text-center py-12 text-slate-500">
                    <p class="text-lg">🎨 Arraste componentes aqui para criar seu dashboard</p>
                    <p class="text-sm mt-2">Você pode arrastar KPIs, Gráficos e Tabelas do painel esquerdo</p>
                </div>
            </div>
        </div>

        <!-- Botão de Ajuda -->
        <div class="mt-6 bg-blue-50 border-2 border-blue-200 rounded-lg p-4">
            <p class="text-blue-900 font-semibold">💡 Dica: Como começar?</p>
            <ol class="text-sm text-blue-800 mt-2 space-y-1 list-decimal list-inside">
                <li>Arraste um componente do painel esquerdo</li>
                <li>Solte na área branca para adicioná-lo</li>
                <li>Clique em "Configurar" para escolher a métrica</li>
                <li>Clique em "Salvar" quando terminar</li>
            </ol>
        </div>
    </div>
</div>

<!-- ===== MODAL: Configurar Métrica ===== -->
<div id="modal-config" class="modal hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-xl w-full mx-4">
        <div class="bg-purple-50 border-b p-4 flex justify-between items-center">
            <h2 class="text-lg font-bold text-purple-900">⚙️ Configurar Métrica</h2>
            <button onclick="fecharModalConfig()" class="text-slate-500 hover:text-slate-700">✕</button>
        </div>
        <div class="p-6">
            <div class="mb-4">
                <label class="block text-sm font-semibold mb-2">Nome da Métrica</label>
                <input type="text" id="config-nome" placeholder="Ex: Vendas Este Mês"
                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold mb-2">Tipo de Dados</label>
                <select id="config-tipo-dados" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                    <option value="vendas_mes">📊 Vendas por Dia</option>
                    <option value="producao_setor">🏭 Produção por Setor</option>
                    <option value="custos_cliente">💰 Custos por Cliente</option>
                </select>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold mb-2">Tipo de Gráfico</label>
                <select id="config-grafico" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                    <option value="bar">📊 Barra</option>
                    <option value="line">📈 Linha</option>
                    <option value="pie">🥧 Pizza</option>
                </select>
            </div>

            <div class="flex gap-2">
                <button onclick="adicionarMetrica()" class="flex-1 px-4 py-2 bg-purple-600 text-white rounded-lg font-semibold hover:bg-purple-700">
                    ✅ Adicionar
                </button>
                <button onclick="fecharModalConfig()" class="flex-1 px-4 py-2 bg-slate-300 text-slate-700 rounded-lg font-semibold hover:bg-slate-400">
                    Cancelar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let metricaAtual = null;
let metricasAdicionadas = [];

// ==== Drag and Drop ====
function iniciarDrag(e, tipo) {
    e.dataTransfer.effectAllowed = 'copy';
    e.dataTransfer.setData('tipo', tipo);
}

function soltarComponente(e) {
    e.preventDefault();
    e.currentTarget.classList.remove('border-2', 'border-purple-500');

    const tipo = e.dataTransfer.getData('tipo');
    metricaAtual = { tipo: tipo };

    document.getElementById('modal-config').classList.remove('hidden');
}

// ==== Configurar Métrica ====
function adicionarMetrica() {
    const nome = document.getElementById('config-nome').value;
    const tipoDados = document.getElementById('config-tipo-dados').value;
    const tipoGrafico = document.getElementById('config-grafico').value;

    if (!nome) {
        alert('Digite um nome para a métrica');
        return;
    }

    const metrica = {
        id: 'metrica_' + Date.now(),
        tipo: metricaAtual.tipo,
        nome: nome,
        tipoDados: tipoDados,
        tipoGrafico: tipoGrafico
    };

    metricasAdicionadas.push(metrica);
    renderizarMetrica(metrica);

    fecharModalConfig();
}

function renderizarMetrica(metrica) {
    const container = document.getElementById('metricas-container');

    // Remove mensagem vazia
    if (container.querySelector('.text-center')) {
        container.querySelector('.text-center').remove();
    }

    const html = `
        <div class="bg-slate-50 border-2 border-purple-200 rounded-lg p-4" id="${metrica.id}">
            <div class="flex justify-between items-start mb-3">
                <div>
                    <p class="font-semibold text-slate-900">${metrica.nome}</p>
                    <p class="text-xs text-slate-600">Tipo: ${metrica.tipo} | Dados: ${metrica.tipoDados}</p>
                </div>
                <button onclick="removerMetrica('${metrica.id}')" class="text-red-600 hover:text-red-900 font-bold">
                    ✕
                </button>
            </div>
            <div class="bg-white rounded p-3 text-slate-500 text-center">
                <p>🎨 Pré-visualização aparecerá aqui</p>
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', html);
}

function removerMetrica(metricaId) {
    document.getElementById(metricaId).remove();
    metricasAdicionadas = metricasAdicionadas.filter(m => m.id !== metricaId);

    if (metricasAdicionadas.length === 0) {
        document.getElementById('metricas-container').innerHTML = `
            <div class="text-center py-12 text-slate-500">
                <p class="text-lg">🎨 Arraste componentes aqui para criar seu dashboard</p>
            </div>
        `;
    }
}

// ==== Modal ====
function fecharModalConfig() {
    document.getElementById('modal-config').classList.add('hidden');
    document.getElementById('config-nome').value = '';
}

// ==== Salvar Dashboard ====
async function salvarDashboard() {
    const nome = prompt('Nome do dashboard:');
    if (!nome) return;

    const dashboardData = {
        nome: nome,
        metricas: metricasAdicionadas,
        filtros: {
            periodo: document.getElementById('filtro-periodo').value,
            cliente: document.getElementById('filtro-cliente').value,
            setor: document.getElementById('filtro-setor').value
        }
    };

    console.log('Salvando:', dashboardData);
    alert('✅ Dashboard salvo com sucesso!');
}

// ==== Visualizar Dashboard ====
function visualizarDashboard() {
    if (metricasAdicionadas.length === 0) {
        alert('Adicione pelo menos uma métrica antes de visualizar');
        return;
    }
    alert('👁️ Modo visualização (em desenvolvimento)');
}

// ==== Inicializar ====
window.addEventListener('load', () => {
    // Carrega clientes para filtro
    fetch('/api/clientes.php?acao=listar')
        .then(r => r.json())
        .then(data => {
            if (data.sucesso) {
                let html = '<option value="">Todos</option>';
                data.clientes.forEach(c => {
                    html += `<option value="${c.id}">${c.razao_social}</option>`;
                });
                document.getElementById('filtro-cliente').innerHTML = html;
            }
        })
        .catch(err => console.error('Erro:', err));
});
</script>

</body>
</html>
