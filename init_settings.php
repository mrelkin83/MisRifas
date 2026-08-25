<?php
/**
 * Script: Inicializar Banners en system_settings
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse por linea de comandos.');
}
require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    $banners = [
        [
            "image" => "https://images.unsplash.com/photo-1540317580384-e5d4361660bd?w=1600",
            "title" => "Gana el carro de tus sueños",
            "subtitle" => "Participa hoy mismo con MisRifas",
            "button_text" => "Ver Rifas",
            "button_link" => "#rifas"
        ],
        [
            "image" => "https://images.unsplash.com/photo-1555529733-0e670560f7e1?w=1600",
            "title" => "Premios increíbles cada semana",
            "subtitle" => "Seguridad y transparencia garantizada",
            "button_text" => "Ver Ganadores",
            "button_link" => "/MisRifas/public/ganadores.php"
        ],
        [
            "image" => "https://images.unsplash.com/photo-1512453979798-5ea266f8880c?w=1600",
            "title" => "Apartamentos y Lotes",
            "subtitle" => "Tu inversión segura en Colombia",
            "button_text" => "Participar",
            "button_link" => "#rifas"
        ],
        [
            "image" => "https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?w=1600",
            "title" => "Jeeps y Camionetas",
            "subtitle" => "Prepárate para la aventura",
            "button_text" => "Comprar Boleto",
            "button_link" => "#rifas"
        ]
    ];

    $bannersJson = json_encode($banners, JSON_UNESCAPED_UNICODE);

    $stmt = $db->prepare("
        INSERT INTO system_settings (setting_key, setting_value, data_type, description, group_name) 
        VALUES ('home_banners', ?, 'json', 'Configuración de los banners de la portada', 'appearance')
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
    ");
    
    $stmt->execute([$bannersJson]);
    
    echo "Configuración de banners inicializada con éxito.\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
