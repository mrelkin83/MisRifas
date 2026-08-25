<?php
require_once __DIR__ . '/../config/database.php';
$page_title = "Ganadores - MisRifas";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <script>const BASE_PATH = "<?= BASE_PATH ?>";</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="<?= BASE_PATH ?>/public/css/tailwind.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background: #0f172a; color: #f8fafc; }
        .glass-nav {
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .winner-card {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 24px;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .winner-card:hover {
            transform: translateY(-5px);
            border-color: rgba(245, 158, 11, 0.3);
            background: rgba(30, 41, 59, 0.8);
            box-shadow: 0 20px 40px -15px rgba(0,0,0,0.5);
        }
        .gold-badge {
            background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%);
            color: #451a03;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            box-shadow: 0 4px 15px rgba(217, 119, 6, 0.4);
        }
        .winning-number-box {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border: 1px solid rgba(245, 158, 11, 0.2);
            position: relative;
            overflow: hidden;
        }
        .winning-number-box::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(245, 158, 11, 0.1) 0%, transparent 70%);
            animation: pulse 4s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.2); opacity: 0.8; }
        }

        @media (max-width: 767px) {
            #nav-menu {
                position: fixed;
                top: 80px;
                left: 0;
                right: 0;
                background: #0f172a;
                flex-direction: column;
                align-items: flex-start;
                padding: 1.5rem;
                border-bottom: 1px solid rgba(255,255,255,0.08);
                z-index: 100;
                gap: 1rem;
                display: none;
                box-shadow: 0 10px 15px -3px rgba(0,0,0,0.4);
            }
            #nav-menu.active {
                display: flex;
            }
        }
    </style>
</head>
<body class="bg-[#0f172a] text-slate-200">
    <header class="glass-nav sticky top-0 z-50">
        <nav class="container mx-auto px-4 h-20 flex items-center justify-between">
            <a href="<?= BASE_PATH ?>/public/index.php" class="flex items-center gap-3 text-2xl font-black text-primary">
                <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v1a2 2 0 0 0 0 4v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1a2 2 0 0 0 0-4Z"/><path d="M13 5v14" stroke-dasharray="2 3"/></svg>
                MisRifas
            </a>

            <button id="mobile-menu-btn" class="md:hidden text-white p-2 focus:outline-none">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path>
                </svg>
            </button>

            <div class="hidden md:flex items-center gap-6" id="nav-menu">
                <a href="<?= BASE_PATH ?>/public/index.php" class="text-slate-300 hover:text-white font-medium transition-colors">Inicio</a>
                <a href="<?= BASE_PATH ?>/public/mis-boletos.php" class="text-slate-300 hover:text-white font-medium transition-colors">Consultar Boletas</a>
                <a href="<?= BASE_PATH ?>/public/ganadores.php" class="text-white font-bold transition-colors border-b-2 border-primary pb-1 flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4Z"/><path d="M7 5H4a1 1 0 0 0-1 1 4 4 0 0 0 4 4M17 5h3a1 1 0 0 1 1 1 4 4 0 0 1-4 4"/></svg>
                    Ganadores
                </a>
                <a href="<?= BASE_PATH ?>/public/admin/index.php?auth=login" class="px-5 py-2.5 bg-white/5 border border-white/10 text-white rounded-xl hover:bg-white/10 transition-all font-medium backdrop-blur-sm">Iniciar Sesión</a>
            </div>
        </nav>
    </header>

    <main class="container mx-auto px-4 py-20">
        <div class="max-w-4xl mx-auto text-center mb-20">
            <h1 class="text-5xl md:text-7xl font-black mb-6 tracking-tight">
                Hall de la <span class="bg-clip-text text-transparent bg-gradient-to-r from-amber-400 to-orange-500">Fama</span>
            </h1>
            <p class="text-slate-400 text-xl md:text-2xl font-medium leading-relaxed">
                Celebramos a nuestros ganadores. Cada sorteo es real, transparente y verificado.
            </p>
        </div>

        <div id="winners-grid" class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-6xl mx-auto">
            <!-- Winners load here -->
            <div class="col-span-full text-center py-20">
                <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-amber-500 mx-auto"></div>
                <p class="mt-4 text-slate-400 uppercase tracking-widest font-bold text-xs">Cargando momentos de gloria...</p>
            </div>
        </div>
    </main>

    <footer class="bg-black/30 text-white py-12 border-t border-white/5 mt-20">
        <div class="container mx-auto px-4 text-center">
            <p class="text-slate-500">&copy; 2026 MisRifas Colombia. Transparencia garantizada.</p>
        </div>
    </footer>

    <script>
        async function loadWinners() {
            const grid = document.getElementById('winners-grid');
            try {
                const response = await fetch(BASE_PATH + '/api/raffles/winners.php');
                const json = await response.json();
                
                if (json.success && json.data.length > 0) {
                    grid.innerHTML = json.data.map(winner => {
                        const maskPhone = (phone) => {
                            if (!phone) return 'N/A';
                            return phone.trim().slice(0, -2) + 'XX';
                        };
                        
                        return `
                        <div class="winner-card group">
                            <div class="flex flex-col sm:flex-row h-full">
                                <div class="sm:w-2/5 relative h-48 sm:h-auto overflow-hidden">
                                    <img src="<?= BASE_PATH ?>/public${winner.image_url}" alt="${winner.raffle_name}" class="absolute inset-0 w-full h-full object-cover group-hover:scale-110 transition-transform duration-700">
                                    <div class="absolute inset-0 bg-gradient-to-r from-slate-900/80 to-transparent sm:hidden"></div>
                                </div>
                                <div class="flex-1 p-8 flex flex-col justify-between">
                                    <div>
                                        <div class="flex justify-between items-start mb-4">
                                            <span class="gold-badge px-3 py-1 rounded-lg text-[10px]">PREMIO ENTREGADO</span>
                                            <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest">${new Date(winner.draw_date).toLocaleDateString('es-CO')}</span>
                                        </div>
                                        <h3 class="text-2xl font-black text-white mb-2">${winner.raffle_name}</h3>
                                        <p class="text-slate-400 text-sm mb-6 uppercase tracking-wider font-bold">Lotería: ${winner.lottery_name}</p>
                                    </div>
                                    
                                    <div class="flex items-center gap-6 pt-6 border-t border-white/5">
                                        <div class="winning-number-box w-20 h-20 rounded-2xl flex items-center justify-center flex-shrink-0 shadow-xl shadow-primary/10">
                                            <span class="text-3xl font-black text-primary relative z-10">${winner.winning_ticket_number}</span>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Ganador Feliz</p>
                                            <p class="text-xl font-bold text-white">${winner.winner_name || 'Anónimo'}</p>
                                            <p class="text-sm text-slate-400 font-medium flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5 inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                                                ${winner.winner_city || 'Colombia'}
                                                <span class="mx-1 text-slate-600">•</span>
                                                <svg class="w-3.5 h-3.5 inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92Z"/></svg>
                                                ${maskPhone(winner.winner_phone)}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;}).join('');
                } else {
                    grid.innerHTML = '<p class="col-span-full text-center text-slate-500 py-20 font-medium tracking-wide">Aún no se han publicado ganadores. ¡Tú podrías ser el primero!</p>';
                }
            } catch (error) {
                grid.innerHTML = '<p class="col-span-full text-center text-red-500 py-20">Error al conectar con el domo de la victoria.</p>';
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadWinners();
            
            // Mobile Menu Toggle
            const mobileMenuBtn = document.getElementById('mobile-menu-btn');
            const navMenu = document.getElementById('nav-menu');
            if (mobileMenuBtn && navMenu) {
                mobileMenuBtn.addEventListener('click', () => {
                    navMenu.classList.toggle('active');
                });
            }
        });
    </script>
</body>
</html>
