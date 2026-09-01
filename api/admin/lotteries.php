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
        $upd = $db->prepare("UPDATE lotteries SET day_of_week = ?, draw_time = ?, active = ? WHERE id = ?");
        foreach ($loterias as $l) {
            $id = (int)($l['id'] ?? 0);
            $dia = (string)($l['day_of_week'] ?? '');
            $hora = (string)($l['draw_time'] ?? '');
            if (!$id) {
                Response::error('Lotería sin id', null, 422);
            }
            if ($err = validarDiaHora($dia, $hora)) {
                Response::error($err, null, 422);
            }
            $upd->execute([$dia, strlen($hora) === 5 ? $hora . ':00' : $hora, !empty($l['active']) ? 1 : 0, $id]);
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

    Response::error('Acción no válida', null, 422);
} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al guardar el calendario de loterías');
}
