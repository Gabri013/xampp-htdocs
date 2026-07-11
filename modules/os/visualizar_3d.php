<?php
require_once '../../config/config.php';
requireLogin(); // qualquer usuário logado pode visualizar o modelo do item

// Nome do arquivo salvo (gerado por uploadFile: uniqid_timestamp.ext).
// Validação estrita para impedir path traversal.
$arquivo = (string)($_GET['arquivo'] ?? '');
$nomeOriginal = trim((string)($_GET['nome'] ?? '')) ?: $arquivo;

if (!preg_match('/^[A-Za-z0-9_.\-]+$/', $arquivo) || strpos($arquivo, '..') !== false) {
    http_response_code(400);
    die('Arquivo inválido.');
}

$caminhoFisico = rtrim(UPLOAD_PATH, '/') . '/projetos/' . $arquivo;
if (!file_exists($caminhoFisico)) {
    http_response_code(404);
    die('Arquivo não encontrado.');
}
if (!isArquivo3DVisualizavel($arquivo)) {
    http_response_code(415);
    die('Formato sem visualização no navegador. Baixe o arquivo para abrir no SolidWorks.');
}

$urlArquivo = SITE_URL . '/assets/uploads/projetos/' . rawurlencode($arquivo);
$tamanhoMb = round(filesize($caminhoFisico) / 1024 / 1024, 2);
$page_title = 'Visualizador 3D — ' . $nomeOriginal;
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;font-family:Arial,Helvetica,sans-serif}
        body{background:#1e293b;height:100vh;display:flex;flex-direction:column;overflow:hidden}
        .topo{background:#0f172a;color:#fff;padding:10px 16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap}
        .topo .titulo{font-size:14px;font-weight:700;flex:1;min-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .topo .titulo i{color:#D85A30;margin-right:6px}
        .topo .info{font-size:11px;color:#94a3b8}
        .topo a{color:#fff;background:#D85A30;border-radius:8px;padding:6px 12px;font-size:12px;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
        .topo a.sec{background:#334155}
        #viewer{flex:1;position:relative}
        #carregando{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#94a3b8;gap:12px;z-index:5}
        #carregando .spin{width:42px;height:42px;border:4px solid #334155;border-top-color:#D85A30;border-radius:50%;animation:sp 1s linear infinite}
        @keyframes sp{to{transform:rotate(360deg)}}
        .dica{background:#0f172a;color:#64748b;font-size:11px;padding:6px 16px;text-align:center}
        @media (max-width:480px){.topo .info{display:none}}
    </style>
    <script src="https://cdn.jsdelivr.net/npm/online-3d-viewer@0.16.0/build/engine/o3dv.min.js"></script>
</head>
<body>
    <div class="topo">
        <div class="titulo"><i class="fas fa-cube"></i> <?= htmlspecialchars($nomeOriginal) ?></div>
        <span class="info"><?= $tamanhoMb ?> MB</span>
        <a href="<?= $urlArquivo ?>" download="<?= htmlspecialchars($nomeOriginal) ?>" class="sec"><i class="fas fa-download"></i> Baixar</a>
        <a href="javascript:window.close()"><i class="fas fa-times"></i> Fechar</a>
    </div>
    <div id="viewer">
        <div id="carregando"><div class="spin"></div><div>Carregando modelo 3D…</div></div>
    </div>
    <div class="dica">Arraste para girar &nbsp;•&nbsp; Roda do mouse para zoom &nbsp;•&nbsp; Botão direito (ou dois dedos) para mover</div>

    <script>
    window.addEventListener('load', function () {
        if (typeof OV === 'undefined') {
            document.getElementById('carregando').innerHTML = 'Não foi possível carregar o visualizador (sem internet?). Use o botão Baixar.';
            return;
        }
        const parent = document.getElementById('viewer');
        const viewer = new OV.EmbeddedViewer(parent, {
            backgroundColor: new OV.RGBAColor(30, 41, 59, 255),
            defaultColor: new OV.RGBColor(200, 200, 200),
            edgeSettings: new OV.EdgeSettings(false, new OV.RGBColor(0, 0, 0), 1)
        });

        viewer.LoadModelFromUrlList(['<?= $urlArquivo ?>']);

        // Esconde o "carregando" quando o canvas do modelo aparecer
        const obs = new MutationObserver(function () {
            if (parent.querySelector('canvas')) {
                setTimeout(function () {
                    const c = document.getElementById('carregando');
                    if (c) c.style.display = 'none';
                }, 1200);
                obs.disconnect();
            }
        });
        obs.observe(parent, { childList: true, subtree: true });
        setTimeout(function () {
            const c = document.getElementById('carregando');
            if (c) c.style.display = 'none';
        }, 15000);
    });
    </script>
</body>
</html>
