<?php
/**
 * Workflow centralizado do ERP.
 *
 * Responsável por validar transições de status e etapas,
 * garantindo que o fluxo comercial/produtivo seja respeitado.
 */

/**
 * Retorna a lista de status válidos da O.S.
 */
function getValidOSStatuses(): array {
    return [
        'pendente',
        'em_projeto',
        'proposta',
        'em_revisao',
        'em_producao',
        'concluida',
        'cancelada',
    ];
}

/**
 * Retorna a lista de etapas válidas da O.S. no fluxo produtivo.
 * Deve espelhar o ENUM de ordens_servico.etapa_atual no banco.
 */
function getValidOSEtapas(): array {
    return [
        'autorizacao',
        'engenharia',
        'programacao',
        'corte',
        'dobra',
        'tubo',
        'solda',
        'mobiliario',
        'coccao',
        'refrigeracao',
        'acabamento',
        'montagem',
        'embalagem',
        'finalizacao',
        'concluida',
    ];
}

/**
 * Retorna a ordem canônica das etapas produtivas.
 */
function getEtapaFluxo(): array {
    return getValidOSEtapas();
}

/**
 * Etapas de bancada (apontamento em os_etapas_producao).
 * Deve espelhar o ENUM de os_etapas_producao.etapa no banco.
 */
function getEtapasBancada(): array {
    return array_values(array_diff(getEtapaFluxo(), ['autorizacao', 'concluida']));
}

/**
 * Mapa de etapas que o perfil pode OPERAR (iniciar/finalizar/retornar).
 * Cada setor opera apenas a própria etapa; perfis de gestão operam todas.
 * Perfis fora do mapa não operam etapa nenhuma (acesso somente leitura).
 */
function getPermittedEtapasByProfile(string $userType): array {
    $todas = getValidOSEtapas();
    $map = [
        'master' => $todas,
        'gerente' => $todas,
        'producao' => $todas,
        // Projetista e engenharia são a MESMA função: a conta do projetista
        // desenha o projeto e opera a etapa de engenharia da produção. O tipo
        // 'engenharia' é apenas um alias com o mesmo escopo.
        'projetista' => ['engenharia'],
        'engenharia' => ['engenharia'],
        'programacao' => ['programacao'],
        'corte' => ['corte'],
        'dobra' => ['dobra'],
        'tubo' => ['tubo'],
        'solda' => ['solda'],
        'mobiliario' => ['mobiliario'],
        'coccao' => ['coccao'],
        'refrigeracao' => ['refrigeracao'],
        'acabamento' => ['acabamento'],
        'montagem' => ['montagem'],
        'embalagem' => ['embalagem'],
        'finalizacao' => ['finalizacao'],
    ];

    return $map[strtolower($userType)] ?? [];
}

/**
 * Valida se a transição de status é permitida.
 */
function validateOSStatusTransition(string $from, string $to, ?string $userType = null): array {
    $validStatuses = getValidOSStatuses();
    if (!in_array($from, $validStatuses, true) || !in_array($to, $validStatuses, true)) {
        return ['valid' => false, 'message' => 'Status inválido.'];
    }

    // Regras explícitas do fluxo comercial
    if ($from === $to) {
        return ['valid' => true, 'message' => 'Mesmo status permitido.'];
    }

    // Cancelada pode ir de/para pendente em alguns fluxos, mas geralmente é terminal
    if ($to === 'cancelada' && !in_array($from, ['pendente','em_projeto','proposta','em_revisao','em_producao'], true)) {
        return ['valid' => false, 'message' => 'Não é permitido cancelar a partir do status atual.'];
    }

    if ($from === 'cancelada') {
        return ['valid' => false, 'message' => 'O.S. cancelada não pode ter status alterado.'];
    }

    $allowed = [
        'pendente' => ['em_projeto','em_producao','proposta','cancelada'],
        'em_projeto' => ['proposta','pendente','cancelada'],
        'proposta' => ['em_producao','em_projeto','em_revisao','cancelada'],
        'em_revisao' => ['proposta','em_projeto','em_producao','cancelada'],
        'em_producao' => ['concluida','em_projeto','cancelada'],
        'concluida' => [],
    ];

    if (!isset($allowed[$from]) || !in_array($to, $allowed[$from], true)) {
        return ['valid' => false, 'message' => "Transição de status '$from' para '$to' não permitida."];
    }

    return ['valid' => true, 'message' => 'Transição permitida.'];
}

/**
 * Valida se a transição de etapa produtiva é permitida.
 */
function validateEtapaTransition(string $from, string $to, ?string $userType = null): array {
    $valid = getValidOSEtapas();
    if (!in_array($from, $valid, true) || !in_array($to, $valid, true)) {
        return ['valid' => false, 'message' => 'Etapa inválida.'];
    }

    if ($from === $to) {
        return ['valid' => true, 'message' => 'Mesma etapa permitida.'];
    }

    $fluxo = getEtapaFluxo();
    $posFrom = array_search($from, $fluxo, true);
    $posTo = array_search($to, $fluxo, true);

    if ($posFrom === false || $posTo === false) {
        return ['valid' => false, 'message' => 'Etapas não encontradas no fluxo padrão.'];
    }

    // Concluída não pode retroceder
    if ($from === 'concluida') {
        return ['valid' => false, 'message' => 'Etapa concluída não pode ser alterada.'];
    }

    // Retrocesso só é permitido pelo fluxo de retorno (retornar_etapa), com justificativa
    if ($posTo <= $posFrom) {
        return ['valid' => false, 'message' => 'Só é permitido avançar para uma etapa posterior.'];
    }

    // Avançar mais de uma posição é permitido: as etapas de cada O.S. são
    // definidas pelo planejamento da engenharia (os_etapas_producao), então
    // etapas não planejadas (ex: refrigeração) são puladas legitimamente.
    return ['valid' => true, 'message' => 'Transição permitida.'];
}

/**
 * Valida se o usuário pode operar na etapa informada.
 */
function validateUserCanOperateEtapa(string $etapa, string $userType): array {
    $permitted = getPermittedEtapasByProfile($userType);
    if (empty($permitted)) {
        return ['valid' => false, 'message' => "Usuário do perfil '$userType' não pode operar etapas de produção."];
    }

    if (!in_array($etapa, $permitted, true)) {
        return ['valid' => false, 'message' => "Usuário do perfil '$userType' não pode operar na etapa '$etapa'."];
    }

    return ['valid' => true, 'message' => 'Permissão concedida.'];
}

/**
 * Valida se o usuário pode aprovar/rejeitar uma proposta.
 */
function validateCanApproveProposal(string $userType): array {
    if (!in_array($userType, ['master','gerente','vendedor','projetista'], true)) {
        return ['valid' => false, 'message' => 'Usuário não tem permissão para aprovar propostas.'];
    }
    return ['valid' => true, 'message' => 'Permissão concedida.'];
}

/**
 * Valida se o primeiro processo produtivo foi iniciado/finalizado.
 * Usado para impedir avanço sem apontamento inicial.
 */
function validateFirstProductionStepStarted(PDO $db, int $osId, string $currentEtapa): array {
    $fluxo = getEtapasBancada();
    if (empty($fluxo)) {
        return ['valid' => true, 'message' => 'Fluxo vazio.'];
    }

    $firstEtapa = $fluxo[0];

    if ($currentEtapa === $firstEtapa) {
        $stmt = $db->prepare("SELECT id, status FROM os_etapas_producao WHERE os_id = ? AND etapa = ?");
        $stmt->execute([$osId, $firstEtapa]);
        $etapa = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$etapa || ($etapa['status'] ?? 'pendente') === 'pendente') {
            return ['valid' => false, 'message' => "Inicie o primeiro processo '$firstEtapa' antes de prosseguir."];
        }
    }

    return ['valid' => true, 'message' => 'Primeiro processo ok.'];
}

/**
 * Valida se a etapa atual possui apontamento/anexo obrigatório antes de avançar.
 */
function validateStepTrackingAndAttachment(PDO $db, int $osId, string $etapa, string $acao = 'finalizar'): array {
    if ($acao !== 'finalizar') {
        return ['valid' => true, 'message' => 'Ação não requer validação de apontamento.'];
    }

    $stmt = $db->prepare("SELECT id, status, data_inicio FROM os_etapas_producao WHERE os_id = ? AND etapa = ?");
    $stmt->execute([$osId, $etapa]);
    $etapaInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$etapaInfo || ($etapaInfo['status'] ?? 'pendente') === 'pendente') {
        return ['valid' => false, 'message' => "Inicie o apontamento da etapa '$etapa' antes de avançar."];
    }

    if (empty($etapaInfo['data_inicio'])) {
        return ['valid' => false, 'message' => "Inicie o cronômetro na etapa '$etapa' antes de avançar."];
    }

    $stmtArq = $db->prepare("SELECT COUNT(*) FROM os_arquivos WHERE os_id = ? AND tipo IN ('projeto_pdf','projeto_dxf','projeto_foto','projeto')");
    $stmtArq->execute([$osId]);
    $temAnexo = (int) $stmtArq->fetchColumn() > 0;

    if (!$temAnexo) {
        return ['valid' => false, 'message' => "Anexe pelo menos um arquivo à O.S. antes de avançar da etapa '$etapa'."];
    }

    return ['valid' => true, 'message' => 'Apontamento e anexo ok.'];
}
