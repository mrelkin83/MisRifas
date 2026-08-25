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

        // 2. Validar tamaño
        $maxSize = ($type === 'banner') ? self::$maxSizeBanner : self::$maxSizeProfile;
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
