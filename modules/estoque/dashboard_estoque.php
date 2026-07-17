<?php
/**
 * Dashboard de Estoque - Controle Visual de Saldos
 *
 * Inspirado no Nomus - entrada/saída rápida, alertas de baixo estoque
 * Acesso: master, gerente, producao
 */

require_once '../../config/config.php';
require_once '../../includes/workflow.php';

$page_title = 'Dashboard de Estoque';
$db = getDB();
requirePermission(['master', 'gerente', 'dashboard_producao', 'producao']);

include '../../includes/header_vendedor.php';
?>

<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-page-head">
            <h1 class="vend-page-title">📦 Dashboard de Estoque</h1>
        </div>
        <div class="vend-content">

<style>
    .estoque-container {
        display: grid;
        grid-template-columns: 1fr 2fr;
        gap: 24px;
    }

    /* CARDS DE AÇÕES RÁPIDAS */
    .estoque-acoes {
        display: grid;
        grid-template-columns: 1fr;
        gap: 12px;
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        height: fit-content;
        position: sticky;
        top: 20px;
    }

    .estoque-acao-titulo {
        font-size: 14px;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 12px;
    }

    .estoque-form-grupo {
        margin-bottom: 12px;
    }

    .estoque-form-label {
        font-size: 11px;
        font-weight: 600;
        color: #666;
        display: block;
        margin-bottom: 4px;
    }

    .estoque-form-input {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 13px;
    }

    .estoque-form-input:focus {
        border-color: #3b82f6;
        outline: none;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .estoque-botao-grupo {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }

    .estoque-botao {
        padding: 10px 12px;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .estoque-botao-entrada {
        background: #10b981;
        color: white;
    }

    .estoque-botao-entrada:hover {
        background: #059669;
    }

    .estoque-botao-saida {
        background: #ef4444;
        color: white;
    }

    .estoque-botao-saida:hover {
        background: #dc2626;
    }

    /* LISTA DE PRODUTOS */
    .estoque-lista {
        display: grid;
        gap: 12px;
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .estoque-filtro {
        display: flex;
        gap: 12px;
        margin-bottom: 16px;
    }

    .estoque-filtro-input {
        flex: 1;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 13px;
    }

    .estoque-filtro-botao {
        padding: 8px 16px;
        border: 1px solid #3b82f6;
        background: white;
        color: #3b82f6;
        border-radius: 6px;
        font-weight: 600;
        font-size: 12px;
        cursor: pointer;
    }

    .estoque-filtro-botao.ativo {
        background: #3b82f6;
        color: white;
    }

    /* ITEM DE PRODUTO */
    .estoque-item {
        background: #f9fafb;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 16px;
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr auto;
        gap: 16px;
        align-items: center;
        transition: all 0.2s;
    }

    .estoque-item:hover {
        background: white;
        border-color: #3b82f6;
        box-shadow: 0 2px 8px rgba(59, 130, 246, 0.1);
    }

    .estoque-item.critico {
        background: #fef2f2;
        border-color: #ef4444;
    }

    .estoque-item-nome {
        font-weight: 600;
        color: #1f2937;
    }

    .estoque-item-id {
        font-size: 11px;
        color: #999;
        margin-top: 4px;
    }

    .estoque-item-saldo {
        font-size: 20px;
        font-weight: 700;
        color: #3b82f6;
    }

    .estoque-item-saldo-label {
        font-size: 10px;
        color: #666;
    }

    .estoque-item-status {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .estoque-status-badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 600;
        text-align: center;
    }

    .estoque-status-ok {
        background: #dcfce7;
        color: #065f46;
    }

    .estoque-status-critico {
        background: #fecaca;
        color: #7f1d1d;
    }

    .estoque-status-baixo {
        background: #fed7aa;
        color: #92400e;
    }

    .estoque-item-acoes {
        display: flex;
        gap: 8px;
    }

    .estoque-item-botao {
        width: 36px;
        height: 36px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 18px;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .estoque-item-botao-entrada {
        background: #dcfce7;
        color: #065f46;
    }

    .estoque-item-botao-entrada:hover {
        background: #bbf7d0;
    }

    .estoque-item-botao-saida {
        background: #fecaca;
        color: #7f1d1d;
    }

    .estoque-item-botao-saida:hover {
        background: #fca5a5;
    }

    .estoque-item-botao-info {
        background: #dbeafe;
        color: #0c4a6e;
    }

    .estoque-item-botao-info:hover {
        background: #bfdbfe;
    }

    @media (max-width: 1024px) {
        .estoque-container {
            grid-template-columns: 1fr;
        }

        .estoque-item {
            grid-template-columns: 1fr;
        }

        .estoque-acoes {
            position: static;
        }
    }

    /* MODAL DE DETALHES */
    .estoque-modal {
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

    .estoque-modal.ativo {
        display: flex;
    }

    .estoque-modal-content {
        background: white;
        border-radius: 12px;
        padding: 24px;
        max-width: 600px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
    }

    .estoque-modal-titulo {
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 16px;
    }

    .estoque-modal-historico {
        display: grid;
        gap: 12px;
        margin-top: 16px;
    }

    .estoque-modal-item {
        padding: 12px;
        background: #f3f4f6;
        border-radius: 6px;
        font-size: 12px;
    }

    .estoque-modal-item-tipo {
        font-weight: 600;
        margin-bottom: 4px;
    }
</style>

<div class="vend-card">
    <div class="vend-card-body">
        <div class="estoque-container">
            <!-- PAINEL DE AÇÕES -->
            <div class="estoque-acoes">
                <div class="estoque-acao-titulo">➕ Entrada de Material</div>
                <div class="estoque-form-grupo">
                    <label class="estoque-form-label">Produto</label>
                    <select class="estoque-form-input" id="entrada-produto" onchange="carregarSaldoProduto()">
                        <option value="">Selecione um produto...</option>
                    </select>
                </div>
                <div class="estoque-form-grupo">
                    <label class="estoque-form-label">Quantidade</label>
                    <input type="number" class="estoque-form-input" id="entrada-qtd" placeholder="0,00" step="0.01">
                </div>
                <div class="estoque-form-grupo">
                    <label class="estoque-form-label">Referência (NF, OP, etc)</label>
                    <input type="text" class="estoque-form-input" id="entrada-ref" placeholder="NF-001234">
                </div>
                <div class="estoque-botao-grupo">
                    <button class="estoque-botao estoque-botao-entrada" onclick="registrarMovimento('entrada')">
                        ✅ Entrada
                    </button>
                </div>

                <hr style="margin: 16px 0; border: none; border-top: 1px solid #ddd;">

                <div class="estoque-acao-titulo">➖ Saída/Consumo</div>
                <div class="estoque-form-grupo">
                    <label class="estoque-form-label">Produto</label>
                    <select class="estoque-form-input" id="saida-produto">
                        <option value="">Selecione um produto...</option>
                    </select>
                </div>
                <div class="estoque-form-grupo">
                    <label class="estoque-form-label">Quantidade</label>
                    <input type="number" class="estoque-form-input" id="saida-qtd" placeholder="0,00" step="0.01">
                </div>
                <div class="estoque-form-grupo">
                    <label class="estoque-form-label">Motivo</label>
                    <select class="estoque-form-input" id="saida-motivo">
                        <option value="">Selecione...</option>
                        <option value="Produção">Produção</option>
                        <option value="Devolução">Devolução</option>
                        <option value="Perdido">Perdido</option>
                        <option value="Refugo">Refugo</option>
                    </select>
                </div>
                <div class="estoque-botao-grupo">
                    <button class="estoque-botao estoque-botao-saida" onclick="registrarMovimento('saida')">
                        🗑️ Saída
                    </button>
                </div>
            </div>

            <!-- LISTA DE PRODUTOS -->
            <div class="estoque-lista">
                <div class="estoque-filtro">
                    <input type="text" class="estoque-filtro-input" id="filtro-produto" placeholder="🔍 Buscar produto...">
                    <button class="estoque-filtro-botao ativo" onclick="filtrarEstoque('todos')">Todos</button>
                    <button class="estoque-filtro-botao" onclick="filtrarEstoque('critico')">🔴 Crítico</button>
                </div>
                <div id="estoque-items"></div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DE DETALHES -->
<div class="estoque-modal" id="modal-detalhes">
    <div class="estoque-modal-content">
        <div class="estoque-modal-titulo" id="modal-titulo">Detalhes do Produto</div>
        <div id="modal-historico" class="estoque-modal-historico"></div>
    </div>
</div>

</div>
        </div>
    </div>
</div>

<script>
    let produtosCache = [];
    let filtroAtivo = 'todos';

    // Carregar lista de produtos
    function carregarProdutos() {
        fetch('<?= SITE_URL ?>/api/estoque_movimentacoes.php?acao=listar_saldos')
            .then(r => r.json())
            .then(data => {
                if (data.sucesso) {
                    produtosCache = data.produtos;
                    atualizarSelects();
                    renderizarProdutos();
                }
            });
    }

    function atualizarSelects() {
        const entrada = document.getElementById('entrada-produto');
        const saida = document.getElementById('saida-produto');

        let html = '<option value="">Selecione um produto...</option>';
        produtosCache.forEach(p => {
            html += `<option value="${p.id}">${p.nome}</option>`;
        });

        entrada.innerHTML = html;
        saida.innerHTML = html;
    }

    function renderizarProdutos() {
        let html = '';
        produtosCache.forEach(p => {
            const saldo = p.quantidade_total || 0;
            const minima = p.quantidade_minima || 0;
            const critico = saldo <= minima;

            if (filtroAtivo === 'critico' && !critico) return;

            let status = 'ok';
            if (critico) status = 'critico';
            else if (saldo < minima * 1.5) status = 'baixo';

            html += `<div class="estoque-item ${status === 'critico' ? 'critico' : ''}">
                <div>
                    <div class="estoque-item-nome">${p.nome}</div>
                    <div class="estoque-item-id">ID: ${p.id}</div>
                </div>
                <div>
                    <div class="estoque-item-saldo">${saldo.toFixed(2)}</div>
                    <div class="estoque-item-saldo-label">un.</div>
                </div>
                <div class="estoque-item-status">
                    <div class="estoque-status-badge ${status === 'ok' ? 'estoque-status-ok' : status === 'critico' ? 'estoque-status-critico' : 'estoque-status-baixo'}">
                        ${status === 'ok' ? '✓ OK' : status === 'critico' ? '🔴 CRÍTICO' : '⚠️ BAIXO'}
                    </div>
                </div>
                <div>Mín: ${minima.toFixed(0)}</div>
                <div class="estoque-item-acoes">
                    <button class="estoque-item-botao estoque-item-botao-entrada" onclick="abrirEntrada(${p.id})">➕</button>
                    <button class="estoque-item-botao estoque-item-botao-saida" onclick="abrirSaida(${p.id})">➖</button>
                    <button class="estoque-item-botao estoque-item-botao-info" onclick="abrirDetalhes(${p.id}, '${p.nome}')">ℹ️</button>
                </div>
            </div>`;
        });

        document.getElementById('estoque-items').innerHTML = html || '<p style="text-align: center; color: #999;">Nenhum produto encontrado</p>';
    }

    function abrirEntrada(produtoId) {
        document.getElementById('entrada-produto').value = produtoId;
        document.getElementById('entrada-qtd').focus();
    }

    function abrirSaida(produtoId) {
        document.getElementById('saida-produto').value = produtoId;
        document.getElementById('saida-qtd').focus();
    }

    function abrirDetalhes(produtoId, nomeProduto) {
        document.getElementById('modal-titulo').innerText = `Histórico: ${nomeProduto}`;
        fetch(`<?= SITE_URL ?>/api/estoque_movimentacoes.php?acao=listar&produto_id=${produtoId}`)
            .then(r => r.json())
            .then(data => {
                if (data.sucesso && data.movimentacoes) {
                    let html = '';
                    data.movimentacoes.forEach(m => {
                        const cor = m.tipo === 'entrada' ? '#10b981' : m.tipo === 'saida' ? '#ef4444' : '#f59e0b';
                        html += `<div class="estoque-modal-item">
                            <div class="estoque-modal-item-tipo" style="color: ${cor}">
                                ${m.tipo.toUpperCase()} - ${m.quantidade.toFixed(2)} un.
                            </div>
                            <div>${new Date(m.created_at).toLocaleString('pt-BR')}</div>
                            ${m.referencia ? `<div>Ref: ${m.referencia}</div>` : ''}
                            ${m.observacao ? `<div>📝 ${m.observacao}</div>` : ''}
                        </div>`;
                    });
                    document.getElementById('modal-historico').innerHTML = html;
                    document.getElementById('modal-detalhes').classList.add('ativo');
                }
            });
    }

    function registrarMovimento(tipo) {
        const produtoId = document.getElementById(`${tipo}-produto`).value;
        const quantidade = parseFloat(document.getElementById(`${tipo}-qtd`).value);
        const referencia = document.getElementById(`${tipo}-ref`) ? document.getElementById(`${tipo}-ref`).value : '';
        const motivo = document.getElementById(`${tipo}-motivo`) ? document.getElementById(`${tipo}-motivo`).value : '';

        if (!produtoId || !quantidade) {
            alert('Preencha produto e quantidade');
            return;
        }

        const formData = new FormData();
        formData.append('acao', tipo);
        formData.append('produto_id', produtoId);
        formData.append('quantidade', quantidade);
        formData.append('referencia', referencia || motivo);

        fetch('<?= SITE_URL ?>/api/estoque_movimentacoes.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.sucesso) {
                alert('✅ Movimentação registrada!');
                document.getElementById(`${tipo}-produto`).value = '';
                document.getElementById(`${tipo}-qtd`).value = '';
                carregarProdutos();
            }
        });
    }

    function carregarSaldoProduto() {
        // Atualiza em tempo real
    }

    function filtrarEstoque(filtro) {
        filtroAtivo = filtro;
        document.querySelectorAll('.estoque-filtro-botao').forEach(b => b.classList.remove('ativo'));
        event.target.classList.add('ativo');
        renderizarProdutos();
    }

    // Carregar ao abrir página + refresh a cada 60s
    carregarProdutos();
    setInterval(carregarProdutos, 60000);

    // Fechar modal ao clicar fora
    document.getElementById('modal-detalhes').onclick = function(e) {
        if (e.target === this) this.classList.remove('ativo');
    }
</script>

<?php include '../../includes/footer_vendedor.php'; ?>
