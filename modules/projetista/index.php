<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/engenharia.php';

$usuario = getCurrentUser();
$tipo_usuario = $usuario['tipo'];

$GLOBALS['modulo_tipo'] = 'projetista';

requirePermission(['master', 'projetista', 'gerente', 'producao']);

$db = getDB();
ensureOrdensServicoIndependentesSchema($db);
ensureEngenhariaSchema($db);

$setores_permitidos = [];
$setor_permissoes = [
    'corte' => ['master', 'gerente', 'producao', 'corte', 'projetista'],
    'dobra' => ['master', 'gerente', 'producao', 'dobra', 'projetista'],
    'solda' => ['master', 'gerente', 'producao', 'solda', 'projetista'],
    'refrigeracao' => ['master', 'gerente', 'producao', 'refrigeracao', 'projetista'],
    'acabamento' => ['master', 'gerente', 'producao', 'acabamento', 'projetista'],
    'montagem' => ['master', 'gerente', 'producao', 'montagem', 'projetista'],
];

foreach ($setor_permissoes as $setor => $tipos) {
    if (in_array($tipo_usuario, $tipos)) {
        $setores_permitidos[] = $setor;
    }
}

$stmt = $db->query("
    SELECT os.*, c.razao_social,
           COALESCE(v.numero, 'Independente') as venda_numero,
           COALESCE(u.nome, '-') as vendedor_nome,
           COALESCE(v.observacoes, '') as observacoes
    FROM ordens_servico os
    INNER JOIN clientes c ON os.cliente_id = c.id
    LEFT JOIN vendas v ON os.venda_id = v.id
    LEFT JOIN usuarios u ON v.usuario_id = u.id
    WHERE os.status IN ('pendente', 'em_projeto', 'em_revisao', 'proposta')
    ORDER BY os.data_inicio ASC
");
$ordens = $stmt->fetchAll();
$ordens_aguardando_projeto = array_values(array_filter($ordens, fn($o) => in_array($o['status'] ?? '', ['pendente', 'em_projeto', 'em_revisao', 'proposta'], true)));

$itens_pendentes = [];
if (!empty($ordens)) {
    $ids = array_map('intval', array_column($ordens, 'id'));
    if (!empty($ids)) {
        // Buscar itens de OS independentes (os_itens)
        $itensOs = $db->query("
            SELECT osi.id, osi.os_id, osi.produto_id, osi.descricao_manual, osi.quantidade, p.codigo, p.nome
            FROM os_itens osi
            LEFT JOIN produtos p ON osi.produto_id = p.id
            WHERE osi.os_id IN (" . implode(',', $ids) . ")
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        // Buscar itens de OS vinculadas a vendas (vendas_itens)
        $itensVenda = $db->query("
            SELECT vi.id, os.id as os_id, vi.produto_id, vi.descricao_manual, vi.quantidade, p.codigo, p.nome
            FROM vendas_itens vi
            INNER JOIN ordens_servico os ON vi.venda_id = os.venda_id
            LEFT JOIN produtos p ON vi.produto_id = p.id
            WHERE os.id IN (" . implode(',', $ids) . ")
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        $itens_pendentes = array_merge($itensOs, $itensVenda);
    }
}

include '../../includes/header_vendedor.php';
?>

<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-page-head">
            <div><h1 class="vend-page-title">Engenharia - Setor de Projetos</h1></div>
            <div>Total: <?= count($ordens_aguardando_projeto) ?> OS | Itens: <?= count($itens_pendentes) ?></div>
        </div>

        <?php if (!empty($ordens)): ?>
        <div class="vend-table-wrap">
            <table class="vend-table">
                <thead>
                    <tr>
                        <th>O.S.</th><th>Cliente</th><th>Venda</th><th>Status</th><th>Prazo</th><th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ordens as $os): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($os['numero']) ?></strong></td>
                            <td><?= htmlspecialchars($os['razao_social']) ?></td>
                            <td><?= htmlspecialchars($os['venda_numero']) ?></td>
                            <td>
                                <span class="vbadge vbadge-<?= $os['status']=='proposta'?'warn':($os['status']=='em_revisao'?'info':'prod') ?>">
                                    <?= $os['status']=='proposta'?'Proposta':ucfirst(str_replace('_', ' ', $os['status'])) ?>
                                </span>
                            </td>
                            <td><?= !empty($os['data_termino']) ? date('d/m/Y', strtotime($os['data_termino'])) : '—' ?></td>
                            <td>
                                <a href="<?= SITE_URL ?>/modules/os/os_detalhes.php?os_id=<?= $os['id'] ?>" class="vbtn-sm"><i class="fas fa-eye"></i></a>
                                <?php if (!empty($setores_permitidos)): ?>
                                <button class="vbtn-sm btn-success" onclick="abrirModalSetor(<?= $os['id'] ?>, <?= htmlspecialchars(json_encode($setores_permitidos)) ?>)"><i class="fas fa-share"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="vend-empty">Nenhuma O.S. pendente de projeto.</div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de Encaminhamento -->
<div id="modalSetor" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3>Encaminhar O.S.</h3>
            <button class="close" onclick="fecharModalSetor()">&times;</button>
        </div>
        <div class="modal-body">
            <p style="margin-bottom: 12px;">Selecione o setor de destino:</p>
            <div id="opcoes-setor" style="display: grid; gap: 8px;"></div>
        </div>
    </div>
</div>

<script>
function abrirModalSetor(osId, setores) {
    const container = document.getElementById('opcoes-setor');
    container.innerHTML = '';
    setores.forEach(setor => {
        const btn = document.createElement('button');
        btn.className = 'vbtn-sm btn-primary';
        btn.style = 'width: 100%; justify-content: center;';
        btn.innerHTML = '<i class="fas fa-arrow-right"></i> ' + ucfirst(setor);
        btn.onclick = () => confirmarEncaminhar(osId, setor);
        container.appendChild(btn);
    });
    document.getElementById('modalSetor').classList.add('show');
}

function fecharModalSetor() {
    document.getElementById('modalSetor').classList.remove('show');
}

function ucfirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function confirmarEncaminhar(osId, setor) {
    if (confirm('Encaminhar O.S. para ' + ucfirst(setor) + '?')) {
        fetch('desmembrar_os.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'os_id=' + osId + '&setor=' + setor
        }).then(r => r.json()).then(d => {
            if (d.success) location.reload(); else alert(d.error || 'Erro ao encaminhar.');
        });
    }
}
</script>

<?php include '../../includes/footer_vendedor.php'; ?>