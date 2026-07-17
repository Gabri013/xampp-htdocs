<?php
/**
 * Criar tabelas necessárias para importação JOTEC
 */

require_once '../config/config.php';

$db = getDB();

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║  📁 CRIAR TABELAS PARA IMPORTAÇÃO JOTEC                        ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

try {
    // Tabela fornecedores
    echo "1️⃣  Criando tabela fornecedores...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS fornecedores (
            id INT PRIMARY KEY AUTO_INCREMENT,
            razao_social VARCHAR(255) NOT NULL UNIQUE,
            cnpj VARCHAR(20),
            email VARCHAR(100),
            telefone VARCHAR(20),
            ativo TINYINT DEFAULT 1,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_razao (razao_social)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "   ✅ Tabela fornecedores criada\n\n";

    // Tabela materias_primas
    echo "2️⃣  Criando tabela materias_primas...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS materias_primas (
            id INT PRIMARY KEY AUTO_INCREMENT,
            codigo VARCHAR(100) UNIQUE NOT NULL,
            descricao VARCHAR(255) NOT NULL,
            fornecedor_id INT,
            preco DECIMAL(10,2),
            unidade VARCHAR(20),
            ativo TINYINT DEFAULT 1,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_codigo (codigo),
            INDEX idx_fornecedor (fornecedor_id),
            FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "   ✅ Tabela materias_primas criada\n\n";

    // Inserir fornecedores padrão
    echo "3️⃣  Inserindo fornecedores padrão...\n";

    $fornecedorPadrao = "Sem Fornecedor";
    $stmt = $db->prepare("INSERT IGNORE INTO fornecedores (razao_social, email) VALUES (?, ?)");
    $stmt->execute([$fornecedorPadrao, 'contato@sempicefornecedor.com']);

    echo "   ✅ Fornecedor padrão criado\n\n";

    echo "════════════════════════════════════════════════════════════════\n";
    echo "✅ TABELAS CRIADAS COM SUCESSO!\n";
    echo "════════════════════════════════════════════════════════════════\n\n";

    // Verificar tabelas
    $stmt = $db->query("SHOW TABLES LIKE '%fornecedor%' OR SHOW TABLES LIKE '%materias%'");
    $tables = $db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()")->fetchAll(PDO::FETCH_COLUMN);

    echo "📊 TABELAS EXISTENTES:\n";
    foreach ($tables as $table) {
        echo "   • $table\n";
    }

    echo "\n✅ PRONTO PARA IMPORTAÇÃO!\n\n";

} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}

?>
