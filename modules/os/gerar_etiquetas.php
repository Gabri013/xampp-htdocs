<?php
/**
 * Gerador de Etiquetas - Interface para imprimir QR-codes e Etiquetas
 *
 * Funcionalidades:
 * - Gerar QR-codes para O.S.
 * - Gerar QR-codes para O.P.
 * - Imprimir em formatos A4, 10x15cm
 * - Rastreamento de impressões
 * - Integração com Estoque
 *
 * Inspirado no Nomus - simples, visual, pronto para imprimir
 * Acesso: master, gerente, producao, projetista
 */

require_once '../../config/config.php';
require_once '../../includes/workflow.php';

$page_title = 'Gerador de Etiquetas e QR-codes';
$db = getDB();
requirePermission(['master', 'gerente', 'dashboard_producao', 'producao', 'projetista']);

// ───────────────────────────────────────────────────────────────
// Processar ações
// ───────────────────────────────────────────────────────────────

$acao = $_POST['acao'] ?? $_GET['acao'] ?? null;

if ($acao === 'gerar_etiqueta' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $os_id = (int)($_POST['os_id'] ?? 0);
    $formato = $_POST['formato'] ?? '10x15';

    if ($os_id > 0) {
        header('Content-Type: application/json');
        $stmt = $db->prepare("SELECT numero FROM ordens_servico WHERE id = ?");
        $stmt->execute([$os_id]);
        $os = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($os) {
            $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=" . urlencode("OS|" . $os['numero'] . "|" . $os_id);
            echo json_encode(['sucesso' => true, 'qr_url' => $qr_url, 'numero' => $os['numero']]);
        } else {
            echo json_encode(['sucesso' => false, 'erro' => 'O.S. não encontrada']);
        }
    }
    exit;
}

// ===== BUSCAR ÚLTIMAS O.S. PARA GERAR ETIQUETAS =====
$os_lista = [];
$etiqueta_atual = null;
$op_lista = [];

$stmt = $db->query("SELECT os.*, c.razao_social
    FROM ordens_servico os
    INNER JOIN clientes c ON os.cliente_id = c.id
    WHERE os.status IN ('em_producao', 'liberada', 'em_pausa')
    ORDER BY os.created_at DESC
    LIMIT 20");
$os_lista = $stmt->fetchAll();

// Buscar últimas O.P.s também
try {
    $stmt = $db->query("SELECT op.numero, os.numero as os_numero, c.razao_social
        FROM ordens_producao op
        LEFT JOIN ordens_servico os ON os.id = op.os_id
        LEFT JOIN clientes c ON c.id = os.cliente_id
        WHERE op.status IN ('pendente', 'em_producao')
        ORDER BY op.criado_em DESC
        LIMIT 10");
    $op_lista = $stmt->fetchAll();
} catch (Exception $e) {}

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
    .etiqueta-abas {
        display: flex;
        gap: 12px;
        margin-bottom: 20px;
        border-bottom: 2px solid #e5e7eb;
    }

    .etiqueta-aba {
        padding: 12px 20px;
        background: none;
        border: none;
        border-bottom: 3px solid transparent;
        cursor: pointer;
        font-weight: 600;
        font-size: 13px;
        color: #666;
        transition: all 0.2s;
    }

    .etiqueta-aba.ativo {
        color: #3b82f6;
        border-bottom-color: #3b82f6;
    }

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
        max-height: 600px;
        overflow-y: auto;
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
        <h3>📋 Gerador de Etiquetas</h3>
    </div>
    <div class="vend-card-body">
        <!-- ABAS DE NAVEGAÇÃO -->
        <div class="etiqueta-abas">
            <button class="etiqueta-aba ativo" onclick="mudarAba('os', this)">📦 Ordens de Serviço</button>
            <button class="etiqueta-aba" onclick="mudarAba('op', this)">🏭 Ordens de Produção</button>
            <button class="etiqueta-aba" onclick="mudarAba('historico', this)">📊 Histórico</button>
        </div>

        <!-- ABA: ORDENS DE SERVIÇO -->
        <div id="aba-os" class="etiqueta-aba-conteudo">
            <div class="etiqueta-container">
                <!-- SELETOR -->
                <div>
                    <div class="etiqueta-seletor">
                        <?php if (empty($os_lista)): ?>
                            <div style="color: #999; text-align: center; padding: 20px;">
                                Nenhuma O.S. disponível
                            </div>
                        <?php else: ?>
                            <?php foreach ($os_lista as $os): ?>
                                <a href="?os_id=<?= $os['id'] ?>" class="etiqueta-os-item">
                                    <div class="etiqueta-os-numero">OS <?= htmlspecialchars($os['numero']) ?></div>
                                    <div class="etiqueta-os-cliente"><?= htmlspecialchars(substr($os['razao_social'], 0, 20)) ?></div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
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

        <!-- ABA: ORDENS DE PRODUÇÃO -->
        <div id="aba-op" class="etiqueta-aba-conteudo" style="display: none;">
            <div class="etiqueta-container">
                <div>
                    <div class="etiqueta-seletor">
                        <?php if (empty($op_lista)): ?>
                            <div style="color: #999; text-align: center; padding: 20px;">
                                Nenhuma O.P. disponível
                            </div>
                        <?php else: ?>
                            <?php foreach ($op_lista as $op): ?>
                                <div class="etiqueta-os-item" onclick="gerarQROp('<?= htmlspecialchars($op['numero']) ?>', '<?= htmlspecialchars($op['os_numero']) ?>')">
                                    <div class="etiqueta-os-numero">OP <?= htmlspecialchars($op['numero']) ?></div>
                                    <div class="etiqueta-os-cliente"><?= htmlspecialchars(substr($op['razao_social'] ?? '-', 0, 20)) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <div class="etiqueta-preview">
                        <div class="etiqueta-header">
                            <p class="etiqueta-header-titulo">OP <span id="op-numero-preview">-</span></p>
                            <p class="etiqueta-header-cliente" id="op-cliente-preview">-</p>
                        </div>
                        <div class="etiqueta-content">
                            <div class="etiqueta-qr" id="qr-container-op">
                                <img id="qr-image-op" src="" alt="QR-code" style="width: 100%; height: 100%;">
                            </div>
                            <p class="etiqueta-numero">OP <span id="op-numero-preview2">-</span></p>
                            <p class="etiqueta-info">
                                <strong>OS:</strong> <span id="op-os-preview">-</span>
                            </p>
                            <div class="etiqueta-acoes">
                                <button class="etiqueta-botao etiqueta-botao-imprimir" onclick="imprimirEtiquetaOP()">
                                    🖨️ Imprimir
                                </button>
                                <button class="etiqueta-botao etiqueta-botao-baixar" onclick="baixarQROP()">
                                    ⬇️ Baixar QR
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ABA: HISTÓRICO -->
        <div id="aba-historico" class="etiqueta-aba-conteudo" style="display: none;">
            <div id="historico-conteudo">
                <p style="color: #999; text-align: center; padding: 40px;">Carregando histórico de impressões...</p>
            </div>
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

<script>
// Função para mudar abas
function mudarAba(abaName, element) {
    // Ocultar todas as abas
    document.querySelectorAll('.etiqueta-aba-conteudo').forEach(aba => {
        aba.style.display = 'none';
    });

    // Remover active de todos os botões
    document.querySelectorAll('.etiqueta-aba').forEach(btn => {
        btn.classList.remove('ativo');
    });

    // Mostrar aba selecionada
    document.getElementById('aba-' + abaName).style.display = 'block';
    element.classList.add('ativo');

    // Carregar histórico se selecionou
    if (abaName === 'historico') {
        carregarHistorico();
    }
}

// Função para gerar QR de O.P.
function gerarQROp(opNumero, osNumero) {
    const formData = new FormData();
    formData.append('acao', 'gerar_qr_svg_op');
    formData.append('op_numero', opNumero);

    fetch('<?= SITE_URL ?>/api/etiqueta_qrcode.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.sucesso) {
                document.getElementById('op-numero-preview').textContent = opNumero;
                document.getElementById('op-numero-preview2').textContent = opNumero;
                document.getElementById('op-os-preview').textContent = osNumero;
                document.getElementById('qr-image-op').src = data.qr_url;
            } else {
                alert('Erro ao gerar QR: ' + data.erro);
            }
        });
}

// Impressão de etiqueta O.P.
function imprimirEtiquetaOP() {
    const qrUrl = document.getElementById('qr-image-op').src;
    const opNumero = document.getElementById('op-numero-preview').textContent;

    if (!qrUrl || opNumero === '-') {
        alert('Selecione uma O.P. primeiro');
        return;
    }

    let html = `
        <div class="etiqueta-print-item">
            <div class="etiqueta-print-qr">
                <img src="${qrUrl}" alt="QR">
            </div>
            <div class="etiqueta-print-numero">OP ${opNumero}</div>
        </div>
    `;

    let win = window.open('', '', 'height=400,width=600');
    win.document.write(html);
    win.document.close();
    setTimeout(() => win.print(), 500);
}

// Download QR O.P.
function baixarQROP() {
    const qrUrl = document.getElementById('qr-image-op').src;
    const opNumero = document.getElementById('op-numero-preview').textContent;

    if (!qrUrl || opNumero === '-') {
        alert('Selecione uma O.P. primeiro');
        return;
    }

    const link = document.createElement('a');
    link.href = qrUrl;
    link.download = `etiqueta_op_${opNumero}.png`;
    link.click();
}

// Carregar histórico de impressões
function carregarHistorico() {
    fetch('<?= SITE_URL ?>/api/etiqueta_qrcode.php?acao=stats_impressoes')
        .then(r => r.json())
        .then(data => {
            if (data.sucesso) {
                let html = '<table class="op-tabela"><thead><tr><th>Tipo</th><th>Total</th><th>Impressões</th></tr></thead><tbody>';
                data.stats.forEach(stat => {
                    html += `<tr>
                        <td>${stat.tipo}</td>
                        <td>${stat.total}</td>
                        <td>${stat.impressoes_totais || 0}</td>
                    </tr>`;
                });
                html += '</tbody></table>';
                document.getElementById('historico-conteudo').innerHTML = html;
            }
        });
}
</script>

<?php include '../../includes/footer_vendedor.php'; ?>
