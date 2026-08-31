<?php

declare(strict_types=1);

/**
 * Seeder de DATOS DE PRUEBA para validar los flujos reales en producción.
 *
 *   php tools/seed_pruebas.php           → crea el dataset (idempotente)
 *   php tools/seed_pruebas.php --limpiar → elimina TODO lo sembrado
 *
 * Todo queda marcado con emails @pruebas.misrifas.online y nombres [PRUEBA],
 * para poder identificarlo y limpiarlo después. Contraseña de TODAS las
 * cuentas: Pruebas2026*
 *
 * Solo CLI (tools/ además está bloqueado por .htaccess).
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Solo CLI');
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/services/TicketCode.php';

$db = Database::getInstance()->getConnection();
$PASS = password_hash('Pruebas2026*', PASSWORD_DEFAULT);
$MARK = '@pruebas.misrifas.online';

// ──────────────────────────── LIMPIEZA ────────────────────────────
if (in_array('--limpiar', $argv, true)) {
    $vids = $db->query("SELECT id FROM vendors WHERE email LIKE '%$MARK'")->fetchAll(PDO::FETCH_COLUMN);
    $uids = $db->query("SELECT id FROM users WHERE email LIKE '%$MARK'")->fetchAll(PDO::FETCH_COLUMN);
    $rids = $vids ? $db->query('SELECT id FROM raffles WHERE created_by IN (' . implode(',', array_map('intval', $vids)) . ')')->fetchAll(PDO::FETCH_COLUMN) : [];
    $in = fn(array $ids) => implode(',', array_map('intval', $ids)) ?: '0';
    $db->exec("DELETE FROM raffle_winners WHERE raffle_id IN ({$in($rids)})");
    $db->exec("DELETE FROM raffle_draws WHERE raffle_id IN ({$in($rids)})");
    $db->exec("DELETE FROM payments WHERE raffle_id IN ({$in($rids)})");
    $db->exec("DELETE FROM message_queue WHERE raffle_id IN ({$in($rids)})");
    $db->exec("DELETE FROM ticket_events WHERE raffle_id IN ({$in($rids)})");
    $db->exec("DELETE FROM numero_reservas WHERE raffle_id IN ({$in($rids)})");
    $db->exec("DELETE FROM tickets WHERE raffle_id IN ({$in($rids)})");
    $db->exec("DELETE FROM raffles WHERE id IN ({$in($rids)})");
    $db->exec("DELETE FROM tapazo_jugadores WHERE tapazo_id IN (SELECT id FROM tapazos WHERE created_by IN ({$in($vids)}))");
    $db->exec("DELETE FROM tapazos WHERE created_by IN ({$in($vids)})");
    $db->exec("DELETE FROM payments WHERE user_id IN ({$in($uids)})");
    $db->exec("DELETE FROM users WHERE id IN ({$in($uids)})");
    $db->exec("DELETE FROM vendors WHERE id IN ({$in($vids)})");
    echo "Datos de prueba eliminados (vendors: " . count($vids) . ", users: " . count($uids) . ", rifas: " . count($rids) . ")\n";
    exit(0);
}

// Idempotencia: si ya existe el primer vendedor, abortar con aviso.
$ya = $db->query("SELECT COUNT(*) FROM vendors WHERE email LIKE '%$MARK'")->fetchColumn();
if ($ya > 0) {
    die("Ya hay datos de prueba sembrados. Usa --limpiar primero si quieres regenerarlos.\n");
}

/** Próxima fecha en que juega la lotería. */
function proxFecha(PDO $db, int $lotteryId, string $desde = '+14 days'): string
{
    $day = $db->query('SELECT day_of_week FROM lotteries WHERE id = ' . $lotteryId)->fetchColumn() ?: 'monday';
    $ts = strtotime($desde);
    while (strtolower(date('l', $ts)) !== $day) {
        $ts = strtotime('+1 day', $ts);
    }
    return date('Y-m-d', $ts) . ' 22:30:00';
}

function uuid(): string
{
    return sprintf('%s-%s-%s-%s-%s', bin2hex(random_bytes(4)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(6)));
}

$codigosUsados = [];
function boletaCode(PDO $db, array &$usados): string
{
    do {
        $c = TicketCode::generate();
    } while (isset($usados[$c]));
    $usados[$c] = true;
    return $c;
}

$db->beginTransaction();

// ──────────────────────────── VENDEDORES ────────────────────────────
$vendedores = [
    ['slug' => 'rifas-la-costena-prueba', 'business' => 'Rifas La Costeña [PRUEBA]', 'nombre' => 'Laura Martínez', 'doc' => '1043558721', 'email' => "laura$MARK", 'phone' => '3001110001', 'city' => 'Barranquilla', 'dep' => 'Atlántico'],
    ['slug' => 'sorteos-el-paisa-prueba', 'business' => 'Sorteos El Paisa [PRUEBA]', 'nombre' => 'Julián Restrepo', 'doc' => '71778812', 'email' => "julian$MARK", 'phone' => '3001110002', 'city' => 'Medellín', 'dep' => 'Antioquia'],
    ['slug' => 'fundacion-suenos-prueba', 'business' => 'Fundación Sueños [PRUEBA]', 'nombre' => 'Carmen Ortiz', 'doc' => '52447890', 'email' => "carmen$MARK", 'phone' => '3001110003', 'city' => 'Bogotá', 'dep' => 'Cundinamarca'],
];
$V = [];
$insV = $db->prepare("
    INSERT INTO vendors (slug, business_name, legal_name, document_type, document_number, email, password_hash,
                         phone, city, department, role, status, email_verified_at, payment_config, created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?, 'vendor', 'active', NOW(), ?, NOW())
");
foreach ($vendedores as $v) {
    $pc = json_encode(['mode' => 'manual', 'nequi_phone' => $v['phone'], 'daviplata_phone' => $v['phone'], 'breb_key' => '@' . explode('-', $v['slug'])[0], 'accepts_cash' => true]);
    $insV->execute([$v['slug'], $v['business'], $v['nombre'], 'CC', $v['doc'], $v['email'], $PASS, $v['phone'], $v['city'], $v['dep'], $pc]);
    $V[] = (int)$db->lastInsertId();
}

// ──────────────────────────── COMPRADORES ────────────────────────────
$compradores = [
    ['Carlos Gómez [PRUEBA]', '3102000001'], ['María Fernanda Ruiz [PRUEBA]', '3102000002'],
    ['Andrés Pineda [PRUEBA]', '3102000003'], ['Luisa Cárdenas [PRUEBA]', '3102000004'],
    ['Pedro Salazar [PRUEBA]', '3102000005'], ['Diana Mora [PRUEBA]', '3102000006'],
    ['Jorge Herrera [PRUEBA]', '3102000007'], ['Camila Torres [PRUEBA]', '3102000008'],
];
$U = [];
$insU = $db->prepare("
    INSERT INTO users (unique_id, name, phone_whatsapp, email, password_hash, role, active, email_verified_at, city, department, created_at)
    VALUES (?,?,?,?,?, 'buyer', 1, NOW(), 'Bogotá', 'Cundinamarca', NOW())
");
$uniqueIds = [];
foreach ($compradores as $i => [$nombre, $cel]) {
    $uid = uuid();
    $mailUser = strtolower(explode(' ', $nombre)[0]) . ($i + 1);
    $insU->execute([$uid, $nombre, $cel, "$mailUser$MARK", $PASS]);
    $U[] = (int)$db->lastInsertId();
    $uniqueIds[] = $uid;
}

// ──────────────────────────── RIFAS ────────────────────────────
$insR = $db->prepare("
    INSERT INTO raffles (name, description, image_url, city, department, whatsapp_contact, responsible_person,
                         ticket_price, total_tickets, digits, draw_date, cutoff_at, lottery_id, opportunities,
                         winning_mode, created_by, vendor_id, status, created_at)
    VALUES (?,?,?,?,?,?,?,?,?,2,?,?,?,1,'last_2',?,?,?, NOW())
");
$insT = $db->prepare("INSERT INTO tickets (raffle_id, ticket_number, opportunities, status, created_at) VALUES (?,?,?, 'available', NOW())");

function crearRifa(PDO $db, PDOStatement $insR, PDOStatement $insT, array $o): int
{
    $insR->execute([
        $o['name'], $o['desc'], $o['img'] ?? '/assets/images/placeholder.svg', $o['city'], $o['dep'],
        $o['wa'], $o['resp'], $o['price'], $o['n'], $o['draw'],
        date('Y-m-d H:i:s', strtotime($o['draw'] . ' -2 days')), $o['lottery'], $o['vendor'], $o['vendor'], $o['status'],
    ]);
    $rid = (int)$db->lastInsertId();
    for ($i = 0; $i < $o['n']; $i++) {
        $num = str_pad((string)$i, 2, '0', STR_PAD_LEFT);
        $insT->execute([$rid, $num, json_encode([$num])]);
    }
    return $rid;
}

$pagar = $db->prepare("
    UPDATE tickets SET status='paid', user_id=?, paid_at=DATE_SUB(NOW(), INTERVAL ? DAY),
           payment_method=?, ticket_code=?, issued_at=NOW(), buyer_name=?, buyer_phone=?
    WHERE raffle_id=? AND ticket_number=?
");
$insPago = $db->prepare("
    INSERT INTO payments (user_id, raffle_id, ticket_id, amount, payment_method, transaction_reference, transaction_status, verified_at, created_at)
    SELECT ?, ?, id, ?, ?, CONCAT('PRB-', id, '-', ?), 'completed', NOW(), NOW() FROM tickets WHERE raffle_id=? AND ticket_number=?
");

/** Marca pagados N números con compradores rotando y emite boleta. */
function venderNumeros(PDO $db, PDOStatement $pagar, PDOStatement $insPago, int $rid, array $nums, array $U, array $compradores, float $price, array &$codigos): array
{
    $codes = [];
    foreach ($nums as $k => $num) {
        $ui = $k % count($U);
        $code = boletaCode($db, $codigos);
        $metodo = ['nequi', 'daviplata', 'cash'][$k % 3];
        $pagar->execute([$U[$ui], rand(1, 9), $metodo, $code, $compradores[$ui][0], $compradores[$ui][1], $rid, $num]);
        $insPago->execute([$U[$ui], $rid, $price, $metodo === 'cash' ? 'cash' : $metodo, bin2hex(random_bytes(3)), $rid, $num]);
        $codes[$num] = $code;
    }
    return $codes;
}

$nn = fn(int $a, int $b) => array_map(fn($i) => str_pad((string)$i, 2, '0', STR_PAD_LEFT), range($a, $b));

// A) Rifa ACTIVA con estados variados (v1) — la de "probar de todo"
$rA = crearRifa($db, $insR, $insT, [
    'name' => 'Moto AKT 125 [PRUEBA]', 'desc' => "Rifa de prueba con boletos en todos los estados.\nPremio: Moto AKT 125 0km",
    'city' => 'Barranquilla', 'dep' => 'Atlántico', 'wa' => '3001110001', 'resp' => 'Laura Martínez',
    'price' => 5000, 'n' => 100, 'draw' => proxFecha($db, 1, '+18 days'), 'lottery' => 1, 'vendor' => $V[0], 'status' => 'active',
]);
$codesA = venderNumeros($db, $pagar, $insPago, $rA, $nn(0, 29), $U, $compradores, 5000, $codigosUsados);

// reservas VIGENTES (para probar pago/expiración)
$resv = $db->prepare("UPDATE tickets SET status='reserved', user_id=?, reserved_at=NOW(), reserved_until=DATE_ADD(NOW(), INTERVAL 45 MINUTE) WHERE raffle_id=? AND ticket_number=?");
$insNR = $db->prepare("INSERT INTO numero_reservas (raffle_id, numero, estado, user_id, reservation_id, reserved_at, expires_at, payment_suffix) VALUES (?,?,'RESERVADO',?,?,NOW(),DATE_ADD(NOW(), INTERVAL 45 MINUTE),?)");
foreach (['40', '41', '42'] as $k => $num) {
    $resv->execute([$U[$k], $rA, $num]);
    $insNR->execute([$rA, $num, $U[$k], 'RES-PRB-' . bin2hex(random_bytes(6)), (int)$num]);
}

// pagos POR CONFIRMAR (pending_review, para probar aprobar/rechazar en panel)
$prv = $db->prepare("UPDATE tickets SET status='pending_review', user_id=?, reserved_at=NOW(), reserved_until=DATE_ADD(NOW(), INTERVAL 45 MINUTE) WHERE raffle_id=? AND ticket_number=?");
$insPagoPend = $db->prepare("
    INSERT INTO payments (user_id, raffle_id, ticket_id, amount, payment_method, transaction_reference, transaction_status, payment_gateway_response, created_at)
    SELECT ?, ?, id, ?, 'nequi', CONCAT('PRBPEND-', id), 'pending', ?, NOW() FROM tickets WHERE raffle_id=? AND ticket_number=?
");
foreach (['50', '51'] as $k => $num) {
    $prv->execute([$U[3 + $k], $rA, $num]);
    $rid2 = 'RES-PRB-' . bin2hex(random_bytes(6));
    $insNR->execute([$rA, $num, $U[3 + $k], $rid2, (int)$num]);
    $gw = json_encode(['proof_url' => null, 'proof_file' => null, 'proof_sha256' => null, 'proof_token' => null, 'flags' => [], 'method' => 'nequi', 'manual' => true, 'reservation_id' => $rid2]);
    $insPagoPend->execute([$U[3 + $k], $rA, 5000, $gw, $rA, $num]);
}

// apartados (held) del vendedor
$held = $db->prepare("UPDATE tickets SET status='held', held_by_vendor_id=?, holder_name=?, holder_phone=?, held_at=NOW(), held_note='Apartado de prueba' WHERE raffle_id=? AND ticket_number=?");
$held->execute([$V[0], 'Doña Rosa (vecina)', '3159990001', $rA, '60']);
$held->execute([$V[0], 'Don Álvaro (tienda)', '3159990002', $rA, '61']);

// B) Rifa activa CASI VENDIDA (v2)
$rB = crearRifa($db, $insR, $insT, [
    'name' => 'iPhone 15 128GB [PRUEBA]', 'desc' => "Casi agotada: quedan pocos números.\nPremio: iPhone 15 128GB nuevo",
    'city' => 'Medellín', 'dep' => 'Antioquia', 'wa' => '3001110002', 'resp' => 'Julián Restrepo',
    'price' => 10000, 'n' => 50, 'draw' => proxFecha($db, 9, '+10 days'), 'lottery' => 9, 'vendor' => $V[1], 'status' => 'active',
]);
venderNumeros($db, $pagar, $insPago, $rB, $nn(0, 44), $U, $compradores, 10000, $codigosUsados);

// C) Rifa COMPLETADA con ganador que ACEPTÓ — probar "Reportar entrega" (v1)
$rC = crearRifa($db, $insR, $insT, [
    'name' => 'TV Samsung 55" [PRUEBA]', 'desc' => "Sorteada: el ganador ya aceptó; falta reportar la entrega.\nPremio: TV Samsung 55\" 4K",
    'city' => 'Barranquilla', 'dep' => 'Atlántico', 'wa' => '3001110001', 'resp' => 'Laura Martínez',
    'price' => 8000, 'n' => 100, 'draw' => date('Y-m-d 22:30:00', strtotime('-5 days')), 'lottery' => 5, 'vendor' => $V[0], 'status' => 'completed',
]);
venderNumeros($db, $pagar, $insPago, $rC, $nn(0, 79), $U, $compradores, 8000, $codigosUsados);
$tkC = $db->query("SELECT id, user_id FROM tickets WHERE raffle_id=$rC AND ticket_number='37'")->fetch(PDO::FETCH_ASSOC);
$db->exec("INSERT INTO raffle_draws (raffle_id, attempt, lottery_id, draw_date, winning_number, ticket_status, outcome, created_at)
           VALUES ($rC, 1, 5, '" . date('Y-m-d', strtotime('-5 days')) . "', '4437', 'paid', 'winner', NOW())");
$acceptTokC = bin2hex(random_bytes(32));
$db->prepare("
    INSERT INTO raffle_winners (raffle_id, ticket_id, user_id, winning_number, matched_opportunity, prize_description, acceptance_status,
                                acceptance_token, accepted_at, notified, notified_at, delivery_status, created_at)
    VALUES (?,?,?,?,?,?, 'accepted', NULL, DATE_SUB(NOW(), INTERVAL 3 DAY), 1, NOW(), 'pending', NOW())
")->execute([$rC, $tkC['id'], $tkC['user_id'], '4437', '37', 'TV Samsung 55" 4K']);

// D) Rifa COMPLETADA con ganador SIN aceptar — probar el enlace de aceptación (v2)
$rD = crearRifa($db, $insR, $insT, [
    'name' => 'Nevera LG 380L [PRUEBA]', 'desc' => "Sorteada: el ganador aún NO acepta el premio (enlace pendiente).\nPremio: Nevera LG 380 litros",
    'city' => 'Medellín', 'dep' => 'Antioquia', 'wa' => '3001110002', 'resp' => 'Julián Restrepo',
    'price' => 6000, 'n' => 100, 'draw' => date('Y-m-d 22:30:00', strtotime('-2 days')), 'lottery' => 10, 'vendor' => $V[1], 'status' => 'completed',
]);
venderNumeros($db, $pagar, $insPago, $rD, $nn(0, 59), $U, $compradores, 6000, $codigosUsados);
$tkD = $db->query("SELECT id, user_id FROM tickets WHERE raffle_id=$rD AND ticket_number='12'")->fetch(PDO::FETCH_ASSOC);
$db->exec("INSERT INTO raffle_draws (raffle_id, attempt, lottery_id, draw_date, winning_number, ticket_status, outcome, created_at)
           VALUES ($rD, 1, 10, '" . date('Y-m-d', strtotime('-2 days')) . "', '9012', 'paid', 'winner', NOW())");
$acceptTokD = bin2hex(random_bytes(32));
$db->prepare("
    INSERT INTO raffle_winners (raffle_id, ticket_id, user_id, winning_number, matched_opportunity, prize_description, acceptance_status,
                                acceptance_token, notified, notified_at, delivery_status, created_at)
    VALUES (?,?,?,?,?,?, 'pending', ?, 1, NOW(), 'pending', NOW())
")->execute([$rD, $tkD['id'], $tkD['user_id'], '9012', '12', 'Nevera LG 380 litros', $acceptTokD]);

// E) Rifa REPROGRAMADA una vez (v3) — historial público de intentos
$rE = crearRifa($db, $insR, $insT, [
    'name' => 'Bicicleta MTB [PRUEBA]', 'desc' => "Reprogramada: el número ganador no estaba vendido.\nPremio: Bicicleta todo terreno rin 29",
    'city' => 'Bogotá', 'dep' => 'Cundinamarca', 'wa' => '3001110003', 'resp' => 'Carmen Ortiz',
    'price' => 3000, 'n' => 100, 'draw' => proxFecha($db, 1, '+11 days'), 'lottery' => 1, 'vendor' => $V[2], 'status' => 'active',
]);
venderNumeros($db, $pagar, $insPago, $rE, $nn(0, 19), $U, $compradores, 3000, $codigosUsados);
$db->exec("INSERT INTO raffle_draws (raffle_id, attempt, lottery_id, draw_date, winning_number, ticket_status, outcome, rescheduled_to, created_at)
           VALUES ($rE, 1, 1, '" . date('Y-m-d', strtotime('-7 days')) . "', '7788', 'available', 'not_sold', '" . proxFecha($db, 1, '+11 days') . "', NOW())");
$db->exec("UPDATE raffles SET draw_rescheduled_count = 1 WHERE id = $rE");

// F) Rifa NUEVA sin ventas (v3) — probar compra desde cero
$rF = crearRifa($db, $insR, $insT, [
    'name' => 'Canasta Navideña [PRUEBA]', 'desc' => "Recién publicada, todos los números libres.\nPremio: Canasta navideña + ancheta premium",
    'city' => 'Bogotá', 'dep' => 'Cundinamarca', 'wa' => '3001110003', 'resp' => 'Carmen Ortiz',
    'price' => 2000, 'n' => 100, 'draw' => proxFecha($db, 13, '+20 days'), 'lottery' => 13, 'vendor' => $V[2], 'status' => 'active',
]);

// ──────────────────────────── TAPAZOS ────────────────────────────
$codT1 = strtoupper(bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(2)) . '-PRB1');
$codT2 = strtoupper(bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(2)) . '-PRB2');
$db->prepare("INSERT INTO tapazos (titulo, descripcion, cantidad_jugadores, valor_cupo, regla, fecha_hora_destape, estado, codigo_unico, created_by, created_at)
              VALUES ('Tapazo del Viernes [PRUEBA]', 'El más alto invita las cervezas', 6, 5000, 'alto_gana', DATE_ADD(NOW(), INTERVAL 3 DAY), 'creado', ?, ?, NOW())")
   ->execute([$codT1, $V[1]]);
$t1 = (int)$db->lastInsertId();
$insJ = $db->prepare("INSERT INTO tapazo_jugadores (tapazo_id, nombre, cerveza_numero, numero_tapa, orden_destape, created_at) VALUES (?,?,?,?,?, NOW())");
foreach ([['Carlos', 1], ['María', 2], ['Andrés', 4], ['Luisa', 6]] as [$n, $c]) {
    $insJ->execute([$t1, $n, $c, null, null]);
}
$db->prepare("INSERT INTO tapazos (titulo, descripcion, cantidad_jugadores, valor_cupo, regla, fecha_hora_destape, estado, codigo_unico, created_by, created_at)
              VALUES ('Tapazo Finalizado [PRUEBA]', 'Ya destapado, con resultados', 4, 0, 'bajo_gana', DATE_SUB(NOW(), INTERVAL 1 DAY), 'finalizado', ?, ?, NOW())")
   ->execute([$codT2, $V[1]]);
$t2 = (int)$db->lastInsertId();
foreach ([['Pedro', 1, 7, 1], ['Diana', 2, 3, 2], ['Jorge', 3, 9, 3], ['Camila', 4, 1, 4]] as [$n, $c, $tapa, $ord]) {
    $insJ->execute([$t2, $n, $c, $tapa, $ord]);
}

$db->commit();

// ──────────────────────────── RESUMEN ────────────────────────────
$base = rtrim(getenv('APP_URL') ?: 'https://misrifas.online', '/');
$fmt = fn(string $c) => substr($c, 0, 4) . '-' . substr($c, 4, 4) . '-' . substr($c, 8);
echo "══════════════════ DATOS DE PRUEBA SEMBRADOS ══════════════════\n\n";
echo "CONTRASEÑA de TODAS las cuentas: Pruebas2026*\n\n";
echo "VENDEDORES (login en /public/vendor/index.php?auth=login):\n";
foreach ($vendedores as $i => $v) {
    echo '  · ' . $v['business'] . ' — ' . $v['email'] . ' (id ' . $V[$i] . ")\n";
}
echo "\nCOMPRADORES (login en /public/dashboard.php; consulta por WhatsApp en /public/mis-boletos.php):\n";
foreach ($compradores as $i => [$n, $c]) {
    echo '  · ' . $n . ' — WhatsApp ' . $c . ' — código único ' . $uniqueIds[$i] . "\n";
}
echo "\nRIFAS:\n";
echo "  A) Moto AKT 125 [PRUEBA] (id $rA) — ACTIVA: 30 pagados, 3 reservados (40-42), 2 por confirmar (50-51), 2 apartados (60-61)\n";
echo "  B) iPhone 15 [PRUEBA] (id $rB) — ACTIVA casi vendida (45/50)\n";
echo "  C) TV Samsung [PRUEBA] (id $rC) — COMPLETADA, ganador boleto 37 ACEPTÓ → probar 'Reportar entrega' con evidencia\n";
echo "  D) Nevera LG [PRUEBA] (id $rD) — COMPLETADA, ganador boleto 12 SIN aceptar → enlace: $base/public/ganador-confirmar.php?t=$acceptTokD\n";
echo "  E) Bicicleta MTB [PRUEBA] (id $rE) — ACTIVA reprogramada 1 vez (historial público)\n";
echo "  F) Canasta Navideña [PRUEBA] (id $rF) — ACTIVA sin ventas (compra desde cero)\n";
echo "\nBOLETAS para VERIFICAR ($base/public/comprobar-boleta.php):\n";
echo '  · Boleto 00 de la Moto: ' . $fmt(array_values($codesA)[0]) . "\n";
echo '  · Boleto 05 de la Moto: ' . $fmt($codesA['05']) . "\n";
echo "\nTAPAZOS: abierto $base/tapazo/index.php?codigo=$codT1 · finalizado $base/tapazo/index.php?codigo=$codT2\n";
echo "\nRESULTADOS ($base/public/mis-boletos.php): busca con el WhatsApp 3102000001 — verá pendientes y su historial.\n";
echo "\nPara eliminar todo: php tools/seed_pruebas.php --limpiar\n";
