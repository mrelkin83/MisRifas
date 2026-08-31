<?php

/**
 * Identidad de la plataforma — 100% ADMINISTRABLE, nada quemado en código.
 *
 * Fuente de verdad: system_settings (editable por el super_admin en
 * Configuración → General):
 *   platform_name    → nombre visible de la plataforma (títulos, correos, boletas)
 *   platform_email   → correo de contacto/remitente por defecto
 *   contact_whatsapp → WhatsApp de soporte de la plataforma
 * El dominio NO se escribe en ningún sitio: se deriva de APP_URL (.env).
 *
 * Regla: ningún archivo debe volver a escribir el nombre de la plataforma ni
 * un dominio/correo literal. El nombre y dominio finales de producción aún no
 * existen; los actuales son de pruebas.
 *
 * Falla suave: si la BD no responde (página estática, instalación a medias)
 * se usan valores neutros derivados del host para no tumbar la página.
 */
if (!function_exists('plataforma')) {
    function plataforma(string $clave = 'nombre'): string
    {
        static $v = null;
        if ($v === null) {
            $host = parse_url(getenv('APP_URL') ?: '', PHP_URL_HOST) ?: 'localhost';
            $v = [
                'nombre'       => 'MisRifas',        // último recurso sin BD
                'email'        => '',                 // efectivo (derivado si falta)
                'email_config' => '',                 // tal cual está en la BD
                'whatsapp'     => '',
                'dominio'      => $host,
            ];
            try {
                require_once __DIR__ . '/database.php';
                $db = Database::getInstance()->getConnection();
                $q = $db->query("SELECT setting_key, setting_value FROM system_settings
                                 WHERE setting_key IN ('platform_name','platform_email','contact_whatsapp')");
                foreach ($q as $r) {
                    $val = trim((string)$r['setting_value']);
                    if ($r['setting_key'] === 'platform_name' && $val !== '') {
                        $v['nombre'] = $val;
                    } elseif ($r['setting_key'] === 'platform_email') {
                        $v['email_config'] = $val;
                    } elseif ($r['setting_key'] === 'contact_whatsapp') {
                        $v['whatsapp'] = $val;
                    }
                }
            } catch (Throwable $e) {
                // Sin BD: valores neutros; la página pública sigue sirviendo.
            }
            $v['email'] = $v['email_config'] !== '' ? $v['email_config'] : 'no-reply@' . $host;
        }
        return $v[$clave] ?? '';
    }

    /** Versión escapada para incrustar en HTML. */
    function plataforma_e(string $clave = 'nombre'): string
    {
        return htmlspecialchars(plataforma($clave), ENT_QUOTES, 'UTF-8');
    }
}
