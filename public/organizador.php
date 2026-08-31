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
// Reseñas de compradores verificados: interruptor de plataforma (v4.12).
$reviewsEnabled = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'reviews_enabled'")->fetchColumn() === '1';
$reviews = [];
$reviewAvg = null;
$reviewCount = 0;

if ($slug !== '') {
    $stmt = $db->prepare("SELECT id, business_name, legal_name, document_type, document_number, phone, email,
                                 email_verified_at, phone_verified_at, city, department, created_at
                          FROM vendors WHERE slug = ? AND status = 'active'");
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
        SELECT id, public_code, name, ticket_price, draw_date FROM raffles
        WHERE COALESCE(vendor_id, created_by) = ? AND status = 'active'
        ORDER BY draw_date ASC LIMIT 10
    ");
    $q->execute([$vid]);
    $activas = $q->fetchAll(PDO::FETCH_ASSOC);

    if ($reviewsEnabled) {
        $rq = $db->prepare("
            SELECT vr.rating, vr.comment, vr.created_at, u.name AS buyer_name, r.name AS raffle_name
            FROM vendor_reviews vr
            JOIN users u ON u.id = vr.user_id
            JOIN raffles r ON r.id = vr.raffle_id
            WHERE vr.vendor_id = ?
            ORDER BY vr.updated_at DESC LIMIT 30
        ");
        $rq->execute([$vid]);
        $reviews = $rq->fetchAll(PDO::FETCH_ASSOC);
        $agg = $db->prepare("SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS n FROM vendor_reviews WHERE vendor_id = ?");
        $agg->execute([$vid]);
        $a = $agg->fetch(PDO::FETCH_ASSOC);
        $reviewAvg = $a['avg_rating'] !== null ? (float)$a['avg_rating'] : null;
        $reviewCount = (int)($a['n'] ?? 0);
    }
}

/** "Carlos Gómez [PRUEBA]" → "Carlos G." (privacidad del comprador). */
function nombreCorto(string $n): string
{
    $n = trim(preg_replace('/\s*\[[^\]]*\]\s*/', ' ', $n));
    $partes = preg_split('/\s+/', $n) ?: [];
    if (!$partes || $partes[0] === '') {
        return 'Comprador';
    }
    $out = $partes[0];
    if (isset($partes[1]) && $partes[1] !== '') {
        $out .= ' ' . mb_substr($partes[1], 0, 1) . '.';
    }
    return $out;
}

$estrellas = fn(int $r) => str_repeat('★', max(0, min(5, $r))) . str_repeat('☆', 5 - max(0, min(5, $r)));

/** Documento corroborable SIN exponerlo completo: "CC ****8721". */
function docEnmascarado(?string $tipo, ?string $num): ?string
{
    $num = preg_replace('/\D/', '', (string)$num);
    if ($num === '' || strlen($num) < 4) {
        return null;
    }
    return trim(($tipo ?: 'CC') . ' ' . str_repeat('*', max(0, strlen($num) - 4)) . substr($num, -4));
}

/** "laura@dominio.com" → "la***@dominio.com". */
function emailEnmascarado(?string $mail): ?string
{
    if (!$mail || strpos($mail, '@') === false) {
        return null;
    }
    [$u, $d] = explode('@', $mail, 2);
    return substr($u, 0, 2) . str_repeat('*', max(1, strlen($u) - 2)) . '@' . $d;
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

    <!-- Quién RESPONDE: datos reales y corroborables del responsable.
         El documento se muestra parcial (corroborable sin exponerlo completo)
         y el celular completo: es su canal de venta y ya es público en cada
         rifa. Transparencia = confianza. -->
    <div class="card">
        <h2 style="font-size:15px;margin-bottom:10px;">🪪 ¿Quién responde por estas rifas?</h2>
        <?php
            $filas = [];
            if (!empty($vendor['legal_name'])) {
                $filas[] = ['Responsable', $vendor['legal_name']];
            }
            if ($doc = docEnmascarado($vendor['document_type'] ?? null, $vendor['document_number'] ?? null)) {
                $filas[] = ['Documento registrado', $doc];
            }
            if (!empty($vendor['phone'])) {
                $filas[] = ['WhatsApp de contacto', $vendor['phone']];
            }
            if ($mail = emailEnmascarado($vendor['email'] ?? null)) {
                $filas[] = ['Correo', $mail];
            }
        ?>
        <?php if (!$filas): ?>
        <p style="font-size:13px;color:#94a3b8;">El organizador aún no ha completado sus datos de responsable.</p>
        <?php else: foreach ($filas as [$k, $v]): ?>
        <div style="display:flex;justify-content:space-between;gap:10px;padding:8px 0;border-bottom:1px dashed rgba(255,255,255,.07);font-size:13.5px;">
            <span style="color:#94a3b8;"><?= $e($k) ?></span>
            <strong style="color:#fff;text-align:right;"><?= $e($v) ?></strong>
        </div>
        <?php endforeach; endif; ?>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;">
            <?php if (!empty($vendor['email_verified_at'])): ?>
            <span style="padding:3px 10px;border-radius:99px;background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.35);color:#4ade80;font-size:11px;font-weight:800;">✓ Correo verificado</span>
            <?php endif; ?>
            <?php if (!empty($vendor['phone_verified_at'])): ?>
            <span style="padding:3px 10px;border-radius:99px;background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.35);color:#4ade80;font-size:11px;font-weight:800;">✓ Celular verificado</span>
            <?php endif; ?>
            <span style="padding:3px 10px;border-radius:99px;background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.3);color:#93c5fd;font-size:11px;font-weight:800;">🪪 Documento en registro de la plataforma</span>
        </div>
        <?php if (!empty($vendor['phone'])): ?>
        <a href="https://wa.me/57<?= $e(preg_replace('/\D/', '', (string)$vendor['phone'])) ?>?text=<?= rawurlencode('Hola, te escribo desde tu perfil en MisRifas 👋') ?>"
           target="_blank" rel="noopener"
           style="display:block;margin-top:12px;padding:12px;border-radius:12px;background:#25D366;color:#fff;font-weight:800;font-size:14px;text-align:center;text-decoration:none;">
            💬 Escribir por WhatsApp
        </a>
        <?php endif; ?>
        <p class="aviso">El documento del responsable queda registrado ante la plataforma al crear la cuenta y se muestra parcialmente por su seguridad. Si algo no cuadra, repórtalo antes de comprar.</p>
    </div>
    <?php if ($activas): ?>
    <div class="card">
        <h2 style="font-size:15px;margin-bottom:6px;">Rifas activas</h2>
        <?php foreach ($activas as $r): ?>
        <div class="rifa">
            <a href="<?= BASE_PATH ?>/public/raffle.php?<?= !empty($r['public_code']) ? 'c=' . $e($r['public_code']) : 'id=' . (int)$r['id'] ?>"><?= $e($r['name']) ?></a>
            <span style="color:#94a3b8;">$<?= number_format((float)$r['ticket_price'], 0, ',', '.') ?> · <?= $e(date('d/m/Y', strtotime((string)$r['draw_date']))) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($reviewsEnabled): ?>
    <!-- Reseñas de COMPRADORES VERIFICADOS (v4.12): la credencial es el
         código de la boleta pagada. Se apaga con reviews_enabled. -->
    <div class="card" id="resenas">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
            <h2 style="font-size:15px;">⭐ Reseñas de compradores</h2>
            <?php if ($reviewCount > 0): ?>
            <span style="font-weight:900;color:#fbbf24;font-size:15px;"><?= number_format($reviewAvg, 1) ?> <span style="font-size:12px;">★</span> <span style="color:#94a3b8;font-weight:600;font-size:12px;">(<?= $reviewCount ?>)</span></span>
            <?php endif; ?>
        </div>
        <p style="font-size:11.5px;color:#64748b;margin:6px 0 12px;">Solo pueden opinar compradores con una <strong>boleta pagada</strong> de este organizador (su código es la llave).</p>

        <?php if (!$reviews): ?>
        <p style="font-size:13px;color:#94a3b8;text-align:center;padding:8px 0 14px;">Aún no hay reseñas. ¡Sé el primero!</p>
        <?php else: foreach ($reviews as $rv): ?>
        <div style="padding:10px 0;border-bottom:1px dashed rgba(255,255,255,.07);">
            <div style="display:flex;justify-content:space-between;gap:8px;font-size:13px;">
                <strong style="color:#fff;"><?= $e(nombreCorto((string)$rv['buyer_name'])) ?></strong>
                <span style="color:#fbbf24;letter-spacing:1px;"><?= $estrellas((int)$rv['rating']) ?></span>
            </div>
            <?php if (!empty($rv['comment'])): ?>
            <p style="font-size:13px;color:#cbd5e1;margin-top:4px;line-height:1.45;"><?= $e($rv['comment']) ?></p>
            <?php endif; ?>
            <p style="font-size:10.5px;color:#64748b;margin-top:4px;"><?= $e($rv['raffle_name']) ?> · <?= $e(date('d/m/Y', strtotime((string)$rv['created_at']))) ?></p>
        </div>
        <?php endforeach; endif; ?>

        <!-- Formulario: boleta + estrellas + comentario -->
        <div style="margin-top:14px;padding-top:14px;border-top:1px solid rgba(255,255,255,.08);">
            <p style="font-size:13px;font-weight:700;color:#fff;margin-bottom:8px;">Deja tu reseña</p>
            <input type="text" id="rv-code" placeholder="Código de tu boleta (XXXX-XXXX-XXXX)" maxlength="14" autocomplete="off"
                   style="width:100%;padding:11px 12px;border-radius:10px;background:#0f172a;border:1px solid rgba(255,255,255,.12);color:#4ade80;font-family:ui-monospace,monospace;font-weight:700;text-transform:uppercase;letter-spacing:1px;font-size:14px;outline:none;">
            <div id="rv-stars" style="display:flex;gap:6px;justify-content:center;margin:10px 0;font-size:28px;cursor:pointer;user-select:none;">
                <span data-v="1">☆</span><span data-v="2">☆</span><span data-v="3">☆</span><span data-v="4">☆</span><span data-v="5">☆</span>
            </div>
            <textarea id="rv-comment" maxlength="500" placeholder="Cuéntanos tu experiencia (opcional)"
                      style="width:100%;min-height:70px;padding:11px 12px;border-radius:10px;background:#0f172a;border:1px solid rgba(255,255,255,.12);color:#fff;font-size:13.5px;resize:vertical;outline:none;"></textarea>
            <button type="button" id="rv-send"
                    style="display:block;width:100%;margin-top:10px;padding:13px;border:none;border-radius:12px;background:linear-gradient(135deg,#f59e0b,#d97706);color:#1c1305;font-weight:800;font-size:14px;cursor:pointer;">Publicar reseña</button>
            <p id="rv-msg" style="display:none;margin-top:10px;padding:10px;border-radius:10px;font-size:13px;text-align:center;"></p>
        </div>
    </div>
    <script>
        (function () {
            const BASE_PATH = '<?= BASE_PATH ?>';
            let rating = 0;
            const stars = document.querySelectorAll('#rv-stars span');
            stars.forEach(s => s.addEventListener('click', () => {
                rating = parseInt(s.dataset.v, 10);
                stars.forEach(x => { x.textContent = parseInt(x.dataset.v, 10) <= rating ? '★' : '☆'; x.style.color = parseInt(x.dataset.v, 10) <= rating ? '#fbbf24' : '#64748b'; });
            }));
            document.getElementById('rv-code').addEventListener('input', function () {
                const raw = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 12);
                this.value = raw.replace(/(.{4})(?=.)/g, '$1-');
            });
            document.getElementById('rv-send').addEventListener('click', async function () {
                const msg = document.getElementById('rv-msg');
                const show = (t, ok) => { msg.style.display = 'block'; msg.textContent = t; msg.style.background = ok ? 'rgba(34,197,94,.12)' : 'rgba(239,68,68,.12)'; msg.style.color = ok ? '#86efac' : '#fca5a5'; };
                if (!rating) { show('Elige de 1 a 5 estrellas', false); return; }
                this.disabled = true; this.textContent = 'Publicando…';
                try {
                    const r = await fetch(BASE_PATH + '/api/vendors/reviews.php', {
                        method: 'POST', headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            slug: '<?= $e($slug) ?>',
                            ticket_code: document.getElementById('rv-code').value,
                            rating: rating,
                            comment: document.getElementById('rv-comment').value
                        })
                    });
                    const j = await r.json();
                    if (j.success) { show(j.message || 'Reseña publicada', true); setTimeout(() => location.reload(), 1400); }
                    else show(j.message || 'No se pudo publicar', false);
                } catch (e) { show('Error de conexión', false); }
                this.disabled = false; this.textContent = 'Publicar reseña';
            });
        })();
    </script>
    <?php endif; ?>
<?php endif; ?>
    <p class="foot"><a href="<?= BASE_PATH ?>/public/index.php">← MisRifas</a> · <a href="<?= BASE_PATH ?>/public/ganadores.php">Hall de ganadores</a></p>
</div>
</body>
</html>
