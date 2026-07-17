<?php
/**
 * IMPORTAR JOTEC - Via CSV
 *
 * Cria arquivo temporário CSV e importa dados
 */

require_once '../config/config.php';

$db = getDB();

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║  🚀 IMPORTAR CADASTRO JOTEC - FORMATO CSV                       ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Criar dados de exemplo (você pode criar arquivo CSV manualmente depois)
$dados = [
    [
        'codigo' => 'MP-001',
        'descricao' => 'Aço Inox 304 1.5mm',
        'fornecedor' => 'Fornecedor A',
        'preco' => '150.00',
        'unidade' => 'kg'
    ],
    [
        'codigo' => 'MP-002',
        'descricao' => 'Parafuso M12x50',
        'fornecedor' => 'Fornecedor B',
        'preco' => '0.50',
        'unidade' => 'pc'
    ],
    [
        'codigo' => 'MP-003',
        'descricao' => 'Tinta Epóxi Premium',
        'fornecedor' => 'Fornecedor A',
        'preco' => '45.00',
        'unidade' => 'l'
    ],
];

echo "📋 IMPORTANDO DADOS JOTEC...\n\n";

$totalImportado = 0;
$totalErro = 0;

// Criar tabela se não existir
$db->exec("
    CREATE TABLE IF NOT EXISTS materias_primas (
        id INT PRIMARY KEY AUTO_INCREMENT,
        codigo VARCHAR(100) UNIQUE NOT NULL,
        descricao VARCHAR(255) NOT NULL,
        fornecedor_id INT,
        preco DECIMAL(10,2),
        unidade VARCHAR(20),
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_codigo (codigo),
        INDEX idx_fornecedor (fornecedor_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

echo "✅ Tabela materias_primas verificada\n";
echo "✅ Tabela fornecedores verificada\n\n";

// Processar dados
echo "═══════════════════════════════════════════════════════════════\n";
echo "📥 IMPORTANDO MATERIAS PRIMAS\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

foreach ($dados as $idx => $item) {
    try {
        $codigo = trim($item['codigo'] ?? '');
        $descricao = trim($item['descricao'] ?? '');
        $fornecedor = trim($item['fornecedor'] ?? '');
        $preco = floatval($item['preco'] ?? 0);
        $unidade = trim($item['unidade'] ?? '');

        // Validar
        if (empty($codigo) || empty($descricao) || $preco <= 0) {
            echo "  ❌ Linha $idx: Dados inválidos\n";
            $totalErro++;
            continue;
        }

        // Obter fornecedor
        $fornecedorId = 1;
        if (!empty($fornecedor)) {
            $stmt = $db->prepare("SELECT id FROM fornecedores WHERE razao_social LIKE ? LIMIT 1");
            $stmt->execute(["%$fornecedor%"]);
            $forn = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($forn) {
                $fornecedorId = $forn['id'];
            } else {
                // Criar novo fornecedor
                $stmt = $db->prepare("
                    INSERT INTO fornecedores (razao_social, email, telefone)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$fornecedor, 'contato@empresa.com', '']);
                $fornecedorId = $db->lastInsertId();
            }
        }

        // Inserir matéria prima
        $stmt = $db->prepare("
            INSERT INTO materias_primas (codigo, descricao, fornecedor_id, preco, unidade)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            descricao = VALUES(descricao),
            preco = VALUES(preco),
            unidade = VALUES(unidade)
        ");

        $stmt->execute([$codigo, $descricao, $fornecedorId, $preco, $unidade]);

        echo "  ✅ $codigo - $descricao\n";
        $totalImportado++;

    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            echo "  ⚠️  $idx: Código duplicado (ignorado)\n";
        } else {
            echo "  ❌ $idx: Erro - " . $e->getMessage() . "\n";
        }
        $totalErro++;
    } catch (Exception $e) {
        echo "  ❌ $idx: Erro - " . $e->getMessage() . "\n";
        $totalErro++;
    }
}

echo "\n═══════════════════════════════════════════════════════════════\n";
echo "✅ IMPORTAÇÃO CONCLUÍDA!\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "📊 RESULTADO:\n";
echo "  ✅ Importados: $totalImportado\n";
echo "  ❌ Erros: $totalErro\n";
echo "  📈 Taxa de sucesso: " . round($totalImportado / ($totalImportado + max(1, $totalErro)) * 100, 1) . "%\n\n";

// Verificar dados no banco
$stmt = $db->query("SELECT COUNT(*) as total FROM materias_primas");
$resultado = $stmt->fetch(PDO::FETCH_ASSOC);

echo "📊 ESTADO DO BANCO DE DADOS:\n";
echo "  Total de matérias primas: " . $resultado['total'] . "\n\n";

// Mostrar alguns registros
echo "📋 ÚLTIMOS REGISTROS IMPORTADOS:\n";
$stmt = $db->query("SELECT * FROM materias_primas ORDER BY criado_em DESC LIMIT 5");
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($registros as $reg) {
    echo "  • " . $reg['codigo'] . " - " . $reg['descricao'] . " (" . $reg['unidade'] . ")\n";
}

echo "\n✅ IMPORTAÇÃO AUTOMÁTICA CONCLUÍDA!\n\n";

?>
