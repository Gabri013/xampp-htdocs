<?php
// API de cadastro rápido de cliente - integração ERP
require_once dirname(__DIR__) . '/config/config.php';
requireLogin();
requirePermission(['master', 'vendedor']);

header('Content-Type: application/json; charset=utf-8');
$db = getDB();

$nome = sanitize($_POST['nome'] ?? '');
$endereco = sanitize($_POST['endereco'] ?? '');
$telefone = sanitize($_POST['telefone'] ?? '');
$email = sanitize($_POST['email'] ?? '');
$cnpj = sanitize($_POST['cnpj'] ?? '');

if (!$nome) {
    echo json_encode(['success' => false, 'error' => 'Nome obrigatório']);
    exit;
}

try {
    // Cliente já existente: devolve o cadastro atual em vez de duplicar
    $existente = encontrarClienteDuplicado($db, $nome, $cnpj);
    if ($existente) {
        echo json_encode([
            'success' => true,
            'ja_existia' => true,
            'id' => $existente['id'],
            'nome' => $existente['razao_social'],
        ]);
        exit;
    }

    $stmt = $db->prepare("INSERT INTO clientes (razao_social, endereco, telefone, email, cnpj_cpf) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$nome, $endereco, $telefone, $email, $cnpj]);

    echo json_encode([
        'success' => true,
        'id' => $db->lastInsertId(),
        'nome' => $nome,
        'endereco' => $endereco,
        'telefone' => $telefone,
        'email' => $email,
        'cnpj' => $cnpj
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
