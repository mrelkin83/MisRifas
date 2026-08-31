<?php
/**
 * Perfil público del organizador (promt2.md §13.5).
 *
 * Su historial ES su reputación: rifas ejecutadas, reprogramaciones,
 * cancelaciones, entregas confirmadas por el ganador y disputas abiertas.
 * Nada se oculta — vale más que cualquier verificación de la plataforma.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/utils/RateLimiter.php';

if (!RateLimiter::check('organizador_' . ($_SERVER['REMOTE_ADDR'] ?? 'x'), 40, 5)) {
    http_response_code(429);
    die('Demasiadas consultas.');
}

$slug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string)($_GET['slug'] ?? '')));
$db = Database::getInstance()->getConnection();
$vendor = null;
$stats = null;
$activas = [];

if ($slug !== '') {
    $stmt = $db->prepare("SELECT id, business_name, city, department, created_at FROM vendors WHERE slug = ? AND status = 'active'");
    $stmt->execute([$slug]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
}
if ($vendor) {
    $vid = (int)$vendor['id'];
    $stats = $db->query("
        SELECT
            SUM(status = 'completed') AS ejecutadas,
            SUM(status = 'active') AS activas,
            SUM(status = 'cancelled') AS canceladas,
            COALESCE(SUM(draw_rescheduled_count), 0) AS reprogramaciones
        FROM raffles WHERE COALESCE(vendor_id, created_by) = $vid
    ")->fetch(PDO::FETCH_ASSOC);
    $entregas = $db->query("
        SELECT
            SUM(rw.delivery_status = 'delivery_confirmed') AS confirmadas,
            SUM(rw.delivery_status = 'delivery_reported') AS reportadas,
            SUM(rw.delivery_status = 'disputed') AS disputas
        FROM raffle_winners rw
        JOIN raffles r ON r.id = rw.raffle_id
        WHERE COALESCE(r.vendor_id, r.created_by) = $vid
    ")->fetch(PDO::FETCH_ASSOC);
    $stats = array_merge($stats ?: [], $entregas ?: []);

    $q = $db->prepare("
        SELECT id, name, ticket_price, draw_date FROM raffles
        WHERE COALESCE(vendor_id, created_by) = ? AND status = 'active'
        ORDER BY draw_date ASC LIMIT 10
    ");
    $q->execute([$vid]);
    $activas = $q->fetchAll(PDO::FETCH_ASSOC);
}

$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $vendor ? $e($vendor['business_name']) : 'Organizador' ?> | MisRifas</title>
    <meta name="theme-color" content="#0f172a">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { min-height:100vh; background:#0f172a; color:#e2e8f0; font-family:system-ui,-apple-system,'Segoe UI',sans-serif; padding:24px 16px; }
        .wrap { max-width:520px; margin:0 auto; }
        .card { background:#1e293b; border:1px solid rgba(255,255,255,.08); border-radius:20px; padding:24px; margin-bottom:16px; }
        h1 { font-size:22px; color:#fff; }
        .sub { color:#94a3b8; font-size:13.5px; margin-top:4px; }
        .grid { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-top:16px; }
        .stat { background:#0f172a; border-radius:12px; padding:12px 8px; text-align:center; }
        .stat .n { font-size:24px; font-weight:900; }
        .stat .l { font-size:10.5px; color:#94a3b8; text-transform:uppercase; letter-spacing:.5px; margin-top:2px; }
        .ok .n { color:#4ade80; } .warn .n { color:#fbbf24; } .bad .n { color:#f87171; } .plain .n { color:#e2e8f0; }
        .rifa { display:flex; justify-content:space-between; gap:10px; padding:10px 0; border-bottom:1px dashed rgba(255,255,255,.07); font-size:14px; }
        .rifa a { color:#fbbf24; text-decoration:none; font-weight:700; }
        .foot { text-align:center; font-size:13px; }
        .foot a { color:#94a3b8; }
        .aviso { font-size:12px; color:#64748b; margin-top:14px; line-height:1.5; }
    </style>
</head>
<body>
<div class="wrap">
<?php if (!$vendor): ?>
    <div class="card" style="text-align:center;">
        <h1>Organizador no encontrado</h1>
        <p class="sub">El perfil no existe o no está activo.</p>
    </div>
<?php else: ?>
    <div class="card">
        <h1>🎪 <?= $e($vendor['business_name']) ?></h1>
        <p class="sub"><?= $e(trim(($vendor['city'] ? $vendor['city'] . ', ' : '') . ($vendor['department'] ?? ''), ', ')) ?: 'Colombia' ?> · organiza desde <?= $e(date('m/Y', strtotime((string)$vendor['created_at']))) ?></p>
        <div class="grid">
            <div class="stat ok"><div class="n"><?= (int)($stats['ejecutadas'] ?? 0) ?></div><div class="l">Rifas ejecutadas</div></div>
            <div class="stat ok"><div class="n"><?= (int)($stats['confirmadas'] ?? 0) ?></div><div class="l">Entregas confirmadas</div></div>
            <div class="stat plain"><div class="n"><?= (int)($stats['activas'] ?? 0) ?></div><div class="l">Rifas activas</div></div>
            <div class="stat warn"><div class="n"><?= (int)($stats['reprogramaciones'] ?? 0) ?></div><div class="l">Reprogramaciones</div></div>
            <div class="stat <?= (int)($stats['canceladas'] ?? 0) > 0 ? 'bad' : 'plain' ?>"><div class="n"><?= (int)($stats['canceladas'] ?? 0) ?></div><div class="l">Canceladas</div></div>
            <div class="stat <?= (int)($stats['disputas'] ?? 0) > 0 ? 'bad' : 'plain' ?>"><div class="n"><?= (int)($stats['disputas'] ?? 0) ?></div><div class="l">Disputas abiertas</div></div>
        </div>
        <p class="aviso">📖 Este historial es público y lo construyen los propios sorteos: solo las entregas <strong>confirmadas por el ganador</strong> cuentan en verde, y las reprogramaciones y disputas nunca se ocultan.</p>
    </div>
    <?php if ($activas): ?>
    <div class="card">
        <h2 style="font-size:15px;margin-bottom:6px;">Rifas activas</h2>
        <?php foreach ($activas as $r): ?>
        <div class="rifa">
            <a href="<?= BASE_PATH ?>/public/raffle.php?id=<?= (int)$r['id'] ?>"><?= $e($r['name']) ?></a>
            <span style="color:#94a3b8;">$<?= number_format((float)$r['ticket_price'], 0, ',', '.') ?> · <?= $e(date('d/m/Y', strtotime((string)$r['draw_date']))) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>
    <p class="foot"><a href="<?= BASE_PATH ?>/public/index.php">← MisRifas</a> · <a href="<?= BASE_PATH ?>/public/ganadores.php">Hall de ganadores</a></p>
</div>
</body>
</html>
