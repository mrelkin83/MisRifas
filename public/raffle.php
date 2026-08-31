<?php
/**
 * Página de Rifa pública — con meta OG dinámicas para WhatsApp/Facebook
 */

// ─── 1. Datos de la rifa (para OG tags) ──────────────────────
require_once __DIR__ . '/../config/database.php';

// Detectar dominio
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
// BASE_PATH (definido en config) es obligatorio aquí: sin él, og:url y
// canonical apuntan a la raíz del dominio en deploys bajo subcarpeta.
$baseUrl  = $protocol . '://' . $host . BASE_PATH . '/public';
$apiBase  = $protocol . '://' . $host . BASE_PATH . '/api';

$raffleId = (int)($_GET['id'] ?? 0);

// Meta defaults
$ogTitle       = 'MisRifas — Rifas digitales en Colombia';
$ogDescription = 'Participa en las mejores rifas digitales de Colombia. Pago fácil con Nequi o tarjeta. Sorteos verificados con loterías oficiales.';
$ogImage       = $baseUrl . '/img/og_default.jpg';
$ogUrl         = $baseUrl . '/raffle.php' . ($raffleId ? '?id=' . $raffleId : '');
$ogPrice       = '';
$ogDate        = '';
$raffleSlug    = '';

if ($raffleId) {
    try {
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT r.id, r.name, r.description, r.ticket_price, r.draw_date, r.total_tickets, r.image_url,
                   SUM(CASE WHEN t.status = 'paid' THEN 1 ELSE 0 END) AS sold_count
            FROM raffles r
            LEFT JOIN tickets t ON t.raffle_id = r.id
            WHERE r.id = ?
            GROUP BY r.id
        ");
        $stmt->execute([$raffleId]);
        $raffle = $stmt->fetch(PDO::FETCH_ASSOC);

        // §12.3: historial público de intentos de sorteo — la transparencia
        // es lo que hace confiable la reprogramación.
        $drawHistory = [];
        $raffleStatusRow = null;
        try {
            $dh = $db->prepare("SELECT attempt, draw_date, winning_number, ticket_status, outcome, rescheduled_to FROM raffle_draws WHERE raffle_id = ? ORDER BY attempt");
            $dh->execute([$raffleId]);
            $drawHistory = $dh->fetchAll(PDO::FETCH_ASSOC);
            $rs = $db->prepare("SELECT status, sales_blocked, draw_rescheduled_count FROM raffles WHERE id = ?");
            $rs->execute([$raffleId]);
            $raffleStatusRow = $rs->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        if ($raffle) {
            // Get all images for gallery
            $stmtImg = $db->prepare("SELECT image_url FROM raffle_images WHERE raffle_id = ? ORDER BY sort_order ASC");
            $stmtImg->execute([$raffleId]);
            $galleryImages = $stmtImg->fetchAll(PDO::FETCH_COLUMN);
            
            // Fallback to single image_url if no gallery images
            if (empty($galleryImages) && !empty($raffle['image_url'])) {
                $galleryImages = [$raffle['image_url']];
            }

            $price    = number_format((float)$raffle['ticket_price'], 0, ',', '.');
            $drawDate = $raffle['draw_date'] ? date('d M Y', strtotime($raffle['draw_date'])) : '';
            $name     = htmlspecialchars($raffle['name'], ENT_QUOTES);
            $desc     = htmlspecialchars($raffle['description'] ?? '', ENT_QUOTES);
            $sold     = (int)($raffle['sold_count'] ?? 0);
            $total    = max(1, (int)$raffle['total_tickets']);
            $pct      = round(($sold / $total) * 100);

            $ogTitle       = "Gana {$name} por solo \${$price} COP";
            $ogDescription = "Sorteo el {$drawDate}. {$pct}% vendido ({$sold}/{$total} boletos). ¡Consigue el tuyo y participa!";
            $ogUrl         = $baseUrl . '/raffle.php?id=' . $raffleId;

            // Imagen OG dinámica generada por PHP GD
            $ogImage = $apiBase . '/og/generate.php?raffle_id=' . $raffleId;

            $page_title = "{$name} — \${$price} COP | MisRifas";
            $ogPrice    = $price;
            $ogDate     = $drawDate;
        }
    } catch (Exception $e) {
        // fallback silencioso — no romper la página
    }
}

// ─── Cache headers para la página (no para la imagen) ─────────
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <script>const BASE_PATH = "<?= BASE_PATH ?>";</script>

    <!-- SEO base -->
    <meta name="description" content="<?= htmlspecialchars($ogDescription) ?>">
    <title><?= $page_title ?? 'Rifa | MisRifas' ?></title>

    <!-- Open Graph (WhatsApp, Facebook, Telegram) -->
    <meta property="og:type"        content="website">
    <meta property="og:site_name"   content="MisRifas">
    <meta property="og:title"       content="<?= htmlspecialchars($ogTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($ogDescription) ?>">
    <meta property="og:image"       content="<?= htmlspecialchars($ogImage) ?>">
    <meta property="og:image:width"  content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:url"         content="<?= htmlspecialchars($ogUrl) ?>">
    <meta property="og:locale"      content="es_CO">

    <!-- Twitter Card -->
    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="<?= htmlspecialchars($ogTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($ogDescription) ?>">
    <meta name="twitter:image"       content="<?= htmlspecialchars($ogImage) ?>">

    <!-- WhatsApp fuerza el refresh si la imagen cambia -->
    <link rel="canonical" href="<?= htmlspecialchars($ogUrl) ?>">

    <meta name="theme-color" content="#0f172a">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/public/css/tailwind.min.css">
    <style>
        @layer base {
            * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif;}
        }
        html { color-scheme: dark; }
        body { background: #0f172a; color: #f8fafc; }
        .skip-link {
            position: absolute; left: -9999px; top: 0; z-index: 200;
            padding: 10px 18px; background: #f59e0b; color: #1c1305;
            font-weight: 700; border-radius: 0 0 12px 0; text-decoration: none;
        }
        .skip-link:focus { left: 0; }
        .glass { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.05); }
        .ticket-btn {
            aspect-ratio: 1; display: flex; align-items: center; justify-content: center;
            font-weight: 800; border-radius: 12px; cursor: pointer; transition: transform 0.15s ease-out, box-shadow 0.2s ease-out, border-color 0.2s ease-out;
            border: 2px solid transparent; user-select: none; font-size: 1.1rem;
            overflow: hidden;
            /* Rifas de 4 cifras generan hasta 10.000 boletos: no pintar
               los que están fuera del viewport del grid scrolleable. */
            content-visibility: auto;
            contain-intrinsic-size: 64px 64px;
        }
        .ticket-btn--available { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; box-shadow: 0 4px 10px rgba(16,185,129,0.2); }
        .ticket-btn--available:hover { transform: translateY(-4px) scale(1.05); box-shadow: 0 10px 20px rgba(16,185,129,0.4); border-color: #34d399; }
        .ticket-btn--available:active { transform: scale(0.95); }
        .ticket-btn--reserved { background: linear-gradient(135deg, #eab308 0%, #ca8a04 100%); color: white; opacity: 0.85; cursor: not-allowed; }
        .ticket-btn--paid    { background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%); color: white; cursor: not-allowed; box-shadow: 0 4px 10px rgba(239,68,68,0.25); }
        .ticket-btn--selected { transform: scale(1.15) translateY(-4px); box-shadow: 0 0 0 4px rgba(245,158,11,0.5), 0 10px 20px rgba(245,158,11,0.4); border-color: white; z-index: 10; }
        @media (prefers-reduced-motion: no-preference) {
            .ticket-btn--selected { animation: pulse 2s infinite; }
        }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(245,158,11,0.7); } 70% { box-shadow: 0 0 0 10px rgba(245,158,11,0); } 100% { box-shadow: 0 0 0 0 rgba(245,158,11,0); } }
        .notification { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); max-width: 450px; width: 90%; padding: 20px 30px; background: #1e293b; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); z-index: 9999; animation: fadeIn 0.3s ease; border: 1px solid rgba(255,255,255,0.1); color: white; text-align: center; font-size: 16px;}
        .notification--error { border: 2px solid #ef4444; }
        .notification--success { border: 2px solid #10b981; }
        .notification--info { border: 2px solid #3b82f6; }
        @keyframes fadeIn { from { opacity: 0; transform: translate(-50%, -50%) scale(0.9); } to { opacity: 1; transform: translate(-50%, -50%) scale(1); } }
        .spinner { border: 4px solid rgba(255,255,255,0.1); border-top: 4px solid #f59e0b; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .modal-overlay { position: fixed; inset: 0; z-index: 50; }
        .modal-overlay__backdrop { position: absolute; inset: 0; background: rgba(15,23,42,0.8); backdrop-filter: blur(8px); }
        .modal-overlay__content {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            width: 100%; max-width: 28rem; background: #1e293b; border-radius: 20px;
            padding: 24px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); color: white;
        }
        @media (min-width: 768px) { .modal-overlay__content { padding: 32px; } }
        .countdown-box { background: rgba(15,23,42,0.6); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; padding: 16px; text-align: center; min-width: 0; }
        .countdown-box > div:first-child { font-variant-numeric: tabular-nums; font-size: 1.875rem; line-height: 1.1; }
        /* Conteo regresivo: en móviles pequeños (≤6.78") las 4 columnas con
           text-3xl y padding grande se desbordaban. Se reduce por breakpoint. */
        .countdown-grid { gap: 0.75rem; }
        @media (max-width: 480px) {
            .countdown-grid { gap: 0.4rem; }
            .countdown-box { padding: 10px 4px; border-radius: 10px; }
            .countdown-box > div:first-child { font-size: 1.35rem; }
            .countdown-box > div:last-child { font-size: 0.6rem; letter-spacing: 0; }
        }
        @media (max-width: 360px) {
            .countdown-box { padding: 8px 2px; }
            .countdown-box > div:first-child { font-size: 1.1rem; }
        }

        /* Hoja emergente de números seleccionados: antes iba en línea debajo de
           la grilla; ahora flota sobre el contenido (bottom-sheet en móvil,
           tarjeta inferior derecha en pantallas grandes). */
        #selection-backdrop { position: fixed; inset: 0; background: rgba(15,23,42,0.6); backdrop-filter: blur(4px); z-index: 60; }
        #multi-selection-summary.sheet {
            position: fixed; z-index: 70; left: 0; right: 0; bottom: 0;
            max-height: 85vh; overflow-y: auto; margin: 0;
            border-radius: 20px 20px 0 0;
            box-shadow: 0 -12px 40px rgba(0,0,0,0.5);
            animation: sheetUp 0.25s ease-out;
        }
        @keyframes sheetUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
        @media (min-width: 640px) {
            #multi-selection-summary.sheet { left: auto; right: 1.5rem; bottom: 1.5rem; width: 24rem; border-radius: 20px; }
        }
        /* Con la tab bar del sitio (z-90) presente en móvil: el FAB de
           selección flota POR ENCIMA de la barra y la hoja/backdrop la CUBREN
           al abrirse (si no, la barra taparía el botón de continuar). */
        @media (max-width: 768px) {
            #selection-fab { bottom: calc(78px + env(safe-area-inset-bottom, 0px)) !important; }
        }
        #selection-backdrop { z-index: 95; }
        #multi-selection-summary.sheet { z-index: 96; }
        #selection-fab {
            position: fixed; z-index: 94; left: 50%; transform: translateX(-50%); bottom: 16px;
            display: flex; align-items: center; gap: 10px; padding: 12px 22px; border-radius: 999px;
            background: linear-gradient(135deg,#f59e0b,#d97706); color: #1c1305; font-weight: 800;
            box-shadow: 0 10px 25px rgba(245,158,11,0.45); cursor: pointer; border: none; font-size: 0.95rem;
        }
        #selection-fab:active { transform: translateX(-50%) scale(0.97); }
        body.sheet-open { overflow: hidden; }
        /* Flechas de galería: en pantallas táctiles no hay hover, y con
           teclado deben aparecer al recibir foco. */
        #gallery-prev:focus-visible, #gallery-next:focus-visible { opacity: 1; }
        @media (hover: none) {
            #gallery-prev, #gallery-next { opacity: 1; }
        }
        input[type="text"], input[type="tel"], input[type="email"], select { background: rgba(15,23,42,0.6); border: 1px solid rgba(255,255,255,0.1); color: white; transition: border-color 0.3s, box-shadow 0.3s; }
        input:focus, select:focus { border-color: #f59e0b; box-shadow: 0 0 0 4px rgba(245,158,11,0.2); outline: none; }
        /* WhatsApp share button */
        .wa-share-btn {
            display: inline-flex; align-items: center; gap: 8px; flex-shrink: 0; white-space: nowrap;
            background: linear-gradient(135deg, #25d366, #128c7e);
            color: white; font-weight: 700; font-size: 14px;
            padding: 10px 18px; border-radius: 12px; border: none; cursor: pointer;
            text-decoration: none; transition: transform 0.2s ease-out, box-shadow 0.2s ease-out;
            box-shadow: 0 4px 14px rgba(37,211,102,0.35);
        }
        .wa-share-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(37,211,102,0.5); }
        .wa-share-btn:active { transform: scale(0.97); }
        
        @media (max-width: 640px) {
            .wa-share-btn span { display: none; }
            .wa-share-btn { padding: 10px; }
            #tickets-grid { 
                grid-template-columns: repeat(4, 1fr) !important; 
                gap: 8px !important; 
                padding: 12px !important;
            }
            .ticket-btn { font-size: 0.9rem !important; }
            header .container { padding: 0 1rem; }
            header a span.text-xl { font-size: 1rem; }
        }

        /* El nav de escritorio (8 items: Inicio/El Tapazo/Consultar Boletas/
           Ganadores/Que es/Iniciar Sesion/Crear Cuenta/Compartir WhatsApp) no
           entra comodo antes de 1024px - el boton hamburguesa y este overlay
           usan el mismo breakpoint (lg) para que no quede un rango donde
           ninguno de los dos funciona. */
        @media (max-width: 1023px) {
            #nav-menu {
                position: fixed;
                top: 80px;
                left: 0;
                right: 0;
                background: rgba(15, 23, 42, 0.98);
                backdrop-filter: blur(20px);
                flex-direction: column;
                align-items: flex-start;
                padding: 2rem;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                z-index: 100;
                gap: 1.5rem;
                display: none;
            }
            #nav-menu.active {
                display: flex !important;
            }
        }
    </style>
</head>
<body class="bg-[#0f172a] text-slate-200">
    <a href="#raffle-content" class="skip-link">Saltar al contenido</a>
    <header class="glass sticky top-0 z-50">
        <nav class="container mx-auto px-4 h-20 flex items-center justify-between gap-6">
            <a href="<?= BASE_PATH ?>/public/index.php" class="shrink-0 flex items-center gap-2 text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-amber-300 to-amber-500">
                <svg class="w-6 h-6 md:w-7 md:h-7 text-amber-400 drop-shadow-lg shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8.5V6a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v2.5a1.5 1.5 0 0 0 0 3V14a1.5 1.5 0 0 0 0 3v2.5a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V17a1.5 1.5 0 0 0 0-3v-2.5a1.5 1.5 0 0 0 0-3Z"/>
                    <path stroke-linecap="round" d="M15 5v14" stroke-dasharray="2 3"/>
                </svg>
                <span class="hidden xs:inline">MisRifas</span>
            </a>
            <button id="mobile-menu-btn" class="lg:hidden text-white p-2 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500" aria-label="Menu">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path>
                </svg>
            </button>

            <div class="hidden lg:flex items-center gap-2 xl:gap-4 shrink-0" id="nav-menu">
                <a href="<?= BASE_PATH ?>/public/index.php" class="shrink-0 whitespace-nowrap text-slate-300 hover:text-white font-medium transition-colors">Inicio</a>
                <a href="<?= BASE_PATH ?>/tapazo/index.php" class="shrink-0 whitespace-nowrap flex items-center gap-1.5 text-slate-300 hover:text-white font-medium transition-colors">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3h11l-1 15a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 3Zm11 4h2.5a2 2 0 0 1 2 2.2l-.4 4A2 2 0 0 1 18.1 15H16"/></svg>
                    El Tapazo
                </a>
                <a href="<?= BASE_PATH ?>/public/mis-boletos.php" class="shrink-0 whitespace-nowrap text-slate-300 hover:text-white font-medium transition-colors">Consultar Boletas</a>
                <a href="<?= BASE_PATH ?>/public/ganadores.php" class="shrink-0 whitespace-nowrap flex items-center gap-1.5 text-slate-300 hover:text-white font-medium transition-colors">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 21h8M12 17v4M7 4h10v4a5 5 0 0 1-10 0V4Zm0 1H4.5a1.5 1.5 0 0 0 0 3H7M17 5h2.5a1.5 1.5 0 0 1 0 3H17"/></svg>
                    Ganadores
                </a>
                <a href="<?= BASE_PATH ?>/public/que-es.php" class="shrink-0 whitespace-nowrap text-slate-300 hover:text-white font-medium transition-colors">¿Qué es MisRifas?</a>

                <div id="auth-buttons" class="flex items-center gap-4 shrink-0">
                    <a href="<?= BASE_PATH ?>/public/admin/index.php?auth=login" class="shrink-0 whitespace-nowrap px-4 py-2 bg-white/5 border border-white/10 text-white rounded-xl hover:bg-white/10 transition-all font-medium text-sm backdrop-blur-sm shadow-lg shadow-black/20">Iniciar Sesión</a>
                    <a href="<?= BASE_PATH ?>/public/register.php" class="shrink-0 whitespace-nowrap px-4 py-2 bg-gradient-to-r from-amber-400 to-amber-600 text-slate-950 rounded-xl hover:from-amber-300 hover:to-amber-500 transition-all font-bold text-sm shadow-lg shadow-amber-500/30">Crear Cuenta</a>
                </div>

                <div id="user-menu" class="hidden items-center gap-4">
                    <a href="<?= BASE_PATH ?>/public/perfil.php" class="flex items-center gap-2 group">
                        <div class="w-10 h-10 rounded-full bg-amber-500 text-slate-950 flex items-center justify-center text-lg font-bold group-hover:scale-110 transition-transform" id="user-avatar">U</div>
                        <span class="text-slate-200 font-bold group-hover:text-white" id="user-name">Usuario</span>
                    </a>
                    <button onclick="logout()" class="p-2.5 bg-white/5 border border-white/10 text-slate-400 rounded-xl hover:text-red-400 hover:bg-red-500/10 transition-all" title="Cerrar Sesión">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 21H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h3M16 17l5-5-5-5M21 12H9"/></svg>
                    </button>
                </div>

                <?php if ($raffleId): ?>
                <a id="wa-share-btn-nav" href="#" class="wa-share-btn" onclick="shareOnWhatsApp(event)">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M11.999 2C6.477 2 2 6.484 2 12.017c0 1.99.522 3.859 1.433 5.474L2.05 22l4.629-1.364A9.956 9.956 0 0 0 12 22.033C17.523 22.033 22 17.549 22 12.017 22 6.484 17.523 2 11.999 2zm0 18.06a8.079 8.079 0 0 1-4.298-1.232l-.308-.183-3.184.94.893-3.26-.202-.325A8.02 8.02 0 0 1 3.955 12c0-4.455 3.606-8.078 8.045-8.078 4.438 0 8.046 3.623 8.046 8.078 0 4.456-3.608 8.08-8.047 8.08z"/></svg>
                <span class="hidden md:inline lg:hidden xl:inline">Compartir en WhatsApp</span>
            </a>
            <?php endif; ?>
            </div>
            </nav>
            </header>

    <main class="py-10">
        <div class="container mx-auto px-4 max-w-7xl">
            <button onclick="history.back()" class="mb-6 flex items-center gap-2 text-slate-400 hover:text-white font-medium transition-colors">
                ← Volver al listado
            </button>

            <div id="error-msg" class="hidden bg-red-900/50 border border-red-500/50 text-red-200 rounded-xl p-4 mb-6 text-center">
                <p class="font-bold">Error al cargar la rifa</p>
                <p class="text-sm mt-1">Redirigiendo al inicio…</p>
            </div>

            <div id="raffle-content" class="hidden">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                    <!-- Detalle Izquierda -->
                    <div class="lg:col-span-5">
                        <div class="relative rounded-3xl overflow-hidden mb-6 border border-slate-700/50 shadow-2xl group">
                            <div class="absolute inset-0 bg-gradient-to-t from-slate-900 via-transparent to-transparent z-10"></div>
                            
                            <!-- Gallery Slider -->
                            <div id="gallery-container" class="relative flex items-center justify-center min-h-[300px] max-h-[500px]">
                                <div id="gallery-track" class="flex transition-transform duration-500 ease-out">
                                    <!-- Images injected by JS -->
                                </div>
                                <!-- Navigation Arrows -->
                                <button id="gallery-prev" onclick="prevImage()" aria-label="Imagen anterior" class="absolute left-3 top-1/2 -translate-y-1/2 z-20 w-10 h-10 bg-black/50 hover:bg-black/70 text-white rounded-full flex items-center justify-center text-xl opacity-0 group-hover:opacity-100 transition-opacity">❮</button>
                                <button id="gallery-next" onclick="nextImage()" aria-label="Imagen siguiente" class="absolute right-3 top-1/2 -translate-y-1/2 z-20 w-10 h-10 bg-black/50 hover:bg-black/70 text-white rounded-full flex items-center justify-center text-xl opacity-0 group-hover:opacity-100 transition-opacity">❯</button>
                                <!-- Dots Indicator -->
                                <div id="gallery-dots" class="absolute bottom-4 left-1/2 -translate-x-1/2 z-20 flex gap-2"></div>
                            </div>
                            
                            <div id="sold-percentage-badge" class="absolute top-4 right-4 px-4 py-2 bg-gradient-to-r from-amber-500 to-amber-600 text-slate-950 rounded-full font-bold text-sm shadow-lg z-20">
                                0% vendido
                            </div>
                        </div>

                        <div class="glass rounded-3xl p-8 mb-6">
                            <h1 id="raffle-title" class="text-4xl md:text-5xl font-bold mb-3 text-white leading-[1.05] tracking-tight">Cargando…</h1>
                            <p id="raffle-city" class="text-slate-400 mb-8 font-medium flex items-center gap-1.5"><svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-6.5 7-11.5a7 7 0 1 0-14 0C5 14.5 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.25"/></svg> Ciudad</p>

                            <div class="grid grid-cols-3 gap-4 mb-8 pb-8 border-b border-white/10">
                                <div>
                                    <span class="text-xs uppercase tracking-wider text-slate-500 mb-1 block">Precio</span>
                                    <div id="ticket-price" class="text-2xl font-black text-amber-400 font-mono">$0</div>
                                </div>
                                <div>
                                    <span class="text-xs uppercase tracking-wider text-slate-500 mb-1 block">Sorteo</span>
                                    <div id="draw-date" class="text-lg font-bold text-slate-200">--/--</div>
                                </div>
                                <div>
                                    <span class="text-xs uppercase tracking-wider text-slate-500 mb-1 block">Lotería</span>
                                    <div id="lottery-name" class="text-lg font-bold text-slate-200">---</div>
                                </div>
                            </div>

                            <div class="mb-8 p-6 bg-slate-900/50 rounded-2xl border border-white/5">
                                <div class="flex justify-between mb-3 text-sm font-medium">
                                    <span class="text-slate-300">Progreso de venta</span>
                                    <span id="sold-count" class="text-amber-400">0 / 0</span>
                                </div>
                                <div class="h-3 bg-slate-800 rounded-full overflow-hidden shadow-inner">
                                    <div id="progress-fill" class="h-full bg-gradient-to-r from-amber-500 to-amber-400 rounded-full transition-all duration-1000 ease-out" style="width: 0%"></div>
                                </div>
                            </div>

                            <div class="grid grid-cols-4 countdown-grid mb-8">
                                <div class="countdown-box shadow-xl">
                                    <div id="days" class="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-br from-amber-300 to-amber-500">0</div>
                                    <div class="text-xs text-slate-400 uppercase tracking-wider mt-1">Días</div>
                                </div>
                                <div class="countdown-box shadow-xl">
                                    <div id="hours" class="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-br from-amber-300 to-amber-500">0</div>
                                    <div class="text-xs text-slate-400 uppercase tracking-wider mt-1">Hrs</div>
                                </div>
                                <div class="countdown-box shadow-xl">
                                    <div id="minutes" class="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-br from-amber-300 to-amber-500">0</div>
                                    <div class="text-xs text-slate-400 uppercase tracking-wider mt-1">Min</div>
                                </div>
                                <div class="countdown-box shadow-xl">
                                    <div id="seconds" class="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-br from-amber-300 to-amber-500">0</div>
                                    <div class="text-xs text-slate-400 uppercase tracking-wider mt-1">Seg</div>
                                </div>
                            </div>

                            <div class="mb-8">
                                <h3 class="text-xl font-bold mb-3 text-white border-b border-slate-700 pb-2 inline-block">Descripción</h3>
                                <p id="raffle-description" class="text-slate-300 leading-relaxed text-lg">Cargando…</p>
                            </div>

                            <div>
                                <h3 class="text-sm font-bold mb-4 uppercase tracking-wider text-slate-500">Compartir con amigos</h3>
                                <div class="flex flex-wrap gap-3">
                                    <button onclick="shareRaffle('whatsapp')" class="px-4 py-2.5 bg-[#25D366]/20 text-[#25D366] border border-[#25D366]/30 rounded-xl text-sm font-bold hover:bg-[#25D366] hover:text-white transition-colors flex items-center gap-2"><svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M11.999 2C6.477 2 2 6.484 2 12.017c0 1.99.522 3.859 1.433 5.474L2.05 22l4.629-1.364A9.956 9.956 0 0 0 12 22.033C17.523 22.033 22 17.549 22 12.017 22 6.484 17.523 2 11.999 2zm0 18.06a8.079 8.079 0 0 1-4.298-1.232l-.308-.183-3.184.94.893-3.26-.202-.325A8.02 8.02 0 0 1 3.955 12c0-4.455 3.606-8.078 8.045-8.078 4.438 0 8.046 3.623 8.046 8.078 0 4.456-3.608 8.08-8.047 8.08z"/></svg> WhatsApp</button>
                                    <button onclick="shareRaffle('facebook')" class="px-4 py-2.5 bg-[#1877F2]/20 text-[#1877F2] border border-[#1877F2]/30 rounded-xl text-sm font-bold hover:bg-[#1877F2] hover:text-white transition-colors flex items-center gap-2"><svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M22 12.06C22 6.505 17.523 2 12 2S2 6.505 2 12.06c0 5.02 3.657 9.184 8.438 9.94v-7.03H7.898v-2.91h2.54V9.845c0-2.507 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562v1.877h2.773l-.443 2.91h-2.33V22c4.78-.756 8.437-4.92 8.437-9.94z"/></svg> Facebook</button>
                                    <button onclick="copyLink()" class="px-4 py-2.5 bg-slate-700 text-white rounded-xl text-sm font-bold hover:bg-slate-600 transition-colors flex items-center gap-2"><svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg> Copiar link</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cartones Derecha -->
                    <div class="lg:col-span-7">
                        <div class="glass rounded-3xl shadow-2xl p-6 md:p-8 sticky top-24">
                            
                            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                                <h2 class="text-2xl lg:text-3xl font-black text-white">Escoge tus boletos</h2>
                                <div class="flex flex-wrap gap-4 p-3 bg-slate-900/50 rounded-xl border border-white/5">
                                    <div class="flex items-center gap-2">
                                        <span class="w-3 h-3 bg-emerald-500 rounded-full shadow-[0_0_8px_rgba(16,185,129,0.8)]"></span>
                                        <span class="text-xs text-slate-300 font-medium">Libre</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="w-3 h-3 bg-amber-500 rounded-full shadow-[0_0_8px_rgba(245,158,11,0.8)]"></span>
                                        <span class="text-xs text-slate-300 font-medium">Reservado</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="w-3 h-3 rounded-full" style="background:#ef4444;box-shadow:0 0 8px rgba(239,68,68,0.8);"></span>
                                        <span class="text-xs text-slate-300 font-medium">Vendido</span>
                                    </div>
                                </div>
                            </div>

                            <div class="relative mb-8">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <span class="text-slate-500"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg></span>
                                </div>
                                <input type="text" id="ticket-search" inputmode="numeric" aria-label="Buscar número de boleto"
                                    class="w-full pl-12 pr-4 py-4 rounded-xl text-lg"
                                    placeholder="Buscar número específico (ej. 14, 07)…">
                            </div>

                            <div id="tickets-grid" class="grid grid-cols-5 sm:grid-cols-6 lg:grid-cols-8 gap-3 max-h-[500px] overflow-y-auto p-4 bg-slate-900/60 rounded-2xl mb-8 border border-slate-800 custom-scrollbar">
                                <div class="col-span-full flex flex-col justify-center items-center py-20 text-slate-400">
                                    <div class="spinner mb-4"></div>
                                    <p>Generando cartones disponibles...</p>
                                </div>
                            </div>

                            <div id="selected-info" class="bg-amber-900/20 border border-amber-500/30 rounded-2xl p-6 mb-8 text-center backdrop-blur shadow-inner">
                                <p class="text-slate-300 font-medium text-lg">Haz click en un número <span class="text-emerald-400 font-bold">Verde</span> para empezar</p>
                            </div>

                            <div id="selection-backdrop" class="hidden"></div>
                            <div id="multi-selection-summary" class="hidden sheet bg-gradient-to-r from-amber-900/30 to-amber-800/30 border border-amber-500/40 rounded-2xl p-6 backdrop-blur shadow-xl">
                                <div class="flex justify-between items-center mb-3">
                                    <p class="text-sm font-bold text-amber-300">Tu selección</p>
                                    <button id="selection-close" type="button" aria-label="Cerrar" class="w-9 h-9 flex items-center justify-center rounded-full bg-black/30 text-slate-300 hover:text-white hover:bg-black/50 text-2xl leading-none">&times;</button>
                                </div>
                                <div class="flex justify-between items-center mb-4">
                                    <div>
                                        <p class="text-xs uppercase tracking-widest text-slate-400 mb-1">Números seleccionados</p>
                                        <p class="text-3xl font-black text-white font-mono"><span id="selected-count">0</span></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs uppercase tracking-widest text-slate-400 mb-1">Total a pagar</p>
                                        <p class="text-3xl font-black text-amber-400 font-mono" id="selected-total">$0</p>
                                    </div>
                                </div>
                                <div id="selected-numbers-display" class="flex flex-wrap gap-2 mb-4"></div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                                    <input type="text" id="buyer-name" name="name" autocomplete="name" aria-label="Tu nombre" required placeholder="Tu nombre" class="w-full px-4 py-3 rounded-xl bg-black/30 border border-white/10 text-white placeholder:text-slate-500 focus:outline-none focus:border-amber-500">
                                    <input type="tel" id="buyer-phone" name="phone" autocomplete="tel" inputmode="numeric" aria-label="Número de WhatsApp" required placeholder="WhatsApp (3001234567)" pattern="[3][0-9]{9}" class="w-full px-4 py-3 rounded-xl bg-black/30 border border-white/10 text-white placeholder:text-slate-500 focus:outline-none focus:border-amber-500">
                                    <input type="email" id="buyer-email" name="email" autocomplete="email" aria-label="Correo electrónico" required placeholder="Correo (te avisamos el resultado)" class="w-full px-4 py-3 rounded-xl bg-black/30 border border-white/10 text-white placeholder:text-slate-500 focus:outline-none focus:border-amber-500">
                                </div>
                                <button id="pay-selected-btn" class="w-full py-4 bg-gradient-to-r from-amber-500 to-amber-600 text-slate-950 font-black rounded-xl text-lg hover:brightness-110 active:scale-[0.97] disabled:opacity-50 disabled:grayscale transition-all shadow-[0_0_20px_rgba(245,158,11,0.3)] hover:shadow-[0_0_25px_rgba(245,158,11,0.5)]">
                                    Pagar números seleccionados →
                                </button>
                            </div>

                            <!-- Botón flotante para reabrir la hoja sin perder la selección -->
                            <button id="selection-fab" type="button" class="hidden">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 3-3h14a3 3 0 0 1 3 3 2 2 0 0 0 0 4 3 3 0 0 1-3 3H5a3 3 0 0 1-3-3 2 2 0 0 0 0-4Z"/></svg>
                                <span>Ver selección (<span id="fab-count">0</span>) · <span id="fab-total">$0</span></span>
                            </button>

                            <button id="continue-btn" class="w-full py-5 bg-gradient-to-r from-amber-500 to-amber-600 text-slate-950 font-black rounded-2xl text-xl hover:from-amber-400 hover:to-amber-500 disabled:opacity-50 disabled:grayscale transition-all shadow-lg hover:shadow-amber-500/25 hidden">
                                Completar Compra →
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php if (!empty($raffleStatusRow['sales_blocked'])): ?>
        <section class="container mx-auto px-4 mt-6">
            <div class="max-w-3xl mx-auto p-4 rounded-xl bg-amber-500/10 border border-amber-500/40 text-amber-300 text-sm">
                ⚠️ El organizador tiene un saldo pendiente con la plataforma: las <strong>ventas nuevas están suspendidas</strong>. Los boletos ya pagados no se afectan y el sorteo se realizará normalmente.
            </div>
        </section>
        <?php endif; ?>
        <?php if (!empty($drawHistory) || (($raffleStatusRow['status'] ?? '') === 'cancelled')): ?>
        <section class="container mx-auto px-4 mt-10" aria-label="Historial de sorteos">
            <div class="max-w-3xl mx-auto bg-white/5 border border-white/10 rounded-2xl p-6">
                <h2 class="text-lg font-bold mb-1">🔍 Historial de sorteos</h2>
                <p class="text-xs text-slate-400 mb-4">Cada intento queda registrado públicamente. Solo un boleto <strong>pagado</strong> puede ganar; si el número cae en un boleto sin pagar o sin vender, el sorteo se reprograma (máximo 3 veces).</p>
                <?php if (($raffleStatusRow['status'] ?? '') === 'cancelled'): ?>
                <div class="mb-4 p-3 rounded-xl bg-red-500/15 border border-red-500/40 text-red-300 text-sm font-bold">
                    ✖ Esta rifa fue CANCELADA tras agotar sus reprogramaciones sin ganador. El organizador debe devolver el dinero de los boletos pagados.
                </div>
                <?php endif; ?>
                <?php $outLabel = ['winner' => '🏆 ¡Hubo ganador!', 'no_winner' => 'Sin ganador (boleto no pagado)', 'not_sold' => 'Sin ganador (número no vendido)'];
                foreach ($drawHistory as $d): ?>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 py-2 border-b border-white/5 text-sm">
                    <span class="text-slate-500">Intento <?= (int)$d['attempt'] ?></span>
                    <span><?= htmlspecialchars(date('d/m/Y', strtotime($d['draw_date'])), ENT_QUOTES, 'UTF-8') ?></span>
                    <span>salió <strong class="text-amber-400 font-mono"><?= htmlspecialchars((string)$d['winning_number'], ENT_QUOTES, 'UTF-8') ?></strong></span>
                    <span class="<?= $d['outcome'] === 'winner' ? 'text-emerald-400 font-bold' : 'text-slate-400' ?>"><?= $outLabel[$d['outcome']] ?? $d['outcome'] ?></span>
                    <?php if (!empty($d['rescheduled_to'])): ?>
                    <span class="text-slate-500">→ reprogramada al <?= htmlspecialchars(date('d/m/Y', strtotime($d['rescheduled_to'])), ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <footer class="bg-[#0b1120] text-slate-500 py-10 border-t border-slate-800 mt-20">
        <div class="container mx-auto px-4 text-center">
            <svg class="w-10 h-10 mx-auto mb-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="1.25" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8.5V6a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v2.5a1.5 1.5 0 0 0 0 3V14a1.5 1.5 0 0 0 0 3v2.5a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V17a1.5 1.5 0 0 0 0-3v-2.5a1.5 1.5 0 0 0 0-3Z"/></svg>
            <p class="font-medium">&copy; 2026 MisRifas Colombia. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script>
    const API = {
        async request(endpoint, options = {}) {
            const config = {
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...options.headers },
                ...options
            };
            const response = await fetch(BASE_PATH + '/api' + endpoint, config);
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Error');
            return data;
        },
        async get(endpoint, params = {}) {
            const qs = new URLSearchParams(params).toString();
            const url = qs ? endpoint + '?' + qs : endpoint;
            return this.request(url, { method: 'GET' });
        },
        async post(endpoint, data = {}) {
            return this.request(endpoint, { method: 'POST', body: JSON.stringify(data) });
        }
    };

    const Utils = {
        formatPrice(p) { return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 }).format(p); },
        formatDate(d) { return new Intl.DateTimeFormat('es-CO', { year: 'numeric', month: 'long', day: 'numeric' }).format(new Date(d)); },
        showNotification(msg, type = 'info') {
            const existing = document.querySelectorAll('.notification');
            existing.forEach(n => n.remove());
            const n = document.createElement('div');
            n.className = 'notification notification--' + type;
            n.setAttribute('role', 'status');
            n.setAttribute('aria-live', 'polite');
            const p = document.createElement('p');
            p.className = 'font-medium';
            p.textContent = msg;
            n.appendChild(p);
            document.body.appendChild(n);
            setTimeout(() => n.remove(), 3000);
        },
        esc(s) {
            return String(s ?? '').replace(/[&<>"']/g, c =>
                ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        }
    };

    let currentRaffle = null;
    let currentGalleryIndex = 0;

    function goToImage(index) {
        const track = document.getElementById('gallery-track');
        const dots = document.getElementById('gallery-dots');
        const total = track.children.length;
        currentGalleryIndex = index;
        track.style.transform = `translateX(-${index * 100}%)`;
        if (dots) {
            Array.from(dots.children).forEach((d, i) => {
                d.className = `w-2 h-2 rounded-full transition-all ${i === index ? 'bg-white w-6' : 'bg-white/40'}`;
            });
        }
    }

    function nextImage() {
        const track = document.getElementById('gallery-track');
        const total = track.children.length;
        goToImage((currentGalleryIndex + 1) % total);
    }

    function prevImage() {
        const track = document.getElementById('gallery-track');
        const total = track.children.length;
        goToImage((currentGalleryIndex - 1 + total) % total);
    }

    // Touch/Swipe support
    let touchStartX = 0;
    let touchEndX = 0;
    document.addEventListener('touchstart', e => {
        if (e.target.closest('#gallery-container')) touchStartX = e.changedTouches[0].screenX;
    });
    document.addEventListener('touchend', e => {
        if (e.target.closest('#gallery-container')) {
            touchEndX = e.changedTouches[0].screenX;
            const diff = touchStartX - touchEndX;
            if (Math.abs(diff) > 50) {
                if (diff > 0) nextImage(); else prevImage();
            }
        }
    });
    let selectedTickets = [];
    let countdownInterval = null;

    const urlParams = new URLSearchParams(window.location.search);
    const raffleId = urlParams.get('id');

    if (!raffleId) {
        window.location.href = BASE_PATH + '/public/index.php';
    }

    async function loadRaffleDetails() {
        try {
            const response = await API.get('/raffles/details.php', { id: raffleId });
            if (response.success) {
                currentRaffle = response.data;
                renderRaffleDetails();
                loadTickets();
                startCountdown();
                document.getElementById('raffle-content').classList.remove('hidden');
            } else {
                showError();
            }
        } catch (error) {
            showError();
        }
    }

    function showError() {
        document.getElementById('error-msg').classList.remove('hidden');
        setTimeout(() => window.location.href = BASE_PATH + '/public/index.php', 2000);
    }

    // image_url en BD es relativa a public/ (subida real: Uploader::upload()
    // devuelve "assets/uploads/raffles/x.jpg" sin slash inicial; el default
    // hardcodeado en create.php es "/assets/images/placeholder.svg" con
    // slash inicial - dos formatos, ninguno incluye "public/" porque los
    // archivos SI viven fisicamente ahi (public/assets/...). Sin anteponer
    // BASE_PATH + "/public/" el <img src> apuntaba a la raiz del dominio
    // (o a la raiz del subdirectorio sin "/public") y daba 404 siempre.
    function fixImageUrl(url) {
        if (!url) return '';
        if (url.startsWith('http')) return url;
        return BASE_PATH + '/public/' + url.replace(/^\/?(public\/)?/, '');
    }

    function renderRaffleDetails() {
        const r = currentRaffle;
        
        // Gallery de imágenes
        const images = r.images && r.images.length > 0 ? r.images.map(img => img.image_url) : [];
        if (r.image_url) images.unshift(r.image_url);
        
        // Eliminar duplicados
        const uniqueImages = [...new Set(images)];
        
        const track = document.getElementById('gallery-track');
        const dots = document.getElementById('gallery-dots');
        const prevBtn = document.getElementById('gallery-prev');
        const nextBtn = document.getElementById('gallery-next');
        
        if (uniqueImages.length > 0) {
            track.innerHTML = uniqueImages.map(url =>
                `<div class="min-w-full flex-shrink-0 flex items-center justify-center bg-slate-800"><img src="${fixImageUrl(url)}" alt="${Utils.esc(r.name)}" class="max-w-full max-h-[500px] w-auto h-auto object-contain"></div>`
            ).join('');
            dots.innerHTML = uniqueImages.map((_, i) =>
                `<button onclick="goToImage(${i})" aria-label="Ver imagen ${i + 1}" class="w-2 h-2 rounded-full transition-all ${i === 0 ? 'bg-white w-6' : 'bg-white/40'}"></button>`
            ).join('');
            prevBtn.style.display = uniqueImages.length > 1 ? 'flex' : 'none';
            nextBtn.style.display = uniqueImages.length > 1 ? 'flex' : 'none';
            currentGalleryIndex = 0;
        } else {
            track.innerHTML = '<div class="min-w-full flex-shrink-0"><div class="w-full h-[400px] bg-slate-800 flex items-center justify-center text-slate-600"><svg class="w-16 h-16" fill="none" stroke="currentColor" stroke-width="1.25" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8.5V6a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v2.5a1.5 1.5 0 0 0 0 3V14a1.5 1.5 0 0 0 0 3v2.5a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V17a1.5 1.5 0 0 0 0-3v-2.5a1.5 1.5 0 0 0 0-3Z"/></svg></div></div>';
            dots.innerHTML = '';
            prevBtn.style.display = 'none';
            nextBtn.style.display = 'none';
        }
        
        document.getElementById('raffle-title').textContent = r.name || '';
        document.getElementById('raffle-city').textContent = (r.city || '');
        document.getElementById('ticket-price').textContent = Utils.formatPrice(r.ticket_price || 0);
        document.getElementById('draw-date').textContent = Utils.formatDate(r.draw_date);
        document.getElementById('lottery-name').textContent = r.lottery_name || '';
        document.getElementById('raffle-description').textContent = r.description || '';

        const soldPct = r.sold_percentage || 0;
        document.getElementById('sold-percentage-badge').textContent = soldPct + '% vendido';
        document.getElementById('progress-fill').style.width = soldPct + '%';
        document.getElementById('sold-count').textContent = (r.sold_tickets || 0) + ' / ' + (r.total_tickets || 0);
        document.title = (r.name || 'Rifa') + ' - MisRifas';
    }

    async function loadTickets() {
        try {
            const response = await API.get('/tickets/available.php', { raffle_id: raffleId });
            if (response.success) {
                renderTickets(response.data);
            }
        } catch (error) {
            document.getElementById('tickets-grid').innerHTML = '<p class="col-span-full text-center text-red-500 py-8">Error al cargar los boletos</p>';
        }
    }

    function renderTickets(tickets) {
        const grid = document.getElementById('tickets-grid');
        grid.innerHTML = '';
        if (!tickets || tickets.length === 0) {
            grid.innerHTML = '<p class="col-span-full text-center text-gray-500 py-8">No hay boletos disponibles</p>';
            return;
        }

        tickets.forEach(ticket => {
            const div = document.createElement('div');
            let statusClass;
            if (ticket.status === 'available')       statusClass = 'ticket-btn--available';
            else if (ticket.status === 'reserved')   statusClass = 'ticket-btn--reserved';
            else                                      statusClass = 'ticket-btn--paid';

            div.className = 'ticket-btn ' + statusClass + ' p-1 rounded-xl text-center cursor-pointer transition-all border-2 relative';
            const opps = typeof ticket.opportunities === 'string' ? JSON.parse(ticket.opportunities) : (ticket.opportunities || []);

            // El número se dimensiona según su longitud (2-5 cifras) para que
            // NUNCA se desborde del botón cuadrado. Se quitó el checkbox: era
            // redundante (el estado seleccionado ya se ve con el anillo ámbar)
            // y robaba el ancho que hacía que se cortara la última cifra.
            const n = String(ticket.ticket_number);
            const sizeClass = n.length <= 3 ? 'text-xl' : (n.length === 4 ? 'text-base' : 'text-xs');
            div.innerHTML = '<span class="ticket-num font-bold font-mono leading-none ' + sizeClass + '">' + n + '</span>';
            div.dataset.id = ticket.id;
            div.dataset.number = ticket.ticket_number;
            div.dataset.opportunities = typeof ticket.opportunities === 'string' ? ticket.opportunities : JSON.stringify(ticket.opportunities || []);
            div.dataset.status = ticket.status;

            if (ticket.status === 'available') {
                div.onclick = () => toggleSelection(ticket);
            }

            grid.appendChild(div);
        });
    }

function toggleSelection(ticket) {
    const el = document.querySelector('[data-number="' + ticket.ticket_number + '"]');
    if (!el) return;

    const index = selectedTickets.findIndex(t => t.ticket_number === ticket.ticket_number);

    if (index > -1) {
        selectedTickets.splice(index, 1);
        el.classList.remove('ticket-btn--selected');
    } else {
        selectedTickets.push(ticket);
        el.classList.add('ticket-btn--selected');
    }

    updateSelectionSummary();
}

// Panel emergente de selección. En escritorio abre automáticamente como tarjeta
// flotante; en móvil arranca minimizado (botón flotante) para no tapar la grilla
// y poder elegir VARIOS números; el usuario lo abre cuando quiere pagar.
let sheetOpen = true;
let hadSelection = false;
function isMobileViewport() { return window.matchMedia('(max-width: 639px)').matches; }

function openSelectionSheet() {
    if (selectedTickets.length === 0) return;
    sheetOpen = true;
    document.getElementById('multi-selection-summary').classList.remove('hidden');
    document.getElementById('selection-fab').classList.add('hidden');
    // NO bloqueante: el panel flota pero la grilla de boletos sigue tocable y
    // scrolleable, para poder seguir eligiendo VARIOS números con el panel abierto.
    document.getElementById('selection-backdrop').classList.add('hidden');
    document.body.classList.remove('sheet-open');
}
function closeSelectionSheet() {
    sheetOpen = false;
    document.getElementById('multi-selection-summary').classList.add('hidden');
    document.getElementById('selection-backdrop').classList.add('hidden');
    document.body.classList.remove('sheet-open');
    if (selectedTickets.length > 0) document.getElementById('selection-fab').classList.remove('hidden');
}

function updateSelectionSummary() {
    const summaryDiv = document.getElementById('multi-selection-summary');
    const selectedInfo = document.getElementById('selected-info');
    const payBtn = document.getElementById('pay-selected-btn');
    const backdrop = document.getElementById('selection-backdrop');
    const fab = document.getElementById('selection-fab');

    if (selectedTickets.length === 0) {
        summaryDiv.classList.add('hidden');
        backdrop.classList.add('hidden');
        fab.classList.add('hidden');
        document.body.classList.remove('sheet-open');
        hadSelection = false;
        selectedInfo.classList.remove('hidden');
        selectedInfo.innerHTML = '<p class="text-slate-300 font-medium text-lg">Haz click en un número <span class="text-emerald-400 font-bold">Verde</span> para empezar</p>';
        payBtn.disabled = true;
        return;
    }

    selectedInfo.classList.add('hidden');

    const count = selectedTickets.length;
    const pricePerTicket = currentRaffle.ticket_price;
    const total = count * pricePerTicket;

    document.getElementById('selected-count').textContent = count;
    document.getElementById('selected-total').textContent = Utils.formatPrice(total);
    document.getElementById('fab-count').textContent = count;
    document.getElementById('fab-total').textContent = Utils.formatPrice(total);

    const numbersDisplay = document.getElementById('selected-numbers-display');
    numbersDisplay.innerHTML = selectedTickets.map(t =>
        `<span class="px-3 py-1 bg-amber-600/30 text-amber-300 rounded-lg font-mono text-sm border border-amber-500/30">${t.ticket_number}</span>`
    ).join('');

    payBtn.disabled = false;

    // Primera selección: escritorio abre la tarjeta; móvil arranca minimizado
    // (botón flotante) para seguir eligiendo varios números sin tapar la grilla.
    if (!hadSelection) {
        hadSelection = true;
        sheetOpen = !isMobileViewport();
    }

    if (sheetOpen) {
        openSelectionSheet();
    } else {
        summaryDiv.classList.add('hidden');
        fab.classList.remove('hidden');
    }
}

// position:fixed no ancla al viewport si un ancestro tiene backdrop-filter o
// transform (las tarjetas .glass): esos crean un bloque contenedor. Se mueven
// el backdrop, la hoja y el botón flotante a <body> para que floten de verdad.
['selection-backdrop', 'multi-selection-summary', 'selection-fab'].forEach(id => {
    const el = document.getElementById(id);
    if (el && el.parentNode !== document.body) document.body.appendChild(el);
});

document.getElementById('selection-close')?.addEventListener('click', closeSelectionSheet);
document.getElementById('selection-backdrop')?.addEventListener('click', closeSelectionSheet);
document.getElementById('selection-fab')?.addEventListener('click', openSelectionSheet);

document.getElementById('pay-selected-btn').addEventListener('click', async () => {
    if (selectedTickets.length === 0) return;

    const buyerName = document.getElementById('buyer-name').value.trim();
    const buyerPhone = document.getElementById('buyer-phone').value.trim();
    const buyerEmail = document.getElementById('buyer-email').value.trim();
    if (!buyerName || !buyerPhone || !buyerEmail) {
        Utils.showNotification('Completa tu nombre, WhatsApp y correo para continuar', 'error');
        return;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(buyerEmail)) {
        Utils.showNotification('El correo no parece válido — lo usamos para avisarte el resultado del sorteo', 'error');
        return;
    }

    const btn = document.getElementById('pay-selected-btn');
    btn.disabled = true;
    btn.textContent = 'Procesando reserva…';

    try {
        const numeros = selectedTickets.map(t => t.ticket_number);
        const response = await API.post('/payments/create-reservation.php', {
            raffle_id: raffleId,
            numeros: numeros,
            payment_gateway: 'manual',
            user: { name: buyerName, phone: buyerPhone, email: buyerEmail }
        });

        if (response.success) {
            const data = response.data;

            localStorage.setItem('current_reservation', JSON.stringify({
                reservation_id: data.reservation_id,
                numeros: data.numeros,
                ticket_price: currentRaffle.ticket_price,
                total_amount: data.amount,           // incluye el sufijo §6
                payment_suffix: data.payment_suffix,
                payment_methods: data.payment_methods || [],
                reserved_until: data.expires_at,
                raffle_name: data.raffle.name
            }));

            Utils.showNotification('Reserva creada exitosamente. Redirigiendo al pago…', 'success');

            setTimeout(() => {
                window.location.href = data.payment_url;
            }, 1500);
        } else {
            Utils.showNotification(response.message || 'Error al crear reserva', 'error');
            btn.disabled = false;
            btn.textContent = 'Pagar números seleccionados →';
        }
    } catch (error) {
        Utils.showNotification(error.message || 'Error al procesar la reserva', 'error');
        btn.disabled = false;
        btn.textContent = 'Pagar números seleccionados →';
    }
});

    function startCountdown() {
        if (countdownInterval) clearInterval(countdownInterval);
        countdownInterval = setInterval(() => {
            const drawDate = new Date(currentRaffle.draw_date).getTime();
            const now = Date.now();
            const diff = drawDate - now;

            if (diff <= 0) {
                clearInterval(countdownInterval);
                document.getElementById('days').textContent = '0';
                document.getElementById('hours').textContent = '0';
                document.getElementById('minutes').textContent = '0';
                document.getElementById('seconds').textContent = '0';
                return;
            }

            const d = Math.floor(diff / (1000 * 60 * 60 * 24));
            const h = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const s = Math.floor((diff % (1000 * 60)) / 1000);

            document.getElementById('days').textContent = d;
            document.getElementById('hours').textContent = h;
            document.getElementById('minutes').textContent = m;
            document.getElementById('seconds').textContent = s;
        }, 1000);
    }

    function shareRaffle(platform) {
        const url = window.location.href;
        const text = 'Mira esta rifa: ' + (currentRaffle?.name || '');
        const links = {
            whatsapp: 'https://wa.me/?text=' + encodeURIComponent(text + ' ' + url),
            facebook: 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url),
            telegram: 'https://t.me/share/url?url=' + encodeURIComponent(url) + '&text=' + encodeURIComponent(text),
            twitter: 'https://twitter.com/intent/tweet?url=' + encodeURIComponent(url) + '&text=' + encodeURIComponent(text)
        };
        if (platform === 'instagram') {
            Utils.showNotification('Comparte el enlace en tu historia de Instagram', 'info');
            return;
        }
        if (links[platform]) {
            window.open(links[platform], '_blank', 'width=600,height=400');
        }
    }

    function copyLink() {
        navigator.clipboard.writeText(window.location.href).then(() => {
            Utils.showNotification('Link copiado al portapapeles', 'success');
        }).catch(() => {
            Utils.showNotification('No se pudo copiar el link', 'error');
        });
    }

    document.getElementById('ticket-search').addEventListener('input', (e) => {
        const search = e.target.value.toLowerCase();
        document.querySelectorAll('.ticket-btn').forEach(ticket => {
            const number = ticket.dataset.number.toLowerCase();
            ticket.style.display = number.includes(search) ? 'flex' : 'none';
        });
    });

    // Mobile Menu Toggle
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const navMenu = document.getElementById('nav-menu');
    if (mobileMenuBtn && navMenu) {
        mobileMenuBtn.addEventListener('click', () => {
            navMenu.classList.toggle('active');
            navMenu.classList.toggle('hidden');
        });
    }

    loadRaffleDetails();

    /**
     * shareOnWhatsApp — abre WhatsApp con el enlace de la rifa.
     * WhatsApp hará el scraping del og:image automáticamente
     * y mostrará la tarjeta visual generada por /api/og/generate.php
     */
    function shareOnWhatsApp(e) {
        if (e) e.preventDefault();
        const url     = window.location.href;
        const name    = document.getElementById('raffle-title')?.textContent?.trim()
                        || document.title.replace(' | MisRifas', '').replace('🎫 ', '');
        const message = '🎉 ¡Participa en esta rifa!\n\n' + name + '\n\n' + url;
        window.open('https://wa.me/?text=' + encodeURIComponent(message), '_blank');
    }
    </script>
<?php $tabActive = 'inicio'; include __DIR__ . '/partials/tabbar.php'; ?>
</body>
</html>
