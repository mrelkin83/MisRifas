<?php
/**
 * Utility: File Uploader
 * Securely handles file uploads for profiles and banners.
 */

class Uploader {
    
    private static $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
    private static $maxSizeProfile = 2097152; // 2MB
    private static $maxSizeBanner  = 5242880; // 5MB

    /**
     * Sube un archivo de imagen al servidor
     * @param array $file $_FILES['input_name']
     * @param string $folder Ruta relativa desde public/ (ej: 'assets/uploads/profiles')
     * @param string $type Indica si es 'profile' o 'banner' para validar tamaño
     */
    public static function upload($file, $folder, $type = 'profile') {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Error en la carga del archivo.");
        }

        // 1. Validar extensión
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::$allowedExt)) {
            throw new Exception("Extensión de archivo no permitida (solo JPG, PNG, WEBP).");
        }

        // 1.5. Validar el contenido real del archivo, no solo el nombre que
        // manda el cliente - sin esto cualquier archivo renombrado a .jpg
        // pasa la validacion anterior.
        $imageInfo = @getimagesize($file['tmp_name']);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if ($imageInfo === false || !in_array($imageInfo['mime'], $allowedMimes)) {
            throw new Exception("El archivo no es una imagen valida.");
        }

        // 2. Validar tamaño. Las fotos de RIFA usan el nivel de 5MB: caían en
        // el default de perfil (2MB) y las fotos normales de celular (3-6MB)
        // rompían la carga masiva una por una.
        $maxSize = in_array($type, ['banner', 'raffle'], true) ? self::$maxSizeBanner : self::$maxSizeProfile;
        if ($file['size'] > $maxSize) {
            throw new Exception("El archivo excede el tamaño máximo permitido (" . ($maxSize / 1024 / 1024) . "MB).");
        }

        // 3. Crear carpeta si no existe
        $baseDir = __DIR__ . '/../../public/' . ltrim($folder, '/');
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0777, true);
        }

        // 4. Generar nombre único
        $newName = uniqid('img_', true) . '.' . $ext;
        $targetPath = $baseDir . '/' . $newName;

        // 5. Mover archivo
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return ltrim($folder, '/') . '/' . $newName; // Ruta relativa para guardar en DB
        } else {
            throw new Exception("Error al mover el archivo al servidor.");
        }
    }

    /**
     * Elimina un archivo si existe
     */
    public static function delete($relativePath) {
        if (empty($relativePath)) return;
        $fullPath = __DIR__ . '/../../public/' . ltrim($relativePath, '/');
        if (file_exists($fullPath) && is_file($fullPath)) {
            unlink($fullPath);
        }
    }
}
