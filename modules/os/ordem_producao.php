<?php
/**
 * Ordem de Produção (O.P.) - Painel Integrado
 *
 * Funcionalidades:
 * - Visualização de Ordens de Produção
 * - Geração automática com sequencial
 * - Status e progresso
 * - Impressão em PDF
 * - Rastreamento de etiquetas
 * - Atribuição de responsáveis
 */

require_once '../../config/config.php';
require_once '../../includes/workflow.php';
require_once '../../includes/engenharia.php';

$page_title = 'Ordens de Produção (O.P.)';
$db = getDB();

requirePermission(['master', 'gerente', 'producao', 'projetista', 'programacao']);

// ───────────────────────────────────────────────────────────────
// Criar tabelas se não existirem
// ───────────────────────────────────────────────────────────────

$db->exec("CREATE TABLE IF NOT EXISTS ordens_producao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    os_id INT NOT NULL,
    numero VARCHAR(50) UNIQUE NOT NULL,
    status ENUM('pendente', 'em_producao', 'concluida', 'parada', 'cancelada') DEFAULT 'pendente',
    responsavel_id INT,
    data_inicio DATETIME,
    data_termino DATETIME,
    prazo_original DATETIME,
    observacoes TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE,
    FOREIGN KEY (responsavel_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_os_numero (os_id, numero),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$db->exec("CREATE TABLE IF NOT EXISTS ordens_producao_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    op_id INT NOT NULL,
    os_item_id INT NOT NULL,
    quantidade INT NOT NULL,
    quantidade_produzida INT DEFAULT 0,
    valor_unitario DECIMAL(10,2),
    status ENUM('pendente', 'produzindo', 'concluido', 'com_defeito') DEFAULT 'pendente',
    observacao TEXT,
    data_conclusao DATETIME,
    FOREIGN KEY (op_id) REFERENCES ordens_producao(id) ON DELETE CASCADE,
    FOREIGN KEY (os_item_id) REFERENCES os_itens(id) ON DELETE CASCADE,
    INDEX idx_op_status (op_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$db->exec("CREATE TABLE IF NOT EXISTS ordens_producao_etapas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    op_id INT NOT NULL,
    etapa VARCHAR(50) NOT NULL,
    status ENUM('pendente', 'em_producao', 'concluido', 'parado') DEFAULT 'pendente',
    usuario_id INT,
    data_inicio DATETIME,
    data_conclusao DATETIME,
    observacao TEXT,
    sequencia INT,
    FOREIGN KEY (op_id) REFERENCES ordens_producao(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_op_etapa (op_id, etapa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// ───────────────────────────────────────────────────────────────
// Processar ações
// ───────────────────────────────────────────────────────────────

$acao = $_POST['acao'] ?? $_GET['acao'] ?? null;
$op_id = (int)($_POST['op_id'] ?? $_GET['op_id'] ?? 0);

// Ação: Criar Ordem de Produção
if ($acao === 'criar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $os_id = (int)$_POST['os_id'];

    try {
        $db->beginTransaction();

        // Buscar dados da O.S.
        $stmt = $db->prepare("SELECT numero, data_termino FROM ordens_servico WHERE id = ?");
        $stmt->execute([$os_id]);
        $os = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$os) {
            throw new Exception('O.S. não encontrada');
        }

        // Número da O.P. = número da O.S.
        $numero_op = $os['numero'];

        // Verificar se já existe
        $stmt = $db->prepare("SELECT id FROM ordens_producao WHERE os_id = ? LIMIT 1");
        $stmt->execute([$os_id]);
        $existe = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existe) {
            throw new Exception('Ordem de Produção já existe para esta O.S.');
        }

        // Inserir O.P.
        $stmt = $db->prepare("INSERT INTO ordens_producao (os_id, numero, status, prazo_original, criado_em)
            VALUES (?, ?, 'pendente', ?, NOW())");
        $stmt->execute([$os_id, $numero_op, $os['data_termino']]);
        $op_id_novo = $db->lastInsertId();

        // Buscar itens da O.S.
        $stmt = $db->prepare("SELECT * FROM os_itens WHERE os_id = ?");
        $stmt->execute([$os_id]);
        $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Inserir itens na O.P.
        foreach ($itens as $item) {
            $stmt = $db->prepare("INSERT INTO ordens_producao_itens (op_id, os_item_id, quantidade, valor_unitario, status)
                VALUES (?, ?, ?, ?, 'pendente')");
            $stmt->execute([$op_id_novo, $item['id'], $item['quantidade'], $item['valor_unitario'] ?? 0]);
        }

        // Criar etapas de produção
        $etapas = getValidOSEtapas();
        foreach ($etapas as $seq => $etapa) {
            if (in_array($etapa, ['autorizacao', 'concluida'], true)) continue;

            $stmt = $db->prepare("INSERT INTO ordens_producao_etapas (op_id, etapa, status, sequencia)
                VALUES (?, ?, 'pendente', ?)");
            $stmt->execute([$op_id_novo, $etapa, $seq]);
        }

        $db->commit();

        echo json_encode(['sucesso' => true, 'op_id' => $op_id_novo, 'numero_op' => $numero_op]);
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

// Ação: Atualizar status
if ($acao === 'atualizar_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $op_id = (int)$_POST['op_id'];
    $novo_status = $_POST['status'] ?? '';

    if (!in_array($novo_status, ['pendente', 'em_producao', 'concluida', 'parada', 'cancelada'])) {
        echo json_encode(['sucesso' => false, 'erro' => 'Status inválido']);
        exit;
    }

    try {
        $stmt = $db->prepare("UPDATE ordens_producao SET status = ?, atualizado_em = NOW() WHERE id = ?");
        $stmt->execute([$novo_status, $op_id]);

        echo json_encode(['sucesso' => true, 'mensagem' => 'Status atualizado']);
    } catch (Exception $e) {
        echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

// Ação: Atualizar item
if ($acao === 'atualizar_item' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = (int)$_POST['item_id'];
    $quantidade_produzida = (int)$_POST['quantidade_produzida'];
    $item_status = $_POST['status'] ?? 'pendente';

    try {
        $stmt = $db->prepare("UPDATE ordens_producao_itens SET quantidade_produzida = ?, status = ? WHERE id = ?");
        $stmt->execute([$quantidade_produzida, $item_status, $item_id]);

        echo json_encode(['sucesso' => true]);
    } catch (Exception $e) {
        echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

// Ação: Atualizar etapa
if ($acao === 'atualizar_etapa' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $etapa_id = (int)$_POST['etapa_id'];
    $etapa_status = $_POST['status'] ?? 'pendente';
    $usuario_id = $_SESSION['usuario_id'] ?? null;

    try {
        $db->beginTransaction();

        // Buscar a etapa
        $stmt = $db->prepare("SELECT op_id, status FROM ordens_producao_etapas WHERE id = ?");
        $stmt->execute([$etapa_id]);
        $etapa = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$etapa) {
            throw new Exception('Etapa não encontrada');
        }

        // Atualizar etapa
        $data_inicio = $etapa['status'] === 'pendente' && $etapa_status === 'em_producao' ? 'NOW()' : 'data_inicio';
        $data_conclusao = $etapa_status === 'concluido' ? 'NOW()' : 'NULL';

        $stmt = $db->prepare("UPDATE ordens_producao_etapas SET status = ?, usuario_id = ?, data_inicio = COALESCE(data_inicio, NOW()),
            data_conclusao = $data_conclusao WHERE id = ?");
        $stmt->execute([$etapa_status, $usuario_id, $etapa_id]);

        $db->commit();

        echo json_encode(['sucesso' => true]);
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

// ───────────────────────────────────────────────────────────────
// Buscar dados para exibição
// ───────────────────────────────────────────────────────────────

// Se op_id foi fornecido, buscar detalhes
$op_detalhes = null;
$op_itens = [];
$op_etapas = [];

if ($op_id > 0) {
    $stmt = $db->prepare("SELECT op.*, os.numero as os_numero, c.razao_social, u.nome as responsavel_nome
        FROM ordens_producao op
        LEFT JOIN ordens_servico os ON os.id = op.os_id
        LEFT JOIN clientes c ON c.id = os.cliente_id
        LEFT JOIN usuarios u ON u.id = op.responsavel_id
        WHERE op.id = ?");
    $stmt->execute([$op_id]);
    $op_detalhes = $stmt->fetch(PDO::FETCH_ASSOC);

    // Itens
    $stmt = $db->prepare("SELECT opi.*, oi.produto_id, p.nome as produto_nome, p.codigo as produto_codigo
        FROM ordens_producao_itens opi
        LEFT JOIN os_itens oi ON oi.id = opi.os_item_id
        LEFT JOIN produtos p ON p.id = oi.produto_id
        WHERE opi.op_id = ?
        ORDER BY opi.id");
    $stmt->execute([$op_id]);
    $op_itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Etapas
    $stmt = $db->prepare("SELECT ope.*, u.nome as usuario_nome
        FROM ordens_producao_etapas ope
        LEFT JOIN usuarios u ON u.id = ope.usuario_id
        WHERE ope.op_id = ?
        ORDER BY ope.sequencia");
    $stmt->execute([$op_id]);
    $op_etapas = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Listar últimas O.P.s
$stmt = $db->query("SELECT op.*, os.numero as os_numero, c.razao_social, u.nome as responsavel_nome,
    COUNT(opi.id) as total_itens, SUM(CASE WHEN opi.status = 'concluido' THEN 1 ELSE 0 END) as itens_concluidos
    FROM ordens_producao op
    LEFT JOIN ordens_servico os ON os.id = op.os_id
    LEFT JOIN clientes c ON c.id = os.cliente_id
    LEFT JOIN usuarios u ON u.id = op.responsavel_id
    LEFT JOIN ordens_producao_itens opi ON opi.op_id = op.id
    GROUP BY op.id
    ORDER BY op.criado_em DESC
    LIMIT 20");
$ultimas_ops = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header_vendedor.php';
?>

<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-page-head">
            <h1 class="vend-page-title">📋 Ordens de Produção</h1>
            <button onclick="novaOP()" class="vend-btn-primary">➕ Nova O.P.</button>
        </div>
        <div class="vend-content">

<style>
    .op-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 24px; }

    .op-lista {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow: hidden;
    }

    .op-item {
        padding: 16px;
        border-bottom: 1px solid #e5e7eb;
        cursor: pointer;
        transition: all 0.2s;
    }

    .op-item:hover {
        background: #f9fafb;
        border-left: 4px solid #3b82f6;
        padding-left: 12px;
    }

    .op-item-numero {
        font-weight: 700;
        color: #3b82f6;
        font-size: 14px;
    }

    .op-item-cliente {
        font-size: 12px;
        color: #666;
        margin-top: 4px;
    }

    .op-item-status {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        margin-top: 8px;
    }

    .op-status-pendente { background: #fef3c7; color: #d97706; }
    .op-status-em_producao { background: #dbeafe; color: #2563eb; }
    .op-status-concluida { background: #dcfce7; color: #16a34a; }
    .op-status-parada { background: #fee2e2; color: #dc2626; }

    .op-detalhes {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        padding: 24px;
    }

    .op-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 2px solid #e5e7eb;
    }

    .op-title {
        font-size: 20px;
        font-weight: 700;
        color: #1f2937;
    }

    .op-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .op-info-box {
        padding: 16px;
        background: #f9fafb;
        border-radius: 8px;
    }

    .op-info-label {
        font-size: 12px;
        color: #666;
        font-weight: 600;
        text-transform: uppercase;
    }

    .op-info-valor {
        font-size: 16px;
        font-weight: 700;
        color: #1f2937;
        margin-top: 8px;
    }

    .op-tabela {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 24px;
    }

    .op-tabela th {
        background: #f3f4f6;
        padding: 12px;
        text-align: left;
        font-weight: 600;
        font-size: 12px;
        border-bottom: 2px solid #d1d5db;
        text-transform: uppercase;
    }

    .op-tabela td {
        padding: 12px;
        border-bottom: 1px solid #e5e7eb;
        font-size: 13px;
    }

    .op-tabela .etapa-concluida {
        background: #dcfce7;
        color: #16a34a;
    }

    .op-progresso {
        width: 100%;
        height: 8px;
        background: #e5e7eb;
        border-radius: 4px;
        overflow: hidden;
    }

    .op-progresso-bar {
        height: 100%;
        background: linear-gradient(90deg, #3b82f6, #2563eb);
        transition: width 0.3s;
    }

    @media (max-width: 1200px) {
        .op-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="op-grid">
    <!-- LISTA DE O.P.s -->
    <div>
        <div class="op-lista">
            <div style="padding: 16px; background: #f3f4f6; font-weight: 600;">Últimas O.P.s</div>
            <?php foreach ($ultimas_ops as $op):
                $percentual = $op['total_itens'] > 0 ? ($op['itens_concluidos'] / $op['total_itens'] * 100) : 0;
            ?>
                <div class="op-item" onclick="selecionarOP(<?= $op['id'] ?>)">
                    <div class="op-item-numero">OP <?= htmlspecialchars($op['numero']) ?></div>
                    <div class="op-item-cliente"><?= htmlspecialchars(substr($op['razao_social'] ?? '-', 0, 40)) ?></div>
                    <span class="op-item-status op-status-<?= $op['status'] ?>">
                        <?= ucfirst(str_replace('_', ' ', $op['status'])) ?>
                    </span>
                    <div style="font-size: 11px; color: #999; margin-top: 8px;">
                        Itens: <?= $op['itens_concluidos'] ?? 0 ?>/<?= $op['total_itens'] ?? 0 ?>
                    </div>
                    <div class="op-progresso" style="margin-top: 8px;">
                        <div class="op-progresso-bar" style="width: <?= $percentual ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- DETALHES DA O.P. -->
    <div>
        <?php if ($op_detalhes): ?>
            <div class="op-detalhes">
                <div class="op-header">
                    <div>
                        <div class="op-title">OP <?= htmlspecialchars($op_detalhes['numero']) ?></div>
                        <div style="font-size: 13px; color: #666; margin-top: 4px;">
                            OS <?= htmlspecialchars($op_detalhes['os_numero']) ?>
                        </div>
                    </div>
                    <span class="op-item-status op-status-<?= $op_detalhes['status'] ?>">
                        <?= ucfirst(str_replace('_', ' ', $op_detalhes['status'])) ?>
                    </span>
                </div>

                <div class="op-info-grid">
                    <div class="op-info-box">
                        <div class="op-info-label">Cliente</div>
                        <div class="op-info-valor"><?= htmlspecialchars(substr($op_detalhes['razao_social'] ?? '-', 0, 20)) ?></div>
                    </div>
                    <div class="op-info-box">
                        <div class="op-info-label">Prazo</div>
                        <div class="op-info-valor"><?= !empty($op_detalhes['prazo_original']) ? date('d/m/Y', strtotime($op_detalhes['prazo_original'])) : '-' ?></div>
                    </div>
                    <div class="op-info-box">
                        <div class="op-info-label">Responsável</div>
                        <div class="op-info-valor" style="font-size: 13px;"><?= htmlspecialchars($op_detalhes['responsavel_nome'] ?? '-') ?></div>
                    </div>
                </div>

                <!-- ITENS -->
                <h3 style="margin-top: 24px; margin-bottom: 12px; font-size: 14px; font-weight: 600;">Itens da O.P.</h3>
                <table class="op-tabela">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Qtde</th>
                            <th>Produzido</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($op_itens as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars(substr($item['produto_nome'] ?? $item['produto_codigo'] ?? '-', 0, 30)) ?></td>
                                <td><?= number_format($item['quantidade'], 0, ',', '.') ?></td>
                                <td><?= $item['quantidade_produzida'] ?></td>
                                <td>
                                    <select onchange="atualizarItem(<?= $item['id'] ?>, this.value, <?= $item['quantidade_produzida'] ?>)" class="vend-select" style="font-size: 12px; padding: 4px;">
                                        <option value="pendente" <?= $item['status'] === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                                        <option value="produzindo" <?= $item['status'] === 'produzindo' ? 'selected' : '' ?>>Produzindo</option>
                                        <option value="concluido" <?= $item['status'] === 'concluido' ? 'selected' : '' ?>>Concluído</option>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- ETAPAS -->
                <h3 style="margin-top: 24px; margin-bottom: 12px; font-size: 14px; font-weight: 600;">Etapas de Produção</h3>
                <table class="op-tabela">
                    <thead>
                        <tr>
                            <th>Etapa</th>
                            <th>Status</th>
                            <th>Responsável</th>
                            <th>Duração</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($op_etapas as $etapa):
                            $duracao = '-';
                            if ($etapa['data_inicio'] && $etapa['data_conclusao']) {
                                $inicio = strtotime($etapa['data_inicio']);
                                $fim = strtotime($etapa['data_conclusao']);
                                $duracao = ceil(($fim - $inicio) / 3600) . 'h';
                            }
                        ?>
                            <tr class="<?= $etapa['status'] === 'concluido' ? 'etapa-concluida' : '' ?>">
                                <td><?= ucfirst(str_replace('_', ' ', $etapa['etapa'])) ?></td>
                                <td>
                                    <select onchange="atualizarEtapa(<?= $etapa['id'] ?>, this.value)" class="vend-select" style="font-size: 12px; padding: 4px;">
                                        <option value="pendente" <?= $etapa['status'] === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                                        <option value="em_producao" <?= $etapa['status'] === 'em_producao' ? 'selected' : '' ?>>Em Produção</option>
                                        <option value="concluido" <?= $etapa['status'] === 'concluido' ? 'selected' : '' ?>>Concluído</option>
                                        <option value="parado" <?= $etapa['status'] === 'parado' ? 'selected' : '' ?>>Parado</option>
                                    </select>
                                </td>
                                <td><?= htmlspecialchars($etapa['usuario_nome'] ?? '-') ?></td>
                                <td><?= $duracao ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="margin-top: 24px; display: flex; gap: 12px;">
                    <button class="vend-btn-primary" onclick="abrirPDF(<?= $op_detalhes['id'] ?>)">🖨️ Imprimir PDF</button>
                    <button class="vend-btn-secondary" onclick="gerarEtiquetas(<?= $op_detalhes['id'] ?>)">🏷️ Gerar Etiquetas</button>
                </div>
            </div>
        <?php else: ?>
            <div style="background: white; border-radius: 12px; padding: 60px 20px; text-align: center; color: #999;">
                <p style="font-size: 48px;">📋</p>
                <p>Selecione uma Ordem de Produção</p>
            </div>
        <?php endif; ?>
    </div>
</div>

        </div>
    </div>
</div>

<script>
function selecionarOP(opId) {
    window.location.href = '?op_id=' + opId;
}

function novaOP() {
    const osNumero = prompt('Digite o número da O.S.:', '');
    if (!osNumero) return;

    // Buscar OS por número
    fetch('<?= SITE_URL ?>/api/os.php?acao=buscar_numero&numero=' + encodeURIComponent(osNumero))
        .then(r => r.json())
        .then(data => {
            if (data.sucesso && data.os_id) {
                criarOP(data.os_id);
            } else {
                alert('O.S. não encontrada');
            }
        });
}

function criarOP(osId) {
    const form = new FormData();
    form.append('acao', 'criar');
    form.append('os_id', osId);

    fetch('<?= SITE_URL ?>/modules/os/ordem_producao.php', { method: 'POST', body: form })
        .then(r => r.json())
        .then(data => {
            if (data.sucesso) {
                alert('O.P. criada: ' + data.numero_op);
                location.reload();
            } else {
                alert('Erro: ' + data.erro);
            }
        });
}

function atualizarItem(itemId, status, qtdProduzida) {
    const form = new FormData();
    form.append('acao', 'atualizar_item');
    form.append('item_id', itemId);
    form.append('status', status);
    form.append('quantidade_produzida', qtdProduzida);

    fetch('<?= SITE_URL ?>/modules/os/ordem_producao.php', { method: 'POST', body: form })
        .then(r => r.json())
        .then(data => {
            if (!data.sucesso) alert('Erro: ' + data.erro);
        });
}

function atualizarEtapa(etapaId, status) {
    const form = new FormData();
    form.append('acao', 'atualizar_etapa');
    form.append('etapa_id', etapaId);
    form.append('status', status);

    fetch('<?= SITE_URL ?>/modules/os/ordem_producao.php', { method: 'POST', body: form })
        .then(r => r.json())
        .then(data => {
            if (!data.sucesso) alert('Erro: ' + data.erro);
        });
}

function abrirPDF(opId) {
    window.open('<?= SITE_URL ?>/modules/os/imprimir_op.php?op_id=' + opId, '_blank');
}

function gerarEtiquetas(opId) {
    alert('Redirecionando para gerador de etiquetas...');
    window.location.href = '<?= SITE_URL ?>/modules/os/gerar_etiquetas.php?op_id=' + opId;
}
</script>

<?php include '../../includes/footer_vendedor.php'; ?>
