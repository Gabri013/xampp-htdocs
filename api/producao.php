<?php
require_once '../config/config.php';
require_once '../includes/expediente.php';
require_once '../includes/workflow.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$acao = $_POST['acao'] ?? '';
$os_id = $_POST['os_id'] ?? null;
$etapa = $_POST['etapa'] ?? null;

if (!$os_id || !$acao) {
    http_response_code(400);
    echo json_encode(['error' => 'Parâmetros inválidos']);
    exit;
}

try {
    $db = getDB();
    ensureExpedienteSchema($db);

    $dbOS = $db->prepare("SELECT status, etapa_atual FROM ordens_servico WHERE id = ?");
    $dbOS->execute([$os_id]);
    $os = $dbOS->fetch();

    if (!$os) {
        echo json_encode(['error' => 'Ordem de Serviço não encontrada']);
        exit;
    }

    $permissaoEtapa = validateUserCanOperateEtapa($os['etapa_atual'] ?? '', $_SESSION['usuario_tipo'] ?? '');
    if (!$permissaoEtapa['valid']) {
        echo json_encode(['error' => $permissaoEtapa['message']]);
        exit;
    }

    switch ($acao) {
        case 'iniciar_etapa':
            $statusExpediente = getStatusExpedienteHoje($db, (int) $_SESSION['usuario_id']);
            if (($statusExpediente['status'] ?? 'nao_iniciado') !== 'em_trabalho') {
                echo json_encode(['error' => 'Inicie seu expediente antes de começar uma O.S.']);
                exit;
            }

            if ($os['etapa_atual'] !== $etapa) {
                echo json_encode(['error' => 'Esta etapa não é a etapa atual da O.S. Etapa esperada: ' . $os['etapa_atual'] . ', recebida: ' . $etapa]);
                exit;
            }

            $db->beginTransaction();

            try {
                $stmt = $db->prepare("SELECT id, status FROM os_etapas_producao WHERE os_id = ? AND etapa = ?");
                $stmt->execute([$os_id, $etapa]);
                $etapa_existente = $stmt->fetch();

                if ($etapa_existente && $etapa_existente['status'] === 'em_andamento') {
                    if ($db->inTransaction()) {
                        $db->commit();
                    }
                    echo json_encode(['success' => true, 'message' => 'Etapa já estava em andamento']);
                } else if ($etapa_existente) {
                    $stmt = $db->prepare("UPDATE os_etapas_producao SET status='em_andamento', data_inicio=NOW(), usuario_id=? WHERE os_id = ? AND etapa = ?");
                    $stmt->execute([$_SESSION['usuario_id'], $os_id, $etapa]);
                    if ($db->inTransaction()) {
                        $db->commit();
                    }
                    echo json_encode(['success' => true, 'message' => 'Etapa iniciada']);
                } else {
                    $stmt = $db->prepare("INSERT INTO os_etapas_producao (os_id, etapa, status, data_inicio, usuario_id) VALUES (?, ?, 'em_andamento', NOW(), ?)");
                    $stmt->execute([$os_id, $etapa, $_SESSION['usuario_id']]);
                    if ($db->inTransaction()) {
                        $db->commit();
                    }
                    echo json_encode(['success' => true, 'message' => 'Etapa iniciada']);
                }
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }
            break;

        case 'finalizar_etapa':
            $etapa_destino = sanitize($_POST['etapa_destino'] ?? '');

            if ($os['etapa_atual'] !== $etapa) {
                echo json_encode(['error' => 'Esta etapa não é a etapa atual da O.S']);
                exit;
            }

            $validation = validateEtapaTransition($etapa, $etapa_destino ?: '', $_SESSION['usuario_tipo'] ?? '');
            if (!$validation['valid']) {
                echo json_encode(['error' => $validation['message']]);
                exit;
            }

            $firstStepValidation = validateFirstProductionStepStarted($db, $os_id, $etapa);
            if (!$firstStepValidation['valid']) {
                echo json_encode(['error' => $firstStepValidation['message']]);
                exit;
            }

            $trackingValidation = validateStepTrackingAndAttachment($db, $os_id, $etapa, 'finalizar');
            if (!$trackingValidation['valid']) {
                echo json_encode(['error' => $trackingValidation['message']]);
                exit;
            }

            $db->beginTransaction();

            try {
                $stmt = $db->prepare("SELECT data_inicio, status, usuario_id FROM os_etapas_producao WHERE os_id = ? AND etapa = ?");
                $stmt->execute([$os_id, $etapa]);
                $etapa_data = $stmt->fetch();

                if (!$etapa_data) {
                    $stmt = $db->prepare("INSERT INTO os_etapas_producao (os_id, etapa, status, data_inicio, usuario_id) VALUES (?, ?, 'em_andamento', NOW(), ?)");
                    $stmt->execute([$os_id, $etapa, $_SESSION['usuario_id']]);

                    $stmt = $db->prepare("SELECT data_inicio, usuario_id FROM os_etapas_producao WHERE os_id = ? AND etapa = ?");
                    $stmt->execute([$os_id, $etapa]);
                    $etapa_data = $stmt->fetch();
                }

                if (!$etapa_data || !$etapa_data['data_inicio']) {
                    throw new Exception('Etapa não foi iniciada corretamente e não foi possível criar um registro');
                }

                $data_inicio = new DateTime($etapa_data['data_inicio']);
                $data_fim = new DateTime();
                $usuarioTempoId = (int) ($etapa_data['usuario_id'] ?? $_SESSION['usuario_id']);
                $segundos = calcularSegundosTrabalhadosNoPeriodo(
                    $db,
                    $usuarioTempoId > 0 ? $usuarioTempoId : (int) $_SESSION['usuario_id'],
                    $data_inicio->format('Y-m-d H:i:s'),
                    $data_fim->format('Y-m-d H:i:s')
                );

                $stmt = $db->prepare("UPDATE os_etapas_producao SET status='concluida', data_fim=NOW(), tempo_total_segundos=? WHERE os_id = ? AND etapa = ?");
                $stmt->execute([$segundos, $os_id, $etapa]);

                $destinos_validos = getValidOSEtapas();
                if ($etapa_destino === '') {
                    $fluxo = getEtapaFluxo();
                    $pos = array_search($etapa, $fluxo, true);
                    if ($pos === false || !isset($fluxo[$pos + 1])) {
                        throw new Exception('Etapa de destino não informada');
                    }
                    $etapa_destino = $fluxo[$pos + 1];
                }

                if (!in_array($etapa_destino, $destinos_validos, true)) {
                    throw new Exception('Etapa de destino inválida');
                }

                if ($etapa_destino === $etapa) {
                    throw new Exception('A etapa de destino deve ser diferente da etapa atual');
                }

                $status_os = ($etapa_destino === 'concluida') ? 'concluida' : 'em_producao';
                $stmt = $db->prepare("UPDATE ordens_servico SET etapa_atual = ?, status = ? WHERE id = ?");
                $stmt->execute([$etapa_destino, $status_os, $os_id]);

                if ($etapa_destino === 'concluida') {
                    $stmt = $db->prepare("UPDATE vendas v INNER JOIN ordens_servico os ON v.id = os.venda_id SET v.status='concluida' WHERE os.id=?");
                    $stmt->execute([$os_id]);
                }

                if ($db->inTransaction()) {
                    $db->commit();
                }
                echo json_encode(['success' => true, 'proxima_etapa' => $etapa_destino]);
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }
            break;

        case 'retornar_etapa':
            $justificativa = sanitize($_POST['justificativa'] ?? '');
            $etapa_atual = $_POST['etapa_atual'] ?? null;
            $etapa_destino_bruta = mb_strtolower(trim((string) ($_POST['etapa_destino'] ?? '')));
            $etapa_destino = $etapa_destino_bruta !== '' ? $etapa_destino_bruta : null;
            $retornar_para_projetista = $etapa_destino_bruta === 'projetista';

            if (empty($justificativa)) {
                echo json_encode(['error' => 'Justificativa é obrigatória']);
                exit;
            }

            $db->beginTransaction();

            try {
                $fluxo = getEtapaFluxo();
                $pos = array_search($etapa_atual, $fluxo, true);

                if ($pos === false || $pos === 0) {
                    echo json_encode(['error' => 'Não é possível retornar desta etapa']);
                    exit;
                }

                if ($retornar_para_projetista) {
                    $etapa_anterior = 'autorizacao';
                } else {
                    if ($etapa_destino === null || !in_array($etapa_destino, $fluxo, true) || $etapa_destino === 'concluida') {
                        echo json_encode(['error' => 'Etapa de retorno inválida']);
                        exit;
                    }

                    $pos_destino = array_search($etapa_destino, $fluxo, true);
                    if ($pos_destino === false || $pos_destino >= $pos) {
                        echo json_encode(['error' => 'Selecione uma etapa anterior válida para retorno']);
                        exit;
                    }

                    $etapa_anterior = $etapa_destino;
                }

                $stmt = $db->prepare("INSERT INTO logs_retorno_etapa (os_id, etapa_anterior, etapa_retornada, justificativa, usuario_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$os_id, $etapa_anterior, $etapa_atual, $justificativa, $_SESSION['usuario_id']]);

                $novo_status_os = ($etapa_anterior === 'autorizacao') ? 'em_revisao' : 'em_producao';
                $stmt = $db->prepare("UPDATE ordens_servico SET etapa_atual = ?, status = ? WHERE id = ?");
                $stmt->execute([$etapa_anterior, $novo_status_os, $os_id]);

                $destinoTexto = $etapa_anterior === 'autorizacao' ? 'Projetista' : ucfirst($etapa_anterior);
                $obs_retorno = "RECALL: Etapa retornada de " . ucfirst($etapa_atual) . " para " . $destinoTexto . ". Motivo: " . $justificativa;
                $stmt = $db->prepare("INSERT INTO os_observacoes (os_id, tipo_setor, observacao, usuario_id) VALUES (?, 'producao', ?, ?)");
                $stmt->execute([$os_id, $obs_retorno, $_SESSION['usuario_id']]);

                $statusAnteriorHistorico = 'em_producao';
                $statusNovoHistorico = $novo_status_os;
                $obsHistorico = $etapa_anterior === 'autorizacao'
                    ? 'O.S. retornada pela produção para o projetista avaliar alterações. Motivo: ' . $justificativa
                    : 'O.S. retornada da etapa ' . ucfirst($etapa_atual) . ' para ' . ucfirst($etapa_anterior) . '. Motivo: ' . $justificativa;
                $stmt = $db->prepare("
                    INSERT INTO os_historico_status (os_id, status_anterior, status_novo, usuario_id, observacao)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$os_id, $statusAnteriorHistorico, $statusNovoHistorico, $_SESSION['usuario_id'], $obsHistorico]);

                $stmt = $db->prepare("DELETE FROM os_etapas_producao WHERE os_id = ? AND etapa = ?");
                $stmt->execute([$os_id, $etapa_atual]);

                if ($etapa_anterior !== 'autorizacao') {
                    $stmt = $db->prepare("UPDATE os_etapas_producao SET status='pendente', data_inicio=NULL, data_fim=NULL, tempo_total_segundos=0 WHERE os_id = ? AND etapa = ?");
                    $stmt->execute([$os_id, $etapa_anterior]);
                }

                if ($db->inTransaction()) {
                    $db->commit();
                }
                echo json_encode([
                    'success' => true,
                    'message' => $etapa_anterior === 'autorizacao'
                        ? 'O.S. retornada com sucesso para o projetista'
                        : 'Etapa retornada com sucesso'
                ]);
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }
            break;

        default:
            echo json_encode(['error' => 'Ação desconhecida']);
    }

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo json_encode(['error' => $e->getMessage()]);
}
