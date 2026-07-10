<?php
require_once '../../config/config.php';
requirePermission(['master']);
require_once BASE_PATH . '/includes/permissoes.php';

$page_title = 'Cadastro de Usuários';

function getTiposUsuarioDisponiveis(): array
{
    return [
        'master' => 'Administrador',
        'vendedor' => 'Vendedor',
        'projetista' => 'Projetista',
        'gerente' => 'Gerente de Produção',
        'producao' => 'Produção Geral',
        'corte' => 'Setor de Corte',
        'dobra' => 'Setor de Dobra',
        'solda' => 'Setor de Solda',
        'refrigeracao' => 'Setor de Refrigeração',
        'acabamento' => 'Setor de Acabamento',
        'finalizacao' => 'Setor de Finalização',
        'montagem' => 'Setor de Montagem',
        'dashboard_producao' => 'Dashboard de Produção'
    ];
}

function ensureUsuariosTiposSchema(PDO $db): void
{
    $tipos = array_keys(getTiposUsuarioDisponiveis());
    $enum = "'" . implode("','", $tipos) . "'";
    $db->exec("
        ALTER TABLE usuarios
        MODIFY COLUMN tipo ENUM($enum) NOT NULL
    ");
}

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    if ($acao === 'salvar') {
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        $nome = sanitize($_POST['nome']);
        $email = sanitize($_POST['email']);
        $tipo = $_POST['tipo'];
        $status = $_POST['status'];
        $senha = $_POST['senha'] ?? '';
        $permissoes = $_POST['permissoes'] ?? [];
        
        try {
            $db = getDB();
            ensureUsuariosTiposSchema($db);
            inicializarPermissoes($db);
            
            if ($id) {
                // Atualizar
                if (!empty($senha)) {
                    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE usuarios SET nome=?, email=?, tipo=?, status=?, senha=? WHERE id=?");
                    $stmt->execute([$nome, $email, $tipo, $status, $senha_hash, $id]);
                } else {
                    $stmt = $db->prepare("UPDATE usuarios SET nome=?, email=?, tipo=?, status=? WHERE id=?");
                    $stmt->execute([$nome, $email, $tipo, $status, $id]);
                }
                
                // Atualizar permissões granulares
                $stmt = $db->prepare("DELETE FROM usuario_permissoes WHERE usuario_id = ?");
                $stmt->execute([$id]);
                foreach ($permissoes as $perm) {
                    concederPermissao($db, $id, $perm);
                }
                
                setSuccess('Usuário atualizado com sucesso!');
            } else {
                // Inserir
                if (empty($senha)) {
                    setError('Senha é obrigatória para novos usuários.');
                } else {
                    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO usuarios (nome, email, senha, tipo, status) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$nome, $email, $senha_hash, $tipo, $status]);
                    $id = (int) $db->lastInsertId();
                    
                    // Conceder permissões granulares
                    foreach ($permissoes as $perm) {
                        concederPermissao($db, $id, $perm);
                    }
                    
                    setSuccess('Usuário cadastrado com sucesso!');
                }
            }
            
            header('Location: usuarios.php');
            exit;
        } catch (Exception $e) {
            setError('Erro ao salvar usuário: ' . $e->getMessage());
        }
    } elseif ($acao === 'excluir') {
        $id = $_POST['id'];
        
        if ($id == $_SESSION['usuario_id']) {
            setError('Você não pode excluir seu próprio usuário.');
        } else {
            try {
                $db = getDB();
                $stmt = $db->prepare("DELETE FROM usuarios WHERE id = ?");
                $stmt->execute([$id]);
                setSuccess('Usuário excluído com sucesso!');
                header('Location: usuarios.php');
                exit;
            } catch (Exception $e) {
                setError('Erro ao excluir usuário.');
            }
        }
    }
}

// Buscar permissões disponíveis
$db = getDB();
ensureUsuariosTiposSchema($db);
$tiposUsuario = getTiposUsuarioDisponiveis();
inicializarPermissoes($db);
$permissoesDisponiveis = $db->query("SELECT chave, nome, modulo FROM permissoes ORDER BY modulo, chave")->fetchAll(PDO::FETCH_ASSOC);

// Buscar usuários
$stmt = $db->query("SELECT * FROM usuarios ORDER BY nome");
$usuarios = $stmt->fetchAll();

// Buscar permissões de cada usuário
$usuarioPermissoes = [];
foreach ($usuarios as $u) {
    $stmt = $db->prepare("SELECT p.chave FROM usuario_permissoes up JOIN permissoes p ON up.permissao_id = p.id WHERE up.usuario_id = ?");
    $stmt->execute([$u['id']]);
    $usuarioPermissoes[$u['id']] = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

include '../../includes/header_vendedor.php';
?>

<div class="vend-card">
    <div class="vend-card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3>Usuários do Sistema</h3>
        <button class="vbtn-sm btn-primary" onclick="abrirModal()">
            <i class="fas fa-plus"></i> Novo Usuário
        </button>
    </div>
    <div class="vend-card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($usuarios)): ?>
                        <tr><td colspan="5" class="text-center">Nenhum usuário cadastrado</td></tr>
                    <?php else: foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($usuario['nome']); ?></td>
                            <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                            <td><?php echo htmlspecialchars($tiposUsuario[$usuario['tipo']] ?? ucfirst((string) $usuario['tipo'])); ?></td>
                            <td>
                                <span class="badge" style="background-color: <?php echo $usuario['status'] === 'ativo' ? '#28a745' : '#6c757d'; ?>; color: white; padding: 5px 10px; border-radius: 4px;">
                                    <?php echo ucfirst($usuario['status']); ?>
                                </span>
                            </td>
                            <td>
                                <button class="vbtn-sm btn-sm btn-primary" onclick='editarUsuario(<?php echo json_encode($usuario); ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($usuario['id'] != $_SESSION['usuario_id']): ?>
                                    <button class="vbtn-sm btn-sm btn-danger" onclick="excluirUsuario(<?php echo $usuario['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="modalUsuario" class="modal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
    <div class="modal-content" style="background:#fff; margin:5% auto; padding:20px; width:90%; max-width:500px; border-radius:8px;">
        <div class="modal-header">
            <h3 id="modalTitulo">Novo Usuário</h3>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id" id="usuarioId">
                
                <div class="form-group">
                    <label>Nome Completo *</label>
                    <input type="text" id="nome" name="nome" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Senha <span id="senhaOpcional" style="display:none; font-size: 12px; color: #666;">(deixe em branco para manter a atual)</span></label>
                    <input type="password" id="senha" name="senha" class="form-control">
                </div>
                
                <div class="form-row" style="display: flex; gap: 10px;">
                    <div class="form-group" style="flex: 1;">
                        <label>Tipo *</label>
                        <select id="tipo" name="tipo" class="form-control" required>
                            <?php foreach ($tiposUsuario as $valor => $rotulo): ?>
                                <option value="<?php echo htmlspecialchars($valor); ?>"><?php echo htmlspecialchars($rotulo); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Status *</label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="ativo">Ativo</option>
                            <option value="inativo">Inativo</option>
                        </select>
                    </div>
                </div>
            </div>
                
                <div class="form-group" style="margin-top: 15px;">
                    <label style="display: block; margin-bottom: 8px;">Permissões Específicas</label>
                    <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 6px; background: #f9f9f9;">
                        <?php 
                        $modulos = [];
                        foreach ($permissoesDisponiveis as $p) {
                            $modulos[$p['modulo']][] = $p;
                        }
                        foreach ($modulos as $modulo => $perms): ?>
                            <div style="margin-bottom: 10px;">
                                <strong style="text-transform: capitalize; font-size: 12px; color: #666;"><?= htmlspecialchars($modulo) ?></strong>
                                <?php foreach ($perms as $perm): ?>
                                    <div style="margin-left: 15px; margin-top: 5px;">
                                        <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; cursor: pointer;">
                                            <input type="checkbox" name="permissoes[]" value="<?= htmlspecialchars($perm['chave']) ?>" class="perm-check">
                                            <?= htmlspecialchars($perm['nome']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="text-align: right; margin-top: 20px;">
                <button type="button" class="vbtn-sm btn-secondary" onclick="fecharModal()">Cancelar</button>
                <button type="submit" class="vbtn-sm btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModal() {
    document.getElementById('modalTitulo').textContent = 'Novo Usuário';
    document.getElementById('usuarioId').value = '';
    document.getElementById('nome').value = '';
    document.getElementById('email').value = '';
    document.getElementById('senha').value = '';
    document.getElementById('senha').required = true;
    document.getElementById('senhaOpcional').style.display = 'none';
    document.getElementById('modalUsuario').style.display = 'block';
}

function editarUsuario(usuario) {
    document.getElementById('modalTitulo').textContent = 'Editar Usuário';
    document.getElementById('usuarioId').value = usuario.id;
    document.getElementById('nome').value = usuario.nome;
    document.getElementById('email').value = usuario.email;
    document.getElementById('senha').value = '';
    document.getElementById('senha').required = false;
    document.getElementById('senhaOpcional').style.display = 'inline';
    document.getElementById('tipo').value = usuario.tipo;
    document.getElementById('status').value = usuario.status;
    
    // Uncheck all permissions first
    document.querySelectorAll('.perm-check').forEach(cb => cb.checked = false);
    
    // Check user's permissions
    const usuarioId = usuario.id;
    const permMap = <?= json_encode($usuarioPermissoes) ?>;
    if (permMap[usuarioId]) {
        permMap[usuarioId].forEach(perm => {
            const cb = document.querySelector('.perm-check[value="' + perm + '"]');
            if (cb) cb.checked = true;
        });
    }
    
    document.getElementById('modalUsuario').style.display = 'block';
}

function fecharModal() {
    document.getElementById('modalUsuario').style.display = 'none';
}

function excluirUsuario(id) {
    if (confirm('Tem certeza que deseja excluir este usuário?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="${id}">`;
        document.body.appendChild(form);
        form.submit();
    }
}

// Fechar ao clicar fora do modal
window.onclick = function(event) {
    const modal = document.getElementById('modalUsuario');
    if (event.target === modal) fecharModal();
}
</script>

<?php include '../../includes/footer_vendedor.php'; ?>

