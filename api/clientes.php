<?php
require_once '../config/config.php';

function ensureClientesCamposRapidos(PDO $db): void
{
    $colunas = $db->query("SHOW COLUMNS FROM clientes")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('responsavel', $colunas, true)) {
        $db->exec("ALTER TABLE clientes ADD COLUMN responsavel VARCHAR(150) NULL AFTER nome_fantasia");
    }
}

// Apenas master e vendedor podem cadastrar clientes
if (!hasPermission(['master', 'vendedor'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $razao_social = sanitize($_POST['razao_social']);
    $nome_fantasia = sanitize($_POST['nome_fantasia'] ?? '');
    $responsavel = sanitize($_POST['responsavel'] ?? '');
    $cnpj_cpf = sanitize($_POST['cnpj_cpf'] ?? '');
    $inscricao_estadual = sanitize($_POST['inscricao_estadual'] ?? '');
    $telefone = sanitize($_POST['telefone'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    
    // Novos campos de endereço
    $cep = sanitize($_POST['cep'] ?? '');
    $endereco = sanitize($_POST['endereco'] ?? '');
    $cidade = sanitize($_POST['cidade'] ?? '');
    $estado = sanitize($_POST['estado'] ?? '');
    
    if (empty($razao_social)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Razão Social é obrigatória.']);
        exit;
    }
    
    try {
        $db = getDB();
        ensureClientesCamposRapidos($db);
        $stmt = $db->prepare("INSERT INTO clientes (razao_social, nome_fantasia, responsavel, cnpj_cpf, inscricao_estadual, telefone, email, cep, endereco, cidade, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$razao_social, $nome_fantasia, $responsavel, $cnpj_cpf, $inscricao_estadual, $telefone, $email, $cep, $endereco, $cidade, $estado]);
        $id = $db->lastInsertId();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Cliente cadastrado com sucesso!',
            'cliente_id' => $id,
            'razao_social' => $razao_social,
            'cliente' => [
                'id' => $id,
                'razao_social' => $razao_social,
                'responsavel' => $responsavel,
                'inscricao_estadual' => $inscricao_estadual,
                'endereco' => $endereco,
            ],
        ]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Erro ao cadastrar cliente: ' . $e->getMessage()]);
    }
    exit;
}

header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Método inválido.']);
