<?php
/**
 * Servicio de WhatsApp (Proveedor: EvolutionAPI)
 * 
 * Envía mensajes de texto de forma programática.
 * Requiere que el vendedor tenga en su base de datos (wa_config) 
 * la URL, la KEY de EvolutionAPI y el nombre de su instancia.
 */

class WhatsAppService {

    /**
     * Extrae la configuración global (fallback) y la de la base de datos (vendedor).
     */
    private static function getConfig($sellerId) {
        $db = Database::getInstance()->getConnection();
        
        // 1. Configuraciones Globales (Templates, flags)
        $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'wa_%'");
        $global = [];
        foreach ($stmt->fetchAll() as $row) {
            $global[$row['setting_key']] = $row['setting_value'];
        }

        // 2. Credenciales EvolutionAPI del Vendedor (tabla vendors - fuente
        // unica de identidad/config, no admin_users que es legacy y no
        // comparte secuencia de IDs con vendors)
        $stmt = $db->prepare("SELECT wa_config FROM vendors WHERE id = ?");
        $stmt->execute([$sellerId]);
        $waConfigRaw = $stmt->fetchColumn();
        $seller = json_decode($waConfigRaw ?: '{}', true);

        return [
            'global' => $global,
            'seller' => $seller
        ];
    }

    /**
     * Función general para enviar mensaje vía Evolution API
     * POST /message/sendText/{instance}
     */
    private static function sendEvolutionMessage($instance, $apiUrl, $apiKey, $phoneNumber, $message) {
        // Limpiamos el número (asegurar que tenga el código de país y no el +, y soporte jid)
        $phoneNumber = preg_replace("/[^0-9]/", "", $phoneNumber);
        
        $url = rtrim($apiUrl, '/') . "/message/sendText/" . urlencode($instance);
        
        $payload = [
            "number" => $phoneNumber,
            "options" => [
                "delay" => 1200,
                "presence" => "composing"
            ],
            "textMessage" => [
                "text" => $message
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpcode >= 200 && $httpcode < 300;
    }

    /**
     * Notifica al comprador que su boleto fue reservado.
     */
    public static function notifyReservation($sellerId, $raffleName, $ticketNumber, $buyerName, $buyerPhone, $drawDate, $price, $ticketUrl) {
        $config = self::getConfig($sellerId);
        
        if (($config['global']['wa_notify_buyer'] ?? '1') !== '1') return false; // Apagado
        if (empty($config['seller']['evo_instance']) || empty($config['seller']['evo_api_key']) || empty($config['seller']['evo_api_url'])) {
            return false; // El vendedor no ha configurado su EvolutionAPI
        }

        $template = $config['global']['wa_msg_buyer'] ?? "Hola {nombre}, boleto #{numero} reservado. Precio: {precio}. Link: {link}";
        
        // Reemplazar variables
        $message = str_replace(
            ['{nombre}', '{rifa}', '{numero}', '{fecha}', '{precio}', '{link}'],
            [$buyerName, $raffleName, $ticketNumber, $drawDate, number_format($price, 0, ',', '.') . ' COP', $ticketUrl],
            $template
        );

        return self::sendEvolutionMessage(
            $config['seller']['evo_instance'],
            $config['seller']['evo_api_url'],
            $config['seller']['evo_api_key'],
            $buyerPhone,
            $message
        );
    }

    /**
     * Notifica al vendedor que recibió un pago (si tiene configurado su numero principal)
     */
    public static function notifySellerPayment($sellerId, $raffleName, $ticketNumber, $buyerName, $price, $soldCount, $totalTickets) {
        $config = self::getConfig($sellerId);
        
        if (($config['global']['wa_notify_seller'] ?? '1') !== '1') return false; // Apagado
        if (empty($config['seller']['evo_instance']) || empty($config['seller']['evo_api_key']) || empty($config['global']['wa_notify_number'])) {
            return false; 
        }

        $template = $config['global']['wa_msg_seller'] ?? "✅ Pago recibido de {nombre} por boleto #{numero}.";
        
        $message = str_replace(
            ['{nombre}', '{rifa}', '{numero}', '{precio}', '{vendidos}', '{total}'],
            [$buyerName, $raffleName, $ticketNumber, number_format($price, 0, ',', '.') . ' COP', $soldCount, $totalTickets],
            $template
        );

        return self::sendEvolutionMessage(
            $config['seller']['evo_instance'],
            $config['seller']['evo_api_url'],
            $config['seller']['evo_api_key'],
            $config['global']['wa_notify_number'],
            $message
        );
    }

    /**
     * Notifica al ganador
     */
    public static function notifyWinner($sellerId, $raffleName, $ticketNumber, $winnerName, $winnerPhone, $drawDate) {
        $config = self::getConfig($sellerId);
        
        if (($config['global']['wa_notify_winner'] ?? '1') !== '1') return false; // Apagado
        if (empty($config['seller']['evo_instance'])) return false; 

        $template = $config['global']['wa_msg_winner'] ?? "🎉 Eres ganador, {nombre}! Boleto #{numero}.";
        
        $message = str_replace(
            ['{nombre}', '{rifa}', '{numero}', '{fecha}'],
            [$winnerName, $raffleName, $ticketNumber, $drawDate],
            $template
        );

        return self::sendEvolutionMessage(
            $config['seller']['evo_instance'],
            $config['seller']['evo_api_url'],
            $config['seller']['evo_api_key'],
            $winnerPhone,
            $message
        );
    }

}
