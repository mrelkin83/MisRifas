<?php

/**
 * Cron Job: Enviar Recordatorios de Pago
 *
 * Envía recordatorios automáticos a usuarios con:
 * - Pagos pendientes con más de 1 hora
 * - Comisiones próximas a vencer (7, 5, 3, 1 días antes)
 * - Sorteos próximos (1 día antes)
 *
 * Frecuencia recomendada: Cada 1 hora
 * Crontab: 0 * * * * php /path/to/cron/send_reminders.php
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/utils/Logger.php';
require_once __DIR__ . '/../api/repositories/PaymentRepository.php';
require_once __DIR__ . '/../api/repositories/CommissionRepository.php';
require_once __DIR__ . '/../api/repositories/NotificationRepository.php';

// Verificar que se ejecute desde CLI
if (php_sapi_name() !== 'cli') {
    die('Este script solo puede ejecutarse desde CLI');
}

$startTime = microtime(true);

Logger::cron('send_reminders', true, ['started_at' => date('Y-m-d H:i:s')]);

try {
    $paymentRepo = new PaymentRepository();
    $commissionRepo = new CommissionRepository();
    $notificationRepo = new NotificationRepository();

    $stats = [
        'payment_reminders' => 0,
        'commission_reminders' => 0,
        'draw_reminders' => 0
    ];

    // 1. RECORDATORIOS DE PAGO PENDIENTE (más de 1 hora sin pagar)
    echo "📋 Buscando pagos pendientes...\n";
    $pendingPayments = $paymentRepo->getPendingPayments(1); // Más de 1 hora

    foreach ($pendingPayments as $payment) {
        // Verificar que el ticket aún esté reservado
        $ticketSql = "SELECT * FROM tickets WHERE id = ? AND status = ?";
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare($ticketSql);
        $stmt->execute([$payment['ticket_id'], TICKET_STATUS_RESERVED]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            continue; // Ticket ya no está reservado
        }

        // Calcular tiempo restante
        $reservedUntil = strtotime($ticket['reserved_until']);
        $now = time();
        $hoursRemaining = ($reservedUntil - $now) / 3600;

        // Solo enviar si aún tiene tiempo
        if ($hoursRemaining > 0) {
            $notificationRepo->notifyPaymentReminder($payment['user_id'], [
                'ticket_number' => $ticket['ticket_number'],
                'raffle_title' => $payment['raffle_title'],
                'amount' => $payment['amount'],
                'reference' => $payment['reference'],
                'reserved_until' => $ticket['reserved_until'],
                'user_phone' => $payment['user_phone']
            ]);

            $stats['payment_reminders']++;
        }
    }

    echo "✅ Recordatorios de pago: {$stats['payment_reminders']}\n";

    // 2. RECORDATORIOS DE COMISIÓN PRÓXIMA A VENCER
    echo "\n📋 Buscando comisiones próximas a vencer...\n";

    // Días para enviar recordatorios
    $reminderDays = [7, 5, 3, 1];

    foreach ($reminderDays as $days) {
        $raffles = $commissionRepo->getRafflesWithUpcomingCommission($days);

        foreach ($raffles as $raffle) {
            // Verificar si ya se envió recordatorio hoy para estos días
            $today = date('Y-m-d');
            $checkSql = "SELECT COUNT(*) as count FROM notifications
                        WHERE user_id = ?
                        AND type = ?
                        AND JSON_EXTRACT(data, '$.raffle_id') = ?
                        AND DATE(created_at) = ?";

            $stmt = $db->prepare($checkSql);
            $stmt->execute([
                $raffle['created_by'],
                NOTIFICATION_TYPE_COMMISSION_DUE,
                $raffle['id'],
                $today
            ]);
            $result = $stmt->fetch();

            if ($result['count'] > 0) {
                continue; // Ya se envió hoy
            }

            // Enviar recordatorio
            $notificationRepo->notifyCommissionDue($raffle['created_by'], [
                'raffle_id' => $raffle['id'],
                'raffle_title' => $raffle['name'],
                'commission_amount' => $raffle['commission_amount'],
                'commission_due_date' => $raffle['commission_due_date'],
                'days_remaining' => $raffle['days_remaining'],
                'creator_phone' => $raffle['creator_phone'],
                'creator_email' => $raffle['creator_email']
            ]);

            $stats['commission_reminders']++;
        }
    }

    echo "✅ Recordatorios de comisión: {$stats['commission_reminders']}\n";

    // 3. RECORDATORIOS DE SORTEO PRÓXIMO (1 día antes)
    echo "\n📋 Buscando sorteos próximos...\n";

    $tomorrow = date('Y-m-d', strtotime('+1 day'));

    $drawSql = "SELECT DISTINCT r.id, r.name as title, r.draw_date, l.name as lottery_name,
                       u.id as user_id, u.phone_whatsapp as user_phone, u.email as user_email,
                       GROUP_CONCAT(t.ticket_number ORDER BY t.ticket_number) as user_tickets
                FROM raffles r
                INNER JOIN tickets t ON r.id = t.raffle_id
                INNER JOIN users u ON t.user_id = u.id
                LEFT JOIN lotteries l ON r.lottery_id = l.id
                WHERE DATE(r.draw_date) = ?
                AND t.status = ?
                AND r.status = ?
                GROUP BY r.id, u.id";

    $stmt = $db->prepare($drawSql);
    $stmt->execute([$tomorrow, TICKET_STATUS_PAID, RAFFLE_STATUS_ACTIVE]);
    $upcomingDraws = $stmt->fetchAll();

    foreach ($upcomingDraws as $draw) {
        // Verificar si ya se envió hoy
        $checkSql = "SELECT COUNT(*) as count FROM notifications
                    WHERE user_id = ?
                    AND type = ?
                    AND JSON_EXTRACT(data, '$.raffle_id') = ?
                    AND DATE(created_at) = ?";

        $stmt = $db->prepare($checkSql);
        $stmt->execute([
            $draw['user_id'],
            NOTIFICATION_TYPE_DRAW_REMINDER,
            $draw['id'],
            date('Y-m-d')
        ]);
        $result = $stmt->fetch();

        if ($result['count'] > 0) {
            continue;
        }

        // Enviar recordatorio
        $tickets = explode(',', $draw['user_tickets']);
        $notificationRepo->notifyUpcomingDraw($draw['user_id'], [
            'raffle_id' => $draw['id'],
            'raffle_title' => $draw['title'],
            'draw_date' => $draw['draw_date'],
            'lottery_name' => $draw['lottery_name'],
            'user_tickets' => $tickets,
            'user_phone' => $draw['user_phone'],
            'user_email' => $draw['user_email']
        ]);

        $stats['draw_reminders']++;
    }

    echo "✅ Recordatorios de sorteo: {$stats['draw_reminders']}\n";

    // RESUMEN
    $executionTime = round(microtime(true) - $startTime, 2);
    $totalReminders = array_sum($stats);

    Logger::cron('send_reminders', true, [
        'total_reminders' => $totalReminders,
        'payment_reminders' => $stats['payment_reminders'],
        'commission_reminders' => $stats['commission_reminders'],
        'draw_reminders' => $stats['draw_reminders'],
        'execution_time' => $executionTime . 's',
        'completed_at' => date('Y-m-d H:i:s')
    ]);

    echo "\n📊 RESUMEN:\n";
    echo "  Total recordatorios: {$totalReminders}\n";
    echo "  Pagos: {$stats['payment_reminders']}\n";
    echo "  Comisiones: {$stats['commission_reminders']}\n";
    echo "  Sorteos: {$stats['draw_reminders']}\n";
    echo "  Tiempo: {$executionTime}s\n";

    exit(0);

} catch (Exception $e) {
    $executionTime = round(microtime(true) - $startTime, 2);

    Logger::cron('send_reminders', false, [
        'error' => $e->getMessage(),
        'execution_time' => $executionTime . 's',
        'failed_at' => date('Y-m-d H:i:s')
    ]);

    echo "❌ Error enviando recordatorios: {$e->getMessage()}\n";
    exit(1);
}
