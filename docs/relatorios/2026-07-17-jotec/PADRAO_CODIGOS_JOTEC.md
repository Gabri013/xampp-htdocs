# 📋 PADRONIZAÇÃO DE CÓDIGOS JOTEC

**Data**: 2026-07-17  
**Status**: ✅ Implementado  
**Arquivo**: `/includes/padrao_jotec.php`

---

## 🎯 OBJETIVO

Centralizar a geração e validação de todos os códigos do sistema seguindo o padrão JOTEC, garantindo:

✅ Consistência em todo o sistema  
✅ Códigos sequenciais e únicos  
✅ Facilidade de identificação por tipo  
✅ Compatibilidade com importação JOTEC  

---

## 📊 PADRÕES DE CÓDIGO

### **1️⃣ Material (Matéria Prima)**
```
Formato: JOTEC-XXXXXX
Exemplo: JOTEC-001000, JOTEC-001001, JOTEC-001002

Uso: Materiais, insumos, componentes
Prefixo: JOTEC-
Dígitos: 6 (sequencial)
```

**Exemplo de Uso**:
```php
$codigo = PadraoJOTEC::gerarCodigoMaterial(1000);
// Resultado: JOTEC-001000
```

---

### **2️⃣ Produto Acabado**
```
Formato: JOTEC-PXXXXX
Exemplo: JOTEC-P00001, JOTEC-P00002

Uso: Produtos finalizados, equipamentos
Prefixo: JOTEC-P
Dígitos: 5 (sequencial)
```

**Exemplo de Uso**:
```php
$codigo = PadraoJOTEC::gerarCodigoProduto(1);
// Resultado: JOTEC-P00001
```

---

### **3️⃣ Fornecedor**
```
Formato: JOTEC-FXXXX
Exemplo: JOTEC-F0001, JOTEC-F0002

Uso: Fornecedores de materiais
Prefixo: JOTEC-F
Dígitos: 4 (sequencial)
```

**Exemplo de Uso**:
```php
$codigo = PadraoJOTEC::gerarCodigoFornecedor(1);
// Resultado: JOTEC-F0001
```

---

### **4️⃣ Cliente**
```
Formato: JOT-CLI-XXXXXX
Exemplo: JOT-CLI-000001, JOT-CLI-000002

Uso: Clientes do sistema
Prefixo: JOT-CLI-
Dígitos: 6 (sequencial)
```

**Exemplo de Uso**:
```php
$codigo = PadraoJOTEC::gerarCodigoCliente(1);
// Resultado: JOT-CLI-000001
```

---

### **5️⃣ Ordem de Serviço**
```
Formato: OS-JOTEC-XXXXXX
Exemplo: OS-JOTEC-000001, OS-JOTEC-000002

Uso: Ordens de serviço, produções
Prefixo: OS-JOTEC-
Dígitos: 6 (sequencial)
```

**Exemplo de Uso**:
```php
$codigo = PadraoJOTEC::gerarCodigoOS(1);
// Resultado: OS-JOTEC-000001
```

---

### **6️⃣ Código com Prefixo de ABA**
```
Formato: XX-XXXXXX (onde XX é o prefixo da aba)

Abas Disponíveis:
├─ PA = Produtos Acabados
├─ MT = Materiais
├─ GR = Geral
├─ IN = Insumos
└─ CP = Componentes

Exemplos:
PA-001000  (Produtos Acabados)
MT-001000  (Materiais)
GR-001000  (Geral)
IN-001000  (Insumos)
CP-001000  (Componentes)
```

**Exemplo de Uso**:
```php
$codigo = PadraoJOTEC::gerarCodigoComABA('PA', 1000);
// Resultado: PA-001000

$codigo = PadraoJOTEC::gerarCodigoComABA('MT', 1001);
// Resultado: MT-001001
```

---

## 💻 COMO USAR

### **Importar a classe**
```php
<?php
require_once '../../includes/padrao_jotec.php';
?>
```

### **Gerar um código simples**
```php
// Material
$codigo_material = PadraoJOTEC::gerarCodigoMaterial(1000);
echo $codigo_material; // JOTEC-001000

// Produto
$codigo_produto = PadraoJOTEC::gerarCodigoProduto(1);
echo $codigo_produto; // JOTEC-P00001

// Cliente
$codigo_cliente = PadraoJOTEC::gerarCodigoCliente(1);
echo $codigo_cliente; // JOT-CLI-000001
```

### **Gerar código único (recomendado)**
```php
// Obtém próxima sequência do banco e cria código único
try {
    $db = getDB();
    $codigo = PadraoJOTEC::criarCodigoUnico($db, 'material');
    echo $codigo; // JOTEC-001010 (próximo sequencial disponível)
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
```

### **Validar um código**
```php
$codigo = "JOTEC-001000";

if (PadraoJOTEC::validarCodigo($codigo)) {
    echo "Código válido!";
} else {
    echo "Código inválido!";
}
```

### **Extrair sequência**
```php
$codigo = "JOTEC-001000";
$sequencia = PadraoJOTEC::extrairSequencia($codigo);
echo $sequencia; // 1000
```

### **Obter próxima sequência disponível**
```php
$db = getDB();
$proxima = PadraoJOTEC::obterProximaSequencia($db, 'materias_primas', 'codigo');
echo $proxima; // 1010 (próximo número disponível)
```

### **Formatar para exibição**
```php
$codigo = "JOTEC-001000";
$formatado = PadraoJOTEC::formatarParaExibicao($codigo);
echo $formatado; // JOTEC-001 000 (com espaço visual)
```

---

## 🔧 EXEMPLO COMPLETO

### Criar novo material com código automático
```php
<?php
require_once '../../config/config.php';
require_once '../../includes/padrao_jotec.php';

$db = getDB();

try {
    // Gerar código único
    $codigo = PadraoJOTEC::criarCodigoUnico($db, 'material');
    
    // Preparar dados
    $descricao = "Aço Inox 304 2.0mm";
    $fornecedor_id = 1;
    $preco = 125.50;
    $unidade = "kg";
    $aba_origem = "MATERIAIS";
    
    // Inserir
    $stmt = $db->prepare("
        INSERT INTO materias_primas 
        (codigo, descricao, fornecedor_id, preco, unidade, aba_origem)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([$codigo, $descricao, $fornecedor_id, $preco, $unidade, $aba_origem]);
    
    echo json_encode([
        'sucesso' => true,
        'codigo' => $codigo,
        'descricao' => $descricao,
        'mensagem' => 'Material criado com sucesso!'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage()
    ]);
}
?>
```

---

## 📋 ONDE USAR

### **APIs**
- `/api/materias_primas.php` - Criar novo material
- `/api/produtos.php` - Criar novo produto
- `/api/fornecedores.php` - Criar novo fornecedor
- `/api/clientes.php` - Criar novo cliente
- `/api/ordens_servico.php` - Criar nova O.S.

### **Módulos**
- `/modules/estoque/importar_jotec.php` - Importação
- `/modules/estoque/materias_primas.php` - Cadastro de materiais
- `/modules/vendas/criar_venda.php` - Criar venda
- `/modules/os/ordem_producao.php` - Criar O.S.

### **Scripts**
- `/scripts/importar_jotec_rapido.php` - Importação de dados
- Qualquer script que gera códigos

---

## ✅ CHECKLIST DE IMPLEMENTAÇÃO

### **Arquivos a Atualizar** (Priority: Alta)

- [ ] `/api/materias_primas.php` - Usar `criarCodigoUnico('material')`
- [ ] `/api/produtos.php` - Usar `criarCodigoUnico('produto')`
- [ ] `/api/fornecedores.php` - Usar `criarCodigoUnico('fornecedor')`
- [ ] `/api/clientes.php` - Usar `criarCodigoUnico('cliente')`
- [ ] `/api/ordens_servico.php` - Usar `criarCodigoUnico('os')`
- [ ] `/api/importar_jotec.php` - Usar `gerarCodigoMaterial()`
- [ ] `/modules/os/ordem_producao.php` - Usar `criarCodigoUnico('os')`
- [ ] `/modules/estoque/importar_jotec.php` - Usar `criarCodigoUnico('material')`
- [ ] `/scripts/importar_jotec_rapido.php` - Usar `gerarCodigoMaterial()`

### **Validação**
- [ ] Todos os códigos gerados seguem padrão JOTEC
- [ ] Nenhum código duplicado
- [ ] Sequências são contínuas
- [ ] Validação funciona corretamente

---

## 🎨 EXEMPLOS DE INTERFACE

### Exibição de Código em Formulário
```html
<div class="form-group">
    <label>Código (Automático)</label>
    <input type="text" 
           id="codigo" 
           class="form-control" 
           readonly
           value="JOTEC-001010"
           data-padrao="jotec">
    <small class="text-muted">Gerado automaticamente no formato JOTEC</small>
</div>
```

### Listagem com Códigos
```html
<table class="table table-hover">
    <thead>
        <tr>
            <th>Código JOTEC</th>
            <th>Descrição</th>
            <th>Fornecedor</th>
            <th>Preço</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>JOTEC-001000</strong></td>
            <td>Aço Inox 304 2.0mm</td>
            <td>Fornecedor A</td>
            <td>R$ 125,50</td>
        </tr>
    </tbody>
</table>
```

---

## 🔍 MÉTODOS DISPONÍVEIS

```php
// Geração de códigos
PadraoJOTEC::gerarCodigoMaterial($seq)
PadraoJOTEC::gerarCodigoProduto($seq)
PadraoJOTEC::gerarCodigoFornecedor($seq)
PadraoJOTEC::gerarCodigoCliente($seq)
PadraoJOTEC::gerarCodigoOS($seq)
PadraoJOTEC::gerarCodigoComABA($aba, $seq)

// Manipulação
PadraoJOTEC::validarCodigo($codigo)
PadraoJOTEC::extrairSequencia($codigo)
PadraoJOTEC::obterProximaSequencia($db, $tabela, $coluna)
PadraoJOTEC::criarCodigoUnico($db, $tipo, $tentativas)
PadraoJOTEC::formatarParaExibicao($codigo)
PadraoJOTEC::obterPadrao($tipo)
```

---

## 📊 BENEFÍCIOS

✅ **Centralização**: Um único lugar para geração de códigos  
✅ **Consistência**: Mesmo padrão em todo sistema  
✅ **Segurança**: Garante unicidade de códigos  
✅ **Performance**: Cache de sequências  
✅ **Manutenção**: Fácil modificar formato se necessário  
✅ **Compatibilidade**: Funciona com importação JOTEC  

---

## 🚀 PRÓXIMOS PASSOS

1. ✅ Classe criada (`/includes/padrao_jotec.php`)
2. ⏳ Atualizar APIs para usar a classe
3. ⏳ Atualizar módulos para usar a classe
4. ⏳ Validar que todos os códigos usam padrão
5. ⏳ Testar com dados reais

---

**Arquivo**: `/includes/padrao_jotec.php`  
**Documentação**: Este arquivo  
**Status**: ✅ Pronto para usar  
**Última Atualização**: 2026-07-17
