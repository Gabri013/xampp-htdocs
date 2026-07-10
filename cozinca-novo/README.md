# Cozinca ERP - Módulo Novo (Laravel + Tailwind)

Implementação do plano de migração "strangler fig" conforme documento em `docs/ANALISE_PROJETO.md`.

## Instalação

```bash
composer create-project laravel/laravel cozinca-novo
cd cozinca-novo
composer require laravel/sanctum
npm install
npm install -D tailwindcss postcss autoprefixer @tailwindcss/forms
npx tailwindcss init -p
```

Copie os arquivos deste diretório para `cozinca-novo/`:

```
htdocs/cozinca-novo/  →  cozinca-novo/
```

## Integração com o Sistema Legado

### 1. Configurar a chave de ponte no legado

Edit `config/config.local.php` (ou `config.php` em produção):

```php
// PONTE LARAVEL - Autenticação compartilhada
// Gere uma chave única: openssl rand -hex 32
define('PONTE_SECRET_KEY', 'SUA_CHAVE_SECRETA_AQUI');
define('SESSION_DOMAIN', '.seudominio.com'); // domínio pai compartilhado
```

### 2. O arquivo `legado/ponte_auth.php` já foi integrado

O `config/config.php` foi modificado para carregar automaticamente o arquivo de ponte.
As funções `gerarTokenPonte()` e `destruirTokenPonte()` agora são chamadas automaticamente
em `includes/auth.php` durante login/logout.

### 3. Registrar o middleware no Laravel 11+

Edite `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'ponte' => \App\Http\Middleware\AutenticarViaLegado::class,
    ]);
})
```

### 4. Configurar .env

Copie `.env.example` para `.env` e ajuste:

```bash
cp .env.example .env
php artisan key:generate
```

Configurações críticas:
- `PONTE_SECRET_KEY` = mesma chave usada no legado
- `SESSION_DOMAIN` = domínio pai (ex: `.seudominio.com`)
- `DB_*` = apontar para o mesmo banco do legado

### 5. Configurar roteamento Nginx

```nginx
location /novo/ {
    try_files $uri $uri/ /novo/index.php?$query_string;
}
```

O Laravel ficará em `/novo/` enquanto o legado continua em `/`.

## Estrutura de arquivos Laravel

```
cozinca-novo/
├── .env.example
├── config/services.php          # config da chave de ponte
├── app/Http/Middleware/
│   └── AutenticarViaLegado.php  # middleware de autenticação via cookie
├── app/Services/
│   └── OSWorkflowStateMachine.php  # State Machine para workflow
├── app/Models/
│   ├── Usuario.php              # tabela usuarios (sem migration)
│   ├── UsuarioExpediente.php    # tabela usuarios_expedientes
│   ├── OrdemServico.php         # tabela ordens_servico
│   ├── Notificacao.php          # tabela notificacoes
│   ├── NotificacaoEnvio.php     # tabela notificacoes_envios
│   ├── OsEtapaProducao.php      # tabela os_etapas_producao
│   ├── OsHistoricoStatus.php    # tabela os_historico_status
│   ├── LogRetornoEtapa.php      # tabela logs_retorno_etapa
│   ├── OsArquivo.php            # tabela os_arquivos
│   ├── OsItem.php               # tabela os_itens
│   ├── OsObservacao.php         # tabela os_observacoes
│   ├── OrdemProducao.php        # tabela ordens_producao
│   ├── OrdemProducaoItem.php    # tabela ordens_producao_itens
│   ├── Cliente.php              # tabela clientes
│   ├── Venda.php                # tabela vendas
│   ├── VendaItem.php            # tabela vendas_itens
│   ├── ContaReceber.php         # tabela contas_receber
│   ├── ContaPagar.php           # tabela contas_pagar
│   ├── TipoCaixa.php            # tabela tipos_caixa
│   ├── Produto.php              # tabela produtos
│   ├── ProdutoCategoria.php     # tabela produto_categorias
│   ├── EstruturaProduto.php     # tabela estrutura_produto
│   ├── ComponenteProduto.php    # tabela componentes_produto
│   └── Insumo.php              # tabela insumos
├── app/Http/Controllers/
│   ├── NotificacaoController.php
│   ├── FinanceiroController.php
│   ├── ContaPagarController.php
│   ├── ProducaoController.php
│   ├── VendaController.php
│   └── OSController.php
├── routes/web.php               # rotas protegidas por 'ponte'
├── tailwind.config.js
├── resources/css/app.css
└── resources/views/
    ├── layouts/app.blade.php
    ├── notificacoes/index.blade.php
    ├── financeiro/index.blade.php
    ├── contas-pagar/index.blade.php
    ├── producao/index.blade.php
    ├── vendas/index.blade.php
    ├── os/index.blade.php
    └── os/show.blade.php
```

## Arquivos do legado modificados

- `config/config.php` - carrega `legado/ponte_auth.php`
- `includes/auth.php` - chama `gerarTokenPonte()` no login e `destruirTokenPonte()` no logout
- `config/config.local.php` - define `PONTE_SECRET_KEY` e `SESSION_DOMAIN`

## Teste de verificação

- [ ] Login no legado cria cookie `cozinca_ponte` (verificar DevTools)
- [ ] Acessar `/novo/notificacoes` autentica automaticamente
- [ ] Logout no legado remove cookie e `/novo/*` redireciona para login
- [ ] `PONTE_SECRET_KEY` idêntica nos dois ambientes
- [ ] Nenhuma migration altera tabelas usadas pelo legado