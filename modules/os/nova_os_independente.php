<?php
require_once '../../config/config.php';
require_once '../../includes/engenharia.php';
requirePermission(['master', 'vendedor', 'projetista', 'gerente']);

$page_title = 'Nova O.S. Independente';
$db = getDB();
ensureOrdensServicoIndependentesSchema($db);
ensureEngenhariaSchema($db);
$usuarioTipo = $_SESSION['usuario_tipo'] ?? '';
$usuario = getCurrentUser();
$voltarUrl = in_array($usuarioTipo, ['master', 'gerente'], true) ? 'gerente.php' : ($usuarioTipo === 'projetista' ? '../projetista/index.php' : 'vendedor.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_cliente = $_POST['tipo_cliente'] ?? 'cadastro';
    $cliente_id = (int) ($_POST['cliente_id'] ?? 0);
    $cliente_manual_nome = sanitize($_POST['cliente_manual_nome'] ?? '');
    $data_inicio = dateToMysql($_POST['data_inicio'] ?? '');
    $data_termino = !empty($_POST['data_termino']) ? dateToMysql($_POST['data_termino']) : null;
    $prioridade = $_POST['prioridade'] ?? 'verde';
    $fluxo_os = $_POST['fluxo_os'] ?? 'projetista';
    $etapa_inicial = normalizarEtapaEngenharia($_POST['etapa_inicial'] ?? '') ?? 'corte';
    $obs_corte_dobra = sanitize($_POST['obs_corte_dobra'] ?? '');
    $obs_solda = sanitize($_POST['obs_solda'] ?? '');
    $observacoes_gerais = sanitize($_POST['observacoes_gerais'] ?? '');
    $itens = json_decode($_POST['itens_json'] ?? '[]', true);

    $fluxo_os = in_array($fluxo_os, ['projetista', 'direto'], true) ? $fluxo_os : 'projetista';

    $erroFormulario = '';
    if (empty($data_inicio)) {
        $erroFormulario = 'Preencha a data de início da O.S.';
    } elseif ($tipo_cliente === 'manual' && $cliente_manual_nome === '') {
        $erroFormulario = 'Informe o nome do cliente manual.';
    } elseif ($tipo_cliente !== 'manual' && $cliente_id <= 0) {
        $erroFormulario = 'Selecione um cliente cadastrado.';
    } elseif ($fluxo_os === 'direto' && $etapa_inicial === '') {
        $erroFormulario = 'Selecione o setor inicial da produção.';
    } elseif (empty($itens)) {
        $erroFormulario = 'Adicione pelo menos um item/produto na O.S.';
    }

    if ($erroFormulario !== '') {
        setError($erroFormulario);
    } else {
        try {
            $db->beginTransaction();

            if ($tipo_cliente === 'manual') {
                $stmtCliente = $db->prepare("
                    INSERT INTO clientes (razao_social, nome_fantasia)
                    VALUES (?, ?)
                ");
                $stmtCliente->execute([$cliente_manual_nome, $cliente_manual_nome]);
                $cliente_id = (int) $db->lastInsertId();
            }

$numero_os = getNextNumber('ordens_servico', 'OS-I');
            $isProjetista = $usuarioTipo === 'projetista';
            $status_inicial = $isProjetista ? 'em_projeto' : ($fluxo_os === 'direto' ? 'em_producao' : 'pendente');
            $etapa_os = $isProjetista ? 'autorizacao' : ($fluxo_os === 'direto' ? $etapa_inicial : 'autorizacao');

            $stmt = $db->prepare("
                INSERT INTO ordens_servico
                (numero, venda_id, cliente_id, data_inicio, data_termino, prioridade, status, etapa_atual, observacoes_gerais, observacoes_corte_dobra, observacoes_solda)
                VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $numero_os,
                $cliente_id,
                $data_inicio,
                $data_termino,
                $prioridade,
                $status_inicial,
                $etapa_os,
                $observacoes_gerais,
                $fluxo_os === 'direto' ? $obs_corte_dobra : '',
                $fluxo_os === 'direto' ? $obs_solda : ''
            ]);
            $os_id = (int) $db->lastInsertId();

            $stmtItem = $db->prepare("
                INSERT INTO os_itens (os_id, produto_id, descricao_manual, quantidade)
                VALUES (?, ?, ?, ?)
            ");
            foreach ($itens as $item) {
                $produtoId = !empty($item['produto_id']) ? (int) $item['produto_id'] : null;
                $descricao = trim((string) ($item['descricao'] ?? ''));
                $quantidade = (float) ($item['quantidade'] ?? 0);

                if ($quantidade <= 0) {
                    continue;
                }

                $stmtItem->execute([$os_id, $produtoId, $descricao, $quantidade]);
            }

            if (!empty($_FILES['arquivos']['name'][0])) {
                foreach ($_FILES['arquivos']['name'] as $key => $nome) {
                    if (($_FILES['arquivos']['error'][$key] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        continue;
                    }

                    $upload = uploadFile([
                        'name' => $_FILES['arquivos']['name'][$key],
                        'type' => $_FILES['arquivos']['type'][$key],
                        'tmp_name' => $_FILES['arquivos']['tmp_name'][$key],
                        'error' => $_FILES['arquivos']['error'][$key],
                        'size' => $_FILES['arquivos']['size'][$key]
                    ], 'projetos');

                    if ($upload['success']) {
                        $stmtArquivo = $db->prepare("
                            INSERT INTO os_arquivos (os_id, tipo, nome_original, nome_arquivo, usuario_id)
                            VALUES (?, 'venda', ?, ?, ?)
                        ");
                        $stmtArquivo->execute([$os_id, $upload['original_name'], $upload['filename'], $_SESSION['usuario_id']]);
                    }
                }
            }

            if ($fluxo_os === 'direto') {
                $stmtHistorico = $db->prepare("
                    INSERT INTO os_historico_status
                    (os_id, status_anterior, status_novo, usuario_id, observacao)
                    VALUES (?, 'pendente', 'em_producao', ?, ?)
                ");
                $stmtHistorico->execute([
                    $os_id,
                    $_SESSION['usuario_id'],
                    'O.S. independente criada com início direto na etapa: ' . getEtapaLabel($etapa_inicial),
                ]);
            }

            $db->commit();
            setSuccess('O.S. independente ' . $numero_os . ' criada com sucesso!');
            header('Location: ' . $voltarUrl);
            exit;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            setError('Erro ao criar a O.S. independente: ' . $e->getMessage());
        }
    }
}

$clientes = $db->query("SELECT id, razao_social FROM clientes ORDER BY razao_social")->fetchAll();
$produtos = $db->query("SELECT id, codigo, nome FROM produtos WHERE status = 'ativo' ORDER BY nome")->fetchAll();

include '../../includes/header_vendedor.php';
?>

<div class="vend-layout">
    <?php $GLOBALS['modulo_tipo'] = 'projetista'; $current_module = 'os'; include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-page-head">
            <div><h1 class="vend-page-title">Nova O.S. Independente</h1></div>
            <a href="<?php echo $voltarUrl; ?>" class="vbtn-sm"><i class="fas fa-arrow-left"></i> Voltar</a>
        </div>

        <div class="vend-content">
        <div class="vend-card"><div class="vend-card-body" style="padding:20px">
        <form method="POST" enctype="multipart/form-data">
            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:12px">
                <div style="flex:2">
                    <label>Cliente *</label>
                    <select name="tipo_cliente" id="tipo_cliente" class="form-control" style="margin-bottom:10px">
                        <option value="cadastro">Puxar do cadastro</option>
                        <option value="manual">Digitar nome do cliente</option>
                    </select>
                    <div id="cliente_cadastro_box">
                        <select name="cliente_id" id="cliente_id" class="form-control">
                            <option value="">Selecione...</option>
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?php echo (int) $cliente['id']; ?>"><?php echo htmlspecialchars($cliente['razao_social']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="cliente_manual_box" style="display:none"><input type="text" name="cliente_manual_nome" id="cliente_manual_nome" class="form-control" placeholder="Nome do cliente"></div>
                </div>
                <div style="flex:1"><label>Data Início *</label><input type="date" name="data_inicio" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div>
                <div style="flex:1"><label>Data Término</label><input type="date" name="data_termino" class="form-control"></div>
            </div>
            
<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:12px">
                <div style="flex:1"><label>Prioridade</label>
                    <select name="prioridade" class="form-control">
                        <option value="verde">🟢 Normal</option>
                        <option value="amarelo">🟡 Emergente</option>
                        <option value="vermelho">🔴 Urgente</option>
                    </select>
                </div>
                <?php if ($usuarioTipo !== 'projetista'): ?>
                <div style="flex:1"><label>Enviar O.S. para</label>
                    <select name="fluxo_os" id="fluxo_os" class="form-control">
                        <option value="projetista">Projetista</option>
                        <option value="direto">Iniciar direto na produção</option>
                    </select>
                </div>
                <?php else: ?>
                <input type="hidden" name="fluxo_os" value="projetista">
                <?php endif; ?>
            </div>
            
            <div id="etapa_inicial_box" style="display:none;margin-bottom:12px">
                <label>Setor inicial do fluxo</label>
                <select name="etapa_inicial" id="etapa_inicial" class="form-control">
                    <option value="corte">Corte</option>
                    <option value="dobra">Dobra</option>
                    <option value="solda">Solda</option>
                    <option value="refrigeracao">Refrigeração</option>
                    <option value="acabamento">Acabamento</option>
                    <option value="finalizacao">Finalização</option>
                    <option value="montagem">Montagem</option>
                </select>
            </div>
            
            <div id="observacoes_diretas_box" style="display:none;margin-bottom:12px">
                <div style="display:flex;gap:12px">
                    <div style="flex:1"><label>Observações para Corte / Dobra</label><textarea name="obs_corte_dobra" class="form-control" rows="4"></textarea></div>
                    <div style="flex:1"><label>Observações para Solda / Montagem</label><textarea name="obs_solda" class="form-control" rows="4"></textarea></div>
                </div>
            </div>
            
            <div style="margin-bottom:12px"><label>Descrição / Observações Gerais</label><textarea name="observacoes_gerais" class="form-control" rows="5" placeholder="Descreva o serviço, medidas, referência..."></textarea></div>
            
            <div style="margin-bottom:12px"><label>Arquivos de Referência</label><input type="file" name="arquivos[]" class="form-control" multiple></div>
            
            <hr style="margin:20px 0">
            
            <h4 style="margin-bottom:12px">Itens da O.S.</h4>
            
            <div style="display:flex;gap:10px;align-items:end;margin-bottom:12px">
                <div style="flex:2">
                    <label>Produto</label>
                    <select id="sel_tipo" class="form-control" style="width:140px;margin-bottom:6px">
                        <option value="P">Cadastrado</option>
                        <option value="M">Manual</option>
                    </select>
                    <div id="div_prod"><select id="sel_prod" class="form-control">
                        <option value="">Selecione...</option>
                        <?php foreach ($produtos as $produto): ?>
                            <option value="<?php echo (int) $produto['id']; ?>" data-nome="<?php echo htmlspecialchars(trim($produto['codigo'] . ' - ' . $produto['nome'])); ?>">
                                <?php echo htmlspecialchars($produto['codigo'] . ' - ' . $produto['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select></div>
                    <div id="div_manual" style="display:none"><textarea id="inp_manual" class="form-control" rows="2" placeholder="Descrição..."></textarea></div>
                </div>
                <div style="flex:0.6"><label>Qtd</label><input type="number" id="inp_qtd" class="form-control" value="1" step="0.01"></div>
                <button type="button" class="vbtn-sm" id="btn_add_item"><i class="fas fa-plus"></i></button>
            </div>
            
            <table class="vend-table" style="margin-bottom:20px">
                <thead><tr><th>Descrição</th><th>Qtd</th><th></th></tr></thead>
                <tbody id="corpo_tabela"></tbody>
            </table>
            
            <input type="hidden" name="itens_json" id="itens_json" value="[]">
            
            <button type="submit" class="vbtn-sm vbtn-brand">Criar O.S. Independente</button>
        </form>
        </div></div></div>
    </div>
</div>

<script>
(function() {
    const tipoCliente = document.getElementById('tipo_cliente');
    const clienteCadastroBox = document.getElementById('cliente_cadastro_box');
    const clienteManualBox = document.getElementById('cliente_manual_box');
    const clienteId = document.getElementById('cliente_id');
    const clienteManualNome = document.getElementById('cliente_manual_nome');

    const selTipo = document.getElementById('sel_tipo');
    const selProd = document.getElementById('sel_prod');
    const inpManual = document.getElementById('inp_manual');
    const inpQtd = document.getElementById('inp_qtd');
    const corpoTabela = document.getElementById('corpo_tabela');
    const hiddenItens = document.getElementById('itens_json');
    const btnAdd = document.getElementById('btn_add_item');
    const fluxoOs = document.getElementById('fluxo_os');
    const etapaInicialBox = document.getElementById('etapa_inicial_box');
    const observacoesDiretasBox = document.getElementById('observacoes_diretas_box');

    let itens = [];

    function atualizarModoCliente() {
        const manual = tipoCliente.value === 'manual';
        clienteCadastroBox.style.display = manual ? 'none' : 'block';
        clienteManualBox.style.display = manual ? 'block' : 'none';
    }

    function atualizarModoItem() {
        document.getElementById('div_prod').style.display = selTipo.value === 'P' ? 'block' : 'none';
        document.getElementById('div_manual').style.display = selTipo.value === 'M' ? 'block' : 'none';
    }

    function atualizarFluxoOS() {
        const direto = fluxoOs && fluxoOs.value === 'direto';
        etapaInicialBox.style.display = direto ? 'block' : 'none';
        observacoesDiretasBox.style.display = direto ? 'block' : 'none';
    }

    function renderizarTabela() {
        hiddenItens.value = JSON.stringify(itens);
        if (!itens.length) {
            corpoTabela.innerHTML = '<tr><td colspan="3" class="text-center">Nenhum item adicionado.</td></tr>';
            return;
        }
        corpoTabela.innerHTML = itens.map((item, index) => `
            <tr>
                <td>${item.descricao}</td>
                <td>${Number(item.quantidade || 0).toLocaleString('pt-BR')}</td>
                <td><button type="button" class="vbtn-sm btn-danger" data-index="${index}"><i class="fas fa-trash"></i></button></td>
            </tr>
        `).join('');
        corpoTabela.querySelectorAll('button[data-index]').forEach((botao) => {
            botao.addEventListener('click', function() {
                itens.splice(Number(this.dataset.index), 1);
                renderizarTabela();
            });
        });
    }

    btnAdd.addEventListener('click', function() {
        const quantidade = parseFloat(inpQtd.value || '0');
        let produtoId = null;
        let descricao = '';

        if (selTipo.value === 'P') {
            if (!selProd.value) { alert('Selecione um produto cadastrado.'); return; }
            produtoId = Number(selProd.value);
            descricao = selProd.options[selProd.selectedIndex].dataset.nome || selProd.options[selProd.selectedIndex].text;
        } else {
            descricao = (inpManual.value || '').trim();
            if (!descricao) { alert('Informe a descrição manual do item.'); return; }
        }

        if (quantidade <= 0) { alert('Informe uma quantidade válida.'); return; }

        itens.push({ produto_id: produtoId, descricao: descricao, quantidade: quantidade });
        selProd.value = '';
        inpManual.value = '';
        inpQtd.value = '1';
        renderizarTabela();
    });

    tipoCliente.addEventListener('change', atualizarModoCliente);
    selTipo.addEventListener('change', atualizarModoItem);
    if (fluxoOs) fluxoOs.addEventListener('change', atualizarFluxoOS);

    atualizarModoCliente();
    atualizarModoItem();
    atualizarFluxoOS();
    renderizarTabela();
})();
</script>

<?php include '../../includes/footer_vendedor.php'; ?>
