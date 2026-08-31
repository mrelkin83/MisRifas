<?php
/**
 * API: Tapazos (panel admin/vendor) — listar y crear
 * GET  /api/tapazo/admin_list.php  → listar tapazos (del vendor, o todos si super_admin)
 * POST /api/tapazo/admin_list.php  → crear tapazo desde el panel
 *
 * Vive junto al resto de endpoints de Tapazo bajo api/tapazo/ (namespace
 * unificado). Comparte tabla y modelo con el flujo público (api/tapazo/
 * crear.php, unirse.php, etc.); estos endpoints son la variante autenticada
 * para gestión desde el panel.
 *
 * IMPORTANTE: este endpoint estaba escrito contra un esquema que nunca
 * existió en la BD (tabla tapazo_participants y columnas name/prize/
 * win_mode/total_participants/whatsapp). El esquema real es:
 *   tapazos(titulo, descripcion, imagen_url, cantidad_jugadores, valor_cupo,
 *           regla ENUM('alto_gana','bajo_gana'), fecha_hora_destape,
 *           estado ENUM('creado','lleno','esperando','destapando','finalizado'),
 *           codigo_unico, ultimo_revelado, created_by)
 *   tapazo_jugadores(nombre, cerveza_numero, numero_tapa, orden_destape)
 * Toda acción del panel lanzaba "Unknown column". Ahora se mapea al esquema
 * real conservando el contrato de campos que espera el JS del panel
 * (name/total_participants/win_mode highest|lowest/status/joined_count).
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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
require_once __DIR__ . '/../../api/utils/Auth.php';
require_once __DIR__ . '/../../api/utils/Validator.php';

// regla real (BD) ⇄ win_mode del JS del panel
function reglaToWinMode(string $regla): string { return $regla === 'bajo_gana' ? 'lowest' : 'highest'; }
function winModeToRegla(string $winMode): string { return $winMode === 'lowest' ? 'bajo_gana' : 'alto_gana'; }
// estado real (BD) → status del JS del panel
function estadoToStatus(string $estado): string { return $estado === 'finalizado' ? 'completed' : 'active'; }

try {
    $adminUser = Auth::requireAdmin();
    $db = Database::getInstance()->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];

        $name              = Validator::sanitize(trim($input['name'] ?? ''));
        $description        = Validator::sanitize(trim($input['description'] ?? ''));
        $prize             = Validator::sanitize(trim($input['prize'] ?? ''));
        $totalParticipants = intval($input['total_participants'] ?? 6);
        $winMode           = ($input['win_mode'] ?? 'highest') === 'lowest' ? 'lowest' : 'highest';

        if ($name === '') {
            Response::error('El nombre es requerido');
        }
        if ($totalParticipants < 2 || $totalParticipants > 100) {
            Response::error('La cantidad de participantes debe estar entre 2 y 100');
        }

        // La tabla real no tiene columna "prize"; se conserva el premio dentro
        // de la descripción para no perder el dato que captura el formulario.
        if ($prize !== '') {
            $description = trim($description . "\nPremio: " . $prize);
        }

        $codigoUnico = sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X',
            mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF),
            mt_rand(0, 0x0FFF) | 0x4000, mt_rand(0, 0x3FFF) | 0x8000,
            mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF));

        // Se crea SOLO el tapazo. Igual que el flujo público (unirse.php), las
        // filas de tapazo_jugadores aparecen cuando alguien se une — no se
        // pre-crean slots vacíos (chocarían con el UNIQUE (tapazo_id, nombre)).
        $stmt = $db->prepare("
            INSERT INTO tapazos (titulo, descripcion, cantidad_jugadores, regla, fecha_hora_destape, estado, codigo_unico, created_by, created_at)
            VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY), 'creado', ?, ?, NOW())
        ");
        $stmt->execute([$name, $description, $totalParticipants, winModeToRegla($winMode), $codigoUnico, $adminUser['id']]);
        $tapazoId = (int)$db->lastInsertId();

        Logger::activity('tapazo_created', $adminUser['id'], ['tapazo_id' => $tapazoId, 'name' => $name]);
        Response::success(['id' => $tapazoId, 'codigo' => $codigoUnico, 'message' => 'Tapazo creado exitosamente']);
    }

    // GET — listar. Solo super_admin ve todos; el vendor ve los suyos.
    if (($adminUser['role'] ?? '') === 'super_admin') {
        $stmt = $db->prepare("
            SELECT t.*, v.business_name AS creator_name,
                   (SELECT COUNT(*) FROM tapazo_jugadores j WHERE j.tapazo_id = t.id AND j.nombre <> '') AS joined_count
            FROM tapazos t
            LEFT JOIN vendors v ON t.created_by = v.id
            ORDER BY t.created_at DESC
        ");
        $stmt->execute();
    } else {
        $stmt = $db->prepare("
            SELECT t.*, NULL AS creator_name,
                   (SELECT COUNT(*) FROM tapazo_jugadores j WHERE j.tapazo_id = t.id AND j.nombre <> '') AS joined_count
            FROM tapazos t
            WHERE t.created_by = ?
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([$adminUser['id']]);
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Normalizar al contrato que espera el JS del panel
    $tapazos = array_map(function ($t) {
        return [
            'id'                 => (int)$t['id'],
            // codigo_unico enlaza a la pantalla pública original (/tapazo/index.php?codigo=…),
            // que es la experiencia de juego canónica.
            'codigo'             => $t['codigo_unico'],
            'name'               => $t['titulo'],
            'prize'              => '',
            'total_participants' => (int)$t['cantidad_jugadores'],
            'joined_count'       => (int)$t['joined_count'],
            'win_mode'           => reglaToWinMode($t['regla']),
            'status'             => estadoToStatus($t['estado']),
            'created_at'         => $t['created_at'],
            'creator_name'       => $t['creator_name'] ?? null,
        ];
    }, $rows);

    Response::success($tapazos);

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al procesar tapazos');
}
