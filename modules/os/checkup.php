<?php
require_once '../../config/config.php';
require_once '../../includes/engenharia.php';

requirePermission(['master', 'gerente', 'finalizacao', 'vendedor']);
$db = getDB();
ensureEngenhariaSchema($db);
$page_title = 'Checkup de Qualidade';

function ensureQualidadeSchema(PDO $db) {
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;

    if (!shouldRunSchemaSync('qualidade', 86400)) {
        return;
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS qualidade_checklist (
            id INT AUTO_INCREMENT PRIMARY KEY,
            os_id INT NOT NULL,
            usuario_id INT NOT NULL,
            responsavel_qc VARCHAR(120) NOT NULL,
            data_check DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            aprovado TINYINT(1) NOT NULL DEFAULT 0,
            observacoes TEXT NULL,
            motivo_reprovacao TEXT NULL,
            setor_retorno ENUM('solda', 'acabamento', 'montagem') NULL,
            INDEX idx_qc_os (os_id),
            INDEX idx_qc_data (data_check),
            CONSTRAINT fk_qc_os FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE,
            CONSTRAINT fk_qc_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS qualidade_itens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            checklist_id INT NOT NULL,
            item VARCHAR(120) NOT NULL,
            status ENUM('ok', 'erro') NOT NULL DEFAULT 'ok',
            INDEX idx_qci_checklist (checklist_id),
            CONSTRAINT fk_qci_checklist FOREIGN KEY (checklist_id) REFERENCES qualidade_checklist(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");

    $colunas = $db->query("SHOW COLUMNS FROM ordens_servico")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('qualidade_status', $colunas, true)) {
        $db->exec("ALTER TABLE ordens_servico ADD COLUMN qualidade_status ENUM('pendente','aprovada','reprovada') DEFAULT 'pendente' AFTER etapa_atual");
    }
    if (!in_array('qualidade_usuario_id', $colunas, true)) {
        $db->exec("ALTER TABLE ordens_servico ADD COLUMN qualidade_usuario_id INT NULL AFTER qualidade_status");
    }
    if (!in_array('qualidade_data', $colunas, true)) {
        $db->exec("ALTER TABLE ordens_servico ADD COLUMN qualidade_data DATETIME NULL AFTER qualidade_usuario_id");
    }
    if (!in_array('status_producao', $colunas, true)) {
        $db->exec("ALTER TABLE ordens_servico ADD COLUMN status_producao TINYINT NOT NULL DEFAULT 2 COMMENT '1-criada,2-em producao,3-aguardando qualidade,4-qualidade aprovada,5-finalizada,6-expedida' AFTER qualidade_status");
    }
}

function getLatestChecklist(PDO $db, $os_id) {
    $stmt = $db->prepare("SELECT * FROM qualidade_checklist WHERE os_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$os_id]);
    $check = $stmt->fetch();
    if (!$check) {
        return [null, []];
    }

    $stmt_itens = $db->prepare("SELECT item, status FROM qualidade_itens WHERE checklist_id = ?");
    $stmt_itens->execute([$check['id']]);
    $itens = [];
    foreach ($stmt_itens->fetchAll() as $row) {
        $itens[$row['item']] = $row['status'];
    }

    return [$check, $itens];
}

ensureQualidadeSchema($db);

$checklist_padrao = [
    'acabamento_inox' => 'Acabamento do inox sem riscos',
    'soldas_polidas' => 'Soldas polidas',
    'estrutura_alinhada' => 'Estrutura alinhada',
    'medidas_conferidas' => 'Medidas conferidas',
    'produto_limpo' => 'Produto limpo',
    'parafusos_fixados' => 'Parafusos fixados',
    'embalagem_realizada' => 'Embalagem realizada'
];

$os_ref = sanitize($_GET['os'] ?? '');
if ($os_ref === '') {
    setError('O.S não informada.');
    header('Location: finalizacao.php');
    exit;
}

if (ctype_digit($os_ref)) {
    $stmt_os = $db->prepare("
        SELECT os.*, c.razao_social, u.nome as responsavel_nome
        FROM ordens_servico os
        INNER JOIN clientes c ON c.id = os.cliente_id
        LEFT JOIN usuarios u ON u.id = os.qualidade_usuario_id
        WHERE os.numero = ? OR os.id = ?
        ORDER BY (os.numero = ?) DESC
        LIMIT 1
    ");
    $stmt_os->execute([$os_ref, $os_ref, $os_ref]);
} else {
    $stmt_os = $db->prepare("
        SELECT os.*, c.razao_social, u.nome as responsavel_nome
        FROM ordens_servico os
        INNER JOIN clientes c ON c.id = os.cliente_id
        LEFT JOIN usuarios u ON u.id = os.qualidade_usuario_id
        WHERE os.numero = ?
        LIMIT 1
    ");
    $stmt_os->execute([$os_ref]);
}
$os = $stmt_os->fetch();

if (!$os) {
    setError('O.S não encontrada.');
    header('Location: finalizacao.php');
    exit;
}

$itens_os = getItensComerciaisOS($db, (int) $os['id'], (int) ($os['venda_id'] ?? 0));
$produto = $itens_os[0] ?? null;
$produto_nome = '-';
if ($produto) {
    $produto_nome = $produto['produto_nome'] ?: $produto['descricao_manual'];
}

$stmt_arq = $db->prepare("
    SELECT nome_original, nome_arquivo
    FROM os_arquivos
    WHERE os_id = ? AND tipo IN ('projeto', 'projeto_foto', 'projeto_pdf', 'projeto_dxf')
    ORDER BY id DESC
");
$stmt_arq->execute([$os['id']]);
$arquivos_projeto = $stmt_arq->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar_checkup') {
        $resultado = sanitize($_POST['resultado'] ?? '');
        $observacoes = sanitize($_POST['observacoes'] ?? '');
        $motivo = sanitize($_POST['motivo_reprovacao'] ?? '');
        $setor_retorno = sanitize($_POST['setor_retorno'] ?? '');
        $itens_marcados = $_POST['itens'] ?? [];
        $aprovado = ($resultado === 'aprovado') ? 1 : 0;

        if (!in_array($resultado, ['aprovado', 'reprovado'], true)) {
            setError('Selecione APROVADO ou REPROVADO.');
            header('Location: checkup.php?os=' . urlencode($os['numero']));
            exit;
        }

        require_once '../../includes/workflow.php';
        if ($resultado === 'reprovado' && ($motivo === '' || !in_array($setor_retorno, getEtapasBancada(), true))) {
            setError('Para reprovação, informe motivo e setor de retorno.');
            header('Location: checkup.php?os=' . urlencode($os['numero']));
            exit;
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("
                INSERT INTO qualidade_checklist
                    (os_id, usuario_id, responsavel_qc, aprovado, observacoes, motivo_reprovacao, setor_retorno)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $os['id'],
                $_SESSION['usuario_id'],
                $_SESSION['usuario_nome'],
                $aprovado,
                $observacoes ?: null,
                $motivo ?: null,
                $setor_retorno ?: null
            ]);
            $checklist_id = (int) $db->lastInsertId();

            $stmt_item = $db->prepare("INSERT INTO qualidade_itens (checklist_id, item, status) VALUES (?, ?, ?)");
            foreach ($checklist_padrao as $item_key => $label) {
                $status = isset($itens_marcados[$item_key]) ? 'ok' : 'erro';
                $stmt_item->execute([$checklist_id, $item_key, $status]);
            }

            if ($aprovado) {
                $stmt = $db->prepare("
                    UPDATE ordens_servico
                    SET qualidade_status = 'aprovada',
                        status_producao = 4,
                        qualidade_data = NOW(),
                        qualidade_usuario_id = ?
                    WHERE id = ?
                ");
                $stmt->execute([$_SESSION['usuario_id'], $os['id']]);

                $obs = 'CHECKUP APROVADO por ' . $_SESSION['usuario_nome'] . '.';
                $stmt = $db->prepare("INSERT INTO os_observacoes (os_id, tipo_setor, observacao, usuario_id) VALUES (?, 'producao', ?, ?)");
                $stmt->execute([$os['id'], $obs, $_SESSION['usuario_id']]);
            } else {
                $stmt = $db->prepare("
                    UPDATE ordens_servico
                    SET qualidade_status = 'reprovada',
                        status_producao = 2,
                        status = 'em_producao',
                        etapa_atual = ?,
                        qualidade_data = NOW(),
                        qualidade_usuario_id = ?
                    WHERE id = ?
                ");
                $stmt->execute([$setor_retorno, $_SESSION['usuario_id'], $os['id']]);

                $stmt = $db->prepare("
                    INSERT INTO os_observacoes (os_id, tipo_setor, observacao, usuario_id)
                    VALUES (?, 'producao', ?, ?)
                ");
                $texto = 'CHECKUP REPROVADO. Motivo: ' . $motivo . '. Retorno para setor: ' . ucfirst($setor_retorno) . '.';
                $stmt->execute([$os['id'], $texto, $_SESSION['usuario_id']]);
            }

            $db->commit();
            setSuccess($aprovado ? 'Checkup aprovado com sucesso.' : 'Checkup reprovado e OS retornada para correção.');
        } catch (Exception $e) {
            $db->rollBack();
            setError('Erro ao salvar checkup: ' . $e->getMessage());
        }

        header('Location: checkup.php?os=' . urlencode($os['numero']));
        exit;
    }

    if ($acao === 'finalizar_os') {
        [$latest_check] = getLatestChecklist($db, $os['id']);
        if (!$latest_check || (int) $latest_check['aprovado'] !== 1) {
            setError('A O.S precisa ter checkup aprovado para finalizar.');
            header('Location: checkup.php?os=' . urlencode($os['numero']));
            exit;
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("
                UPDATE ordens_servico
                SET status = 'concluida',
                    etapa_atual = 'concluida',
                    data_termino = CURDATE(),
                    status_producao = 5
                WHERE id = ?
            ");
            $stmt->execute([$os['id']]);

            $stmt = $db->prepare("
                UPDATE vendas v
                INNER JOIN ordens_servico os ON os.venda_id = v.id
                SET v.status = 'concluida'
                WHERE os.id = ?
            ");
            $stmt->execute([$os['id']]);

            $obs = 'O.S finalizada pela qualidade por ' . $_SESSION['usuario_nome'] . '.';
            $stmt = $db->prepare("INSERT INTO os_observacoes (os_id, tipo_setor, observacao, usuario_id) VALUES (?, 'producao', ?, ?)");
            $stmt->execute([$os['id'], $obs, $_SESSION['usuario_id']]);

            $db->commit();
            setSuccess('O.S finalizada com sucesso.');
        } catch (Exception $e) {
            $db->rollBack();
            setError('Erro ao finalizar O.S: ' . $e->getMessage());
        }

        header('Location: checkup.php?os=' . urlencode($os['numero']));
        exit;
    }

    if ($acao === 'enviar_expedicao') {
        $stmt = $db->prepare("UPDATE ordens_servico SET status_producao = 6 WHERE id = ?");
        $stmt->execute([$os['id']]);
        $obs = 'O.S enviada para expedição por ' . $_SESSION['usuario_nome'] . '.';
        $stmt = $db->prepare("INSERT INTO os_observacoes (os_id, tipo_setor, observacao, usuario_id) VALUES (?, 'producao', ?, ?)");
        $stmt->execute([$os['id'], $obs, $_SESSION['usuario_id']]);
        setSuccess('O.S enviada para expedição.');
        header('Location: checkup.php?os=' . urlencode($os['numero']));
        exit;
    }
}

[$latest_check, $latest_items] = getLatestChecklist($db, $os['id']);
$pode_imprimir_finalizar = $latest_check && (int) $latest_check['aprovado'] === 1;
$os_finalizada = ((int) ($os['status_producao'] ?? 0) >= 5) || $os['status'] === 'concluida';
$os_expedida = ((int) ($os['status_producao'] ?? 0) >= 6);

include '../../includes/header_vendedor.php';
?>
<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main"><div class="vend-page-head"><h1 class="vend-page-title">Checkup - <?php echo htmlspecialchars($os['numero']); ?></h1></div><div class="vend-content">
<style>
.thumb-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; margin-top: 10px; }
.thumb-card { border: 1px solid #ddd; border-radius: 8px; padding: 8px; background: #fff; text-align: center; }
.thumb-card img { width: 100%; height: 95px; object-fit: cover; border-radius: 4px; border: 1px solid #eee; cursor: zoom-in; }
.thumb-name { font-size: 11px; margin-top: 6px; word-break: break-word; color: #333; }
.lightbox-modal { display: none; position: fixed; z-index: 10001; inset: 0; background: rgba(0,0,0,0.88); align-items: center; justify-content: center; padding: 20px; }
.lightbox-modal.show { display: flex; }
.lightbox-content { max-width: 95vw; max-height: 90vh; border-radius: 6px; }
.lightbox-close { position: absolute; top: 16px; right: 20px; color: #fff; font-size: 34px; border: 0; background: transparent; cursor: pointer; }
.lightbox-caption { position: absolute; bottom: 16px; left: 0; right: 0; text-align: center; color: #fff; font-size: 14px; }
</style>
    <div class="vend-card">
        <div class="vend-card-header d-flex justify-content-between align-items-center">
            <h3 class="m-0"><i class="fas fa-clipboard-list"></i> Checkup - <?php echo htmlspecialchars($os['numero']); ?></h3>
            <a href="finalizacao.php" class="vbtn-sm btn-sm"><i class="fas fa-arrow-left"></i> Voltar</a>
        </div>
        <div class="vend-card-body">
            <div class="row mb-3">
                <div class="col-md-4"><strong>O.S:</strong> <?php echo htmlspecialchars($os['numero']); ?></div>
                <div class="col-md-4"><strong>Cliente:</strong> <?php echo htmlspecialchars($os['razao_social']); ?></div>
                <div class="col-md-4"><strong>Produto:</strong> <?php echo htmlspecialchars($produto_nome); ?></div>
            </div>
            <div class="row mb-4">
                <div class="col-md-6"><strong>Responsável pela inspeção:</strong> <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?></div>
                <div class="col-md-6"><strong>Status Qualidade:</strong>
                    <?php if ($os_expedida): ?>
                        <span class="vbadge badge-primary">Expedida</span>
                    <?php elseif ($os_finalizada): ?>
                        <span class="vbadge badge-success">Finalizada</span>
                    <?php elseif (($os['qualidade_status'] ?? 'pendente') === 'aprovada'): ?>
                        <span class="vbadge badge-success">Aprovada</span>
                    <?php elseif (($os['qualidade_status'] ?? 'pendente') === 'reprovada'): ?>
                        <span class="vbadge badge-danger">Reprovada</span>
                    <?php else: ?>
                        <span class="vbadge badge-warning">Aguardando qualidade</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php
            $imagens_projeto = [];
            foreach ($arquivos_projeto as $arq) {
                $ext = strtolower(pathinfo($arq['nome_arquivo'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                    $imagens_projeto[] = $arq;
                }
            }
            ?>
            <?php if (!empty($imagens_projeto)): ?>
                <div class="mb-4">
                    <strong>Imagem anexada pelo projetista:</strong>
                    <div class="thumb-grid">
                        <?php foreach ($imagens_projeto as $arq): ?>
                            <?php $arquivo_url = SITE_URL . '/assets/uploads/projetos/' . rawurlencode($arq['nome_arquivo']); ?>
                            <div class="thumb-card">
                                <img src="<?php echo htmlspecialchars($arquivo_url); ?>"
                                     alt="<?php echo htmlspecialchars($arq['nome_original']); ?>"
                                     onclick="abrirLightbox('<?php echo htmlspecialchars($arquivo_url, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($arq['nome_original'], ENT_QUOTES, 'UTF-8'); ?>')">
                                <div class="thumb-name"><?php echo htmlspecialchars($arq['nome_original']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" class="border rounded p-3">
                <input type="hidden" name="acao" value="salvar_checkup">
                <h5 class="mb-3"><i class="fas fa-check-square"></i> CHECKLIST</h5>
                <?php foreach ($checklist_padrao as $key => $label): ?>
                    <?php $checked = (($latest_items[$key] ?? '') === 'ok') ? 'checked' : ''; ?>
                    <div class="form-group form-check">
                        <input type="checkbox" class="form-check-input" name="itens[<?php echo $key; ?>]" id="<?php echo $key; ?>" value="ok" <?php echo $checked; ?>>
                        <label class="form-check-label" for="<?php echo $key; ?>">☑ <?php echo htmlspecialchars($label); ?></label>
                    </div>
                <?php endforeach; ?>

                <div class="form-group mt-3">
                    <label for="observacoes"><strong>Observações:</strong></label>
                    <textarea id="observacoes" name="observacoes" class="form-control" rows="3"><?php echo htmlspecialchars($latest_check['observacoes'] ?? ''); ?></textarea>
                </div>

                <div class="form-group mt-3">
                    <label><strong>Resultado</strong></label>
                    <div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="resultado" id="resultado_aprovado" value="aprovado" <?php echo ($latest_check && (int) $latest_check['aprovado'] === 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="resultado_aprovado">APROVADO</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="resultado" id="resultado_reprovado" value="reprovado" <?php echo ($latest_check && (int) $latest_check['aprovado'] === 0) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="resultado_reprovado">REPROVADO</label>
                        </div>
                    </div>
                </div>

                <div id="bloco_reprovacao" class="border rounded p-3 mt-3">
                    <div class="form-group">
                        <label for="motivo_reprovacao"><strong>Motivo</strong></label>
                        <textarea id="motivo_reprovacao" name="motivo_reprovacao" class="form-control" rows="2"><?php echo htmlspecialchars($latest_check['motivo_reprovacao'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group mb-0">
                        <label for="setor_retorno"><strong>Enviar para setor</strong></label>
                        <select id="setor_retorno" name="setor_retorno" class="form-control">
                            <option value="">Selecione</option>
                            <option value="solda" <?php echo (($latest_check['setor_retorno'] ?? '') === 'solda') ? 'selected' : ''; ?>>Solda</option>
                            <option value="acabamento" <?php echo (($latest_check['setor_retorno'] ?? '') === 'acabamento') ? 'selected' : ''; ?>>Polimento</option>
                            <option value="montagem" <?php echo (($latest_check['setor_retorno'] ?? '') === 'montagem') ? 'selected' : ''; ?>>Montagem</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="vbtn-sm mt-3">
                    <i class="fas fa-save"></i> Salvar Checkup
                </button>
            </form>

            <?php if ($pode_imprimir_finalizar): ?>
                <div class="mt-4 p-3 border rounded">
                    <h5><i class="fas fa-cogs"></i> Ações após aprovação</h5>
                    <a target="_blank" class="vbtn-sm mr-2" href="imprimir_etiqueta.php?os_id=<?php echo (int) $os['id']; ?>">
                        <i class="fas fa-print"></i> IMPRIMIR ETIQUETA
                    </a>
                    <?php if (!$os_finalizada): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="acao" value="finalizar_os">
                            <button type="submit" class="vbtn-sm">
                                <i class="fas fa-check-circle"></i> FINALIZAR OS
                            </button>
                        </form>
                    <?php endif; ?>
                    <?php if ($os_finalizada && !$os_expedida): ?>
                        <form method="POST" class="d-inline ml-2">
                            <input type="hidden" name="acao" value="enviar_expedicao">
<button type="submit" class="vbtn-sm">
                                 <i class="fas fa-truck"></i> ENVIAR PARA EXPEDIÇÃO
                             </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>

<div id="lightboxModal" class="lightbox-modal">
    <button type="button" class="lightbox-close" onclick="fecharLightbox()">&times;</button>
    <img id="lightboxImage" class="lightbox-content" src="" alt="Visualizacao ampliada">
    <div id="lightboxCaption" class="lightbox-caption"></div>
</div>

<script>
function alternarReprovacao() {
    const reprovado = document.getElementById('resultado_reprovado').checked;
    const bloco = document.getElementById('bloco_reprovacao');
    bloco.style.display = reprovado ? 'block' : 'none';
}

document.getElementById('resultado_aprovado').addEventListener('change', alternarReprovacao);
document.getElementById('resultado_reprovado').addEventListener('change', alternarReprovacao);
alternarReprovacao();

function abrirLightbox(url, legenda) {
    document.getElementById('lightboxImage').src = url;
    document.getElementById('lightboxCaption').textContent = legenda || '';
    document.getElementById('lightboxModal').classList.add('show');
}

function fecharLightbox() {
    const modal = document.getElementById('lightboxModal');
    modal.classList.remove('show');
    document.getElementById('lightboxImage').src = '';
    document.getElementById('lightboxCaption').textContent = '';
}

window.addEventListener('click', function (event) {
    const lightbox = document.getElementById('lightboxModal');
    if (event.target === lightbox) {
        fecharLightbox();
    }
});
</script>

<?php include '../../includes/footer_vendedor.php'; ?>


