<?php
/**
 * Comprobador de boletas (promt2.md §9.5): digitar el código o leer el QR con
 * la cámara (BarcodeDetector nativo del navegador; si no está disponible, el
 * campo manual siempre funciona).
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/brand.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar boleta | <?= plataforma_e() ?></title>
    <meta name="theme-color" content="#0f172a">
    <script>const BASE_PATH = "<?= BASE_PATH ?>";</script>
    <style>
        @font-face {
            font-family: 'Outfit';
            font-style: normal;
            font-weight: 800;
            font-display: swap;
            src: url('<?= BASE_PATH ?>/public/assets/fonts/outfit-800.woff2') format('woff2');
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        html { color-scheme:dark; }
        body { min-height:100vh; background:linear-gradient(135deg,#1e293b 0%,#0f172a 100%); color:#e2e8f0;
               font-family:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif; display:flex; flex-direction:column; }
        .topbar { position:sticky; top:0; z-index:50; background:rgba(15,23,42,.7); backdrop-filter:blur(12px);
                  -webkit-backdrop-filter:blur(12px); border-bottom:1px solid rgba(255,255,255,.1); }
        .topbar-in { max-width:1100px; margin:0 auto; padding:0 16px; height:72px; display:flex; align-items:center; justify-content:space-between; }
        .brand { font-family:'Outfit','Inter',sans-serif; font-weight:800; font-size:22px; color:#fff; text-decoration:none; letter-spacing:-.5px; }
        .brand span { color:#f59e0b; }
        .topbar a.back { color:#94a3b8; font-size:13px; font-weight:700; text-decoration:none; }
        .topbar a.back:hover { color:#fff; }
        .stage { flex:1; display:flex; align-items:center; justify-content:center; padding:24px 16px calc(24px + env(safe-area-inset-bottom,0px)); }
        .card { width:100%; max-width:440px; background:rgba(30,41,59,.8); backdrop-filter:blur(12px);
                border:1px solid rgba(255,255,255,.06); border-radius:24px; padding:28px 22px; }
        .badge { display:inline-block; padding:4px 12px; border-radius:99px; background:rgba(245,158,11,.12);
                 border:1px solid rgba(245,158,11,.3); color:#fbbf24; font-size:11px; font-weight:800;
                 text-transform:uppercase; letter-spacing:.8px; margin-bottom:12px; }
        h1 { font-family:'Outfit','Inter',sans-serif; font-size:26px; color:#fff; margin-bottom:8px; text-wrap:balance; }
        p { color:#94a3b8; font-size:14px; line-height:1.5; }
        input { width:100%; margin-top:18px; padding:16px 14px; border-radius:14px; background:rgba(15,23,42,.7);
                border:1px solid rgba(255,255,255,.12); color:#4ade80; font-family:ui-monospace,monospace; font-size:19px;
                font-weight:700; text-align:center; letter-spacing:3px; text-transform:uppercase; outline:none; transition:border-color .2s, box-shadow .2s; }
        input:focus { border-color:#f59e0b; box-shadow:0 0 0 4px rgba(245,158,11,.15); }
        .btn { display:block; width:100%; margin-top:12px; padding:15px; border:none; border-radius:14px; font-size:15px;
               font-weight:800; cursor:pointer; transition:transform .15s ease, box-shadow .2s ease; }
        .btn:active { transform:scale(.97); }
        .btn-go { background:linear-gradient(135deg,#f59e0b,#d97706); color:#1c1305; box-shadow:0 8px 20px rgba(217,119,6,.3); }
        .btn-go:hover { box-shadow:0 12px 28px rgba(217,119,6,.45); }
        .btn-cam { background:rgba(255,255,255,.06); color:#e2e8f0; border:1px solid rgba(255,255,255,.12); }
        .btn-cam:hover { background:rgba(255,255,255,.12); }
        video { width:100%; border-radius:14px; margin-top:12px; display:none; }
        .msg { display:none; margin-top:10px; font-size:13px; text-align:center; color:#fca5a5; }
        .foot { margin-top:18px; text-align:center; font-size:13px; }
        .foot a { color:#94a3b8; text-decoration:none; }
        .foot a:hover { color:#fff; }
    </style>
</head>
<body>
<header class="topbar">
    <div class="topbar-in">
        <a class="brand" href="<?= BASE_PATH ?>/public/index.php">MIS<span>RIFAS</span></a>
        <a class="back" href="<?= BASE_PATH ?>/public/index.php">← Volver al inicio</a>
    </div>
</header>
<div class="stage">
<div class="card">
    <span class="badge">Verificación de autenticidad</span>
    <h1>🎟️ Verificar boleta</h1>
    <p>Digita el código de la boleta (formato <strong>XXXX-XXXX-XXXX</strong>) o escanea su QR con la cámara.</p>
    <input type="text" id="code" placeholder="XXXX-XXXX-XXXX" maxlength="14" autocomplete="off" autocapitalize="characters" aria-label="Código de la boleta">
    <button class="btn btn-go" onclick="go()">Verificar</button>
    <button class="btn btn-cam" id="cam-btn" onclick="scan()">📷 Escanear QR</button>
    <video id="video" playsinline muted></video>
    <p class="msg" id="msg" aria-live="polite"></p>
    <div class="foot"><a href="<?= BASE_PATH ?>/public/ganadores.php">🏆 Ver el hall de ganadores</a></div>
</div>
</div>
<script>
function normalize(v) {
    return v.toUpperCase().replace(/[^A-Z0-9]/g, '')
        .replace(/[IL]/g, '1').replace(/O/g, '0').replace(/U/g, 'V');
}
function go() {
    const c = normalize(document.getElementById('code').value);
    const msg = document.getElementById('msg');
    if (c.length !== 12) {
        msg.textContent = 'El código debe tener 12 caracteres (sin contar los guiones).';
        msg.style.display = 'block';
        return;
    }
    const fmt = c.slice(0, 4) + '-' + c.slice(4, 8) + '-' + c.slice(8);
    window.location.href = BASE_PATH + '/public/boleta.php?c=' + fmt;
}
document.getElementById('code').addEventListener('keydown', e => { if (e.key === 'Enter') go(); });

// Formateo en vivo XXXX-XXXX-XXXX
document.getElementById('code').addEventListener('input', function () {
    const raw = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 12);
    this.value = raw.replace(/(.{4})(?=.)/g, '$1-');
});

let stream = null;
async function scan() {
    const msg = document.getElementById('msg');
    const video = document.getElementById('video');
    if (!('BarcodeDetector' in window)) {
        msg.textContent = 'Tu navegador no soporta el escáner; digita el código manualmente.';
        msg.style.display = 'block';
        return;
    }
    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        video.srcObject = stream;
        video.style.display = 'block';
        await video.play();
        const detector = new BarcodeDetector({ formats: ['qr_code'] });
        const tick = async () => {
            if (!stream) return;
            try {
                const codes = await detector.detect(video);
                if (codes.length) {
                    const value = codes[0].rawValue || '';
                    stopCam();
                    // El QR contiene la URL de la boleta: navegar directo; si es
                    // solo un código, comprobarlo.
                    if (/boleta\.php\?c=/.test(value)) { window.location.href = value; return; }
                    document.getElementById('code').value = value;
                    go();
                    return;
                }
            } catch (e) {}
            requestAnimationFrame(tick);
        };
        tick();
    } catch (e) {
        msg.textContent = 'No se pudo abrir la cámara. Digita el código manualmente.';
        msg.style.display = 'block';
    }
}
function stopCam() {
    if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
    document.getElementById('video').style.display = 'none';
}
window.addEventListener('pagehide', stopCam);
</script>
<?php $tabActive = 'boletas'; include __DIR__ . '/partials/tabbar.php'; ?>
</body>
</html>
