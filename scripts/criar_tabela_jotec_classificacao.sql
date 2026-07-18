-- ============================================================
-- TABELA DE CLASSIFICACAO JOTEC - 2137 CODIGOS
-- Data: 2026-07-17
-- ============================================================

-- Criar tabela de classificacao JOTEC
CREATE TABLE IF NOT EXISTS jotec_classificacao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_jotec INT NOT NULL UNIQUE,
    tipo ENUM('INSUMO', 'PRODUTO', 'LEGADO', 'DESCONHECIDO') NOT NULL,
    aba VARCHAR(100) NOT NULL,
    categoria VARCHAR(100),
    descricao TEXT,
    status ENUM('ativo', 'inativo', 'descontinuado') DEFAULT 'ativo',
    range_inicio INT,
    range_fim INT,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    observacoes TEXT,
    INDEX idx_tipo (tipo),
    INDEX idx_aba (aba),
    INDEX idx_codigo (codigo_jotec),
    INDEX idx_range (range_inicio, range_fim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Mapeamento completo dos codigos JOTEC com classificacao';

-- Criar tabela de auditoria
CREATE TABLE IF NOT EXISTS jotec_classificacao_auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_jotec INT NOT NULL,
    tipo_anterior VARCHAR(50),
    tipo_novo VARCHAR(50),
    usuario VARCHAR(100),
    motivo TEXT,
    data_alteracao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_codigo (codigo_jotec),
    INDEX idx_data (data_alteracao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Criar indice de busca rapida
CREATE INDEX idx_jotec_completo ON jotec_classificacao (codigo_jotec, tipo, aba);

-- ============================================================
-- DADOS DE MATERIAIS (1000000-1000058) - INSUMO
-- ============================================================

INSERT INTO jotec_classificacao (codigo_jotec, tipo, aba, categoria, descricao, range_inicio, range_fim, observacoes)
VALUES
(1000000, 'INSUMO', 'MATERIAIS', 'Materia Prima', 'Aco Inox', 1000000, 1000058, 'Range principal de materiais'),
(1000001, 'INSUMO', 'MATERIAIS', 'Materia Prima', 'Aco Carbono', 1000000, 1000058, ''),
(1000002, 'INSUMO', 'MATERIAIS', 'Materia Prima', 'Aluminio', 1000000, 1000058, ''),
(1000003, 'INSUMO', 'MATERIAIS', 'Materia Prima', 'Cobre', 1000000, 1000058, ''),
(1000004, 'INSUMO', 'MATERIAIS', 'Materia Prima', 'Lataao', 1000000, 1000058, ''),
(1000005, 'INSUMO', 'MATERIAIS', 'Materia Prima', 'Cromo', 1000000, 1000058, ''),
(1000006, 'INSUMO', 'MATERIAIS', 'Materia Prima', 'Niquel', 1000000, 1000058, ''),
(1000007, 'INSUMO', 'MATERIAIS', 'Materia Prima', 'Zinco', 1000000, 1000058, ''),
(1000008, 'INSUMO', 'MATERIAIS', 'Materia Prima', 'Manganes', 1000000, 1000058, ''),
(1000009, 'INSUMO', 'MATERIAIS', 'Materia Prima', 'Molibdenio', 1000000, 1000058, '');

-- ============================================================
-- DADOS DE INSUMOS DIRETOS (1006001-1006489) - INSUMO
-- ============================================================

INSERT INTO jotec_classificacao (codigo_jotec, tipo, aba, categoria, descricao, range_inicio, range_fim, status, observacoes)
VALUES
(1006001, 'INSUMO', 'INSUMOS DIRETOS', 'Componente', 'Parafuso M8x25', 1006001, 1006489, 'ativo', 'Componentes que entram direto na producao'),
(1006002, 'INSUMO', 'INSUMOS DIRETOS', 'Componente', 'Porca M8', 1006001, 1006489, 'ativo', ''),
(1006003, 'INSUMO', 'INSUMOS DIRETOS', 'Componente', 'Arruela M8', 1006001, 1006489, 'ativo', '');

-- ============================================================
-- DADOS DE INSUMOS INDIRETOS (3000000-3000149) - INSUMO
-- ============================================================

INSERT INTO jotec_classificacao (codigo_jotec, tipo, aba, categoria, descricao, range_inicio, range_fim, status, observacoes)
VALUES
(3000000, 'INSUMO', 'INSUMOS INDIRETOS', 'Consumo', 'Oxigenio Industrial', 3000000, 3000149, 'ativo', 'Gases e materiais de apoio'),
(3000001, 'INSUMO', 'INSUMOS INDIRETOS', 'Consumo', 'Nitrogenio', 3000000, 3000149, 'ativo', ''),
(3000002, 'INSUMO', 'INSUMOS INDIRETOS', 'Consumo', 'Argonio', 3000000, 3000149, 'ativo', ''),
(3000003, 'INSUMO', 'INSUMOS INDIRETOS', 'Consumo', 'Acetilenio', 3000000, 3000149, 'ativo', '');

-- ============================================================
-- DADOS DE MATERIAL DE CONSUMO (4003001-4003498) - INSUMO
-- ============================================================

INSERT INTO jotec_classificacao (codigo_jotec, tipo, aba, categoria, descricao, range_inicio, range_fim, status, observacoes)
VALUES
(4003001, 'INSUMO', 'MATERIAL DE CONSUMO', 'Consumivel', 'Pano de Limpeza', 4003001, 4003498, 'ativo', 'Materiais consumiveis diversos'),
(4003002, 'INSUMO', 'MATERIAL DE CONSUMO', 'Consumivel', 'Lubrificante', 4003001, 4003498, 'ativo', ''),
(4003003, 'INSUMO', 'MATERIAL DE CONSUMO', 'Consumivel', 'Tinta', 4003001, 4003498, 'ativo', ''),
(4003004, 'INSUMO', 'MATERIAL DE CONSUMO', 'Consumivel', 'Adesivo', 4003001, 4003498, 'ativo', '');

-- ============================================================
-- DADOS DE REVENDA (1500000-1500155) - PRODUTO
-- ============================================================

INSERT INTO jotec_classificacao (codigo_jotec, tipo, aba, categoria, descricao, range_inicio, range_fim, status, observacoes)
VALUES
(1500000, 'PRODUTO', 'REVENDA', 'Revenda', 'Produto Revenda A', 1500000, 1500155, 'ativo', 'Produtos comprados para revenda'),
(1500001, 'PRODUTO', 'REVENDA', 'Revenda', 'Produto Revenda B', 1500000, 1500155, 'ativo', ''),
(1500002, 'PRODUTO', 'REVENDA', 'Revenda', 'Produto Revenda C', 1500000, 1500155, 'ativo', '');

-- ============================================================
-- DADOS DE ATIVO (3500001-3500498) - PRODUTO
-- ============================================================

INSERT INTO jotec_classificacao (codigo_jotec, tipo, aba, categoria, descricao, range_inicio, range_fim, status, observacoes)
VALUES
(3500001, 'PRODUTO', 'ATIVO', 'Ativo Fixo', 'Maquina Solda A', 3500001, 3500498, 'ativo', 'Ativos fixos e equipamentos'),
(3500002, 'PRODUTO', 'ATIVO', 'Ativo Fixo', 'Maquina Solda B', 3500001, 3500498, 'ativo', ''),
(3500003, 'PRODUTO', 'ATIVO', 'Ativo Fixo', 'Compressor Ar', 3500001, 3500498, 'ativo', ''),
(3500004, 'PRODUTO', 'ATIVO', 'Ativo Fixo', 'Esmerilhadeira', 3500001, 3500498, 'ativo', '');

-- ============================================================
-- DADOS LEGADO (992-999) - PRODUTO
-- ============================================================

INSERT INTO jotec_classificacao (codigo_jotec, tipo, aba, categoria, descricao, range_inicio, range_fim, status, observacoes)
VALUES
(992, 'LEGADO', 'PRODUTOS ACABADOS', 'Legado', 'Codigo Teste 992', 992, 999, 'inativo', 'Codigos legados/teste do sistema antigo'),
(993, 'LEGADO', 'PRODUTOS ACABADOS', 'Legado', 'Codigo Teste 993', 992, 999, 'inativo', ''),
(994, 'LEGADO', 'PRODUTOS ACABADOS', 'Legado', 'Codigo Teste 994', 992, 999, 'inativo', ''),
(995, 'LEGADO', 'PRODUTOS ACABADOS', 'Legado', 'Codigo Teste 995', 992, 999, 'inativo', ''),
(996, 'LEGADO', 'PRODUTOS ACABADOS', 'Legado', 'Codigo Teste 996', 992, 999, 'inativo', ''),
(997, 'LEGADO', 'PRODUTOS ACABADOS', 'Legado', 'Codigo Teste 997', 992, 999, 'inativo', ''),
(998, 'LEGADO', 'PRODUTOS ACABADOS', 'Legado', 'Codigo Teste 998', 992, 999, 'inativo', ''),
(999, 'LEGADO', 'PRODUTOS ACABADOS', 'Legado', 'Codigo Teste 999', 992, 999, 'inativo', '');

-- ============================================================
-- RESUMO DO BANCO CRIADO
-- ============================================================

-- Total esperado: 2137 codigos
-- INSUMO: ~1305 codigos
-- PRODUTO: ~669 codigos
-- LEGADO: 8 codigos
-- DESCONHECIDO: ~155 codigos

-- Verificar integridade:
-- SELECT tipo, COUNT(*) as quantidade FROM jotec_classificacao GROUP BY tipo;
-- SELECT COUNT(*) as total FROM jotec_classificacao;

-- ============================================================
-- NOTA IMPORTANTE
-- ============================================================
-- Este script cria a estrutura e alguns dados de exemplo.
-- Para importar os 2137 codigos completos, usar o arquivo
-- JSON e o script PHP de importacao.
--
-- Ver: /api/importar_jotec_classificacao.php
