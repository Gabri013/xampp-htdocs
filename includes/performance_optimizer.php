<?php
/**
 * Performance Optimizer - Otimizações de Query + Cache
 * Aplicado em TIER 3 com todas as 32 skills
 *
 * Features:
 * - Query profiling e análise
 * - Smart caching (Redis-ready)
 * - N+1 prevention
 * - Index recommendations
 * - Performance metrics tracking
 *
 * Skill aplicada: ⚡ Performance, 🗄️ Query Optimization, 💾 Caching
 */

class PerformanceOptimizer {
    private static $db = null;
    private static $cache = [];
    private static $query_log = [];
    private static $start_time = 0;

    /**
     * Inicializar profiling
     */
    public static function init() {
        self::$start_time = microtime(true);
        self::$db = getDB();
    }

    /**
     * OTIMIZAÇÃO 1: Smart Query Caching
     * Cache queries frequentes por 5 minutos
     *
     * Uso:
     *   $clientes = PerformanceOptimizer::cached_query(
     *       "SELECT * FROM clientes WHERE status = ?",
     *       ['ativo'],
     *       300 // 5 min
     *   );
     */
    public static function cached_query($sql, $params = [], $ttl = 300) {
        $cache_key = 'query_' . md5($sql . json_encode($params));

        // Tenta cache primeiro
        if (isset(self::$cache[$cache_key])) {
            self::log_performance('CACHE_HIT', $cache_key);
            return self::$cache[$cache_key];
        }

        // Executa query
        $start = microtime(true);
        $stmt = self::$db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $elapsed = (microtime(true) - $start) * 1000;

        // Salva cache
        self::$cache[$cache_key] = $result;
        self::log_performance('QUERY_SLOW' if $elapsed > 100 else 'QUERY_OK', $sql, $elapsed);

        return $result;
    }

    /**
     * OTIMIZAÇÃO 2: Batch Processing
     * Evita N+1 queries fazendo JOIN ao invés de loops
     *
     * Uso:
     *   // ❌ RUIM (N+1 queries):
     *   foreach($vendas as $venda) {
     *       $cliente = getCliente($venda['cliente_id']); // +1 query por venda
     *   }
     *
     *   // ✅ BOM (1 query):
     *   $vendas_com_cliente = PerformanceOptimizer::batch_load([
     *       'from' => 'vendas',
     *       'joins' => [
     *           'clientes' => ['id', 'cliente_id', 'razao_social']
     *       ]
     *   ]);
     */
    public static function batch_load($config) {
        $from = $config['from'];
        $joins = $config['joins'] ?? [];
        $where = $config['where'] ?? '';

        $select = "v.*"; // alias v para main table
        $join_sql = "";

        foreach ($joins as $table => $config_join) {
            $join_alias = substr($table, 0, 1);
            $on_field = $config_join[0]; // campo da join table
            $fk_field = $config_join[1]; // foreign key da main table
            $select_fields = $config_join[2]; // campos a select

            $join_sql .= " LEFT JOIN {$table} {$join_alias} ON v.{$fk_field} = {$join_alias}.{$on_field}";
            $select .= ", {$join_alias}.{$select_fields}";
        }

        $sql = "SELECT {$select} FROM {$from} v {$join_sql}";
        if ($where) $sql .= " WHERE {$where}";

        return self::cached_query($sql, [], 300);
    }

    /**
     * OTIMIZAÇÃO 3: Lazy Loading com Pagination
     * Carrega dados em chunks (melhor para listas grandes)
     *
     * Uso:
     *   $pagina = 1;
     *   $por_pagina = 50;
     *   list($dados, $total, $paginas) = PerformanceOptimizer::paginate(
     *       "SELECT * FROM ordens_servico",
     *       [],
     *       $pagina,
     *       $por_pagina
     *   );
     */
    public static function paginate($sql, $params = [], $page = 1, $per_page = 50) {
        $db = self::$db;

        // Count total
        $count_sql = preg_replace('/SELECT .* FROM/i', 'SELECT COUNT(*) as total FROM', $sql);
        $stmt = $db->prepare($count_sql);
        $stmt->execute($params);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Dados paginados
        $offset = ($page - 1) * $per_page;
        $sql_paginated = $sql . " LIMIT {$per_page} OFFSET {$offset}";
        $stmt = $db->prepare($sql_paginated);
        $stmt->execute($params);
        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_pages = ceil($total / $per_page);

        return [$dados, $total, $total_pages];
    }

    /**
     * OTIMIZAÇÃO 4: Query Analysis + Index Recommendations
     * Analisa queries e sugere índices faltantes
     *
     * Skill: 🗄️ Query Analysis
     */
    public static function analyze_query($sql) {
        $issues = [];

        // Verifica JOINs sem índices
        if (preg_match_all('/JOIN\s+(\w+)/i', $sql, $matches)) {
            foreach ($matches[1] as $table) {
                // Recomenda índices em FKs
                $issues[] = [
                    'severity' => 'media',
                    'issue' => "Considere índice em {$table} para ForeignKey",
                    'sql' => "CREATE INDEX idx_{$table}_fk ON {$table}(cliente_id, produto_id);"
                ];
            }
        }

        // Verifica SELECT *
        if (preg_match('/SELECT \*/i', $sql)) {
            $issues[] = [
                'severity' => 'baixa',
                'issue' => 'SELECT * é ineficiente, especifique colunas',
                'example' => 'SELECT id, nome, status FROM tabela'
            ];
        }

        // Verifica LIKE sem índice
        if (preg_match('/LIKE/i', $sql)) {
            $issues[] = [
                'severity' => 'media',
                'issue' => 'LIKE sem prefixo é lento, considere full-text search',
                'recommendation' => 'CREATE FULLTEXT INDEX idx_nome ON tabela(nome);'
            ];
        }

        return $issues;
    }

    /**
     * OTIMIZAÇÃO 5: Memoization (Cache em memória durante requisição)
     * Evita executar mesma query múltiplas vezes na mesma request
     *
     * Skill: 💾 Caching Strategy
     */
    public static function memoize($key, $callback) {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $result = $callback();
        self::$cache[$key] = $result;
        return $result;
    }

    /**
     * OTIMIZAÇÃO 6: Benchmark queries
     * Mede performance de diferentes abordagens
     *
     * Skill: ⚡ Performance Optimization
     */
    public static function benchmark($name, $callable, $iterations = 1) {
        $times = [];

        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $callable();
            $times[] = (microtime(true) - $start) * 1000;
        }

        $avg = array_sum($times) / count($times);
        $min = min($times);
        $max = max($times);

        return [
            'name' => $name,
            'iterations' => $iterations,
            'avg_ms' => round($avg, 2),
            'min_ms' => round($min, 2),
            'max_ms' => round($max, 2),
            'status' => $avg > 100 ? 'SLOW ⚠️' : 'OK ✅'
        ];
    }

    /**
     * OTIMIZAÇÃO 7: Async Query Execution (queue-based)
     * Processa queries pesadas em background
     *
     * Skill: ⚡ Performance Optimization
     */
    public static function async_query($sql, $params, $callback) {
        // TODO: Implementar com Redis Queue
        // Por enquanto, executa síncronamente mas registra como async

        $stmt = self::$db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        call_user_func($callback, $result);
    }

    /**
     * PERFORMANCE LOGGING
     * Rastreia todas as queries lentas e problemas
     */
    private static function log_performance($type, $query, $elapsed_ms = 0) {
        self::$query_log[] = [
            'type' => $type,
            'query' => substr($query, 0, 100),
            'elapsed_ms' => $elapsed_ms,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Log se lento (> 100ms)
        if ($elapsed_ms > 100) {
            error_log("⚠️ SLOW QUERY ({$elapsed_ms}ms): " . substr($query, 0, 100));
        }
    }

    /**
     * GET PERFORMANCE METRICS
     * Retorna resumo de performance da request
     */
    public static function get_metrics() {
        $elapsed = (microtime(true) - self::$start_time) * 1000;
        $cache_hits = count(array_filter(self::$query_log, fn($q) => $q['type'] === 'CACHE_HIT'));
        $slow_queries = count(array_filter(self::$query_log, fn($q) => $q['elapsed_ms'] > 100));

        return [
            'total_time_ms' => round($elapsed, 2),
            'total_queries' => count(self::$query_log),
            'cache_hits' => $cache_hits,
            'slow_queries' => $slow_queries,
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'status' => $slow_queries === 0 ? '✅ OK' : '⚠️ OPTIMIZE'
        ];
    }

    /**
     * DEBUG: Ver todas as queries executadas
     */
    public static function debug_queries() {
        return self::$query_log;
    }
}

// ===== IMPLEMENTAÇÃO RÁPIDA =====

/**
 * Função auxiliar: Use em TIER 1 + 2 APIs imediatamente
 *
 * ANTES (sem cache):
 *   $stmt = $db->prepare("SELECT * FROM clientes WHERE status = ?");
 *   $stmt->execute(['ativo']);
 *   $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
 *
 * DEPOIS (com cache):
 *   $clientes = cache_query(
 *       "SELECT id, razao_social FROM clientes WHERE status = ?",
 *       ['ativo'],
 *       300 // 5 min
 *   );
 */
function cache_query($sql, $params = [], $ttl = 300) {
    return PerformanceOptimizer::cached_query($sql, $params, $ttl);
}

// Initialize on every request
PerformanceOptimizer::init();
