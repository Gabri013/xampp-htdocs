<?php
require_once '../../config/config.php';
requirePermission(['master', 'vendedor']);

$db = getDB();
$usuario = getCurrentUser();

// Garantir coluna venda_id na tabela orcamentos
try {
    $colCheck = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orcamentos' AND COLUMN_NAME = 'venda_id'")->fetchColumn();
    if ($colCheck == 0) {
        $db->exec("ALTER TABLE orcamentos ADD COLUMN venda_id INT NULL, ADD COLUMN status VARCHAR(30) NULL DEFAULT 'aberto'");
    }
} catch (Exception $e) { /* ignora erro se não conseguir */ }

if (empty($_GET['id'])) {
    setError('ID de orçamento inválido.');
    header('Location: index.php');
    exit;
}

$id_orc = (int)$_GET['id'];

try {
    // Verificar orçamento
    $stmt = $db->prepare("SELECT o.*, c.razao_social, c.id as cliente_id FROM orcamentos o LEFT JOIN clientes c ON o.cliente_id = c.id WHERE o.id = ?");
    $stmt->execute([$id_orc]);
    $orc = $stmt->fetch();
    
    if (!$orc) {
        setError('Orçamento não encontrado.');
        header('Location: index.php');
        exit;
    }
    
    // Verificar se já foi convertido
    if (!empty($orc['venda_id'])) {
        setSuccess('Este orçamento já foi convertido em venda.');
        header('Location: index.php');
        exit;
    }
    
    $db->beginTransaction();
    
    // Buscar itens do orçamento
    $stmtItens = $db->prepare("SELECT * FROM orcamento_itens WHERE orcamento_id = ? OR id_orcamento = ? ORDER BY id");
    $stmtItens->execute([$id_orc, $id_orc]);
    $itens = $stmtItens->fetchAll();
    
    // Calcular totais
    $total_produtos = 0;
    foreach ($itens as $it) {
        $total_produtos += (float)($it['preco_total'] ?? $it['valor_total'] ?? 0);
    }
    $frete = (float)($orc['frete'] ?? 0);
    $descPerc = (float)($orc['desconto'] ?? 0);
    $base = $total_produtos + $frete;
    $vDesc = $base * ($descPerc / 100);
    $total_fin = $base - $vDesc;
    
    // Criar venda
    $numero = getNextNumber('vendas', 'VDA');
    $sqlV = "INSERT INTO vendas (numero, cliente_id, usuario_id, valor_total, status, data_venda) VALUES (?, ?, ?, ?, 'aberta', NOW())";
    $stmtV = $db->prepare($sqlV);
    $stmtV->execute([
        $numero,
        $orc['cliente_id'],
        $usuario['id'],
        $total_fin
    ]);
    $id_venda = $db->lastInsertId();
    
    // Copiar itens para venda_itens (se existir a tabela)
    if (!empty($itens)) {
        $stmtVI = $db->prepare("INSERT INTO venda_itens (venda_id, produto_id, quantidade, preco_unitario, preco_total, descricao) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($itens as $it) {
            $stmtVI->execute([
                $id_venda,
                $it['produto_id'] ?? $it['id_produto'] ?? null,
                $it['quantidade'],
                $it['preco_unitario'] ?? $it['valor_unitario'] ?? 0,
                $it['preco_total'] ?? $it['valor_total'] ?? 0,
                $it['descricao'] ?? $it['produto_nome'] ?? ''
            ]);
        }
    }
    
    // Atualizar orçamento com ID da venda
    $stmtUpd = $db->prepare("UPDATE orcamentos SET status = 'aprovado', venda_id = ? WHERE id = ?");
    $stmtUpd->execute([$id_venda, $id_orc]);
    
    $db->commit();
    setSuccess("Orçamento convertido em venda com sucesso! Venda #$numero criada.");
    
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    setError("Erro ao converter: " . $e->getMessage());
}

header('Location: index.php');
exit;
