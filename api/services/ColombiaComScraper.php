<?php

class ColombiaComScraper
{
    private static $lotterySlugMap = [
        'cundinamarca' => 'loteria-de-cundinamarca',
        'tolima' => 'loteria-del-tolima',
        'cruz roja' => 'loteria-de-la-cruz-roja',
        'huila' => 'loteria-del-huila',
        'manizales' => 'loteria-de-manizales',
        'meta' => 'loteria-del-meta',
        'valle' => 'loteria-del-valle',
        'quindio' => 'loteria-del-quindio',
        'quindío' => 'loteria-del-quindio',
        'bogotá' => 'loteria-de-bogota',
        'bogota' => 'loteria-de-bogota',
        'santander' => 'loteria-de-santander',
        'medellín' => 'loteria-de-medellin',
        'medellin' => 'loteria-de-medellin',
        'risaralda' => 'loteria-de-risaralda',
        'boyacá' => 'loteria-de-boyaca',
        'boyaca' => 'loteria-de-boyaca',
        'cauca' => 'loteria-del-cauca',
        'extra' => 'sorteo-extraordinario--loteria-extra',
    ];

    /**
     * $slugOverride: slug configurado por el admin en lotteries.api_source
     * (Gestión de Rifas → Scraper). Si viene vacío, se auto-resuelve del nombre.
     */
    public static function fetchResult($lotteryName, $drawDate, $slugOverride = '')
    {
        $slug = trim((string)$slugOverride) !== '' ? trim((string)$slugOverride) : self::resolveSlug($lotteryName);
        if (!$slug) {
            return null;
        }

        $url = "https://www.colombia.com/loterias/{$slug}";

        $html = self::httpGet($url);
        if (!$html) {
            return null;
        }

        // El bloque "último resultado" NO trae fecha en su interior, pero la
        // página publica "Fecha de Sorteo <día> de <mes> de <año>" justo antes.
        // Sin esta validación, scrapear ANTES de que la lotería publicara
        // devolvía el número del sorteo ANTERIOR y se guardaba como el de hoy
        // (verified=1) — y process_draws declararía un ganador con un número
        // que no salió en esa fecha. Solo se acepta si la fecha coincide.
        $number = self::extractWinningNumber($html);
        if ($number && self::fechaUltimoCoincide($html, $drawDate)) {
            return $number;
        }

        return self::extractFromHistorical($html, $drawDate);
    }

    /** ¿La "Fecha de Sorteo" publicada junto al último resultado es la pedida?
     *  Conservador: si la fecha no se encuentra, NO se acepta (queda el
     *  histórico, que sí compara fecha por fecha). */
    private static function fechaUltimoCoincide($html, $drawDate)
    {
        $fecha = self::fechaUltimo($html);
        return $fecha !== null && $fecha === date('Y-m-d', strtotime($drawDate));
    }

    /** Fecha (Y-m-d) del "Fecha de Sorteo" que la página publica justo antes
     *  del bloque de último resultado; null si no se puede determinar. */
    private static function fechaUltimo($html)
    {
        $pos = stripos($html, 'ultimo-resultado');
        if ($pos === false) {
            return null;
        }
        $antes = substr($html, max(0, $pos - 2500), 2500);
        $texto = html_entity_decode(strip_tags($antes), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!preg_match_all('/(\d{1,2})\s+de\s+([a-záéíóú]+)\s+de\s+(\d{4})/iu', $texto, $m, PREG_SET_ORDER)) {
            return null;
        }
        $months = [
            'enero' => '01', 'febrero' => '02', 'marzo' => '03', 'abril' => '04',
            'mayo' => '05', 'junio' => '06', 'julio' => '07', 'agosto' => '08',
            'septiembre' => '09', 'octubre' => '10', 'noviembre' => '11', 'diciembre' => '12',
        ];
        $ultima = end($m); // la fecha más cercana al bloque del resultado
        $mes = $months[mb_strtolower($ultima[2])] ?? null;
        if (!$mes) {
            return null;
        }
        return $ultima[3] . '-' . $mes . '-' . str_pad($ultima[1], 2, '0', STR_PAD_LEFT);
    }

    /** Último resultado PUBLICADO (número + fecha), sin filtrar por fecha:
     *  es lo que muestra el botón "Probar" del panel para validar la fuente. */
    public static function ultimoPublicado($lotteryName, $slugOverride = '')
    {
        $slug = trim((string)$slugOverride) !== '' ? trim((string)$slugOverride) : self::resolveSlug($lotteryName);
        if (!$slug) {
            return null;
        }
        $html = self::httpGet("https://www.colombia.com/loterias/{$slug}");
        if (!$html) {
            return null;
        }
        $numero = self::extractWinningNumber($html);
        if (!$numero) {
            return null;
        }
        return ['numero' => $numero, 'fecha' => self::fechaUltimo($html)];
    }

    /**
     * Devuelve el texto visible de la página de la lotería + su URL, para
     * cuando el parser determinista no da y se quiere que una IA lo LEA
     * (grounded, nunca de memoria). Devuelve null si no hay slug o no carga.
     *
     * @return array{url:string,text:string}|null
     */
    public static function pageTextFor($lotteryName)
    {
        $slug = self::resolveSlug($lotteryName);
        if (!$slug) {
            return null;
        }
        $url = "https://www.colombia.com/loterias/{$slug}";
        $html = self::httpGet($url);
        if (!$html) {
            return null;
        }
        // Quedarnos con el cuerpo y limpiar a texto plano acotado.
        $html = preg_replace('#<(script|style|noscript)[^>]*>.*?</\1>#is', ' ', $html);
        $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if ($text === '') {
            return null;
        }
        // Acotar a un tramo razonable para no inflar el prompt del LLM.
        return ['url' => $url, 'text' => mb_substr($text, 0, 6000)];
    }

    /** Slug efectivo que se usaría para un nombre de lotería (para mostrarlo
     *  en el panel de configuración del scraper). */
    public static function slugPara($lotteryName)
    {
        return self::resolveSlug($lotteryName);
    }

    private static function resolveSlug($lotteryName)
    {
        $name = strtolower($lotteryName);
        $name = str_replace(['lotería', 'loteria', 'de ', 'del ', 'la '], '', $name);
        $name = trim($name);

        foreach (self::$lotterySlugMap as $key => $slug) {
            $keyNorm = str_replace(['lotería', 'loteria', 'de ', 'del ', 'la '], '', $key);
            if (strpos($name, trim($keyNorm)) !== false || strpos(trim($keyNorm), $name) !== false) {
                return $slug;
            }
        }

        foreach (self::$lotterySlugMap as $key => $slug) {
            if (strpos(strtolower($lotteryName), $key) !== false) {
                return $slug;
            }
        }

        $search = strtolower($lotteryName);
        $search = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ'],
            ['a', 'e', 'i', 'o', 'u', 'n'],
            $search
        );
        $search = preg_replace('/[^a-z0-9]+/', '-', $search);
        $search = trim($search, '-');

        return $search;
    }

    private static function extractWinningNumber($html)
    {
        if (preg_match('/ultimo-resultado.*?<\/div>\s*<div[^>]*>\s*(.*?)<\/div>/is', $html, $container)) {
            $block = $container[1];
            if (preg_match_all('/<span[^>]*bg-danger[^>]*>\s*(\d)\s*<\/span>/i', $block, $digits)) {
                $number = implode('', $digits[1]);
                if (strlen($number) >= 2) {
                    return $number;
                }
            }
        }

        if (preg_match('/Numero Ganador<\/div>\s*<div[^>]*class="[^"]*d-flex[^"]*"[^>]*>\s*(.*?)<\/div>/is', $html, $container)) {
            $block = $container[1];
            if (preg_match_all('/<span[^>]*>\s*(\d)\s*<\/span>/i', $block, $digits)) {
                $number = implode('', $digits[1]);
                if (strlen($number) >= 2) {
                    return $number;
                }
            }
        }

        return null;
    }

    private static function extractFromHistorical($html, $drawDate)
    {
        $months = [
            'enero' => '01', 'febrero' => '02', 'marzo' => '03',
            'abril' => '04', 'mayo' => '05', 'junio' => '06',
            'julio' => '07', 'agosto' => '08', 'septiembre' => '09',
            'octubre' => '10', 'noviembre' => '11', 'diciembre' => '12',
        ];

        $targetDate = date('Y-m-d', strtotime($drawDate));

        if (preg_match_all('/<li[^>]*list-group-item[^>]*p-0[^>]*>(.*?)<\/li>/is', $html, $items)) {
            foreach ($items[1] as $item) {
                if (preg_match('/(\d{1,2})\s+(de\s+)?(\w+)\s+(de\s+)?(\d{4})/i', $item, $m)) {
                    $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
                    $monthName = strtolower(trim($m[3]));
                    $year = $m[5];
                    $month = $months[$monthName] ?? null;

                    if ($month) {
                        $dateStr = "{$year}-{$month}-{$day}";
                        if ($dateStr === $targetDate) {
                            if (preg_match_all('/<span[^>]*bg-danger[^>]*>\s*(\d)\s*<\/span>/i', $item, $digits)) {
                                $number = implode('', $digits[1]);
                                if (strlen($number) >= 2) {
                                    return $number;
                                }
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    private static function httpGet($url)
    {
        // Sin verificacion de TLS, un atacante en la red (o que envenene DNS)
        // podia servir HTML falso y este scraper lo tomaba como el numero
        // ganador real de la loteria (verified=1, sin revision humana) - un
        // problema de integridad en una app que mueve dinero real. No hay
        // razon para desactivarla contra un sitio publico como colombia.com.
        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
                'header' => "Accept: text/html,application/xhtml+xml\r\nAccept-Language: es-CO,es;q=0.9\r\n",
                'follow_location' => true,
                'max_redirects' => 3,
            ],
        ]);

        $html = @file_get_contents($url, false, $context);

        if ($html === false && function_exists('curl_init')) {
            $html = self::curlGet($url);
        }

        return $html ?: null;
    }

    private static function curlGet($url)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER => ['Accept: text/html', 'Accept-Language: es-CO,es;q=0.9'],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpCode >= 200 && $httpCode < 300) ? $response : null;
    }
}
