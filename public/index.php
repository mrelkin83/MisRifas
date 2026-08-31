<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/../config/database.php';

$cache_bust = time();
$page_title = "MisRifas - Rifas Digitales en Colombia";
$page_description = "La plataforma más confiable para crear y participar en rifas digitales en Colombia. 100% gratuita y segura.";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <script>const BASE_PATH = "<?= BASE_PATH ?>";</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="description" content="<?= $page_description ?>">
    <title><?= $page_title ?></title>
    <meta name="theme-color" content="#0f172a">
    <link rel="preconnect" href="https://picsum.photos">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/public/css/tailwind.min.css">
    <style>
        /* Fuente display para titulares - autohospedada (nunca <link> a
           Google Fonts en produccion), un solo peso (800) que cubre el uso
           real en esta pagina (hero, "Como funciona", tarjetas de rifa). */
        @font-face {
            font-family: 'Outfit';
            font-style: normal;
            font-weight: 800;
            font-display: swap;
            src: url('<?= BASE_PATH ?>/public/assets/fonts/outfit-800.woff2') format('woff2');
        }
        @layer base {
            * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        }
        html { color-scheme: dark; }
        body { background: #0f172a; color: #f8fafc; }
        h1, h2, .hero-slide__title, .raffle-card__title { font-family: 'Outfit', 'Inter', sans-serif; }
        /* Skip link: invisible hasta recibir foco de teclado */
        .skip-link {
            position: absolute; left: -9999px; top: 0; z-index: 200;
            padding: 10px 18px; background: #f59e0b; color: #1c1305;
            font-weight: 700; border-radius: 0 0 12px 0; text-decoration: none;
        }
        .skip-link:focus { left: 0; }
        .glass-nav {
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .raffle-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 30px -10px rgba(0,0,0,0.5);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s cubic-bezier(0.4, 0, 0.2, 1), border-color 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .raffle-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -12px rgba(0,0,0,0.5);
            border: 1px solid rgba(245,158,11,0.2);
        }
        /* Scroll-reveal: solo opacity (nunca junto a "transform" del hover
           de arriba, para que no compitan por la misma propiedad).
           prefers-reduced-motion deja todo visible de inmediato. */
        @media (prefers-reduced-motion: no-preference) {
            .raffle-card { opacity: 0; transition: opacity 0.6s ease; }
            .raffle-card.is-visible { opacity: 1; }
        }
        .raffle-card__image { position: relative; width: 100%; height: 220px; overflow: hidden; }
        .raffle-card__image img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
        .raffle-card:hover .raffle-card__image img { transform: scale(1.1); }
        .raffle-card__badge { position: absolute; top: 12px; right: 12px; padding: 6px 14px; background: rgba(15,23,42,0.75); backdrop-filter: blur(6px); border: 1px solid rgba(245,158,11,0.4); color: #fbbf24; border-radius: 9999px; font-size: 12px; font-weight: 700; }
        .raffle-card__content { padding: 24px; }
        .raffle-card__title { font-size: 22px; font-weight: 700; margin-bottom: 8px; color: #f8fafc; font-family: 'Outfit', 'Inter', sans-serif; }
        .raffle-card__city { font-size: 14px; color: #94a3b8; margin-bottom: 16px; }
        .raffle-card__info { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .raffle-card__price { font-size: 26px; font-weight: 800; background: linear-gradient(to right, #fbbf24, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .raffle-card__price span { display: block; font-size: 12px; font-weight: 500; color: #94a3b8; -webkit-text-fill-color: initial; }
        .raffle-card__date { font-size: 14px; color: #cbd5e1; text-align: right; }
        .progress-bar { width: 100%; height: 6px; background: rgba(255,255,255,0.1); border-radius: 9999px; overflow: hidden; margin-bottom: 8px; }
        .progress-bar__fill { height: 100%; background: linear-gradient(90deg, #f59e0b, #fbbf24); border-radius: 9999px; transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1); }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 12px 24px; font-size: 16px; font-weight: 600; border-radius: 12px; cursor: pointer; border: none; text-decoration: none; transition: transform 160ms ease-out, box-shadow 200ms ease-out; }
        .btn--primary { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #1c1305; box-shadow: 0 4px 15px rgba(217,119,6,0.35); }
        .btn--primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(217,119,6,0.5); }
        .btn:active { transform: scale(0.97); }
    .notification { 
        position: fixed; 
        top: 50%; 
        left: 50%; 
        transform: translate(-50%, -50%);
        max-width: 450px; 
        width: 90%;
        padding: 20px 30px; 
        background: #1e293b; 
        color: white; 
        border-radius: 16px; 
        box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); 
        z-index: 9999; 
        border: 1px solid rgba(255,255,255,0.1);
        text-align: center;
        font-size: 16px;
        font-weight: 500;
    }
        .notification--error { border: 2px solid #ef4444; background: rgba(239, 68, 68, 0.95); }
        .notification--success { border: 2px solid #10b981; background: rgba(16, 185, 129, 0.95); }
        .notification--info { border: 2px solid #3b82f6; background: rgba(59, 130, 246, 0.95); }
        .notification--warning { border: 2px solid #f59e0b; background: rgba(245, 158, 11, 0.95); color: #111827; }
        .no-results { text-align: center; padding: 60px; color: #94a3b8; font-size: 18px; }
        input[type="text"] { background: rgba(30,41,59,0.8); border: 1px solid rgba(255,255,255,0.1); color: white; transition: border-color 0.3s, box-shadow 0.3s; }
        input[type="text"]:focus { border-color: #f59e0b; box-shadow: 0 0 0 4px rgba(245,158,11,0.2); }
        .tab { background: rgba(30,41,59,0.6); color: #cbd5e1; border: 1px solid rgba(255,255,255,0.05); transition: background-color 0.3s, color 0.3s; }
        .tab:hover { background: rgba(51,65,85,0.8); color: white; }
        .tab.active { background: linear-gradient(135deg, #f59e0b, #d97706); color: #1c1305; border-color: transparent; box-shadow: 0 4px 15px rgba(217,119,6,0.4); }

        /* ===== HERO SLIDER ===== */
        .hero-slider { position: relative; width: 100%; height: 600px; overflow: hidden; }
        @media (max-width: 768px) { .hero-slider { height: 480px; } }

        .hero-slider__track { display: flex; height: 100%; transition: transform 0.75s cubic-bezier(0.77, 0, 0.175, 1); will-change: transform; }

        .hero-slide {
            min-width: 100%; height: 100%; position: relative; overflow: hidden;
            display: flex; align-items: center; justify-content: center;
        }
        .hero-slide__bg {
            position: absolute; inset: 0;
            background-size: cover; background-position: center;
            transition: transform 8s ease-out;
            transform: scale(1.08);
        }
        .hero-slide.is-active .hero-slide__bg { transform: scale(1); }
        .hero-slide__overlay {
            position: absolute; inset: 0;
            background: linear-gradient(90deg, rgba(0,0,0,0.78) 0%, rgba(0,0,0,0.45) 55%, rgba(0,0,0,0.15) 100%);
        }
        .hero-slide__content {
            position: relative; z-index: 10; text-align: left;
            max-width: 680px; padding: 0 56px;
            opacity: 0; transform: translateY(24px);
            transition: opacity 0.7s 0.15s ease, transform 0.7s 0.15s ease;
        }
        @media (max-width: 640px) {
            .hero-slide__content { padding: 0 24px; }
        }
        .hero-slide.is-active .hero-slide__content { opacity: 1; transform: translateY(0); }

        .hero-slide__tag {
            display: block;
            font-size: 13px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase;
            color: #fbbf24;
            margin-bottom: 16px;
        }
        .hero-slide__title {
            font-size: clamp(2.25rem, 6.5vw, 4.75rem); font-weight: 700;
            line-height: 1.05; letter-spacing: -0.02em; color: #fff; margin-bottom: 20px;
        }
        @media (max-width: 640px) {
            .hero-slide__title { margin-bottom: 12px; }
            .hero-slide__desc { font-size: 1rem !important; margin-bottom: 24px !important; }
        }
        .hero-slide__title em {
            font-style: normal;
            background: linear-gradient(90deg, #fbbf24, #f59e0b);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .hero-slide__desc {
            font-size: 1.2rem; color: rgba(255,255,255,0.75); font-weight: 400;
            margin-bottom: 36px; line-height: 1.5; max-width: 480px; letter-spacing: -0.005em;
        }
        .hero-slide__actions { display: flex; gap: 14px; flex-wrap: wrap; }
        .hero-slide__btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 14px 30px; border-radius: 14px; font-size: 16px; font-weight: 700;
            cursor: pointer; border: none; text-decoration: none;
            transition: transform 160ms ease-out, box-shadow 200ms ease-out, background-color 200ms ease-out;
        }
        .hero-slide__btn--primary {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: #1c1305; box-shadow: 0 8px 30px rgba(217,119,6,0.45);
        }
        .hero-slide__btn--primary:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 14px 40px rgba(217,119,6,0.6);
        }
        .hero-slide__btn--ghost {
            background: rgba(255,255,255,0.1); color: white;
            border: 1.5px solid rgba(255,255,255,0.25); backdrop-filter: blur(6px);
        }
        .hero-slide__btn--ghost:hover { background: rgba(255,255,255,0.18); transform: translateY(-2px); }
        .hero-slide__btn:active { transform: scale(0.97); }

        /* Arrows */
        .hero-slider__arrow {
            position: absolute; top: 50%; transform: translateY(-50%); z-index: 20;
            width: 52px; height: 52px; border-radius: 50%;
            background: rgba(255,255,255,0.08); border: 1.5px solid rgba(255,255,255,0.15);
            backdrop-filter: blur(8px); color: white; font-size: 20px;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            transition: background-color 0.2s, transform 0.2s; user-select: none;
        }
        .hero-slider__arrow:hover { background: rgba(255,255,255,0.18); transform: translateY(-50%) scale(1.08); }
        .hero-slider__arrow--prev { left: 24px; }
        .hero-slider__arrow--next { right: 24px; }
        @media (max-width: 640px) {
            /* En móvil las flechas se encimaban sobre el texto del hero. Se
               ocultan: swipe táctil + dots + autoplay cubren la navegación. */
            .hero-slider__arrow { display: none; }
        }

        /* Dots */
        .hero-slider__dots {
            position: absolute; bottom: 28px; left: 50%; transform: translateX(-50%);
            z-index: 20; display: flex; gap: 10px; align-items: center;
        }
        .hero-slider__dot {
            width: 8px; height: 8px; border-radius: 9999px;
            background: rgba(255,255,255,0.35); border: none; cursor: pointer;
            transition: width 0.35s, background-color 0.35s, box-shadow 0.35s; padding: 0;
        }
        .hero-slider__dot.is-active {
            width: 30px; background: white;
            box-shadow: 0 0 12px rgba(255,255,255,0.5);
        }

        /* Progress bar */
        .hero-slider__progress {
            position: absolute; bottom: 0; left: 0; height: 3px; z-index: 20;
            background: linear-gradient(90deg, #f59e0b, #fbbf24);
            transition: width 0.1s linear;
            box-shadow: 0 0 8px rgba(245,158,11,0.7);
        }

        .premium-filter {
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(24px);
            border: 1px solid rgba(245, 158, 11, 0.2);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .premium-filter label { color: #fbbf24 !important; font-weight: 800; }
        .premium-filter select, .premium-filter input { background: rgba(30, 41, 59, 0.5) !important; border-color: rgba(245, 158, 11, 0.15) !important; color: #fff !important; }
        .premium-filter select:focus, .premium-filter input:focus { border-color: #f59e0b !important; box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.15) !important; }
        @media (max-width: 767px) {
            #nav-menu {
                position: fixed;
                top: 80px;
                left: 0;
                right: 0;
                background: rgba(15, 23, 42, 0.98);
                backdrop-filter: blur(20px);
                flex-direction: column;
                padding: 2rem;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                z-index: 100;
                gap: 1.5rem;
                height: calc(100vh - 80px);
                overflow-y: auto;
            }
            #nav-menu.active {
                display: flex !important;
            }
            /* En el menú móvil, el menú de usuario se muestra como lista siempre
               abierta (el dropdown absoluto de escritorio no aplica aquí). */
            #user-menu { flex-direction: column; align-items: stretch; gap: .5rem; width: 100%; }
            #user-avatar-btn { justify-content: flex-start; width: 100%; }
            #user-caret { display: none; }
            #user-dropdown { display: block !important; position: static; width: 100%; box-shadow: none; background: rgba(255,255,255,.03); margin-top: 6px; }
            #auth-buttons { flex-direction: column; align-items: stretch; width: 100%; }
            .premium-filter {
                padding: 1.25rem !important;
                border-radius: 20px !important;
            }
            #search-section { padding-top: 2rem; padding-bottom: 2rem; }
        }
    </style>
</head>
<body class="bg-[#0f172a] text-slate-200">
    <a href="#rifas" class="skip-link">Saltar a las rifas</a>
    <header class="glass-nav sticky top-0 z-50 transition-all duration-300">
        <nav class="container mx-auto px-4 h-20 flex items-center justify-between">
            <a href="<?= BASE_PATH ?>/public/index.php" class="flex items-center gap-2.5 text-2xl font-black bg-clip-text text-transparent bg-gradient-to-r from-amber-300 to-amber-500">
                <svg class="w-7 h-7 text-amber-400 drop-shadow-lg" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8.5V6a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v2.5a1.5 1.5 0 0 0 0 3V14a1.5 1.5 0 0 0 0 3v2.5a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V17a1.5 1.5 0 0 0 0-3v-2.5a1.5 1.5 0 0 0 0-3Z"/>
                    <path stroke-linecap="round" d="M15 5v14" stroke-dasharray="2 3"/>
                </svg>
                MisRifas
            </a>

            <button id="mobile-menu-btn" class="md:hidden text-white p-2 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500" aria-label="Menu">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path>
                </svg>
            </button>

            <div class="hidden md:flex items-center gap-6" id="nav-menu">
                <a href="<?= BASE_PATH ?>/public/index.php" class="text-slate-300 hover:text-white font-medium transition-colors">Inicio</a>
                <a href="<?= BASE_PATH ?>/tapazo/index.php" class="flex items-center gap-1.5 text-slate-300 hover:text-white font-medium transition-colors">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3h11l-1 15a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 3Zm11 4h2.5a2 2 0 0 1 2 2.2l-.4 4A2 2 0 0 1 18.1 15H16"/></svg>
                    El Tapazo
                </a>
                <a href="<?= BASE_PATH ?>/public/mis-boletos.php" class="text-slate-300 hover:text-white font-medium transition-colors">Consultar Boletas</a>
                <a href="<?= BASE_PATH ?>/public/ganadores.php" class="flex items-center gap-1.5 text-slate-300 hover:text-white font-medium transition-colors">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 21h8M12 17v4M7 4h10v4a5 5 0 0 1-10 0V4Zm0 1H4.5a1.5 1.5 0 0 0 0 3H7M17 5h2.5a1.5 1.5 0 0 1 0 3H17"/></svg>
                    Ganadores
                </a>
                <a href="<?= BASE_PATH ?>/public/que-es.php" class="text-slate-300 hover:text-white font-medium transition-colors">¿Qué es MisRifas?</a>


                <div id="auth-buttons" class="flex items-center gap-4">
                    <a href="<?= BASE_PATH ?>/public/admin/index.php?auth=login" class="px-5 py-2.5 bg-white/5 border border-white/10 text-white rounded-xl hover:bg-white/10 active:scale-[0.97] transition-all font-medium backdrop-blur-sm shadow-lg shadow-black/20">Iniciar Sesión</a>
                    <a href="<?= BASE_PATH ?>/public/register.php" class="px-5 py-2.5 bg-gradient-to-r from-amber-400 to-amber-600 text-slate-950 rounded-xl hover:from-amber-300 hover:to-amber-500 active:scale-[0.97] transition-all font-bold shadow-lg shadow-amber-500/30">Crear Cuenta</a>
                </div>

                <div id="user-menu" class="hidden items-center gap-3" style="position:relative;">
                    <button id="user-avatar-btn" type="button" aria-haspopup="true" aria-expanded="false" class="flex items-center gap-2 group focus:outline-none" style="background:none;border:none;cursor:pointer;padding:0;">
                        <div class="w-10 h-10 rounded-full bg-amber-500 text-slate-950 flex items-center justify-center text-lg font-bold group-hover:scale-110 transition-transform" id="user-avatar">U</div>
                        <span class="text-slate-200 font-bold group-hover:text-white" id="user-name">Usuario</span>
                        <svg id="user-caret" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color:#94a3b8;transition:transform .2s;"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div id="user-dropdown" style="display:none;position:absolute;right:0;top:calc(100% + 10px);width:15rem;background:#1e293b;border:1px solid rgba(255,255,255,.1);border-radius:16px;box-shadow:0 20px 40px rgba(0,0,0,.5);padding:8px;z-index:60;">
                        <div style="padding:8px 12px 10px;border-bottom:1px solid rgba(255,255,255,.06);margin-bottom:6px;">
                            <p style="font-size:11px;color:#94a3b8;margin:0;">Sesión iniciada</p>
                            <p id="user-dd-name" style="font-size:14px;font-weight:700;color:#fff;margin:2px 0 0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">Usuario</p>
                        </div>
                        <a href="<?= BASE_PATH ?>/public/dashboard.php" class="udd-item">📊 Mi Panel</a>
                        <a href="<?= BASE_PATH ?>/public/mis-boletos.php" class="udd-item">🎟️ Mis Boletos</a>
                        <a href="<?= BASE_PATH ?>/public/ganadores.php" class="udd-item">🏆 Ganadores</a>
                        <a href="<?= BASE_PATH ?>/public/perfil.php" class="udd-item">⚙️ Configuración</a>
                        <button type="button" onclick="logout()" class="udd-item" style="color:#f87171;width:100%;text-align:left;background:none;border:none;cursor:pointer;">↪ Cerrar sesión</button>
                    </div>
                </div>
                <style>
                    .udd-item{display:block;padding:10px 12px;border-radius:10px;font-size:14px;color:#e2e8f0;text-decoration:none;font-weight:500;transition:background .15s;}
                    .udd-item:hover{background:rgba(255,255,255,.06);}
                    #user-avatar-btn[aria-expanded="true"] #user-caret{transform:rotate(180deg);}
                </style>
            </div>
        </nav>
    </header>


    <!-- ===== HERO SLIDER ===== -->
    <section class="hero-slider" id="heroSlider" aria-label="Banners promocionales">
        <div class="hero-slider__track" id="sliderTrack">

            <!-- Slide 1 -->
            <!-- TODO: reemplazar por foto real de un premio entregado -->
            <div class="hero-slide is-active">
                <div class="hero-slide__bg" style="background-image:url('https://picsum.photos/seed/misrifas-premio-mayor/1600/900');"></div>
                <div class="hero-slide__overlay"></div>
                <div class="hero-slide__content">
                    <span class="hero-slide__tag">Rifa destacada del mes</span>
                    <h1 class="hero-slide__title">Gana tu <em>gran premio</em> esta semana</h1>
                    <p class="hero-slide__desc">Boletos desde $5.000 COP. Sorteos verificados con lotería oficial.</p>
                    <div class="hero-slide__actions">
                        <a href="#rifas" class="hero-slide__btn hero-slide__btn--primary">Ver rifas activas</a>
                        <a href="<?= BASE_PATH ?>/public/vendor/index.php?auth=register" class="hero-slide__btn hero-slide__btn--ghost">Crear mi rifa</a>
                    </div>
                </div>
            </div>

            <!-- Slide 2 -->
            <!-- TODO: reemplazar por foto real del flujo de pago/entrega -->
            <div class="hero-slide">
                <div class="hero-slide__bg" style="background-image:url('https://picsum.photos/seed/misrifas-pago-nequi/1600/900');"></div>
                <div class="hero-slide__overlay"></div>
                <div class="hero-slide__content">
                    <span class="hero-slide__tag">Pago directo al organizador</span>
                    <h2 class="hero-slide__title">Paga con <em>Nequi</em> directo al organizador</h2>
                    <p class="hero-slide__desc">Transfieres directo al organizador, subes tu comprobante y tu número queda asegurado.</p>
                    <div class="hero-slide__actions">
                        <a href="#rifas" class="hero-slide__btn hero-slide__btn--primary">Ver rifas</a>
                        <a href="<?= BASE_PATH ?>/public/mis-boletos.php" class="hero-slide__btn hero-slide__btn--ghost">Consultar boletas</a>
                    </div>
                </div>
            </div>

            <!-- Slide 3 -->
            <!-- TODO: reemplazar por foto real de un carro/premio grande rifado -->
            <div class="hero-slide">
                <div class="hero-slide__bg" style="background-image:url('https://picsum.photos/seed/misrifas-carro-0km/1600/900');"></div>
                <div class="hero-slide__overlay"></div>
                <div class="hero-slide__content">
                    <span class="hero-slide__tag">Rifa de carros</span>
                    <h2 class="hero-slide__title">¿Y si hoy ganas un <em>carro 0km</em>?</h2>
                    <p class="hero-slide__desc">Carros, motos y electrodomésticos con sorteo vinculado a la Lotería Nacional.</p>
                    <div class="hero-slide__actions">
                        <a href="#rifas" class="hero-slide__btn hero-slide__btn--primary">Explorar rifas</a>
                        <a href="<?= BASE_PATH ?>/public/vendor/index.php?auth=register" class="hero-slide__btn hero-slide__btn--ghost">Vendo mis rifas</a>
                    </div>
                </div>
            </div>

            <!-- Slide 4 -->
            <!-- TODO: reemplazar por foto real de un equipo de tecnologia rifado -->
            <div class="hero-slide">
                <div class="hero-slide__bg" style="background-image:url('https://picsum.photos/seed/misrifas-tecnologia/1600/900');"></div>
                <div class="hero-slide__overlay"></div>
                <div class="hero-slide__content">
                    <span class="hero-slide__tag">Tecnología</span>
                    <h2 class="hero-slide__title">iPhone, MacBook y más <em>gadgets</em></h2>
                    <p class="hero-slide__desc">Comparte tu rifa en WhatsApp y tus amigos pueden ganar contigo.</p>
                    <div class="hero-slide__actions">
                        <a href="#rifas" class="hero-slide__btn hero-slide__btn--primary">Ver electrónicos</a>
                        <a href="<?= BASE_PATH ?>/public/admin/index.php?auth=login" class="hero-slide__btn hero-slide__btn--ghost">Iniciar sesión</a>
                    </div>
                </div>
            </div>

        </div><!-- /track -->

        <!-- Flechas -->
        <button class="hero-slider__arrow hero-slider__arrow--prev" id="sliderPrev" aria-label="Anterior">&#8592;</button>
        <button class="hero-slider__arrow hero-slider__arrow--next" id="sliderNext" aria-label="Siguiente">&#8594;</button>

        <!-- Dots -->
        <div class="hero-slider__dots" id="sliderDots"></div>

        <!-- Barra de progreso -->
        <div class="hero-slider__progress" id="sliderProgress"></div>

    </section><!-- /hero-slider -->

    <script>
    (function() {
        var AUTOPLAY_MS  = 5500;
        var TRANSITION_MS = 750;
        var track      = document.getElementById('sliderTrack');
        var slides     = Array.from(track.querySelectorAll('.hero-slide'));
        var total      = slides.length;
        var dotsEl     = document.getElementById('sliderDots');
        var thumbsEl   = document.getElementById('sliderThumbs');
        var progressEl = document.getElementById('sliderProgress');
        var prevBtn    = document.getElementById('sliderPrev');
        var nextBtn    = document.getElementById('sliderNext');
        var current    = 0;
        var autoTimer  = null;
        var busy       = false;

        /* Dots */
        slides.forEach(function(_, i) {
            var d = document.createElement('button');
            d.className = 'hero-slider__dot' + (i === 0 ? ' is-active' : '');
            d.setAttribute('aria-label', 'Slide ' + (i + 1));
            d.addEventListener('click', function() { goTo(i); restart(); });
            dotsEl.appendChild(d);
        });

        /* Thumb clicks */
        if (thumbsEl) {
            thumbsEl.querySelectorAll('.hero-slider__thumb').forEach(function(t, i) {
                t.addEventListener('click', function() { goTo(i); restart(); });
            });
        }

        function goTo(index) {
            if (busy || index === current) return;
            busy = true;
            slides[current].classList.remove('is-active');
            current = ((index % total) + total) % total;
            track.style.transform = 'translateX(-' + (current * 100) + '%)';
            slides[current].classList.add('is-active');
            dotsEl.querySelectorAll('.hero-slider__dot').forEach(function(d, i) { d.classList.toggle('is-active', i === current); });
            if (thumbsEl) thumbsEl.querySelectorAll('.hero-slider__thumb').forEach(function(t, i) { t.classList.toggle('is-active', i === current); });
            setTimeout(function() { busy = false; }, TRANSITION_MS);
            resetProgress();
        }

        prevBtn.addEventListener('click', function() { goTo(current - 1); restart(); });
        nextBtn.addEventListener('click', function() { goTo(current + 1); restart(); });

        /* Touch */
        var tx = 0;
        var sl = document.getElementById('heroSlider');
        sl.addEventListener('touchstart', function(e) { tx = e.touches[0].clientX; }, { passive: true });
        sl.addEventListener('touchend', function(e) {
            var dx = e.changedTouches[0].clientX - tx;
            if (Math.abs(dx) > 50) { goTo(dx < 0 ? current + 1 : current - 1); restart(); }
        }, { passive: true });

        /* Progress */
        function resetProgress() {
            progressEl.style.transition = 'none';
            progressEl.style.width = '0%';
            requestAnimationFrame(function() {
                requestAnimationFrame(function() {
                    progressEl.style.transition = 'width ' + AUTOPLAY_MS + 'ms linear';
                    progressEl.style.width = '100%';
                });
            });
        }

        /* Autoplay */
        function start() { autoTimer = setInterval(function() { goTo(current + 1); }, AUTOPLAY_MS); }
        function restart() { clearInterval(autoTimer); start(); }

        sl.addEventListener('mouseenter', function() { clearInterval(autoTimer); });
        sl.addEventListener('mouseleave', restart);
        document.addEventListener('keydown', function(e) {
            // No secuestrar las flechas cuando el usuario escribe o navega
            // dentro de un campo de formulario (buscador, selects de filtro).
            var tag = (e.target.tagName || '').toLowerCase();
            if (tag === 'input' || tag === 'select' || tag === 'textarea' || e.target.isContentEditable) return;
            if (e.key === 'ArrowLeft')  { goTo(current - 1); restart(); }
            if (e.key === 'ArrowRight') { goTo(current + 1); restart(); }
        });

        resetProgress();
        // Autoplay solo si el usuario no pidió movimiento reducido; las
        // flechas, dots y swipe siguen funcionando en ambos casos.
        var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (!reduceMotion) start();
        else progressEl.style.display = 'none';
    })();
    </script>



    <section id="search-section" class="py-12 border-b border-white/5 bg-slate-900/50">
        <div class="container mx-auto px-4 max-w-6xl">
            <div class="premium-filter rounded-[40px] p-8 md:p-12">
                <div class="flex flex-col lg:flex-row gap-6 mb-8">
                    <div class="flex-1 relative group">
                        <input type="text" id="search-input" name="search" aria-label="Buscar rifas" autocomplete="off" class="w-full pl-16 pr-8 py-5 bg-slate-950/40 border border-white/10 rounded-3xl text-xl outline-none focus:border-amber-500/50 transition-all placeholder-slate-500 text-white shadow-inner" placeholder="¿Qué quieres ganar hoy? (ej: Carro, Moto, iPhone…)">
                        <svg class="absolute left-6 top-1/2 -translate-y-1/2 w-6 h-6 text-slate-400 group-focus-within:text-amber-400 group-focus-within:scale-110 transition-all" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="space-y-2">
                        <label for="filter-dept" class="text-[11px] font-black text-amber-400 uppercase tracking-[0.2em] ml-2">Departamento</label>
                        <div class="relative">
                            <select id="filter-dept" class="w-full bg-slate-950/60 border border-white/10 rounded-2xl px-5 py-4 text-slate-200 outline-none focus:border-amber-500/50 transition-all appearance-none cursor-pointer pr-10">
                                <option value="">Selecciona Depto</option>
                            </select>
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-amber-400 text-xs">▼</span>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label for="filter-city" class="text-[11px] font-black text-amber-400 uppercase tracking-[0.2em] ml-2">Municipio / Ciudad</label>
                        <div class="relative">
                            <select id="filter-city" class="w-full bg-slate-950/60 border border-white/10 rounded-2xl px-5 py-4 text-slate-200 outline-none focus:border-amber-500/50 transition-all appearance-none cursor-pointer pr-10">
                                <option value="">Selecciona Ciudad</option>
                            </select>
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-amber-400 text-xs">▼</span>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <span id="price-range-label" class="text-[11px] font-black text-amber-400 uppercase tracking-[0.2em] ml-2 block">Precio Mín y Máx</span>
                        <div class="grid grid-cols-2 gap-2">
                            <select id="filter-min-price" aria-label="Precio mínimo" class="w-full bg-slate-950/60 border border-white/10 rounded-2xl px-3 py-4 text-slate-200 text-xs outline-none focus:border-amber-500/50 transition-all appearance-none cursor-pointer">
                                <option value="">Min</option>
                                <option value="1000">$1.000</option>
                                <option value="5000">$5.000</option>
                                <option value="10000">$10.000</option>
                                <option value="50000">$50.000</option>
                            </select>
                            <select id="filter-max-price" aria-label="Precio máximo" class="w-full bg-slate-950/60 border border-white/10 rounded-2xl px-3 py-4 text-slate-200 text-xs outline-none focus:border-amber-500/50 transition-all appearance-none cursor-pointer">
                                <option value="">Max</option>
                                <option value="5000">$5.000</option>
                                <option value="10000">$10.000</option>
                                <option value="20000">$20.000</option>
                                <option value="50000">$50.000</option>
                                <option value="100000">$100.000</option>
                                <option value="500000">$500.000</option>
                            </select>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label for="filter-lottery" class="text-[11px] font-black text-amber-400 uppercase tracking-[0.2em] ml-2">Lotería</label>
                        <div class="relative">
                            <select id="filter-lottery" class="w-full bg-slate-950/60 border border-white/10 rounded-2xl px-5 py-4 text-slate-200 outline-none focus:border-amber-500/50 transition-all appearance-none cursor-pointer pr-10">
                                <option value="">Selecciona Lotería</option>
                            </select>
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-amber-400 text-xs">▼</span>
                        </div>
                    </div>
                    <div class="space-y-2 flex flex-col justify-end">
                        <div class="flex gap-2">
                            <button onclick="handleFilter()" class="flex-1 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black uppercase tracking-widest text-[11px] h-[55px] rounded-2xl shadow-xl shadow-amber-500/20 transition-all hover:-translate-y-0.5 active:scale-95 flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg> Buscar
                            </button>
                            <button onclick="clearFilters()" class="px-6 py-3 bg-gradient-to-r from-slate-600 to-slate-700 hover:from-slate-500 hover:to-slate-600 text-white font-bold uppercase tracking-widest text-[10px] rounded-2xl transition-all flex items-center justify-center gap-2 h-[55px]">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M4.5 9a8 8 0 0 1 14.3-3.5M19.5 15a8 8 0 0 1-14.3 3.5"/></svg> Limpiar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-8">
        <div class="container mx-auto px-4 max-w-7xl">
            <div class="flex flex-wrap gap-3 justify-center">
                <button class="tab active px-6 py-2.5 rounded-xl font-semibold shadow-lg" data-tab="destacadas">Destacadas</button>
                <button class="tab px-6 py-2.5 rounded-xl font-semibold shadow-lg" data-tab="populares">Top ventas</button>
                <button class="tab px-6 py-2.5 rounded-xl font-semibold shadow-lg" data-tab="proximas">Cierran pronto</button>
                <button class="tab px-6 py-2.5 rounded-xl font-semibold shadow-lg" data-tab="nuevas">Recientes</button>
            </div>
        </div>
    </section>

    <section id="rifas" class="py-12 min-h-[500px]">
        <div class="container mx-auto px-4 max-w-7xl">
            <div id="raffles-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
                <!-- Skeletons go here while loading -->
            </div>
        </div>
    </section>

    <section class="py-32 bg-slate-900 border-t border-slate-800/60">
        <div class="container mx-auto px-4">
            <div class="text-center mb-20">
                <h2 class="text-4xl md:text-5xl font-bold tracking-tight mb-4 text-white">¿Cómo funciona?</h2>
                <p class="text-slate-400 text-lg">Un proceso transparente, conectado a tu WhatsApp.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-16 max-w-6xl mx-auto">
                <div class="text-center">
                    <div class="text-6xl font-bold text-amber-500/25 mb-4 tracking-tight">01</div>
                    <h3 class="text-lg font-bold mb-2 text-white">Elige Rifa</h3>
                    <p class="text-slate-400 text-sm leading-relaxed">Encuentra una rifa verificada de nuestra red nacional.</p>
                </div>
                <div class="text-center">
                    <div class="text-6xl font-bold text-amber-500/25 mb-4 tracking-tight">02</div>
                    <h3 class="text-lg font-bold mb-2 text-white">Toma un Cupo</h3>
                    <p class="text-slate-400 text-sm leading-relaxed">Los tickets bloquean dobles compras gracias a nuestra concurrencia estricta.</p>
                </div>
                <div class="text-center">
                    <div class="text-6xl font-bold text-amber-500/25 mb-4 tracking-tight">03</div>
                    <h3 class="text-lg font-bold mb-2 text-white">Pago Seguro</h3>
                    <p class="text-slate-400 text-sm leading-relaxed">Transfieres directo al organizador y él confirma tu pago; tu boleta digital queda emitida.</p>
                </div>
                <div class="text-center">
                    <div class="text-6xl font-bold text-amber-500/25 mb-4 tracking-tight">04</div>
                    <h3 class="text-lg font-bold mb-2 text-white">Lotería en Vivo</h3>
                    <p class="text-slate-400 text-sm leading-relaxed">Te notificamos si tu número ganó apenas se conoce el resultado.</p>
                </div>
            </div>
        </div>
    </section>

    <footer class="bg-slate-950 border-t border-white/5 text-white pt-16 pb-8">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-10 pb-12">
                <div>
                    <div class="flex items-center gap-2.5 text-xl font-black bg-clip-text text-transparent bg-gradient-to-r from-amber-300 to-amber-500 mb-3">
                        <svg class="w-6 h-6 text-amber-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8.5V6a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v2.5a1.5 1.5 0 0 0 0 3V14a1.5 1.5 0 0 0 0 3v2.5a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V17a1.5 1.5 0 0 0 0-3v-2.5a1.5 1.5 0 0 0 0-3Z"/>
                        </svg>
                        MisRifas
                    </div>
                    <p class="text-sm text-slate-400 leading-relaxed">Rifas digitales verificadas con lotería oficial colombiana.</p>
                </div>
                <div>
                    <h4 class="text-xs font-black uppercase tracking-widest text-slate-500 mb-4">Explorar</h4>
                    <ul class="space-y-2.5 text-sm">
                        <li><a href="<?= BASE_PATH ?>/public/index.php" class="text-slate-400 hover:text-amber-400 transition-colors">Inicio</a></li>
                        <li><a href="<?= BASE_PATH ?>/public/mis-boletos.php" class="text-slate-400 hover:text-amber-400 transition-colors">Consultar boletas</a></li>
                        <li><a href="<?= BASE_PATH ?>/public/ganadores.php" class="text-slate-400 hover:text-amber-400 transition-colors">Ganadores</a></li>
                        <li><a href="<?= BASE_PATH ?>/public/que-es.php" class="text-slate-400 hover:text-amber-400 transition-colors">¿Qué es MisRifas?</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-xs font-black uppercase tracking-widest text-slate-500 mb-4">Cuenta</h4>
                    <ul class="space-y-2.5 text-sm">
                        <li><a href="<?= BASE_PATH ?>/public/register.php" class="text-slate-400 hover:text-amber-400 transition-colors">Crear cuenta</a></li>
                        <li><a href="<?= BASE_PATH ?>/public/admin/index.php?auth=login" class="text-slate-400 hover:text-amber-400 transition-colors">Iniciar sesión</a></li>
                        <li><a href="<?= BASE_PATH ?>/public/vendor/index.php?auth=register" class="text-slate-400 hover:text-amber-400 transition-colors">Vender mis rifas</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-xs font-black uppercase tracking-widest text-slate-500 mb-4">Pagos aceptados</h4>
                    <div class="flex flex-wrap gap-2">
                        <span class="px-3 py-1.5 bg-white/5 border border-white/10 rounded-lg text-xs font-bold text-slate-300">Nequi</span>
                        <span class="px-3 py-1.5 bg-white/5 border border-white/10 rounded-lg text-xs font-bold text-slate-300">DaviPlata</span>
                        <span class="px-3 py-1.5 bg-white/5 border border-white/10 rounded-lg text-xs font-bold text-slate-300">Bre-B</span>
                    </div>
                </div>
            </div>
            <div class="pt-8 border-t border-white/5 text-center">
                <p class="text-sm text-slate-500">&copy; 2026 MisRifas Colombia. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>

    <script>
    // API Client
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

    // Utils
    const Utils = {
        formatPrice(p) { return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 }).format(p); },
        formatDate(d) { return new Intl.DateTimeFormat('es-CO', { year: 'numeric', month: 'long', day: 'numeric' }).format(new Date(d)); },
        showNotification(msg, type = 'info') {
            const n = document.createElement('div');
            n.className = 'notification notification--' + type;
            n.setAttribute('role', 'status');
            n.setAttribute('aria-live', 'polite');
            n.textContent = msg;
            document.body.appendChild(n);
            setTimeout(() => n.remove(), 3000);
        },
        esc(s) {
            return String(s ?? '').replace(/[&<>"']/g, c =>
                ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        },
        fixUrl(url) {
            // image_url es relativa a public/ (subida real via Uploader::upload()
            // no trae slash inicial; el default hardcodeado en create.php si trae
            // uno) - ninguna de las dos formas incluye "public/", que es donde el
            // archivo vive de verdad. Sin BASE_PATH + "/public/" el <img> apuntaba
            // a la raiz del dominio/subcarpeta y daba 404 en cualquier deploy que
            // no sea la raiz del dominio.
            if (!url) return BASE_PATH + '/public/assets/images/placeholder.svg';
            if (url.startsWith('http')) return url;
            return BASE_PATH + '/public/' + url.replace(/^\/?(public\/)?/, '');
        }
    };

    function toggleLoading(isLoading) {
        const container = document.getElementById('raffles-container');
        if (isLoading) {
            container.innerHTML = Array(4).fill(0).map(() => `
                <div class="raffle-card animate-pulse">
                    <div class="raffle-card__image bg-slate-800 h-[220px]"></div>
                    <div class="raffle-card__content space-y-4">
                        <div class="h-6 bg-slate-800 rounded w-3/4"></div>
                        <div class="h-4 bg-slate-800 rounded w-1/2"></div>
                        <div class="h-10 bg-slate-800 rounded"></div>
                    </div>
                </div>
            `).join('');
        }
    }

    async function loadLotteries() {
        try {
            const response = await API.get('/lotteries/index.php');
            if (response.success) {
                const select = document.getElementById('filter-lottery');
                const options = response.data.map(l => `<option value="${l.id}">${Utils.esc(l.name)}</option>`).join('');
                select.innerHTML = '<option value="">Selecciona Lotería</option>' + options;
                const preLottery = new URLSearchParams(location.search).get('lottery_id');
                if (preLottery) select.value = preLottery;
            }
        } catch (e) {
            console.error('Error loading lotteries:', e);
        }
    }

    const Raffles = {
        async loadRaffles(filters = {}) {
            toggleLoading(true);
            try {
                const response = await API.get('/raffles/index.php', filters);
                if (response.success) {
                    const raffles = response.data.raffles || [];
                    const container = document.getElementById('raffles-container');
                    if (!container) return;

                    if (raffles.length === 0) {
                        container.innerHTML = `
                            <div class="col-span-full py-20 text-center">
                                <svg class="w-16 h-16 mx-auto mb-6 text-slate-600" fill="none" stroke="currentColor" stroke-width="1.25" viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="6.5"/><path stroke-linecap="round" d="m20 20-4.35-4.35"/></svg>
                                <h3 class="text-2xl font-black text-white mb-2">No encontramos resultados</h3>
                                <p class="text-slate-400">Intenta ajustar los filtros de búsqueda.</p>
                            </div>
                        `;
                        return;
                    }

                    container.innerHTML = raffles.map(r => `
                        <div class="raffle-card group" data-id="${r.id}">
                            <div class="raffle-card__image">
                                <img src="${Utils.fixUrl(r.image_url)}" alt="${Utils.esc(r.name)}" width="400" height="220" loading="lazy" class="group-hover:scale-110 transition-transform duration-500">
                                <span class="raffle-card__badge">${r.sold_percentage || 0}% vendido</span>
                                <div class="absolute bottom-0 left-0 right-0 h-1/2 bg-gradient-to-t from-slate-900 to-transparent"></div>
                            </div>
                            <div class="raffle-card__content">
                                <h3 class="raffle-card__title group-hover:text-amber-400 transition-colors">${Utils.esc(r.name)}</h3>
                                <p class="raffle-card__city flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 shrink-0 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-6.5 7-11.5a7 7 0 1 0-14 0C5 14.5 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.25"/></svg>
                                    ${Utils.esc(r.city)}${r.department ? ', ' + Utils.esc(r.department) : ''}
                                </p>
                                <div class="raffle-card__info">
                                    <div class="raffle-card__price">${Utils.formatPrice(r.ticket_price)}<span>por boleto</span></div>
                                    <div class="raffle-card__date">${Utils.formatDate(r.draw_date)}</div>
                                </div>
                                <div class="progress-bar group-hover:h-2 transition-all"><div class="progress-bar__fill" style="width: ${r.sold_percentage}%"></div></div>
                                <div class="flex justify-between items-center mt-2">
                                    <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest">${r.sold_tickets} / ${r.total_tickets} Vendidos</span>
                                    <span class="text-[10px] font-black text-amber-500 uppercase tracking-widest">${(() => { const dd = Math.max(0, Math.floor((new Date(r.draw_date) - new Date()) / (1000 * 60 * 60 * 24))); return dd + (dd === 1 ? ' Día restante' : ' Días restantes'); })()}</span>
                                </div>
                                <a href="${BASE_PATH}/public/raffle.php?id=${r.id}" class="btn btn--primary w-full mt-6 shadow-amber-500/20 group-hover:shadow-amber-500/40 group-hover:-translate-y-0.5 transition-all">Participar Ahora &rarr;</a>
                            </div>
                        </div>
                    `).join('');
                    revealRaffleCards();
                }
            } catch (e) {
                console.error('Error:', e);
                Utils.showNotification('Error al cargar las rifas', 'error');
            }
        }
    };

    // Revela cada tarjeta de rifa (opacity) cuando entra al viewport - la
    // motivacion es dar jerarquia al scroll del catalogo, no decoracion:
    // sin esto todo el grid aparece de golpe. No usa scroll listeners
    // (banned - ver skill de diseno), IntersectionObserver nativo en su lugar.
    function revealRaffleCards() {
        const cards = document.querySelectorAll('#raffles-container .raffle-card:not(.animate-pulse)');
        if (!('IntersectionObserver' in window)) {
            cards.forEach(c => c.classList.add('is-visible'));
            return;
        }
        const io = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });
        cards.forEach(c => io.observe(c));
    }

    const TAB_ORDER_MAP = {
        populares: 'sold_percentage',
        proximas: 'draw_date',
        nuevas: 'created_at',
        destacadas: 'views'
    };

    function getActiveFilters() {
        const activeTab = document.querySelector('.tab.active');
        return {
            search: document.getElementById('search-input')?.value || '',
            department: document.getElementById('filter-dept')?.value || '',
            city: document.getElementById('filter-city')?.value || '',
            min_price: document.getElementById('filter-min-price')?.value || '',
            max_price: document.getElementById('filter-max-price')?.value || '',
            lottery_id: document.getElementById('filter-lottery')?.value || '',
            order_by: TAB_ORDER_MAP[activeTab?.dataset.tab] || 'views'
        };
    }

    // Refleja filtros y tab en la URL para que el estado sea compartible
    // y sobreviva un refresh. replaceState para no ensuciar el historial.
    function syncUrlWithFilters(filters) {
        const params = new URLSearchParams();
        Object.entries(filters).forEach(([k, v]) => {
            if (v && !(k === 'order_by' && v === 'views')) params.set(k, v);
        });
        const qs = params.toString();
        history.replaceState(null, '', qs ? '?' + qs : window.location.pathname);
    }

    let colombiaData = [];
    async function loadGeography() {
        try {
            const res = await fetch(`${BASE_PATH}/public/assets/data/colombia.json`);
            colombiaData = await res.json();

            const deptSelect = document.getElementById('filter-dept');
            if (!deptSelect) return;

            deptSelect.innerHTML = '<option value="">Selecciona Depto</option>' +
                colombiaData.map(d => `<option value="${d.departamento}">${d.departamento}</option>`).join('');

            deptSelect.addEventListener('change', (e) => {
                const dept = colombiaData.find(d => d.departamento === e.target.value);
                const citySelect = document.getElementById('filter-city');
                if (dept) {
                    citySelect.innerHTML = '<option value="">Selecciona Ciudad</option>' +
                        dept.ciudades.map(c => `<option value="${c}">${c}</option>`).join('');
                    citySelect.disabled = false;
                } else {
                    citySelect.innerHTML = '<option value="">Primero selecciona un departamento</option>';
                    citySelect.disabled = true;
                }
            });

            // Restaurar depto/ciudad desde la URL (deep-link de filtros)
            const params = new URLSearchParams(location.search);
            const preDept = params.get('department');
            if (preDept) {
                deptSelect.value = preDept;
                const dept = colombiaData.find(d => d.departamento === preDept);
                const citySelect = document.getElementById('filter-city');
                if (dept && citySelect) {
                    citySelect.innerHTML = '<option value="">Selecciona Ciudad</option>' +
                        dept.ciudades.map(c => `<option value="${c}">${c}</option>`).join('');
                    citySelect.disabled = false;
                    const preCity = params.get('city');
                    if (preCity) citySelect.value = preCity;
                }
            }
        } catch (e) {
            console.error('Error loading geography:', e);
        }
    }

    // FUNCIONES GLOBALES para onclick handlers
    let debounceTimer;
    function handleFilter() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            const filters = getActiveFilters();
            syncUrlWithFilters(filters);
            Raffles.loadRaffles(filters);
        }, 400);
    }

    function clearFilters() {
        document.getElementById('search-input').value = '';
        document.getElementById('filter-dept').value = '';
        document.getElementById('filter-city').value = '';
        document.getElementById('filter-min-price').value = '';
        document.getElementById('filter-max-price').value = '';
        document.getElementById('filter-lottery').value = '';
        const citySelect = document.getElementById('filter-city');
        citySelect.innerHTML = '<option value="">Primero selecciona un departamento</option>';
        citySelect.disabled = true;
        syncUrlWithFilters({});
        Raffles.loadRaffles();
    }

    function logout() {
        localStorage.removeItem('misrifas_token');
        localStorage.removeItem('misrifas_user');
        window.location.reload();
    }

    function toggleUserDropdown(force) {
        const dd = document.getElementById('user-dropdown');
        const btn = document.getElementById('user-avatar-btn');
        if (!dd || !btn) return;
        const show = (force === undefined) ? (dd.style.display === 'none' || !dd.style.display) : force;
        dd.style.display = show ? 'block' : 'none';
        btn.setAttribute('aria-expanded', show ? 'true' : 'false');
    }

    function checkAuth() {
        const userStr = localStorage.getItem('misrifas_user');
        if (userStr) {
            try {
                const user = JSON.parse(userStr);
                const name = user.full_name || user.name || user.email || 'Usuario';
                document.getElementById('auth-buttons').classList.add('hidden');
                document.getElementById('user-menu').classList.remove('hidden');
                document.getElementById('user-menu').classList.add('flex');
                document.getElementById('user-name').textContent = name;
                document.getElementById('user-dd-name').textContent = name;
                document.getElementById('user-avatar').textContent = name.charAt(0).toUpperCase();

                // Menú desplegable del avatar (antes solo abría el perfil).
                const btn = document.getElementById('user-avatar-btn');
                btn.addEventListener('click', (e) => { e.stopPropagation(); toggleUserDropdown(); });
                document.addEventListener('click', () => toggleUserDropdown(false));
                document.getElementById('user-dropdown').addEventListener('click', (e) => e.stopPropagation());
            } catch (e) {
                console.error('Error parsing user data:', e);
            }
        }
    }

    // Inicialización
    document.addEventListener('DOMContentLoaded', () => {
        checkAuth();

        // Restaurar filtros y tab desde la URL antes de la primera carga
        const initialParams = new URLSearchParams(location.search);
        ['search-input:search', 'filter-min-price:min_price', 'filter-max-price:max_price'].forEach(pair => {
            const [id, key] = pair.split(':');
            const el = document.getElementById(id);
            if (el && initialParams.get(key)) el.value = initialParams.get(key);
        });
        const preOrder = initialParams.get('order_by');
        if (preOrder) {
            const tabKey = Object.keys(TAB_ORDER_MAP).find(k => TAB_ORDER_MAP[k] === preOrder);
            if (tabKey) {
                document.querySelectorAll('.tab').forEach(t => t.classList.toggle('active', t.dataset.tab === tabKey));
            }
        }

        loadLotteries();
        loadGeography();
        Raffles.loadRaffles(initialParams.size ? Object.fromEntries(initialParams) : {});

        // Mobile Menu Toggle
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const navMenu = document.getElementById('nav-menu');
        if (mobileMenuBtn && navMenu) {
            mobileMenuBtn.addEventListener('click', () => {
                navMenu.classList.toggle('active');
                navMenu.classList.toggle('hidden');
            });
        }

        // Event listeners
        const searchInput = document.getElementById('search-input');
        if (searchInput) {
            searchInput.addEventListener('input', handleFilter);
        }

        const citySelect = document.getElementById('filter-city');
        if (citySelect) {
            citySelect.addEventListener('change', handleFilter);
        }

        ['filter-lottery', 'filter-min-price', 'filter-max-price'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', handleFilter);
            }
        });

        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                const filters = getActiveFilters();
                syncUrlWithFilters(filters);
                Raffles.loadRaffles(filters);
            });
        });
    });
    </script>
</body>
</html>
