<?php

declare(strict_types=1);

require_once __DIR__ . '/TicketCode.php';
require_once __DIR__ . '/../lib/qrcode.php';

/**
 * Boleta digital en PNG (promt2.md §9.6).
 *
 * Generada con GD (dependencia ya presente) — el destino es WhatsApp, por eso
 * PNG y no PDF. Se guarda FUERA del directorio público (storage/boletas/) y
 * se sirve por un controlador. NUNCA generarla dentro de una transacción
 * abierta: se produce bajo demanda (con caché) después del commit.
 */
final class BoletaImage
{
    public static function storageDir(): string
    {
        $dir = __DIR__ . '/../../storage/boletas';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /** Ruta del PNG (lo genera si no existe aún). */
    public static function ensure(array $b): string
    {
        $code = TicketCode::normalize($b['ticket_code']);
        $path = self::storageDir() . '/' . $code . '.png';
        if (!is_file($path)) {
            self::render($b, $path);
        }
        return $path;
    }

    /** Regenera (p. ej. tras reprogramación: cambia la fecha del sorteo). */
    public static function invalidateCache(string $code): void
    {
        $path = self::storageDir() . '/' . TicketCode::normalize($code) . '.png';
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private static function font(): ?string
    {
        foreach ([
            __DIR__ . '/../../public/assets/fonts/bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            'C:/Windows/Fonts/arialbd.ttf',
        ] as $f) {
            if (is_file($f)) {
                return $f;
            }
        }
        return null;
    }

    private static function render(array $b, string $path): void
    {
        $w = 900;
        $h = 1420;
        $im = imagecreatetruecolor($w, $h);

        $bg     = imagecolorallocate($im, 15, 23, 42);    // slate-900
        $card   = imagecolorallocate($im, 30, 41, 59);    // slate-800
        $accent = imagecolorallocate($im, 245, 158, 11);  // amber-500
        $white  = imagecolorallocate($im, 241, 245, 249);
        $muted  = imagecolorallocate($im, 148, 163, 184);
        $green  = imagecolorallocate($im, 34, 197, 94);

        imagefilledrectangle($im, 0, 0, $w, $h, $bg);
        imagefilledrectangle($im, 30, 30, $w - 30, $h - 30, $card);
        imagefilledrectangle($im, 30, 30, $w - 30, 120, $accent);

        $font = self::font();
        $text = function (int $size, $color, int $x, int $y, string $s, bool $center = false) use ($im, $font, $w) {
            if ($font) {
                if ($center) {
                    $box = imagettfbbox($size, 0, $font, $s);
                    $x = (int)(($w - ($box[2] - $box[0])) / 2);
                }
                imagettftext($im, $size, 0, $x, $y, $color, $font, $s);
            } else {
                if ($center) {
                    $x = (int)(($w - strlen($s) * 9) / 2);
                }
                imagestring($im, 5, $x, $y - 14, $s, $color);
            }
        };

        // Cabecera
        $dark = imagecolorallocate($im, 28, 19, 5);
        $text(30, $dark, 0, 88, 'MisRifas', true);

        // Nombre de la rifa
        $name = mb_strimwidth((string)$b['raffle_name'], 0, 36, '…', 'UTF-8');
        $text(26, $white, 0, 195, $name, true);
        $text(15, $muted, 0, 235, 'BOLETA DIGITAL', true);

        // Número gigante
        $text(120, $accent, 0, 430, (string)$b['ticket_number'], true);
        $text(15, $muted, 0, 470, 'TU NUMERO', true);

        // Detalles
        $y = 545;
        $rows = [
            ['Sorteo',    date('d/m/Y', strtotime((string)$b['draw_date'])) . ' - ' . (string)$b['lottery_name']],
            ['Modalidad', (string)$b['mode_label']],
            ['Comprador', (string)$b['buyer_masked']],
            ['Organiza',  mb_strimwidth((string)$b['vendor_name'], 0, 30, '…', 'UTF-8')],
            ['Pagado',    '$' . number_format((float)$b['amount'], 0, ',', '.')],
            ['Emitida',   date('d/m/Y H:i', strtotime((string)$b['issued_at']))],
        ];
        foreach ($rows as [$k, $v]) {
            $text(15, $muted, 80, $y, strtoupper($k));
            $text(17, $white, 300, $y, $v);
            $y += 52;
        }

        // Código formateado
        imagefilledrectangle($im, 80, $y + 10, $w - 80, $y + 80, $bg);
        $text(26, $green, 0, $y + 58, TicketCode::format((string)$b['ticket_code']), true);

        // QR hacia la página pública de la boleta
        $qr = QRCode::getMinimumQRCode((string)$b['url'], QR_ERROR_CORRECT_LEVEL_M);
        $qrIm = $qr->createImage(8, 4);
        $qw = imagesx($qrIm);
        $dst = 260;
        $qx = (int)(($w - $dst) / 2);
        $qy = $y + 110;
        imagefilledrectangle($im, $qx - 10, $qy - 10, $qx + $dst + 10, $qy + $dst + 10, $white);
        imagecopyresampled($im, $qrIm, $qx, $qy, 0, 0, $dst, $dst, $qw, $qw);
        imagedestroy($qrIm);
        $text(13, $muted, 0, $qy + $dst + 45, 'Escanea para comprobar la boleta', true);

        imagepng($im, $path);
        imagedestroy($im);
    }
}
