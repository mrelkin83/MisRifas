<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/Response.php';

class Auth
{
    /**
     * Los auth_token se guardan hasheados en BD (ver hashToken()). El
     * token en texto plano solo existe en el header Authorization del
     * cliente y en la respuesta del login - una fuga de BD no entrega
     * tokens de sesion utilizables directamente.
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function requireVendor(): array
    {
        $token = self::extractToken();
        if (!$token) {
            Response::error('Token de autenticacion requerido', null, 401);
        }

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT * FROM vendors
            WHERE auth_token = ? AND status = 'active'
            AND (auth_token_expires IS NULL OR auth_token_expires > NOW())
        ");
        $stmt->execute([self::hashToken($token)]);
        $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$vendor) {
            Response::error('Sesion expirada o invalida', null, 401);
        }

        $_SESSION['user_id'] = $vendor['id'];
        $_SESSION['user_email'] = $vendor['email'];
        $_SESSION['user_role'] = $vendor['role'];

        return $vendor;
    }

    public static function requireSuperAdmin(): array
    {
        $vendor = self::requireVendor();
        if ($vendor['role'] !== 'super_admin') {
            Response::error('Acceso restringido a super administradores', null, 403);
        }
        return $vendor;
    }

    public static function requireBuyer(): array
    {
        $token = self::extractToken();
        if (!$token) {
            Response::error('Token de autenticacion requerido', null, 401);
        }

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE auth_token = ?");
        $stmt->execute([self::hashToken($token)]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            Response::error('Sesion expirada', null, 401);
        }

        return $user;
    }

    public static function requireLogin(): array
    {
        $token = self::extractToken();
        if (!$token) {
            Response::error('Token de autenticacion requerido', null, 401);
        }
        $hashed = self::hashToken($token);

        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("
            SELECT *, 'vendor' as auth_type FROM vendors
            WHERE auth_token = ? AND status = 'active'
            AND (auth_token_expires IS NULL OR auth_token_expires > NOW())
        ");
        $stmt->execute([$hashed]);
        $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($vendor) return $vendor;

        $stmt = $db->prepare("SELECT *, 'buyer' as auth_type FROM users WHERE auth_token = ?");
        $stmt->execute([$hashed]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) return $user;

        Response::error('Sesion expirada', null, 401);
    }

    public static function requireAdmin(): array
    {
        return self::requireVendor();
    }

    public static function requireRole($roles): array
    {
        $user = self::requireVendor();
        if (is_string($roles)) $roles = [$roles];
        if (!in_array($user['role'], $roles)) {
            Response::error('No tienes permisos para esta accion', null, 403);
        }
        return $user;
    }

    private static function extractToken(): ?string
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            $headers = [];
            foreach ($_SERVER as $name => $value) {
                if (str_starts_with($name, 'HTTP_')) {
                    $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                    $headers[$key] = $value;
                }
            }
        }

        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
