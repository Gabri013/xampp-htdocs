<?php
require_once '../../config/config.php';
require_once '../../includes/financeiro.php';
requirePermission(['master', 'vendedor']);

$page_title = 'Vendas';

$db = getDB();
ensureFinanceiroSchema($db);
$usuario_logado = getCurrentUser();

// --- LÓGICA DE EXCLUSÃO (PROCESSAMENTO NO TOPO) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'excluir_venda') {
    $id_excluir = $_POST['id'] ?? null;
    $motivo = $_POST['motivo'] ?? '';

    if ($id_excluir && !empty($motivo)) {
        try {
            $db->beginTransaction();

            // 1. Cancelar financeiro automaticamente
            cancelarContasReceberPorVenda($db, $id_excluir, $usuario_logado['id'], $motivo);

            // 2. Cancelar O.S vinculada
            $stmt_os = $db->prepare("UPDATE ordens_servico SET status='cancelada' WHERE venda_id = ?");
            $stmt_os->execute([$id_excluir]);

            // 3. Cancelar venda (não exclui fisicamente)
            $stmt_venda = $db->prepare("UPDATE vendas SET status='cancelada' WHERE id = ?");
            $stmt_venda->execute([$id_excluir]);

            $db->commit();
            setSuccess("Venda cancelada com sucesso. Contas a receber canceladas automaticamente.");
            header("Location: index.php");
            exit;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            setError("Erro ao excluir venda: " . $e->getMessage());
        }
    }
}
// --------------------------------------------------

// Buscar vendas
$busca = $_GET['busca'] ?? '';
$status_filtro = $_GET['status'] ?? '';

$sql = "SELECT v.*, c.razao_social, u.nome as usuario_nome, os.numero AS os_numero
        FROM vendas v 
        INNER JOIN clientes c ON v.cliente_id = c.id 
        INNER JOIN usuarios u ON v.usuario_id = u.id
        LEFT JOIN ordens_servico os ON os.venda_id = v.id
        WHERE 1=1";

$params = [];

if ($usuario_logado['tipo'] === 'vendedor') {
    $sql .= " AND v.usuario_id = ?";
    $params[] = $usuario_logado['id'];
} elseif ($usuario_logado['tipo'] === 'projetista') {
    $sql .= " AND EXISTS (SELECT 1 FROM ordens_servico os WHERE os.venda_id = v.id AND os.status IN ('pendente', 'em_projeto', 'em_revisao', 'proposta'))";
}

if ($busca) {
    $sql .= " AND (v.numero LIKE ? OR c.razao_social LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

if ($status_filtro) {
    $sql .= " AND v.status = ?";
    $params[] = $status_filtro;
}

$sql .= " ORDER BY v.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$vendas = $stmt->fetchAll();

// Handle exports
if(isset($_GET['export_type'])){
    $type = $_GET['export_type'];
    if($type === 'csv'){
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="vendas.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID','Número','Cliente','Data','Valor Total','Status','Vendedor','OS']);
        foreach($vendas as $v){
            fputcsv($output, [
                $v['id'],
                $v['numero'],
                $v['razao_social'],
                $v['data_venda'],
                $v['valor_total'],
                $v['status'],
                $v['usuario_nome'],
                $v['os_numero'] ?? ''
            ]);
        }
        fclose($output);
        exit;
    }
if($type === 'pdf'){
    require_once('../vendor/tcpdf/tcpdf.php');
    $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetMargins(15, 20, 15);
    $pdf->SetAutoPageBreaks(true);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 10, 'Relatório de Vendas - Cozinca Inox', 0, 1, 'C');
    $pdf->Ln(5);
    $html = '<table border="1" cellpadding="4">';
    $html .= '<tr><th>ID</th><th>Número</th><th>Cliente</th><th>Data</th><th>Valor Total</th><th>Status</th><th>Vendedor</th><th>OS</th></tr>';
    foreach($vendas as $v) {
        $html .= '<tr>';
        $html .= '<td>' . $v['id'] . '</td>';
        $html .= '<td>' . $v['numero'] . '</td>';
        $html .= '<td>' . $v['razao_social'] . '</td>';
        $html .= '<td>' . $v['data'] . '</td>';
        $html .= '<td>R$ ' . number_format($v['valor_total'], 2, ',', '.') . '</td>';
        $html .= '<td>' . $v['status'] . '</td>';
        $html .= '<td>' . $v['usuario_nome'] . '</td>';
        $html .= '<td>' . ($v['os_numero'] ?: 'N/A') . '</td>';
        $html .= '</tr>';
    }
    $html .= '</table>';
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('vendas.pdf', 'I');
    exit;
}
}

include '../../includes/header_vendedor.php';
?>

<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">

        <div class="vend-page-head">
            <div>
                <h1 class="vend-page-title">Vendas</h1>
                <p class="vend-page-sub">Gerencie suas vendas e veja status de faturamento</p>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <form method="GET" style="display:flex;gap:8px;align-items:center">
                    <input type="text" name="busca" value="<?php echo htmlspecialchars($busca); ?>" placeholder="Buscar vendas..." class="vbtn-sm" style="width:200px">
                    <button type="submit" class="vbtn-sm"><i class="fas fa-search"></i></button>
                </form>
                <a href="nova_venda.php" class="vbtn-sm" style="border-color:#D85A30;color:#D85A30"><i class="fas fa-plus"></i> Nova Venda</a>
            </div>
        </div>

        <div class="vend-table-wrap" style="background:#fff;border:1px solid #e9ecef;border-radius:12px;overflow:hidden">
            <table class="vend-table">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Cliente</th>
                        <th>Data</th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th>O.S</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vendas)): ?>
                        <tr><td colspan="7" class="text-center" style="padding:40px;color:#888">Nenhuma venda encontrada</td></tr>
                    <?php else: foreach ($vendas as $venda): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($venda['numero']); ?></strong></td>
                            <td><?php echo htmlspecialchars($venda['razao_social']); ?></td>
                            <td><?php echo formatDate($venda['data_venda']); ?></td>
                            <td><strong><?php echo formatMoney($venda['valor_total']); ?></strong></td>
                            <td>
                                <?php
                                $cor = ['em_andamento' => '#f39c12', 'concluida' => '#27ae60', 'cancelada' => '#e74c3c'][$venda['status']] ?? '#95a5a6';
                                ?>
                                <span class="vbadge" style="background-color:<?php echo $cor; ?>;color:#fff"><?php echo ucfirst(str_replace('_', ' ', $venda['status'])); ?></span>
                            </td>
                            <td>
                                <?php if (!empty($venda['os_numero'])): ?>
                                    <a href="../os/vendedor.php?os=<?php echo urlencode($venda['os_numero']); ?>" class="vbtn-sm" style="border-color:#1565C0;color:#1565C0"><?php echo htmlspecialchars($venda['os_numero']); ?></a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="detalhes_venda.php?id=<?php echo $venda['id']; ?>" class="vbtn-sm"><i class="fas fa-eye"></i></a>
                                <?php if ($venda['status'] !== 'cancelada'): ?>
                                    <a href="detalhes_venda.php?id=<?php echo $venda['id']; ?>&resolver_financeiro=1" class="vbtn-sm btn-success" title="Resolver financeiro"><i class="fas fa-wallet"></i></a>
                                <?php endif; ?>
                                <a href="editar_venda.php?id=<?php echo $venda['id']; ?>" class="vbtn-sm btn-primary"><i class="fas fa-edit"></i></a>
                                <button type="button" class="vbtn-sm btn-danger btn-excluir" data-id="<?php echo $venda['id']; ?>" data-numero="<?php echo $venda['numero']; ?>"><i class="fas fa-ban"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="modalExcluir" class="modal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
    <div class="modal-content" style="background:#white; margin:10% auto; padding:20px; max-width:500px; border-radius:8px; background-color:#fff;">
        <div class="modal-header">
            <h3>Excluir/Cancelar Venda <span id="excluir_numero"></span></h3>
        </div>
        <form method="POST" id="formExcluir">
            <div class="modal-body">
                <div style="padding:14px 16px; border-radius:10px; background:#fee2e2; color:#991b1b; border:1px solid #fecaca; margin-bottom:16px;">
                    <strong>Atenção:</strong> ao excluir/cancelar esta venda, a ordem de serviço vinculada também será cancelada, junto com as contas a receber pendentes.
                </div>
                <p style="margin-bottom:14px; color:#475569;">
                    Confirme essa ação apenas se tiver certeza de que a venda e a O.S não devem mais seguir no sistema.
                </p>
                <div class="form-group">
                    <label>Motivo do Cancelamento *</label>
                    <textarea name="motivo" id="motivo_exclusao" class="form-control" rows="4" required></textarea>
                </div>
            </div>
            <div class="modal-footer" style="text-align:right; margin-top:20px;">
                <input type="hidden" name="acao" value="excluir_venda">
                <input type="hidden" name="id" id="excluir_id">
                <button type="button" class="vbtn-sm btn-secondary" onclick="fecharModal()">Cancelar</button>
                <button type="submit" class="vbtn-sm">Confirmar Exclusão da Venda</button>
            </div>
        </form>
    </div>
</div>

<script>
function fecharModal() {
    document.getElementById('modalExcluir').style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('modalExcluir');
    
    // Abrir Modal
    document.querySelectorAll('.btn-excluir').forEach(btn => {
        btn.onclick = function() {
            document.getElementById('excluir_id').value = this.dataset.id;
            document.getElementById('excluir_numero').textContent = this.dataset.numero;
            document.getElementById('motivo_exclusao').value = '';
            modal.style.display = 'block';
        };
    });

    // Seleção em lote
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.venda-checkbox');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = this.checked);
            const btn = document.getElementById('btnImprimirLote');
            btn.style.display = this.checked ? 'inline-block' : 'none';
        });
    }

    // Fechar modal ao clicar fora
    window.onclick = function(event) {
        if (event.target == modal) fecharModal();
    }
});
</script>

<?php include '../../includes/footer_vendedor.php'; ?>

