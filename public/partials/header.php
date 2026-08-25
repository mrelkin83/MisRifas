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
$basePath = defined('BASE_PATH') ? BASE_PATH : '';
?>
<header class="glass-nav sticky top-0 z-50 transition-all duration-300">
    <nav class="container mx-auto px-4 h-20 flex items-center justify-between">
        <a href="<?= $basePath ?>/public/index.php" class="flex items-center gap-2.5 group">
            <span class="flex items-center justify-center w-10 h-10 bg-blue-500/10 rounded-xl text-2xl group-hover:scale-110 transition-transform">🎟️</span>
            <span class="text-2xl font-black bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-emerald-400">MisRifas</span>
        </a>

        <!-- Mobile hamburger button -->
        <button id="mobile-menu-btn" class="md:hidden flex flex-col gap-1.5 p-2 z-50" aria-label="Menu">
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
            <a href="<?= $basePath ?>/public/vendor/index.php" class="text-slate-300 hover:text-white font-medium transition-colors <?= $current_page === 'index' && basename($_SERVER['REQUEST_URI']) === 'vendor/index.php' ? 'text-white' : '' ?>">
                Panel Vendedor
            </a>
            <a href="<?= $basePath ?>/public/ganadores.php" class="flex items-center gap-1 text-slate-300 hover:text-white font-medium transition-colors <?= $current_page === 'ganadores' ? 'text-white' : '' ?>">
                <span class="text-sm">🏆</span> Ganadores
            </a>
            <a href="<?= $basePath ?>/public/que-es.php" class="text-slate-300 hover:text-white font-medium transition-colors">
                ¿Qué es?
            </a>
            <a href="<?= $basePath ?>/public/dashboard.php" class="ml-2 px-5 py-2.5 bg-white/5 border border-white/10 text-white rounded-xl hover:bg-white/10 transition-all font-medium backdrop-blur-sm text-sm">
                Iniciar Sesion
            </a>
        </div>
    </nav>

    <!-- Mobile menu -->
    <div id="mobile-menu" class="md:hidden hidden bg-[#0f172a]/95 backdrop-blur-xl border-t border-white/5">
        <div class="container mx-auto px-4 py-4 flex flex-col gap-2">
            <a href="<?= $basePath ?>/public/index.php" class="text-slate-300 hover:text-white font-medium py-3 px-3 rounded-lg hover:bg-white/5 transition-all">Inicio</a>
            <a href="<?= $basePath ?>/public/dashboard.php" class="text-slate-300 hover:text-white font-medium py-3 px-3 rounded-lg hover:bg-white/5 transition-all">Mi Panel</a>
            <a href="<?= $basePath ?>/public/vendor/index.php" class="text-slate-300 hover:text-white font-medium py-3 px-3 rounded-lg hover:bg-white/5 transition-all">Panel Vendedor</a>
            <a href="<?= $basePath ?>/public/ganadores.php" class="text-slate-300 hover:text-white font-medium py-3 px-3 rounded-lg hover:bg-white/5 transition-all">🏆 Ganadores</a>
            <a href="<?= $basePath ?>/public/que-es.php" class="text-slate-300 hover:text-white font-medium py-3 px-3 rounded-lg hover:bg-white/5 transition-all">¿Qué es MisRifas?</a>
            <a href="<?= $basePath ?>/public/dashboard.php" class="mt-2 px-5 py-3 bg-white/5 border border-white/10 text-white rounded-xl hover:bg-white/10 transition-all font-medium text-center">Iniciar Sesión</a>
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
