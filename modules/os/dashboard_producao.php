<?php
require_once '../../config/config.php';
require_once '../../includes/os_atrasadas.php';
require_once '../../includes/notificacao_atrasos.php';
requirePermission(['master', 'dashboard_producao', 'gerente', 'producao']);

$page_title = 'Panorama Geral da Produção';
$db = getDB();
$usuario = getCurrentUser();

// 1. Dados para o Gráfico por Etapas de Produção
$stmt = $db->query("
    SELECT etapa_atual, COUNT(*) as total 
    FROM ordens_servico 
    WHERE status = 'em_producao'
    GROUP BY etapa_atual
");
$etapas_data = $stmt->fetchAll();

$labels_etapas = [];
$valores_etapas = [];
$cores_etapas = [
    'autorizacao' => '#6c757d',
    'corte' => '#007bff',
    'dobra' => '#6610f2',
    'solda' => '#fd7e14',
    'refrigeracao' => '#0ea5e9',
    'acabamento' => '#20c997',
    'finalizacao' => '#28a745',
    'montagem' => '#17a2b8',
    'concluida' => '#28a745'
];

$labels_nomes = [
    'autorizacao' => 'Aguardando Autorização',
    'corte' => 'Corte',
    'dobra' => 'Dobra',
    'solda' => 'Solda',
    'refrigeracao' => 'Refrigeração',
    'acabamento' => 'Acabamento',
    'finalizacao' => 'Finalização',
    'montagem' => 'Montagem',
    'concluida' => 'Concluída'
];

$cores_grafico = [];
foreach ($etapas_data as $row) {
    $labels_etapas[] = $labels_nomes[$row['etapa_atual']] ?? ucfirst($row['etapa_atual']);
    $valores_etapas[] = $row['total'];
    $cores_grafico[] = $cores_etapas[$row['etapa_atual']] ?? '#adb5bd';
}

// 2. Indicadores Rápidos
$stmt = $db->query("SELECT COUNT(*) FROM ordens_servico WHERE status = 'em_producao'");
$total_os_producao = $stmt->fetchColumn();

// 3. Carregar O.S. atrasadas (apenas para Master, Gerente de Produção, Encarregado de Produção e Gerente Geral)
$tipos_permitidos = ['master', 'gerente', 'dashboard_producao', 'producao'];
$os_atrasadas = [];
$contagem_atraso = ['critico' => 0, 'urgente' => 0, 'atrasado' => 0, 'total' => 0];

if (!empty($usuario['tipo']) && in_array($usuario['tipo'], $tipos_permitidos, true)) {
    $os_atrasadas = getOSAtrasadas($db);
    $contagem_atraso = contarOSAtrasadas($os_atrasadas);

    $stmtConferenciaAtrasos = $db->query("
        SELECT 
            os.id,
            os.numero,
            os.data_termino,
            os.prioridade,
            c.razao_social,
            DATEDIFF(CURDATE(), DATE(os.data_termino)) AS dias_atraso,
            CASE
                WHEN DATEDIFF(CURDATE(), DATE(os.data_termino)) >= 7 THEN 'critico'
                WHEN DATEDIFF(CURDATE(), DATE(os.data_termino)) >= 3 THEN 'urgente'
                ELSE 'atrasado'
            END AS nivel_atraso
        FROM ordens_servico os
        INNER JOIN clientes c ON c.id = os.cliente_id
        WHERE os.status NOT IN ('concluida', 'cancelada')
          AND os.data_termino IS NOT NULL
          AND DATE(os.data_termino) <= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
        ORDER BY DATE(os.data_termino) ASC, os.numero ASC
    ");
    $atrasosConferencia = $stmtConferenciaAtrasos->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($atrasosConferencia)) {
        $os_atrasadas = [
            'critico' => [],
            'urgente' => [],
            'atrasado' => [],
        ];

        foreach ($atrasosConferencia as $osAtrasada) {
            $nivel = $osAtrasada['nivel_atraso'] ?? 'atrasado';
            if (!isset($os_atrasadas[$nivel])) {
                $os_atrasadas[$nivel] = [];
            }
            $os_atrasadas[$nivel][] = $osAtrasada;
        }

        $contagem_atraso = contarOSAtrasadas($os_atrasadas);
    }
    
    // Verificar limite de atrasos
    $alerta_limite = verificarLimiteAtrasos($contagem_atraso);
}

$stmt = $db->query("SELECT COUNT(*) FROM vendas WHERE MONTH(data_venda) = MONTH(CURDATE()) AND YEAR(data_venda) = YEAR(CURDATE()) AND status != 'cancelada'");
$total_vendas_mes = $stmt->fetchColumn();

// 4. Lógica da Agenda (30 dias divididos em 2 partes)
$data_inicio = date('Y-m-d');
$agenda_parte1 = [];
$agenda_parte2 = [];

for ($i = 0; $i < 30; $i++) {
    $data = date('Y-m-d', strtotime("+$i days"));
    if ($i < 15) {
        $agenda_parte1[$data] = [];
    } else {
        $agenda_parte2[$data] = [];
    }
}

// Buscar entregas para os 30 dias
$data_fim = date('Y-m-d', strtotime("+29 days"));
$stmt = $db->prepare("
    SELECT os.id, os.numero, os.data_termino, c.razao_social 
    FROM ordens_servico os 
    INNER JOIN clientes c ON os.cliente_id = c.id 
    WHERE os.data_termino BETWEEN ? AND ?
    AND os.status NOT IN ('concluida', 'cancelada')
    ORDER BY os.data_termino ASC
");
$stmt->execute([$data_inicio, $data_fim]);
$entregas = $stmt->fetchAll();

foreach ($entregas as $entrega) {
    $dt = $entrega['data_termino'];
    if (isset($agenda_parte1[$dt])) {
        $agenda_parte1[$dt][] = $entrega;
    } elseif (isset($agenda_parte2[$dt])) {
        $agenda_parte2[$dt][] = $entrega;
    }
}

include '../../includes/header_vendedor.php';
?>
<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main"><div class="vend-page-head"><h1 class="vend-page-title">Panorama Geral da Produção</h1></div><div class="vend-content">

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

<?php if ($contagem_atraso['total'] > 0): ?>
<!-- Seção Compacta de O.S. Atrasadas -->
<div class="card mt-20 card-atrasadas-compacta">
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
    <div class="table-responsive">
        <table class="table-os-atrasadas">
            <thead>
                <tr>
                    <th style="width: 15%;">O.S.</th>
                    <th style="width: 35%;">Cliente</th>
                    <th style="width: 15%;">Atraso</th>
                    <th style="width: 20%;">Previsão</th>
                    <th style="width: 15%;">Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $todas_atrasadas = array_merge(
                    $os_atrasadas['critico'],
                    $os_atrasadas['urgente'],
                    $os_atrasadas['atrasado']
                );
                foreach ($todas_atrasadas as $os): 
                    $nivel = $os['nivel_atraso'];
                    $cor_classe = 'nivel-' . $nivel;
                ?>
                <tr class="row-os-atrasada <?php echo $cor_classe; ?>">
                    <td><strong><?php echo htmlspecialchars($os['numero']); ?></strong></td>
                    <td><?php echo htmlspecialchars(substr($os['razao_social'], 0, 40)); ?></td>
                    <td>
                        <span class="badge-nivel-<?php echo $nivel; ?>">
                            <?php echo getDescricaoAtraso($os['dias_atraso']); ?>
                        </span>
                    </td>
                    <td><?php echo formatDate($os['data_termino']); ?></td>
                    <td>
                        <a href="os_detalhes.php?os_id=<?php echo $os['id']; ?>" class="btn-link-compacto">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<!-- Mensagem de Status - Sem O.S. Atrasadas -->
<div class="card mt-20 card-sucesso-producao">
    <div class="card-header-sucesso">
        <div class="sucesso-content">
            <div class="sucesso-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="sucesso-text">
                <h3>Sem O.S. em atraso</h3>
                <p>Não há ordens de serviço vencidas no momento.</p>
                <small>As O.S. ainda em aberto seguem dentro do prazo de entrega.</small>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>


<!-- Cronograma Parte 1 (1-15 dias) -->
<div class="card mt-20">
    <div class="card-header">
        <h3><i class="fas fa-calendar-day"></i> Cronograma de Entregas (Próximos 15 dias)</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table-agenda">
                <thead>
                    <tr>
                        <?php foreach ($agenda_parte1 as $data => $clientes): ?>
                            <th class="<?php echo (date('N', strtotime($data)) >= 6) ? 'fds' : ''; ?>">
                                <div class="dia-semana">
                                    <?php 
                                    $dias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
                                    echo $dias[date('w', strtotime($data))]; 
                                    ?>
                                </div>
                                <div class="dia-mes"><?php echo date('d/m', strtotime($data)); ?></div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <?php foreach ($agenda_parte1 as $data => $clientes): ?>
                            <td class="<?php echo (date('N', strtotime($data)) >= 6) ? 'fds' : ''; ?>">
                                <?php if (empty($clientes)): ?>
                                    <span class="vazio">-</span>
                                <?php else: ?>
                                    <?php foreach ($clientes as $c): ?>
                                        <div class="cliente-item" title="O.S: <?php echo $c['numero']; ?>">
                                            <a href="os_detalhes.php?os_id=<?php echo $c['id']; ?>">
                                                <?php echo mb_strimwidth($c['razao_social'], 0, 12, ".."); ?>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Cronograma Parte 2 (16-30 dias) -->
<div class="card mt-20">
    <div class="card-header">
        <h3><i class="fas fa-calendar-alt"></i> Cronograma de Entregas (16 a 30 dias)</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table-agenda">
                <thead>
                    <tr>
                        <?php foreach ($agenda_parte2 as $data => $clientes): ?>
                            <th class="<?php echo (date('N', strtotime($data)) >= 6) ? 'fds' : ''; ?>">
                                <div class="dia-semana">
                                    <?php 
                                    $dias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
                                    echo $dias[date('w', strtotime($data))]; 
                                    ?>
                                </div>
                                <div class="dia-mes"><?php echo date('d/m', strtotime($data)); ?></div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <?php foreach ($agenda_parte2 as $data => $clientes): ?>
                            <td class="<?php echo (date('N', strtotime($data)) >= 6) ? 'fds' : ''; ?>">
                                <?php if (empty($clientes)): ?>
                                    <span class="vazio">-</span>
                                <?php else: ?>
                                    <?php foreach ($clientes as $c): ?>
                                        <div class="cliente-item" title="O.S: <?php echo $c['numero']; ?>">
                                            <a href="os_detalhes.php?os_id=<?php echo $c['id']; ?>">
                                                <?php echo mb_strimwidth($c['razao_social'], 0, 12, ".."); ?>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.mb-20 { margin-bottom: 20px; }
.mt-20 { margin-top: 20px; }

.table-agenda {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

.table-agenda th, .table-agenda td {
    border: 1px solid #ddd;
    text-align: center;
    padding: 8px 3px;
    vertical-align: top;
    min-width: 70px;
}

.table-agenda th {
    background-color: #f8f9fa;
}

.table-agenda th.fds, .table-agenda td.fds {
    background-color: #fff5f5;
}

.dia-semana {
    font-size: 0.7rem;
    text-transform: uppercase;
    color: #666;
}

.dia-mes {
    font-size: 0.9rem;
    font-weight: bold;
}

.cliente-item {
    background-color: #e3f2fd;
    border-left: 3px solid #2196f3;
    margin-bottom: 4px;
    padding: 3px;
    font-size: 0.75rem;
    border-radius: 2px;
    word-wrap: break-word;
    text-align: left;
    line-height: 1.1;
}

.cliente-item a {
    text-decoration: none;
    color: #1976d2;
    display: block;
}

.cliente-item:hover {
    background-color: #bbdefb;
}

.vazio {
    color: #eee;
    font-size: 0.8rem;
}

@media (max-width: 768px) {
    .card-body { flex-direction: column; }
    .dashboard-cards { margin-left: 0 !important; margin-top: 20px; width: 100%; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($etapas_data)): ?>
    const ctx = document.getElementById('graficoEtapasOS').getContext('2d');
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($labels_etapas); ?>,
            datasets: [{
                data: <?php echo json_encode($valores_etapas); ?>,
                backgroundColor: <?php echo json_encode($cores_grafico); ?>,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false // Desativado pois agora temos a lista textual detalhada ao lado
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
});
</script>
<!-- Gráfico Superior por Etapas -->
<div class="card mb-20">
    <div class="card-header">
        <h3><i class="fas fa-layer-group"></i> O.S. por Etapa de Produção</h3>
    </div>
    <div class="card-body" style="display: flex; justify-content: center; align-items: center; min-height: 200px; gap: 20px;">
        <?php if (empty($etapas_data)): ?>
            <div class="text-center" style="width: 100%; color: #666;">
                <i class="fas fa-info-circle"></i> Nenhuma O.S. em produção no momento.
            </div>
        <?php else: ?>
            <div style="width: 250px; height: 250px;">
                <canvas id="graficoEtapasOS"></canvas>
            </div>
        <?php endif; ?>
        
        <div class="setores-volume" style="flex: 1; margin-left: 0;">
            <h4 style="margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px; font-size: 14px;">Volume por Setor</h4>
            <ul style="list-style: none; padding: 0;">
                <?php foreach ($etapas_data as $row): 
                    $nome = $labels_nomes[$row['etapa_atual']] ?? ucfirst($row['etapa_atual']);
                    $cor = $cores_etapas[$row['etapa_atual']] ?? '#adb5bd';
                ?>
                    <li style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; font-size: 0.85rem;">
                        <span>
                            <i class="fas fa-square" style="color: <?php echo $cor; ?>; margin-right: 6px;"></i>
                            <span style="display: inline-block; max-width: 120px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo $nome; ?></span>:
                        </span>
                        <strong style="background: #f0f0f0; padding: 2px 6px; border-radius: 8px; min-width: 25px; text-align: center; font-size: 0.8rem;">
                            <?php echo $row['total']; ?>
                        </strong>
                    </li>
                <?php endforeach; ?>
            </ul>
            
            <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #f0f0f0; font-size: 0.9rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                    <span>Total em Produção:</span>
                    <strong><?php echo $total_os_producao; ?></strong>
                </div>
<div style="display: flex; justify-content: space-between;">
                    <span>Vendas no Mês:</span>
                    <strong><?php echo $total_vendas_mes; ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>
    </div>
</div>
</div>

<?php include '../../includes/footer_vendedor.php'; ?>


