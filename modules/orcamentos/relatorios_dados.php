<?php
// relatorios_dados.php — JSON para Relatórios (com métricas extras)
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['error'=>'unauthorized']);
    exit;
}
require 'db.php';

$tipoUsuario = $_SESSION['tipo'] ?? 'user';
$meuId       = $_SESSION['id_usuario'] ?? 0;

$inicio = isset($_GET['inicio']) ? $_GET['inicio'].' 00:00:00' : date('Y-m-01 00:00:00');
$fim    = isset($_GET['fim'])    ? $_GET['fim'].' 23:59:59'   : date('Y-m-t 23:59:59');

$usuario_id = null;
if ($tipoUsuario === 'admin') {
    $usuario_id = ($_GET['usuario_id'] ?? 'all') !== 'all' ? (int)$_GET['usuario_id'] : null;
} else {
    $usuario_id = (int)$meuId;
}

// WHERE base
$where = "o.data_criacao BETWEEN ? AND ?";
$params = [$inicio, $fim];
$types  = "ss";
if (!empty($usuario_id)) {
    $where .= " AND o.id_usuario = ?";
    $params[] = $usuario_id;
    $types   .= "i";
}

// Base por orçamento (total_final, desconto aplicado, frete)
$sql_base = "
    SELECT
      o.id,
      o.codigo_orcamento,
      o.nome_cliente,
      o.pagamento,
      DATE(o.data_criacao) AS dia,
      COALESCE(o.frete,0) AS frete,
      COALESCE(o.desconto,0) AS desconto_perc,
      SUM(oi.preco_total) AS total_produtos,
      (SUM(oi.preco_total) + COALESCE(o.frete,0)) AS total_com_frete,
      (SUM(oi.preco_total) + COALESCE(o.frete,0)) * (COALESCE(o.desconto,0)/100) AS valor_desconto,
      (SUM(oi.preco_total) + COALESCE(o.frete,0)) - (SUM(oi.preco_total) + COALESCE(o.frete,0)) * (COALESCE(o.desconto,0)/100) AS total_final
    FROM orcamentos o
    JOIN orcamento_itens oi ON oi.id_orcamento = o.id
    WHERE {$where}
    GROUP BY o.id
";

// 1) Métricas gerais
$stmt = $conn->prepare("SELECT COUNT(*) qtd, COALESCE(SUM(total_final),0) total_final, COALESCE(SUM(valor_desconto),0) total_desconto, COALESCE(SUM(frete),0) total_frete FROM ($sql_base) t");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$agg = $stmt->get_result()->fetch_assoc();
$qtd           = (int)($agg['qtd'] ?? 0);
$total_final   = (float)($agg['total_final'] ?? 0.0);
$total_desc    = (float)($agg['total_desconto'] ?? 0.0);
$total_frete   = (float)($agg['total_frete'] ?? 0.0);
$ticket        = $qtd > 0 ? $total_final / $qtd : 0.0;

// 2) Série por dia
$stmt = $conn->prepare("SELECT dia, SUM(total_final) valor FROM ($sql_base) t GROUP BY dia ORDER BY dia ASC");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res_dia = $stmt->get_result();
$series_dia = [];
while ($r = $res_dia->fetch_assoc()) {
    $series_dia[] = ['dia'=> date('d/m', strtotime($r['dia'])), 'valor'=> (float)$r['valor'] ];
}

// 3) Top clientes
$stmt = $conn->prepare("SELECT nome_cliente, SUM(total_final) valor FROM ($sql_base) t GROUP BY nome_cliente ORDER BY valor DESC LIMIT 7");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res_cli = $stmt->get_result();
$top_clientes = [];
while ($r = $res_cli->fetch_assoc()) {
    $top_clientes[] = ['nome'=> $r['nome_cliente'] ?: '(Sem nome)', 'valor'=> (float)$r['valor'] ];
}

// 4) Pizza por forma de pagamento (soma do total_final)
$stmt = $conn->prepare("SELECT pagamento, SUM(total_final) valor FROM ($sql_base) t GROUP BY pagamento ORDER BY valor DESC");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res_pg = $stmt->get_result();
$pagamento_pizza = [];
while ($r = $res_pg->fetch_assoc()) {
    $pagamento_pizza[] = ['pagamento'=> $r['pagamento'] ?: '(não informado)', 'valor'=> (float)$r['valor'] ];
}

// 5) Total (produtos) por setor — soma dos itens
$sql_setor = "
    SELECT
      COALESCE(NULLIF(TRIM(oi.setor),''),'Sem setor') AS setor,
      SUM(oi.preco_total) AS valor
    FROM orcamentos o
    JOIN orcamento_itens oi ON oi.id_orcamento = o.id
    WHERE {$where}
    GROUP BY COALESCE(NULLIF(TRIM(oi.setor),''),'Sem setor')
    ORDER BY valor DESC
";
$stmt = $conn->prepare($sql_setor);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res_set = $stmt->get_result();
$total_por_setor = [];
while ($r = $res_set->fetch_assoc()) {
    $total_por_setor[] = ['setor'=> $r['setor'], 'valor'=> (float)$r['valor'] ];
}

// 6) Top produtos (por nome do item) — soma dos itens
$sql_prod = "
    SELECT
      oi.item,
      SUM(oi.preco_total) AS valor
    FROM orcamentos o
    JOIN orcamento_itens oi ON oi.id_orcamento = o.id
    WHERE {$where}
    GROUP BY oi.item
    ORDER BY valor DESC
    LIMIT 10
";
$stmt = $conn->prepare($sql_prod);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res_prod = $stmt->get_result();
$top_produtos = [];
while ($r = $res_prod->fetch_assoc()) {
    $top_produtos[] = ['item'=> $r['item'] ?: '(sem nome)', 'valor'=> (float)$r['valor'] ];
}

// 7) Últimos orçamentos do período
$stmt = $conn->prepare("
    SELECT t.id, t.codigo_orcamento, t.nome_cliente, t.total_final, t.dia
    FROM ($sql_base) t
    ORDER BY t.id DESC
    LIMIT 15
");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res_u = $stmt->get_result();
$ultimos = [];
while ($r = $res_u->fetch_assoc()) {
    $ultimos[] = [
        'id'     => (int)$r['id'],
        'codigo' => $r['codigo_orcamento'],
        'cliente'=> $r['nome_cliente'] ?: '(Sem nome)',
        'total'  => (float)$r['total_final'],
        'data'   => date('d/m/Y', strtotime($r['dia']))
    ];
}

echo json_encode([
    'qtd'             => $qtd,
    'total_final'     => $total_final,
    'ticket_medio'    => $ticket,
    'total_desconto'  => $total_desc,
    'total_frete'     => $total_frete,
    'series_dia'      => $series_dia,
    'top_clientes'    => $top_clientes,
    'pagamento_pizza' => $pagamento_pizza,
    'total_por_setor' => $total_por_setor,
    'top_produtos'    => $top_produtos,
    'ultimos'         => $ultimos,
], JSON_UNESCAPED_UNICODE);
