# 🚀 Instruções do Claude Code - Cozinka ERP

## 📋 Sobre Este Projeto

**Cozinka ERP Modernizado** - Sistema completo de gestão de produção estilo Nomus.

- **Status**: TIER 1 Completo (8 módulos, fluxo cliente→expedição)
- **Próximo**: TIER 2 (MRP, Custos, Dashboard Custom)
- **Stack**: PHP puro + Tailwind CSS + MySQL
- **Padrão**: Nomus ERP (botões, cores, fluxo integrado)

---

## 🎯 Skills Para Usar

### **1. Code Validation** (Sempre antes de commits)
- ✅ Validar sintaxe PHP (`php -l`)
- ✅ Verificar padrões (PSR-2, nomes variáveis)
- ✅ Segurança: SQL injection, XSS, CSRF
- ✅ Performance: queries N+1, loops aninhados

### **2. Documentation** (Durante desenvolvimento)
- ✅ Documentar APIs (endpoint, parâmetros, retorno)
- ✅ JSDoc para funções JavaScript
- ✅ README atualizado por módulo
- ✅ Comentários em código complexo

### **3. Refactoring** (Após TIER 1)
- ✅ DRY (Don't Repeat Yourself)
- ✅ Remover dead code
- ✅ Simplificar lógica complexa
- ✅ Consolidar duplicações

### **4. Testing** (Para TIER 2)
- ✅ Testes unitários (APIs)
- ✅ Testes de integração (fluxo)
- ✅ Testes de performance
- ✅ Casos edge

### **5. Security Audit** (Antes de produção)
- ✅ PDO prepared statements (✓ já feito)
- ✅ Validação de entrada
- ✅ CORS headers
- ✅ Rate limiting

---

## 📝 Padrões de Código

### Estrutura de Arquivo
```php
<?php
/**
 * Descrição breve do módulo
 * Funcionalidades principais
 * Acesso: tipos de usuário
 */

require_once '../../config/config.php';
require_once '../../includes/workflow.php';

// Segurança
$db = getDB();
requirePermission(['master', 'gerente']);

// Lógica
// ...

// Saída
```

### API Pattern
```php
// POST /api/nome.php
$acao = $_POST['acao'] ?? null;

if ($acao === 'criar') {
    // Validação
    // Execução
    // Resposta JSON
    echo json_encode(['sucesso' => true, 'id' => $id]);
}
```

### UI Pattern (Nomus-style)
```html
<div class="card">
    <div class="card-header">Título</div>
    <div class="card-body">Conteúdo</div>
</div>

<!-- Botões Nomus -->
<?= btn_primary('Salvar', 'onclick="salvar()"') ?>
<?= btn_success('Confirmar', 'type="submit"') ?>
<?= btn_danger('Deletar', 'onclick="deletar()"') ?>
```

---

## ✅ Checklist Antes de Cada Commit

- [ ] `php -l arquivo.php` (sem erros)
- [ ] Validação de entrada (isset, sanitize)
- [ ] PDO prepared statements (sem SQL injection)
- [ ] Acesso verificado (requirePermission)
- [ ] Tabelas criadas automaticamente
- [ ] Real-time refresh implementado (se relevante)
- [ ] Padrão Nomus aplicado (botões, cores, layout)
- [ ] Testes manuais na aplicação
- [ ] Git message descritivo

---

## 🔧 Próximas Features (TIER 2)

### MRP (5-7 dias)
- [ ] Análise de demanda vs estoque
- [ ] Sugestão de ordens
- [ ] Previsão de matérias-primas
- [ ] Dashboard de planejamento

### Custos (5-6 dias)
- [ ] Custo de mão de obra
- [ ] Custo de materiais
- [ ] Overhead por O.S.
- [ ] Margem por cliente

### Dashboard Customizável (6-8 dias)
- [ ] Drag-drop de métricas
- [ ] Filtros por período/setor
- [ ] Salvar views personalizadas
- [ ] Relatórios em PDF

---

## 📞 Contatos Rápidos

- **Usuário**: Gabriel Costa
- **Email**: g4bs011.gbl@gmail.com
- **Projeto**: Cozinka ERP (inox)
- **Versão**: TIER 1 (100% completo)

---

## 🎨 Paleta de Cores (Nomus)

| Setor | Hex | Tailwind |
|-------|-----|----------|
| Vendas | #3b82f6 | blue-500 |
| SAC | #ec4899 | pink-500 |
| Engenharia | #8b5cf6 | purple-500 |
| Estoque | #10b981 | green-600 |
| Produção | #f59e0b | amber-500 |
| Qualidade | #dc2626 | red-600 |
| Expedição | #0891b2 | cyan-600 |

---

**Última atualização**: 2026-07-17
**Versão**: 1.1 (TIER 1 Completo + TIER 2 Fase 1: MRP)
