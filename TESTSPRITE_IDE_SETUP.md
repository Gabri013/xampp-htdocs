# Como Usar TestSprite MCP Server no Seu IDE

O TestSprite MCP Server foi instalado com sucesso (versão 0.0.19), mas ele funciona como uma integração com IDE, não como ferramenta de linha de comando direta.

## Status Atual

✅ **TestSprite MCP Server instalado** (versão 0.0.19)
✅ **Servidor PHP rodando** em http://localhost:8080
✅ **PRD do projeto criado** (PRD.md)
✅ **Configuração do TestSprite criada** (testsprite-config.json)

## Como Usar o TestSprite

### Passo 1: Configure o MCP Server no Seu IDE

Adicione a configuração do TestSprite ao arquivo de configuração MCP do seu IDE (varia dependendo do IDE):

```json
{
  "mcpServers": {
    "TestSprite": {
      "command": "npx",
      "args": [
        "@testsprite/testsprite-mcp@latest"
      ],
      "env": {
        "API_KEY": "sua-api-key-aqui"
      }
    }
  }
}
```

**Nota:** Você precisará de uma API key do TestSprite. Obtenha em https://testsprite.com/

### Passo 2: Reinicie o IDE

Após adicionar a configuração, reinicie seu IDE para carregar o servidor MCP.

### Passo 3: Use o Comando Mágico

No chat do seu IDE, digite:

```
Can you test this project with TestSprite?
```

### Passo 4: Configure os Testes

A página de configuração do TestSprite abrirá no navegador. Configure:

- **Testing Type**: Frontend
- **Scope**: Codebase
- **Application URL**: http://localhost:8080
- **Test Credentials**: Configure credenciais de teste do seu sistema
- **PRD**: Faça upload do arquivo `PRD.md` que foi criado

### Passo 5: Execute os Testes

O TestSprite irá:
1. Analisar seu código
2. Gerar automaticamente testes baseados no PRD
3. Executar os testes na nuvem
4. Gerar relatórios detalhados
5. Sugerir correções automáticas para bugs

## Alternativa: Suíte Playwright (Recomendada para Uso Imediato)

Se você quiser executar testes agora mesmo sem configurar o TestSprite MCP no IDE, use a suíte Playwright que já foi criada:

### Instalação (após corrigir o PATH do npm)

```bash
cd c:\xampp\htdocs\e2e-tests
npm install
npx playwright install
```

### Execução

```bash
npm test
```

## Comparação

| Característica | Playwright | TestSprite MCP |
|---------------|-------------|----------------|
| **Uso imediato** | ✅ Sim | ❌ Requer configuração IDE |
| **Custo** | ✅ Gratuito | ❌ Requer API key |
| **Geração automática** | ❌ Manual | ✅ IA automática |
| **Correção automática** | ❌ Manual | ✅ IA automática |
| **Testes criados** | ✅ 64 testes prontos | ⏳ Gerados sob demanda |
| **Controle total** | ✅ Sim | ❌ Limitado |

## Recomendação

**Para uso imediato:** Use a suíte Playwright em `c:\xampp\htdocs\e2e-tests\`

**Para uso futuro com IA:** Configure o TestSprite MCP no seu IDE seguindo os passos acima

## Arquivos Criados

- `c:\xampp\htdocs\e2e-tests\` - Suíte completa de testes Playwright (64 testes)
- `c:\xampp\htdocs\PRD.md` - Product Requirements Document para TestSprite
- `c:\xampp\htdocs\testsprite-config.json` - Configuração do TestSprite
- `c:\xampp\htdocs\e2e-tests\README.md` - Documentação do Playwright
- `c:\xampp\htdocs\e2e-tests\TESTSPRITE_GUIDE.md` - Guia do TestSprite
