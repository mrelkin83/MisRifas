<?php
/**
 * Microservicio SMS para Gammu SMSD
 * POST /sms-service/send-sms
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$number = $input['number'] ?? '';
$message = $input['message'] ?? '';

if (empty($number) || empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'number and message are required']);
    exit;
}

// Ruta al SQLite de la bandeja de salida de Gammu SMSD. Configurable por
// entorno para que funcione igual en Windows/Laragon que en el VPS Linux
// (antes estaba hardcodeada a C:\xampp\... y no existía en producción).
// En Linux, Gammu SMSD suele usar el backend "files" o una BD MySQL; si se
// usa el backend SQLite, apuntar GAMMU_SMSD_DB a ese archivo.
$dbPath = getenv('GAMMU_SMSD_DB');
if (!$dbPath) {
    $dbPath = __DIR__ . '/gammu.db'; // junto a este script por defecto
}

try {
    if (!file_exists($dbPath)) {
        throw new RuntimeException('Bandeja de salida de Gammu no encontrada. Configura GAMMU_SMSD_DB.');
    }
    $sqlite = new PDO('sqlite:' . $dbPath);
    $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $sqlite->prepare("INSERT INTO outbox (DestinationNumber, TextDecoded, Coding, CreatorID, SendingDateTime) VALUES (?, ?, 'Default_No_Compression', 'MisRifas_SMS', datetime('now'))");
    $stmt->execute([$number, $message]);

    echo json_encode(['success' => true, 'id' => $sqlite->lastInsertId()]);
} catch (Exception $e) {
    error_log('SMS send error: ' . $e->getMessage());
    http_response_code(500);
    // No filtrar el detalle interno (rutas, driver) en la respuesta.
    echo json_encode(['success' => false, 'error' => 'No se pudo encolar el SMS']);
}
