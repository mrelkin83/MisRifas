<?php
/**
 * Generador de imágenes Open Graph para Rifas
 * GET /api/og/generate.php?raffle_id=X
 *
 * Genera dinámicamente una imagen 1200x630 px tipo banner
 * que incluye: foto del premio, nombre, precio, fecha, progreso y mini grid.
 */

// No enviar HTML en caso de error — solo imagen o error limpio
error_reporting(0);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/brand.php';

// ─── Constantes ────────────────────────────────────────────────
define('OG_W', 1200);
define('OG_H', 630);
define('OG_CACHE_DIR', __DIR__ . '/../../public/uploads/og/');
define('OG_CACHE_TTL', 3600); // 1 hora

// ─── Parámetros ────────────────────────────────────────────────
$raffleId = (int)($_GET['raffle_id'] ?? 0);
$forceRegen = isset($_GET['regen']);   // ?regen para forzar regeneración

if (!$raffleId) {
    http_response_code(400);
    exit('raffle_id requerido');
}

// ─── Verificar caché ───────────────────────────────────────────
if (!is_dir(OG_CACHE_DIR)) {
    mkdir(OG_CACHE_DIR, 0777, true);
}

$cachePath = OG_CACHE_DIR . 'og_rifa_' . $raffleId . '.jpg';

if (!$forceRegen && file_exists($cachePath) && (time() - filemtime($cachePath)) < OG_CACHE_TTL) {
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=' . OG_CACHE_TTL);
    readfile($cachePath);
    exit;
}

// ─── Obtener datos de la rifa ──────────────────────────────────
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT r.id, r.name, r.description, r.image_url, r.ticket_price,
               r.total_tickets, r.draw_date, r.status,
               l.name AS lottery_name,
               SUM(CASE WHEN t.status = 'paid'      THEN 1 ELSE 0 END) AS sold_count,
               SUM(CASE WHEN t.status = 'reserved'  THEN 1 ELSE 0 END) AS reserved_count,
               SUM(CASE WHEN t.status = 'available' THEN 1 ELSE 0 END) AS avail_count,
               GROUP_CONCAT(t.status ORDER BY CAST(t.ticket_number AS UNSIGNED) SEPARATOR ',') AS ticket_statuses
        FROM raffles r
        LEFT JOIN lotteries l ON r.lottery_id = l.id
        LEFT JOIN tickets   t ON t.raffle_id  = r.id
        WHERE r.id = ?
        GROUP BY r.id
    ");
    $stmt->execute([$raffleId]);
    $raffle = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    http_response_code(500);
    exit('DB error');
}

if (!$raffle) {
    http_response_code(404);
    exit('Rifa no encontrada');
}

// ─── Extensiones GD requeridas ─────────────────────────────────
if (!extension_loaded('gd')) {
    http_response_code(500);
    exit('GD no disponible');
}

// ─── Helpers de fuentes ────────────────────────────────────────
$fontPaths = [
    'C:/Windows/Fonts/arialbd.ttf',
    'C:/Windows/Fonts/arial.ttf',
    'C:/Windows/Fonts/verdanab.ttf',
    '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
    '/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
];
$fontBold = null;
$fontRegPath = null;
foreach ($fontPaths as $fp) {
    if (file_exists($fp)) { $fontBold = $fp; break; }
}
$fontRegPaths = [
    'C:/Windows/Fonts/arial.ttf',
    'C:/Windows/Fonts/verdana.ttf',
    '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
    '/usr/share/fonts/truetype/freefont/FreeSans.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
];
foreach ($fontRegPaths as $fp) {
    if (file_exists($fp)) { $fontRegPath = $fp; break; }
}
if (!$fontRegPath) $fontRegPath = $fontBold;

$useTTF = ($fontBold !== null);

// ─── Datos del raffle ─────────────────────────────────────────
$name         = mb_strimwidth($raffle['name'] ?? 'Rifa', 0, 50, '…');
$price        = number_format((float)($raffle['ticket_price'] ?? 0), 0, ',', '.');
$drawDate     = $raffle['draw_date'] ? date('d M Y', strtotime($raffle['draw_date'])) : '--';
$total        = max(1, (int)$raffle['total_tickets']);
$sold         = (int)($raffle['sold_count'] ?? 0);
$reserved     = (int)($raffle['reserved_count'] ?? 0);
$avail        = (int)($raffle['avail_count'] ?? 0);
$soldPct      = round(($sold / $total) * 100);
$ticketStats  = explode(',', $raffle['ticket_statuses'] ?? '');

// ─── Crear canvas ─────────────────────────────────────────────
$img = imagecreatetruecolor(OG_W, OG_H);
imagealphablending($img, true);

// ─── Colores ──────────────────────────────────────────────────
$c = [
    'bg_dark'    => imagecolorallocate($img, 10,  20,  45),
    'bg_mid'     => imagecolorallocate($img, 18,  30,  65),
    'bg_panel'   => imagecolorallocate($img, 25,  40,  80),
    'white'      => imagecolorallocate($img, 255, 255, 255),
    'white80'    => imagecolorallocate($img, 200, 215, 230),
    'blue'       => imagecolorallocate($img, 37,  99,  235),
    'blue_light' => imagecolorallocate($img, 96,  165, 250),
    'green'      => imagecolorallocate($img, 16,  185, 129),
    'amber'      => imagecolorallocate($img, 234, 179, 8),
    'red'        => imagecolorallocate($img, 239, 68,  68),
    'gray'       => imagecolorallocate($img, 71,  85,  105),
    'gray_light' => imagecolorallocate($img, 100, 116, 139),
    'overlay'    => imagecolorallocate($img, 5,   10,  25),
];

// ─── Fondo degradado vertical ────────────────────────────────
for ($y = 0; $y < OG_H; $y++) {
    $t = $y / OG_H;
    $r = (int)(10  + $t * 8);
    $g = (int)(20  + $t * 10);
    $b = (int)(45  + $t * 20);
    $line = imagecolorallocate($img, $r, $g, $b);
    imageline($img, 0, $y, OG_W, $y, $line);
    imagecolordeallocate($img, $line);
}

// ─── Franja izquierda (zona imagen) ──────────────────────────
$imgZoneW = 460;
imagefilledrectangle($img, 0, 0, $imgZoneW, OG_H, $c['bg_panel']);

// ─── Cargar imagen del premio ─────────────────────────────────
$prizeImg = null;
$imageUrl = $raffle['image_url'] ?? '';

if (!empty($imageUrl)) {
    // Resolver URL absoluta
    if (strpos($imageUrl, 'http') !== 0) {
        // Es ruta relativa local → construir path en disco
        $localPath = __DIR__ . '/../../public' . $imageUrl;
        if (file_exists($localPath)) {
            $imageUrl = $localPath;
        }
    }
    try {
        $ctx = stream_context_create(['http' => ['timeout' => 5]]);
        $imgData = @file_get_contents($imageUrl, false, $ctx);
        if ($imgData) {
            $prizeImg = @imagecreatefromstring($imgData);
        }
    } catch (Exception $e) { $prizeImg = null; }
}

if ($prizeImg) {
    // Escalar y centrar la imagen en la zona izquierda
    $sw = imagesx($prizeImg);
    $sh = imagesy($prizeImg);
    $scale = max($imgZoneW / $sw, OG_H / $sh);
    $dw    = (int)($sw * $scale);
    $dh    = (int)($sh * $scale);
    $dx    = (int)(($imgZoneW - $dw) / 2);
    $dy    = (int)((OG_H - $dh) / 2);
    imagecopyresampled($img, $prizeImg, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
    imagedestroy($prizeImg);

    // Overlay semitransparente sobre la imagen para legibilidad
    $overlayImg = imagecreatetruecolor($imgZoneW, OG_H);
    $transColor = imagecolorallocatealpha($overlayImg, 5, 10, 25, 80);
    imagefill($overlayImg, 0, 0, $transColor);
    imagecopymerge($img, $overlayImg, 0, 0, 0, 0, $imgZoneW, OG_H, 50);
    imagedestroy($overlayImg);
} else {
    // Placeholder con gradiente y emoji
    imagefilledrectangle($img, 0, 0, $imgZoneW, OG_H, $c['bg_panel']);
    if ($useTTF) {
        imagettftext($img, 80, 0, 170, 350, $c['gray_light'], $fontBold, '?');
    }
}

// ─── Separador diagonal izquierda/derecha ─────────────────────
$sep = new stdClass();
$sepPoints = [$imgZoneW, 0, $imgZoneW + 60, 0, $imgZoneW, OG_H, $imgZoneW - 0, OG_H];
// Línea vertical estilizada
imagesetthickness($img, 6);
imageline($img, $imgZoneW + 2, 0, $imgZoneW + 2, OG_H, $c['blue']);
imagesetthickness($img, 1);

// ─── Panel de texto (zona derecha) ───────────────────────────
$px = $imgZoneW + 48;   // X inicio texto
$pw = OG_W - $px - 40; // Ancho disponible

// === LOGO / MARCA ===
$logoY = 48;
if ($useTTF) {
    imagettftext($img, 13, 0, $px, $logoY, $c['blue_light'], $fontBold, plataforma('nombre'));
} else {
    imagestring($img, 4, $px, $logoY - 12, plataforma('nombre'), $c['blue_light']);
}

// Línea decorativa bajo el logo
imagefilledrectangle($img, $px, $logoY + 10, $px + 120, $logoY + 13, $c['blue']);

// === BADGE PRECIO ===
$badgeX = OG_W - 200;
$badgeY = 28;
$badgeW = 160;
$badgeH = 44;
imagefilledroundedrectangle2($img, $badgeX, $badgeY, $badgeX + $badgeW, $badgeY + $badgeH, 10, $c['green']);
if ($useTTF) {
    imagettftext($img, 11, 0, $badgeX + 12, $badgeY + 17, $c['white'], $fontBold, 'DESDE');
    imagettftext($img, 16, 0, $badgeX + 12, $badgeY + 36, $c['white'], $fontBold, '$' . $price . ' COP');
} else {
    imagestring($img, 3, $badgeX + 12, $badgeY + 6,  'DESDE',      $c['white']);
    imagestring($img, 4, $badgeX + 8,  $badgeY + 22, '$'.$price,   $c['white']);
}

// === NOMBRE DE LA RIFA ===
$titleY = 120;
if ($useTTF) {
    $titleSize = strlen($name) > 30 ? 32 : 38;
    // Wrap largo si es necesario
    $lines = wordwrap_ttf($name, $fontBold, $titleSize, $pw);
    foreach ($lines as $i => $line) {
        imagettftext($img, $titleSize, 0, $px, $titleY + ($i * ($titleSize + 10)), $c['white'], $fontBold, $line);
    }
    $titleY += count($lines) * ($titleSize + 10) + 10;
} else {
    imagestring($img, 5, $px, $titleY - 14, mb_strimwidth($name, 0, 35, '…'), $c['white']);
    $titleY += 24;
}

// === DETALLES ===
$detailsY = $titleY + 20;
$detailsGap = 38;

// Precio
drawDetailRow($img, $px, $detailsY, '$ Precio por boleto:', '$' . $price . ' COP',
    $c['gray_light'], $c['blue_light'], $useTTF, $fontRegPath, $fontBold);

// Fecha sorteo
drawDetailRow($img, $px, $detailsY + $detailsGap, 'Sorteo:', $drawDate,
    $c['gray_light'], $c['white80'], $useTTF, $fontRegPath, $fontBold);

// Estado
$status = $sold . ' de ' . $total . ' vendidos (' . $soldPct . '%)';
drawDetailRow($img, $px, $detailsY + $detailsGap * 2, 'Avance:', $status,
    $c['gray_light'], $c['amber'], $useTTF, $fontRegPath, $fontBold);

// === BARRA DE PROGRESO ===
$barY   = $detailsY + $detailsGap * 3 + 10;
$barH   = 14;
$barW   = $pw;
$barFill = (int)($barW * min($soldPct / 100, 1));

// Fondo barra
imagefilledroundedrectangle2($img, $px, $barY, $px + $barW, $barY + $barH, 7, $c['gray']);

// Relleno progreso
if ($barFill > 10) {
    imagefilledroundedrectangle2($img, $px, $barY, $px + $barFill, $barY + $barH, 7, $c['green']);
}

// Texto porcentaje sobre la barra
if ($useTTF) {
    imagettftext($img, 10, 0, $px + $barW + 8, $barY + 11, $c['green'], $fontBold, $soldPct . '%');
}

// === MINI GRID DE BOLETOS ===
$gridY   = $barY + $barH + 24;
$gridLabel = 'Vista de boletos:';
if ($useTTF) {
    imagettftext($img, 11, 0, $px, $gridY, $c['gray_light'], $fontRegPath, $gridLabel);
}
$gridY += 18;

$dotSize = 12;
$dotGap  = 4;
$dotsPerRow = (int)($pw / ($dotSize + $dotGap));
$maxDots = min(count($ticketStats), $dotsPerRow * 4); // Máximo 4 filas

for ($i = 0; $i < $maxDots; $i++) {
    $col = $i % $dotsPerRow;
    $row = (int)($i / $dotsPerRow);
    $dx  = $px + $col * ($dotSize + $dotGap);
    $dy  = $gridY + $row * ($dotSize + $dotGap);
    $status_ = $ticketStats[$i] ?? 'available';
    $dotColor = $status_ === 'paid'     ? $c['red']   :
               ($status_ === 'reserved' ? $c['amber'] : $c['green']);
    imagefilledroundedrectangle2($img, $dx, $dy, $dx + $dotSize, $dy + $dotSize, 3, $dotColor);
}

// === BOTÓN CTA ===
$btnY  = OG_H - 90;
$btnH  = 56;
$btnW  = $pw;

// Gradiente simulado (dos rectángulos)
$cBtnL = imagecolorallocate($img, 37, 99, 235);
$cBtnR = imagecolorallocate($img, 16, 185, 129);
for ($xi = 0; $xi < $btnW; $xi++) {
    $t = $xi / $btnW;
    $r = (int)(37  + $t * (16  - 37));
    $g = (int)(99  + $t * (185 - 99));
    $b = (int)(235 + $t * (129 - 235));
    $lc = imagecolorallocate($img, $r, $g, $b);
    imageline($img, $px + $xi, $btnY, $px + $xi, $btnY + $btnH, $lc);
    imagecolordeallocate($img, $lc);
}

// Bordes redondeados sobre el botón
imagefilledroundedrectangle2($img, $px, $btnY, $px + $btnW, $btnY + $btnH, 12, $c['blue']); // fallback shape
// Re-apply gradient over rounded shape (simplified: use color fill)
$cBtn = imagecolorallocate($img, 37, 99, 235);
for ($xi = 0; $xi < $btnW; $xi++) {
    $t  = $xi / $btnW;
    $r2 = (int)(37  + $t * (16  - 37));
    $g2 = (int)(99  + $t * (185 - 99));
    $b2 = (int)(235 + $t * (129 - 235));
    $lc2 = imagecolorallocate($img, $r2, $g2, $b2);
    imageline($img, $px + $xi, $btnY + 2, $px + $xi, $btnY + $btnH - 2, $lc2);
    imagecolordeallocate($img, $lc2);
}

if ($useTTF) {
    $btnText = 'Compra tu numero ahora  >>>';
    $bbox = imagettfbbox(18, 0, $fontBold, $btnText);
    $tw = abs($bbox[4] - $bbox[0]);
    $tx = $px + (int)(($btnW - $tw) / 2);
    imagettftext($img, 18, 0, $tx, $btnY + 36, $c['white'], $fontBold, $btnText);
} else {
    imagestring($img, 5, $px + 40, $btnY + 20, 'Compra tu numero ahora >>>', $c['white']);
}

// === WATERMARK ===
if ($useTTF) {
    imagettftext($img, 9, 0, $px, OG_H - 16, $c['gray'], $fontRegPath, 'misrifas.co · Rifas digitales seguras en Colombia');
}

// ─── Guardar y servir ─────────────────────────────────────────
imagejpeg($img, $cachePath, 90);
imagedestroy($img);

header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=' . OG_CACHE_TTL);
readfile($cachePath);
exit;

// ─── Funciones auxiliares ─────────────────────────────────────

/**
 * Rectángulo redondeado relleno (compatible PHP < 8.1)
 */
function imagefilledroundedrectangle2($img, $x1, $y1, $x2, $y2, $r, $color) {
    if (function_exists('imagefilledroundedrectangle')) {
        imagefilledroundedrectangle($img, $x1, $y1, $x2, $y2, $r, $color);
    } else {
        imagefilledrectangle($img, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($img, $x1, $y1 + $r, $x2, $y2 - $r, $color);
        imagefilledarc($img, $x1 + $r, $y1 + $r, $r * 2, $r * 2, 180, 270, $color, IMG_ARC_PIE);
        imagefilledarc($img, $x2 - $r, $y1 + $r, $r * 2, $r * 2, 270, 360, $color, IMG_ARC_PIE);
        imagefilledarc($img, $x1 + $r, $y2 - $r, $r * 2, $r * 2,  90, 180, $color, IMG_ARC_PIE);
        imagefilledarc($img, $x2 - $r, $y2 - $r, $r * 2, $r * 2,   0,  90, $color, IMG_ARC_PIE);
    }
}

/**
 * Wrap texto para TTF
 */
function wordwrap_ttf($text, $font, $size, $maxWidth) {
    $words = explode(' ', $text);
    $lines = [];
    $current = '';
    foreach ($words as $word) {
        $test = $current ? $current . ' ' . $word : $word;
        $bbox = imagettfbbox($size, 0, $font, $test);
        $w    = abs($bbox[4] - $bbox[0]);
        if ($w > $maxWidth && $current !== '') {
            $lines[] = $current;
            $current = $word;
        } else {
            $current = $test;
        }
    }
    if ($current) $lines[] = $current;
    return array_slice($lines, 0, 3); // máx 3 líneas
}

/**
 * Dibujar fila de detalle: label + valor
 */
function drawDetailRow($img, $x, $y, $label, $value, $labelColor, $valueColor, $useTTF, $fontReg, $fontBold) {
    if ($useTTF) {
        imagettftext($img, 12, 0, $x,        $y, $labelColor, $fontReg,  $label);
        imagettftext($img, 14, 0, $x + 180,  $y, $valueColor, $fontBold, $value);
    } else {
        imagestring($img, 3, $x,       $y - 13, $label, $labelColor);
        imagestring($img, 4, $x + 180, $y - 13, $value, $valueColor);
    }
}
