<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');

    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Logger.php';
require_once __DIR__ . '/../../api/utils/RateLimiter.php';
require_once __DIR__ . '/../../api/services/TicketStateMachine.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Metodo no permitido', null, 405);
}

// La compra es de invitado (reserve.php no exige login), por lo que este
// endpoint tampoco puede exigirlo. La proteccion real contra el fraude
// original (C1) es que YA NO marca el ticket como pagado directamente -
// solo registra el comprobante como 'pending' para revision humana via
// POST /api/admin/payments.php (action=approve). El rate limit evita
// spam de reportes falsos sobre boletos ajenos.
if (!RateLimiter::check('confirm_payment_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 10, 5)) {
    Response::rateLimitExceeded('Demasiados intentos. Intenta de nuevo en unos minutos.');
}

$db = null;

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        Response::error('JSON invalido');
    }

    $ticketId = (int)($input['ticket_id'] ?? 0);
    $reservationId = trim($input['reservation_id'] ?? '');
    $paymentMethod = strtolower(trim($input['payment_method'] ?? ''));
    $proof = $input['proof'] ?? null;

    if ($ticketId <= 0 && $reservationId === '') {
        Response::error('ID de ticket o de reserva invalido', null, 400);
    }

    $allowedMethods = ['nequi', 'bancolombia', 'daviplata', 'efecty', 'manual'];
    if (!in_array($paymentMethod, $allowedMethods)) {
        Response::error('Metodo de pago invalido. Permitidos: ' . implode(', ', $allowedMethods), null, 400);
    }

    $db = Database::getInstance()->getConnection();

    // reservation_id agrupa varios boletos reservados juntos (selector
    // multiple de raffle.php via create-reservation.php) - se resuelve a
    // la lista real de tickets uniendo con numero_reservas, ya que
    // `tickets` no guarda el reservation_id directamente.
    if ($reservationId !== '') {
        $stmt = $db->prepare("
            SELECT t.*, r.ticket_price, r.name as raffle_title
            FROM numero_reservas nr
            INNER JOIN tickets t ON t.raffle_id = nr.raffle_id AND t.ticket_number = nr.numero
            INNER JOIN raffles r ON t.raffle_id = r.id
            WHERE nr.reservation_id = ?
        ");
        $stmt->execute([$reservationId]);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($tickets)) {
            Response::error('Reserva no encontrada', null, 404);
        }
    } else {
        $stmt = $db->prepare("
            SELECT t.*, r.ticket_price, r.name as raffle_title
            FROM tickets t
            INNER JOIN raffles r ON t.raffle_id = r.id
            WHERE t.id = ?
        ");
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) {
            Response::error('Ticket no encontrado', null, 404);
        }

        $tickets = [$ticket];
    }

    foreach ($tickets as $t) {
        if ($t['status'] !== 'reserved') {
            Response::error('El boleto #' . $t['ticket_number'] . ' no esta reservado o ya fue pagado (estado: ' . $t['status'] . ')');
        }
    }

    $db->beginTransaction();

    // §16 — Subida segura + antifraude. El subtipo del data-URI no es de
    // fiar: whitelist en el regex, tope de 5 MB, validación del contenido
    // real (getimagesizefromstring) y RE-CODIFICACIÓN con GD (descarta
    // payloads embebidos). El archivo va a storage/comprobantes/ — FUERA del
    // directorio público — y se sirve solo por controlador con un token no
    // adivinable (api/vendor/proof.php?t=...).
    $proofUrl = null;      // legado; los nuevos usan proof_file + proof_token
    $proofFile = null;
    $proofSha = null;
    $proofToken = null;
    $flags = [];
    if ($proof && strpos($proof, 'data:image') === 0
        && preg_match('/^data:image\/(jpe?g|png|webp);base64,(.+)$/i', $proof, $matches)) {
        $imageData = base64_decode($matches[2], true);

        if ($imageData !== false && strlen($imageData) <= 5 * 1024 * 1024) {
            $imageInfo = @getimagesizefromstring($imageData);
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
            if ($imageInfo !== false && in_array($imageInfo['mime'], $allowedMimes, true)) {
                // §16.1: hash del archivo ORIGINAL — un mismo pantallazo usado
                // en otro boleto es sospechoso (se avisa, nunca auto-rechazo:
                // hay falsos positivos).
                $proofSha = hash('sha256', $imageData);
                $dupStmt = $db->prepare("
                    SELECT COUNT(*) FROM payments
                    WHERE payment_gateway_response LIKE ? AND ticket_id NOT IN (" . implode(',', array_map('intval', array_column($tickets, 'id')) ?: [0]) . ")
                ");
                $dupStmt->execute(['%' . $proofSha . '%']);
                if ((int)$dupStmt->fetchColumn() > 0) {
                    $flags[] = 'comprobante_repetido';
                }

                // §16.2: ventana temporal — si el JPEG declara en EXIF una
                // fecha de captura fuera del rango de la reserva, se señala.
                if ($imageInfo['mime'] === 'image/jpeg' && function_exists('exif_read_data')) {
                    $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($imageData));
                    $exifDate = $exif['DateTimeOriginal'] ?? $exif['DateTime'] ?? null;
                    if ($exifDate && ($ts = strtotime((string)$exifDate)) !== false) {
                        $desde = strtotime((string)($tickets[0]['reserved_at'] ?? 'now')) - 2 * 3600;
                        if ($ts < $desde || $ts > time() + 3600) {
                            $flags[] = 'fecha_fuera_de_rango';
                        }
                    }
                }

                $im = @imagecreatefromstring($imageData);
                if ($im !== false) {
                    $dir = __DIR__ . '/../../storage/comprobantes';
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    $proofFile = 'proof_' . time() . '_' . bin2hex(random_bytes(6)) . '.jpg';
                    imagejpeg($im, $dir . '/' . $proofFile, 85);
                    imagedestroy($im);
                    $proofToken = bin2hex(random_bytes(24));
                    // Compat con el panel/aviso: proof_url apunta al controlador.
                    $proofUrl = null;
                }
            }
        }
    }

    // §16.3: reputación del comprador — un celular con 2+ rechazos en 30 días
    // queda señalado (jamás bloqueado en silencio; decide el vendedor).
    $repStmt = $db->prepare("
        SELECT COUNT(*) FROM payments p
        JOIN users u ON u.id = p.user_id
        WHERE u.phone_whatsapp = (SELECT phone_whatsapp FROM users WHERE id = ?)
          AND p.transaction_status = 'failed'
          AND p.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $repStmt->execute([(int)$tickets[0]['user_id']]);
    if ((int)$repStmt->fetchColumn() >= 2) {
        $flags[] = 'comprador_con_rechazos';
    }

    // Un comprobante cubre todos los boletos de la reserva (selector
    // multiple) - se inserta una fila en `payments` por boleto, todas
    // apuntando al mismo comprobante, para que la revision/aprobacion del
    // vendedor (api/admin/payments.php) siga operando boleto por boleto
    // sin cambios.
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $gatewayData = json_encode([
        'proof_url' => $proofUrl,               // legado (rutas viejas)
        'proof_file' => $proofFile,             // storage/comprobantes/<file>
        'proof_sha256' => $proofSha,            // §16.1
        'proof_token' => $proofToken,           // capability para el controlador
        'flags' => $flags,                      // §16: señales, decide el vendedor
        'method' => $paymentMethod, 'manual' => true, 'reservation_id' => $reservationId ?: null,
    ]);
    $stmtPayment = $db->prepare("
        INSERT INTO payments (user_id, raffle_id, ticket_id, amount, payment_method, transaction_reference, transaction_status, payment_gateway_response, ip_address, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW(), NOW())
    ");

    // §11: bloquear en orden ascendente de id para evitar deadlocks.
    usort($tickets, fn($a, $b) => (int)$a['id'] <=> (int)$b['id']);

    $totalAmount = 0;
    $ticketIds = [];
    $userId = null;
    foreach ($tickets as $t) {
        $reference = 'PAY-' . $t['id'] . '-' . strtoupper(bin2hex(random_bytes(4)));
        $stmtPayment->execute([$t['user_id'], $t['raffle_id'], $t['id'], $t['ticket_price'], $paymentMethod, $reference, $gatewayData, $ip]);
        // Comprobante subido → el boleto pasa a revisión del vendedor (§7.2:
        // reserved → pending_review), en la MISMA transacción que la fila de
        // payments. El cron de expiración ya no lo puede liberar por TTL de
        // reserva (tiene su propio TTL de revisión).
        TicketStateMachine::transition($db, (int)$t['id'], 'pending_review', [
            'actor' => 'buyer', 'source' => 'web', 'actor_id' => (int)$t['user_id'],
            'reason' => 'comprobante subido',
            'detail' => ['method' => $paymentMethod, 'proof' => $proofFile ?: $proofUrl, 'reference' => $reference],
            'fields' => ['payment_method' => in_array($paymentMethod, ['nequi', 'daviplata', 'breb', 'cash'], true) ? $paymentMethod : null],
        ]);
        $totalAmount += (float)$t['ticket_price'];
        $ticketIds[] = (int)$t['id'];
        $userId = $t['user_id'];
    }

    $db->commit();

    // §10.1: aviso al VENDEDOR por WhatsApp con lo mínimo para decidir —
    // SIEMPRE después del commit, best-effort (el panel es la contingencia).
    try {
        $stmt = $db->prepare("
            SELECT COALESCE(r.vendor_id, r.created_by) AS vendor_id, r.id AS raffle_id, r.name, r.ticket_price,
                   v.phone AS vendor_phone, v.email AS vendor_email, v.notification_email AS vendor_email2, u.name AS buyer_name
            FROM tickets t
            JOIN raffles r ON r.id = t.raffle_id
            JOIN vendors v ON v.id = COALESCE(r.vendor_id, r.created_by)
            LEFT JOIN users u ON u.id = t.user_id
            WHERE t.id = ?
        ");
        $stmt->execute([$ticketIds[0]]);
        if ($ctx = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $ordenAmount = $totalAmount;
            if ($reservationId !== '') {
                $sfx = $db->prepare("SELECT payment_suffix FROM numero_reservas WHERE reservation_id = ? LIMIT 1");
                $sfx->execute([$reservationId]);
                $ordenAmount += (int)($sfx->fetchColumn() ?: 0);
            }
            $nums = array_map(fn($t2) => $t2['ticket_number'], $tickets);
            $appUrl = rtrim(getenv('APP_URL') ?: 'http://localhost', '/');
            $proofLink = $proofToken
                ? $appUrl . BASE_PATH . '/api/vendor/proof.php?t=' . $proofToken
                : ($proofUrl ? $appUrl . BASE_PATH . '/public' . $proofUrl : null);
            $flagLabels = [
                'comprobante_repetido' => '⚠️ El comprobante YA fue usado en otro boleto',
                'fecha_fuera_de_rango' => '⚠️ La foto declara una fecha fuera del rango de la reserva',
                'comprador_con_rechazos' => '⚠️ Este celular acumula 2+ rechazos en 30 días',
            ];
            $alertas = '';
            foreach ($flags as $f) {
                $alertas .= ($flagLabels[$f] ?? $f) . "\n";
            }
            $lineas = "🧾 *Nuevo pago por confirmar*\n"
                . 'Rifa: ' . $ctx['name'] . "\n"
                . 'Comprador: ' . ($ctx['buyer_name'] ?: 'Sin nombre') . "\n"
                . 'Número(s): ' . implode(', ', $nums) . "\n"
                . 'Monto exacto: $' . number_format($ordenAmount, 0, ',', '.') . "\n"
                . 'Hora: ' . date('d/m H:i') . "\n"
                . ($proofLink ? ('Comprobante: ' . $proofLink . "\n") : "Sin comprobante adjunto\n")
                . ($alertas !== '' ? "\n" . $alertas : '')
                . "\nResponde por cada boleto:\n";
            foreach ($tickets as $t2) {
                $lineas .= '✅ *SI ' . $t2['id'] . '*  |  ❌ *NO ' . $t2['id'] . '*  (boleto ' . $t2['ticket_number'] . ")\n";
            }
            $lineas .= "\nO confírmalo desde tu panel → Pagos por confirmar.";
            require_once __DIR__ . '/../whatsapp/notify.php';
            notificarWhatsAppVendor((int)$ctx['vendor_id'], (string)$ctx['vendor_phone'], $lineas);

            // También por CORREO ("el correo va siempre"): al correo de cuenta
            // Y al correo adicional de avisos si lo configuró en Mi Perfil.
            $destinos = array_unique(array_filter([
                trim((string)($ctx['vendor_email'] ?? '')),
                trim((string)($ctx['vendor_email2'] ?? '')),
            ]));
            foreach ($destinos as $correo) {
                $db->prepare("
                    INSERT INTO message_queue (raffle_id, vendor_id, recipient_phone, recipient_email,
                                               channel, message_type, subject, body_text, status, scheduled_at, created_at)
                    VALUES (?,?,?,?, 'email', 'payment_reminder', ?, ?, 'pending', NOW(), NOW())
                ")->execute([(int)$ctx['raffle_id'], (int)$ctx['vendor_id'],
                    (string)$ctx['vendor_phone'], $correo,
                    '🧾 Nuevo pago por confirmar — ' . $ctx['name'],
                    str_replace('*', '', $lineas)]);
            }
        }
    } catch (\Throwable $e) {
        Logger::error('Aviso WA de comprobante falló: ' . $e->getMessage());
    }

    Logger::activity('payment_proof_reported', (int)$userId, [
        'ticket_ids' => $ticketIds,
        'reservation_id' => $reservationId ?: null,
        'amount' => $totalAmount,
        'method' => $paymentMethod,
        'has_proof' => !empty($proofFile) || !empty($proofUrl)
    ]);

    Response::success([
        'ticket_ids' => $ticketIds,
        'status' => 'pending_review',
        'payment_status' => 'pending_review',
        'amount' => $totalAmount
    ], 'Comprobante recibido. Tu' . (count($ticketIds) > 1 ? 's boletos quedaran pagados' : ' boleto quedara pagado') . ' cuando el vendedor verifique el pago.');

} catch (Exception $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    Logger::exception($e);
    Response::serverError('Error al confirmar el pago');
}
