<?php
/**
 * Workflow centralizado do ERP.
 *
 * Responsável por validar transições de status e etapas,
 * garantindo que o fluxo comercial/produtivo seja respeitado.
 */

/**
 * Nome de exibição de uma etapa. A etapa "engenharia" aparece como
 * "Projetista" — o setor foi unificado (projetista e engenharia são um só);
 * a chave interna continua "engenharia" no banco por compatibilidade.
 */
function getEtapaLabel(string $etapa): string {
    $labels = [
        'autorizacao'  => 'Autorização',
        'engenharia'   => 'Projetista',
        'programacao'  => 'Programação',
        'corte'        => 'Corte',
        'dobra'        => 'Dobra',
        'tubo'         => 'Tubo',
        'solda'        => 'Solda',
        'mobiliario'   => 'Mobiliário',
        'coccao'       => 'Cocção',
        'refrigeracao' => 'Refrigeração',
        'acabamento'   => 'Acabamento',
        'montagem'     => 'Montagem',
        'embalagem'    => 'Embalagem',
        'finalizacao'  => 'Finalização',
        'concluida'    => 'Concluída',
    ];
    return $labels[$etapa] ?? ucfirst($etapa);
}

/**
 * Mapa etapa => label para uso em JS (json_encode).
 */
function getEtapasLabels(): array {
    $mapa = [];
    foreach (array_merge(getValidOSEtapas(), ['autorizacao', 'concluida']) as $e) {
        $mapa[$e] = getEtapaLabel($e);
    }
    return $mapa;
}

/**
 * Bolinhas (indicadores coloridos) da O.S./O.P.:
 *   mobiliário = verde, refrigeração = azul, cocção = amarelo,
 *   urgência (prioridade vermelha) = rosa, + cor da linha do produto
 *   (produto_categorias.cor). $os precisa de: id, prioridade, venda_id.
 */
function getBolinhasOS(PDO $db, array $os): array {
    $bolinhas = [];
    if (($os['prioridade'] ?? '') === 'vermelho') {
        $bolinhas[] = ['cor' => '#ec4899', 'titulo' => 'Urgência'];
    }
    try {
        $stmt = $db->prepare("SELECT DISTINCT etapa FROM os_etapas_producao WHERE os_id = ? AND etapa IN ('mobiliario', 'coccao', 'refrigeracao')");
        $stmt->execute([(int) ($os['id'] ?? 0)]);
        $mapa = [
            'mobiliario'   => ['#16a34a', 'Mobiliário'],
            'refrigeracao' => ['#2563eb', 'Refrigeração'],
            'coccao'       => ['#eab308', 'Cocção'],
        ];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $e) {
            if (isset($mapa[$e])) {
                $bolinhas[] = ['cor' => $mapa[$e][0], 'titulo' => $mapa[$e][1]];
            }
        }
    } catch (Exception $e) { /* sem etapas */ }
    // Cor da linha: categoria dos produtos da O.S. (produto_categorias.cor)
    try {
        if (!empty($os['venda_id'])) {
            $stmt = $db->prepare("SELECT DISTINCT pc.cor, pc.nome FROM vendas_itens vi
                INNER JOIN produtos p ON p.id = vi.produto_id
                INNER JOIN produto_categorias pc ON pc.id = p.categoria_id
                WHERE vi.venda_id = ? AND pc.cor IS NOT NULL AND pc.cor != ''");
            $stmt->execute([(int) $os['venda_id']]);
        } else {
            $stmt = $db->prepare("SELECT DISTINCT pc.cor, pc.nome FROM os_itens oi
                INNER JOIN produtos p ON p.id = oi.produto_id
                INNER JOIN produto_categorias pc ON pc.id = p.categoria_id
                WHERE oi.os_id = ? AND pc.cor IS NOT NULL AND pc.cor != ''");
            $stmt->execute([(int) ($os['id'] ?? 0)]);
        }
        $coresJa = array_column($bolinhas, 'cor');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {
            if (!in_array($linha['cor'], $coresJa, true)) {
                $bolinhas[] = ['cor' => $linha['cor'], 'titulo' => 'Linha: ' . $linha['nome']];
                $coresJa[] = $linha['cor'];
            }
        }
    } catch (Exception $e) { /* coluna cor pode não existir ainda */ }
    return $bolinhas;
}

/**
 * HTML das bolinhas (12px, com tooltip). Use ao lado do nº da O.S./O.P.
 */
function renderBolinhasOS(array $bolinhas, int $tamanho = 12): string {
    $html = '';
    foreach ($bolinhas as $b) {
        $html .= '<span title="' . htmlspecialchars($b['titulo']) . '" style="display:inline-block;width:' . $tamanho . 'px;height:' . $tamanho . 'px;border-radius:50%;background:' . htmlspecialchars($b['cor']) . ';border:1px solid rgba(0,0,0,.25);margin-right:3px;vertical-align:middle"></span>';
    }
    return $html;
}

/**
 * Estágios do ciclo de vida da Ordem de Produção (modelo enxuto, sem PCP
 * pesado). Cancelada é tratada à parte.
 */
function getEstagiosCicloOP(): array {
    return [
        'planejada'   => ['label' => 'Planejada',   'cor' => '#64748b', 'icon' => 'fa-clipboard-list'],
        'liberada'    => ['label' => 'Liberada',    'cor' => '#0284c7', 'icon' => 'fa-unlock'],
        'em_producao' => ['label' => 'Em Produção', 'cor' => '#D85A30', 'icon' => 'fa-industry'],
        'encerrada'   => ['label' => 'Encerrada',   'cor' => '#16a34a', 'icon' => 'fa-flag-checkered'],
    ];
}

/**
 * Deriva o estágio do ciclo de vida da OP a partir do estado real da O.S. e
 * das etapas de produção (fonte única — nunca fica desatualizado).
 * $os precisa de: id, status, etapa_atual.
 */
function getCicloVidaOP(PDO $db, array $os): array {
    $statusOS = (string) ($os['status'] ?? '');

    if ($statusOS === 'cancelada') {
        return ['estagio' => 'cancelada', 'label' => 'Cancelada', 'cor' => '#dc2626', 'ordem' => 0, 'progresso' => 0, 'concluidas' => 0, 'total' => 0];
    }

    $stmt = $db->prepare("SELECT status FROM os_etapas_producao WHERE os_id = ?");
    $stmt->execute([(int) ($os['id'] ?? 0)]);
    $etapas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $total = count($etapas);
    $concluidas = 0;
    $iniciadas = 0;
    foreach ($etapas as $st) {
        if ($st === 'concluida') { $concluidas++; $iniciadas++; }
        elseif ($st !== 'pendente') { $iniciadas++; }
    }
    $progresso = $total > 0 ? (int) round($concluidas / $total * 100) : 0;

    if ($statusOS === 'concluida') {
        return ['estagio' => 'encerrada', 'label' => 'Encerrada', 'cor' => '#16a34a', 'ordem' => 4, 'progresso' => 100, 'concluidas' => $concluidas, 'total' => $total];
    }
    if ($statusOS === 'em_producao') {
        if ($iniciadas > 0) {
            return ['estagio' => 'em_producao', 'label' => 'Em Produção', 'cor' => '#D85A30', 'ordem' => 3, 'progresso' => $progresso, 'concluidas' => $concluidas, 'total' => $total];
        }
        return ['estagio' => 'liberada', 'label' => 'Liberada', 'cor' => '#0284c7', 'ordem' => 2, 'progresso' => 0, 'concluidas' => 0, 'total' => $total];
    }
    // pendente, em_projeto, proposta, em_revisao = ainda não liberada
    return ['estagio' => 'planejada', 'label' => 'Planejada', 'cor' => '#64748b', 'ordem' => 1, 'progresso' => 0, 'concluidas' => 0, 'total' => $total];
}

/**
 * Sincroniza o campo ordens_producao.status com o estágio derivado (para
 * relatórios/consultas). Silencioso — não quebra se a OP não existir.
 */
function sincronizarStatusOP(PDO $db, array $os): void {
    $ciclo = getCicloVidaOP($db, $os);
    try {
        $db->prepare("UPDATE ordens_producao SET status = ? WHERE os_id = ?")
           ->execute([$ciclo['estagio'], (int) ($os['id'] ?? 0)]);
    } catch (Exception $e) { /* OP pode não existir ainda */ }
}

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
        'em_projeto' => ['proposta','pendente','em_producao','cancelada'],
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
