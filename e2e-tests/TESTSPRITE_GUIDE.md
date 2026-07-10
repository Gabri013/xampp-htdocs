# Guia Opcional - TestSprite MCP Server

Este documento explica como usar o TestSprite MCP Server como alternativa ao Playwright para testar seu aplicativo.

## Pré-requisitos

Antes de usar o TestSprite MCP Server, você precisa:

1. **Instalar Node.js e npm**
   - Baixe em https://nodejs.org/ (versão LTS)
   - Execute o instalador
   - Verifique: `node --version` e `npm --version`

2. **Instalar o TestSprite MCP Server**
   ```bash
   npx @testsprite/testsprite-mcp@latest
   ```

## Configuração do TestSprite

### 1. Inicie seu aplicativo PHP

Certifique-se de que seu aplicativo está rodando localmente:

```bash
# Usando PHP built-in server
php -S localhost:8080 -t c:\xampp\htdocs

# Ou usando XAMPP Apache
# Inicie o Apache pelo XAMPP Control Panel
```

### 2. Configure o MCP Server

Adicione a configuração do TestSprite MCP ao seu arquivo de configuração MCP (varia dependendo do seu IDE/cliente MCP):

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

### 3. Execute o comando mágico

No chat do seu IDE, digite:

```
Can you test this project with TestSprite?
```

### 4. Configure os testes

A página de configuração do TestSprite abrirá no navegador. Configure:

- **Testing Type**: Frontend (para testar UI)
- **Scope**: Codebase (para testar todo o projeto)
- **Application URL**: `http://localhost:8080` (ou a porta do seu servidor)
- **Test Credentials**: Configure credenciais de teste se necessário
- **PRD**: Faça upload de um documento de requisitos (pode ser um rascunho)

## Comparação: Playwright vs TestSprite

### Playwright (Implementação Atual)
✅ **Vantagens:**
- Não requer instalação de Node.js
- Totalmente local e offline
- Controle completo sobre os testes
- Sem custos associados
- Open source e bem documentado

❌ **Desvantagens:**
- Requer escrita manual de testes
- Sem geração automática de testes por IA
- Sem correção automática de bugs

### TestSprite MCP Server
✅ **Vantagens:**
- Geração automática de testes por IA
- Correção automática de bugs
- Execução em nuvem
- Relatórios detalhados
- Integração com agentes de codificação

❌ **Desvantagens:**
- Requer Node.js instalado
- Requer API key (custo associado)
- Dependente de conexão com internet
- Menos controle sobre os testes

## Recomendação

**Para o seu cenário atual:**

Use a suíte de testes Playwright que já foi criada (`c:\xampp\htdocs\e2e-tests\`) porque:
- Node.js não está instalado no seu sistema
- Os testes já estão prontos e funcionais
- Não há custos adicionais
- Você tem controle total sobre os testes

**Considere TestSprite quando:**
- Node.js estiver instalado
- Você tiver orçamento para API key do TestSprite
- Quiser geração automática de testes por IA
- Precisar de correção automática de bugs

## Próximos Passos com Playwright

Para usar os testes Playwright já criados:

1. Instale Node.js (se ainda não tiver)
2. Navegue até `c:\xampp\htdocs\e2e-tests`
3. Execute `npm install`
4. Execute `npx playwright install`
5. Execute `npm test`

Consulte o `README.md` para mais detalhes.
