<?php

/**
 * Cron: OpenClaw Notificaciones
 * Frecuencia: Todos los días a las 6:00 AM
 */

require_once __DIR__ . '/../config/database.php';

// Verificación CLI
if (php_sapi_name() !== 'cli') {
    die('Sólo ejecutable desde CLI');
}

try {
    $db = Database::getInstance()->getConnection();

    // Buscar rifas que terminaron el día de ayer y ya tienen resultados pero aún no han notificado
    $stmt = $db->prepare("
        SELECT r.id, r.name, res.winning_number, r.whatsapp_contact 
        FROM raffles r
        JOIN lottery_results res ON r.lottery_id = res.lottery_id 
             AND DATE(r.draw_date) = res.draw_date
        WHERE r.status = 'completed' AND DATE(r.draw_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
    ");
    $stmt->execute();
    $raffles = $stmt->fetchAll();

    foreach ($raffles as $raffle) {
        $raffleId = $raffle['id'];
        $winningNumber = $raffle['winning_number'];

        // Obtener todos los tickets vendidos de esta rifa
        $stmtTickets = $db->prepare("SELECT t.id, t.ticket_number, t.opportunities, u.name, u.phone_whatsapp FROM tickets t JOIN users u ON t.user_id = u.id WHERE t.raffle_id = ? AND t.status = 'paid'");
        $stmtTickets->execute([$raffleId]);
        $tickets = $stmtTickets->fetchAll();

        foreach ($tickets as $ticket) {
            $opportunities = json_decode($ticket['opportunities'], true);
            $userId = $ticket['user_id'] ?? null;
            $userPhone = $ticket['phone_whatsapp'] ?? null;
            $userName = $ticket['name'] ?? null;

            if (!$userPhone) continue;

            $isWinner = false;
            foreach ($opportunities as $opp) {
                // Validación simplificada: termina en lo mismo o exacto según dígitos
                if (str_ends_with($winningNumber, $opp)) {
                    $isWinner = true;
                    break;
                }
            }

            // Preparar mensaje
            $baseLink = getenv('APP_URL') ?: 'http://localhost';
            $verifyLink = $baseLink . "/mis-boletos?phone=" . urlencode($userPhone);

            $msg = "Hola {$userName},\n\n";
            $msg .= "El resultado del sorteo de '{$raffle['name']}' es: *{$winningNumber}*.\n";

            if ($isWinner) {
                $msg .= "🎉 ¡FELICITACIONES! Tienes un cartón ganador. Comunícate al {$raffle['whatsapp_contact']} para reclamar tu premio.\n";
            } else {
                $msg .= "En esta ocasión no ganaste. ¡Sigue intentándolo!\n";
            }

            $msg .= "\nVerifica aquí: {$verifyLink}";

            // Invocar OpenClaw API
            $openclawKey = getenv('OPENCLAW_API_KEY') ?: 'YOUR_OPENCLAW_KEY';
            $openclawUrl = 'https://api.openclaw.com/v1/messages'; // URL hipotética

            // Descomentar en entorno real:
            /*
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $openclawUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'to' => $userPhone,
                'message' => $msg
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $openclawKey,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);
            */

             echo "-> Mensaje simulado OpenClaw enviado a {$userPhone} para rifa {$raffleId}\n";
        }
    }

    echo "Proceso finalizado. " . count($raffles) . " rifas procesadas.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
