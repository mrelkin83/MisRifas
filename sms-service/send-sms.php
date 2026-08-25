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

try {
    $dbPath = 'C:\xampp\htdocs\MisRifas\sms-service\gammu.db';
    $sqlite = new PDO('sqlite:' . $dbPath);
    $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $sqlite->prepare("INSERT INTO outbox (DestinationNumber, TextDecoded, Coding, CreatorID, SendingDateTime) VALUES (?, ?, 'Default_No_Compression', 'MisRifas_SMS', datetime('now'))");
    $stmt->execute([$number, $message]);

    echo json_encode(['success' => true, 'id' => $sqlite->lastInsertId()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
