<?php
/**
 * WebSocket Server - MisRifas
 * Maneja conexiones WebSocket para Tapazo y notificaciones en tiempo real.
 *
 * Puertos:
 *   - 8081: WebSocket (clientes browser)
 *   - 8082: TCP interno (broadcast desde API PHP)
 *
 * Iniciar: php ws/server.php
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/env.php';
\loadEnv();

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\Socket\Server as TcpServer;
use React\EventLoop\Loop;

$loop = Loop::get();

// WebSocket server en puerto 8081
$wsServer = new WsServer(new \MisRifas\Ws\TapazoHandler());

$wsIoServer = new IoServer(
    new HttpServer($wsServer),
    new TcpServer('0.0.0.0:8081', $loop),
    $loop
);

// TCP interno en puerto 8082 para broadcasts desde la API PHP
$tcpServer = new TcpServer('127.0.0.1:8082', $loop);
$tcpServer->on('connection', function ($conn) use ($wsServer) {
    $buffer = '';
    $conn->on('data', function ($data) use ($conn, &$buffer) {
        $buffer .= $data;
        // Procesar mensajes delimitados por newline
        while (($pos = strpos($buffer, "\n")) !== false) {
            $message = substr($buffer, 0, $pos);
            $buffer = substr($buffer, $pos + 1);

            $json = json_decode($message, true);
            if ($json && isset($json['tapazo_id'])) {
                // Broadcast a todos los clientes conectados a este tapazo
                foreach (\MisRifas\Ws\TapazoHandler::$rooms as $conn) {
                    if ($conn->tapazoId === $json['tapazo_id']) {
                        $conn->send(json_encode([
                            'event' => $json['event'] ?? 'update',
                            'data'  => $json['data'] ?? [],
                        ]));
                    }
                }
            }
        }
    });
});

echo "WebSocket server started on ws://0.0.0.0:8081\n";
echo "Internal TCP bridge on tcp://127.0.0.1:8082\n";
$loop->run();
