# E2E Tests - OS Management System

Suíte completa de testes E2E automatizados para o Sistema de Gestão de Ordens de Serviço, utilizando Playwright.

## 📋 Estrutura dos Testes

Os testes estão organizados em categorias baseadas nos tópicos de UI:

```
e2e-tests/
├── tests/
│   ├── auth/
│   │   └── login.spec.ts           # Authorization & Auth Flows
│   ├── navigation/
│   │   └── user-journey.spec.ts    # User Journey Navigation
│   ├── forms/
│   │   └── form-flows.spec.ts      # Form Flows & Validation
│   ├── visual-states/
│   │   └── visual-states.spec.ts   # Visual States & Layouts
│   ├── interactive/
│   │   └── interactive-components.spec.ts  # Interactive Components & Stateful UI
│   └── error-handling/
│       └── error-handling.spec.ts  # Error Handling (UI)
├── playwright.config.ts
├── package.json
└── README.md
```

## 🚀 Instalação

### Pré-requisitos

- Node.js (v18 ou superior)
- npm ou yarn
- PHP (para executar o servidor de desenvolvimento)
- XAMPP ou servidor PHP configurado

### Passos de Instalação

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

## 🏃 Executando os Testes

### Executar todos os testes
```bash
npm test
```

### Executar em modo headed (com visibilidade do navegador)
```bash
npm run test:headed
```

### Executar em modo debug
```bash
npm run test:debug
```

### Executar com interface UI do Playwright
```bash
npm run test:ui
```

### Executar testes específicos
```bash
npx playwright test tests/auth/login.spec.ts
```

### Executar testes de uma categoria específica
```bash
npx playwright test tests/auth/
npx playwright test tests/navigation/
npx playwright test tests/forms/
```

## 📊 Relatórios

Após executar os testes, visualize o relatório HTML:

```bash
npm run report
```

Isso abrirá um relatório interativo com screenshots, vídeos e traces dos testes.

## 🧪 Categorias de Testes

### 1. Authorization & Auth Flows
Testa o fluxo de autenticação e autorização:
- Exibição da página de login
- Validação de credenciais inválidas
- Validação de campos vazios
- Redirecionamento após login bem-sucedido
- Redirecionamento de usuário já logado
- Validação HTML5 dos campos

### 2. User Journey Navigation
Testa a navegação entre páginas e componentes:
- Navegação do dashboard para detalhes da OS
- Navegação entre abas na página de detalhes
- Funcionalidade do botão voltar
- Navegação via breadcrumbs
- Links externos em nova aba

### 3. Form Flows & Validation
Testa formulários e validação:
- Validação de campos obrigatórios
- Validação de formato de email
- Submit com Enter
- Formulário de anexação de arquivos
- Formulário de produção
- Formulário de anotações
- Filtro de busca funcional

### 4. Visual States & Layouts
Testa estados visuais e layouts responsivos:
- Layout responsivo em desktop, tablet e mobile
- Estados de prioridade visual
- Estados de prazo crítico
- Layout de abas
- Layout do Kanban
- Consistência visual de badges
- Layout em grid de KPIs

### 5. Interactive Components & Stateful UI
Testa componentes interativos:
- Filtro de busca em tempo real
- Mudança de estado ativo em abas
- Toggle de sidebar
- Feedback visual de botões
- Abertura e fechamento de modais
- Toggle de dropdowns
- Expansão e colapso de accordions
- Navegação por teclado

### 6. Error Handling (UI)
Testa tratamento de erros na interface:
- Erro para credenciais inválidas
- Erro para campos vazios
- Página não encontrada (404)
- Erro de validação visual
- Erro de autorização
- Tratamento de erro de API
- Erro de upload de arquivo
- Timeout de requisição
- Indicador offline

## ⚙️ Configuração

### Configuração do Servidor

O `playwright.config.ts` está configurado para iniciar automaticamente um servidor PHP:

```typescript
webServer: {
  command: 'php -S localhost:8080 -t ..',
  port: 8080,
  reuseExistingServer: !process.env.CI,
}
```

Se você já tiver um servidor rodando, modifique a configuração ou inicie o servidor manualmente.

### Configuração de Credenciais de Teste

Para testes que requerem login, você precisará configurar credenciais válidas no ambiente de teste. Edite os arquivos de teste e substitua as credenciais de exemplo.

### Viewport e Dispositivos

Os testes são executados em três navegadores:
- Chromium (Desktop Chrome)
- Firefox (Desktop Firefox)
- WebKit (Desktop Safari)

Você pode adicionar mais projetos no `playwright.config.ts` para testar em dispositivos móveis.

## 🐛 Debugging

### Modo Debug
```bash
npm run test:debug
```

Isso abrirá o Playwright Inspector, permitindo você:
- Ver cada passo do teste
- Inspecionar elementos
- Executar comandos no console
- Avançar passo a passo

### Screenshots e Vídeos

Os testes são configurados para:
- Tirar screenshots apenas em falhas
- Gravar vídeos apenas em falhas
- Gerar traces apenas na primeira retry

## 📝 Boas Práticas

1. **Execute testes regularmente:** Execute a suíte completa antes de cada deploy
2. **Mantenha testes atualizados:** Atualize os testes quando houver mudanças na UI
3. **Use seletores estáveis:** Prefira seletores baseados em data-testid, aria-label ou IDs
4. **Evite waits desnecessários:** Use `waitForSelector` em vez de `waitForTimeout`
5. **Teste cenários reais:** Foque em fluxos de usuários reais, não apenas implementação

## 🔧 Troubleshooting

### Erro: "Cannot find module '@playwright/test'"
**Solução:** Execute `npm install` no diretório e2e-tests

### Erro: "Browser not found"
**Solução:** Execute `npx playwright install`

### Testes falhando aleatoriamente
**Solução:** Aumente o tempo de espera ou use `waitForSelector` mais específicos

### Servidor não inicia
**Solução:** Verifique se o PHP está instalado e no PATH, ou inicie o servidor manualmente

## 📚 Recursos Adicionais

- [Documentação do Playwright](https://playwright.dev/)
- [Best Practices do Playwright](https://playwright.dev/docs/best-practices)
- [Playwright Trace Viewer](https://playwright.dev/docs/trace-viewer)

## 🤝 Contribuindo

Ao adicionar novos testes:
1. Siga a estrutura de diretórios existente
2. Use descrições claras nos testes
3. Documente cenários complexos
4. Mantenha testes independentes uns dos outros

## 📄 Licença

Esta suíte de testes é parte do Sistema de Gestão de Ordens de Serviço.
