<?php

require_once __DIR__ . '/ColombiaComScraper.php';

class LotteryScraperService
{
    /**
     * Devuelve el número ganador REAL scrapeado, o null si no se pudo obtener.
     *
     * IMPORTANTE: nunca fabrica un número. Antes, al fallar el scraping, se
     * devolvía un número derivado de un md5(nombre+fecha) — que fetch_lottery_
     * results.php guardaba como resultado "verified=1" de colombia.com, y
     * process_draws.php usaba para declarar ganadores de rifas con dinero real.
     * Es decir: si el sitio de la lotería caía o cambiaba de formato, la
     * plataforma inventaba un ganador. Ahora, al fallar, se devuelve null: el
     * sorteo queda pendiente y se reintenta en la siguiente corrida del cron;
     * jamás se declara un ganador con un número que no salió de verdad.
     */
    public static function fetchResult($lotteryName, $drawDate)
    {
        try {
            $result = ColombiaComScraper::fetchResult($lotteryName, $drawDate);

            // Aceptar solo un número plausible: dígitos, 2 a 6 cifras.
            if (is_string($result) && preg_match('/^\d{2,6}$/', $result)) {
                return $result;
            }

            return null;
        } catch (Exception $e) {
            error_log("LotteryScraperService error: " . $e->getMessage());
            return null;
        }
    }
}
