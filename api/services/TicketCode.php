<?php

declare(strict_types=1);

/**
 * Código público de la boleta (promt2.md §9.3).
 *
 * 12 caracteres del alfabeto Crockford Base32 (0-9, A-Z sin I, L, O, U),
 * generados con random_bytes — NUNCA secuencial ni derivado del id (un
 * código predecible permite enumerar boletas ajenas). Espacio: 32^12 ≈ 1.15e18.
 */
final class TicketCode
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /** 12 caracteres crudos (sin guiones). */
    public static function generate(): string
    {
        $out = '';
        $bytes = random_bytes(12);
        for ($i = 0; $i < 12; $i++) {
            $out .= self::ALPHABET[ord($bytes[$i]) % 32];
        }
        return $out;
    }

    /** Presentación XXXX-XXXX-XXXX. */
    public static function format(string $code): string
    {
        $c = self::normalize($code);
        return substr($c, 0, 4) . '-' . substr($c, 4, 4) . '-' . substr($c, 8, 4);
    }

    /**
     * Normaliza entrada del usuario: mayúsculas, sin guiones/espacios, y los
     * caracteres excluidos del alfabeto se mapean a su equivalente Crockford
     * (I/L→1, O→0, U→V) para tolerar transcripción a mano.
     */
    public static function normalize(string $input): string
    {
        $c = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $input));
        return strtr($c, ['I' => '1', 'L' => '1', 'O' => '0', 'U' => 'V']);
    }

    public static function isValid(string $code): bool
    {
        $c = self::normalize($code);
        return strlen($c) === 12 && preg_match('/^[' . self::ALPHABET . ']{12}$/', $c) === 1;
    }
}
