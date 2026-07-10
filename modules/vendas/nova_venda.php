<?php
    require_once '../../config/config.php';
    require_once '../../includes/financeiro.php';
    require_once '../../includes/engenharia.php';
    require_once '../../includes/notificacoes.php';
    requirePermission(['master', 'vendedor']);

    $page_title = 'Nova Venda';

    function ensureVendaItensDescricaoManualLonga(PDO $db): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }

        $checked = true;
        $db->exec("ALTER TABLE vendas_itens MODIFY COLUMN descricao_manual TEXT NULL");
    }

    // Processar formulário de Venda
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cliente_id'])) {
        $cliente_id = $_POST['cliente_id'];
        $data_venda = dateToMysql($_POST['data_venda']);
        $data_termino = !empty($_POST['data_termino']) ? dateToMysql($_POST['data_termino']) : null;
        $prioridade = $_POST['prioridade'] ?? 'verde';
        $caixa_tipo_id = (int) ($_POST['caixa_tipo_id'] ?? 0);
        $num_parcelas = max(1, (int) ($_POST['num_parcelas'] ?? 1));
        $taxa_antecipacao_percent = max(0, (float) ($_POST['taxa_antecipacao_percent'] ?? 0));
        $data_recebimento_prevista = !empty($_POST['data_recebimento_prevista']) ? dateToMysql($_POST['data_recebimento_prevista']) : null;
        $forma_pagamento = null;
        $desconto = floatval($_POST['desconto_final'] ?? 0);
        $observacoes = sanitize($_POST['observacoes']);
        $observacoes_venda = sanitize($_POST['observacoes_venda'] ?? '');
        $itens_json = $_POST['itens_json'] ?? '[]';
        $itens = json_decode($itens_json, true);
        
        if (empty($itens)) {
            setError('Adicione pelo menos um item à venda.');
        } else {
            try {
                $db = getDB();
                ensureFinanceiroSchema($db);
                ensureEngenhariaSchema($db);
                ensureVendaItensDescricaoManualLonga($db);

                if ($caixa_tipo_id <= 0) {
                    throw new Exception('Selecione o tipo de caixa para a venda.');
                }

                $stmt_caixa = $db->prepare("SELECT id, nome, categoria, ativo, taxa_padrao_antecipacao FROM tipos_caixa WHERE id = ?");
                $stmt_caixa->execute([$caixa_tipo_id]);
                $tipo_caixa = $stmt_caixa->fetch();
                if (!$tipo_caixa || (int) $tipo_caixa['ativo'] !== 1) {
                    throw new Exception('Tipo de caixa inválido/inativo.');
                }

                $forma_pagamento = mapFormaPagamentoByCategoria($tipo_caixa['categoria']);
                if ($tipo_caixa['categoria'] !== 'cartao_credito') {
                    $num_parcelas = 1;
                    $taxa_antecipacao_percent = 0;
                }
                if ($tipo_caixa['categoria'] === 'boleto' && empty($data_recebimento_prevista)) {
                    throw new Exception('Informe a data prevista para receber o boleto.');
                }
                if ($tipo_caixa['categoria'] !== 'boleto') {
                    $data_recebimento_prevista = null;
                }

                $db->beginTransaction();
                
                $numero = getNextNumber('vendas', 'VND-');
                $subtotal = 0;
                foreach ($itens as $item) { $subtotal += $item['valor_total']; }
                $valor_total = $subtotal - $desconto;
                
                $stmt = $db->prepare("INSERT INTO vendas (numero, cliente_id, usuario_id, data_venda, valor_total, desconto, forma_pagamento, caixa_tipo_id, num_parcelas, taxa_antecipacao_percent, observacoes, observacoes_venda, data_recebimento_prevista) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$numero, $cliente_id, $_SESSION['usuario_id'], $data_venda, $valor_total, $desconto, $forma_pagamento, $caixa_tipo_id, $num_parcelas, $taxa_antecipacao_percent, $observacoes, $observacoes_venda, $data_recebimento_prevista]);
                $venda_id = $db->lastInsertId();
                
                $stmt = $db->prepare("INSERT INTO vendas_itens (venda_id, produto_id, descricao_manual, quantidade, valor_unitario, valor_total) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($itens as $item) {
                    $produto_id = !empty($item['produto_id']) ? $item['produto_id'] : null;

                    $stmt->execute([$venda_id, $produto_id, $item['descricao'], $item['quantidade'], $item['valor_unitario'], $item['valor_total']]);
                }
                
                $numero_os = getNextNumber('ordens_servico', 'OS-');
                $fluxo_os = $_POST['fluxo_os'] ?? null;
                if ($fluxo_os === null) {
                    $fluxo_os = (isset($_POST['pular_projeto']) && $_POST['pular_projeto'] == '1') ? 'corte' : 'projetista';
                }
                $fluxo_os = in_array($fluxo_os, ['projetista', 'corte'], true) ? $fluxo_os : 'projetista';
                $status_inicial = $fluxo_os === 'corte' ? 'em_producao' : 'pendente';
                $etapa_inicial = $fluxo_os === 'corte' ? 'corte' : 'autorizacao';

                $stmt = $db->prepare("INSERT INTO ordens_servico (numero, venda_id, cliente_id, data_inicio, data_termino, status, etapa_atual, prioridade) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$numero_os, $venda_id, $cliente_id, $data_venda, $data_termino, $status_inicial, $etapa_inicial, $prioridade]);
                $os_id = (int) $db->lastInsertId();
                sincronizarPlanejamentoOS($db, $os_id, (int) $venda_id);

                $componentesPorVenda = getComponentesPorVenda($db, (int) $venda_id);
                $resumoComponentes = formatarResumoComponentesVenda($componentesPorVenda);
                if ($resumoComponentes !== '') {
                    $stmt = $db->prepare("
                        INSERT INTO os_observacoes (os_id, tipo_setor, observacao, usuario_id)
                        VALUES (?, 'projeto', ?, ?)
                    ");
                    $stmt->execute([$os_id, $resumoComponentes, $_SESSION['usuario_id']]);

                    $payload = [
                        'tipo' => 'componentes_os',
                        'titulo' => 'Componentes da venda disponíveis',
                        'mensagem' => 'A O.S ' . $numero_os . ' recebeu a lista de componentes dos produtos vendidos para revisão do gerente.',
                        'chave_evento' => 'componentes_os_' . $os_id,
                        'referencia_tipo' => 'os',
                        'referencia_id' => $os_id
                    ];
                    notificarPerfis($db, ['master', 'gerente'], $payload, ['interno']);
                }
                
                if (isset($_FILES['arquivos']) && !empty($_FILES['arquivos']['name'][0])) {
                    foreach ($_FILES['arquivos']['name'] as $key => $nome) {
                        if ($_FILES['arquivos']['error'][$key] === UPLOAD_ERR_OK) {
                            $upload = uploadFile([
                                'name'=>$_FILES['arquivos']['name'][$key],
                                'type'=>$_FILES['arquivos']['type'][$key],
                                'tmp_name'=>$_FILES['arquivos']['tmp_name'][$key],
                                'error'=>$_FILES['arquivos']['error'][$key],
                                'size'=>$_FILES['arquivos']['size'][$key]
                            ], 'projetos');
                            if ($upload['success']) {
                                $stmt = $db->prepare("INSERT INTO os_arquivos (os_id, tipo, nome_original, nome_arquivo, usuario_id) VALUES (?, 'venda', ?, ?, ?)");
                                $stmt->execute([$os_id, $upload['original_name'], $upload['filename'], $_SESSION['usuario_id']]);
                            }
                        }
                    }
                }

                $db->commit();
                setSuccess('Pedido de venda criado com sucesso! Faça o faturamento para gerar o financeiro.');
                header('Location: detalhes_venda.php?id=' . $venda_id);
                exit;
            } catch (Exception $e) {
                if (isset($db) && $db->inTransaction()) {
                    $db->rollBack();
                }
                setError('Erro: ' . $e->getMessage());
            }
        }
    }

    $db = getDB();
    ensureFinanceiroSchema($db);
    ensureVendaItensDescricaoManualLonga($db);
    $clientes = $db->query("SELECT id, razao_social FROM clientes ORDER BY razao_social")->fetchAll();
    $produtos = $db->query("SELECT id, codigo, nome, valor FROM produtos WHERE status = 'ativo' ORDER BY nome")->fetchAll();
    $tipos_caixa = $db->query("SELECT id, nome, categoria, taxa_padrao_antecipacao FROM tipos_caixa WHERE ativo = 1 ORDER BY nome")->fetchAll();

include '../../includes/header_vendedor.php';
    ?>
    
    <style>
        .venda-itens-table {
            table-layout: fixed;
            width: 100%;
        }

        .venda-itens-table td,
        .venda-itens-table th {
            white-space: normal;
            vertical-align: top;
        }

        .venda-itens-table .col-descricao {
            width: 50%;
        }

        .venda-itens-table .col-qtd,
        .venda-itens-table .col-unit,
        .venda-itens-table .col-total {
            width: 14%;
        }

        .venda-itens-table .col-acao {
            width: 8%;
        }

        .venda-desc-cell {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .item-edit-input,
        .item-edit-textarea {
            width: 100%;
            border: 1px solid #d7deea;
            border-radius: 8px;
            padding: 8px 10px;
            font: inherit;
            background: #fff;
        }

        .item-edit-textarea {
            min-height: 84px;
            resize: vertical;
        }

        .item-edit-input:focus,
        .item-edit-textarea:focus {
            outline: none;
            border-color: #5b4ce2;
            box-shadow: 0 0 0 3px rgba(91, 76, 226, 0.12);
        }

        .item-total-cell {
            font-weight: 600;
            white-space: nowrap;
        }

        .item-edit-hint {
            display: block;
            margin-top: 6px;
            font-size: 12px;
            color: #6b7280;
        }

        .cliente-rapido-acoes {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .cliente-rapido-modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            padding: 24px 16px;
            background: rgba(15, 23, 42, 0.68);
            overflow: auto;
        }

        .cliente-rapido-modal.show {
            display: block;
        }

        .cliente-rapido-modal-content {
            background: #fff;
            margin: 0 auto;
            width: 100%;
            max-width: 720px;
            border-radius: 16px;
            box-shadow: var(--shadow-xl);
            border: 1px solid rgba(226, 232, 240, 0.9);
            overflow: hidden;
        }

        .cliente-rapido-titulo {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 0;
        }

        .cliente-rapido-titulo h4 {
            margin: 0;
            color: var(--dark-color);
        }

        .cliente-rapido-modal-body {
            padding: 24px;
        }
</style>
    
    <div class="vend-layout">
        <?php include '../../includes/vend_sidebar.php'; ?>
        <div class="vend-main">
            <div class="vend-page-head">
                <div><h1 class="vend-page-title">Nova Venda</h1></div>
                <a href="index.php" class="vbtn-sm"><i class="fas fa-arrow-left"></i> Voltar</a>
            </div>
             <div class="vend-card">
                 <div class="vend-card-head"><div class="vend-card-title">Nova Venda</div></div>
                 <div style="padding:24px">
                     <form method="POST" id="formVenda" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group">
                        <label>Cliente *</label>
                        <select id="cliente_id" name="cliente_id" class="form-control" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($clientes as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['razao_social']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="cliente-rapido-acoes">
                            <button type="button" id="btnAbrirClienteRapido" class="vbtn-sm">
                                <i class="fas fa-plus-circle"></i> Novo Cliente Rápido
                            </button>
                            <a href="<?php echo SITE_URL; ?>/modules/cadastros/clientes.php" class="vbtn-sm">
                                <i class="fas fa-address-book"></i> Cadastro Completo
                            </a>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Data Venda *</label>
                        <input type="date" name="data_venda" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Data Término</label>
                        <input type="date" name="data_termino" class="form-control">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Prioridade</label>
                        <select name="prioridade" class="form-control">
                            <option value="verde">🟢 Verde</option>
                            <option value="amarelo">🟡 Amarelo</option>
                            <option value="vermelho">🔴 Vermelho</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Destino inicial da O.S.</label>
                        <select name="fluxo_os" class="form-control">
                            <option value="projetista">Enviar para Projetista</option>
                            <option value="corte">Enviar direto para Corte</option>
                        </select>
                        <small class="text-muted">Se for para o projetista, ele define a etapa inicial da produção. Se for para corte, a O.S. entra direto no fluxo produtivo.</small>
                    </div>
                    <div class="form-group">
                        <label>Tipo de Caixa *</label>
                        <select name="caixa_tipo_id" id="caixa_tipo_id" class="form-control" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($tipos_caixa as $tc): ?>
                                <option value="<?php echo (int) $tc['id']; ?>"
                                        data-categoria="<?php echo htmlspecialchars($tc['categoria']); ?>"
                                        data-taxa="<?php echo htmlspecialchars($tc['taxa_padrao_antecipacao']); ?>">
                                    <?php echo htmlspecialchars($tc['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small id="forma_pagamento_info" class="text-muted">Forma financeira: -</small>
                    </div>
                </div>

                <div class="form-row" id="bloco_cartao_credito" style="display:none;">
                    <div class="form-group">
                        <label>Parcelas</label>
                        <select name="num_parcelas" id="num_parcelas" class="form-control">
                            <?php for ($p = 1; $p <= 12; $p++): ?>
                                <option value="<?php echo $p; ?>"><?php echo $p; ?>x</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Desconto antecipação (%)</label>
                        <input type="number" step="0.01" min="0" name="taxa_antecipacao_percent" id="taxa_antecipacao_percent" class="form-control" value="0">
                    </div>
                </div>

                <div class="form-row" id="bloco_boleto" style="display:none;">
                    <div class="form-group">
                        <label>Data para Receber o Boleto *</label>
                        <input type="date" name="data_recebimento_prevista" id="data_recebimento_prevista" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Observações da Venda</label>
                    <textarea name="observacoes_venda" class="form-control" rows="2" placeholder="Informações comerciais visíveis apenas no módulo de vendas..."></textarea>
                </div>

                <div class="form-group">
                    <label>Observações para Produção/O.S.</label>
                    <textarea name="observacoes" class="form-control" rows="2"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Arquivos</label>
                    <input type="file" name="arquivos[]" class="form-control" multiple>
                </div>
                
                <hr>
                <h4>Itens da Venda</h4>
                
                <div class="form-row" style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                    <div class="form-group" style="flex: 2;">
                        <label>Produto</label>
                        <div style="display: flex; gap: 10px;">
                            <select id="sel_tipo" class="form-control" style="width: 120px;">
                                <option value="P">Cadastrado</option>
                                <option value="M">Manual</option>
                            </select>
                            <div id="div_prod" style="flex: 1;">
                                <select id="sel_prod" class="form-control">
                                    <option value="">Selecione...</option>
                                    <?php foreach ($produtos as $p): ?>
                                        <option value="<?php echo $p['id']; ?>" data-nome="<?php echo htmlspecialchars($p['nome']); ?>" data-valor="<?php echo $p['valor']; ?>">
                                            <?php echo htmlspecialchars($p['codigo'] . ' - ' . $p['nome']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div id="div_manual" style="flex: 1; display:none;">
                                <textarea id="inp_manual" class="form-control" placeholder="Descrição..." rows="2" style="min-height: 44px; resize: vertical;"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="form-group" style="flex: 0.5;"><label>Qtd</label><input type="number" id="inp_qtd" class="form-control" value="1" step="0.01"></div>
                    <div class="form-group" style="flex: 1;"><label>Valor</label><input type="text" id="inp_vlr" class="form-control"></div>
                    <div class="form-group" style="flex: 0.5;"><label>&nbsp;</label><button type="button" class="vbtn-sm" id="btn_add_item"><i class="fas fa-plus"></i></button></div>
                </div>
                
                <table class="table venda-itens-table">
                    <colgroup>
                        <col class="col-descricao">
                        <col class="col-qtd">
                        <col class="col-unit">
                        <col class="col-total">
                        <col class="col-acao">
                    </colgroup>
                    <thead><tr><th>Descrição</th><th>Qtd</th><th>Unit.</th><th>Total</th><th></th></tr></thead>
                    <tbody id="corpo_tabela"></tbody>
                </table>
                <small class="item-edit-hint">Clique e edite diretamente a descrição, a quantidade ou o valor unitário antes de finalizar a venda.</small>

                <div class="form-row mt-20" style="background: #e9ecef; padding: 15px; border-radius: 5px;">
                    <div class="form-group" style="flex: 1;">
                        <label>Desconto (%)</label>
                        <input type="number" id="desc_porc" class="form-control" value="0" step="0.01">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Desconto (R$)</label>
                        <input type="text" id="desc_valor" class="form-control" value="0,00">
                    </div>
                    <div class="form-group" style="flex: 2; text-align: right;">
                        <div style="font-size: 14px; color: #666;">Subtotal: <span id="txt_subtotal">R$ 0,00</span></div>
                        <div style="font-size: 14px; color: #d9534f;" id="resumo_desconto">Desconto: R$ 0,00</div>
                        <div style="font-size: 20px; font-weight: bold; color: var(--primary-color);">Total: <span id="txt_total">R$ 0,00</span></div>
                    </div>
                </div>
                
                <input type="hidden" name="itens_json" id="itens_json" value="[]">
                <input type="hidden" name="desconto_final" id="desconto_final" value="0">
                
<div class="mt-20"><button type="submit" class="vbtn-sm" style="border-color:#D85A30;color:#D85A30">Finalizar Venda</button></div>
</form>
    </div>
</div>
</div>
</div>

<div id="clienteRapidoModal" class="cliente-rapido-modal" aria-hidden="true">
         <div class="cliente-rapido-modal-content">
             <div class="vend-card-head">
                 <div class="cliente-rapido-titulo">
                     <span class="vend-card-title">Novo Cliente</span>
                 </div>
                 <button type="button" class="vbtn-sm" id="btnFecharClienteRapido">
                     <i class="fas fa-times"></i> Fechar
                 </button>
            </div>
            <div class="cliente-rapido-modal-body">
                <div id="modal_erro"></div>
                <div class="form-group"><label>Razão Social *</label><input type="text" id="m_razao" class="form-control"></div>
                <div class="form-group"><label>Nome Fantasia</label><input type="text" id="m_fantasia" class="form-control"></div>
                <div class="form-row">
                    <div class="form-group"><label>CNPJ/CPF</label><input type="text" id="m_cnpj" class="form-control"></div>
                    <div class="form-group"><label>I.E.</label><input type="text" id="m_ie" class="form-control"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Responsável</label><input type="text" id="m_responsavel" class="form-control"></div>
                    <div class="form-group"><label>Email</label><input type="email" id="m_email" class="form-control"></div>
                </div>
                <div class="form-group"><label>Telefone</label><input type="text" id="m_tel" class="form-control"></div>
                <div class="form-group"><label>Endereço</label><input type="text" id="m_endereco" class="form-control"></div>
            </div>
            <div class="modal-footer" style="padding: 0 24px 24px;">
<button type="button" class="vbtn-sm btn-secondary" id="btn_canc_modal">Cancelar</button>
                 <button type="button" class="vbtn-sm" style="border-color:#D85A30;color:#D85A30" id="btn_salvar_cli">Salvar Cliente</button>
            </div>
        </div>
    </div>

</div>

<script>
    function limparModalClienteRapido() {
        ['m_razao', 'm_fantasia', 'm_cnpj', 'm_ie', 'm_responsavel', 'm_email', 'm_tel', 'm_endereco'].forEach((id) => {
            const campo = document.getElementById(id);
            if (campo) campo.value = '';
        });
    }

    function fecharModalClienteRapido() {
        const modal = document.getElementById('clienteRapidoModal');
        if (modal) {
            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }
        limparModalClienteRapido();
    }

    function abrirModalClienteRapido() {
        const modal = document.getElementById('clienteRapidoModal');
        if (modal) {
            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }
        const campoRazao = document.getElementById('m_razao');
        if (campoRazao) {
            campoRazao.focus();
        }
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    (function() {
        let itens = [];
        let subtotal = 0;
        let descontoFinal = 0;

        const btnAdd = document.getElementById('btn_add_item');
        const corpoTabela = document.getElementById('corpo_tabela');
        const selTipo = document.getElementById('sel_tipo');
        const selProd = document.getElementById('sel_prod');
        const inpManual = document.getElementById('inp_manual');
        const inpQtd = document.getElementById('inp_qtd');
        const inpVlr = document.getElementById('inp_vlr');
        const txtSubtotal = document.getElementById('txt_subtotal');
        const txtTotal = document.getElementById('txt_total');
        const hiddenJson = document.getElementById('itens_json');
        const hiddenDesconto = document.getElementById('desconto_final');
        const descPorc = document.getElementById('desc_porc');
        const descValor = document.getElementById('desc_valor');
        const selCaixa = document.getElementById('caixa_tipo_id');
        const blocoCartao = document.getElementById('bloco_cartao_credito');
        const blocoBoleto = document.getElementById('bloco_boleto');
        const taxaAntecipacao = document.getElementById('taxa_antecipacao_percent');
        const numParcelas = document.getElementById('num_parcelas');
        const formaPagamentoInfo = document.getElementById('forma_pagamento_info');
        const dataRecebimentoPrevista = document.getElementById('data_recebimento_prevista');

        // Alternar campos produto/manual
        selTipo.onchange = function() {
            document.getElementById('div_prod').style.display = this.value === 'P' ? 'block' : 'none';
            document.getElementById('div_manual').style.display = this.value === 'M' ? 'block' : 'none';
        };

        function atualizarRegraFinanceiraPorCaixa() {
            const opt = selCaixa.options[selCaixa.selectedIndex];
            const categoria = opt?.dataset?.categoria || '';
            if (!categoria) {
                blocoCartao.style.display = 'none';
                blocoBoleto.style.display = 'none';
                dataRecebimentoPrevista.value = '';
                dataRecebimentoPrevista.required = false;
                formaPagamentoInfo.textContent = 'Forma financeira: -';
                return;
            }

            let forma = 'À vista';
            if (categoria === 'cartao_credito') forma = 'Cartão';
            if (categoria === 'boleto') forma = 'Boleto';
            formaPagamentoInfo.textContent = 'Forma financeira: ' + forma;

            if (categoria === 'cartao_credito') {
                blocoCartao.style.display = 'flex';
                blocoBoleto.style.display = 'none';
                dataRecebimentoPrevista.value = '';
                dataRecebimentoPrevista.required = false;
                if (!taxaAntecipacao.value || parseFloat(taxaAntecipacao.value) === 0) {
                    taxaAntecipacao.value = opt.dataset.taxa || '0';
                }
            } else if (categoria === 'boleto') {
                blocoCartao.style.display = 'none';
                blocoBoleto.style.display = 'flex';
                dataRecebimentoPrevista.required = true;
                taxaAntecipacao.value = '0';
                numParcelas.value = '1';
            } else {
                blocoCartao.style.display = 'none';
                blocoBoleto.style.display = 'none';
                dataRecebimentoPrevista.value = '';
                dataRecebimentoPrevista.required = false;
                taxaAntecipacao.value = '0';
                numParcelas.value = '1';
            }
        }
        selCaixa.addEventListener('change', atualizarRegraFinanceiraPorCaixa);
        atualizarRegraFinanceiraPorCaixa();

        // Auto-preencher valor unitário
        selProd.onchange = function() {
            const opt = this.options[this.selectedIndex];
            if(opt.value) inpVlr.value = opt.dataset.valor.replace('.', ',');
        };

        // Adicionar Item
        btnAdd.onclick = async function() {
            // Desabilitar botão durante a verificação
            const originalHtml = btnAdd.innerHTML;
            btnAdd.disabled = true;
            
            try {
                let desc = '';
                let prod_id = null;

                if(selTipo.value === 'P') {
                    const opt = selProd.options[selProd.selectedIndex];
                    if(!opt.value) return alert('Selecione um produto');
                    prod_id = opt.value;
                    desc = opt.dataset.nome;
                } else {
                    desc = inpManual.value.trim();
                    if(!desc) return alert('Informe a descrição');
                }

                const qtd = parseFloat(inpQtd.value) || 0;
                const vlr = parseFloat(inpVlr.value.replace('.', '').replace(',', '.')) || 0;

                if(qtd <= 0) return alert('Qtd inválida');

                itens.push({
                    uid: Date.now(),
                    produto_id: prod_id,
                    descricao: desc,
                    quantidade: qtd,
                    valor_unitario: vlr,
                    valor_total: qtd * vlr
                });
                render();
                
            } catch (error) {
                console.error('Erro ao adicionar item:', error);
                alert('Erro ao adicionar item. Por favor, tente novamente.');
            } finally {
                // Restaurar botão
                btnAdd.disabled = false;
                btnAdd.innerHTML = originalHtml;
                
                // CORREÇÃO: Reseta os campos para o estado original "Cadastrado"
                selProd.value = '';
                inpManual.value = '';
                inpVlr.value = '';
                inpQtd.value = '1';
                selTipo.value = 'P';
                document.getElementById('div_prod').style.display = 'block';
                document.getElementById('div_manual').style.display = 'none';
            }
        };

        corpoTabela.onclick = function(e) {
            const btn = e.target.closest('.btn-del');
            if(btn) {
                itens = itens.filter(i => i.uid !== parseInt(btn.dataset.uid));
                render();
            }
        };

        corpoTabela.addEventListener('input', function(e) {
            const campo = e.target.closest('[data-uid][data-field]');
            if (!campo) return;

            const uid = parseInt(campo.dataset.uid, 10);
            const field = campo.dataset.field;
            const item = itens.find((i) => i.uid === uid);
            if (!item) return;

            if (field === 'descricao') {
                item.descricao = campo.value;
                hiddenJson.value = JSON.stringify(itens);
                return;
            }

            if (field === 'quantidade') {
                item.quantidade = parseFloat(campo.value.replace(',', '.')) || 0;
            }

            if (field === 'valor_unitario') {
                item.valor_unitario = parseFloat(campo.value.replace(/\./g, '').replace(',', '.')) || 0;
            }

            item.valor_total = item.quantidade * item.valor_unitario;
            atualizarLinhaEditavel(uid);
        });

        corpoTabela.addEventListener('change', function(e) {
            const campo = e.target.closest('[data-uid][data-field]');
            if (!campo) return;

            const uid = parseInt(campo.dataset.uid, 10);
            const field = campo.dataset.field;
            const item = itens.find((i) => i.uid === uid);
            if (!item) return;

            if (field === 'descricao') {
                item.descricao = campo.value;
                hiddenJson.value = JSON.stringify(itens);
                return;
            }

            if (field === 'quantidade' && item.quantidade <= 0) {
                item.quantidade = 1;
                campo.value = '1';
            }

            if (field === 'valor_unitario' && item.valor_unitario < 0) {
                item.valor_unitario = 0;
                campo.value = '0,00';
            }

            item.valor_total = item.quantidade * item.valor_unitario;
            atualizarLinhaEditavel(uid);
        });

        function calcularTotal() {
            const total = Math.max(0, subtotal - descontoFinal);
            txtTotal.textContent = 'R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits: 2});
            hiddenDesconto.value = descontoFinal;
        }

        function normalizarNumeroBr(valor) {
            return parseFloat(String(valor ?? '').replace(/\./g, '').replace(',', '.')) || 0;
        }

        function atualizarDescontoPorPercentual() {
            const percentual = Math.max(0, parseFloat(descPorc.value) || 0);
            descontoFinal = subtotal * (percentual / 100);
            descValor.value = descontoFinal.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            calcularTotal();
        }

        function atualizarDescontoPorValor() {
            descontoFinal = Math.max(0, normalizarNumeroBr(descValor.value));
            if (descontoFinal > subtotal) {
                descontoFinal = subtotal;
            }

            descValor.value = descontoFinal.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            const percentual = subtotal > 0 ? (descontoFinal / subtotal) * 100 : 0;
            descPorc.value = percentual.toFixed(2);
            calcularTotal();
        }

        function atualizarResumoItens() {
            subtotal = itens.reduce((total, item) => total + (item.valor_total || 0), 0);
            txtSubtotal.textContent = 'R$ ' + subtotal.toLocaleString('pt-BR', {minimumFractionDigits: 2});
            if (document.activeElement !== descValor) {
                descValor.value = descontoFinal.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }
            if (document.activeElement !== descPorc) {
                const percentualAtual = subtotal > 0 ? (descontoFinal / subtotal) * 100 : 0;
                descPorc.value = percentualAtual.toFixed(2);
            }
            calcularTotal();
            hiddenJson.value = JSON.stringify(itens);
        }

        function atualizarLinhaEditavel(uid) {
            const item = itens.find((i) => i.uid === uid);
            if (!item) return;

            const linha = corpoTabela.querySelector(`button.btn-del[data-uid="${uid}"]`)?.closest('tr');
            if (!linha) {
                atualizarResumoItens();
                return;
            }

            const campoQuantidade = linha.querySelector('[data-field="quantidade"]');
            const campoValorUnitario = linha.querySelector('[data-field="valor_unitario"]');
            const totalCell = linha.querySelector('.item-total-cell');

            if (campoQuantidade && document.activeElement !== campoQuantidade) {
                campoQuantidade.value = String(item.quantidade);
            }

            if (campoValorUnitario && document.activeElement !== campoValorUnitario) {
                campoValorUnitario.value = item.valor_unitario.toLocaleString('pt-BR', {minimumFractionDigits: 2});
            }

            if (totalCell) {
                totalCell.textContent = 'R$ ' + item.valor_total.toLocaleString('pt-BR', {minimumFractionDigits: 2});
            }

            atualizarResumoItens();
        }

        function render(foco = null) {
            corpoTabela.innerHTML = '';
            subtotal = 0;
            itens.forEach(i => {
                subtotal += i.valor_total;
                corpoTabela.innerHTML += `
                    <tr>
                        <td class="venda-desc-cell">
                            <textarea
                                class="item-edit-textarea"
                                data-uid="${i.uid}"
                                data-field="descricao"
                                rows="3"
                            >${escapeHtml(i.descricao)}</textarea>
                        </td>
                        <td>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                class="item-edit-input"
                                data-uid="${i.uid}"
                                data-field="quantidade"
                                value="${i.quantidade}"
                            >
                        </td>
                        <td>
                            <input
                                type="text"
                                class="item-edit-input"
                                data-uid="${i.uid}"
                                data-field="valor_unitario"
                                value="${i.valor_unitario.toLocaleString('pt-BR', {minimumFractionDigits: 2})}"
                            >
                        </td>
                        <td class="item-total-cell">R$ ${i.valor_total.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                        <td><button type="button" class="vbtn-sm btn-sm btn-del" data-uid="${i.uid}"><i class="fas fa-times"></i></button></td>
                    </tr>`;
            });
            atualizarResumoItens();

            if (foco && foco.uid && foco.field) {
                const campo = corpoTabela.querySelector(`[data-uid="${foco.uid}"][data-field="${foco.field}"]`);
                if (campo) {
                    campo.focus();
                    const tamanho = campo.value?.length ?? 0;
                    if (typeof campo.setSelectionRange === 'function') {
                        campo.setSelectionRange(tamanho, tamanho);
                    }
                }
            }
        }

        descPorc.addEventListener('input', atualizarDescontoPorPercentual);
        descPorc.addEventListener('change', atualizarDescontoPorPercentual);
        descValor.addEventListener('input', atualizarDescontoPorValor);
        descValor.addEventListener('change', atualizarDescontoPorValor);

        const modalClienteRapido = document.getElementById('clienteRapidoModal');
        const btnAbrirClienteRapido = document.getElementById('btnAbrirClienteRapido');
        const btnFechar = document.getElementById('btnFecharClienteRapido');
        const btnCanc = document.getElementById('btn_canc_modal');
        const btnSalvar = document.getElementById('btn_salvar_cli');

        if (btnAbrirClienteRapido) btnAbrirClienteRapido.onclick = abrirModalClienteRapido;
        if (btnFechar) btnFechar.onclick = fecharModalClienteRapido;
        if (btnCanc) btnCanc.onclick = fecharModalClienteRapido;

        window.addEventListener('click', function(event) {
            if (event.target === modalClienteRapido) {
                fecharModalClienteRapido();
            }
        });

        window.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && modalClienteRapido && modalClienteRapido.classList.contains('show')) {
                fecharModalClienteRapido();
            }
        });

        btnSalvar.onclick = function() {
            const razao = document.getElementById('m_razao').value.trim();
            if(!razao) return alert('Razão Social é obrigatória');

            btnSalvar.disabled = true;
            btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';

            const fd = new FormData();
            fd.append('razao_social', razao);
            fd.append('nome_fantasia', document.getElementById('m_fantasia').value);
            fd.append('responsavel', document.getElementById('m_responsavel').value);
            fd.append('cnpj_cpf', document.getElementById('m_cnpj').value);
            fd.append('inscricao_estadual', document.getElementById('m_ie').value);
            fd.append('email', document.getElementById('m_email').value);
            fd.append('telefone', document.getElementById('m_tel').value);
            fd.append('endereco', document.getElementById('m_endereco').value);
            fetch('<?php echo SITE_URL; ?>/api/clientes.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if(data.success) {
                    const clienteId = data.cliente?.id || data.cliente_id;
                    const razaoSocial = data.cliente?.razao_social || data.razao_social || razao;
                    if (!clienteId) {
                        throw new Error('Resposta do servidor sem ID do cliente.');
                    }
                    const selectCli = document.getElementById('cliente_id');
                    const newOption = new Option(razaoSocial, clienteId, true, true);
                    selectCli.add(newOption);
                    fecharModalClienteRapido();
                } else {
                    alert('Erro: ' + data.message);
                }
            })
            .catch(err => alert(err?.message || 'Erro na comunicação com o servidor.'))
            .finally(() => {
                btnSalvar.disabled = false;
                btnSalvar.innerHTML = 'Salvar Cliente';
            });
        };

        render();
    })();
</script>

<?php include '../../includes/footer_vendedor.php'; ?>


