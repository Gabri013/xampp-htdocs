<?php
/**
 * API: Importar Cadastro JOTEC
 *
 * Endpoints:
 * POST /api/importar_jotec.php?acao=preview
 * POST /api/importar_jotec.php?acao=importar
 * GET /api/importar_jotec.php?acao=status
 *
 * Lê arquivo Excel, valida e importa matérias primas
 */

require_once '../config/config.php';
require_once '../includes/sistema_validacao_100.php';

$db = getDB();
$acao = $_POST['acao'] ?? $_GET['acao'] ?? null;

header('Content-Type: application/json; charset=utf-8');

if (!$acao) {
    echo json_encode(['erro' => true, 'mensagem' => 'Ação não especificada']);
    exit;
}

// ===== PRÉVIA DO ARQUIVO =====
if ($acao === 'preview') {
    if (!isset($_FILES['arquivo'])) {
        echo json_encode(['erro' => true, 'mensagem' => 'Arquivo não enviado']);
        exit;
    }

    $arquivo = $_FILES['arquivo'];
    $tmpFile = $arquivo['tmp_name'];

    try {
        $resultado = previewArquivo($tmpFile, $arquivo['name']);
        echo json_encode($resultado);
    } catch (Exception $e) {
        echo json_encode(['erro' => true, 'mensagem' => $e->getMessage()]);
    }
    exit;
}

// ===== IMPORTAR ARQUIVO =====
if ($acao === 'importar') {
    if (!isset($_FILES['arquivo'])) {
        echo json_encode(['erro' => true, 'mensagem' => 'Arquivo não enviado']);
        exit;
    }

    $arquivo = $_FILES['arquivo'];
    $tmpFile = $arquivo['tmp_name'];
    $validarDuplicidade = isset($_POST['validar_duplicidade']);
    $atualizarExistentes = isset($_POST['atualizar_existentes']);
    $registrarAuditoria = isset($_POST['registrar_auditoria']);

    try {
        $resultado = importarArquivo($tmpFile, $validarDuplicidade, $atualizarExistentes, $registrarAuditoria);
        echo json_encode($resultado);
    } catch (Exception $e) {
        echo json_encode(['erro' => true, 'mensagem' => $e->getMessage()]);
    }
    exit;
}

// ===== STATUS DE IMPORTAÇÃO =====
if ($acao === 'status') {
    $ultimaImportacao = $db->query("
        SELECT * FROM import_log
        ORDER BY data_criacao DESC
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'ok',
        'ultima_importacao' => $ultimaImportacao,
        'tabelas' => [
            'materias_primas' => $db->query("SELECT COUNT(*) as total FROM materias_primas")->fetch(PDO::FETCH_ASSOC)['total'],
            'import_log' => $db->query("SELECT COUNT(*) as total FROM import_log")->fetch(PDO::FETCH_ASSOC)['total']
        ]
    ]);
    exit;
}

// ===== FUNÇÕES =====

function previewArquivo($tmpFile, $nomearquivo) {
    global $db;

    // Detectar tipo de arquivo
    $ext = strtolower(pathinfo($nomearquivo, PATHINFO_EXTENSION));

    if ($ext === 'xlsx') {
        return previewXLSX($tmpFile);
    } elseif ($ext === 'xls') {
        return previewXLS($tmpFile);
    } elseif ($ext === 'csv') {
        return previewCSV($tmpFile);
    } else {
        throw new Exception("Formato de arquivo não suportado: $ext");
    }
}

function previewXLSX($tmpFile) {
    // Tentar com PhpSpreadsheet
    if (!class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
        throw new Exception("Biblioteca PhpSpreadsheet não disponível. Use formato CSV ou XLSX.");
    }

    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpFile);
    $abas = [];

    foreach ($spreadsheet->getSheetNames() as $sheetName) {
        $sheet = $spreadsheet->getSheetByName($sheetName);
        $maxRow = $sheet->getHighestRow();
        $maxCol = $sheet->getHighestColumn();

        // Headers
        $headers = [];
        for ($col = 'A'; $col <= $maxCol; $col++) {
            $headers[] = $sheet->getCell($col . '1')->getValue();
        }

        // Primeiras 5 linhas
        $amostra = [];
        for ($row = 2; $row <= min(6, $maxRow); $row++) {
            $rowData = [];
            for ($col = 'A'; $col <= $maxCol; $col++) {
                $rowData[] = $sheet->getCell($col . $row)->getValue();
            }
            $amostra[] = $rowData;
        }

        $abas[] = [
            'nome' => $sheetName,
            'linhas' => $maxRow - 1,
            'colunas' => $maxCol,
            'headers' => $headers,
            'amostra' => $amostra
        ];
    }

    return [
        'status' => 'ok',
        'arquivo' => $nomearquivo ?? 'arquivo.xlsx',
        'tipo' => 'XLSX',
        'abas' => $abas,
        'total_abas' => count($abas)
    ];
}

function previewXLS($tmpFile) {
    throw new Exception("Arquivo .xls antigo detectado. Converta para .xlsx ou use upload via web.");
}

function previewCSV($tmpFile) {
    $fp = fopen($tmpFile, 'r');
    $headers = fgetcsv($fp);

    $amostra = [];
    for ($i = 0; $i < 5 && !feof($fp); $i++) {
        $row = fgetcsv($fp);
        if ($row) $amostra[] = $row;
    }

    fclose($fp);

    return [
        'status' => 'ok',
        'arquivo' => 'arquivo.csv',
        'tipo' => 'CSV',
        'abas' => [[
            'nome' => 'Dados',
            'linhas' => count($amostra),
            'colunas' => count($headers),
            'headers' => $headers,
            'amostra' => $amostra
        ]],
        'total_abas' => 1
    ];
}

function importarArquivo($tmpFile, $validarDuplicidade, $atualizarExistentes, $registrarAuditoria) {
    global $db;

    // Detectar e processar arquivo
    $ext = strtolower(pathinfo($_FILES['arquivo']['name'], PATHINFO_EXTENSION));

    if ($ext === 'xlsx' && class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
        return importarXLSX($tmpFile, $validarDuplicidade, $atualizarExistentes, $registrarAuditoria);
    } elseif ($ext === 'csv') {
        return importarCSV($tmpFile, $validarDuplicidade, $atualizarExistentes, $registrarAuditoria);
    } else {
        throw new Exception("Formato não suportado para importação: $ext");
    }
}

function importarXLSX($tmpFile, $validarDuplicidade, $atualizarExistentes, $registrarAuditoria) {
    global $db;

    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpFile);
    $totalImportado = 0;
    $totalErro = 0;
    $registros = [];

    foreach ($spreadsheet->getSheetNames() as $sheetName) {
        $sheet = $spreadsheet->getSheetByName($sheetName);
        $maxRow = $sheet->getHighestRow();

        // Headers
        $headers = [];
        for ($col = 'A'; $col <= 'E'; $col++) {
            $headers[strtolower($sheet->getCell($col . '1')->getValue())] = $col;
        }

        // Processar linhas
        for ($row = 2; $row <= $maxRow; $row++) {
            try {
                $dados = [
                    'codigo' => trim($sheet->getCell('A' . $row)->getValue()),
                    'descricao' => trim($sheet->getCell('B' . $row)->getValue()),
                    'fornecedor' => trim($sheet->getCell('C' . $row)->getValue()),
                    'preco' => floatval($sheet->getCell('D' . $row)->getValue()),
                    'unidade' => trim($sheet->getCell('E' . $row)->getValue())
                ];

                if (empty($dados['codigo'])) continue; // Pular linhas vazias

                // Validar
                $validador = new SistemaValidacao100();
                $dados['fornecedor_id'] = obterFornecedorId($dados['fornecedor']);

                $relatorio = validar_100('materia_prima', $dados, $_SESSION['usuario_id'] ?? 1);

                if ($relatorio['status'] === 'OK') {
                    // Verificar duplicidade
                    if ($validarDuplicidade) {
                        $stmt = $db->prepare("SELECT id FROM materias_primas WHERE codigo = ?");
                        $stmt->execute([$dados['codigo']]);
                        $existe = $stmt->rowCount() > 0;

                        if ($existe && !$atualizarExistentes) {
                            $registros[] = [
                                'linha' => $row,
                                'status' => 'duplicado',
                                'codigo' => $dados['codigo'],
                                'mensagem' => 'Código já existe (não atualizar)'
                            ];
                            $totalErro++;
                            continue;
                        }
                    }

                    // Salvar no banco
                    if (isset($existe) && $existe && $atualizarExistentes) {
                        // UPDATE
                        $stmt = $db->prepare("
                            UPDATE materias_primas
                            SET descricao = ?, fornecedor_id = ?, preco = ?, unidade = ?
                            WHERE codigo = ?
                        ");
                        $stmt->execute([
                            $dados['descricao'],
                            $dados['fornecedor_id'],
                            $dados['preco'],
                            $dados['unidade'],
                            $dados['codigo']
                        ]);
                    } else {
                        // INSERT
                        $stmt = $db->prepare("
                            INSERT INTO materias_primas (codigo, descricao, fornecedor_id, preco, unidade)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $dados['codigo'],
                            $dados['descricao'],
                            $dados['fornecedor_id'],
                            $dados['preco'],
                            $dados['unidade']
                        ]);
                    }

                    $registros[] = [
                        'linha' => $row,
                        'status' => 'ok',
                        'codigo' => $dados['codigo'],
                        'descricao' => $dados['descricao']
                    ];
                    $totalImportado++;

                } else {
                    $registros[] = [
                        'linha' => $row,
                        'status' => 'erro',
                        'codigo' => $dados['codigo'],
                        'erros' => $relatorio['erros']
                    ];
                    $totalErro++;
                }

            } catch (Exception $e) {
                $registros[] = [
                    'linha' => $row,
                    'status' => 'erro',
                    'mensagem' => $e->getMessage()
                ];
                $totalErro++;
            }
        }
    }

    return [
        'status' => 'ok',
        'total_importado' => $totalImportado,
        'total_erro' => $totalErro,
        'taxa_sucesso' => $totalImportado > 0 ? round(($totalImportado / ($totalImportado + $totalErro)) * 100, 2) : 0,
        'registros' => array_slice($registros, 0, 50) // Retornar primeiros 50 para não sobrecarregar
    ];
}

function importarCSV($tmpFile, $validarDuplicidade, $atualizarExistentes, $registrarAuditoria) {
    global $db;

    $fp = fopen($tmpFile, 'r');
    $headers = fgetcsv($fp);

    $totalImportado = 0;
    $totalErro = 0;
    $registros = [];
    $row = 2;

    while (($linha = fgetcsv($fp)) !== false && $row <= 1000) { // Limitar a 1000 linhas
        try {
            $dados = [
                'codigo' => trim($linha[0] ?? ''),
                'descricao' => trim($linha[1] ?? ''),
                'fornecedor' => trim($linha[2] ?? ''),
                'preco' => floatval($linha[3] ?? 0),
                'unidade' => trim($linha[4] ?? '')
            ];

            if (empty($dados['codigo'])) {
                $row++;
                continue;
            }

            // Validar
            $dados['fornecedor_id'] = obterFornecedorId($dados['fornecedor']);
            $relatorio = validar_100('materia_prima', $dados, $_SESSION['usuario_id'] ?? 1);

            if ($relatorio['status'] === 'OK') {
                // Salvar
                $stmt = $db->prepare("
                    INSERT INTO materias_primas (codigo, descricao, fornecedor_id, preco, unidade)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE descricao = VALUES(descricao)
                ");
                $stmt->execute([
                    $dados['codigo'],
                    $dados['descricao'],
                    $dados['fornecedor_id'],
                    $dados['preco'],
                    $dados['unidade']
                ]);

                $totalImportado++;
            } else {
                $totalErro++;
            }
        } catch (Exception $e) {
            $totalErro++;
        }

        $row++;
    }

    fclose($fp);

    return [
        'status' => 'ok',
        'total_importado' => $totalImportado,
        'total_erro' => $totalErro,
        'taxa_sucesso' => $totalImportado > 0 ? round(($totalImportado / ($totalImportado + $totalErro)) * 100, 2) : 0
    ];
}

function obterFornecedorId($nomeFornecedor) {
    global $db;

    if (empty($nomeFornecedor)) return 1; // Default

    $stmt = $db->prepare("SELECT id FROM fornecedores WHERE razao_social LIKE ? LIMIT 1");
    $stmt->execute(["%$nomeFornecedor%"]);
    $fornecedor = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($fornecedor) {
        return $fornecedor['id'];
    }

    // Criar novo fornecedor se não existir
    $stmt = $db->prepare("
        INSERT INTO fornecedores (razao_social, email, telefone)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$nomeFornecedor, 'contato@' . strtolower(str_replace(' ', '', $nomeFornecedor)) . '.com', '']);

    return $db->lastInsertId();
}

?>
