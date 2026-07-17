# 🚀 TIER 2 ROADMAP - Cozinka ERP Modernizado

**Status TIER 1**: ✅ 100% Completo (8 módulos, 4.500 linhas código, 32 skills ativadas)

**Status TIER 2**: 🔄 EM DESENVOLVIMENTO

---

## 📋 TIER 2: 3 FEATURES PRINCIPAIS

### **FEATURE 1: 🤖 MRP (Material Requirements Planning)**
**Objetivo**: Planejamento automático de produção

**O que faz**:
- Análise de demanda vs estoque
- Sugestão automática de ordens de produção
- Previsão de matérias-primas necessárias
- Otimização de cronograma
- Alertas de falta de material

**Impacto**: ⭐⭐⭐⭐⭐ (ALTÍSSIMO - Automação core)

**Estimativa**: 5-7 dias

**Arquivos a criar**:
- `/api/mrp.php` - Engine de MRP
- `/modules/producao/mrp_dashboard.php` - Interface
- `/includes/components/mrp.component.php` - Componentes reutilizáveis

**Skills que serão usadas**:
- 🤖 Code Review
- ⚡ Performance Optimization
- 🧪 Unit Testing
- 📚 Documentation

---

### **FEATURE 2: 💰 Gestão de Custos**
**Objetivo**: Calcular custo real por O.S.

**O que faz**:
- Custo de mão de obra (por etapa + tempo)
- Custo de materiais (consumo real vs BOM)
- Overhead por O.S.
- Margens por cliente
- Comparação planejado vs real
- Lucratividade por pedido

**Impacto**: ⭐⭐⭐⭐⭐ (ALTÍSSIMO - Decisões financeiras)

**Estimativa**: 5-6 dias

**Arquivos a criar**:
- `/api/custos.php` - Engine de custos
- `/modules/financeiro/dashboard_custos.php` - Interface
- `/modules/financeiro/custos_os.php` - Detalhes por O.S.

**Skills que serão usadas**:
- 🔍 Query Optimization
- 📊 Performance Analysis
- 🧪 Integration Testing
- 📚 API Documentation

---

### **FEATURE 3: 📊 Dashboard Customizável**
**Objetivo**: Relatórios personalizados sem código

**O que faz**:
- Usuário arrasta métricas (drag-drop)
- Filtros por período/setor/cliente
- Múltiplos tipos de gráficos (barra, linha, pizza, Gantt)
- Salva views personalizadas
- Exporta para PDF/Excel
- Comparação períodos

**Impacto**: ⭐⭐⭐⭐ (MUITO ALTO - Análise visual)

**Estimativa**: 6-8 dias

**Arquivos a criar**:
- `/api/dashboard_builder.php` - Engine de dashboards
- `/modules/dashboard/builder.php` - UI para criar dashboards
- `/modules/dashboard/viewer.php` - Visualizar dashboard salvo

**Skills que serão usadas**:
- 🎨 UI/UX Review
- ♿ Accessibility Check
- 📊 Frontend Performance
- 🧪 E2E Testing

---

## 📅 CRONOGRAMA TIER 2

**Semana 1** (Dias 1-7):
- [ ] Segunda-terça: MRP Engine + API
- [ ] Quarta-quinta: MRP Dashboard + testes
- [ ] Sexta: Code review + otimizações

**Semana 2** (Dias 8-14):
- [ ] Segunda-terça: Custos API + cálculos
- [ ] Quarta-quinta: Dashboard Custos + testes
- [ ] Sexta: Integração com TIER 1

**Semana 3** (Dias 15-21):
- [ ] Segunda-quarta: Dashboard Builder API
- [ ] Quinta-sexta: Dashboard Builder UI + salvar views

**Semana 4** (Dias 22-28):
- [ ] Testes e2e completos
- [ ] Performance optimization
- [ ] Load testing
- [ ] Documentação final

**Total**: ~21-28 dias (3-4 semanas)

---

## 🎯 FLUXO INTEGRADO TIER 2

```
TIER 1 (Cliente → Expedição) COMPLETO
  ↓
MRP: Análise demanda × estoque → Sugestão O.S. automática
  ↓
BOM: Requisita materiais automaticamente
  ↓
Custos: Calcula mão de obra + materiais em tempo real
  ↓
Dashboard Custom: Visualiza tudo em gráficos personalizados
  ↓
RESULTADO: Fábrica 100% otimizada com dados reais
```

---

## 📈 MÉTRICAS ESPERADAS

### MRP
- [ ] Redução de stock-outs: 80%
- [ ] Otimização de cronograma: 40%
- [ ] Sugestões automáticas: 100% cobertas

### Custos
- [ ] Custo real por O.S.: 100% rastreável
- [ ] Margem por cliente: Visível em tempo real
- [ ] Desvio planejado vs real: < 5%

### Dashboard Custom
- [ ] Relatórios gerados sem código: ✓
- [ ] Tempo criação novo dashboard: < 2 min
- [ ] Exportação PDF/Excel: 100% funcional

---

## 🔐 SEGURANÇA TIER 2

- ✅ Rate limiting em APIs (novo)
- ✅ CSRF tokens em formulários (novo)
- ✅ Validação de cálculos financeiros
- ✅ Auditoria de mudanças de custos
- ✅ Logs de acesso ao dashboard

---

## 📚 DOCUMENTAÇÃO TIER 2

- [ ] MRP Algorithm Documentation
- [ ] Custos Calculation Formula
- [ ] Dashboard Builder User Guide
- [ ] API Reference (3 novas APIs)
- [ ] Video tutorials (3x)

---

## 🧪 TESTES TIER 2

**MRP**:
- [ ] Unit: Engine de MRP
- [ ] Integration: MRP + BOM + Estoque
- [ ] E2E: Criar O.S. automática até expedição

**Custos**:
- [ ] Unit: Cálculos de custo
- [ ] Integration: Custos + Produção + Estoque
- [ ] Load: 10k O.S. simultâneas

**Dashboard**:
- [ ] Unit: Componentes visuais
- [ ] Integration: Salvar/carregar views
- [ ] E2E: Criar, editar, compartilhar dashboard

---

## 🎨 PADRÃO VISUAL TIER 2

**MRP Dashboard**:
- Cores: Azul (primária) + Verde (sugestões OK) + Vermelho (alertas)
- Layout: Recomendações no topo, análise abaixo
- Mobile: Responsivo (tablete/desktop)

**Dashboard Custos**:
- Cores: Verde (lucro) + Vermelho (prejuízo) + Amarelo (margem baixa)
- Layout: Cards KPI + gráficos detalhados
- Real-time: Atualiza a cada 1 minuto

**Dashboard Builder**:
- Cores: Purple (builder mode) + Nomus original (viewer mode)
- Drag-drop: Padrão moderno
- Responsive: 100% mobile-friendly

---

## 🚀 PRÓXIMOS PASSOS

### ✅ AGORA:
- [ ] Iniciar TIER 2 com MRP
- [ ] Usar todas 32 skills
- [ ] Code review em cada commit
- [ ] Testes + performance desde dia 1

### 📊 DEPOIS DE TIER 2:
- [ ] Performance tuning completo
- [ ] Load testing em produção
- [ ] User training
- [ ] Go-live!

---

**STATUS**: 🔴 NÃO INICIADO → 🟡 EM PLANEJAMENTO → 🟢 PRONTO COMEÇAR

**QUANDO**: AGORA! 🚀

---

*Última atualização: 2026-07-17*
*Versão: 1.0 (Pre-TIER 2)*
