<?php
/**
 * API: Calendario de loterías administrable (solo super_admin).
 *
 * El día y la hora del sorteo de cada lotería vivían sembrados en la BD sin
 * ninguna pantalla: si una lotería cambiaba de horario en el mundo real, solo
 * se podía corregir por SQL. Ahora se administra desde Gestión de Rifas.
 *
 * POST {action:'guardar', loterias:[{id, day_of_week, draw_time, active}]}
 * POST {action:'crear',   name, day_of_week, draw_time}
 *
 * Las rifas YA creadas no se tocan (su draw_date quedó fijado al crearlas);
 * el calendario aplica a rifas nuevas y a las reprogramaciones automáticas.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Logger.php';
require_once __DIR__ . '/../../api/utils/Auth.php';

const DIAS_VALIDOS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

function validarDiaHora(string $dia, string $hora): ?string
{
    if (!in_array($dia, DIAS_VALIDOS, true)) {
        return "Día inválido: $dia";
    }
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $hora)) {
        return "Hora inválida: $hora (usa HH:MM, 24 horas)";
    }
    return null;
}

try {
    $admin = Auth::requireRole('super_admin');
    $db = Database::getInstance()->getConnection();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Método no permitido', null, 405);
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = (string)($input['action'] ?? '');

    if ($action === 'guardar') {
        $loterias = is_array($input['loterias'] ?? null) ? $input['loterias'] : [];
        if (!$loterias) {
            Response::error('Nada que guardar', null, 422);
        }
        $upd = $db->prepare("UPDATE lotteries SET name = ?, day_of_week = ?, draw_time = ?, active = ? WHERE id = ?");
        $dup = $db->prepare("SELECT id FROM lotteries WHERE name = ? AND id <> ?");
        foreach ($loterias as $l) {
            $id = (int)($l['id'] ?? 0);
            $dia = (string)($l['day_of_week'] ?? '');
            $hora = (string)($l['draw_time'] ?? '');
            if (!$id) {
                Response::error('Lotería sin id', null, 422);
            }
            // name es OPCIONAL: ausente = conservar el actual (compatibilidad
            // con llamadores que solo tocan día/hora/activa).
            if (array_key_exists('name', $l)) {
                $nombre = trim((string)$l['name']);
                if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 100) {
                    Response::error("El nombre de la lotería $id debe tener entre 3 y 100 caracteres", null, 422);
                }
                $dup->execute([$nombre, $id]);
                if ($dup->fetchColumn()) {
                    Response::error("Ya existe otra lotería llamada «{$nombre}»", null, 409);
                }
            } else {
                $cur = $db->prepare('SELECT name FROM lotteries WHERE id = ?');
                $cur->execute([$id]);
                $nombre = (string)$cur->fetchColumn();
                if ($nombre === '') {
                    Response::error("Lotería $id no encontrada", null, 404);
                }
            }
            if ($err = validarDiaHora($dia, $hora)) {
                Response::error($err, null, 422);
            }
            // Renombrar es seguro para el historial (las rifas referencian por
            // id), pero cambia el slug automático del scraper: si la fuente
            // deja de resolver, se fija con la "fuente propia" (api_source).
            $upd->execute([$nombre, $dia, strlen($hora) === 5 ? $hora . ':00' : $hora, !empty($l['active']) ? 1 : 0, $id]);
        }
        Logger::activity('lotteries_schedule_updated', (int)$admin['id'], ['count' => count($loterias)]);
        Response::success(['message' => 'Calendario de loterías guardado']);
    }

    if ($action === 'crear') {
        $name = trim((string)($input['name'] ?? ''));
        $dia = (string)($input['day_of_week'] ?? '');
        $hora = (string)($input['draw_time'] ?? '');
        if (mb_strlen($name) < 3 || mb_strlen($name) > 100) {
            Response::error('El nombre debe tener entre 3 y 100 caracteres', null, 422);
        }
        if ($err = validarDiaHora($dia, $hora)) {
            Response::error($err, null, 422);
        }
        $dup = $db->prepare("SELECT id FROM lotteries WHERE name = ?");
        $dup->execute([$name]);
        if ($dup->fetchColumn()) {
            Response::error('Ya existe una lotería con ese nombre', null, 409);
        }
        $ins = $db->prepare("INSERT INTO lotteries (name, day_of_week, draw_time, active) VALUES (?, ?, ?, 1)");
        $ins->execute([$name, $dia, strlen($hora) === 5 ? $hora . ':00' : $hora]);
        Logger::activity('lottery_created', (int)$admin['id'], ['name' => $name]);
        Response::success(['id' => (int)$db->lastInsertId(), 'message' => "Lotería «{$name}» creada"]);
    }

    if ($action === 'eliminar') {
        $id = (int)($input['id'] ?? 0);
        if (!$id) {
            Response::error('Falta el id de la lotería', null, 422);
        }
        // Una lotería con rifas (del estado que sea) NO se elimina: romperia
        // el historial de sorteos y la trazabilidad pública. Se desactiva.
        $n = $db->prepare('SELECT COUNT(*) FROM raffles WHERE lottery_id = ?');
        $n->execute([$id]);
        $enUso = (int)$n->fetchColumn();
        if ($enUso > 0) {
            Response::error("No se puede eliminar: {$enUso} rifa(s) usan esta lotería (su historial la referencia). Desactívala en su lugar.", null, 409);
        }
        $nom = $db->prepare('SELECT name FROM lotteries WHERE id = ?');
        $nom->execute([$id]);
        $nombre = (string)$nom->fetchColumn();
        if ($nombre === '') {
            Response::error('Lotería no encontrada', null, 404);
        }
        $db->prepare('DELETE FROM lotteries WHERE id = ?')->execute([$id]);
        Logger::activity('lottery_deleted', (int)$admin['id'], ['id' => $id, 'name' => $nombre]);
        Response::success(['message' => "Lotería «{$nombre}» eliminada"]);
    }

    Response::error('Acción no válida', null, 422);
} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al guardar el calendario de loterías');
}
