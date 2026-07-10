<?php
require_once '../../config/config.php';
requirePermission(['master', 'vendedor']);

$db = getDB();
$usuario = getCurrentUser();

if (empty($_GET['id'])) {
    setError('ID de orçamento inválido.');
    header('Location: index.php');
    exit;
}

$id_orc = (int)$_GET['id'];

try {
    // Verificar orçamento
    $stmt = $db->prepare("SELECT o.*, c.razao_social FROM orcamentos o LEFT JOIN clientes c ON o.cliente_id = c.id WHERE o.id = ?");
    $stmt->execute([$id_orc]);
    $orc = $stmt->fetch();

    if (!$orc) {
        setError('Orçamento não encontrado.');
        header('Location: index.php');
        exit;
    }

    // Verificar se já foi convertido (venda vinculada via vendas.orcamento_id)
    $stmtVendaExistente = $db->prepare("SELECT id FROM vendas WHERE orcamento_id = ? LIMIT 1");
    $stmtVendaExistente->execute([$id_orc]);
    if ($orc['status'] === 'convertido' || $stmtVendaExistente->fetch()) {
        setSuccess('Este orçamento já foi convertido em venda.');
        header('Location: index.php');
        exit;
    }

    if ($orc['status'] === 'cancelado') {
        setError('Orçamento cancelado não pode ser convertido em venda.');
        header('Location: index.php');
        exit;
    }

    $db->beginTransaction();

    // Buscar itens do orçamento
    $stmtItens = $db->prepare("SELECT * FROM orcamentos_itens WHERE orcamento_id = ? ORDER BY id");
    $stmtItens->execute([$id_orc]);
    $itens = $stmtItens->fetchAll();

    // Criar venda com o valor final já calculado no orçamento
    $numero = getNextNumber('vendas', 'VND-');
    $sqlV = "INSERT INTO vendas (numero, orcamento_id, cliente_id, usuario_id, data_venda, valor_total) VALUES (?, ?, ?, ?, CURDATE(), ?)";
    $stmtV = $db->prepare($sqlV);
    $stmtV->execute([
        $numero,
        $id_orc,
        $orc['cliente_id'],
        $usuario['id'],
        $orc['valor_total']
    ]);
    $id_venda = $db->lastInsertId();

    // Copiar itens para vendas_itens
    if (!empty($itens)) {
        $stmtVI = $db->prepare("INSERT INTO vendas_itens (venda_id, produto_id, descricao_manual, quantidade, valor_unitario, valor_total) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($itens as $it) {
            $stmtVI->execute([
                $id_venda,
                $it['produto_id'] ?? null,
                $it['descricao_manual'] ?? '',
                $it['quantidade'],
                $it['valor_unitario'] ?? 0,
                $it['valor_total'] ?? 0
            ]);
        }
    }

    // Marcar orçamento como convertido
    $stmtUpd = $db->prepare("UPDATE orcamentos SET status = 'convertido' WHERE id = ?");
    $stmtUpd->execute([$id_orc]);

    $db->commit();
    setSuccess("Orçamento convertido em venda com sucesso! Venda #$numero criada.");

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    setError("Erro ao converter: " . $e->getMessage());
}

header('Location: index.php');
exit;
