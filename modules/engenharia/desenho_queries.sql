-- ===== QUERIES ÚTEIS - DESENHO TÉCNICO E APROVAÇÃO =====

-- 1. LISTAR TODOS OS DESENHOS DE UMA O.S.
SELECT d.id, d.titulo, d.versao, d.status, d.prioridade,
       u_proj.nome AS projetista,
       DATE_FORMAT(d.created_at, '%d/%m/%Y %H:%i') AS data_criacao,
       COUNT(da.id) AS total_arquivos
FROM desenhos_tecnicos d
LEFT JOIN usuarios u_proj ON u_proj.id = d.usuario_projetista_id
LEFT JOIN desenhos_arquivos da ON da.desenho_id = d.id
WHERE d.os_id = 123  -- Substituir pelo ID da O.S.
GROUP BY d.id
ORDER BY d.created_at DESC;

-- 2. DESENHOS AGUARDANDO APROVAÇÃO DA GERÊNCIA
SELECT d.id, d.titulo, d.versao, os.numero AS os_numero,
       u_proj.nome AS projetista,
       u_gerente.nome AS gerente_designado,
       dap.prazo_resposta,
       DATEDIFF(dap.prazo_resposta, NOW()) AS dias_restantes
FROM desenhos_tecnicos d
INNER JOIN desenhos_aprovaes dap ON dap.desenho_id = d.id
INNER JOIN ordens_servico os ON os.id = d.os_id
LEFT JOIN usuarios u_proj ON u_proj.id = d.usuario_projetista_id
LEFT JOIN usuarios u_gerente ON u_gerente.id = d.usuario_gerente_id
WHERE dap.etapa = 'gerencia'
  AND dap.status = 'pendente'
  AND dap.prazo_resposta > NOW()
ORDER BY dap.prazo_resposta ASC;

-- 3. DESENHOS REJEITADOS AGUARDANDO RESUBMISSÃO
SELECT d.id, d.titulo, d.versao, os.numero AS os_numero,
       u_proj.nome AS projetista, u_proj.email,
       d.data_rejeicao,
       d.observacoes_internas,
       DATEDIFF(NOW(), d.data_rejeicao) AS dias_desde_rejeicao
FROM desenhos_tecnicos d
INNER JOIN ordens_servico os ON os.id = d.os_id
LEFT JOIN usuarios u_proj ON u_proj.id = d.usuario_projetista_id
WHERE d.status = 'rejeitado'
ORDER BY d.data_rejeicao DESC;

-- 4. DESENHOS APROVADOS PRONTOS PARA PRODUÇÃO
SELECT d.id, d.titulo, d.versao, d.qualidade_exigida,
       os.numero AS os_numero, os.descricao,
       u_proj.nome AS projetista,
       DATE_FORMAT(d.data_aprovacao_gerencia, '%d/%m/%Y') AS data_aprovacao,
       COUNT(da.id) AS total_arquivos
FROM desenhos_tecnicos d
INNER JOIN ordens_servico os ON os.id = d.os_id
LEFT JOIN usuarios u_proj ON u_proj.id = d.usuario_projetista_id
LEFT JOIN desenhos_arquivos da ON da.desenho_id = d.id
WHERE d.status = 'aprovado'
GROUP BY d.id
ORDER BY d.data_aprovacao_gerencia DESC;

-- 5. HISTÓRICO COMPLETO DE UM DESENHO
SELECT dh.id, dh.acao,
       u.nome AS usuario,
       DATE_FORMAT(dh.created_at, '%d/%m/%Y %H:%i:%s') AS data_hora,
       dh.status_anterior, dh.status_novo,
       dh.detalhes, dh.endereco_ip
FROM desenhos_historico dh
LEFT JOIN usuarios u ON u.id = dh.usuario_id
WHERE dh.desenho_id = 5  -- Substituir pelo ID do desenho
ORDER BY dh.created_at DESC;

-- 6. STATUS DE APROVAÇÃO DE UM DESENHO
SELECT dap.etapa,
       dap.status,
       u.nome AS usuario_resposta,
       DATE_FORMAT(dap.data_resposta, '%d/%m/%Y %H:%i') AS data_resposta,
       dap.prazo_resposta,
       dap.observacoes,
       dap.requer_alteracoes
FROM desenhos_aprovaes dap
LEFT JOIN usuarios u ON u.id = dap.usuario_id
WHERE dap.desenho_id = 5  -- Substituir pelo ID do desenho
ORDER BY FIELD(dap.etapa, 'gerencia', 'producao', 'qualidade');

-- 7. ARQUIVOS DE UM DESENHO
SELECT da.id, da.nome_original, da.arquivo_tipo,
       ROUND(da.tamanho_bytes / 1024 / 1024, 2) AS tamanho_mb,
       u.nome AS usuario_upload,
       DATE_FORMAT(da.created_at, '%d/%m/%Y %H:%i') AS data_upload
FROM desenhos_arquivos da
LEFT JOIN usuarios u ON u.id = da.usuario_upload_id
WHERE da.desenho_id = 5  -- Substituir pelo ID do desenho
ORDER BY da.sequencia ASC;

-- 8. RESUMO POR STATUS
SELECT d.status,
       COUNT(*) AS total,
       COUNT(CASE WHEN d.prioridade = 'critica' THEN 1 END) AS criticos,
       COUNT(CASE WHEN d.prioridade = 'alta' THEN 1 END) AS altos,
       AVG(DATEDIFF(NOW(), d.created_at)) AS dias_em_fila
FROM desenhos_tecnicos d
WHERE d.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY d.status
ORDER BY total DESC;

-- 9. DESEMPENHO DE APROVAÇÃO (Últimos 30 dias)
SELECT COUNT(*) AS total_processados,
       COUNT(CASE WHEN d.status = 'aprovado' THEN 1 END) AS aprovados,
       COUNT(CASE WHEN d.status = 'rejeitado' THEN 1 END) AS rejeitados,
       ROUND((COUNT(CASE WHEN d.status = 'aprovado' THEN 1 END) / COUNT(*)) * 100, 2) AS taxa_aprovacao_pct,
       ROUND(AVG(TIMESTAMPDIFF(HOUR, d.data_submissao, d.data_aprovacao_gerencia)), 2) AS tempo_medio_horas
FROM desenhos_tecnicos d
WHERE d.data_submissao >= DATE_SUB(NOW(), INTERVAL 30 DAY);

-- 10. VERSÕES DE UM DESENHO (HISTÓRICO DE EVOLUÇÃO)
SELECT d.versao, d.status,
       COUNT(da.id) AS total_arquivos,
       DATE_FORMAT(d.created_at, '%d/%m/%Y') AS data_criacao,
       u.nome AS criado_por,
       d.observacoes_internas
FROM desenhos_technicos d
LEFT JOIN usuarios u ON u.id = d.usuario_projetista_id
LEFT JOIN desenhos_arquivos da ON da.desenho_id = d.id
WHERE d.os_id = 123
GROUP BY d.versao
ORDER BY d.versao DESC;

-- 11. DESENHOS SEM ARQUIVO (INCOMPLETOS)
SELECT d.id, d.titulo, d.versao, d.status,
       os.numero AS os_numero,
       u.nome AS projetista,
       DATEDIFF(NOW(), d.created_at) AS dias_criacao,
       d.observacoes_internas
FROM desenhos_tecnicos d
LEFT JOIN ordens_servico os ON os.id = d.os_id
LEFT JOIN usuarios u ON u.id = d.usuario_projetista_id
WHERE d.id NOT IN (SELECT DISTINCT desenho_id FROM desenhos_arquivos)
  AND d.status != 'obsoleto'
ORDER BY d.created_at DESC;

-- 12. ATRASOS EM APROVAÇÕES
SELECT d.id, d.titulo, d.versao, os.numero AS os_numero,
       dap.etapa, dap.prazo_resposta,
       DATEDIFF(NOW(), dap.prazo_resposta) AS dias_em_atraso,
       u.nome AS responsavel, u.email
FROM desenhos_tecnicos d
INNER JOIN desenhos_aprovaes dap ON dap.desenho_id = d.id
INNER JOIN ordens_servico os ON os.id = d.os_id
LEFT JOIN usuarios u ON u.id = (
    CASE dap.etapa
        WHEN 'gerencia' THEN d.usuario_gerente_id
        WHEN 'producao' THEN d.usuario_producao_id
        ELSE d.usuario_projetista_id
    END
)
WHERE dap.status = 'pendente'
  AND dap.prazo_resposta < NOW()
ORDER BY DATEDIFF(NOW(), dap.prazo_resposta) DESC;

-- 13. COMPARAR VERSÕES DE UM DESENHO
SELECT v1.versao AS versao_anterior, v2.versao AS versao_nova,
       DATEDIFF(v2.created_at, v1.created_at) AS dias_entre_versoes,
       v2.observacoes_internas AS motivo_atualizacao,
       u.nome AS atualizado_por
FROM desenhos_tecnicos v1
INNER JOIN desenhos_tecnicos v2 ON v2.os_id = v1.os_id
LEFT JOIN usuarios u ON u.id = v2.usuario_projetista_id
WHERE v1.os_id = 123
  AND v2.versao > v1.versao
ORDER BY v1.versao ASC;

-- 14. ESTATÍSTICAS POR PROJETISTA (30 dias)
SELECT u.id, u.nome,
       COUNT(d.id) AS total_desenhos,
       COUNT(CASE WHEN d.status = 'aprovado' THEN 1 END) AS aprovados,
       COUNT(CASE WHEN d.status = 'rejeitado' THEN 1 END) AS rejeitados,
       ROUND((COUNT(CASE WHEN d.status = 'aprovado' THEN 1 END) / COUNT(d.id)) * 100, 2) AS taxa_aprovacao_pct,
       ROUND(AVG(TIMESTAMPDIFF(HOUR, d.data_submissao, COALESCE(d.data_aprovacao_gerencia, NOW()))), 2) AS tempo_medio_horas
FROM usuarios u
INNER JOIN desenhos_tecnicos d ON d.usuario_projetista_id = u.id
WHERE d.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY u.id, u.nome
ORDER BY total_desenhos DESC;

-- 15. LISTAR DESENHOS CRÍTICOS OU ATRASADOS
SELECT d.id, d.titulo, d.versao, d.prioridade,
       os.numero AS os_numero,
       d.status,
       CASE
           WHEN d.status = 'rejeitado' THEN DATEDIFF(NOW(), d.data_rejeicao)
           WHEN d.status IN ('submetido', 'em_revisao') THEN DATEDIFF(NOW(), d.data_submissao)
           ELSE DATEDIFF(NOW(), d.created_at)
       END AS dias_pendente,
       u_proj.nome AS projetista
FROM desenhos_tecnicos d
INNER JOIN ordens_servico os ON os.id = d.os_id
LEFT JOIN usuarios u_proj ON u_proj.id = d.usuario_projetista_id
WHERE (
    d.prioridade = 'critica'
    OR (d.status = 'rejeitado' AND DATEDIFF(NOW(), d.data_rejeicao) > 7)
    OR (d.status IN ('submetido', 'em_revisao') AND DATEDIFF(NOW(), d.data_submissao) > 5)
)
ORDER BY d.prioridade DESC, dias_pendente DESC;

-- 16. LISTAR TODOS OS DESENHOS APROVADOS DE MÚLTIPLAS O.S.
SELECT d.id, d.titulo, d.versao, d.qualidade_exigida,
       os.numero AS os_numero, os.descricao AS os_descricao,
       u_proj.nome AS projetista,
       u_ger.nome AS aprovado_por,
       DATE_FORMAT(d.data_aprovacao_gerencia, '%d/%m/%Y') AS data_aprovacao
FROM desenhos_tecnicos d
INNER JOIN ordens_servico os ON os.id = d.os_id
LEFT JOIN usuarios u_proj ON u_proj.id = d.usuario_projetista_id
LEFT JOIN usuarios u_ger ON u_ger.id = d.usuario_gerente_id
WHERE d.status = 'aprovado'
  AND os.cliente_id = 5  -- Substituir pelo ID do cliente
  AND d.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
ORDER BY d.created_at DESC;

-- 17. LIMPAR DESENHOS OBSOLETOS (CUIDADO!)
-- DELETE FROM desenhos_tecnicos
-- WHERE status = 'obsoleto'
--   AND updated_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- 18. BACKUPS - EXPORTAR ESTRUTURA COMPLETA
SELECT 'DESENHO' AS tipo, d.id, d.titulo, d.versao, d.status,
       d.data_submissao, d.data_aprovacao_gerencia,
       (SELECT COUNT(*) FROM desenhos_arquivos WHERE desenho_id = d.id) AS arquivos
FROM desenhos_tecnicos d
WHERE d.created_at >= DATE_SUB(NOW(), INTERVAL 180 DAY)
ORDER BY d.id DESC;

-- 19. ÍNDICES PARA OTIMIZAÇÃO
-- SHOW INDEX FROM desenhos_tecnicos;
-- SHOW INDEX FROM desenhos_arquivos;
-- SHOW INDEX FROM desenhos_aprovaes;
-- SHOW INDEX FROM desenhos_historico;

-- 20. ANÁLISE DE TAMANHO DE ARQUIVOS
SELECT ROUND(SUM(da.tamanho_bytes) / 1024 / 1024 / 1024, 2) AS tamanho_total_gb,
       COUNT(*) AS total_arquivos,
       COUNT(DISTINCT da.desenho_id) AS total_desenhos,
       ROUND(AVG(da.tamanho_bytes) / 1024 / 1024, 2) AS tamanho_medio_mb,
       MAX(da.tamanho_bytes) / 1024 / 1024 AS maior_arquivo_mb
FROM desenhos_arquivos da;

-- ===== VIEWS ÚTEIS (OPCIONAL) =====

-- View: Desenhos Pendentes de Aprovação
-- CREATE OR REPLACE VIEW v_desenhos_pendentes AS
-- SELECT d.id, d.titulo, d.versao, d.prioridade,
--        os.numero AS os_numero,
--        dap.etapa, dap.prazo_resposta,
--        DATEDIFF(dap.prazo_resposta, NOW()) AS dias_restantes
-- FROM desenhos_tecnicos d
-- INNER JOIN desenhos_aprovaes dap ON dap.desenho_id = d.id
-- INNER JOIN ordens_servico os ON os.id = d.os_id
-- WHERE dap.status = 'pendente'
--   AND d.status IN ('submetido', 'em_revisao')
-- ORDER BY dap.prazo_resposta ASC;

-- View: Status de Todos os Desenhos
-- CREATE OR REPLACE VIEW v_desenhos_status AS
-- SELECT d.id, d.titulo, d.versao, d.status, d.prioridade,
--        os.numero AS os_numero,
--        COUNT(da.id) AS total_arquivos,
--        GROUP_CONCAT(dap.etapa, ',') AS etapas_pendentes
-- FROM desenhos_tecnicos d
-- LEFT JOIN ordens_servico os ON os.id = d.os_id
-- LEFT JOIN desenhos_arquivos da ON da.desenho_id = d.id
-- LEFT JOIN desenhos_aprovaes dap ON dap.desenho_id = d.id AND dap.status = 'pendente'
-- GROUP BY d.id;

-- ===== MAINTENANCE =====

-- Verificar integridade referencial
-- SELECT d.id FROM desenhos_tecnicos d
-- WHERE NOT EXISTS (SELECT 1 FROM ordens_servico WHERE id = d.os_id);

-- Reparar sequência de IDs
-- ALTER TABLE desenhos_tecnicos AUTO_INCREMENT = (SELECT MAX(id) + 1 FROM desenhos_tecnicos);
