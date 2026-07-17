# 🔄 PLANO COMPLETO DE MESCLAGEM DE DADOS

**Objetivo**: Integrar banco de dados existente (10.129.76.12) com novo Cozinka ERP

**Status**: ⏳ AGUARDANDO CONCLUSÃO DE 100% DAS TAREFAS

**Data Planejada**: 2026-07-17

---

## ✅ CHECKLIST DE TAREFAS - STATUS ATUAL

### Fase 1: Funcionalidades Base
- [x] Exportação de dados (Excel, PDF, CSV)
- [x] Controle de dados (100% validação)
- [x] Etiqueta com QR code
- [x] Ordem de Produção
- [x] Desenho Técnico
- [x] Fluxo de Aprovação
- [x] Design padronizado (sidebar)
- [x] Validação 100% (anti-duplicidade)
- [x] Importação JOTEC

### Fase 2: Avançada (Em Andamento)
- [ ] Design Review (4 módulos)
- [ ] 362 Skills Ativadas
- [ ] Stress Test (1000+ usuários)
- [ ] Relatório Final
- [ ] **QUANDO TUDO ACIMA ✅ = COMEÇAR MESCLAGEM**

---

## 📊 BANCO EXISTENTE ANALISADO

**Host**: 10.129.76.12  
**Database**: dbcozinca  
**Charset**: latin1 (utf8mb4 em tabelas)  
**MySQL Version**: 5.6.26

### Tabelas Identificadas:
```
✅ centro_custo (id, nome, descricao, ativo, created_at)
✅ clientes (id, razao_social, nome_fantasia, cnpj_cpf, email, telefone, endereco, etc)
   └─ Dados: 51+ clientes cadastrados (IDs 20-51)

Outras tabelas esperadas (a analisar):
⏳ usuarios
⏳ vendas
⏳ ordens_servico
⏳ estoque
⏳ fornecedores
⏳ ... (total ~20+ tabelas)
```

---

## 🔄 PROCESSO DE MESCLAGEM (7 ETAPAS)

### **ETAPA 1: Análise Completa** (Quando iniciar)
```
1️⃣ Ler arquivo SQL completo
2️⃣ Analisar estrutura de TODAS as tabelas
3️⃣ Identificar dados existentes
4️⃣ Mapear correspondências com novo ERP
5️⃣ Detectar conflitos de IDs
6️⃣ Planejar ordem de importação
```

### **ETAPA 2: Backup e Preparação**
```
1️⃣ Criar backup do banco atual (safety)
2️⃣ Criar nova instância limpa do Cozinka
3️⃣ Verificar integridade de dados
4️⃣ Testar em ambiente de staging
5️⃣ Validar permissions/usuarios
```

### **ETAPA 3: Mapeamento de Dados**
```
Tabelas a Mesclar:

BANCO ATUAL → COZINKA ERP
────────────────────────
clientes → clientes
  └─ id: AUTO_INCREMENT
  └─ razao_social, nome_fantasia
  └─ cnpj_cpf, inscricao_estadual
  └─ endereco, cidade, estado, cep
  └─ telefone, email
  └─ observacoes

centro_custo → departamentos/setores
  └─ Mapear 1:1

usuarios → usuarios
  └─ Preservar credenciais
  └─ Manter roles/permissions

vendas → vendas
  └─ Manter histórico
  └─ Vincular a clientes

ordens_servico → ordens_servico
  └─ Manter rastreamento
  └─ Validar FK integridade

estoque → estoque_materias_primas
  └─ Sincronizar saldos
  └─ Preservar histórico

fornecedores → fornecedores
  └─ Mesclar dados duplicados
  └─ Validar CNPJ
```

### **ETAPA 4: Tratamento de Conflitos**
```
Conflitos Esperados:

1️⃣ IDs AUTO_INCREMENT
   ├─ Solução: SET AUTO_INCREMENT
   ├─ Verificar max(id) em cada tabela
   └─ Ajustar sequências

2️⃣ Dados Duplicados
   ├─ Solução: Validar CNPJ/email
   ├─ Mesclar registros similares
   └─ Preservar dados mais completos

3️⃣ Foreign Keys Quebradas
   ├─ Solução: Desabilitar temporariamente
   ├─ Importar dados
   ├─ Validar integridade
   └─ Reativar constraints

4️⃣ Timestamps
   ├─ Solução: Preservar created_at
   ├─ Atualizar updated_at
   └─ Manter auditoria

5️⃣ Encoding UTF-8
   ├─ Solução: Converter latin1 → utf8mb4
   ├─ Validar caracteres especiais
   └─ Testar acentuação
```

### **ETAPA 5: Validações Pré-Merge**
```
Verificações Obrigatórias:

✅ Integridade Referencial
   └─ Todas as FKs válidas
   └─ Nenhum órfão

✅ Dados Duplicados
   └─ Clientes únicos (CNPJ)
   └─ Usuários únicos (email)
   └─ Vendas sem duplicação

✅ Valores Fora do Intervalo
   └─ Preços > 0
   └─ Datas válidas
   └─ CPF/CNPJ válidos

✅ Campos Obrigatórios
   └─ razao_social preenchido
   └─ email válido
   └─ telefone formatado

✅ Performance
   └─ Indexes presentes
   └─ Queries rápidas
   └─ Sem locks prolongados
```

### **ETAPA 6: Importação de Dados**
```
Ordem de Execução:

1️⃣ DROP CONSTRAINTS (desabilitar FKs)

2️⃣ IMPORTAR DADOS em ordem:
   a) usuarios
   b) departamentos/setores
   c) fornecedores
   d) clientes
   e) produtos/materias_primas
   f) estoque
   g) vendas
   h) ordens_servico
   i) apontamentos
   j) ... (demais tabelas)

3️⃣ VALIDAR CADA TABELA
   └─ Count registros
   └─ Verificar nulls
   └─ Testar FKs

4️⃣ REATIVAR CONSTRAINTS

5️⃣ VERIFICAR INTEGRIDADE
   └─ phpMyAdmin check
   └─ ANALYZE TABLE
   └─ OPTIMIZE TABLE
```

### **ETAPA 7: Pós-Merge**
```
Validações Finais:

✅ Relatório de Mesclagem
   ├─ Registros por tabela
   ├─ Registros ignorados
   ├─ Erros encontrados
   └─ Taxa de sucesso

✅ Testes de Funcionalidade
   ├─ Login com usuários antigos
   ├─ Ver clientes históricos
   ├─ Abrir vendas antigas
   ├─ Gerar ordens de serviço
   └─ Acessar estoque

✅ Performance Check
   ├─ Queries tempo resposta
   ├─ Indexes análise
   ├─ Tamanho BD
   └─ Backup novo

✅ Rollback Plan
   └─ Se erro crítico: restaurar backup
   └─ Revalidar integridade
   └─ Tentar novamente
```

---

## 📋 SCRIPT SQL SERÁ GERADO

Quando iniciar a mesclagem, será criado:

```
📁 /scripts/mesclar_banco_dados.sql (10.000+ linhas)

Conteúdo:
✅ Análise completa de dados
✅ Detecção de conflitos
✅ Transformação de IDs
✅ Conversão de encoding
✅ Importação segura
✅ Validações
✅ Relatório
```

---

## ⚠️ RISCOS IDENTIFICADOS

```
CRÍTICOS:
🔴 Perda de dados se erro em FK
🔴 Charset mismatch (latin1 vs utf8mb4)
🔴 ID conflicts em AUTO_INCREMENT

ALTOS:
🟠 Dados duplicados (clientes)
🟠 Registros órfãos
🟠 Performance durante import

MÉDIOS:
🟡 Timestamps alterados
🟡 Histórico incompleto
🟡 Auditoria quebrada

BAIXOS:
🟢 Estética de dados
🟢 Ordenação de IDs
🟢 Nomes de colunas
```

---

## 🛡️ MEDIDAS DE SEGURANÇA

```
1️⃣ BACKUP ANTES
   └─ Snapshot do banco atual
   └─ Armazenado em local seguro

2️⃣ TRANSAÇÃO MYSQL
   └─ BEGIN TRANSACTION
   └─ Se erro: ROLLBACK
   └─ Se sucesso: COMMIT

3️⃣ VALIDAÇÃO EM STAGING
   └─ Testar em cópia
   └─ Não tocar produção
   └─ Validar tudo antes

4️⃣ ROLLBACK PLAN
   └─ Se der problema: restaurar backup
   └─ Voltar ao estado anterior
   └─ Tentar novamente

5️⃣ MONITORAMENTO
   └─ Verificar cada etapa
   └─ Logs detalhados
   └─ Alertas imediatos
```

---

## 📅 CRONOGRAMA

```
QUANDO TAREFAS FOREM 100% COMPLETAS:

Dia 1: Análise
   ├─ Ler SQL completo
   ├─ Mapear todas as tabelas
   ├─ Identificar conflitos
   └─ Planejar mesclagem

Dia 2: Preparação
   ├─ Criar backup
   ├─ Preparar scripts
   ├─ Testar em staging
   └─ Validar tudo

Dia 3: Execução
   ├─ Fazer backup final
   ├─ Executar mesclagem
   ├─ Validar resultados
   ├─ Resolver problemas
   └─ Testar funcionalidades

Dia 4: Pós-Merge
   ├─ Validações finais
   ├─ Gerar relatório
   ├─ Documentar changes
   └─ Preparar produção
```

---

## ✅ STATUS FINAL ESPERADO

```
Quando mesclagem terminar:

✅ 100% dos dados migrados
✅ 0 erros críticos
✅ Integridade 100%
✅ Performance OK
✅ Todos usuários acessam
✅ Histórico preservado
✅ Sistema pronto para produção

Score: 100/100
Status: PRODUÇÃO OK 🚀
```

---

## 🎯 PRÓXIMAS ETAPAS

```
1️⃣ COMPLETAR TODAS AS 7 FASES
   └─ Workflow rodando
   └─ ETA: ~4 horas

2️⃣ VERIFICAR CONCLUSÃO
   └─ Relatório final
   └─ Score 100/100
   └─ Stress test OK

3️⃣ COMEÇAR MESCLAGEM
   └─ Usar este plano
   └─ Executar em staging
   └─ Validar tudo

4️⃣ DEPLOY EM PRODUÇÃO
   └─ Backup de segurança
   └─ Fazer merge
   └─ Monitorar

5️⃣ GO LIVE
   └─ Sistema 100% pronto
   └─ Dados históricos OK
   └─ Sem problemas
```

---

**Status**: ⏳ AGUARDANDO (Será acionado após 100% das tarefas)

**Documento de Referência**: /PLANO_MESCLAGEM_DADOS.md

**Pronto para Mesclagem**: SIM ✅

---

🔔 **AVISO**: Este plano será executado ASSIM que todas as tarefas anteriores forem 100% concluídas!
