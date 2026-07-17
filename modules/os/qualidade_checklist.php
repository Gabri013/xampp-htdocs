<?php
/**
 * Qualidade Checklist Visual - Inspeção Touch-Friendly
 *
 * Inspirado no Nomus - Checkboxes gigantes, sem tabelas
 * Reutiliza: /api/qualidade.php, os_qualidade
 *
 * Acesso: master, gerente, dashboard_producao, producao
 */

require_once '../../config/config.php';
require_once '../../includes/workflow.php';

$page_title = 'Checklist de Qualidade';
$db = getDB();
requirePermission(['master', 'gerente', 'dashboard_producao', 'producao']);

// Criar tabela se não existir
$db->exec("CREATE TABLE IF NOT EXISTS os_qualidade (
    id INT AUTO_INCREMENT PRIMARY KEY,
    os_id INT NOT NULL,
    etapa VARCHAR(50) NOT NULL,
    item VARCHAR(255) NOT NULL,
    status ENUM('pendente', 'ok', 'problema') DEFAULT 'pendente',
    observacao TEXT,
    usuario_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_os_etapa (os_id, etapa)
)");

// ===== PROCESSAR AÇÕES =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $os_id = (int)($_POST['os_id'] ?? 0);
    $item_id = (int)($_POST['item_id'] ?? 0);
    $status = $_POST['status'] ?? 'pendente';
    $observacao = sanitize($_POST['observacao'] ?? '');

    if ($acao === 'marcar_status' && $item_id) {
        $stmt = $db->prepare("UPDATE os_qualidade SET status=?, observacao=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$status, $observacao, $item_id]);
        echo json_encode(['success' => true]);
        exit;
    }
}

// ===== BUSCAR O.S. E CHECKLIST =====
$os_id = $_GET['os_id'] ?? null;
$os_atual = null;
$checklist_itens = [];

if ($os_id) {
    $stmt = $db->prepare("SELECT os.*, c.razao_social FROM ordens_servico os
        INNER JOIN clientes c ON os.cliente_id = c.id WHERE os.id = ?");
    $stmt->execute([(int)$os_id]);
    $os_atual = $stmt->fetch();

    if ($os_atual) {
        // Buscar itens do checklist
        $stmt = $db->prepare("SELECT * FROM os_qualidade WHERE os_id = ? ORDER BY etapa, id");
        $stmt->execute([(int)$os_id]);
        $checklist_itens = $stmt->fetchAll();

        // Se não há itens, criar padrão
        if (empty($checklist_itens)) {
            $itens_padrao = [
                ['Dimensões', 'Verificar medidas conforme desenho'],
                ['Acabamento', 'Superfícies lisas sem imperfeições'],
                ['Soldas', 'Soldas bem feitas e sem porosidade'],
                ['Pintura', 'Pintura uniforme e sem falhas'],
                ['Montagem', 'Todas as peças montadas corretamente'],
                ['Funcionamento', 'Teste funcional OK'],
            ];

            foreach ($itens_padrao as $item) {
                $stmt = $db->prepare("INSERT INTO os_qualidade (os_id, etapa, item, status) VALUES (?, ?, ?, 'pendente')");
                $stmt->execute([(int)$os_id, $os_atual['etapa_atual'], $item[1]]);
            }

            $stmt = $db->prepare("SELECT * FROM os_qualidade WHERE os_id = ? ORDER BY etapa, id");
            $stmt->execute([(int)$os_id]);
            $checklist_itens = $stmt->fetchAll();
        }
    }
} else {
    // Mostrar últimas O.S.
    $stmt = $db->query("SELECT os.*, c.razao_social FROM ordens_servico os
        INNER JOIN clientes c ON os.cliente_id = c.id
        WHERE os.status IN ('em_producao', 'concluida')
        ORDER BY os.updated_at DESC LIMIT 10");
    $os_lista = $stmt->fetchAll();
}

include '../../includes/header_vendedor.php';
?>

<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-page-head">
            <h1 class="vend-page-title">✅ Checklist de Qualidade</h1>
        </div>
        <div class="vend-content">

<style>
    .qualidade-container {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .qualidade-seletor {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 12px;
        background: white;
        padding: 16px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .qualidade-os-item {
        padding: 12px;
        border: 2px solid #ddd;
        border-radius: 8px;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
        transition: all 0.2s;
        background: #fafafa;
    }

    .qualidade-os-item:hover {
        border-color: #22c55e;
        background: #f0fdf4;
        transform: translateY(-2px);
    }

    .qualidade-os-numero {
        font-size: 16px;
        font-weight: 700;
        color: #22c55e;
    }

    .qualidade-os-cliente {
        font-size: 12px;
        color: #666;
        margin-top: 4px;
    }

    /* CHECKLIST */
    .qualidade-checklist {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow: hidden;
    }

    .qualidade-header {
        background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
        color: white;
        padding: 24px;
    }

    .qualidade-header-titulo {
        font-size: 20px;
        font-weight: 700;
        margin: 0 0 8px 0;
    }

    .qualidade-header-cliente {
        font-size: 14px;
        opacity: 0.9;
        margin: 0;
    }

    .qualidade-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-top: 16px;
    }

    .qualidade-stat {
        background: rgba(255,255,255,0.15);
        padding: 12px;
        border-radius: 8px;
        text-align: center;
    }

    .qualidade-stat-valor {
        font-size: 24px;
        font-weight: 700;
    }

    .qualidade-stat-label {
        font-size: 12px;
        opacity: 0.8;
        margin-top: 4px;
    }

    .qualidade-itens {
        padding: 20px;
    }

    .qualidade-item {
        background: #fafafa;
        padding: 16px;
        margin-bottom: 12px;
        border-radius: 12px;
        border-left: 6px solid #ddd;
        display: flex;
        gap: 16px;
        align-items: flex-start;
        transition: all 0.2s;
    }

    .qualidade-item:hover {
        background: #f0f0f0;
        border-left-color: #999;
    }

    .qualidade-item.ok {
        background: #f0fdf4;
        border-left-color: #22c55e;
    }

    .qualidade-item.problema {
        background: #fef2f2;
        border-left-color: #ef4444;
    }

    .qualidade-checkbox {
        width: 60px;
        height: 60px;
        border: 3px solid #ddd;
        border-radius: 12px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        transition: all 0.2s;
        background: white;
        flex-shrink: 0;
    }

    .qualidade-checkbox.ok {
        background: #22c55e;
        border-color: #22c55e;
        color: white;
    }

    .qualidade-checkbox.problema {
        background: #ef4444;
        border-color: #ef4444;
        color: white;
    }

    .qualidade-item-content {
        flex: 1;
    }

    .qualidade-item-titulo {
        font-size: 16px;
        font-weight: 700;
        margin: 0 0 8px 0;
    }

    .qualidade-item-descricao {
        font-size: 14px;
        color: #666;
        margin-bottom: 12px;
    }

    .qualidade-item-obs {
        background: white;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 12px;
        color: #333;
        border-left: 3px solid #ef4444;
        display: none;
    }

    .qualidade-item.problema .qualidade-item-obs {
        display: block;
    }

    .qualidade-botoes {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .qualidade-botao {
        padding: 8px 16px;
        border: 2px solid #ddd;
        border-radius: 8px;
        background: white;
        cursor: pointer;
        font-weight: 600;
        font-size: 13px;
        transition: all 0.2s;
        text-transform: uppercase;
    }

    .qualidade-botao-ok {
        border-color: #22c55e;
        color: #22c55e;
    }

    .qualidade-botao-ok:hover,
    .qualidade-item.ok .qualidade-botao-ok {
        background: #22c55e;
        color: white;
    }

    .qualidade-botao-problema {
        border-color: #ef4444;
        color: #ef4444;
    }

    .qualidade-botao-problema:hover,
    .qualidade-item.problema .qualidade-botao-problema {
        background: #ef4444;
        color: white;
    }

    .qualidade-botao-pendente {
        border-color: #999;
        color: #999;
    }

    .qualidade-botao-pendente:hover,
    .qualidade-item:not(.ok):not(.problema) .qualidade-botao-pendente {
        background: #999;
        color: white;
    }

    .qualidade-observacao {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 12px;
        margin-top: 8px;
        display: none;
        resize: vertical;
        min-height: 60px;
    }

    .qualidade-item.problema .qualidade-observacao {
        display: block;
    }

    @media (max-width: 768px) {
        .qualidade-item {
            flex-direction: column;
            gap: 12px;
        }

        .qualidade-checkbox {
            width: 50px;
            height: 50px;
            font-size: 20px;
        }

        .qualidade-stats {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php if (!$os_atual): ?>
    <!-- SELETOR -->
    <div class="vend-card">
        <div class="vend-card-head">
            <h3>📋 Selecione uma O.S. para Fazer Inspeção</h3>
        </div>
        <div class="vend-card-body">
            <?php if (!empty($os_lista)): ?>
                <div class="qualidade-seletor">
                    <?php foreach ($os_lista as $os): ?>
                        <a href="?os_id=<?= $os['id'] ?>" class="qualidade-os-item">
                            <div class="qualidade-os-numero">OS <?= htmlspecialchars($os['numero']) ?></div>
                            <div class="qualidade-os-cliente"><?= htmlspecialchars(substr($os['razao_social'], 0, 30)) ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #999;">
                    <p>Nenhuma O.S. disponível</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>
    <!-- CHECKLIST -->
    <div class="qualidade-checklist">
        <div class="qualidade-header">
            <div class="qualidade-header-titulo">OS <?= htmlspecialchars($os_atual['numero']) ?></div>
            <p class="qualidade-header-cliente"><?= htmlspecialchars($os_atual['razao_social']) ?></p>

            <div class="qualidade-stats">
                <?php
                $ok = array_filter($checklist_itens, fn($i) => $i['status'] === 'ok');
                $problema = array_filter($checklist_itens, fn($i) => $i['status'] === 'problema');
                $pendente = array_filter($checklist_itens, fn($i) => $i['status'] === 'pendente');
                $total = count($checklist_itens);
                $percentual = $total > 0 ? round(count($ok) * 100 / $total) : 0;
                ?>
                <div class="qualidade-stat">
                    <div class="qualidade-stat-valor"><?= $percentual ?>%</div>
                    <div class="qualidade-stat-label">Concluído</div>
                </div>
                <div class="qualidade-stat">
                    <div class="qualidade-stat-valor"><?= count($ok) ?>/<?= $total ?></div>
                    <div class="qualidade-stat-label">Aprovados</div>
                </div>
                <div class="qualidade-stat">
                    <div class="qualidade-stat-valor">{{ count($problema) }}</div>
                    <div class="qualidade-stat-label">Problemas</div>
                </div>
            </div>
        </div>

        <div class="qualidade-itens">
            <?php foreach ($checklist_itens as $item): ?>
                <div class="qualidade-item <?= $item['status'] ?>" data-item-id="<?= $item['id'] ?>">
                    <div class="qualidade-checkbox <?= $item['status'] ?>" onclick="abrirOpcoes(this)">
                        <?php
                        if ($item['status'] === 'ok') echo '✓';
                        elseif ($item['status'] === 'problema') echo '✗';
                        else echo '◯';
                        ?>
                    </div>
                    <div class="qualidade-item-content">
                        <div class="qualidade-item-titulo"><?= htmlspecialchars($item['item']) ?></div>
                        <div class="qualidade-item-obs"><?= htmlspecialchars($item['observacao'] ?? '') ?></div>
                        <div class="qualidade-botoes">
                            <button class="qualidade-botao qualidade-botao-ok" onclick="marcarStatus(<?= $item['id'] ?>, 'ok')">
                                ✓ OK
                            </button>
                            <button class="qualidade-botao qualidade-botao-problema" onclick="marcarStatus(<?= $item['id'] ?>, 'problema')">
                                ✗ PROBLEMA
                            </button>
                            <button class="qualidade-botao qualidade-botao-pendente" onclick="marcarStatus(<?= $item['id'] ?>, 'pendente')">
                                ⭕ PENDENTE
                            </button>
                        </div>
                        <?php if ($item['status'] === 'problema'): ?>
                            <textarea class="qualidade-observacao" placeholder="Descrever o problema encontrado..." onchange="atualizarObs(<?= $item['id'] ?>, this.value)">{{ $item['observacao'] ?? '' }}</textarea>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        function marcarStatus(itemId, status) {
            const formData = new FormData();
            formData.append('acao', 'marcar_status');
            formData.append('item_id', itemId);
            formData.append('status', status);

            const obs = document.querySelector(`[data-item-id="${itemId}"] .qualidade-observacao`);
            if (obs) {
                formData.append('observacao', obs.value);
            }

            fetch('<?= SITE_URL ?>/modules/os/qualidade_checklist.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }

        function atualizarObs(itemId, valor) {
            const formData = new FormData();
            formData.append('acao', 'marcar_status');
            formData.append('item_id', itemId);
            formData.append('status', 'problema');
            formData.append('observacao', valor);

            fetch('<?= SITE_URL ?>/modules/os/qualidade_checklist.php', {
                method: 'POST',
                body: formData
            });
        }

        // Auto-refresh
        setInterval(() => { location.reload(); }, 60000);
    </script>

<?php endif; ?>

</div>
        </div>
    </div>

<?php include '../../includes/footer_vendedor.php'; ?>
