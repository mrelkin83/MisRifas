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

if (!function_exists('notificarImagenVendor')) {
    /**
     * Manda una IMAGEN (base64) con caption a `$telefono` por la instancia
     * Evolution del `$vendorId`. Best-effort: false si no hay canal o falla.
     * NUNCA llamarla dentro de una transacción abierta.
     */
    function notificarImagenVendor(int $vendorId, string $telefono, string $imagenBase64, string $caption = ''): bool
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
            'config' => new \ElkinLinan\WhatsappAiEngine\Defecto\ConfigDeEntorno(),
        ]);
        $canal = \ElkinLinan\WhatsappAiEngine\Channel\EvolutionClient::desdeConfig(\ElkinLinan\WhatsappAiEngine\Engine::db());
        if (!$canal) {
            return false;
        }
        try {
            $r = $canal->enviarImagen($telefono, $imagenBase64, $caption);
            return !empty($r['ok']);
        } catch (\Throwable $e) {
            error_log('[WhatsApp][notifyImg] vendor=' . $vendorId . ' ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('notificarWhatsAppVendor')) {
    /**
     * Versión con DETALLE: además del ok dice POR QUÉ falló y si tiene
     * sentido reintentar. Un vendedor sin WhatsApp vinculado no es un fallo
     * transitorio: reintentarlo cada 10 minutos jamás lo va a arreglar.
     * @return array{ok:bool, error:string, reintentable:bool}
     */
    function notificarWhatsAppVendorDetalle(int $vendorId, string $telefono, string $mensaje): array
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
            return ['ok' => false, 'reintentable' => false,
                    'error' => 'Organizador sin WhatsApp vinculado (el aviso salió por correo)'];
        }

        try {
            $resultado = $canal->enviarTexto($telefono, $mensaje);
            if (!empty($resultado['ok'])) {
                return ['ok' => true, 'error' => '', 'reintentable' => false];
            }
            return ['ok' => false, 'reintentable' => true,
                    'error' => 'Evolution rechazó el envío: ' . (string)($resultado['error'] ?? 'sin detalle')];
        } catch (\Throwable $e) {
            error_log('[WhatsApp][notify] vendor=' . $vendorId . ' ' . $e->getMessage());
            return ['ok' => false, 'reintentable' => true, 'error' => $e->getMessage()];
        }
    }

    /** Compatibilidad: los llamadores existentes solo necesitan el bool. */
    function notificarWhatsAppVendor(int $vendorId, string $telefono, string $mensaje): bool
    {
        return notificarWhatsAppVendorDetalle($vendorId, $telefono, $mensaje)['ok'];
    }
}
