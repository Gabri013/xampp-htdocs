<?php
require_once '../../config/config.php';
requirePermission(['master', 'vendedor']);

$db = getDB();
$usuario = getCurrentUser();

// Buscar orçamentos
$busca = $_GET['busca'] ?? '';
$sql = "SELECT o.*, c.razao_social as cliente_nome, v.id as venda_id, v.numero as venda_numero
        FROM orcamentos o
        LEFT JOIN clientes c ON o.cliente_id = c.id
        LEFT JOIN vendas v ON v.orcamento_id = o.id
        WHERE 1=1";
$params = [];

if ($usuario['tipo'] === 'vendedor') {
    $sql .= " AND o.usuario_id = ?";
    $params[] = $usuario['id'];
}

if ($busca) {
    $sql .= " AND (o.numero LIKE ? OR c.razao_social LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

$sql .= " ORDER BY o.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$orcamentos = $stmt->fetchAll();

$page_title = 'Orçamentos';
include '../../includes/header_vendedor.php';
?>

<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-page-head">
            <div><h1 class="vend-page-title">Orçamentos</h1></div>
            <a href="criar_orcamento.php" class="vbtn-sm vbtn-brand"><i class="fas fa-plus"></i> Novo Orçamento</a>
        </div>
        
        <div class="vend-alert warning">
            <i class="fas fa-info-circle"></i>
            <div>
                Orçamentos aprovados podem ser convertidos em vendas através do botão "Converter em Venda".
            </div>
        </div>
        
        <form method="GET" style="margin:20px 0">
            <div style="display:flex;gap:8px;max-width:400px">
                <input type="text" name="busca" class="form-control" placeholder="Buscar orçamentos..." value="<?php echo htmlspecialchars($busca); ?>">
                <button type="submit" class="vbtn-sm"><i class="fas fa-search"></i></button>
            </div>
        </form>
        
        <div class="vend-table-wrap">
            <table class="vend-table">
                <thead>
                    <tr>
                        <th>ID</th><th>Número</th><th>Cliente</th><th>Data</th><th>Valor</th><th>Status</th><th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orcamentos)): ?>
                        <tr><td colspan="7" class="text-center">Nenhum orçamento encontrado.</td></tr>
                    <?php else: foreach ($orcamentos as $orc):
                        $valor = $orc['valor_total'] ?? 0;
                        $status = $orc['status'] ?? 'pendente';
                        $statusClass = $status === 'pendente' ? 'vbadge-ok' : ($status === 'aprovado' ? 'vbadge-warn' : 'vbadge-info');
                    ?>
                        <tr>
                            <td><?php echo $orc['id']; ?></td>
                            <td><?php echo htmlspecialchars($orc['numero'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($orc['cliente_nome'] ?? '-'); ?></td>
                            <td><?php echo formatDate($orc['data_orcamento'] ?? $orc['created_at'] ?? null); ?></td>
                            <td><?php echo formatMoney($valor); ?></td>
                            <td><span class="vbadge <?php echo $statusClass; ?>"><?php echo htmlspecialchars(ucfirst($status)); ?></span></td>
                            <td>
                                <?php if (empty($orc['venda_id']) && in_array($status, ['pendente', 'aprovado'], true)): ?>
                                    <a href="transformar_em_venda.php?id=<?php echo $orc['id']; ?>" class="vbtn-sm btn-success" onclick="return confirm('Converter este orçamento em venda?');">
                                        <i class="fas fa-shopping-cart"></i> Converter em Venda
                                    </a>
                                <?php elseif (!empty($orc['venda_id'])): ?>
                                    <span class="vbtn-sm btn-secondary" style="cursor:default"><i class="fas fa-check"></i> Convertido</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer_vendedor.php'; ?>