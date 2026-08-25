<?php
/**
 * API: Listar Loterías
 * GET /api/lotteries/index.php
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Método no permitido', null, 405);
}

try {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->query("SELECT * FROM lotteries WHERE active = 1 ORDER BY name ASC");
    $lotteries = $stmt->fetchAll();

    Response::success($lotteries);

} catch (Exception $e) {
    Response::serverError('Error al cargar las loterías');
}
