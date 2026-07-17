/**
 * Cliente JavaScript para Exportador Cozinka ERP
 *
 * Uso:
 *   const exp = new ExportadorCozinka('/api/exportacao.php');
 *   exp.exportar('vendas', 'xlsx', {status: 'confirmada'}).then(resultado => console.log(resultado));
 */

class ExportadorCozinka {
    constructor(apiUrl = '/api/exportacao.php') {
        this.apiUrl = apiUrl;
        this.tabelasCache = null;
        this.filtrosCache = {};
        this.ultimaExportacao = null;
    }

    /**
     * Exporta dados em formato especificado
     *
     * @param {string} tabela - Nome da tabela (vendas, os, etc)
     * @param {string} formato - Formato (csv, xlsx, pdf, json)
     * @param {object} filtros - Filtros a aplicar
     * @param {boolean} download - Se deve fazer download automático
     * @returns {Promise}
     */
    async exportar(tabela, formato, filtros = {}, download = true) {
        try {
            const formData = new URLSearchParams({
                acao: 'exportar',
                tabela: tabela,
                formato: formato,
                filtros: JSON.stringify(filtros)
            });

            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();

            if (!data.sucesso) {
                throw new Error(data.erro || 'Erro desconhecido');
            }

            this.ultimaExportacao = data;

            // Fazer download automático se solicitado
            if (download && data.conteudo_base64) {
                this._baixarArquivo(data.conteudo_base64, data.nome_arquivo);
            }

            return data;

        } catch (erro) {
            console.error('Erro ao exportar:', erro);
            throw erro;
        }
    }

    /**
     * Lista tabelas disponíveis para exportação
     *
     * @returns {Promise<array>}
     */
    async listarTabelas() {
        try {
            if (this.tabelasCache) {
                return this.tabelasCache;
            }

            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'acao=listar_tabelas'
            });

            const data = await response.json();

            if (!data.sucesso) {
                throw new Error(data.erro || 'Erro ao listar tabelas');
            }

            this.tabelasCache = data.tabelas;
            return data.tabelas;

        } catch (erro) {
            console.error('Erro ao listar tabelas:', erro);
            throw erro;
        }
    }

    /**
     * Obtém filtros disponíveis para uma tabela
     *
     * @param {string} tabela - Nome da tabela
     * @returns {Promise<array>}
     */
    async obterFiltros(tabela) {
        try {
            // Verificar cache
            if (this.filtrosCache[tabela]) {
                return this.filtrosCache[tabela];
            }

            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    acao: 'filtros_disponiveis',
                    tabela: tabela
                })
            });

            const data = await response.json();

            if (!data.sucesso) {
                throw new Error(data.erro || 'Erro ao obter filtros');
            }

            this.filtrosCache[tabela] = data.filtros;
            return data.filtros;

        } catch (erro) {
            console.error('Erro ao obter filtros:', erro);
            throw erro;
        }
    }

    /**
     * Testa conexão com a API
     *
     * @returns {Promise}
     */
    async testar() {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'acao=teste'
            });

            const data = await response.json();

            if (!data.sucesso) {
                throw new Error(data.erro || 'Erro na conexão');
            }

            return data;

        } catch (erro) {
            console.error('Erro ao testar API:', erro);
            throw erro;
        }
    }

    /**
     * Cria um formulário de exportação dinâmico
     *
     * @param {string} containerId - ID do elemento container
     * @param {array} tabelasPermitidas - Tabelas que o usuário pode exportar
     */
    async criarFormulario(containerId, tabelasPermitidas = null) {
        const container = document.getElementById(containerId);
        if (!container) {
            console.error('Container não encontrado:', containerId);
            return;
        }

        try {
            // Buscar tabelas
            let tabelas = await this.listarTabelas();

            if (tabelasPermitidas) {
                tabelas = tabelas.filter(t => tabelasPermitidas.includes(t.chave));
            }

            // Criar HTML
            const html = this._gerarHTMLFormulario(tabelas);
            container.innerHTML = html;

            // Adicionar event listeners
            this._anexarEventos(container);

        } catch (erro) {
            container.innerHTML = `<div class="alert alert-danger">Erro ao criar formulário: ${erro.message}</div>`;
        }
    }

    /**
     * Gera HTML do formulário
     */
    _gerarHTMLFormulario(tabelas) {
        let html = `
            <form id="form-exportacao-cozinka" class="card">
                <div class="card-header">
                    <h5>Exportador de Dados</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="tabela-select" class="form-label">Tabela</label>
                            <select id="tabela-select" class="form-select" required>
                                <option value="">-- Selecione --</option>
        `;

        tabelas.forEach(t => {
            html += `<option value="${t.chave}">${t.nome}</option>`;
        });

        html += `
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Formato</label>
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
                    </div>

                    <div id="filtros-area"></div>

                    <div class="mt-3">
                        <button type="button" id="btn-exportar" class="btn btn-primary">
                            <i class="fas fa-download"></i> Exportar
                        </button>
                        <button type="reset" class="btn btn-secondary">Limpar</button>
                    </div>

                    <div id="progresso-area" style="display: none; margin-top: 1rem;">
                        <div class="progress">
                            <div class="progress-bar progress-bar-animated" style="width: 100%"></div>
                        </div>
                    </div>

                    <div id="resultado-area" style="margin-top: 1rem;"></div>
                </div>
            </form>
        `;

        return html;
    }

    /**
     * Anexa event listeners ao formulário
     */
    _anexarEventos(container) {
        const form = container.querySelector('#form-exportacao-cozinka');
        const selectTabela = container.querySelector('#tabela-select');
        const btnExportar = container.querySelector('#btn-exportar');

        // Atualizar filtros quando tabela mudar
        selectTabela.addEventListener('change', async (e) => {
            const tabela = e.target.value;
            const filtrosArea = container.querySelector('#filtros-area');

            if (!tabela) {
                filtrosArea.innerHTML = '';
                return;
            }

            filtrosArea.innerHTML = '<div class="spinner-border spinner-border-sm"></div>';

            try {
                const filtros = await this.obterFiltros(tabela);
                filtrosArea.innerHTML = this._gerarHTMLFiltros(filtros);
            } catch (erro) {
                filtrosArea.innerHTML = `<div class="alert alert-danger">${erro.message}</div>`;
            }
        });

        // Botão exportar
        btnExportar.addEventListener('click', async () => {
            const tabela = selectTabela.value;
            const formato = container.querySelector('input[name="formato"]:checked').value;

            if (!tabela) {
                alert('Selecione uma tabela');
                return;
            }

            // Coletar filtros
            const filtros = {};
            container.querySelectorAll('[data-filtro]').forEach(el => {
                if (el.value) {
                    filtros[el.dataset.filtro] = el.value;
                }
            });

            // Mostrar progresso
            const progresso = container.querySelector('#progresso-area');
            const resultado = container.querySelector('#resultado-area');
            progresso.style.display = 'block';
            resultado.innerHTML = '';

            try {
                await this.exportar(tabela, formato, filtros);

                progresso.style.display = 'none';
                resultado.innerHTML = `
                    <div class="alert alert-success">
                        ✓ Arquivo exportado com sucesso!
                        ${this.ultimaExportacao.avisos ? `
                            <br><small>${this.ultimaExportacao.avisos.length} avisos encontrados</small>
                        ` : ''}
                    </div>
                `;
            } catch (erro) {
                progresso.style.display = 'none';
                resultado.innerHTML = `
                    <div class="alert alert-danger">
                        Erro: ${erro.message}
                    </div>
                `;
            }
        });
    }

    /**
     * Gera HTML dos filtros
     */
    _gerarHTMLFiltros(filtros) {
        if (filtros.length === 0) {
            return '<p class="text-muted">Nenhum filtro disponível</p>';
        }

        let html = '<div class="row">';

        filtros.forEach(filtro => {
            const id = `filtro-${filtro.chave}`;
            html += `<div class="col-md-6 mb-3">`;
            html += `<label for="${id}" class="form-label">${filtro.label}</label>`;

            if (filtro.tipo === 'select') {
                html += `<select id="${id}" class="form-select" data-filtro="${filtro.chave}">`;
                html += `<option value="">-- Qualquer --</option>`;
                filtro.opcoes.forEach(opt => {
                    html += `<option value="${opt}">${opt}</option>`;
                });
                html += `</select>`;
            } else if (filtro.tipo === 'date') {
                html += `<input type="date" id="${id}" class="form-control" data-filtro="${filtro.chave}">`;
            } else {
                html += `<input type="text" id="${id}" class="form-control" data-filtro="${filtro.chave}">`;
            }

            html += `</div>`;
        });

        html += '</div>';
        return html;
    }

    /**
     * Baixa arquivo a partir de base64
     */
    _baixarArquivo(base64, nome) {
        try {
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
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);

            window.URL.revokeObjectURL(url);
        } catch (erro) {
            console.error('Erro ao baixar arquivo:', erro);
        }
    }

    /**
     * Retorna última exportação realizada
     */
    obterUltimaExportacao() {
        return this.ultimaExportacao;
    }

    /**
     * Limpa cache
     */
    limparCache() {
        this.tabelasCache = null;
        this.filtrosCache = {};
    }
}

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.ExportadorCozinka = ExportadorCozinka;
}
