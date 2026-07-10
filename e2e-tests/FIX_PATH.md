# Como corrigir o problema do PATH do Node.js

O Node.js foi instalado em `C:\Program Files\nodejs\`, mas o PATH do sistema não foi atualizado. Siga estas instruções:

## Solução 1: Reiniciar o Terminal/IDE (Recomendado)

1. **Feche completamente** seu terminal PowerShell e seu IDE
2. **Abra novamente** o terminal PowerShell
3. **Verifique** se o Node.js está funcionando:
   ```bash
   node --version
   npm --version
   ```

Se funcionar, navegue até o diretório de testes e instale as dependências:
```bash
cd c:\xampp\htdocs\e2e-tests
npm install
npx playwright install
```

## Solução 2: Adicionar ao PATH Manualmente

Se a Solução 1 não funcionar, adicione manualmente o Node.js ao PATH:

1. **Pressione Win + X** e selecione **Sistema**
2. **Clique em "Configurações avançadas do sistema"**
3. **Clique em "Variáveis de Ambiente"**
4. **Em "Variáveis do sistema", encontre "Path" e clique em "Editar"**
5. **Clique em "Novo" e adicione:**
   ```
   C:\Program Files\nodejs
   ```
6. **Clique em OK** em todas as janelas
7. **Reinicie seu terminal/IDE**

## Solução 3: Usar Caminho Completo (Temporário)

Se você precisar executar agora sem reiniciar, pode usar o caminho completo em um novo terminal CMD (não PowerShell):

1. **Abra o Prompt de Comando (CMD)** - não PowerShell
2. **Execute:**
   ```cmd
   cd C:\xampp\htdocs\e2e-tests
   C:\Program Files\nodejs\npm.cmd install
   C:\Program Files\nodejs\npx.cmd playwright install
   ```

## Verificação

Após corrigir o PATH, verifique a instalação:

```bash
node --version
npm --version
npm install
npx playwright install
```

## Executar os Testes

Após instalar as dependências:

```bash
npm test
```
