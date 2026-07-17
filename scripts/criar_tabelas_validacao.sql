-- ===== TABELAS PARA SISTEMA DE VALIDAÇÃO 100% =====

-- 1. TABELA: operacoes_registro
-- Registra TODAS as operações com hash único (anti-duplicidade)
CREATE TABLE IF NOT EXISTS operacoes_registro (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tipo_operacao VARCHAR(100) NOT NULL,
    hash_dados VARCHAR(255) UNIQUE NOT NULL,
    dados_json LONGTEXT,
    usuario_id INT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tipo (tipo_operacao),
    INDEX idx_hash (hash_dados),
    INDEX idx_usuario (usuario_id),
    INDEX idx_criado (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. TABELA: validacao_log
-- Log de todas as validações realizadas
CREATE TABLE IF NOT EXISTS validacao_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tipo_validacao VARCHAR(100),
    objeto_id INT,
    status ENUM('OK', 'ERRO', 'AVISO'),
    mensagem TEXT,
    usuario_id INT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tipo (tipo_validacao),
    INDEX idx_status (status),
    INDEX idx_criado (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. TABELA: estoque_materias_primas
-- Controle de estoque por matéria prima
CREATE TABLE IF NOT EXISTS estoque_materias_primas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    materia_prima_id INT NOT NULL,
    quantidade DECIMAL(10,2) NOT NULL,
    data_entrada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    numero_lote VARCHAR(100),
    data_validade DATE,
    status ENUM('ativo', 'reservado', 'descartado') DEFAULT 'ativo',
    origem_compra_id INT,
    origem_recebimento_id INT,
    usuario_id INT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_materia (materia_prima_id),
    INDEX idx_status (status),
    INDEX idx_lote (numero_lote),
    INDEX idx_validade (data_validade),
    FOREIGN KEY (materia_prima_id) REFERENCES materias_primas(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. TABELA: recebimentos
-- Registro de recebimentos de compras
CREATE TABLE IF NOT EXISTS recebimentos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    compra_id INT NOT NULL,
    quantidade_recebida DECIMAL(10,2) NOT NULL,
    numero_lote VARCHAR(100),
    data_validade DATE,
    data_recebimento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    usuario_recebimento_id INT,
    status ENUM('pendente', 'confirmado', 'rejeitado') DEFAULT 'pendente',
    observacoes TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_compra (compra_id),
    INDEX idx_status (status),
    INDEX idx_data (data_recebimento),
    FOREIGN KEY (compra_id) REFERENCES compras_materia_prima(id),
    FOREIGN KEY (usuario_recebimento_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. TABELA: apontamentos_producao
-- Apontamento de matéria prima na produção
CREATE TABLE IF NOT EXISTS apontamentos_producao (
    id INT PRIMARY KEY AUTO_INCREMENT,
    os_id INT NOT NULL,
    etapa_id INT,
    materia_prima_id INT NOT NULL,
    quantidade DECIMAL(10,2) NOT NULL,
    data_apontamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    usuario_id INT NOT NULL,
    numero_lote VARCHAR(100),
    observacoes TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_os (os_id),
    INDEX idx_mp (materia_prima_id),
    INDEX idx_data (data_apontamento),
    INDEX idx_usuario (usuario_id),
    FOREIGN KEY (os_id) REFERENCES ordens_servico(id),
    FOREIGN KEY (materia_prima_id) REFERENCES materias_primas(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. TABELA: validacao_fluxo
-- Rastreia fluxo de validação (compra → recebimento → apontamento → estoque)
CREATE TABLE IF NOT EXISTS validacao_fluxo (
    id INT PRIMARY KEY AUTO_INCREMENT,
    materia_prima_id INT NOT NULL,
    compra_id INT,
    recebimento_id INT,
    apontamento_id INT,
    etapa_atual VARCHAR(50),
    status ENUM('em_compra', 'em_recebimento', 'em_estoque', 'em_producao', 'completo') DEFAULT 'em_compra',
    data_inicio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_conclusao DATETIME,
    INDEX idx_mp (materia_prima_id),
    INDEX idx_status (status),
    FOREIGN KEY (materia_prima_id) REFERENCES materias_primas(id),
    FOREIGN KEY (compra_id) REFERENCES compras_materia_prima(id),
    FOREIGN KEY (recebimento_id) REFERENCES recebimentos(id),
    FOREIGN KEY (apontamento_id) REFERENCES apontamentos_producao(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. TABELA: alertas_duplicidade
-- Registra alertas de tentativas de duplicidade
CREATE TABLE IF NOT EXISTS alertas_duplicidade (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tipo_operacao VARCHAR(100),
    hash_dados VARCHAR(255),
    dados_tentativa LONGTEXT,
    operacao_original_id INT,
    usuario_id INT,
    data_tentativa TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    bloqueado INT DEFAULT 1,
    INDEX idx_hash (hash_dados),
    INDEX idx_data (data_tentativa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. ALTERAR tabela compras_materia_prima (se não tiver status)
ALTER TABLE compras_materia_prima
ADD COLUMN IF NOT EXISTS status ENUM('pendente', 'recebido', 'cancelado') DEFAULT 'pendente',
ADD COLUMN IF NOT EXISTS numero_nf VARCHAR(100),
ADD COLUMN IF NOT EXISTS data_compra DATE,
ADD COLUMN IF NOT EXISTS fornecedor_id INT,
ADD INDEX IF NOT EXISTS idx_status (status),
ADD INDEX IF NOT EXISTS idx_nf (numero_nf);

-- 9. ALTERAR tabela materias_primas (se não tiver campos)
ALTER TABLE materias_primas
ADD COLUMN IF NOT EXISTS codigo VARCHAR(100) UNIQUE,
ADD COLUMN IF NOT EXISTS fornecedor_id INT,
ADD COLUMN IF NOT EXISTS preco DECIMAL(10,2),
ADD COLUMN IF NOT EXISTS unidade VARCHAR(50),
ADD INDEX IF NOT EXISTS idx_codigo (codigo),
ADD INDEX IF NOT EXISTS idx_fornecedor (fornecedor_id);

-- 10. ALTERAR tabela ordens_servico (se não tiver status)
ALTER TABLE ordens_servico
ADD COLUMN IF NOT EXISTS status ENUM('pendente', 'em_producao', 'finalizado', 'cancelado') DEFAULT 'pendente',
ADD INDEX IF NOT EXISTS idx_status (status);

-- ===== DADOS DE EXEMPLO =====

-- Inserir fornecedores de teste
INSERT IGNORE INTO fornecedores (razao_social, cnpj, email, telefone) VALUES
('Fornecedor A', '11.222.333/0001-44', 'contato@fornecedora.com', '11 3333-3333'),
('Fornecedor B', '55.666.777/0001-88', 'contato@fornecedorb.com', '11 4444-4444');

-- Inserir matérias primas de teste
INSERT IGNORE INTO materias_primas (codigo, descricao, fornecedor_id, preco, unidade) VALUES
('MP-001', 'Aço Inox 304', 1, 150.00, 'kg'),
('MP-002', 'Parafuso M12', 2, 0.50, 'pc'),
('MP-003', 'Tinta Epóxi', 1, 45.00, 'l');
