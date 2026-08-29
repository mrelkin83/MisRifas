<?php
/**
 * Helper compartido: iniciar el destape de un tapazo de forma ATÓMICA.
 *
 * BUG que resuelve: la asignación de numero_tapa (que decide el ganador) se
 * hacía con un patrón check-then-act SIN candado en 3 sitios (iniciar_destape.php
 * y dos bloques de destape.php). destape.php es un stream SSE que CADA navegador
 * abre; cuando llegaba la hora, varios streams simultáneos pasaban el check de
 * estado 'creado/esperando' antes de que ninguno cambiara el estado, y cada uno
 * hacía su propio shuffle → cada navegador veía un ganador distinto.
 *
 * Solución: bloquear la fila del tapazo con SELECT ... FOR UPDATE dentro de una
 * transacción, re-verificar el estado bajo el candado y asignar los numero_tapa
 * UNA sola vez. Los requests concurrentes se serializan: el primero asigna y
 * pasa a 'destapando'; el resto ven 'destapando' y no reasignan. Determinista.
 */

if (!function_exists('iniciarDestapeAtomico')) {
    /**
     * @param PDO    $db
     * @param string $codigo  codigo_unico del tapazo
     * @param bool   $force   true = iniciar aunque no haya llegado la hora
     *                        (para el botón "Iniciar Destape Ahora")
     * @return array|null     el tapazo (con estado actualizado) o null si no existe
     */
    function iniciarDestapeAtomico(PDO $db, string $codigo, bool $force = false): ?array
    {
        $db->beginTransaction();
        try {
            // Candado de fila: serializa a todos los que intenten iniciar el
            // destape del mismo tapazo.
            $stmt = $db->prepare("SELECT * FROM tapazos WHERE codigo_unico = ? FOR UPDATE");
            $stmt->execute([$codigo]);
            $tapazo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tapazo) {
                $db->commit();
                return null;
            }

            $now         = time();
            $destapeTime = strtotime((string)$tapazo['fecha_hora_destape']);
            $yaEsHora    = $force || ($destapeTime !== false && $now >= $destapeTime);
            $estadoPrevio = in_array($tapazo['estado'], ['creado', 'lleno', 'esperando'], true);

            // Solo el PRIMER request que entre bajo el candado en estado previo
            // llega aquí; una vez que pone 'destapando', los demás ven ese estado
            // y no reasignan.
            if ($estadoPrevio && $yaEsHora) {
                $jStmt = $db->prepare("SELECT id FROM tapazo_jugadores WHERE tapazo_id = ? ORDER BY id ASC");
                $jStmt->execute([$tapazo['id']]);
                $jugadores = $jStmt->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($jugadores)) {
                    // numero_tapa: valores únicos 1..999 barajados (deciden el
                    // ganador por mayor/menor). orden_destape: orden de revelado.
                    $numeros = range(1, 999);
                    shuffle($numeros);
                    $ordenes = range(1, count($jugadores));
                    shuffle($ordenes);

                    $upd = $db->prepare("UPDATE tapazo_jugadores SET numero_tapa = ?, orden_destape = ? WHERE id = ?");
                    foreach ($jugadores as $idx => $jid) {
                        $upd->execute([$numeros[$idx], $ordenes[$idx], $jid]);
                    }
                    $db->prepare("UPDATE tapazos SET estado = 'destapando', ultimo_revelado = '' WHERE id = ?")
                       ->execute([$tapazo['id']]);
                    $tapazo['estado'] = 'destapando';
                }
            }

            $db->commit();
            return $tapazo;

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
