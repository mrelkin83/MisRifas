<?php

declare(strict_types=1);

/**
 * API: Editor de plantillas de mensajes (v4.13)
 * GET  /api/admin/templates.php            → lista (default + override + vars)
 * POST /api/admin/templates.php { key, body_text }        → guardar override
 * POST /api/admin/templates.php { key, restore: true }    → volver al original
 *
 * SOLO super_admin. Los defaults viven en MessageBuilderService::PLANTILLAS;
 * la BD guarda únicamente lo personalizado.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Auth.php';
require_once __DIR__ . '/../../api/utils/Logger.php';
require_once __DIR__ . '/../../api/services/MessageBuilderService.php';

try {
    $admin = Auth::requireAdmin();
    if (($admin['role'] ?? '') !== 'super_admin') {
        Response::error('Solo el super administrador edita las plantillas', null, 403);
    }
    $db = Database::getInstance()->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $overrides = [];
        foreach ($db->query('SELECT template_key, body_text, updated_at FROM message_templates') as $r) {
            $overrides[$r['template_key']] = $r;
        }
        $out = [];
        foreach (MessageBuilderService::PLANTILLAS as $key => $def) {
            $out[] = [
                'key' => $key,
                'nombre' => $def['nombre'],
                'descripcion' => $def['descripcion'],
                'vars' => $def['vars'],
                'default_text' => $def['default'],
                'custom_text' => $overrides[$key]['body_text'] ?? null,
                'updated_at' => $overrides[$key]['updated_at'] ?? null,
            ];
        }
        Response::success($out);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Método no permitido', null, 405);
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $key = (string)($input['key'] ?? '');
    if (!isset(MessageBuilderService::PLANTILLAS[$key])) {
        Response::error('Plantilla desconocida', null, 422);
    }

    if (!empty($input['restore'])) {
        $db->prepare('DELETE FROM message_templates WHERE template_key = ?')->execute([$key]);
        Logger::activity('template_restored', (int)$admin['id'], ['key' => $key]);
        MessageBuilderService::recargarPlantillas();
        Response::success(['key' => $key], 'Plantilla restaurada al texto original.');
    }

    $body = trim((string)($input['body_text'] ?? ''));
    if ($body === '' || mb_strlen($body) > 2000) {
        Response::error('El texto es obligatorio y máximo 2000 caracteres', null, 422);
    }

    // Aviso (no bloqueo) si se pierden variables — las CRÍTICAS las repone el
    // sistema al enviar (guardas en MessageBuilderService::plantilla()).
    $faltantes = [];
    foreach (MessageBuilderService::PLANTILLAS[$key]['vars'] as $v) {
        if ($v === 'platform') {
            continue; // opcional: el nombre de la plataforma se inyecta solo si se usa
        }
        if ($v === 'boleta_url') {
            continue; // crítica con GUARDA: el sistema la repone al enviar si falta
        }
        if (strpos($body, '{' . $v . '}') === false) {
            $faltantes[] = '{' . $v . '}';
        }
    }

    $db->prepare('
        INSERT INTO message_templates (template_key, body_text) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE body_text = VALUES(body_text), updated_at = NOW()
    ')->execute([$key, $body]);
    Logger::activity('template_saved', (int)$admin['id'], ['key' => $key]);
    MessageBuilderService::recargarPlantillas();

    Response::success(
        ['key' => $key, 'variables_sin_usar' => $faltantes],
        'Plantilla guardada.' . ($faltantes ? ' Ojo: no usaste ' . implode(', ', $faltantes) . '.' : '')
    );
} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error en el editor de plantillas');
}
