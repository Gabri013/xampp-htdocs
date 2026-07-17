<?php
/**
 * Apontamento Visual - Interface Mobile-First para Produção
 *
 * Inspirado no Nomus ERP - cards grandes, botões gigantes, sem complexidade
 * Reutiliza: /api/producao.php, /api/expediente.php, os_etapas_producao
 *
 * Acesso: gerente, producao, [setores específicos]
 */

require_once '../../config/config.php';
require_once '../../includes/workflow.php';
require_once '../../includes/expediente.php';

$page_title = 'Apontamento de Produção';
$db = getDB();
ensureExpedienteSchema($db);
ensureOrdensServicoIndependentesSchema($db);

// Verificar permissão (qualquer setor de produção pode apontar)
requirePermission(['master', 'gerente', 'producao', 'engenharia', 'programacao', 'corte', 'dobra', 'tubo', 'solda', 'mobiliario', 'coccao', 'refrigeracao', 'acabamento', 'montagem', 'embalagem', 'finalizacao']);

$usuario = getCurrentUser();
$usuario_tipo = $usuario['tipo'] ?? '';

// ===== STATUS EXPEDIENTE =====
$status_expediente = getStatusExpedienteHoje($db, (int)$usuario['id']);
$expediente_ativo = ($status_expediente['status'] ?? 'nao_iniciado') === 'em_trabalho';

// ===== CORES E LABELS (Reutilizar de dashboard_producao.php) =====
$cores_etapas = [
    'autorizacao' => '#6c757d',
    'corte' => '#007bff',
    'dobra' => '#6610f2',
    'tubo' => '#17a2b8',
    'solda' => '#fd7e14',
    'mobiliario' => '#20c997',
    'coccao' => '#0ea5e9',
    'refrigeracao' => '#0ea5e9',
    'acabamento' => '#20c997',
    'montagem' => '#17a2b8',
    'embalagem' => '#6c757d',
    'programacao' => '#6610f2',
    'engenharia' => '#007bff',
    'finalizacao' => '#28a745',
];

$labels_etapas = [
    'autorizacao' => 'Autorização',
    'engenharia' => 'Engenharia',
    'programacao' => 'Programação',
    'corte' => 'Corte',
    'dobra' => 'Dobra',
    'tubo' => 'Tubo',
    'solda' => 'Solda',
    'mobiliario' => 'Mobiliário',
    'coccao' => 'Cocção',
    'refrigeracao' => 'Refrigeração',
    'acabamento' => 'Acabamento',
    'montagem' => 'Montagem',
    'embalagem' => 'Embalagem',
    'finalizacao' => 'Finalização',
];

// ===== BUSCAR O.S. NA ETAPA ATUAL DO USUÁRIO =====
// Se o usuário é de um setor específico, mostrar O.S. naquela etapa
$etapa_usuario = ($usuario_tipo !== 'master' && $usuario_tipo !== 'gerente' && $usuario_tipo !== 'producao')
    ? $usuario_tipo
    : null;

if ($etapa_usuario) {
    // Usuário é de um setor específico - mostrar O.S. naquela etapa
    $stmt = $db->query("
        SELECT os.*, c.razao_social,
               COALESCE(oep.data_inicio, NULL) as etapa_data_inicio,
               COALESCE(oep.data_fim, NULL) as etapa_data_fim,
               COALESCE(oep.tempo_total_segundos, 0) as tempo_investido
        FROM ordens_servico os
        INNER JOIN clientes c ON os.cliente_id = c.id
        LEFT JOIN os_etapas_producao oep ON os.id = oep.os_id AND oep.etapa = '$etapa_usuario'
        WHERE os.status = 'em_producao' AND os.etapa_atual = '$etapa_usuario'
        ORDER BY oep.data_inicio DESC, os.numero DESC
        LIMIT 1
    ");
    $os_atual = $stmt->fetch();
} else {
    // Gerente ou Master - mostrar lista de O.S. em produção
    $stmt = $db->query("
        SELECT os.*, c.razao_social
        FROM ordens_servico os
        INNER JOIN clientes c ON os.cliente_id = c.id
        WHERE os.status = 'em_producao'
        ORDER BY os.data_termino ASC, os.numero DESC
        LIMIT 10
    ");
    $os_lista = $stmt->fetchAll();
    $os_atual = null;
}

include '../../includes/header_vendedor.php';
?>

<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-page-head">
            <h1 class="vend-page-title">⏱️ Apontamento de Produção</h1>
        </div>
        <div class="vend-content">

<style>
    /* MOBILE-FIRST APONTAMENTO */
    .apontamento-container {
        display: flex;
        flex-direction: column;
        height: 100vh;
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        padding: 0;
        gap: 0;
    }

    .apontamento-header {
        background: linear-gradient(135deg, #D85A30 0%, #ef4444 100%);
        color: white;
        padding: 20px;
        text-align: center;
        flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .apontamento-header h2 {
        font-size: 28px;
        margin: 0;
        font-weight: 700;
    }

    .apontamento-header .status-expediente {
        font-size: 14px;
        margin-top: 8px;
        opacity: 0.9;
    }

    .apontamento-status-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 20px;
        background: rgba(255,255,255,0.2);
        border: 2px solid white;
        font-weight: 600;
        font-size: 12px;
    }

    .apontamento-status-badge.ativo {
        background: #22c55e;
        border-color: white;
    }

    .apontamento-content {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
    }

    .apontamento-card {
        background: white;
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 16px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .apontamento-os-numero {
        font-size: 32px;
        font-weight: 700;
        color: #D85A30;
    }

    .apontamento-cliente {
        font-size: 16px;
        color: #333;
        font-weight: 600;
    }

    .apontamento-etapa {
        display: inline-block;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        color: white;
    }

    .apontamento-tempo {
        background: #f0f0f0;
        padding: 16px;
        border-radius: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .apontamento-tempo-label {
        font-size: 12px;
        color: #666;
        text-transform: uppercase;
    }

    .apontamento-tempo-valor {
        font-size: 24px;
        font-weight: 700;
        color: #333;
    }

    .apontamento-botoes {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .apontamento-botao {
        padding: 20px;
        border: none;
        border-radius: 12px;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .apontamento-botao-iniciar {
        background: #22c55e;
        color: white;
        grid-column: 1 / 3;
        padding: 30px;
        font-size: 18px;
    }

    .apontamento-botao-iniciar:hover {
        background: #16a34a;
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(34,197,94,0.3);
    }

    .apontamento-botao-finalizar {
        background: #3b82f6;
        color: white;
    }

    .apontamento-botao-finalizar:hover {
        background: #2563eb;
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(59,130,246,0.3);
    }

    .apontamento-botao-retornar {
        background: #f97316;
        color: white;
    }

    .apontamento-botao-retornar:hover {
        background: #ea580c;
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(249,115,22,0.3);
    }

    .apontamento-botao-expedicao {
        background: #8b5cf6;
        color: white;
    }

    .apontamento-botao-expedicao:hover {
        background: #7c3aed;
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(139,92,246,0.3);
    }

    .apontamento-expediente {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-top: 12px;
    }

    .apontamento-botao-expediente {
        padding: 16px;
        border: 2px solid #ddd;
        border-radius: 8px;
        background: white;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .apontamento-botao-expediente:hover {
        border-color: #D85A30;
        background: #fef0ea;
    }

    .apontamento-lista-os {
        display: grid;
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .apontamento-card-pequeno {
        background: white;
        padding: 16px;
        border-radius: 12px;
        border-left: 6px solid #D85A30;
        cursor: pointer;
        transition: all 0.2s;
    }

    .apontamento-card-pequeno:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }

    .apontamento-card-pequeno-numero {
        font-size: 20px;
        font-weight: 700;
        color: #D85A30;
    }

    .apontamento-card-pequeno-cliente {
        font-size: 13px;
        color: #666;
        margin-top: 4px;
    }

    @media (max-width: 768px) {
        .apontamento-botoes {
            grid-template-columns: 1fr;
        }

        .apontamento-botao-iniciar {
            grid-column: 1;
        }
    }
</style>

<!-- SE USUÁRIO É DE SETOR ESPECÍFICO - MOSTRAR CARD GRANDE -->
<?php if ($os_atual): ?>
    <div class="apontamento-container">
        <div class="apontamento-header">
            <h2>⏱️ Apontamento</h2>
            <div class="status-expediente">
                Expediente:
                <span class="apontamento-status-badge <?= $expediente_ativo ? 'ativo' : '' ?>">
                    <?= $expediente_ativo ? '✓ Em Trabalho' : '⭕ Não Iniciado' ?>
                </span>
            </div>
        </div>

        <div class="apontamento-content">
            <div class="apontamento-card">
                <!-- O.S. Número -->
                <div class="apontamento-os-numero">OS <?= htmlspecialchars($os_atual['numero']) ?></div>

                <!-- Cliente -->
                <div class="apontamento-cliente"><?= htmlspecialchars($os_atual['razao_social']) ?></div>

                <!-- Etapa -->
                <div>
                    <span class="apontamento-etapa" style="background-color: <?= $cores_etapas[$usuario_tipo] ?? '#999' ?>">
                        <?= $labels_etapas[$usuario_tipo] ?? ucfirst($usuario_tipo) ?>
                    </span>
                </div>

                <!-- Tempo Investido -->
                <div class="apontamento-tempo">
                    <div>
                        <div class="apontamento-tempo-label">Tempo Investido</div>
                        <div class="apontamento-tempo-valor" id="tempo-investido">
                            <?= $os_atual['tempo_investido'] ? gmdate('H:i', $os_atual['tempo_investido']) : '00:00' ?>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div class="apontamento-tempo-label">Status Etapa</div>
                        <div class="apontamento-tempo-valor">
                            <?= $os_atual['etapa_data_fim'] ? '✓' : '🔄' ?>
                        </div>
                    </div>
                </div>

                <!-- Botões de Ação -->
                <div class="apontamento-botoes">
                    <?php if (!$os_atual['etapa_data_fim']): ?>
                        <button class="apontamento-botao apontamento-botao-iniciar" onclick="iniciarEtapa()">
                            ▶ Iniciar Trabalho
                        </button>
                        <button class="apontamento-botao apontamento-botao-finalizar" onclick="finalizarEtapa()" style="grid-column: 1;">
                            ✓ Finalizar
                        </button>
                    <?php else: ?>
                        <button class="apontamento-botao" style="background: #10b981; grid-column: 1/3; color: white;" disabled>
                            ✓ ETAPA CONCLUÍDA
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Expediente -->
                <div class="apontamento-expediente">
                    <?php if (!$expediente_ativo): ?>
                        <button class="apontamento-botao-expediente" onclick="iniciarExpedicao()" style="background: #22c55e; color: white; border-color: #22c55e;">
                            ▶ Iniciar Expediente
                        </button>
                    <?php else: ?>
                        <button class="apontamento-botao-expediente" onclick="finalizarExpedicao()" style="background: #ef4444; color: white; border-color: #ef4444;">
                            ⏹ Finalizar Expediente
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- SE NÃO, MOSTRAR LISTA DE O.S. -->
    <div class="vend-card">
        <div class="vend-card-head">
            <h3>📋 O.S. em Produção</h3>
        </div>
        <div class="vend-card-body">
            <?php if (empty($os_lista)): ?>
                <div style="text-align: center; padding: 40px; color: #999;">
                    <p style="font-size: 48px; margin: 0;">✓</p>
                    <p>Nenhuma O.S. em produção</p>
                </div>
            <?php else: ?>
                <div class="apontamento-lista-os">
                    <?php foreach ($os_lista as $os): ?>
                        <div class="apontamento-card-pequeno">
                            <div class="apontamento-card-pequeno-numero">OS <?= htmlspecialchars($os['numero']) ?></div>
                            <div class="apontamento-card-pequeno-cliente">
                                <?= htmlspecialchars($os['razao_social']) ?>
                            </div>
                            <div style="font-size: 12px; color: #999; margin-top: 4px;">
                                Etapa: <?= $labels_etapas[$os['etapa_atual']] ?? $os['etapa_atual'] ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

</div>
        </div>
    </div>

<script>
/**
 * Funções de Apontamento - Reutilizam /api/producao.php
 */

function iniciarEtapa() {
    const os_id = <?= $os_atual['id'] ?? 'null' ?>;
    const etapa = '<?= $usuario_tipo ?>';

    if (!os_id || !etapa) {
        alert('Dados insuficientes');
        return;
    }

    const formData = new FormData();
    formData.append('acao', 'iniciar_etapa');
    formData.append('os_id', os_id);
    formData.append('etapa', etapa);

    fetch('<?= SITE_URL ?>/api/producao.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('✓ Trabalho iniciado!');
            location.reload();
        } else {
            alert('Erro: ' + (data.error || data.message));
        }
    })
    .catch(e => alert('Erro: ' + e.message));
}

function finalizarEtapa() {
    const os_id = <?= $os_atual['id'] ?? 'null' ?>;
    const etapa = '<?= $usuario_tipo ?>';

    if (confirm('Finalizar trabalho nesta etapa?')) {
        const formData = new FormData();
        formData.append('acao', 'finalizar_etapa');
        formData.append('os_id', os_id);
        formData.append('etapa', etapa);

        fetch('<?= SITE_URL ?>/api/producao.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('✓ Etapa finalizada!');
                location.reload();
            } else {
                alert('Erro: ' + (data.error || data.message));
            }
        })
        .catch(e => alert('Erro: ' + e.message));
    }
}

function iniciarExpedicao() {
    fetch('<?= SITE_URL ?>/api/expediente.php', {
        method: 'POST',
        body: new FormData(Object.assign(document.createElement('form'), {
            innerHTML: '<input name="acao" value="iniciar">'
        }))
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('✓ Expediente iniciado!');
            location.reload();
        }
    })
    .catch(e => alert('Erro: ' + e.message));
}

function finalizarExpedicao() {
    if (confirm('Finalizar expediente?')) {
        const formData = new FormData();
        formData.append('acao', 'finalizar');

        fetch('<?= SITE_URL ?>/api/expediente.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('✓ Expediente finalizado!');
                location.reload();
            }
        })
        .catch(e => alert('Erro: ' + e.message));
    }
}

// Auto-refresh a cada 30 segundos
setInterval(() => { location.reload(); }, 30000);
</script>

<?php include '../../includes/footer_vendedor.php'; ?>
