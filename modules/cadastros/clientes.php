<?php
require_once '../../config/config.php';
requirePermission(['master', 'vendedor']);

$page_title = 'Cadastro de Clientes';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    if ($acao === 'salvar') {
        $id = $_POST['id'] ?? null;
        $razao_social = sanitize($_POST['razao_social']);
        $nome_fantasia = sanitize($_POST['nome_fantasia']);
        $cnpj_cpf = sanitize($_POST['cnpj_cpf']);
        $inscricao_estadual = sanitize($_POST['inscricao_estadual']);
        $endereco = sanitize($_POST['endereco']);
        $cidade = sanitize($_POST['cidade']);
        $estado = sanitize($_POST['estado']);
        $cep = sanitize($_POST['cep']);
        $telefone = sanitize($_POST['telefone']);
        $email = sanitize($_POST['email']);
        $observacoes = sanitize($_POST['observacoes']);
        
        try {
            $db = getDB();
            
            if ($id) {
                $stmt = $db->prepare("UPDATE clientes SET razao_social=?, nome_fantasia=?, cnpj_cpf=?, inscricao_estadual=?, endereco=?, cidade=?, estado=?, cep=?, telefone=?, email=?, observacoes=? WHERE id=?");
                $stmt->execute([$razao_social, $nome_fantasia, $cnpj_cpf, $inscricao_estadual, $endereco, $cidade, $estado, $cep, $telefone, $email, $observacoes, $id]);
                setSuccess('Cliente atualizado com sucesso!');
            } else {
                $stmt = $db->prepare("INSERT INTO clientes (razao_social, nome_fantasia, cnpj_cpf, inscricao_estadual, endereco, cidade, estado, cep, telefone, email, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$razao_social, $nome_fantasia, $cnpj_cpf, $inscricao_estadual, $endereco, $cidade, $estado, $cep, $telefone, $email, $observacoes]);
                setSuccess('Cliente cadastrado com sucesso!');
            }
            
            header('Location: clientes.php');
            exit;
        } catch (Exception $e) {
            setError('Erro ao salvar cliente: ' . $e->getMessage());
        }
    } elseif ($acao === 'excluir') {
        $id = $_POST['id'];
        try {
            $db = getDB();
            $stmt = $db->prepare("DELETE FROM clientes WHERE id = ?");
            $stmt->execute([$id]);
            setSuccess('Cliente excluído com sucesso!');
            header('Location: clientes.php');
            exit;
        } catch (Exception $e) {
            setError('Erro ao excluir cliente. Pode haver registros vinculados.');
        }
    }
}

// Buscar clientes
$busca = $_GET['busca'] ?? '';
$db = getDB();

if ($busca) {
    $stmt = $db->prepare("SELECT * FROM clientes WHERE razao_social LIKE ? OR nome_fantasia LIKE ? OR cnpj_cpf LIKE ? ORDER BY razao_social");
    $stmt->execute(["%$busca%", "%$busca%", "%$busca%"]);
} else {
    $stmt = $db->query("SELECT * FROM clientes ORDER BY razao_social");
}
$clientes = $stmt->fetchAll();

include '../../includes/header_vendedor.php';
?>

<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main">
        <div class="vend-page-head">
            <div><h1 class="vend-page-title">Clientes Cadastrados</h1></div>
            <button class="vbtn-sm" style="border-color:#D85A30;color:#D85A30" onclick="abrirModal()"><i class="fas fa-plus"></i> Novo Cliente</button>
        </div>
        
        <form method="GET" style="margin-bottom:20px;display:flex;gap:8px;align-items:end">
            <div style="flex:1"><input type="text" name="busca" class="form-control" placeholder="Buscar..." value="<?php echo htmlspecialchars($busca); ?>"></div>
            <button type="submit" class="vbtn-sm"><i class="fas fa-search"></i> Buscar</button>
            <?php if ($busca): ?><a href="clientes.php" class="vbtn-sm"><i class="fas fa-eraser"></i> Limpar</a><?php endif; ?>
        </form>
        
        <div class="vend-table-wrap">
            <table class="vend-table">
                <thead>
                    <tr>
                        <th>Razão Social</th>
                        <th>Nome Fantasia</th>
                        <th>CNPJ/CPF</th>
                        <th>Cidade/UF</th>
                        <th>Telefone</th>
                        <th>Email</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clientes)): ?>
                        <tr><td colspan="7" class="text-center">Nenhum cliente cadastrado</td></tr>
                    <?php else: foreach ($clientes as $cliente): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($cliente['razao_social']); ?></td>
                            <td><?php echo htmlspecialchars($cliente['nome_fantasia']); ?></td>
                            <td><?php echo htmlspecialchars($cliente['cnpj_cpf']); ?></td>
                            <td><?php echo htmlspecialchars($cliente['cidade'] . '/' . $cliente['estado']); ?></td>
                            <td><?php echo htmlspecialchars($cliente['telefone']); ?></td>
                            <td><?php echo htmlspecialchars($cliente['email']); ?></td>
                            <td>
                                <button class="vbtn-sm btn-primary" onclick='editarCliente(<?php echo json_encode($cliente, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'><i class="fas fa-edit"></i></button>
                                <button class="vbtn-sm btn-danger" onclick="excluirCliente(<?php echo $cliente['id']; ?>)"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="modalCliente" class="modal" style="display:none;position:fixed;z-index:9999;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,.5);">
    <div class="modal-content" style="background:#fff;margin:3% auto;padding:20px;width:95%;max-width:600px;border-radius:8px;max-height:90vh;overflow-y:auto">
        <div class="modal-header"><h3 id="modalTitulo">Novo Cliente</h3><button type="button" onclick="fecharModal()" style="background:none;border:none;font-size:24px;cursor:pointer">&times;</button></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id" id="clienteId">
                
                <div style="display:flex;gap:12px;margin-bottom:12px">
                    <div style="flex:2"><label>Razão Social *</label><input type="text" id="razao_social" name="razao_social" class="form-control" required></div>
                    <div style="flex:1"><label>Nome Fantasia</label><input type="text" id="nome_fantasia" name="nome_fantasia" class="form-control"></div>
                </div>
                
                <div style="display:flex;gap:12px;margin-bottom:12px">
                    <div style="flex:1"><label>CNPJ/CPF</label><input type="text" id="cnpj_cpf" name="cnpj_cpf" class="form-control"></div>
                    <div style="flex:1"><label>Inscrição Estadual</label><input type="text" id="inscricao_estadual" name="inscricao_estadual" class="form-control"></div>
                </div>
                
                <div style="margin-bottom:12px"><label>Endereço</label><input type="text" id="endereco" name="endereco" class="form-control"></div>
                
                <div style="display:flex;gap:12px;margin-bottom:12px">
                    <div style="flex:1"><label>Cidade</label><input type="text" id="cidade" name="cidade" class="form-control"></div>
                    <div style="flex:1"><label>Estado</label>
                        <select id="estado" name="estado" class="form-control">
                            <option value="">Selecione...</option>
                            <?php foreach (['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $uf): ?>
                                <option value="<?php echo $uf; ?>"><?php echo $uf; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex:1"><label>CEP</label><input type="text" id="cep" name="cep" class="form-control"></div>
                </div>
                
                <div style="display:flex;gap:12px;margin-bottom:12px">
                    <div style="flex:1"><label>Telefone</label><input type="text" id="telefone" name="telefone" class="form-control"></div>
                    <div style="flex:1"><label>Email</label><input type="email" id="email" name="email" class="form-control"></div>
                </div>
                
                <div><label>Observações</label><textarea id="observacoes" name="observacoes" class="form-control" rows="3"></textarea></div>
            </div>
            <div class="modal-footer" style="margin-top:16px;text-align:right">
                <button type="button" class="vbtn-sm" onclick="fecharModal()">Cancelar</button>
                <button type="submit" class="vbtn-sm" style="border-color:#D85A30;color:#D85A30">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModal() {
    document.getElementById('modalTitulo').textContent = 'Novo Cliente';
    document.getElementById('clienteId').value = '';
    ['razao_social','nome_fantasia','cnpj_cpf','inscricao_estadual','endereco','cidade','estado','cep','telefone','email','observacoes'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('modalCliente').style.display = 'block';
}

function editarCliente(cliente) {
    document.getElementById('modalTitulo').textContent = 'Editar Cliente';
    document.getElementById('clienteId').value = cliente.id;
    Object.keys(cliente).forEach(key => {
        const el = document.getElementById(key);
        if (el) el.value = cliente[key] || '';
    });
    document.getElementById('modalCliente').style.display = 'block';
}

function fecharModal() { document.getElementById('modalCliente').style.display = 'none'; }

function excluirCliente(id) {
    if (confirm('Tem certeza que deseja excluir este cliente?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

window.onclick = function(event) {
    if (event.target === document.getElementById('modalCliente')) fecharModal();
}
</script>

<?php include '../../includes/footer_vendedor.php'; ?>