# Migração Laravel - Guia de Implementação

Arquivos criados em `htdocs/cozinca-novo/`:

## Models
- Usuario.php - autenticação compartilhada
- UsuarioExpediente.php - controle de expediente
- OrdemServico.php - ordens de serviço
- Notificacao.php - notificações
- NotificacaoEnvio.php - envios de notificação
- OsEtapaProducao.php - etapas de produção
- OsHistoricoStatus.php - histórico de status
- LogRetornoEtapa.php - logs de retorno de etapa
- OsArquivo.php - arquivos de OS
- OsItem.php - itens de OS
- OsObservacao.php - observações de OS
- OrdemProducao.php - ordens de produção
- OrdemProducaoItem.php - itens de OP
- Cliente.php - clientes
- Venda.php - vendas
- VendaItem.php - itens de venda
- ContaReceber.php - contas a receber
- ContaPagar.php - contas a pagar
- TipoCaixa.php - tipos de caixa
- Produto.php - produtos
- ProdutoCategoria.php - categorias
- EstruturaProduto.php - estrutura de produto
- ComponenteProduto.php - componentes de estrutura
- Insumo.php - insumos

## Controllers
- NotificacaoController.php
- FinanceiroController.php
- ContaPagarController.php
- ProducaoController.php
- VendaController.php
- OSController.php

## Services
- OSWorkflowStateMachine.php - State Machine para workflow

## Views
- layouts/app.blade.php
- notificacoes/index.blade.php
- financeiro/index.blade.php
- contas-pagar/index.blade.php
- producao/index.blade.php
- vendas/index.blade.php
- os/index.blade.php
- os/show.blade.php

## Rotas (routes/web.php)
- /novo/notificacoes - listagem
- /novo/financeiro - contas a receber
- /novo/contas-pagar - contas a pagar
- /novo/producao - dashboard produção
- /novo/vendas - listagem vendas
- /novo/os - listagem ordens

## Arquivos do legado modificados
- config/config.php
- includes/auth.php
- config/config.local.php
- legado/ponte_auth.php (criado)