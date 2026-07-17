# Documentação: Módulo de Desenho Técnico e Aprovação

## Visão Geral

O módulo de **Desenho Técnico e Aprovação** implementa um fluxo completo de gerenciamento de desenhos técnicos para ordens de produção no Cozinka ERP. Oferece:

- Upload e armazenamento de múltiplos formatos (PDF, DWG, PNG, JPG, DXF, 3D)
- Sistema de versionamento automático (v1.0, v1.1, v2.0...)
- Fluxo de aprovação em 3 etapas (Gerência → Produção → Qualidade)
- Pré-visualização de imagens
- Histórico completo de mudanças com rastreamento de usuário
- Integração total com O.S. (Ordem de Serviço)

---

## Arquitetura

### Tabelas do Banco de Dados

```
desenhos_tecnicos
├── id (PK)
├── os_id (FK) → ordens_servico
├── titulo
├── descricao
├── versao (v1.0, v1.1, v2.0...)
├── status (rascunho, submetido, em_revisao, aprovado, rejeitado, obsoleto)
├── prioridade (baixa, normal, alta, critica)
├── qualidade_exigida (normal, certificada, alimentar, clinica)
├── usuario_projetista_id (FK)
├── usuario_gerente_id (FK)
├── usuario_producao_id (FK)
├── data_submissao
├── data_aprovacao_gerencia
├── data_aprovacao_producao
├── data_rejeicao
├── observacoes_gerencia
├── observacoes_producao
├── observacoes_internas
└── created_at, updated_at

desenhos_arquivos
├── id (PK)
├── desenho_id (FK) → desenhos_tecnicos
├── arquivo_tipo (pdf, dwg, png, jpg, 3d, dxf, outro)
├── nome_original
├── nome_arquivo
├── caminho_arquivo
├── tamanho_bytes
├── dimensoes (para imagens)
├── sequencia
├── usuario_upload_id (FK)
└── created_at

desenhos_revisoes
├── id (PK)
├── desenho_id (FK) → desenhos_tecnicos
├── versao_anterior
├── versao_nova
├── tipo_revisao (criacao, atualizacao, correcao, aprovacao, rejeicao)
├── motivo_revisao
├── usuario_id (FK)
├── alteracoes_descricao
└── created_at

desenhos_aprovaes
├── id (PK)
├── desenho_id (FK) → desenhos_tecnicos
├── etapa (gerencia, producao, qualidade)
├── status (pendente, aprovado, rejeitado, observacoes)
├── usuario_id (FK)
├── observacoes
├── data_resposta
├── prazo_resposta
├── requer_alteracoes
└── created_at, updated_at

desenhos_historico
├── id (PK)
├── desenho_id (FK) → desenhos_tecnicos
├── acao (criacao, submissao, aprovacao, rejeicao...)
├── usuario_id (FK)
├── status_anterior
├── status_novo
├── detalhes
├── endereco_ip
└── created_at
```

---

## Fluxo de Trabalho

### 1. Criação do Desenho (Projetista)

**Status: `rascunho`**

```
Projetista → Módulo → Nova Ordem Técnica
   ↓
Preenche Titulo, Descrição, Qualidade Exigida
   ↓
Seleciona Prioridade
   ↓
Upload Arquivo(s) (PDF, DWG, PNG, etc)
   ↓
Clica "Salvar como Rascunho" OU "Enviar para Revisão"
```

**Ação na API:**
```php
POST /api/desenho.php
- acao=criar_desenho
- os_id=123
- titulo="Estrutura do Forno"
- descricao="Dimensões e especificações"
- qualidade_exigida="certificada"
- prioridade="alta"
- arquivos[]=file.pdf
- enviar="rascunho" | "submetido"
```

### 2. Submissão para Aprovação (Projetista)

**Status: `submetido` → `em_revisao`**

```
Projetista revisa seu rascunho
   ↓
Clica "Enviar para Revisão"
   ↓
Sistema cria registros de aprovação
   ↓
Notifica Gerente
```

### 3. Revisão pela Gerência

**Status: `em_revisao`**

**Opções:**
- **Aprova** → passa para Produção
- **Rejeita** → retorna ao Projetista com observações
- **Observações** → solicita ajustes específicos

```php
// Aprovar
POST /api/desenho.php
- acao=aprovar
- desenho_id=5
- etapa=gerencia
- observacoes="Ajustes menores na dimensão"

// Rejeitar
POST /api/desenho.php
- acao=rejeitar
- desenho_id=5
- etapa=gerencia
- motivo="Dimensões incompatíveis com molde"
- observacoes="Revisar seção 3.2 do desenho anterior"
```

### 4. Revisão pela Produção

**Status: `em_revisao`**

```
Produção examina o desenho aprovado
   ↓
Valida viabilidade de fabricação
   ↓
Aprova OU Rejeita com observações
```

### 5. Aprovação Final

**Status: `aprovado`**

```
Desenho está pronto para produção
   ↓
Disponível para impressão junto com O.P.
   ↓
Integração com Qualidade
```

---

## API Endpoints

### 1. Criar Novo Desenho
```
POST /api/desenho.php
Content-Type: multipart/form-data

Parameters:
- acao=criar_desenho (required)
- os_id=123 (required)
- titulo="..." (required)
- descricao="..." (optional)
- qualidade_exigida="normal|certificada|alimentar|clinica"
- prioridade="baixa|normal|alta|critica"
- arquivos[] (multiple files)
- enviar="rascunho|submetido"

Response:
{
  "sucesso": true,
  "mensagem": "Desenho criado com sucesso. 1 arquivo enviado.",
  "desenho_id": 5,
  "versao": "v1.0",
  "status": "rascunho",
  "redirect": "../../modules/engenharia/desenho_tecnico.php?os_id=123&desenho_id=5"
}
```

### 2. Submeter para Aprovação
```
POST /api/desenho.php

Parameters:
- acao=submeter_aprovacao
- desenho_id=5

Response:
{
  "sucesso": true,
  "mensagem": "Desenho submetido para aprovação",
  "status": "submetido"
}
```

### 3. Aprovar Desenho
```
POST /api/desenho.php

Parameters:
- acao=aprovar
- desenho_id=5
- etapa="gerencia|producao|qualidade"
- observacoes="..." (optional)

Response:
{
  "sucesso": true,
  "mensagem": "Desenho aprovado com sucesso",
  "status": "em_revisao" | "aprovado",
  "proxima_etapa": "Aguardando próximas aprovações"
}
```

### 4. Rejeitar Desenho
```
POST /api/desenho.php

Parameters:
- acao=rejeitar
- desenho_id=5
- etapa="gerencia|producao"
- motivo="Dimensões incompatíveis" (required)
- observacoes="..." (optional)

Response:
{
  "sucesso": true,
  "mensagem": "Desenho rejeitado. Notificação enviada ao projetista.",
  "status": "rejeitado",
  "proxima_acao": "Aguardando revisão e resubmissão do projetista"
}
```

### 5. Obter Desenho Completo
```
GET /api/desenho.php?acao=obter_desenho&desenho_id=5

Response:
{
  "sucesso": true,
  "desenho": { ... },
  "arquivos": [ ... ],
  "aprovaes": [ ... ],
  "historico": [ ... ]
}
```

### 6. Listar Desenhos de uma O.S.
```
GET /api/desenho.php?acao=listar_desenhos&os_id=123&status=aprovado

Response:
{
  "sucesso": true,
  "total": 3,
  "desenhos": [ ... ]
}
```

### 7. Obter Histórico
```
GET /api/desenho.php?acao=obter_historico&desenho_id=5

Response:
{
  "sucesso": true,
  "total": 8,
  "historico": [
    {
      "id": 1,
      "acao": "criacao",
      "usuario_nome": "João da Silva",
      "status_anterior": null,
      "status_novo": "rascunho",
      "detalhes": "Desenho criado por João da Silva",
      "created_at": "2026-07-17 10:30:00"
    },
    ...
  ]
}
```

---

## Permissões e Controle de Acesso

| Ação | Projetista | Gerente | Produção | Master |
|------|-----------|---------|----------|--------|
| Criar Desenho | ✓ | ✓ | ✗ | ✓ |
| Editar Rascunho | ✓ | ✓ | ✗ | ✓ |
| Submeter para Revisão | ✓ | ✓ | ✗ | ✓ |
| Aprovar (Gerência) | ✗ | ✓ | ✗ | ✓ |
| Aprovar (Produção) | ✗ | ✗ | ✓ | ✓ |
| Rejeitar | ✓ (próprio) | ✓ | ✓ | ✓ |
| Visualizar | ✓ | ✓ | ✓ | ✓ |
| Deletar | ✓ (rascunho) | ✓ | ✗ | ✓ |
| Imprimir | ✓ | ✓ | ✓ | ✓ |

---

## Integração com Outros Módulos

### Com Ordem de Serviço (O.S.)

Cada desenho está vinculado a uma O.S.:

```php
// Obter desenhos aprovados de uma O.S.
SELECT * FROM desenhos_tecnicos
WHERE os_id = 123
  AND status = 'aprovado'
ORDER BY versao DESC
```

### Com Qualidade

Desenhos aprovados ficam disponíveis para o módulo de Qualidade:

```php
// Verificar se O.S. tem desenho técnico aprovado
$stmt = $db->prepare("
    SELECT COUNT(*) FROM desenhos_tecnicos
    WHERE os_id = ? AND status = 'aprovado'
");
$stmt->execute([$osId]);
$temDesenho = $stmt->fetchColumn() > 0;
```

### Com Produção (O.P.)

Desenhos aprovados são impressos junto com a O.P.:

```php
// Na impressão da O.P., incluir desenho técnico
$stmt = $db->prepare("
    SELECT da.* FROM desenhos_arquivos da
    INNER JOIN desenhos_tecnicos d ON d.id = da.desenho_id
    WHERE d.os_id = ? AND d.status = 'aprovado'
    AND arquivo_tipo IN ('pdf', 'png', 'jpg')
    ORDER BY da.sequencia
");
$stmt->execute([$osId]);
```

---

## Versionamento

O sistema gera versões automaticamente:

- **Criação**: v1.0
- **Primeira revisão**: v1.1, v1.2...
- **Mudança significativa**: v2.0, v2.1...

Cada versão é rastreada na tabela `desenhos_revisoes`:

```php
{
  "id": 3,
  "desenho_id": 5,
  "versao_anterior": "v1.0",
  "versao_nova": "v1.1",
  "tipo_revisao": "correcao",
  "motivo_revisao": "Ajuste dimensão eixo X",
  "usuario_id": 2,
  "created_at": "2026-07-17 11:45:00"
}
```

---

## Armazenamento de Arquivos

**Diretório Base:** `/assets/uploads/desenhos/`

**Estrutura:**
```
/assets/uploads/desenhos/
├── desenho_5_xyz123.pdf
├── desenho_5_xyz124.png
├── desenho_5_xyz125.dwg
├── desenho_6_abc456.pdf
└── ...
```

**Nomenclatura:**
```
desenho_{desenho_id}_{uniqid}.{extensao}
```

**Segurança:**
- Máximo 50MB por arquivo
- Tipos permitidos: PDF, DWG, PNG, JPG, DXF, 3DS, OBJ
- Validação MIME type
- Nomes únicos para evitar conflitos

---

## Rastreamento e Histórico

Todas as ações são registradas na tabela `desenhos_historico`:

| Ação | Quando |
|------|--------|
| `criacao` | Desenho criado |
| `submissao` | Enviado para revisão |
| `aprovacao` | Aprovado em alguma etapa |
| `rejeicao` | Rejeitado com motivo |
| `atualizacao` | Informações alteradas |
| `versao` | Nova versão criada |
| `arquivo_adicionado` | Arquivo vinculado |
| `arquivo_removido` | Arquivo deletado |

Cada registro inclui:
- Usuário que executou a ação
- Timestamp exato
- IP de origem
- Status anterior e novo
- Detalhes adicionais

**Exemplo de consulta:**
```php
SELECT dh.*, u.nome, u.email
FROM desenhos_historico dh
LEFT JOIN usuarios u ON u.id = dh.usuario_id
WHERE dh.desenho_id = 5
ORDER BY dh.created_at DESC
```

---

## Notificações (Futura Implementação)

```php
// Notificar gerente quando desenho é submetido
enviarNotificacao([
    'para' => $usuarioGerenteId,
    'tipo' => 'desenho_aguardando_aprovacao',
    'assunto' => 'Desenho técnico aguardando sua revisão',
    'mensagem' => "Desenho '{$titulo}' da OS #{$osId} foi submetido",
    'link' => "modules/engenharia/desenho_tecnico.php?os_id=$osId&desenho_id=$desenhoId"
]);

// Notificar projetista quando desenho é rejeitado
enviarNotificacao([
    'para' => $usuarioProjetistaId,
    'tipo' => 'desenho_rejeitado',
    'assunto' => 'Seu desenho foi rejeitado',
    'mensagem' => "Motivo: $motivo",
    'link' => "modules/engenharia/desenho_tecnico.php?os_id=$osId&desenho_id=$desenhoId"
]);
```

---

## Exemplos de Uso

### Criar e Submeter Desenho (JavaScript)

```javascript
async function criarDesenho() {
    const form = document.getElementById('form-novo-desenho');
    const formData = new FormData(form);
    
    formData.set('acao', 'criar_desenho');
    formData.set('enviar', 'submetido');
    
    try {
        const response = await fetch('/api/desenho.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.sucesso) {
            alert(data.mensagem);
            window.location.href = data.redirect;
        } else {
            alert('Erro: ' + data.erro);
        }
    } catch (error) {
        console.error('Erro:', error);
    }
}
```

### Listar Desenhos de uma O.S. (PHP)

```php
$stmt = $db->prepare("
    SELECT d.*, u.nome AS projetista_nome,
           COUNT(da.id) AS total_arquivos
    FROM desenhos_tecnicos d
    LEFT JOIN usuarios u ON u.id = d.usuario_projetista_id
    LEFT JOIN desenhos_arquivos da ON da.desenho_id = d.id
    WHERE d.os_id = ?
    GROUP BY d.id
    ORDER BY d.created_at DESC
");
$stmt->execute([123]);

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $desenho) {
    echo $desenho['titulo'] . ' (' . $desenho['status'] . ')';
    echo $desenho['total_arquivos'] . ' arquivo(s)';
}
```

### Aprovar Desenho (AJAX)

```javascript
async function aprovarDesenho(desenhoId) {
    const response = await fetch('/api/desenho.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
            acao: 'aprovar',
            desenho_id: desenhoId,
            etapa: 'gerencia',
            observacoes: 'Aprovado. Encaminhar para produção.'
        })
    });
    
    const data = await response.json();
    console.log(data.mensagem);
    location.reload();
}
```

---

## Performance e Otimizações

### Índices de Banco de Dados

```sql
-- Principais índices criados automaticamente:
CREATE INDEX idx_desenho_os ON desenhos_tecnicos (os_id);
CREATE INDEX idx_desenho_status ON desenhos_tecnicos (status);
CREATE INDEX idx_desenho_versao ON desenhos_tecnicos (os_id, versao);
CREATE INDEX idx_desenho_projetista ON desenhos_tecnicos (usuario_projetista_id);
CREATE INDEX idx_desenho_gerente ON desenhos_tecnicos (usuario_gerente_id);
CREATE INDEX idx_desenho_producao ON desenhos_tecnicos (usuario_producao_id);
CREATE INDEX idx_arquivo_desenho ON desenhos_arquivos (desenho_id);
CREATE INDEX idx_aprovacao_desenho ON desenhos_aprovaes (desenho_id);
```

### Caching (Recomendado)

```php
// Cache de desenhos por 5 minutos
$cacheKey = 'desenhos_os_' . $osId;
$desenhos = $cache->get($cacheKey);

if (!$desenhos) {
    $stmt = $db->prepare("SELECT * FROM desenhos_tecnicos WHERE os_id = ?");
    $stmt->execute([$osId]);
    $desenhos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $cache->set($cacheKey, $desenhos, 300);
}
```

---

## Troubleshooting

### Problema: Arquivos não são salvos

**Verificar:**
1. Permissões da pasta `/assets/uploads/desenhos/` (755)
2. Limite de upload no php.ini
3. Espaço em disco disponível

### Problema: Status não muda após aprovação

**Verificar:**
1. Se usuário tem permissão correta
2. Se o desenho existe no banco
3. Logs de erro do PHP

### Problema: Histórico não registra ações

**Verificar:**
1. Tabela `desenhos_historico` existe
2. Função `registrarHistoricoDesenho()` está sendo chamada
3. Erros na inserção (ver logs)

---

## Roadmap Futuro

- [ ] Pré-visualização 3D de modelos
- [ ] Assinatura digital de aprovações
- [ ] Importação automática de metadados de DWG
- [ ] Comparação visual de versões
- [ ] Integração com OCR para extração de dimensões
- [ ] Notificações em tempo real
- [ ] API de webhook para sistemas externos
- [ ] Exportação de relatório de aprovação em PDF

---

## Suporte

Para dúvidas ou problemas, consulte:
- Gabriel Costa (g4bs011.gbl@gmail.com)
- Documentação técnica: CLAUDE.md
- Logs: `/logs/desenho_tecnico.log`

---

**Última atualização:** 17 de julho de 2026  
**Versão:** 1.0  
**Status:** Produção
