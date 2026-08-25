<?php
// Redirigir al formulario unificado en admin
// Usar ruta relativa que funcione en cualquier servidor
$requestUri = $_SERVER['REQUEST_URI'];

// Si contiene /MisRifas/, eliminarlo para obtener ruta relativa
$path = str_replace('/MisRifas', '', $requestUri);
$path = rtrim($path, '/');

// Redirigir usando ruta relativa desde public/
header('Location: admin/index.php?auth=register');
exit;
