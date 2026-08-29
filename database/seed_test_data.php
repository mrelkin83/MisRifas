<?php
/**
 * Seeder de datos de prueba — SOLO CLI.
 *
 *   php database/seed_test_data.php [--force]
 *
 * Crea: 1 vendor extra, 3 compradores, 6 rifas (4 activas con distintos
 * porcentajes de venta, 1 completada con ganador para /ganadores, 1 draft),
 * boletos generados con la misma lógica de TicketService (identificador
 * secuencial + oportunidades random únicas), reservas activas y pagos
 * pendientes para el panel "Pagos Recibidos".
 *
 * Con --force borra primero las rifas/boletos/pagos/ganadores existentes
 * (NO toca vendors ni system_settings).
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Solo ejecutable por CLI');
}

require __DIR__ . '/../config/database.php';

$force = in_array('--force', $argv, true);
$db = Database::getInstance()->getConnection();

$existing = (int)$db->query('SELECT COUNT(*) FROM raffles')->fetchColumn();
if ($existing > 0 && !$force) {
    exit("Ya hay {$existing} rifas en la BD. Usa --force para reemplazar los datos de prueba.\n");
}

function uuid4(): string {
    $d = random_bytes(16);
    $d[6] = chr(ord($d[6]) & 0x0f | 0x40);
    $d[8] = chr(ord($d[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}

/** Próximo día de semana (monday..sunday) a las HH:MM:SS, N semanas adelante. */
function nextDraw(string $dayOfWeek, string $time, int $weeksAhead = 0): string {
    $ts = strtotime("next {$dayOfWeek}");
    if ($weeksAhead > 0) $ts = strtotime("+{$weeksAhead} week", $ts);
    return date('Y-m-d', $ts) . ' ' . $time;
}

/** Réplica de TicketService::generateTicketsForRaffle (identificador secuencial
 *  con padding + oportunidades random únicas del pool barajado). */
function buildTickets(int $digits, int $opportunities): array {
    $maxNumbers = 10 ** $digits;
    $totalTickets = (int)floor($maxNumbers / $opportunities);
    $format = '%0' . $digits . 'd';
    $pool = [];
    for ($i = 0; $i < $maxNumbers; $i++) $pool[] = sprintf($format, $i);
    shuffle($pool);

    $tickets = [];
    $idx = 0;
    $idFormat = '%0' . strlen((string)$totalTickets) . 'd';
    for ($i = 1; $i <= $totalTickets; $i++) {
        $opps = [];
        for ($j = 0; $j < $opportunities; $j++) $opps[] = $pool[$idx++];
        $tickets[] = ['number' => sprintf($idFormat, $i), 'opps' => $opps];
    }
    return $tickets;
}

$db->beginTransaction();

try {
    if ($force) {
        foreach (['raffle_winners', 'payments', 'tickets', 'raffle_images', 'raffles'] as $t) {
            $db->exec("DELETE FROM {$t}");
        }
        echo "Datos anteriores de rifas eliminados (--force).\n";
    }

    // ── Vendor de prueba ──────────────────────────────────────────
    $vendorEmail = 'vendedor@test.com';
    $vendorId = $db->query("SELECT id FROM vendors WHERE email = " . $db->quote($vendorEmail))->fetchColumn();
    if (!$vendorId) {
        $db->prepare("
            INSERT INTO vendors (slug, business_name, legal_name, email, password_hash, phone, city, department, role, status)
            VALUES ('rifas-el-costeno', 'Rifas El Costeño', 'Carlos Mendoza', ?, ?, '3005551234', 'Barranquilla', 'Atlántico', 'vendor', 'active')
        ")->execute([$vendorEmail, password_hash('Prueba123!', PASSWORD_DEFAULT)]);
        $vendorId = (int)$db->lastInsertId();
    }
    $adminVendorId = (int)($db->query("SELECT id FROM vendors WHERE role = 'super_admin' ORDER BY id LIMIT 1")->fetchColumn() ?: $vendorId);

    // ── Compradores ───────────────────────────────────────────────
    $buyersDef = [
        ['María González',  '3011234567', 'Barranquilla', 'Atlántico'],
        ['Juan Pérez',      '3029876543', 'Bogotá',       'Cundinamarca'],
        ['Laura Ramírez',   '3155550001', 'Bucaramanga',  'Santander'],
    ];
    $buyerIds = [];
    $insUser = $db->prepare("
        INSERT INTO users (unique_id, name, phone_whatsapp, role, active, city, department)
        VALUES (?, ?, ?, 'buyer', 1, ?, ?)
    ");
    foreach ($buyersDef as [$name, $phone, $city, $dept]) {
        $id = $db->query("SELECT id FROM users WHERE phone_whatsapp = " . $db->quote($phone))->fetchColumn();
        if (!$id) {
            $insUser->execute([uuid4(), $name, $phone, $city, $dept]);
            $id = (int)$db->lastInsertId();
        }
        $buyerIds[] = (int)$id;
    }

    // ── Definición de rifas ───────────────────────────────────────
    // [nombre, desc, ciudad, depto, precio, digits, opps, lottery_id, weeksAhead, status, %vendido, %reservado, vendor, seedImg]
    $rafflesDef = [
        ['Moto Yamaha NMAX 155', 'Moto 0km modelo 2026, papeles al día y casco de regalo. Entrega inmediata al ganador.',
            'Barranquilla', 'Atlántico', 10000, 2, 1, 1, 0, 'active', 0.72, 0.06, $vendorId, 'moto-nmax'],
        ['iPhone 16 Pro Max 256GB', 'Nuevo, sellado, con factura y garantía Apple de 1 año. Color titanio.',
            'Medellín', 'Antioquia', 5000, 2, 1, 3, 0, 'active', 0.45, 0.04, $vendorId, 'iphone-pro'],
        ['Carro Chevrolet Onix 0km', 'Sedán 0km full equipo, matrícula incluida. El premio más grande de la plataforma.',
            'Bogotá', 'Cundinamarca', 20000, 3, 1, 9, 1, 'active', 0.18, 0.02, $adminVendorId, 'chevrolet-onix'],
        ['Nevera Samsung + TV 55"', 'Combo hogar: nevera No Frost 384L y Smart TV Crystal UHD 55 pulgadas.',
            'Cali', 'Valle del Cauca', 8000, 2, 1, 7, 0, 'active', 0.90, 0.05, $vendorId, 'combo-hogar'],
        ['PlayStation 5 + 2 controles', 'PS5 slim digital con dos controles DualSense y 3 juegos. ¡Ya entregada a su ganadora!',
            'Bucaramanga', 'Santander', 6000, 2, 1, 10, -1, 'completed', 1.00, 0.00, $vendorId, 'playstation-5'],
        ['Viaje a San Andrés x2', 'Plan todo incluido 4 días / 3 noches para dos personas. (Borrador, aún sin publicar).',
            'Cartagena', 'Bolívar', 15000, 2, 1, 4, 2, 'draft', 0.00, 0.00, $vendorId, 'san-andres'],
    ];

    $insRaffle = $db->prepare("
        INSERT INTO raffles (name, description, image_url, city, department, scope, whatsapp_contact,
            responsible_person, ticket_price, total_tickets, digits, draw_date, lottery_id, opportunities,
            winning_mode, status, views, shares, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, 'departamental', '3005551234', 'Carlos Mendoza', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insTicket = $db->prepare("
        INSERT INTO tickets (raffle_id, ticket_number, opportunities, status, user_id, reserved_at, reserved_until, paid_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insImage = $db->prepare("INSERT INTO raffle_images (raffle_id, image_url, is_primary, sort_order) VALUES (?, ?, ?, ?)");
    $insPayment = $db->prepare("
        INSERT INTO payments (user_id, raffle_id, ticket_id, amount, payment_method, transaction_reference, transaction_status)
        VALUES (?, ?, ?, ?, ?, ?, 'pending')
    ");

    $lotteries = [];
    foreach ($db->query('SELECT id, day_of_week, draw_time FROM lotteries') as $l) {
        $lotteries[(int)$l['id']] = $l;
    }

    $summary = [];
    $completedInfo = null;

    foreach ($rafflesDef as $def) {
        [$name, $desc, $city, $dept, $price, $digits, $opps, $lotteryId, $weeks, $status, $pctPaid, $pctReserved, $createdBy, $imgSeed] = $def;

        $lot = $lotteries[$lotteryId];
        $drawDate = $weeks >= 0
            ? nextDraw($lot['day_of_week'], $lot['draw_time'], $weeks)
            : date('Y-m-d', strtotime("last {$lot['day_of_week']}")) . ' ' . $lot['draw_time']; // completada: sorteo pasado

        $tickets = buildTickets($digits, $opps);
        $total = count($tickets);
        $winningMode = 'last_' . $digits;
        $imageUrl = "https://picsum.photos/seed/misrifas-{$imgSeed}/800/600";

        $insRaffle->execute([
            $name, $desc, $imageUrl, $city, $dept, $price, $total, $digits, $drawDate,
            $lotteryId, $opps, $winningMode, $status, rand(80, 950), rand(5, 120), $createdBy,
            date('Y-m-d H:i:s', strtotime('-' . rand(3, 25) . ' days')),
        ]);
        $raffleId = (int)$db->lastInsertId();

        for ($i = 1; $i <= 3; $i++) {
            $insImage->execute([$raffleId, "https://picsum.photos/seed/misrifas-{$imgSeed}-{$i}/800/600", $i === 1 ? 1 : 0, $i]);
        }

        // Repartir estados: primero pagados, luego reservados, resto libres,
        // sobre un orden barajado para que no queden en bloque.
        $order = range(0, $total - 1);
        shuffle($order);
        $paidCount = (int)round($total * $pctPaid);
        $reservedCount = (int)round($total * $pctReserved);

        $paidSet = array_slice($order, 0, $paidCount);
        $reservedSet = array_slice($order, $paidCount, $reservedCount);
        $paidLookup = array_flip($paidSet);
        $reservedLookup = array_flip($reservedSet);

        $ticketIdByIndex = [];
        foreach ($tickets as $idx => $t) {
            $isPaid = isset($paidLookup[$idx]);
            $isReserved = isset($reservedLookup[$idx]);
            $buyer = ($isPaid || $isReserved) ? $buyerIds[array_rand($buyerIds)] : null;
            $insTicket->execute([
                $raffleId,
                $t['number'],
                json_encode($t['opps']),
                $isPaid ? 'paid' : ($isReserved ? 'reserved' : 'available'),
                $buyer,
                $isReserved ? date('Y-m-d H:i:s') : null,
                $isReserved ? date('Y-m-d H:i:s', strtotime('+15 minutes')) : null,
                $isPaid ? date('Y-m-d H:i:s', strtotime('-' . rand(1, 20) . ' days')) : null,
            ]);
            $ticketIdByIndex[$idx] = (int)$db->lastInsertId();
        }

        // Pagos pendientes (para el panel "Pagos Recibidos") sobre los reservados
        $methods = ['nequi', 'bancolombia', 'daviplata', 'manual'];
        foreach (array_slice($reservedSet, 0, 3) as $k => $idx) {
            $buyer = $db->query("SELECT user_id FROM tickets WHERE id = " . $ticketIdByIndex[$idx])->fetchColumn();
            $insPayment->execute([
                $buyer, $raffleId, $ticketIdByIndex[$idx], $price,
                $methods[$k % count($methods)], 'TEST-' . strtoupper(substr(uuid4(), 0, 8)),
            ]);
        }

        // Rifa completada: número ganador + fila en raffle_winners
        if ($status === 'completed') {
            $winnerIdx = $paidSet[array_rand($paidSet)];
            $winnerTicket = $tickets[$winnerIdx];
            $winningNumber = $winnerTicket['opps'][0];
            $winnerTicketId = $ticketIdByIndex[$winnerIdx];
            $winnerUserId = (int)$db->query("SELECT user_id FROM tickets WHERE id = {$winnerTicketId}")->fetchColumn();

            $db->prepare("UPDATE raffles SET winning_ticket_number = ? WHERE id = ?")
               ->execute([$winningNumber, $raffleId]);
            $db->prepare("
                INSERT INTO raffle_winners (raffle_id, ticket_id, user_id, winning_number, matched_opportunity,
                    prize_description, prize_delivered, prize_delivered_at, notified, notified_at)
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), 1, NOW())
            ")->execute([$raffleId, $winnerTicketId, $winnerUserId, $winningNumber, $winningNumber,
                'PlayStation 5 slim + 2 controles DualSense + 3 juegos']);
            $completedInfo = "boleta {$winnerTicket['number']} (número {$winningNumber})";
        }

        $summary[] = sprintf('  #%d %-28s %-9s %4d boletos, %3d%% vendido', $raffleId, $name, $status, $total, (int)($pctPaid * 100));
    }

    $db->commit();

    echo "Datos de prueba insertados:\n" . implode("\n", $summary) . "\n\n";
    echo "Vendor de prueba: {$vendorEmail} / Prueba123!\n";
    echo "Compradores (consultar boletas por WhatsApp): 3011234567, 3029876543, 3155550001\n";
    if ($completedInfo) echo "Ganadora de la PS5: {$completedInfo}\n";
} catch (Throwable $e) {
    $db->rollBack();
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
}
