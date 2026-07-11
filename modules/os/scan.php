<?php
require_once '../../config/config.php';
requireLogin(); // todos os setores podem localizar uma O.S. pelo código

$db = getDB();

// Resolve um código lido (OP-..., OS-..., URL do QR ou id) para a O.S.
function resolverCodigoOS(PDO $db, string $code): ?int
{
    $code = trim($code);
    if ($code === '') return null;

    // URL do QR: extrai o parâmetro code=
    if (stripos($code, 'http') === 0) {
        $query = parse_url($code, PHP_URL_QUERY) ?: '';
        parse_str($query, $qs);
        $code = trim((string)($qs['code'] ?? ''));
        if ($code === '') return null;
    }

    // OP-2026-00086-01 -> remove sufixo de item -> ordens_producao
    if (stripos($code, 'OP-') === 0) {
        $base = preg_replace('/-\d{2}$/', '', $code);
        $stmt = $db->prepare("SELECT os_id FROM ordens_producao WHERE numero = ? OR numero = ? LIMIT 1");
        $stmt->execute([$code, $base]);
        $osId = (int) $stmt->fetchColumn();
        if ($osId > 0) return $osId;
        // OP gerada na impressão sem registro: OP-YYYY-000NN carrega o id da OS
        if (preg_match('/^OP-\d{4}-0*(\d+)/i', $base, $m)) {
            $stmt = $db->prepare("SELECT id FROM ordens_servico WHERE id = ?");
            $stmt->execute([(int)$m[1]]);
            $osId = (int) $stmt->fetchColumn();
            if ($osId > 0) return $osId;
        }
        return null;
    }

    // OS-0131 (número da O.S.)
    $stmt = $db->prepare("SELECT id FROM ordens_servico WHERE numero = ? LIMIT 1");
    $stmt->execute([$code]);
    $osId = (int) $stmt->fetchColumn();
    if ($osId > 0) return $osId;

    // Só dígitos: tenta id direto
    if (ctype_digit($code)) {
        $stmt = $db->prepare("SELECT id FROM ordens_servico WHERE id = ?");
        $stmt->execute([(int)$code]);
        $osId = (int) $stmt->fetchColumn();
        if ($osId > 0) return $osId;
    }

    return null;
}

$erro = '';
$codigoLido = trim($_GET['code'] ?? $_POST['code'] ?? '');
if ($codigoLido !== '') {
    $osId = resolverCodigoOS($db, $codigoLido);
    if ($osId) {
        header('Location: os_detalhes.php?os_id=' . $osId);
        exit;
    }
    $erro = 'Nenhuma O.S. encontrada para o código "' . htmlspecialchars($codigoLido) . '".';
}

$page_title = 'Escanear O.P. / O.S.';
include '../../includes/header_vendedor.php';
?>

<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-page-head">
            <div>
                <h1 class="vend-page-title">Escanear O.P. / O.S.</h1>
                <p class="vend-page-sub">Use o leitor de código de barras (USB) ou a câmera do celular para abrir a O.S.</p>
            </div>
        </div>

        <?php if ($erro !== ''): ?>
            <div class="vend-alert warning"><i class="fas fa-exclamation-triangle"></i> <div><?php echo $erro; ?></div></div>
        <?php endif; ?>

        <div class="vend-card" style="max-width:560px">
            <div class="vend-card-head"><div class="vend-card-title"><i class="fas fa-barcode"></i> Leitor / digitação</div></div>
            <div style="padding:20px">
                <form method="GET" id="formScan">
                    <label class="form-label" for="code">Bipe o código de barras ou digite OP-…/OS-…</label>
                    <div style="display:flex;gap:8px;margin-top:6px">
                        <input type="text" name="code" id="code" class="form-control" placeholder="OP-2026-00086 ou OS-0131"
                               autofocus autocomplete="off" style="font-size:18px;letter-spacing:1px">
                        <button type="submit" class="vbtn-sm vbtn-brand" style="white-space:nowrap"><i class="fas fa-search"></i> Abrir</button>
                    </div>
                </form>
                <p style="font-size:12px;color:#888;margin-top:8px">O leitor USB digita e confirma sozinho — deixe o campo em foco e bipe.</p>
            </div>
        </div>

        <div class="vend-card" style="max-width:560px;margin-top:16px">
            <div class="vend-card-head"><div class="vend-card-title"><i class="fas fa-camera"></i> Câmera (QR Code)</div></div>
            <div style="padding:20px">
                <button type="button" class="vbtn-sm btn-success" id="btnCamera"><i class="fas fa-qrcode"></i> Ler QR com a câmera</button>
                <div id="leitorQr" style="width:100%;max-width:420px;margin-top:12px"></div>
                <p style="font-size:12px;color:#888;margin-top:8px">Aponte a câmera para o QR Code impresso na Ordem de Produção.</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(function () {
    // Mantém o foco no campo para o leitor USB (exceto quando a câmera está ativa)
    let cameraAtiva = false;
    const campo = document.getElementById('code');
    setInterval(function () {
        if (!cameraAtiva && document.activeElement !== campo &&
            !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) {
            campo.focus();
        }
    }, 1500);

    let leitor = null;
    document.getElementById('btnCamera').addEventListener('click', function () {
        const alvo = document.getElementById('leitorQr');
        if (cameraAtiva && leitor) {
            leitor.stop().catch(function () {});
            alvo.innerHTML = '';
            cameraAtiva = false;
            this.innerHTML = '<i class="fas fa-qrcode"></i> Ler QR com a câmera';
            return;
        }
        cameraAtiva = true;
        this.innerHTML = '<i class="fas fa-stop"></i> Parar câmera';
        leitor = new Html5Qrcode('leitorQr');
        leitor.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 220, height: 220 } },
            function (texto) {
                leitor.stop().catch(function () {});
                window.location.href = 'scan.php?code=' + encodeURIComponent(texto);
            },
            function () { /* leitura em andamento */ }
        ).catch(function (err) {
            alvo.innerHTML = '<div class="vend-alert warning" style="margin-top:8px">Não foi possível acessar a câmera: ' + err + '</div>';
            cameraAtiva = false;
            document.getElementById('btnCamera').innerHTML = '<i class="fas fa-qrcode"></i> Ler QR com a câmera';
        });
    });
})();
</script>

<?php include '../../includes/footer_vendedor.php'; ?>
