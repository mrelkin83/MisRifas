<?php
/**
 * Harness de tests de regresión (integración) — sin dependencias externas.
 *
 * Golpea los endpoints HTTP reales del sitio local y verifica los flujos
 * críticos (reserva, aprobación de pago, aceptación de premio, gates de auth,
 * rate limiting). Crea sus propios fixtures marcados «__TEST__» y los LIMPIA
 * siempre al final (incluso si un test falla), para no ensuciar la BD.
 *
 * Uso:   php tests/run.php
 * Config: TEST_BASE_URL (por defecto http://localhost/MisRifas)
 *
 * NUNCA correr contra producción: aborta si la URL no es local.
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

define('TEST_BASE_URL', rtrim(getenv('TEST_BASE_URL') ?: 'http://localhost/MisRifas', '/'));

// Salvaguarda: solo local.
$host = parse_url(TEST_BASE_URL, PHP_URL_HOST);
if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
    fwrite(STDERR, "ABORTADO: los tests solo corren contra localhost (URL: " . TEST_BASE_URL . ")\n");
    exit(2);
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/utils/Auth.php';

/* ------------------------------------------------------------------ *
 *  Estado global del harness
 * ------------------------------------------------------------------ */
$GLOBALS['__tests']    = ['pass' => 0, 'fail' => 0, 'fails' => []];
$GLOBALS['__teardown'] = [];   // pila de closures de limpieza
$GLOBALS['__section']  = '';

function testdb(): PDO { return Database::getInstance()->getConnection(); }

/** Registra una limpieza que se ejecuta al final (LIFO). */
function onTeardown(callable $fn): void { $GLOBALS['__teardown'][] = $fn; }

function runTeardown(): void {
    // LIFO: deshacer en orden inverso al de creación (respeta FKs).
    foreach (array_reverse($GLOBALS['__teardown']) as $fn) {
        try { $fn(); } catch (\Throwable $e) { fwrite(STDERR, "  [teardown] " . $e->getMessage() . "\n"); }
    }
    $GLOBALS['__teardown'] = [];
}

/* ------------------------------------------------------------------ *
 *  Aserciones / reporte
 * ------------------------------------------------------------------ */
function section(string $name): void {
    $GLOBALS['__section'] = $name;
    echo "\n\033[1m== $name ==\033[0m\n";
}

function pass(string $msg): void {
    $GLOBALS['__tests']['pass']++;
    echo "  \033[32m✓\033[0m $msg\n";
}

function fail(string $msg, $extra = null): void {
    $GLOBALS['__tests']['fail']++;
    $line = ($GLOBALS['__section'] ? "[{$GLOBALS['__section']}] " : '') . $msg;
    $GLOBALS['__tests']['fails'][] = $line;
    echo "  \033[31m✗ $msg\033[0m\n";
    if ($extra !== null) echo "      " . (is_string($extra) ? $extra : json_encode($extra)) . "\n";
}

function check(bool $cond, string $msg, $extra = null): bool {
    if ($cond) { pass($msg); return true; }
    fail($msg, $extra); return false;
}

function assertHttp(int $expected, array $res, string $msg): bool {
    return check($res['code'] === $expected, $msg . " (HTTP $expected)",
        "esperado $expected, obtenido {$res['code']}: " . substr(json_encode($res['json'] ?? $res['raw']), 0, 200));
}

/* ------------------------------------------------------------------ *
 *  Cliente HTTP (curl)
 * ------------------------------------------------------------------ */
function httpReq(string $method, string $path, ?array $body = null, ?string $token = null): array {
    $url = TEST_BASE_URL . $path;
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];
    if ($body !== null) $headers[] = 'Content-Type: application/json';
    if ($token !== null) $headers[] = 'Authorization: Bearer ' . $token;
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_POSTFIELDS     => $body !== null ? json_encode($body) : null,
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'raw' => $raw, 'json' => json_decode((string)$raw, true)];
}

function httpGet(string $p, ?string $token = null): array  { return httpReq('GET',  $p, null, $token); }
function httpPost(string $p, array $b = [], ?string $token = null): array { return httpReq('POST', $p, $b, $token); }

/* ------------------------------------------------------------------ *
 *  Fixtures (todos con marca __TEST__ y auto-limpieza)
 * ------------------------------------------------------------------ */

/** Crea una rifa de prueba y sus boletos (00..$nTickets-1). Devuelve el id. */
function fxRaffle(array $opts = []): int {
    $db = testdb();
    $drawDate = $opts['draw_date'] ?? date('Y-m-d H:i:s', strtotime('+7 days'));
    $createdBy = $opts['created_by'] ?? 5; // vendor de prueba por defecto
    $nTickets  = $opts['tickets'] ?? 10;
    $lotteryId = $opts['lottery_id'] ?? 1;

    $stmt = $db->prepare("INSERT INTO raffles
        (name, description, image_url, city, whatsapp_contact, responsible_person,
         ticket_price, total_tickets, digits, draw_date, lottery_id, opportunities,
         winning_mode, created_by, status)
        VALUES ('__TEST__ Rifa', '__TEST__', 'https://placehold.co/1x1', 'Bogotá',
                '3000000000', '__TEST__', ?, ?, 2, ?, ?, 1, 'last_2', ?, 'active')");
    $stmt->execute([$opts['price'] ?? 1000, $nTickets, $drawDate, $lotteryId, $createdBy]);
    $raffleId = (int)$db->lastInsertId();

    if ($nTickets > 0) {
        $ins = $db->prepare("INSERT INTO tickets (raffle_id, ticket_number, opportunities, status) VALUES (?,?,?, 'available')");
        for ($i = 0; $i < $nTickets; $i++) {
            $num = str_pad((string)$i, 2, '0', STR_PAD_LEFT);
            $ins->execute([$raffleId, $num, json_encode([$num])]);
        }
    }

    onTeardown(function () use ($db, $raffleId) {
        $db->prepare("DELETE FROM payments WHERE raffle_id = ? OR ticket_id IN (SELECT id FROM tickets WHERE raffle_id = ?)")->execute([$raffleId, $raffleId]);
        $db->prepare("DELETE FROM raffle_winners WHERE raffle_id = ?")->execute([$raffleId]);
        foreach (['numero_reservas', 'tickets', 'raffles'] as $t) {
            $col = $t === 'raffles' ? 'id' : 'raffle_id';
            $db->prepare("DELETE FROM {$t} WHERE {$col} = ?")->execute([$raffleId]);
        }
    });
    return $raffleId;
}

/** Crea un usuario comprador de prueba. Devuelve ['id'=>, 'phone'=>]. */
function fxBuyer(?string $phone = null): array {
    $db = testdb();
    $phone = $phone ?: ('39' . random_int(10000000, 99999999));
    $uuid = sprintf('%s-%s-%s-%s-%s', bin2hex(random_bytes(4)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(6)));
    // Email incluido: es el canal por defecto de notificación de resultados.
    $stmt = $db->prepare("INSERT INTO users (unique_id, name, phone_whatsapp, email) VALUES (?, '__TEST__ Comprador', ?, ?)");
    $stmt->execute([$uuid, $phone, 'test-' . $phone . '@test.local']);
    $id = (int)$db->lastInsertId();
    onTeardown(function () use ($db, $id) {
        // Limpiar referencias primero para que el borrado no dependa del orden
        // de teardown (FKs ON DELETE RESTRICT sobre users).
        foreach (['raffle_winners', 'notifications', 'payments'] as $t) {
            try { $db->prepare("DELETE FROM {$t} WHERE user_id = ?")->execute([$id]); } catch (\Throwable $e) {}
        }
        try { $db->prepare("UPDATE tickets SET user_id = NULL WHERE user_id = ?")->execute([$id]); } catch (\Throwable $e) {}
        $db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
    });
    return ['id' => $id, 'phone' => $phone];
}

/**
 * Deja un boleto de la rifa en estado 'reserved' para un comprador, con su fila
 * en numero_reservas y un pago 'pending'. Devuelve ['ticket_id','payment_id'].
 * Simula el estado tras una reserva + comprobante subido, listo para aprobar.
 */
function fxPendingPayment(int $raffleId, string $numero, int $buyerId, float $amount = 1000): array {
    $db = testdb();
    $expires = date('Y-m-d H:i:s', strtotime('+10 min'));
    $db->prepare("UPDATE tickets SET status='reserved', user_id=?, reserved_at=NOW(), reserved_until=? WHERE raffle_id=? AND ticket_number=?")
       ->execute([$buyerId, $expires, $raffleId, $numero]);
    $ticketId = (int)$db->query("SELECT id FROM tickets WHERE raffle_id=$raffleId AND ticket_number=" . $db->quote($numero))->fetchColumn();

    $reservationId = 'TEST-' . bin2hex(random_bytes(6));
    $db->prepare("INSERT INTO numero_reservas (raffle_id, numero, estado, user_id, reservation_id, reserved_at, expires_at) VALUES (?,?, 'RESERVADO', ?, ?, NOW(), ?)")
       ->execute([$raffleId, $numero, $buyerId, $reservationId, $expires]);

    $ref = 'TESTREF-' . bin2hex(random_bytes(6));
    $db->prepare("INSERT INTO payments (user_id, raffle_id, ticket_id, amount, transaction_reference, transaction_status) VALUES (?,?,?,?,?, 'pending')")
       ->execute([$buyerId, $raffleId, $ticketId, $amount, $ref]);
    $paymentId = (int)$db->lastInsertId();
    // La limpieza de payments/numero_reservas/tickets la cubre el teardown de la rifa.
    return ['ticket_id' => $ticketId, 'payment_id' => $paymentId];
}

/** Ejecuta un script de cron por CLI y devuelve ['rc'=>int,'out'=>string]. */
function runCron(string $relativePath): array {
    $php  = PHP_BINARY ?: 'php';
    $path = __DIR__ . '/../' . ltrim($relativePath, '/');
    $out = [];
    $rc = 0;
    exec(escapeshellarg($php) . ' ' . escapeshellarg($path) . ' 2>&1', $out, $rc);
    return ['rc' => $rc, 'out' => implode("\n", $out)];
}

/** Mintea un token válido para un vendor/super_admin y lo restaura al final. */
function fxToken(int $vendorId): string {
    $db = testdb();
    $prev = $db->query("SELECT auth_token, auth_token_expires FROM vendors WHERE id = " . (int)$vendorId)->fetch(PDO::FETCH_ASSOC);
    $token = bin2hex(random_bytes(32));
    $db->prepare("UPDATE vendors SET auth_token = ?, auth_token_expires = ? WHERE id = ?")
       ->execute([Auth::hashToken($token), date('Y-m-d H:i:s', strtotime('+1 hour')), $vendorId]);
    onTeardown(function () use ($db, $vendorId, $prev) {
        $db->prepare("UPDATE vendors SET auth_token = ?, auth_token_expires = ? WHERE id = ?")
           ->execute([$prev['auth_token'] ?? null, $prev['auth_token_expires'] ?? null, $vendorId]);
    });
    return $token;
}
