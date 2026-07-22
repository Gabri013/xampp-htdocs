<?php
/**
 * Apontamento por leitura (estilo Nomus) — tela touch-first do chão de fábrica.
 * O operador bipa o código da O.P. (scan.php) e cai aqui: vê a O.S., a etapa
 * atual, o roteiro e aponta Iniciar/Finalizar com botões grandes. As regras
 * (expediente aberto, setor só opera a própria etapa, transições) são as do
 * api/producao.php — esta tela é só a superfície rápida.
 *
 * Acesso: qualquer usuário logado (os botões de apontar só aparecem para quem
 * pode operar a etapa atual).
 */

require_once '../../config/config.php';
require_once '../../includes/workflow.php';
require_once '../../includes/expediente.php';
requireLogin();

$db = getDB();
$osId = (int) ($_GET['os_id'] ?? 0);
if ($osId <= 0) {
    header('Location: scan.php');
    exit;
}

$stmt = $db->prepare("
    SELECT os.*, c.razao_social,
           op.numero AS op_numero
    FROM ordens_servico os
    JOIN clientes c ON c.id = os.cliente_id
    LEFT JOIN ordens_producao op ON op.os_id = os.id
    WHERE os.id = ?
    ORDER BY op.id DESC
    LIMIT 1
");
$stmt->execute([$osId]);
$os = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$os) {
    header('Location: scan.php');
    exit;
}

$tipoUsuario = $_SESSION['usuario_tipo'] ?? '';
$etapaAtual = (string) ($os['etapa_atual'] ?? '');

// Roteiro: etapas planejadas desta O.S. na ordem canônica (com status)
$stmt = $db->prepare("SELECT etapa, status, data_inicio, tempo_total_segundos FROM os_etapas_producao WHERE os_id = ?");
$stmt->execute([$osId]);
$planejadas = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $planejadas[$r['etapa']] = $r;
}
$roteiro = [];
foreach (getEtapasBancada() as $et) {
    if (isset($planejadas[$et])) {
        $roteiro[] = ['etapa' => $et] + $planejadas[$et];
    }
}
// Sem planejamento registrado: mostra o fluxo padrão
if (empty($roteiro)) {
    foreach (getEtapasBancada() as $et) {
        $roteiro[] = ['etapa' => $et, 'status' => 'pendente', 'data_inicio' => null, 'tempo_total_segundos' => 0];
    }
}

// Próxima etapa (destino do Finalizar): próxima planejada após a atual; senão 'concluida'
$proximaEtapa = 'concluida';
$etapasRoteiro = array_column($roteiro, 'etapa');
$posAtual = array_search($etapaAtual, $etapasRoteiro, true);
if ($posAtual !== false && isset($etapasRoteiro[$posAtual + 1])) {
    $proximaEtapa = $etapasRoteiro[$posAtual + 1];
}

// Estado da etapa atual (cronômetro)
$etapaInfo = $planejadas[$etapaAtual] ?? null;
$emAndamento = $etapaInfo && ($etapaInfo['status'] ?? '') === 'em_andamento';

// Pode operar? (mesma regra do api/producao.php)
$podeOperar = ($os['status'] === 'em_producao')
    && validateUserCanOperateEtapa($etapaAtual, $tipoUsuario)['valid']
    && in_array($etapaAtual, getEtapasBancada(), true);

// Expediente aberto? (o api exige para iniciar — avisamos antes)
try {
    $expStatus = getStatusExpedienteHoje($db, (int) ($_SESSION['usuario_id'] ?? 0))['status'] ?? 'nao_iniciado';
} catch (Throwable $e) {
    $expStatus = 'nao_iniciado';
}

// Desenho técnico / anexos (Nomus: anexos visíveis na tela de apontamento)
$desenhoImg = '';
$anexos = [];
try {
    $stmtArq = $db->prepare("SELECT nome_original, nome_arquivo, tipo FROM os_arquivos WHERE os_id = ? ORDER BY FIELD(tipo,'projeto_foto','projeto_pdf','projeto') DESC, id DESC LIMIT 8");
    $stmtArq->execute([$osId]);
    foreach ($stmtArq->fetchAll(PDO::FETCH_ASSOC) as $a) {
        if (is_file(BASE_PATH . '/assets/uploads/projetos/' . $a['nome_arquivo'])) {
            $url = SITE_URL . '/assets/uploads/projetos/' . rawurlencode($a['nome_arquivo']);
            $anexos[] = ['nome' => $a['nome_original'] ?: $a['nome_arquivo'], 'url' => $url];
            if ($desenhoImg === '' && preg_match('/\.(jpe?g|png|webp|gif)$/i', $a['nome_arquivo'])) {
                $desenhoImg = $url;
            }
        }
    }
} catch (Throwable $e) {}

$labels = [];
foreach (getEtapasBancada() as $et) {
    $labels[$et] = ucfirst(str_replace('_', ' ', $et));
}

$page_title = 'Apontar — ' . $os['numero'];
include '../../includes/header_vendedor.php';
?>
<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main"><div class="vend-content">

    <div class="dash-head">
        <div>
            <h1 class="dash-head-title"><span class="dash-head-ic"><i class="fas fa-stopwatch"></i></span> Apontamento — <?= htmlspecialchars($os['numero']) ?></h1>
            <p class="dash-head-sub"><?= htmlspecialchars($os['razao_social']) ?><?= $os['op_numero'] ? ' · O.P. ' . htmlspecialchars($os['op_numero']) : '' ?></p>
        </div>
        <a href="scan.php" class="dash-btn slate"><i class="fas fa-qrcode"></i> Bipar outra</a>
    </div>

    <?php if ($os['status'] !== 'em_producao'): ?>
        <div class="dash-section"><div class="dash-row amber" style="border-top:none">
            <div class="dash-row-title"><i class="fas fa-circle-info"></i> Esta O.S. não está em produção (status: <?= htmlspecialchars($os['status']) ?>).</div>
            <div class="dash-row-sub">Nada para apontar aqui. Consulte os detalhes se precisar.</div>
        </div></div>
    <?php endif; ?>

    <div class="dash-grid cols-2">
        <!-- COLUNA DE APONTAMENTO -->
        <div class="dash-section" style="margin:0">
            <div class="dash-section-head"><h2><i class="fas fa-industry"></i> Etapa atual: <?= htmlspecialchars($labels[$etapaAtual] ?? ucfirst($etapaAtual ?: '—')) ?></h2>
                <p>Prioridade: <?= htmlspecialchars(ucfirst($os['prioridade'] ?? '-')) ?> · Entrega: <?= $os['data_termino'] ? date('d/m/Y', strtotime($os['data_termino'])) : '—' ?></p></div>
            <div class="dash-section-body" style="text-align:center">

                <?php if ($emAndamento): ?>
                    <div class="dash-chip green" style="font-size:13px;margin-bottom:6px"><i class="fas fa-clock"></i> Em andamento</div>
                    <div id="cronometro" data-inicio="<?= htmlspecialchars($etapaInfo['data_inicio'] ?? '') ?>" style="font-size:40px;font-weight:800;font-variant-numeric:tabular-nums;color:var(--dash-text)">--:--:--</div>
                <?php elseif ($podeOperar): ?>
                    <div class="dash-chip amber" style="font-size:13px;margin-bottom:6px"><i class="fas fa-hourglass-start"></i> Aguardando início</div>
                <?php endif; ?>

                <?php if ($podeOperar && $expStatus !== 'em_trabalho'): ?>
                    <div class="dash-row amber" style="border-radius:8px;text-align:left;margin:12px 0">
                        <div class="dash-row-title"><i class="fas fa-user-clock"></i> Inicie seu expediente primeiro</div>
                        <div class="dash-row-sub">Use o botão "Iniciar expediente" no topo da tela — o apontamento exige expediente aberto.</div>
                    </div>
                <?php endif; ?>

                <?php if ($podeOperar): ?>
                    <div style="display:flex;flex-direction:column;gap:12px;margin-top:14px">
                        <?php if (!$emAndamento): ?>
                            <button type="button" class="dash-btn green" style="font-size:20px;padding:18px" onclick="apontar('iniciar')"><i class="fas fa-play"></i> Iniciar trabalho</button>
                        <?php else: ?>
                            <button type="button" class="dash-btn red" style="font-size:20px;padding:18px" onclick="apontar('finalizar')"><i class="fas fa-flag-checkered"></i> Finalizar e enviar para <?= htmlspecialchars($proximaEtapa === 'concluida' ? 'Concluída' : ($labels[$proximaEtapa] ?? ucfirst($proximaEtapa))) ?></button>
                        <?php endif; ?>
                        <a href="os_detalhes.php?os_id=<?= $osId ?>" class="dash-btn ghost">Ver detalhes completos</a>
                    </div>
                <?php elseif ($os['status'] === 'em_producao'): ?>
                    <div class="dash-row" style="border-radius:8px;text-align:left;margin-top:8px">
                        <div class="dash-row-title"><i class="fas fa-lock"></i> Etapa do setor <?= htmlspecialchars($labels[$etapaAtual] ?? $etapaAtual) ?></div>
                        <div class="dash-row-sub">Seu perfil (<?= htmlspecialchars(getTipoUsuarioNome($tipoUsuario)) ?>) não opera esta etapa. Você pode acompanhar pelo roteiro ao lado.</div>
                    </div>
                    <a href="os_detalhes.php?os_id=<?= $osId ?>" class="dash-btn ghost" style="margin-top:12px">Ver detalhes completos</a>
                <?php endif; ?>

                <div id="apontarMsg" style="margin-top:12px"></div>
            </div>
        </div>

        <!-- ROTEIRO -->
        <div class="dash-section" style="margin:0">
            <div class="dash-section-head"><h2><i class="fas fa-route"></i> Roteiro de produção</h2></div>
            <div class="dash-list dash-scroll">
                <?php foreach ($roteiro as $r):
                    $st = $r['status'] ?? 'pendente';
                    $ehAtual = $r['etapa'] === $etapaAtual;
                    $cor = $st === 'concluida' ? 'green' : ($st === 'em_andamento' ? 'amber' : ($ehAtual ? 'blue' : ''));
                    $seg = (int) ($r['tempo_total_segundos'] ?? 0);
                ?>
                <div class="dash-row <?= $cor ?>">
                    <div class="dash-row-top">
                        <div class="dash-row-title">
                            <?= $st === 'concluida' ? '<i class="fas fa-circle-check" style="color:var(--dash-green)"></i>' : ($st === 'em_andamento' ? '<i class="fas fa-person-digging" style="color:var(--dash-amber)"></i>' : '<i class="far fa-circle" style="color:#cbd5e1"></i>') ?>
                            <?= htmlspecialchars($labels[$r['etapa']] ?? ucfirst($r['etapa'])) ?>
                            <?= $ehAtual ? '<span class="dash-chip blue">atual</span>' : '' ?>
                        </div>
                        <?php if ($seg > 0): ?><span class="dash-row-sub"><?= sprintf('%02d:%02d', intdiv($seg, 3600), intdiv($seg % 3600, 60)) ?>h</span><?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php if ($desenhoImg !== '' || !empty($anexos)): ?>
    <div class="dash-section" style="margin-top:16px">
        <div class="dash-section-head"><h2><i class="fas fa-drafting-compass"></i> Desenho técnico e anexos</h2></div>
        <div class="dash-section-body">
            <?php if ($desenhoImg !== ''): ?>
                <a href="<?= $desenhoImg ?>" target="_blank"><img src="<?= $desenhoImg ?>" alt="Desenho técnico" style="max-width:100%;max-height:420px;border:1px solid var(--dash-border);border-radius:8px"></a>
            <?php endif; ?>
            <?php if (!empty($anexos)): ?>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
                    <?php foreach ($anexos as $a): ?>
                        <a class="dash-chip blue" style="text-decoration:none" href="<?= $a['url'] ?>" target="_blank"><i class="fas fa-paperclip"></i> <?= htmlspecialchars(mb_strimwidth($a['nome'], 0, 34, '…')) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    </div></div>
</div>

<script>
// Cronômetro da etapa em andamento
(function () {
    const el = document.getElementById('cronometro');
    if (!el || !el.dataset.inicio) return;
    const inicio = new Date(el.dataset.inicio.replace(' ', 'T'));
    function tick() {
        const s = Math.max(0, Math.floor((Date.now() - inicio.getTime()) / 1000));
        el.textContent = String(Math.floor(s/3600)).padStart(2,'0') + ':' + String(Math.floor(s%3600/60)).padStart(2,'0') + ':' + String(s%60).padStart(2,'0');
    }
    tick(); setInterval(tick, 1000);
})();

function apontar(acao) {
    const msg = document.getElementById('apontarMsg');
    msg.innerHTML = '<span style="color:#666"><i class="fas fa-spinner fa-spin"></i> Registrando…</span>';
    const fd = new FormData();
    fd.append('os_id', <?= (int) $osId ?>);
    fd.append('etapa', <?= json_encode($etapaAtual) ?>);
    fd.append('acao', acao === 'iniciar' ? 'iniciar_etapa' : 'finalizar_etapa');
    if (acao === 'finalizar') fd.append('etapa_destino', <?= json_encode($proximaEtapa) ?>);
    fetch('<?= SITE_URL ?>/api/producao.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) { location.reload(); }
            else { msg.innerHTML = '<div class="dash-row red" style="border-radius:8px;text-align:left"><div class="dash-row-title">' + (d.error || 'Erro ao apontar') + '</div></div>'; }
        })
        .catch(() => { msg.innerHTML = '<div class="dash-row red" style="border-radius:8px;text-align:left"><div class="dash-row-title">Erro de conexão.</div></div>'; });
}
</script>
<?php include '../../includes/footer_vendedor.php'; ?>
