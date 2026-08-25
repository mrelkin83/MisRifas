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
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT: '#2563eb', dark: '#1e40af', light: '#3b82f6' }
                    }
                }
            }
        }
    </script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background: #0f172a; color: #f8fafc; }
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
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .raffle-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px -10px rgba(59,130,246,0.3);
            border: 1px solid rgba(59,130,246,0.3);
        }
        .raffle-card__image { position: relative; width: 100%; height: 220px; overflow: hidden; }
        .raffle-card__image img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
        .raffle-card:hover .raffle-card__image img { transform: scale(1.1); }
        .raffle-card__badge { position: absolute; top: 12px; right: 12px; padding: 6px 14px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border-radius: 9999px; font-size: 12px; font-weight: 700; box-shadow: 0 4px 10px rgba(16,185,129,0.3); }
        .raffle-card__content { padding: 24px; }
        .raffle-card__title { font-size: 22px; font-weight: 700; margin-bottom: 8px; color: #f8fafc; }
        .raffle-card__city { font-size: 14px; color: #94a3b8; margin-bottom: 16px; }
        .raffle-card__info { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .raffle-card__price { font-size: 26px; font-weight: 800; background: linear-gradient(to right, #60a5fa, #3b82f6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .raffle-card__price span { display: block; font-size: 12px; font-weight: 500; color: #94a3b8; -webkit-text-fill-color: initial; }
        .raffle-card__date { font-size: 14px; color: #cbd5e1; text-align: right; }
        .progress-bar { width: 100%; height: 6px; background: rgba(255,255,255,0.1); border-radius: 9999px; overflow: hidden; margin-bottom: 8px; }
        .progress-bar__fill { height: 100%; background: linear-gradient(90deg, #3b82f6, #10b981); border-radius: 9999px; transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1); }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 12px 24px; font-size: 16px; font-weight: 600; border-radius: 12px; cursor: pointer; border: none; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .btn--primary { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; box-shadow: 0 4px 15px rgba(37,99,235,0.4); }
        .btn--primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(37,99,235,0.6); }
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
        input[type="text"] { background: rgba(30,41,59,0.8); border: 1px solid rgba(255,255,255,0.1); color: white; transition: all 0.3s; }
        input[type="text"]:focus { border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59,130,246,0.2); }
        .tab { background: rgba(30,41,59,0.6); color: #cbd5e1; border: 1px solid rgba(255,255,255,0.05); transition: all 0.3s; }
        .tab:hover { background: rgba(51,65,85,0.8); color: white; }
        .tab.active { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border-color: transparent; box-shadow: 0 4px 15px rgba(37,99,235,0.4); }

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
            background: linear-gradient(135deg, rgba(0,0,0,0.72) 0%, rgba(0,0,0,0.2) 60%, rgba(0,0,0,0.5) 100%);
        }
        .hero-slide__content {
            position: relative; z-index: 10; text-align: left;
            max-width: 650px; padding: 0 40px;
            opacity: 0; transform: translateY(40px);
            transition: opacity 0.7s 0.15s ease, transform 0.7s 0.15s ease;
        }
        @media (max-width: 640px) {
            .hero-slide__content { padding: 0 24px; }
        }
        .hero-slide.is-active .hero-slide__content { opacity: 1; transform: translateY(0); }

        .hero-slide__tag {
            display: inline-block; padding: 6px 16px; border-radius: 9999px;
            font-size: 12px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase;
            background: rgba(255,255,255,0.12); backdrop-filter: blur(6px);
            border: 1px solid rgba(255,255,255,0.2); color: #e2e8f0;
            margin-bottom: 20px;
        }
        .hero-slide__title {
            font-size: clamp(1.75rem, 8vw, 3.5rem); font-weight: 900;
            line-height: 1.1; color: #fff; margin-bottom: 18px;
            text-shadow: 0 2px 20px rgba(0,0,0,0.4);
        }
        @media (max-width: 640px) {
            .hero-slide__title { margin-bottom: 12px; }
            .hero-slide__desc { font-size: 1rem !important; margin-bottom: 24px !important; }
        }
        .hero-slide__title em {
            font-style: normal;
            background: linear-gradient(90deg, #60a5fa, #34d399);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .hero-slide__desc {
            font-size: 1.1rem; color: rgba(255,255,255,0.8);
            margin-bottom: 32px; line-height: 1.6; max-width: 480px;
        }
        .hero-slide__actions { display: flex; gap: 14px; flex-wrap: wrap; }
        .hero-slide__btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 14px 30px; border-radius: 14px; font-size: 16px; font-weight: 700;
            cursor: pointer; border: none; text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
        }
        .hero-slide__btn--primary {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: white; box-shadow: 0 8px 30px rgba(37,99,235,0.5);
        }
        .hero-slide__btn--primary:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 14px 40px rgba(37,99,235,0.65);
        }
        .hero-slide__btn--ghost {
            background: rgba(255,255,255,0.1); color: white;
            border: 1.5px solid rgba(255,255,255,0.25); backdrop-filter: blur(6px);
        }
        .hero-slide__btn--ghost:hover { background: rgba(255,255,255,0.18); transform: translateY(-2px); }

        /* Arrows */
        .hero-slider__arrow {
            position: absolute; top: 50%; transform: translateY(-50%); z-index: 20;
            width: 52px; height: 52px; border-radius: 50%;
            background: rgba(255,255,255,0.08); border: 1.5px solid rgba(255,255,255,0.15);
            backdrop-filter: blur(8px); color: white; font-size: 20px;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            transition: all 0.2s; user-select: none;
        }
        .hero-slider__arrow:hover { background: rgba(255,255,255,0.18); transform: translateY(-50%) scale(1.08); }
        .hero-slider__arrow--prev { left: 24px; }
        .hero-slider__arrow--next { right: 24px; }
        @media (max-width: 640px) {
            .hero-slider__arrow { width: 40px; height: 40px; font-size: 16px; }
            .hero-slider__arrow--prev { left: 12px; }
            .hero-slider__arrow--next { right: 12px; }
        }

        /* Dots */
        .hero-slider__dots {
            position: absolute; bottom: 28px; left: 50%; transform: translateX(-50%);
            z-index: 20; display: flex; gap: 10px; align-items: center;
        }
        .hero-slider__dot {
            width: 8px; height: 8px; border-radius: 9999px;
            background: rgba(255,255,255,0.35); border: none; cursor: pointer;
            transition: all 0.35s; padding: 0;
        }
        .hero-slider__dot.is-active {
            width: 30px; background: white;
            box-shadow: 0 0 12px rgba(255,255,255,0.5);
        }

        /* Progress bar */
        .hero-slider__progress {
            position: absolute; bottom: 0; left: 0; height: 3px; z-index: 20;
            background: linear-gradient(90deg, #3b82f6, #10b981);
            transition: width 0.1s linear;
            box-shadow: 0 0 8px rgba(59,130,246,0.7);
        }

        /* Thumbnails strip */
        .hero-slider__thumbs {
            position: absolute; bottom: 60px; right: 28px; z-index: 20;
            display: flex; gap: 10px;
        }
        .hero-slider__thumb {
            width: 72px; height: 48px; border-radius: 8px; overflow: hidden;
            cursor: pointer; border: 2px solid rgba(255,255,255,0.15);
            opacity: 0.5; transition: all 0.25s;
        }
        .hero-slider__thumb.is-active { opacity: 1; border-color: white; transform: scale(1.08); }
        .hero-slider__thumb img { width: 100%; height: 100%; object-fit: cover; }
        @media (max-width: 768px) { .hero-slider__thumbs { display: none; } }
        .premium-filter {
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(24px);
            border: 1px solid rgba(59, 130, 246, 0.2);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .premium-filter label { color: #60a5fa !important; font-weight: 800; }
        .premium-filter select, .premium-filter input { background: rgba(30, 41, 59, 0.5) !important; border-color: rgba(59, 130, 246, 0.1) !important; color: #fff !important; }
        .premium-filter select:focus, .premium-filter input:focus { border-color: #3b82f6 !important; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important; }
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
            .premium-filter {
                padding: 1.25rem !important;
                border-radius: 20px !important;
            }
            #search-section { padding-top: 2rem; padding-bottom: 2rem; }
        }
    </style>
</head>
<body class="bg-[#0f172a] text-slate-200">
    <header class="glass-nav sticky top-0 z-50 transition-all duration-300">
        <nav class="container mx-auto px-4 h-20 flex items-center justify-between">
            <a href="<?= BASE_PATH ?>/public/index.php" class="flex items-center gap-3 text-2xl font-black bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-emerald-400">
                <span class="text-3xl drop-shadow-lg">🎟️</span>
                MisRifas
            </a>
            
            <button id="mobile-menu-btn" class="md:hidden text-white p-2 focus:outline-none" aria-label="Menu">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path>
                </svg>
            </button>

            <div class="hidden md:flex items-center gap-6" id="nav-menu">
                <a href="<?= BASE_PATH ?>/public/index.php" class="text-slate-300 hover:text-white font-medium transition-colors">Inicio</a>
                <a href="<?= BASE_PATH ?>/tapazo/index.php" class="text-slate-300 hover:text-white font-medium transition-colors">🍺 El Tapazo</a>
                <a href="<?= BASE_PATH ?>/public/mis-boletos.php" class="text-slate-300 hover:text-white font-medium transition-colors">Consultar Boletas</a>
                <a href="<?= BASE_PATH ?>/public/ganadores.php" class="text-slate-300 hover:text-white font-medium transition-colors">🏆 Ganadores</a>
                <a href="<?= BASE_PATH ?>/public/que-es.php" class="text-slate-300 hover:text-white font-medium transition-colors">✨ ¿Qué es MisRifas?</a>


                <div id="auth-buttons" class="flex items-center gap-4">
                    <a href="<?= BASE_PATH ?>/public/admin/index.php?auth=login" class="px-5 py-2.5 bg-white/5 border border-white/10 text-white rounded-xl hover:bg-white/10 transition-all font-medium backdrop-blur-sm shadow-lg shadow-black/20">Iniciar Sesión</a>
                    <a href="<?= BASE_PATH ?>/public/register.php" class="px-5 py-2.5 bg-gradient-to-r from-blue-600 to-emerald-600 text-white rounded-xl hover:from-blue-500 hover:to-emerald-500 transition-all font-bold shadow-lg shadow-blue-500/30">Crear Cuenta</a>
                </div>

                <div id="user-menu" class="hidden flex items-center gap-4">
                    <a href="<?= BASE_PATH ?>/public/perfil.php" class="flex items-center gap-2 group">
                        <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center text-lg font-bold group-hover:scale-110 transition-transform" id="user-avatar">U</div>
                        <span class="text-slate-200 font-bold group-hover:text-white" id="user-name">Usuario</span>
                    </a>
                    <button onclick="logout()" class="p-2.5 bg-white/5 border border-white/10 text-slate-400 rounded-xl hover:text-red-400 hover:bg-red-500/10 transition-all" title="Cerrar Sesión">
                        🚪
                    </button>
                </div>
            </div>
        </nav>
    </header>


    <!-- ===== HERO SLIDER ===== -->
    <section class="hero-slider" id="heroSlider" aria-label="Banners promocionales">
        <div class="hero-slider__track" id="sliderTrack">

            <!-- Slide 1 -->
            <div class="hero-slide is-active">
                <div class="hero-slide__bg" style="background-image:url('https://images.unsplash.com/photo-1592921870789-04563d55041c?w=1600&q=80');"></div>
                <div class="hero-slide__overlay"></div>
                <div class="hero-slide__content">
                    <span class="hero-slide__tag">&#127903;&#65039; Rifa Destacada del Mes</span>
                    <h1 class="hero-slide__title">Gana tu <em>Gran Premio</em><br>esta semana</h1>
                    <p class="hero-slide__desc">Participa en las rifas m&aacute;s grandes de Colombia. Boletos desde $5.000 COP. Sorteos verificados con loter&iacute;a oficial.</p>
                    <div class="hero-slide__actions">
                        <a href="#rifas" class="hero-slide__btn hero-slide__btn--primary">&#127919; Ver Rifas Activas</a>
                        <a href="<?= BASE_PATH ?>/public/register.php" class="hero-slide__btn hero-slide__btn--ghost">&#128640; Crear mi Rifa</a>
                    </div>
                </div>
            </div>

            <!-- Slide 2 -->
            <div class="hero-slide">
                <div class="hero-slide__bg" style="background-image:url('https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=1600&q=80');"></div>
                <div class="hero-slide__overlay" style="background:linear-gradient(135deg,rgba(5,46,22,0.8) 0%,rgba(0,0,0,0.2) 60%,rgba(0,0,0,0.5) 100%);"></div>
                <div class="hero-slide__content">
                    <span class="hero-slide__tag">&#128176; Pagos Instant&aacute;neos</span>
                    <h1 class="hero-slide__title">Paga con <em>Nequi</em><br>y gana al instante</h1>
                    <p class="hero-slide__desc">Integraci&oacute;n directa con Wompi. Tu pago se confirma en segundos y tu boleto queda asegurado sin llamadas ni esperas.</p>
                    <div class="hero-slide__actions">
                        <a href="#rifas" class="hero-slide__btn hero-slide__btn--primary" style="background:linear-gradient(135deg,#059669,#10b981);box-shadow:0 8px 30px rgba(16,185,129,0.5);">Ver Rifas &rarr;</a>
                        <a href="<?= BASE_PATH ?>/public/mis-boletos.php" class="hero-slide__btn hero-slide__btn--ghost">Consultar Boletas</a>
                    </div>
                </div>
            </div>

            <!-- Slide 3 -->
            <div class="hero-slide">
                <div class="hero-slide__bg" style="background-image:url('https://images.unsplash.com/photo-1547036967-23d11aacaee0?w=1600&q=80');"></div>
                <div class="hero-slide__overlay" style="background:linear-gradient(135deg,rgba(67,20,120,0.85) 0%,rgba(0,0,0,0.15) 60%,rgba(0,0,0,0.4) 100%);"></div>
                <div class="hero-slide__content">
                    <span class="hero-slide__tag">&#128663; Rifa de Carros</span>
                    <h1 class="hero-slide__title">&iquest;Y si hoy<br>ganas un <em>Carro 0km</em>?</h1>
                    <p class="hero-slide__desc">Rifas de carros, motos, electrodom&eacute;sticos y m&aacute;s. Transparencia total: sorteos vinculados a la Loter&iacute;a Nacional.</p>
                    <div class="hero-slide__actions">
                        <a href="#rifas" class="hero-slide__btn hero-slide__btn--primary" style="background:linear-gradient(135deg,#7c3aed,#a855f7);box-shadow:0 8px 30px rgba(124,58,237,0.5);">Explorar Rifas</a>
                        <a href="<?= BASE_PATH ?>/public/register.php" class="hero-slide__btn hero-slide__btn--ghost">Vendo mis Rifas</a>
                    </div>
                </div>
            </div>

            <!-- Slide 4 -->
            <div class="hero-slide">
                <div class="hero-slide__bg" style="background-image:url('https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=1600&q=80');"></div>
                <div class="hero-slide__overlay" style="background:linear-gradient(135deg,rgba(7,89,133,0.85) 0%,rgba(0,0,0,0.15) 60%,rgba(0,0,0,0.4) 100%);"></div>
                <div class="hero-slide__content">
                    <span class="hero-slide__tag">&#128241; Tecnolog&iacute;a</span>
                    <h1 class="hero-slide__title">iPhone, MacBook<br>y m&aacute;s <em>gadgets</em></h1>
                    <p class="hero-slide__desc">Los mejores premios tecnol&oacute;gicos. Comp&aacute;rtelo en WhatsApp y tus amigos pueden ganar contigo.</p>
                    <div class="hero-slide__actions">
                        <a href="#rifas" class="hero-slide__btn hero-slide__btn--primary" style="background:linear-gradient(135deg,#0369a1,#0ea5e9);box-shadow:0 8px 30px rgba(14,165,233,0.5);">Ver Electr&oacute;nicos</a>
                        <a href="<?= BASE_PATH ?>/public/admin/index.php?auth=login" class="hero-slide__btn hero-slide__btn--ghost">Iniciar Sesi&oacute;n</a>
                    </div>
                </div>
            </div>

        </div><!-- /track -->

        <!-- Flechas -->
        <button class="hero-slider__arrow hero-slider__arrow--prev" id="sliderPrev" aria-label="Anterior">&#8592;</button>
        <button class="hero-slider__arrow hero-slider__arrow--next" id="sliderNext" aria-label="Siguiente">&#8594;</button>

        <!-- Dots -->
        <div class="hero-slider__dots" id="sliderDots" role="tablist"></div>

        <!-- Barra de progreso -->
        <div class="hero-slider__progress" id="sliderProgress"></div>

        <!-- Miniaturas (desktop) -->
        <div class="hero-slider__thumbs" id="sliderThumbs">
            <div class="hero-slider__thumb is-active" data-slide="0"><img src="https://images.unsplash.com/photo-1592921870789-04563d55041c?w=200&q=60" alt="Slide 1"></div>
            <div class="hero-slider__thumb" data-slide="1"><img src="https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=200&q=60" alt="Slide 2"></div>
            <div class="hero-slider__thumb" data-slide="2"><img src="https://images.unsplash.com/photo-1547036967-23d11aacaee0?w=200&q=60" alt="Slide 3"></div>
            <div class="hero-slider__thumb" data-slide="3"><img src="https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=200&q=60" alt="Slide 4"></div>
        </div>

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
            if (e.key === 'ArrowLeft')  { goTo(current - 1); restart(); }
            if (e.key === 'ArrowRight') { goTo(current + 1); restart(); }
        });

        resetProgress();
        start();
    })();
    </script>



    <section id="search-section" class="py-12 border-b border-white/5 bg-slate-900/50">
        <div class="container mx-auto px-4 max-w-6xl">
            <div class="premium-filter rounded-[40px] p-8 md:p-12">
                <div class="flex flex-col lg:flex-row gap-6 mb-8">
                    <div class="flex-1 relative group">
                        <input type="text" id="search-input" class="w-full pl-16 pr-8 py-5 bg-slate-950/40 border border-white/10 rounded-3xl text-xl outline-none focus:border-fuchsia-500/50 transition-all placeholder-slate-500 text-white shadow-inner" placeholder="¿Qué quieres ganar hoy? (ej: Carro, Moto, iPhone...)">
                        <span class="absolute left-6 top-1/2 -translate-y-1/2 text-3xl group-focus-within:scale-110 transition-transform">🔍</span>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="space-y-2">
                        <label class="text-[11px] font-black text-blue-400 uppercase tracking-[0.2em] ml-2">Departamento</label>
                        <div class="relative">
                            <select id="filter-dept" class="w-full bg-slate-950/60 border border-white/10 rounded-2xl px-5 py-4 text-slate-200 outline-none focus:border-blue-500/50 transition-all appearance-none cursor-pointer pr-10">
                                <option value="">Selecciona Depto</option>
                            </select>
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-blue-400 text-xs">▼</span>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label class="text-[11px] font-black text-blue-400 uppercase tracking-[0.2em] ml-2">Municipio / Ciudad</label>
                        <div class="relative">
                            <select id="filter-city" class="w-full bg-slate-950/60 border border-white/10 rounded-2xl px-5 py-4 text-slate-200 outline-none focus:border-blue-500/50 transition-all appearance-none cursor-pointer pr-10">
                                <option value="">Selecciona Ciudad</option>
                            </select>
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-blue-400 text-xs">▼</span>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label class="text-[11px] font-black text-fuchsia-400 uppercase tracking-[0.2em] ml-2">Precio Mín y Máx</label>
                        <div class="grid grid-cols-2 gap-2">
                            <select id="filter-min-price" class="w-full bg-slate-950/60 border border-white/10 rounded-2xl px-3 py-4 text-slate-200 text-xs outline-none focus:border-fuchsia-500/50 transition-all appearance-none cursor-pointer">
                                <option value="">Min</option>
                                <option value="1000">$1.000</option>
                                <option value="5000">$5.000</option>
                                <option value="10000">$10.000</option>
                                <option value="50000">$50.000</option>
                            </select>
                            <select id="filter-max-price" class="w-full bg-slate-950/60 border border-white/10 rounded-2xl px-3 py-4 text-slate-200 text-xs outline-none focus:border-fuchsia-500/50 transition-all appearance-none cursor-pointer">
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
                        <label class="text-[11px] font-black text-blue-400 uppercase tracking-[0.2em] ml-2">Lotería</label>
                        <div class="relative">
                            <select id="filter-lottery" class="w-full bg-slate-950/60 border border-white/10 rounded-2xl px-5 py-4 text-slate-200 outline-none focus:border-blue-500/50 transition-all appearance-none cursor-pointer pr-10">
                                <option value="">Selecciona Lotería</option>
                            </select>
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-blue-400 text-xs">▼</span>
                        </div>
                    </div>
                    <div class="space-y-2 flex flex-col justify-end">
                        <div class="flex gap-2">
                            <button onclick="handleFilter()" class="flex-1 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white font-black uppercase tracking-widest text-[11px] h-[55px] rounded-2xl shadow-xl shadow-blue-500/20 transition-all hover:-translate-y-0.5 active:scale-95 flex items-center justify-center gap-2">
                                <span>🔍</span> Buscar
                            </button>
                            <button onclick="clearFilters()" class="px-6 py-3 bg-gradient-to-r from-slate-600 to-slate-700 hover:from-slate-500 hover:to-slate-600 text-white font-bold uppercase tracking-widest text-[10px] rounded-2xl transition-all flex items-center justify-center gap-2 h-[55px]">
                                <span>🔄</span> Limpiar
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
                <button class="tab active px-6 py-2.5 rounded-xl font-semibold shadow-lg" data-tab="destacadas">🔥 Destacadas</button>
                <button class="tab px-6 py-2.5 rounded-xl font-semibold shadow-lg" data-tab="populares">⭐ Top Ventas</button>
                <button class="tab px-6 py-2.5 rounded-xl font-semibold shadow-lg" data-tab="proximas">⏰ Cierran Pronto</button>
                <button class="tab px-6 py-2.5 rounded-xl font-semibold shadow-lg" data-tab="nuevas">✨ Recientes</button>
            </div>
        </div>
    </section>

    <section class="py-12 min-h-[500px]">
        <div class="container mx-auto px-4 max-w-7xl">
            <div id="raffles-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
                <!-- Skeletons go here while loading -->
            </div>
        </div>
    </section>

    <section class="py-24 bg-slate-900 border-t border-slate-800">
        <div class="container mx-auto px-4">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-black mb-4"><span class="bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-emerald-400">¿Cómo funciona?</span></h2>
                <p class="text-slate-400 text-lg">Un proceso transparente, conectado a tu WhatsApp.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 max-w-6xl mx-auto">
                <div class="relative group">
                    <div class="absolute -inset-0.5 bg-gradient-to-r from-blue-500 to-blue-600 rounded-3xl blur opacity-25 group-hover:opacity-100 transition duration-1000 group-hover:duration-200"></div>
                    <div class="relative bg-slate-800/80 backdrop-blur p-8 rounded-3xl border border-slate-700/50 text-center h-full">
                        <div class="w-16 h-16 bg-blue-500/20 text-blue-400 rounded-2xl flex items-center justify-center text-3xl mb-6 mx-auto shadow-inner border border-blue-500/30">1</div>
                        <h3 class="text-xl font-bold mb-3 text-f8fafc">Elige Rifa</h3>
                        <p class="text-slate-400 font-medium">Encuentra una rifa verificada de nuestra red nacional.</p>
                    </div>
                </div>
                <div class="relative group">
                    <div class="absolute -inset-0.5 bg-gradient-to-r from-emerald-500 to-emerald-600 rounded-3xl blur opacity-25 group-hover:opacity-100 transition duration-1000 group-hover:duration-200"></div>
                    <div class="relative bg-slate-800/80 backdrop-blur p-8 rounded-3xl border border-slate-700/50 text-center h-full">
                        <div class="w-16 h-16 bg-emerald-500/20 text-emerald-400 rounded-2xl flex items-center justify-center text-3xl mb-6 mx-auto shadow-inner border border-emerald-500/30">2</div>
                        <h3 class="text-xl font-bold mb-3 text-f8fafc">Toma un Cupo</h3>
                        <p class="text-slate-400 font-medium">Los tickets bloquean dobles compras gracias a nuestra concurrencia estricta.</p>
                    </div>
                </div>
                <div class="relative group">
                    <div class="absolute -inset-0.5 bg-gradient-to-r from-purple-500 to-purple-600 rounded-3xl blur opacity-25 group-hover:opacity-100 transition duration-1000 group-hover:duration-200"></div>
                    <div class="relative bg-slate-800/80 backdrop-blur p-8 rounded-3xl border border-slate-700/50 text-center h-full">
                        <div class="w-16 h-16 bg-purple-500/20 text-purple-400 rounded-2xl flex items-center justify-center text-3xl mb-6 mx-auto shadow-inner border border-purple-500/30">3</div>
                        <h3 class="text-xl font-bold mb-3 text-f8fafc">Pago Seguro</h3>
                        <p class="text-slate-400 font-medium">Botonería de Wompi detecta pagos en segundos.</p>
                    </div>
                </div>
                <div class="relative group">
                    <div class="absolute -inset-0.5 bg-gradient-to-r from-amber-500 to-amber-600 rounded-3xl blur opacity-25 group-hover:opacity-100 transition duration-1000 group-hover:duration-200"></div>
                    <div class="relative bg-slate-800/80 backdrop-blur p-8 rounded-3xl border border-slate-700/50 text-center h-full">
                        <div class="w-16 h-16 bg-amber-500/20 text-amber-400 rounded-2xl flex items-center justify-center text-3xl mb-6 mx-auto shadow-inner border border-amber-500/30">4</div>
                        <h3 class="text-xl font-bold mb-3 text-f8fafc">Lotería en Vivo</h3>
                        <p class="text-slate-400 font-medium">Te notificamos vía API directa si tu cartón ganò con la lotería.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="bg-gray-900 text-white py-12">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; 2026 MisRifas Colombia. Todos los derechos reservados.</p>
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
            const response = await fetch('/api' + endpoint, config);
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
            n.textContent = msg;
            document.body.appendChild(n);
            setTimeout(() => n.remove(), 3000);
        },
        fixUrl(url) {
            if (!url) return 'https://images.unsplash.com/photo-1540317580384-e5d4361660bd?w=800';
            if (url.startsWith('http')) return url;
            return (url.startsWith('/') ? '' : '/') + url;
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
                const options = response.data.map(l => `<option value="${l.id}">${l.name}</option>`).join('');
                select.innerHTML = '<option value="">Selecciona Lotería</option>' + options;
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
                                <div class="text-6xl mb-6">🏜️</div>
                                <h3 class="text-2xl font-black text-white mb-2">No encontramos resultados</h3>
                                <p class="text-slate-400">Intenta ajustar los filtros de búsqueda.</p>
                            </div>
                        `;
                        return;
                    }

                    container.innerHTML = raffles.map(r => `
                        <div class="raffle-card group" data-id="${r.id}">
                            <div class="raffle-card__image">
                                <img src="${Utils.fixUrl(r.image_url)}" alt="${r.name}" loading="lazy" class="group-hover:scale-110 transition-transform duration-500">
                                <span class="raffle-card__badge">${r.sold_percentage || 0}% vendido</span>
                                <div class="absolute bottom-0 left-0 right-0 h-1/2 bg-gradient-to-t from-slate-900 to-transparent"></div>
                            </div>
                            <div class="raffle-card__content">
                                <h3 class="raffle-card__title group-hover:text-blue-400 transition-colors">${r.name}</h3>
                                <p class="raffle-card__city">📍 ${r.city}${r.department ? ', ' + r.department : ''}</p>
                                <div class="raffle-card__info">
                                    <div class="raffle-card__price">${Utils.formatPrice(r.ticket_price)}<span>por boleto</span></div>
                                    <div class="raffle-card__date">${Utils.formatDate(r.draw_date)}</div>
                                </div>
                                <div class="progress-bar group-hover:h-2 transition-all"><div class="progress-bar__fill" style="width: ${r.sold_percentage}%"></div></div>
                                <div class="flex justify-between items-center mt-2">
                                    <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest">${r.sold_tickets} / ${r.total_tickets} Vendidos</span>
                                    <span class="text-[10px] font-black text-blue-500 uppercase tracking-widest">${Math.max(0, Math.floor((new Date(r.draw_date) - new Date()) / (1000 * 60 * 60 * 24)))} Días restantes</span>
                                </div>
                                <button class="btn btn--primary w-full mt-6 shadow-blue-500/20 group-hover:shadow-blue-500/40 group-hover:-translate-y-0.5 transition-all" onclick="window.location.href=' + BASE_PATH + '/public/raffle.php?id=${r.id}'">Participar Ahora &rarr;</button>
                            </div>
                        </div>
                    `).join('');
                }
            } catch (e) {
                console.error('Error:', e);
                Utils.showNotification('Error al cargar las rifas', 'error');
            }
        }
    };

    function getActiveFilters() {
        return {
            search: document.getElementById('search-input')?.value || '',
            department: document.getElementById('filter-dept')?.value || '',
            city: document.getElementById('filter-city')?.value || '',
            min_price: document.getElementById('filter-min-price')?.value || '',
            max_price: document.getElementById('filter-max-price')?.value || '',
            lottery_id: document.getElementById('filter-lottery')?.value || ''
        };
    }

    let colombiaData = [];
    async function loadGeography() {
        try {
            const res = await fetch(' + BASE_PATH + '/public/assets/data/colombia.json');
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
        } catch (e) {
            console.error('Error loading geography:', e);
        }
    }

    // FUNCIONES GLOBALES para onclick handlers
    let debounceTimer;
    function handleFilter() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            Raffles.loadRaffles(getActiveFilters());
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
        Raffles.loadRaffles();
    }

    function logout() {
        localStorage.removeItem('misrifas_token');
        localStorage.removeItem('misrifas_user');
        window.location.reload();
    }

    function checkAuth() {
        const userStr = localStorage.getItem('misrifas_user');
        if (userStr) {
            try {
                const user = JSON.parse(userStr);
                document.getElementById('auth-buttons').classList.add('hidden');
                document.getElementById('user-menu').classList.remove('hidden');
                document.getElementById('user-name').textContent = user.full_name;
                document.getElementById('user-avatar').textContent = user.full_name.charAt(0).toUpperCase();
            } catch (e) {
                console.error('Error parsing user data:', e);
            }
        }
    }

    // Inicialización
    document.addEventListener('DOMContentLoaded', () => {
        checkAuth();
        loadLotteries();
        loadGeography();
        Raffles.loadRaffles();

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
                const orderMap = {
                    populares: 'sold_percentage',
                    proximas: 'draw_date',
                    nuevas: 'created_at',
                    destacadas: 'views'
                };
                const filters = getActiveFilters();
                filters.order_by = orderMap[tab.dataset.tab] || 'views';
                Raffles.loadRaffles(filters);
            });
        });
    });
    </script>
</body>
</html>
