# Sistema de Etiquetas e Ordem de Produção - Cozinka ERP

## 📋 Visão Geral

Sistema completo de geração, impressão e rastreamento de:
- **Etiquetas com QR-code** para Ordens de Serviço (O.S.)
- **Etiquetas com QR-code** para Ordens de Produção (O.P.)
- **Ordem de Produção (O.P.)** com detalhes completos
- **Códigos de Barras 128** para rastreamento
- **Integração com Estoque** para controle

---

## 🗂️ Arquivos Principais

### 1. **`/api/etiqueta_qrcode.php`** - API Central
Gerencia todas as operações de etiquetas e QR-codes.

**Endpoints disponíveis:**

#### Gerar QR-code para O.S.
```
POST /api/etiqueta_qrcode.php
acao=gerar_qr_svg
os_id=123
```

**Retorno:**
```json
{
  "sucesso": true,
  "etiqueta_id": 456,
  "os_id": 123,
  "os_numero": "OS-001",
  "qr_content": "OS|OS-001|123",
  "qr_url": "https://api.qrserver.com/v1/create-qr-code/...",
  "dados_qr": {
    "id": 123,
    "numero": "OS-001",
    "tipo": "ordem_servico",
    "timestamp": 1234567890,
    "url": "http://localhost/modules/os/scan.php?code=OS|OS-001|123"
  }
}
```

#### Gerar QR-code para O.P.
```
POST /api/etiqueta_qrcode.php
acao=gerar_qr_svg_op
op_numero=001-01
os_id=123
```

#### Gerar Código 128
```
POST /api/etiqueta_qrcode.php
acao=gerar_codigo128
texto=ABC123XYZ
os_id=123
```

#### Listar etiquetas de uma O.S.
```
GET /api/etiqueta_qrcode.php?acao=listar_etiquetas&os_id=123
```

#### Registrar impressão
```
POST /api/etiqueta_qrcode.php
acao=registrar_impressao
etiqueta_id=456
```

#### Estatísticas de impressão
```
GET /api/etiqueta_qrcode.php?acao=stats_impressoes
GET /api/etiqueta_qrcode.php?acao=stats_impressoes&os_id=123
```

#### Excluir etiqueta
```
POST /api/etiqueta_qrcode.php
acao=excluir_etiqueta
etiqueta_id=456
```

---

### 2. **`/modules/os/gerar_etiquetas.php`** - Interface de Geração
Interface visual para geração e impressão de etiquetas.

**Funcionalidades:**
- Abas para navegação (O.S., O.P., Histórico)
- Seleção de Ordem de Serviço
- Preview do QR-code
- Impressão imediata
- Download de QR-code
- Histórico de impressões

**Formatos de impressão suportados:**
- **10x15cm** - Etiqueta pequena (padrão)
- **A4** - Etiqueta em folha completa

---

### 3. **`/modules/os/ordem_producao.php`** - Painel de O.P.
Painel completo para gerenciamento de Ordens de Produção.

**Funcionalidades:**
- Criar nova O.P. a partir de O.S.
- Visualizar status em tempo real
- Acompanhar progresso dos itens
- Controlar etapas de produção
- Atribuir responsáveis
- Gerar PDF da O.P.
- Integração com etiquetas

---

## 📊 Estrutura de Banco de Dados

### Tabela: `etiquetas_impressas`
```sql
CREATE TABLE etiquetas_impressas (
    id INT PRIMARY KEY,
    os_id INT,                    -- Ordem de Serviço
    op_numero VARCHAR(50) UNIQUE, -- Número da O.P.
    tipo ENUM(...),               -- qr_os, qr_op, codigo128
    conteudo VARCHAR(500),        -- Conteúdo do QR
    dados_qr JSON,               -- Dados estruturados
    impressoes INT,              -- Contador de impressões
    usuario_id INT,              -- Quem criou
    data_criacao TIMESTAMP,
    data_ultima_impressao TIMESTAMP
);
```

### Tabela: `ordens_producao`
```sql
CREATE TABLE ordens_producao (
    id INT PRIMARY KEY,
    os_id INT,                    -- Ordem de Serviço
    numero VARCHAR(50) UNIQUE,    -- OP-001, OP-001-01, etc
    status ENUM(...),             -- pendente, em_producao, concluida
    responsavel_id INT,           -- Usuário responsável
    data_inicio DATETIME,
    data_termino DATETIME,
    prazo_original DATETIME,
    observacoes TEXT,
    criado_em TIMESTAMP,
    atualizado_em TIMESTAMP
);
```

### Tabela: `ordens_producao_itens`
```sql
CREATE TABLE ordens_producao_itens (
    id INT PRIMARY KEY,
    op_id INT,                    -- Ordem de Produção
    os_item_id INT,               -- Item da O.S.
    quantidade INT,
    quantidade_produzida INT,
    valor_unitario DECIMAL(10,2),
    status ENUM(...),             -- pendente, produzindo, concluido
    observacao TEXT,
    data_conclusao DATETIME
);
```

### Tabela: `ordens_producao_etapas`
```sql
CREATE TABLE ordens_producao_etapas (
    id INT PRIMARY KEY,
    op_id INT,                    -- Ordem de Produção
    etapa VARCHAR(50),            -- corte, dobra, solda, etc
    status ENUM(...),             -- pendente, em_producao, concluido
    usuario_id INT,               -- Responsável
    data_inicio DATETIME,
    data_conclusao DATETIME,
    observacao TEXT,
    sequencia INT                 -- Ordem de execução
);
```

---

## 🎯 Fluxo de Uso

### Cenário 1: Gerar Etiqueta para O.S.

1. Acessar `/modules/os/gerar_etiquetas.php`
2. Aba "Ordens de Serviço"
3. Selecionar uma O.S. da lista
4. Preview do QR-code aparece
5. Clicar "🖨️ Imprimir" para abrir diálogo de impressão
6. Selecionar impressora e formato (10x15cm ou A4)
7. Imprimir

**Impressão registrada automaticamente no banco.**

---

### Cenário 2: Criar Ordem de Produção

1. Acessar `/modules/os/ordem_producao.php`
2. Clicar "➕ Nova O.P."
3. Informar número da O.S.
4. Sistema cria O.P. com número igual ao da O.S. (ou O.S.-01, O.S.-02 para múltiplos itens)
5. Distribuir itens entre etapas
6. Atribuir responsáveis
7. Monitorar progresso

**Status possíveis:**
- Pendente → Em Produção → Concluída
- Parada (atrasos)
- Cancelada

---

### Cenário 3: Rastreamento de Estoque

1. Etiqueta é gerada com QR-code
2. Produto entra em produção
3. Scanner lê QR-code na cada etapa
4. Sistema registra movimentação no estoque
5. Histórico completo disponível

---

## 🔢 Formatos de Número

### O.P. (Ordem de Produção)
- **Padrão:** `OS-001` (igual ao número da O.S.)
- **Múltiplos itens:** `OS-001-01`, `OS-001-02`, etc.
- **Formato no QR:** `OP|OS-001|123` (tipo|número|id_os)

### Etiqueta
- **QR-code O.S.:** `OS|OS-001|123`
- **QR-code O.P.:** `OP|OS-001|123`
- **Código 128:** Qualquer texto (produto, lote, etc)

---

## 🖨️ Impressão

### Formatos Suportados

#### 1. Etiqueta 10x15cm
- Tamanho: 100mm x 150mm
- Quantidade: Múltiplas por folha A4
- Ideal para: Identificação de lotes na produção

#### 2. Folha A4
- Uma etiqueta por página
- Ideal para: Impressão individual
- Bordas com linhas de corte

#### 3. Personalizado
Editar CSS em `/modules/os/gerar_etiquetas.php`:
```css
.etiqueta-print-item {
    width: 8cm;      /* Largura */
    height: 10cm;    /* Altura */
    margin: 0.5cm;   /* Espaçamento */
}
```

---

## 🔐 Permissões

**Quem pode acessar:**
- `master` - Acesso total
- `gerente` - Acesso total
- `producao` - Gerar e imprimir etiquetas
- `projetista` - Visualizar etiquetas
- `dashboard_producao` - Visualizar etiquetas

**Endpoints protegidos:**
- Todas as ações em `/api/etiqueta_qrcode.php`
- Página `/modules/os/ordem_producao.php`

---

## 📈 Estatísticas

Dados rastreados:
- Total de etiquetas geradas por tipo
- Número de impressões por etiqueta
- Data/hora da última impressão
- Usuário que gerou a etiqueta
- Duração de cada etapa de produção

**Acessar:** Aba "Histórico" em `/modules/os/gerar_etiquetas.php`

---

## 🔧 Configuração

### Serviço de QR-code
Padrão: `https://api.qrserver.com/v1/create-qr-code/`

Para usar outro serviço, editar em `/api/etiqueta_qrcode.php`:
```php
$qr_url = "seu_serviço?size=400x400&data=" . urlencode($qr_content);
```

### Serviço de Código de Barras
Padrão: `https://www.aspose.cloud/v3.0/barcode/generate`

Para usar localmente, instalar biblioteca:
```bash
composer require tecnickcom/barcode
```

---

## ⚙️ Troubleshooting

### QR-code não aparece
1. Verificar conexão com internet (serviço externo)
2. Verificar console do navegador (F12)
3. Testar URL diretamente: `https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=test`

### Impressão com problema de tamanho
1. Ir a Arquivo → Configurar página
2. Ajustar margens para 0
3. Desmarcar "Imprimir cabeçalho/rodapé"
4. Selecionar orientação correta

### Etiqueta não aparece no banco
1. Verificar se tabela foi criada: `DESC etiquetas_impressas;`
2. Verificar permissões do usuário
3. Checar logs do servidor

---

## 📱 Integração com Dispositivos Móveis

Sistema preparado para scanner de QR-code via smartphone:
1. Abrir `/modules/os/scan.php?code=OS|OS-001|123`
2. Scanner automaticamente processa código
3. Registra movimentação no estoque

---

## 🚀 Próximas Features (TIER 2)

- [ ] Integração automática com WMS (Warehouse Management)
- [ ] Impressão em rede (CUPS no servidor)
- [ ] Geração de etiquetas em massa
- [ ] Histórico visual (timeline)
- [ ] Alertas de atraso
- [ ] Dashboard em tempo real
- [ ] Exportação para PDF em lote
- [ ] Integração com RFID
- [ ] Rastreamento GPS

---

## 📞 Suporte

**Contato:** g4bs011.gbl@gmail.com
**Projeto:** Cozinka ERP (TIER 1 + TIER 2)
**Versão:** 1.0

---

**Última atualização:** 2026-07-17
