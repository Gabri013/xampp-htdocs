<?php
/**
 * Importar Cadastro JOTEC — Produtos/Insumos (padrão Nomus).
 * Upload de Excel, validação e importação de matérias primas/insumos.
 *
 * Acesso: master, estoque, gerente
 */

require_once '../../config/config.php';
require_once '../../includes/workflow.php';

$db = getDB();
requirePermission(['master', 'estoque', 'gerente']);

$page_title = 'Importar Cadastro JOTEC';
include '../../includes/header_vendedor.php';
?>
<div class="vend-layout">
    <?php $GLOBALS['modulo_tipo'] = 'estoque'; include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main"><div class="vend-content">

    <div class="dash-head">
        <div>
            <h1 class="dash-head-title"><span class="dash-head-ic" style="background:var(--dash-green)"><i class="fas fa-file-import"></i></span> Importar Cadastro JOTEC</h1>
            <p class="dash-head-sub">Importe matérias primas e insumos a partir de um arquivo Excel</p>
        </div>
    </div>

    <div class="dash-section">
        <div class="dash-section-head"><h2><i class="fas fa-cloud-arrow-up"></i> Upload de Arquivo</h2></div>
        <div class="dash-section-body">
            <form id="formImport" method="POST" enctype="multipart/form-data">
                <div class="dash-row blue" style="border-radius:8px;margin-bottom:16px">
                    <div class="dash-row-title"><i class="fas fa-circle-info"></i> Instruções</div>
                    <ul class="dash-row-sub" style="margin-top:6px;line-height:1.7;padding-left:16px;list-style:disc">
                        <li>Arquivo em formato Excel (.xls ou .xlsx)</li>
                        <li>Primeira linha deve conter os cabeçalhos</li>
                        <li>Colunas esperadas: Código, Descrição, Fornecedor, Preço, Unidade</li>
                        <li>Todas as abas do arquivo serão importadas</li>
                        <li>Validação anti-duplicidade é aplicada automaticamente</li>
                    </ul>
                </div>

                <label for="arquivo" style="display:block;border:2px dashed var(--dash-border);border-radius:12px;padding:32px;text-align:center;cursor:pointer;transition:border-color .15s" id="dropzone">
                    <div style="font-size:38px;color:var(--dash-brand);margin-bottom:8px"><i class="fas fa-file-arrow-up"></i></div>
                    <p style="font-weight:700;color:var(--dash-text)">Clique ou arraste o arquivo aqui</p>
                    <p class="dash-row-sub">Formatos aceitos: .xls, .xlsx</p>
                    <input type="file" name="arquivo" id="arquivo" accept=".xls,.xlsx" required style="display:none">
                </label>
                <div id="fileInfo" class="dash-row green" hidden style="border-radius:8px;margin-top:12px">
                    <div class="dash-row-title"><i class="fas fa-file-excel"></i> <span id="fileName"></span></div>
                </div>

                <div style="display:flex;flex-direction:column;gap:10px;margin:18px 0">
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer"><input type="checkbox" name="validar_duplicidade" checked> <span>Validar duplicidade (não importar registros repetidos)</span></label>
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer"><input type="checkbox" name="atualizar_existentes" checked> <span>Atualizar registros existentes (por código)</span></label>
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer"><input type="checkbox" name="registrar_auditoria" checked> <span>Registrar auditoria (quem, quando, o quê)</span></label>
                </div>

                <div class="dash-form-row">
                    <button type="button" class="dash-btn slate" onclick="enviarImport('preview')"><i class="fas fa-eye"></i> Pré-visualizar</button>
                    <button type="button" class="dash-btn green" onclick="enviarImport('importar')"><i class="fas fa-download"></i> Importar Agora</button>
                </div>
            </form>
        </div>
    </div>

    <div id="resultado-import" class="dash-section" hidden>
        <div class="dash-section-head"><h2><i class="fas fa-clipboard-check"></i> Resultado</h2></div>
        <div class="dash-section-body" id="resultado-conteudo"></div>
    </div>

    <div class="dash-grid cols-2">
        <div class="dash-section" style="margin:0">
            <div class="dash-section-head"><h2><i class="fas fa-table-columns"></i> Estrutura esperada</h2></div>
            <div class="dash-section-body">
                <div class="dash-list">
                    <div class="dash-row"><div class="dash-row-top"><span class="dash-row-title">Coluna A</span><span class="dash-chip blue">Código</span></div></div>
                    <div class="dash-row"><div class="dash-row-top"><span class="dash-row-title">Coluna B</span><span class="dash-chip blue">Descrição</span></div></div>
                    <div class="dash-row"><div class="dash-row-top"><span class="dash-row-title">Coluna C</span><span class="dash-chip blue">Fornecedor</span></div></div>
                    <div class="dash-row"><div class="dash-row-top"><span class="dash-row-title">Coluna D</span><span class="dash-chip blue">Preço</span></div></div>
                    <div class="dash-row"><div class="dash-row-top"><span class="dash-row-title">Coluna E</span><span class="dash-chip blue">Unidade</span></div></div>
                </div>
            </div>
        </div>
        <div class="dash-section" style="margin:0">
            <div class="dash-section-head"><h2><i class="fas fa-circle-check"></i> Validações aplicadas</h2></div>
            <div class="dash-section-body">
                <ul style="list-style:none;display:flex;flex-direction:column;gap:10px;font-size:13px;color:var(--dash-text)">
                    <li><i class="fas fa-check" style="color:var(--dash-green)"></i> Código único (sem duplicar)</li>
                    <li><i class="fas fa-check" style="color:var(--dash-green)"></i> Descrição obrigatória</li>
                    <li><i class="fas fa-check" style="color:var(--dash-green)"></i> Fornecedor válido</li>
                    <li><i class="fas fa-check" style="color:var(--dash-green)"></i> Preço maior que zero</li>
                    <li><i class="fas fa-check" style="color:var(--dash-green)"></i> Unidade válida</li>
                    <li><i class="fas fa-check" style="color:var(--dash-green)"></i> Anti-duplicidade por HASH</li>
                </ul>
            </div>
        </div>
    </div>

    </div></div>
</div>

<script>
const fileInput = document.getElementById('arquivo');
const fileInfo = document.getElementById('fileInfo');
const fileName = document.getElementById('fileName');
const dropzone = document.getElementById('dropzone');

['dragover'].forEach(ev => document.addEventListener(ev, e => { e.preventDefault(); dropzone.style.borderColor = 'var(--dash-brand)'; }));
document.addEventListener('dragleave', () => { dropzone.style.borderColor = 'var(--dash-border)'; });
document.addEventListener('drop', e => {
    e.preventDefault(); dropzone.style.borderColor = 'var(--dash-border)';
    if (e.dataTransfer.files.length) { fileInput.files = e.dataTransfer.files; updateFileInfo(); }
});
fileInput.addEventListener('change', updateFileInfo);
function updateFileInfo() {
    if (fileInput.files.length) { fileName.textContent = fileInput.files[0].name; fileInfo.hidden = false; }
}

const escI = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

async function enviarImport(acao) {
    if (!fileInput.files.length) { alert('Selecione um arquivo primeiro.'); return; }
    const box = document.getElementById('resultado-import');
    const cont = document.getElementById('resultado-conteudo');
    box.hidden = false;
    cont.innerHTML = '<div class="dash-empty"><i class="fas fa-spinner fa-spin"></i> Processando…</div>';
    try {
        const fd = new FormData(document.getElementById('formImport'));
        fd.append('acao', acao);
        const data = await (await fetch('<?= SITE_URL ?>/api/importar_jotec.php', { method: 'POST', body: fd })).json();
        if (data.erro) { cont.innerHTML = `<div class="dash-empty err"><i class="fas fa-circle-xmark"></i> ${escI(data.mensagem || 'Erro na importação')}</div>`; return; }
        const linhas = data.total ?? data.importados ?? data.registros ?? '';
        cont.innerHTML = `<div class="dash-row ${acao === 'importar' ? 'green' : 'blue'}" style="border-radius:8px">
            <div class="dash-row-title"><i class="fas fa-circle-check"></i> ${acao === 'importar' ? 'Importação concluída' : 'Pré-visualização gerada'}</div>
            <div class="dash-row-sub" style="margin-top:6px;white-space:pre-wrap">${escI(JSON.stringify(data, null, 2))}</div>
        </div>`;
    } catch (err) {
        cont.innerHTML = `<div class="dash-empty err">Erro: ${escI(err.message)}</div>`;
    }
}
</script>
<?php include '../../includes/footer_vendedor.php'; ?>
