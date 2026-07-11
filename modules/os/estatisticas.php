<?php
require_once '../../config/config.php';
require_once '../../includes/notificacao_atrasos.php';
requirePermission(['master', 'gerente', 'producao', 'producao_geral']);

$page_title = 'Estatísticas de Produção';

$db = getDB();

try {
    // 1. Resumo Geral
    $stmt = $db->query("SELECT COUNT(*) as total FROM ordens_servico");
    $total_os = $stmt->fetch()['total'];

    $stmt = $db->query("SELECT COUNT(*) as total FROM ordens_servico WHERE status = 'concluida'");
    $total_concluidas = $stmt->fetch()['total'];

    $stmt = $db->query("SELECT COUNT(*) as total FROM ordens_servico WHERE status = 'em_producao'");
    $total_producao = $stmt->fetch()['total'];

    // 2. Tempo médio por etapa
    $stmt = $db->query("
        SELECT etapa, 
               AVG(tempo_total_segundos) as tempo_medio,
               COUNT(*) as total_concluidas,
               MIN(tempo_total_segundos) as tempo_minimo,
               MAX(tempo_total_segundos) as tempo_maximo
        FROM os_etapas_producao 
        WHERE status = 'concluida'
        GROUP BY etapa
        ORDER BY FIELD(etapa, 'corte', 'dobra', 'solda', 'refrigeracao', 'acabamento', 'finalizacao', 'montagem')
    ");
    $estatisticas_etapas = $stmt->fetchAll();

    // 3. Tempo total por O.S (Últimas 20 Concluídas)
    $stmt = $db->query("
        SELECT os.numero, c.razao_social, 
               SUM(ep.tempo_total_segundos) as tempo_total,
               os.data_inicio, os.updated_at as data_conclusao
        FROM ordens_servico os
        INNER JOIN clientes c ON os.cliente_id = c.id
        INNER JOIN os_etapas_producao ep ON os.id = ep.os_id
        WHERE os.status = 'concluida'
        GROUP BY os.id
        ORDER BY os.updated_at DESC
        LIMIT 20
    ");
    $recentes = $stmt->fetchAll();

    // 4. Produtividade do Mes (Produtos Produzidos)
    $mes_atual = date('Y-m');
    $stmt = $db->query("
        SELECT p.id, p.nome, p.codigo, SUM(vi.quantidade) as quantidade_produzida
        FROM vendas_itens vi
        INNER JOIN vendas v ON vi.venda_id = v.id
        INNER JOIN ordens_servico os ON v.id = os.venda_id
        INNER JOIN produtos p ON vi.produto_id = p.id
        WHERE os.status = 'concluida' 
        AND DATE_FORMAT(os.updated_at, '%Y-%m') = '$mes_atual'
        AND vi.produto_id IS NOT NULL
        GROUP BY p.id
        ORDER BY quantidade_produzida DESC
    ");
    $produtividade_mes = $stmt->fetchAll();

    // Calcular total de itens produzidos no mes
    $total_itens_mes = 0;
    foreach ($produtividade_mes as $prod) {
        $total_itens_mes += $prod['quantidade_produzida'];
    }
    
    // 5. Carregar O.S. atrasadas (para destaque na página)
    require_once '../../includes/os_atrasadas.php';
    $os_atrasadas = getOSAtrasadas($db);
    $contagem_atraso = contarOSAtrasadas($os_atrasadas);
    
    // Verificar limite de atrasos
    $alerta_limite = verificarLimiteAtrasos($contagem_atraso);
} catch (Exception $e) {
    // Se houver erro no banco, inicializa variáveis vazias para não quebrar a página
    $total_os = 0;
    $total_concluidas = 0;
    $total_producao = 0;
    $estatisticas_etapas = [];
    $recentes = [];
    $produtividade_mes = [];
    $total_itens_mes = 0;
    $os_atrasadas = [];
    $contagem_atraso = ['critico' => 0, 'urgente' => 0, 'atrasado' => 0, 'total' => 0];
    $erro_db = "Erro ao carregar estatísticas: " . $e->getMessage();
}

function formatarTempo($segundos) {
    if ($segundos === null) return '--';
    $h = floor($segundos / 3600);
    $m = floor(($segundos % 3600) / 60);
    $s = floor($segundos % 60);
    
    $partes = [];
    if ($h > 0) $partes[] = "{$h}h";
    if ($m > 0) $partes[] = "{$m}m";
    if ($s > 0 || empty($partes)) $partes[] = "{$s}s";
    
    return implode(' ', $partes);
}

include '../../includes/header_vendedor.php';

if (isset($erro_db)) {
    echo '<div class="alert alert-danger">' . $erro_db . '</div>';
}

// Preparar dados para o gráfico
$labels = [];
$tempos_medios = [];
$cores_grafico = [];

// Encontrar o maior tempo médio para destacar como gargalo
$maior_tempo = 0;
foreach ($estatisticas_etapas as $est) {
    if ($est['tempo_medio'] > $maior_tempo) {
        $maior_tempo = $est['tempo_medio'];
    }
}

foreach ($estatisticas_etapas as $est) {
    $labels[] = ucfirst($est['etapa']);
    $tempos_medios[] = round($est['tempo_medio'] / 3600, 2);
    $cores_grafico[] = ($est['tempo_medio'] == $maior_tempo && $maior_tempo > 0) ? 'rgba(220, 53, 69, 0.8)' : 'rgba(0, 123, 255, 0.6)';
}
?>
<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main"><div class="vend-page-head"><h1 class="vend-page-title">Estatísticas de Produção</h1></div><div class="vend-content">
<div class="card mb-20">
    <div class="card-header">
        <h3><i class="fas fa-chart-bar"></i> Gráfico de Desempenho (Tempo Médio em Horas)</h3>
    </div>
    <div class="card-body">
        <div style="height: 300px;">
            <canvas id="graficoGargalos"></canvas>
        </div>
        <div class="mt-10 text-center">
            <span style="display: inline-block; width: 15px; height: 15px; background: rgba(220, 53, 69, 0.8); margin-right: 5px;"></span> 
            <small>Possível Gargalo (Maior Tempo Médio)</small>
        </div>
    </div>
</div>

<!-- Alerta de Limite de Atrasos -->
<?php if (isset($alerta_limite) && $alerta_limite['ativo']): ?>
<div class="<?php echo obterClasseAlerta($alerta_limite['nivel']); ?>">
    <i class="<?php echo obterIconeAlerta($alerta_limite['nivel']); ?>"></i>
    <div style="flex: 1;">
        <h4><?php echo $alerta_limite['mensagem']; ?></h4>
        <p>
            Crítico: <?php echo $contagem_atraso['critico']; ?> 
            | Urgente: <?php echo $contagem_atraso['urgente']; ?> 
            | Atrasado: <?php echo $contagem_atraso['atrasado']; ?>
        </p>
        <div class="barra-limite-atraso <?php echo $alerta_limite['nivel']; ?>">
            <div class="progresso" style="width: <?php echo min($alerta_limite['percentual_total'], 100); ?>%;"></div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Destaque de O.S. Atrasadas -->
<?php if ($contagem_atraso['total'] > 0): ?>
<div class="card mt-20 card-atrasadas-compacta" style="margin-bottom: 20px;">
    <div class="card-header-compacta">
        <h3><i class="fas fa-exclamation-triangle"></i> O.S. em Atraso</h3>
        <div class="atraso-badges">
            <?php if ($contagem_atraso['critico'] > 0): ?>
                <span class="badge-mini badge-critico" title="Crítico (7+ dias)">🔴 <?php echo $contagem_atraso['critico']; ?></span>
            <?php endif; ?>
            <?php if ($contagem_atraso['urgente'] > 0): ?>
                <span class="badge-mini badge-urgente" title="Urgente (3-6 dias)">🟠 <?php echo $contagem_atraso['urgente']; ?></span>
            <?php endif; ?>
            <?php if ($contagem_atraso['atrasado'] > 0): ?>
                <span class="badge-mini badge-atrasado" title="Atrasado (1-2 dias)">🟡 <?php echo $contagem_atraso['atrasado']; ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php else: ?>
<div class="card mt-20 card-sucesso-producao" style="margin-bottom: 20px;">
    <div class="card-header-sucesso">
        <div class="sucesso-content">
            <div class="sucesso-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="sucesso-text">
                <h3>Sem O.S. em atraso</h3>
                <p>Não há ordens de serviço vencidas no momento.</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="form-row" style="display: flex; gap: 20px; margin-bottom: 20px;">
    <div class="card" style="flex: 1; text-align: center; padding: 20px;">
        <h4 style="margin: 0; color: #666;">Total de O.S</h4>
        <h2 style="margin: 10px 0; font-size: 2.5rem;"><?php echo $total_os; ?></h2>
    </div>
    <div class="card" style="flex: 1; text-align: center; padding: 20px; border-left: 5px solid var(--success-color);">
        <h4 style="margin: 0; color: #666;">Concluídas</h4>
        <h2 style="margin: 10px 0; font-size: 2.5rem; color: var(--success-color);"><?php echo $total_concluidas; ?></h2>
    </div>
    <div class="card" style="flex: 1; text-align: center; padding: 20px; border-left: 5px solid var(--warning-color);">
        <h4 style="margin: 0; color: #666;">Em Produção</h4>
        <h2 style="margin: 10px 0; font-size: 2.5rem; color: var(--warning-color);"><?php echo $total_producao; ?></h2>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-stopwatch"></i> Desempenho por Etapa de Produção</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Etapa</th>
                        <th class="text-center">Qtd. Concluída</th>
                        <th>Tempo Médio</th>
                        <th>Tempo Mínimo</th>
                        <th>Tempo Máximo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($estatisticas_etapas)): ?>
                        <tr><td colspan="5" class="text-center">Nenhum dado de produção registrado ainda.</td></tr>
                    <?php else: ?>
                        <?php foreach ($estatisticas_etapas as $est): ?>
                            <tr>
                                <td><strong><?php echo ucfirst($est['etapa']); ?></strong></td>
                                <td class="text-center"><?php echo $est['total_concluidas']; ?></td>
                                <td style="color: var(--primary-color); font-weight: bold;"><?php echo formatarTempo($est['tempo_medio']); ?></td>
                                <td style="color: var(--success-color);"><?php echo formatarTempo($est['tempo_minimo']); ?></td>
                                <td style="color: var(--danger-color);"><?php echo formatarTempo($est['tempo_maximo']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mt-20">
    <div class="card-header">
        <h3><i class="fas fa-history"></i> Histórico de Tempo Total por O.S</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>O.S</th>
                        <th>Cliente</th>
                        <th>Data Início</th>
                        <th>Data Conclusão</th>
                        <th>Tempo Total de Produção</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentes)): ?>
                        <tr><td colspan="5" class="text-center">Nenhuma O.S concluída para exibir estatísticas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentes as $r): ?>
                            <tr>
                                <td><strong><?php echo $r['numero']; ?></strong></td>
                                <td><?php echo htmlspecialchars($r['razao_social']); ?></td>
                                <td><?php echo formatDate($r['data_inicio']); ?></td>
                                <td><?php echo formatDateTime($r['data_conclusao']); ?></td>
                                <td style="font-weight: bold;"><?php echo formatarTempo($r['tempo_total']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
</tbody>
                </table>
            </div>
        </div>
    </div>
</div>
    </div>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('graficoGargalos').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($labels); ?>,
            datasets: [{
                label: 'Tempo Médio (Horas)',
                data: <?php echo json_encode($tempos_medios); ?>,
                backgroundColor: <?php echo json_encode($cores_grafico); ?>,
                borderColor: <?php echo json_encode($cores_grafico); ?>,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Horas'
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
});
</script>

<div class="card mt-20">
    <div class="card-header">
        <h3><i class="fas fa-box"></i> Produtividade do Mês - Produtos Produzidos</h3>
    </div>
    <div class="card-body">
        <div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
            <strong style="font-size: 1.2rem;">Total de Itens Produzidos no Mês:</strong>
            <span style="font-size: 1.5rem; color: var(--primary-color); margin-left: 10px;"><?php echo $total_itens_mes; ?> unidades</span>
        </div>
        
        <?php if (empty($produtividade_mes)): ?>
            <p class="text-center" style="color: #999; padding: 20px;">Nenhum produto foi concluído neste mês ainda.</p>
        <?php else: ?>
            <div style="height: 300px; margin-bottom: 30px;">
                <canvas id="graficoProdutividade"></canvas>
            </div>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Código</th>
                            <th class="text-center">Quantidade Produzida</th>
                            <th class="text-center">% do Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produtividade_mes as $prod): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($prod['nome']); ?></strong></td>
                                <td><?php echo htmlspecialchars($prod['codigo']); ?></td>
                                <td class="text-center" style="font-weight: bold;"><?php echo intval($prod['quantidade_produzida']); ?></td>
                                <td class="text-center"><?php echo number_format(($prod['quantidade_produzida'] / $total_itens_mes) * 100, 1); ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Preparar dados para o gráfico de produtividade
    const produtosLabels = <?php echo json_encode(array_map(function($p) { return $p['nome']; }, $produtividade_mes)); ?>;
    const produtosQuantidades = <?php echo json_encode(array_map(function($p) { return intval($p['quantidade_produzida']); }, $produtividade_mes)); ?>;
    
    // Gerar cores aleatórias para cada barra
    const cores = produtosLabels.map(() => {
        const r = Math.floor(Math.random() * 200 + 55);
        const g = Math.floor(Math.random() * 200 + 55);
        const b = Math.floor(Math.random() * 200 + 55);
        return `rgba(${r}, ${g}, ${b}, 0.7)`;
    });
    
    const ctxProdutividade = document.getElementById('graficoProdutividade');
    if (ctxProdutividade) {
        new Chart(ctxProdutividade.getContext('2d'), {
            type: 'bar',
            data: {
                labels: produtosLabels,
                datasets: [{
                    label: 'Quantidade Produzida',
                    data: produtosQuantidades,
                    backgroundColor: cores,
                    borderColor: cores,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                }
            }
        });
    }
});
</script>

<?php include '../../includes/footer_vendedor.php'; ?>

