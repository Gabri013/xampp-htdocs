# Arquitetura - Sistema de Exportação Cozinka ERP

## 📐 Visão Geral da Arquitetura

```
┌─────────────────────────────────────────────────────────────┐
│                    CAMADA DE APRESENTAÇÃO                   │
├──────────────────────┬──────────────────────┬───────────────┤
│  Interface Web       │  API REST JSON       │  JavaScript   │
│  (exportador_       │  (/api/exportacao.   │  Client       │
│   interface.php)    │   php)               │  (exportador. │
│  [HTML/Tailwind]    │  [200+ linhas]       │   js)         │
└──────────────────────┴──────────────────────┴───────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│                    CAMADA DE LÓGICA (API)                   │
├──────────────────────────────────────────────────────────────┤
│  Validação de Entrada                                        │
│  ├─ Autenticação (isLoggedIn)                               │
│  ├─ Autorização (validarAcesso)                             │
│  ├─ Sanitização de Filtros                                  │
│  └─ Validação de Ação                                       │
│                                                              │
│  Orquestração                                               │
│  ├─ Roteamento de ação                                      │
│  ├─ Chamada ao Exportador                                   │
│  └─ Tratamento de Erros                                     │
└──────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│                    CAMADA DE NEGÓCIO                        │
├──────────────────────────────────────────────────────────────┤
│  Classe Exportador (1000+ linhas)                            │
│  ├─ buscarVendas()        │  ├─ exportarCSV()              │
│  ├─ buscarOrcamentos()    │  ├─ exportarXLSX()             │
│  ├─ buscarOS()            │  ├─ exportarPDF()              │
│  ├─ buscarClientes()      │  └─ exportarJSON()             │
│  ├─ buscarEstoque()       │                                 │
│  ├─ buscarProducao()      │  Validação de Integridade       │
│  └─ buscarFinanceiro()    │  ├─ validarIntegridade()       │
│                            │  ├─ getCamposObrigatorios()    │
│  Controle de Acesso        │  └─ validarTipo()             │
│  └─ validarAcesso()        │                                 │
└──────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│                    CAMADA DE DADOS                          │
├──────────────────────────────────────────────────────────────┤
│  Banco de Dados MySQL/MariaDB                               │
│  ├─ vendas               ├─ usuarios                        │
│  ├─ orcamentos           ├─ clientes                        │
│  ├─ ordens_servico       ├─ estoque                         │
│  ├─ ordens_producao      └─ contas_financeiras             │
│  └─ exportacoes_log (auditoria)                             │
└──────────────────────────────────────────────────────────────┘
```

---

## 🏗️ Componentes Principais

### 1. API REST (`/api/exportacao.php`)

**Responsabilidades:**
- Validar autenticação e autorização
- Sanitizar entrada de filtros
- Orquestrar chamadas ao Exportador
- Tratamento de erros HTTP
- Logging de exportações

**Ações:**
```
exportar           → Exporta dados em formato especificado
listar_tabelas     → Lista tabelas disponíveis para o usuário
filtros_disponiveis → Retorna filtros de uma tabela
teste              → Testa conexão com banco de dados
```

**Fluxo de Requisição:**

```
POST /api/exportacao.php
│
├─ 1. Validar sessão (isLoggedIn)
│
├─ 2. Validar ação (switch)
│
├─ 3. Sanitizar entrada (sanitizarFiltros)
│
├─ 4. Instanciar Exportador($db, $usuario)
│
├─ 5. Chamar $exportador->exportar()
│  ├─ validarAcesso()
│  ├─ buscarDados()
│  ├─ validarIntegridade()
│  └─ exportarFormato()
│
├─ 6. Registrar em exportacoes_log
│
└─ 7. Retornar JSON ou arquivo
```

### 2. Classe Exportador (`/includes/exportador.php`)

**Estrutura:**

```php
class Exportador {
    private $db;              // PDO connection
    private $usuario;         // Usuario context
    private $erros;          // Error messages
    private $avisos;         // Warning messages
    
    // Métodos públicos
    public exportar()
    public getErros()
    public getAvisos()
    public limpar()
    
    // Métodos privados
    private validarAcesso()
    private buscarDados()
    private validarIntegridade()
    private exportarCSV()
    private exportarXLSX()
    private exportarPDF()
    private exportarJSON()
}
```

**Fluxo de Exportação:**

```
exportar($tabela, $formato, $filtros)
│
├─ 1. validarAcesso($tabela)
│     └─ Verificar permissão do usuário por tipo
│
├─ 2. buscarDados($tabela, $filtros)
│     ├─ Switch para método específico
│     ├─ Construir SQL com prepared statements
│     ├─ Aplicar filtros
│     └─ Retornar array de dados
│
├─ 3. validarIntegridade($tabela, $dados)
│     ├─ Validar campos obrigatórios
│     ├─ Validar tipos de dados
│     └─ Gerar avisos
│
├─ 4. exportarFormato($formato)
│     ├─ CSV: fputcsv em php://output
│     ├─ XLSX: Gerar XML + ZIP
│     ├─ PDF: TCPDF ou HTML fallback
│     └─ JSON: json_encode com metadados
│
└─ 5. Retornar array com [conteudo, tipo_mime, nome, extensão]
```

### 3. Interface Web (`/modules/admin/exportador_interface.php`)

**Componentes HTML:**
- Seletor de tabela (dropdown)
- Radio buttons de formato
- Container de filtros dinâmicos
- Indicador de progresso
- Área de status/resultado

**Fluxo JavaScript:**

```
[Carregar página]
│
├─ Carregador histórico (AJAX)
│
[Selecionar tabela] → atualizarFiltros()
│
├─ Buscar filtros da API
├─ Gerar HTML de filtros
└─ Adicionar event listeners
│
[Clicar Exportar] → exportarDados()
│
├─ Coletar dados do formulário
├─ Mostrar progresso
├─ POST para /api/exportacao.php
├─ Receber resultado em JSON
├─ Decodificar base64
├─ Triggar download
└─ Mostrar resultado
```

### 4. Cliente JavaScript (`/assets/js/exportador.js`)

**Classe: ExportadorCozinka**

```javascript
class ExportadorCozinka {
    async exportar(tabela, formato, filtros, download)
    async listarTabelas()
    async obterFiltros(tabela)
    async testar()
    async criarFormulario(containerId, tabelasPermitidas)
}
```

**Métodos Privados:**
```javascript
_gerarHTMLFormulario()
_anexarEventos()
_gerarHTMLFiltros()
_baixarArquivo()
```

---

## 🔐 Segurança

### 1. Autenticação
```php
// Em api/exportacao.php, linha ~20
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['erro' => 'Sessão expirada']);
    exit;
}
```

### 2. Autorização
```php
// Em Exportador->validarAcesso()
private $acessoSetores = [
    'master' => ['vendas', 'orcamentos', 'os', ...],
    'vendedor' => ['vendas', 'orcamentos'],
    'projetista' => ['os', 'orcamentos'],
    // ...
];

// Verificação:
if (!in_array($tabela, $this->acessoSetores[$tipo])) {
    return false; // Acesso negado
}
```

### 3. Prepared Statements
```php
// Exemplo: Buscar vendas com filtro
$stmt = $db->prepare("SELECT * FROM vendas WHERE usuario_id = ? AND status = ?");
$stmt->execute([$usuario_id, $status]); // Parâmetros separados
```

### 4. Sanitização de Filtros
```php
// Em api/exportacao.php, função sanitizarFiltros()
$filtros_validos = ['status', 'data_inicio', 'data_fim', 'cliente_id', ...];

// Apenas filtros conhecidos são permitidos
if (!in_array($chave, $filtros_validos)) {
    continue; // Ignorar filtro desconhecido
}
```

### 5. Logging de Auditoria
```php
// Registrar cada exportação
INSERT INTO exportacoes_log (usuario_id, tabela, formato, filtros_count, data_exportacao)
VALUES (?, ?, ?, ?, NOW())
```

---

## 📊 Estrutura de Dados

### Entrada (POST)

```json
{
    "acao": "exportar",
    "tabela": "vendas",
    "formato": "xlsx",
    "filtros": "{\"status\":\"confirmada\",\"data_inicio\":\"2026-07-01\"}",
    "download": "1"
}
```

### Saída (JSON)

```json
{
    "sucesso": true,
    "tabela": "vendas",
    "formato": "xlsx",
    "nome_arquivo": "vendas_2026-07-17_141530.xlsx",
    "tipo_mime": "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    "tamanho": 15234,
    "data_exportacao": "2026-07-17 14:15:30",
    "avisos": [],
    "conteudo_base64": "UEsDBBQABgAI..."
}
```

### Estrutura XLSX Gerada

```
arquivo.xlsx
└─ (é um ZIP contendo:)
   ├─ [Content_Types].xml
   ├─ _rels/.rels
   ├─ xl/
   │  ├─ workbook.xml
   │  ├─ worksheets/sheet1.xml
   │  ├─ styles.xml
   │  └─ _rels/workbook.xml.rels
   └─ docProps/core.xml
```

---

## 🧪 Testes

### Testes Unitários (`/tests/ExportadorTest.php`)

```php
class ExportadorTest {
    testeInstanciacao()           // Criar classe
    testeValidacaoAcesso()        // Permissões
    testeExportarCSV()            // Formato CSV
    testeExportarXLSX()           // Formato XLSX
    testeExportarJSON()           // Formato JSON
    testeExportarPDF()            // Formato PDF
    testeValidacaoIntegridade()   // Dados válidos
    testeFiltros()                // Filtros aplicados
    testeAcessoVendedor()         // Vendedor (limitado)
    testeAcessoProjetista()       // Projetista (limitado)
}
```

### Executar Testes

```bash
php tests/ExportadorTest.php
```

---

## 📈 Performance

### Complexidade

| Operação | Complexidade | Tempo |
|----------|-------------|-------|
| buscarDados (10K) | O(n) | ~100ms |
| validarIntegridade | O(n) | ~50ms |
| exportarCSV | O(n) | ~50ms |
| exportarXLSX | O(n) | ~200ms |
| exportarJSON | O(n) | ~80ms |
| **Total** | | **~500ms** |

### Otimizações Implementadas

1. **Prepared Statements** - Reutilizar query plans
2. **Limit 10000** - Não carregar mais de 10K registros
3. **Índices de BD** - Usar índices em WHERE
4. **Buffer de Output** - Não carregar tudo na memória
5. **Compressão XLSX** - ZIP nativo reduz tamanho 80%

---

## 🔄 Fluxo End-to-End

```
[Usuário acessa http://localhost/modules/admin/exportador_interface.php]
│
├─ Servidor retorna HTML + JS
│
[Browser carrega exportador.js e chama listarTabelas()]
│
├─ fetch POST /api/exportacao.php?acao=listar_tabelas
├─ API valida sessão
├─ API retorna JSON com tabelas permitidas
└─ JS renderiza dropdown
│
[Usuário seleciona tabela]
│
├─ JS chama obterFiltros(tabela)
├─ fetch POST /api/exportacao.php?acao=filtros_disponiveis
├─ API retorna filtros disponíveis
└─ JS renderiza formulário dinâmico
│
[Usuário clica Exportar]
│
├─ JS coleta filtros
├─ fetch POST /api/exportacao.php?acao=exportar
├─ API valida entrada
├─ API cria Exportador instance
├─ Exportador valida acesso
├─ Exportador busca dados com filtros
├─ Exportador valida integridade
├─ Exportador exporta ao formato
├─ API registra em exportacoes_log
├─ API retorna base64 + metadados
├─ JS decodifica base64
├─ Browser faz download automático
└─ Usuário vê arquivo no navegador
```

---

## 🚀 Escalabilidade

### Para volumes maiores (100K+ registros):

1. **Paginação**
   ```php
   $sql .= " LIMIT 10000 OFFSET " . ($page - 1) * 10000;
   ```

2. **Background Jobs**
   ```bash
   php scripts/export_async.php --id=123 --bg=true
   ```

3. **Cache Redis**
   ```php
   $chave = md5(json_encode($filtros));
   $dados = $redis->get($chave) ?: buscarDados();
   ```

4. **Compressão em Servidor**
   ```php
   if (filesize($arquivo) > 10*1024*1024) {
       gzcompress($conteudo);
   }
   ```

---

## 📚 Referências de Código

### Key Files

| Arquivo | Linhas | Função |
|---------|--------|--------|
| includes/exportador.php | 1000+ | Classe principal |
| api/exportacao.php | 400+ | API REST |
| modules/admin/exportador_interface.php | 300+ | Interface Web |
| assets/js/exportador.js | 400+ | Cliente JS |
| tests/ExportadorTest.php | 200+ | Testes unitários |

### Key Methods

**Exportador:**
- `exportar($tabela, $formato, $filtros)` - Método principal
- `validarAcesso($tabela)` - Verificar permissão
- `buscarDados($tabela, $filtros)` - Buscar registros
- `validarIntegridade($tabela, $dados)` - Validar dados
- `exportarXLSX($tabela, $dados)` - Gerar Excel

**API:**
- `GET /api/exportacao.php?acao=exportar` - Exportar
- `GET /api/exportacao.php?acao=listar_tabelas` - Listar
- `GET /api/exportacao.php?acao=filtros_disponiveis` - Filtros

---

## ✨ Diagrama de Classes

```
PDO (Banco de Dados)
  └─ Exportador
      ├─ buscarVendas()
      ├─ buscarOrcamentos()
      ├─ buscarOS()
      ├─ buscarClientes()
      ├─ buscarEstoque()
      ├─ buscarProducao()
      ├─ buscarFinanceiro()
      ├─ validarIntegridade()
      ├─ exportarCSV()
      ├─ exportarXLSX()
      ├─ exportarPDF()
      └─ exportarJSON()

ExportadorCozinka (JavaScript)
  ├─ exportar()
  ├─ listarTabelas()
  ├─ obterFiltros()
  ├─ testar()
  └─ criarFormulario()
```

---

**Versão**: 1.0  
**Data**: 17/07/2026  
**Desenvolvedor**: Gabriel Costa
