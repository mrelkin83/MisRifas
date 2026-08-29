<?php
/**
 * [DEPRECADO] Liberar Reservas Expiradas.
 *
 * La logica de expiracion se unifico en cron/expire-reservations.php (que
 * ademas de liberar tickets, sincroniza numero_reservas y cancela los
 * payment_intents pendientes). Este archivo queda solo como shim de
 * compatibilidad: si un crontab del servidor todavia apunta aqui, sigue
 * ejecutando el cron canonico en vez de una version parcial y divergente.
 *
 * Actualiza tu crontab para llamar a expire-reservations.php directamente y
 * elimina la entrada de este archivo (ver DEPLOY.md).
 */

require __DIR__ . '/expire-reservations.php';
