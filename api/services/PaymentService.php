<?php

require_once __DIR__ . '/../repositories/PaymentRepository.php';
require_once __DIR__ . '/../repositories/TicketRepository.php';
require_once __DIR__ . '/../repositories/NotificationRepository.php';

/**
 * PaymentService
 *
 * Orquesta el flujo completo de pagos:
 * - Integración con Nequi
 * - Validación de webhooks
 * - Confirmación de pagos
 * - Actualización de tickets
 * - Envío de notificaciones
 */
class PaymentService
{
    private $paymentRepo;
    private $ticketRepo;
    private $notificationRepo;
    private $nequiApiKey;
    private $nequiApiUrl;

    public function __construct()
    {
        $this->paymentRepo = new PaymentRepository();
        $this->ticketRepo = new TicketRepository();
        $this->notificationRepo = new NotificationRepository();

        $this->nequiApiKey = $_ENV['NEQUI_API_KEY'] ?? '';
        $this->nequiApiUrl = $_ENV['NEQUI_API_URL'] ?? 'https://api.nequi.com.co';
    }

    /**
     * Iniciar pago con Nequi
     * Genera QR o link de pago
     */
    public function initiateNequiPayment(array $ticketData): array
    {
        try {
            // Generar referencia única
            $reference = $this->paymentRepo->generatePaymentReference($ticketData['ticket_id']);

            // Crear registro de pago
            $payment = $this->paymentRepo->createPayment([
                'ticket_id' => $ticketData['ticket_id'],
                'user_id' => $ticketData['user_id'],
                'amount' => $ticketData['amount'],
                'payment_method' => PAYMENT_METHOD_NEQUI,
                'reference' => $reference
            ]);

            if (!$payment) {
                throw new Exception('No se pudo crear el registro de pago');
            }

            // Llamar API de Nequi para generar pago
            $nequiResponse = $this->createNequiPaymentRequest([
                'amount' => $ticketData['amount'],
                'reference' => $reference,
                'description' => "Boleto #{$ticketData['ticket_number']} - {$ticketData['raffle_title']}",
                'phone' => $ticketData['user_phone']
            ]);

            return [
                'success' => true,
                'payment_id' => $payment['id'],
                'reference' => $reference,
                'nequi_data' => $nequiResponse,
                'instructions' => $this->getPaymentInstructions($reference, $ticketData['amount'])
            ];

        } catch (Exception $e) {
            Logger::error('Error iniciando pago Nequi', [
                'ticket_id' => $ticketData['ticket_id'],
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Crear solicitud de pago en Nequi
     * Esta es una implementación de ejemplo - adaptar a la API real de Nequi
     */
    private function createNequiPaymentRequest(array $data): array
    {
        $endpoint = "{$this->nequiApiUrl}/payments/v1/create";

        $payload = [
            'RequestMessage' => [
                'RequestHeader' => [
                    'Channel' => 'MIS_RIFAS',
                    'RequestDate' => date('c'),
                    'MessageID' => uniqid(),
                    'ClientID' => $_ENV['NEQUI_CLIENT_ID'] ?? '',
                    'Destination' => [
                        'ServiceName' => 'PaymentService',
                        'ServiceOperation' => 'createPayment'
                    ]
                ],
                'RequestBody' => [
                    'any' => [
                        'createPaymentRQ' => [
                            'phoneNumber' => $data['phone'],
                            'value' => strval($data['amount']),
                            'reference1' => $data['reference'],
                            'description' => $data['description']
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->makeNequiRequest($endpoint, $payload);

        return [
            'transaction_id' => $response['transactionId'] ?? null,
            'qr_code' => $response['qrCode'] ?? null,
            'deep_link' => $response['deepLink'] ?? null,
            'status' => $response['status'] ?? 'pending'
        ];
    }

    /**
     * Realizar petición HTTP a Nequi
     */
    private function makeNequiRequest(string $endpoint, array $payload): array
    {
        $ch = curl_init($endpoint);

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->nequiApiKey,
            'Accept: application/json'
        ];

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Error en petición Nequi: {$error}");
        }

        if ($httpCode !== 200) {
            throw new Exception("Nequi respondió con código {$httpCode}: {$response}");
        }

        return json_decode($response, true) ?? [];
    }

    /**
     * Procesar webhook de Nequi
     * Validar firma y confirmar pago
     */
    public function processNequiWebhook(array $webhookData, string $signature): array
    {
        try {
            // Validar firma del webhook
            if (!$this->validateNequiSignature($webhookData, $signature)) {
                throw new Exception('Firma de webhook inválida');
            }

            // Extraer datos del webhook
            $reference = $webhookData['reference'] ?? null;
            $transactionId = $webhookData['transactionId'] ?? null;
            $status = $webhookData['status'] ?? 'unknown';
            $amount = floatval($webhookData['amount'] ?? 0);

            if (!$reference) {
                throw new Exception('Referencia no encontrada en webhook');
            }

            // Buscar pago por referencia
            $payment = $this->paymentRepo->findByReference($reference);

            if (!$payment) {
                throw new Exception("Pago no encontrado con referencia: {$reference}");
            }

            // Verificar si ya está confirmado
            if ($payment['status'] === PAYMENT_STATUS_CONFIRMED) {
                return [
                    'success' => true,
                    'message' => 'Pago ya confirmado previamente',
                    'payment_id' => $payment['id']
                ];
            }

            // Validar monto
            if (abs($payment['amount'] - $amount) > 0.01) {
                throw new Exception("Monto no coincide. Esperado: {$payment['amount']}, Recibido: {$amount}");
            }

            // Confirmar o fallar según estado
            if ($status === 'APPROVED' || $status === 'SUCCESS') {
                return $this->confirmPaymentFromWebhook($payment['id'], $webhookData);
            } else {
                $this->paymentRepo->markAsFailed($payment['id'], "Estado Nequi: {$status}");
                return [
                    'success' => false,
                    'message' => 'Pago rechazado',
                    'status' => $status
                ];
            }

        } catch (Exception $e) {
            Logger::error('Error procesando webhook Nequi', [
                'webhook_data' => $webhookData,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Confirmar pago desde webhook y enviar notificación
     */
    private function confirmPaymentFromWebhook(int $paymentId, array $webhookData): array
    {
        try {
            // Confirmar pago (actualiza payment y ticket)
            $confirmed = $this->paymentRepo->confirmPayment($paymentId, $webhookData);

            if (!$confirmed) {
                throw new Exception('No se pudo confirmar el pago');
            }

            // Obtener datos completos del pago
            $payment = $this->paymentRepo->findById($paymentId);
            $ticket = $this->ticketRepo->findById($payment['ticket_id']);

            // Obtener datos de la rifa
            $raffleSql = "SELECT r.*, l.name as lottery_name
                         FROM raffles r
                         INNER JOIN tickets t ON r.id = t.raffle_id
                         LEFT JOIN lotteries l ON r.lottery_id = l.id
                         WHERE t.id = ?";
            $stmt = $this->paymentRepo->db->prepare($raffleSql);
            $stmt->execute([$ticket['id']]);
            $raffle = $stmt->fetch();

            // Obtener datos del usuario
            $userSql = "SELECT * FROM users WHERE id = ?";
            $userStmt = $this->paymentRepo->db->prepare($userSql);
            $userStmt->execute([$payment['user_id']]);
            $user = $userStmt->fetch();

            // Enviar notificación de confirmación
            $this->notificationRepo->notifyPurchaseConfirmed($payment['user_id'], [
                'ticket_number' => $ticket['ticket_number'],
                'raffle_title' => $raffle['title'],
                'price' => $payment['amount'],
                'draw_date' => $raffle['draw_date'],
                'lottery_name' => $raffle['lottery_name'] ?? 'N/A',
                'user_phone' => $user['phone'],
                'user_email' => $user['email'] ?? null
            ]);

            return [
                'success' => true,
                'message' => 'Pago confirmado exitosamente',
                'payment_id' => $paymentId,
                'ticket_id' => $ticket['id'],
                'ticket_number' => $ticket['ticket_number']
            ];

        } catch (Exception $e) {
            Logger::error('Error confirmando pago desde webhook', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Validar firma HMAC del webhook de Nequi
     */
    private function validateNequiSignature(array $webhookData, string $receivedSignature): bool
    {
        $secret = $_ENV['NEQUI_WEBHOOK_SECRET'] ?? '';

        if (empty($secret)) {
            Logger::error('NEQUI_WEBHOOK_SECRET no configurado');
            return false;
        }

        // Crear payload ordenado para firma
        ksort($webhookData);
        $payload = json_encode($webhookData);

        // Calcular firma HMAC
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        // Comparación segura
        return hash_equals($expectedSignature, $receivedSignature);
    }

    /**
     * Confirmar pago manualmente (admin)
     */
    public function confirmPaymentManually(int $paymentId, int $adminId, string $notes = ''): bool
    {
        try {
            $confirmed = $this->paymentRepo->confirmPayment($paymentId, [
                'manual_confirmation' => true,
                'confirmed_by' => $adminId,
                'notes' => $notes
            ]);

            if ($confirmed) {
                Logger::activity('payment_confirmed_manually', $adminId, [
                    'payment_id' => $paymentId,
                    'notes' => $notes
                ]);
            }

            return $confirmed;

        } catch (Exception $e) {
            Logger::error('Error en confirmación manual', [
                'payment_id' => $paymentId,
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Obtener instrucciones de pago
     */
    private function getPaymentInstructions(string $reference, float $amount): array
    {
        return [
            'method' => 'Nequi',
            'steps' => [
                '1. Abre tu app de Nequi',
                '2. Ve a "Enviar dinero"',
                '3. Ingresa el monto: $' . number_format($amount, 0),
                '4. Usa la referencia: ' . $reference,
                '5. Confirma el pago'
            ],
            'alternative' => [
                'method' => 'Transferencia Bancaria',
                'bank' => 'Bancolombia',
                'account_type' => 'Ahorros',
                'account_number' => $_ENV['BANK_ACCOUNT_NUMBER'] ?? 'XXXXXXXXXXXX',
                'account_holder' => $_ENV['BANK_ACCOUNT_HOLDER'] ?? 'MisRifas Colombia',
                'reference' => $reference
            ]
        ];
    }

    /**
     * Consultar estado de pago en Nequi
     */
    public function checkPaymentStatus(string $reference): array
    {
        try {
            $endpoint = "{$this->nequiApiUrl}/payments/v1/status";

            $payload = [
                'reference' => $reference
            ];

            $response = $this->makeNequiRequest($endpoint, $payload);

            return [
                'status' => $response['status'] ?? 'unknown',
                'transaction_id' => $response['transactionId'] ?? null,
                'amount' => $response['amount'] ?? null,
                'date' => $response['date'] ?? null
            ];

        } catch (Exception $e) {
            Logger::error('Error consultando estado en Nequi', [
                'reference' => $reference,
                'error' => $e->getMessage()
            ]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Procesar pago de comisión
     */
    public function processCommissionPayment(int $raffleId, array $paymentData): array
    {
        try {
            // Generar referencia
            $reference = "COMM-{$raffleId}-" . time() . "-" . strtoupper(substr(md5(uniqid()), 0, 6));

            // Crear registro de comisión
            $commission = $this->paymentRepo->createCommissionPayment(
                $raffleId,
                $paymentData['amount'],
                $reference
            );

            return [
                'success' => true,
                'commission_id' => $commission['id'],
                'reference' => $reference,
                'amount' => $paymentData['amount'],
                'instructions' => $this->getPaymentInstructions($reference, $paymentData['amount'])
            ];

        } catch (Exception $e) {
            Logger::error('Error procesando pago de comisión', [
                'raffle_id' => $raffleId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
