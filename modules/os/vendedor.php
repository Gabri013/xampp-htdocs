<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
requirePermission(['master', 'vendedor', 'projetista']);
require_once '../../includes/components/kanban.component.php';

$page_title = 'Ordens de Serviço — Vendedor';

$db = getDB();
$usuario = getCurrentUser();
ensureOrdensServicoIndependentesSchema($db);

// Filtros
$status_filtro = $_GET['status'] ?? 'todas';
$busca         = trim($_GET['busca'] ?? '');

$status_validos = ['todas', 'em_producao', 'em_revisao', 'aguardando', 'pendente', 'concluida'];
if (!in_array($status_filtro, $status_validos)) $status_filtro = 'todas';

// Query base
$where_parts = [];
$params = [];

// Filtro de status
if ($status_filtro !== 'todas') {
    $where_parts[] = 'os.status = ?';
    $params[] = $status_filtro;
}

if ($busca !== '') {
    $where_parts[] = '(c.razao_social LIKE ? OR os.numero LIKE ?)';
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

// Usuário master/gerente/projetista vê todas, vendedor vê só as suas
$usuarioCondition = '';
if (!hasPermission(['master', 'gerente', 'projetista'])) {
    $usuarioCondition = 'AND (v.usuario_id = ? OR os.venda_id IS NULL)';
    $params[] = $usuario['id'];
}

try {
    $whereClause = '';
    if (!empty($where_parts)) {
        $whereClause = ' WHERE ' . implode(' AND ', $where_parts) . ($usuarioCondition ? ' ' . $usuarioCondition : '');
    } elseif ($usuarioCondition !== '') {
        $whereClause = ' WHERE 1=1 ' . $usuarioCondition;
    }
    $stmt = $db->prepare("
        SELECT os.*, c.razao_social,
               COALESCE(v.numero, 'Independente') AS venda_numero,
               COALESCE(u.nome, '—')              AS vendedor_nome
        FROM ordens_servico os
        INNER JOIN clientes c ON os.cliente_id = c.id
        LEFT JOIN vendas v ON os.venda_id = v.id
        LEFT JOIN usuarios u ON v.usuario_id = u.id
        $whereClause
        ORDER BY os.id DESC
        LIMIT 1000
    ");
    $stmt->execute($params);
    $ordens = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Erro SQL vendedor.php: ' . $e->getMessage());
    $ordens = [];
}

// DEBUG: mostra total de OS no sistema
if (empty($ordens)) {
    $stmt = $db->query("SELECT COUNT(*) FROM ordens_servico");
    error_log('Total OS no sistema: ' . $stmt->fetchColumn());
}

// Contadores por status (para pills) - apenas OS do usuário se não for master/gerente/projetista
$status_counts = [];
try {
    if (hasPermission(['master', 'gerente', 'projetista'])) {
        $stmt_cnt = $db->query("
            SELECT os.status, COUNT(*) as total
            FROM ordens_servico os
            GROUP BY os.status
        ");
    } else {
        $stmt_cnt = $db->prepare("
            SELECT os.status, COUNT(*) as total
            FROM ordens_servico os
            WHERE os.venda_id IS NULL OR EXISTS (SELECT 1 FROM vendas v WHERE v.id = os.venda_id AND v.usuario_id = ?)
            GROUP BY os.status
        ");
        $stmt_cnt->execute([$usuario['id']]);
    }
    $status_counts_raw = $stmt_cnt->fetchAll();
    $status_counts = ['todas' => 0];
    foreach ($status_counts_raw as $row) {
        $status_counts[$row['status']] = (int)$row['total'];
        $status_counts['todas'] += (int)$row['total'];
    }
} catch (Exception $e) { $status_counts = []; }

// Notificações (badge sidebar)
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND lida = 0");
    $stmt->execute([$usuario['id']]);
    $notif_count = (int) $stmt->fetchColumn();
} catch (Exception $e) { $notif_count = 0; }

// Orçamentos abertos
$orcamentos_abertos = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) FROM orcamentos WHERE (status = 'aberto' OR status IS NULL) AND (validade IS NULL OR validade >= CURDATE())");
    $orcamentos_abertos = (int) $stmt->fetchColumn();
} catch (Exception $e) {}

include '../../includes/header_vendedor.php';
?>
<style>
/** Estilos específicos da página de O.S. **/
.vend-view-toggle{display:inline-flex;gap:2px;background:#f5f5f5;border-radius:10px;padding:3px;margin-left:auto}
.vend-view-toggle button{padding:5px 12px;border:none;background:transparent;font-size:12px;color:#666;border-radius:8px;cursor:pointer}
.vend-view-toggle button.active{background:#fff;color:#1a1a1a;box-shadow:0 1px 3px rgba(0,0,0,.08);font-weight:600}
.vend-filter-bar{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px;align-items:center}
.vend-filter-pill{padding:5px 14px;border-radius:999px;font-size:12px;font-weight:500;border:1px solid #e9ecef;background:#fff;color:#555;text-decoration:none;transition:all .15s;cursor:pointer}
.vend-filter-pill:hover{border-color:#D85A30;color:#D85A30;text-decoration:none}
.vend-filter-pill.active{background:#D85A30;border-color:#D85A30;color:#fff}
.vend-search{display:flex;gap:8px}
.vend-search input{padding:6px 12px;border:1px solid #e9ecef;border-radius:8px;font-size:13px;width:200px}
.vend-search input:focus{outline:none;border-color:#D85A30}
.vend-table-wrap{background:#fff;border:1px solid #e9ecef;border-radius:12px;overflow:hidden}
.vend-table{width:100%;border-collapse:collapse;font-size:13px}
.vend-table thead th{background:#f9f9f9;padding:10px 14px;text-align:left;font-weight:600;font-size:12px;color:#666;border-bottom:1px solid #e9ecef;white-space:nowrap}
.vend-table tbody tr{border-bottom:1px solid #f5f5f5;transition:background .1s}
.vend-table tbody tr:last-child{border-bottom:none}
.vend-table tbody tr:hover{background:#fafafa}
.vend-table td{padding:11px 14px;vertical-align:middle}
.td-num{font-weight:600;color:#D85A30;font-size:12px}
.td-client{font-weight:500;color:#1a1a1a}
.td-sub{font-size:11px;color:#aaa;margin-top:2px}
.td-prazo-ok{color:#888}
.td-prazo-close{color:#e65100;font-weight:600}
.td-prazo-over{color:#b71c1c;font-weight:600}
.prio-alta{color:#b71c1c;font-weight:700}
.prio-media{color:#e65100;font-weight:600}
.prio-baixa{color:#2e7d32}
.vend-empty{padding:40px;text-align:center;color:#bbb;font-size:13px}
.vbtn-primary{background:#D85A30;color:#fff;border-color:#D85A30}
.vbtn-primary:hover{background:#c04e28;color:#fff}
</style>
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/kanban.css">

<div class="vend-layout">
    <?php $GLOBALS['modulo_tipo'] = 'vendedor'; $current_module = 'os'; include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">

<div class="vend-page-head">
       <div>
         <h1 class="vend-page-title">Ordens de Serviço</h1>
       </div>
       <div class="vend-view-toggle">
         <button type="button" id="viewTable" class="active"><i class="fas fa-table"></i> Tabela</button>
         <button type="button" id="viewKanban"><i class="fas fa-columns"></i> Kanban</button>
       </div>
     </div>

    <!-- Filtros status + busca -->
    <div class="vend-filter-bar">
      <?php
      $labels_status = [
          'todas'       => 'Todas',
          'em_producao' => 'Em produção',
          'em_revisao'  => 'Em revisão',
          'aguardando'  => 'Aguardando',
          'concluida'   => 'Concluídas',
      ];
      foreach ($labels_status as $k => $lbl):
          $cnt = $status_counts[$k] ?? 0;
      ?>
        <a href="?status=<?php echo $k; ?>&busca=<?php echo urlencode($busca); ?>"
           class="vend-filter-pill <?php echo $status_filtro === $k ? 'active' : ''; ?>">
          <?php echo $lbl; ?><?php if ($cnt > 0): ?> <span style="opacity:.7">(<?php echo $cnt; ?>)</span><?php endif; ?>
        </a>
      <?php endforeach; ?>

      <form class="vend-search" method="get">
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filtro); ?>">
        <input type="text" name="busca" value="<?php echo htmlspecialchars($busca); ?>" placeholder="Buscar cliente ou nº...">
        <button type="submit" class="vbtn-sm"><i class="fas fa-search"></i></button>
      </form>
    </div>

    <!-- Tabela -->
<div class="vend-table-wrap" id="tableView">
       <?php if (empty($ordens)): ?>
         <div class="vend-empty">Nenhuma ordem de serviço encontrada.</div>
       <?php else: ?>
       <table class="vend-table">
        <thead>
          <tr>
            <th>Número</th>
            <th>Cliente</th>
            <th>Venda</th>
            <th>Prazo</th>
            <th>Prioridade</th>
            <th>Status</th>
            <th>Vendedor</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($ordens as $os):
            // Prazo
            $prazo_class = 'td-prazo-ok';
            $prazo_label = !empty($os['data_termino']) ? formatDate($os['data_termino']) : '—';
            if (!empty($os['data_termino'])) {
                $hoje  = new DateTime();
                $prazo = new DateTime($os['data_termino']);
                $diff  = (int) $hoje->diff($prazo)->days;
                if ($prazo < $hoje)   { $prazo_class = 'td-prazo-over'; $prazo_label .= ' ⚠'; }
                elseif ($diff <= 1)   { $prazo_class = 'td-prazo-close'; }
            }
            // Prioridade
            $prioridade = $os['prioridade'] ?? '';
            if (in_array($prioridade, ['alta', 'vermelho', 'red'])) {
                $prio_class = 'prio-alta';
                $prio_label = 'Alta';
            } elseif (in_array($prioridade, ['media', 'amarelo', 'yellow'])) {
                $prio_class = 'prio-media';
                $prio_label = 'Média';
            } else {
                $prio_class = 'prio-baixa';
                $prio_label = 'Baixa';
            }
            // Status badge
            $status = $os['status'] ?? '';
            $statusMap = [
                'em_producao' => ['vbadge-prod', 'Em produção'],
                'em_revisao'  => ['vbadge-rev',  'Em revisão'],
                'aguardando'  => ['vbadge-info',  'Aguardando'],
                'concluida'   => ['vbadge-ok',    'Concluída'],
            ];
            $s = $statusMap[$status] ?? ['vbadge-warn', ucfirst($status)];
          ?>
          <tr>
            <td class="td-num"><?php echo htmlspecialchars($os['numero']); ?></td>
            <td>
              <div class="td-client"><?php echo htmlspecialchars($os['razao_social']); ?></div>
            </td>
            <td style="font-size:12px;color:#888"><?php echo htmlspecialchars($os['venda_numero']); ?></td>
            <td class="<?php echo $prazo_class; ?>"><?php echo $prazo_label; ?></td>
            <td class="<?php echo $prio_class; ?>"><?php echo $prio_label; ?></td>
            <td><span class="vbadge <?php echo $s[0]; ?>"><?php echo $s[1]; ?></span></td>
            <td style="font-size:12px;color:#888"><?php echo htmlspecialchars($os['vendedor_nome']); ?></td>
            <td>
              <a href="os_detalhes.php?os_id=<?php echo $os['id']; ?>" class="vbtn-sm"><i class="fas fa-eye"></i></a>
              <?php if (hasPermission(['master', 'gerente']) && $os['status'] === 'em_revisao'): ?>
                <a href="gerente.php" class="vbtn-sm" style="color:#2e7d32;border-color:#c8e6c9"><i class="fas fa-check"></i> Liberar</a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
</table>
       <?php endif; ?>
       </div>
       
       <!-- Kanban View -->
       <div id="kanbanView" style="display:none">
       <?php
       $kanbanColumns = [
           'aguardando' => ['label' => 'Aguardando'],
           'em_producao' => ['label' => 'Em Produção'],
           'em_revisao' => ['label' => 'Em Revisão'],
           'concluida' => ['label' => 'Concluída'],
       ];
       $kanbanItems = array_map(function($os) {
           return [
               'id' => $os['id'],
               'status' => $os['status'],
               'titulo' => $os['numero'],
               'subtitulo' => $os['razao_social'],
               'valor' => isset($os['valor_total']) ? formatMoney($os['valor_total']) : '',
           ];
       }, $ordens);
       echo renderKanban($kanbanColumns, $kanbanItems);
       ?>
       </div>
       </div>
     </div>
     
     <script>
     document.getElementById('viewTable')?.addEventListener('click', () => {
         document.getElementById('viewTable').classList.add('active');
         document.getElementById('viewKanban').classList.remove('active');
         document.getElementById('tableView').style.display = 'block';
         document.getElementById('kanbanView').style.display = 'none';
     });
     document.getElementById('viewKanban')?.addEventListener('click', () => {
         document.getElementById('viewKanban').classList.add('active');
         document.getElementById('viewTable').classList.remove('active');
         document.getElementById('tableView').style.display = 'none';
         document.getElementById('kanbanView').style.display = 'block';
     });
     </script>

<?php include '../../includes/footer_vendedor.php'; ?>

