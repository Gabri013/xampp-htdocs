<?php
require_once '../../config/config.php';
requirePermission(['master', 'vendedor', 'gerente']);

$id = $_GET['id'] ?? null; // ID da O.S
if (!$id) die('ID da O.S não fornecido.');

$db = getDB();
$usuario_logado = getCurrentUser();

// Buscar dados atuais da O.S e Venda
$stmt = $db->prepare("
    SELECT os.*, v.id as venda_id, v.numero as venda_numero, v.valor_total, v.desconto, v.observacoes as venda_obs, v.usuario_id as vendedor_id
    FROM ordens_servico os
    INNER JOIN vendas v ON os.venda_id = v.id
    WHERE os.id = ?
");
$stmt->execute([$id]);
$dados = $stmt->fetch();

if (!$dados) die('O.S não encontrada.');

// Verificar se o vendedor tem permissão para editar esta O.S específica
if ($usuario_logado['tipo'] === 'vendedor' && $dados['vendedor_id'] != $usuario_logado['id']) {
    die('Você não tem permissão para editar esta O.S.');
}

// Buscar itens da venda
$stmt = $db->prepare("SELECT * FROM vendas_itens WHERE venda_id = ?");
$stmt->execute([$dados['venda_id']]);
$itens_venda = $stmt->fetchAll();

// Processar salvamento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        
        $nova_prioridade = $_POST['prioridade'];
        $nova_data_termino = dateToMysql($_POST['data_termino']);
        $novas_obs_venda = sanitize($_POST['observacoes_venda']);
        $novas_obs_os = sanitize($_POST['observacoes_os']);
        
        // Função para registrar log
        $registrarLog = function($tipo, $entidade_id, $campo, $anterior, $novo) use ($db, $usuario_logado) {
            if ($anterior != $novo) {
                $stmt = $db->prepare("INSERT INTO logs_alteracoes (tipo_entidade, entidade_id, usuario_id, campo_alterado, valor_anterior, valor_novo) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$tipo, $entidade_id, $usuario_logado['id'], $campo, $anterior, $novo]);
            }
        };

        // 1. Atualizar Venda
        $registrarLog('venda', $dados['venda_id'], 'observacoes', $dados['venda_obs'], $novas_obs_venda);
        $stmt = $db->prepare("UPDATE vendas SET observacoes = ? WHERE id = ?");
        $stmt->execute([$novas_obs_venda, $dados['venda_id']]);

        // 2. Atualizar O.S
        $registrarLog('os', $id, 'prioridade', $dados['prioridade'], $nova_prioridade);
        $registrarLog('os', $id, 'data_termino', $dados['data_termino'], $nova_data_termino);
        $registrarLog('os', $id, 'observacoes_gerais', $dados['observacoes_gerais'], $novas_obs_os);
        
        $stmt = $db->prepare("UPDATE ordens_servico SET prioridade = ?, data_termino = ?, observacoes_gerais = ? WHERE id = ?");
        $stmt->execute([$nova_prioridade, $nova_data_termino, $novas_obs_os, $id]);

        // 3. Upload de Novo Projeto (se houver)
        if (isset($_FILES['novo_projeto']) && $_FILES['novo_projeto']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['novo_projeto'], 'projetos');
            if ($upload['success']) {
                $stmt = $db->prepare("INSERT INTO os_arquivos (os_id, tipo, nome_original, nome_arquivo, usuario_id) VALUES (?, 'projeto', ?, ?, ?)");
                $stmt->execute([$id, $upload['original_name'], $upload['filename'], $usuario_logado['id']]);
                
                $registrarLog('os', $id, 'novo_arquivo_projeto', 'Nenhum', $upload['original_name']);
            }
        }

        $db->commit();
        setSuccess('Alterações salvas com sucesso e registradas no log!');
        header("Location: os_detalhes.php?os_id=$id");
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        setError('Erro ao salvar alterações: ' . $e->getMessage());
    }
}

$page_title = "Editar O.S " . $dados['numero'];
include '../../includes/header_vendedor.php';
?>
<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main"><div class="vend-page-head"><h1 class="vend-page-title">Editar O.S <?php echo $dados['numero']; ?></h1></div><div class="vend-content">
        <div class="vend-card">
            <div class="vend-card-header">
                <h3>Editar O.S e Venda: <?php echo $dados['numero']; ?></h3>
                <a href="os_detalhes.php?os_id=<?php echo $id; ?>" class="vbtn-sm">Cancelar</a>
            </div>
    <div class="vend-card-body">
        <form method="POST" enctype="multipart/form-data">
            <div class="form-row">
                <div class="form-group">
                    <label>Prioridade</label>
                    <select name="prioridade" class="form-control">
                        <option value="verde" <?php echo $dados['prioridade'] == 'verde' ? 'selected' : ''; ?>>🟢 Verde</option>
                        <option value="amarelo" <?php echo $dados['prioridade'] == 'amarelo' ? 'selected' : ''; ?>>🟡 Amarelo</option>
                        <option value="vermelho" <?php echo $dados['prioridade'] == 'vermelho' ? 'selected' : ''; ?>>🔴 Vermelho</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Data de Entrega</label>
                    <input type="date" name="data_termino" class="form-control" value="<?php echo $dados['data_termino']; ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Observações da Venda (Vendedor)</label>
                <textarea name="observacoes_venda" class="form-control" rows="3"><?php echo htmlspecialchars($dados['venda_obs']); ?></textarea>
            </div>

            <div class="form-group">
                <label>Observações Gerais da O.S</label>
                <textarea name="observacoes_os" class="form-control" rows="3"><?php echo htmlspecialchars($dados['observacoes_gerais']); ?></textarea>
            </div>

            <div class="form-group">
                <label>Enviar Novo Arquivo de Projeto/Referência</label>
                <input type="file" name="novo_projeto" class="form-control">
                <small class="text-muted">O arquivo será adicionado à lista de arquivos da O.S.</small>
            </div>

            <hr>
            <h4>Itens do Pedido (Apenas Visualização)</h4>
            <table class="table">
                <thead><tr><th>Descrição</th><th>Qtd</th><th>Valor Total</th></tr></thead>
                <tbody>
                    <?php foreach ($itens_venda as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['descricao_manual']); ?></td>
                            <td><?php echo $item['quantidade']; ?></td>
                            <td><?php echo formatMoney($item['valor_total']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="mt-20">
                <button type="submit" class="vbtn-sm btn-lg">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>
    </div>
</div>
</div>

<?php include '../../includes/footer_vendedor.php'; ?>



