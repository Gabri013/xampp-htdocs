# Guia de Instalação: Módulo de Desenho Técnico

## Pré-requisitos

- PHP 7.4+
- MySQL 5.7+
- Permissões de escrita em `/assets/uploads/`
- Espaço em disco disponível (mínimo 5GB para uploads)

## Passos de Instalação

### 1. Criar Diretório de Uploads

```bash
# Criar pasta para armazenar arquivos de desenhos
mkdir -p /assets/uploads/desenhos
chmod 755 /assets/uploads/desenhos
```

### 2. Adicionar Schemas ao Banco de Dados

**Opção A: Automático (Recomendado)**

O sistema cria as tabelas automaticamente ao chamar `ensureEngenhariaSchema()`:

```php
<?php
require_once 'config/config.php';
require_once 'includes/engenharia.php';

$db = getDB();
ensureEngenhariaSchema($db); // Cria todas as tabelas necessárias
?>
```

**Opção B: Manual**

Executar o SQL abaixo diretamente no MySQL:

```sql
-- Ver arquivo: desenho_queries.sql
-- A partir da linha "CREATE TABLE IF NOT EXISTS desenhos_tecnicos"
```

### 3. Verificar Permissões no Banco

```sql
-- Verificar se tabelas foram criadas
SHOW TABLES LIKE 'desenho%';

-- Deve retornar:
-- desenhos_technicos
-- desenhos_arquivos
-- desenhos_revisoes
-- desenhos_aprovaes
-- desenhos_historico
```

### 4. Incluir o Módulo no Sistema

Adicionar link no menu de navegação principal (`index.php` ou sidebar):

```html
<a href="modules/engenharia/desenho_tecnico.php" class="nav-link">
    <i class="fas fa-file-alt"></i> Desenho Técnico
</a>
```

### 5. Registrar Permissões (se usar sistema customizado)

```php
// Em seu arquivo de permissões, adicionar:
$permissoes['desenho_tecnico'] = [
    'projetista' => ['visualizar', 'criar', 'editar_rascunho', 'submeter'],
    'gerente' => ['visualizar', 'criar', 'editar', 'aprovar_gerencia', 'rejeitar'],
    'producao' => ['visualizar', 'aprovar_producao', 'rejeitar'],
    'master' => ['visualizar', 'criar', 'editar', 'deletar', 'aprovar_gerencia', 'aprovar_producao']
];
```

### 6. Configurar Limites de Upload (php.ini)

```ini
; Configurações recomendadas
upload_max_filesize = 50M          ; Máximo por arquivo
post_max_size = 250M               ; Máximo por request
max_execution_time = 300           ; 5 minutos para upload grande
memory_limit = 512M                ; Memória disponível
```

### 7. Integrar com O.S.

No arquivo que gerencia Ordens de Serviço, adicionar link:

```html
<!-- Em modules/os/index.php ou similar -->
<a href="engenharia/desenho_tecnico.php?os_id=<?= $osId ?>" class="btn btn-info">
    <i class="fas fa-drafting-compass"></i> Desenhos Técnicos
</a>
```

### 8. Criar Atalhos de Navegação Rápida

Adicionar botões/links em páginas relacionadas:

```php
<!-- Em modules/producao/ordens_producao.php -->
<?php
// Ao visualizar O.P., mostrar se tem desenho aprovado
if (temDesenhoAprovado($db, $osId)) {
    echo '<div class="alert alert-info">';
    echo '<i class="fas fa-check-circle"></i> ';
    echo '<a href="../engenharia/desenho_tecnico.php?os_id=' . $osId . '">';
    echo 'Visualizar Desenho Técnico Aprovado';
    echo '</a>';
    echo '</div>';
}
?>
```

---

## Checklist de Instalação

- [ ] Pasta `/assets/uploads/desenhos/` criada e com permissões corretas
- [ ] Arquivo `includes/engenharia.php` atualizado com schemas
- [ ] Arquivo `modules/engenharia/desenho_tecnico.php` criado
- [ ] Arquivo `api/desenho.php` criado
- [ ] Arquivo `modules/engenharia/desenho_helpers.php` criado
- [ ] Tabelas do banco de dados criadas (6 tabelas)
- [ ] Links de navegação adicionados ao menu
- [ ] Permissões configuradas no sistema
- [ ] Upload máximo configurado no php.ini
- [ ] Espaço em disco verificado
- [ ] Testes manuais executados

---

## Testes Pós-Instalação

### Teste 1: Criar Desenho

1. Acessar `modules/engenharia/desenho_tecnico.php?os_id=123`
2. Clicar em "Novo Desenho"
3. Preencher formulário
4. Fazer upload de arquivo PDF ou imagem
5. Clicar "Salvar como Rascunho"

**Esperado:** Desenho criado com status "rascunho"

### Teste 2: Submeter para Aprovação

1. Visualizar desenho criado
2. Clicar em "Enviar para Revisão"
3. Confirmar ação

**Esperado:** Status muda para "submetido", registros de aprovação criados

### Teste 3: Aprovar na Gerência

1. Acessar painel como usuário com perfil "gerente"
2. Visualizar desenho pendente
3. Clicar "Aprovar"
4. Adicionar observação (opcional)
5. Confirmar

**Esperado:** Status muda para "em_revisao" ou "aprovado"

### Teste 4: Visualizar Histórico

1. Abrir desenho
2. Ir para aba "Histórico" ou similiar
3. Verificar todas as ações registradas

**Esperado:** Timeline completa com todos os eventos

### Teste 5: Consultas SQL

```sql
-- Verificar desenhos criados
SELECT COUNT(*) FROM desenhos_technicos;

-- Verificar arquivos
SELECT COUNT(*) FROM desenhos_arquivos;

-- Verificar histórico
SELECT COUNT(*) FROM desenhos_historico;

-- Verificar aprovações
SELECT COUNT(*) FROM desenhos_aprovaes;
```

---

## Troubleshooting

### Problema: "Pasta não existe ou sem permissão"

**Solução:**
```bash
mkdir -p /xampp/htdocs/assets/uploads/desenhos
chmod 755 /xampp/htdocs/assets/uploads/desenhos
chmod 644 /xampp/htdocs/assets/uploads/desenhos/*
```

### Problema: "Arquivo não foi salvo"

**Verificar:**
1. Limite de upload no php.ini
2. Permissões da pasta
3. Espaço em disco disponível
4. Logs do PHP em `/xampp/php/logs/`

### Problema: "Tabelas não foram criadas"

**Solução:**
```php
<?php
$db = getDB();
ensureEngenhariaSchema($db); // Força recriação

// Verificar criação
$result = $db->query("SHOW TABLES LIKE 'desenho%'");
echo $result->rowCount() . " tabelas criadas";
?>
```

### Problema: "Erro ao criar desenho: FOREIGN KEY constraint fails"

**Verificar:**
- O.S. existe no banco
- Usuário ID existe na tabela usuarios
- IDs estão corretos

---

## Configurações Avançadas

### Aumentar Limite de Tamanho de Arquivo

No `php.ini`:
```ini
upload_max_filesize = 100M
post_max_size = 500M
```

### Ativar Compressão de PDFs

Adicionar ao `api/desenho.php`:
```php
// Ao fazer upload, comprimir PDFs
if ($tipoArquivo === 'pdf') {
    // Usar ghostscript ou similar para comprimir
}
```

### Integração com Slack/Email

Adicionar ao `api/desenho.php` após aprovação:
```php
// Notificar via Slack
enviarNotificacao([
    'canal' => '#producao',
    'mensagem' => "Desenho '{$titulo}' aprovado! Pronto para produção."
]);

// Ou enviar email
mail($email, 'Desenho Aprovado', 'Seu desenho foi aprovado.');
```

---

## Backups

### Backup do Banco de Dados

```bash
# Exportar apenas tabelas de desenho
mysqldump -u user -p database desenhos_technicos desenhos_arquivos \
    desenhos_revisoes desenhos_aprovaes desenhos_historico > desenho_backup.sql
```

### Backup de Arquivos

```bash
# Comprimir pasta de uploads
tar -czf desenhos_backup_$(date +%Y%m%d).tar.gz /assets/uploads/desenhos/
```

### Restaurar Backup

```bash
# Restaurar banco
mysql -u user -p database < desenho_backup.sql

# Restaurar arquivos
tar -xzf desenhos_backup_20260717.tar.gz -C /
```

---

## Performance

### Otimizações Recomendadas

1. **Índices de Banco (automáticos)**
   - Criados automaticamente pela função `ensureEngenhariaSchema()`

2. **Cache de Desenhos**
   ```php
   $cacheKey = 'desenho_' . $desenhoId;
   $desenho = $cache->get($cacheKey);
   if (!$desenho) {
       $desenho = obterDesenho($db, $desenhoId);
       $cache->set($cacheKey, $desenho, 3600); // 1 hora
   }
   ```

3. **Paginação de Listas**
   ```php
   $pagina = $_GET['pagina'] ?? 1;
   $limite = 20;
   $offset = ($pagina - 1) * $limite;
   
   // Adicionar ao SELECT: LIMIT $limite OFFSET $offset
   ```

4. **Limpeza de Desenhos Obsoletos**
   ```php
   // Executar monthly via cron
   $db->exec("
       DELETE FROM desenhos_technicos
       WHERE status = 'obsoleto'
         AND updated_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
   ");
   ```

---

## Suporte e Documentação

- **Documentação Principal:** `DESENHO_TECNICO_DOCS.md`
- **Exemplos de Código:** `DESENHO_EXEMPLOS.md`
- **Queries SQL:** `desenho_queries.sql`
- **Helper Functions:** `desenho_helpers.php`

---

## Log de Mudanças

### Versão 1.0 (17/07/2026)
- Implementação inicial
- 6 tabelas do banco de dados
- Interface web completa
- API REST
- Fluxo de aprovação 3 etapas
- Histórico rastreado
- Upload de múltiplos formatos

---

## Suporte

Para problemas ou dúvidas:

1. Verificar logs do PHP: `/xampp/php/logs/`
2. Verificar logs da aplicação: `/logs/` (se existir)
3. Executar `php -l` para validar sintaxe
4. Contatar: Gabriel Costa (g4bs011.gbl@gmail.com)

---

**Instalação concluída! Sistema pronto para uso.**
