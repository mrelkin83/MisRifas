<?php
/**
 * Adaptador de whatsapp-engine\Ports\DbPort sobre el PDO singleton que ya
 * usa todo MisRifas (config/database.php). Una sola base de datos: maestra()
 * y conectarA() no tienen nada que resolver, devuelven $this.
 */

require_once __DIR__ . '/../../config/database.php';

class MisRifasDb implements \ElkinLinan\WhatsappAiEngine\Ports\DbPort
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance()->getConnection();
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    public function query(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function beginTransaction(): void
    {
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }
    }

    public function commit(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
    }

    public function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function maestra(): \ElkinLinan\WhatsappAiEngine\Ports\DbPort
    {
        return $this;
    }

    public function conectarA(?string $baseDatos): \ElkinLinan\WhatsappAiEngine\Ports\DbPort
    {
        return $this;
    }
}
