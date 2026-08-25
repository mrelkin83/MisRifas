<?php
/**
 * Page: ¿Qué es MisRifas?
 */
require_once __DIR__ . '/../config/app.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¿Qué es MisRifas? - La plataforma #1 de Sorteos en Colombia</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background: #0f172a; color: white; }
        .premium-gradient { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); }
        .glass-card { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .text-gradient { background: linear-gradient(90deg, #60a5fa, #c084fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    </style>
</head>
<body class="premium-gradient min-h-screen">

    <!-- Header / Navbar Simplificado -->
    <nav class="border-b border-white/5 py-4 backdrop-blur-md sticky top-0 z-50">
        <div class="container mx-auto px-4 flex justify-between items-center">
            <a href="index.php" class="text-2xl font-black tracking-tighter">MIS<span class="text-blue-500">RIFAS</span></a>
            <a href="index.php" class="text-sm font-bold text-slate-400 hover:text-white transition-colors">← Volver al Inicio</a>
        </div>
    </nav>

    <main class="container mx-auto px-4 py-16 max-w-5xl">
        
        <!-- Hero Section -->
        <header class="text-center mb-24">
            <h1 class="text-5xl md:text-7xl font-black mb-6 leading-tight">
                Transformamos la forma de <br> 
                <span class="text-gradient">crear y ganar sorteos.</span>
            </h1>
            <p class="text-xl text-slate-400 max-w-2xl mx-auto leading-relaxed">
                MisRifas es la infraestructura digital diseñada para que cualquier persona o empresa en Colombia lance sorteos profesionales, seguros y 100% automatizados.
            </p>
        </header>

        <!-- Services Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-24">
            
            <div class="glass-card p-10 rounded-[40px] hover:bg-white/5 transition-all group">
                <div class="text-4xl mb-6 group-hover:scale-110 transition-transform inline-block">🎟️</div>
                <h3 class="text-2xl font-bold mb-4">Gestión Inteligente de Rifas</h3>
                <p class="text-slate-400 leading-relaxed">
                    Olvídate de las listas de papel. Nuestra plataforma genera miles de boletos digitales en segundos. Los usuarios pueden buscar sus números favoritos por ciudad o departamento, reservar al instante y recibir su comprobante digital de inmediato.
                </p>
            </div>

            <div class="glass-card p-10 rounded-[40px] hover:bg-white/5 transition-all group">
                <div class="text-4xl mb-6 group-hover:scale-110 transition-transform inline-block">📲</div>
                <h3 class="text-2xl font-bold mb-4">Notificaciones Automáticas WhatsApp + Email</h3>
                <p class="text-slate-400 leading-relaxed">
                    Se acabó el problema de los vendedores que nunca informan los resultados. En MisRifas, cada persona que compra un boleto recibe automáticamente una notificación por WhatsApp y Email a primera hora del día siguiente del sorteo, informándole los resultados y si fue uno de los afortunados ganadores. Transparencia total, sin depender de nadie.
                </p>
            </div>

            <div class="glass-card p-10 rounded-[40px] hover:bg-white/5 transition-all group">
                <div class="text-4xl mb-6 group-hover:scale-110 transition-transform inline-block">⚡</div>
                <h3 class="text-2xl font-bold mb-4">Pagos Automatizados</h3>
                <p class="text-slate-400 leading-relaxed">
                    Integración directa con Nequi y plataformas de pago colombianas. Tus compradores pueden pagar desde su celular y el sistema valida la transacción automáticamente, marcando el boleto como "Pagado" sin que tengas que mover un dedo.
                </p>
            </div>

            <div class="glass-card p-10 rounded-[40px] hover:bg-white/5 transition-all group">
                <div class="text-4xl mb-6 group-hover:scale-110 transition-transform inline-block">🏆</div>
                <h3 class="text-2xl font-bold mb-4">Lotería en Vivo</h3>
                <p class="text-slate-400 leading-relaxed">
                    La transparencia es nuestro pilar. Conectamos nuestras rifas con los resultados oficiales de las loterías de Colombia. El sistema verifica el número ganador en tiempo real y notifica al afortunado ganador automáticamente. Sin trucos, sin demoras.
                </p>
            </div>

        </div>

        <!-- How it works -->
        <section class="mb-24 bg-blue-600/10 border border-blue-500/20 rounded-[50px] p-12 overflow-hidden relative">
            <div class="relative z-10">
                <h2 class="text-3xl font-black mb-12 text-center">¿Cómo funciona para ti?</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-12">
                    <div class="text-center">
                        <div class="w-12 h-12 bg-blue-600 rounded-2xl flex items-center justify-center font-black mx-auto mb-6 text-xl shadow-lg shadow-blue-600/40">1</div>
                        <h4 class="font-bold text-lg mb-2">Crea tu Rifa</h4>
                        <p class="text-sm text-slate-400">Sube fotos, elige el premio, la lotería y el precio del boleto.</p>
                    </div>
                    <div class="text-center">
                        <div class="w-12 h-12 bg-blue-600 rounded-2xl flex items-center justify-center font-black mx-auto mb-6 text-xl shadow-lg shadow-blue-600/40">2</div>
                        <h4 class="font-bold text-lg mb-2">Comparte el Link</h4>
                        <p class="text-sm text-slate-400">Tus usuarios compran desde cualquier lugar de Colombia 24/7.</p>
                    </div>
                    <div class="text-center">
                        <div class="w-12 h-12 bg-blue-600 rounded-2xl flex items-center justify-center font-black mx-auto mb-6 text-xl shadow-lg shadow-blue-600/40">3</div>
                        <h4 class="font-bold text-lg mb-2">¡Gana y Entrega!</h4>
                        <p class="text-sm text-slate-400">El sistema anuncia al ganador y tú solo te encargas de la alegría.</p>
                    </div>
                </div>
            </div>
            <div class="absolute -right-20 -bottom-20 w-80 h-80 bg-blue-600/20 blur-[100px] rounded-full"></div>
        </section>

        <!-- CTA -->
        <footer class="text-center">
            <h2 class="text-3xl font-black mb-8 italic">¿Listo para lanzar tu primer gran sorteo?</h2>
            <a href="admin/index.php?auth=register" class="inline-block px-12 py-6 bg-white text-slate-950 rounded-3xl font-black text-xl hover:scale-105 transition-transform shadow-2xl shadow-white/10">
                Empezar Ahora 🚀
            </a>
            <p class="mt-8 text-slate-500 text-sm font-medium">Únete a cientos de emprendedores que ya usan MisRifas.</p>
        </footer>

    </main>

    <div class="py-12 border-t border-white/5 mt-12 text-center text-slate-600 text-xs font-bold uppercase tracking-widest">
        MisRifas Colombia &copy; 2026 · Tecnología para Soñadores
    </div>

</body>
</html>
