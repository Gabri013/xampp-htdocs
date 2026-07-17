# Sistema de Exportação de Dados - Cozinka ERP

## 📋 Índice
- [Visão Geral](#visão-geral)
- [Instalação](#instalação)
- [Uso via API REST](#uso-via-api-rest)
- [Uso via PHP](#uso-via-php)
- [Formatos Suportados](#formatos-suportados)
- [Controle de Acesso](#controle-de-acesso)
- [Filtros Disponíveis](#filtros-disponíveis)
- [Validação de Integridade](#validação-de-integridade)
- [Exemplos Práticos](#exemplos-práticos)
- [Troubleshooting](#troubleshooting)

---

## 🎯 Visão Geral

Sistema completo e robusto de exportação de dados do Cozinka ERP com suporte a:

✅ **Formatos**: CSV, XLSX (Excel), PDF, JSON  
✅ **Tabelas**: Vendas, Orçamentos, O.S., Clientes, Estoque, Produção, Financeiro  
✅ **Controle**: Por setor e tipo de usuário  
✅ **Validação**: Integridade de dados com FK, campos obrigatórios  
✅ **Performance**: Suporta até 10.000 registros por exportação  
✅ **Logging**: Todas as exportações são registradas para auditoria  

---

## 🚀 Instalação

### 1. Arquivos Necessários

```
/includes/exportador.php       ← Classe principal
/api/exportacao.php            ← API REST
/docs/EXPORTACAO.md            ← Esta documentação
```

### 2. Criar Tabela de Log (automática)

A tabela de log é criada automaticamente na primeira exportação:

```sql
CREATE TABLE exportacoes_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    tabela VARCHAR(100),
    formato VARCHAR(20),
    filtros_count INT DEFAULT 0,
    data_exportacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario (usuario_id),
    INDEX idx_data (data_exportacao),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3. Validar Permissões

Incluir nos includes necessários (já feito em `/config/config.php`):

```php
require_once BASE_PATH . '/includes/exportador.php';
```

---

## 📡 Uso via API REST

### Endpoint Base

```
POST /api/exportacao.php
```

### Ações Disponíveis

#### 1. Exportar Dados

**Requisição:**
```bash
curl -X POST "http://localhost/api/exportacao.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "acao=exportar" \
  -d "tabela=vendas" \
  -d "formato=xlsx" \
  -d "filtros={\"status\":\"confirmada\",\"data_inicio\":\"2026-07-01\"}" \
  -d "download=1"
```

**Parâmetros:**

| Parâmetro | Tipo | Obrigatório | Descrição |
|-----------|------|------------|-----------|
| acao | string | ✅ | `exportar` |
| tabela | string | ✅ | `vendas`, `orcamentos`, `os`, `clientes`, `estoque`, `producao`, `financeiro` |
| formato | string | ✅ | `csv`, `xlsx`, `pdf`, `json` |
| filtros | JSON | ❌ | Filtros adicionais (veja Filtros Disponíveis) |
| download | boolean | ❌ | Se `1`, retorna arquivo direto; se `0` ou omitido, retorna base64 |

**Resposta com Download (download=1):**
```
Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
Content-Disposition: attachment; filename="vendas_2026-07-17_141530.xlsx"
[Conteúdo do arquivo em binário]
```

**Resposta sem Download (download=0 ou padrão):**
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

#### 2. Listar Tabelas Disponíveis

**Requisição:**
```bash
curl -X POST "http://localhost/api/exportacao.php" \
  -d "acao=listar_tabelas"
```

**Resposta:**
```json
{
  "sucesso": true,
  "tabelas": [
    {
      "chave": "vendas",
      "nome": "Vendas",
      "descricao": "Relatório de vendas realizadas",
      "icone": "shopping-cart"
    },
    {
      "chave": "os",
      "nome": "Ordens de Serviço",
      "descricao": "Ordens de serviço em andamento",
      "icone": "list-check"
    }
  ],
  "formatos_suportados": ["csv", "xlsx", "pdf", "json"],
  "usuario": {
    "nome": "Gabriel Costa",
    "tipo": "master"
  }
}
```

#### 3. Obter Filtros Disponíveis

**Requisição:**
```bash
curl -X POST "http://localhost/api/exportacao.php" \
  -d "acao=filtros_disponiveis" \
  -d "tabela=vendas"
```

**Resposta:**
```json
{
  "sucesso": true,
  "tabela": "vendas",
  "filtros": [
    {
      "chave": "status",
      "tipo": "select",
      "label": "Status",
      "opcoes": ["confirmada", "pendente", "cancelada"]
    },
    {
      "chave": "data_inicio",
      "tipo": "date",
      "label": "Data Inicial"
    },
    {
      "chave": "data_fim",
      "tipo": "date",
      "label": "Data Final"
    },
    {
      "chave": "cliente_id",
      "tipo": "number",
      "label": "ID do Cliente"
    }
  ]
}
```

#### 4. Testar Conexão

**Requisição:**
```bash
curl -X POST "http://localhost/api/exportacao.php" \
  -d "acao=teste"
```

**Resposta:**
```json
{
  "sucesso": true,
  "mensagem": "Conexão com banco de dados OK",
  "usuario_logado": "Gabriel Costa",
  "tipo_usuario": "master"
}
```

---

## 🔧 Uso via PHP

### Exemplo Básico

```php
<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/exportador.php';

$db = getDB();
$usuario = getCurrentUser();

// Criar instância
$exportador = new Exportador($db, $usuario);

// Exportar vendas em XLSX
$resultado = $exportador->exportar('vendas', 'xlsx', [
    'status' => 'confirmada',
    'data_inicio' => '2026-07-01'
]);

if ($resultado === false) {
    echo "Erros: " . implode('; ', $exportador->getErros());
    exit;
}

// Enviar como download
header('Content-Type: ' . $resultado['tipo_mime']);
header('Content-Disposition: attachment; filename="' . $resultado['nome'] . '"');
echo $resultado['conteudo'];
```

### Exemplo Avançado com Validação

```php
<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/exportador.php';

$db = getDB();
$usuario = getCurrentUser();

try {
    $exportador = new Exportador($db, $usuario);

    // Exportar com filtros complexos
    $filtros = [
        'status' => 'confirmada',
        'data_inicio' => date('Y-m-d', strtotime('-30 days')),
        'data_fim' => date('Y-m-d'),
        'cliente_id' => 42
    ];

    $resultado = $exportador->exportar('vendas', 'pdf', $filtros);

    if ($resultado === false) {
        $erros = $exportador->getErros();
        $avisos = $exportador->getAvisos();

        // Log de erro
        error_log("Erro na exportação: " . implode('; ', $erros));

        // Resposta ao usuário
        http_response_code(400);
        echo json_encode([
            'sucesso' => false,
            'erros' => $erros,
            'avisos' => $avisos
        ]);
        exit;
    }

    // Sucesso
    header('Content-Type: ' . $resultado['tipo_mime']);
    header('Content-Disposition: attachment; filename="' . $resultado['nome'] . '"');
    header('Content-Length: ' . strlen($resultado['conteudo']));
    echo $resultado['conteudo'];

} catch (Exception $e) {
    error_log("Exceção na exportação: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno']);
}
```

---

## 📊 Formatos Suportados

### CSV (Comma-Separated Values)

**Características:**
- Compatível com Excel, Google Sheets, LibreOffice
- Codificação UTF-8
- Suporta campos com aspas e quebras de linha
- Melhor para integração com sistemas externos

**Exemplo:**
```csv
"ID","Número","Cliente","Data Venda","Valor Total","Status","Vendedor","OS"
"1","V-2026-001","EMPRESA LTDA","2026-07-17","5000.00","confirmada","Gabriel Costa","OS-001"
```

### XLSX (Excel OpenXML)

**Características:**
- Nativo do Microsoft Excel (2007+)
- Compatível com LibreOffice, Google Sheets
- Suporta formatação básica
- Formato compactado (ZIP com XMLs internos)
- Recomendado para usuários finais

**Exemplo:**
```
[Arquivo binário com estrutura XML interna]
- Cabeçalhos em negrito
- Cores de fundo nos cabeçalhos
- Formatação numérica automática
```

### PDF

**Características:**
- Documento portátil
- Pronto para impressão
- Inclui cabeçalho com data/hora e total de registros
- Rodapé com informações de geração

**Modos de Operação:**
1. **Com TCPDF instalado**: Gera PDF real e compactado
2. **Sem TCPDF**: Retorna HTML que pode ser enviado ao navegador

### JSON

**Características:**
- Estruturado para integração com APIs
- Suporta caracteres especiais nativamente
- Inclui metadados da exportação
- Ideal para consumo programático

**Exemplo:**
```json
{
  "exportacao": {
    "tabela": "vendas",
    "data_exportacao": "2026-07-17T14:15:30",
    "total_registros": 42,
    "usuario": "Gabriel Costa",
    "dados": [
      {
        "id": 1,
        "numero": "V-2026-001",
        "cliente": "EMPRESA LTDA",
        "valor_total": 5000.00,
        "status": "confirmada"
      }
    ]
  }
}
```

---

## 🔐 Controle de Acesso

Cada tipo de usuário tem acesso a diferentes tabelas:

| Tipo | Tabelas Permitidas |
|------|-------------------|
| **master** | Todas |
| **gerente** | Vendas, Orçamentos, O.S., Estoque, Produção, Financeiro, Clientes |
| **vendedor** | Vendas (próprios), Orçamentos, Clientes |
| **projetista** | O.S., Orçamentos |
| **producao** | O.S., Estoque, Produção |
| **qualidade** | Produção, O.S. |
| **expedicao** | O.S., Vendas, Estoque |
| **contador** | Financeiro, Vendas, O.S. |

**Filtros Automáticos por Tipo:**

- **Vendedor**: Apenas suas próprias vendas (`usuario_id = ?`)
- **Outros**: Dados do setor correspondente

**Validação:**

Tentativa de acesso não autorizado retorna erro 403:

```json
{
  "sucesso": false,
  "erro": "Acesso negado à tabela: financeiro"
}
```

---

## 🔍 Filtros Disponíveis

### Vendas

```json
{
  "status": "confirmada",        // confirmada, pendente, cancelada
  "data_inicio": "2026-07-01",   // YYYY-MM-DD
  "data_fim": "2026-07-31",      // YYYY-MM-DD
  "cliente_id": 42               // ID numérico
}
```

### Orçamentos

```json
{
  "status": "enviado",           // rascunho, enviado, aprovado, rejeitado
  "data_inicio": "2026-07-01",
  "data_fim": "2026-07-31"
}
```

### Ordens de Serviço

```json
{
  "status": "em_producao",       // pendente, em_projeto, em_producao, finalizada, cancelada
  "etapa": "solda"               // corte, solda, montagem, acabamento, qualidade
}
```

### Clientes

```json
{
  "status": "ativo",             // ativo, inativo
  "busca": "empresa"             // Busca em nome ou CNPJ
}
```

### Estoque

```json
{
  "status": "disponivel",        // disponivel, reservado, danificado
  "material_id": 5               // ID numérico
}
```

### Produção

```json
{
  "status": "em_producao",       // pendente, em_producao, finalizada, cancelada
  "etapa": "montagem"            // corte, solda, montagem, acabamento
}
```

### Financeiro

```json
{
  "status": "aberto",            // aberto, parcial, pago, cancelado
  "tipo": "a_receber",           // a_receber, a_pagar
  "data_inicio": "2026-07-01",
  "data_fim": "2026-07-31"
}
```

---

## ✅ Validação de Integridade

O sistema valida automaticamente:

### 1. Campos Obrigatórios

Por tabela:

| Tabela | Campos Obrigatórios |
|--------|-------------------|
| vendas | numero, cliente, valor_total, status |
| orcamentos | numero, cliente, status |
| os | numero, cliente, status |
| clientes | razao_social, cpf_cnpj |
| estoque | numero, material, quantidade |
| producao | numero, os_numero, status |
| financeiro | numero, valor, status |

### 2. Tipos de Dados

Validação automática de tipos:

```
int:      Deve ser numérico
numeric:  Pode ser número ou string numérica
string:   Texto
date:     Formato YYYY-MM-DD
```

### 3. Integridade Referencial (FK)

Avisos são gerados para:
- Registros com cliente_id inválido
- Registros com usuario_id inválido
- Referências órfãs

### 4. Relatório de Avisos

Incluído na resposta JSON:

```json
{
  "sucesso": true,
  "avisos": [
    "Campo obrigatório vazio: data_entrega_estimada (linha 5)",
    "Tipo inválido em valor_total: abc (linha 12)",
    "Total de 42 problemas de integridade encontrados"
  ]
}
```

---

## 📝 Exemplos Práticos

### Exemplo 1: Exportar Vendas Confirmadas em Junho

**JavaScript:**
```javascript
fetch('/api/exportacao.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  body: new URLSearchParams({
    acao: 'exportar',
    tabela: 'vendas',
    formato: 'xlsx',
    filtros: JSON.stringify({
      status: 'confirmada',
      data_inicio: '2026-06-01',
      data_fim: '2026-06-30'
    }),
    download: 1
  })
})
.then(response => {
  const header = response.headers.get('content-disposition');
  const filename = header.split('filename=')[1].replace(/"/g, '');
  return response.blob().then(blob => ({blob, filename}));
})
.then(({blob, filename}) => {
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
  window.URL.revokeObjectURL(url);
})
.catch(err => console.error('Erro:', err));
```

### Exemplo 2: Integração com Sistema Externo

**Python:**
```python
import requests
import json
from base64 import b64decode

# 1. Autenticar (assumindo cookies de sessão)
cookies = {'PHPSESSID': 'seu_session_id'}

# 2. Exportar dados
response = requests.post('http://localhost/api/exportacao.php', 
    data={
        'acao': 'exportar',
        'tabela': 'vendas',
        'formato': 'json',
        'filtros': json.dumps({
            'status': 'confirmada',
            'data_inicio': '2026-07-01'
        })
    },
    cookies=cookies
)

data = response.json()

if data['sucesso']:
    # Decodificar base64
    conteudo = b64decode(data['conteudo_base64']).decode('utf-8')
    json_data = json.loads(conteudo)
    
    # Procesar dados
    for venda in json_data['exportacao']['dados']:
        print(f"Venda {venda['numero']}: R$ {venda['valor_total']}")
else:
    print(f"Erro: {data['erro']}")
```

### Exemplo 3: Agenda de Exportações Automáticas

**Shell Script:**
```bash
#!/bin/bash

# Exportar vendas diárias
curl -X POST "http://localhost/api/exportacao.php" \
  -H "Cookie: PHPSESSID=$SESSION_ID" \
  -d "acao=exportar" \
  -d "tabela=vendas" \
  -d "formato=xlsx" \
  -d "filtros={\"data_inicio\":\"$(date -d 'yesterday' +%Y-%m-%d)\",\"data_fim\":\"$(date +%Y-%m-%d)\"}" \
  -d "download=1" \
  -o "vendas_$(date +%Y-%m-%d).xlsx"

# Enviar por email
echo "Relatório anexado" | mail -s "Vendas de $(date +%d/%m/%Y)" \
  -a "vendas_$(date +%Y-%m-%d).xlsx" \
  vendedor@cozinka.com.br
```

---

## 🐛 Troubleshooting

### Erro: "Sessão expirada"

```json
{
  "sucesso": false,
  "erro": "Sessão expirada. Faça login novamente."
}
```

**Solução**: Autenticar antes de chamar a API

### Erro: "Acesso negado à tabela"

```json
{
  "sucesso": false,
  "erro": "Acesso negado à tabela: financeiro"
}
```

**Solução**: Verificar permissões do usuário na tabela de controle de acesso

### Erro: "Nenhum dado encontrado"

**Solução**: Verificar filtros - pode não haver registros que atendam aos critérios

### PDF retorna HTML ao invés de binário

**Causa**: TCPDF não instalado no servidor

**Solução**: 
```
composer require tecnickcom/tcpdf
```
Ou usar outro formato (CSV, XLSX, JSON)

### XLSX não abre no Excel

**Causa**: Problema na estrutura XML interna

**Solução**: 
1. Validar se ZipArchive está disponível
2. Tentar com outro formato (CSV)
3. Usar `php -m | grep zip` para verificar

### Performance lenta com muitos registros

**Solução**: Usar filtros para reduzir volume de dados
- Adicionar filtro de data (`data_inicio`, `data_fim`)
- Filtrar por status específico
- Limitar a 10.000 registros por exportação

### Base64 truncado na resposta

**Causa**: Limite de POST_MAX_SIZE ou output_buffering

**Solução**:
```php
// php.ini
post_max_size = 256M
upload_max_filesize = 256M
```

---

## 📋 Checklist de Implementação

- [x] Classe Exportador criada
- [x] API REST implementada
- [x] Suporte CSV, XLSX, PDF, JSON
- [x] Validação de integridade
- [x] Controle de acesso por setor
- [x] Logging de exportações
- [x] Documentação completa
- [ ] Testes unitários
- [ ] Interface web (dashboard)
- [ ] Agendamento de exportações

---

## 📞 Suporte e Contribuições

**Desenvolvido para**: Cozinka ERP  
**Versão**: 1.0  
**Data**: 17/07/2026  
**Autor**: Gabriel Costa  

Para reportar bugs ou sugerir melhorias:
- Email: g4bs011.gbl@gmail.com
- Repositório: Git do projeto Cozinka ERP
