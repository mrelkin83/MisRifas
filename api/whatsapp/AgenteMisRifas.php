<?php

/**
 * Identidad y catálogo del agente EN TÉRMINOS DE RIFAS.
 *
 * El motor (whatsapp-engine) es genérico de comercio —menús, pedidos, cocina,
 * garantías: herencia de ControlBarMax—. Sin esta capa, la pestaña Agente
 * mostraba un rol de restaurante ("mostrar la carta, tomar pedidos") y el
 * bloque "Qué puede hacer" ofrecía el almuerzo del día y órdenes de servicio
 * técnico. Aquí se traduce TODO al dominio de MisRifas: el panel y el modelo
 * hablan de rifas, boletos y sorteos.
 */

require_once __DIR__ . '/../../config/brand.php';

final class AgenteMisRifas
{
    /** Textos del agente acordes a la misión de la plataforma (administrable:
     *  el super_admin puede editarlos luego en la pestaña Agente). */
    public static function porDefecto(): array
    {
        $marca = plataforma('nombre');
        return [
            'nombre' => 'Asistente',
            'rol' => "Atiendes por WhatsApp a compradores y participantes de las rifas publicadas en {$marca}.",
            'objetivo' => 'Resolver dudas sobre las rifas activas (premio, precio del boleto, fecha y lotería del sorteo), '
                . 'ayudar a elegir y apartar números, explicar cómo pagar y enviar el comprobante, '
                . 'y consultar el estado de boletos, pagos y sorteos.',
            'personalidad' => 'Amable, claro y transparente. Nunca prometas que alguien va a ganar: '
                . 'el resultado lo define únicamente la lotería oficial del sorteo.',
        ];
    }

    /** Frases del default genérico del motor que delatan que NADIE ha
     *  configurado el agente. Solo en ese caso se reemplaza: un texto escrito
     *  por el administrador jamás se toca. */
    private const GENERICOS = ['tomar pedidos', 'mostrar la carta', 'clientes del negocio'];

    /**
     * Herramientas del motor que SÍ tienen sentido para rifas, con su nombre y
     * descripción en el idioma del dominio. Las que no están aquí (estado de
     * cocina, garantías, servicio técnico, almuerzo del día…) no se muestran
     * en el panel NI se le ofrecen al modelo.
     */
    public const HERRAMIENTAS = [
        'consultar_menu'    => ['titulo' => 'Ver rifas activas',
            'desc' => 'Lista las rifas en venta con su premio, el precio del boleto y los números disponibles. Se consulta siempre antes de hablar de rifas o precios: nada se inventa.'],
        'consultar_stock'   => ['titulo' => 'Ver disponibilidad de una rifa',
            'desc' => 'Cuántos números quedan disponibles en una rifa en este momento, antes de apartar.'],
        'calcular_total'    => ['titulo' => 'Calcular el total',
            'desc' => 'Calcula cuánto cuestan los boletos elegidos SIN apartarlos. Los precios los pone el servidor, nunca el modelo.'],
        'crear_pedido'      => ['titulo' => 'Apartar boletos',
            'desc' => 'Aparta los números elegidos a nombre del comprador y crea la reserva con su tiempo límite de pago.'],
        'consultar_pedido'  => ['titulo' => 'Consultar una reserva',
            'desc' => 'Estado de una reserva de boletos: apartada, pagada o vencida.'],
        'cancelar_pedido'   => ['titulo' => 'Cancelar una reserva',
            'desc' => 'Libera los números de una reserva que aún no se ha pagado.'],
        'generar_pago'      => ['titulo' => 'Indicar cómo pagar',
            'desc' => 'Entrega las instrucciones de pago de la reserva (los métodos que definió el organizador).'],
        'consultar_pago'    => ['titulo' => 'Verificar un pago',
            'desc' => 'Consulta el estado REAL del pago de una reserva. Un pago nunca se da por confirmado sin esta verificación.'],
        'transferir_a_humano' => ['titulo' => 'Pasar con una persona',
            'desc' => 'Transfiere la conversación al organizador cuando el cliente lo pide o algo se sale del guion. No se puede apagar.'],
    ];

    /**
     * Devuelve el agente activo, corrigiendo dos cosas si hace falta:
     * 1. Textos genéricos de restaurante → identidad de rifas.
     * 2. Lista de herramientas: se acota a las del dominio (una lista vacía o
     *    con herramientas ajenas dejaría al modelo hablando de cocinas).
     */
    public static function normalizar($am): array
    {
        $a = $am->activo();
        $cambios = [];

        $texto = mb_strtolower(($a['rol'] ?? '') . ' ' . ($a['objetivo'] ?? ''));
        foreach (self::GENERICOS as $g) {
            if (strpos($texto, $g) !== false) {
                $cambios = self::porDefecto();
                break;
            }
        }

        $curadas = array_keys(self::HERRAMIENTAS);
        $actuales = json_decode((string)($a['herramientas'] ?? ''), true);
        if (!is_array($actuales) || !$actuales) {
            // null = "todas las del motor" (incluiría cocina y garantías): se
            // fija la lista del dominio.
            $cambios['herramientas'] = json_encode($curadas);
        } else {
            $filtradas = array_values(array_intersect($actuales, $curadas));
            if (!$filtradas) {
                $filtradas = $curadas;
            }
            if ($filtradas !== array_values($actuales)) {
                $cambios['herramientas'] = json_encode($filtradas);
            }
        }

        if ($cambios) {
            $am->guardar($cambios);
            return $am->activo();
        }
        return $a;
    }

    /** Catálogo para el bloque "Qué puede hacer": solo herramientas del
     *  dominio, ya filtradas por el plan/capacidades del motor. */
    public static function catalogoPanel($te): array
    {
        $out = [];
        foreach ($te->definiciones(null, null) as $def) {
            $n = $def['name'];
            if (!isset(self::HERRAMIENTAS[$n])) {
                continue;
            }
            $out[] = [
                'nombre'      => $n,
                'titulo'      => self::HERRAMIENTAS[$n]['titulo'],
                'descripcion' => self::HERRAMIENTAS[$n]['desc'],
                'siempre'     => $n === 'transferir_a_humano',
            ];
        }
        return $out;
    }

    /** Acota una lista de herramientas elegida en el panel a las del dominio. */
    public static function acotarHerramientas(?array $lista): array
    {
        $curadas = array_keys(self::HERRAMIENTAS);
        if (!$lista) {
            return $curadas;
        }
        $filtradas = array_values(array_intersect($lista, $curadas));
        return $filtradas ?: $curadas;
    }
}
