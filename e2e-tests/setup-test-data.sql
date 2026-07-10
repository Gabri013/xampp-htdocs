-- Script para criar dados de teste para os testes E2E
-- Banco de dados: dbcozinca

-- 1. Criar cliente de teste
INSERT IGNORE INTO clientes (id, razao_social, nome_fantasia, cnpj_cpf, email, telefone, endereco, bairro, cidade, estado, cep, created_at)
VALUES (999, 'Cliente Teste E2E', 'Teste E2E', '00000000000191', 'teste@e2e.com', '11999999999', 'Rua Teste', 'Bairro Teste', 'São Paulo', 'SP', '01001000', NOW());

-- 2. Criar usuário de teste (projetista)
INSERT IGNORE INTO usuarios (id, nome, email, senha, tipo, ativo, created_at)
VALUES (999, 'Usuário Teste E2E', 'teste@e2e.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'projetista', 1, NOW());
-- Senha: password (hash do Laravel)

-- 3. Criar venda de teste
INSERT IGNORE INTO vendas (id, cliente_id, usuario_id, numero, data_venda, valor_total, desconto, status, observacoes, created_at)
VALUES (999, 999, 999, 'VENDA-TEST-001', NOW(), 1000.00, 0.00, 'concluida', 'Venda de teste para E2E', NOW());

-- 4. Criar itens da venda de teste
INSERT IGNORE INTO vendas_itens (id, venda_id, produto_id, descricao_manual, quantidade, valor_unitario, valor_total)
VALUES 
(999, 999, NULL, 'Produto Teste 1', 1, 500.00, 500.00),
(998, 999, NULL, 'Produto Teste 2', 1, 500.00, 500.00);

-- 5. Criar OS de teste com ID=1
INSERT IGNORE INTO ordens_servico (id, numero, cliente_id, venda_id, status, prioridade, data_inicio, data_termino, etapa_atual, observacoes_corte_dobra, observacoes_solda, created_at)
VALUES (1, 'OS-TEST-001', 999, 999, 'pendente', 'verde', NOW(), DATE_ADD(NOW(), INTERVAL 15 DAY), 'corte', 'Observação teste corte', 'Observação teste solda', NOW());

-- 6. Criar itens da OS de teste
INSERT IGNORE INTO os_itens (id, os_id, produto_id, descricao_manual, quantidade, valor_unitario)
VALUES 
(999, 1, NULL, 'Item Teste 1', 1, 500.00),
(998, 1, NULL, 'Item Teste 2', 1, 500.00);

-- 7. Criar histórico de status da OS
INSERT IGNORE INTO os_historico_status (os_id, status_anterior, status_novo, usuario_id, observacao, created_at)
VALUES (1, NULL, 'pendente', 999, 'OS criada automaticamente para testes E2E', NOW());

-- Verificar se os dados foram inseridos
SELECT 'Cliente' as tipo, COUNT(*) as total FROM clientes WHERE id = 999
UNION ALL
SELECT 'Usuario', COUNT(*) FROM usuarios WHERE id = 999
UNION ALL
SELECT 'Venda', COUNT(*) FROM vendas WHERE id = 999
UNION ALL
SELECT 'OS', COUNT(*) FROM ordens_servico WHERE id = 1;
