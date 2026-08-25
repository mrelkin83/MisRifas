<?php

require_once __DIR__ . '/BaseRepository.php';

/**
 * PaymentRepository
 *
 * Maneja todas las operaciones relacionadas con pagos:
 * - Crear registros de pago
 * - Validar pagos de Nequi
 * - Confirmar pagos
 * - Registrar comisiones
 * - Obtener historial de pagos
 */
class PaymentRepository extends BaseRepository
{
    protected $table = 'payments';

    /**
     * Crear registro de pago pendiente
     */
    public function createPayment(array $data): ?array
    {
        $requiredFields = ['ticket_id', 'user_id', 'amount', 'payment_method', 'reference'];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new InvalidArgumentException("Campo requerido faltante: {$field}");
            }
        }

        $insertData = [
            'ticket_id' => $data['ticket_id'],
            'user_id' => $data['user_id'],
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'],
            'reference' => $data['reference'],
            'status' => PAYMENT_STATUS_PENDING,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $paymentId = $this->create($insertData);

        if ($paymentId) {
            Logger::payment('pending', [
                'payment_id' => $paymentId,
                'ticket_id' => $data['ticket_id'],
                'amount' => $data['amount'],
                'method' => $data['payment_method'],
                'reference' => $data['reference']
            ]);
        }

        return $paymentId ? $this->findById($paymentId) : null;
    }

    /**
     * Confirmar pago y actualizar ticket a estado "paid"
     * Usa transacción para garantizar integridad
     */
    public function confirmPayment(int $paymentId, array $gatewayResponse = []): bool
    {
        try {
            $this->beginTransaction();

            // Obtener pago
            $payment = $this->findById($paymentId);
            if (!$payment) {
                throw new Exception("Pago no encontrado: {$paymentId}");
            }

            if ($payment['status'] === PAYMENT_STATUS_CONFIRMED) {
                $this->rollback();
                return true; // Ya está confirmado
            }

            // Actualizar pago
            $updateData = [
                'status' => PAYMENT_STATUS_CONFIRMED,
                'gateway_response' => json_encode($gatewayResponse),
                'confirmed_at' => date('Y-m-d H:i:s')
            ];

            $this->update($paymentId, $updateData);

            // Actualizar ticket a "paid"
            $ticketSql = "UPDATE tickets
                         SET status = ?, paid_at = NOW()
                         WHERE id = ?";
            $ticketStmt = $this->db->prepare($ticketSql);
            $ticketStmt->execute([TICKET_STATUS_PAID, $payment['ticket_id']]);

            $this->commit();

            Logger::payment('confirmed', [
                'payment_id' => $paymentId,
                'ticket_id' => $payment['ticket_id'],
                'amount' => $payment['amount']
            ]);

            return true;

        } catch (Exception $e) {
            $this->rollback();
            Logger::error('Error confirmando pago', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Marcar pago como fallido
     */
    public function markAsFailed(int $paymentId, string $reason = ''): bool
    {
        $updateData = [
            'status' => PAYMENT_STATUS_FAILED,
            'gateway_response' => json_encode(['error' => $reason]),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $result = $this->update($paymentId, $updateData);

        if ($result) {
            Logger::payment('failed', [
                'payment_id' => $paymentId,
                'reason' => $reason
            ]);
        }

        return $result;
    }

    /**
     * Buscar pago por referencia única
     * Usado para validar webhooks de Nequi
     */
    public function findByReference(string $reference): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE reference = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$reference]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Buscar pago por ticket_id
     */
    public function findByTicketId(int $ticketId): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE ticket_id = ? ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$ticketId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Obtener pagos por usuario
     */
    public function getPaymentsByUser(int $userId, int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT p.*, t.ticket_number, r.title as raffle_title
                FROM {$this->table} p
                INNER JOIN tickets t ON p.ticket_id = t.id
                INNER JOIN raffles r ON t.raffle_id = r.id
                WHERE p.user_id = ?
                ORDER BY p.created_at DESC
                LIMIT ? OFFSET ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $limit, $offset]);
        return $stmt->fetchAll();
    }

    /**
     * Obtener pagos pendientes con más de X horas
     * Usado para enviar recordatorios
     */
    public function getPendingPayments(int $hoursOld = 1): array
    {
        $sql = "SELECT p.*, u.name as user_name, u.phone as user_phone,
                       t.ticket_number, r.title as raffle_title
                FROM {$this->table} p
                INNER JOIN users u ON p.user_id = u.id
                INNER JOIN tickets t ON p.ticket_id = t.id
                INNER JOIN raffles r ON t.raffle_id = r.id
                WHERE p.status = ?
                AND p.created_at < DATE_SUB(NOW(), INTERVAL ? HOUR)
                ORDER BY p.created_at ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([PAYMENT_STATUS_PENDING, $hoursOld]);
        return $stmt->fetchAll();
    }

    /**
     * Crear pago de comisión
     */
    public function createCommissionPayment(int $raffleId, float $amount, string $reference): ?array
    {
        $sql = "INSERT INTO commission_payments
                (raffle_id, amount, reference, status, created_at)
                VALUES (?, ?, ?, ?, NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$raffleId, $amount, $reference, PAYMENT_STATUS_PENDING]);

        $commissionId = $this->db->lastInsertId();

        Logger::payment('commission_created', [
            'commission_id' => $commissionId,
            'raffle_id' => $raffleId,
            'amount' => $amount,
            'reference' => $reference
        ]);

        return $this->getCommissionPaymentById($commissionId);
    }

    /**
     * Confirmar pago de comisión
     */
    public function confirmCommissionPayment(int $commissionId, array $gatewayResponse = []): bool
    {
        try {
            $this->beginTransaction();

            // Actualizar comisión
            $sql = "UPDATE commission_payments
                    SET status = ?, gateway_response = ?, confirmed_at = NOW()
                    WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                PAYMENT_STATUS_CONFIRMED,
                json_encode($gatewayResponse),
                $commissionId
            ]);

            // Obtener raffle_id
            $commission = $this->getCommissionPaymentById($commissionId);
            if (!$commission) {
                throw new Exception("Comisión no encontrada: {$commissionId}");
            }

            // Actualizar rifa: marcar comisión como pagada
            $raffleSql = "UPDATE raffles
                         SET commission_paid = 1, commission_paid_at = NOW()
                         WHERE id = ?";
            $raffleStmt = $this->db->prepare($raffleSql);
            $raffleStmt->execute([$commission['raffle_id']]);

            $this->commit();

            Logger::payment('commission_confirmed', [
                'commission_id' => $commissionId,
                'raffle_id' => $commission['raffle_id'],
                'amount' => $commission['amount']
            ]);

            return true;

        } catch (Exception $e) {
            $this->rollback();
            Logger::error('Error confirmando comisión', [
                'commission_id' => $commissionId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Obtener comisión por ID
     */
    public function getCommissionPaymentById(int $commissionId): ?array
    {
        $sql = "SELECT * FROM commission_payments WHERE id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$commissionId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Buscar comisión por referencia
     */
    public function findCommissionByReference(string $reference): ?array
    {
        $sql = "SELECT * FROM commission_payments WHERE reference = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$reference]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Obtener comisiones pendientes por rifa
     */
    public function getPendingCommissionsByRaffle(int $raffleId): array
    {
        $sql = "SELECT * FROM commission_payments
                WHERE raffle_id = ? AND status = ?
                ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$raffleId, PAYMENT_STATUS_PENDING]);
        return $stmt->fetchAll();
    }

    /**
     * Estadísticas de pagos
     */
    public function getPaymentStats(int $raffleId = null): array
    {
        $whereSql = $raffleId ? "WHERE t.raffle_id = ?" : "";

        $sql = "SELECT
                    COUNT(*) as total_payments,
                    SUM(CASE WHEN p.status = ? THEN 1 ELSE 0 END) as confirmed_count,
                    SUM(CASE WHEN p.status = ? THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN p.status = ? THEN 1 ELSE 0 END) as failed_count,
                    SUM(CASE WHEN p.status = ? THEN p.amount ELSE 0 END) as total_confirmed_amount,
                    SUM(CASE WHEN p.status = ? THEN p.amount ELSE 0 END) as total_pending_amount
                FROM {$this->table} p
                INNER JOIN tickets t ON p.ticket_id = t.id
                {$whereSql}";

        $params = [];
        if ($raffleId) {
            $params[] = $raffleId;
        }
        $params = array_merge($params, [
            PAYMENT_STATUS_CONFIRMED,
            PAYMENT_STATUS_PENDING,
            'failed',
            PAYMENT_STATUS_CONFIRMED,
            PAYMENT_STATUS_PENDING
        ]);

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            PAYMENT_STATUS_CONFIRMED,
            PAYMENT_STATUS_PENDING,
            PAYMENT_STATUS_FAILED,
            PAYMENT_STATUS_CONFIRMED,
            PAYMENT_STATUS_PENDING
        ]);

        return $stmt->fetch() ?: [];
    }

    /**
     * Generar referencia única para pago
     */
    public function generatePaymentReference(int $ticketId): string
    {
        $timestamp = time();
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "MR-{$ticketId}-{$timestamp}-{$random}";
    }

    /**
     * Verificar si un pago está duplicado (por monto y fecha cercana)
     * Útil para validar webhooks duplicados
     */
    public function isDuplicatePayment(int $ticketId, float $amount, int $minutesThreshold = 5): bool
    {
        $sql = "SELECT COUNT(*) as count
                FROM {$this->table}
                WHERE ticket_id = ?
                AND amount = ?
                AND status = ?
                AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$ticketId, $amount, PAYMENT_STATUS_CONFIRMED, $minutesThreshold]);
        $result = $stmt->fetch();

        return ($result['count'] ?? 0) > 0;
    }
}
