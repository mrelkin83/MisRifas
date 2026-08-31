<?php
/**
 * Page: ¿Qué es MisRifas?
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/brand.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¿Qué es <?= plataforma_e() ?>? - La plataforma #1 de Sorteos en Colombia</title>
    <meta name="theme-color" content="#0f172a">
    <script>const BASE_PATH = "<?= BASE_PATH ?>";</script>
    <link rel="stylesheet" href="<?= BASE_PATH ?>/public/css/tailwind.min.css">
    <style>
        @font-face {
            font-family: 'Outfit';
            font-style: normal;
            font-weight: 800;
            font-display: swap;
            src: url('<?= BASE_PATH ?>/public/assets/fonts/outfit-800.woff2') format('woff2');
        }
        html { color-scheme: dark; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f172a; color: white; }
        h1, h2, h3, h4 { font-family: 'Outfit', 'Inter', sans-serif; }
        .premium-gradient { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); }
        .glass-card { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .text-gradient { background: linear-gradient(90deg, #fbbf24, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .glass-nav { background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        @media (max-width: 767px) {
            #nav-menu {
                position: fixed; top: 80px; left: 0; right: 0; background: #0f172a;
                flex-direction: column; align-items: flex-start; padding: 1.5rem;
                border-bottom: 1px solid rgba(255,255,255,0.08); z-index: 100; gap: 1rem;
                display: none; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.4);
            }
            #nav-menu.active { display: flex; }
        }
    </style>
</head>
<body class="premium-gradient min-h-screen">

    <header class="glass-nav sticky top-0 z-50">
        <nav class="container mx-auto px-4 h-20 flex items-center justify-between">
            <a href="<?= BASE_PATH ?>/public/index.php" class="text-2xl font-black tracking-tighter">MIS<span class="text-primary">RIFAS</span></a>
            <button id="mobile-menu-btn" class="md:hidden text-white p-2 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500" aria-label="Menu">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path></svg>
            </button>
            <div class="hidden md:flex items-center gap-6" id="nav-menu">
                <a href="<?= BASE_PATH ?>/public/index.php" class="text-slate-300 hover:text-white font-medium transition-colors">Inicio</a>
                <a href="<?= BASE_PATH ?>/tapazo/index.php" class="text-slate-300 hover:text-white font-medium transition-colors">🍺 El Tapazo</a>
                <a href="<?= BASE_PATH ?>/public/mis-boletos.php" class="text-slate-300 hover:text-white font-medium transition-colors">Resultados</a>
                <a href="<?= BASE_PATH ?>/public/comprobar-boleta.php" class="text-slate-300 hover:text-white font-medium transition-colors">Verificar Boleta</a>
                <a href="<?= BASE_PATH ?>/public/ganadores.php" class="text-slate-300 hover:text-white font-medium transition-colors">Ganadores</a>
                <a href="<?= BASE_PATH ?>/public/vendor/index.php?auth=register" class="px-5 py-2.5 bg-white/5 border border-white/10 text-white rounded-xl hover:bg-white/10 transition-all font-medium backdrop-blur-sm">Crear mi rifa</a>
            </div>
        </nav>
    </header>

    <main class="container mx-auto px-4 py-10 md:py-16 max-w-5xl">

        <!-- Hero Section -->
        <header class="text-center mb-12 md:mb-24">
            <h1 class="text-4xl md:text-7xl font-black mb-6 leading-tight" style="text-wrap:balance;">
                Transformamos la forma de
                <span class="text-gradient">crear y ganar sorteos.</span>
            </h1>
            <p class="text-lg md:text-xl text-slate-400 max-w-2xl mx-auto leading-relaxed">
                <?= plataforma_e() ?> es la infraestructura digital para que cualquier persona o empresa en Colombia lance sorteos profesionales, transparentes y fáciles de administrar.
            </p>
        </header>

        <!-- Services Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 md:gap-8 mb-12 md:mb-24">
            
            <div class="glass-card p-6 md:p-10 rounded-3xl md:rounded-[40px] hover:bg-white/5 transition-all group">
                <svg class="w-10 h-10 mb-6 text-primary group-hover:scale-110 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v1a2 2 0 0 0 0 4v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1a2 2 0 0 0 0-4Z"/><path d="M13 5v14" stroke-dasharray="2 3"/></svg>
                <h3 class="text-2xl font-bold mb-4">Gestión Inteligente de Rifas</h3>
                <p class="text-slate-400 leading-relaxed">
                    Olvídate de las listas de papel. Nuestra plataforma genera miles de boletos digitales en segundos. Los usuarios pueden buscar sus números favoritos por ciudad o departamento, reservar al instante y recibir su comprobante digital de inmediato.
                </p>
            </div>

            <div class="glass-card p-6 md:p-10 rounded-3xl md:rounded-[40px] hover:bg-white/5 transition-all group">
                <svg class="w-10 h-10 mb-6 text-primary group-hover:scale-110 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0a2.25 2.25 0 0 0-2.25-2.25h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
                <h3 class="text-2xl font-bold mb-4">Notificaciones Automáticas WhatsApp + Email</h3>
                <p class="text-slate-400 leading-relaxed">
                    Se acabó el problema de los vendedores que nunca informan los resultados. En <?= plataforma_e() ?>, cada persona que compra un boleto recibe automáticamente una notificación por WhatsApp y Email a primera hora del día siguiente del sorteo, informándole los resultados y si fue uno de los afortunados ganadores. Transparencia total, sin depender de nadie.
                </p>
            </div>

            <div class="glass-card p-6 md:p-10 rounded-3xl md:rounded-[40px] hover:bg-white/5 transition-all group">
                <svg class="w-10 h-10 mb-6 text-primary group-hover:scale-110 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11.35 2.25a.5.5 0 0 1 .84.46l-1.2 6.79h6.26a.75.75 0 0 1 .55 1.26l-8.5 11a.5.5 0 0 1-.84-.46l1.2-6.79H3.4a.75.75 0 0 1-.55-1.26l8.5-11Z"/></svg>
                <h3 class="text-2xl font-bold mb-4">Pagos directos a tu bolsillo</h3>
                <p class="text-slate-400 leading-relaxed">
                    Tus compradores te pagan directo a tu Nequi, Daviplata o llave Bre-B — la plataforma nunca toca tu dinero. Tú confirmas cada pago con un toque (desde el panel o respondiendo un WhatsApp) y el boleto queda vendido con su boleta digital emitida al instante.
                </p>
            </div>

            <div class="glass-card p-6 md:p-10 rounded-3xl md:rounded-[40px] hover:bg-white/5 transition-all group">
                <svg class="w-10 h-10 mb-6 text-primary group-hover:scale-110 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4Z"/><path d="M7 5H4a1 1 0 0 0-1 1 4 4 0 0 0 4 4M17 5h3a1 1 0 0 1 1 1 4 4 0 0 1-4 4"/></svg>
                <h3 class="text-2xl font-bold mb-4">Lotería en Vivo</h3>
                <p class="text-slate-400 leading-relaxed">
                    La transparencia es nuestro pilar. Conectamos nuestras rifas con los resultados oficiales de las loterías de Colombia. El sistema verifica el número ganador en tiempo real y notifica al afortunado ganador automáticamente. Sin trucos, sin demoras.
                </p>
            </div>

        </div>

        <!-- How it works -->
        <section class="mb-12 md:mb-24 bg-primary/10 border border-primary/20 rounded-3xl md:rounded-[50px] p-6 md:p-12 overflow-hidden relative">
            <div class="relative z-10">
                <h2 class="text-2xl md:text-3xl font-black mb-8 md:mb-12 text-center">¿Cómo funciona para ti?</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 md:gap-12">
                    <div class="text-center">
                        <div class="w-12 h-12 bg-primary text-slate-950 rounded-2xl flex items-center justify-center font-black mx-auto mb-6 text-xl shadow-lg shadow-primary/40">1</div>
                        <h4 class="font-bold text-lg mb-2">Crea tu Rifa</h4>
                        <p class="text-sm text-slate-400">Sube fotos, elige el premio, la lotería y el precio del boleto.</p>
                    </div>
                    <div class="text-center">
                        <div class="w-12 h-12 bg-primary text-slate-950 rounded-2xl flex items-center justify-center font-black mx-auto mb-6 text-xl shadow-lg shadow-primary/40">2</div>
                        <h4 class="font-bold text-lg mb-2">Comparte el Link</h4>
                        <p class="text-sm text-slate-400">Tus usuarios compran desde cualquier lugar de Colombia 24/7.</p>
                    </div>
                    <div class="text-center">
                        <div class="w-12 h-12 bg-primary text-slate-950 rounded-2xl flex items-center justify-center font-black mx-auto mb-6 text-xl shadow-lg shadow-primary/40">3</div>
                        <h4 class="font-bold text-lg mb-2">¡Gana y Entrega!</h4>
                        <p class="text-sm text-slate-400">El sistema anuncia al ganador y tú solo te encargas de la alegría.</p>
                    </div>
                </div>
            </div>
            <div class="absolute -right-20 -bottom-20 w-80 h-80 bg-primary/20 blur-[100px] rounded-full"></div>
        </section>

        <!-- CTA -->
        <footer class="text-center">
            <h2 class="text-2xl md:text-3xl font-black mb-8 italic" style="text-wrap:balance;">¿Listo para lanzar tu primer gran sorteo?</h2>
            <a href="<?= BASE_PATH ?>/public/vendor/index.php?auth=register" class="inline-block w-full sm:w-auto px-8 md:px-12 py-5 md:py-6 bg-primary text-slate-950 rounded-2xl md:rounded-3xl font-black text-lg md:text-xl hover:scale-105 active:scale-95 transition-transform shadow-2xl shadow-primary/20">
                Empezar Ahora
            </a>
            <p class="mt-8 text-slate-500 text-sm font-medium">Únete a cientos de emprendedores que ya usan <?= plataforma_e() ?>.</p>
        </footer>

    </main>

    <script>
        // Menú hamburguesa (mismo patrón del resto del sitio).
        document.addEventListener('DOMContentLoaded', () => {
            const btn = document.getElementById('mobile-menu-btn');
            const menu = document.getElementById('nav-menu');
            if (btn && menu) btn.addEventListener('click', () => menu.classList.toggle('active'));
        });
    </script>

    <div class="py-12 border-t border-white/5 mt-12 text-center text-slate-600 text-xs font-bold uppercase tracking-widest">
        <?= plataforma_e() ?> &copy; <?= date('Y') ?> · Tecnología para Soñadores
    </div>

<?php $tabActive = ''; include __DIR__ . '/partials/tabbar.php'; ?>
</body>
</html>
