<?php
/**
 * Página pública de la boleta digital (promt2.md §9.4).
 * URL compartible: /public/boleta.php?c=XXXX-XXXX-XXXX
 *
 * Datos personales del comprador SIEMPRE enmascarados. Rate limit por IP
 * contra la enumeración del espacio de códigos.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/brand.php';
require_once __DIR__ . '/../api/utils/RateLimiter.php';
require_once __DIR__ . '/../api/services/Boleta.php';

if (!RateLimiter::check('boleta_' . ($_SERVER['REMOTE_ADDR'] ?? 'x'), 30, 5)) {
    http_response_code(429);
    die('Demasiadas consultas. Intenta en unos minutos.');
}

$codigo = (string)($_GET['c'] ?? '');
$db = Database::getInstance()->getConnection();
$b = Boleta::buscar($db, $codigo);
$anulada = $b === null && Boleta::fueAnulada($db, $codigo);

$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boleta <?= $b ? $e(TicketCode::format($b['ticket_code'])) : '' ?> | <?= plataforma_e() ?></title>
    <meta name="theme-color" content="#0f172a">
    <meta name="robots" content="noindex">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { min-height:100vh; background:#0f172a; color:#e2e8f0; font-family:system-ui,-apple-system,'Segoe UI',sans-serif; display:flex; align-items:center; justify-content:center; padding:20px; }
        .card { width:100%; max-width:440px; background:#1e293b; border:1px solid rgba(255,255,255,.08); border-radius:20px; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,.4); }
        .estado { padding:22px; text-align:center; font-size:22px; font-weight:900; letter-spacing:1px; }
        .estado--ok { background:rgba(34,197,94,.15); color:#4ade80; border-bottom:2px solid rgba(34,197,94,.4); }
        .estado--bad { background:rgba(239,68,68,.15); color:#f87171; border-bottom:2px solid rgba(239,68,68,.4); }
        .body { padding:24px; }
        .numero { text-align:center; margin:6px 0 18px; }
        .numero .n { font-size:72px; font-weight:900; color:#f59e0b; font-family:ui-monospace,monospace; line-height:1; }
        .numero .l { font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:2px; }
        .fila { display:flex; justify-content:space-between; gap:12px; padding:10px 0; border-bottom:1px dashed rgba(255,255,255,.07); font-size:14.5px; }
        .fila .k { color:#94a3b8; }
        .fila .v { font-weight:600; text-align:right; }
        .codigo { margin:18px 0 4px; text-align:center; background:#0f172a; border-radius:12px; padding:12px; font-family:ui-monospace,monospace; font-size:18px; font-weight:800; color:#4ade80; letter-spacing:2px; }
        .foot { padding:14px 24px 22px; text-align:center; font-size:12.5px; color:#64748b; }
        .foot a { color:#94a3b8; }
        .btn { display:block; margin:14px 24px 0; text-align:center; padding:13px; background:linear-gradient(135deg,#f59e0b,#d97706); color:#1c1305; border-radius:12px; font-weight:800; text-decoration:none; }
    </style>
</head>
<body>
<div class="card">
<?php if ($b): ?>
    <div class="estado estado--ok">✔ BOLETA VÁLIDA</div>
    <div class="body">
        <?php
            // Los números QUE JUEGAN (oportunidades) son lo relevante; el
            // consecutivo del boleto es solo el identificador.
            $numsJuego = json_decode((string)($b['opportunities'] ?? ''), true);
            if (!is_array($numsJuego) || !$numsJuego) { $numsJuego = [(string)$b['ticket_number']]; }
        ?>
        <div class="numero">
            <div class="n" style="<?= count($numsJuego) > 2 ? 'font-size:34px;letter-spacing:2px;line-height:1.4;' : '' ?>"><?= $e(implode(' · ', $numsJuego)) ?></div>
            <div class="l"><?= count($numsJuego) > 1 ? 'Tus ' . count($numsJuego) . ' números en juego' : 'Tu número en juego' ?></div>
        </div>
        <?php if (count($numsJuego) > 1): ?>
        <div class="fila"><span class="k">Boleto Nº</span><span class="v"><?= $e($b['ticket_number']) ?></span></div>
        <?php endif; ?>
        <div class="fila"><span class="k">Rifa</span><span class="v"><?= $e($b['raffle_name']) ?></span></div>
        <div class="fila"><span class="k">Sorteo</span><span class="v"><?= $e(date('d/m/Y', strtotime($b['draw_date']))) ?> · <?= $e($b['lottery_name']) ?></span></div>
        <div class="fila"><span class="k">Modalidad</span><span class="v"><?= $e(Boleta::MODE_LABELS[$b['winning_mode']] ?? $b['winning_mode']) ?> (<?= (int)$b['digits'] ?> cifras)</span></div>
        <div class="fila"><span class="k">Organiza</span><span class="v"><a href="<?= BASE_PATH ?>/public/organizador.php?slug=<?= $e($b['vendor_slug']) ?>" style="color:#fbbf24;text-decoration:none;"><?= $e($b['vendor_name']) ?> →</a></span></div>
        <div class="fila"><span class="k">Comprador</span><span class="v"><?= $e(Boleta::nombreEnmascarado($b['buyer_name'])) ?> · <?= $e(Boleta::celularEnmascarado($b['buyer_phone'])) ?></span></div>
        <div class="fila"><span class="k">Pagado</span><span class="v">$<?= number_format((float)$b['amount'], 0, ',', '.') ?></span></div>
        <div class="fila" style="border-bottom:none;"><span class="k">Emitida</span><span class="v"><?= $e(date('d/m/Y H:i', strtotime($b['issued_at']))) ?></span></div>
        <div class="codigo"><?= $e(TicketCode::format($b['ticket_code'])) ?></div>
    </div>
    <a class="btn" href="<?= BASE_PATH ?>/api/tickets/boleta_png.php?c=<?= $e(TicketCode::format($b['ticket_code'])) ?>">⬇ Descargar boleta (imagen)</a>
    <?php if ($b['raffle_status'] === 'active'): ?>
    <p class="foot" style="padding-bottom:0;">Si la rifa se reprograma, esta página siempre muestra la fecha vigente.</p>
    <?php endif; ?>
<?php elseif ($anulada): ?>
    <div class="estado estado--bad">✖ BOLETA ANULADA</div>
    <div class="body">
        <p style="font-size:14.5px;color:#94a3b8;line-height:1.6;">Esta boleta fue anulada por vía administrativa y su código quedó invalidado. Si crees que es un error, contacta al organizador de la rifa.</p>
    </div>
<?php else: ?>
    <div class="estado estado--bad">✖ BOLETA NO ENCONTRADA</div>
    <div class="body">
        <p style="font-size:14.5px;color:#94a3b8;line-height:1.6;">El código no corresponde a ninguna boleta emitida. Revisa que esté bien escrito (formato XXXX-XXXX-XXXX) o consulta con el organizador.</p>
    </div>
<?php endif; ?>
    <div class="foot">
        <a href="<?= BASE_PATH ?>/public/comprobar-boleta.php">Verificar otra boleta</a> ·
        <a href="<?= BASE_PATH ?>/public/index.php"><?= plataforma_e() ?></a>
    </div>
</div>
<?php $tabActive = 'boletas'; include __DIR__ . '/partials/tabbar.php'; ?>
</body>
</html>
