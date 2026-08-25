<?php
/**
 * Open Graph Service
 * Genera imágenes dinámicas para OG Tags usando PHP GD
 */

class OpenGraphService
{
    private $fontPath;
    private $outputPath;
    private $templatePath;

    public function __construct()
    {
        $this->fontPath = __DIR__ . '/../../public/assets/fonts/Inter-Bold.ttf';
        $this->outputPath = __DIR__ . '/../../uploads/og/';
        $this->templatePath = __DIR__ . '/../../public/assets/images/og_template.jpg';
        
        if (!is_dir($this->outputPath)) {
            mkdir($this->outputPath, 0755, true);
        }
        
        // Asumiendo que copiaremos una fuente y template. Para no fallar, creamos base
    }

    /**
     * Genera la imagen OG para una rifa.
     * $data = ['id' => 1, 'name' => 'Carro M2', 'price' => '10.000', 'status' => 'Activa', 'image' => '/uploads/carro.jpg']
     */
    public function generateForRaffle(int $raffleId, array $data): string
    {
        $filename = "og_rifa_{$raffleId}.jpg";
        $destination = $this->outputPath . $filename;

        // Crear una imagen base de 1200x630
        $image = imagecreatetruecolor(1200, 630);
        
        // Colores
        $bg = imagecolorallocate($image, 240, 245, 245); // gris suave
        $primary = imagecolorallocate($image, 37, 99, 235); // azul tailwind
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 30, 41, 59);

        imagefill($image, 0, 0, $bg);

        // Intentar agregar imagen del premio a la izquierda (la meta mitad 600x630)
        if (!empty($data['image']) && file_exists(__DIR__ . '/../../public' . $data['image'])) {
            $ext = strtolower(pathinfo($data['image'], PATHINFO_EXTENSION));
            $sourceImage = null;
            if ($ext === 'jpg' || $ext === 'jpeg') $sourceImage = @imagecreatefromjpeg(__DIR__ . '/../../public' . $data['image']);
            elseif ($ext === 'png') $sourceImage = @imagecreatefrompng(__DIR__ . '/../../public' . $data['image']);
            elseif ($ext === 'webp') $sourceImage = @imagecreatefromwebp(__DIR__ . '/../../public' . $data['image']);

            if ($sourceImage) {
                // recortar/escalar para mitadas (esto es básico, en prod se usa redimensionado mejor)
                imagecopyresampled($image, $sourceImage, 0, 0, 0, 0, 600, 630, imagesx($sourceImage), imagesy($sourceImage));
                imagedestroy($sourceImage);
            } else {
                imagefilledrectangle($image, 0, 0, 600, 630, $primary);
            }
        } else {
             imagefilledrectangle($image, 0, 0, 600, 630, $primary);
        }

        // Si existe fuente TTF, usar imagettftext, sino usar imagestring (fuente básica)
        if (file_exists($this->fontPath)) {
            imagettftext($image, 40, 0, 650, 150, $black, $this->fontPath, substr($data['name'], 0, 30));
            imagettftext($image, 30, 0, 650, 250, $primary, $this->fontPath, "Valor: $" . $data['price']);
            imagettftext($image, 24, 0, 650, 350, $black, $this->fontPath, "Estado: " . $data['status']);
            // CTA Button param
            imagefilledrectangle($image, 650, 450, 950, 520, $primary);
            imagettftext($image, 24, 0, 720, 500, $white, $this->fontPath, "Comprar Ahora");
        } else {
            imagestring($image, 5, 650, 150, substr($data['name'], 0, 30), $black);
            imagestring($image, 5, 650, 250, "Valor: $" . $data['price'], $primary);
            imagestring($image, 5, 650, 350, "Estado: " . $data['status'], $black);
            imagefilledrectangle($image, 650, 450, 950, 520, $primary);
            imagestring($image, 5, 720, 480, "Comprar Ahora", $white);
        }

        imagejpeg($image, $destination, 90);
        imagedestroy($image);

        return "/uploads/og/{$filename}";
    }
}
