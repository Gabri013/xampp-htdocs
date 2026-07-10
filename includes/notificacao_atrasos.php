<?php
/**
 * Sistema de Notificação de Limite de O.S. Atrasadas
 * Monitora o número de atrasos e dispara alertas quando limites são atingidos
 */

/**
 * Verifica se o limite de O.S. atrasadas foi excedido
 * Retorna um array com informações sobre o status do alerta
 */
function verificarLimiteAtrasos($contagem_atraso) {
    $alerta = [
        'ativo' => false,
        'nivel' => 'normal',
        'mensagem' => '',
        'critico_excedido' => false,
        'urgente_excedido' => false,
        'total_excedido' => false,
        'percentual_critico' => 0,
        'percentual_urgente' => 0,
        'percentual_total' => 0
    ];
    
    // Calcular percentuais em relação aos limites
    $alerta['percentual_critico'] = (LIMITE_OS_ATRASADAS_CRITICO > 0) 
        ? round(($contagem_atraso['critico'] / LIMITE_OS_ATRASADAS_CRITICO) * 100, 0) 
        : 0;
    
    $alerta['percentual_urgente'] = (LIMITE_OS_ATRASADAS_URGENTE > 0) 
        ? round(($contagem_atraso['urgente'] / LIMITE_OS_ATRASADAS_URGENTE) * 100, 0) 
        : 0;
    
    $alerta['percentual_total'] = (LIMITE_OS_ATRASADAS_TOTAL > 0) 
        ? round(($contagem_atraso['total'] / LIMITE_OS_ATRASADAS_TOTAL) * 100, 0) 
        : 0;
    
    // Verificar se algum limite foi excedido
    if ($contagem_atraso['critico'] >= LIMITE_OS_ATRASADAS_CRITICO && LIMITE_OS_ATRASADAS_CRITICO > 0) {
        $alerta['critico_excedido'] = true;
        $alerta['ativo'] = true;
        $alerta['nivel'] = 'critico';
        $alerta['mensagem'] = "🚨 ALERTA CRÍTICO: {$contagem_atraso['critico']} O.S. em situação crítica (7+ dias de atraso)!";
    }
    
    if ($contagem_atraso['urgente'] >= LIMITE_OS_ATRASADAS_URGENTE && LIMITE_OS_ATRASADAS_URGENTE > 0) {
        $alerta['urgente_excedido'] = true;
        $alerta['ativo'] = true;
        if ($alerta['nivel'] !== 'critico') {
            $alerta['nivel'] = 'urgente';
            $alerta['mensagem'] = "⚠️ ALERTA URGENTE: {$contagem_atraso['urgente']} O.S. com atraso urgente (3-6 dias)!";
        }
    }
    
    if ($contagem_atraso['total'] >= LIMITE_OS_ATRASADAS_TOTAL && LIMITE_OS_ATRASADAS_TOTAL > 0) {
        $alerta['total_excedido'] = true;
        $alerta['ativo'] = true;
        if ($alerta['nivel'] !== 'critico' && $alerta['nivel'] !== 'urgente') {
            $alerta['nivel'] = 'atencao';
            $alerta['mensagem'] = "⚠️ ATENÇÃO: Total de {$contagem_atraso['total']} O.S. atrasadas. Situação requer revisão!";
        }
    }
    
    return $alerta;
}

/**
 * Retorna a classe CSS para o alerta baseado no nível
 */
function obterClasseAlerta($nivel) {
    $classes = [
        'critico' => 'alert-critico-limite',
        'urgente' => 'alert-urgente-limite',
        'atencao' => 'alert-atencao-limite',
        'normal' => 'alert-normal'
    ];
    
    return $classes[$nivel] ?? 'alert-normal';
}

/**
 * Retorna o ícone para o alerta baseado no nível
 */
function obterIconeAlerta($nivel) {
    $icones = [
        'critico' => 'fas fa-exclamation-circle',
        'urgente' => 'fas fa-exclamation-triangle',
        'atencao' => 'fas fa-info-circle',
        'normal' => 'fas fa-check-circle'
    ];
    
    return $icones[$nivel] ?? 'fas fa-info-circle';
}

/**
 * Registra o alerta no banco de dados (opcional, para histórico)
 * Pode ser usado para análise posterior ou integração com sistemas de notificação
 */
function registrarAlerta($db, $tipo, $nivel, $contagem) {
    try {
        $stmt = $db->prepare("
            INSERT INTO alertas_sistema (tipo, nivel, dados, data_criacao, lido)
            VALUES (?, ?, ?, NOW(), 0)
        ");
        
        $dados_json = json_encode($contagem);
        $stmt->execute([$tipo, $nivel, $dados_json]);
        
        return true;
    } catch (Exception $e) {
        // Silenciosamente falhar se a tabela não existir
        return false;
    }
}
?>
