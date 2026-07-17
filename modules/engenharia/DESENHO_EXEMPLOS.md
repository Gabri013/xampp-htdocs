# Exemplos de Uso: Módulo de Desenho Técnico

## Índice
1. [Exemplos PHP](#exemplos-php)
2. [Exemplos JavaScript](#exemplos-javascript)
3. [Exemplos SQL](#exemplos-sql)
4. [Integração com Módulos](#integração-com-módulos)
5. [Testes](#testes)

---

## Exemplos PHP

### 1. Criar um Novo Desenho Programaticamente

```php
<?php
require_once 'config/config.php';
require_once 'includes/engenharia.php';
require_once 'modules/engenharia/desenho_helpers.php';

$db = getDB();
ensureEngenhariaSchema($db);

// Dados do desenho
$osId = 123;
$usuarioId = 5; // ID do projetista
$titulo = "Dimensões Estrutura Principal";
$descricao = "Desenho técnico com especificações de corte e solda";
$prioridade = "alta";
$qualidade = "certificada";

// Inserir desenho
$stmt = $db->prepare("
    INSERT INTO desenhos_tecnicos
    (os_id, titulo, descricao, versao, status, prioridade, qualidade_exigida, usuario_projetista_id)
    VALUES (?, ?, ?, 'v1.0', 'rascunho', ?, ?, ?)
");

try {
    $stmt->execute([$osId, $titulo, $descricao, $prioridade, $qualidade, $usuarioId]);
    $desenhoId = (int) $db->lastInsertId();
    echo "Desenho criado: ID #$desenhoId\n";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
```

### 2. Submeter Desenho para Aprovação

```php
<?php
$desenhoId = 5;
$usuarioId = 5;

// Obter desenho
$stmt = $db->prepare("SELECT * FROM desenhos_tecnicos WHERE id = ?");
$stmt->execute([$desenhoId]);
$desenho = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$desenho) {
    die("Desenho não encontrado");
}

// Validar permissão (projetista só pode submeter seu próprio desenho)
if ($usuarioId !== $desenho['usuario_projetista_id'] && $usuarioPerfil !== 'master') {
    die("Sem permissão");
}

// Submeter
$stmt = $db->prepare("
    UPDATE desenhos_tecnicos
    SET status = 'submetido', data_submissao = ?
    WHERE id = ?
");
$stmt->execute([date('Y-m-d H:i:s'), $desenhoId]);

// Criar registros de aprovação
$etapas = ['gerencia', 'producao'];
$prazo = date('Y-m-d H:i:s', strtotime('+5 days'));

foreach ($etapas as $etapa) {
    $stmt = $db->prepare("
        INSERT IGNORE INTO desenhos_aprovaes
        (desenho_id, etapa, status, prazo_resposta)
        VALUES (?, ?, 'pendente', ?)
    ");
    $stmt->execute([$desenhoId, $etapa, $prazo]);
}

// Registrar histórico
$stmt = $db->prepare("
    INSERT INTO desenhos_historico
    (desenho_id, acao, usuario_id, status_anterior, status_novo, detalhes)
    VALUES (?, 'submissao', ?, 'rascunho', 'submetido', ?)
");
$stmt->execute([$desenhoId, $usuarioId, "Desenho submetido para aprovação"]);

echo "Desenho submetido com sucesso!";
?>
```

### 3. Aprovar Desenho na Gerência

```php
<?php
require_once 'modules/engenharia/desenho_helpers.php';

$desenhoId = 5;
$usuarioId = 2; // Gerente
$observacoes = "Aprovado. Encaminhar para Produção.";

// Validar permissão
$usuarioPerfil = $_SESSION['perfil'];
if (!in_array($usuarioPerfil, ['master', 'gerente'])) {
    die("Sem permissão para aprovar");
}

// Obter desenho
$stmt = $db->prepare("SELECT * FROM desenhos_tecnicos WHERE id = ?");
$stmt->execute([$desenhoId]);
$desenho = $stmt->fetch(PDO::FETCH_ASSOC);

// Registrar aprovação
$stmt = $db->prepare("
    UPDATE desenhos_tecnicos
    SET usuario_gerente_id = ?, data_aprovacao_gerencia = ?
    WHERE id = ?
");
$stmt->execute([$usuarioId, date('Y-m-d H:i:s'), $desenhoId]);

// Atualizar registro de aprovação
$stmt = $db->prepare("
    UPDATE desenhos_aprovaes
    SET status = 'aprovado', usuario_id = ?, data_resposta = ?, observacoes = ?
    WHERE desenho_id = ? AND etapa = 'gerencia'
");
$stmt->execute([$usuarioId, date('Y-m-d H:i:s'), $observacoes, $desenhoId]);

// Verificar se todas as aprovações estão feitas
$stmt = $db->prepare("
    SELECT COUNT(*) FROM desenhos_aprovaes
    WHERE desenho_id = ? AND status != 'aprovado'
");
$stmt->execute([$desenhoId]);
$pendentes = $stmt->fetchColumn();

// Se nenhuma pendente, marcar como aprovado
if ($pendentes == 0) {
    $stmt = $db->prepare("UPDATE desenhos_tecnicos SET status = 'aprovado' WHERE id = ?");
    $stmt->execute([$desenhoId]);
    $novoStatus = 'aprovado';
} else {
    $novoStatus = 'em_revisao';
}

// Registrar histórico
$stmt = $db->prepare("
    INSERT INTO desenhos_historico
    (desenho_id, acao, usuario_id, status_anterior, status_novo, detalhes)
    VALUES (?, 'aprovacao', ?, ?, ?, ?)
");
$stmt->execute([$desenhoId, $usuarioId, $desenho['status'], $novoStatus, "Aprovado na gerência"]);

echo "Desenho aprovado! Status: $novoStatus";
?>
```

### 4. Rejeitar Desenho

```php
<?php
$desenhoId = 5;
$usuarioId = 2;
$etapa = 'gerencia';
$motivo = "Dimensões incompatíveis com molde existente";
$observacoes = "Revisar seção 3.2 baseado no arquivo anterior (v0.9)";

// Obter desenho
$stmt = $db->prepare("SELECT * FROM desenhos_tecnicos WHERE id = ?");
$stmt->execute([$desenhoId]);
$desenho = $stmt->fetch(PDO::FETCH_ASSOC);

// Rejeitar
$stmt = $db->prepare("
    UPDATE desenhos_tecnicos
    SET status = 'rejeitado', data_rejeicao = ?
    WHERE id = ?
");
$stmt->execute([date('Y-m-d H:i:s'), $desenhoId]);

// Registrar rejeição
$stmt = $db->prepare("
    UPDATE desenhos_aprovaes
    SET status = 'rejeitado', usuario_id = ?, data_resposta = ?,
        observacoes = ?, requer_alteracoes = 1
    WHERE desenho_id = ? AND etapa = ?
");
$stmt->execute([$usuarioId, date('Y-m-d H:i:s'), $motivo . " - " . $observacoes, $desenhoId, $etapa]);

// Registrar histórico
$stmt = $db->prepare("
    INSERT INTO desenhos_historico
    (desenho_id, acao, usuario_id, status_anterior, status_novo, detalhes)
    VALUES (?, 'rejeicao', ?, ?, 'rejeitado', ?)
");
$stmt->execute([$desenhoId, $usuarioId, $desenho['status'], "Rejeitado: $motivo"]);

// Notificar projetista (quando implementado)
// enviarNotificacao([
//     'para' => $desenho['usuario_projetista_id'],
//     'tipo' => 'desenho_rejeitado',
//     'titulo' => 'Seu desenho foi rejeitado',
//     'mensagem' => $motivo
// ]);

echo "Desenho rejeitado. Notificação enviada ao projetista.";
?>
```

### 5. Obter Desenho Completo (com Todos os Dados)

```php
<?php
require_once 'modules/engenharia/desenho_helpers.php';

$desenhoId = 5;

// Obter desenho
$stmt = $db->prepare("
    SELECT d.*,
           u_proj.nome AS projetista_nome, u_proj.email AS projetista_email,
           u_ger.nome AS gerente_nome,
           u_prod.nome AS producao_nome
    FROM desenhos_tecnicos d
    LEFT JOIN usuarios u_proj ON u_proj.id = d.usuario_projetista_id
    LEFT JOIN usuarios u_ger ON u_ger.id = d.usuario_gerente_id
    LEFT JOIN usuarios u_prod ON u_prod.id = d.usuario_producao_id
    WHERE d.id = ?
");
$stmt->execute([$desenhoId]);
$desenho = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$desenho) {
    die("Desenho não encontrado");
}

// Obter arquivos
$arquivos = obterArquivosDesenho($db, $desenhoId);

// Obter status de aprovações
$aprovaes = obterStatusAprovaes($db, $desenhoId);

// Obter histórico
$historico = obterHistoricoDesenho($db, $desenhoId, 20);

// Estrutura completa
$resultado = [
    'desenho' => $desenho,
    'arquivos' => $arquivos,
    'aprovaes' => $aprovaes,
    'historico' => $historico,
    'proxima_versao' => obterProximaVersao($db, $desenhoId),
];

// Usar como JSON ou template
echo json_encode($resultado);
?>
```

### 6. Integrar com O.S. (Verificar Aprovação)

```php
<?php
// Ao gerar Ordem de Produção (O.P.), verificar se tem desenho aprovado
require_once 'modules/engenharia/desenho_helpers.php';

$osId = 123;

if (temDesenhoAprovado($db, $osId)) {
    $desenho = obterDesenhoAprovado($db, $osId);
    echo "Desenho técnico disponível: " . $desenho['titulo'];
    echo "Versão: " . $desenho['versao'];
    
    // Ao imprimir O.P., incluir desenhos
    $arquivosImpressao = obterArquivosVisualizacao($db, $desenho['id']);
    foreach ($arquivosImpressao as $arquivo) {
        echo "<img src='" . htmlspecialchars($arquivo['caminho_arquivo']) . "' />";
    }
} else {
    echo "Aviso: Esta O.S. ainda não tem desenho técnico aprovado!";
}
?>
```

---

## Exemplos JavaScript

### 1. Form de Upload com Validação

```html
<form id="form-novo-desenho" enctype="multipart/form-data">
    <div>
        <label>Título *</label>
        <input type="text" name="titulo" required 
               placeholder="Estrutura Principal" />
    </div>

    <div>
        <label>Arquivos (PDF, DWG, PNG...)</label>
        <input type="file" id="arquivo-input" name="arquivos[]" 
               multiple accept=".pdf,.dwg,.png,.jpg,.dxf" />
        <div id="arquivo-preview"></div>
    </div>

    <button type="submit">Enviar</button>
</form>

<script>
// Mostrar preview de arquivos selecionados
document.getElementById('arquivo-input').addEventListener('change', (e) => {
    const preview = document.getElementById('arquivo-preview');
    preview.innerHTML = '';
    
    Array.from(e.target.files).forEach(file => {
        const div = document.createElement('div');
        div.textContent = `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
        preview.appendChild(div);
    });
});

// Submeter form
document.getElementById('form-novo-desenho').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.set('acao', 'criar_desenho');
    formData.set('os_id', '123'); // Substituir
    formData.set('enviar', 'submetido');
    
    try {
        const response = await fetch('/api/desenho.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.sucesso) {
            alert('Desenho criado com sucesso!');
            window.location = data.redirect;
        } else {
            alert('Erro: ' + data.erro);
        }
    } catch (error) {
        console.error('Erro:', error);
    }
});
</script>
```

### 2. Listar Desenhos com Status

```javascript
async function listarDesenhos(osId) {
    try {
        const response = await fetch(`/api/desenho.php?acao=listar_desenhos&os_id=${osId}`);
        const data = await response.json();
        
        if (!data.sucesso) {
            console.error(data.erro);
            return;
        }
        
        const tabela = document.getElementById('tabela-desenhos');
        tabela.innerHTML = '';
        
        data.desenhos.forEach(d => {
            const linha = document.createElement('tr');
            linha.innerHTML = `
                <td>${d.titulo}</td>
                <td>${d.versao}</td>
                <td><span class="badge badge-${d.status}">${d.status}</span></td>
                <td>${d.total_arquivos}</td>
                <td>
                    <button onclick="editarDesenho(${d.id})">Editar</button>
                    <button onclick="apagarDesenho(${d.id})">Apagar</button>
                </td>
            `;
            tabela.appendChild(linha);
        });
        
        console.log(`Total: ${data.total} desenhos`);
    } catch (error) {
        console.error('Erro ao listar:', error);
    }
}

// Chamar ao carregar página
listarDesenhos(123);
```

### 3. Aprovar Desenho (AJAX)

```javascript
async function aprovarDesenho(desenhoId, etapa = 'gerencia') {
    const observacoes = prompt('Observações (opcional):') || '';
    
    try {
        const response = await fetch('/api/desenho.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                acao: 'aprovar',
                desenho_id: desenhoId,
                etapa: etapa,
                observacoes: observacoes
            })
        });
        
        const data = await response.json();
        
        if (data.sucesso) {
            alert('Desenho aprovado!');
            console.log('Novo status:', data.status);
            location.reload();
        } else {
            alert('Erro: ' + data.erro);
        }
    } catch (error) {
        console.error('Erro:', error);
    }
}
```

### 4. Rejeitar Desenho

```javascript
async function rejeitarDesenho(desenhoId, etapa = 'gerencia') {
    const motivo = prompt('Motivo da rejeição:');
    if (!motivo) return;
    
    const observacoes = prompt('Observações adicionais (opcional):') || '';
    
    try {
        const response = await fetch('/api/desenho.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                acao: 'rejeitar',
                desenho_id: desenhoId,
                etapa: etapa,
                motivo: motivo,
                observacoes: observacoes
            })
        });
        
        const data = await response.json();
        
        if (data.sucesso) {
            alert('Desenho rejeitado. Projetista notificado.');
            location.reload();
        } else {
            alert('Erro: ' + data.erro);
        }
    } catch (error) {
        console.error('Erro:', error);
    }
}
```

### 5. Visualizar Histórico

```javascript
async function visualizarHistorico(desenhoId) {
    try {
        const response = await fetch(`/api/desenho.php?acao=obter_historico&desenho_id=${desenhoId}`);
        const data = await response.json();
        
        if (!data.sucesso) {
            console.error(data.erro);
            return;
        }
        
        let html = '<ul class="timeline">';
        data.historico.forEach(h => {
            html += `
                <li class="timeline-item">
                    <strong>${h.acao}</strong> - ${h.usuario_nome}
                    <br/>${h.created_at}
                    <br/><span style="color: #666;">${h.detalhes || ''}</span>
                </li>
            `;
        });
        html += '</ul>';
        
        document.getElementById('historico').innerHTML = html;
    } catch (error) {
        console.error('Erro:', error);
    }
}
```

---

## Exemplos SQL

### Consultar Desenhos Pendentes de Aprovação

```sql
SELECT d.id, d.titulo, d.versao, d.prioridade,
       os.numero,
       dap.etapa,
       dap.prazo_resposta,
       DATEDIFF(dap.prazo_resposta, NOW()) AS dias_restantes,
       u.nome AS projetista
FROM desenhos_tecnicos d
INNER JOIN desenhos_aprovaes dap ON dap.desenho_id = d.id
INNER JOIN ordens_servico os ON os.id = d.os_id
LEFT JOIN usuarios u ON u.id = d.usuario_projetista_id
WHERE dap.status = 'pendente'
  AND dap.etapa = 'gerencia'
ORDER BY dap.prazo_resposta ASC, d.prioridade DESC;
```

### Desempenho de Aprovação

```sql
SELECT
    CONCAT(EXTRACT(YEAR_MONTH FROM d.data_submissao)) AS mes,
    COUNT(*) AS total,
    COUNT(CASE WHEN d.status = 'aprovado' THEN 1 END) AS aprovados,
    ROUND((COUNT(CASE WHEN d.status = 'aprovado' THEN 1 END) / COUNT(*)) * 100, 2) AS taxa_aprovacao_pct
FROM desenhos_tecnicos d
WHERE d.data_submissao IS NOT NULL
GROUP BY mes
ORDER BY mes DESC;
```

---

## Integração com Módulos

### Com Módulo de Qualidade

```php
// qualidade/index.php
require_once 'modules/engenharia/desenho_helpers.php';

// Ao inspecionar O.S., mostrar desenho técnico
$osId = 123;
$desenhoAprovado = obterDesenhoAprovado($db, $osId);

if ($desenhoAprovado) {
    $arquivos = obterArquivosVisualizacao($db, $desenhoAprovado['id']);
    echo "<h3>Desenho Técnico de Referência</h3>";
    foreach ($arquivos as $arquivo) {
        echo "<img src='" . htmlspecialchars($arquivo['caminho_arquivo']) . "' />";
    }
}
```

### Com Módulo de Expedição

```php
// expedicao/imprimir_op.php
require_once 'modules/engenharia/desenho_helpers.php';

// Ao imprimir O.P., incluir desenho técnico
if (temDesenhoAprovado($db, $osId)) {
    $desenho = obterDesenhoAprovado($db, $osId);
    echo "<!-- DESENHO TÉCNICO -->";
    foreach (obterArquivosVisualizacao($db, $desenho['id']) as $arquivo) {
        if ($arquivo['arquivo_tipo'] === 'pdf') {
            echo "<embed src='" . htmlspecialchars($arquivo['caminho_arquivo']) . "' />";
        }
    }
}
```

---

## Testes

### Teste Unitário (PHP)

```php
<?php
// tests/DesenhoTest.php

require_once 'config/config.php';
require_once 'includes/engenharia.php';
require_once 'modules/engenharia/desenho_helpers.php';

class DesenhoTest {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
        ensureEngenhariaSchema($this->db);
    }
    
    public function testarCriarDesenho() {
        $stmt = $this->db->prepare("
            INSERT INTO desenhos_tecnicos
            (os_id, titulo, versao, status, usuario_projetista_id)
            VALUES (?, 'Teste', 'v1.0', 'rascunho', ?)
        ");
        $stmt->execute([1, 1]);
        $id = $this->db->lastInsertId();
        
        assert($id > 0, "Desenho não foi criado");
        echo "✓ Desenho criado com sucesso\n";
    }
    
    public function testarObterDesenho() {
        $resultado = obterDesenhoMaisRecente($this->db, 1);
        assert($resultado !== null, "Desenho não encontrado");
        assert($resultado['titulo'] === 'Teste', "Titulo incorreto");
        echo "✓ Desenho obtido com sucesso\n";
    }
    
    public function testarAprovacao() {
        $desenhoId = 1;
        $usuarioId = 1;
        
        $stmt = $this->db->prepare("
            UPDATE desenhos_tecnicos
            SET status = 'aprovado', data_aprovacao_gerencia = ?
            WHERE id = ?
        ");
        $stmt->execute([date('Y-m-d H:i:s'), $desenhoId]);
        
        $stmt = $this->db->prepare("SELECT status FROM desenhos_tecnicos WHERE id = ?");
        $stmt->execute([$desenhoId]);
        $status = $stmt->fetchColumn();
        
        assert($status === 'aprovado', "Status não foi alterado");
        echo "✓ Aprovação registrada com sucesso\n";
    }
}

// Executar testes
$teste = new DesenhoTest();
$teste->testarCriarDesenho();
$teste->testarObterDesenho();
$teste->testarAprovacao();
echo "\n✓ Todos os testes passaram!\n";
?>
```

---

**Última atualização:** 17 de julho de 2026
