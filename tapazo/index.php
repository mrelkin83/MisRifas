<?php
require_once __DIR__ . '/../config/database.php';

$codigo = trim($_GET['codigo'] ?? ($_GET['id'] ?? ''));
$tapazo = null;

if ($codigo) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM tapazos WHERE codigo_unico = ?");
        $stmt->execute([$codigo]);
        $tapazo = $stmt->fetch();
    } catch (Exception $e) {}
}

// URL absoluta del talonario para compartir. WhatsApp/Facebook necesitan la
// URL completa (antes se compartía $_SERVER['REQUEST_URI'], una ruta relativa
// que no abre nada fuera del sitio). Abrirla carga esta misma vista pública.
$protocol   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host       = $_SERVER['HTTP_HOST'] ?? 'localhost';
$origin     = $protocol . '://' . $host;
$shareUrl   = $tapazo
    ? $origin . BASE_PATH . '/tapazo/index.php?codigo=' . urlencode($tapazo['codigo_unico'])
    : '';
// imagen_url se guarda como ruta del sitio (con BASE_PATH); para og:image
// hace falta absoluta.
$ogImage = ($tapazo && !empty($tapazo['imagen_url']))
    ? (strpos($tapazo['imagen_url'], 'http') === 0 ? $tapazo['imagen_url'] : $origin . $tapazo['imagen_url'])
    : '';
$ogDesc = $tapazo
    ? trim(($tapazo['descripcion'] ? $tapazo['descripcion'] . ' · ' : '')
        . $tapazo['cantidad_jugadores'] . ' jugadores · '
        . ($tapazo['regla'] === 'bajo_gana' ? 'gana el número más bajo' : 'gana el número más alto'))
    : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <script>const BASE_PATH = "<?= BASE_PATH ?>";</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $tapazo ? htmlspecialchars($tapazo['titulo']) : 'El Tapazo' ?> | MisRifas</title>
    <meta name="theme-color" content="#0f172a">
    <?php if ($tapazo): ?>
    <!-- Open Graph / Twitter: tarjeta de previsualización al compartir el talonario -->
    <meta property="og:type"        content="website">
    <meta property="og:site_name"   content="MisRifas · El Tapazo">
    <meta property="og:title"       content="<?= htmlspecialchars($tapazo['titulo']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($ogDesc) ?>">
    <meta property="og:url"         content="<?= htmlspecialchars($shareUrl) ?>">
    <?php if ($ogImage): ?>
    <meta property="og:image"       content="<?= htmlspecialchars($ogImage) ?>">
    <meta name="twitter:card"       content="summary_large_image">
    <meta name="twitter:image"      content="<?= htmlspecialchars($ogImage) ?>">
    <?php else: ?>
    <meta name="twitter:card"       content="summary">
    <?php endif; ?>
    <meta name="twitter:title"       content="<?= htmlspecialchars($tapazo['titulo']) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($ogDesc) ?>">
    <meta name="description"          content="<?= htmlspecialchars($ogDesc) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($shareUrl) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= BASE_PATH ?>/public/css/tailwind.min.css">
    <style>
        @layer base {
            * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        }
        body { background: #0f172a; color: #f8fafc; min-height: 100vh; }

        .beer-bottle {
            position: relative;
            width: 80px; height: 180px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex; flex-direction: column; align-items: center;
        }
        .beer-bottle:hover { transform: scale(1.05); }
        .beer-bottle--taken { opacity: 0.5; cursor: not-allowed; }
        .beer-bottle--revealed { cursor: default; }

        .bottle-body {
            width: 60px; height: 120px;
            background: linear-gradient(135deg, #92400e 0%, #78350f 50%, #451a03 100%);
            border-radius: 8px 8px 12px 12px;
            position: relative;
            box-shadow: inset -8px 0 12px rgba(0,0,0,0.3), 0 4px 12px rgba(0,0,0,0.4);
        }
        .bottle-neck {
            width: 24px; height: 50px;
            background: linear-gradient(135deg, #92400e 0%, #78350f 100%);
            border-radius: 4px 4px 0 0;
            margin-bottom: -2px;
            box-shadow: inset -4px 0 8px rgba(0,0,0,0.3);
        }
        .bottle-cap {
            width: 30px; height: 14px;
            background: linear-gradient(135deg, #eab308, #ca8a04);
            border-radius: 4px 4px 0 0;
            position: relative;
            box-shadow: 0 -2px 4px rgba(0,0,0,0.2);
        }
        .bottle-cap::after {
            content: '';
            position: absolute; bottom: -3px; left: -2px; right: -2px;
            height: 6px;
            background: repeating-linear-gradient(90deg, #ca8a04 0px, #ca8a04 3px, #a16207 3px, #a16207 6px);
            border-radius: 0 0 2px 2px;
        }

        .bottle-label {
            position: absolute; top: 20px; left: 5px; right: 5px;
            background: #fef3c7; border-radius: 4px;
            padding: 4px; text-align: center;
            font-size: 10px; font-weight: 800; color: #78350f;
        }

        .bottle-number {
            position: absolute; bottom: 15px; left: 0; right: 0;
            text-align: center; font-size: 18px; font-weight: 900;
            color: #fbbf24; text-shadow: 0 2px 4px rgba(0,0,0,0.5);
        }

        /* Animación de destape */
        @keyframes capFly {
            0% { transform: translateY(0) rotate(0); opacity: 1; }
            50% { transform: translateY(-80px) rotate(45deg) translateX(30px); opacity: 0.8; }
            100% { transform: translateY(-120px) rotate(90deg) translateX(60px); opacity: 0; }
        }
        @keyframes bottleShake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-3px) rotate(-2deg); }
            20%, 40%, 60%, 80% { transform: translateX(3px) rotate(2deg); }
        }
        @keyframes revealNumber {
            0% { transform: scale(0) rotateY(180deg); opacity: 0; }
            60% { transform: scale(1.3) rotateY(0); opacity: 1; }
            100% { transform: scale(1) rotateY(0); opacity: 1; }
        }
        @keyframes foamRise {
            0% { transform: translateY(0); opacity: 0; }
            50% { opacity: 0.8; }
            100% { transform: translateY(-40px); opacity: 0; }
        }
        @keyframes winnerPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(250, 204, 21, 0.6); }
            50% { box-shadow: 0 0 0 20px rgba(250, 204, 21, 0); }
        }

        .cap-flying { animation: capFly 0.8s ease-out forwards; }
        .bottle-shaking { animation: bottleShake 0.5s ease-in-out; }
        .number-reveal { animation: revealNumber 0.6s ease-out 0.8s both; }
        .foam-effect { animation: foamRise 1s ease-out 0.5s both; }
        .winner-glow { animation: winnerPulse 1.5s ease-in-out infinite; }

        .tap-number {
            font-family: 'Courier New', monospace;
            font-size: 48px; font-weight: 900;
            color: #fbbf24;
            text-shadow: 0 0 20px rgba(251, 191, 36, 0.5), 0 2px 4px rgba(0,0,0,0.5);
            letter-spacing: 4px;
        }

        /* ===== ANIMACIÓN DE TAPA (FLIP) ===== */
        .cap-container {
            perspective: 1000px;
            width: 120px; height: 120px;
        }
        .cap-inner {
            position: relative;
            width: 100%; height: 100%;
            transition: transform 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            transform-style: preserve-3d;
        }
        .cap-inner.flipping {
            transform: rotateY(180deg);
        }
        .cap-front, .cap-back {
            position: absolute;
            width: 100%; height: 100%;
            backface-visibility: hidden;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
        }
        .cap-front {
            background: linear-gradient(135deg, #eab308 0%, #ca8a04 50%, #a16207 100%);
            box-shadow: 0 8px 20px rgba(0,0,0,0.4), inset 0 2px 4px rgba(255,255,255,0.3);
            border: 3px solid #78350f;
        }
        .cap-front::before {
            content: '';
            position: absolute; bottom: 8px; left: 8px; right: 8px;
            height: 10px;
            background: repeating-linear-gradient(90deg, #ca8a04 0px, #ca8a04 4px, #a16207 4px, #a16207 8px);
            border-radius: 2px;
        }
        .cap-front::after {
            content: '🍺';
            font-size: 36px;
        }
        .cap-back {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            transform: rotateY(180deg);
            border: 3px solid #ca8a04;
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
        }
        .cap-number-revealed {
            font-size: 42px; font-weight: 900;
            color: #78350f;
            text-shadow: 2px 2px 0 #fde68a, -1px -1px 0 #fbbf24;
            letter-spacing: 2px;
        }

        @keyframes capShake {
            0%, 100% { transform: translateX(0) rotate(0); }
            20% { transform: translateX(-8px) rotate(-5deg); }
            40% { transform: translateX(8px) rotate(5deg); }
            60% { transform: translateX(-4px) rotate(-2deg); }
            80% { transform: translateX(4px) rotate(2deg); }
        }
        .cap-shaking {
            animation: capShake 0.4s ease-in-out;
        }

        /* Selector de botella */
        .bottle-option {
            width: 50px; height: 50px;
            border-radius: 10px;
            background: linear-gradient(135deg, #92400e, #78350f);
            border: 2px solid #451a03;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            font-weight: 700; color: #fbbf24;
            transition: all 0.2s;
        }
        .bottle-option:hover {
            transform: scale(1.1);
            border-color: #eab308;
        }
        .bottle-option.selected {
            border-color: #eab308;
            box-shadow: 0 0 15px rgba(234, 179, 8, 0.5);
            background: linear-gradient(135deg, #a16207, #78350f);
        }
        .bottle-option.disabled {
            opacity: 0.3; cursor: not-allowed;
        }

        .glass-card {
            background: rgba(30, 41, 59, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 24px;
        }

        .countdown-digit {
            font-size: 3rem; font-weight: 900;
            background: linear-gradient(135deg, #f59e0b, #ef4444);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .player-card {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px; padding: 16px;
            transition: all 0.3s;
        }
        .player-card--winner {
            border: 2px solid #fbbf24;
            background: rgba(251, 191, 36, 0.1);
            box-shadow: 0 0 30px rgba(251, 191, 36, 0.3);
        }
        .player-card--loser {
            border: 2px solid #ef4444;
            background: rgba(239, 68, 68, 0.1);
        }

        .bg-blob {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1;
            background: radial-gradient(circle at 20% 20%, rgba(245, 158, 11, 0.1) 0%, transparent 40%),
                        radial-gradient(circle at 80% 80%, rgba(146, 64, 14, 0.1) 0%, transparent 40%);
        }

        /* Sound toggle */
        .sound-btn {
            position: fixed; bottom: calc(20px + env(safe-area-inset-bottom, 0px)); right: 20px; z-index: 100;
            width: 48px; height: 48px; border-radius: 50%;
            background: rgba(30, 41, 59, 0.8); border: 1px solid rgba(255,255,255,0.1);
            color: white; font-size: 20px; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
        }
        .sound-btn:active { transform: scale(.94); }
        @media (max-width: 768px) {
            /* Por encima de la tab bar inferior del sitio */
            .sound-btn { bottom: calc(78px + env(safe-area-inset-bottom, 0px)); }
        }

        /* Header del sitio (mismo patrón glass-nav del resto de páginas) */
        .site-nav {
            position: sticky; top: 0; z-index: 90;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .site-nav__in { max-width: 1100px; margin: 0 auto; padding: 0 16px; height: 64px; display: flex; align-items: center; justify-content: space-between; }
        .site-nav__brand { font-weight: 800; font-size: 20px; color: #fff; text-decoration: none; letter-spacing: -.5px; }
        .site-nav__brand span { color: #f59e0b; }
        .site-nav__links { display: flex; align-items: center; gap: 14px; }
        .site-nav__links a { color: #94a3b8; font-size: 13px; font-weight: 700; text-decoration: none; }
        .site-nav__links a:hover { color: #fff; }

        @media (max-width: 640px) {
            .glass-card { padding: 1.5rem !important; }
            .countdown-digit { font-size: 2rem; }
            .tap-number { font-size: 32px; }
            .cap-container { width: 90px; height: 90px; }
            .cap-number-revealed { font-size: 32px; }
            .beer-bottle { height: 140px; }
        }
    </style>
</head>
<body>
    <div class="bg-blob"></div>
    <header class="site-nav">
        <div class="site-nav__in">
            <a class="site-nav__brand" href="<?= BASE_PATH ?>/public/index.php">MIS<span>RIFAS</span></a>
            <div class="site-nav__links">
                <a href="<?= BASE_PATH ?>/public/ganadores.php">🏆 Ganadores</a>
                <a href="<?= BASE_PATH ?>/public/index.php">← Volver al inicio</a>
            </div>
        </div>
    </header>
    <button class="sound-btn" id="sound-toggle" onclick="toggleSound()" aria-label="Activar o silenciar sonido" title="Sonido">🔊</button>

    <?php if (!$tapazo): ?>
    <!-- ===== PANTALLA: CREAR TAPAZO ===== -->
    <div class="container mx-auto px-4 max-w-2xl py-6 md:py-10">
        <div class="text-center mb-6 md:mb-10">
            <a href="<?= BASE_PATH ?>/public/index.php" class="inline-flex items-center gap-2 text-2xl md:text-3xl font-black mb-2">
                <span>🍺</span>
                <span class="bg-clip-text text-transparent bg-gradient-to-r from-amber-400 to-orange-500">El Tapazo</span>
            </a>
            <p class="text-slate-400 text-sm md:text-base">El ritual de la tapita de cerveza, ahora digital</p>
        </div>

        <div class="glass-card p-6 md:p-8">
            <form id="tapazo-form" class="space-y-4 md:space-y-5">
                <div>
                    <label class="text-xs font-bold text-slate-400 uppercase tracking-wider block mb-1.5">Título del Tapazo *</label>
                    <input type="text" id="titulo" required class="w-full px-4 py-3 md:px-5 md:py-4 rounded-xl bg-slate-800/50 border border-slate-700 text-white outline-none focus:border-amber-500 transition-colors" placeholder="ej: Tapazo del Viernes">
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-400 uppercase tracking-wider block mb-1.5">Descripción</label>
                    <input type="text" id="descripcion" class="w-full px-4 py-3 md:px-5 md:py-4 rounded-xl bg-slate-800/50 border border-slate-700 text-white outline-none focus:border-amber-500 transition-colors" placeholder="ej: El que saque el número más alto invita las cervezas">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-bold text-slate-400 uppercase tracking-wider block mb-1.5">Jugadores *</label>
                        <input type="number" id="cantidad" required min="2" max="50" value="6" class="w-full px-4 py-3 md:px-5 md:py-4 rounded-xl bg-slate-800/50 border border-slate-700 text-white outline-none focus:border-amber-500 transition-colors" placeholder="Número de jugadores (2-50)">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-400 uppercase tracking-wider block mb-1.5">Valor Cupo</label>
                        <input type="number" id="valor_cupo" class="w-full px-4 py-3 md:px-5 md:py-4 rounded-xl bg-slate-800/50 border border-slate-700 text-white outline-none focus:border-amber-500" placeholder="5000">
                    </div>
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-400 uppercase tracking-wider block mb-1.5">Imagen (opcional)</label>
                    <div class="flex flex-col sm:flex-row items-center gap-3">
                        <label class="w-full sm:flex-1 cursor-pointer focus-within:ring-2 focus-within:ring-amber-500 rounded-xl block">
                            <div id="image-preview" class="w-full h-24 border-2 border-dashed border-slate-700 rounded-xl flex items-center justify-center text-slate-500 hover:border-amber-500 transition-colors overflow-hidden">
                                <span class="text-xs">Click para subir imagen</span>
                            </div>
                            <input type="file" id="imagen" accept="image/*" class="sr-only">
                        </label>
                        <input type="text" id="imagen_url" class="w-full sm:flex-1 px-4 py-3 md:px-5 md:py-4 rounded-xl bg-slate-800/50 border border-slate-700 text-white outline-none focus:border-amber-500 transition-colors text-sm" placeholder="O pega una URL de imagen">
                    </div>
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-400 uppercase tracking-wider block mb-1.5">Fecha y Hora del Destape *</label>
                    <input type="datetime-local" id="fecha_destape" required class="w-full px-4 py-3 md:px-5 md:py-4 rounded-xl bg-slate-800/50 border border-slate-700 text-white outline-none focus:border-amber-500">
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-400 uppercase tracking-wider block mb-1.5">Regla del Juego *</label>
                    <select id="regla" required class="w-full px-4 py-3 md:px-5 md:py-4 rounded-xl bg-slate-800/50 border border-slate-700 text-white outline-none focus:border-amber-500">
                        <option value="alto_gana">🔼 El número más ALTO GANA</option>
                        <option value="bajo_gana">🔽 El número más BAJO GANA</option>
                    </select>
                </div>
                <button type="submit" class="w-full py-4 rounded-2xl bg-gradient-to-r from-amber-500 to-orange-600 text-white font-black text-lg uppercase tracking-wider shadow-xl shadow-amber-500/30 hover:shadow-amber-500/50 active:scale-[0.97] transition-all hover:-translate-y-0.5 mt-4">
                    🍺 Crear El Tapazo
                </button>
            </form>
        </div>
    </div>

    <script>
        // Set min date to now
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        document.getElementById('fecha_destape').min = now.toISOString().slice(0, 16);

        // Preview de imagen
        const imagenInput = document.getElementById('imagen');
        const imagenUrlInput = document.getElementById('imagen_url');
        const imagePreview = document.getElementById('image-preview');
        
        imagenInput.addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (file) {
                const formData = new FormData();
                formData.append('imagen', file);
                imagePreview.innerHTML = '<span class="text-xs">Subiendo imagen…</span>';
                try {
                    const res = await fetch(BASE_PATH + '/api/tapazo/upload.php', {
                        method: 'POST',
                        body: formData
                    });
                    const json = await res.json();
                    if (json.success) {
                        imagenUrlInput.value = json.data.url;
                        imagePreview.innerHTML = `<img src="${json.data.url}" class="w-full h-full object-cover" alt="Imagen del tapazo">`;
                    } else {
                        imagePreview.innerHTML = '<span class="text-xs">Click para subir imagen</span>';
                        alert(json.message || 'Error al subir imagen');
                    }
                } catch(err) {
                    imagePreview.innerHTML = '<span class="text-xs">Click para subir imagen</span>';
                    alert('Error al subir imagen');
                }
            }
        });

        imagenUrlInput.addEventListener('input', (e) => {
            if (e.target.value) {
                imagePreview.innerHTML = `<img src="${e.target.value}" class="w-full h-full object-cover" onerror="this.parentElement.innerHTML='<span class=\\'text-sm\\'>Imagen no válida</span>'">`;
            }
        });

        document.getElementById('tapazo-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button');
            btn.disabled = true; btn.textContent = 'Creando...';

            try {
                const res = await fetch(BASE_PATH + '/api/tapazo/crear.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        titulo: document.getElementById('titulo').value,
                        descripcion: document.getElementById('descripcion').value,
                        cantidad_jugadores: parseInt(document.getElementById('cantidad').value),
                        valor_cupo: parseFloat(document.getElementById('valor_cupo').value) || 0,
                        regla: document.getElementById('regla').value,
                        imagen_url: document.getElementById('imagen_url').value,
                        fecha_hora_destape: document.getElementById('fecha_destape').value + ':00'
                    })
                });
                if (!res.ok) throw new Error(`HTTP ${res.status}: ${res.statusText}`);
                const json = await res.json();
                if (json.success) {
                    window.location.href = BASE_PATH + '/tapazo/index.php?codigo=' + json.data.codigo;
                } else {
                    alert(json.message || 'Error al crear');
                }
            } catch (err) {
                console.error("Error:", err);
                alert("Error: " + err.message);
            } finally {
                btn.disabled = false; btn.textContent = '🍺 Crear El Tapazo';
            }
        });
    </script>

    <?php else: ?>
    <!-- ===== PANTALLA PÚBLICA DEL TAPAZO ===== -->
    <div class="container mx-auto px-4 max-w-4xl py-6">
        <div class="text-center mb-6">
            <a href="<?= BASE_PATH ?>/tapazo/index.php" class="inline-flex items-center gap-2 text-2xl font-black mb-2">
                <span>🍺</span>
                <span class="bg-clip-text text-transparent bg-gradient-to-r from-amber-400 to-orange-500">El Tapazo</span>
            </a>
            <?php if ($tapazo['imagen_url']): ?>
                <div class="mt-3 mb-3">
                    <img src="<?= htmlspecialchars($tapazo['imagen_url']) ?>" alt="<?= htmlspecialchars($tapazo['titulo']) ?>" class="max-h-48 mx-auto rounded-xl shadow-lg">
                </div>
            <?php endif; ?>
            <h1 class="text-2xl md:text-3xl font-black text-white px-2"><?= htmlspecialchars($tapazo['titulo']) ?></h1>
            <?php if ($tapazo['descripcion']): ?>
                <p class="text-slate-400 mt-1 text-sm md:text-base"><?= htmlspecialchars($tapazo['descripcion']) ?></p>
            <?php endif; ?>
            <div class="flex items-center justify-center gap-3 md:gap-4 mt-3 text-xs md:text-sm">
                <span class="px-3 py-1 rounded-full bg-amber-500/20 text-amber-400 font-bold">
                    <?= $tapazo['cantidad_jugadores'] ?> jugadores
                </span>
                <span class="px-3 py-1 rounded-full <?= in_array($tapazo['regla'], ['alto_gana', 'bajo_pierde']) ? 'bg-green-500/20 text-green-400' : (in_array($tapazo['regla'], ['alto_pierde', 'bajo_gana']) ? 'bg-red-500/20 text-red-400' : 'bg-blue-500/20 text-blue-400') ?> font-bold">
                    <?php 
                    $reglas = [
                        'alto_gana' => '🔼 Más alto GANA',
                        'bajo_gana' => '🔽 Más bajo GANA'
                    ];
                    echo $reglas[$tapazo['regla']] ?? $tapazo['regla'];
                    ?>
                </span>
            </div>
            <div class="mt-4 flex flex-col items-center gap-3">
                <div class="flex flex-wrap items-center justify-center gap-2">
                    <span class="text-[10px] text-slate-500 uppercase font-bold w-full text-center sm:w-auto">Invita a tus amigos:</span>
                    <a href="https://wa.me/?text=<?= rawurlencode('🍺 ¡Únete a este Tapazo! ' . $tapazo['titulo'] . "\n" . $shareUrl) ?>" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#25D366] text-white text-xs font-bold hover:brightness-110 transition-all">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M11.999 2C6.477 2 2 6.484 2 12.017c0 1.99.522 3.859 1.433 5.474L2.05 22l4.629-1.364A9.956 9.956 0 0 0 12 22.033C17.523 22.033 22 17.549 22 12.017 22 6.484 17.523 2 11.999 2zm0 18.06a8.079 8.079 0 0 1-4.298-1.232l-.308-.183-3.184.94.893-3.26-.202-.325A8.02 8.02 0 0 1 3.955 12c0-4.455 3.606-8.078 8.045-8.078 4.438 0 8.046 3.623 8.046 8.078 0 4.456-3.608 8.08-8.047 8.08z"/></svg>
                        WhatsApp
                    </a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($shareUrl) ?>" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#1877F2] text-white text-xs font-bold hover:brightness-110 transition-all">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M22 12.06C22 6.505 17.523 2 12 2S2 6.505 2 12.06c0 5.02 3.657 9.184 8.438 9.94v-7.03H7.898v-2.91h2.54V9.845c0-2.507 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562v1.877h2.773l-.443 2.91h-2.33V22c4.78-.756 8.437-4.92 8.437-9.94z"/></svg>
                        Facebook
                    </a>
                    <button type="button" onclick="copyLink()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 text-white text-xs font-bold hover:bg-slate-600 transition-all">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>
                        Copiar Link
                    </button>
                </div>
                <code class="text-amber-400/70 text-[11px] font-mono truncate max-w-[280px] sm:max-w-md" id="share-link"><?= htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8') ?></code>
            </div>
        </div>

        <!-- Estado: CREADO / LLENO / ESPERANDO -->
        <div id="state-joining" class="<?= in_array($tapazo['estado'], ['creado', 'lleno', 'esperando']) ? '' : 'hidden' ?>">
            <!-- Formulario unirse -->
            <div id="join-form-container" class="glass-card p-5 md:p-6 mb-6 <?= $tapazo['estado'] === 'lleno' ? 'hidden' : '' ?>">
                <h3 class="font-bold text-lg mb-4 text-center">🎯 ¿Quién se apunta?</h3>
                <form id="join-form" class="space-y-4">
                    <div>
                        <label class="text-xs font-bold text-slate-400 uppercase mb-1.5 block">Tu Nombre</label>
                        <input type="text" id="player-name" required placeholder="Ingresa tu nombre" class="w-full px-5 py-3 rounded-xl bg-slate-800/50 border border-slate-700 text-white outline-none focus:border-amber-500" maxlength="30">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-400 uppercase block mb-2">Elige tu número de botella:</label>
                        <div id="bottle-selector" class="flex flex-wrap gap-2 justify-center">
                            <!-- JS populated -->
                        </div>
                        <input type="hidden" id="selected-bottle" value="">
                    </div>
                    <button type="submit" id="join-btn" disabled class="w-full px-6 py-4 rounded-xl bg-gradient-to-r from-amber-500 to-orange-600 text-white font-bold hover:shadow-lg transition-all disabled:opacity-30 disabled:cursor-not-allowed">
                        ¡Me apunto!
                    </button>
                </form>
            </div>

            <!-- Cervezas visuales -->
            <div class="glass-card p-5 md:p-6 mb-6">
                <h3 class="font-bold text-base md:text-lg mb-4 text-center">🍺 Cervezas en la mesa</h3>
                <div id="beers-grid" class="flex flex-wrap justify-center gap-3 md:gap-4">
                    <!-- JS populated -->
                </div>
            </div>

            <!-- Jugadores actuales -->
            <div class="glass-card p-5 md:p-6 mb-6">
                <h3 class="font-bold text-base md:text-lg mb-4">👥 Jugadores (<span id="player-count">0</span>/<span id="player-max"><?= $tapazo['cantidad_jugadores'] ?></span>)</h3>
                <div id="players-list" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <!-- JS populated -->
                </div>
            </div>

            <!-- Contador regresivo -->
            <div id="countdown-section" class="glass-card p-6 md:p-8 text-center <?= $tapazo['estado'] === 'creado' ? 'hidden' : '' ?>">
                <h3 class="text-sm md:text-lg font-bold text-slate-400 mb-4 uppercase tracking-wider">⏰ Destape en</h3>
                <div class="flex justify-center gap-3 md:gap-4">
                    <div><div id="cd-hours" class="countdown-digit">00</div><div class="text-[10px] text-slate-500 uppercase">Hrs</div></div>
                    <div><div id="cd-minutes" class="countdown-digit">00</div><div class="text-[10px] text-slate-500 uppercase">Min</div></div>
                    <div><div id="cd-seconds" class="countdown-digit">00</div><div class="text-[10px] text-slate-500 uppercase">Seg</div></div>
                </div>
                <button onclick="forceStartDestape()" id="btn-force-start" class="mt-6 w-full sm:w-auto px-8 py-3 rounded-xl bg-gradient-to-r from-red-500 to-red-600 text-white font-bold hover:shadow-lg transition-all hidden">
                    🔥 ¡Iniciar Destape Ahora!
                </button>
            </div>
        </div>

        <!-- Estado: DESTAPANDO -->
        <div id="state-revealing" class="hidden">
            <div class="glass-card p-6 md:p-8 text-center mb-6">
                <h2 class="text-xl md:text-2xl font-black text-amber-400 mb-2">🍺 ¡DESTAPE EN CURSO!</h2>
                <p class="text-slate-400 text-sm">Las tapas se están revelando una a una...</p>
            </div>
            <div id="revealing-stage" class="glass-card p-6 md:p-8 text-center mb-6 min-h-[300px] flex items-center justify-center">
                <!-- Animación de destape -->
                <div id="current-reveal" class="hidden w-full">
                    <p class="text-base md:text-lg text-slate-300 mb-4 uppercase tracking-widest">Destapando a: <br><span id="reveal-player-name" class="font-black text-white text-2xl md:text-3xl"></span></p>
                    <div id="reveal-bottle-container" class="flex justify-center mb-6 scale-90 md:scale-100">
                        <!-- Bottle animation -->
                    </div>
                    <div id="reveal-number-container" class="hidden">
                        <p class="text-xs text-slate-500 uppercase tracking-wider mb-2">Número obtenido</p>
                        <div id="reveal-number" class="tap-number"></div>
                    </div>
                </div>
            </div>
            <div id="revealed-players" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <!-- Resultados revelados -->
            </div>
        </div>

        <!-- Estado: FINALIZADO -->
        <div id="state-final" class="hidden">
            <div class="glass-card p-6 md:p-8 text-center mb-6">
                <h2 class="text-2xl md:text-3xl font-black text-amber-400 mb-2">🏆 ¡RESULTADOS!</h2>
                <p id="result-text" class="text-base md:text-lg text-slate-300"></p>
            </div>
            <div id="final-results" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <!-- Todos los resultados -->
            </div>
            <div class="text-center mt-8 flex flex-col sm:flex-row gap-3 justify-center">
                <a href="<?= BASE_PATH ?>/tapazo/index.php" class="inline-block w-full sm:w-auto px-8 py-4 rounded-xl bg-gradient-to-r from-amber-500 to-orange-600 text-white font-black text-lg hover:shadow-lg transition-all">
                    🍺 Crear Nuevo Tapazo
                </a>
                <a href="<?= BASE_PATH ?>/public/index.php" class="inline-block w-full sm:w-auto px-8 py-4 rounded-xl bg-white/5 border border-white/10 text-white font-bold text-lg hover:bg-white/10 transition-all">
                    Volver al inicio
                </a>
            </div>
        </div>
    </div>

    <script>
        const TAPAZO_CODIGO = '<?= $tapazo['codigo_unico'] ?>';
        const TAPAZO_ESTADO = '<?= $tapazo['estado'] ?>';
        let soundEnabled = true;

        function toggleSound() {
            soundEnabled = !soundEnabled;
            document.getElementById('sound-toggle').textContent = soundEnabled ? '🔊' : '🔇';
        }

        function playPopSound() {
            if (!soundEnabled) return;
            try {
                const audio = new Audio(BASE_PATH + '/recursos/Botella.mp3');
                audio.volume = 0.5;
                audio.play().catch(e => console.log('Sound play error:', e));
            } catch(e) { console.log('Sound error:', e); }
        }

        function playRevealSound() {
            if (!soundEnabled) return;
            try {
                const audio = new Audio(BASE_PATH + '/recursos/Botella.mp3');
                audio.volume = 0.6;
                audio.play().catch(e => console.log('Sound play error:', e));
            } catch(e) { console.log('Sound error:', e); }
        }

        const SHARE_URL = <?= json_encode($shareUrl) ?>;
        function copyLink() {
            const url = SHARE_URL || window.location.href;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(
                    () => alert('¡Link copiado! Compártelo con tus amigos.'),
                    () => window.prompt('Copia el link:', url)
                );
            } else {
                window.prompt('Copia el link:', url);
            }
        }

        // ===== JOIN FORM =====
        document.getElementById('join-form')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button');
            btn.disabled = true; btn.textContent = 'Procesando...';
            try {
                const res = await fetch(BASE_PATH + '/api/tapazo/unirse.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        codigo: TAPAZO_CODIGO,
                        nombre: document.getElementById('player-name').value,
                        cerveza_numero: parseInt(document.getElementById('selected-bottle').value) || 0
                    })
                });
                const json = await res.json();
                if (json.success) {
                    location.reload();
                } else {
                    alert(json.message || 'Error');
                }
            } catch(err) { alert('Error de conexión'); }
            finally { btn.disabled = false; btn.textContent = '¡Me apunto!'; }
        });

        // ===== SELECTOR DE BOTELLA =====
        async function loadBottleSelector() {
            const selector = document.getElementById('bottle-selector');
            const hiddenInput = document.getElementById('selected-bottle');
            const joinBtn = document.getElementById('join-btn');
            const nameInput = document.getElementById('player-name');
            if (!selector) return;

            try {
                const res = await fetch(BASE_PATH + '/api/tapazo/disponibles.php?codigo=' + TAPAZO_CODIGO);
                const json = await res.json();
                if (json.success && json.data.disponibles) {
                    selector.innerHTML = json.data.disponibles.map(n => 
                        `<div class="bottle-option" data-num="${n}">${n}</div>`
                    ).join('');
                    
                    selector.querySelectorAll('.bottle-option').forEach(opt => {
                        opt.addEventListener('click', () => {
                            selector.querySelectorAll('.bottle-option').forEach(o => o.classList.remove('selected'));
                            opt.classList.add('selected');
                            hiddenInput.value = opt.dataset.num;
                            joinBtn.disabled = !nameInput.value;
                        });
                    });
                }
            } catch(e) { console.error('Error loadBottleSelector:', e); }
        }

        // Habilitar botón cuando hay nombre y número seleccionado
        document.getElementById('player-name')?.addEventListener('input', (e) => {
            const joinBtn = document.getElementById('join-btn');
            const bottle = document.getElementById('selected-bottle');
            if (joinBtn && bottle) {
                joinBtn.disabled = !e.target.value || !bottle.value;
            }
        });

        // ===== FORCE START =====
        async function forceStartDestape() {
            if (!confirm('¿Iniciar el destape ahora?')) return;
            
            try {
                await fetch(BASE_PATH + '/api/tapazo/iniciar_destape.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ codigo: TAPAZO_CODIGO })
                });
                document.getElementById('state-joining').classList.add('hidden');
                document.getElementById('state-revealing').classList.remove('hidden');
                document.getElementById('countdown-section').classList.add('hidden');
                document.getElementById('btn-force-start').classList.add('hidden');
                startDestapePolling();
            } catch(e) { alert('Error'); }
        }

        // ===== RENDER BEERS =====
        function renderBeers(jugadores, total) {
            const grid = document.getElementById('beers-grid');
            if (!grid) return;
            grid.innerHTML = '';
            const takenSlots = new Map();
            jugadores.forEach(j => takenSlots.set(j.cerveza_numero, j));
            
            const width = window.innerWidth < 640 ? (total <= 10 ? 40 : 30) : (total <= 10 ? 55 : 45);

            for (let i = 1; i <= total; i++) {
                const jugador = takenSlots.get(i);
                const div = document.createElement('div');
                div.className = 'beer-bottle' + (jugador ? ' beer-bottle--taken' : '');
                div.style.cssText = `width:${width}px;flex-shrink:0;height:auto;`;
                div.innerHTML = `
                    <img src="<?= BASE_PATH ?>/recursos/aguila.png" style="width:100%;height:auto;max-width:${width}px;" alt="Cerveza #${i}">
                    <div class="text-center mt-1 font-black" style="color:#fbbf24;font-size:${Math.max(10, width/5)}px;">${i}</div>
                    ${jugador ? `<div class="text-center truncate w-full font-bold" style="color:#fff;font-size:${Math.max(8, width/6)}px;">${jugador.nombre}</div>` : ''}
                `;
                grid.appendChild(div);
            }
        }

        // ===== RENDER PLAYERS LIST =====
        function renderPlayersList(jugadores, total) {
            const list = document.getElementById('players-list');
            const count = document.getElementById('player-count');
            if (!list) return;
            if (count) count.textContent = jugadores.length;

            if (jugadores.length === 0) {
                list.innerHTML = '<p class="col-span-full text-slate-500 text-center py-4">Aún no hay jugadores. ¡Sé el primero!</p>';
                return;
            }

            list.innerHTML = jugadores.map(j => `
                <div class="flex items-center gap-3 p-3 bg-slate-800/50 rounded-xl border border-white/5">
                    <div class="w-10 h-10 rounded-full bg-amber-500/20 flex items-center justify-center text-amber-400 font-black text-sm border border-amber-500/20">
                        ${j.cerveza_numero}
                    </div>
                    <div class="flex-1">
                        <p class="font-bold text-white text-sm truncate">${j.nombre}</p>
                        <p class="text-[10px] text-slate-500 uppercase font-black">Cerveza #${j.cerveza_numero}</p>
                    </div>
                    <span class="text-[9px] font-black px-2 py-1 rounded-full bg-slate-700 text-slate-400">SELLADA</span>
                </div>
            `).join('');

            if (jugadores.length === total) {
                const countdownSection = document.getElementById("countdown-section");
                const joinFormContainer = document.getElementById("join-form-container");
                const forceStartBtn = document.getElementById("btn-force-start");

                if (countdownSection) countdownSection.classList.remove("hidden");
                if (joinFormContainer) joinFormContainer.classList.add("hidden");
                if (forceStartBtn) forceStartBtn.classList.remove("hidden");
            }
        }

        // ===== COUNTDOWN =====
        function updateCountdown(remaining) {
            if (remaining <= 0) return;
            const h = Math.floor(remaining / 3600);
            const m = Math.floor((remaining % 3600) / 60);
            const s = remaining % 60;
            const hEl = document.getElementById('cd-hours');
            const mEl = document.getElementById('cd-minutes');
            const sEl = document.getElementById('cd-seconds');
            if (hEl) hEl.textContent = String(h).padStart(2, '0');
            if (mEl) mEl.textContent = String(m).padStart(2, '0');
            if (sEl) sEl.textContent = String(s).padStart(2, '0');
        }

        // ===== SSE CONNECTION =====
        let eventSource = null;
        let revealedData = [];
        let destapeInterval = null;
        let revealedIds = [];
        const REVEAL_DELAY = 5000;

        function connectSSE() {
            eventSource = new EventSource(BASE_PATH + '/api/tapazo/destape.php?codigo=' + TAPAZO_CODIGO);

            eventSource.onmessage = function(e) {
                const data = JSON.parse(e.data);
                if (data.error) return;

                if (data.type === 'init') {
                    if (data.estado === 'destapando' || data.estado === 'finalizado') {
                        document.getElementById('state-joining').classList.add('hidden');
                        document.getElementById('state-revealing').classList.remove('hidden');
                        startDestapePolling();
                    }
                    if (['esperando', 'creado', 'lleno'].includes(data.estado)) {
                        document.getElementById('countdown-section').classList.remove('hidden');
                        document.getElementById('btn-force-start').classList.remove('hidden');
                        const destapeTime = new Date(data.fecha_destape).getTime();
                        const startCountdown = () => {
                            const remaining = Math.max(0, Math.floor((destapeTime - Date.now()) / 1000));
                            updateCountdown(remaining);
                            if (remaining > 0) setTimeout(startCountdown, 1000);
                        };
                        startCountdown();
                    }
                }

                if (data.type === 'countdown') updateCountdown(data.remaining);
                if (data.type === 'destape') revealPlayer(data);
                if (data.type === 'finalizado') {
                    stopDestapePolling();
                    showFinalResults(data.regla);
                }
            };

            eventSource.onerror = function() {
                eventSource.close();
                setTimeout(connectSSE, 3000);
            };
        }

        function startDestapePolling() {
            if (destapeInterval) return;
            destapeInterval = setInterval(async () => {
                try {
                    const res = await fetch(BASE_PATH + '/api/tapazo/siguiente.php?codigo=' + TAPAZO_CODIGO);
                    const json = await res.json();
                    if (json.success && json.data.siguiente) {
                        const j = json.data.siguiente;
                        if (!revealedIds.includes(j.id)) {
                            revealPlayer({
                                jugador_id: j.id,
                                nombre: j.nombre,
                                cerveza_numero: j.cerveza_numero,
                                numero_tapa: j.numero_tapa
                            });
                        }
                    }
                    if (json.success && json.data.finalizado) {
                        stopDestapePolling();
                        showFinalResults(json.data.regla);
                    }
                } catch(e) {}
            }, REVEAL_DELAY);
        }

        function stopDestapePolling() {
            if (destapeInterval) { clearInterval(destapeInterval); destapeInterval = null; }
        }

        function revealPlayer(data) {
            if (revealedIds.includes(data.jugador_id)) return;
            revealedIds.push(data.jugador_id);

            document.getElementById('state-joining').classList.add('hidden');
            document.getElementById('state-revealing').classList.remove('hidden');

            revealedData.push(data);

            const revealContainer = document.getElementById('current-reveal');
            const bottleContainer = document.getElementById('reveal-bottle-container');
            const numberContainer = document.getElementById('reveal-number-container');
            const playerName = document.getElementById('reveal-player-name');
            const revealNumber = document.getElementById('reveal-number');

            revealContainer.classList.remove('hidden');
            playerName.textContent = data.nombre;
            numberContainer.classList.add('hidden');

            bottleContainer.innerHTML = `
                <div class="cap-container">
                    <div class="cap-inner" id="cap-anim-${data.jugador_id}">
                        <div class="cap-front"></div>
                        <div class="cap-back">
                            <span class="cap-number-revealed">${String(data.numero_tapa).padStart(3, '0')}</span>
                        </div>
                    </div>
                </div>
            `;

            playPopSound();

            setTimeout(() => {
                const cap = document.getElementById('cap-anim-' + data.jugador_id);
                if (cap) {
                    cap.classList.add('cap-shaking');
                    setTimeout(() => {
                        cap.classList.add('flipping');
                        playRevealSound();
                    }, 400);
                }
            }, 500);

            setTimeout(() => {
                numberContainer.classList.remove('hidden');
                revealNumber.textContent = String(data.numero_tapa).padStart(3, '0');
                revealNumber.classList.add('number-reveal');
            }, 2000);

            setTimeout(() => {
                const revealedDiv = document.getElementById('revealed-players');
                const card = document.createElement('div');
                card.className = 'player-card flex items-center gap-4 border border-white/5';
                card.innerHTML = `
                    <div class="w-10 h-10 rounded-full bg-amber-500/20 flex items-center justify-center text-amber-400 font-bold text-sm">
                        ${data.cerveza_numero}
                    </div>
                    <div class="flex-1 truncate">
                        <span class="font-bold text-white text-sm">${data.nombre}</span>
                    </div>
                    <div class="tap-number" style="font-size:24px;">${String(data.numero_tapa).padStart(3, '0')}</div>
                `;
                revealedDiv.prepend(card);
                revealContainer.classList.add('hidden');
            }, 4000);
        }

        function showFinalResults(regla) {
            stopDestapePolling();
            document.getElementById('state-revealing').classList.add('hidden');
            document.getElementById('state-final').classList.remove('hidden');

            const sorted = [...revealedData].sort((a, b) => a.numero_tapa - b.numero_tapa);
            let winner, mensaje;
            
            if (regla === 'alto_gana') {
                winner = sorted[sorted.length - 1];
                mensaje = `GANA con el más ALTO`;
            } else {
                winner = sorted[0];
                mensaje = `GANA con el más BAJO`;
            }
            
            const resultText = document.getElementById('result-text');
            resultText.innerHTML = `<strong class="text-amber-400 uppercase">${winner.nombre}</strong> ${mensaje}: <strong class="text-amber-400">${String(winner.numero_tapa).padStart(3, '0')}</strong>`;

            const finalDiv = document.getElementById('final-results');
            finalDiv.innerHTML = sorted.map((p, i) => {
                const isWinner = p.jugador_id === winner.jugador_id;
                return `
                    <div class="player-card flex items-center gap-4 ${isWinner ? 'player-card--winner winner-glow' : ''}">
                        <div class="w-8 h-8 rounded-full ${isWinner ? 'bg-amber-500' : 'bg-slate-700'} flex items-center justify-center text-white font-bold text-xs">
                            ${i + 1}
                        </div>
                        <div class="flex-1 truncate">
                            <span class="font-bold text-white text-sm">${p.nombre}</span>
                            <span class="text-[10px] text-slate-500 block uppercase font-black">Cerveza #${p.cerveza_numero}</span>
                        </div>
                        <div class="tap-number" style="font-size:20px;">${String(p.numero_tapa).padStart(3, '0')}</div>
                        ${isWinner ? '<span>🏆</span>' : ''}
                    </div>
                `;
            }).join('');
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadBottleSelector();
            <?php
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT * FROM tapazo_jugadores WHERE tapazo_id = ? ORDER BY cerveza_numero ASC");
            $stmt->execute([$tapazo['id']]);
            $jugadores = $stmt->fetchAll();
            ?>
            const initialJugadores = <?= json_encode($jugadores) ?>;
            renderBeers(initialJugadores, <?= $tapazo['cantidad_jugadores'] ?>);
            renderPlayersList(initialJugadores, <?= $tapazo['cantidad_jugadores'] ?>);

            const estadoActual = '<?= $tapazo['estado'] ?>';
            if (estadoActual === 'finalizado') {
                fetch(BASE_PATH + '/api/tapazo/info.php?codigo=<?= $tapazo['codigo_unico'] ?>')
                    .then(r => r.json()).then(json => {
                        if (json.success) {
                            revealedData = json.data.jugadores.map(j => ({
                                jugador_id: j.id, nombre: j.nombre, cerveza_numero: j.cerveza_numero, numero_tapa: j.numero_tapa
                            }));
                            showFinalResults('<?= $tapazo['regla'] ?>');
                        }
                    });
            } else {
                connectSSE();
            }
        });
    </script>
    <?php endif; ?>
<?php $tabActive = 'tapazo'; include __DIR__ . '/../public/partials/tabbar.php'; ?>
</body>
</html>
