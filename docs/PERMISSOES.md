# Mapa de Permissões — ERP Cozinca Inox

> Gerado em 11/07/2026 a partir de varredura do código **e validação prática**
> (login real com as 18 contas de teste × 43 páginas = 774 acessos verificados).
> Regra geral: quem não está na lista de uma página é redirecionado; quem não
> está autorizado em um endpoint recebe erro/401.

## 1. Perfis do sistema

| Perfil | Papel |
|---|---|
| `master` | Administrador — acesso total |
| `vendedor` | Comercial: CRM, orçamentos, vendas, clientes/produtos, faturamento, relatórios |
| `projetista` | Projetos + **opera a etapa de engenharia** (projetista e engenharia = mesma conta) |
| `gerente` | Gestão da produção: aprovação, liberação, estatísticas, expediente, CRM |
| `producao` | Produção geral: painéis e todos os setores |
| `programacao, corte, dobra, tubo, solda, mobiliario, coccao, refrigeracao, acabamento, montagem, embalagem, finalizacao` | Cada setor: **apenas o próprio painel** |
| `engenharia` | Setor de engenharia (equivalente ao projetista na produção) |
| `dashboard_producao` | TV do chão de fábrica: apenas o panorama |

## 2. Acesso às páginas (validado na prática)

### Comercial / CRM
| Página | master | vendedor | gerente | projetista | producao | setores | TV |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| CRM Pipeline + Contatos + Oportunidade | ✅ | ✅ | ✅ | — | — | — | — |
| Orçamentos (listar/criar/converter) | ✅ | ✅ | — | — | — | — | — |
| Vendas (listar/nova/editar/detalhes/imprimir) | ✅ | ✅ | — | — | — | — | — |
| Conteúdos Digitais | ✅ | ✅ | — | — | — | — | — |
| Dashboard do Vendedor | ✅ | ✅ | — | — | — | — | — |
| Relatórios (+ imprimir) | ✅ | ✅ | — | — | — | — | — |

### Cadastros
| Página | master | vendedor | demais |
|---|:-:|:-:|:-:|
| Clientes / Produtos (+ imprimir tabela) | ✅ | ✅ | — |
| **Usuários** | ✅ | — | — |

### Financeiro
| Página | master | vendedor | demais |
|---|:-:|:-:|:-:|
| Faturamento | ✅ | ✅ (só as próprias vendas) | — |
| Contas a Receber / Contas a Pagar | ✅ | — | — |

### Produção
| Página | master | gerente | producao | projetista | vendedor | setor X | TV |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| Painéis dos 13 setores | ✅ | ✅ | ✅ | ✅¹ | ✅¹ | só o seu | — |
| Painel Produção (os/producao) | ✅ | ✅ | ✅ | — | ✅ | ✅ (leitura) | — |
| Painel do Gerente (aprovação/liberação) | ✅ | ✅ | — | — | — | — | — |
| Painel do Projetista (os/projetista) | ✅ | — | — | ✅ | — | — | — |
| Projetista/Setores (projetista/index) | ✅ | ✅ | ✅ | ✅ | — | — | — |
| Lista de O.S. (os/vendedor) | ✅ | — | — | ✅ | ✅ | — | — |
| Kanban de O.S. | ✅ | ✅ | ✅ | ✅ | ✅ (só as suas) | — | — |
| Detalhe da O.S. (leitura) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — |
| Nova O.S. independente | ✅ | ✅ | — | ✅ | ✅ | — | — |
| Engenharia de Produto | ✅ | ✅ | ✅ | — | — | — | — |
| Importar CSV de engenharia | ✅ | ✅ | ✅ | ✅ | — | — | — |
| Estatísticas / gráficos | ✅ | ✅ | ✅ | — | — | — | — |
| Dashboard Produção (TV) | ✅ | ✅ | ✅ | — | — | — | ✅ |
| Checkup de qualidade / Finalização | ✅ | ✅ | — | — | ✅ | só `finalizacao` | — |
| Imprimir O.P. | ✅ | ✅ | ✅ | ✅ | — | — | — |
| Imprimir etiqueta | ✅ | ✅ | — | — | — | só `finalizacao` | — |
| Controle de Expediente | ✅ | ✅ | — | — | — | — | — |

¹ Projetista/vendedor têm acesso de leitura aos painéis de setor, **exceto Finalização** (só master/gerente/finalizacao/vendedor).

### Administração e utilidades
| Página | master | demais |
|---|:-:|:-:|
| Logs do Sistema (retorno de etapa + expediente) | ✅ | — |
| Logs de Exclusão de vendas | ✅ | — |
| **Escanear O.P./O.S.** | ✅ | ✅ todos os logados |
| **Visualizador 3D** | ✅ | ✅ todos os logados |
| **Notificações** | ✅ | ✅ todos os logados |

## 3. Operação de etapas da produção (workflow — validado com 251 checks)

Quem pode **iniciar/finalizar/retornar** cada etapa (api/producao.php):

| Perfil | Etapas que opera |
|---|---|
| `master`, `gerente`, `producao` | **Todas** |
| `projetista`, `engenharia` | Somente **engenharia** |
| Cada setor (`corte`, `dobra`, `tubo`…) | Somente a **própria etapa** |
| `vendedor`, `dashboard_producao` | **Nenhuma** (só visualizam) |

Regras adicionais do fluxo:
- Avançar exige apontamento iniciado + anexo de projeto na O.S.
- Só avança para etapa **posterior**; voltar exige "retornar etapa" com justificativa
- Iniciar etapa exige **expediente aberto** do operador
- Aprovação de proposta: `master`, `gerente`, `vendedor`, `projetista`
- Liberação parcial (desmembrar O.S./item): `master`, `projetista`, `gerente`

## 4. Endpoints (api/)

| Endpoint | Quem usa |
|---|---|
| `producao.php` (iniciar/finalizar/retornar etapa) | logado + regra de etapa acima |
| `os_update_status.php` (kanban de O.S.) | logado + transição validada |
| `crm_move.php` (pipeline CRM) | master, vendedor (só as suas), gerente |
| `clientes.php` (cadastro rápido) | master, vendedor |
| `excluir_venda.php` | master, vendedor |
| `notificacoes_worker.php` (motor) | master, gerente |
| `export.php` | logado; não-master exporta só as próprias vendas |
| `os.php`, `os_arquivos.php`, `dashboard_data.php`, `realtime.php` | qualquer logado |

## 5. Ações internas com guarda extra (além da página)

- Excluir contato do CRM: só `master`
- Excluir/cancelar venda: só `master`/`vendedor` via POST
- Ações de escrita no detalhe da O.S. (gerar OP, proposta, anexos): `master`, `vendedor`, `projetista`, `gerente` — setores têm somente leitura
- Botão "Liberar" na lista de O.S.: `master`, `gerente`
- Processar motor de notificações: `master`, `gerente`

## 6. Contas de teste (senha `teste123`)

`<perfil>@teste.cozinca.com.br` — uma para cada perfil da tabela do item 1
(ids 25–42 no banco). O projetista de teste é a conta unificada
projeto+engenharia; não há conta separada de engenharia.
