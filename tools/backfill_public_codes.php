<?php

declare(strict_types=1);

/** Backfill de raffles.public_code (v4.11). CLI, idempotente. */
if (php_sapi_name() !== 'cli') {
    die('Solo CLI');
}
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

// Alfabeto Crockford (sin I/L/O/U confundibles), 10 chars ≈ 50 bits.
function publicCode(): string
{
    $abc = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    $c = '';
    for ($i = 0; $i < 10; $i++) {
        $c .= $abc[random_int(0, 31)];
    }
    return $c;
}

$db = Database::getInstance()->getConnection();
$ids = $db->query('SELECT id FROM raffles WHERE public_code IS NULL')->fetchAll(PDO::FETCH_COLUMN);
$upd = $db->prepare('UPDATE raffles SET public_code = ? WHERE id = ?');
$n = 0;
foreach ($ids as $id) {
    for ($try = 0; $try < 5; $try++) {
        try {
            $upd->execute([publicCode(), (int)$id]);
            $n++;
            break;
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
        }
    }
}
echo "public_code asignado a $n rifas\n";
