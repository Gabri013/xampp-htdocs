<?php
/**
 * Helpers do modulo de engenharia de produto.
 */

function ensureEngenhariaSchema(PDO $db): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;

    if (!shouldRunSchemaSync('engenharia_v3', 86400)) {
        return;
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS insumos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            codigo VARCHAR(60) NULL,
            nome VARCHAR(180) NOT NULL,
            fornecedor VARCHAR(180) NULL,
            unidade VARCHAR(20) NOT NULL DEFAULT 'un',
            custo_unitario DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
            observacoes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_insumo_nome (nome),
            INDEX idx_insumo_fornecedor (fornecedor)
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
            insumo_id INT NULL,
            componente_nome VARCHAR(180) NOT NULL,
            quantidade DECIMAL(12,4) NOT NULL DEFAULT 1,
            unidade VARCHAR(20) NOT NULL DEFAULT 'un',
            custo_unitario DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (estrutura_id) REFERENCES estrutura_produto(id) ON DELETE CASCADE,
            FOREIGN KEY (insumo_id) REFERENCES insumos(id) ON DELETE SET NULL
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
        CREATE TABLE IF NOT EXISTS os_etapas_producao (
            id INT AUTO_INCREMENT PRIMARY KEY,
            os_id INT NOT NULL,
            etapa ENUM('engenharia', 'programacao', 'corte', 'dobra', 'tubo', 'solda', 'mobiliario', 'coccao', 'refrigeracao', 'acabamento', 'montagem', 'embalagem', 'finalizacao') NOT NULL,
            status ENUM('pendente', 'em_andamento', 'concluida') DEFAULT 'pendente',
            data_inicio DATETIME NULL,
            data_fim DATETIME NULL,
            tempo_total_segundos INT DEFAULT 0,
            usuario_id INT NULL,
            FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE,
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
            INDEX idx_os_etapa (os_id, etapa)
        ) ENGINE=InnoDB
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS os_itens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            os_id INT NOT NULL,
            produto_id INT NULL,
            descricao_manual TEXT NULL,
            quantidade DECIMAL(12,2) NOT NULL DEFAULT 1.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE,
            FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE SET NULL,
            INDEX idx_os_item (os_id),
            INDEX idx_os_item_produto (produto_id)
        ) ENGINE=InnoDB
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS os_itens_arquivos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            os_id INT NOT NULL,
            os_item_id INT NOT NULL,
            tipo VARCHAR(50) NOT NULL DEFAULT 'projeto_pdf',
            nome_original VARCHAR(255) NOT NULL,
            nome_arquivo VARCHAR(255) NOT NULL,
            usuario_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE,
            FOREIGN KEY (os_item_id) REFERENCES os_itens(id) ON DELETE CASCADE,
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
            INDEX idx_os_item_arq_item (os_item_id),
            INDEX idx_os_item_arq_os (os_id)
        ) ENGINE=InnoDB
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS os_arquivos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            os_id INT NOT NULL,
            tipo ENUM('venda','projeto','projeto_foto','projeto_pdf','projeto_dxf','projeto_3d') NOT NULL DEFAULT 'projeto',
            nome_original VARCHAR(255) NOT NULL,
            nome_arquivo VARCHAR(255) NOT NULL,
            usuario_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE,
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
            INDEX idx_os_arquivo_os (os_id)
        ) ENGINE=InnoDB
    ");

    $colunasOS = $db->query("SHOW COLUMNS FROM ordens_servico")->fetchAll(PDO::FETCH_COLUMN);
    
    // Add etapa_atual primeiro (depois de status)
    if (!in_array('etapa_atual', $colunasOS, true)) {
        $db->exec("ALTER TABLE ordens_servico ADD COLUMN etapa_atual ENUM('autorizacao', 'engenharia', 'programacao', 'corte', 'dobra', 'tubo', 'solda', 'mobiliario', 'coccao', 'refrigeracao', 'acabamento', 'montagem', 'embalagem', 'finalizacao', 'concluida') DEFAULT 'autorizacao' AFTER status");
    }
    
    // Add tipo (depois de status)
    if (!in_array('tipo', $colunasOS, true)) {
        $db->exec("ALTER TABLE ordens_servico ADD COLUMN tipo ENUM('projeto','manutencao','padrao') NOT NULL DEFAULT 'projeto' AFTER etapa_atual");
    }
    
    // Add alteracoes_projeto
    if (!in_array('alteracoes_projeto', $colunasOS, true)) {
        $db->exec("ALTER TABLE ordens_servico ADD COLUMN alteracoes_projeto TEXT NULL AFTER tipo");
    }

    // O workflow usa o status 'proposta' (desenho aguardando aprovação);
    // garante que o ENUM do banco o aceite, senão o MySQL grava vazio.
    $tipoStatusOS = (string) ($db->query("SHOW COLUMNS FROM ordens_servico LIKE 'status'")->fetch(PDO::FETCH_ASSOC)['Type'] ?? '');
    if ($tipoStatusOS !== '' && stripos($tipoStatusOS, 'proposta') === false) {
        $db->exec("
            ALTER TABLE ordens_servico
            MODIFY COLUMN status ENUM('pendente', 'em_projeto', 'proposta', 'em_revisao', 'em_producao', 'concluida', 'cancelada')
            DEFAULT 'pendente'
        ");
    }

    $tipoEtapaAtual = (string) ($db->query("SHOW COLUMNS FROM ordens_servico LIKE 'etapa_atual'")->fetch(PDO::FETCH_ASSOC)['Type'] ?? '');
    if ($tipoEtapaAtual !== '' && stripos($tipoEtapaAtual, 'embalagem') === false) {
        $db->exec("
            ALTER TABLE ordens_servico
            MODIFY COLUMN etapa_atual ENUM('autorizacao', 'engenharia', 'programacao', 'corte', 'dobra', 'tubo', 'solda', 'mobiliario', 'coccao', 'refrigeracao', 'acabamento', 'montagem', 'embalagem', 'finalizacao', 'concluida')
            DEFAULT 'autorizacao'
        ");
    }

    $tipoEtapaProducao = (string) ($db->query("SHOW COLUMNS FROM os_etapas_producao LIKE 'etapa'")->fetch(PDO::FETCH_ASSOC)['Type'] ?? '');
    if ($tipoEtapaProducao !== '' && stripos($tipoEtapaProducao, 'embalagem') === false) {
        $db->exec("
            ALTER TABLE os_etapas_producao
            MODIFY COLUMN etapa ENUM('engenharia', 'programacao', 'corte', 'dobra', 'tubo', 'solda', 'mobiliario', 'coccao', 'refrigeracao', 'acabamento', 'montagem', 'embalagem', 'finalizacao') NOT NULL
        ");
    }

    $tipoArquivoProjeto = (string) ($db->query("SHOW COLUMNS FROM os_arquivos LIKE 'tipo'")->fetch(PDO::FETCH_ASSOC)['Type'] ?? '');
    if ($tipoArquivoProjeto !== '' && stripos($tipoArquivoProjeto, 'projeto_3d') === false) {
        $db->exec("
            ALTER TABLE os_arquivos
            MODIFY COLUMN tipo ENUM('venda','projeto','projeto_foto','projeto_pdf','projeto_dxf','projeto_3d') NOT NULL DEFAULT 'projeto'
        ");
    }

    $colunasProduto = $db->query("SHOW COLUMNS FROM produtos")->fetchAll(PDO::FETCH_COLUMN);
    $alteracoesProduto = [
        'unidade_medida' => "ALTER TABLE produtos ADD COLUMN unidade_medida VARCHAR(20) NOT NULL DEFAULT 'un' AFTER descricao",
        'categoria_id' => "ALTER TABLE produtos ADD COLUMN categoria_id INT NULL AFTER unidade_medida",
        'medidas_preco' => "ALTER TABLE produtos ADD COLUMN medidas_preco VARCHAR(120) NULL AFTER categoria_id",
        'observacoes_preco' => "ALTER TABLE produtos ADD COLUMN observacoes_preco TEXT NULL AFTER medidas_preco",
        'percentual_embalagem' => "ALTER TABLE produtos ADD COLUMN percentual_embalagem DECIMAL(8,2) NOT NULL DEFAULT 0.00 AFTER observacoes_preco",
        'preco_embalagem' => "ALTER TABLE produtos ADD COLUMN preco_embalagem DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER valor",
        'custo_mao_obra' => "ALTER TABLE produtos ADD COLUMN custo_mao_obra DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER valor",
        'custo_indireto' => "ALTER TABLE produtos ADD COLUMN custo_indireto DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER custo_mao_obra",
        'margem_lucro' => "ALTER TABLE produtos ADD COLUMN margem_lucro DECIMAL(8,2) NOT NULL DEFAULT 30.00 AFTER custo_indireto",
        'custo_total' => "ALTER TABLE produtos ADD COLUMN custo_total DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER margem_lucro",
        'preco_sugerido' => "ALTER TABLE produtos ADD COLUMN preco_sugerido DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER custo_total",
    ];

    foreach ($alteracoesProduto as $coluna => $sql) {
        if (!in_array($coluna, $colunasProduto, true)) {
            $db->exec($sql);
        }
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS produto_categorias (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(120) NOT NULL,
            descricao TEXT NULL,
            status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_produto_categoria_nome (nome)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $foreignKeysCategoriaProduto = $db->query("
        SELECT CONSTRAINT_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'produtos'
          AND COLUMN_NAME = 'categoria_id'
          AND REFERENCED_TABLE_NAME = 'produto_categorias'
    ")->fetchAll(PDO::FETCH_COLUMN);

    if (empty($foreignKeysCategoriaProduto) && in_array('categoria_id', $db->query("SHOW COLUMNS FROM produtos")->fetchAll(PDO::FETCH_COLUMN), true)) {
        $db->exec("ALTER TABLE produtos ADD CONSTRAINT fk_produtos_categoria FOREIGN KEY (categoria_id) REFERENCES produto_categorias(id) ON DELETE SET NULL");
    }

    $colunasComponente = $db->query("SHOW COLUMNS FROM componentes_produto")->fetchAll(PDO::FETCH_COLUMN);
    $alteracoesComponente = [
        'insumo_id' => "ALTER TABLE componentes_produto ADD COLUMN insumo_id INT NULL AFTER estrutura_id",
        'custo_unitario' => "ALTER TABLE componentes_produto ADD COLUMN custo_unitario DECIMAL(15,4) NOT NULL DEFAULT 0.0000 AFTER unidade",
    ];

    foreach ($alteracoesComponente as $coluna => $sql) {
        if (!in_array($coluna, $colunasComponente, true)) {
            $db->exec($sql);
        }
    }

    $foreignKeysComponentes = $db->query("
        SELECT CONSTRAINT_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'componentes_produto'
          AND COLUMN_NAME = 'insumo_id'
          AND REFERENCED_TABLE_NAME = 'insumos'
    ")->fetchAll(PDO::FETCH_COLUMN);

    if (empty($foreignKeysComponentes) && in_array('insumo_id', $db->query("SHOW COLUMNS FROM componentes_produto")->fetchAll(PDO::FETCH_COLUMN), true)) {
        $db->exec("ALTER TABLE componentes_produto ADD CONSTRAINT fk_componentes_insumo FOREIGN KEY (insumo_id) REFERENCES insumos(id) ON DELETE SET NULL");
    }

    ensureIndexIfMissing($db, 'ordens_servico', 'idx_os_status_etapa_entrega', "CREATE INDEX idx_os_status_etapa_entrega ON ordens_servico (status, etapa_atual, data_termino)");
    ensureIndexIfMissing($db, 'ordens_servico', 'idx_os_venda', "CREATE INDEX idx_os_venda ON ordens_servico (venda_id)");
    ensureIndexIfMissing($db, 'ordens_servico', 'idx_os_cliente', "CREATE INDEX idx_os_cliente ON ordens_servico (cliente_id)");
    ensureIndexIfMissing($db, 'vendas_itens', 'idx_vendas_itens_venda_produto', "CREATE INDEX idx_vendas_itens_venda_produto ON vendas_itens (venda_id, produto_id)");
    ensureIndexIfMissing($db, 'os_arquivos', 'idx_os_arquivos_os_tipo', "CREATE INDEX idx_os_arquivos_os_tipo ON os_arquivos (os_id, tipo)");
    ensureIndexIfMissing($db, 'logs_retorno_etapa', 'idx_logs_retorno_os_data', "CREATE INDEX idx_logs_retorno_os_data ON logs_retorno_etapa (os_id, created_at)");
}

function getOrCreateEstruturaProduto(PDO $db, int $produtoId): array
{
    $stmt = $db->prepare("
        SELECT *
        FROM estrutura_produto
        WHERE produto_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$produtoId]);
    $estrutura = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($estrutura) {
        return $estrutura;
    }

    $stmt = $db->prepare("
        INSERT INTO estrutura_produto (produto_id, versao, observacoes)
        VALUES (?, 'v1', NULL)
    ");
    $stmt->execute([$produtoId]);

    return [
        'id' => (int) $db->lastInsertId(),
        'produto_id' => $produtoId,
        'versao' => 'v1',
        'observacoes' => null,
    ];
}

function getResumoEngenhariaProduto(PDO $db, int $produtoId): array
{
    $estrutura = getOrCreateEstruturaProduto($db, $produtoId);

    $stmtComponentes = $db->prepare("
        SELECT
            COUNT(*) AS total_itens,
            COALESCE(SUM(cp.quantidade), 0) AS total_quantidade,
            COALESCE(SUM(cp.quantidade * COALESCE(i.custo_unitario, cp.custo_unitario, 0)), 0) AS custo_materiais
        FROM componentes_produto cp
        LEFT JOIN insumos i ON i.id = cp.insumo_id
        WHERE cp.estrutura_id = ?
    ");
    $stmtComponentes->execute([$estrutura['id']]);
    $componentes = $stmtComponentes->fetch(PDO::FETCH_ASSOC) ?: ['total_itens' => 0, 'total_quantidade' => 0, 'custo_materiais' => 0];

    $stmtTempos = $db->prepare("
        SELECT COUNT(*) AS total_etapas, COALESCE(SUM(minutos_estimados), 0) AS total_minutos
        FROM tempo_producao
        WHERE produto_id = ?
    ");
    $stmtTempos->execute([$produtoId]);
    $tempos = $stmtTempos->fetch(PDO::FETCH_ASSOC) ?: ['total_etapas' => 0, 'total_minutos' => 0];

    return [
        'estrutura' => $estrutura,
        'total_componentes' => (int) ($componentes['total_itens'] ?? 0),
        'total_quantidade_componentes' => (float) ($componentes['total_quantidade'] ?? 0),
        'custo_materiais' => (float) ($componentes['custo_materiais'] ?? 0),
        'total_etapas' => (int) ($tempos['total_etapas'] ?? 0),
        'total_minutos' => (int) ($tempos['total_minutos'] ?? 0),
    ];
}

function normalizarTextoComercial(?string $texto): string
{
    return mb_strtolower(trim((string) $texto));
}

/**
 * Gera o próximo código de insumo seguindo o MESMO padrão dos códigos da
 * matéria-prima do SolidWorks (numérico de 7 dígitos, faixa 10xxxxx).
 * Continua a numeração a partir do maior código existente.
 */
function gerarCodigoInsumo(PDO $db): string
{
    $stmt = $db->query("SELECT MAX(CAST(codigo AS UNSIGNED)) FROM insumos WHERE codigo REGEXP '^[0-9]{7}$'");
    $max = (int) ($stmt->fetchColumn() ?: 0);
    if ($max < 1000000) {
        $max = 1000000; // garante 7 dígitos começando em 10xxxxx
    }
    $chk = $db->prepare("SELECT 1 FROM insumos WHERE codigo = ?");
    do {
        $max++;
        $codigo = (string) $max;
        $chk->execute([$codigo]);
    } while ($chk->fetchColumn());
    return $codigo;
}

/**
 * Gera o próximo código de PRODUTO no padrão Cozinca:
 *   - padrão:   CZI-15101, CZI-15102, ...
 *   - especial: CZI-32101, CZI-32102, ...
 * Sequencial por família para evitar duplicidade; quem define se o produto
 * é padrão ou especial é o vendedor — o sistema só gera o código.
 */
function gerarCodigoProdutoCZI(PDO $db, string $tipo): string
{
    $prefixo = ($tipo === 'especial') ? 'CZI-32' : 'CZI-15';
    // dígitos após "CZI-15"/"CZI-32" (posição 7 em diante, 1-indexado)
    $stmt = $db->prepare("SELECT MAX(CAST(SUBSTRING(codigo, 7) AS UNSIGNED)) FROM produtos WHERE codigo LIKE ? AND SUBSTRING(codigo, 7) REGEXP '^[0-9]+$'");
    $stmt->execute([$prefixo . '%']);
    $max = (int) ($stmt->fetchColumn() ?: 0);
    if ($max < 100) {
        $max = 100; // primeira sequência: 101
    }
    $chk = $db->prepare("SELECT 1 FROM produtos WHERE codigo = ?");
    do {
        $max++;
        $codigo = $prefixo . $max;
        $chk->execute([$codigo]);
    } while ($chk->fetchColumn());
    return $codigo;
}

function upsertInsumo(PDO $db, array $dados): int
{
    $insumoId = (int) ($dados['id'] ?? 0);
    $codigo = trim((string) ($dados['codigo'] ?? ''));
    $nome = trim((string) ($dados['nome'] ?? ''));
    $fornecedor = trim((string) ($dados['fornecedor'] ?? ''));
    $unidade = trim((string) ($dados['unidade'] ?? 'un'));
    $custoUnitario = (float) ($dados['custo_unitario'] ?? 0);
    $observacoes = trim((string) ($dados['observacoes'] ?? ''));

    if ($nome === '') {
        throw new RuntimeException('O nome do componente/insumo e obrigatorio.');
    }

    if ($insumoId > 0) {
        $stmt = $db->prepare("
            UPDATE insumos
            SET codigo = ?, nome = ?, fornecedor = ?, unidade = ?, custo_unitario = ?, observacoes = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $codigo !== '' ? $codigo : null,
            $nome,
            $fornecedor !== '' ? $fornecedor : null,
            $unidade !== '' ? $unidade : 'un',
            $custoUnitario,
            $observacoes !== '' ? $observacoes : null,
            $insumoId,
        ]);

        return $insumoId;
    }

    $stmtBusca = $db->prepare("
        SELECT id
        FROM insumos
        WHERE LOWER(nome) = ?
          AND LOWER(COALESCE(fornecedor, '')) = ?
          AND LOWER(unidade) = ?
        LIMIT 1
    ");
    $stmtBusca->execute([
        normalizarTextoComercial($nome),
        normalizarTextoComercial($fornecedor),
        normalizarTextoComercial($unidade !== '' ? $unidade : 'un'),
    ]);
    $existenteId = (int) ($stmtBusca->fetchColumn() ?: 0);

    if ($existenteId > 0) {
        $stmt = $db->prepare("
            UPDATE insumos
            SET codigo = COALESCE(?, codigo),
                nome = ?,
                fornecedor = ?,
                unidade = ?,
                custo_unitario = ?,
                observacoes = COALESCE(?, observacoes)
            WHERE id = ?
        ");
        $stmt->execute([
            $codigo !== '' ? $codigo : null,
            $nome,
            $fornecedor !== '' ? $fornecedor : null,
            $unidade !== '' ? $unidade : 'un',
            $custoUnitario,
            $observacoes !== '' ? $observacoes : null,
            $existenteId,
        ]);

        return $existenteId;
    }

    $stmt = $db->prepare("
        INSERT INTO insumos (codigo, nome, fornecedor, unidade, custo_unitario, observacoes)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $codigo !== '' ? $codigo : null,
        $nome,
        $fornecedor !== '' ? $fornecedor : null,
        $unidade !== '' ? $unidade : 'un',
        $custoUnitario,
        $observacoes !== '' ? $observacoes : null,
    ]);

    return (int) $db->lastInsertId();
}

function salvarComponentesProduto(PDO $db, int $produtoId, array $componentes): void
{
    $estrutura = getOrCreateEstruturaProduto($db, $produtoId);

    $stmtDelete = $db->prepare("DELETE FROM componentes_produto WHERE estrutura_id = ?");
    $stmtDelete->execute([$estrutura['id']]);

    $stmtInsert = $db->prepare("
        INSERT INTO componentes_produto (estrutura_id, insumo_id, componente_nome, quantidade, unidade, custo_unitario)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach ($componentes as $componente) {
        $nome = trim((string) ($componente['nome'] ?? ''));
        $quantidade = (float) ($componente['quantidade'] ?? 0);
        $unidade = trim((string) ($componente['unidade'] ?? 'un'));
        $fornecedor = trim((string) ($componente['fornecedor'] ?? ''));
        $custoUnitario = (float) ($componente['custo_unitario'] ?? 0);
        $codigo = trim((string) ($componente['codigo'] ?? ''));
        $insumoIdInformado = (int) ($componente['insumo_id'] ?? 0);

        if ($nome === '' || $quantidade <= 0) {
            continue;
        }

        $insumoId = upsertInsumo($db, [
            'id' => $insumoIdInformado,
            'codigo' => $codigo,
            'nome' => $nome,
            'fornecedor' => $fornecedor,
            'unidade' => $unidade,
            'custo_unitario' => $custoUnitario,
        ]);

        $stmtInsert->execute([
            $estrutura['id'],
            $insumoId,
            $nome,
            $quantidade,
            $unidade !== '' ? $unidade : 'un',
            $custoUnitario,
        ]);
    }
}

function calcularCustosProduto(PDO $db, int $produtoId, float $custoMaoObra, float $custoIndireto, float $margemLucro): array
{
    $estrutura = getOrCreateEstruturaProduto($db, $produtoId);

    $stmt = $db->prepare("
        SELECT
            cp.id,
            cp.insumo_id,
            cp.componente_nome,
            cp.quantidade,
            cp.unidade,
            COALESCE(i.codigo, '') AS insumo_codigo,
            COALESCE(i.fornecedor, '') AS fornecedor,
            COALESCE(i.custo_unitario, cp.custo_unitario, 0) AS custo_unitario,
            (cp.quantidade * COALESCE(i.custo_unitario, cp.custo_unitario, 0)) AS custo_total
        FROM componentes_produto cp
        LEFT JOIN insumos i ON i.id = cp.insumo_id
        WHERE cp.estrutura_id = ?
        ORDER BY cp.id ASC
    ");
    $stmt->execute([$estrutura['id']]);
    $componentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $custoMateriais = 0.0;
    foreach ($componentes as &$componente) {
        $componente['quantidade'] = (float) $componente['quantidade'];
        $componente['custo_unitario'] = (float) $componente['custo_unitario'];
        $componente['custo_total'] = (float) $componente['custo_total'];
        $custoMateriais += $componente['custo_total'];
    }
    unset($componente);

    $custoTotal = $custoMateriais + $custoMaoObra + $custoIndireto;
    $precoSugerido = $custoTotal * (1 + max($margemLucro, 0) / 100);

    return [
        'estrutura' => $estrutura,
        'componentes' => $componentes,
        'custo_materiais' => round($custoMateriais, 2),
        'custo_mao_obra' => round($custoMaoObra, 2),
        'custo_indireto' => round($custoIndireto, 2),
        'custo_total' => round($custoTotal, 2),
        'margem_lucro' => round($margemLucro, 2),
        'preco_sugerido' => round($precoSugerido, 2),
    ];
}

function atualizarCustosProduto(PDO $db, int $produtoId): array
{
    $stmt = $db->prepare("
        SELECT custo_mao_obra, custo_indireto, margem_lucro
        FROM produtos
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$produtoId]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$produto) {
        throw new RuntimeException('Produto nao encontrado para recalculo.');
    }

    $custos = calcularCustosProduto(
        $db,
        $produtoId,
        (float) ($produto['custo_mao_obra'] ?? 0),
        (float) ($produto['custo_indireto'] ?? 0),
        (float) ($produto['margem_lucro'] ?? 0)
    );

    $stmtUpdate = $db->prepare("UPDATE produtos SET custo_total = ?, preco_sugerido = ? WHERE id = ?");
    $stmtUpdate->execute([
        $custos['custo_total'],
        $custos['preco_sugerido'],
        $produtoId,
    ]);

    return $custos;
}

function getProdutosAfetadosPorInsumos(PDO $db, array $insumoIds): array
{
    $insumoIds = array_values(array_unique(array_filter(array_map('intval', $insumoIds))));
    if (empty($insumoIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($insumoIds), '?'));
    $stmt = $db->prepare("
        SELECT DISTINCT ep.produto_id
        FROM componentes_produto cp
        INNER JOIN estrutura_produto ep ON ep.id = cp.estrutura_id
        WHERE cp.insumo_id IN ($placeholders)
    ");
    $stmt->execute($insumoIds);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function atualizarCustosProdutosAfetados(PDO $db, array $insumoIds, array $produtoIdsExtras = []): array
{
    $produtoIds = array_values(array_unique(array_merge(
        getProdutosAfetadosPorInsumos($db, $insumoIds),
        array_map('intval', $produtoIdsExtras)
    )));

    if (empty($produtoIds)) {
        return [];
    }

    $resultado = [];
    foreach ($produtoIds as $produtoId) {
        if ($produtoId <= 0) {
            continue;
        }

        $resultado[$produtoId] = atualizarCustosProduto($db, $produtoId);
    }

    return $resultado;
}

function getFluxoPadraoEngenharia(): array
{
    // Fluxo padrão quando o produto não tem planejamento específico.
    // Mobiliário, cocção e refrigeração são etapas condicionais: só entram
    // quando o planejamento do produto (tempo_producao) as inclui.
    return ['engenharia', 'programacao', 'corte', 'dobra', 'tubo', 'solda', 'acabamento', 'montagem', 'embalagem', 'finalizacao'];
}

function normalizarEtapaEngenharia(string $etapa): ?string
{
    $etapa = trim(mb_strtolower($etapa));
    if ($etapa === '') {
        return null;
    }

    $mapa = [
        'engenharia' => 'engenharia',
        'programacao' => 'programacao',
        'programação' => 'programacao',
        'corte' => 'corte',
        'dobra' => 'dobra',
        'tubo' => 'tubo',
        'solda' => 'solda',
        'mobiliario' => 'mobiliario',
        'mobiliário' => 'mobiliario',
        'coccao' => 'coccao',
        'cocção' => 'coccao',
        'refrigeracao' => 'refrigeracao',
        'refrigeração' => 'refrigeracao',
        'acabamento' => 'acabamento',
        'montagem' => 'montagem',
        'montar' => 'montagem',
        'embalagem' => 'embalagem',
        'finalizacao' => 'finalizacao',
        'finalização' => 'finalizacao',
    ];

    return $mapa[$etapa] ?? null;
}

function getPlanejamentoEtapasPorVenda(PDO $db, int $vendaId): array
{
    $stmt = $db->prepare("
        SELECT
            tp.etapa,
            SUM(tp.minutos_estimados * COALESCE(vi.quantidade, 1)) AS minutos_estimados,
            COUNT(*) AS total_registros
        FROM vendas_itens vi
        INNER JOIN tempo_producao tp ON tp.produto_id = vi.produto_id
        WHERE vi.venda_id = ?
          AND vi.produto_id IS NOT NULL
        GROUP BY tp.etapa
    ");
    $stmt->execute([$vendaId]);
    $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $etapasPlanejadas = [];
    foreach ($linhas as $linha) {
        $etapaNormalizada = normalizarEtapaEngenharia((string) ($linha['etapa'] ?? ''));
        if ($etapaNormalizada === null) {
            continue;
        }

        if (!isset($etapasPlanejadas[$etapaNormalizada])) {
            $etapasPlanejadas[$etapaNormalizada] = [
                'etapa' => $etapaNormalizada,
                'minutos_estimados' => 0,
                'origens' => 0,
            ];
        }

        $etapasPlanejadas[$etapaNormalizada]['minutos_estimados'] += (int) round((float) ($linha['minutos_estimados'] ?? 0));
        $etapasPlanejadas[$etapaNormalizada]['origens'] += (int) ($linha['total_registros'] ?? 0);
    }

    if (empty($etapasPlanejadas)) {
        foreach (getFluxoPadraoEngenharia() as $etapaPadrao) {
            $etapasPlanejadas[$etapaPadrao] = [
                'etapa' => $etapaPadrao,
                'minutos_estimados' => 0,
                'origens' => 0,
            ];
        }
    }

    $fluxoPadrao = getFluxoPadraoEngenharia();
    usort($etapasPlanejadas, function (array $a, array $b) use ($fluxoPadrao): int {
        return array_search($a['etapa'], $fluxoPadrao, true) <=> array_search($b['etapa'], $fluxoPadrao, true);
    });

    return $etapasPlanejadas;
}

function getPlanejamentoEtapasPorOS(PDO $db, int $osId, int $vendaId = 0): array
{
    if ($vendaId > 0) {
        return getPlanejamentoEtapasPorVenda($db, $vendaId);
    }

    $stmt = $db->prepare("
        SELECT
            tp.etapa,
            SUM(tp.minutos_estimados * COALESCE(oi.quantidade, 1)) AS minutos_estimados,
            COUNT(*) AS total_registros
        FROM os_itens oi
        INNER JOIN tempo_producao tp ON tp.produto_id = oi.produto_id
        WHERE oi.os_id = ?
          AND oi.produto_id IS NOT NULL
        GROUP BY tp.etapa
    ");
    $stmt->execute([$osId]);
    $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $etapasPlanejadas = [];
    foreach ($linhas as $linha) {
        $etapaNormalizada = normalizarEtapaEngenharia((string) ($linha['etapa'] ?? ''));
        if ($etapaNormalizada === null) {
            continue;
        }

        if (!isset($etapasPlanejadas[$etapaNormalizada])) {
            $etapasPlanejadas[$etapaNormalizada] = [
                'etapa' => $etapaNormalizada,
                'minutos_estimados' => 0,
                'origens' => 0,
            ];
        }

        $etapasPlanejadas[$etapaNormalizada]['minutos_estimados'] += (int) round((float) ($linha['minutos_estimados'] ?? 0));
        $etapasPlanejadas[$etapaNormalizada]['origens'] += (int) ($linha['total_registros'] ?? 0);
    }

    if (empty($etapasPlanejadas)) {
        foreach (getFluxoPadraoEngenharia() as $etapaPadrao) {
            $etapasPlanejadas[$etapaPadrao] = [
                'etapa' => $etapaPadrao,
                'minutos_estimados' => 0,
                'origens' => 0,
            ];
        }
    }

    $fluxoPadrao = getFluxoPadraoEngenharia();
    usort($etapasPlanejadas, function (array $a, array $b) use ($fluxoPadrao): int {
        return array_search($a['etapa'], $fluxoPadrao, true) <=> array_search($b['etapa'], $fluxoPadrao, true);
    });

    return $etapasPlanejadas;
}

function sincronizarPlanejamentoOS(PDO $db, int $osId, int $vendaId = 0): array
{
    // Roteiro definido manualmente pelo projetista tem prioridade — não
    // sobrescreve com o planejamento automático derivado do produto.
    try {
        $stmtManual = $db->prepare("SELECT roteiro_manual FROM ordens_servico WHERE id = ?");
        $stmtManual->execute([$osId]);
        if ((int) $stmtManual->fetchColumn() === 1) {
            $stmtAtual = $db->prepare("SELECT etapa FROM os_etapas_producao WHERE os_id = ?");
            $stmtAtual->execute([$osId]);
            return array_map(fn($e) => ['etapa' => $e], $stmtAtual->fetchAll(PDO::FETCH_COLUMN));
        }
    } catch (Exception $e) { /* coluna pode não existir em bancos antigos */ }

    $etapasPlanejadas = getPlanejamentoEtapasPorOS($db, $osId, $vendaId);

    $stmtExistentes = $db->prepare("
        SELECT id, etapa, status, data_inicio, data_fim, tempo_total_segundos, usuario_id
        FROM os_etapas_producao
        WHERE os_id = ?
    ");
    $stmtExistentes->execute([$osId]);
    $existentes = $stmtExistentes->fetchAll(PDO::FETCH_ASSOC);

    $existentesPorEtapa = [];
    foreach ($existentes as $existente) {
        $existentesPorEtapa[$existente['etapa']] = $existente;
    }

    $planejadasPorEtapa = [];
    foreach ($etapasPlanejadas as $etapaPlanejada) {
        $planejadasPorEtapa[$etapaPlanejada['etapa']] = $etapaPlanejada;
    }

    foreach ($etapasPlanejadas as $etapaPlanejada) {
        if (isset($existentesPorEtapa[$etapaPlanejada['etapa']])) {
            continue;
        }

        $stmtInsert = $db->prepare("INSERT INTO os_etapas_producao (os_id, etapa, status) VALUES (?, ?, 'pendente')");
        $stmtInsert->execute([$osId, $etapaPlanejada['etapa']]);
    }

    foreach ($existentes as $existente) {
        if (!isset($planejadasPorEtapa[$existente['etapa']])) {
            if (
                $existente['status'] === 'pendente'
                && empty($existente['data_inicio'])
                && empty($existente['data_fim'])
                && (int) $existente['tempo_total_segundos'] === 0
            ) {
                $stmtDelete = $db->prepare("DELETE FROM os_etapas_producao WHERE id = ?");
                $stmtDelete->execute([$existente['id']]);
            }
        }
    }

    return $etapasPlanejadas;
}

function getComponentesPorVenda(PDO $db, int $vendaId): array
{
    $stmt = $db->prepare("
        SELECT
            vi.id AS venda_item_id,
            vi.quantidade AS quantidade_vendida,
            vi.descricao_manual,
            p.id AS produto_id,
            p.codigo AS produto_codigo,
            p.nome AS produto_nome,
            ep.id AS estrutura_id
        FROM vendas_itens vi
        LEFT JOIN produtos p ON p.id = vi.produto_id
        LEFT JOIN estrutura_produto ep ON ep.id = (
            SELECT ep2.id
            FROM estrutura_produto ep2
            WHERE ep2.produto_id = vi.produto_id
            ORDER BY ep2.id DESC
            LIMIT 1
        )
        WHERE vi.venda_id = ?
        ORDER BY vi.id ASC
    ");
    $stmt->execute([$vendaId]);
    $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return montarComponentesItensComerciais($db, $itens, 'venda_item_id');
}

function montarComponentesItensComerciais(PDO $db, array $itens, string $idCampo): array
{
    if (empty($itens)) {
        return [];
    }

    $estruturasIds = [];
    foreach ($itens as $item) {
        $estruturaId = (int) ($item['estrutura_id'] ?? 0);
        if ($estruturaId > 0) {
            $estruturasIds[] = $estruturaId;
        }
    }

    $estruturasIds = array_values(array_unique($estruturasIds));
    if (empty($estruturasIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($estruturasIds), '?'));
    $stmtComponentes = $db->prepare("
        SELECT
            cp.estrutura_id,
            cp.componente_nome,
            cp.quantidade,
            cp.unidade,
            COALESCE(i.custo_unitario, cp.custo_unitario, 0) AS custo_unitario
        FROM componentes_produto cp
        LEFT JOIN insumos i ON i.id = cp.insumo_id
        WHERE cp.estrutura_id IN ($placeholders)
        ORDER BY cp.estrutura_id ASC, cp.id ASC
    ");
    $stmtComponentes->execute($estruturasIds);
    $componentesBrutos = $stmtComponentes->fetchAll(PDO::FETCH_ASSOC);

    $componentesPorEstrutura = [];
    foreach ($componentesBrutos as $componente) {
        $estruturaId = (int) ($componente['estrutura_id'] ?? 0);
        if ($estruturaId <= 0) {
            continue;
        }
        $componentesPorEstrutura[$estruturaId][] = $componente;
    }

    $resultado = [];
    foreach ($itens as $item) {
        if (empty($item['produto_id']) || empty($item['estrutura_id'])) {
            continue;
        }

        $estruturaId = (int) ($item['estrutura_id'] ?? 0);
        $componentes = $componentesPorEstrutura[$estruturaId] ?? [];

        if (empty($componentes)) {
            continue;
        }

        $quantidadeVendida = (float) ($item['quantidade_vendida'] ?? 1);
        $componentesCalculados = [];
        foreach ($componentes as $componente) {
            $componentesCalculados[] = [
                'componente_nome' => $componente['componente_nome'],
                'quantidade_base' => (float) $componente['quantidade'],
                'quantidade_total' => (float) $componente['quantidade'] * $quantidadeVendida,
                'unidade' => $componente['unidade'],
                'custo_unitario' => (float) ($componente['custo_unitario'] ?? 0),
            ];
        }

        $resultado[] = [
            $idCampo => (int) ($item[$idCampo] ?? 0),
            'produto_id' => (int) $item['produto_id'],
            'produto_codigo' => $item['produto_codigo'],
            'produto_nome' => $item['produto_nome'],
            'descricao_manual' => $item['descricao_manual'],
            'quantidade_vendida' => $quantidadeVendida,
            'componentes' => $componentesCalculados,
        ];
    }

    return $resultado;
}

function getComponentesPorOS(PDO $db, int $osId, int $vendaId = 0): array
{
    if ($vendaId > 0) {
        return getComponentesPorVenda($db, $vendaId);
    }

    $stmt = $db->prepare("
        SELECT
            oi.id AS os_item_id,
            oi.quantidade AS quantidade_vendida,
            oi.descricao_manual,
            p.id AS produto_id,
            p.codigo AS produto_codigo,
            p.nome AS produto_nome,
            ep.id AS estrutura_id
        FROM os_itens oi
        LEFT JOIN produtos p ON p.id = oi.produto_id
        LEFT JOIN estrutura_produto ep ON ep.id = (
            SELECT ep2.id
            FROM estrutura_produto ep2
            WHERE ep2.produto_id = oi.produto_id
            ORDER BY ep2.id DESC
            LIMIT 1
        )
        WHERE oi.os_id = ?
        ORDER BY oi.id ASC
    ");
    $stmt->execute([$osId]);
    $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return montarComponentesItensComerciais($db, $itens, 'os_item_id');
}

function getItensComerciaisOS(PDO $db, int $osId, int $vendaId = 0, int $itemId = 0): array
{
    if ($vendaId > 0) {
        $sql = "
            SELECT
                vi.id,
                vi.produto_id,
                vi.descricao_manual,
                vi.quantidade,
                p.codigo AS produto_codigo,
                p.nome AS produto_nome,
                p.descricao AS produto_descricao
            FROM vendas_itens vi
            LEFT JOIN produtos p ON vi.produto_id = p.id
            WHERE vi.venda_id = ?";
        if ($itemId > 0) {
            $sql .= " AND vi.id = ?";
        }
        $sql .= " ORDER BY vi.id ASC";
        $stmt = $db->prepare($sql);
        if ($itemId > 0) {
            $stmt->execute([$vendaId, $itemId]);
        } else {
            $stmt->execute([$vendaId]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $sql = "
        SELECT
            oi.id,
            oi.produto_id,
            oi.descricao_manual,
            oi.quantidade,
            p.codigo AS produto_codigo,
            p.nome AS produto_nome,
            p.descricao AS produto_descricao
        FROM os_itens oi
        LEFT JOIN produtos p ON oi.produto_id = p.id
        WHERE oi.os_id = ?";
    if ($itemId > 0) {
        $sql .= " AND oi.id = ?";
    }
    $sql .= " ORDER BY oi.id ASC";
    
    $stmt = $db->prepare($sql);
    if ($itemId > 0) {
        $stmt->execute([$osId, $itemId]);
    } else {
        $stmt->execute([$osId]);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function formatarResumoComponentesVenda(array $componentesPorVenda): string
{
    if (empty($componentesPorVenda)) {
        return '';
    }

    $linhas = ["Lista de componentes dos produtos vendidos:"];
    foreach ($componentesPorVenda as $produto) {
        $titulo = trim(($produto['produto_codigo'] ? $produto['produto_codigo'] . ' - ' : '') . ($produto['produto_nome'] ?? 'Produto'));
        $linhas[] = $titulo . ' | Qtd vendida: ' . number_format((float) $produto['quantidade_vendida'], 2, ',', '.');
        foreach ($produto['componentes'] as $componente) {
            $linhas[] = '- ' . $componente['componente_nome'] . ': ' . number_format((float) $componente['quantidade_total'], 2, ',', '.') . ' ' . $componente['unidade'];
        }
    }

    return implode("\n", $linhas);
}
