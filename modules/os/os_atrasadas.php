<?php
/**
 * Função para obter O.S. atrasadas
 * Identifica ordens de serviço que ultrapassaram a data de término
 * 
 * @param PDO $db Conexão com banco de dados
 * @param int|null $usuario_id ID do usuário (opcional, para filtrar por vendedor)
 * @return array Array com O.S. atrasadas agrupadas por nível de atraso
 */
function getOSAtrasadas($db, $usuario_id = null) {
    try {
        $where = "WHERE os.status NOT IN ('concluida', 'cancelada') AND os.data_termino IS NOT NULL AND DATE(os.data_termino) < CURDATE()";
        $params = [];
        
        if ($usuario_id !== null) {
            $where .= " AND v.usuario_id = ?";
            $params[] = $usuario_id;
        }
        
        $sql = "
            SELECT 
                os.*,
                c.razao_social,
                COALESCE(v.numero, 'Independente') as venda_numero,
                DATEDIFF(CURDATE(), DATE(os.data_termino)) as dias_atraso,
                CASE 
                    WHEN DATEDIFF(CURDATE(), DATE(os.data_termino)) >= 7 THEN 'critico'
                    WHEN DATEDIFF(CURDATE(), DATE(os.data_termino)) >= 3 THEN 'urgente'
                    ELSE 'atrasado'
                END as nivel_atraso
            FROM ordens_servico os
            INNER JOIN clientes c ON os.cliente_id = c.id
            LEFT JOIN vendas v ON os.venda_id = v.id
            $where
            ORDER BY 
                DATEDIFF(CURDATE(), DATE(os.data_termino)) DESC,
                os.prioridade DESC
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $resultado = $stmt->fetchAll();
        
        // Agrupar por nível de atraso
        $os_agrupadas = [
            'critico' => [],
            'urgente' => [],
            'atrasado' => []
        ];
        
        foreach ($resultado as $os) {
            $nivel = $os['nivel_atraso'];
            if (isset($os_agrupadas[$nivel])) {
                $os_agrupadas[$nivel][] = $os;
            }
        }
        
        return $os_agrupadas;
        
    } catch (Exception $e) {
        error_log("Erro ao obter O.S. atrasadas: " . $e->getMessage());
        return [
            'critico' => [],
            'urgente' => [],
            'atrasado' => []
        ];
    }
}

/**
 * Retorna a cor e ícone para o nível de atraso
 * 
 * @param string $nivel_atraso Nível de atraso (critico, urgente, atrasado)
 * @return array Array com 'cor' e 'icone'
 */
function getNivelAtrasoCor($nivel_atraso) {
    $niveis = [
        'critico' => [
            'cor' => '#dc3545',
            'icone' => 'fa-exclamation-circle',
            'nome' => 'Crítico'
        ],
        'urgente' => [
            'cor' => '#ff6b6b',
            'icone' => 'fa-exclamation-triangle',
            'nome' => 'Urgente'
        ],
        'atrasado' => [
            'cor' => '#ffc107',
            'icone' => 'fa-clock',
            'nome' => 'Atrasado'
        ]
    ];
    
    return $niveis[$nivel_atraso] ?? [
        'cor' => '#6c757d',
        'icone' => 'fa-info-circle',
        'nome' => 'Desconhecido'
    ];
}

/**
 * Retorna a descrição do atraso em dias
 * 
 * @param int $dias_atraso Número de dias em atraso
 * @return string Descrição formatada
 */
function getDescricaoAtraso($dias_atraso) {
    if ($dias_atraso == 1) {
        return "1 dia em atraso";
    } elseif ($dias_atraso < 7) {
        return "$dias_atraso dias em atraso";
    } elseif ($dias_atraso < 30) {
        $semanas = floor($dias_atraso / 7);
        return "$semanas semana(s) em atraso";
    } else {
        $meses = floor($dias_atraso / 30);
        return "$meses mês(es) em atraso";
    }
}

/**
 * Conta o total de O.S. atrasadas por nível
 * 
 * @param array $os_agrupadas Array retornado por getOSAtrasadas()
 * @return array Array com contagem por nível
 */
function contarOSAtrasadas($os_agrupadas) {
    return [
        'critico' => count($os_agrupadas['critico'] ?? []),
        'urgente' => count($os_agrupadas['urgente'] ?? []),
        'atrasado' => count($os_agrupadas['atrasado'] ?? []),
        'total' => count($os_agrupadas['critico'] ?? []) + 
                   count($os_agrupadas['urgente'] ?? []) + 
                   count($os_agrupadas['atrasado'] ?? [])
    ];
}
?>

