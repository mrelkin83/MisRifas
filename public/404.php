<?php require_once __DIR__ . '/../config/paths.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Pagina no encontrada | MisRifas</title>
    <link rel="stylesheet" href="<?= BASE_PATH ?>/public/css/tailwind.min.css">
</head>
<body class="bg-[#0f172a] text-white min-h-screen flex items-center justify-center">
    <div class="text-center px-4">
        <div class="text-8xl font-bold text-blue-500 mb-4">404</div>
        <h1 class="text-2xl font-semibold mb-2">Pagina no encontrada</h1>
        <p class="text-slate-400 mb-8">Lo sentimos, la pagina que buscas no existe o ha sido movida.</p>
        <a href="<?= BASE_PATH ?>/public/index.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-8 rounded-lg transition-colors">
            Volver al inicio
        </a>
    </div>
</body>
</html>
