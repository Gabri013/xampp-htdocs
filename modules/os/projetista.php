<?php
require_once '../../config/config.php';
require_once '../../includes/engenharia.php';
requirePermission(['master','projetista']);

$page_title = 'Ordens de Serviço - Projetista';

function getClassePrazoProjetista(?string $dataTermino): string {
    if (empty($dataTermino)) return '';
    try {
        $hoje = new DateTimeImmutable(date('Y-m-d'));
        $fim  = new DateTimeImmutable((new DateTimeImmutable($dataTermino))->format('Y-m-d'));
        $dias = (int)$hoje->diff($fim)->format('%r%a');
        if ($dias <= 10) return 'projetista-prazo-vermelho';
        if ($dias <= 13) return 'projetista-prazo-amarelo';
    } catch (Throwable $e) {}
    return '';
}

$db = getDB();
ensureOrdensServicoIndependentesSchema($db);

$stmt = $db->query("
    SELECT os.*, c.razao_social,
           COALESCE(v.numero,'Independente') as venda_numero,
           COALESCE(u.nome,'-') as vendedor_nome,
           COALESCE(v.observacoes,'') as observacoes,
           recall.recall_em, recall.justificativa_recall,
           CASE WHEN os.status='em_revisao' AND recall.os_id IS NOT NULL THEN 1 ELSE 0 END as possui_recall
    FROM ordens_servico os
    INNER JOIN clientes c ON os.cliente_id=c.id
    LEFT JOIN vendas v ON os.venda_id=v.id
    LEFT JOIN usuarios u ON v.usuario_id=u.id
    LEFT JOIN (
        SELECT lr.os_id, lr.created_at as recall_em, lr.justificativa as justificativa_recall
        FROM logs_retorno_etapa lr
        INNER JOIN (SELECT MAX(id) as ultimo_id FROM logs_retorno_etapa GROUP BY os_id) ult ON ult.ultimo_id=lr.id
    ) recall ON recall.os_id=os.id
    WHERE os.status IN ('pendente','em_projeto','em_revisao','em_producao')
    ORDER BY
        CASE os.status WHEN 'em_revisao' THEN 1 WHEN 'pendente' THEN 2 WHEN 'em_projeto' THEN 3 WHEN 'em_producao' THEN 4 ELSE 5 END,
        CASE os.prioridade WHEN 'vermelho' THEN 1 WHEN 'amarelo' THEN 2 WHEN 'verde' THEN 3 END,
        os.data_inicio ASC
");
$ordens = $stmt->fetchAll();
$ordens_aguardando_projeto = array_values(array_filter($ordens, fn($o) => in_array($o['status']??'', ['pendente','em_projeto','em_revisao'], true)));
$ordens_em_execucao        = array_values(array_filter($ordens, fn($o) => ($o['status']??'')==='em_producao'));
$ordens_retorno_projetista = array_values(array_filter($ordens_aguardando_projeto, fn($o) => !empty($o['possui_recall']) && ($o['status']??'')==='em_revisao'));

include '../../includes/header_vendedor.php';
$GLOBALS['modulo_tipo'] = 'projetista';
?>

<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-page-head">
            <div><h1 class="vend-page-title">Painel do Projetista</h1></div>
            <div>Aguardando: <?php echo count($ordens_aguardando_projeto); ?> | Em Execução: <?php echo count($ordens_em_execucao); ?></div>
        </div>
        
        <?php if (!empty($ordens_retorno_projetista)): ?>
        <div class="vend-alert warning">
            <i class="fas fa-exclamation-triangle"></i>
            <div><strong><?php echo count($ordens_retorno_projetista); ?> O.S. devolvidas</strong> necessitam revisão.</div>
        </div>
        <?php endif; ?>
        
        <div class="vend-metrics" style="margin-bottom:24px">
            <div class="vend-metric"><div class="vend-metric-label">Total O.S.</div><div class="vend-metric-val"><?php echo count($ordens); ?></div></div>
            <div class="vend-metric"><div class="vend-metric-label">Aguardando Projeto</div><div class="vend-metric-val"><?php echo count($ordens_aguardando_projeto); ?></div></div>
            <div class="vend-metric"><div class="vend-metric-label">Retorno</div><div class="vend-metric-val"><?php echo count($ordens_retorno_projetista); ?></div></div>
            <div class="vend-metric"><div class="vend-metric-label">Em Execução</div><div class="vend-metric-val"><?php echo count($ordens_em_execucao); ?></div></div>
        </div>
        
        <div class="vend-table-wrap">
            <table class="vend-table">
                <thead>
                    <tr>
                        <th>O.S.</th><th>Cliente</th><th>Venda</th><th>Prazo</th><th>Status</th><th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ordens_aguardando_projeto)): ?>
                        <tr><td colspan="6" class="text-center">Nenhuma O.S. aguardando projeto.</td></tr>
                    <?php else: foreach ($ordens_aguardando_projeto as $os): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($os['numero']); ?></strong></td>
                            <td><?php echo htmlspecialchars($os['razao_social']); ?></td>
                            <td><?php echo htmlspecialchars($os['venda_numero']); ?></td>
                            <td><?php echo !empty($os['data_termino']) ? date('d/m/Y', strtotime($os['data_termino'])) : '—'; ?></td>
                            <td><span class="vbadge vbadge-<?php echo $os['status']=='em_revisao'?'warn':($os['possui_recall']?'info':'prod');?>">
                                <?php echo $os['possui_recall']?'Retorno':ucfirst(str_replace('_',' ',$os['status']));?>
                            </span></td>
                            <td><a href="os_detalhes.php?os_id=<?php echo $os['id']; ?>" class="vbtn-sm" target="_blank"><i class="fas fa-eye"></i> Abrir O.S.</a></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer_vendedor.php'; ?>
