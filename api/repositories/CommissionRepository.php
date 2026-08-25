<?php

require_once __DIR__ . '/BaseRepository.php';

/**
 * CommissionRepository
 *
 * Maneja operaciones relacionadas con comisiones:
 * - Calcular comisiones automáticas
 * - Validar fechas de pago
 * - Obtener comisiones pendientes
 * - Bloquear rifas sin comisión pagada
 */
class CommissionRepository extends BaseRepository
{
    protected $table = 'commission_payments';

    /**
     * Obtener rifas con comisión pendiente próxima a vencer
     * Devuelve rifas donde la comisión debe pagarse en los próximos X días
     */
    public function getRafflesWithUpcomingCommission(int $daysAhead = 10): array
    {
        $sql = "SELECT r.*, u.name as creator_name, u.phone as creator_phone, u.email as creator_email,
                       DATEDIFF(r.commission_due_date, CURDATE()) as days_remaining
                FROM raffles r
                INNER JOIN users u ON r.created_by = u.id
                WHERE r.commission_paid = 0
                AND r.status = ?
                AND r.commission_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                ORDER BY r.commission_due_date ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([RAFFLE_STATUS_ACTIVE, $daysAhead]);
        return $stmt->fetchAll();
    }

    /**
     * Obtener rifas con comisión vencida (sin pagar)
     * Estas rifas deben ser bloqueadas automáticamente
     */
    public function getRafflesWithOverdueCommission(): array
    {
        $sql = "SELECT r.*, u.name as creator_name, u.phone as creator_phone, u.email as creator_email
                FROM raffles r
                INNER JOIN users u ON r.created_by = u.id
                WHERE r.commission_paid = 0
                AND r.status = ?
                AND r.commission_due_date < CURDATE()
                ORDER BY r.commission_due_date ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([RAFFLE_STATUS_ACTIVE]);
        return $stmt->fetchAll();
    }

    /**
     * Bloquear rifa por comisión no pagada
     */
    public function blockRaffleForUnpaidCommission(int $raffleId, string $reason = 'Comisión no pagada'): bool
    {
        try {
            $this->beginTransaction();

            // Actualizar estado de la rifa
            $sql = "UPDATE raffles
                    SET status = ?, blocked_reason = ?, blocked_at = NOW()
                    WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([RAFFLE_STATUS_BLOCKED, $reason, $raffleId]);

            // Registrar en audit_log
            $auditSql = "INSERT INTO audit_log (table_name, record_id, action, user_id, changes, created_at)
                        VALUES (?, ?, ?, ?, ?, NOW())";

            $auditStmt = $this->db->prepare($auditSql);
            $auditStmt->execute([
                'raffles',
                $raffleId,
                'blocked',
                null, // Sistema automático
                json_encode(['reason' => $reason])
            ]);

            $this->commit();

            Logger::activity('raffle_blocked', null, [
                'raffle_id' => $raffleId,
                'reason' => $reason
            ]);

            return true;

        } catch (Exception $e) {
            $this->rollback();
            Logger::error('Error bloqueando rifa', [
                'raffle_id' => $raffleId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Desbloquear rifa después de pago de comisión
     */
    public function unblockRaffle(int $raffleId): bool
    {
        try {
            $this->beginTransaction();

            $sql = "UPDATE raffles
                    SET status = ?, blocked_reason = NULL, blocked_at = NULL
                    WHERE id = ? AND status = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([RAFFLE_STATUS_ACTIVE, $raffleId, RAFFLE_STATUS_BLOCKED]);

            $auditSql = "INSERT INTO audit_log (table_name, record_id, action, user_id, changes, created_at)
                        VALUES (?, ?, ?, ?, ?, NOW())";

            $auditStmt = $this->db->prepare($auditSql);
            $auditStmt->execute([
                'raffles',
                $raffleId,
                'unblocked',
                null,
                json_encode(['reason' => 'Comisión pagada'])
            ]);

            $this->commit();

            Logger::activity('raffle_unblocked', null, ['raffle_id' => $raffleId]);

            return true;

        } catch (Exception $e) {
            $this->rollback();
            Logger::error('Error desbloqueando rifa', [
                'raffle_id' => $raffleId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Calcular monto de comisión para una rifa
     * Usa el porcentaje configurado en system_settings
     */
    public function calculateCommissionAmount(float $ticketPrice, int $totalTickets): float
    {
        $sql = "SELECT CAST(setting_value AS DECIMAL(5,4)) as commission_percentage
                FROM system_settings
                WHERE setting_key = 'commission_percentage'
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();

        $percentage = $result['commission_percentage'] ?? 0.01; // Default 1%
        $totalRevenue = $ticketPrice * $totalTickets;

        return round($totalRevenue * $percentage, 2);
    }

    /**
     * Obtener fecha límite de pago (8 días antes del sorteo)
     */
    public function calculateCommissionDueDate(string $drawDate): string
    {
        $date = new DateTime($drawDate);
        $date->modify('-' . COMMISSION_DAYS_BEFORE_DRAW . ' days');
        return $date->format('Y-m-d');
    }

    /**
     * Verificar si la comisión está habilitada globalmente
     */
    public function isCommissionEnabled(): bool
    {
        $sql = "SELECT CAST(setting_value AS UNSIGNED) as enabled
                FROM system_settings
                WHERE setting_key = 'commission_enabled'
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();

        return ($result['enabled'] ?? 1) == 1;
    }

    /**
     * Obtener todas las comisiones pagadas (para reportes)
     */
    public function getPaidCommissions(int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT cp.*, r.title as raffle_title, r.draw_date,
                       u.name as creator_name, u.phone as creator_phone
                FROM {$this->table} cp
                INNER JOIN raffles r ON cp.raffle_id = r.id
                INNER JOIN users u ON r.created_by = u.id
                WHERE cp.status = ?
                ORDER BY cp.confirmed_at DESC
                LIMIT ? OFFSET ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([PAYMENT_STATUS_CONFIRMED, $limit, $offset]);
        return $stmt->fetchAll();
    }

    /**
     * Obtener comisiones pendientes de todas las rifas
     */
    public function getAllPendingCommissions(): array
    {
        $sql = "SELECT r.*, cp.amount as commission_amount, cp.reference as commission_reference,
                       cp.created_at as commission_created_at,
                       u.name as creator_name, u.phone as creator_phone, u.email as creator_email,
                       DATEDIFF(r.commission_due_date, CURDATE()) as days_remaining
                FROM raffles r
                LEFT JOIN commission_payments cp ON r.id = cp.raffle_id AND cp.status = ?
                INNER JOIN users u ON r.created_by = u.id
                WHERE r.commission_paid = 0
                AND r.status IN (?, ?)
                ORDER BY r.commission_due_date ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            PAYMENT_STATUS_PENDING,
            RAFFLE_STATUS_ACTIVE,
            RAFFLE_STATUS_BLOCKED
        ]);
        return $stmt->fetchAll();
    }

    /**
     * Estadísticas de comisiones
     */
    public function getCommissionStats(): array
    {
        $sql = "SELECT
                    COUNT(DISTINCT r.id) as total_raffles,
                    SUM(CASE WHEN r.commission_paid = 1 THEN 1 ELSE 0 END) as paid_count,
                    SUM(CASE WHEN r.commission_paid = 0 THEN 1 ELSE 0 END) as unpaid_count,
                    SUM(CASE WHEN r.commission_paid = 1 THEN r.commission_amount ELSE 0 END) as total_paid_amount,
                    SUM(CASE WHEN r.commission_paid = 0 THEN r.commission_amount ELSE 0 END) as total_pending_amount
                FROM raffles r
                WHERE r.status != ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([RAFFLE_STATUS_DELETED]);
        return $stmt->fetch() ?: [];
    }

    /**
     * Obtener comisiones por usuario/creador
     */
    public function getCommissionsByCreator(int $creatorId): array
    {
        $sql = "SELECT r.id, r.title, r.draw_date, r.commission_amount, r.commission_paid,
                       r.commission_paid_at, r.commission_due_date, r.status,
                       cp.reference as payment_reference, cp.confirmed_at as payment_confirmed_at
                FROM raffles r
                LEFT JOIN commission_payments cp ON r.id = cp.raffle_id AND cp.status = ?
                WHERE r.created_by = ?
                ORDER BY r.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([PAYMENT_STATUS_CONFIRMED, $creatorId]);
        return $stmt->fetchAll();
    }

    /**
     * Validar si una rifa puede ser publicada (comisión habilitada y calculada)
     */
    public function validateRaffleCommission(int $raffleId): array
    {
        $errors = [];

        // Verificar si la comisión está habilitada
        if (!$this->isCommissionEnabled()) {
            return ['valid' => true, 'errors' => []]; // No hay validación si está deshabilitada
        }

        $sql = "SELECT commission_amount, commission_due_date FROM raffles WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$raffleId]);
        $raffle = $stmt->fetch();

        if (!$raffle) {
            $errors[] = 'Rifa no encontrada';
        } elseif ($raffle['commission_amount'] <= 0) {
            $errors[] = 'El monto de comisión no está calculado';
        } elseif (empty($raffle['commission_due_date'])) {
            $errors[] = 'La fecha límite de pago de comisión no está definida';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Exportar comisiones a CSV (para reportes)
     */
    public function exportCommissionsToCSV(string $status = 'all'): string
    {
        $whereSql = "";
        $params = [];

        if ($status === 'paid') {
            $whereSql = "WHERE r.commission_paid = 1";
        } elseif ($status === 'pending') {
            $whereSql = "WHERE r.commission_paid = 0";
        }

        $sql = "SELECT r.id as raffle_id, r.title, r.commission_amount,
                       r.commission_due_date, r.commission_paid, r.commission_paid_at,
                       u.name as creator_name, u.email as creator_email, u.phone as creator_phone
                FROM raffles r
                INNER JOIN users u ON r.created_by = u.id
                {$whereSql}
                ORDER BY r.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $commissions = $stmt->fetchAll();

        // Generar CSV
        $csv = "ID Rifa,Título,Monto Comisión,Fecha Límite,Pagada,Fecha Pago,Creador,Email,Teléfono\n";

        foreach ($commissions as $commission) {
            $csv .= sprintf(
                "%d,\"%s\",%s,%s,%s,%s,\"%s\",\"%s\",%s\n",
                $commission['raffle_id'],
                str_replace('"', '""', $commission['title']),
                number_format($commission['commission_amount'], 2),
                $commission['commission_due_date'],
                $commission['commission_paid'] ? 'Sí' : 'No',
                $commission['commission_paid_at'] ?? 'N/A',
                str_replace('"', '""', $commission['creator_name']),
                $commission['creator_email'],
                $commission['creator_phone']
            );
        }

        return $csv;
    }
}
