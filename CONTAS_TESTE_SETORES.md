# Contas de Teste para Validação do ERP — Criadas em 2026-07-17

## ✓ Criação Concluída

19 contas de teste foram criadas com sucesso no banco de dados `dbcozinca`. Uma conta para cada tipo de usuário/setor do ERP.

---

## Credenciais de Acesso

**Senha padrão para todas as contas:** `123`

**URL de acesso:** http://localhost/index.php

---

## Tabela de Contas por Setor

| # | Email | Tipo | Descrição |
|---|-------|------|-----------|
| 1 | teste_master@cozinca.local | master | Administrador (acesso total) |
| 2 | teste_vendedor@cozinca.local | vendedor | Vendedor (CRM/Orçamentos) |
| 3 | teste_projetista@cozinca.local | projetista | Projetista (Desenhos/Especificações) |
| 4 | teste_gerente@cozinca.local | gerente | Gerente de Produção |
| 5 | teste_producao@cozinca.local | producao | Produção Geral (acesso a todos os setores) |
| 6 | teste_engenharia@cozinca.local | engenharia | Setor de Engenharia |
| 7 | teste_programacao@cozinca.local | programacao | Setor de Programação (CNC) |
| 8 | teste_corte@cozinca.local | corte | Setor de Corte |
| 9 | teste_dobra@cozinca.local | dobra | Setor de Dobra |
| 10 | teste_tubo@cozinca.local | tubo | Setor de Tubo |
| 11 | teste_solda@cozinca.local | solda | Setor de Solda |
| 12 | teste_mobiliario@cozinca.local | mobiliario | Setor de Mobiliário |
| 13 | teste_coccao@cozinca.local | coccao | Setor de Cocção |
| 14 | teste_refrigeracao@cozinca.local | refrigeracao | Setor de Refrigeração |
| 15 | teste_acabamento@cozinca.local | acabamento | Setor de Acabamento |
| 16 | teste_montagem@cozinca.local | montagem | Setor de Montagem |
| 17 | teste_embalagem@cozinca.local | embalagem | Setor de Embalagem |
| 18 | teste_finalizacao@cozinca.local | finalizacao | Setor de Finalização |
| 19 | teste_dashboard_producao@cozinca.local | dashboard_producao | Dashboard de Produção (monitoramento) |

---

## Fluxo Canônico de Etapas (Referência)

O ERP Cozinca possui o seguinte fluxo de produção:

1. **Autorização** (inicial)
2. **Engenharia** ← teste_engenharia@cozinca.local
3. **Programação** ← teste_programacao@cozinca.local
4. **Corte** ← teste_corte@cozinca.local
5. **Dobra** ← teste_dobra@cozinca.local
6. **Tubo** ← teste_tubo@cozinca.local
7. **Solda** ← teste_solda@cozinca.local
8. **Mobiliário** ← teste_mobiliario@cozinca.local (condicional)
9. **Cocção** ← teste_coccao@cozinca.local (condicional)
10. **Refrigeração** ← teste_refrigeracao@cozinca.local (condicional)
11. **Acabamento** ← teste_acabamento@cozinca.local
12. **Montagem** ← teste_montagem@cozinca.local
13. **Embalagem** ← teste_embalagem@cozinca.local
14. **Finalização** ← teste_finalizacao@cozinca.local
15. **Concluída** (final)

---

## Como Validar

### 1. Login com cada conta
```
Email: teste_[setor]@cozinca.local
Senha: 123
```

Cada login deve:
- ✓ Conectar com sucesso
- ✓ Mostrar o nome "Teste [Setor]" no menu
- ✓ Exibir o tipo de usuário correto
- ✓ Permanecer ativo e com status na interface

### 2. Verificar permissões
- Contas de setor devem ter acesso apenas às suas etapas
- `teste_master` e `teste_producao` devem ter acesso a todos os setores
- `teste_gerente` deve ter visibilidade geral de produção
- `teste_projetista` e `teste_vendedor` devem ter acesso ao CRM

### 3. Testar fluxo de O.S.
- Criar uma Ordem de Serviço (com `teste_vendedor`)
- Passar pela engenharia (`teste_engenharia`)
- Avançar pelos setores de produção
- Confirmar que cada setor só vê suas etapas

### 4. Verificar no dashboard
- `teste_dashboard_producao` deve ver estatísticas gerais
- `teste_master` deve ter acesso ao painel administrativo

---

## Gerenciar Contas de Teste

### Recriar as contas (se necessário deletar e recriá-las)
```bash
php /c/xampp/htdocs/tools/criar_contas_teste.php --delete
```

Este comando:
- Remove todas as contas com email `teste_*@cozinca.local`
- Recria as 19 contas do zero com senha `123`

### Deletar apenas as contas
```bash
mysql -u root -D dbcozinca -e "DELETE FROM usuarios WHERE email LIKE 'teste_%@cozinca.local'"
```

### Alterar senha de uma conta
```bash
# No MySQL:
UPDATE usuarios SET senha = PASSWORD('nova_senha') WHERE email = 'teste_corte@cozinca.local';

# Nota: Use password_hash() em PHP para uma hash segura
```

---

## Informações Técnicas

- **Banco de dados**: dbcozinca
- **Host**: 127.0.0.1:3306 (local XAMPP)
- **Tabela**: usuarios
- **Script de criação**: `/c/xampp/htdocs/tools/criar_contas_teste.php`
- **Data de criação**: 2026-07-17
- **Total de contas**: 19
- **Status**: Ativas e testadas ✓

---

## Contato / Suporte

Para alterar o padrão ou recriar as contas, execute:
```bash
php /c/xampp/htdocs/tools/criar_contas_teste.php [--delete]
```

Dúvidas ou problemas? Verificar logs em:
- Banco de dados: phpMyAdmin (http://localhost/phpmyadmin)
- Aplicação: Ver sidebar do ERP e "Notificações"
