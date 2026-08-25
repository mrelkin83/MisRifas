<?php
/**
 * WsBroadcast - Utilidad para enviar eventos al servidor WebSocket
 * desde los endpoints de la API PHP.
 *
 * Uso:
 *   WsBroadcast::send('tapazo_abc123', 'player_joined', ['nombre' => 'Juan']);
 */

class WsBroadcast
{
    private static string $host = '127.0.0.1';
    private static int $port = 8082;
    private static float $timeout = 1.0;

    /**
     * Envia un evento al servidor WebSocket para broadcast a clientes conectados.
     */
    public static function send(string $tapazoId, string $event, array $data = []): bool
    {
        try {
            $socket = @fsockopen(self::$host, self::$port, $errno, $errstr, self::$timeout);
            if (!$socket) {
                error_log("WsBroadcast: No se pudo conectar ({$errno}: {$errstr})");
                return false;
            }

            $payload = json_encode([
                'tapazo_id' => $tapazoId,
                'event'     => $event,
                'data'      => $data,
            ]) . "\n";

            fwrite($socket, $payload);
            fclose($socket);

            return true;
        } catch (\Throwable $e) {
            error_log("WsBroadcast error: " . $e->getMessage());
            return false;
        }
    }
}
