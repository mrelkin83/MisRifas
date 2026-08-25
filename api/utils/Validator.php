<?php
/**
 * Clase Validator
 * Validación de datos
 */

class Validator
{
    private $errors = [];
    private $data = [];

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Validar campo requerido
     */
    public function required(string $field, string $message = null): self
    {
        $value = $this->getValue($field);
        if ($value === null || $value === '') {
            $this->addError($field, $message ?? "El campo {$field} es requerido");
        }
        return $this;
    }

    /**
     * Validar email
     */
    public function email(string $field, string $message = null): self
    {
        $value = $this->getValue($field);
        if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, $message ?? "El campo {$field} debe ser un email válido");
        }
        return $this;
    }

    /**
     * Validar teléfono colombiano
     */
    public function phoneColombia(string $field, string $message = null): self
    {
        $value = $this->getValue($field);
        if ($value && !preg_match(REGEX_PHONE_COLOMBIA, $value)) {
            $this->addError($field, $message ?? "El campo {$field} debe ser un teléfono válido de Colombia");
        }
        return $this;
    }

    /**
     * Validar longitud mínima
     */
    public function minLength(string $field, int $min, string $message = null): self
    {
        $value = $this->getValue($field);
        if ($value && strlen($value) < $min) {
            $this->addError($field, $message ?? "El campo {$field} debe tener al menos {$min} caracteres");
        }
        return $this;
    }

    /**
     * Validar longitud máxima
     */
    public function maxLength(string $field, int $max, string $message = null): self
    {
        $value = $this->getValue($field);
        if ($value && strlen($value) > $max) {
            $this->addError($field, $message ?? "El campo {$field} no debe exceder {$max} caracteres");
        }
        return $this;
    }

    /**
     * Validar valor numérico
     */
    public function numeric(string $field, string $message = null): self
    {
        $value = $this->getValue($field);
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            $this->addError($field, $message ?? "El campo {$field} debe ser numérico");
        }
        return $this;
    }

    /**
     * Validar entero
     */
    public function integer(string $field, string $message = null): self
    {
        $value = $this->getValue($field);
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_INT)) {
            $this->addError($field, $message ?? "El campo {$field} debe ser un número entero");
        }
        return $this;
    }

    /**
     * Validar valor mínimo numérico
     */
    public function min(string $field, $min, string $message = null): self
    {
        $value = $this->getValue($field);
        if ($value !== null && $value !== '' && is_numeric($value) && $value < $min) {
            $this->addError($field, $message ?? "El campo {$field} debe ser mayor o igual a {$min}");
        }
        return $this;
    }

    /**
     * Validar valor máximo numérico
     */
    public function max(string $field, $max, string $message = null): self
    {
        $value = $this->getValue($field);
        if ($value !== null && $value !== '' && is_numeric($value) && $value > $max) {
            $this->addError($field, $message ?? "El campo {$field} debe ser menor o igual a {$max}");
        }
        return $this;
    }

    /**
     * Validar rango numérico
     */
    public function between(string $field, $min, $max, string $message = null): self
    {
        $value = $this->getValue($field);
        if ($value !== null && ($value < $min || $value > $max)) {
            $this->addError($field, $message ?? "El campo {$field} debe estar entre {$min} y {$max}");
        }
        return $this;
    }

    /**
     * Validar que esté en un array de valores
     */
    public function in(string $field, array $values, string $message = null): self
    {
        $value = $this->getValue($field);
        if ($value && !in_array($value, $values, true)) {
            $allowed = implode(', ', $values);
            $this->addError($field, $message ?? "El campo {$field} debe ser uno de: {$allowed}");
        }
        return $this;
    }

    /**
     * Validar fecha
     */
    public function date(string $field, string $format = 'Y-m-d H:i:s', string $message = null): self
    {
        $value = $this->getValue($field);
        if ($value) {
            $dateTime = DateTime::createFromFormat($format, $value);
            if (!$dateTime || $dateTime->format($format) !== $value) {
                $this->addError($field, $message ?? "El campo {$field} debe ser una fecha válida");
            }
        }
        return $this;
    }

    /**
     * Validar fecha futura
     */
    public function futureDate(string $field, string $message = null): self
    {
        $value = $this->getValue($field);
        if ($value) {
            $date = strtotime($value);
            if ($date === false || $date <= time()) {
                $this->addError($field, $message ?? "El campo {$field} debe ser una fecha futura");
            }
        }
        return $this;
    }

    /**
     * Validar URL
     */
    public function url(string $field, string $message = null): self
    {
        $value = $this->getValue($field);
        if ($value && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, $message ?? "El campo {$field} debe ser una URL válida");
        }
        return $this;
    }

    /**
     * Validar patrón regex
     */
    public function regex(string $field, string $pattern, string $message): self
    {
        $value = $this->getValue($field);
        if ($value && !preg_match($pattern, $value)) {
            $this->addError($field, $message);
        }
        return $this;
    }

    /**
     * Validar que el valor sea único en la base de datos
     */
    public function unique(string $field, string $table, string $column, $exceptId = null, string $message = null): self
    {
        $value = $this->getValue($field);
        if ($value) {
            $db = Database::getInstance()->getConnection();

            $sql = "SELECT COUNT(*) FROM {$table} WHERE {$column} = ?";
            $params = [$value];

            if ($exceptId !== null) {
                $sql .= " AND id != ?";
                $params[] = $exceptId;
            }

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $count = $stmt->fetchColumn();

            if ($count > 0) {
                $this->addError($field, $message ?? "El valor de {$field} ya existe");
            }
        }
        return $this;
    }

    /**
     * Validar archivo de imagen
     */
    public function image(string $field, array $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'], int $maxSize = 5242880, string $message = null): self
    {
        if (isset($_FILES[$field])) {
            $file = $_FILES[$field];

            // Validar errores de carga
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $this->addError($field, $message ?? "Error al cargar el archivo");
                return $this;
            }

            // Validar tamaño
            if ($file['size'] > $maxSize) {
                $maxSizeMB = round($maxSize / 1024 / 1024, 2);
                $this->addError($field, $message ?? "El archivo no debe exceder {$maxSizeMB} MB");
                return $this;
            }

            // Validar tipo
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedTypes)) {
                $allowed = implode(', ', $allowedTypes);
                $this->addError($field, $message ?? "El archivo debe ser de tipo: {$allowed}");
                return $this;
            }

            // Validar MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = [
                'image/jpeg',
                'image/jpg',
                'image/png',
                'image/gif',
                'image/webp',
            ];

            if (!in_array($mimeType, $allowedMimes)) {
                $this->addError($field, $message ?? "El archivo debe ser una imagen válida");
            }
        }
        return $this;
    }

    /**
     * Validación personalizada
     */
    public function custom(string $field, callable $callback, string $message): self
    {
        $value = $this->getValue($field);
        if (!$callback($value, $this->data)) {
            $this->addError($field, $message);
        }
        return $this;
    }

    /**
     * Validar JSON válido
     */
    public function json(string $field, string $message = null): self
    {
        $value = $this->getValue($field);
        if ($value) {
            json_decode($value);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->addError($field, $message ?? "El campo {$field} debe ser JSON válido");
            }
        }
        return $this;
    }

    /**
     * Obtener valor de un campo
     */
    private function getValue(string $field)
    {
        if (strpos($field, '.') !== false) {
            $keys = explode('.', $field);
            $value = $this->data;
            foreach ($keys as $key) {
                if (is_array($value) && isset($value[$key])) {
                    $value = $value[$key];
                } else {
                    return null;
                }
            }
            return $value;
        }

        return $this->data[$field] ?? null;
    }

    /**
     * Agregar error
     */
    private function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    /**
     * Verificar si la validación pasó
     */
    public function passes(): bool
    {
        return empty($this->errors);
    }

    /**
     * Verificar si la validación falló
     */
    public function fails(): bool
    {
        return !$this->passes();
    }

    /**
     * Obtener todos los errores
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Obtener primer error de un campo
     */
    public function getFirstError(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * Sanitizar datos (HTML)
     */
    public static function sanitize($data)
    {
        if (is_array($data)) {
            return array_map([self::class, 'sanitize'], $data);
        }

        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitizar para SQL (usar prepared statements en su lugar)
     */
    public static function sanitizeSQL(string $string): string
    {
        return addslashes(trim($string));
    }

    /**
     * Validar CSRF token
     */
    public static function validateCSRFToken(string $token): bool
    {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Generar CSRF token
     */
    public static function generateCSRFToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }

        return $_SESSION['csrf_token'];
    }
}
