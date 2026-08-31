<?php

declare(strict_types=1);

/**
 * Llaves de cobro del vendedor (promt2.md §5.1).
 *
 * Viven en vendors.payment_config (JSON). El comprador solo ve los métodos
 * configurados; el vendedor necesita AL MENOS uno para publicar una rifa.
 * La llave Bre-B se guarda tal como la escribe el vendedor (celular, cédula,
 * correo o alfanumérica — sin validación de formato, §5.1).
 */
final class PaymentKeys
{
    /** Claves heredadas de la era de pasarelas: jamás se exponen. */
    private const LEGACY_SECRETS = ['nequi_key', 'nequi_secret'];

    public static function delVendor(PDO $db, int $vendorId): array
    {
        $stmt = $db->prepare('SELECT payment_config FROM vendors WHERE id = ?');
        $stmt->execute([$vendorId]);
        $cfg = json_decode((string)$stmt->fetchColumn() ?: '{}', true);
        return is_array($cfg) ? $cfg : [];
    }

    /** Config saneada para exponer (panel o comprador): sin secretos legados. */
    public static function sanear(array $cfg): array
    {
        return [
            'mode'            => 'manual',
            'nequi_phone'     => (string)($cfg['nequi_phone'] ?? ''),
            'daviplata_phone' => (string)($cfg['daviplata_phone'] ?? ''),
            'breb_key'        => (string)($cfg['breb_key'] ?? ''),
            'accepts_cash'    => !empty($cfg['accepts_cash']),
        ];
    }

    /**
     * Métodos que el comprador puede usar, con su dato de destino.
     * 'cash' no lleva destino: solo el vendedor lo registra (§5.2).
     */
    public static function metodosDisponibles(array $cfg): array
    {
        $m = [];
        if (!empty($cfg['nequi_phone'])) {
            $m[] = ['method' => 'nequi', 'label' => 'Nequi', 'destination' => (string)$cfg['nequi_phone']];
        }
        if (!empty($cfg['daviplata_phone'])) {
            $m[] = ['method' => 'daviplata', 'label' => 'DaviPlata', 'destination' => (string)$cfg['daviplata_phone']];
        }
        if (!empty($cfg['breb_key'])) {
            $m[] = ['method' => 'breb', 'label' => 'Bre-B', 'destination' => (string)$cfg['breb_key']];
        }
        return $m;
    }

    /** ¿Puede publicar rifas? (≥1 método transferible o efectivo, §5.1) */
    public static function tieneAlguno(array $cfg): bool
    {
        return !empty($cfg['nequi_phone']) || !empty($cfg['daviplata_phone'])
            || !empty($cfg['breb_key']) || !empty($cfg['accepts_cash']);
    }

    /** Guarda las llaves preservando lo demás del JSON (y purgando secretos legados). */
    public static function guardar(PDO $db, int $vendorId, array $input): array
    {
        $cfg = self::delVendor($db, $vendorId);

        if (array_key_exists('nequi_phone', $input)) {
            $cfg['nequi_phone'] = preg_replace('/\D+/', '', (string)$input['nequi_phone']);
        }
        if (array_key_exists('daviplata_phone', $input)) {
            $cfg['daviplata_phone'] = preg_replace('/\D+/', '', (string)$input['daviplata_phone']);
        }
        if (array_key_exists('breb_key', $input)) {
            // Tal como la escribe el vendedor (§5.1) — solo recorte y tope.
            $cfg['breb_key'] = mb_substr(trim((string)$input['breb_key']), 0, 120);
        }
        if (array_key_exists('accepts_cash', $input)) {
            $cfg['accepts_cash'] = (bool)$input['accepts_cash'];
        }
        $cfg['mode'] = 'manual';
        foreach (self::LEGACY_SECRETS as $k) {
            unset($cfg[$k]);
        }

        $db->prepare('UPDATE vendors SET payment_config = ? WHERE id = ?')
           ->execute([json_encode($cfg, JSON_UNESCAPED_UNICODE), $vendorId]);
        return self::sanear($cfg);
    }
}
