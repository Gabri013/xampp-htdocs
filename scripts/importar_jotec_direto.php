<?php
/**
 * IMPORTAR CADASTRO JOTEC DIRETO PARA BANCO DE DADOS
 *
 * Lê arquivo Excel e insere direto no banco
 * Sem interface web - automático total
 */

require_once '../config/config.php';

$db = getDB();

echo "\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "🚀 IMPORTAR CADASTRO JOTEC - AUTOMATIZADO\n";
echo "════════════════════════════════════════════════════════════════\n\n";

$arquivo = 'C:\\Users\\gabri\\Downloads\\CADASTRO PRODUTOS JOTEC - 2019 C.xls';

// Verificar se arquivo existe
if (!file_exists($arquivo)) {
    echo "❌ Arquivo não encontrado: $arquivo\n";
    exit(1);
}

echo "✅ Arquivo encontrado!\n";
echo "📁 " . basename($arquivo) . "\n";
echo "📊 Tamanho: " . number_format(filesize($arquivo) / 1024 / 1024, 2) . " MB\n\n";

// Tentar com COM object (Windows only)
echo "════════════════════════════════════════════════════════════════\n";
echo "📖 LENDO ARQUIVO EXCEL...\n";
echo "════════════════════════════════════════════════════════════════\n\n";

try {
    // Usar COM object do Excel
    $excel = new COM("Excel.Application");
    $excel->Visible = false;
    $excel->DisplayAlerts = false;

    $workbook = $excel->Workbooks->Open($arquivo);

    $totalAbas = $workbook->Sheets->Count;
    echo "📑 Total de abas: $totalAbas\n";
    echo "Abas encontradas:\n";

    $abasNomes = [];
    foreach ($workbook->Sheets as $idx => $sheet) {
        echo "  $idx. " . $sheet->Name . "\n";
        $abasNomes[] = $sheet->Name;
    }

    echo "\n";

    // Processar cada aba
    $totalImportado = 0;
    $totalErro = 0;
    $fornecedoresNovos = [];

    foreach ($workbook->Sheets as $sheet) {
        echo "\n════════════════════════════════════════════════════════════════\n";
        echo "📑 ABA: " . $sheet->Name . "\n";
        echo "════════════════════════════════════════════════════════════════\n\n";

        $usedRange = $sheet->UsedRange;
        $maxRow = $usedRange->Rows->Count;
        $maxCol = $usedRange->Columns->Count;

        echo "Linhas: $maxRow, Colunas: $maxCol\n";

        // Extrair headers
        $headers = [];
        for ($c = 1; $c <= $maxCol; $c++) {
            $val = $sheet->Cells(1, $c)->Value;
            if ($val) {
                $headers[] = trim($val);
            } else {
                $headers[] = "Coluna$c";
            }
        }

        echo "Headers: " . implode(" | ", $headers) . "\n\n";

        // Encontrar colunas
        $colCodigo = null;
        $colDescricao = null;
        $colFornecedor = null;
        $colPreco = null;
        $colUnidade = null;

        foreach ($headers as $idx => $header) {
            $h = strtolower($header);
            if (strpos($h, 'cod') !== false || strpos($h, 'code') !== false) {
                $colCodigo = $idx + 1;
            } elseif (strpos($h, 'desc') !== false || strpos($h, 'prod') !== false || strpos($h, 'nome') !== false) {
                $colDescricao = $idx + 1;
            } elseif (strpos($h, 'forn') !== false || strpos($h, 'fornecedor') !== false) {
                $colFornecedor = $idx + 1;
            } elseif (strpos($h, 'prec') !== false || strpos($h, 'price') !== false) {
                $colPreco = $idx + 1;
            } elseif (strpos($h, 'unit') !== false || strpos($h, 'un') !== false) {
                $colUnidade = $idx + 1;
            }
        }

        echo "Colunas detectadas:\n";
        echo "  Código: Coluna $colCodigo\n";
        echo "  Descrição: Coluna $colDescricao\n";
        echo "  Fornecedor: Coluna $colFornecedor\n";
        echo "  Preço: Coluna $colPreco\n";
        echo "  Unidade: Coluna $colUnidade\n\n";

        // Processar dados
        $abaImportado = 0;
        $abaErro = 0;

        echo "Processando " . ($maxRow - 1) . " linhas...\n";

        for ($r = 2; $r <= min($r + 1000, $maxRow); $r++) { // Limitar a 1000 linhas por teste
            try {
                $codigo = trim((string)$sheet->Cells($r, $colCodigo)->Value);
                $descricao = trim((string)$sheet->Cells($r, $colDescricao)->Value);
                $fornecedor = trim((string)$sheet->Cells($r, $colFornecedor)->Value);
                $preco = floatval($sheet->Cells($r, $colPreco)->Value ?? 0);
                $unidade = trim((string)$sheet->Cells($r, $colUnidade)->Value);

                // Pular linhas vazias
                if (empty($codigo)) {
                    continue;
                }

                // Validar
                if (empty($descricao) || empty($unidade) || $preco <= 0) {
                    $abaErro++;
                    continue;
                }

                // Obter ou criar fornecedor
                $fornecedorId = 1; // Default
                if (!empty($fornecedor)) {
                    $stmt = $db->prepare("SELECT id FROM fornecedores WHERE razao_social LIKE ? LIMIT 1");
                    $stmt->execute(["%$fornecedor%"]);
                    $forn = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($forn) {
                        $fornecedorId = $forn['id'];
                    } else {
                        // Criar novo
                        $stmt = $db->prepare("
                            INSERT INTO fornecedores (razao_social, email, telefone)
                            VALUES (?, ?, ?)
                        ");
                        $stmt->execute([$fornecedor, 'contato@' . sanitizarEmail($fornecedor) . '.com', '']);
                        $fornecedorId = $db->lastInsertId();
                        $fornecedoresNovos[] = $fornecedor;
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

                $abaImportado++;
                $totalImportado++;

                if ($abaImportado % 100 == 0) {
                    echo "  ✓ $abaImportado registros processados...\n";
                }

            } catch (Exception $e) {
                $abaErro++;
                $totalErro++;
            }
        }

        echo "\n✅ Aba concluída:\n";
        echo "   Importados: $abaImportado\n";
        echo "   Erros: $abaErro\n";
        echo "   Taxa: " . round($abaImportado / ($abaImportado + $abaErro) * 100, 1) . "%\n";
    }

    // Fechar Excel
    $workbook->Close(false);
    $excel->Quit();
    unset($excel);

    echo "\n════════════════════════════════════════════════════════════════\n";
    echo "✅ IMPORTAÇÃO COMPLETA!\n";
    echo "════════════════════════════════════════════════════════════════\n\n";

    echo "📊 RESUMO:\n";
    echo "  Total importado: $totalImportado\n";
    echo "  Total com erro: $totalErro\n";
    echo "  Fornecedores novos criados: " . count($fornecedoresNovos) . "\n";
    echo "  Taxa de sucesso: " . round($totalImportado / ($totalImportado + $totalErro) * 100, 1) . "%\n";

    echo "\n";

} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

function sanitizarEmail($texto) {
    $texto = strtolower($texto);
    $texto = preg_replace('/[^a-z0-9]/', '', $texto);
    return substr($texto, 0, 20);
}

?>
