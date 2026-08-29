<?php
/**
 * Página de Procesamiento de Pago — redirect al flujo real.
 *
 * El flujo Wompi nunca se terminó (los botones de esta página solo
 * SIMULABAN el pago) y la plataforma se consolidó en el flujo manual
 * de payment.php. Solo la rama gateway==='wompi' de
 * api/payments/create-reservation.php apuntaba aquí, así que esta
 * página queda como puente: manda la reserva al flujo manual real.
 */

require_once __DIR__ . '/../config/app.php';

$reservationId = $_GET['reservation_id'] ?? '';

if ($reservationId !== '') {
    header('Location: ' . BASE_PATH . '/public/payment.php?reservation_id=' . urlencode($reservationId));
} else {
    header('Location: ' . BASE_PATH . '/public/index.php');
}
exit;
