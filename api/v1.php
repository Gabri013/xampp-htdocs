<?php
/**
 * API REST externa v1 — integração de sistemas (estilo Nomus).
 *
 * Autenticação: header  Authorization: Bearer <token>   (ou X-API-Key: <token>)
 * Formato: JSON. Base: /api/v1.php?resource=<recurso>[&id=<id>][&limit=&offset=]
 *
 * Recursos (GET / leitura):
 *   clientes, produtos, ordens-servico, ordens-producao, estoque, vendas
 * Criação (POST, requer token de escopo 'completo'):
 *   clientes
 *
 * Ex.: curl -H "Authorization: Bearer czk_..." \
 *        "http://SEU_HOST/api/v1.php?resource=ordens-producao&limit=20"
 */

require_once '../config/config.php';
require_once '../includes/api_auth.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function jout($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$db = getDB();
ensureApiTokensSchema($db);

$cliente = autenticarApi($db);
if (!$cliente) {
    jout(['erro' => 'Não autorizado. Envie o header "Authorization: Bearer <token>".'], 401);
}

$resource = strtolower(trim($_GET['resource'] ?? ''));
$id = (int) ($_GET['id'] ?? 0);
$metodo = $_SERVER['REQUEST_METHOD'];
$limit = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
$offset = max(0, (int) ($_GET['offset'] ?? 0));
$busca = trim($_GET['q'] ?? '');

// Índice da API (sem resource): lista os recursos disponíveis
if ($resource === '') {
    jout([
        'api'     => 'Cozinca ERP',
        'versao'  => 'v1',
        'token'   => $cliente['nome'],
        'escopo'  => $cliente['escopo'],
        'recursos' => [
            'GET  /api/v1.php?resource=clientes[&id=&q=&limit=&offset=]',
            'GET  /api/v1.php?resource=produtos[&id=&q=&limit=&offset=]',
            'GET  /api/v1.php?resource=ordens-servico[&id=&limit=&offset=]',
            'GET  /api/v1.php?resource=ordens-producao[&id=&limit=&offset=]',
            'GET  /api/v1.php?resource=estoque[&limit=&offset=]',
            'GET  /api/v1.php?resource=vendas[&id=&limit=&offset=]',
            'POST /api/v1.php?resource=clientes   (escopo completo)',
        ],
    ]);
}

// ── Escrita (POST) ────────────────────────────────────────────────
if ($metodo === 'POST') {
    if (($cliente['escopo'] ?? 'leitura') !== 'completo') {
        jout(['erro' => 'Este token é somente-leitura. Use um token de escopo "completo" para criar registros.'], 403);
    }
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) { $body = $_POST; }

    if ($resource === 'clientes') {
        $razao = trim($body['razao_social'] ?? '');
        if ($razao === '') jout(['erro' => 'razao_social é obrigatório'], 422);
        $cnpj = trim($body['cnpj_cpf'] ?? '');
        // Anti-duplicidade reaproveitando o helper do sistema
        if (function_exists('encontrarClienteDuplicado')) {
            $dup = encontrarClienteDuplicado($db, $razao, $cnpj);
            if ($dup) jout(['erro' => 'Cliente já existe', 'id' => (int) $dup['id']], 409);
        }
        $stmt = $db->prepare("INSERT INTO clientes (razao_social, nome_fantasia, cnpj_cpf, telefone, email, cidade, estado, endereco)
            VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $razao,
            trim($body['nome_fantasia'] ?? ''),
            $cnpj,
            trim($body['telefone'] ?? ''),
            trim($body['email'] ?? ''),
            trim($body['cidade'] ?? ''),
            trim($body['estado'] ?? ''),
            trim($body['endereco'] ?? ''),
        ]);
        jout(['sucesso' => true, 'id' => (int) $db->lastInsertId()], 201);
    }

    jout(['erro' => 'Recurso não aceita POST: ' . $resource], 404);
}

// ── Leitura (GET) ─────────────────────────────────────────────────
if ($metodo !== 'GET') {
    jout(['erro' => 'Método não suportado'], 405);
}

switch ($resource) {
    case 'clientes':
        if ($id > 0) {
            $stmt = $db->prepare("SELECT id, razao_social, nome_fantasia, cnpj_cpf, telefone, email, cidade, estado, endereco, created_at FROM clientes WHERE id = ?");
            $stmt->execute([$id]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            $r ? jout(['dado' => $r]) : jout(['erro' => 'Cliente não encontrado'], 404);
        }
        if ($busca !== '') {
            $stmt = $db->prepare("SELECT id, razao_social, nome_fantasia, cnpj_cpf, cidade, estado FROM clientes WHERE razao_social LIKE ? OR cnpj_cpf LIKE ? ORDER BY razao_social LIMIT $limit OFFSET $offset");
            $like = "%$busca%"; $stmt->execute([$like, $like]);
        } else {
            $stmt = $db->query("SELECT id, razao_social, nome_fantasia, cnpj_cpf, cidade, estado FROM clientes ORDER BY razao_social LIMIT $limit OFFSET $offset");
        }
        jout(['dados' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'limit' => $limit, 'offset' => $offset]);

    case 'produtos':
        if ($id > 0) {
            $stmt = $db->prepare("SELECT id, codigo, nome, descricao, unidade_medida, valor, custo_total, preco_sugerido, status FROM produtos WHERE id = ?");
            $stmt->execute([$id]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            $r ? jout(['dado' => $r]) : jout(['erro' => 'Produto não encontrado'], 404);
        }
        if ($busca !== '') {
            $stmt = $db->prepare("SELECT id, codigo, nome, unidade_medida, valor FROM produtos WHERE status='ativo' AND (nome LIKE ? OR codigo LIKE ?) ORDER BY nome LIMIT $limit OFFSET $offset");
            $like = "%$busca%"; $stmt->execute([$like, $like]);
        } else {
            $stmt = $db->query("SELECT id, codigo, nome, unidade_medida, valor FROM produtos WHERE status='ativo' ORDER BY nome LIMIT $limit OFFSET $offset");
        }
        jout(['dados' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'limit' => $limit, 'offset' => $offset]);

    case 'ordens-servico':
        if ($id > 0) {
            $stmt = $db->prepare("SELECT os.id, os.numero, os.status, os.etapa_atual, os.prioridade, os.data_inicio, os.data_termino,
                c.id AS cliente_id, c.razao_social AS cliente FROM ordens_servico os JOIN clientes c ON c.id=os.cliente_id WHERE os.id = ?");
            $stmt->execute([$id]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$r) jout(['erro' => 'O.S. não encontrada'], 404);
            $it = $db->prepare("SELECT oi.produto_id, COALESCE(p.nome, oi.descricao_manual) AS descricao, oi.quantidade FROM os_itens oi LEFT JOIN produtos p ON p.id=oi.produto_id WHERE oi.os_id = ?");
            $it->execute([$id]);
            $r['itens'] = $it->fetchAll(PDO::FETCH_ASSOC);
            jout(['dado' => $r]);
        }
        $stmt = $db->query("SELECT os.id, os.numero, os.status, os.etapa_atual, os.prioridade, os.data_termino, c.razao_social AS cliente
            FROM ordens_servico os JOIN clientes c ON c.id=os.cliente_id ORDER BY os.id DESC LIMIT $limit OFFSET $offset");
        jout(['dados' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'limit' => $limit, 'offset' => $offset]);

    case 'ordens-producao':
        if ($id > 0) {
            $stmt = $db->prepare("SELECT op.id, op.numero, op.status, op.criado_em, op.data_inicio, op.data_termino,
                os.id AS os_id, os.numero AS os_numero, os.etapa_atual, c.razao_social AS cliente
                FROM ordens_producao op LEFT JOIN ordens_servico os ON os.id=op.os_id LEFT JOIN clientes c ON c.id=os.cliente_id WHERE op.id = ?");
            $stmt->execute([$id]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            $r ? jout(['dado' => $r]) : jout(['erro' => 'O.P. não encontrada'], 404);
        }
        $stmt = $db->query("SELECT op.id, op.numero, op.status, op.criado_em, os.numero AS os_numero, os.etapa_atual
            FROM ordens_producao op LEFT JOIN ordens_servico os ON os.id=op.os_id ORDER BY op.id DESC LIMIT $limit OFFSET $offset");
        jout(['dados' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'limit' => $limit, 'offset' => $offset]);

    case 'estoque':
        $stmt = $db->query("SELECT es.produto_id, p.codigo, p.nome, es.quantidade_total AS saldo, es.quantidade_minima, es.localizacao
            FROM estoque_saldos es JOIN produtos p ON p.id=es.produto_id ORDER BY p.nome LIMIT $limit OFFSET $offset");
        jout(['dados' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'limit' => $limit, 'offset' => $offset]);

    case 'vendas':
        if ($id > 0) {
            $stmt = $db->prepare("SELECT v.id, v.numero, v.status, v.valor_total, v.data_venda, c.razao_social AS cliente
                FROM vendas v JOIN clientes c ON c.id=v.cliente_id WHERE v.id = ?");
            $stmt->execute([$id]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            $r ? jout(['dado' => $r]) : jout(['erro' => 'Venda não encontrada'], 404);
        }
        $stmt = $db->query("SELECT v.id, v.numero, v.status, v.valor_total, v.data_venda, c.razao_social AS cliente
            FROM vendas v JOIN clientes c ON c.id=v.cliente_id ORDER BY v.id DESC LIMIT $limit OFFSET $offset");
        jout(['dados' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'limit' => $limit, 'offset' => $offset]);

    default:
        jout(['erro' => 'Recurso desconhecido: ' . $resource . '. Chame /api/v1.php sem parâmetros para ver a lista.'], 404);
}
