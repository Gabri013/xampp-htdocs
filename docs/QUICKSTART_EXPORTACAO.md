# Quick Start - Exportador Cozinka ERP

## ⚡ 5 Minutos para Começar

### 1. Verificar Instalação (30 segundos)

```bash
# Validar sintaxe PHP
php -l /xampp/htdocs/includes/exportador.php
php -l /xampp/htdocs/api/exportacao.php
```

**Esperado**: `No syntax errors detected`

### 2. Acessar Interface Web (1 minuto)

Abrir navegador:
```
http://localhost/modules/admin/exportador_interface.php
```

Você verá:
- Seletor de tabela
- Seletor de formato (CSV, Excel, PDF, JSON)
- Filtros dinâmicos
- Botão Exportar

### 3. Fazer Primeira Exportação (2 minutos)

1. Selecionar: **Clientes**
2. Formato: **Excel**
3. Clicar: **Exportar**
4. Arquivo `clientes_YYYY-MM-DD_HHMMSS.xlsx` é baixado

✅ Pronto! Você exportou com sucesso!

### 4. Usar via API (1 minuto)

```bash
# Terminal/PowerShell
curl -X POST "http://localhost/api/exportacao.php" \
  -d "acao=exportar&tabela=vendas&formato=xlsx&download=1" \
  -o vendas.xlsx
```

### 5. Usar via JavaScript (1 minuto)

```html
<script src="/assets/js/exportador.js"></script>
<script>
    const exp = new ExportadorCozinka('/api/exportacao.php');
    
    exp.exportar('vendas', 'xlsx', {
        status: 'confirmada'
    }).then(resultado => {
        console.log('✓ Exportado:', resultado.nome_arquivo);
    });
</script>
```

---

## 📚 Próximos Passos

### Ler Documentação Completa
👉 [EXPORTACAO.md](./EXPORTACAO.md)

### Ver Exemplos de Uso
👉 [EXEMPLOS_EXPORTACAO.md](./EXEMPLOS_EXPORTACAO.md)

### Guia de Configuração
👉 [EXPORTACAO_SETUP.md](./EXPORTACAO_SETUP.md)

---

## 🎯 Casos de Uso Comuns

### Exportar Vendas do Mês
```bash
curl -X POST "http://localhost/api/exportacao.php" \
  -d "acao=exportar" \
  -d "tabela=vendas" \
  -d "formato=xlsx" \
  -d "filtros={\"status\":\"confirmada\",\"data_inicio\":\"2026-07-01\",\"data_fim\":\"2026-07-31\"}" \
  -d "download=1" \
  -o vendas_julho.xlsx
```

### Exportar O.S. em Produção
```bash
curl -X POST "http://localhost/api/exportacao.php" \
  -d "acao=exportar" \
  -d "tabela=os" \
  -d "formato=pdf" \
  -d "filtros={\"status\":\"em_producao\"}" \
  -d "download=1" \
  -o os_producao.pdf
```

### Exportar para Integração
```bash
curl -X POST "http://localhost/api/exportacao.php" \
  -d "acao=exportar" \
  -d "tabela=clientes" \
  -d "formato=json" \
  -d "download=0" \
  | jq '.conteudo_base64' | base64 -d | jq .
```

---

## 🔑 Tabelas Disponíveis

| Tabela | Descrição |
|--------|-----------|
| **vendas** | Vendas realizadas |
| **orcamentos** | Orçamentos enviados |
| **os** | Ordens de Serviço |
| **clientes** | Cadastro de clientes |
| **estoque** | Inventário de materiais |
| **producao** | Ordens de produção |
| **financeiro** | Contas a pagar/receber |

---

## 📊 Formatos Suportados

| Formato | Extensão | Uso |
|---------|----------|-----|
| **CSV** | .csv | Integração com sistemas |
| **Excel** | .xlsx | Compartilhamento e análise |
| **PDF** | .pdf | Documentos e impressão |
| **JSON** | .json | APIs e aplicações |

---

## ✅ Checklist

- [ ] Acessei a interface em `http://localhost/modules/admin/exportador_interface.php`
- [ ] Exportei uma tabela com sucesso
- [ ] Recebi o arquivo no navegador
- [ ] Testei um endpoint da API via curl
- [ ] Li a documentação completa
- [ ] Executei os testes unitários

---

## 🆘 Problemas Comuns

### "Sessão expirada"
→ Fazer login no Cozinka ERP antes

### "Acesso negado"
→ Verificar permissões do tipo de usuário

### "Arquivo não abre"
→ Tentar outro formato (CSV funciona sempre)

### "Erro 500"
→ Verificar logs em `/var/log/apache2/error.log`

---

## 🚀 Dicas Pro

1. **Use filtros** para reduzir volume
   ```
   data_inicio: 2026-07-01
   data_fim: 2026-07-31
   status: confirmada
   ```

2. **Cache de 5 minutos** para mesmos filtros
3. **Agendamento automático** com cron jobs
4. **Integração com BI** via JSON/CSV
5. **Email automático** de relatórios

---

## 📞 Precisa de Ajuda?

Consulte:
- 📖 Documentação: `docs/EXPORTACAO.md`
- 💡 Exemplos: `docs/EXEMPLOS_EXPORTACAO.md`
- 🔧 Setup: `docs/EXPORTACAO_SETUP.md`
- 🧪 Testes: `php tests/ExportadorTest.php`

---

**Versão**: 1.0  
**Desenvolvedor**: Gabriel Costa  
**Email**: g4bs011.gbl@gmail.com
