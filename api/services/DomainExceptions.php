<?php

declare(strict_types=1);

/**
 * Excepciones de dominio (promt2.md §17).
 *
 * En un mismo archivo porque el proyecto no tiene autoloader de clases
 * propias (todo es require_once explícito) y son tipos triviales sin lógica:
 * un archivo por clase multiplicaría requires sin ganar nada.
 */

abstract class DomainException_ extends RuntimeException
{
}

final class TicketNotFound extends DomainException_
{
    public function __construct(int $raffleId, string $number)
    {
        parent::__construct("El boleto {$number} no existe en la rifa {$raffleId}");
    }
}

final class TicketNotAvailable extends DomainException_
{
    public string $ticketNumber;

    public function __construct(int $raffleId, string $number, string $currentStatus = '')
    {
        $this->ticketNumber = $number;
        parent::__construct(
            "El boleto {$number} de la rifa {$raffleId} no está disponible"
            . ($currentStatus !== '' ? " (estado: {$currentStatus})" : '')
        );
    }
}

final class InvalidTransition extends DomainException_
{
    public function __construct(int $ticketId, ?string $from, string $to)
    {
        parent::__construct("Transición ilegal del ticket {$ticketId}: " . ($from ?? '?') . " → {$to}");
    }
}

final class ReservationExpired extends DomainException_
{
    public function __construct(int $ticketId)
    {
        parent::__construct("La reserva del ticket {$ticketId} ya venció");
    }
}

final class RescheduleNotAllowed extends DomainException_
{
    public function __construct(int $raffleId, string $motivo)
    {
        parent::__construct("La rifa {$raffleId} no puede reprogramarse: {$motivo}");
    }
}
