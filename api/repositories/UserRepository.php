<?php
/**
 * User Repository
 */

require_once __DIR__ . '/BaseRepository.php';

class UserRepository extends BaseRepository
{
    protected $table = 'users';

    /**
     * Buscar usuario por teléfono
     */
    public function findByPhone(string $phone): ?array
    {
        return $this->findOne(['phone_whatsapp' => $phone]);
    }

    /**
     * Buscar usuario por email
     */
    public function findByEmail(string $email): ?array
    {
        return $this->findOne(['email' => $email]);
    }

    /**
     * Buscar usuario por unique_id (UUID)
     */
    public function findByUniqueId(string $uniqueId): ?array
    {
        return $this->findOne(['unique_id' => $uniqueId]);
    }

    /**
     * Crear usuario con UUID único
     */
    public function createUser(array $data): int
    {
        // Generar UUID si no existe
        if (!isset($data['unique_id'])) {
            $data['unique_id'] = $this->generateUUID();
        }

        return $this->create($data);
    }

    /**
     * Generar UUID v4
     */
    private function generateUUID(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Obtener estadísticas del usuario
     */
    public function getUserStats(int $userId): array
    {
        $sql = "SELECT
                    COUNT(DISTINCT t.raffle_id) as raffles_participated,
                    COUNT(t.id) as total_tickets,
                    COUNT(CASE WHEN t.status = ? THEN 1 END) as paid_tickets,
                    SUM(CASE WHEN t.status = ? THEN r.ticket_price ELSE 0 END) as total_spent,
                    COUNT(w.id) as wins
                FROM users u
                LEFT JOIN tickets t ON u.id = t.user_id
                LEFT JOIN raffles r ON t.raffle_id = r.id
                LEFT JOIN raffle_winners w ON u.id = w.user_id
                WHERE u.id = ?
                GROUP BY u.id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([TICKET_STATUS_PAID, TICKET_STATUS_PAID, $userId]);

        $result = $stmt->fetch();
        return $result ?: [
            'raffles_participated' => 0,
            'total_tickets' => 0,
            'paid_tickets' => 0,
            'total_spent' => 0,
            'wins' => 0,
        ];
    }
}
