<?php
/**
 * API de Desenho Técnico e Aprovação
 *
 * POST /api/desenho.php
 * - acao=criar_desenho → cria novo desenho com upload de arquivos
 * - acao=atualizar_desenho → atualiza informações do desenho
 * - acao=submeter_aprovacao → submete desenho para aprovação
 * - acao=aprovar → aprova desenho (gerente ou produção)
 * - acao=rejeitar → rejeita desenho com motivo
 * - acao=adicionar_versao → cria nova versão do desenho
 * - acao=obter_desenho → obtém dados completos do desenho
 * - acao=listar_desenhos → lista desenhos de uma O.S.
 * - acao=obter_historico → obtém histórico de mudanças
 * - acao=deletar_arquivo → remove arquivo do desenho
 * - acao=obter_aprovaes → obtém status das aprovações
 * - acao=responder_aprovacao → gerente/produção responde à aprovação
 */

require_once '../config/config.php';
require_once '../includes/workflow.php';
require_once '../includes/engenharia.php';

header('Content-Type: application/json');
$db = getDB();
requirePermission(['master', 'gerente', 'producao', 'projetista', 'engenharia']);

ensureEngenhariaSchema($db);

$usuarioId = $_SESSION['usuario_id'] ?? 0;
$usuarioPerfil = $_SESSION['perfil'] ?? 'usuario';
$usuarioNome = $_SESSION['usuario_nome'] ?? 'Usuário';
$enderecoDIP = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// Diretório de upload
$uploadDir = '../assets/uploads/desenhos/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

$acao = $_POST['acao'] ?? $_GET['acao'] ?? null;

// ===== CRIAR NOVO DESENHO =====
if ($acao === 'criar_desenho') {
    $osId = (int) ($_POST['os_id'] ?? 0);
    $titulo = sanitize($_POST['titulo'] ?? '');
    $descricao = $_POST['descricao'] ?? null;
    $qualidadeExigida = $_POST['qualidade_exigida'] ?? 'normal';
    $prioridade = $_POST['prioridade'] ?? 'normal';
    $enviar = $_POST['enviar'] ?? 'rascunho';

    if (!$osId || !$titulo) {
        http_response_code(400);
        echo json_encode(['erro' => 'OS e Título são obrigatórios']);
        exit;
    }

    // Validar O.S.
    $stmt = $db->prepare("SELECT id FROM ordens_servico WHERE id = ?");
    $stmt->execute([$osId]);
    if (!$stmt->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['erro' => 'Ordem de Serviço não encontrada']);
        exit;
    }

    try {
        $statusInicial = $enviar === 'submetido' ? 'submetido' : 'rascunho';
        $dataSubmissao = $enviar === 'submetido' ? date('Y-m-d H:i:s') : null;

        // Inserir desenho
        $stmt = $db->prepare("
            INSERT INTO desenhos_tecnicos
            (os_id, titulo, descricao, versao, status, prioridade, usuario_projetista_id, qualidade_exigida, data_submissao)
            VALUES (?, ?, ?, 'v1.0', ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$osId, $titulo, $descricao, $statusInicial, $prioridade, $usuarioId, $qualidadeExigida, $dataSubmissao]);
        $desenhoId = (int) $db->lastInsertId();

        // Registrar histórico
        registrarHistoricoDesenho($db, $desenhoId, 'criacao', $usuarioId, null, $statusInicial, "Desenho criado por $usuarioNome");

        // Processar uploads
        $totalArquivos = 0;
        if (!empty($_FILES['arquivos']['name'][0])) {
            $totalArquivos = processarUploadDesenho($db, $desenhoId, $osId, $usuarioId);
        }

        // Se submetido, criar registros de aprovação
        if ($enviar === 'submetido') {
            criarRegistrosAprovacao($db, $desenhoId);
        }

        echo json_encode([
            'sucesso' => true,
            'mensagem' => "Desenho criado com sucesso. $totalArquivos arquivo(s) enviado(s).",
            'desenho_id' => $desenhoId,
            'versao' => 'v1.0',
            'status' => $statusInicial,
            'redirect' => "../../modules/engenharia/desenho_tecnico.php?os_id=$osId&desenho_id=$desenhoId"
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['erro' => 'Erro ao criar desenho: ' . $e->getMessage()]);
    }
    exit;
}

// ===== SUBMETER PARA APROVAÇÃO =====
if ($acao === 'submeter_aprovacao') {
    $desenhoId = (int) ($_POST['desenho_id'] ?? 0);

    if (!$desenhoId) {
        http_response_code(400);
        echo json_encode(['erro' => 'ID do desenho é obrigatório']);
        exit;
    }

    try {
        $stmt = $db->prepare("SELECT * FROM desenhos_tecnicos WHERE id = ?");
        $stmt->execute([$desenhoId]);
        $desenho = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$desenho) {
            http_response_code(404);
            echo json_encode(['erro' => 'Desenho não encontrado']);
            exit;
        }

        // Validar permissão (apenas projetista ou master)
        if ($usuarioId !== $desenho['usuario_projetista_id'] && $usuarioPerfil !== 'master') {
            http_response_code(403);
            echo json_encode(['erro' => 'Sem permissão para submeter este desenho']);
            exit;
        }

        // Atualizar status
        $stmt = $db->prepare("
            UPDATE desenhos_tecnicos
            SET status = 'submetido', data_submissao = ?
            WHERE id = ?
        ");
        $stmt->execute([date('Y-m-d H:i:s'), $desenhoId]);

        // Criar registros de aprovação
        criarRegistrosAprovacao($db, $desenhoId);

        // Registrar histórico
        registrarHistoricoDesenho($db, $desenhoId, 'submissao', $usuarioId, 'rascunho', 'submetido', 'Desenho submetido para aprovação');

        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Desenho submetido para aprovação',
            'status' => 'submetido'
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['erro' => $e->getMessage()]);
    }
    exit;
}

// ===== APROVAR DESENHO =====
if ($acao === 'aprovar') {
    $desenhoId = (int) ($_POST['desenho_id'] ?? 0);
    $etapa = sanitize($_POST['etapa'] ?? 'gerencia'); // gerencia, producao, qualidade
    $observacoes = $_POST['observacoes'] ?? null;

    if (!$desenhoId) {
        http_response_code(400);
        echo json_encode(['erro' => 'ID do desenho é obrigatório']);
        exit;
    }

    try {
        $stmt = $db->prepare("SELECT * FROM desenhos_tecnicos WHERE id = ?");
        $stmt->execute([$desenhoId]);
        $desenho = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$desenho) {
            http_response_code(404);
            echo json_encode(['erro' => 'Desenho não encontrado']);
            exit;
        }

        // Validar permissão baseado na etapa
        $permissoesPorEtapa = [
            'gerencia' => ['master', 'gerente'],
            'producao' => ['master', 'producao'],
            'qualidade' => ['master', 'gerente']
        ];

        if (!in_array($usuarioPerfil, $permissoesPorEtapa[$etapa] ?? [])) {
            http_response_code(403);
            echo json_encode(['erro' => 'Sem permissão para aprovar nesta etapa']);
            exit;
        }

        // Registrar aprovação
        $coluna = $etapa === 'gerencia' ? 'usuario_gerente_id' : ($etapa === 'producao' ? 'usuario_producao_id' : 'usuario_gerente_id');
        $stmt = $db->prepare("
            UPDATE desenhos_tecnicos
            SET $coluna = ?, data_aprovacao_" . ($etapa === 'producao' ? 'producao' : 'gerencia') . " = ?
            WHERE id = ?
        ");
        $stmt->execute([$usuarioId, date('Y-m-d H:i:s'), $desenhoId]);

        // Atualizar registro de aprovação
        $stmt = $db->prepare("
            UPDATE desenhos_aprovaes
            SET status = 'aprovado', usuario_id = ?, data_resposta = ?, observacoes = ?
            WHERE desenho_id = ? AND etapa = ?
        ");
        $stmt->execute([$usuarioId, date('Y-m-d H:i:s'), $observacoes, $desenhoId, $etapa]);

        // Verificar se todas as etapas foram aprovadas
        $stmt = $db->prepare("SELECT COUNT(*) FROM desenhos_aprovaes WHERE desenho_id = ? AND status != 'aprovado'");
        $stmt->execute([$desenhoId]);
        $pendentes = $stmt->fetchColumn();

        $novoStatus = $pendentes > 0 ? 'em_revisao' : 'aprovado';

        if ($pendentes === 0) {
            $stmt = $db->prepare("UPDATE desenhos_tecnicos SET status = 'aprovado' WHERE id = ?");
            $stmt->execute([$desenhoId]);
        }

        // Registrar histórico
        registrarHistoricoDesenho($db, $desenhoId, 'aprovacao', $usuarioId, $desenho['status'], $novoStatus, "Aprovado na etapa: $etapa");

        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Desenho aprovado com sucesso',
            'status' => $novoStatus,
            'proxima_etapa' => $pendentes > 0 ? 'Aguardando próximas aprovações' : 'Aprovado para produção'
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['erro' => $e->getMessage()]);
    }
    exit;
}

// ===== REJEITAR DESENHO =====
if ($acao === 'rejeitar') {
    $desenhoId = (int) ($_POST['desenho_id'] ?? 0);
    $etapa = sanitize($_POST['etapa'] ?? 'gerencia');
    $motivo = $_POST['motivo'] ?? '';
    $observacoes = $_POST['observacoes'] ?? null;

    if (!$desenhoId || !$motivo) {
        http_response_code(400);
        echo json_encode(['erro' => 'Desenho e motivo da rejeição são obrigatórios']);
        exit;
    }

    try {
        $stmt = $db->prepare("SELECT * FROM desenhos_tecnicos WHERE id = ?");
        $stmt->execute([$desenhoId]);
        $desenho = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$desenho) {
            http_response_code(404);
            echo json_encode(['erro' => 'Desenho não encontrado']);
            exit;
        }

        // Atualizar status
        $stmt = $db->prepare("
            UPDATE desenhos_tecnicos
            SET status = 'rejeitado', data_rejeicao = ?, observacoes_internas = ?
            WHERE id = ?
        ");
        $stmt->execute([date('Y-m-d H:i:s'), "Rejeitado na etapa $etapa: $motivo", $desenhoId]);

        // Registrar rejeição
        $stmt = $db->prepare("
            UPDATE desenhos_aprovaes
            SET status = 'rejeitado', usuario_id = ?, data_resposta = ?, observacoes = ?, requer_alteracoes = 1
            WHERE desenho_id = ? AND etapa = ?
        ");
        $stmt->execute([$usuarioId, date('Y-m-d H:i:s'), $motivo . (!empty($observacoes) ? " - " . $observacoes : ''), $desenhoId, $etapa]);

        // Registrar histórico
        registrarHistoricoDesenho($db, $desenhoId, 'rejeicao', $usuarioId, $desenho['status'], 'rejeitado', "Rejeitado: $motivo");

        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Desenho rejeitado. Notificação enviada ao projetista.',
            'status' => 'rejeitado',
            'proxima_acao' => 'Aguardando revisão e resubmissão do projetista'
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['erro' => $e->getMessage()]);
    }
    exit;
}

// ===== OBTER DESENHO (com todos os dados) =====
if ($acao === 'obter_desenho') {
    $desenhoId = (int) ($_GET['desenho_id'] ?? 0);

    if (!$desenhoId) {
        http_response_code(400);
        echo json_encode(['erro' => 'ID do desenho é obrigatório']);
        exit;
    }

    try {
        $stmt = $db->prepare("
            SELECT d.*,
                   u_proj.nome AS projetista_nome,
                   u_ger.nome AS gerente_nome,
                   u_prod.nome AS producao_nome
            FROM desenhos_tecnicos d
            LEFT JOIN usuarios u_proj ON u_proj.id = d.usuario_projetista_id
            LEFT JOIN usuarios u_ger ON u_ger.id = d.usuario_gerente_id
            LEFT JOIN usuarios u_prod ON u_prod.id = d.usuario_producao_id
            WHERE d.id = ?
        ");
        $stmt->execute([$desenhoId]);
        $desenho = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$desenho) {
            http_response_code(404);
            echo json_encode(['erro' => 'Desenho não encontrado']);
            exit;
        }

        // Obter arquivos
        $stmt = $db->prepare("
            SELECT * FROM desenhos_arquivos WHERE desenho_id = ?
            ORDER BY sequencia ASC
        ");
        $stmt->execute([$desenhoId]);
        $arquivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Obter aprovações
        $stmt = $db->prepare("
            SELECT * FROM desenhos_aprovaes WHERE desenho_id = ?
            ORDER BY etapa
        ");
        $stmt->execute([$desenhoId]);
        $aprovaes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Obter histórico
        $stmt = $db->prepare("
            SELECT dh.*, u.nome AS usuario_nome
            FROM desenhos_historico dh
            LEFT JOIN usuarios u ON u.id = dh.usuario_id
            WHERE dh.desenho_id = ?
            ORDER BY dh.created_at DESC
        ");
        $stmt->execute([$desenhoId]);
        $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'sucesso' => true,
            'desenho' => $desenho,
            'arquivos' => $arquivos,
            'aprovaes' => $aprovaes,
            'historico' => $historico
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['erro' => $e->getMessage()]);
    }
    exit;
}

// ===== LISTAR DESENHOS DE UMA O.S. =====
if ($acao === 'listar_desenhos') {
    $osId = (int) ($_GET['os_id'] ?? 0);
    $status = sanitize($_GET['status'] ?? null);

    if (!$osId) {
        http_response_code(400);
        echo json_encode(['erro' => 'ID da OS é obrigatório']);
        exit;
    }

    try {
        $sql = "
            SELECT d.*,
                   u_proj.nome AS projetista_nome,
                   u_ger.nome AS gerente_nome,
                   u_prod.nome AS producao_nome,
                   COUNT(da.id) AS total_arquivos
            FROM desenhos_tecnicos d
            LEFT JOIN usuarios u_proj ON u_proj.id = d.usuario_projetista_id
            LEFT JOIN usuarios u_ger ON u_ger.id = d.usuario_gerente_id
            LEFT JOIN usuarios u_prod ON u_prod.id = d.usuario_producao_id
            LEFT JOIN desenhos_arquivos da ON da.desenho_id = d.id
            WHERE d.os_id = ?
        ";
        $params = [$osId];

        if ($status) {
            $sql .= " AND d.status = ?";
            $params[] = $status;
        }

        $sql .= " GROUP BY d.id ORDER BY d.created_at DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $desenhos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'sucesso' => true,
            'total' => count($desenhos),
            'desenhos' => $desenhos
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['erro' => $e->getMessage()]);
    }
    exit;
}

// ===== OBTER HISTÓRICO =====
if ($acao === 'obter_historico') {
    $desenhoId = (int) ($_GET['desenho_id'] ?? 0);

    if (!$desenhoId) {
        http_response_code(400);
        echo json_encode(['erro' => 'ID do desenho é obrigatório']);
        exit;
    }

    try {
        $stmt = $db->prepare("
            SELECT dh.*, u.nome AS usuario_nome
            FROM desenhos_historico dh
            LEFT JOIN usuarios u ON u.id = dh.usuario_id
            WHERE dh.desenho_id = ?
            ORDER BY dh.created_at DESC
        ");
        $stmt->execute([$desenhoId]);
        $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'sucesso' => true,
            'total' => count($historico),
            'historico' => $historico
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['erro' => $e->getMessage()]);
    }
    exit;
}

// Se nenhuma ação foi reconhecida
http_response_code(400);
echo json_encode(['erro' => 'Ação não reconhecida: ' . ($acao ?? 'nenhuma')]);


// ===== FUNÇÕES AUXILIARES =====

/**
 * Processa upload de arquivos para o desenho
 */
function processarUploadDesenho(PDO $db, int $desenhoId, int $osId, int $usuarioId): int {
    if (empty($_FILES['arquivos']['name'][0])) {
        return 0;
    }

    $uploadDir = '../assets/uploads/desenhos/';
    $arquivosProcessados = 0;
    $tiposPermitidos = ['pdf' => 'pdf', 'dwg' => 'dwg', 'png' => 'png', 'jpg' => 'jpg', 'jpeg' => 'jpg', 'dxf' => 'dxf', '3ds' => '3d', 'obj' => '3d'];

    foreach ($_FILES['arquivos']['name'] as $chave => $nomeOriginal) {
        $erro = $_FILES['arquivos']['error'][$chave];
        $tamanho = $_FILES['arquivos']['size'][$chave];
        $tmpName = $_FILES['arquivos']['tmp_name'][$chave];

        if ($erro !== UPLOAD_ERR_OK) {
            continue; // Pular arquivo com erro
        }

        // Validar tamanho (máx 50MB por arquivo)
        if ($tamanho > 50 * 1024 * 1024) {
            continue;
        }

        $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
        $tipoArquivo = $tiposPermitidos[$extensao] ?? 'outro';

        // Gerar nome único
        $nomeArquivo = uniqid('desenho_' . $desenhoId . '_') . '.' . $extensao;
        $caminhoCompleto = $uploadDir . $nomeArquivo;

        // Mover arquivo
        if (move_uploaded_file($tmpName, $caminhoCompleto)) {
            // Registrar no banco
            $stmt = $db->prepare("
                INSERT INTO desenhos_arquivos
                (desenho_id, arquivo_tipo, nome_original, nome_arquivo, caminho_arquivo, tamanho_bytes, usuario_upload_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$desenhoId, $tipoArquivo, $nomeOriginal, $nomeArquivo, $caminhoCompleto, $tamanho, $usuarioId]);
            $arquivosProcessados++;
        }
    }

    return $arquivosProcessados;
}

/**
 * Cria registros iniciais de aprovação para um desenho
 */
function criarRegistrosAprovacao(PDO $db, int $desenhoId): void {
    $etapas = ['gerencia', 'producao'];
    $prazo = date('Y-m-d H:i:s', strtotime('+5 days'));

    foreach ($etapas as $etapa) {
        $stmt = $db->prepare("
            INSERT IGNORE INTO desenhos_aprovaes
            (desenho_id, etapa, status, prazo_resposta)
            VALUES (?, ?, 'pendente', ?)
        ");
        $stmt->execute([$desenhoId, $etapa, $prazo]);
    }
}

/**
 * Registra ação no histórico do desenho
 */
function registrarHistoricoDesenho(PDO $db, int $desenhoId, string $acao, int $usuarioId, ?string $statusAnterior, ?string $statusNovo, ?string $detalhes): void {
    try {
        $stmt = $db->prepare("
            INSERT INTO desenhos_historico
            (desenho_id, acao, usuario_id, status_anterior, status_novo, detalhes, endereco_ip)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $desenhoId,
            $acao,
            $usuarioId,
            $statusAnterior,
            $statusNovo,
            $detalhes,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
        ]);
    } catch (Exception $e) {
        error_log("Erro ao registrar histórico: " . $e->getMessage());
    }
}
