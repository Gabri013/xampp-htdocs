<?php
/**
 * Dashboard SAC - Atendimento ao Cliente
 *
 * Padrão Nomus: Gerenciar chamados com prioridade, status, respostas
 * Acesso: master, gerente, sac
 */

require_once '../../config/config.php';
require_once '../../includes/workflow.php';
require_once '../../includes/components/buttons.nomus.php';

$page_title = 'SAC - Atendimento ao Cliente';
$db = getDB();
requirePermission(['master', 'gerente', 'sac', 'dashboard_producao']);

// Buscar clientes para criar chamado
$stmt = $db->query("SELECT id, razao_social FROM clientes ORDER BY razao_social LIMIT 100");
$clientes = $stmt->fetchAll();

// Usuários SAC para atribuição
$stmt = $db->query("SELECT id, nome FROM usuarios WHERE tipo IN ('sac', 'gerente', 'master') ORDER BY nome");
$usuarios_sac = $stmt->fetchAll();

include '../../includes/header_vendedor.php';
?>

<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-page-head">
            <h1 class="vend-page-title">📞 SAC - Atendimento ao Cliente</h1>
        </div>
        <div class="vend-content">

<style>
    .sac-container {
        display: grid;
        grid-template-columns: 1fr 3fr;
        gap: 24px;
    }

    .sac-sidebar {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        height: fit-content;
        position: sticky;
        top: 20px;
    }

    .sac-titulo {
        font-size: 14px;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 12px;
    }

    .sac-form-grupo {
        margin-bottom: 12px;
    }

    .sac-form-label {
        font-size: 11px;
        font-weight: 600;
        color: #666;
        display: block;
        margin-bottom: 4px;
    }

    .sac-form-input {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 13px;
    }

    .sac-form-input:focus {
        border-color: #ec4899;
        outline: none;
    }

    .sac-botao {
        width: 100%;
        padding: 10px;
        background: #ec4899;
        color: white;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .sac-botao:hover {
        background: #be185d;
        transform: translateY(-2px);
    }

    /* LISTA DE CHAMADOS */
    .sac-main {
        display: grid;
        gap: 16px;
    }

    .sac-filtros {
        display: flex;
        gap: 12px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }

    .sac-filtro-botao {
        padding: 8px 12px;
        border: 1px solid #ddd;
        background: white;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
        transition: all 0.2s;
    }

    .sac-filtro-botao:hover,
    .sac-filtro-botao.ativo {
        background: #ec4899;
        color: white;
        border-color: #ec4899;
    }

    .sac-lista {
        display: grid;
        gap: 12px;
    }

    .sac-item {
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 16px;
        cursor: pointer;
        transition: all 0.2s;
        border-left: 4px solid #ddd;
    }

    .sac-item:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border-left-color: #ec4899;
    }

    .sac-item.critica { border-left-color: #dc2626; background: #fef2f2; }
    .sac-item.alta { border-left-color: #f97316; background: #fff7ed; }
    .sac-item.media { border-left-color: #f59e0b; background: #fffbeb; }
    .sac-item.baixa { border-left-color: #10b981; background: #f0fdf4; }

    .sac-item-header {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr auto;
        gap: 12px;
        align-items: center;
        margin-bottom: 8px;
    }

    .sac-item-numero {
        font-weight: 700;
        color: #1f2937;
        font-size: 12px;
    }

    .sac-item-cliente {
        font-size: 11px;
        color: #666;
    }

    .sac-item-titulo {
        font-weight: 600;
        color: #1f2937;
        margin: 8px 0;
    }

    .sac-item-descricao {
        font-size: 12px;
        color: #666;
        margin-bottom: 8px;
        line-height: 1.4;
    }

    .sac-item-footer {
        display: flex;
        gap: 8px;
        justify-content: space-between;
        font-size: 10px;
        color: #999;
    }

    .sac-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 600;
    }

    .sac-badge-novo { background: #dbeafe; color: #0c4a6e; }
    .sac-badge-aberto { background: #fef3c7; color: #92400e; }
    .sac-badge-aguardando { background: #dbeafe; color: #0c4a6e; }
    .sac-badge-em-atendimento { background: #fce7f3; color: #831843; }
    .sac-badge-resolvido { background: #dcfce7; color: #15803d; }
    .sac-badge-fechado { background: #f3f4f6; color: #374151; }

    .sac-prioridade {
        font-weight: 700;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 10px;
    }

    .sac-prioridade-critica { background: #fecaca; color: #7f1d1d; }
    .sac-prioridade-alta { background: #fed7aa; color: #92400e; }
    .sac-prioridade-media { background: #fef08a; color: #713f12; }
    .sac-prioridade-baixa { background: #dcfce7; color: #15803d; }

    /* MODAL DE DETALHES */
    .sac-modal {
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

    .sac-modal.ativo {
        display: flex;
    }

    .sac-modal-content {
        background: white;
        border-radius: 12px;
        padding: 24px;
        max-width: 700px;
        width: 90%;
        max-height: 85vh;
        overflow-y: auto;
    }

    .sac-modal-header {
        border-bottom: 2px solid #f3f4f6;
        padding-bottom: 16px;
        margin-bottom: 16px;
    }

    .sac-modal-numero {
        font-size: 12px;
        color: #666;
        margin-bottom: 4px;
    }

    .sac-modal-titulo {
        font-size: 18px;
        font-weight: 700;
        color: #1f2937;
    }

    .sac-modal-info {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 16px;
    }

    .sac-modal-info-item {
        border: 1px solid #e5e7eb;
        padding: 12px;
        border-radius: 6px;
    }

    .sac-modal-info-label {
        font-size: 10px;
        font-weight: 600;
        color: #666;
    }

    .sac-modal-info-valor {
        font-size: 13px;
        font-weight: 600;
        color: #1f2937;
        margin-top: 4px;
    }

    .sac-respostas {
        background: #f9fafb;
        border-radius: 8px;
        padding: 12px;
        margin-bottom: 16px;
        max-height: 250px;
        overflow-y: auto;
    }

    .sac-resposta {
        background: white;
        padding: 12px;
        border-radius: 6px;
        margin-bottom: 8px;
        border-left: 3px solid #ec4899;
    }

    .sac-resposta.interna {
        border-left-color: #999;
        background: #f3f4f6;
    }

    .sac-resposta-usuario {
        font-weight: 600;
        font-size: 11px;
        color: #1f2937;
    }

    .sac-resposta-data {
        font-size: 9px;
        color: #999;
    }

    .sac-resposta-texto {
        font-size: 12px;
        color: #333;
        margin-top: 4px;
        line-height: 1.4;
    }

    .sac-responder {
        display: grid;
        gap: 8px;
    }

    .sac-responder-input {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 12px;
        resize: vertical;
        min-height: 80px;
    }

    .sac-acoes {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    @media (max-width: 1024px) {
        .sac-container {
            grid-template-columns: 1fr;
        }

        .sac-sidebar {
            position: static;
        }

        .sac-item-header {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="sac-container">
    <!-- SIDEBAR: Criar Chamado -->
    <div class="sac-sidebar">
        <div class="sac-titulo">➕ Novo Chamado</div>

        <div class="sac-form-grupo">
            <label class="sac-form-label">Cliente</label>
            <select class="sac-form-input" id="sac-cliente">
                <option value="">Selecione...</option>
                <?php foreach ($clientes as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['razao_social']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="sac-form-grupo">
            <label class="sac-form-label">Assunto</label>
            <input type="text" class="sac-form-input" id="sac-titulo" placeholder="Breve descrição">
        </div>

        <div class="sac-form-grupo">
            <label class="sac-form-label">Descrição</label>
            <textarea class="sac-form-input" id="sac-descricao" placeholder="Detalhes do problema..." style="resize: vertical; min-height: 80px;"></textarea>
        </div>

        <div class="sac-form-grupo">
            <label class="sac-form-label">Prioridade</label>
            <select class="sac-form-input" id="sac-prioridade">
                <option value="media">🟨 Média</option>
                <option value="baixa">🟩 Baixa</option>
                <option value="alta">🟧 Alta</option>
                <option value="critica">🔴 Crítica</option>
            </select>
        </div>

        <div class="sac-form-grupo">
            <label class="sac-form-label">Categoria</label>
            <select class="sac-form-input" id="sac-categoria">
                <option value="">Selecione...</option>
                <option value="Técnico">Técnico</option>
                <option value="Comercial">Comercial</option>
                <option value="Financeiro">Financeiro</option>
                <option value="Entrega">Entrega</option>
                <option value="Qualidade">Qualidade</option>
                <option value="Outro">Outro</option>
            </select>
        </div>

        <button class="sac-botao" onclick="criarChamado()">📞 Criar Chamado</button>

        <hr style="margin: 20px 0;">

        <div class="sac-titulo">📊 Filtros</div>
        <div class="sac-filtros" id="sac-filtros-container">
            <button class="sac-filtro-botao ativo" onclick="filtrarChamados('todos')">Todos</button>
            <button class="sac-filtro-botao" onclick="filtrarChamados('novo')">🆕 Novo</button>
            <button class="sac-filtro-botao" onclick="filtrarChamados('aberto')">📂 Aberto</button>
            <button class="sac-filtro-botao" onclick="filtrarChamados('critica')">🔴 Crítica</button>
        </div>
    </div>

    <!-- MAIN: Lista de Chamados -->
    <div class="sac-main">
        <div id="sac-lista"></div>
    </div>
</div>

<!-- MODAL DE DETALHES -->
<div class="sac-modal" id="sac-modal">
    <div class="sac-modal-content">
        <div class="sac-modal-header">
            <div class="sac-modal-numero" id="sac-modal-numero"></div>
            <div class="sac-modal-titulo" id="sac-modal-titulo"></div>
        </div>

        <div class="sac-modal-info">
            <div class="sac-modal-info-item">
                <div class="sac-modal-info-label">CLIENTE</div>
                <div class="sac-modal-info-valor" id="sac-modal-cliente"></div>
            </div>
            <div class="sac-modal-info-item">
                <div class="sac-modal-info-label">STATUS</div>
                <div class="sac-modal-info-valor" id="sac-modal-status"></div>
            </div>
            <div class="sac-modal-info-item">
                <div class="sac-modal-info-label">PRIORIDADE</div>
                <div class="sac-modal-info-valor" id="sac-modal-prioridade"></div>
            </div>
            <div class="sac-modal-info-item">
                <div class="sac-modal-info-label">RESPONSÁVEL</div>
                <div class="sac-modal-info-valor" id="sac-modal-responsavel"></div>
            </div>
        </div>

        <div class="sac-respostas" id="sac-respostas"></div>

        <div class="sac-responder">
            <textarea class="sac-responder-input" id="sac-nova-resposta" placeholder="Escrever resposta..."></textarea>
            <div class="sac-acoes">
                <button class="sac-botao" onclick="enviarResposta('cliente')">💬 Responder ao Cliente</button>
                <button class="sac-botao" onclick="enviarResposta('interno')" style="background: #999;">📝 Nota Interna</button>
            </div>
        </div>

        <hr style="margin: 16px 0;">

        <div class="sac-acoes">
            <select id="sac-novo-status" style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 6px;">
                <option value="">Alterar status...</option>
                <option value="aberto">📂 Aberto</option>
                <option value="em_atendimento">🔧 Em Atendimento</option>
                <option value="aguardando_cliente">⏳ Aguardando Cliente</option>
                <option value="resolvido">✅ Resolvido</option>
                <option value="fechado">🔒 Fechado</option>
            </select>
            <button class="sac-botao" onclick="atualizarStatus()">Atualizar</button>
        </div>
    </div>
</div>

</div>
        </div>
    </div>
</div>

<script>
    let filtroAtivo = 'todos';
    let chamadoAtualId = null;

    function carregarChamados() {
        let filtro = filtroAtivo === 'todos' ? '' : `&status=${filtroAtivo === 'critica' ? 'novo' : filtroAtivo}`;
        if (filtroAtivo === 'critica') filtro = '&prioridade=critica';

        fetch(`<?= SITE_URL ?>/api/chamados.php?acao=listar${filtro}`)
            .then(r => r.json())
            .then(data => {
                if (data.sucesso && data.chamados) {
                    renderizarChamados(data.chamados);
                }
            });
    }

    function renderizarChamados(chamados) {
        if (chamados.length === 0) {
            document.getElementById('sac-lista').innerHTML = '<p style="text-align: center; color: #999; padding: 40px;">Nenhum chamado encontrado</p>';
            return;
        }

        let html = '<div class="sac-lista">';
        chamados.forEach(c => {
            const statusClass = `sac-badge-${c.status.replace('_', '-')}`;
            const prioridadeClass = `sac-prioridade-${c.prioridade}`;

            html += `<div class="sac-item ${c.prioridade}" onclick="abrirDetalhes(${c.id})">
                <div class="sac-item-header">
                    <div>
                        <div class="sac-item-numero">${c.numero}</div>
                        <div class="sac-item-cliente">${c.razao_social}</div>
                    </div>
                    <span class="sac-badge ${statusClass}">${c.status.toUpperCase()}</span>
                    <span class="sac-prioridade ${prioridadeClass}">${c.prioridade.toUpperCase()}</span>
                    <span>${c.usuario_responsavel ? '✓' : '○'}</span>
                </div>
                <div class="sac-item-titulo">${c.titulo}</div>
                <div class="sac-item-descricao">${c.descricao.substring(0, 100)}...</div>
                <div class="sac-item-footer">
                    <span>${new Date(c.data_criacao).toLocaleString('pt-BR')}</span>
                    <span>${c.usuario_responsavel || 'Sem responsável'}</span>
                </div>
            </div>`;
        });
        html += '</div>';

        document.getElementById('sac-lista').innerHTML = html;
    }

    function filtrarChamados(filtro) {
        filtroAtivo = filtro;
        document.querySelectorAll('.sac-filtro-botao').forEach(b => b.classList.remove('ativo'));
        event.target.classList.add('ativo');
        carregarChamados();
    }

    function criarChamado() {
        const clienteId = document.getElementById('sac-cliente').value;
        const titulo = document.getElementById('sac-titulo').value;
        const descricao = document.getElementById('sac-descricao').value;
        const prioridade = document.getElementById('sac-prioridade').value;
        const categoria = document.getElementById('sac-categoria').value;

        if (!clienteId || !titulo || !descricao) {
            alert('Preencha cliente, assunto e descrição');
            return;
        }

        const formData = new FormData();
        formData.append('acao', 'criar');
        formData.append('cliente_id', clienteId);
        formData.append('titulo', titulo);
        formData.append('descricao', descricao);
        formData.append('prioridade', prioridade);
        formData.append('categoria', categoria);

        fetch('<?= SITE_URL ?>/api/chamados.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.sucesso) {
                alert('✅ Chamado criado: ' + data.numero);
                document.getElementById('sac-cliente').value = '';
                document.getElementById('sac-titulo').value = '';
                document.getElementById('sac-descricao').value = '';
                carregarChamados();
            }
        });
    }

    function abrirDetalhes(chamadoId) {
        chamadoAtualId = chamadoId;
        fetch(`<?= SITE_URL ?>/api/chamados.php?acao=obter&chamado_id=${chamadoId}`)
            .then(r => r.json())
            .then(data => {
                if (data.sucesso) {
                    const c = data.chamado;
                    document.getElementById('sac-modal-numero').innerText = c.numero;
                    document.getElementById('sac-modal-titulo').innerText = c.titulo;
                    document.getElementById('sac-modal-cliente').innerText = c.razao_social;
                    document.getElementById('sac-modal-status').innerText = c.status.toUpperCase();
                    document.getElementById('sac-modal-prioridade').innerText = c.prioridade.toUpperCase();
                    document.getElementById('sac-modal-responsavel').innerText = c.usuario_responsavel || 'Não atribuído';

                    let html = '';
                    c.respostas.forEach(r => {
                        html += `<div class="sac-resposta ${r.tipo}">
                            <div class="sac-resposta-usuario">${r.usuario_nome || 'Sistema'}</div>
                            <div class="sac-resposta-data">${new Date(r.data_criacao).toLocaleString('pt-BR')}</div>
                            <div class="sac-resposta-texto">${r.mensagem}</div>
                        </div>`;
                    });
                    document.getElementById('sac-respostas').innerHTML = html || '<p style="color: #999; text-align: center;">Nenhuma resposta ainda</p>';

                    document.getElementById('sac-modal').classList.add('ativo');
                }
            });
    }

    function enviarResposta(tipo) {
        const mensagem = document.getElementById('sac-nova-resposta').value;
        if (!mensagem) {
            alert('Digite uma mensagem');
            return;
        }

        const formData = new FormData();
        formData.append('acao', 'adicionar_resposta');
        formData.append('chamado_id', chamadoAtualId);
        formData.append('mensagem', mensagem);
        formData.append('tipo', tipo);

        fetch('<?= SITE_URL ?>/api/chamados.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.sucesso) {
                document.getElementById('sac-nova-resposta').value = '';
                abrirDetalhes(chamadoAtualId);
            }
        });
    }

    function atualizarStatus() {
        const status = document.getElementById('sac-novo-status').value;
        if (!status) return;

        const formData = new FormData();
        formData.append('acao', 'atualizar_status');
        formData.append('chamado_id', chamadoAtualId);
        formData.append('status', status);

        fetch('<?= SITE_URL ?>/api/chamados.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.sucesso) {
                abrirDetalhes(chamadoAtualId);
                carregarChamados();
            }
        });
    }

    // Fechar modal
    document.getElementById('sac-modal').onclick = function(e) {
        if (e.target === this) this.classList.remove('ativo');
    }

    // Carregar chamados ao abrir
    carregarChamados();
    setInterval(carregarChamados, 60000);
</script>

<?php include '../../includes/footer_vendedor.php'; ?>
