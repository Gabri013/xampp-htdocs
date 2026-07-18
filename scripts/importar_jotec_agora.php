<?php
/**
 * IMPORTAR JOTEC 100% - AGORA
 *
 * Lê arquivo Excel e importa TODOS os dados para banco
 * Sem parar, sem esperar, SÓ IMPORTA!
 */

require_once __DIR__ . '/../config/config.php';

$db = getDB();
set_time_limit(600);

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║  🚀 IMPORTAR JOTEC 100% - AGORA!!                             ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$arquivo = 'C:\\Users\\gabri\\Downloads\\CADASTRO PRODUTOS JOTEC - 2019 C.xls';

if (!file_exists($arquivo)) {
    echo "❌ Arquivo não encontrado: $arquivo\n";
    exit(1);
}

echo "✅ Arquivo encontrado!\n";
echo "📁 " . basename($arquivo) . "\n";
echo "📊 Tamanho: " . number_format(filesize($arquivo) / 1024 / 1024, 2) . " MB\n\n";

// Verificar tabelas
echo "Verificando tabelas...\n";
try {
    $db->exec("CREATE TABLE IF NOT EXISTS fornecedores (
        id INT PRIMARY KEY AUTO_INCREMENT,
        razao_social VARCHAR(255) UNIQUE,
        email VARCHAR(100),
        ativo TINYINT DEFAULT 1,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS materias_primas (
        id INT PRIMARY KEY AUTO_INCREMENT,
        codigo VARCHAR(100) UNIQUE,
        descricao VARCHAR(255),
        fornecedor_id INT,
        preco DECIMAL(10,2),
        unidade VARCHAR(20),
        aba_origem VARCHAR(100),
        ativo TINYINT DEFAULT 1,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_codigo (codigo),
        INDEX idx_aba (aba_origem),
        FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    echo "✅ Tabelas verificadas\n\n";
} catch (Exception $e) {
    echo "❌ Erro ao criar tabelas: " . $e->getMessage() . "\n";
    exit(1);
}

// Tentar ler com COM object (Windows)
echo "════════════════════════════════════════════════════════════════\n";
echo "📖 LENDO ARQUIVO EXCEL...\n";
echo "════════════════════════════════════════════════════════════════\n\n";

$totalImportado = 0;
$totalErro = 0;
$fornecedoresNovos = [];
$abasProcessadas = [];

try {
    $excel = new COM("Excel.Application");
    $excel->Visible = false;
    $excel->DisplayAlerts = false;

    $workbook = $excel->Workbooks->Open($arquivo);
    $totalAbas = $workbook->Sheets->Count;

    echo "📑 Total de abas: $totalAbas\n";
    echo "Iniciando importação de TODAS as abas...\n\n";

    // Desabilitar keys temporariamente para speed
    $db->exec("SET FOREIGN_KEY_CHECKS=0");
    $db->exec("SET AUTOCOMMIT=0");

    $insertCount = 0;
    $transactionSize = 1000; // Commit a cada 1000 registros

    foreach ($workbook->Sheets as $sheetIndex => $sheet) {
        $sheetName = $sheet->Name;
        echo "═══════════════════════════════════════════════════════════════\n";
        echo "📑 ABA $sheetIndex: $sheetName\n";
        echo "═══════════════════════════════════════════════════════════════\n";

        $usedRange = $sheet->UsedRange;
        $maxRow = $usedRange->Rows->Count;
        $maxCol = $usedRange->Columns->Count;

        echo "Linhas: $maxRow, Colunas: $maxCol\n";

        // Headers
        $headers = [];
        for ($col = 1; $col <= $maxCol; $col++) {
            $val = @$sheet->Cells($col, 1)->Value;
            $headers[$col] = trim($val) ?: "Col$col";
        }

        // Encontrar colunas
        $colCodigo = null;
        $colDescricao = null;
        $colFornecedor = null;
        $colPreco = null;
        $colUnidade = null;

        foreach ($headers as $idx => $header) {
            $h = strtolower($header);
            if (strpos($h, 'cod') !== false || strpos($h, 'code') !== false) { $colCodigo = $idx; }
            elseif (strpos($h, 'desc') !== false || strpos($h, 'prod') !== false || strpos($h, 'nome') !== false) { $colDescricao = $idx; }
            elseif (strpos($h, 'forn') !== false) { $colFornecedor = $idx; }
            elseif (strpos($h, 'prec') !== false || strpos($h, 'price') !== false) { $colPreco = $idx; }
            elseif (strpos($h, 'unit') !== false) { $colUnidade = $idx; }
        }

        // Se não achou colunas, tenta padrão
        if (!$colCodigo) $colCodigo = 1;
        if (!$colDescricao) $colDescricao = 2;
        if (!$colFornecedor) $colFornecedor = 3;
        if (!$colPreco) $colPreco = 4;
        if (!$colUnidade) $colUnidade = 5;

        echo "Colunas: Cod=$colCodigo, Desc=$colDescricao, Forn=$colFornecedor, Prec=$colPreco, Unit=$colUnidade\n";

        $abaImportado = 0;
        $abaErro = 0;

        // Processar todas as linhas
        for ($row = 2; $row <= $maxRow; $row++) {
            try {
                $codigo = trim((string)(@$sheet->Cells($row, $colCodigo)->Value ?? ''));
                $descricao = trim((string)(@$sheet->Cells($row, $colDescricao)->Value ?? ''));
                $fornecedor = trim((string)(@$sheet->Cells($row, $colFornecedor)->Value ?? ''));
                $preco = floatval(@$sheet->Cells($row, $colPreco)->Value ?? 0);
                $unidade = trim((string)(@$sheet->Cells($row, $colUnidade)->Value ?? ''));

                // Pular vazios
                if (empty($codigo) || empty($descricao)) {
                    continue;
                }

                // Validar preço
                if ($preco < 0) $preco = 0;

                // Obter fornecedor ID
                $fornecedorId = 1;
                if (!empty($fornecedor)) {
                    try {
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
                    } catch (Exception $e) {
                        $fornecedorId = 1;
                    }
                }

                // Inserir ou atualizar
                try {
                    $stmt = $db->prepare("
                        INSERT INTO materias_primas (codigo, descricao, fornecedor_id, preco, unidade, aba_origem)
                        VALUES (?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                        descricao = VALUES(descricao),
                        preco = VALUES(preco),
                        unidade = VALUES(unidade)
                    ");

                    $stmt->execute([$codigo, $descricao, $fornecedorId, $preco, $unidade, $sheetName]);

                    $abaImportado++;
                    $totalImportado++;
                    $insertCount++;

                    // Commit a cada N registros
                    if ($insertCount >= $transactionSize) {
                        $db->exec("COMMIT");
                        $db->exec("BEGIN");
                        $insertCount = 0;
                        echo "  ✓ $abaImportado importados desta aba...\n";
                    }

                } catch (Exception $e) {
                    $abaErro++;
                    $totalErro++;
                }

            } catch (Exception $e) {
                $abaErro++;
                $totalErro++;
            }
        }

        // Commit final da aba
        $db->exec("COMMIT");
        $db->exec("BEGIN");

        echo "✅ Aba $sheetName: $abaImportado importados, $abaErro erros\n";
        echo "   Total até agora: $totalImportado registros\n\n";

        $abasProcessadas[] = [
            'nome' => $sheetName,
            'importado' => $abaImportado,
            'erro' => $abaErro
        ];
    }

    // Commit final
    $db->exec("COMMIT");
    $db->exec("SET AUTOCOMMIT=1");
    $db->exec("SET FOREIGN_KEY_CHECKS=1");

    $workbook->Close(false);
    $excel->Quit();
    unset($excel);

    echo "\n════════════════════════════════════════════════════════════════\n";
    echo "✅ IMPORTAÇÃO 100% COMPLETA!!!\n";
    echo "════════════════════════════════════════════════════════════════\n\n";

    echo "📊 RESULTADO FINAL:\n";
    echo "   Total importado: $totalImportado registros\n";
    echo "   Total com erro: $totalErro\n";
    echo "   Fornecedores novos: " . count($fornecedoresNovos) . "\n";
    echo "   Taxa de sucesso: " . round($totalImportado / max(1, $totalImportado + $totalErro) * 100, 2) . "%\n\n";

    echo "📋 RESUMO POR ABA:\n";
    foreach ($abasProcessadas as $aba) {
        echo "   • " . $aba['nome'] . ": " . $aba['importado'] . " ✓ | " . $aba['erro'] . " ✗\n";
    }

    // Verificar totais
    $stmt = $db->query("SELECT COUNT(*) as total, COUNT(DISTINCT aba_origem) as abas FROM materias_primas");
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "\n📊 BANCO DE DADOS:\n";
    echo "   Total de matérias primas: " . $resultado['total'] . "\n";
    echo "   Total de abas originadas: " . $resultado['abas'] . "\n\n";

    echo "✅ JOTEC 100% IMPORTADO COM SUCESSO!\n\n";

} catch (Exception $e) {
    echo "❌ Erro fatal: " . $e->getMessage() . "\n";
    $db->exec("ROLLBACK");
    $db->exec("SET FOREIGN_KEY_CHECKS=1");
    exit(1);
}

?>
