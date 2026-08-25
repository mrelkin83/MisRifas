<?php
/**
 * Configuración de Base de Datos
 * Singleton PDO connection
 * ENTORNO: Desarrollo local XAMPP
 */
// Cargar configuración de rutas
require_once __DIR__ . '/paths.php';

class Database
{
    private static $instance = null;
    private $connection;
    private $host;
    private $port;
    private $dbname;
    private $username;
    private $password;
    private $charset;

    private function __construct()
    {
        // Cargar variables del .env si aún no están en el entorno
        self::loadEnv();

        $this->host     = getenv('DB_HOST')    ?: 'localhost';
        $this->port     = getenv('DB_PORT')    ?: 3306;
        $this->dbname   = getenv('DB_NAME')    ?: '';
        $this->username = getenv('DB_USER')    ?: '';
        $this->password = getenv('DB_PASS')    ?: '';
        $this->charset  = getenv('DB_CHARSET') ?: 'utf8mb4';

        if (empty($this->dbname) || empty($this->username)) {
            throw new Exception('Database not configured. Set DB_NAME, DB_USER in .env');
        }

        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset};port={$this->port}";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset} COLLATE {$this->charset}_unicode_ci"
            ];

            $this->connection = new PDO($dsn, $this->username, $this->password, $options);

        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());

            // En desarrollo mostramos el error real para facilitar el debug
            if (getenv('APP_ENV') === 'development' || getenv('APP_DEBUG') === 'true') {
                throw new Exception("Error de conexión a la BD: " . $e->getMessage());
            }

            throw new Exception("No se pudo conectar a la base de datos. Intenta de nuevo más tarde.");
        }
    }

    /**
     * Carga el archivo .env desde la raíz del proyecto.
     * Solo procesa líneas con formato CLAVE=VALOR y omite comentarios.
     */
    private static function loadEnv(): void
    {
        // Busca el .env en la raíz del proyecto
        $envFile = dirname(__DIR__) . '/.env';

        if (!file_exists($envFile)) {
            return; // Si no existe .env, continúa con variables del sistema
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            // Ignorar comentarios
            if (str_starts_with($line, '#')) {
                continue;
            }

            // Solo procesar líneas con CLAVE=VALOR
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'"); // Quita espacios y comillas

            // Solo setear si no está ya definida en el entorno
            if (!getenv($key)) {
                putenv("{$key}={$value}");
                $_ENV[$key]    = $value;
                $_SERVER[$key] = $value;
            }
        }
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get PDO connection
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    /**
     * Begin transaction
     */
    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        return $this->connection->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback(): bool
    {
        return $this->connection->rollback();
    }

    /**
     * Check if in transaction
     */
    public function inTransaction(): bool
    {
        return $this->connection->inTransaction();
    }

    /**
     * Get last insert ID
     */
    public function lastInsertId(): string
    {
        return $this->connection->lastInsertId();
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
}
