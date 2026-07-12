# Ferramentas de validação — ERP Cozinca

Scripts para conferir a saúde do sistema sozinho, sem precisar saber o que testar.

## Como rodar

**Jeito fácil (Windows):** com o **XAMPP ligado** (Apache + MySQL), dê
duplo-clique em `tools/healthcheck.bat`. Uma janela abre, roda tudo e mostra
o resultado.

**Pelo terminal:**
```bash
bash tools/healthcheck.sh
```

## O que o healthcheck verifica (não altera nada — pode rodar quando quiser)

1. **Sintaxe** de todos os arquivos PHP do projeto
2. **Renderização** de todas as ~55 páginas, com o perfil certo, caçando
   erros de PHP e de banco em execução (variável indefinida, coluna que não
   existe, erro de SQL, etc.)
3. **Cada setor** abrindo o próprio painel sem erro
4. **Endpoints** da API sem erro fatal
5. **Auditoria de dados**: O.S. sem etapa, contas a receber órfãs, etc.

No fim aparece `TUDO OK` ou a lista de falhas com a página e o erro.

## Contas de teste (senha `teste123`)

`<perfil>@teste.cozinca.com.br` — uma para cada perfil
(master, vendedor, projetista, gerente, producao, e cada setor).
O healthcheck usa essas contas para logar e testar cada tela.

## Se der "Apache: 000" ou "Can't connect to MySQL"

O XAMPP não está ligado. Abra o **XAMPP Control Panel** e clique em
**Start** no Apache e no MySQL, depois rode de novo.
