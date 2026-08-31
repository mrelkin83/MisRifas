<?php

require_once __DIR__ . '/BaseRepository.php';
require_once __DIR__ . '/../../config/brand.php';

/**
 * NotificationRepository
 *
 * Maneja cola de notificaciones:
 * - Encolar notificaciones (WhatsApp, Email)
 * - Procesar cola
 * - Reintentos automáticos
 * - Historial de notificaciones
 */
class NotificationRepository extends BaseRepository
{
    protected $table = 'notifications';

    /**
     * Encolar notificación
     */
    public function enqueue(array $data): ?int
    {
        $requiredFields = ['user_id', 'type', 'channel', 'recipient', 'subject', 'message'];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new InvalidArgumentException("Campo requerido faltante: {$field}");
            }
        }

        $insertData = [
            'user_id' => $data['user_id'],
            'type' => $data['type'],
            'channel' => $data['channel'], // 'whatsapp', 'email'
            'recipient' => $data['recipient'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'data' => isset($data['data']) ? json_encode($data['data']) : null,
            'status' => NOTIFICATION_STATUS_PENDING,
            'attempts' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $notificationId = $this->create($insertData);

        Logger::debug('Notificación encolada', [
            'notification_id' => $notificationId,
            'type' => $data['type'],
            'channel' => $data['channel'],
            'recipient' => $data['recipient']
        ]);

        return $notificationId;
    }

    /**
     * Obtener notificaciones pendientes para procesar
     */
    public function getPendingNotifications(int $limit = 100): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE status = ?
                AND (scheduled_at IS NULL OR scheduled_at <= NOW())
                AND attempts < ?
                ORDER BY created_at ASC
                LIMIT ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([NOTIFICATION_STATUS_PENDING, MAX_NOTIFICATION_ATTEMPTS, $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Marcar notificación como enviada
     */
    public function markAsSent(int $notificationId, array $response = []): bool
    {
        $updateData = [
            'status' => NOTIFICATION_STATUS_SENT,
            'sent_at' => date('Y-m-d H:i:s'),
            'response' => json_encode($response),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $result = $this->update($notificationId, $updateData);

        if ($result) {
            Logger::debug('Notificación enviada', [
                'notification_id' => $notificationId,
                'response' => $response
            ]);
        }

        return $result;
    }

    /**
     * Marcar notificación como fallida e incrementar intentos
     */
    public function markAsFailed(int $notificationId, string $error = ''): bool
    {
        // Obtener notificación actual
        $notification = $this->findById($notificationId);
        if (!$notification) {
            return false;
        }

        $attempts = $notification['attempts'] + 1;
        $status = $attempts >= MAX_NOTIFICATION_ATTEMPTS
            ? NOTIFICATION_STATUS_FAILED
            : NOTIFICATION_STATUS_PENDING;

        $updateData = [
            'status' => $status,
            'attempts' => $attempts,
            'last_error' => $error,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $result = $this->update($notificationId, $updateData);

        if ($result) {
            Logger::error('Notificación fallida', [
                'notification_id' => $notificationId,
                'attempts' => $attempts,
                'error' => $error
            ]);
        }

        return $result;
    }

    /**
     * Crear notificación de compra confirmada
     */
    public function notifyPurchaseConfirmed(int $userId, array $ticketData): ?int
    {
        $message = sprintf(
            "¡Tu boleto #%s ha sido confirmado!\n\nRifa: %s\nPrecio: $%s\nSorteo: %s\n\n¡Mucha suerte!",
            $ticketData['ticket_number'],
            $ticketData['raffle_title'],
            number_format($ticketData['price'], 0),
            date('d/m/Y', strtotime($ticketData['draw_date']))
        );

        return $this->enqueue([
            'user_id' => $userId,
            'type' => NOTIFICATION_TYPE_PURCHASE_CONFIRMED,
            'channel' => NOTIFICATION_CHANNEL_WHATSAPP,
            'recipient' => $ticketData['user_phone'],
            'subject' => 'Boleto Confirmado - ' . plataforma('nombre'),
            'message' => $message,
            'data' => $ticketData
        ]);
    }

    /**
     * Crear notificación de recordatorio de pago
     */
    public function notifyPaymentReminder(int $userId, array $reservationData): ?int
    {
        $hoursRemaining = round((strtotime($reservationData['reserved_until']) - time()) / 3600, 1);

        $message = sprintf(
            "⏰ Recordatorio de pago\n\nTu boleto #%s está reservado pero aún no has completado el pago.\n\nTiempo restante: %s horas\nMonto: $%s\nReferencia: %s\n\nCompleta tu pago para asegurar tu boleto.",
            $reservationData['ticket_number'],
            $hoursRemaining,
            number_format($reservationData['amount'], 0),
            $reservationData['reference']
        );

        return $this->enqueue([
            'user_id' => $userId,
            'type' => NOTIFICATION_TYPE_PAYMENT_REMINDER,
            'channel' => NOTIFICATION_CHANNEL_WHATSAPP,
            'recipient' => $reservationData['user_phone'],
            'subject' => 'Recordatorio de Pago - ' . plataforma('nombre'),
            'message' => $message,
            'data' => $reservationData
        ]);
    }

    /**
     * Crear notificación de sorteo próximo
     */
    public function notifyUpcomingDraw(int $userId, array $raffleData): ?int
    {
        $daysRemaining = floor((strtotime($raffleData['draw_date']) - time()) / 86400);

        $message = sprintf(
            "🎉 ¡Sorteo próximo!\n\nEl sorteo de \"%s\" será en %d días.\n\nFecha: %s\nLotería: %s\n\nTus boletos: %s\n\n¡Te deseamos mucha suerte!",
            $raffleData['raffle_title'],
            $daysRemaining,
            date('d/m/Y', strtotime($raffleData['draw_date'])),
            $raffleData['lottery_name'],
            implode(', ', $raffleData['user_tickets'])
        );

        return $this->enqueue([
            'user_id' => $userId,
            'type' => NOTIFICATION_TYPE_DRAW_REMINDER,
            'channel' => NOTIFICATION_CHANNEL_WHATSAPP,
            'recipient' => $raffleData['user_phone'],
            'subject' => 'Sorteo Próximo - ' . plataforma('nombre'),
            'message' => $message,
            'data' => $raffleData
        ]);
    }

    /**
     * Crear notificación de ganador
     */
    public function notifyWinner(int $userId, array $winnerData): ?int
    {
        $message = sprintf(
            "🏆 ¡FELICIDADES, GANASTE!\n\n¡Tu boleto #%s ha sido ganador!\n\nRifa: %s\nPremio: %s\n\nNos contactaremos contigo pronto para coordinar la entrega del premio.\n\n¡Muchas felicidades!",
            $winnerData['ticket_number'],
            $winnerData['raffle_title'],
            $winnerData['prize']
        );

        // Enviar por WhatsApp Y Email
        $whatsappId = $this->enqueue([
            'user_id' => $userId,
            'type' => NOTIFICATION_TYPE_WINNER,
            'channel' => NOTIFICATION_CHANNEL_WHATSAPP,
            'recipient' => $winnerData['user_phone'],
            'subject' => '¡GANASTE! - ' . plataforma('nombre'),
            'message' => $message,
            'data' => $winnerData
        ]);

        if (!empty($winnerData['user_email'])) {
            $this->enqueue([
                'user_id' => $userId,
                'type' => NOTIFICATION_TYPE_WINNER,
                'channel' => NOTIFICATION_CHANNEL_EMAIL,
                'recipient' => $winnerData['user_email'],
                'subject' => '¡Felicidades, Ganaste! - ' . plataforma('nombre'),
                'message' => $message,
                'data' => $winnerData
            ]);
        }

        return $whatsappId;
    }

    /**
     * Crear notificación de comisión pendiente
     */
    public function notifyCommissionDue(int $userId, array $commissionData): ?int
    {
        $message = sprintf(
            "⚠️ Comisión pendiente\n\nRifa: %s\nMonto: $%s\nFecha límite: %s\nDías restantes: %d\n\nRecuerda pagar la comisión antes de la fecha límite para que tu rifa permanezca activa.",
            $commissionData['raffle_title'],
            number_format($commissionData['commission_amount'], 0),
            date('d/m/Y', strtotime($commissionData['commission_due_date'])),
            $commissionData['days_remaining']
        );

        return $this->enqueue([
            'user_id' => $userId,
            'type' => NOTIFICATION_TYPE_COMMISSION_DUE,
            'channel' => NOTIFICATION_CHANNEL_WHATSAPP,
            'recipient' => $commissionData['creator_phone'],
            'subject' => 'Comisión Pendiente - ' . plataforma('nombre'),
            'message' => $message,
            'data' => $commissionData
        ]);
    }

    /**
     * Crear notificación de rifa bloqueada
     */
    public function notifyRaffleBlocked(int $userId, array $raffleData): ?int
    {
        $message = sprintf(
            "🚫 Rifa bloqueada\n\nTu rifa \"%s\" ha sido bloqueada.\n\nMotivo: %s\n\nPor favor contacta a soporte para más información.",
            $raffleData['raffle_title'],
            $raffleData['blocked_reason']
        );

        return $this->enqueue([
            'user_id' => $userId,
            'type' => NOTIFICATION_TYPE_RAFFLE_BLOCKED,
            'channel' => NOTIFICATION_CHANNEL_WHATSAPP,
            'recipient' => $raffleData['creator_phone'],
            'subject' => 'Rifa Bloqueada - ' . plataforma('nombre'),
            'message' => $message,
            'data' => $raffleData
        ]);
    }

    /**
     * Obtener historial de notificaciones de un usuario
     */
    public function getUserNotifications(int $userId, int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $limit, $offset]);
        return $stmt->fetchAll();
    }

    /**
     * Limpiar notificaciones antiguas (más de 90 días)
     */
    public function cleanOldNotifications(int $daysOld = 90): int
    {
        $sql = "DELETE FROM {$this->table}
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
                AND status IN (?, ?)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$daysOld, NOTIFICATION_STATUS_SENT, NOTIFICATION_STATUS_FAILED]);

        $deletedCount = $stmt->rowCount();

        Logger::debug('Notificaciones antiguas eliminadas', ['count' => $deletedCount]);

        return $deletedCount;
    }

    /**
     * Estadísticas de notificaciones
     */
    public function getNotificationStats(): array
    {
        $sql = "SELECT
                    COUNT(*) as total_notifications,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as sent_count,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed_count,
                    SUM(CASE WHEN channel = ? THEN 1 ELSE 0 END) as whatsapp_count,
                    SUM(CASE WHEN channel = ? THEN 1 ELSE 0 END) as email_count
                FROM {$this->table}
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            NOTIFICATION_STATUS_PENDING,
            NOTIFICATION_STATUS_SENT,
            NOTIFICATION_STATUS_FAILED,
            NOTIFICATION_CHANNEL_WHATSAPP,
            NOTIFICATION_CHANNEL_EMAIL
        ]);

        return $stmt->fetch() ?: [];
    }

    /**
     * Programar notificación para envío futuro
     */
    public function scheduleNotification(array $data, string $scheduledAt): ?int
    {
        $data['scheduled_at'] = $scheduledAt;
        return $this->enqueue($data);
    }

    /**
     * Reintentar notificaciones fallidas
     */
    public function retryFailedNotifications(): int
    {
        $sql = "UPDATE {$this->table}
                SET status = ?, attempts = 0, last_error = NULL
                WHERE status = ?
                AND attempts < ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            NOTIFICATION_STATUS_PENDING,
            NOTIFICATION_STATUS_FAILED,
            MAX_NOTIFICATION_ATTEMPTS
        ]);

        return $stmt->rowCount();
    }
}
