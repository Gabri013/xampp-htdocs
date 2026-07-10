# Guia de Instalação - E2E Tests

## Pré-requisitos

Antes de executar os testes, você precisa instalar o Node.js e npm no seu sistema.

### Instalação do Node.js e npm

1. **Baixe o Node.js:**
   - Acesse: https://nodejs.org/
   - Baixe a versão LTS (Long Term Support) recomendada
   - Execute o instalador e siga as instruções

2. **Verifique a instalação:**
   Abra um novo terminal (PowerShell ou CMD) e execute:
   ```bash
   node --version
   npm --version
   ```

   Se aparecer a versão, a instalação foi bem-sucedida.

## Instalação das Dependências

Após instalar o Node.js, siga estes passos:

1. **Navegue até o diretório de testes:**
   ```bash
   cd c:\xampp\htdocs\e2e-tests
   ```

2. **Instale as dependências:**
   ```bash
   npm install
   ```

3. **Instale os navegadores do Playwright:**
   ```bash
   npx playwright install
   ```

## Executando os Testes

Após a instalação completa, você pode executar os testes:

```bash
npm test
```

Para mais opções, consulte o README.md.
