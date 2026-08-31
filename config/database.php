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
        // Cargar variables del .env si aún no están en el entorno.
        // getenv()/putenv() no son thread-safe (Apache mpm_winnt corre PHP en
        // hilos que comparten el environ del proceso): bajo carga concurrente
        // getenv() puede devolver vacío de forma intermitente. Por eso el
        // array parseado del .env (estado local, sin carrera) es el respaldo.
        $env = self::loadEnv();

        $this->host     = getenv('DB_HOST')    ?: ($env['DB_HOST']    ?? 'localhost');
        $this->port     = getenv('DB_PORT')    ?: ($env['DB_PORT']    ?? 3306);
        $this->dbname   = getenv('DB_NAME')    ?: ($env['DB_NAME']    ?? '');
        $this->username = getenv('DB_USER')    ?: ($env['DB_USER']    ?? '');
        $this->password = getenv('DB_PASS')    ?: ($env['DB_PASS']    ?? '');
        $this->charset  = getenv('DB_CHARSET') ?: ($env['DB_CHARSET'] ?? 'utf8mb4');

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
            ];
            // PDO::MYSQL_ATTR_INIT_COMMAND quedó deprecado en PHP 8.5 a favor de
            // Pdo\Mysql::ATTR_INIT_COMMAND (disponible desde 8.4). Mismo valor
            // entero (1002); se usa el nuevo si existe y se cae al viejo en
            // PHP < 8.4 para no romper entornos anteriores.
            $initCommandKey = defined('Pdo\\Mysql::ATTR_INIT_COMMAND')
                ? \Pdo\Mysql::ATTR_INIT_COMMAND
                : PDO::MYSQL_ATTR_INIT_COMMAND;
            // CRÍTICO: un solo reloj para TODO el sistema. En el VPS, MySQL
            // corre en UTC y PHP en America/Bogota (constants.php): las fechas
            // escritas con date() (-05:00) quedaban "5 horas en el pasado"
            // para NOW() de MySQL y el cron liberaba TODA reserva al minuto de
            // creada (nadie podía comprar en producción). Se fija aquí — el
            // único punto por el que pasa cualquier request con BD — la zona
            // de PHP Y la de la sesión MySQL, sin depender de qué config se
            // cargó antes.
            $appTz = getenv('APP_TIMEZONE') ?: 'America/Bogota';
            date_default_timezone_set($appTz);
            $tzOffset = (new DateTime('now', new DateTimeZone($appTz)))->format('P');
            $options[$initCommandKey] = "SET NAMES {$this->charset} COLLATE {$this->charset}_unicode_ci, time_zone = '{$tzOffset}'";

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
     * Carga el archivo .env desde la raíz del proyecto y devuelve el mapa
     * parseado. Solo procesa líneas con formato CLAVE=VALOR y omite
     * comentarios. El valor de retorno permite leer la configuración sin
     * depender de getenv() (ver nota de thread-safety en el constructor).
     */
    private static function loadEnv(): array
    {
        // Busca el .env en la raíz del proyecto
        $envFile = dirname(__DIR__) . '/.env';

        if (!file_exists($envFile)) {
            return []; // Si no existe .env, continúa con variables del sistema
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $vars = [];

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
            $vars[$key] = $value;

            // Solo setear si no está ya definida en el entorno
            if (!getenv($key)) {
                putenv("{$key}={$value}");
                $_ENV[$key]    = $value;
                $_SERVER[$key] = $value;
            }
        }

        return $vars;
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
