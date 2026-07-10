<?php
// Arquivo de conexão
$host = "localhost";
$usuario = "root";
$senha = ""; // ou sua senha do MySQL
$banco = "cozinca_orcamentos";

$conn = new mysqli($host, $usuario, $senha, $banco);

// Verifica a conexão
if ($conn->connect_error) {
    die("<strong>Erro ao conectar com o banco:</strong> " . $conn->connect_error);
} else {
    echo "<strong>Conexão estabelecida com sucesso!</strong><br><br>";

    // Verifica se a tabela de usuários existe
    $result = $conn->query("SHOW TABLES LIKE 'usuarios'");
    if ($result->num_rows > 0) {
        echo "✅ Tabela <code>usuarios</code> encontrada!<br>";

        // Mostra os usuários cadastrados
        $users = $conn->query("SELECT id, nome, usuario, tipo FROM usuarios");
        if ($users->num_rows > 0) {
            echo "<br><strong>Usuários cadastrados:</strong><ul>";
            while ($row = $users->fetch_assoc()) {
                echo "<li>ID {$row['id']} — <b>{$row['usuario']}</b> ({$row['tipo']})</li>";
            }
            echo "</ul>";
        } else {
            echo "⚠️ Nenhum usuário cadastrado na tabela.";
        }
    } else {
        echo "❌ Tabela <code>usuarios</code> não encontrada.";
    }
}

$conn->close();
?>
