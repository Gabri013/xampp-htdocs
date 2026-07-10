<?php
/**
 * Configuração do Banco de Dados - Corrigido para Produção
 */

// Carrega overrides locais, se existir
if (file_exists(__DIR__ . '/database.local.php')) {
    require_once __DIR__ . '/database.local.php';
}

$serverHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
$isLocalEnvironment = false;

if ($serverHost !== '') {
    $lowerHost = strtolower($serverHost);
    $isLocalEnvironment = (
        $lowerHost === 'localhost' ||
        strpos($lowerHost, 'localhost') !== false ||
        strpos($lowerHost, '127.0.0.1') !== false ||
        strpos($lowerHost, '.local') !== false ||
        strpos($lowerHost, '.test') !== false ||
        strpos($lowerHost, '.dev') !== false
    );
}

// Configurações do banco (Local XAMPP ou Produção)
if ($isLocalEnvironment) {
    if (!defined('DB_HOST')) {
        define('DB_HOST', '127.0.0.1');
    }
    if (!defined('DB_PORT')) {
        define('DB_PORT', '3306');
    }
    if (!defined('DB_NAME')) {
        define('DB_NAME', 'dbcozinca');
    }
    if (!defined('DB_USER')) {
        define('DB_USER', 'root');
    }
    if (!defined('DB_PASS')) {
        define('DB_PASS', '');
    }
} else {
    if (!defined('DB_HOST')) {
        define('DB_HOST', 'dbcozinca.mysql.uhserver.com');
    }
    if (!defined('DB_PORT')) {
        define('DB_PORT', '3306');
    }
    if (!defined('DB_NAME')) {
        define('DB_NAME', 'dbcozinca');
    }
    if (!defined('DB_USER')) {
        define('DB_USER', 'cozinca_db');
    }
    if (!defined('DB_PASS')) {
        define('DB_PASS', 'Coz2025@@08');
    }
}

if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}

// Classe de conexão (Singleton)
class Database
{
    private static $instance = null;
    private $conn;

    private function __construct()
    {
        try {
            // Em servidores como o UHServer, concatenar a porta às vezes exige a sintaxe correta do DSN
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $this->conn = new PDO(
                $dsn,
                DB_USER,
                DB_PASS,
                $options
            );

        } catch (PDOException $e) {
            die(
                'Erro na conexão com o banco de dados: ' . 
                $e->getMessage()
            );
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->conn;
    }

    private function __clone() {}

    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
}

// Função auxiliar
function getDB()
{
    return Database::getInstance()->getConnection();
}
