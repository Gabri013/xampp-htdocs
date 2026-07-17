<?php
/**
 * Gerador de Etiquetas - Interface para imprimir QR-codes
 *
 * Inspirado no Nomus - simples, visual, pronto para imprimir
 * Acesso: master, gerente, producao
 */

require_once '../../config/config.php';
require_once '../../includes/workflow.php';

$page_title = 'Gerador de Etiquetas';
$db = getDB();
requirePermission(['master', 'gerente', 'dashboard_producao', 'producao']);

// ===== BUSCAR ÚLTIMAS O.S. PARA GERAR ETIQUETAS =====
$os_lista = [];
$etiqueta_atual = null;

$stmt = $db->query("SELECT os.*, c.razao_social
    FROM ordens_servico os
    INNER JOIN clientes c ON os.cliente_id = c.id
    WHERE os.status IN ('em_producao', 'liberada')
    ORDER BY os.created_at DESC
    LIMIT 15");
$os_lista = $stmt->fetchAll();

// Se uma O.S. foi selecionada, buscar suas etiquetas
if (isset($_GET['os_id'])) {
    $os_id = (int)$_GET['os_id'];
    $stmt = $db->prepare("SELECT os.*, c.razao_social
        FROM ordens_servico os
        INNER JOIN clientes c ON os.cliente_id = c.id
        WHERE os.id = ?");
    $stmt->execute([$os_id]);
    $etiqueta_atual = $stmt->fetch();
}

include '../../includes/header_vendedor.php';
?>

<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-page-head">
            <h1 class="vend-page-title">🏷️ Gerador de Etiquetas</h1>
        </div>
        <div class="vend-content">

<style>
    .etiqueta-container {
        display: grid;
        grid-template-columns: 1fr 2fr;
        gap: 24px;
    }

    /* SELETOR DE O.S. */
    .etiqueta-seletor {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 10px;
        padding: 16px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .etiqueta-os-item {
        padding: 12px;
        border: 2px solid #ddd;
        border-radius: 8px;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
        transition: all 0.2s;
        text-align: center;
    }

    .etiqueta-os-item:hover {
        border-color: #3b82f6;
        background: #eff6ff;
        transform: translateY(-2px);
    }

    .etiqueta-os-numero {
        font-size: 14px;
        font-weight: 700;
        color: #3b82f6;
    }

    .etiqueta-os-cliente {
        font-size: 11px;
        color: #666;
        margin-top: 4px;
    }

    /* PREVIEW DA ETIQUETA */
    .etiqueta-preview {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow: hidden;
    }

    .etiqueta-header {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        padding: 20px;
        text-align: center;
    }

    .etiqueta-header-titulo {
        font-size: 16px;
        font-weight: 700;
        margin: 0;
    }

    .etiqueta-header-cliente {
        font-size: 12px;
        opacity: 0.9;
        margin-top: 4px;
    }

    .etiqueta-content {
        padding: 24px;
        text-align: center;
    }

    .etiqueta-qr {
        width: 240px;
        height: 240px;
        margin: 0 auto 20px;
        padding: 12px;
        background: white;
        border: 2px solid #ddd;
        border-radius: 8px;
    }

    .etiqueta-qr img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .etiqueta-numero {
        font-size: 16px;
        font-weight: 700;
        color: #1f2937;
        margin: 16px 0 0 0;
    }

    .etiqueta-info {
        font-size: 12px;
        color: #666;
        margin-top: 8px;
        padding: 12px;
        background: #f3f4f6;
        border-radius: 6px;
    }

    .etiqueta-acoes {
        display: flex;
        gap: 12px;
        margin-top: 20px;
        flex-wrap: wrap;
        justify-content: center;
    }

    .etiqueta-botao {
        padding: 12px 20px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .etiqueta-botao-imprimir {
        background: #3b82f6;
        color: white;
    }

    .etiqueta-botao-imprimir:hover {
        background: #2563eb;
        transform: translateY(-2px);
    }

    .etiqueta-botao-baixar {
        background: #10b981;
        color: white;
    }

    .etiqueta-botao-baixar:hover {
        background: #059669;
        transform: translateY(-2px);
    }

    /* PREVIEW IMPRESSÃO */
    @media print {
        body > * {
            display: none !important;
        }

        .etiqueta-print-page {
            display: block !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .etiqueta-print-item {
            width: 8cm;
            height: 10cm;
            margin: 0.5cm;
            page-break-inside: avoid;
            border: 1px solid #ddd;
        }
    }

    .etiqueta-print-page {
        display: none;
    }

    .etiqueta-print-item {
        width: 8cm;
        height: 10cm;
        padding: 8px;
        border: 2px dashed #ddd;
        display: inline-block;
        margin: 8px;
        vertical-align: top;
        background: white;
    }

    .etiqueta-print-qr {
        width: 100%;
        height: 70%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .etiqueta-print-qr img {
        max-width: 100%;
        max-height: 100%;
    }

    .etiqueta-print-numero {
        font-size: 10px;
        font-weight: 700;
        text-align: center;
        margin-top: 4px;
        word-break: break-all;
    }

    /* GRID DE ETIQUETAS */
    .etiqueta-lista {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 16px;
        padding: 20px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .etiqueta-item {
        border: 2px solid #ddd;
        border-radius: 8px;
        padding: 12px;
        text-align: center;
    }

    .etiqueta-item-qr {
        width: 100%;
        margin-bottom: 8px;
    }

    .etiqueta-item-numero {
        font-size: 11px;
        font-weight: 600;
        color: #1f2937;
    }

    @media (max-width: 1024px) {
        .etiqueta-container {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="vend-card">
    <div class="vend-card-head">
        <h3>📋 Selecione uma O.S. para Gerar Etiqueta</h3>
    </div>
    <div class="vend-card-body">
        <div class="etiqueta-container">
            <!-- SELETOR -->
            <div>
                <div class="etiqueta-seletor">
                    <?php foreach ($os_lista as $os): ?>
                        <a href="?os_id=<?= $os['id'] ?>" class="etiqueta-os-item">
                            <div class="etiqueta-os-numero">OS <?= htmlspecialchars($os['numero']) ?></div>
                            <div class="etiqueta-os-cliente"><?= htmlspecialchars(substr($os['razao_social'], 0, 20)) ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- PREVIEW / GERAR -->
            <?php if ($etiqueta_atual): ?>
                <div>
                    <div class="etiqueta-preview">
                        <div class="etiqueta-header">
                            <p class="etiqueta-header-titulo">OS <?= htmlspecialchars($etiqueta_atual['numero']) ?></p>
                            <p class="etiqueta-header-cliente"><?= htmlspecialchars($etiqueta_atual['razao_social']) ?></p>
                        </div>
                        <div class="etiqueta-content">
                            <div class="etiqueta-qr" id="qr-container">
                                <img id="qr-image" src="" alt="QR-code" style="width: 100%; height: 100%;">
                            </div>
                            <p class="etiqueta-numero" id="os-numero">OS <?= htmlspecialchars($etiqueta_atual['numero']) ?></p>
                            <p class="etiqueta-info">
                                <strong>Status:</strong> <?= htmlspecialchars($etiqueta_atual['status']) ?><br>
                                <strong>Criada:</strong> <?= date('d/m/Y', strtotime($etiqueta_atual['created_at'])) ?>
                            </p>
                            <div class="etiqueta-acoes">
                                <button class="etiqueta-botao etiqueta-botao-imprimir" onclick="imprimirEtiqueta()">
                                    🖨️ Imprimir
                                </button>
                                <button class="etiqueta-botao etiqueta-botao-baixar" onclick="baixarQR()">
                                    ⬇️ Baixar QR
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px; color: #999;">
                    <p style="font-size: 48px;">🏷️</p>
                    <p>Clique em uma O.S. para gerar etiqueta</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($etiqueta_atual): ?>
    <div class="vend-card" style="margin-top: 24px;">
        <div class="vend-card-head">
            <h3>📌 Histórico de Etiquetas</h3>
        </div>
        <div class="vend-card-body">
            <div id="etiquetas-lista"></div>
        </div>
    </div>
<?php endif; ?>

</div>
        </div>
    </div>
</div>

<?php if ($etiqueta_atual): ?>
<script>
    const osId = <?= $etiqueta_atual['id'] ?>;
    const osNumero = '<?= htmlspecialchars($etiqueta_atual['numero']) ?>';

    // Carregar QR-code
    function carregarQR() {
        fetch('<?= SITE_URL ?>/api/etiqueta.php', {
            method: 'POST',
            body: new FormData(new DOMParser().parseFromString(
                `<form><input name="acao" value="gerar_qr"><input name="os_id" value="${osId}"></form>`,
                'text/html'
            ).forms[0])
        })
        .then(r => r.json())
        .then(data => {
            if (data.sucesso) {
                document.getElementById('qr-image').src = data.qr_data_uri;
            }
        });

        // Carregar lista de etiquetas
        fetch('<?= SITE_URL ?>/api/etiqueta.php?acao=listar&os_id=' + osId)
            .then(r => r.json())
            .then(data => {
                if (data.sucesso && data.etiquetas.length > 0) {
                    let html = '<div class="etiqueta-lista">';
                    data.etiquetas.forEach(e => {
                        html += `<div class="etiqueta-item">
                            <img src="${e.qr_svg}" class="etiqueta-item-qr" alt="QR">
                            <div class="etiqueta-item-numero">${e.conteudo}</div>
                        </div>`;
                    });
                    html += '</div>';
                    document.getElementById('etiquetas-lista').innerHTML = html;
                }
            });
    }

    function imprimirEtiqueta() {
        const qrUrl = document.getElementById('qr-image').src;
        let html = `
            <div class="etiqueta-print-item">
                <div class="etiqueta-print-qr">
                    <img src="${qrUrl}" alt="QR">
                </div>
                <div class="etiqueta-print-numero">OS ${osNumero}</div>
            </div>
        `;

        let win = window.open('', '', 'height=400,width=600');
        win.document.write(html);
        win.document.close();
        setTimeout(() => win.print(), 500);

        // Registrar impressão
        fetch('<?= SITE_URL ?>/api/etiqueta.php', {
            method: 'POST',
            body: new FormData(new DOMParser().parseFromString(
                `<form><input name="acao" value="registrar_impressao"><input name="etiqueta_id" value="1"></form>`,
                'text/html'
            ).forms[0])
        });
    }

    function baixarQR() {
        const link = document.createElement('a');
        link.href = document.getElementById('qr-image').src;
        link.download = `etiqueta_os_${osNumero}.png`;
        link.click();
    }

    // Carregar ao abrir
    carregarQR();
</script>
<?php endif; ?>

<?php include '../../includes/footer_vendedor.php'; ?>
