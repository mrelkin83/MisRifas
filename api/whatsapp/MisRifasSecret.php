<?php
/**
 * Adaptador de whatsapp-engine\Ports\SecretPort con cifrado real
 * (AES-256-GCM). El paquete trae `SecretoEnClaro` (passthrough, sin
 * cifrar) como default de bootstrap - su propio docblock lo marca como no
 * apto para producción, así que MisRifas nunca lo usa. La clave sale de
 * APP_SECRET_KEY (config/app.php / .env), derivada a 32 bytes exactos via
 * SHA-256 para que sirva sin importar la longitud del secreto original.
 */

class MisRifasSecret implements \ElkinLinan\WhatsappAiEngine\Ports\SecretPort
{
    private const CIFRADO = 'aes-256-gcm';
    private const IV_LEN = 12;
    private const TAG_LEN = 16;

    private string $key;

    public function __construct(?string $appSecretKey = null)
    {
        $secret = $appSecretKey ?? (getenv('APP_SECRET_KEY') ?: '');
        if ($secret === '') {
            throw new RuntimeException('APP_SECRET_KEY no esta configurado - no se pueden cifrar credenciales de WhatsApp.');
        }
        $this->key = hash('sha256', $secret, true);
    }

    public function cifrar(string $claro): string
    {
        if ($claro === '') {
            return '';
        }
        $iv = random_bytes(self::IV_LEN);
        $tag = '';
        $cifrado = openssl_encrypt($claro, self::CIFRADO, $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cifrado === false) {
            throw new RuntimeException('No se pudo cifrar el secreto.');
        }
        return base64_encode($iv . $tag . $cifrado);
    }

    public function descifrar(string $cifrado): string
    {
        if ($cifrado === '') {
            return '';
        }
        $raw = base64_decode($cifrado, true);
        if ($raw === false || strlen($raw) <= self::IV_LEN + self::TAG_LEN) {
            return '';
        }
        $iv = substr($raw, 0, self::IV_LEN);
        $tag = substr($raw, self::IV_LEN, self::TAG_LEN);
        $texto = substr($raw, self::IV_LEN + self::TAG_LEN);
        $claro = openssl_decrypt($texto, self::CIFRADO, $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        return $claro === false ? '' : $claro;
    }
}
