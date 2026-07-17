# 📚 Documentação de APIs - Cozinka ERP TIER 1

## 🔐 Autenticação

Todas as APIs requerem autenticação via `$_SESSION['usuario_id']` e permissões:
- `master` - acesso total
- `gerente` - acesso administrativo
- `sac` - acesso SAC
- `expedicao` - acesso expedição
- `producao` - acesso produção

---

## 🏷️ API de Etiquetas

**Arquivo**: `/api/etiqueta.php`

### Gerar QR-code
```
POST /api/etiqueta.php
{
  "acao": "gerar_qr",
  "os_id": 123
}

Response:
{
  "sucesso": true,
  "id": 1,
  "os_numero": "OS-001",
  "qr_content": "OS|OS-001|123",
  "qr_data_uri": "https://api.qrserver.com/..."
}
```

### Listar Etiquetas
```
GET /api/etiqueta.php?acao=listar&os_id=123

Response:
{
  "sucesso": true,
  "total": 5,
  "etiquetas": [
    {
      "id": 1,
      "os_id": 123,
      "conteudo": "OS|OS-001|123",
      "impressoes": 3,
      "qr_svg": "https://..."
    }
  ]
}
```

### Registrar Impressão
```
POST /api/etiqueta.php
{
  "acao": "registrar_impressao",
  "etiqueta_id": 1
}
```

---

## 📦 API de Estoque

**Arquivo**: `/api/estoque_movimentacoes.php`

### Entrada de Material
```
POST /api/estoque_movimentacoes.php
{
  "acao": "entrada",
  "produto_id": 5,
  "quantidade": 100.00,
  "referencia": "NF-001234"
}

Response:
{
  "sucesso": true,
  "movimento_id": 42,
  "saldo_atual": 150.50
}
```

### Saída de Material
```
POST /api/estoque_movimentacoes.php
{
  "acao": "saida",
  "produto_id": 5,
  "quantidade": 25.00,
  "referencia": "OS-001"
}
```

### Obter Saldo
```
GET /api/estoque_movimentacoes.php?acao=obter_saldo&produto_id=5

Response:
{
  "sucesso": true,
  "produto_id": 5,
  "saldo": 125.50
}
```

### Listar Saldos
```
GET /api/estoque_movimentacoes.php?acao=listar_saldos&filtro=nome_produto

Response:
{
  "sucesso": true,
  "total": 150,
  "produtos": [
    {
      "id": 5,
      "nome": "Parafuso",
      "quantidade_total": 1000.50,
      "quantidade_minima": 100,
      "quantidade_maxima": 5000
    }
  ]
}
```

### Configurar Limites
```
POST /api/estoque_movimentacoes.php
{
  "acao": "configurar_limites",
  "produto_id": 5,
  "quantidade_minima": 100,
  "quantidade_maxima": 5000
}
```

---

## 📋 API de BOM

**Arquivo**: `/api/bom.php`

### Adicionar Item à BOM
```
POST /api/bom.php
{
  "acao": "adicionar_item",
  "produto_principal_id": 10,
  "material_id": 5,
  "quantidade": 4.00,
  "unidade": "un"
}

Response:
{
  "sucesso": true,
  "id": 1,
  "mensagem": "Item adicionado à BOM"
}
```

### Obter BOM de um Produto
```
GET /api/bom.php?acao=obter_bom_produto&produto_id=10

Response:
{
  "sucesso": true,
  "total": 3,
  "itens": [
    {
      "id": 1,
      "material_id": 5,
      "material_nome": "Parafuso",
      "quantidade": 4.00,
      "unidade": "un"
    }
  ]
}
```

### Requisitar por BOM
```
POST /api/bom.php
{
  "acao": "requisitar_por_bom",
  "os_id": 123,
  "produto_id": 10,
  "quantidade": 5
}

Response:
{
  "sucesso": true,
  "requisicoes": [
    {
      "material_id": 5,
      "quantidade": 20,
      "requisicao_id": 42
    }
  ]
}
```

### Registrar Consumo
```
POST /api/bom.php
{
  "acao": "registrar_consumo",
  "requisicao_id": 42,
  "quantidade": 20
}

Response:
{
  "sucesso": true,
  "consumo_total": 20
}
```

---

## 📞 API de Chamados (SAC)

**Arquivo**: `/api/chamados.php`

### Criar Chamado
```
POST /api/chamados.php
{
  "acao": "criar",
  "cliente_id": 5,
  "titulo": "Problema na entrega",
  "descricao": "Produto chegou com defeito",
  "prioridade": "alta",
  "categoria": "Qualidade"
}

Response:
{
  "sucesso": true,
  "chamado_id": 42,
  "numero": "CHA-20260717000001"
}
```

### Listar Chamados
```
GET /api/chamados.php?acao=listar&status=novo&prioridade=critica

Response:
{
  "sucesso": true,
  "total": 3,
  "chamados": [
    {
      "id": 42,
      "numero": "CHA-20260717000001",
      "titulo": "Problema na entrega",
      "cliente_id": 5,
      "razao_social": "Cliente XYZ",
      "status": "novo",
      "prioridade": "critica"
    }
  ]
}
```

### Atualizar Status
```
POST /api/chamados.php
{
  "acao": "atualizar_status",
  "chamado_id": 42,
  "status": "em_atendimento"
}
```

### Atribuir Responsável
```
POST /api/chamados.php
{
  "acao": "atribuir",
  "chamado_id": 42,
  "usuario_id": 7
}
```

### Adicionar Resposta
```
POST /api/chamados.php
{
  "acao": "adicionar_resposta",
  "chamado_id": 42,
  "mensagem": "Vamos resolver isso rapidinho",
  "tipo": "cliente"
}
```

---

## 📮 API de Expedição

**Arquivo**: `/api/expedicao.php`

### Criar Expedição
```
POST /api/expedicao.php
{
  "acao": "criar",
  "os_id": 123
}

Response:
{
  "sucesso": true,
  "expedicao_id": 1,
  "numero": "EXP-20260717000001"
}
```

### Listar Expedições
```
GET /api/expedicao.php?acao=listar&status=despachado

Response:
{
  "sucesso": true,
  "total": 10,
  "expedicoes": [
    {
      "id": 1,
      "numero": "EXP-20260717000001",
      "os_numero": "OS-001",
      "razao_social": "Cliente XYZ",
      "status": "despachado",
      "transportadora": "Sedex"
    }
  ]
}
```

### Atualizar Status
```
POST /api/expedicao.php
{
  "acao": "atualizar_status",
  "expedicao_id": 1,
  "status": "entregue"
}
```

### Conferir Item
```
POST /api/expedicao.php
{
  "acao": "conferir_item",
  "item_id": 5
}
```

---

## ⚠️ Códigos de Erro

| Código | Mensagem | Causa |
|--------|----------|-------|
| 400 | Bad Request | Parâmetros faltando ou inválidos |
| 404 | Not Found | Recurso não existe |
| 403 | Forbidden | Sem permissão para acessar |
| 500 | Server Error | Erro no processamento |

Resposta de erro:
```json
{
  "erro": "Descrição do erro"
}
```

---

## 🔄 Padrão de Resposta

Todas as APIs retornam JSON com este padrão:

**Sucesso**:
```json
{
  "sucesso": true,
  "mensagem": "Operação realizada",
  "dados": { ... }
}
```

**Erro**:
```json
{
  "erro": "Descrição do erro"
}
```

---

## 📊 Headers Recomendados

```
Content-Type: application/json
Accept: application/json
Authorization: Bearer <session_token> (futuro)
```

---

## 🚀 Exemplo Completo de Fluxo

```javascript
// 1. Criar chamado SAC
const chamado = await fetch('/api/chamados.php', {
  method: 'POST',
  body: new FormData({
    acao: 'criar',
    cliente_id: 5,
    titulo: 'Pedir orçamento'
  })
}).then(r => r.json());

// 2. Criar pedido de venda (em vendas.php - futuro)
const venda = await fetch('/api/vendas.php', {
  method: 'POST',
  body: new FormData({
    acao: 'criar',
    cliente_id: 5,
    titulo: 'Orçamento aprovado'
  })
}).then(r => r.json());

// 3. Requisitar materiais por BOM
const requisicao = await fetch('/api/bom.php', {
  method: 'POST',
  body: new FormData({
    acao: 'requisitar_por_bom',
    os_id: 123,
    produto_id: 10,
    quantidade: 5
  })
}).then(r => r.json());

// 4. Criar expedição quando pronto
const expedicao = await fetch('/api/expedicao.php', {
  method: 'POST',
  body: new FormData({
    acao: 'criar',
    os_id: 123
  })
}).then(r => r.json());
```

---

---

## 🤖 API de MRP (Material Requirements Planning)

**Arquivo**: `/api/mrp.php`

### Analisar Demanda
```
GET /api/mrp.php?acao=analisar_demanda

Response:
{
  "sucesso": true,
  "total": 5,
  "criticas": 2,
  "demanda": [
    {
      "venda_id": 1,
      "venda_numero": "VD-001",
      "cliente": "Empresa XYZ",
      "produto_id": 10,
      "produto_nome": "Fogão Inox",
      "quantidade_solicitada": 10,
      "estoque_atual": 2,
      "faltante": 8,
      "percentual_falta": 80.0,
      "dias_para_entrega": 2,
      "urgencia_score": 95.5,
      "status_urgencia": "crítica"
    }
  ]
}
```

### Sugerir Ordens de Produção
```
GET /api/mrp.php?acao=sugerir_ordens

Response:
{
  "sucesso": true,
  "total_sugestoes": 3,
  "criticas": 1,
  "altas": 2,
  "sugestoes": [
    {
      "produto_id": 10,
      "produto_nome": "Fogão Inox",
      "estoque_atual": 2.0,
      "estoque_minimo": 5,
      "quantidade_vendas": 10.0,
      "necessario": 8.0,
      "quantidade_sugerida": 9.2,
      "margem_seguranca": 15,
      "prioridade": "crítica",
      "acao_recomendada": "Criar O.S. de produção"
    }
  ]
}
```

### Prever Materiais (BOM)
```
POST /api/mrp.php
{
  "acao": "prever_materiais",
  "produto_id": 10,
  "quantidade": 5
}

Response:
{
  "sucesso": true,
  "produto_id": 10,
  "quantidade_producao": 5,
  "total_materiais": 4,
  "materiais_faltando": 1,
  "materiais": [
    {
      "material_id": 5,
      "material_nome": "Parafuso M8",
      "qtd_bom": 4.0,
      "unidade": "un",
      "quantidade_necessaria": 20.0,
      "estoque_atual": 30.0,
      "faltante": 0.0,
      "status": "ok"
    }
  ]
}
```

### Otimizar Cronograma
```
GET /api/mrp.php?acao=otimizar_cronograma

Response:
{
  "sucesso": true,
  "total_os": 3,
  "acelerar": 1,
  "focar": 2,
  "cronograma": [
    {
      "os_id": 5,
      "os_numero": "OS-001",
      "cliente": "Cliente A",
      "data_prevista": "2026-07-18",
      "dias_faltando": 1,
      "prioridade": "alta",
      "progresso_percentual": 50,
      "etapas_totais": 5,
      "etapas_concluidas": 2,
      "score_urgencia": 250.0,
      "recomendacao": "ACELERAR"
    }
  ]
}
```

### Alertas Críticos
```
GET /api/mrp.php?acao=alertas

Response:
{
  "sucesso": true,
  "total": 3,
  "criticas": 2,
  "alertas": [
    {
      "tipo": "produto_sem_estoque",
      "severidade": "crítica",
      "titulo": "Produto sem estoque: Fogão",
      "descricao": "Produto não tem quantidade no estoque",
      "produto_id": 10,
      "icon": "🚨"
    }
  ]
}
```

---

**Última atualização**: 2026-07-17  
**Versão**: 1.1 (TIER 1 + TIER 2 Fase 1)  
**Status**: ✅ Documentação Completa (MRP adicionado)
