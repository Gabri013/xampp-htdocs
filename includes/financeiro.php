<?php
/**
 * Helpers do módulo financeiro
 */

function ensureFinanceiroSchema(PDO $db) {
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;

    if (!shouldRunSchemaSync('financeiro', 86400)) {
        return;
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS tipos_caixa (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(100) NOT NULL UNIQUE,
            categoria ENUM('dinheiro','pix','cartao_credito','cartao_debito','boleto','transferencia','outro') NOT NULL DEFAULT 'outro',
            taxa_padrao_antecipacao DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS contas_receber (
            id INT AUTO_INCREMENT PRIMARY KEY,
            venda_id INT NOT NULL,
            cliente_id INT NOT NULL,
            tipo_caixa_id INT NULL,
            parcela_numero INT NOT NULL DEFAULT 1,
            total_parcelas INT NOT NULL DEFAULT 1,
            valor_bruto DECIMAL(15,2) NOT NULL,
            taxa_antecipacao_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            valor_liquido DECIMAL(15,2) NOT NULL,
            valor_recebido DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            data_vencimento DATE NOT NULL,
            data_pagamento DATETIME NULL,
            forma_pagamento VARCHAR(50) NOT NULL,
            status ENUM('PENDENTE','PAGO','ATRASADO','CANCELADO') NOT NULL DEFAULT 'PENDENTE',
            observacoes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_cr_venda (venda_id),
            INDEX idx_cr_cliente (cliente_id),
            INDEX idx_cr_status (status),
            FOREIGN KEY (venda_id) REFERENCES vendas(id) ON DELETE CASCADE,
            FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT,
            FOREIGN KEY (tipo_caixa_id) REFERENCES tipos_caixa(id) ON DELETE SET NULL
        ) ENGINE=InnoDB
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS pagamentos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            conta_receber_id INT NOT NULL,
            usuario_id INT NOT NULL,
            valor_pago DECIMAL(15,2) NOT NULL,
            data_pagamento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            forma_pagamento VARCHAR(50) NOT NULL,
            observacao TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_pg_conta (conta_receber_id),
            FOREIGN KEY (conta_receber_id) REFERENCES contas_receber(id) ON DELETE CASCADE,
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS logs_sistema (
            id INT AUTO_INCREMENT PRIMARY KEY,
            entidade VARCHAR(50) NOT NULL,
            entidade_id INT NOT NULL,
            acao VARCHAR(50) NOT NULL,
            detalhe TEXT NULL,
            usuario_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_logs_entidade (entidade, entidade_id)
        ) ENGINE=InnoDB
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS centro_custo (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(120) NOT NULL UNIQUE,
            descricao VARCHAR(255) NULL,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS contas_pagar (
            id INT AUTO_INCREMENT PRIMARY KEY,
            descricao VARCHAR(255) NOT NULL,
            fornecedor VARCHAR(150) NULL,
            centro_custo_id INT NULL,
            valor DECIMAL(15,2) NOT NULL,
            data_vencimento DATE NOT NULL,
            data_pagamento DATETIME NULL,
            status ENUM('PENDENTE','PAGO','ATRASADO','CANCELADO') NOT NULL DEFAULT 'PENDENTE',
            observacoes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (centro_custo_id) REFERENCES centro_custo(id) ON DELETE SET NULL,
            INDEX idx_cp_status (status)
        ) ENGINE=InnoDB
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS fluxo_caixa (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tipo ENUM('ENTRADA','SAIDA') NOT NULL,
            referencia_tipo VARCHAR(40) NOT NULL,
            referencia_id INT NOT NULL,
            descricao VARCHAR(255) NOT NULL,
            valor DECIMAL(15,2) NOT NULL,
            data_movimento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_fc_tipo_ref (referencia_tipo, referencia_id)
        ) ENGINE=InnoDB
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS conciliacao (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pagamento_id INT NULL,
            origem VARCHAR(60) NOT NULL,
            referencia_externa VARCHAR(120) NOT NULL,
            valor DECIMAL(15,2) NOT NULL,
            data_evento DATETIME NOT NULL,
            status ENUM('PENDENTE','CONCILIADO','DIVERGENTE') NOT NULL DEFAULT 'PENDENTE',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (pagamento_id) REFERENCES pagamentos(id) ON DELETE SET NULL
        ) ENGINE=InnoDB
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS estrutura_produto (
            id INT AUTO_INCREMENT PRIMARY KEY,
            produto_id INT NOT NULL,
            versao VARCHAR(30) NOT NULL DEFAULT 'v1',
            observacoes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS componentes_produto (
            id INT AUTO_INCREMENT PRIMARY KEY,
            estrutura_id INT NOT NULL,
            componente_nome VARCHAR(180) NOT NULL,
            quantidade DECIMAL(12,2) NOT NULL DEFAULT 1,
            unidade VARCHAR(20) NOT NULL DEFAULT 'un',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (estrutura_id) REFERENCES estrutura_produto(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS tempo_producao (
            id INT AUTO_INCREMENT PRIMARY KEY,
            produto_id INT NOT NULL,
            etapa VARCHAR(80) NOT NULL,
            minutos_estimados INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS processos_produtivos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            os_id INT NOT NULL,
            etapa VARCHAR(80) NOT NULL,
            status ENUM('A_FAZER','EM_PRODUCAO','AGUARDANDO_PECA','FINALIZADO') NOT NULL DEFAULT 'A_FAZER',
            observacao VARCHAR(255) NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS notificacoes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            tipo VARCHAR(50) NOT NULL,
            titulo VARCHAR(120) NOT NULL,
            mensagem TEXT NOT NULL,
            lida TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            INDEX idx_notif_usuario_lida (usuario_id, lida)
        ) ENGINE=InnoDB
    ");

    $colunas = $db->query("SHOW COLUMNS FROM vendas")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('caixa_tipo_id', $colunas, true)) {
        $db->exec("ALTER TABLE vendas ADD COLUMN caixa_tipo_id INT NULL AFTER forma_pagamento");
    }
    if (!in_array('num_parcelas', $colunas, true)) {
        $db->exec("ALTER TABLE vendas ADD COLUMN num_parcelas INT NOT NULL DEFAULT 1 AFTER caixa_tipo_id");
    }
    if (!in_array('taxa_antecipacao_percent', $colunas, true)) {
        $db->exec("ALTER TABLE vendas ADD COLUMN taxa_antecipacao_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER num_parcelas");
    }
    if (!in_array('faturado_em', $colunas, true)) {
        $db->exec("ALTER TABLE vendas ADD COLUMN faturado_em DATETIME NULL AFTER taxa_antecipacao_percent");
    }
    if (!in_array('faturado_por', $colunas, true)) {
        $db->exec("ALTER TABLE vendas ADD COLUMN faturado_por INT NULL AFTER faturado_em");
    }
    if (!in_array('data_recebimento_prevista', $colunas, true)) {
        $db->exec("ALTER TABLE vendas ADD COLUMN data_recebimento_prevista DATE NULL AFTER faturado_por");
    }
    if (!in_array('tipo_entrada', $colunas, true)) {
        $db->exec("ALTER TABLE vendas ADD COLUMN tipo_entrada VARCHAR(50) NULL AFTER data_recebimento_prevista");
    }
    if (!in_array('valor_entrada', $colunas, true)) {
        $db->exec("ALTER TABLE vendas ADD COLUMN valor_entrada DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER tipo_entrada");
    }
    if (!in_array('data_entrada', $colunas, true)) {
        $db->exec("ALTER TABLE vendas ADD COLUMN data_entrada DATE NULL AFTER valor_entrada");
    }
    if (!in_array('desconto_financeiro_tipo', $colunas, true)) {
        $db->exec("ALTER TABLE vendas ADD COLUMN desconto_financeiro_tipo VARCHAR(20) NULL AFTER data_entrada");
    }
    if (!in_array('desconto_financeiro_valor', $colunas, true)) {
        $db->exec("ALTER TABLE vendas ADD COLUMN desconto_financeiro_valor DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER desconto_financeiro_tipo");
    }
    if (!in_array('juros_percent', $colunas, true)) {
        $db->exec("ALTER TABLE vendas ADD COLUMN juros_percent DECIMAL(7,2) NOT NULL DEFAULT 0.00 AFTER desconto_financeiro_valor");
    }
    if (!in_array('taxa_fixa', $colunas, true)) {
        $db->exec("ALTER TABLE vendas ADD COLUMN taxa_fixa DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER juros_percent");
    }
    if (!in_array('documento_financeiro', $colunas, true)) {
        $db->exec("ALTER TABLE vendas ADD COLUMN documento_financeiro VARCHAR(80) NULL AFTER taxa_fixa");
    }
    if (!in_array('numero_documento_financeiro', $colunas, true)) {
        $db->exec("ALTER TABLE vendas ADD COLUMN numero_documento_financeiro VARCHAR(80) NULL AFTER documento_financeiro");
    }
    if (!in_array('palavra_chave_financeira', $colunas, true)) {
        $db->exec("ALTER TABLE vendas ADD COLUMN palavra_chave_financeira VARCHAR(120) NULL AFTER numero_documento_financeiro");
    }
    if (!in_array('observacoes_venda', $colunas, true)) {
        $db->exec("ALTER TABLE vendas ADD COLUMN observacoes_venda TEXT NULL AFTER observacoes");
    }

    $colunasContasPagar = $db->query("SHOW COLUMNS FROM contas_pagar")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('observacoes', $colunasContasPagar, true)) {
        $db->exec("ALTER TABLE contas_pagar ADD COLUMN observacoes TEXT NULL AFTER status");
    }

    $qtd = (int) $db->query("SELECT COUNT(*) FROM tipos_caixa")->fetchColumn();
    if ($qtd === 0) {
        $db->exec("
            INSERT INTO tipos_caixa (nome, categoria, taxa_padrao_antecipacao, ativo) VALUES
            ('Dinheiro', 'dinheiro', 0.00, 1),
            ('PIX', 'pix', 0.00, 1),
            ('Cartao de Credito', 'cartao_credito', 0.00, 1),
            ('Cartao de Debito', 'cartao_debito', 0.00, 1),
            ('Boleto', 'boleto', 0.00, 1)
        ");
    }
}

function mapFormaPagamentoByCategoria($categoria) {
    if (in_array($categoria, ['dinheiro', 'pix', 'cartao_debito', 'transferencia', 'outro'], true)) {
        return 'avista';
    }
    if ($categoria === 'cartao_credito') {
        return 'cartao';
    }
    if ($categoria === 'boleto') {
        return 'boleto';
    }
    return 'avista';
}

function atualizarStatusFinanceiro(PDO $db) {
    $db->exec("UPDATE contas_receber SET status='ATRASADO' WHERE status='PENDENTE' AND data_vencimento < CURDATE()");
    $db->exec("UPDATE contas_pagar SET status='ATRASADO' WHERE status='PENDENTE' AND data_vencimento < CURDATE()");
}

function calcularDataRecebimentoParcela(array $venda, $parcelaNumero = 1) {
    $dataBase = $venda['data_venda'] ?: date('Y-m-d');

    if (($venda['forma_pagamento'] ?? '') === 'boleto' && !empty($venda['data_recebimento_prevista'])) {
        $dias = max(0, ((int) $parcelaNumero) - 1) * 30;
        return date('Y-m-d', strtotime($venda['data_recebimento_prevista'] . " +{$dias} days"));
    }

    $numParcelas = max(1, (int) ($venda['num_parcelas'] ?? 1));
    $dias = ($numParcelas > 1) ? (30 * (int) $parcelaNumero) : 0;
    return date('Y-m-d', strtotime($dataBase . " +{$dias} days"));
}

function calcularDescontoFinanceiro($base, $tipo, $valor) {
    $base = max(0, (float) $base);
    $valor = max(0, (float) $valor);

    if ($tipo === 'percentual') {
        return round($base * ($valor / 100), 2);
    }

    return min($base, round($valor, 2));
}

function calcularResumoFinanceiroVenda(array $venda) {
    $valorBase = max(0, (float) ($venda['valor_total'] ?? 0));
    $descontoFinanceiro = calcularDescontoFinanceiro(
        $valorBase,
        $venda['desconto_financeiro_tipo'] ?? '',
        $venda['desconto_financeiro_valor'] ?? 0
    );
    $subtotal = max(0, round($valorBase - $descontoFinanceiro, 2));
    $jurosPercent = max(0, (float) ($venda['juros_percent'] ?? 0));
    $jurosValor = round($subtotal * ($jurosPercent / 100), 2);
    $taxaFixa = max(0, round((float) ($venda['taxa_fixa'] ?? 0), 2));
    $totalFinanceiro = max(0, round($subtotal + $jurosValor + $taxaFixa, 2));
    $valorEntrada = min($totalFinanceiro, max(0, round((float) ($venda['valor_entrada'] ?? 0), 2)));
    $saldoReceber = max(0, round($totalFinanceiro - $valorEntrada, 2));

    return [
        'valor_base' => $valorBase,
        'desconto_financeiro' => $descontoFinanceiro,
        'subtotal' => $subtotal,
        'juros_percent' => $jurosPercent,
        'juros_valor' => $jurosValor,
        'taxa_fixa' => $taxaFixa,
        'total_financeiro' => $totalFinanceiro,
        'valor_entrada' => $valorEntrada,
        'saldo_receber' => $saldoReceber
    ];
}

function gerarContasReceberDaVenda(PDO $db, array $venda, array $tipo_caixa, $usuario_id) {
    $stmt_count = $db->prepare("SELECT COUNT(*) FROM contas_receber WHERE venda_id = ?");
    $stmt_count->execute([$venda['id']]);
    if ((int) $stmt_count->fetchColumn() > 0) {
        throw new Exception('Contas a receber já foram geradas para esta venda.');
    }

    $resumo = calcularResumoFinanceiroVenda($venda);
    $valor_total = (float) $resumo['saldo_receber'];
    $entrada = (float) $resumo['valor_entrada'];
    $num_parcelas = max(1, (int) $venda['num_parcelas']);
    $taxa = max(0, (float) $venda['taxa_antecipacao_percent']);

    $stmt = $db->prepare("
        INSERT INTO contas_receber
        (venda_id, cliente_id, tipo_caixa_id, parcela_numero, total_parcelas, valor_bruto, taxa_antecipacao_percent, valor_liquido, valor_recebido, data_vencimento, data_pagamento, forma_pagamento, status, observacoes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $totalParcelasVisuais = $num_parcelas + ($entrada > 0 ? 1 : 0);
    $indiceParcela = 1;

    if ($entrada > 0) {
        $stmt->execute([
            $venda['id'],
            $venda['cliente_id'],
            $venda['caixa_tipo_id'],
            $indiceParcela,
            $totalParcelasVisuais,
            $entrada,
            0,
            $entrada,
            $entrada,
            $venda['data_entrada'] ?: ($venda['data_venda'] ?: date('Y-m-d')),
            ($venda['data_entrada'] ?: ($venda['data_venda'] ?: date('Y-m-d'))) . ' 00:00:00',
            $venda['tipo_entrada'] ?: $venda['forma_pagamento'],
            'PAGO',
            'Entrada recebida no ato do faturamento.'
        ]);
        $indiceParcela++;
    }

    if ($valor_total > 0) {
        $base = round($valor_total / $num_parcelas, 2);
        $saldo = $valor_total;

        for ($i = 1; $i <= $num_parcelas; $i++) {
            $valor_parcela = ($i === $num_parcelas) ? round($saldo, 2) : $base;
            $saldo -= $valor_parcela;

            $valor_liquido = round($valor_parcela * (1 - ($taxa / 100)), 2);
            $data_vencimento = calcularDataRecebimentoParcela($venda, $i);

            $stmt->execute([
                $venda['id'],
                $venda['cliente_id'],
                $venda['caixa_tipo_id'],
                $indiceParcela,
                $totalParcelasVisuais,
                $valor_parcela,
                $taxa,
                $valor_liquido,
                0,
                $data_vencimento,
                null,
                $venda['forma_pagamento'],
                'PENDENTE',
                null
            ]);
            $indiceParcela++;
        }
    }

    $stmt_log = $db->prepare("INSERT INTO logs_sistema (entidade, entidade_id, acao, detalhe, usuario_id) VALUES ('venda', ?, 'gerar_contas_receber', ?, ?)");
    $detalhe = "Geradas {$totalParcelasVisuais} movimentação(ões) financeiras para o caixa {$tipo_caixa['nome']}.";
    $stmt_log->execute([$venda['id'], $detalhe, $usuario_id]);
}

function faturarVenda(PDO $db, $venda_id, $usuario_id) {
    $stmt = $db->prepare("
        SELECT v.*, tc.nome as caixa_nome, tc.categoria as caixa_categoria
        FROM vendas v
        LEFT JOIN tipos_caixa tc ON tc.id = v.caixa_tipo_id
        WHERE v.id = ?
    ");
    $stmt->execute([$venda_id]);
    $venda = $stmt->fetch();
    if (!$venda) {
        throw new Exception('Venda não encontrada.');
    }
    if ($venda['status'] === 'cancelada') {
        throw new Exception('Venda cancelada não pode ser faturada.');
    }
    if (!empty($venda['faturado_em'])) {
        throw new Exception('Venda já está faturada.');
    }
    if (empty($venda['caixa_tipo_id'])) {
        throw new Exception('Venda sem tipo de caixa definido.');
    }

    gerarContasReceberDaVenda($db, [
        'id' => $venda['id'],
        'cliente_id' => $venda['cliente_id'],
        'caixa_tipo_id' => $venda['caixa_tipo_id'],
        'valor_total' => $venda['valor_total'],
        'data_venda' => $venda['data_venda'],
        'num_parcelas' => $venda['num_parcelas'],
        'taxa_antecipacao_percent' => $venda['taxa_antecipacao_percent'],
        'forma_pagamento' => $venda['forma_pagamento'],
        'data_recebimento_prevista' => $venda['data_recebimento_prevista'],
        'tipo_entrada' => $venda['tipo_entrada'],
        'valor_entrada' => $venda['valor_entrada'],
        'data_entrada' => $venda['data_entrada'],
        'desconto_financeiro_tipo' => $venda['desconto_financeiro_tipo'],
        'desconto_financeiro_valor' => $venda['desconto_financeiro_valor'],
        'juros_percent' => $venda['juros_percent'],
        'taxa_fixa' => $venda['taxa_fixa']
    ], [
        'nome' => $venda['caixa_nome'],
        'categoria' => $venda['caixa_categoria']
    ], $usuario_id);

    $stmt = $db->prepare("UPDATE vendas SET faturado_em = NOW(), faturado_por = ? WHERE id = ?");
    $stmt->execute([$usuario_id, $venda_id]);

    $stmt_log = $db->prepare("INSERT INTO logs_sistema (entidade, entidade_id, acao, detalhe, usuario_id) VALUES ('venda', ?, 'faturar', 'Venda faturada e financeiro gerado.', ?)");
    $stmt_log->execute([$venda_id, $usuario_id]);
}

function cancelarContasReceberPorVenda(PDO $db, $venda_id, $usuario_id, $motivo = '') {
    $stmt = $db->prepare("UPDATE contas_receber SET status = 'CANCELADO', observacoes = CONCAT(COALESCE(observacoes,''), ?) WHERE venda_id = ? AND status IN ('PENDENTE', 'ATRASADO')");
    $obs = $motivo ? "\nCancelamento automático: {$motivo}" : "\nCancelamento automático por cancelamento da venda.";
    $stmt->execute([$obs, $venda_id]);

    $stmt_log = $db->prepare("INSERT INTO logs_sistema (entidade, entidade_id, acao, detalhe, usuario_id) VALUES ('venda', ?, 'cancelar_contas', ?, ?)");
    $stmt_log->execute([$venda_id, $obs, $usuario_id]);
}
