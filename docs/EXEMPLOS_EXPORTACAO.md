# Exemplos de Uso - Sistema de Exportação Cozinka ERP

## 📋 Índice
1. [Caso 1: Relatório Diário de Vendas](#caso-1-relatório-diário-de-vendas)
2. [Caso 2: Integração com Sistema Externo](#caso-2-integração-com-sistema-externo)
3. [Caso 3: Exportação para Email](#caso-3-exportação-para-email)
4. [Caso 4: Dashboard com Dados Exportados](#caso-4-dashboard-com-dados-exportados)
5. [Caso 5: Processamento em Batch](#caso-5-processamento-em-batch)
6. [Caso 6: Auditoria e Compliance](#caso-6-auditoria-e-compliance)

---

## Caso 1: Relatório Diário de Vendas

### Objetivo
Gerar relatório diário de vendas do dia anterior em Excel para compartilhar com gerência.

### Código PHP

```php
<?php
/**
 * gerar_relatorio_vendas_diario.php
 * Script para gerar relatório diário de vendas
 */

require_once 'config/config.php';
require_once 'includes/exportador.php';

// Usuário administrativo
$usuario = [
    'id' => 1,
    'nome' => 'Sistema Automático',
    'tipo' => 'master',
    'email' => 'sistema@cozinka.com'
];

$db = getDB();
$exportador = new Exportador($db, $usuario);

// Data de ontem
$ontem = date('Y-m-d', strtotime('-1 day'));

// Exportar vendas de ontem
$resultado = $exportador->exportar('vendas', 'xlsx', [
    'status' => 'confirmada',
    'data_inicio' => $ontem,
    'data_fim' => $ontem
]);

if ($resultado === false) {
    echo "ERRO: " . implode('; ', $exportador->getErros());
    exit(1);
}

// Salvar arquivo
$arquivo = '/tmp/vendas_' . $ontem . '.xlsx';
file_put_contents($arquivo, $resultado['conteudo']);

echo "✓ Relatório gerado: " . $arquivo . "\n";
echo "Tamanho: " . filesize($arquivo) . " bytes\n";

// Opcional: Enviar por email
if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    $mail = new PHPMailer\PHPMailer\PHPMailer();
    $mail->setFrom('sistema@cozinka.com', 'Cozinka ERP');
    $mail->addAddress('gerente@cozinka.com');
    $mail->addAttachment($arquivo);
    $mail->Subject = 'Relatório de Vendas - ' . $ontem;
    $mail->Body = "Segue em anexo o relatório de vendas do dia " . $ontem;
    $mail->send();
    echo "✓ Email enviado para gerente@cozinka.com\n";
}
?>
```

### Cron Job

```bash
# /etc/cron.d/cozinka_relatorios
# Executar diariamente às 08:00 da manhã
0 8 * * * www-data php /xampp/htdocs/scripts/gerar_relatorio_vendas_diario.php >> /var/log/cozinka_exports.log 2>&1
```

### Resultado

```
✓ Relatório gerado: /tmp/vendas_2026-07-16.xlsx
Tamanho: 24512 bytes
✓ Email enviado para gerente@cozinka.com
```

---

## Caso 2: Integração com Sistema Externo

### Objetivo
Sincronizar vendas confirmadas do Cozinka com um sistema de BI (Power BI, Looker, etc).

### Código Python

```python
#!/usr/bin/env python3
"""
sync_vendas_bi.py
Sincroniza vendas do Cozinka ERP com sistema de BI
"""

import requests
import json
import time
from datetime import datetime, timedelta

class CozinkaExporter:
    def __init__(self, base_url, session_id):
        self.base_url = base_url
        self.session_id = session_id
        self.cookies = {'PHPSESSID': session_id}
    
    def exportar_json(self, tabela, filtros=None):
        """Exporta dados em JSON"""
        url = f"{self.base_url}/api/exportacao.php"
        
        data = {
            'acao': 'exportar',
            'tabela': tabela,
            'formato': 'json',
            'filtros': json.dumps(filtros or {})
        }
        
        response = requests.post(url, data=data, cookies=self.cookies)
        return response.json()
    
    def sincronizar_bi(self):
        """Sincroniza vendas com BI"""
        print("🔄 Iniciando sincronização de vendas...")
        
        # Filtrar por data (últimas 24 horas)
        ontem = (datetime.now() - timedelta(days=1)).strftime('%Y-%m-%d')
        hoje = datetime.now().strftime('%Y-%m-%d')
        
        filtros = {
            'status': 'confirmada',
            'data_inicio': ontem,
            'data_fim': hoje
        }
        
        # Exportar
        resultado = self.exportar_json('vendas', filtros)
        
        if not resultado['sucesso']:
            print(f"❌ Erro: {resultado['erro']}")
            return False
        
        # Parse de dados
        import base64
        conteudo_base64 = resultado['conteudo_base64']
        conteudo_json = base64.b64decode(conteudo_base64).decode('utf-8')
        dados = json.loads(conteudo_json)
        
        # Enviar para BI (exemplo com Looker)
        bi_url = "https://looker.example.com/api/3/sql_queries"
        
        registros = dados['exportacao']['dados']
        print(f"📊 {len(registros)} vendas encontradas para sincronizar")
        
        # Processar cada venda
        for venda in registros:
            payload = {
                'vendor_id': venda['id'],
                'cliente': venda['cliente'],
                'valor': venda['valor_total'],
                'data': venda['data_venda'],
                'timestamp': datetime.now().isoformat()
            }
            
            # POST para BI
            try:
                resp = requests.post(bi_url, json=payload, timeout=5)
                print(f"  ✓ Venda {venda['numero']} sincronizada")
            except Exception as e:
                print(f"  ❌ Erro ao sincronizar {venda['numero']}: {e}")
        
        print("✓ Sincronização concluída!")
        return True

# Uso
if __name__ == '__main__':
    exporter = CozinkaExporter(
        'http://localhost',
        'seu_phpsessid_aqui'
    )
    exporter.sincronizar_bi()
```

### Configuração no BI

```python
# Power BI Python Script
import requests
import json
import base64

api_url = "http://localhost/api/exportacao.php"
cookies = {'PHPSESSID': 'seu_session_id'}

# Requisição
response = requests.post(api_url, data={
    'acao': 'exportar',
    'tabela': 'vendas',
    'formato': 'json',
    'filtros': json.dumps({'status': 'confirmada'})
}, cookies=cookies)

# Processar
if response.json()['sucesso']:
    conteudo = base64.b64decode(response.json()['conteudo_base64']).decode('utf-8')
    dataset = pd.DataFrame(json.loads(conteudo)['exportacao']['dados'])
```

---

## Caso 3: Exportação para Email

### Objetivo
Enviar relatório de O.S. em produção para o departamento de produção diariamente.

### Código PHP com PHPMailer

```php
<?php
/**
 * enviar_relatorio_producao_email.php
 */

require_once 'config/config.php';
require_once 'includes/exportador.php';
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function enviarRelatorioProducao()
{
    $db = getDB();
    $usuario = [
        'id' => 1,
        'nome' => 'Sistema',
        'tipo' => 'master',
        'email' => 'sistema@cozinka.com'
    ];

    // Exportar O.S. em produção
    $exportador = new Exportador($db, $usuario);
    $resultado = $exportador->exportar('producao', 'pdf', [
        'status' => 'em_producao'
    ]);

    if ($resultado === false) {
        error_log("Erro ao exportar: " . implode('; ', $exportador->getErros()));
        return false;
    }

    // Configurar email
    $mail = new PHPMailer(true);

    try {
        // SMTP
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'seu_email@gmail.com';
        $mail->Password = 'sua_senha_app';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Remetente e destinatário
        $mail->setFrom('sistema@cozinka.com', 'Cozinka ERP');
        $mail->addAddress('producao@cozinka.com');
        $mail->addCC('gerente@cozinka.com');

        // Conteúdo
        $mail->isHTML(true);
        $mail->Subject = 'Relatório de Produção - ' . date('d/m/Y H:i');
        $mail->Body = '
            <h2>Relatório de Ordens em Produção</h2>
            <p>Segue em anexo o relatório das ordens em produção.</p>
            <p>Data: ' . date('d/m/Y H:i:s') . '</p>
        ';
        $mail->AltBody = 'Relatório de Produção';

        // Anexo
        $mail->addStringAttachment(
            $resultado['conteudo'],
            $resultado['nome'],
            'base64',
            $resultado['tipo_mime']
        );

        // Enviar
        $mail->send();
        echo "✓ Email enviado com sucesso\n";
        return true;

    } catch (Exception $e) {
        error_log("Erro ao enviar email: " . $e->getMessage());
        return false;
    }
}

// Executar
if (php_sapi_name() === 'cli') {
    enviarRelatorioProducao();
}
?>
```

### Resultado de Email

```
De: sistema@cozinka.com
Para: producao@cozinka.com
CC: gerente@cozinka.com
Assunto: Relatório de Produção - 17/07/2026 09:15

Corpo:
Relatório de Ordens em Produção

Segue em anexo o relatório das ordens em produção.
Data: 17/07/2026 09:15:30

[ANEXO: producao_2026-07-17_091530.pdf - 45 KB]
```

---

## Caso 4: Dashboard com Dados Exportados

### Objetivo
Criar um dashboard em HTML5 que carrega dados das exportações.

### Código HTML/JavaScript

```html
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - Cozinka ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="/assets/js/exportador.js"></script>
</head>
<body>
<div class="container-fluid p-4">
    <h1>Dashboard de Vendas</h1>

    <!-- Controles -->
    <div class="row mb-3">
        <div class="col-md-3">
            <label>Período</label>
            <input type="date" id="data-inicio" class="form-control" value="2026-07-01">
        </div>
        <div class="col-md-3">
            <label>&nbsp;</label>
            <input type="date" id="data-fim" class="form-control" value="2026-07-17">
        </div>
        <div class="col-md-3">
            <label>&nbsp;</label>
            <button class="btn btn-primary w-100" onclick="carregarDados()">
                <i class="fas fa-sync"></i> Carregar
            </button>
        </div>
        <div class="col-md-3">
            <label>&nbsp;</label>
            <button class="btn btn-success w-100" onclick="exportarDados()">
                <i class="fas fa-download"></i> Exportar Excel
            </button>
        </div>
    </div>

    <!-- Métricas -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6>Total de Vendas</h6>
                    <h3 id="total-vendas">--</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6>Valor Total</h6>
                    <h3 id="valor-total">--</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6>Ticket Médio</h6>
                    <h3 id="ticket-medio">--</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6>Status: Confirmadas</h6>
                    <h3 id="status-confirmadas">--</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela -->
    <div class="card">
        <div class="card-header">
            <h5>Detalhes de Vendas</h5>
        </div>
        <div class="card-body">
            <table class="table table-striped" id="tabela-vendas">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Número</th>
                        <th>Cliente</th>
                        <th>Data</th>
                        <th>Valor</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="vendas-body">
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const exportador = new ExportadorCozinka('/api/exportacao.php');

async function carregarDados() {
    const dataInicio = document.getElementById('data-inicio').value;
    const dataFim = document.getElementById('data-fim').value;

    try {
        const resultado = exportador.exportar('vendas', 'json', {
            status: 'confirmada',
            data_inicio: dataInicio,
            data_fim: dataFim
        }, false); // Sem download automático

        const dados = await resultado;
        if (!dados.sucesso) throw new Error(dados.erro);

        // Decodificar base64
        const conteudo = atob(dados.conteudo_base64);
        const json = JSON.parse(conteudo);
        const vendas = json.exportacao.dados;

        // Calcular métricas
        const total = vendas.length;
        const valorTotal = vendas.reduce((sum, v) => sum + (v.valor_total || 0), 0);
        const ticketMedio = total > 0 ? valorTotal / total : 0;

        // Atualizar tela
        document.getElementById('total-vendas').textContent = total;
        document.getElementById('valor-total').textContent = 'R$ ' + valorTotal.toFixed(2);
        document.getElementById('ticket-medio').textContent = 'R$ ' + ticketMedio.toFixed(2);
        document.getElementById('status-confirmadas').textContent = total;

        // Preencher tabela
        const tbody = document.getElementById('vendas-body');
        tbody.innerHTML = '';

        vendas.forEach(v => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${v.id}</td>
                <td>${v.numero}</td>
                <td>${v.cliente}</td>
                <td>${v.data_venda}</td>
                <td>R$ ${v.valor_total.toFixed(2)}</td>
                <td><span class="badge bg-success">${v.status}</span></td>
            `;
            tbody.appendChild(tr);
        });

    } catch (erro) {
        alert('Erro ao carregar: ' + erro.message);
    }
}

function exportarDados() {
    const dataInicio = document.getElementById('data-inicio').value;
    const dataFim = document.getElementById('data-fim').value;

    exportador.exportar('vendas', 'xlsx', {
        status: 'confirmada',
        data_inicio: dataInicio,
        data_fim: dataFim
    }, true); // Com download automático
}

// Carregar ao abrir
carregarDados();
</script>
</body>
</html>
```

---

## Caso 5: Processamento em Batch

### Objetivo
Processar exportações de múltiplas tabelas automaticamente.

### Código Node.js

```javascript
/**
 * batch_export.js
 * Exportar múltiplas tabelas para arquivos
 */

const fetch = require('node-fetch');
const fs = require('fs');
const path = require('path');

class CozinkaBatchExporter {
    constructor(apiUrl, sessionId) {
        this.apiUrl = apiUrl;
        this.sessionId = sessionId;
        this.outputDir = './exports';
        
        // Criar diretório de saída
        if (!fs.existsSync(this.outputDir)) {
            fs.mkdirSync(this.outputDir, { recursive: true });
        }
    }

    async exportarTabela(tabela, formato, filtros = {}) {
        console.log(`📁 Exportando ${tabela} em ${formato}...`);

        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    acao: 'exportar',
                    tabela: tabela,
                    formato: formato,
                    filtros: JSON.stringify(filtros)
                })
            });

            const json = await response.json();

            if (!json.sucesso) {
                console.log(`  ❌ ${json.erro}`);
                return null;
            }

            // Decodificar base64
            const buffer = Buffer.from(json.conteudo_base64, 'base64');
            const arquivo = path.join(this.outputDir, json.nome_arquivo);

            fs.writeFileSync(arquivo, buffer);
            console.log(`  ✓ Salvo em: ${arquivo}`);

            return arquivo;

        } catch (erro) {
            console.log(`  ❌ ${erro.message}`);
            return null;
        }
    }

    async exportarTudo() {
        console.log('🚀 Iniciando exportação em batch...\n');

        const hoje = new Date().toISOString().split('T')[0];
        const filtros = {
            data_inicio: '2026-07-01',
            data_fim: hoje
        };

        const tabelas = [
            { nome: 'vendas', formato: 'xlsx' },
            { nome: 'orcamentos', formato: 'xlsx' },
            { nome: 'os', formato: 'pdf' },
            { nome: 'clientes', formato: 'csv' },
            { nome: 'financeiro', formato: 'json' }
        ];

        let sucesso = 0;
        let falhas = 0;

        for (const t of tabelas) {
            const resultado = await this.exportarTabela(t.nome, t.formato, filtros);
            if (resultado) {
                sucesso++;
            } else {
                falhas++;
            }
        }

        console.log(`\n✓ Concluído! Sucessos: ${sucesso}, Falhas: ${falhas}`);
        console.log(`📂 Arquivos salvos em: ${path.resolve(this.outputDir)}`);
    }
}

// Usar
const exporter = new CozinkaBatchExporter(
    'http://localhost/api/exportacao.php',
    'seu_phpsessid'
);

exporter.exportarTudo();
```

### Execução

```bash
node batch_export.js

# Saída:
# 🚀 Iniciando exportação em batch...
# 
# 📁 Exportando vendas em xlsx...
#   ✓ Salvo em: ./exports/vendas_2026-07-17_141530.xlsx
# 📁 Exportando orcamentos em xlsx...
#   ✓ Salvo em: ./exports/orcamentos_2026-07-17_141530.xlsx
# ...
# ✓ Concluído! Sucessos: 5, Falhas: 0
# 📂 Arquivos salvos em: /home/user/exports
```

---

## Caso 6: Auditoria e Compliance

### Objetivo
Gerar relatório de auditoria com todas as exportações realizadas.

### Código SQL + PHP

```sql
-- Query de auditoria
SELECT 
    el.id,
    u.nome as usuario,
    u.email,
    el.tabela,
    el.formato,
    el.filtros_count,
    el.data_exportacao,
    TIMESTAMPDIFF(SECOND, el.data_exportacao, NOW()) as minutos_atras
FROM exportacoes_log el
JOIN usuarios u ON u.id = el.usuario_id
WHERE el.data_exportacao >= DATE_SUB(NOW(), INTERVAL 30 DAY)
ORDER BY el.data_exportacao DESC;
```

### PHP para Gerar Relatório

```php
<?php
/**
 * relatorio_auditoria.php
 */

require_once 'config/config.php';
require_once 'includes/exportador.php';

$db = getDB();

// Buscar logs de exportação
$stmt = $db->prepare("
    SELECT 
        el.id,
        u.nome as usuario,
        u.email,
        el.tabela,
        el.formato,
        el.filtros_count,
        el.data_exportacao
    FROM exportacoes_log el
    JOIN usuarios u ON u.id = el.usuario_id
    WHERE el.data_exportacao >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY el.data_exportacao DESC
");

$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Gerar relatório
$relatorio = [
    'titulo' => 'Relatório de Auditoria - Exportações (Últimos 30 dias)',
    'data_relatorio' => date('d/m/Y H:i:s'),
    'total_exportacoes' => count($logs),
    'por_formato' => array_count_values(array_column($logs, 'formato')),
    'por_usuario' => array_count_values(array_column($logs, 'usuario')),
    'por_tabela' => array_count_values(array_column($logs, 'tabela')),
    'detalhes' => $logs
];

// Exportar como JSON
$usuario = [
    'id' => 1,
    'nome' => 'Sistema Auditoria',
    'tipo' => 'master',
    'email' => 'auditoria@cozinka.com'
];

// Criar dado exportável
$exportador = new Exportador($db, $usuario);

// Usar exportação JSON para relatório
$resultado = $exportador->exportar('vendas', 'json', []);

// Salvar relatório
$arquivo = '/tmp/auditoria_exportacoes_' . date('Y-m-d') . '.json';
file_put_contents($arquivo, json_encode($relatorio, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "✓ Relatório gerado: " . $arquivo . "\n";
echo "\nResumo:\n";
echo "- Total de exportações: " . $relatorio['total_exportacoes'] . "\n";
echo "- Formatos: " . json_encode($relatorio['por_formato']) . "\n";
echo "- Usuários: " . json_encode($relatorio['por_usuario']) . "\n";
?>
```

---

## 🎓 Resumo de Exemplos

| Caso | Objetivo | Tecnologia | Frequência |
|------|----------|-----------|-----------|
| 1 | Relatório Diário | PHP + Cron | Diário (08:00) |
| 2 | Integração BI | Python + API REST | A cada 6h |
| 3 | Email Automático | PHP + PHPMailer | Diário (09:00) |
| 4 | Dashboard | HTML + JavaScript | Sob demanda |
| 5 | Batch Export | Node.js | Semanal |
| 6 | Auditoria | SQL + PHP | Mensal |

---

## ✨ Dicas Práticas

1. **Use filtros** para reduzir volume de dados
2. **Cache de resultados** para exportações frequentes
3. **Background jobs** para grandes volumes (10K+)
4. **Compressão** de arquivos antes de enviar por email
5. **Versionamento** de relatórios com timestamp
6. **Alertas** para exportações anormais (tamanho, duração)

---

## 📚 Documentação Relacionada

- [EXPORTACAO.md](./EXPORTACAO.md) - Documentação técnica completa
- [EXPORTACAO_SETUP.md](./EXPORTACAO_SETUP.md) - Guia de instalação
- API Reference: `/api/exportacao.php`

---

**Versão**: 1.0  
**Data**: 17/07/2026  
**Desenvolvedor**: Gabriel Costa
