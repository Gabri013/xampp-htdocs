<?php
/**
 * Gerenciador de BOM (Bill of Materials) - Lista de Materiais por Produto
 *
 * Inspirado no Nomus - definir componentes de cada produto
 * Acesso: master, gerente, producao
 */

require_once '../../config/config.php';
require_once '../../includes/workflow.php';

$page_title = 'Gerenciador de BOM';
$db = getDB();
requirePermission(['master', 'gerente', 'dashboard_producao', 'producao']);

// Buscar lista de produtos
$stmt = $db->query("SELECT id, nome FROM produtos ORDER BY nome LIMIT 100");
$produtos = $stmt->fetchAll();

include '../../includes/header_vendedor.php';
?>

<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-page-head">
            <h1 class="vend-page-title">📋 Gerenciador de BOM</h1>
        </div>
        <div class="vend-content">

<style>
    .bom-container {
        display: grid;
        grid-template-columns: 1fr 2fr;
        gap: 24px;
    }

    .bom-seletor {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        height: fit-content;
        position: sticky;
        top: 20px;
    }

    .bom-titulo {
        font-size: 14px;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 12px;
    }

    .bom-seletor-select {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 13px;
    }

    /* BOM ATUAL */
    .bom-atual {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow: hidden;
    }

    .bom-header {
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        color: white;
        padding: 20px;
    }

    .bom-header-titulo {
        font-size: 16px;
        font-weight: 700;
        margin: 0;
    }

    .bom-header-info {
        font-size: 12px;
        opacity: 0.9;
        margin-top: 4px;
    }

    .bom-conteudo {
        padding: 20px;
    }

    .bom-lista {
        display: grid;
        gap: 12px;
    }

    .bom-item {
        background: #f9fafb;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 12px;
        display: grid;
        grid-template-columns: 2fr 1fr 1fr auto;
        gap: 12px;
        align-items: center;
    }

    .bom-item-nome {
        font-weight: 600;
        color: #1f2937;
        font-size: 13px;
    }

    .bom-item-quantidade {
        font-size: 14px;
        font-weight: 700;
        color: #8b5cf6;
    }

    .bom-item-remover {
        width: 32px;
        height: 32px;
        border: none;
        background: #fee2e2;
        color: #7f1d1d;
        border-radius: 6px;
        cursor: pointer;
        font-size: 16px;
        transition: all 0.2s;
    }

    .bom-item-remover:hover {
        background: #fecaca;
    }

    .bom-adicionar {
        background: #f3f4f6;
        border: 2px dashed #9ca3af;
        border-radius: 8px;
        padding: 16px;
        margin-top: 16px;
    }

    .bom-form-grupo {
        margin-bottom: 12px;
    }

    .bom-form-label {
        font-size: 11px;
        font-weight: 600;
        color: #666;
        display: block;
        margin-bottom: 4px;
    }

    .bom-form-input {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 13px;
    }

    .bom-form-row {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 12px;
    }

    .bom-botao {
        padding: 10px 16px;
        background: #8b5cf6;
        color: white;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.2s;
        width: 100%;
    }

    .bom-botao:hover {
        background: #7c3aed;
        transform: translateY(-2px);
    }

    .bom-vazio {
        text-align: center;
        padding: 40px 20px;
        color: #999;
    }

    @media (max-width: 1024px) {
        .bom-container {
            grid-template-columns: 1fr;
        }

        .bom-seletor {
            position: static;
        }

        .bom-item {
            grid-template-columns: 1fr;
        }
    }

    .bom-stats {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 12px;
        margin-bottom: 16px;
        padding: 12px;
        background: #f3f4f6;
        border-radius: 8px;
    }

    .bom-stats-item {
        font-size: 12px;
    }

    .bom-stats-label {
        color: #666;
    }

    .bom-stats-valor {
        font-weight: 700;
        color: #8b5cf6;
        font-size: 16px;
    }
</style>

<div class="bom-container">
    <!-- SELETOR -->
    <div class="bom-seletor">
        <div class="bom-titulo">📦 Selecione um Produto</div>
        <select class="bom-seletor-select" id="bom-produto" onchange="carregarBOM()">
            <option value="">Escolha um produto...</option>
            <?php foreach ($produtos as $p): ?>
                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nome']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- BOM ATUAL -->
    <div class="bom-atual">
        <div class="bom-header">
            <p class="bom-header-titulo" id="bom-nome">Selecione um produto</p>
            <p class="bom-header-info" id="bom-info">Lista de componentes e materiais</p>
        </div>

        <div class="bom-conteudo">
            <div class="bom-stats">
                <div class="bom-stats-item">
                    <div class="bom-stats-label">Total de Itens</div>
                    <div class="bom-stats-valor" id="bom-total">0</div>
                </div>
                <div class="bom-stats-item">
                    <div class="bom-stats-label">Últimas Alterações</div>
                    <div class="bom-stats-valor" id="bom-alteracoes">—</div>
                </div>
            </div>

            <div class="bom-lista" id="bom-items">
                <div class="bom-vazio">
                    <p>📋 Nenhuma BOM carregada</p>
                    <p style="font-size: 12px;">Selecione um produto à esquerda</p>
                </div>
            </div>

            <!-- ADICIONAR NOVO ITEM -->
            <div class="bom-adicionar" id="bom-adicionar-forma" style="display: none;">
                <div class="bom-titulo">➕ Adicionar Material à BOM</div>

                <div class="bom-form-grupo">
                    <label class="bom-form-label">Material (Componente)</label>
                    <select class="bom-form-input" id="bom-material">
                        <option value="">Selecione um material...</option>
                        <?php foreach ($produtos as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="bom-form-row">
                    <div class="bom-form-grupo">
                        <label class="bom-form-label">Quantidade</label>
                        <input type="number" class="bom-form-input" id="bom-quantidade" placeholder="Ex: 2" step="0.01" min="0.01">
                    </div>
                    <div class="bom-form-grupo">
                        <label class="bom-form-label">Unidade</label>
                        <select class="bom-form-input" id="bom-unidade">
                            <option value="un">Unidade</option>
                            <option value="kg">Kg</option>
                            <option value="m">Metro</option>
                            <option value="l">Litro</option>
                        </select>
                    </div>
                </div>

                <button class="bom-botao" onclick="adicionarItemBOM()">✅ Adicionar à BOM</button>
            </div>

            <!-- BOTÃO PARA MOSTRAR FORMA -->
            <button class="bom-botao" id="bom-toggle-form" style="display: none; margin-top: 16px;" onclick="toggleFormaAdicionar()">
                ➕ Novo Material
            </button>
        </div>
    </div>
</div>

</div>
        </div>
    </div>
</div>

<script>
    let produtoAtualId = null;

    function carregarBOM() {
        const produtoId = document.getElementById('bom-produto').value;
        if (!produtoId) {
            resetarBOM();
            return;
        }

        produtoAtualId = produtoId;
        document.getElementById('bom-adicionar-forma').style.display = 'none';
        document.getElementById('bom-toggle-form').style.display = 'block';

        fetch(`<?= SITE_URL ?>/api/bom.php?acao=obter_bom_produto&produto_id=${produtoId}`)
            .then(r => r.json())
            .then(data => {
                if (data.sucesso) {
                    renderizarBOM(data.itens);
                    document.getElementById('bom-total').innerText = data.total;
                }
            });

        // Carregar nome do produto
        const nome = document.getElementById('bom-produto').options[document.getElementById('bom-produto').selectedIndex].text;
        document.getElementById('bom-nome').innerText = nome;
        document.getElementById('bom-info').innerText = 'Define os componentes necessários para fabricar este produto';
    }

    function renderizarBOM(itens) {
        if (itens.length === 0) {
            document.getElementById('bom-items').innerHTML = '<div class="bom-vazio">📋 Nenhum material definido ainda</div>';
            return;
        }

        let html = '';
        itens.forEach(item => {
            html += `<div class="bom-item">
                <div class="bom-item-nome">${item.material_nome}</div>
                <div class="bom-item-quantidade">${item.quantidade.toFixed(2)} ${item.unidade}</div>
                <div style="font-size: 11px; color: #666;">ID: ${item.material_id}</div>
                <button class="bom-item-remover" onclick="removerItemBOM(${item.id}, '${item.material_nome}')">🗑️</button>
            </div>`;
        });

        document.getElementById('bom-items').innerHTML = html;
    }

    function toggleFormaAdicionar() {
        const forma = document.getElementById('bom-adicionar-forma');
        forma.style.display = forma.style.display === 'none' ? 'block' : 'none';
    }

    function adicionarItemBOM() {
        const materialId = document.getElementById('bom-material').value;
        const quantidade = parseFloat(document.getElementById('bom-quantidade').value);
        const unidade = document.getElementById('bom-unidade').value;

        if (!materialId || !quantidade) {
            alert('Preencha todos os campos');
            return;
        }

        const formData = new FormData();
        formData.append('acao', 'adicionar_item');
        formData.append('produto_principal_id', produtoAtualId);
        formData.append('material_id', materialId);
        formData.append('quantidade', quantidade);
        formData.append('unidade', unidade);

        fetch('<?= SITE_URL ?>/api/bom.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.sucesso) {
                alert('✅ Material adicionado à BOM!');
                document.getElementById('bom-material').value = '';
                document.getElementById('bom-quantidade').value = '';
                carregarBOM();
            }
        });
    }

    function removerItemBOM(itemId, materialNome) {
        if (!confirm(`Remover "${materialNome}" da BOM?`)) return;

        const formData = new FormData();
        formData.append('acao', 'remover_item');
        formData.append('item_id', itemId);

        fetch('<?= SITE_URL ?>/api/bom.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.sucesso) {
                carregarBOM();
            }
        });
    }

    function resetarBOM() {
        document.getElementById('bom-items').innerHTML = '<div class="bom-vazio"><p>📋 Nenhuma BOM carregada</p></div>';
        document.getElementById('bom-nome').innerText = 'Selecione um produto';
        document.getElementById('bom-total').innerText = '0';
        document.getElementById('bom-adicionar-forma').style.display = 'none';
        document.getElementById('bom-toggle-form').style.display = 'none';
    }
</script>

<?php include '../../includes/footer_vendedor.php'; ?>
