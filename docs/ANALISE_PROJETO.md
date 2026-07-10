# ANALISE DO PROJETO — SISTEMA COZINCA

> Documento de diagnóstico (levantamento arquitetural + fluxos principais + contratos de API + riscos)

## 1) Visão geral da arquitetura
- **Stack**: PHP com integração via rotas via páginas (`modules/`, `os/`) e backend JSON em `api/`.
- **Entry point**: `index.php`
  - Faz `requireLogin()` via `config/config.php`.
  - Redireciona por `tipo` do usuário:
    - `vendedor` → `modules/vendas/dashboard_vendedor.php`
    - `projetista` → `modules/projetista/index.php`
  - Para master/gerente: monta dashboard com métricas via SQL.

### Estrutura de pastas (principais)
- `config/`
  - `config.php`: constantes, paths, includes do core (auth/functions/notificações) e inicia sessão.
  - `database.php`: conexão PDO (singleton) com alternância local/prod.
- `includes/`
  - `auth.php`: autenticação e helpers por perfil.
  - `functions.php`: utilitários (sanitize, upload, formatação) e helpers de schema.
  - `workflow.php`: motor de status/etapas.
  - `expediente.php`: expediente diário + cálculo de tempo.
  - `notificacoes.php`: geração de eventos e envio via fila.
  - `financeiro.php`, `engenharia.php`, `permissoes.php`, `scripts.php`, `validators.php`.
- `api/`
  - endpoints JSON com `POST/GET` e validações.
- `modules/`
  - telas por área (auth/login, vendas, engenharia, financeiro, notificações, etc.).
- `os/`
  - telas e fluxos por setor/etapa (produção).

## 2) Autenticação e autorização
### 2.1 `includes/auth.php`
- `login($email,$senha)`: consulta `usuarios` e valida `password_verify`, exige `status='ativo'`.
- Sessão:
  - `$_SESSION['usuario_id']`, `$_SESSION['usuario_nome']`, `$_SESSION['usuario_email']`, `$_SESSION['usuario_tipo']`.
- Autorização:
  - `isLoggedIn()`, `hasPermission($tipos_permitidos)`, `requireLogin()`, `requirePermission()`.
- Helpers por perfil:
  - `isMaster`, `isVendedor`, `isProjetista`, `isGerente`, `isProducao`, etc.

### 2.2 Sistema granular (parcial no diagnóstico)
- `includes/permissoes.php`:
  - Tabelas: `permissoes`, `usuario_permissoes`.
  - Seed de permissões e função `hasPermissao()`.
- Observação: nos endpoints lidos, o padrão dominante é `hasPermission()` por tipo; o sistema granular aparece como base futura/alternativa.

## 3) Motor do fluxo (OS → etapas → status)

### 3.1 `includes/workflow.php`
- Status válidos de OS:
  - `pendente`, `em_projeto`, `proposta`, `em_revisao`, `em_producao`, `concluida`, `cancelada`.
- Etapas válidas e ordem canônica (fluxo produtivo):
  - `programacao`, `corte`, `mobiliario`, `coccao`, `refrigeracao`, `embalagem`, `engenharia`, `dobra`, `tubo`, `solda`, `concluida`.
- Validações:
  - `validateOSStatusTransition(from,to)`: regra explícita por estado.
  - `validateEtapaTransition(from,to)`: impede pular etapas (diferença > 1), impede retrocesso de `concluida`, permite caminho especial com `engenharia`.
  - `validateUserCanOperateEtapa(etapa,userType)`: permissão por `usuario_tipo` via mapa de etapas.

### 3.2 `includes/expediente.php`
- Controla “expediente do dia” por usuário:
  - `usuarios_expedientes`: 1 registro por usuário/dia com `status em_trabalho|encerrado`.
  - `usuarios_expediente_logs`: logs do início/fim.
- Cálculo de tempo:
  - `calcularSegundosTrabalhadosNoPeriodo()`
  - usado no `api/producao.php` para somar `tempo_total_segundos` da etapa.

## 4) Contratos e fluxos mais importantes na API

### 4.1 `api/os.php` — Geração de OP e GET detalhado da OS
**Ações POST**
- `acao=gerar_op_individual`
  - Entrada: `os_id`
  - Cria/retém `ordens_producao` vinculada à OS.
  - Atualiza `ordens_servico`:
    - `status='em_producao'`
    - `etapa_atual='corte'`
  - Insere histórico em `os_historico_status`.
  - Insere itens em `ordens_producao_itens` a partir de `os_itens`.

- `acao=gerar_op_massa`
  - Entrada: `os_ids` (lista separada por vírgula)
  - Repete o processo para cada OS dentro de transação.

**GET**
- Entrada: `?id=`
- Retorna JSON:
  - detalhes da OS + cliente + venda
  - `itens` (itens comerciais/materiais)
  - `arquivos` via `os_arquivos`
  - `etapas_planejadas` (via `os_etapas_producao` ou fallback de engenharia)
  - `componentes_venda`
  - `ultimo_recall` (via `logs_retorno_etapa`)
  - `checkups` (novo/legado)
  - `historico` (via `os_historico_status`, compatível com colunas de data)

### 4.2 `api/producao.php` — Iniciar / Finalizar / Retornar etapas
**Autorização**: exige login.
- Lê OS (`ordens_servico`) e valida permissão via `validateUserCanOperateEtapa(etapa_atual, usuario_tipo)`.

#### `acao=iniciar_etapa`
- Valida:
  - expediente do dia do usuário está em `em_trabalho`
  - etapa informada é a `etapa_atual` da OS
- Upsert em `os_etapas_producao` com:
  - `status='em_andamento'`, `data_inicio=NOW()`, `usuario_id`.

#### `acao=finalizar_etapa`
- Valida:
  - etapa atual (`etapa` recebida deve ser `etapa_atual`)
  - transição de etapa via `validateEtapaTransition()`
  - valida “primeiro passo” (`validateFirstProductionStepStarted`)
  - valida apontamento/anexos via `validateStepTrackingAndAttachment()`
    - exige pelo menos um registro em `os_arquivos` com tipos:
      - `projeto_pdf`, `projeto_dxf`, `projeto_foto`, `projeto`
- Calcula tempo:
  - soma tempo trabalhado no período por usuário.
- Atualiza:
  - `os_etapas_producao`: `status='concluida'`, `data_fim=NOW()`, `tempo_total_segundos`
  - `ordens_servico`: `etapa_atual=etapa_destino`, `status=concluida|em_producao`
  - se concluída: `vendas.status='concluida'`.

#### `acao=retornar_etapa`
- Valida:
  - `justificativa` obrigatória
  - retorno só permitido para etapa anterior válida no fluxo
- Efeitos:
  - insere em `logs_retorno_etapa`
  - atualiza `ordens_servico` para a etapa anterior (ou `autorizacao`/projetista)
  - insere observação em `os_observacoes`
  - insere histórico em `os_historico_status`
  - remove registros da etapa atual do produtor em `os_etapas_producao`
  - se retorno não for `autorizacao`, rebaixa `os_etapas_producao` da etapa anterior para `pendente` e zera apontamentos.

### 4.3 `includes/scripts.php` → `api/os_update_status.php` (Kanban)
- No front (Kanban), ao arrastar cartão:
  - `fetch('/api/os_update_status.php', { id: itemId, status: newStatus })`
- `api/os_update_status.php`:
  - valida status via `getValidOSStatuses()` e `validateOSStatusTransition()`
  - atualiza `ordens_servico.status`.

### 4.4 Notificações
- `includes/notificacoes.php`:
  - `ensureNotificacoesSchema()` cria/ajusta tabelas:
    - `notificacoes`
    - `notificacoes_envios`
  - `gerarEventosNotificacoes()` gera eventos em base de queries:
    - OS atrasada (por `data_termino` e `status NOT IN concl/cancel`)
    - OS aguardando (pendente/em_projeto há X horas)
    - venda aguardando pagamento (`contas_receber` pendente/atrasado e vencimento)
  - `processarMotorNotificacoes()`:
    - gera eventos
    - envia fila (email com `mail()`, whatsapp com webhook via curl)
- `api/notificacoes_worker.php` executa o motor e retorna JSON.

### 4.5 Financeiro (e exclusão de venda em cascata)
- `includes/financeiro.php`:
  - `ensureFinanceiroSchema()` cria tabelas e altera `vendas` com campos financeiros.
  - `faturarVenda()`, `gerarContasReceberDaVenda()` e `cancelarContasReceberPorVenda()`.
- `api/excluir_venda.php`:
  - exige POST e permissão (master/vendedor)
  - loga exclusão em `logs_exclusao_vendas`
  - chama `cancelarContasReceberPorVenda()`
  - atualiza `ordens_servico` para `cancelada`
  - atualiza `vendas` para `cancelada`.

### 4.6 Engenharia (componentes e planejamento)
- `includes/engenharia.php`:
  - `ensureEngenhariaSchema()` cria tabelas ligadas a engenharia:
    - `insumos`, `estrutura_produto`, `componentes_produto`, `os_etapas_producao`, `os_itens`, `os_arquivos`, etc.
  - Planejamento:
    - `getPlanejamentoEtapasPorVenda(vendaId)`
    - `getPlanejamentoEtapasPorOS(osId, vendaId)`
  - Sincronização:
    - `sincronizarPlanejamentoOS()` cria `os_etapas_producao` pendentes que faltam.
  - Componentes:
    - monta componentes por estrutura a partir de `componentes_produto`.

## 5) Banco e schema-evolução em runtime (padrão observado)
- Diversos módulos executam `CREATE TABLE IF NOT EXISTS` e `ALTER TABLE` durante requests.
- Há mitigação por `shouldRunSchemaSync($chave, $ttl)` (cache temporário), mas ainda assim:
  - depende de permissões do usuário do DB
  - aumenta risco de race conditions
  - pode impactar performance e estabilidade.

## 6) Achados e riscos (priorizados)
1) **Schema sync em runtime**
   - Impacto: alto (risco operacional/concorrência e latência).
   - Observado em `notificacoes`, `expediente`, `engenharia`, `financeiro`, etc.

2) **Permissão por `usuario_tipo` acoplada a chaves do map**
   - Se `tipo` do usuário não casar exatamente com as chaves esperadas, cai em fallback.

3) **`api/os.php` com muitas responsabilidades**
   - Geração de OP + retorno completo detalhado + compatibilidades legado.
   - Impacto: alto para manutenção e testes.

4) **Validação forte de tracking/anexos obrigatórios**
   - `validateStepTrackingAndAttachment` exige arquivos e tipos.
   - Pode gerar “travamentos” se UI não inserir corretamente.

5) **Estoque como stub**
   - `api/estoque_data.php` retorna array fixo.
   - Se front assume estoque real, haverá inconsistência.

6) **Consistência do recall/retorno de etapas**
   - `retornar_etapa` remove registros em `os_etapas_producao` do produtor e escreve histórico/log.
   - Deve ser auditável e consistente com UI.

## 7) Próximos passos recomendados (para completar o diagnóstico 100%)
- Ler integralmente a pasta `os/` (telas por setor e como chamam `api/producao.php`/`api/os.php`).
- Ler os restantes arquivos de `api/` e `modules/` para mapear todos endpoints consumidos pelo front.
- Conferir consistência de nomenclatura de tipos (`usuario_tipo`, etapas, tipos de arquivo em `os_arquivos`).
- Revisar como `os_arquivos` é gravado pela UI (para garantir que `tipo` está correto).

---
### Sumário final
O sistema implementa um ERP voltado ao fluxo **Vendas/Orçamentos → OS → geração de OP → execução por etapas com expediente/tempo → histórico/recall → fechamento (concluída) e sincronização com financeiro e notificações**.

O motor de transição e validação é concentrado em `includes/workflow.php` e o controle de tempo em `includes/expediente.php`, enquanto os endpoints mais críticos são `api/os.php` e `api/producao.php`.

