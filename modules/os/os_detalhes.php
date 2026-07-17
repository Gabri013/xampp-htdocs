<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/engenharia.php';
// Leitura liberada também para gestão e setores de produção (ver detalhes/etapas da O.S.)
requirePermission(['master', 'vendedor', 'projetista', 'gerente', 'producao', 'engenharia', 'programacao', 'corte', 'dobra', 'tubo', 'solda', 'mobiliario', 'coccao', 'refrigeracao', 'acabamento', 'montagem', 'embalagem', 'finalizacao']);

// Ações de escrita (gerar OP, propostas, anexos) continuam restritas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hasPermission(['master', 'vendedor', 'projetista', 'gerente'])) {
    setError('Você não tem permissão para executar esta ação.');
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$page_title = 'Detalhes da O.S.';
$db = getDB();
ensureOrdensServicoIndependentesSchema($db);
ensureEngenhariaSchema($db);

function getTipoArquivoProducao(string $nomeArquivo): string
{
    if (preg_match('/\.(dxf|dwg)$/i', $nomeArquivo)) {
        return 'projeto_dxf';
    }
    if (preg_match('/\.(pdf)$/i', $nomeArquivo)) {
        return 'projeto_pdf';
    }
    if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $nomeArquivo)) {
        return 'projeto_foto';
    }
    if (isArquivo3D($nomeArquivo)) {
        return 'projeto_3d';
    }
    return 'projeto';
}

// Primeira etapa do planejamento da O.S. (os_etapas_producao); usa o fluxo
// canônico como ordem e 'corte' apenas como último recurso.
function getPrimeiraEtapaPlanejada(PDO $db, int $osId): string
{
    require_once __DIR__ . '/../../includes/workflow.php';
    $stmtVenda = $db->prepare("SELECT venda_id FROM ordens_servico WHERE id = ?");
    $stmtVenda->execute([$osId]);
    $vendaId = (int) $stmtVenda->fetchColumn();

    $etapasPlanejadas = sincronizarPlanejamentoOS($db, $osId, max(0, $vendaId));
    $etapas = array_column($etapasPlanejadas, 'etapa');
    if (!empty($etapas)) {
        foreach (getEtapaFluxo() as $etapaFluxo) {
            if (in_array($etapaFluxo, $etapas, true)) {
                return $etapaFluxo;
            }
        }
    }
    return 'corte';
}

function garantirOrdemProducao(PDO $db, int $osId, int $usuarioId): array
{
    require_once __DIR__ . '/../../includes/workflow.php';
    $stmt = $db->prepare("SELECT id, numero, status FROM ordens_producao WHERE os_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$osId]);
    $op = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($op) {
        return ['created' => false, 'op' => $op];
    }

    // Nº da OP = nº da O.S.
    $stmtNum = $db->prepare("SELECT numero FROM ordens_servico WHERE id = ? LIMIT 1");
    $stmtNum->execute([$osId]);
    $numeroOp = (string) $stmtNum->fetchColumn();
    $stmt = $db->prepare("INSERT INTO ordens_producao (os_id, numero, status, criado_em) VALUES (?, ?, 'pendente', NOW())");
    $stmt->execute([$osId, $numeroOp]);

    $stmtStatus = $db->prepare("SELECT status FROM ordens_servico WHERE id = ? LIMIT 1");
    $stmtStatus->execute([$osId]);
    $statusAtual = (string) $stmtStatus->fetchColumn();
    $validation = validateOSStatusTransition($statusAtual, 'em_producao', $_SESSION['usuario_tipo'] ?? '');
    if (!$validation['valid']) {
        throw new RuntimeException($validation['message']);
    }

    $etapaInicial = getPrimeiraEtapaPlanejada($db, $osId);
    $stmt = $db->prepare("UPDATE ordens_servico SET status = 'em_producao', etapa_atual = ? WHERE id = ?");
    $stmt->execute([$etapaInicial, $osId]);

    $stmt = $db->prepare("INSERT INTO os_historico_status (os_id, status_anterior, status_novo, usuario_id, observacao) VALUES (?, ?, 'em_producao', ?, ?)");
    $stmt->execute([$osId, $statusAtual ?: 'pendente', $usuarioId, 'Ordem de produção gerada e liberada para ' . $etapaInicial]);

    return [
        'created' => true,
        'op' => [
            'id' => (int) $db->lastInsertId(),
            'numero' => $numeroOp,
            'status' => 'pendente',
        ],
    ];
}

$os_id = isset($_GET['os_id']) ? (int)$_GET['os_id'] : 0;
if ($os_id <= 0) {
    header('Location: projetista.php');
    exit;
}

$os = null;
$op_atual = null;

// Carregar dados da OS primeiro para permitir ações de produção.
$s = $db->prepare("SELECT o.*, c.razao_social, c.nome_fantasia, v.numero as venda_numero, v.data_venda, COALESCE(uv.nome,'-') as vendedor_nome, COALESCE(v.observacoes,'') as obs_vendedor FROM ordens_servico o LEFT JOIN clientes c ON o.cliente_id = c.id LEFT JOIN vendas v ON o.venda_id = v.id LEFT JOIN usuarios uv ON v.usuario_id = uv.id WHERE o.id = ?");
$s->execute([$os_id]);
$os = $s->fetch(PDO::FETCH_ASSOC);
if (!$os) {
    header('Location: projetista.php');
    exit;
}

$stmtOpAtual = $db->prepare("SELECT id, numero, status FROM ordens_producao WHERE os_id = ? ORDER BY id DESC LIMIT 1");
$stmtOpAtual->execute([$os_id]);
$op_atual = $stmtOpAtual->fetch(PDO::FETCH_ASSOC) ?: null;

// POST: gerar OP em lote para a O.S.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'gerar_op_lote') {
    try {
        $db->beginTransaction();
        $opInfo = garantirOrdemProducao($db, $os_id, (int) $_SESSION['usuario_id']);

        $stmtStatus = $db->prepare("SELECT status FROM ordens_servico WHERE id = ? LIMIT 1");
        $stmtStatus->execute([$os_id]);
        $statusAtual = (string) $stmtStatus->fetchColumn();
        if ($statusAtual !== 'em_producao') {
            $etapaInicial = getPrimeiraEtapaPlanejada($db, (int) $os_id);
            $stmt = $db->prepare("UPDATE ordens_servico SET status = 'em_producao', etapa_atual = ? WHERE id = ?");
            $stmt->execute([$etapaInicial, $os_id]);

            $stmt = $db->prepare("INSERT INTO os_historico_status (os_id, status_anterior, status_novo, usuario_id, observacao) VALUES (?, ?, 'em_producao', ?, ?)");
            $stmt->execute([$os_id, $statusAtual ?: 'pendente', (int) $_SESSION['usuario_id'], 'Ordem de produção liberada para ' . $etapaInicial]);
        }
        $db->commit();
        setSuccess($opInfo['created'] ? 'Ordem de produção gerada para a O.S.' : 'Esta O.S. já possuía ordem de produção.');
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        setError('Erro ao gerar OP: ' . $e->getMessage());
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// POST: gerar OP individual do item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'gerar_op_item') {
    $itemId = (int) ($_POST['os_item_id'] ?? 0);
    if ($itemId <= 0) {
        setError('Item inválido.');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    try {
        $db->beginTransaction();
        $opInfo = garantirOrdemProducao($db, $os_id, (int) $_SESSION['usuario_id']);

        $stmtStatus = $db->prepare("SELECT status FROM ordens_servico WHERE id = ? LIMIT 1");
        $stmtStatus->execute([$os_id]);
        $statusAtual = (string) $stmtStatus->fetchColumn();
        if ($statusAtual !== 'em_producao') {
            $etapaInicial = getPrimeiraEtapaPlanejada($db, (int) $os_id);
            $stmt = $db->prepare("UPDATE ordens_servico SET status = 'em_producao', etapa_atual = ? WHERE id = ?");
            $stmt->execute([$etapaInicial, $os_id]);

            $stmt = $db->prepare("INSERT INTO os_historico_status (os_id, status_anterior, status_novo, usuario_id, observacao) VALUES (?, ?, 'em_producao', ?, ?)");
            $stmt->execute([$os_id, $statusAtual ?: 'pendente', (int) $_SESSION['usuario_id'], 'Ordem de produção liberada para ' . $etapaInicial]);
        }
        $db->commit();
        setSuccess($opInfo['created'] ? 'Ordem de produção gerada para o item.' : 'Item vinculado a uma OP já existente.');
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        setError('Erro ao gerar OP do item: ' . $e->getMessage());
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// POST: anexar arquivo(s) por item — aceita seleção múltipla (3 a 50
// DXFs/PDFs/3D de uma vez, como o projetista exporta do SolidWorks)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'anexar_arquivo_item') {
    $osId = (int)($_POST['os_id'] ?? 0);
    $itemId = (int)($_POST['os_item_id'] ?? 0);

    if ($osId > 0 && $itemId > 0 && !empty($_SESSION['usuario_id'])) {
        // Normaliza: input single (name="arquivo") ou múltiplo (name="arquivo[]")
        $arquivos = [];
        if (isset($_FILES['arquivo'])) {
            if (is_array($_FILES['arquivo']['name'])) {
                foreach ($_FILES['arquivo']['name'] as $k => $nome) {
                    $arquivos[] = [
                        'name' => $nome,
                        'type' => $_FILES['arquivo']['type'][$k],
                        'tmp_name' => $_FILES['arquivo']['tmp_name'][$k],
                        'error' => $_FILES['arquivo']['error'][$k],
                        'size' => $_FILES['arquivo']['size'][$k],
                    ];
                }
            } else {
                $arquivos[] = $_FILES['arquivo'];
            }
        }

        // A tabela os_itens_arquivos referencia os_itens(id). Em O.S. vinda de
        // venda, o item está em vendas_itens — então só gravamos o vínculo
        // por item quando o id existe mesmo em os_itens (O.S. independente).
        $itemExisteEmOsItens = false;
        if ($itemId > 0) {
            $chkItem = $db->prepare("SELECT 1 FROM os_itens WHERE id = ? AND os_id = ?");
            $chkItem->execute([$itemId, $osId]);
            $itemExisteEmOsItens = (bool) $chkItem->fetchColumn();
        }

        $ok = 0; $erros = [];
        foreach ($arquivos as $arq) {
            if (($arq['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
            $tipoArquivo = getTipoArquivoProducao($arq['name']);
            $up = uploadFile($arq, 'projetos');
            if ($up['success'] ?? false) {
                // Anexo da O.S. (tabela lida por qualidade, impressão, 3D, avanço)
                $db->prepare("INSERT INTO os_arquivos (os_id, tipo, nome_original, nome_arquivo, usuario_id) VALUES (?, ?, ?, ?, ?)")
                   ->execute([$osId, $tipoArquivo, $arq['name'], $up['filename'], $_SESSION['usuario_id']]);
                // Vínculo por item apenas quando o item existe em os_itens
                if ($itemExisteEmOsItens) {
                    $db->prepare("INSERT INTO os_itens_arquivos (os_id, os_item_id, tipo, nome_original, nome_arquivo, usuario_id) VALUES (?, ?, ?, ?, ?, ?)")
                       ->execute([$osId, $itemId, $tipoArquivo, $arq['name'], $up['filename'], $_SESSION['usuario_id']]);
                }
                $ok++;
            } else {
                $erros[] = $arq['name'] . ': ' . ($up['message'] ?? 'arquivo inválido');
            }
        }

        if ($ok > 0 && empty($erros)) {
            setSuccess($ok . ' arquivo(s) anexado(s) ao item com sucesso.');
        } elseif ($ok > 0) {
            setError($ok . ' anexado(s), mas houve falhas: ' . implode(' | ', $erros));
        } else {
            setError('Erro ao anexar: ' . (empty($erros) ? 'nenhum arquivo recebido' : implode(' | ', $erros)));
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// POST: salvar proposta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar_proposta') {
    require_once __DIR__ . '/../../includes/workflow.php';
    $uid = (int)$_SESSION['usuario_id'];
    $statusAtual = (string) ($os['status'] ?? '');
    $validation = validateOSStatusTransition($statusAtual, 'proposta', $_SESSION['usuario_tipo'] ?? '');
    if (!$validation['valid']) {
        setError($validation['message']);
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $alteracoes = trim($_POST['alteracoes'] ?? '');
    $up = uploadFile($_FILES['proposta_pdf'], 'propostas');
    if ($up['success'] ?? false) {
        $db->prepare("UPDATE ordens_servico SET tipo='projeto', alteracoes_projeto=?, status='proposta' WHERE id=?")
           ->execute([$alteracoes, $os_id]);
        $db->prepare("INSERT INTO os_arquivos (os_id, tipo, nome_original, nome_arquivo, usuario_id) VALUES (?, 'projeto_pdf', ?, ?, ?)")
           ->execute([$os_id, $_FILES['proposta_pdf']['name'], $up['filename'], $uid]);
        $db->prepare("INSERT INTO os_historico_status (os_id, status_anterior, status_novo, usuario_id, observacao) VALUES (?, ?, 'proposta', ?, 'Proposta enviada para aprovação')")
           ->execute([$os_id, $statusAtual, $uid]);
        setSuccess('Proposta enviada com sucesso!');
    } else { setError('Erro ao enviar proposta: ' . ($up['message'] ?? '')); }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// POST: aprovar proposta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'aprovar_proposta') {
    require_once __DIR__ . '/../../includes/workflow.php';
    $uid = (int)$_SESSION['usuario_id'];
    $validation = validateCanApproveProposal($_SESSION['usuario_tipo'] ?? '');
    if (!$validation['valid']) {
        setError($validation['message']);
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $statusAtual = (string) ($os['status'] ?? '');
    $validation = validateOSStatusTransition($statusAtual, 'em_producao', $_SESSION['usuario_tipo'] ?? '');
    if (!$validation['valid']) {
        setError($validation['message']);
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $etapaInicialAprov = getPrimeiraEtapaPlanejada($db, (int) $os_id);
    $db->prepare("UPDATE ordens_servico SET status='em_producao', etapa_atual=? WHERE id=?")->execute([$etapaInicialAprov, $os_id]);
    $db->prepare("INSERT INTO os_historico_status (os_id, status_anterior, status_novo, usuario_id, observacao) VALUES (?, ?, 'em_producao', ?, 'Proposta aprovada')")
       ->execute([$os_id, $statusAtual, $uid]);
    setSuccess('Proposta aprovada!');
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// POST: solicitar alteração (cliente ou projetista pediu mudança de medida)
// — volta para o Projetista refazer o desenho proposta (status em_revisao)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'devolver_proposta') {
    require_once __DIR__ . '/../../includes/workflow.php';
    $uid = (int)$_SESSION['usuario_id'];
    $motivo = trim($_POST['motivo'] ?? '');
    $statusAtual = (string) ($os['status'] ?? '');
    $validation = validateOSStatusTransition($statusAtual, 'em_revisao', $_SESSION['usuario_tipo'] ?? '');
    if (!$validation['valid']) {
        setError($validation['message']);
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $db->prepare("UPDATE ordens_servico SET status='em_revisao', etapa_atual=NULL WHERE id=?")->execute([$os_id]);
    $db->prepare("INSERT INTO os_historico_status (os_id, status_anterior, status_novo, usuario_id, observacao) VALUES (?, ?, 'em_revisao', ?, ?)")
       ->execute([$os_id, $statusAtual, $uid, 'Alteração solicitada: ' . ($motivo ?: 'sem motivo informado')]);
    setSuccess('Alteração solicitada — a O.S. voltou para o Projetista refazer o desenho proposta.');
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// POST: salvar roteiro de produção (projetista marca por quais setores
// o projeto deve passar). Etapas já iniciadas/concluídas ficam travadas.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar_roteiro') {
    require_once __DIR__ . '/../../includes/workflow.php';
    $uid = (int)$_SESSION['usuario_id'];
    $statusAtual = (string) ($os['status'] ?? '');
    $podeEditarRoteiro = hasPermission(['master', 'projetista', 'gerente'])
        && (in_array($statusAtual, ['pendente', 'em_projeto', 'proposta', 'em_revisao'], true)
            || ($statusAtual === 'em_producao' && ($os['etapa_atual'] ?? '') === 'engenharia'));

    if (!$podeEditarRoteiro) {
        setError('O roteiro só pode ser alterado pelo Projetista/gestão antes da produção ou enquanto a O.S. está no Projetista.');
    } else {
        $selecionadas = array_values(array_intersect((array) ($_POST['roteiro_etapas'] ?? []), getEtapasBancada()));
        // engenharia (Projetista) sempre faz parte do roteiro
        if (!in_array('engenharia', $selecionadas, true)) {
            array_unshift($selecionadas, 'engenharia');
        }
        try {
            $db->beginTransaction();
            $stmtEx = $db->prepare("SELECT etapa, status FROM os_etapas_producao WHERE os_id = ?");
            $stmtEx->execute([$os_id]);
            $existentes = [];
            foreach ($stmtEx->fetchAll(PDO::FETCH_ASSOC) as $ex) {
                $existentes[$ex['etapa']] = $ex['status'];
            }
            // remove etapas desmarcadas que ainda estão pendentes
            $stmtDel = $db->prepare("DELETE FROM os_etapas_producao WHERE os_id = ? AND etapa = ? AND status = 'pendente'");
            foreach ($existentes as $etapaEx => $statusEx) {
                if ($statusEx === 'pendente' && !in_array($etapaEx, $selecionadas, true)) {
                    $stmtDel->execute([$os_id, $etapaEx]);
                }
            }
            // adiciona etapas marcadas que não existem
            $stmtIns = $db->prepare("INSERT INTO os_etapas_producao (os_id, etapa, status) VALUES (?, ?, 'pendente')");
            foreach ($selecionadas as $etapaSel) {
                if (!isset($existentes[$etapaSel])) {
                    $stmtIns->execute([$os_id, $etapaSel]);
                }
            }
            $db->prepare("UPDATE ordens_servico SET roteiro_manual = 1 WHERE id = ?")->execute([$os_id]);
            $db->prepare("INSERT INTO os_historico_status (os_id, status_anterior, status_novo, usuario_id, observacao) VALUES (?, ?, ?, ?, ?)")
               ->execute([$os_id, $statusAtual, $statusAtual, $uid, 'Roteiro definido pelo projetista: ' . implode(', ', array_map('getEtapaLabel', $selecionadas))]);
            $db->commit();
            setSuccess('Roteiro de produção salvo! O projeto vai passar por: ' . implode(' → ', array_map('getEtapaLabel', $selecionadas)));
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            setError('Erro ao salvar o roteiro: ' . $e->getMessage());
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// POST: solicitar material (matéria-prima/insumo — rodízios, cubas,
// chapas no tamanho de melhor aproveitamento etc.)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'solicitar_material') {
    $uid = (int)$_SESSION['usuario_id'];
    if (!hasPermission(['master', 'projetista', 'gerente'])) {
        setError('Somente Projetista/gestão podem solicitar materiais.');
    } else {
        $matDescricao = trim($_POST['mat_descricao'] ?? '');
        $matQtd = (float) str_replace(',', '.', (string) ($_POST['mat_quantidade'] ?? 1));
        $matUnidade = trim($_POST['mat_unidade'] ?? 'un') ?: 'un';
        $matObs = trim($_POST['mat_observacao'] ?? '');
        // se a descrição bate com um insumo do catálogo (por código no início), vincula
        $matInsumoId = null;
        if (preg_match('/^(\S+)\s*[—-]/u', $matDescricao, $mIns)) {
            $stmtIns = $db->prepare("SELECT id FROM insumos WHERE codigo = ? LIMIT 1");
            $stmtIns->execute([$mIns[1]]);
            $matInsumoId = $stmtIns->fetchColumn() ?: null;
        }
        if ($matDescricao === '' || $matQtd <= 0) {
            setError('Informe o material e a quantidade.');
        } else {
            $db->prepare("INSERT INTO os_materiais_solicitados (os_id, insumo_id, descricao, quantidade, unidade, observacao, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?)")
               ->execute([$os_id, $matInsumoId, $matDescricao, $matQtd, $matUnidade, $matObs ?: null, $uid]);
            setSuccess('Material solicitado!');
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// POST: remover/atender solicitação de material
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['acao'] ?? '', ['remover_material', 'atender_material'], true)) {
    $matId = (int) ($_POST['material_id'] ?? 0);
    if (!hasPermission(['master', 'projetista', 'gerente'])) {
        setError('Sem permissão.');
    } elseif ($matId > 0) {
        if (($_POST['acao'] ?? '') === 'remover_material') {
            $db->prepare("DELETE FROM os_materiais_solicitados WHERE id = ? AND os_id = ?")->execute([$matId, $os_id]);
            setSuccess('Solicitação removida.');
        } else {
            $db->prepare("UPDATE os_materiais_solicitados SET atendido = 1 - atendido WHERE id = ? AND os_id = ?")->execute([$matId, $os_id]);
            setSuccess('Solicitação atualizada.');
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// POST: editar descrição/quantidade do item (vendedor ajusta medidas
// solicitadas pelo cliente ou pelo projetista antes da produção)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'editar_item') {
    $uid = (int)$_SESSION['usuario_id'];
    $itemIdEd = (int) ($_POST['item_id'] ?? 0);
    $novaDescricao = trim($_POST['nova_descricao'] ?? '');
    $novaQtd = (float) str_replace(',', '.', (string) ($_POST['nova_quantidade'] ?? 0));
    $statusAtual = (string) ($os['status'] ?? '');

    if (!in_array($statusAtual, ['pendente', 'em_projeto', 'proposta', 'em_revisao'], true)) {
        setError('Itens só podem ser alterados antes da produção. Em produção, use o retorno de etapa.');
    } elseif ($itemIdEd <= 0 || $novaDescricao === '' || $novaQtd <= 0) {
        setError('Informe a descrição e a quantidade do item.');
    } else {
        try {
            if (!empty($os['venda_id'])) {
                $stmtEd = $db->prepare("UPDATE vendas_itens SET descricao_manual = ?, quantidade = ? WHERE id = ? AND venda_id = ?");
                $stmtEd->execute([$novaDescricao, $novaQtd, $itemIdEd, (int) $os['venda_id']]);
            } else {
                $stmtEd = $db->prepare("UPDATE os_itens SET descricao_manual = ?, quantidade = ? WHERE id = ? AND os_id = ?");
                $stmtEd->execute([$novaDescricao, $novaQtd, $itemIdEd, $os_id]);
            }
            $db->prepare("INSERT INTO os_historico_status (os_id, status_anterior, status_novo, usuario_id, observacao) VALUES (?, ?, ?, ?, ?)")
               ->execute([$os_id, $statusAtual, $statusAtual, $uid, 'Item #' . $itemIdEd . ' alterado: ' . mb_substr($novaDescricao, 0, 150)]);
            setSuccess('Item atualizado! Se a alteração afeta o desenho, use "Solicitar Alteração" para devolver ao Projetista.');
        } catch (Exception $e) {
            setError('Erro ao alterar o item: ' . $e->getMessage());
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Itens
if (!empty($os['venda_id'])) {
    $si = $db->prepare("SELECT vi.id, vi.produto_id, vi.descricao_manual, vi.quantidade, p.codigo, COALESCE(NULLIF(vi.descricao_manual,''), p.nome) as descricao FROM vendas_itens vi LEFT JOIN produtos p ON vi.produto_id=p.id WHERE vi.venda_id=? ORDER BY vi.id");
} else {
    $si = $db->prepare("SELECT oi.id, oi.produto_id, oi.descricao_manual, oi.quantidade, p.codigo, COALESCE(NULLIF(oi.descricao_manual,''), p.nome) as descricao FROM os_itens oi LEFT JOIN produtos p ON oi.produto_id=p.id WHERE oi.os_id=? ORDER BY oi.id");
}
$si->execute([$os['venda_id'] ? $os['venda_id'] : $os_id]);
$itens = $si->fetchAll(PDO::FETCH_ASSOC);

// Arquivos por item
$arquivosPorItem = [];
if (!empty($itens)) {
    $stmtArqItem = $db->prepare("SELECT * FROM os_itens_arquivos WHERE os_id = ? ORDER BY id DESC");
    $stmtArqItem->execute([$os_id]);
    foreach ($stmtArqItem->fetchAll(PDO::FETCH_ASSOC) as $arqItem) {
        $arquivosPorItem[(int) $arqItem['os_item_id']][] = $arqItem;
    }
}

// Roteiro de produção atual (etapa => status) para o card do roteiro
$roteiroAtual = [];
try {
    $stmtRot = $db->prepare("SELECT etapa, status FROM os_etapas_producao WHERE os_id = ?");
    $stmtRot->execute([$os_id]);
    foreach ($stmtRot->fetchAll(PDO::FETCH_ASSOC) as $rot) {
        $roteiroAtual[$rot['etapa']] = $rot['status'];
    }
} catch (Exception $e) {}
$podeEditarRoteiroUI = hasPermission(['master', 'projetista', 'gerente'])
    && (in_array($os['status'], ['pendente', 'em_projeto', 'proposta', 'em_revisao'], true)
        || ($os['status'] === 'em_producao' && ($os['etapa_atual'] ?? '') === 'engenharia'));

// Materiais solicitados (matéria-prima/insumos) + catálogo para o datalist
$materiaisSolicitados = [];
$insumosCatalogo = [];
try {
    $stmtMat = $db->prepare("SELECT m.*, COALESCE(u.nome,'-') as solicitante FROM os_materiais_solicitados m LEFT JOIN usuarios u ON u.id = m.usuario_id WHERE m.os_id = ? ORDER BY m.atendido, m.id");
    $stmtMat->execute([$os_id]);
    $materiaisSolicitados = $stmtMat->fetchAll(PDO::FETCH_ASSOC);
    $insumosCatalogo = $db->query("SELECT codigo, nome, unidade FROM insumos ORDER BY nome LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
$podeSolicitarMaterial = hasPermission(['master', 'projetista', 'gerente']);

// Itens já despachados por envio parcial (desmembramento)
$despachosPorItem = [];
try {
    $origemItens = !empty($os['venda_id']) ? 'vendas_itens' : 'os_itens';
    $stmtDesp = $db->prepare("SELECT d.item_id, d.setor, f.numero AS filho_numero, f.id AS filho_id
        FROM os_desmembramentos d LEFT JOIN ordens_servico f ON f.id = d.os_filho_id
        WHERE d.os_pai_id = ? AND d.origem = ?");
    $stmtDesp->execute([$os_id, $origemItens]);
    foreach ($stmtDesp->fetchAll(PDO::FETCH_ASSOC) as $desp) {
        $despachosPorItem[(int) $desp['item_id']] = $desp;
    }
} catch (Exception $e) { /* tabela ainda não existe = nenhum despacho */ }

require_once __DIR__ . '/../../includes/workflow.php';
$cicloOP = getCicloVidaOP($db, $os);
sincronizarStatusOP($db, $os); // mantém ordens_producao.status atualizado
$estagiosOP = getEstagiosCicloOP();
$ordemAtual = (int) ($cicloOP['ordem'] ?? 0);
include '../../includes/header_vendedor.php';
?>
<div class="vend-layout">
    <?php $GLOBALS['modulo_tipo'] = 'projetista'; include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-page-head">
            <div><h1 class="vend-page-title"><?= htmlspecialchars($os['numero']) ?> <?= renderBolinhasOS(getBolinhasOS($db, $os), 14) ?></h1></div>
            <a href="imprimir_op.php?os_id=<?= $os_id ?>" target="_blank" class="vbtn-sm" title="Imprimir O.S."><i class="fas fa-print"></i> Imprimir</a>
        </div>

        <!-- Ciclo de vida da Ordem de Produção -->
        <div class="vend-card" style="margin-bottom:16px">
            <div style="padding:16px 20px">
                <?php if ($cicloOP['estagio'] === 'cancelada'): ?>
                    <div style="display:flex;align-items:center;gap:10px;color:#dc2626;font-weight:700">
                        <i class="fas fa-ban" style="font-size:18px"></i> Ordem de Produção Cancelada
                    </div>
                <?php else: ?>
                    <div style="display:flex;align-items:center;gap:0;flex-wrap:wrap">
                        <?php $i = 0; $nEst = count($estagiosOP); foreach ($estagiosOP as $chave => $est): $i++;
                            $ordemEst = $i;
                            $ativo = $ordemAtual >= $ordemEst;
                            $atual = ($cicloOP['estagio'] === $chave);
                        ?>
                            <div style="display:flex;align-items:center;gap:8px">
                                <div title="<?= $est['label'] ?>" style="width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;<?= $ativo ? 'background:' . $est['cor'] . ';color:#fff' : 'background:#e9ecef;color:#98a2b3' ?>;<?= $atual ? 'box-shadow:0 0 0 4px ' . $est['cor'] . '33' : '' ?>">
                                    <i class="fas <?= $est['icon'] ?>" style="font-size:14px"></i>
                                </div>
                                <span style="font-size:13px;font-weight:<?= $atual ? '700' : '500' ?>;color:<?= $ativo ? '#1a1a1a' : '#98a2b3' ?>"><?= $est['label'] ?></span>
                            </div>
                            <?php if ($ordemEst < $nEst): ?>
                                <div style="flex:1;min-width:24px;height:3px;margin:0 8px;border-radius:2px;background:<?= $ordemAtual > $ordemEst ? $est['cor'] : '#e9ecef' ?>"></div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($cicloOP['estagio'] === 'em_producao' && ($cicloOP['total'] ?? 0) > 0): ?>
                        <div style="margin-top:12px;display:flex;align-items:center;gap:10px">
                            <div style="flex:1;height:8px;background:#e9ecef;border-radius:4px;overflow:hidden">
                                <div style="height:100%;width:<?= (int) $cicloOP['progresso'] ?>%;background:#D85A30;border-radius:4px"></div>
                            </div>
                            <span style="font-size:12px;color:#666;white-space:nowrap"><?= (int) $cicloOP['concluidas'] ?>/<?= (int) $cicloOP['total'] ?> etapas • <?= (int) $cicloOP['progresso'] ?>%</span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="vend-card">
            <div class="vend-card-head"><span class="vend-card-title">Detalhes da Ordem</span></div>
            <div class="vend-metrics">
                <div class="vend-metric"><div class="vend-metric-label">Cliente</div><div class="vend-metric-val"><?= htmlspecialchars($os['razao_social']) ?></div></div>
                <div class="vend-metric"><div class="vend-metric-label">Status</div><div class="vend-metric-val"><span class="vbadge <?= $os['status']==='em_producao'?'vbadge-prod':($os['status']==='proposta'?'vbadge-warn':'vbadge-info') ?>"><?= getStatusOSNome($os['status']) ?></span></div></div>
                <div class="vend-metric"><div class="vend-metric-label">Venda</div><div class="vend-metric-val"><?= $os['venda_numero'] ? htmlspecialchars($os['venda_numero']) : 'Independente' ?></div></div>
                <div class="vend-metric"><div class="vend-metric-label">Vendedor</div><div class="vend-metric-val"><?= htmlspecialchars($os['vendedor_nome']) ?></div></div>
            </div>
        </div>

        <div class="vend-card" style="margin-top:20px;">
            <div class="vend-card-head">
                <span class="vend-card-title">Ações de Produção</span>
                <?php if ($op_atual): ?>
                    <span class="vbadge vbadge-info"><?= htmlspecialchars($op_atual['numero']) ?> · <?= htmlspecialchars(ucfirst($op_atual['status'] ?? 'pendente')) ?></span>
                <?php endif; ?>
            </div>
            <div class="vend-card-body" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <form method="POST" style="display:inline">
                    <input type="hidden" name="acao" value="gerar_op_lote">
                    <button type="submit" class="vbtn-sm btn-primary"><i class="fas fa-layer-group"></i> Gerar OP em lote</button>
                </form>
                <?php if ($op_atual): ?>
                    <a href="imprimir_op.php?os_id=<?= $os_id ?>" target="_blank" class="vbtn-sm"><i class="fas fa-print"></i> Abrir OP</a>
                <?php endif; ?>
                <span class="vend-page-sub">Use os botões por item para anexar PDF/DXF e gerar a OP individual quando necessário.</span>
            </div>
        </div>

        <?php if (in_array($os['status'], ['pendente', 'em_projeto', 'em_revisao'], true)): ?>
        <div class="vend-card">
            <div class="vend-card-head"><span class="vend-card-title"><i class="fas fa-drafting-compass"></i> Desenho Proposta <?= $os['status'] === 'em_revisao' ? '(REFAZER — alteração solicitada)' : '' ?></span></div>
            <?php if ($os['status'] === 'em_revisao'): ?>
                <div class="vend-alert" style="background:#FEF0EA;border-left:4px solid #D85A30;margin:0 0 10px"><i class="fas fa-exclamation-circle" style="color:#D85A30"></i> O vendedor/cliente solicitou alteração — confira o histórico e os itens atualizados abaixo, refaça o desenho proposta e envie de novo.</div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="acao" value="salvar_proposta">
                <div class="form-group"><label>Alterações do Projeto</label><textarea name="alteracoes" class="form-control" rows="3" placeholder="Descrição das alterações..."></textarea></div>
                <div class="form-group"><label>Arquivo PDF da Proposta</label><input type="file" name="proposta_pdf" accept="application/pdf" class="form-control" required></div>
                <button type="submit" class="vbtn-sm btn-success"><i class="fas fa-paper-plane"></i> Enviar Proposta ao Vendedor</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($os['status'] === 'proposta'): ?>
        <div class="vend-card">
            <div class="vend-card-head"><span class="vend-card-title">Proposta em Análise</span></div>
            <?php if (!empty($arquivos)): foreach ($arquivos as $arq): ?>
                <a href="<?= SITE_URL ?>/assets/uploads/propostas/<?= $arq['nome_arquivo'] ?>" class="vbadge vbadge-info" target="_blank"><i class="fas fa-file-pdf"></i> <?= htmlspecialchars($arq['nome_original']) ?></a>
            <?php endforeach; endif; ?>
            <form method="POST" style="margin-top:12px" onsubmit="return confirm('Cliente aprovou a proposta? A O.S. vai para o Projetista fazer o desenho de produção (DXF/PDF/3D).')">
                <input type="hidden" name="acao" value="aprovar_proposta">
                <button type="submit" class="vbtn-sm btn-success"><i class="fas fa-check"></i> Cliente Aprovou</button>
                <button type="button" class="vbtn-sm btn-warning" onclick="document.getElementById('motivo-devolucao').style.display='block'"><i class="fas fa-undo"></i> Solicitar Alteração</button>
            </form>
            <div id="motivo-devolucao" style="display:none;margin-top:12px">
                <form method="POST">
                    <input type="hidden" name="acao" value="devolver_proposta">
                    <div style="font-size:12px;color:#666;margin-bottom:6px"><i class="fas fa-info-circle"></i> Se a medida mudou, primeiro edite a descrição do item (lápis na tabela de itens) e depois confirme aqui — a O.S. volta ao Projetista para refazer o desenho.</div>
                    <textarea name="motivo" class="form-control" rows="2" placeholder="O que precisa mudar? (ex.: cliente pediu bancada com 1,80m em vez de 2,00m)" required></textarea>
                    <button type="submit" class="vbtn-sm btn-danger" style="margin-top:8px">Confirmar — devolver ao Projetista</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if (hasPermission(['master', 'projetista', 'gerente', 'producao'])): ?>
        <div class="vend-card" style="margin-bottom:16px">
            <div class="vend-card-head"><span class="vend-card-title"><i class="fas fa-route"></i> Roteiro de Produção — por quais setores o projeto passa</span></div>
            <form method="POST" style="padding:12px 16px">
                <input type="hidden" name="acao" value="salvar_roteiro">
                <div style="display:flex;flex-wrap:wrap;gap:10px">
                    <?php require_once __DIR__ . '/../../includes/workflow.php';
                    foreach (getEtapasBancada() as $etapaRot):
                        $statusRot = $roteiroAtual[$etapaRot] ?? null;
                        $marcada = $statusRot !== null;
                        $travada = $marcada && $statusRot !== 'pendente'; // já iniciada/concluída
                        $obrigatoria = ($etapaRot === 'engenharia');
                    ?>
                        <label style="display:flex;align-items:center;gap:6px;background:<?= $travada ? '#e7f6ec' : ($marcada ? '#FEF0EA' : '#f8f9fa') ?>;border:1px solid #e9ecef;border-radius:6px;padding:6px 10px;cursor:<?= ($travada || $obrigatoria || !$podeEditarRoteiroUI) ? 'default' : 'pointer' ?>;font-weight:normal;margin:0">
                            <input type="checkbox" name="roteiro_etapas[]" value="<?= $etapaRot ?>" <?= $marcada || $obrigatoria ? 'checked' : '' ?> <?= ($travada || $obrigatoria || !$podeEditarRoteiroUI) ? 'disabled' : '' ?>>
                            <?php if ($travada && $marcada): // disabled não envia — repõe via hidden ?>
                                <input type="hidden" name="roteiro_etapas[]" value="<?= $etapaRot ?>">
                            <?php endif; ?>
                            <?= getEtapaLabel($etapaRot) ?>
                            <?php if ($travada): ?><i class="fas fa-lock" style="font-size:10px;color:#16a34a" title="Etapa já iniciada/concluída — não pode ser removida"></i><?php endif; ?>
                            <?php if ($obrigatoria): ?><span style="font-size:10px;color:#666">(sempre)</span><?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php if ($podeEditarRoteiroUI): ?>
                    <div style="margin-top:10px;display:flex;align-items:center;gap:10px">
                        <button type="submit" class="vbtn-sm btn-success"><i class="fas fa-save"></i> Salvar Roteiro</button>
                        <span style="font-size:12px;color:#666">Marque só os setores que este projeto precisa (ex.: sem mobiliário se não tem móvel).</span>
                    </div>
                <?php else: ?>
                    <div style="margin-top:10px;font-size:12px;color:#666"><i class="fas fa-lock"></i> Roteiro editável pelo Projetista/gestão antes da produção ou na etapa do Projetista.</div>
                <?php endif; ?>
            </form>
        </div>
        <?php endif; ?>

        <?php if (hasPermission(['master', 'projetista', 'gerente', 'producao']) || !empty($materiaisSolicitados)): ?>
        <div class="vend-card" style="margin-bottom:16px">
            <div class="vend-card-head"><span class="vend-card-title"><i class="fas fa-boxes"></i> Matéria-Prima / Insumos Solicitados</span></div>
            <div style="padding:12px 16px">
                <?php if (empty($materiaisSolicitados)): ?>
                    <div style="font-size:13px;color:#666;margin-bottom:10px">Nenhum material solicitado ainda. Ex.: rodízios, cubas, chapas no tamanho de melhor aproveitamento.</div>
                <?php else: ?>
                    <div class="vend-table-wrap" style="margin-bottom:12px">
                        <table class="vend-table">
                            <thead><tr><th>Material</th><th>Qtd</th><th>Un</th><th>Observação / Medida</th><th>Solicitante</th><th>Status</th><?php if ($podeSolicitarMaterial): ?><th></th><?php endif; ?></tr></thead>
                            <tbody>
                                <?php foreach ($materiaisSolicitados as $mat): ?>
                                <tr style="<?= $mat['atendido'] ? 'opacity:.6' : '' ?>">
                                    <td><?= htmlspecialchars($mat['descricao']) ?></td>
                                    <td><?= rtrim(rtrim(number_format((float) $mat['quantidade'], 2, ',', '.'), '0'), ',') ?></td>
                                    <td><?= htmlspecialchars($mat['unidade']) ?></td>
                                    <td><?= htmlspecialchars($mat['observacao'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($mat['solicitante']) ?></td>
                                    <td><?= $mat['atendido'] ? '<span class="vbadge vbadge-ok"><i class="fas fa-check"></i> Atendido</span>' : '<span class="vbadge vbadge-warn">Pendente</span>' ?></td>
                                    <?php if ($podeSolicitarMaterial): ?>
                                    <td style="white-space:nowrap">
                                        <form method="POST" style="display:inline"><input type="hidden" name="acao" value="atender_material"><input type="hidden" name="material_id" value="<?= (int) $mat['id'] ?>"><button type="submit" class="vbtn-sm" title="<?= $mat['atendido'] ? 'Voltar para pendente' : 'Marcar como atendido' ?>"><i class="fas fa-<?= $mat['atendido'] ? 'undo' : 'check' ?>"></i></button></form>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Remover esta solicitação de material?')"><input type="hidden" name="acao" value="remover_material"><input type="hidden" name="material_id" value="<?= (int) $mat['id'] ?>"><button type="submit" class="vbtn-sm btn-danger" title="Remover"><i class="fas fa-trash"></i></button></form>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                <?php if ($podeSolicitarMaterial): ?>
                <form method="POST" style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end">
                    <input type="hidden" name="acao" value="solicitar_material">
                    <div style="flex:2;min-width:240px">
                        <label style="font-size:12px">Material (busque no catálogo ou descreva) *</label>
                        <input type="text" name="mat_descricao" class="form-control" list="insumosCatalogo" required placeholder="Ex.: 1050203 — Chapa inox 304 1,2mm | ou: Rodízio 4'' com trava">
                        <datalist id="insumosCatalogo">
                            <?php foreach ($insumosCatalogo as $ins): ?>
                                <option value="<?= htmlspecialchars($ins['codigo'] . ' — ' . $ins['nome']) ?>"><?= htmlspecialchars($ins['unidade']) ?></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div style="width:90px"><label style="font-size:12px">Qtd *</label><input type="text" name="mat_quantidade" class="form-control" value="1" required></div>
                    <div style="width:80px"><label style="font-size:12px">Un</label><input type="text" name="mat_unidade" class="form-control" value="un"></div>
                    <div style="flex:2;min-width:200px"><label style="font-size:12px">Observação / Medida (melhor aproveitamento)</label><input type="text" name="mat_observacao" class="form-control" placeholder="Ex.: cortar em 2000x1250 para melhor aproveitamento"></div>
                    <button type="submit" class="vbtn-sm btn-success"><i class="fas fa-plus"></i> Solicitar</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="vend-card">
            <div class="vend-card-head"><span class="vend-card-title">Itens da O.S.</span></div>
            <div class="vend-table-wrap">
                <table class="vend-table">
                    <thead><tr><th>#</th><th>Código</th><th>Descrição</th><th>Qtd</th><th>Setor</th><th>Ações</th></tr></thead>
                    <tbody>
                        <?php if (empty($itens)): ?>
                            <tr><td colspan="6" class="vend-empty">Nenhum item encontrado.</td></tr>
                        <?php else: foreach ($itens as $item): ?>
                            <tr>
                                <td><?= $item['id'] ?></td>
                                <td><?= htmlspecialchars($item['codigo'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($item['descricao']) ?></td>
                                <td><?= $item['quantidade'] ?></td>
<td>
                                    <?php $podeEnviarItem = in_array($os['status'], ['pendente', 'em_projeto', 'proposta'], true)
                                        || ($os['status'] === 'em_producao' && ($os['etapa_atual'] ?? '') === 'engenharia'); ?>
                                    <?php if (!empty($despachosPorItem[(int) $item['id']])): $desp = $despachosPorItem[(int) $item['id']]; ?>
                                        <span class="vbadge vbadge-ok" title="Item já enviado por desmembramento">
                                            <i class="fas fa-share"></i> <?= ucfirst($desp['setor']) ?>
                                            <?php if (!empty($desp['filho_numero'])): ?>
                                                — <a href="os_detalhes.php?os_id=<?= (int) $desp['filho_id'] ?>" style="color:inherit;text-decoration:underline"><?= htmlspecialchars($desp['filho_numero']) ?></a>
                                            <?php endif; ?>
                                        </span>
                                    <?php elseif ($podeEnviarItem): ?>
                                        <select onchange="enviarItemSetor(<?= $item['id'] ?>, this.value)">
                                            <option value="">-- Setor --</option>
                                            <option value="corte">Corte</option>
                                            <option value="dobra">Dobra</option>
                                            <option value="solda">Solda</option>
                                            <option value="refrigeracao">Refrigeração</option>
                                            <option value="acabamento">Acabamento</option>
                                            <option value="montagem">Montagem</option>
                                        </select>
                                    <?php else: ?>
                                        <span class="vbadge vbadge-info"><?= getEtapaLabel($os['etapa_atual'] ?? '') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display:flex;gap:4px;flex-wrap:wrap;align-items:center;">
                                        <?php if ($os['status'] === 'pendente' || $os['status'] === 'em_projeto' || $os['status'] === 'proposta' || $os['status'] === 'em_producao'): ?>
                                            <a href="imprimir_op.php?os_id=<?= $os_id ?>&item_id=<?= $item['id'] ?>" target="_blank" class="vbtn-sm" title="Abrir OP do item"><i class="fas fa-print"></i></a>
                                            <form method="POST" style="display:inline">
                                                <input type="hidden" name="acao" value="gerar_op_item">
                                                <input type="hidden" name="os_item_id" value="<?= $item['id'] ?>">
                                                <button type="submit" class="vbtn-sm btn-primary" title="Gerar OP do item"><i class="fas fa-cubes"></i></button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if (in_array($os['status'], ['pendente', 'em_projeto', 'proposta', 'em_revisao'], true)): ?>
                                            <button type="button" class="vbtn-sm" title="Editar descrição/medidas do item (alteração do cliente ou do projetista)" onclick='abrirModalEditarItem(<?= (int) $item['id'] ?>, <?= json_encode((string) $item['descricao'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>, "<?= htmlspecialchars((string) $item['quantidade']) ?>")'><i class="fas fa-pencil-alt"></i></button>
                                        <?php endif; ?>
                                        <?php if (in_array($os['status'], ['pendente', 'em_projeto', 'proposta', 'em_revisao', 'em_producao'], true)): ?>
                                            <form method="POST" enctype="multipart/form-data" style="display:inline" id="form-pdf-<?= $item['id'] ?>">
                                                <input type="hidden" name="acao" value="anexar_arquivo_item">
                                                <input type="hidden" name="os_id" value="<?= $os_id ?>">
                                                <input type="hidden" name="os_item_id" value="<?= $item['id'] ?>">
                                                <input type="file" name="arquivo[]" accept=".pdf" multiple onchange="document.getElementById('form-pdf-<?= $item['id'] ?>').submit()" style="display:none" id="upload-pdf-<?= $item['id'] ?>">
                                                <label for="upload-pdf-<?= $item['id'] ?>" class="vbtn-sm" title="Anexar PDFs (pode selecionar vários)"><i class="fas fa-file-pdf"></i></label>
                                            </form>
                                            <form method="POST" enctype="multipart/form-data" style="display:inline" id="form-dxf-<?= $item['id'] ?>">
                                                <input type="hidden" name="acao" value="anexar_arquivo_item">
                                                <input type="hidden" name="os_id" value="<?= $os_id ?>">
                                                <input type="hidden" name="os_item_id" value="<?= $item['id'] ?>">
                                                <input type="file" name="arquivo[]" accept=".dxf,.dwg" multiple onchange="document.getElementById('form-dxf-<?= $item['id'] ?>').submit()" style="display:none" id="upload-dxf-<?= $item['id'] ?>">
                                                <label for="upload-dxf-<?= $item['id'] ?>" class="vbtn-sm" title="Anexar DXFs de corte (pode selecionar vários)"><i class="fas fa-drafting-compass"></i></label>
                                            </form>
                                            <form method="POST" enctype="multipart/form-data" style="display:inline" id="form-3d-<?= $item['id'] ?>">
                                                <input type="hidden" name="acao" value="anexar_arquivo_item">
                                                <input type="hidden" name="os_id" value="<?= $os_id ?>">
                                                <input type="hidden" name="os_item_id" value="<?= $item['id'] ?>">
                                                <input type="file" name="arquivo[]" accept=".step,.stp,.obj,.stl,.iges,.igs,.3mf,.glb,.gltf,.ply,.fbx,.3ds,.brep,.sldprt,.sldasm" multiple onchange="document.getElementById('form-3d-<?= $item['id'] ?>').submit()" style="display:none" id="upload-3d-<?= $item['id'] ?>">
                                                <label for="upload-3d-<?= $item['id'] ?>" class="vbtn-sm" title="Anexar modelos 3D (STEP, OBJ, STL…)"><i class="fas fa-cube"></i></label>
                                            </form>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($arquivosPorItem[$item['id']])): ?>
                                        <div style="margin-top:6px;display:flex;gap:4px;flex-wrap:wrap;">
                                            <?php foreach ($arquivosPorItem[$item['id']] as $arqItem):
                                                if (isArquivo3DVisualizavel($arqItem['nome_arquivo'])): ?>
                                                <a href="visualizar_3d.php?arquivo=<?= urlencode($arqItem['nome_arquivo']) ?>&nome=<?= urlencode($arqItem['nome_original'] ?? $arqItem['nome_arquivo']) ?>" target="_blank" class="vbadge vbadge-ok" title="Visualizar em 3D: <?= htmlspecialchars($arqItem['nome_original'] ?? '') ?>"><i class="fas fa-cube"></i> 3D</a>
                                            <?php else: ?>
                                                <a href="<?= SITE_URL ?>/assets/uploads/projetos/<?= htmlspecialchars($arqItem['nome_arquivo']) ?>" target="_blank" class="vbadge vbadge-info" title="<?= htmlspecialchars($arqItem['nome_original'] ?? '') ?>"><?= htmlspecialchars($arqItem['tipo']) ?></a>
                                            <?php endif; endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Arquivos Anexados -->
        <div class="vend-card" style="margin-top:20px;">
            <div class="vend-card-head"><span class="vend-card-title">Arquivos Anexados</span></div>
            <div class="vend-card-body">
                <?php
                $arquivosOS = $db->prepare("SELECT * FROM os_arquivos WHERE os_id = ? AND tipo IN ('projeto', 'projeto_pdf', 'projeto_dxf', 'projeto_foto', 'projeto_3d') ORDER BY id DESC");
                $arquivosOS->execute([$os_id]);
                $arqsOS = $arquivosOS->fetchAll();
                if (!empty($arqsOS)):
                    foreach ($arqsOS as $arq):
                        $url = SITE_URL . '/assets/uploads/projetos/' . htmlspecialchars($arq['nome_arquivo']);
                        if (isArquivo3DVisualizavel($arq['nome_arquivo'])):
                ?>
                    <a href="visualizar_3d.php?arquivo=<?= urlencode($arq['nome_arquivo']) ?>&nome=<?= urlencode($arq['nome_original']) ?>" class="vbadge vbadge-ok" target="_blank" style="margin:2px;">
                        <i class="fas fa-cube"></i> <?= htmlspecialchars($arq['nome_original']) ?>
                    </a>
                <?php else: ?>
                    <a href="<?= $url ?>" class="vbadge vbadge-info" target="_blank" style="margin:2px;">
                        <i class="fas <?= $arq['tipo'] === 'projeto_dxf' ? 'fa-drafting-compass' : 'fa-file-pdf' ?>"></i> <?= htmlspecialchars($arq['nome_original']) ?>
                    </a>
                <?php endif; endforeach; else: ?>
                    <p class="text-muted">Nenhum arquivo anexado.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Item (alteração de medidas/descrição pelo vendedor) -->
<div id="modalEditarItem" class="modal"><div class="modal-content" style="max-width:520px"><div class="modal-header"><h3><i class="fas fa-pencil-alt"></i> Alterar Item</h3><button class="close" onclick="document.getElementById('modalEditarItem').style.display='none'">&times;</button></div><form method="POST"><div class="modal-body"><input type="hidden" name="acao" value="editar_item"><input type="hidden" name="item_id" id="edit_item_id"><div class="form-group"><label><strong>Descrição / Medidas *</strong></label><textarea name="nova_descricao" id="edit_item_desc" class="form-control" rows="4" required placeholder="Descrição completa com as medidas atualizadas..."></textarea></div><div class="form-group"><label><strong>Quantidade *</strong></label><input type="text" name="nova_quantidade" id="edit_item_qtd" class="form-control" required style="max-width:120px"></div><div style="font-size:12px;color:#666"><i class="fas fa-info-circle"></i> Depois de salvar, se a alteração afetar o desenho, use <strong>Solicitar Alteração</strong> para a O.S. voltar ao Projetista.</div></div><div class="modal-footer"><button type="button" class="vbtn-sm" onclick="document.getElementById('modalEditarItem').style.display='none'">Cancelar</button><button type="submit" class="vbtn-sm btn-success"><i class="fas fa-save"></i> Salvar Alteração</button></div></form></div></div>

<script>
function abrirModalEditarItem(itemId, descricao, qtd) {
    document.getElementById('edit_item_id').value = itemId;
    document.getElementById('edit_item_desc').value = descricao || '';
    document.getElementById('edit_item_qtd').value = qtd || '1';
    document.getElementById('modalEditarItem').style.display = 'block';
}

function enviarItemSetor(itemId, setor) {
    if (!setor) return;
    if (confirm('Enviar este item para ' + setor + '? Os demais itens continuam na O.S.')) {
        fetch('desmembrar_item.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'item_id=' + itemId + '&setor=' + setor + '&os_id=<?= (int) $os_id ?>'
        }).then(r => r.json()).then(d => {
            if (d.success) { if (d.message) alert(d.message); location.reload(); }
            else alert(d.error || 'Erro ao enviar o item.');
        }).catch(() => alert('Erro de comunicação ao enviar o item.'));
    } else {
        event.target.value = '';
    }
}
</script>

<?php include '../../includes/footer_vendedor.php'; ?>