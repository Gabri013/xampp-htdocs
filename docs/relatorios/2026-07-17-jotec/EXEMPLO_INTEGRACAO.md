# 📚 Exemplos de Integração - Sistema de Etiquetas

## 1️⃣ Gerar QR-code para O.S. (JavaScript)

```javascript
// Função para gerar QR-code
async function gerarQRcodeOS(osId) {
    const form = new FormData();
    form.append('acao', 'gerar_qr_svg');
    form.append('os_id', osId);

    try {
        const response = await fetch('/api/etiqueta_qrcode.php', {
            method: 'POST',
            body: form
        });

        const data = await response.json();

        if (data.sucesso) {
            console.log('✅ QR-code gerado:', data.qr_url);
            console.log('Etiqueta ID:', data.etiqueta_id);
            console.log('OS Número:', data.os_numero);

            // Exibir QR-code
            document.getElementById('qr-preview').src = data.qr_url;
        } else {
            console.error('❌ Erro:', data.erro);
        }
    } catch (error) {
        console.error('Erro na requisição:', error);
    }
}

// Usar:
// gerarQRcodeOS(123);
```

---

## 2️⃣ Gerar QR-code para O.P. (JavaScript)

```javascript
async function gerarQRcodeOP(opNumero, osId) {
    const form = new FormData();
    form.append('acao', 'gerar_qr_svg_op');
    form.append('op_numero', opNumero);
    form.append('os_id', osId);

    const response = await fetch('/api/etiqueta_qrcode.php', {
        method: 'POST',
        body: form
    });

    const data = await response.json();

    if (data.sucesso) {
        return {
            qrUrl: data.qr_url,
            opNumero: opNumero,
            etiquetaId: data.etiqueta_id
        };
    } else {
        throw new Error(data.erro);
    }
}

// Usar:
// gerarQRcodeOP('OS-001-01', 123);
```

---

## 3️⃣ Listar Etiquetas de uma O.S. (JavaScript)

```javascript
async function listarEtiquetas(osId) {
    const response = await fetch(
        `/api/etiqueta_qrcode.php?acao=listar_etiquetas&os_id=${osId}`
    );

    const data = await response.json();

    if (data.sucesso) {
        console.log(`Total de etiquetas: ${data.total}`);
        console.log('Etiquetas:', data.etiquetas);

        // Exibir etiquetas
        data.etiquetas.forEach(etiqueta => {
            console.log(`- ${etiqueta.tipo}: ${etiqueta.conteudo} (${etiqueta.impressoes} impressões)`);
        });
    }
}

// Usar:
// listarEtiquetas(123);
```

---

## 4️⃣ Registrar Impressão (JavaScript)

```javascript
async function registrarImpressao(etiquetaId) {
    const form = new FormData();
    form.append('acao', 'registrar_impressao');
    form.append('etiqueta_id', etiquetaId);

    const response = await fetch('/api/etiqueta_qrcode.php', {
        method: 'POST',
        body: form
    });

    const data = await response.json();

    if (data.sucesso) {
        console.log('✅ Impressão registrada');
    }
}

// Usar:
// registrarImpressao(456);
```

---

## 5️⃣ Criar Ordem de Produção (JavaScript)

```javascript
async function criarOrdenProducao(osId) {
    const form = new FormData();
    form.append('acao', 'criar');
    form.append('os_id', osId);

    const response = await fetch('/modules/os/ordem_producao.php', {
        method: 'POST',
        body: form
    });

    const data = await response.json();

    if (data.sucesso) {
        console.log('✅ O.P. Criada:', data.numero_op);
        return {
            opId: data.op_id,
            numeroOP: data.numero_op
        };
    } else {
        throw new Error(data.erro);
    }
}

// Usar:
// const op = await criarOrdenProducao(123);
// console.log('O.P. ID:', op.opId);
```

---

## 6️⃣ Atualizar Status de O.P. (JavaScript)

```javascript
async function atualizarStatusOP(opId, novoStatus) {
    const form = new FormData();
    form.append('acao', 'atualizar_status');
    form.append('op_id', opId);
    form.append('status', novoStatus);

    const response = await fetch('/modules/os/ordem_producao.php', {
        method: 'POST',
        body: form
    });

    const data = await response.json();

    if (data.sucesso) {
        console.log(`✅ Status atualizado para: ${novoStatus}`);
    } else {
        console.error('❌ Erro:', data.erro);
    }
}

// Usar:
// atualizarStatusOP(789, 'em_producao');
// Opções: 'pendente', 'em_producao', 'concluida', 'parada', 'cancelada'
```

---

## 7️⃣ Atualizar Etapa de Produção (JavaScript)

```javascript
async function atualizarEtapaProducao(etapaId, novoStatus) {
    const form = new FormData();
    form.append('acao', 'atualizar_etapa');
    form.append('etapa_id', etapaId);
    form.append('status', novoStatus);

    const response = await fetch('/modules/os/ordem_producao.php', {
        method: 'POST',
        body: form
    });

    const data = await response.json();

    if (data.sucesso) {
        console.log(`✅ Etapa atualizada para: ${novoStatus}`);
    }
}

// Usar:
// atualizarEtapaProducao(999, 'concluido');
// Opções: 'pendente', 'em_producao', 'concluido', 'parado'
```

---

## 8️⃣ Fluxo Completo (JavaScript)

```javascript
async function fluxoCompletoEtiquetaOP(osId) {
    try {
        console.log('🚀 Iniciando fluxo completo...');

        // 1. Gerar QR-code para O.S.
        console.log('1️⃣  Gerando QR-code da O.S...');
        const osQR = await fetch('/api/etiqueta_qrcode.php', {
            method: 'POST',
            body: new FormData(
                new DOMParser().parseFromString(
                    `<form><input name="acao" value="gerar_qr_svg"><input name="os_id" value="${osId}"></form>`,
                    'text/html'
                ).forms[0]
            )
        }).then(r => r.json());

        if (!osQR.sucesso) throw new Error(osQR.erro);
        console.log('✅ QR-code gerado:', osQR.os_numero);

        // 2. Criar Ordem de Produção
        console.log('2️⃣  Criando Ordem de Produção...');
        const opForm = new FormData();
        opForm.append('acao', 'criar');
        opForm.append('os_id', osId);

        const opCriada = await fetch('/modules/os/ordem_producao.php', {
            method: 'POST',
            body: opForm
        }).then(r => r.json());

        if (!opCriada.sucesso) throw new Error(opCriada.erro);
        console.log('✅ O.P. criada:', opCriada.numero_op);

        // 3. Gerar QR-code para O.P.
        console.log('3️⃣  Gerando QR-code da O.P...');
        const opQR = await fetch('/api/etiqueta_qrcode.php', {
            method: 'POST',
            body: new FormData(
                new DOMParser().parseFromString(
                    `<form><input name="acao" value="gerar_qr_svg_op"><input name="op_numero" value="${opCriada.numero_op}"><input name="os_id" value="${osId}"></form>`,
                    'text/html'
                ).forms[0]
            )
        }).then(r => r.json());

        if (!opQR.sucesso) throw new Error(opQR.erro);
        console.log('✅ QR-code O.P. gerado');

        // 4. Listar todas as etiquetas
        console.log('4️⃣  Listando etiquetas...');
        const etiquetas = await fetch(
            `/api/etiqueta_qrcode.php?acao=listar_etiquetas&os_id=${osId}`
        ).then(r => r.json());

        console.log(`✅ Total de etiquetas: ${etiquetas.total}`);

        // Resultado final
        console.log('📊 Resumo:');
        console.log('- O.S.:', osQR.os_numero);
        console.log('- O.P.:', opCriada.numero_op);
        console.log('- Etiquetas geradas:', etiquetas.total);
        console.log('🎉 Fluxo completo finalizado!');

        return {
            osId: osId,
            osNumero: osQR.os_numero,
            opId: opCriada.op_id,
            numeroOP: opCriada.numero_op,
            totalEtiquetas: etiquetas.total
        };

    } catch (error) {
        console.error('❌ Erro no fluxo:', error.message);
        throw error;
    }
}

// Usar:
// fluxoCompletoEtiquetaOP(123);
```

---

## 9️⃣ Impressão de Etiqueta (HTML)

```html
<!DOCTYPE html>
<html>
<head>
    <title>Impressão de Etiqueta</title>
    <style>
        .etiqueta {
            width: 100mm;
            height: 150mm;
            border: 2px dashed #ddd;
            padding: 10mm;
            text-align: center;
            page-break-inside: avoid;
            margin: 10mm;
        }

        .etiqueta-qr {
            width: 80mm;
            height: 80mm;
            margin: 0 auto 10mm;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .etiqueta-numero {
            font-weight: bold;
            font-size: 14px;
            margin-top: 10mm;
        }

        @media print {
            body { margin: 0; padding: 0; }
            .etiqueta { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="etiqueta">
        <div class="etiqueta-qr">
            <img id="qr-img" src="" alt="QR-code" style="width: 100%; height: 100%; object-fit: contain;">
        </div>
        <div class="etiqueta-numero">OS <span id="os-numero">-</span></div>
    </div>

    <script>
        // Quando a página carrega
        window.addEventListener('load', () => {
            const osId = new URLSearchParams(window.location.search).get('os_id');
            if (osId) {
                gerarEtiqueta(osId);
            }
        });

        async function gerarEtiqueta(osId) {
            const response = await fetch(`/api/etiqueta_qrcode.php`, {
                method: 'POST',
                body: new FormData(
                    new DOMParser().parseFromString(
                        `<form><input name="acao" value="gerar_qr_svg"><input name="os_id" value="${osId}"></form>`,
                        'text/html'
                    ).forms[0]
                )
            });

            const data = await response.json();
            if (data.sucesso) {
                document.getElementById('qr-img').src = data.qr_url;
                document.getElementById('os-numero').textContent = data.os_numero;
                setTimeout(() => window.print(), 500);
            }
        }
    </script>
</body>
</html>

<!-- Usar: /imprimir_etiqueta.html?os_id=123 -->
```

---

## 🔟 Integração com Estoque (PHP)

```php
<?php
/**
 * Integração do Sistema de Etiquetas com Estoque
 * Exemplo de como registrar movimento de estoque quando etiqueta é criada
 */

require_once 'config/config.php';
require_once 'includes/workflow.php';

$db = getDB();

function registrarMovimentoEstoque($os_id, $tipo = 'entrada') {
    global $db;

    try {
        // Buscar itens da O.S.
        $stmt = $db->prepare("SELECT id, produto_id, quantidade FROM os_itens WHERE os_id = ?");
        $stmt->execute([$os_id]);
        $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($itens as $item) {
            // Registrar movimento no estoque
            $descricao = $tipo === 'entrada' ? 'Entrada por O.S.' : 'Saída para produção';

            $stmt = $db->prepare("INSERT INTO estoque_movimentacoes 
                (produto_id, tipo, quantidade, descricao, referencia, usuario_id, data)
                VALUES (?, ?, ?, ?, ?, ?, NOW())");

            $stmt->execute([
                $item['produto_id'],
                $tipo,
                $item['quantidade'],
                $descricao,
                "OS-$os_id",
                $_SESSION['usuario_id']
            ]);
        }

        return true;
    } catch (Exception $e) {
        error_log("Erro ao registrar movimento: " . $e->getMessage());
        return false;
    }
}

// Usar na criação de O.P.:
if ($op_criada) {
    registrarMovimentoEstoque($os_id, 'saida');
    echo json_encode(['sucesso' => true, 'msg' => 'O.P. criada e estoque atualizado']);
}
```

---

## 1️⃣1️⃣ Webhook para Integração Externa

```php
<?php
/**
 * Webhook para notificar sistema externo quando etiqueta é impressa
 */

require_once 'config/config.php';

$db = getDB();

function notificarSistemaExterno($etiqueta_id, $os_id) {
    $webhook_url = 'https://seu-sistema.com/api/etiqueta-impressa';

    $dados = [
        'etiqueta_id' => $etiqueta_id,
        'os_id' => $os_id,
        'timestamp' => time(),
        'usuario_id' => $_SESSION['usuario_id']
    ];

    $ch = curl_init($webhook_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}

// Usar:
// notificarSistemaExterno(456, 123);
```

---

## 📊 Consultas SQL Úteis

```sql
-- Listar todas as etiquetas impressas
SELECT e.*, os.numero as os_numero, u.nome as usuario_nome
FROM etiquetas_impressas e
LEFT JOIN ordens_servico os ON os.id = e.os_id
LEFT JOIN usuarios u ON u.id = e.usuario_id
ORDER BY e.data_criacao DESC;

-- Contar impressões por tipo
SELECT tipo, COUNT(*) as total, SUM(impressoes) as impressoes_totais
FROM etiquetas_impressas
GROUP BY tipo;

-- Ordens de Produção em andamento
SELECT op.*, os.numero as os_numero, c.razao_social
FROM ordens_producao op
LEFT JOIN ordens_servico os ON os.id = op.os_id
LEFT JOIN clientes c ON c.id = os.cliente_id
WHERE op.status = 'em_producao'
ORDER BY op.criado_em DESC;

-- Etapas atrasadas
SELECT ope.*, op.numero as op_numero
FROM ordens_producao_etapas ope
LEFT JOIN ordens_producao op ON op.id = ope.op_id
WHERE ope.status != 'concluido'
AND ope.data_inicio < DATE_SUB(NOW(), INTERVAL 8 HOUR);

-- Tempo total por etapa
SELECT etapa, 
    COUNT(*) as total,
    AVG(TIMESTAMPDIFF(HOUR, data_inicio, data_conclusao)) as media_horas
FROM ordens_producao_etapas
WHERE data_conclusao IS NOT NULL
GROUP BY etapa;
```

---

## 🎯 Checklist de Integração

- [ ] Copiar arquivos para diretórios corretos
- [ ] Executar testes em `/tests/test_etiquetas_qrcode.php`
- [ ] Validar tabelas do banco
- [ ] Testar endpoints da API
- [ ] Verificar permissões dos usuários
- [ ] Testar geração de QR-codes
- [ ] Testar impressão de etiquetas
- [ ] Validar integração com estoque
- [ ] Treinar usuários
- [ ] Deploy em produção

---

**Última atualização:** 2026-07-17
**Versão:** 1.0
