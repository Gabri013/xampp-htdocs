<?php
/**
 * Interface de Exportação de Dados - Cozinka ERP
 * Módulo administrativo para gerenciar exportações
 */

require_once '../../config/config.php';
require_once '../../includes/exportador.php';

// Validar acesso
requireLogin();

if (!in_array($_SESSION['usuario_tipo'], ['master', 'gerente'])) {
    $_SESSION['erro'] = 'Você não tem permissão para acessar o exportador.';
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

$page_title = 'Exportador de Dados';
$db = getDB();
$usuario = [
    'id' => $_SESSION['usuario_id'],
    'nome' => $_SESSION['usuario_nome'],
    'tipo' => $_SESSION['usuario_tipo'],
    'email' => $_SESSION['usuario_email']
];
?>

<?php include '../../includes/header_vendedor.php'; ?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-12">
            <h1 class="mb-4">
                <i class="fas fa-download"></i> Exportador de Dados
            </h1>
        </div>
    </div>

    <div class="row">
        <!-- Painel de Exportação -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Nova Exportação</h5>
                </div>
                <div class="card-body">
                    <form id="form-exportacao">
                        <!-- Seleção de Tabela -->
                        <div class="mb-3">
                            <label for="tabela" class="form-label">Tabela</label>
                            <select id="tabela" class="form-select" required onchange="atualizarFiltros()">
                                <option value="">-- Selecione uma tabela --</option>
                                <option value="vendas">Vendas</option>
                                <option value="orcamentos">Orçamentos</option>
                                <option value="os">Ordens de Serviço</option>
                                <option value="clientes">Clientes</option>
                                <option value="estoque">Estoque</option>
                                <option value="producao">Produção</option>
                                <option value="financeiro">Financeiro</option>
                            </select>
                        </div>

                        <!-- Seleção de Formato -->
                        <div class="mb-3">
                            <label for="formato" class="form-label">Formato</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="formato" id="fmt-csv" value="csv">
                                <label class="btn btn-outline-primary" for="fmt-csv">CSV</label>

                                <input type="radio" class="btn-check" name="formato" id="fmt-xlsx" value="xlsx" checked>
                                <label class="btn btn-outline-primary" for="fmt-xlsx">Excel</label>

                                <input type="radio" class="btn-check" name="formato" id="fmt-pdf" value="pdf">
                                <label class="btn btn-outline-primary" for="fmt-pdf">PDF</label>

                                <input type="radio" class="btn-check" name="formato" id="fmt-json" value="json">
                                <label class="btn btn-outline-primary" for="fmt-json">JSON</label>
                            </div>
                        </div>

                        <!-- Filtros Dinâmicos -->
                        <div id="filtros-container" class="mb-3">
                            <p class="text-muted">Selecione uma tabela para ver filtros disponíveis</p>
                        </div>

                        <!-- Botões de Ação -->
                        <div class="mb-3">
                            <button type="button" class="btn btn-primary" onclick="exportarDados()">
                                <i class="fas fa-download"></i> Exportar
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                <i class="fas fa-redo"></i> Limpar
                            </button>
                        </div>
                    </form>

                    <!-- Progresso -->
                    <div id="progresso" style="display: none;">
                        <div class="progress mb-3">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%"></div>
                        </div>
                        <p class="text-muted">Processando exportação...</p>
                    </div>

                    <!-- Status da Exportação -->
                    <div id="status-exportacao"></div>
                </div>
            </div>
        </div>

        <!-- Painel Lateral -->
        <div class="col-md-4">
            <!-- Informações do Usuário -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">Seu Perfil</h6>
                </div>
                <div class="card-body">
                    <p class="mb-2">
                        <strong><?= htmlspecialchars($_SESSION['usuario_nome']) ?></strong><br>
                        <small class="text-muted"><?= htmlspecialchars($_SESSION['usuario_email']) ?></small>
                    </p>
                    <p>
                        <span class="badge bg-info"><?= ucfirst($_SESSION['usuario_tipo']) ?></span>
                    </p>
                </div>
            </div>

            <!-- Dicas -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">💡 Dicas</h6>
                </div>
                <div class="card-body" style="font-size: 12px;">
                    <ul class="list-unstyled mb-0">
                        <li>✓ Use <strong>CSV</strong> para integrar com outros sistemas</li>
                        <li>✓ Use <strong>Excel</strong> para análise e compartilhamento</li>
                        <li>✓ Use <strong>PDF</strong> para documentos formais</li>
                        <li>✓ Use <strong>JSON</strong> para APIs e aplicações</li>
                        <li>✓ Adicione filtros para reduzir volume de dados</li>
                        <li>✓ Até 10.000 registros por exportação</li>
                    </ul>
                </div>
            </div>

            <!-- Histórico de Exportações -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">📊 Últimas Exportações</h6>
                </div>
                <div class="card-body" id="historico-container" style="font-size: 12px;">
                    <p class="text-muted">Carregando...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .card {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }

    .card-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        font-weight: 600;
    }

    .btn-group .btn {
        padding: 0.5rem 1rem;
    }

    #filtros-container {
        background-color: #f9f9f9;
        padding: 1rem;
        border-radius: 0.25rem;
    }

    .filtro-group {
        margin-bottom: 1rem;
    }

    .filtro-group label {
        font-weight: 500;
        font-size: 0.9rem;
        margin-bottom: 0.25rem;
    }

    #status-exportacao {
        margin-top: 1rem;
    }

    .alert-custom {
        padding: 1rem;
        border-radius: 0.25rem;
        margin-top: 1rem;
    }
</style>

<script>
// Variáveis globais
const API_URL = '/api/exportacao.php';

// Carregar filtros quando tabela mudar
function atualizarFiltros() {
    const tabela = document.getElementById('tabela').value;
    const container = document.getElementById('filtros-container');

    if (!tabela) {
        container.innerHTML = '<p class="text-muted">Selecione uma tabela para ver filtros disponíveis</p>';
        return;
    }

    // Mostrar carregamento
    container.innerHTML = '<div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Carregando...</span></div> Carregando filtros...';

    // Buscar filtros via API
    fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `acao=filtros_disponiveis&tabela=${encodeURIComponent(tabela)}`
    })
    .then(r => r.json())
    .then(data => {
        if (!data.sucesso) {
            container.innerHTML = `<div class="alert alert-danger">${data.erro}</div>`;
            return;
        }

        if (data.filtros.length === 0) {
            container.innerHTML = '<p class="text-muted">Nenhum filtro disponível para esta tabela</p>';
            return;
        }

        let html = '';
        data.filtros.forEach(filtro => {
            const id = `filtro-${filtro.chave}`;
            html += `<div class="filtro-group">`;
            html += `<label for="${id}" class="form-label">${filtro.label}</label>`;

            if (filtro.tipo === 'select') {
                html += `<select id="${id}" class="form-select form-select-sm" data-filtro="${filtro.chave}">`;
                html += `<option value="">-- Qualquer --</option>`;
                filtro.opcoes.forEach(opt => {
                    html += `<option value="${opt}">${opt}</option>`;
                });
                html += `</select>`;
            } else if (filtro.tipo === 'date') {
                html += `<input type="date" id="${id}" class="form-control form-control-sm" data-filtro="${filtro.chave}">`;
            } else if (filtro.tipo === 'number') {
                html += `<input type="number" id="${id}" class="form-control form-control-sm" data-filtro="${filtro.chave}" placeholder="Ex: 42">`;
            } else {
                html += `<input type="text" id="${id}" class="form-control form-control-sm" data-filtro="${filtro.chave}" placeholder="Digite...">`;
            }

            html += `</div>`;
        });

        container.innerHTML = html;
    })
    .catch(err => {
        container.innerHTML = `<div class="alert alert-danger">Erro ao carregar filtros: ${err.message}</div>`;
    });
}

// Exportar dados
function exportarDados() {
    const tabela = document.getElementById('tabela').value;
    const formato = document.querySelector('input[name="formato"]:checked').value;

    if (!tabela) {
        alert('Selecione uma tabela');
        return;
    }

    // Coletar filtros
    const filtros = {};
    document.querySelectorAll('[data-filtro]').forEach(el => {
        const valor = el.value;
        if (valor) {
            filtros[el.dataset.filtro] = valor;
        }
    });

    // Mostrar progresso
    document.getElementById('progresso').style.display = 'block';
    document.getElementById('status-exportacao').innerHTML = '';

    // Enviar requisição
    fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            acao: 'exportar',
            tabela: tabela,
            formato: formato,
            filtros: JSON.stringify(filtros)
        })
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('progresso').style.display = 'none';

        if (!data.sucesso) {
            let html = `<div class="alert alert-danger">
                <strong>Erro:</strong> ${data.erro}
            </div>`;
            if (data.avisos && data.avisos.length > 0) {
                html += `<div class="alert alert-warning">
                    <strong>Avisos:</strong>
                    <ul class="mb-0">
                        ${data.avisos.map(a => `<li>${a}</li>`).join('')}
                    </ul>
                </div>`;
            }
            document.getElementById('status-exportacao').innerHTML = html;
            return;
        }

        // Sucesso
        let html = `<div class="alert alert-success">
            <strong>✓ Exportação realizada com sucesso!</strong><br>
            Arquivo: <code>${data.nome_arquivo}</code> (${formatarTamanho(data.tamanho)})<br>
            Data: ${data.data_exportacao}
        </div>`;

        if (data.avisos && data.avisos.length > 0) {
            html += `<div class="alert alert-info">
                <strong>Avisos (${data.avisos.length}):</strong>
                <ul class="mb-0" style="font-size: 11px;">
                    ${data.avisos.slice(0, 5).map(a => `<li>${a}</li>`).join('')}
                    ${data.avisos.length > 5 ? `<li>... e mais ${data.avisos.length - 5} avisos</li>` : ''}
                </ul>
            </div>`;
        }

        // Botão de download
        html += `<button class="btn btn-sm btn-success" onclick="baixarArquivo('${data.conteudo_base64}', '${data.nome_arquivo}')">
            <i class="fas fa-download"></i> Baixar ${data.formato.toUpperCase()}
        </button>`;

        document.getElementById('status-exportacao').innerHTML = html;

        // Carregar histórico
        carregarHistorico();
    })
    .catch(err => {
        document.getElementById('progresso').style.display = 'none';
        document.getElementById('status-exportacao').innerHTML = `
            <div class="alert alert-danger">
                Erro na requisição: ${err.message}
            </div>
        `;
    });
}

// Baixar arquivo a partir de base64
function baixarArquivo(base64, nome) {
    const bytes = atob(base64);
    const array = new Uint8Array(bytes.length);
    for (let i = 0; i < bytes.length; i++) {
        array[i] = bytes.charCodeAt(i);
    }
    const blob = new Blob([array]);
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = nome;
    a.click();
    window.URL.revokeObjectURL(url);
}

// Formatar tamanho de arquivo
function formatarTamanho(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

// Carregar histórico de exportações
function carregarHistorico() {
    fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'acao=teste'
    })
    .then(r => r.json())
    .then(data => {
        // Simulando histórico (em produção, seria uma tabela real)
        const html = `
            <p class="text-muted mb-0" style="font-size: 11px;">
                <strong>Usuário:</strong> ${data.usuario_logado}<br>
                <strong>Status:</strong> <span class="badge bg-success">Conectado</span>
            </p>
        `;
        document.getElementById('historico-container').innerHTML = html;
    })
    .catch(err => {
        document.getElementById('historico-container').innerHTML = `<p class="text-danger mb-0" style="font-size: 11px;">Erro ao carregar</p>`;
    });
}

// Inicializar ao carregar
document.addEventListener('DOMContentLoaded', carregarHistorico);
</script>

<?php include '../../includes/footer_vendedor.php'; ?>
