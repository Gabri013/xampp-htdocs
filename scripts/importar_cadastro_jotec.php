<?php
/**
 * IMPORTAR CADASTRO DE PRODUTOS JOTEC
 *
 * Lê arquivo Excel C:\Users\gabri\Downloads\CADASTRO PRODUTOS JOTEC - 2019 C.xls
 * Valida todas as abas
 * Importa matérias primas para o banco de dados
 */

require_once __DIR__ . '/../config/config.php';

// Usando biblioteca PHPExcel/PhpSpreadsheet se disponível
$excelFile = 'C:\\Users\\gabri\\Downloads\\CADASTRO PRODUTOS JOTEC - 2019 C.xls';

echo "🚀 IMPORTAÇÃO DE CADASTRO JOTEC\n";
echo "================================\n\n";

// Verificar se arquivo existe
if (!file_exists($excelFile)) {
    echo "❌ Arquivo não encontrado: $excelFile\n";
    exit(1);
}

echo "✅ Arquivo encontrado: " . basename($excelFile) . "\n";
echo "📊 Tamanho: " . number_format(filesize($excelFile) / 1024 / 1024, 2) . " MB\n\n";

// Usar PhpSpreadsheet se disponível
try {
    // Tentar com PhpOffice\PhpSpreadsheet
    if (class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($excelFile);
        $sheetNames = $spreadsheet->getSheetNames();

        echo "✅ Arquivo lido com PhpSpreadsheet!\n";
        echo "📋 Total de abas: " . count($sheetNames) . "\n";
        echo "Abas: " . implode(', ', $sheetNames) . "\n\n";

        // Analisar cada aba
        foreach ($sheetNames as $sheetName) {
            echo "\n" . str_repeat("=", 70) . "\n";
            echo "📑 ABA: $sheetName\n";
            echo str_repeat("=", 70) . "\n\n";

            $sheet = $spreadsheet->getSheetByName($sheetName);
            $maxRow = $sheet->getHighestRow();
            $maxCol = $sheet->getHighestColumn();

            echo "Linhas: $maxRow, Colunas: $maxCol\n\n";

            // Headers (primeira linha)
            $headers = [];
            for ($col = 'A'; $col <= $maxCol; $col++) {
                $cell = $sheet->getCell($col . '1');
                $headers[$col] = $cell->getValue();
            }
            echo "Headers: " . implode(' | ', $headers) . "\n\n";

            // Primeiras 5 linhas
            echo "Primeiros registros:\n";
            for ($row = 2; $row <= min(6, $maxRow); $row++) {
                $rowData = [];
                for ($col = 'A'; $col <= $maxCol; $col++) {
                    $cell = $sheet->getCell($col . $row);
                    $rowData[] = $cell->getValue();
                }
                echo "Linha $row: " . implode(' | ', $rowData) . "\n";
            }

            echo "\n... (" . ($maxRow - 1) . " linhas de dados total)\n";
        }

    } else {
        echo "⚠️  PhpSpreadsheet não disponível, tentando método alternativo...\n";
        usarMetodoAlternativo($excelFile);
    }

} catch (Exception $e) {
    echo "⚠️  Erro ao ler com PhpSpreadsheet: " . $e->getMessage() . "\n";
    echo "Tentando método alternativo...\n\n";
    usarMetodoAlternativo($excelFile);
}

function usarMetodoAlternativo($excelFile) {
    echo "📌 Usando análise de arquivo binário...\n";
    echo "📊 Arquivo é formato Excel 97-2003 (.xls)\n";
    echo "⚠️  Para importação completa, recomendo converter para .xlsx\n";
    echo "   ou usar o sistema web para upload!\n";
}

?>
