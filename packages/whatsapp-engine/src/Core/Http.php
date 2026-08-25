<?php
/**
 * ============================================================================
 * Http — cliente HTTP mínimo compartido por todo el motor
 * ============================================================================
 * El proyecto ya usa curl a pelo en DianSoapClient y FeEmisorAlegra. Aquí se
 * centraliza para no repetir seis veces el manejo de timeouts y errores, y
 * sobre todo para que NINGÚN adapter tenga excusa para volcar cabeceras
 * (donde vive la API Key) en un log.
 */

namespace ElkinLinan\WhatsappAiEngine\Core;

class Http
{
    /**
     * @return array ['ok'=>bool, 'status'=>int, 'body'=>string, 'json'=>?array, 'error'=>string]
     */
    public static function request(string $metodo, string $url, array $opts = []): array
    {
        $cabeceras = $opts['headers'] ?? [];
        $cuerpo    = $opts['body']    ?? null;
        $timeout   = (int)($opts['timeout'] ?? 60);
        $out = ['ok' => false, 'status' => 0, 'body' => '', 'json' => null, 'error' => ''];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => strtoupper($metodo),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(15, $timeout),
            CURLOPT_FOLLOWLOCATION => false,   // una redirección se lleva la cabecera de auth
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        if ($cabeceras) curl_setopt($ch, CURLOPT_HTTPHEADER, $cabeceras);
        if ($cuerpo !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($cuerpo) ? $cuerpo : json_encode($cuerpo, JSON_UNESCAPED_UNICODE));
        }

        $resp = curl_exec($ch);
        if ($resp === false) {
            $out['error'] = curl_error($ch);
            curl_close($ch);
            return $out;
        }
        $out['status'] = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $out['body'] = (string)$resp;
        $json = json_decode($out['body'], true);
        if (is_array($json)) $out['json'] = $json;
        $out['ok'] = $out['status'] >= 200 && $out['status'] < 300;
        if (!$out['ok'] && $out['error'] === '') {
            // Mensaje del proveedor si lo trae, recortado: va a la bitácora, no al cliente.
            $out['error'] = $json['error']['message'] ?? $json['message'] ?? ('HTTP ' . $out['status']);
            $out['error'] = mb_substr((string)$out['error'], 0, 500);
        }
        return $out;
    }

    public static function json(string $metodo, string $url, array $cabeceras = [], $cuerpo = null, int $timeout = 60): array
    {
        $cabeceras[] = 'Content-Type: application/json';
        return self::request($metodo, $url, ['headers' => $cabeceras, 'body' => $cuerpo, 'timeout' => $timeout]);
    }

    /**
     * ¿La URL apunta a un destino público seguro? Rechaza esquemas que no sean
     * http(s) y hosts que resuelven a direcciones internas (loopback, privadas,
     * link-local —incluida la metadata de la nube 169.254.169.254—, ULA).
     *
     * SE USA SOLO PARA URLs QUE VIENEN DEL USUARIO: probar la conexión, listar
     * modelos, o las *_url que guarda el propio negocio. Las URLs que pone la
     * PLATAFORMA por variable de entorno (evolution:8080, ollama:11434,
     * whisper:8000 del modo gestionado) NO pasan por aquí, así que la
     * integración interna sigue funcionando. Sin esta puerta, un usuario podía
     * hacer que el servidor sondeara la red interna y leyera parte de la
     * respuesta (SSRF).
     */
    public static function destinoPublicoSeguro(string $url): bool
    {
        $u = trim($url);
        if (!preg_match('#^https?://#i', $u)) return false;

        $host = parse_url($u, PHP_URL_HOST);
        if (!is_string($host) || $host === '') return false;
        $host = trim($host, '[]');   // literal IPv6 entre corchetes

        // Resolver el host a IPs y comprobar TODAS (defensa parcial ante DNS
        // rebinding: si cualquier registro apunta a una IP interna, se rechaza).
        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            $recs = @dns_get_record($host, DNS_A | DNS_AAAA);
            if (is_array($recs)) {
                foreach ($recs as $r) {
                    if (!empty($r['ip']))   $ips[] = $r['ip'];
                    if (!empty($r['ipv6'])) $ips[] = $r['ipv6'];
                }
            }
            if (!$ips) {
                $v4 = gethostbyname($host);
                if ($v4 && $v4 !== $host) $ips[] = $v4;
            }
        }
        if (!$ips) return false;   // no resuelve → no se arriesga

        foreach ($ips as $ip) {
            // NO_PRIV_RANGE: 10/8, 172.16/12, 192.168/16, fc00::/7.
            // NO_RES_RANGE: loopback, link-local (169.254/16 y fe80::/10,
            //   incluida la metadata 169.254.169.254), 0.0.0.0, etc.
            if (filter_var($ip, FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }
        return true;
    }

    /** Descarga binaria (audio o imagen de WhatsApp). Devuelve null si falla. */
    public static function descargar(string $url, int $maxBytes = 26214400, int $timeout = 60): ?array
    {
        $r = self::request('GET', $url, ['timeout' => $timeout]);
        if (!$r['ok'] || $r['body'] === '') return null;
        if (strlen($r['body']) > $maxBytes) return null;   // 25 MB: tope de WhatsApp
        return ['bytes' => $r['body'], 'size' => strlen($r['body'])];
    }
}
