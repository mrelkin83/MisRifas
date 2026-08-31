<?php
/**
 * Confirmación de entrega del premio por el GANADOR (promt2.md §13.4).
 * Enlace tokenizado, sin login. Distinto de ganador-confirmar.php (aceptación).
 */
require_once __DIR__ . '/../config/app.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar entrega del premio | MisRifas</title>
    <meta name="theme-color" content="#0f172a">
    <meta name="robots" content="noindex">
    <script>const BASE_PATH = "<?= BASE_PATH ?>";</script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { min-height:100vh; background:#0f172a; color:#e2e8f0; font-family:system-ui,-apple-system,'Segoe UI',sans-serif; display:flex; align-items:center; justify-content:center; padding:20px; }
        .card { width:100%; max-width:440px; background:#1e293b; border:1px solid rgba(255,255,255,.08); border-radius:20px; padding:26px 22px; }
        h1 { font-size:20px; color:#fff; margin-bottom:8px; }
        p { color:#94a3b8; font-size:14.5px; line-height:1.55; }
        .premio { background:#0f172a; border-radius:12px; padding:14px; margin:16px 0; font-size:14.5px; }
        .btn { display:block; width:100%; margin-top:10px; padding:14px; border:none; border-radius:12px; font-size:15px; font-weight:800; cursor:pointer; }
        .btn-ok { background:#22c55e; color:#052e13; }
        .btn-no { background:rgba(239,68,68,.15); color:#f87171; border:1px solid rgba(239,68,68,.4); }
        textarea { width:100%; margin-top:10px; padding:12px; border-radius:12px; background:#0f172a; border:1px solid rgba(255,255,255,.12); color:#fff; font-size:14px; min-height:90px; resize:vertical; }
        .file-label { display:block; margin-top:12px; padding:12px; border:2px dashed rgba(255,255,255,.15); border-radius:12px; text-align:center; font-size:13px; color:#94a3b8; cursor:pointer; }
        .hidden { display:none !important; }
        .msg { margin-top:12px; padding:12px; border-radius:10px; font-size:13.5px; text-align:center; display:none; }
        .msg--ok { display:block; background:rgba(34,197,94,.12); color:#86efac; }
        .msg--err { display:block; background:rgba(239,68,68,.12); color:#fca5a5; }
    </style>
</head>
<body>
<div class="card">
    <div id="loading"><p>Cargando…</p></div>
    <div id="main" class="hidden">
        <h1>📦 ¿Recibiste tu premio?</h1>
        <p>El organizador <strong id="vendor-name"></strong> reporta que te entregó el premio. Tu confirmación queda pública en el hall de ganadores y protege a los próximos compradores.</p>
        <div class="premio">
            🏆 <strong id="raffle-name"></strong><br>
            <span style="color:#94a3b8;font-size:13px;">Boleto ganador #<span id="ticket-number"></span> · <span id="winner-name"></span></span>
        </div>

        <!-- Evidencia OBLIGATORIA que subió el organizador al reportar -->
        <div id="vendor-evidence" class="hidden" style="margin:14px 0;">
            <p style="font-size:12px;color:#94a3b8;text-transform:uppercase;font-weight:700;letter-spacing:.5px;margin-bottom:6px;">📷 Evidencia de entrega del organizador</p>
            <img id="vendor-photo" alt="Evidencia de la entrega subida por el organizador" style="width:100%;border-radius:12px;border:1px solid rgba(255,255,255,.1);">
        </div>

        <label class="file-label" for="photo">📷 Foto recibiendo el premio (opcional)<input type="file" id="photo" accept="image/*" style="display:none;"></label>
        <p id="photo-name" style="font-size:12px;color:#4ade80;text-align:center;margin-top:6px;"></p>

        <button class="btn btn-ok" id="btn-confirm">✅ Sí, recibí mi premio</button>
        <button class="btn btn-no" id="btn-dispute-toggle">❌ No lo he recibido</button>
        <div id="dispute-form" class="hidden">
            <textarea id="dispute-reason" placeholder="Cuéntanos qué pasó (ej: quedamos en encontrarnos y no llegó)…"></textarea>
            <button class="btn btn-no" id="btn-dispute">Enviar reporte</button>
        </div>
        <p class="msg" id="msg"></p>
    </div>
    <div id="done" class="hidden" style="text-align:center;padding:18px 0;">
        <div style="font-size:52px;margin-bottom:10px;" id="done-icon">🎉</div>
        <h1 id="done-title"></h1>
        <p id="done-text" style="margin-top:8px;"></p>
        <p style="margin-top:16px;"><a href="<?= BASE_PATH ?>/public/ganadores.php" style="color:#fbbf24;">Ver el hall de ganadores →</a></p>
    </div>
</div>
<script>
(function () {
    const token = new URLSearchParams(location.search).get('t') || '';
    const $ = id => document.getElementById(id);
    let photoData = null;

    function showMsg(t, ok) { const m = $('msg'); m.textContent = t; m.className = 'msg ' + (ok ? 'msg--ok' : 'msg--err'); }
    function finish(icon, title, text) {
        $('main').classList.add('hidden');
        $('done').classList.remove('hidden');
        $('done-icon').textContent = icon;
        $('done-title').textContent = title;
        $('done-text').textContent = text;
    }

    fetch(BASE_PATH + '/api/winners/delivery.php?t=' + encodeURIComponent(token))
        .then(r => r.json())
        .then(r => {
            $('loading').classList.add('hidden');
            if (!r.success) {
                finish('🔗', 'Enlace no disponible', r.message || 'El enlace es inválido, ya fue usado o venció.');
                return;
            }
            $('main').classList.remove('hidden');
            $('raffle-name').textContent = r.data.raffle_name;
            $('ticket-number').textContent = r.data.ticket_number;
            $('winner-name').textContent = r.data.winner_name;
            $('vendor-name').textContent = r.data.vendor_name;
            if (r.data.vendor_photo) {
                $('vendor-photo').src = r.data.vendor_photo;
                $('vendor-evidence').classList.remove('hidden');
            }
        })
        .catch(() => { $('loading').classList.add('hidden'); finish('⚠️', 'Error de conexión', 'Intenta de nuevo en unos minutos.'); });

    $('photo').addEventListener('change', function () {
        const f = this.files[0];
        if (!f) return;
        const reader = new FileReader();
        reader.onload = e => { photoData = e.target.result; $('photo-name').textContent = '✓ ' + f.name; };
        reader.readAsDataURL(f);
    });

    async function post(body) {
        const r = await fetch(BASE_PATH + '/api/winners/delivery.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({ token }, body)),
        });
        return r.json();
    }

    $('btn-confirm').addEventListener('click', async function () {
        this.disabled = true; this.textContent = 'Confirmando…';
        try {
            const r = await post({ action: 'confirm', photo: photoData });
            if (r.success) { finish('🎉', '¡Entrega confirmada!', r.message); return; }
            showMsg(r.message || 'No se pudo confirmar', false);
        } catch (e) { showMsg('Error de conexión', false); }
        this.disabled = false; this.textContent = '✅ Sí, recibí mi premio';
    });

    $('btn-dispute-toggle').addEventListener('click', () => $('dispute-form').classList.toggle('hidden'));

    $('btn-dispute').addEventListener('click', async function () {
        const reason = $('dispute-reason').value.trim();
        if (reason.length < 5) { showMsg('Cuéntanos brevemente qué pasó.', false); return; }
        this.disabled = true; this.textContent = 'Enviando…';
        try {
            const r = await post({ action: 'dispute', reason });
            if (r.success) { finish('⚠️', 'Reporte enviado', r.message); return; }
            showMsg(r.message || 'No se pudo enviar', false);
        } catch (e) { showMsg('Error de conexión', false); }
        this.disabled = false; this.textContent = 'Enviar reporte';
    });
})();
</script>
</body>
</html>
