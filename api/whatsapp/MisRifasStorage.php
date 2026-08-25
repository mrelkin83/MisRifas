<?php
/**
 * Adaptador de whatsapp-engine\Ports\StoragePort. Guarda las notas de voz y
 * fotos que llegan por WhatsApp en uploads/whatsapp/{vendor_id}/ - fuera de
 * public/, igual que uploads/payment_proofs/ (comprobantes de pago): son
 * archivos privados de una conversación, no contenido público del sitio.
 *
 * url() apunta a un endpoint de servicio autenticado que todavía no existe
 * (api/whatsapp/media.php) - no hace falta hasta que el panel necesite
 * mostrar una foto/audio de una conversación. Nada del flujo de los Pasos
 * 1-5 (webhook, AiOrchestrator, RaffleDomainAdapter) llama a url().
 */

class MisRifasStorage implements \ElkinLinan\WhatsappAiEngine\Ports\StoragePort
{
    private string $raiz;
    private int $vendorId;

    public function __construct(int $vendorId)
    {
        $this->vendorId = $vendorId;
        $this->raiz = __DIR__ . '/../../uploads/whatsapp';
    }

    public function directorio(): string
    {
        $dir = $this->raiz . '/' . $this->vendorId;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    public function raiz(): string
    {
        return $this->raiz;
    }

    public function url(string $rutaRelativa): string
    {
        return '/api/whatsapp/media.php?path=' . rawurlencode($rutaRelativa);
    }

    public function comprimirImagen(string $binario, int $maxLado = 1024, int $calidad = 78): ?array
    {
        $img = @imagecreatefromstring($binario);
        if ($img === false) {
            return null;
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $escala = min(1, $maxLado / max($w, $h));

        if ($escala < 1) {
            $nw = max(1, (int)round($w * $escala));
            $nh = max(1, (int)round($h * $escala));
            $redim = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($redim, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($img);
            $img = $redim;
        }

        ob_start();
        imagejpeg($img, null, $calidad);
        $bin = ob_get_clean();
        imagedestroy($img);

        if ($bin === false || strlen($bin) >= strlen($binario)) {
            return null;
        }
        return ['bin' => $bin, 'mime' => 'image/jpeg'];
    }

    public function cabe(int $bytes): bool
    {
        // Sin cupo de almacenamiento por vendor todavia (coherente con
        // FeaturePort=TodoPermitido - no hay gating por plan hoy).
        return true;
    }
}
