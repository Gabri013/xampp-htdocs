<?php
/**
 * Script para criar dados de teste para os testes E2E
 * Banco de dados: dbcozinca
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    echo "Iniciando criação de dados de teste...\n";
    
    // 1. Verificar estrutura da tabela ordens_servico
    echo "Verificando estrutura da tabela ordens_servico...\n";
    $result = $db->query("DESCRIBE ordens_servico");
    $columns = [];
    while ($row = $result->fetch()) {
        $columns[] = $row['Field'];
    }
    echo "Colunas encontradas: " . implode(', ', $columns) . "\n";
    
    // 2. Criar OS de teste com ID=1 (mínimo necessário para os testes)
    // Usar apenas colunas que existem
    $sql = "INSERT IGNORE INTO ordens_servico (id, numero, status, prioridade, data_inicio, data_termino, etapa_atual, created_at)
            VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 15 DAY), ?, NOW())
            ON DUPLICATE KEY UPDATE numero = VALUES(numero)";
    $stmt = $db->prepare($sql);
    $stmt->execute([1, 'OS-TEST-001', 'pendente', 'verde', 'corte']);
    echo "OS de teste criada/verificada.\n";
    
    // 3. Verificar estrutura da tabela os_itens
    echo "Verificando estrutura da tabela os_itens...\n";
    $result = $db->query("DESCRIBE os_itens");
    $columns = [];
    while ($row = $result->fetch()) {
        $columns[] = $row['Field'];
    }
    echo "Colunas encontradas: " . implode(', ', $columns) . "\n";
    
    // 4. Criar itens da OS de teste (se tabela existir)
    if (in_array('os_id', $columns) && in_array('descricao_manual', $columns)) {
        $sql = "INSERT IGNORE INTO os_itens (os_id, descricao_manual, quantidade, created_at)
                VALUES (?, ?, ?, NOW())";
        $stmt = $db->prepare($sql);
        $stmt->execute([1, 'Item Teste 1', 1]);
        $stmt->execute([1, 'Item Teste 2', 1]);
        echo "Itens da OS criados/verificados.\n";
    } else {
        echo "Tabela os_itens não tem estrutura esperada, pulando criação de itens.\n";
    }
    
    // 5. Verificar dados criados
    echo "\n=== Verificação dos dados criados ===\n";
    
    $sql = "SELECT 'OS' as tipo, COUNT(*) as total FROM ordens_servico WHERE id = 1
            UNION ALL
            SELECT 'OS-100', COUNT(*) FROM ordens_servico WHERE id = 100
            UNION ALL
            SELECT 'Itens OS-100', COUNT(*) FROM os_itens WHERE os_id = 100";
    $result = $db->query($sql);
    while ($row = $result->fetch()) {
        echo "{$row['tipo']}: {$row['total']}\n";
    }
    
    // Listar todas as OS existentes
    echo "\n=== Todas as OS existentes ===\n";
    $sql = "SELECT id, numero, status FROM ordens_servico ORDER BY id LIMIT 10";
    $result = $db->query($sql);
    while ($row = $result->fetch()) {
        echo "ID: {$row['id']}, Número: {$row['numero']}, Status: {$row['status']}\n";
    }
    
    // Listar usuários existentes
    echo "\n=== Usuários existentes ===\n";
    $sql = "SELECT id, nome, email, tipo FROM usuarios LIMIT 10";
    $result = $db->query($sql);
    if ($result->rowCount() > 0) {
        while ($row = $result->fetch()) {
            echo "ID: {$row['id']}, Nome: {$row['nome']}, Email: {$row['email']}, Tipo: {$row['tipo']}\n";
        }
        
        // Resetar senha do usuário admin para 'admin123'
        echo "\nResetando senha do usuário admin...\n";
        $sql = "UPDATE usuarios SET senha = ? WHERE email = 'admin@sistema.com'";
        $stmt = $db->prepare($sql);
        $senhaHash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt->execute([$senhaHash]);
        echo "Senha do usuário admin@sistema.com resetada para: admin123\n";
    } else {
        echo "Nenhum usuário encontrado. Criando usuário de teste...\n";
        
        // Criar usuário de teste
        $sql = "INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $senhaHash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt->execute(['Admin Teste', 'admin@teste.com', $senhaHash, 'admin']);
        echo "Usuário de teste criado: admin@teste.com / admin123\n";
    }
    
    echo "\nDados de teste criados com sucesso!\n";
    
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    exit(1);
}
