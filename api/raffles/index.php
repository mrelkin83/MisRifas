<?php
/**
 * API: Listar Rifas
 * GET /api/raffles/index.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');

    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Logger.php';
require_once __DIR__ . '/../../api/repositories/RaffleRepository.php';

try {
    $page = (int)($_GET['page'] ?? 1);
    $perPage = (int)($_GET['per_page'] ?? 20);

    $filters = [
        'search' => $_GET['search'] ?? '',
        'department' => $_GET['department'] ?? '',
        'city' => $_GET['city'] ?? '',
        'min_price' => $_GET['min_price'] ?? null,
        'max_price' => $_GET['max_price'] ?? null,
        'lottery_id' => $_GET['lottery_id'] ?? null,
        'order_by' => $_GET['order_by'] ?? 'draw_date',
        'order_dir' => $_GET['order_dir'] ?? 'ASC',
    ];

    $raffleRepo = new RaffleRepository();
    $raffles = $raffleRepo->getActiveRaffles($filters, $page, $perPage);

    Response::success([
        'raffles' => $raffles,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => count($raffles)
        ]
    ]);

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al cargar las rifas');
}
