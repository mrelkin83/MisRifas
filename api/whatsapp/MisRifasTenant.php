<?php
/**
 * Adaptador de whatsapp-engine\Ports\TenantPort para el modo "una base de
 * datos, una columna": MisRifas guarda a todos los vendors en la misma BD
 * `misrifas`, separados por `vendor_id`. Ver Core\Scope del motor - el
 * scopeFila() que devolvemos aqui es lo que activa el filtro automatico en
 * las tablas propias del motor (wa_config, wa_agentes, wa_conversaciones).
 */

class MisRifasTenant implements \ElkinLinan\WhatsappAiEngine\Ports\TenantPort
{
    private int $vendorId;
    private string $businessName;

    public function __construct(int $vendorId, string $businessName = '')
    {
        $this->vendorId = $vendorId;
        $this->businessName = $businessName;
    }

    public function id(): ?int
    {
        return $this->vendorId;
    }

    public function nombre(): string
    {
        return $this->businessName ?: 'el negocio';
    }

    public function baseDatos(): ?string
    {
        // Una sola base de datos compartida - no hay una base por tenant.
        return null;
    }

    public function esMultiNegocio(): bool
    {
        return true;
    }

    public function scopeFila(): ?array
    {
        return ['columna' => 'vendor_id', 'valor' => $this->vendorId];
    }
}
