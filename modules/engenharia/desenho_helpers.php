<?php
/**
 * Funções Auxiliares para o Módulo de Desenho Técnico
 *
 * Utilitários para integração com outros módulos, consultoria de dados,
 * e operações comuns no contexto de desenhos técnicos.
 */

/**
 * Obter o desenho técnico mais recente de uma O.S.
 *
 * @param PDO $db
 * @param int $osId
 * @return array|null
 */
function obterDesenhoMaisRecente(PDO $db, int $osId): ?array {
    $stmt = $db->prepare("
        SELECT d.*, u.nome AS projetista_nome
        FROM desenhos_tecnicos d
        LEFT JOIN usuarios u ON u.id = d.usuario_projetista_id
        WHERE d.os_id = ?
        ORDER BY d.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$osId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Obter desenho técnico aprovado de uma O.S.
 *
 * @param PDO $db
 * @param int $osId
 * @return array|null
 */
function obterDesenhoAprovado(PDO $db, int $osId): ?array {
    $stmt = $db->prepare("
        SELECT d.*, u.nome AS projetista_nome
        FROM desenhos_tecnicos d
        LEFT JOIN usuarios u ON u.id = d.usuario_projetista_id
        WHERE d.os_id = ? AND d.status = 'aprovado'
        ORDER BY d.versao DESC
        LIMIT 1
    ");
    $stmt->execute([$osId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Verificar se uma O.S. tem desenho técnico aprovado
 *
 * @param PDO $db
 * @param int $osId
 * @return bool
 */
function temDesenhoAprovado(PDO $db, int $osId): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM desenhos_tecnicos
        WHERE os_id = ? AND status = 'aprovado'
    ");
    $stmt->execute([$osId]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Obter todos os arquivos de um desenho
 *
 * @param PDO $db
 * @param int $desenhoId
 * @param string|null $tipo Filtrar por tipo (pdf, dwg, png, jpg, 3d, dxf)
 * @return array
 */
function obterArquivosDesenho(PDO $db, int $desenhoId, ?string $tipo = null): array {
    $sql = "
        SELECT * FROM desenhos_arquivos
        WHERE desenho_id = ?
    ";
    $params = [$desenhoId];

    if ($tipo) {
        $sql .= " AND arquivo_tipo = ?";
        $params[] = $tipo;
    }

    $sql .= " ORDER BY sequencia ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obter arquivos PDF/imagem de um desenho para visualização
 *
 * @param PDO $db
 * @param int $desenhoId
 * @return array
 */
function obterArquivosVisualizacao(PDO $db, int $desenhoId): array {
    $stmt = $db->prepare("
        SELECT * FROM desenhos_arquivos
        WHERE desenho_id = ? AND arquivo_tipo IN ('pdf', 'png', 'jpg')
        ORDER BY sequencia ASC
    ");
    $stmt->execute([$desenhoId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obter status atual das aprovações de um desenho
 *
 * @param PDO $db
 * @param int $desenhoId
 * @return array Associativo: ['gerencia' => [...], 'producao' => [...], 'qualidade' => [...]]
 */
function obterStatusAprovaes(PDO $db, int $desenhoId): array {
    $stmt = $db->prepare("
        SELECT da.*, u.nome AS usuario_nome
        FROM desenhos_aprovaes da
        LEFT JOIN usuarios u ON u.id = da.usuario_id
        WHERE da.desenho_id = ?
        ORDER BY da.etapa
    ");
    $stmt->execute([$desenhoId]);

    $resultado = [
        'gerencia' => null,
        'producao' => null,
        'qualidade' => null,
    ];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $aprv) {
        $resultado[$aprv['etapa']] = $aprv;
    }

    return $resultado;
}

/**
 * Verificar se desenho está aguardando aprovação do usuário
 *
 * @param PDO $db
 * @param int $desenhoId
 * @param int $usuarioId
 * @param string $etapa gerencia|producao|qualidade
 * @return bool
 */
function usuarioDeveAprovar(PDO $db, int $desenhoId, int $usuarioId, string $etapa): bool {
    // Obter perfil do usuário
    $stmt = $db->prepare("SELECT perfil FROM usuarios WHERE id = ?");
    $stmt->execute([$usuarioId]);
    $perfil = $stmt->fetchColumn();

    // Verificar permissão por etapa
    $permissoes = [
        'gerencia' => ['master', 'gerente'],
        'producao' => ['master', 'producao'],
        'qualidade' => ['master', 'gerente'],
    ];

    if (!in_array($perfil, $permissoes[$etapa] ?? [])) {
        return false;
    }

    // Verificar se aprovação está pendente
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM desenhos_aprovaes
        WHERE desenho_id = ? AND etapa = ? AND status = 'pendente'
    ");
    $stmt->execute([$desenhoId, $etapa]);

    return (bool) $stmt->fetchColumn();
}

/**
 * Obter desenhos pendentes de aprovação para um usuário
 *
 * @param PDO $db
 * @param int $usuarioId
 * @param string $etapa gerencia|producao|qualidade
 * @return array
 */
function obterDesenhosAguardandoAprovacao(PDO $db, int $usuarioId, string $etapa = 'gerencia'): array {
    $stmt = $db->prepare("
        SELECT d.*, os.numero AS os_numero,
               COUNT(da.id) AS total_arquivos
        FROM desenhos_tecnicos d
        INNER JOIN desenhos_aprovaes da_check ON da_check.desenho_id = d.id
        INNER JOIN ordens_servico os ON os.id = d.os_id
        LEFT JOIN desenhos_arquivos da ON da.desenho_id = d.id
        WHERE da_check.etapa = ?
          AND da_check.status = 'pendente'
          AND d.status IN ('submetido', 'em_revisao')
          AND da_check.prazo_resposta > NOW()
        GROUP BY d.id
        ORDER BY da_check.prazo_resposta ASC,
                 d.prioridade DESC
    ");
    $stmt->execute([$etapa]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Contar desenhos aguardando aprovação
 *
 * @param PDO $db
 * @param string $etapa gerencia|producao|qualidade
 * @return int
 */
function contarDesenhosAguardando(PDO $db, string $etapa = 'gerencia'): int {
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM desenhos_approvaes
        WHERE etapa = ? AND status = 'pendente' AND prazo_resposta > NOW()
    ");
    $stmt->execute([$etapa]);
    return (int) $stmt->fetchColumn();
}

/**
 * Obter histórico de alterações de um desenho
 *
 * @param PDO $db
 * @param int $desenhoId
 * @param int $limite
 * @return array
 */
function obterHistoricoDesenho(PDO $db, int $desenhoId, int $limite = 50): array {
    $stmt = $db->prepare("
        SELECT dh.*, u.nome AS usuario_nome, u.email
        FROM desenhos_historico dh
        LEFT JOIN usuarios u ON u.id = dh.usuario_id
        WHERE dh.desenho_id = ?
        ORDER BY dh.created_at DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $desenhoId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limite, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obter próxima versão para um desenho
 *
 * @param PDO $db
 * @param int $desenhoId
 * @param string $tipo criacao|atualizacao|correcao|upgrade
 * @return string Nova versão (ex: v1.1, v2.0)
 */
function obterProximaVersao(PDO $db, int $desenhoId, string $tipo = 'atualizacao'): string {
    // Obter última versão
    $stmt = $db->prepare("
        SELECT versao FROM desenhos_tecnicos WHERE id = ?
    ");
    $stmt->execute([$desenhoId]);
    $versaoAtual = $stmt->fetchColumn() ?: 'v1.0';

    // Parsear versão (ex: v1.0 → [1, 0])
    preg_match('/v(\d+)\.(\d+)/', $versaoAtual, $matches);
    $maior = (int) ($matches[1] ?? 1);
    $menor = (int) ($matches[2] ?? 0);

    // Incrementar baseado no tipo
    switch ($tipo) {
        case 'criacao':
            return 'v1.0';
        case 'correcao':
            $menor++;
            break;
        case 'upgrade':
        case 'atualizacao':
            $menor++;
            break;
        default:
            $menor++;
    }

    return "v$maior.$menor";
}

/**
 * Gerar relatório de desenhos pendentes
 *
 * @param PDO $db
 * @return array Estrutura: ['total', 'por_etapa' => [...], 'por_prioridade' => [...]]
 */
function gerarRelatorioPendentes(PDO $db): array {
    // Total
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM desenhos_tecnicos
        WHERE status IN ('submetido', 'em_revisao')
    ");
    $stmt->execute();
    $total = (int) $stmt->fetchColumn();

    // Por etapa
    $stmt = $db->prepare("
        SELECT da.etapa, COUNT(*) AS total
        FROM desenhos_aprovaes da
        WHERE da.status = 'pendente'
        GROUP BY da.etapa
    ");
    $stmt->execute();
    $porEtapa = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $porEtapa[$row['etapa']] = (int) $row['total'];
    }

    // Por prioridade
    $stmt = $db->prepare("
        SELECT d.prioridade, COUNT(*) AS total
        FROM desenhos_tecnicos d
        WHERE d.status IN ('submetido', 'em_revisao')
        GROUP BY d.prioridade
    ");
    $stmt->execute();
    $porPrioridade = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $porPrioridade[$row['prioridade']] = (int) $row['total'];
    }

    return [
        'total' => $total,
        'por_etapa' => $porEtapa,
        'por_prioridade' => $porPrioridade,
    ];
}

/**
 * Obter resumo de desempenho de aprovações
 *
 * @param PDO $db
 * @param int $diasAtras Últimos N dias
 * @return array
 */
function obterDesempenhoAprovaes(PDO $db, int $diasAtras = 30): array {
    $dataLimite = date('Y-m-d', strtotime("-$diasAtras days"));

    // Total aprovado
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM desenhos_tecnicos
        WHERE status = 'aprovado' AND data_aprovacao_gerencia >= ?
    ");
    $stmt->execute([$dataLimite]);
    $aprovados = (int) $stmt->fetchColumn();

    // Total rejeitado
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM desenhos_tecnicos
        WHERE status = 'rejeitado' AND data_rejeicao >= ?
    ");
    $stmt->execute([$dataLimite]);
    $rejeitados = (int) $stmt->fetchColumn();

    // Tempo médio de aprovação (em horas)
    $stmt = $db->prepare("
        SELECT AVG(TIMESTAMPDIFF(HOUR, data_submissao, data_aprovacao_gerencia))
        FROM desenhos_tecnicos
        WHERE status = 'aprovado'
          AND data_submissao >= ?
          AND data_aprovacao_gerencia IS NOT NULL
    ");
    $stmt->execute([$dataLimite]);
    $tempoMedioHoras = (float) ($stmt->fetchColumn() ?? 0);

    return [
        'periodo' => "$diasAtras dias",
        'aprovados' => $aprovados,
        'rejeitados' => $rejeitados,
        'taxa_aprovacao' => $aprovados + $rejeitados > 0 ?
            round(($aprovados / ($aprovados + $rejeitados)) * 100, 2) . '%' : 'N/A',
        'tempo_medio_horas' => round($tempoMedioHoras, 2),
    ];
}

/**
 * Validar arquivo antes do upload
 *
 * @param array $arquivo $_FILES['arquivo']
 * @param int $maxSizeMB Tamanho máximo em MB
 * @return array ['valido' => bool, 'erro' => string|null]
 */
function validarArquivoDesenho(array $arquivo, int $maxSizeMB = 50): array {
    $tiposPermitidos = ['pdf', 'dwg', 'png', 'jpg', 'jpeg', 'dxf', '3ds', 'obj'];
    $maxSizeBytes = $maxSizeMB * 1024 * 1024;

    // Verificar erros de upload
    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        return [
            'valido' => false,
            'erro' => 'Erro no upload: ' . $arquivo['error']
        ];
    }

    // Verificar tamanho
    if ($arquivo['size'] > $maxSizeBytes) {
        return [
            'valido' => false,
            'erro' => "Arquivo muito grande. Máximo: $maxSizeMB MB"
        ];
    }

    // Verificar extensão
    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extensao, $tiposPermitidos)) {
        return [
            'valido' => false,
            'erro' => 'Tipo de arquivo não permitido: ' . $extensao
        ];
    }

    return ['valido' => true];
}

/**
 * Gerar URL de download seguro para arquivo
 *
 * @param int $arquivoId
 * @return string URL de download
 */
function gerarUrlDownloadArquivo(int $arquivoId): string {
    return "../../api/desenho_download.php?arquivo_id=$arquivoId";
}

/**
 * Limpar desenhos obsoletos (deletar após N dias)
 *
 * @param PDO $db
 * @param int $diasRetencao
 * @return int Quantidade de registros deletados
 */
function limparDesenhosObsoletos(PDO $db, int $diasRetencao = 90): int {
    $dataLimite = date('Y-m-d', strtotime("-$diasRetencao days"));

    // Obter desenhos obsoletos
    $stmt = $db->prepare("
        SELECT id FROM desenhos_tecnicos
        WHERE status = 'obsoleto' AND updated_at < ?
    ");
    $stmt->execute([$dataLimite]);
    $desenhos = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $deletados = 0;
    foreach ($desenhos as $desenhoId) {
        // Deletar arquivos do disco
        $stmt = $db->prepare("SELECT caminho_arquivo FROM desenhos_arquivos WHERE desenho_id = ?");
        $stmt->execute([$desenhoId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $caminho) {
            if (file_exists($caminho)) {
                @unlink($caminho);
            }
        }

        // Deletar registro do banco (CASCADE)
        $stmt = $db->prepare("DELETE FROM desenhos_tecnicos WHERE id = ?");
        $stmt->execute([$desenhoId]);
        $deletados++;
    }

    return $deletados;
}

/**
 * Exportar desenho para PDF com histórico
 *
 * Nota: Requer biblioteca dompdf ou similar
 *
 * @param PDO $db
 * @param int $desenhoId
 * @return string Conteúdo PDF (base64 ou binário)
 */
function exportarDesenhoPDF(PDO $db, int $desenhoId): string {
    // TODO: Implementar com dompdf/mpdf
    // Por enquanto retorna string vazia
    return '';
}
