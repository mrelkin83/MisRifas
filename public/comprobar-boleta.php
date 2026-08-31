<?php
/**
 * Comprobador de boletas (promt2.md §9.5): digitar el código o leer el QR con
 * la cámara (BarcodeDetector nativo del navegador; si no está disponible, el
 * campo manual siempre funciona).
 */
require_once __DIR__ . '/../config/app.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobar boleta | MisRifas</title>
    <meta name="theme-color" content="#0f172a">
    <script>const BASE_PATH = "<?= BASE_PATH ?>";</script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { min-height:100vh; background:#0f172a; color:#e2e8f0; font-family:system-ui,-apple-system,'Segoe UI',sans-serif; display:flex; align-items:center; justify-content:center; padding:20px; }
        .card { width:100%; max-width:420px; background:#1e293b; border:1px solid rgba(255,255,255,.08); border-radius:20px; padding:26px 22px; }
        h1 { font-size:20px; color:#fff; margin-bottom:8px; }
        p { color:#94a3b8; font-size:14px; line-height:1.5; }
        input { width:100%; margin-top:16px; padding:14px; border-radius:12px; background:#0f172a; border:1px solid rgba(255,255,255,.12); color:#4ade80; font-family:ui-monospace,monospace; font-size:18px; font-weight:700; text-align:center; letter-spacing:2px; text-transform:uppercase; outline:none; }
        input:focus { border-color:#f59e0b; }
        .btn { display:block; width:100%; margin-top:12px; padding:14px; border:none; border-radius:12px; font-size:15px; font-weight:800; cursor:pointer; }
        .btn-go { background:linear-gradient(135deg,#f59e0b,#d97706); color:#1c1305; }
        .btn-cam { background:rgba(255,255,255,.08); color:#e2e8f0; border:1px solid rgba(255,255,255,.12); }
        video { width:100%; border-radius:12px; margin-top:12px; display:none; }
        .msg { display:none; margin-top:10px; font-size:13px; text-align:center; color:#fca5a5; }
        .foot { margin-top:16px; text-align:center; font-size:13px; }
        .foot a { color:#94a3b8; }
    </style>
</head>
<body>
<div class="card">
    <h1>🎟️ Comprobar boleta</h1>
    <p>Digita el código de la boleta (formato <strong>XXXX-XXXX-XXXX</strong>) o escanea su QR con la cámara.</p>
    <input type="text" id="code" placeholder="XXXX-XXXX-XXXX" maxlength="14" autocomplete="off" autocapitalize="characters">
    <button class="btn btn-go" onclick="go()">Comprobar</button>
    <button class="btn btn-cam" id="cam-btn" onclick="scan()">📷 Escanear QR</button>
    <video id="video" playsinline muted></video>
    <p class="msg" id="msg"></p>
    <div class="foot"><a href="<?= BASE_PATH ?>/public/index.php">← Volver a MisRifas</a></div>
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
</body>
</html>
