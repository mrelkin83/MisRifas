<?php

declare(strict_types=1);

/**
 * Rotación del número EMISOR de WhatsApp entre las instancias de la
 * plataforma (misrifas-v1..v5) que estén CONECTADAS.
 *
 * Objetivo anti-baneo: no enviar siempre desde el mismo número. En cada
 * tanda de envíos (process_notifications) se avanza al siguiente conectado
 * en round-robin y se fija como instancia activa (wa_config
 * .evolution_instancia) — todos los caminos de envío (resultados, OTP, bot)
 * salen por la activa, así que rotar la activa rota TODO sin tocar el motor.
 *
 * Interruptor administrable: system_settings wa_rotacion (WhatsApp IA →
 * Conexión). La ENTRADA no se afecta: el webhook está registrado en todas.
 */
class RotacionInstancias
{
    public static function habilitada(PDO $db): bool
    {
        $v = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'wa_rotacion'")->fetchColumn();
        return (string)$v === '1';
    }

    /**
     * Avanza la instancia activa al siguiente número CONECTADO (round-robin).
     * Devuelve el nombre nuevo, o null si no rotó (deshabilitada, una sola
     * conectada, o Evolution inalcanzable). Nunca lanza: rotar es best-effort
     * y jamás debe frenar una tanda de envíos.
     */
    public static function rotar(PDO $db): ?string
    {
        try {
            if (!self::habilitada($db)) {
                return null;
            }
            $url = rtrim((string)(getenv('WA_EVOLUTION_URL') ?: ''), '/');
            $key = (string)(getenv('WA_EVOLUTION_APIKEY') ?: '');
            if ($url === '' || $key === '') {
                return null;
            }

            $ch = curl_init($url . '/instance/fetchInstances');
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
                CURLOPT_HTTPHEADER => ['apikey: ' . $key]]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            unset($ch);
            if (!$resp || $code !== 200) {
                return null;
            }

            $conectadas = [];
            foreach ((array)json_decode($resp, true) as $i) {
                $n = (string)($i['name'] ?? '');
                if (preg_match('/^misrifas-v[1-5]$/', $n) && ($i['connectionStatus'] ?? '') === 'open') {
                    $conectadas[] = $n;
                }
            }
            sort($conectadas);
            if (count($conectadas) < 2) {
                return null; // con una (o ninguna) no hay entre qué rotar
            }

            // Fila de la PLATAFORMA en wa_config (su instancia es misrifas-v*).
            $fila = $db->query("SELECT id, evolution_instancia FROM wa_config
                                WHERE evolution_instancia REGEXP '^misrifas-v[1-5]$' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if (!$fila) {
                return null;
            }
            $actual = (string)$fila['evolution_instancia'];
            $pos = array_search($actual, $conectadas, true);
            $siguiente = $conectadas[($pos === false ? 0 : $pos + 1) % count($conectadas)];
            if ($siguiente === $actual) {
                return null;
            }
            $db->prepare('UPDATE wa_config SET evolution_instancia = ? WHERE id = ?')
               ->execute([$siguiente, (int)$fila['id']]);
            return $siguiente;
        } catch (Throwable $e) {
            error_log('[WhatsApp][rotacion] ' . $e->getMessage());
            return null;
        }
    }
}
