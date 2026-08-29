<?php
/**
 * API: Crear Tapazo
 * POST /api/tapazo/crear.php
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {     header('Access-Control-Allow-Origin: *');
 http_response_code(200); exit; }

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Validator.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $db = Database::getInstance()->getConnection();

    // Sanitizar texto libre al escribir (mismo criterio que el resto de la
    // app): titulo/descripcion se rinden via innerHTML en el panel admin, asi
    // que sin esto un tapazo con <img onerror> era XSS almacenado contra el
    // navegador del super_admin.
    $titulo = Validator::sanitize(trim($input['titulo'] ?? ''));
    $descripcion = Validator::sanitize(trim($input['descripcion'] ?? ''));
    // imagen_url solo puede ser vacia o una ruta local del sitio (nunca
    // http(s):// externo ni javascript:/data:).
    $imagen_url = trim($input['imagen_url'] ?? '');
    if ($imagen_url !== '' && !Validator::esRutaLocalSegura($imagen_url)) {
        Response::error('La imagen debe subirse desde el formulario (ruta local).', null, 400);
    }
    $cantidad_jugadores = intval($input['cantidad_jugadores'] ?? 6);
    if ($cantidad_jugadores < 2 || $cantidad_jugadores > 50) {
        Response::error('La cantidad de jugadores debe estar entre 2 y 50');
    }
    $valor_cupo = floatval($input['valor_cupo'] ?? 0);
    $regla = in_array($input['regla'] ?? '', ['alto_gana', 'bajo_gana'], true) ? $input['regla'] : 'alto_gana';
    $fecha_hora_destape = trim($input['fecha_hora_destape'] ?? '');

    if (empty($titulo) || empty($fecha_hora_destape)) {
        Response::error('Título y fecha de destape son requeridos');
    }

    $codigo_unico = sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X',
        mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF),
        mt_rand(0, 0x0FFF) | 0x4000, mt_rand(0, 0x3FFF) | 0x8000,
        mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF)
    );

    $stmt = $db->prepare("
        INSERT INTO tapazos (titulo, descripcion, imagen_url, cantidad_jugadores, valor_cupo, regla, fecha_hora_destape, estado, codigo_unico)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'creado', ?)
    ");
    $stmt->execute([$titulo, $descripcion, $imagen_url, $cantidad_jugadores, $valor_cupo, $regla, $fecha_hora_destape, $codigo_unico]);
    $tapazoId = (int)$db->lastInsertId();

    Response::success([
        'id' => $tapazoId,
        'codigo' => $codigo_unico,
        'url' => '/tapazo/index.php?codigo=' . $codigo_unico
    ], 'Tapazo creado exitosamente');

} catch (Exception $e) {
    error_log("Error al crear tapazo: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    // En desarrollo, mostrar el error completo
    if (getenv('APP_ENV') === 'development' || getenv('APP_DEBUG') === 'true') {
        Response::serverError('Error al crear tapazo: ' . $e->getMessage());
    } else {
        Response::serverError('Error al crear tapazo');
    }
}
