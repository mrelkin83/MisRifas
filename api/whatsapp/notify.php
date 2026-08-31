<?php
/**
 * Envio de WhatsApp proactivo (fuera del loop conversacional): recordatorios,
 * confirmaciones de pago, alertas al vendor. Reemplaza los 3 puntos que
 * antes hacian esto cada uno a su manera (WhatsAppService.php,
 * NotificationService.php, cron/process_notifications.php con su propio
 * cURL) - ahora todos pasan por aqui, que solo arma el motor y llama
 * EvolutionClient::enviarTexto().
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/MisRifasDb.php';
require_once __DIR__ . '/MisRifasTenant.php';
require_once __DIR__ . '/MisRifasSecret.php';
require_once __DIR__ . '/MisRifasStorage.php';
require_once __DIR__ . '/RaffleDomainAdapter.php';

if (!function_exists('notificarWhatsAppVendor')) {
    /**
     * Manda `$mensaje` a `$telefono` usando la instancia Evolution
     * configurada por `$vendorId` en wa_config. Devuelve false si el vendor
     * no tiene canal configurado o si el envio falla - nunca lanza.
     */
    function notificarWhatsAppVendor(int $vendorId, string $telefono, string $mensaje): bool
    {
        \ElkinLinan\WhatsappAiEngine\Engine::reiniciar();
        \ElkinLinan\WhatsappAiEngine\Engine::arrancar([
            'db' => new MisRifasDb(),
            'dominio' => new RaffleDomainAdapter($vendorId),
            'archivo' => new MisRifasStorage($vendorId),
            'secreto' => new MisRifasSecret(),
            'negocio' => new MisRifasTenant($vendorId),
            'formato' => new \ElkinLinan\WhatsappAiEngine\Defecto\PesosColombianos(),
            'funcion' => new \ElkinLinan\WhatsappAiEngine\Defecto\TodoPermitido(),
            // ConfigDeEntorno: usa las credenciales Evolution gestionadas de la
            // plataforma (WA_EVOLUTION_URL/APIKEY del .env) cuando el vendedor
            // no configuró servidor propio.
            'config' => new \ElkinLinan\WhatsappAiEngine\Defecto\ConfigDeEntorno(),
        ]);

        $canal = \ElkinLinan\WhatsappAiEngine\Channel\EvolutionClient::desdeConfig(\ElkinLinan\WhatsappAiEngine\Engine::db());
        if (!$canal) {
            return false;
        }

        try {
            $resultado = $canal->enviarTexto($telefono, $mensaje);
            return !empty($resultado['ok']);
        } catch (\Throwable $e) {
            error_log('[WhatsApp][notify] vendor=' . $vendorId . ' ' . $e->getMessage());
            return false;
        }
    }
}
