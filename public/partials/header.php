<?php
/**
 * MisRifas - Header compartido
 * Incluir en todas las paginas del frontend publico.
 * Requiere: config/paths.php cargado antes.
 */
if (!defined('BASE_PATH') && function_exists('getBasePath')) {
    define('BASE_PATH', getBasePath());
}
if (!defined('BASE_PATH')) {
    require_once __DIR__ . '/../../config/paths.php';
}

// Detectar pagina actual para resaltar en nav
$current_page = basename($_SERVER['PHP_SELF'] ?? '', '.php');
// index.php existe tanto en public/ como en public/vendor/; distinguirlos
// requiere mirar el directorio, no el basename (que nunca contiene "/").
$is_vendor_page = str_contains(dirname($_SERVER['PHP_SELF'] ?? ''), 'vendor');
$basePath = defined('BASE_PATH') ? BASE_PATH : '';
?>
<header class="glass-nav sticky top-0 z-50 transition-all duration-300">
    <nav class="container mx-auto px-4 h-20 flex items-center justify-between">
        <a href="<?= $basePath ?>/public/index.php" class="flex items-center gap-2.5 group">
            <span class="flex items-center justify-center w-10 h-10 bg-amber-500/10 rounded-xl group-hover:scale-110 transition-transform">
                <svg class="w-6 h-6 text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v1a2 2 0 0 0 0 4v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1a2 2 0 0 0 0-4Z"/><path d="M13 5v14" stroke-dasharray="2 3"/></svg>
            </span>
            <span class="text-2xl font-black text-amber-400">MisRifas</span>
        </a>

        <!-- Mobile hamburger button -->
        <button id="mobile-menu-btn" class="md:hidden flex flex-col gap-1.5 p-2 z-50 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500" aria-label="Menu">
            <span class="block w-6 h-0.5 bg-white rounded-full transition-all duration-300"></span>
            <span class="block w-6 h-0.5 bg-white rounded-full transition-all duration-300"></span>
            <span class="block w-6 h-0.5 bg-white rounded-full transition-all duration-300"></span>
        </button>

        <!-- Desktop nav -->
        <div id="desktop-nav" class="hidden md:flex items-center gap-5" role="navigation">
            <a href="<?= $basePath ?>/public/index.php" class="text-slate-300 hover:text-white font-medium transition-colors <?= $current_page === 'index' ? 'text-white' : '' ?>">
                Inicio
            </a>
            <a href="<?= $basePath ?>/public/dashboard.php" class="text-slate-300 hover:text-white font-medium transition-colors <?= $current_page === 'dashboard' ? 'text-white' : '' ?>">
                Mi Panel
            </a>
            <a href="<?= $basePath ?>/public/vendor/index.php" class="text-slate-300 hover:text-white font-medium transition-colors <?= $current_page === 'index' && $is_vendor_page ? 'text-white' : '' ?>">
                Panel Vendedor
            </a>
            <a href="<?= $basePath ?>/public/ganadores.php" class="flex items-center gap-1 text-slate-300 hover:text-white font-medium transition-colors <?= $current_page === 'ganadores' ? 'text-white' : '' ?>">
                <svg class="w-4 h-4 inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4Z"/><path d="M7 5H4a1 1 0 0 0-1 1 4 4 0 0 0 4 4M17 5h3a1 1 0 0 1 1 1 4 4 0 0 1-4 4"/></svg> Ganadores
            </a>
            <a href="<?= $basePath ?>/public/que-es.php" class="text-slate-300 hover:text-white font-medium transition-colors">
                ¿Qué es?
            </a>
            <a href="<?= $basePath ?>/public/admin/index.php?auth=login" class="ml-2 px-5 py-2.5 bg-white/5 border border-white/10 text-white rounded-xl hover:bg-white/10 active:scale-[0.97] transition-all font-medium backdrop-blur-sm text-sm">
                Iniciar Sesión
            </a>
        </div>
    </nav>

    <!-- Mobile menu -->
    <div id="mobile-menu" class="md:hidden hidden bg-[#0f172a]/95 backdrop-blur-xl border-t border-white/5">
        <div class="container mx-auto px-4 py-4 flex flex-col gap-2">
            <a href="<?= $basePath ?>/public/index.php" class="text-slate-300 hover:text-white font-medium py-3 px-3 rounded-lg hover:bg-white/5 transition-all">Inicio</a>
            <a href="<?= $basePath ?>/public/dashboard.php" class="text-slate-300 hover:text-white font-medium py-3 px-3 rounded-lg hover:bg-white/5 transition-all">Mi Panel</a>
            <a href="<?= $basePath ?>/public/vendor/index.php" class="text-slate-300 hover:text-white font-medium py-3 px-3 rounded-lg hover:bg-white/5 transition-all">Panel Vendedor</a>
            <a href="<?= $basePath ?>/public/ganadores.php" class="text-slate-300 hover:text-white font-medium py-3 px-3 rounded-lg hover:bg-white/5 transition-all"><svg class="w-4 h-4 inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4Z"/><path d="M7 5H4a1 1 0 0 0-1 1 4 4 0 0 0 4 4M17 5h3a1 1 0 0 1 1 1 4 4 0 0 1-4 4"/></svg> Ganadores</a>
            <a href="<?= $basePath ?>/public/que-es.php" class="text-slate-300 hover:text-white font-medium py-3 px-3 rounded-lg hover:bg-white/5 transition-all">¿Qué es MisRifas?</a>
            <a href="<?= $basePath ?>/public/admin/index.php?auth=login" class="mt-2 px-5 py-3 bg-white/5 border border-white/10 text-white rounded-xl hover:bg-white/10 active:scale-[0.97] transition-all font-medium text-center">Iniciar Sesión</a>
        </div>
    </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('mobile-menu-btn');
    const menu = document.getElementById('mobile-menu');
    if (btn && menu) {
        btn.addEventListener('click', function() {
            menu.classList.toggle('hidden');
            const spans = btn.querySelectorAll('span');
            if (!menu.classList.contains('hidden')) {
                spans[0].style.transform = 'rotate(45deg) translate(5px, 5px)';
                spans[1].style.opacity = '0';
                spans[2].style.transform = 'rotate(-45deg) translate(5px, -5px)';
            } else {
                spans[0].style.transform = '';
                spans[1].style.opacity = '';
                spans[2].style.transform = '';
            }
        });
    }
});
</script>
