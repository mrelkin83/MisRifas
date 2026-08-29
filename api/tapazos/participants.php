<?php
/**
 * API: Tapazos — participantes y destape (panel admin/vendor)
 * GET  /api/tapazos/participants.php?tapazo_id=X  → listar participantes
 * POST /api/tapazos/participants.php              → join | reveal | complete
 *
 * Reescrito contra el esquema real (tabla tapazo_jugadores: nombre,
 * numero_tapa, orden_destape). El original usaba una tabla/columnas
 * inexistentes (tapazo_participants.cap_number/status) y fallaba siempre.
 * Se mantiene el contrato de campos que espera el JS del panel:
 *   cap_number, participant_name, status (pending|confirmed|revealed).
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

/** Un jugador (fila de tapazo_jugadores) → contrato del JS del panel.
 *  cap_number = cerveza_numero (la cerveza/slot visible que eligió el jugador).
 *  Una fila solo existe si el jugador ya se unió, así que nunca es 'pending'. */
function jugadorToContract(array $j): array {
    return [
        'id'               => (int)$j['id'],
        'cap_number'       => (int)$j['cerveza_numero'],
        'participant_name' => $j['nombre'] ?: null,
        'status'           => $j['orden_destape'] !== null ? 'revealed' : 'confirmed',
    ];
}

/** Verifica que el tapazo pertenezca al usuario (o sea super_admin). */
function assertOwnsTapazo(PDO $db, array $user, int $tapazoId): void {
    if (($user['role'] ?? '') === 'super_admin') return;
    $stmt = $db->prepare("SELECT 1 FROM tapazos WHERE id = ? AND created_by = ?");
    $stmt->execute([$tapazoId, $user['id']]);
    if (!$stmt->fetch()) {
        Response::error('No tienes permisos sobre este tapazo', null, 403);
    }
}

try {
    $adminUser = Auth::requireAdmin();
    $db = Database::getInstance()->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input  = json_decode(file_get_contents('php://input'), true) ?: [];
        $action = $input['action'] ?? '';
        $tapazoId = intval($input['tapazo_id'] ?? 0);
        if (!$tapazoId) {
            Response::error('tapazo_id requerido');
        }
        assertOwnsTapazo($db, $adminUser, $tapazoId);

        if ($action === 'join') {
            $name = Validator::sanitize(trim($input['participant_name'] ?? ''));
            if ($name === '') {
                Response::error('El nombre del participante es requerido');
            }

            // Insertar un jugador nuevo tomando la siguiente cerveza libre, bajo
            // transacción para que dos "join" simultáneos no colisionen en el
            // UNIQUE (tapazo_id, cerveza_numero).
            $db->beginTransaction();
            try {
                $cap = $db->prepare("SELECT cantidad_jugadores, estado FROM tapazos WHERE id = ? FOR UPDATE");
                $cap->execute([$tapazoId]);
                $tp = $cap->fetch(PDO::FETCH_ASSOC);

                $used = $db->prepare("SELECT cerveza_numero FROM tapazo_jugadores WHERE tapazo_id = ?");
                $used->execute([$tapazoId]);
                $taken = array_map('intval', $used->fetchAll(PDO::FETCH_COLUMN));

                if (count($taken) >= (int)$tp['cantidad_jugadores']) {
                    $db->rollBack();
                    Response::error('No hay cupos disponibles en este tapazo');
                }
                // Primera cerveza libre (1..N)
                $next = 0;
                for ($i = 1; $i <= (int)$tp['cantidad_jugadores']; $i++) {
                    if (!in_array($i, $taken, true)) { $next = $i; break; }
                }

                $ins = $db->prepare("INSERT INTO tapazo_jugadores (tapazo_id, nombre, cerveza_numero, created_at) VALUES (?, ?, ?, NOW())");
                $ins->execute([$tapazoId, $name, $next]);

                // Marcar 'lleno' cuando se completan los cupos
                if (count($taken) + 1 >= (int)$tp['cantidad_jugadores']) {
                    $db->prepare("UPDATE tapazos SET estado = 'lleno' WHERE id = ? AND estado = 'creado'")->execute([$tapazoId]);
                }
                $db->commit();
            } catch (Throwable $e) {
                $db->rollBack();
                if ($e instanceof PDOException && $e->getCode() === '23000') {
                    Response::error('Ese nombre ya está en uso en este tapazo');
                }
                throw $e;
            }

            Response::success(['message' => 'Se unió al tapazo', 'cap_number' => $next]);
        }

        if ($action === 'complete') {
            $stmt = $db->prepare("SELECT regla, descripcion FROM tapazos WHERE id = ?");
            $stmt->execute([$tapazoId]);
            $tapazo = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$tapazo) {
                Response::error('Tapazo no encontrado', null, 404);
            }

            $db->beginTransaction();
            try {
                // Asignar las tapas ocultas (numero_tapa) si aún no se hizo:
                // reparto aleatorio 1..cantidad entre los jugadores unidos.
                $players = $db->prepare("SELECT id FROM tapazo_jugadores WHERE tapazo_id = ? ORDER BY id ASC FOR UPDATE");
                $players->execute([$tapazoId]);
                $ids = array_map('intval', $players->fetchAll(PDO::FETCH_COLUMN));
                if (empty($ids)) {
                    $db->rollBack();
                    Response::error('Nadie se ha unido a este tapazo todavía');
                }

                $missing = $db->prepare("SELECT COUNT(*) FROM tapazo_jugadores WHERE tapazo_id = ? AND numero_tapa IS NULL");
                $missing->execute([$tapazoId]);
                if ((int)$missing->fetchColumn() > 0) {
                    $caps = range(1, count($ids));
                    shuffle($caps);
                    $setCap = $db->prepare("UPDATE tapazo_jugadores SET numero_tapa = ? WHERE id = ?");
                    foreach ($ids as $k => $jid) {
                        $setCap->execute([$caps[$k], $jid]);
                    }
                }

                $order = $tapazo['regla'] === 'bajo_gana' ? 'ASC' : 'DESC';
                $stmt = $db->prepare("
                    SELECT nombre AS participant_name, numero_tapa AS cap_number
                    FROM tapazo_jugadores
                    WHERE tapazo_id = ? AND nombre <> ''
                    ORDER BY numero_tapa {$order} LIMIT 1
                ");
                $stmt->execute([$tapazoId]);
                $winner = $stmt->fetch(PDO::FETCH_ASSOC);

                $db->prepare("UPDATE tapazos SET estado = 'finalizado' WHERE id = ?")->execute([$tapazoId]);
                $db->commit();
            } catch (Throwable $e) {
                $db->rollBack();
                throw $e;
            }

            if ($winner) {
                $winner['cap_number'] = (int)$winner['cap_number'];
                $prize = '';
                if (preg_match('/Premio:\s*(.+)$/m', (string)$tapazo['descripcion'], $m)) {
                    $prize = trim($m[1]);
                }
                $winner['prize'] = $prize;
            }

            Logger::activity('tapazo_completed', $adminUser['id'], ['tapazo_id' => $tapazoId]);
            Response::success(['message' => 'Tapazo completado', 'winner' => $winner ?: null]);
        }

        Response::error('Acción inválida');
    }

    // GET — participantes de un tapazo
    $tapazoId = intval($_GET['tapazo_id'] ?? 0);
    if (!$tapazoId) {
        Response::error('tapazo_id requerido');
    }
    assertOwnsTapazo($db, $adminUser, $tapazoId);

    $stmt = $db->prepare("
        SELECT id, nombre, cerveza_numero, numero_tapa, orden_destape
        FROM tapazo_jugadores
        WHERE tapazo_id = ?
        ORDER BY cerveza_numero ASC
    ");
    $stmt->execute([$tapazoId]);
    $participants = array_map('jugadorToContract', $stmt->fetchAll(PDO::FETCH_ASSOC));

    Response::success($participants);

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al procesar participantes');
}
