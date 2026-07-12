<?php
require_once '../../config/config.php';
require_once '../../includes/crm.php';
requirePermission(['master', 'vendedor', 'gerente']);

$page_title = 'CRM — Contatos';
$db = getDB();
ensureCrmSchema($db);
$usuario = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar') {
        $id = (int)($_POST['id'] ?? 0);
        $nome = sanitize($_POST['nome'] ?? '');
        $cliente_id = (int)($_POST['cliente_id'] ?? 0) ?: null;
        $cargo = sanitize($_POST['cargo'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $telefone = sanitize($_POST['telefone'] ?? '');
        $whatsapp = sanitize($_POST['whatsapp'] ?? '');
        $cidade = sanitize($_POST['cidade'] ?? '');
        $obs = sanitize($_POST['observacoes'] ?? '');

        if ($nome === '') {
            setError('Informe o nome do contato.');
        } else {
            // Anti-duplicação: mesmo e-mail ou mesmo nome+empresa
            $dup = null;
            if ($id === 0) {
                if ($email !== '') {
                    $stmt = $db->prepare("SELECT id, nome FROM crm_contatos WHERE email = ? LIMIT 1");
                    $stmt->execute([$email]);
                    $dup = $stmt->fetch();
                }
                if (!$dup) {
                    $stmt = $db->prepare("SELECT id, nome FROM crm_contatos WHERE LOWER(TRIM(nome)) = LOWER(?) AND (cliente_id <=> ?) LIMIT 1");
                    $stmt->execute([trim($nome), $cliente_id]);
                    $dup = $stmt->fetch();
                }
            }

            if ($dup) {
                setError('Contato já cadastrado: ' . $dup['nome'] . ' (#' . $dup['id'] . ').');
            } elseif ($id > 0) {
                $stmt = $db->prepare("UPDATE crm_contatos SET nome=?, cliente_id=?, cargo=?, email=?, telefone=?, whatsapp=?, cidade=?, observacoes=? WHERE id=?");
                $stmt->execute([$nome, $cliente_id, $cargo ?: null, $email ?: null, $telefone ?: null, $whatsapp ?: null, $cidade ?: null, $obs ?: null, $id]);
                setSuccess('Contato atualizado!');
            } else {
                $stmt = $db->prepare("INSERT INTO crm_contatos (nome, cliente_id, cargo, email, telefone, whatsapp, cidade, observacoes, criado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nome, $cliente_id, $cargo ?: null, $email ?: null, $telefone ?: null, $whatsapp ?: null, $cidade ?: null, $obs ?: null, $usuario['id']]);
                setSuccess('Contato cadastrado!');
            }
        }
        header('Location: contatos.php');
        exit;
    }

    if ($acao === 'excluir' && hasPermission(['master'])) {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM crm_contatos WHERE id = ?")->execute([$id]);
        setSuccess('Contato excluído.');
        header('Location: contatos.php');
        exit;
    }
}

$busca = trim($_GET['busca'] ?? '');
$sql = "
    SELECT ct.*, c.razao_social,
           (SELECT COUNT(*) FROM crm_oportunidades o WHERE o.contato_id = ct.id) AS total_oportunidades
    FROM crm_contatos ct
    LEFT JOIN clientes c ON c.id = ct.cliente_id
    WHERE 1=1
";
$params = [];
if ($busca !== '') {
    $sql .= " AND (ct.nome LIKE ? OR ct.email LIKE ? OR c.razao_social LIKE ? OR ct.telefone LIKE ?)";
    $like = "%$busca%";
    $params = [$like, $like, $like, $like];
}
$sql .= " ORDER BY ct.nome";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$contatos = $stmt->fetchAll();

$clientes = $db->query("SELECT id, razao_social FROM clientes ORDER BY razao_social")->fetchAll();
include '../../includes/header_vendedor.php';
?>

<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-page-head">
            <div>
                <h1 class="vend-page-title">Contatos</h1>
                <p class="vend-page-sub">Pessoas vinculadas às empresas — quem você fala em cada cliente</p>
            </div>
            <div style="display:flex;gap:8px">
                <a href="index.php" class="vbtn-sm"><i class="fas fa-columns"></i> Pipeline</a>
                <button type="button" class="vbtn-sm vbtn-brand" onclick="abrirModalContato()"><i class="fas fa-user-plus"></i> Novo Contato</button>
            </div>
        </div>

        <form method="GET" style="margin-bottom:16px">
            <div style="display:flex;gap:8px;max-width:420px">
                <input type="text" name="busca" class="form-control" placeholder="Buscar por nome, e-mail, empresa ou telefone…" value="<?php echo htmlspecialchars($busca); ?>">
                <button type="submit" class="vbtn-sm"><i class="fas fa-search"></i></button>
            </div>
        </form>

        <div class="vend-table-wrap">
            <table class="vend-table">
                <thead>
                    <tr><th>Nome</th><th>Empresa</th><th>Cargo</th><th>Telefone / WhatsApp</th><th>E-mail</th><th>Oport.</th><th>Ações</th></tr>
                </thead>
                <tbody>
                <?php if (empty($contatos)): ?>
                    <tr><td colspan="7" class="text-center" style="padding:24px;color:#999">Nenhum contato<?php echo $busca ? ' para esta busca' : ' cadastrado ainda'; ?>.</td></tr>
                <?php else: foreach ($contatos as $ct): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($ct['nome']); ?></strong></td>
                        <td><?php echo htmlspecialchars($ct['razao_social'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($ct['cargo'] ?? '—'); ?></td>
                        <td>
                            <?php echo htmlspecialchars($ct['telefone'] ?? ''); ?>
                            <?php if (!empty($ct['whatsapp'])): ?>
                                <a href="https://wa.me/55<?php echo preg_replace('/\D+/', '', $ct['whatsapp']); ?>" target="_blank" class="vbadge vbadge-ok" style="margin-left:4px"><i class="fab fa-whatsapp"></i></a>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($ct['email'] ?? '—'); ?></td>
                        <td><span class="vbadge vbadge-info"><?php echo (int)$ct['total_oportunidades']; ?></span></td>
                        <td>
                            <button type="button" class="vbtn-sm" onclick='abrirModalContato(<?php echo json_encode([
                                'id' => (int)$ct['id'], 'nome' => $ct['nome'], 'cliente_id' => (int)($ct['cliente_id'] ?? 0),
                                'cargo' => $ct['cargo'], 'email' => $ct['email'], 'telefone' => $ct['telefone'],
                                'whatsapp' => $ct['whatsapp'], 'cidade' => $ct['cidade'], 'observacoes' => $ct['observacoes'],
                            ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' title="Editar"><i class="fas fa-pen"></i></button>
                            <?php if (hasPermission(['master'])): ?>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Excluir este contato?')">
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="id" value="<?php echo (int)$ct['id']; ?>">
                                <button type="submit" class="vbtn-sm vbtn-danger" title="Excluir"><i class="fas fa-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal contato -->
<div id="modalContato" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1060;align-items:center;justify-content:center;padding:16px">
    <div style="background:#fff;border-radius:14px;max-width:520px;width:100%;padding:22px;max-height:90vh;overflow:auto">
        <h3 style="margin-bottom:14px" id="modalContatoTitulo"><i class="fas fa-user-plus" style="color:#D85A30"></i> Novo Contato</h3>
        <form method="POST">
            <input type="hidden" name="acao" value="salvar">
            <input type="hidden" name="id" id="ct_id" value="0">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <div class="form-group"><label class="form-label">Nome *</label><input type="text" name="nome" id="ct_nome" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Cargo</label><input type="text" name="cargo" id="ct_cargo" class="form-control" placeholder="Comprador, Chef, Sócio…"></div>
            </div>
            <div class="form-group" style="margin:10px 0">
                <label class="form-label">Empresa</label>
                <select name="cliente_id" id="ct_cliente" class="form-control">
                    <option value="">— Sem empresa —</option>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['razao_social']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <div class="form-group"><label class="form-label">Telefone</label><input type="text" name="telefone" id="ct_tel" class="form-control"></div>
                <div class="form-group"><label class="form-label">WhatsApp</label><input type="text" name="whatsapp" id="ct_whats" class="form-control"></div>
            </div>
            <div style="display:grid;grid-template-columns:1.4fr 1fr;gap:10px;margin-top:10px">
                <div class="form-group"><label class="form-label">E-mail</label><input type="email" name="email" id="ct_email" class="form-control"></div>
                <div class="form-group"><label class="form-label">Cidade</label><input type="text" name="cidade" id="ct_cidade" class="form-control"></div>
            </div>
            <div class="form-group" style="margin:10px 0">
                <label class="form-label">Observações</label>
                <textarea name="observacoes" id="ct_obs" class="form-control" rows="2"></textarea>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px">
                <button type="button" class="vbtn-sm" onclick="document.getElementById('modalContato').style.display='none'">Cancelar</button>
                <button type="submit" class="vbtn-sm vbtn-brand"><i class="fas fa-save"></i> Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalContato(dados) {
    const m = document.getElementById('modalContato');
    document.getElementById('modalContatoTitulo').innerHTML = dados
        ? '<i class="fas fa-user-pen" style="color:#D85A30"></i> Editar Contato'
        : '<i class="fas fa-user-plus" style="color:#D85A30"></i> Novo Contato';
    document.getElementById('ct_id').value = dados ? dados.id : 0;
    document.getElementById('ct_nome').value = dados ? (dados.nome || '') : '';
    document.getElementById('ct_cargo').value = dados ? (dados.cargo || '') : '';
    document.getElementById('ct_cliente').value = dados ? (dados.cliente_id || '') : '';
    document.getElementById('ct_tel').value = dados ? (dados.telefone || '') : '';
    document.getElementById('ct_whats').value = dados ? (dados.whatsapp || '') : '';
    document.getElementById('ct_email').value = dados ? (dados.email || '') : '';
    document.getElementById('ct_cidade').value = dados ? (dados.cidade || '') : '';
    document.getElementById('ct_obs').value = dados ? (dados.observacoes || '') : '';
    m.style.display = 'flex';
}
</script>

<?php include '../../includes/footer_vendedor.php'; ?>
