<?php
$host = "localhost";       // ou IP do servidor MySQL
$usuario = "root";         // seu usuário do banco
$senha = "";               // sua senha do banco
$banco = "cozinca_orcamentos"; // nome do banco de dados

$conn = new mysqli($host, $usuario, $senha, $banco);

// Verifica se houve erro na conexão
if ($conn->connect_error) {
    die("Erro de conexão com o banco de dados: " . $conn->connect_error);
}
?>
