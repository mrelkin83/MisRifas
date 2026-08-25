<?php

namespace MisRifas\Ws;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

/**
 * TapazoHandler - Maneja conexiones WebSocket para el juego El Tapazo.
 * Cada sala (tapazo) agrupa jugadores conectados.
 */
class TapazoHandler implements MessageComponentInterface
{
    /** @var \SplObjectStorage Todos los clientes conectados */
    protected $clients;

    /** @var array<string, ConnectionInterface[]> Clientes agrupados por tapazo_id */
    public static $rooms = [];

    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
        echo "TapazoHandler inicializado\n";
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);

        $query = $conn->httpRequest->getUri()->getQuery();
        parse_str($query, $params);
        $conn->tapazoId = $params['tapazo_id'] ?? null;

        if ($conn->tapazoId) {
            self::$rooms[$conn->resourceId] = $conn;
            echo "Nuevo cliente conectado a tapazo {$conn->tapazoId} (total: " . count($this->clients) . ")\n";
        }

        $conn->send(json_encode([
            'event' => 'connected',
            'data'  => ['tapazo_id' => $conn->tapazoId],
        ]));
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);
        if (!$data || !isset($data['event'])) {
            return;
        }

        echo "Mensaje recibido: event={$data['event']}, tapazo={$from->tapazoId}\n";

        switch ($data['event']) {
            case 'ping':
                $from->send(json_encode(['event' => 'pong']));
                break;

            case 'join':
                $this->broadcast($from->tapazoId, 'player_joined', $data['data'] ?? [], $from);
                break;

            case 'destape':
            case 'reveal':
                $this->broadcast($from->tapazoId, 'tapa_revealed', $data['data'] ?? [], $from);
                break;

            default:
                $this->broadcast($from->tapazoId, $data['event'], $data['data'] ?? [], $from);
                break;
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        unset(self::$rooms[$conn->resourceId]);
        echo "Cliente desconectado de tapazo {$conn->tapazoId} (total: " . count($this->clients) . ")\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }

    /**
     * Broadcast a todos los clientes en la misma sala de tapazo.
     */
    private function broadcast(?string $tapazoId, string $event, array $data, ConnectionInterface $sender): void
    {
        if (!$tapazoId) return;

        $payload = json_encode([
            'event' => $event,
            'data'  => $data,
        ]);

        foreach (self::$rooms as $conn) {
            if ($conn->tapazoId === $tapazoId && $conn !== $sender) {
                $conn->send($payload);
            }
        }
    }
}
