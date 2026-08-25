<?php
/**
 * Adapta el motor de WhatsApp al dominio de MisRifas: rifas cuyo premio es
 * un producto de tecnologia. No reimplementa reserva/disponibilidad -
 * reusa RaffleRepository/TicketRepository/UserRepository, el mismo codigo
 * que ya usan api/tickets/reserve.php y api/raffles/index.php.
 *
 * "Transaccion" del motor = uno o mas boletos reservados juntos. MisRifas
 * reserva boletos individuales (una fila por numero), asi que el id de
 * transaccion es una cadena compuesta "WA-{raffle_id}-{ticket_id1,...}"
 * que estadoTransaccion()/cancelarTransaccion()/confirmarTransaccion()
 * parsean para operar sobre todos los tickets referenciados. Ver Fase 2
 * Paso 3 en el plan para la justificacion completa de esta decision.
 *
 * confirmarTransaccion() NO esta expuesta como herramienta invocable por
 * el LLM (confirmado leyendo Core/ToolEngine.php) - solo la llama codigo
 * propio de MisRifas ya verificado: el webhook de Wompi/Nequi tras validar
 * firma, o POST /api/admin/payments.php (action=approve). El bot jamas
 * marca un boleto como pagado por su cuenta.
 */

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../repositories/RaffleRepository.php';
require_once __DIR__ . '/../repositories/TicketRepository.php';
require_once __DIR__ . '/../repositories/UserRepository.php';

class RaffleDomainAdapter implements
    \ElkinLinan\WhatsappAiEngine\Ports\DomainAdapter,
    \ElkinLinan\WhatsappAiEngine\Ports\SoportaAvisoInterno
{
    private RaffleRepository $raffleRepo;
    private TicketRepository $ticketRepo;
    private UserRepository $userRepo;
    private int $vendorId;

    public function __construct(int $vendorId)
    {
        $this->vendorId = $vendorId;
        $this->raffleRepo = new RaffleRepository();
        $this->ticketRepo = new TicketRepository();
        $this->userRepo = new UserRepository();
    }

    public function contextoCliente(array $conversacion): array
    {
        $telefono = (string)($conversacion['telefono'] ?? '');
        $user = ($telefono !== '' && strpos($telefono, '@') === false)
            ? $this->userRepo->findByPhone($telefono)
            : null;

        // PromptComposer.php exige exactamente estas 4 claves (nombre,
        // es_nuevo, pedidos_abiertos, puntos - este ultimo puede ser null
        // pero tiene que existir, lo compara con !== null). No hay concepto
        // de puntos de fidelidad en MisRifas, se deja null a proposito.
        // "pedidos_abiertos" se mapea a boletos reservados sin pagar aun.
        $reservados = 0;
        if ($user) {
            foreach ($this->ticketRepo->getUserTickets((int)$user['id']) as $t) {
                if ($t['status'] === 'reserved') {
                    $reservados++;
                }
            }
        }

        return [
            'nombre' => $user['name'] ?? (string)($conversacion['nombre_contacto'] ?? ''),
            'es_nuevo' => !$user,
            'pedidos_abiertos' => $reservados,
            'puntos' => null,
        ];
    }

    public function buscarItems(?string $busqueda = null, array $filtros = [], int $limite = 60): array
    {
        $filters = ['vendor_id' => $this->vendorId];
        if ($busqueda) {
            $filters['search'] = $busqueda;
        }
        if (!empty($filtros['ciudad'])) {
            $filters['city'] = $filtros['ciudad'];
        }
        if (!empty($filtros['departamento'])) {
            $filters['department'] = $filtros['departamento'];
        }

        $perPage = max(1, min($limite, 60));
        $raffles = $this->raffleRepo->getActiveRaffles($filters, 1, $perPage);

        $out = [];
        foreach ($raffles as $r) {
            $out[] = $this->aItem($r);
        }
        return $out;
    }

    public function detalleItem(string $id): ?array
    {
        $raffle = $this->raffleRepo->getRaffleWithStats((int)$id);
        if (!$raffle || !$this->perteneceAlVendor($raffle)) {
            return null;
        }
        $item = $this->aItem($raffle);
        $item['sorteo'] = $raffle['draw_date'] ?? null;
        $item['loteria'] = $raffle['lottery_name'] ?? null;
        $item['total_numeros'] = (int)($raffle['total_tickets'] ?? 0);
        return $item;
    }

    public function disponibilidad(string $id): ?int
    {
        $raffle = $this->raffleRepo->findById((int)$id);
        if (!$raffle || !$this->perteneceAlVendor($raffle)) {
            return null;
        }
        $stats = $this->ticketRepo->getTicketStats((int)$id);
        return (int)($stats['available'] ?? 0);
    }

    public function calcularTotal(array $items, float $extra = 0.0): array
    {
        $lineas = [];
        $subtotal = 0.0;
        foreach ($items as $it) {
            $raffleId = (int)($it['producto_id'] ?? 0);
            $raffle = $this->raffleRepo->findById($raffleId);
            if (!$raffle || !$this->perteneceAlVendor($raffle)) {
                throw new \InvalidArgumentException('Esa rifa no existe.');
            }
            $cantidad = max(1, (int)($it['cantidad'] ?? 1));
            $precio = (float)$raffle['ticket_price'];
            $importe = $precio * $cantidad;
            $subtotal += $importe;
            $lineas[] = [
                'producto_id' => (string)$raffleId,
                'nombre' => $raffle['name'],
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'importe' => $importe,
            ];
        }
        return ['lineas' => $lineas, 'subtotal' => $subtotal, 'total' => $subtotal + $extra];
    }

    public function crearTransaccion(array $conversacion, array $items, array $datos = []): array
    {
        if (empty($items)) {
            throw new \InvalidArgumentException('No se especifico ninguna rifa.');
        }
        $item = $items[0];
        $raffleId = (int)($item['producto_id'] ?? 0);
        $cantidad = max(1, (int)($item['cantidad'] ?? 1));
        $numerosSolicitados = array_values(array_unique(array_map('strval', $item['numeros'] ?? [])));

        $raffle = $this->raffleRepo->getRaffleWithStats($raffleId);
        if (!$raffle || !$this->perteneceAlVendor($raffle) || $raffle['status'] !== 'active') {
            throw new \InvalidArgumentException('Esa rifa no existe o no esta activa.');
        }

        $telefono = (string)($conversacion['telefono'] ?? '');
        if ($telefono === '' || strpos($telefono, '@') !== false) {
            throw new \InvalidArgumentException('No pude identificar tu numero de telefono para reservar el boleto.');
        }

        $user = $this->userRepo->findByPhone($telefono);
        if (!$user) {
            $userId = $this->userRepo->create([
                'unique_id' => bin2hex(random_bytes(16)),
                'name' => (string)($conversacion['nombre_contacto'] ?: 'Cliente WhatsApp'),
                'phone_whatsapp' => $telefono,
            ]);
            $user = $this->userRepo->findById($userId);
        }

        if ($numerosSolicitados) {
            $numeros = array_slice($numerosSolicitados, 0, $cantidad);
        } else {
            // Margen x3 por si algunos ya no estan disponibles al momento de reservar.
            $disponibles = $this->ticketRepo->getAvailableTickets($raffleId, $cantidad * 3);
            $numeros = array_slice(array_column($disponibles, 'ticket_number'), 0, $cantidad);
        }

        // reserveTicket() ya hace su propio lock+transaccion por numero (SELECT FOR
        // UPDATE); no hay forma de envolver varios numeros en una unica transaccion
        // atomica sin tocar ese metodo, asi que se acepta exito parcial: se
        // reservan los que se puedan y se informa el total real conseguido.
        $ticketIds = [];
        $total = 0.0;
        foreach ($numeros as $numero) {
            $ticket = $this->ticketRepo->reserveTicket($raffleId, (string)$numero, (int)$user['id'], 2);
            if ($ticket) {
                $ticketIds[] = (int)$ticket['id'];
                $total += (float)$raffle['ticket_price'];
            }
        }

        if (empty($ticketIds)) {
            throw new \InvalidArgumentException('No se pudo reservar ningun numero - puede que ya no esten disponibles.');
        }

        return ['id' => 'WA-' . $raffleId . '-' . implode(',', $ticketIds), 'total' => $total];
    }

    public function estadoTransaccion(string $id): array
    {
        $parsed = $this->parseTransaccionId($id);
        if (!$parsed) {
            return [];
        }
        $tickets = array_filter(array_map(fn($tid) => $this->ticketRepo->findById($tid), $parsed['ticket_ids']));
        if (!$tickets) {
            return [];
        }
        $estados = array_unique(array_column($tickets, 'status'));
        if (count($estados) === 1 && $estados[0] === 'paid') {
            $estado = 'pagado';
        } elseif (in_array('available', $estados, true)) {
            $estado = 'cancelado';
        } else {
            $estado = 'esperando pago';
        }
        return ['estado' => $estado, 'numeros' => array_column($tickets, 'ticket_number')];
    }

    public function transaccionesDe(int $conversacionId): array
    {
        $db = \ElkinLinan\WhatsappAiEngine\Engine::db();
        $conv = $db->fetch('SELECT telefono, cliente_id FROM wa_conversaciones WHERE id = ?', [$conversacionId]);
        if (!$conv) {
            return [];
        }
        $user = !empty($conv['cliente_id'])
            ? $this->userRepo->findById((int)$conv['cliente_id'])
            : (($conv['telefono'] && strpos($conv['telefono'], '@') === false) ? $this->userRepo->findByPhone($conv['telefono']) : null);
        if (!$user) {
            return [];
        }

        $tickets = $this->ticketRepo->getUserTickets((int)$user['id']);
        $out = [];
        foreach ($tickets as $t) {
            $out[] = [
                'id' => 'WA-' . $t['raffle_id'] . '-' . $t['id'],
                'rifa' => $t['raffle_name'] ?? '',
                'numero' => $t['ticket_number'],
                'estado' => $t['status'] === 'paid' ? 'pagado' : 'esperando pago',
            ];
        }
        return $out;
    }

    public function cancelarTransaccion(string $id): array
    {
        $parsed = $this->parseTransaccionId($id);
        if (!$parsed) {
            return ['ok' => false];
        }
        foreach ($parsed['ticket_ids'] as $tid) {
            $t = $this->ticketRepo->findById($tid);
            if ($t && $t['status'] === 'reserved') {
                $this->ticketRepo->update($tid, [
                    'status' => 'available',
                    'user_id' => null,
                    'reserved_at' => null,
                    'reserved_until' => null,
                ]);
            }
        }
        return ['ok' => true];
    }

    public function confirmarTransaccion(string $id): bool
    {
        $parsed = $this->parseTransaccionId($id);
        if (!$parsed) {
            return false;
        }
        $algunoConfirmado = false;
        foreach ($parsed['ticket_ids'] as $tid) {
            $t = $this->ticketRepo->findById($tid);
            if ($t && $t['status'] === 'reserved') {
                if ($this->ticketRepo->update($tid, ['status' => 'paid', 'paid_at' => date('Y-m-d H:i:s')])) {
                    $algunoConfirmado = true;
                }
            }
        }
        return $algunoConfirmado;
    }

    public function capacidades(): array
    {
        return ['aviso_interno'];
    }

    /**
     * El aviso al numero de guardia (handoff_numero) ya lo manda
     * HumanHandoff::transferir() por su cuenta, y el evento ya queda en
     * wa_eventos via AuditLogger. Este metodo es el punto de extension
     * para integrarlo al panel de notificaciones propio de MisRifas mas
     * adelante; por ahora solo deja rastro, sin inventar una tabla nueva.
     */
    public function avisarEquipo(array $conversacion, string $motivo): void
    {
        error_log(sprintf(
            '[WhatsApp][handoff] vendor=%d telefono=%s motivo=%s',
            $this->vendorId,
            $conversacion['telefono'] ?? '',
            $motivo
        ));
    }

    private function aItem(array $raffle): array
    {
        return [
            'id' => (string)$raffle['id'],
            'nombre' => $raffle['name'],
            'descripcion' => mb_strimwidth((string)($raffle['description'] ?? ''), 0, 200, '...'),
            'precio' => (float)$raffle['ticket_price'],
            'stock' => (int)($raffle['available_tickets'] ?? 0),
        ];
    }

    private function perteneceAlVendor(array $raffle): bool
    {
        $raffleVendorId = (int)($raffle['vendor_id'] ?? $raffle['created_by'] ?? 0);
        return $raffleVendorId === $this->vendorId;
    }

    /** @return array{raffle_id:int,ticket_ids:int[]}|null */
    private function parseTransaccionId(string $id): ?array
    {
        if (!preg_match('/^WA-(\d+)-([\d,]+)$/', $id, $m)) {
            return null;
        }
        return ['raffle_id' => (int)$m[1], 'ticket_ids' => array_map('intval', explode(',', $m[2]))];
    }
}
