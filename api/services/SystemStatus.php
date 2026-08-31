<?php

declare(strict_types=1);

/**
 * Auto-diagnóstico del sistema: VERIFICA cada canal/subsistema en vivo en
 * lugar de afirmar que funciona. Lo consumen la tarjeta de Comunicaciones
 * del panel (api/admin/system_status.php) y el CLI (tools/diagnostico.php).
 *
 * Cada check: ['key','nombre','estado' => ok|warn|fail,'detalle','arreglo'].
 */
require_once __DIR__ . '/../../config/brand.php';

class SystemStatus
{
    public static function checks(PDO $db): array
    {
        $out = [];

        // ── Identidad de la plataforma (nombre/correo/dominio administrables) ──
        // El dominio y hasta el nombre pueden cambiar en el despliegue final:
        // NADA de esto está quemado; sale de Configuración → General y de
        // APP_URL en el .env. Este check muestra lo que está vigente HOY.
        $pn = plataforma('nombre');
        $peCfg = plataforma('email_config');
        $host = plataforma('dominio');
        $out[] = self::c('identidad', 'Identidad de la plataforma', $peCfg !== '' ? 'ok' : 'warn',
            "Nombre: «{$pn}» · Correo de contacto: " . ($peCfg !== '' ? $peCfg : "SIN definir (mientras tanto se deriva no-reply@{$host})")
            . " · Dominio (APP_URL): {$host}. Correos, mensajes, boletas y páginas usan estos valores en vivo.",
            $peCfg !== '' ? '' : 'Define nombre y correo en Configuración → General. El dominio se cambia con APP_URL en el .env del servidor.');

        // ── SMTP (correo del sistema) ──
        $cfg = [];
        foreach ($db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'mailing_%'") as $r) {
            $cfg[$r['setting_key']] = $r['setting_value'];
        }
        $smtpHost = $cfg['mailing_smtp_host'] ?: (getenv('SMTP_HOST') ?: '');
        $port = (int)(($cfg['mailing_smtp_port'] ?? '') !== '' ? $cfg['mailing_smtp_port'] : (getenv('SMTP_PORT') ?: 587));
        // Remitente EFECTIVO: el mismo que usará MailService (nada supuesto).
        $from = $cfg['mailing_smtp_from'] ?: (getenv('EMAIL_FROM_ADDRESS') ?: plataforma('email'));
        if ($smtpHost === '') {
            $out[] = self::c('smtp', 'Correo (SMTP)', 'fail', 'Sin servidor configurado: NINGÚN correo sale (resultados, boletas, OTP).',
                'Configura host/puerto en Configuración → Correo del sistema.');
        } else {
            $target = ($port === 465) ? "ssl://$smtpHost" : $smtpHost;
            $s = @fsockopen($target, $port, $en, $er, 4);
            if (!$s) {
                $out[] = self::c('smtp', 'Correo (SMTP)', 'fail', "No responde: $smtpHost:$port ($er).",
                    'Revisa host/puerto o el servicio de correo.');
            } else {
                fclose($s);
                $esCaptura = in_array($smtpHost, ['127.0.0.1', 'localhost'], true) && in_array($port, [1025, 8025], true);
                if ($esCaptura) {
                    $out[] = self::c('smtp', 'Correo (SMTP)', 'warn', "Conectado a $smtpHost:$port — es MAILPIT (captura de pruebas): los correos se guardan en una bandeja local y NO llegan a destinatarios reales.",
                        'Para producción real: configura el SMTP de tu proveedor definitivo en Configuración → Correo del sistema (host, puerto y credenciales que te dé tu servicio de correo).');
                } else {
                    $out[] = self::c('smtp', 'Correo (SMTP)', 'ok', "Conectado a $smtpHost:$port. Remitente: $from.", '');
                }
            }
        }

        // ── Autenticación del dominio remitente (SPF/DKIM) ──
        // Que el SMTP acepte el correo NO es entrega: Gmail/Outlook exigen que
        // el dominio del remitente publique SPF o DKIM y sin eso lo descartan
        // en silencio (el usuario ve "enviado" y nunca le llega nada). Esto se
        // comprueba EN VIVO contra el DNS real.
        $fromDom = substr(strrchr($from, '@') ?: '', 1);
        $esCapturaLocal = in_array($smtpHost, ['127.0.0.1', 'localhost'], true);
        if ($smtpHost !== '' && !$esCapturaLocal && $fromDom !== '') {
            $spf = false;
            foreach ((array)@dns_get_record($fromDom, DNS_TXT) as $t) {
                foreach ((array)($t['entries'] ?? [$t['txt'] ?? '']) as $txt) {
                    if (stripos((string)$txt, 'v=spf1') === 0) { $spf = true; break 2; }
                }
            }
            $dkim = false;
            foreach (['hostingermail-a', 'default', 'mail', 'postal'] as $sel) {
                if (@dns_get_record("{$sel}._domainkey.{$fromDom}", DNS_CNAME) || @dns_get_record("{$sel}._domainkey.{$fromDom}", DNS_TXT)) {
                    $dkim = true;
                    break;
                }
            }
            $out[] = self::c('correo_dns', 'Autenticación del correo (SPF/DKIM)',
                ($spf || $dkim) ? ($spf && $dkim ? 'ok' : 'warn') : 'fail',
                "Dominio remitente: {$fromDom} — SPF: " . ($spf ? 'publicado' : 'FALTA') . ' · DKIM: ' . ($dkim ? 'publicado' : 'FALTA')
                . (($spf || $dkim) ? '.' : '. Sin ninguno de los dos, Gmail/Outlook DESCARTAN los correos aunque el SMTP diga "enviado".'),
                ($spf && $dkim) ? '' : "Agrega en el DNS de {$fromDom} los registros SPF (TXT) y DKIM (CNAME) que indique tu proveedor de correo.");
        }

        // ── OTP por correo (depende del SMTP) ──
        $smtp = $out[1];
        $smtpOk = $smtp['estado'] !== 'fail';
        $out[] = self::c('otp_email', 'OTP por correo', $smtpOk ? $smtp['estado'] : 'fail',
            $smtpOk ? "Envía el código VERIFY-XXXXX desde $from usando el SMTP de arriba (hereda su estado)."
                    : 'Sin SMTP no hay OTP por correo: los registros nuevos no pueden verificarse por este canal.',
            $smtpOk ? '' : 'Configura el SMTP.');

        // ── OTP por WhatsApp (inverso: el usuario ENVÍA el código) ──
        $otpNum = (string)$db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'otp_whatsapp_number'")->fetchColumn();
        if (trim($otpNum) === '') {
            $out[] = self::c('otp_whatsapp', 'OTP por WhatsApp', 'warn',
                'SIN número de plataforma configurado: el canal se ofrece como "no disponible" y el registro cae al correo (degradación controlada, no magia).',
                'Escribe el número en el campo de abajo y guarda. Ese número debe estar vinculado al motor de WhatsApp para leer los códigos que le lleguen.');
        } else {
            $out[] = self::c('otp_whatsapp', 'OTP por WhatsApp', 'ok',
                "El registro abre wa.me/{$otpNum} con el código prellenado; el webhook del motor lo lee y verifica la cuenta.", '');
        }

        // ── Motor WhatsApp (Evolution) ──
        $evoUrl = getenv('WA_EVOLUTION_URL') ?: '';
        $evoKey = getenv('WA_EVOLUTION_APIKEY') ?: '';
        if ($evoUrl === '' || $evoKey === '') {
            $out[] = self::c('wa_engine', 'Motor WhatsApp (Evolution)', 'fail',
                'WA_EVOLUTION_URL/APIKEY sin definir en el .env: nadie puede vincular su WhatsApp.',
                'Define ambas en el .env del servidor.');
        } else {
            $ch = curl_init(rtrim($evoUrl, '/') . '/instance/fetchInstances');
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 4, CURLOPT_HTTPHEADER => ['apikey: ' . $evoKey]]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            unset($ch); // curl_close() está deprecado en PHP 8.5 (sin efecto desde 8.0)
            $insts = ($resp && $code === 200) ? (json_decode($resp, true) ?: []) : null;
            if ($insts === null) {
                $out[] = self::c('wa_engine', 'Motor WhatsApp (Evolution)', 'fail', "La API de Evolution no responde en $evoUrl (HTTP $code).",
                    'Revisa el contenedor Docker de Evolution.');
            } else {
                $abiertas = array_filter($insts, fn($i) => ($i['connectionStatus'] ?? '') === 'open');
                $nombres = implode(', ', array_map(fn($i) => ($i['name'] ?? '?') . ':' . ($i['connectionStatus'] ?? '?'), $insts));
                $out[] = self::c('wa_engine', 'Motor WhatsApp (Evolution)',
                    $abiertas ? 'ok' : ($insts ? 'warn' : 'warn'),
                    count($insts) . ' instancia(s): ' . ($nombres ?: 'ninguna') . '. '
                    . ($abiertas ? count($abiertas) . ' conectada(s) y enviando.' : 'NINGUNA conectada: nadie ha escaneado el QR — no sale ningún WhatsApp.'),
                    $abiertas ? '' : 'Vincula un número: Mi Perfil → WhatsApp → Vincular con código QR.');
            }
        }

        // ── SMS (Gammu) ──
        $smsEnabled = strtolower((string)getenv('SMS_ENABLED')) === 'true';
        $cmdGammu = DIRECTORY_SEPARATOR === '\\' ? 'where gammu 2>NUL' : 'command -v gammu 2>/dev/null';
        $gammu = trim((string)@shell_exec($cmdGammu));
        if (!$smsEnabled && $gammu === '') {
            $out[] = self::c('sms', 'SMS (Gammu)', 'warn',
                'Apagado (SMS_ENABLED=false) y gammu NO está instalado. Ojo: Gammu necesita un MÓDEM GSM FÍSICO con SIM — en un VPS de nube no puede funcionar; es para un servidor local con hardware.',
                'Si algún día lo quieres: servidor con módem USB + apt install gammu + SMS_ENABLED=true en .env. Mientras tanto, correo + WhatsApp cubren todo.');
        } elseif ($smsEnabled && $gammu === '') {
            $out[] = self::c('sms', 'SMS (Gammu)', 'fail', 'SMS_ENABLED=true pero gammu no está instalado.', 'apt install gammu o apaga SMS_ENABLED.');
        } else {
            $out[] = self::c('sms', 'SMS (Gammu)', $smsEnabled ? 'ok' : 'warn',
                'gammu instalado' . ($smsEnabled ? ' y habilitado.' : ' pero SMS_ENABLED=false (apagado).'), '');
        }

        // ── Almacenamiento y cron ──
        $dirs = ['storage/boletas', 'storage/comprobantes', 'storage/entregas'];
        $malos = array_filter($dirs, fn($d) => !is_writable(__DIR__ . '/../../' . $d));
        $out[] = self::c('storage', 'Almacenamiento', $malos ? 'fail' : 'ok',
            $malos ? 'Sin permiso de escritura: ' . implode(', ', $malos) . ' (boletas/comprobantes/evidencias fallarán).' : 'storage/ escribible (boletas, comprobantes, evidencias).',
            $malos ? 'chown www-data y permisos 755 en esas carpetas.' : '');

        $cronLog = __DIR__ . '/../../logs/cron.log';
        $edad = is_file($cronLog) ? (time() - filemtime($cronLog)) : null;
        $out[] = self::c('cron', 'Tareas programadas (cron)', ($edad !== null && $edad < 600) ? 'ok' : 'warn',
            $edad === null ? 'logs/cron.log no existe: el cron nunca ha corrido aquí.'
                : 'Última actividad hace ' . round($edad / 60) . ' min' . ($edad < 600 ? ' (vivo: expiración, notificaciones y sorteos girando).' : ' — parece detenido.'),
            ($edad !== null && $edad < 600) ? '' : 'Revisa la tarea programada del servidor (archivo en /etc/cron.d).');

        return $out;
    }

    private static function c(string $key, string $nombre, string $estado, string $detalle, string $arreglo): array
    {
        return ['key' => $key, 'nombre' => $nombre, 'estado' => $estado, 'detalle' => $detalle, 'arreglo' => $arreglo];
    }
}
