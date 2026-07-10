<?php
/**
 * Script para criar o usuário vendedor 'nilton' para os testes E2E
 * Banco de dados: dbcozinca
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    echo "=== Configurando usuário vendedor nilton ===\n";
    
    // Verificar se o usuário já existe
    $sql = "SELECT id, nome, email, tipo, senha FROM usuarios WHERE email = 'nilton@cozinca.com.br'";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $usuario = $stmt->fetch();
    
    if ($usuario) {
        echo "Usuário nilton já existe:\n";
        echo "ID: {$usuario['id']}\n";
        echo "Nome: {$usuario['nome']}\n";
        echo "Email: {$usuario['email']}\n";
        echo "Tipo: {$usuario['tipo']}\n";
        
        // Atualizar senha para 'nilton'
        $sql = "UPDATE usuarios SET senha = ?, tipo = 'vendedor', status = 'ativo' WHERE email = 'nilton@cozinca.com.br'";
        $stmt = $db->prepare($sql);
        $senhaHash = password_hash('nilton', PASSWORD_DEFAULT);
        $stmt->execute([$senhaHash]);
        echo "Senha atualizada para: nilton\n";
    } else {
        // Criar o usuário nilton
        $sql = "INSERT INTO usuarios (nome, email, senha, tipo, status) VALUES (?, ?, ?, 'vendedor', 'ativo')";
        $stmt = $db->prepare($sql);
        $senhaHash = password_hash('nilton', PASSWORD_DEFAULT);
        $stmt->execute(['nilton', 'nilton@cozinca.com.br', $senhaHash]);
        echo "Usuário nilton criado com sucesso!\n";
        echo "Email: nilton@cozinca.com.br\n";
        echo "Senha: nilton\n";
        echo "Tipo: vendedor\n";
    }
    
    // Criar cliente de teste
    $sql = "INSERT IGNORE INTO clientes (razao_social, nome_fantasia, email, telefone, cidade, estado) 
            VALUES ('Cliente Teste Vendedor', 'Cliente Teste', 'cliente@teste.com', '(11) 99999-9999', 'São Paulo', 'SP')";
    $db->exec($sql);
    echo "\nCliente de teste criado: Cliente Teste Vendedor\n";
    
    // Criar produto de teste
    $sql = "INSERT IGNORE INTO produtos (codigo, nome, valor, status) 
            VALUES ('TESTE-001', 'Produto Teste Vendedor', 100.00, 'ativo')";
    $db->exec($sql);
    echo "Produto de teste criado: TESTE-001\n";
    
    // Criar tipo de caixa de teste
    $sql = "INSERT IGNORE INTO tipos_caixa (nome, categoria, taxa_padrao_antecipacao, ativo) 
            VALUES ('Caixa Teste', 'avista', 0, 1)";
    $db->exec($sql);
    echo "Tipo de caixa de teste criado: Caixa Teste\n";
    
    // Verificar todos os usuários
    echo "\n=== Usuários no sistema ===\n";
    $sql = "SELECT id, nome, email, tipo, status FROM usuarios ORDER BY id";
    $result = $db->query($sql);
    while ($row = $result->fetch()) {
        echo "ID: {$row['id']}, Nome: {$row['nome']}, Email: {$row['email']}, Tipo: {$row['tipo']}, Status: {$row['status']}\n";
    }
    
    echo "\n=== Setup concluído! ===\n";
    
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    exit(1);
}
