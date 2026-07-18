# ✅ IMPLEMENTAR PADRONIZAÇÃO JOTEC NO SISTEMA

**Data**: 2026-07-17  
**Status**: ✅ Classe criada + Testes passando  
**Próximo**: Atualizar APIs e módulos

---

## 🎯 O QUE FOI FEITO

### ✅ Classe Criada
- **Arquivo**: `/includes/padrao_jotec.php`
- **Métodos**: 11 funções de geração, validação e formatação
- **Status**: ✅ **TESTADO E FUNCIONANDO 100%**

### ✅ Padrões Implementados
```
Material:           JOTEC-001000, JOTEC-001001, ...
Produto:            JOTEC-P00001, JOTEC-P00002, ...
Fornecedor:         JOTEC-F0001, JOTEC-F0002, ...
Cliente:            JOT-CLI-000001, JOT-CLI-000002, ...
Ordem de Serviço:   OS-JOTEC-000001, OS-JOTEC-000002, ...
Com ABA:            PA-001000, MT-001001, GR-001002, ...
```

### ✅ Documentação
- **Arquivo**: `/PADRAO_CODIGOS_JOTEC.md`
- **Guia**: Como usar a classe
- **Exemplos**: Código pronto para copiar/colar

### ✅ Testes
- **Arquivo**: `/scripts/testar_padrao_jotec.php`
- **Status**: ✅ **100% PASSOU**
- **Resultado**: Todos os padrões funcionando

---

## 📋 CHECKLIST DE IMPLEMENTAÇÃO

### **Fase 1: Atualizar APIs** (Priority: ALTA)

```
[ ] /api/materias_primas.php
    Adicionar no inicio:
    require_once '../includes/padrao_jotec.php';
    
    Na criação:
    $codigo = PadraoJOTEC::criarCodigoUnico($db, 'material');

[ ] /api/produtos.php
    Usar: PadraoJOTEC::criarCodigoUnico($db, 'produto')

[ ] /api/fornecedores.php
    Usar: PadraoJOTEC::criarCodigoUnico($db, 'fornecedor')

[ ] /api/clientes.php
    Usar: PadraoJOTEC::criarCodigoUnico($db, 'cliente')

[ ] /api/ordens_servico.php
    Usar: PadraoJOTEC::criarCodigoUnico($db, 'os')

[ ] /api/importar_jotec.php
    Usar: PadraoJOTEC::gerarCodigoMaterial($seq)
```

### **Fase 2: Atualizar Módulos** (Priority: ALTA)

```
[ ] /modules/os/ordem_producao.php
    Usar: PadraoJOTEC::criarCodigoUnico($db, 'os')

[ ] /modules/estoque/importar_jotec.php
    Usar: PadraoJOTEC::criarCodigoUnico($db, 'material')

[ ] /modules/estoque/materias_primas.php
    Usar: PadraoJOTEC::criarCodigoUnico($db, 'material')

[ ] /modules/vendas/criar_venda.php
    Se precisar gerar códigos, usar PadraoJOTEC
```

### **Fase 3: Atualizar Scripts** (Priority: MÉDIA)

```
[ ] /scripts/importar_jotec_rapido.php
    ATUAL:
    $codigo = "JOTEC-" . str_pad($codigoSequencia, 6, "0", STR_PAD_LEFT);
    
    NOVO:
    require_once '../includes/padrao_jotec.php';
    $codigo = PadraoJOTEC::gerarCodigoMaterial($codigoSequencia);

[ ] /scripts/criar_tabelas_jotec.php
    Revisar se usa códigos

[ ] Qualquer outro script que gera códigos
    Usar PadraoJOTEC
```

### **Fase 4: Validação** (Priority: MÉDIA)

```
[ ] Testar criação de novo material
    Verificar se código segue JOTEC-XXXXXX

[ ] Testar criação de novo produto
    Verificar se código segue JOTEC-PXXXXX

[ ] Testar criação de nova O.S.
    Verificar se código segue OS-JOTEC-XXXXXX

[ ] Testar importação JOTEC
    Verificar se usa PadraoJOTEC

[ ] Verificar sequências no banco
    Não devem ter duplicatas ou gaps
```

---

## 📝 TEMPLATE DE IMPLEMENTAÇÃO

### Para cada arquivo a atualizar, use este template:

```php
<?php
/**
 * ARQUIVO: /api/exemplo.php
 * ALTERAÇÃO: Usar padronização JOTEC para gerar códigos
 * DATA: 2026-07-17
 */

require_once '../config/config.php';
require_once '../includes/padrao_jotec.php';  // ← ADICIONE ISTO

$db = getDB();
$acao = $_POST['acao'] ?? null;

if ($acao === 'criar') {
    try {
        // GERAR CÓDIGO USANDO PADRÃO JOTEC
        $codigo = PadraoJOTEC::criarCodigoUnico($db, 'material');  // ← USE ISTO
        
        $descricao = $_POST['descricao'] ?? '';
        
        // Validar código
        if (!PadraoJOTEC::validarCodigo($codigo)) {
            throw new Exception('Código inválido');
        }
        
        // Inserir no banco
        $stmt = $db->prepare("
            INSERT INTO materias_primas (codigo, descricao, ...)
            VALUES (?, ?, ...)
        ");
        $stmt->execute([$codigo, $descricao, ...]);
        
        echo json_encode([
            'sucesso' => true,
            'codigo' => $codigo,
            'mensagem' => 'Criado com sucesso'
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'sucesso' => false,
            'erro' => $e->getMessage()
        ]);
    }
}
?>
```

---

## 🔧 COMO USAR (Rápido Referência)

### Criar código único (RECOMENDADO)
```php
require_once '../includes/padrao_jotec.php';
$db = getDB();

// Material
$codigo = PadraoJOTEC::criarCodigoUnico($db, 'material');
// Resultado: JOTEC-001041 (próximo disponível)

// Produto
$codigo = PadraoJOTEC::criarCodigoUnico($db, 'produto');
// Resultado: JOTEC-P00001

// Cliente
$codigo = PadraoJOTEC::criarCodigoUnico($db, 'cliente');
// Resultado: JOT-CLI-000001
```

### Gerar código simples (sem validar banco)
```php
// Se você conhece a sequência
$codigo = PadraoJOTEC::gerarCodigoMaterial(1000);
// Resultado: JOTEC-001000
```

### Validar código
```php
if (PadraoJOTEC::validarCodigo($codigo)) {
    echo "Código válido!";
}
```

### Extrair sequência
```php
$seq = PadraoJOTEC::extrairSequencia('JOTEC-001000');
echo $seq; // 1000
```

---

## 📊 EXEMPLO REAL: ATUALIZAR `/api/materias_primas.php`

### ANTES (atual)
```php
<?php
require_once '../config/config.php';

$db = getDB();
$acao = $_POST['acao'] ?? null;

if ($acao === 'criar') {
    $codigo = $_POST['codigo'] ?? null; // ❌ Manual!
    $descricao = $_POST['descricao'] ?? '';
    
    $stmt = $db->prepare("INSERT INTO materias_primas (codigo, ...) VALUES (?, ...)");
    $stmt->execute([$codigo, ...]);
}
?>
```

### DEPOIS (padronizado)
```php
<?php
require_once '../config/config.php';
require_once '../includes/padrao_jotec.php'; // ✅ NOVO

$db = getDB();
$acao = $_POST['acao'] ?? null;

if ($acao === 'criar') {
    try {
        $codigo = PadraoJOTEC::criarCodigoUnico($db, 'material'); // ✅ AUTOMÁTICO
        $descricao = $_POST['descricao'] ?? '';
        
        $stmt = $db->prepare("INSERT INTO materias_primas (codigo, ...) VALUES (?, ...)");
        $stmt->execute([$codigo, ...]);
        
        echo json_encode(['sucesso' => true, 'codigo' => $codigo]);
    } catch (Exception $e) {
        echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
    }
}
?>
```

---

## ✅ TESTES RECOMENDADOS

### Teste 1: Criar Material
```bash
curl -X POST http://localhost/api/materias_primas.php \
  -d "acao=criar&descricao=Teste"
# Esperado: {"sucesso": true, "codigo": "JOTEC-001041"}
```

### Teste 2: Validar Código
```php
$codigo = "JOTEC-001041";
assert(PadraoJOTEC::validarCodigo($codigo) === true);
```

### Teste 3: Sem Duplicatas
```php
$cod1 = PadraoJOTEC::criarCodigoUnico($db, 'material');
$cod2 = PadraoJOTEC::criarCodigoUnico($db, 'material');
assert($cod1 !== $cod2); // Diferentes!
```

---

## 🚀 CRONOGRAMA SUGERIDO

### **Hoje (2026-07-17)**
- ✅ Classe criada
- ✅ Documentação pronta
- ✅ Testes passando
- ⏳ Começar Fase 1

### **Amanhã (2026-07-18)**
- 📍 Atualizar 5 APIs principais
- 📍 Testar cada uma

### **Dia 3 (2026-07-19)**
- 📍 Atualizar módulos
- 📍 Atualizar scripts
- 📍 Validação completa

### **Dia 4+ (2026-07-20+)**
- 📍 Teste com dados reais
- 📍 Deploy com padrão
- ✅ Concluído

---

## 📞 DÚVIDAS FREQUENTES

### P: Preciso atualizar todos os arquivos agora?
**R**: Não urgente, mas recomendado. Comece pelas APIs de criação de registros.

### P: E os códigos já criados?
**R**: Podem conviver. A validação aceita qualquer formato conhecido.

### P: Isso vai quebrar importações?
**R**: Não. O padrão é retroativo e compatível com dados existentes.

### P: Como faço para ajustar a sequência?
**R**: Use `PadraoJOTEC::obterProximaSequencia($db, $tabela, $coluna)`.

### P: Posso usar sem o banco de dados?
**R**: Sim! Use `gerarCodigo*()` sem validação de banco.

---

## 📚 LINKS ÚTEIS

- **Classe**: `/includes/padrao_jotec.php`
- **Documentação**: `/PADRAO_CODIGOS_JOTEC.md`
- **Teste**: `/scripts/testar_padrao_jotec.php`
- **Ordem de Implementação**: Este arquivo

---

## ✅ CHECKLIST FINAL

- [x] Classe PadraoJOTEC criada
- [x] Testes executados com sucesso
- [x] Documentação completa
- [x] Template de implementação pronto
- [ ] APIs atualizadas (Fase 1)
- [ ] Módulos atualizados (Fase 2)
- [ ] Scripts atualizados (Fase 3)
- [ ] Validação completa (Fase 4)
- [ ] Deploy com padrão

---

**Status**: ✅ **PRONTO PARA IMPLEMENTAÇÃO**

**Próximo Passo**: Começar com a Fase 1 (Atualizar APIs)

**Responsável**: Gabriel Costa

🎯 **Objetivo**: 100% do sistema seguindo padrão JOTEC até 2026-07-20
