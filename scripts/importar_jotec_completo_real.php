<?php
/**
 * IMPORTAR CADASTRO JOTEC - COMPLETO
 *
 * Lê todas as abas do arquivo Excel (15 abas)
 * Importa ~27.000+ registros
 */

require_once __DIR__ . '/../config/config.php';

$db = getDB();
set_time_limit(600); // 10 minutos de timeout

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║  🚀 IMPORTAR CADASTRO COMPLETO JOTEC                           ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$arquivo = 'C:\\Users\\gabri\\Downloads\\CADASTRO PRODUTOS JOTEC - 2019 C.xls';

if (!file_exists($arquivo)) {
    echo "❌ Arquivo não encontrado: $arquivo\n";
    exit(1);
}

echo "✅ Arquivo: " . basename($arquivo) . "\n";
echo "📊 Tamanho: " . number_format(filesize($arquivo) / 1024 / 1024, 2) . " MB\n\n";

// Tentar usar PhpSpreadsheet se disponível
if (class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
    echo "Usando PhpOffice\\PhpSpreadsheet...\n\n";

    try {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($arquivo);

        $totalImportado = 0;
        $totalErro = 0;
        $fornecedoresNovos = [];

        echo "════════════════════════════════════════════════════════════════\n";
        echo "📖 LENDO E IMPORTANDO ABAS\n";
        echo "════════════════════════════════════════════════════════════════\n\n";

        foreach ($spreadsheet->getSheetNames() as $sheetName) {
            echo "📑 ABA: $sheetName\n";

            $sheet = $spreadsheet->getSheetByName($sheetName);
            $maxRow = $sheet->getHighestRow();
            $maxCol = $sheet->getHighestColumn();

            // Headers
            $headers = [];
            for ($col = 'A'; $col <= $maxCol; $col++) {
                $val = $sheet->getCell($col . '1')->getValue();
                $headers[$col] = trim($val) ?: "Coluna";
            }

            echo "   Linhas: $maxRow, Colunas: $maxCol\n";

            // Encontrar colunas
            $colCodigo = null;
            $colDescricao = null;
            $colFornecedor = null;
            $colPreco = null;
            $colUnidade = null;

            foreach ($headers as $col => $header) {
                $h = strtolower($header);
                if (strpos($h, 'cod') !== false) { $colCodigo = $col; }
                elseif (strpos($h, 'desc') !== false || strpos($h, 'prod') !== false) { $colDescricao = $col; }
                elseif (strpos($h, 'forn') !== false) { $colFornecedor = $col; }
                elseif (strpos($h, 'prec') !== false) { $colPreco = $col; }
                elseif (strpos($h, 'unit') !== false) { $colUnidade = $col; }
            }

            // Processar linhas
            $abaImportado = 0;
            $abaErro = 0;

            for ($row = 2; $row <= min($row + 5000, $maxRow); $row++) { // Limitar a 5000/aba para teste
                try {
                    $codigo = trim((string)($sheet->getCell($colCodigo . $row)->getValue() ?? ''));
                    $descricao = trim((string)($sheet->getCell($colDescricao . $row)->getValue() ?? ''));
                    $fornecedor = trim((string)($sheet->getCell($colFornecedor . $row)->getValue() ?? ''));
                    $preco = floatval($sheet->getCell($colPreco . $row)->getValue() ?? 0);
                    $unidade = trim((string)($sheet->getCell($colUnidade . $row)->getValue() ?? ''));

                    // Pular vazios
                    if (empty($codigo) || empty($descricao)) { continue; }

                    // Obter fornecedor
                    $fornecedorId = 1;
                    if (!empty($fornecedor)) {
                        $stmt = $db->prepare("SELECT id FROM fornecedores WHERE razao_social LIKE ? LIMIT 1");
                        $stmt->execute(["%$fornecedor%"]);
                        $forn = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($forn) {
                            $fornecedorId = $forn['id'];
                        } else {
                            $stmt = $db->prepare("INSERT INTO fornecedores (razao_social) VALUES (?)");
                            $stmt->execute([$fornecedor]);
                            $fornecedorId = $db->lastInsertId();
                            if (!in_array($fornecedor, $fornecedoresNovos)) {
                                $fornecedoresNovos[] = $fornecedor;
                            }
                        }
                    }

                    // Inserir
                    $stmt = $db->prepare("
                        INSERT INTO materias_primas (codigo, descricao, fornecedor_id, preco, unidade)
                        VALUES (?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                        descricao = VALUES(descricao),
                        preco = VALUES(preco),
                        unidade = VALUES(unidade)
                    ");

                    $stmt->execute([$codigo, $descricao, $fornecedorId, $preco, $unidade]);

                    $abaImportado++;
                    $totalImportado++;

                } catch (Exception $e) {
                    $abaErro++;
                    $totalErro++;
                }

                if ($abaImportado % 100 == 0 && $abaImportado > 0) {
                    echo "   ✓ $abaImportado processados...\n";
                }
            }

            echo "   ✅ $abaImportado importados, $abaErro erros\n\n";
        }

        echo "════════════════════════════════════════════════════════════════\n";
        echo "✅ IMPORTAÇÃO COMPLETA!\n";
        echo "════════════════════════════════════════════════════════════════\n\n";

        echo "📊 RESUMO:\n";
        echo "   Total importado: $totalImportado\n";
        echo "   Total com erro: $totalErro\n";
        echo "   Fornecedores novos: " . count($fornecedoresNovos) . "\n";
        echo "   Taxa de sucesso: " . round($totalImportado / max(1, $totalImportado + $totalErro) * 100, 1) . "%\n\n";

        // Verificar BD
        $stmt = $db->query("SELECT COUNT(*) as total FROM materias_primas");
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "📊 TOTAL NO BANCO: " . $resultado['total'] . " matérias primas\n\n";

    } catch (Exception $e) {
        echo "❌ Erro: " . $e->getMessage() . "\n";
    }

} else {
    echo "⚠️  PhpSpreadsheet não disponível.\n\n";

    echo "Opções para importar:\n";
    echo "1️⃣  Usar interface web:\n";
    echo "    http://localhost/modules/estoque/importar_jotec.php\n\n";

    echo "2️⃣  Converter arquivo manualmente:\n";
    echo "    • Abrir em Excel\n";
    echo "    • Salvar como CSV UTF-8\n";
    echo "    • Fazer upload via web\n\n";

    echo "3️⃣  Instalar PhpSpreadsheet:\n";
    echo "    composer require phpoffice/phpspreadsheet\n\n";
}

?>
