<?php
/**
 * CORRIGIR FK INVÁLIDAS
 */

require_once '../config/config.php';

$db = getDB();

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║  🔧 CORRIGIR FK INVÁLIDAS                                      ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Encontrar materiais com FK inválida
$stmt = $db->query("
    SELECT id, codigo, descricao, aba_origem
    FROM materias_primas
    WHERE fornecedor_id IS NULL OR fornecedor_id NOT IN (SELECT id FROM fornecedores)
");
$invalidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Encontrados " . count($invalidos) . " registros com FK inválida:\n\n";

foreach ($invalidos as $mat) {
    echo "  • {$mat['codigo']} - {$mat['descricao']} (aba: {$mat['aba_origem']})\n";
}

echo "\n";

// Obter primeiro fornecedor válido
$stmt = $db->query("SELECT id FROM fornecedores LIMIT 1");
$fornecedor = $stmt->fetch(PDO::FETCH_ASSOC);
$forn_id = $fornecedor['id'] ?? 1;

echo "Usando fornecedor_id=$forn_id para corrigir\n\n";

// Corrigir
$stmt = $db->prepare("
    UPDATE materias_primas
    SET fornecedor_id = ?
    WHERE fornecedor_id IS NULL OR fornecedor_id NOT IN (SELECT id FROM fornecedores)
");

$stmt->execute([$forn_id]);
$linhas = $stmt->rowCount();

echo "✅ Corrigidos $linhas registros\n\n";

// Verificar após correção
$stmt = $db->query("
    SELECT COUNT(*) as total
    FROM materias_primas
    WHERE fornecedor_id NOT IN (SELECT id FROM fornecedores)
");
$ainda_invalidos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

if ($ainda_invalidos === 0) {
    echo "✅ Todas as FK estão válidas agora!\n";
} else {
    echo "❌ Ainda existem $ainda_invalidos FK inválidas\n";
}

echo "\n════════════════════════════════════════════════════════════════\n";

?>
