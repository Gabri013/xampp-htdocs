# Checklist de Teste Manual - Fluxo do Vendedor 100%

## Usuário de Teste
- **Nome:** nilton
- **Email:** nilton@cozinca.com.br
- **Senha:** nilton
- **Tipo:** vendedor

## Servidor
- ✅ Servidor PHP rodando em http://localhost:8080
- ✅ Browser preview disponível

## Teste 1: Login com Usuário Nilton
- [ ] Acessar http://localhost:8080/modules/auth/login.php
- [ ] Preencher email: nilton@cozinca.com.br
- [ ] Preencher senha: nilton
- [ ] Clicar em "Entrar"
- [ ] Verificar se o login foi bem-sucedido (redirecionado para index.php ou dashboard)

## Teste 2: Dashboard do Vendedor
- [ ] Após login, acessar http://localhost:8080/modules/vendas/dashboard_vendedor.php
- [ ] Verificar se a sidebar está visível com logo "Cozinca Inox"
- [ ] Verificar se o título "Dashboard" aparece
- [ ] Verificar se as 4 abas de período estão visíveis (Hoje, Esta semana, Este mês, Acumulado)
- [ ] Clicar em cada aba e verificar se a URL muda corretamente (?periodo=hoje, ?periodo=semana, etc.)
- [ ] Verificar se as 4 métricas estão visíveis (Vendas realizadas, Valor faturado, O.S. em produção, Vendas concluídas)
- [ ] Verificar se as 6 ações rápidas estão visíveis
- [ ] Verificar se os 2 cards recentes estão visíveis (Vendas recentes, O.S. que precisam de atenção)
- [ ] Verificar se há alerta de orçamentos vencidos (se aplicável)

## Teste 3: Sidebar - Navegação
- [ ] Verificar se o link "Dashboard" está ativo (cor diferente)
- [ ] Clicar em "O.S." e verificar se redireciona para /modules/os/vendedor.php
- [ ] Clicar em "Orçamentos" e verificar se redireciona para /modules/orcamentos/index.php
- [ ] Verificar se há badges de notificação (números em laranja)
- [ ] Clicar em "Nova venda" e verificar se redireciona corretamente
- [ ] Clicar em "Novo orçamento" e verificar se redireciona corretamente
- [ ] Clicar em "Lançar O.S." e verificar se redireciona corretamente
- [ ] Clicar em "Clientes" e verificar se redireciona corretamente
- [ ] Clicar em "Produtos" e verificar se redireciona corretamente
- [ ] Clicar em "Faturamento" e verificar se redireciona corretamente
- [ ] Clicar em "Relatórios" e verificar se redireciona corretamente
- [ ] Clicar em "Notificações" e verificar se redireciona corretamente
- [ ] Voltar para "Dashboard" e verificar se está ativo novamente

## Teste 3: Lista de O.S.
- [ ] Acessar http://localhost:8080/modules/os/vendedor.php
- [ ] Verificar se a sidebar está visível
- [ ] Verificar se o título "Ordens de Serviço" aparece
- [ ] Verificar se os filtros por status estão visíveis
- [ ] Clicar em cada filtro (Todas, Em produção, Em revisão, Aguardando, Concluídas)
- [ ] Verificar se a URL muda com o parâmetro status
- [ ] Preencher o campo de busca com um nome de cliente
- [ ] Clicar no botão de busca e verificar se a URL tem o parâmetro busca
- [ ] Verificar se a tabela está visível
- [ ] Verificar se as colunas estão corretas (Número, Cliente, Venda, Prazo, Prioridade, Status, Vendedor)
- [ ] Verificar se há badges coloridos por status
- [ ] Verificar se há indicadores de prazo (cores diferentes para atrasado, próximo, ok)

## Teste 5: Layout Responsivo
- [ ] Abrir o DevTools (F12)
- [ ] Ativar o modo de dispositivo móvel
- [ ] Selecionar um dispositivo mobile (iPhone SE, por exemplo)
- [ ] Verificar se a sidebar está escondida
- [ ] Verificar se o conteúdo principal está visível
- [ ] Voltar para desktop e verificar se a sidebar aparece novamente

## Teste 5: Integração Completa
- [ ] Fazer login no sistema (se necessário)
- [ ] Acessar o dashboard do vendedor
- [ ] Verificar todas as métricas
- [ ] Navegar para a lista de O.S.
- [ ] Usar os filtros
- [ ] Fazer uma busca
- [ ] Voltar para o dashboard
- [ ] Clicar em uma ação rápida (Novo orçamento, por exemplo)
- [ ] Verificar se redireciona corretamente
- [ ] Voltar para o dashboard
- [ ] Verificar se os cards recentes estão atualizados

## Teste 7: Visual e UX
- [ ] Verificar se as cores estão corretas (principal #D85A30)
- [ ] Verificar se a tipografia está legível
- [ ] Verificar se há espaçamento adequado entre elementos
- [ ] Verificar se os hover effects funcionam
- [ ] Verificar se os badges estão visíveis e legíveis
- [ ] Verificar se os ícones estão carregando corretamente
- [ ] Verificar se não há erros de JavaScript no console (F12 > Console)

## Resultado Final
- [ ] Login com usuário nilton funcionou
- [ ] Todos os testes passaram
- [ ] Fluxo do vendedor 100% funcional
- [ ] Layout moderno implementado corretamente
- [ ] Navegação fluida entre páginas
- [ ] Responsivo em mobile

## Observações
- Anote qualquer erro encontrado
- Anote qualquer comportamento inesperado
- Tire screenshots se necessário
