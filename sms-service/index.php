<?php
/**
 * Microservicio SMS - Index Router
 */

header('Content-Type: application/json; charset=utf-8');

$path = $_SERVER['REQUEST_URI'] ?? '';

if (preg_match('#/sms-service/send-sms#', $path)) {
    require_once __DIR__ . '/send-sms.php';
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Not found']);
}
