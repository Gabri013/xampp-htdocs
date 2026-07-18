<?php
/**
 * Mesclagem do banco antigo (10.129.76.12, staging local `dbcozinca_antigo`)
 * para o banco atual `dbcozinca`.
 *
 * Estratégia (linhagem comum confirmada — zero colisões de id/numero):
 *  - INSERT da "cauda" (linhas que só existem no antigo) mantendo ids,
 *    com remap de cliente_id 27/28 -> 26 (duplicatas WEST BURGER);
 *  - UPDATE das linhas compartilhadas em que a fábrica avançou no servidor
 *    antigo depois do clone (O.S., vendas, contas a receber, etapas);
 *  - logs por chave natural (sem id) para evitar colisão de PK;
 *  - notificacoes/notificacoes_envios NÃO são mescladas (histórico de
 *    alertas por usuário, ids colidem e não afetam operação).
 *
 * Uso:
 *   php mesclar_banco_antigo.php            (dry-run: só mostra contagens)
 *   php mesclar_banco_antigo.php --executar (aplica dentro de transação)
 *
 * Pré-requisitos: staging `dbcozinca_antigo` importado e backup feito
 * (backups/dbcozinca_pre_merge_*.sql).
 */

$executar = in_array('--executar', $argv ?? [], true);

$db = new PDO('mysql:host=localhost;dbname=dbcozinca;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

const ANTIGO = 'dbcozinca_antigo';
const NOVO = 'dbcozinca';

// Remap de clientes duplicados removidos do novo
const REMAP_CLIENTE = "CASE a.cliente_id WHEN 27 THEN 26 WHEN 28 THEN 26 ELSE a.cliente_id END";

function colunas(PDO $db, string $schema, string $tabela): array
{
    $stmt = $db->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema=? AND table_name=? ORDER BY ordinal_position");
    $stmt->execute([$schema, $tabela]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function listaSelect(array $cols, array $overrides = []): string
{
    return implode(', ', array_map(
        fn($c) => $overrides[$c] ?? "a.`$c`",
        $cols
    ));
}

$acoes = [];

// ── 1. INSERTs de cauda por id (mantém ids; antigo ⊆ novo em colunas) ──
$tabelasCaudaPorId = [
    'clientes' => ['where_extra' => 'AND a.id NOT IN (27,28)', 'remap' => []],
    'vendas' => ['where_extra' => '', 'remap' => ['cliente_id' => REMAP_CLIENTE]],
    'vendas_itens' => ['where_extra' => '', 'remap' => []],
    'ordens_servico' => ['where_extra' => '', 'remap' => ['cliente_id' => REMAP_CLIENTE]],
    'os_itens' => ['where_extra' => '', 'remap' => []],
    'os_arquivos' => ['where_extra' => '', 'remap' => []],
    'os_historico_status' => ['where_extra' => '', 'remap' => []],
    'contas_receber' => ['where_extra' => '', 'remap' => ['cliente_id' => REMAP_CLIENTE]],
];

foreach ($tabelasCaudaPorId as $tabela => $cfg) {
    $cols = colunas($db, ANTIGO, $tabela);
    $sel = listaSelect($cols, $cfg['remap']);
    $colList = '`' . implode('`, `', $cols) . '`';
    $sql = "INSERT INTO " . NOVO . ".`$tabela` ($colList)
            SELECT $sel FROM " . ANTIGO . ".`$tabela` a
            WHERE a.id NOT IN (SELECT id FROM " . NOVO . ".`$tabela`) {$cfg['where_extra']}";
    $count = "SELECT COUNT(*) FROM " . ANTIGO . ".`$tabela` a WHERE a.id NOT IN (SELECT id FROM " . NOVO . ".`$tabela`) {$cfg['where_extra']}";
    $acoes[] = ['desc' => "INSERT cauda: $tabela", 'sql' => $sql, 'count' => $count];
}

// ── 2. os_etapas_producao: por (os_id, etapa) ──
$colsEtapas = colunas($db, ANTIGO, 'os_etapas_producao');
$colsSemId = array_values(array_diff($colsEtapas, ['id']));
$colList = '`' . implode('`, `', $colsSemId) . '`';
$sel = listaSelect($colsSemId);
$acoes[] = [
    'desc' => 'INSERT etapas novas: os_etapas_producao (os_id+etapa)',
    'sql' => "INSERT INTO " . NOVO . ".os_etapas_producao ($colList)
              SELECT $sel FROM " . ANTIGO . ".os_etapas_producao a
              WHERE NOT EXISTS (SELECT 1 FROM " . NOVO . ".os_etapas_producao n WHERE n.os_id=a.os_id AND n.etapa=a.etapa)",
    'count' => "SELECT COUNT(*) FROM " . ANTIGO . ".os_etapas_producao a
                WHERE NOT EXISTS (SELECT 1 FROM " . NOVO . ".os_etapas_producao n WHERE n.os_id=a.os_id AND n.etapa=a.etapa)",
];

// Etapas compartilhadas em que o antigo tem progresso diferente (fábrica = verdade)
$acoes[] = [
    'desc' => 'UPDATE etapas compartilhadas divergentes: os_etapas_producao',
    'sql' => "UPDATE " . NOVO . ".os_etapas_producao n
              JOIN " . ANTIGO . ".os_etapas_producao a ON a.os_id=n.os_id AND a.etapa=n.etapa
              SET n.status=a.status, n.data_inicio=a.data_inicio, n.data_fim=a.data_fim,
                  n.tempo_total_segundos=a.tempo_total_segundos, n.usuario_id=a.usuario_id
              WHERE n.status<>a.status OR NOT(n.data_inicio<=>a.data_inicio) OR NOT(n.data_fim<=>a.data_fim)
                 OR n.tempo_total_segundos<>a.tempo_total_segundos",
    'count' => "SELECT COUNT(*) FROM " . NOVO . ".os_etapas_producao n
                JOIN " . ANTIGO . ".os_etapas_producao a ON a.os_id=n.os_id AND a.etapa=n.etapa
                WHERE n.status<>a.status OR NOT(n.data_inicio<=>a.data_inicio) OR NOT(n.data_fim<=>a.data_fim)
                   OR n.tempo_total_segundos<>a.tempo_total_segundos",
];

// ── 3. UPDATEs de linhas compartilhadas (servidor antigo = operação real) ──
$acoes[] = [
    'desc' => 'UPDATE O.S. compartilhadas (status/etapa/prioridade/datas)',
    'sql' => "UPDATE " . NOVO . ".ordens_servico n
              JOIN " . ANTIGO . ".ordens_servico a ON a.id=n.id
              SET n.status=a.status, n.etapa_atual=a.etapa_atual, n.prioridade=a.prioridade,
                  n.data_inicio=a.data_inicio, n.data_termino=a.data_termino
              WHERE n.status<>a.status OR NOT(n.etapa_atual<=>a.etapa_atual)
                 OR n.prioridade<>a.prioridade OR NOT(n.data_termino<=>a.data_termino)",
    'count' => "SELECT COUNT(*) FROM " . NOVO . ".ordens_servico n
                JOIN " . ANTIGO . ".ordens_servico a ON a.id=n.id
                WHERE n.status<>a.status OR NOT(n.etapa_atual<=>a.etapa_atual)
                   OR n.prioridade<>a.prioridade OR NOT(n.data_termino<=>a.data_termino)",
];

$acoes[] = [
    'desc' => 'UPDATE vendas compartilhadas (status/valor)',
    'sql' => "UPDATE " . NOVO . ".vendas n
              JOIN " . ANTIGO . ".vendas a ON a.id=n.id
              SET n.status=a.status, n.valor_total=a.valor_total
              WHERE n.status<>a.status OR n.valor_total<>a.valor_total",
    'count' => "SELECT COUNT(*) FROM " . NOVO . ".vendas n
                JOIN " . ANTIGO . ".vendas a ON a.id=n.id
                WHERE n.status<>a.status OR n.valor_total<>a.valor_total",
];

$acoes[] = [
    'desc' => 'UPDATE contas a receber compartilhadas (baixas feitas na fábrica)',
    'sql' => "UPDATE " . NOVO . ".contas_receber n
              JOIN " . ANTIGO . ".contas_receber a ON a.id=n.id
              SET n.status=a.status, n.valor_recebido=a.valor_recebido, n.valor_liquido=a.valor_liquido,
                  n.valor_bruto=a.valor_bruto, n.data_pagamento=a.data_pagamento,
                  n.forma_pagamento=a.forma_pagamento, n.observacoes=a.observacoes
              WHERE n.status<>a.status OR NOT(n.valor_recebido<=>a.valor_recebido)
                 OR n.valor_liquido<>a.valor_liquido OR NOT(n.data_pagamento<=>a.data_pagamento)",
    'count' => "SELECT COUNT(*) FROM " . NOVO . ".contas_receber n
                JOIN " . ANTIGO . ".contas_receber a ON a.id=n.id
                WHERE n.status<>a.status OR NOT(n.valor_recebido<=>a.valor_recebido)
                   OR n.valor_liquido<>a.valor_liquido OR NOT(n.data_pagamento<=>a.data_pagamento)",
];

// ── 4. Logs por chave natural (id novo, evita colisão de PK) ──
foreach (['logs_retorno_etapa', 'logs_sistema'] as $tabela) {
    $cols = colunas($db, ANTIGO, $tabela);
    $colsSemId = array_values(array_diff($cols, ['id']));
    $colList = '`' . implode('`, `', $colsSemId) . '`';
    $sel = listaSelect($colsSemId);
    $matchNullSafe = implode(' AND ', array_map(fn($c) => "n.`$c`<=>a.`$c`", $colsSemId));
    $acoes[] = [
        'desc' => "INSERT por chave natural: $tabela",
        'sql' => "INSERT INTO " . NOVO . ".`$tabela` ($colList)
                  SELECT $sel FROM " . ANTIGO . ".`$tabela` a
                  WHERE NOT EXISTS (SELECT 1 FROM " . NOVO . ".`$tabela` n WHERE $matchNullSafe)",
        'count' => "SELECT COUNT(*) FROM " . ANTIGO . ".`$tabela` a
                    WHERE NOT EXISTS (SELECT 1 FROM " . NOVO . ".`$tabela` n WHERE $matchNullSafe)",
    ];
}

// ── 5. ordens_producao antigas (só para O.S. sem OP local; id novo) ──
$colsOp = colunas($db, ANTIGO, 'ordens_producao');
$colsSemId = array_values(array_diff($colsOp, ['id']));
$colList = '`' . implode('`, `', $colsSemId) . '`';
$sel = listaSelect($colsSemId);
$acoes[] = [
    'desc' => 'INSERT ordens_producao antigas (O.S. sem OP no novo)',
    'sql' => "INSERT INTO " . NOVO . ".ordens_producao ($colList)
              SELECT $sel FROM " . ANTIGO . ".ordens_producao a
              WHERE a.os_id NOT IN (SELECT os_id FROM " . NOVO . ".ordens_producao)",
    'count' => "SELECT COUNT(*) FROM " . ANTIGO . ".ordens_producao a
                WHERE a.os_id NOT IN (SELECT os_id FROM " . NOVO . ".ordens_producao)",
];

// ── Execução ──
echo $executar ? "== EXECUTANDO MESCLAGEM ==\n" : "== DRY-RUN (nada será alterado) ==\n";

if ($executar) {
    $db->beginTransaction();
}

try {
    $total = 0;
    foreach ($acoes as $acao) {
        $qtd = (int) $db->query($acao['count'])->fetchColumn();
        printf("%-62s %5d linha(s)\n", $acao['desc'], $qtd);
        if ($executar && $qtd > 0) {
            $afetadas = $db->exec($acao['sql']);
            if ($afetadas != $qtd) {
                printf("   (afetadas: %d)\n", $afetadas);
            }
        }
        $total += $qtd;
    }

    if ($executar) {
        $db->commit();
        echo "\n== COMMIT OK — $total linha(s) mescladas ==\n";
    } else {
        echo "\n== DRY-RUN: $total linha(s) seriam mescladas. Rode com --executar ==\n";
    }
} catch (Throwable $e) {
    if ($executar && $db->inTransaction()) {
        $db->rollBack();
        echo "\n!! ROLLBACK: " . $e->getMessage() . "\n";
    } else {
        echo "\n!! ERRO: " . $e->getMessage() . "\n";
    }
    exit(1);
}
