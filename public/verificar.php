<?php
/**
 * Pantalla de verificación de cuenta (OTP).
 *
 * WhatsApp (recomendado): botón wa.me con VERIFY-XXXXX prellenado hacia el
 * número de la plataforma; la página hace polling y avanza sola al validar.
 * Correo: se envía un código y se digita aquí.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/brand.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifica tu cuenta | <?= plataforma_e() ?></title>
    <meta name="theme-color" content="#0f172a">
    <script>const BASE_PATH = "<?= BASE_PATH ?>";</script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { min-height:100vh; background:#0f172a; color:#e2e8f0; font-family:system-ui,-apple-system,'Segoe UI',sans-serif; display:flex; align-items:center; justify-content:center; padding:20px; }
        .card { width:100%; max-width:430px; background:#1e293b; border:1px solid rgba(255,255,255,.08); border-radius:20px; padding:28px 24px; box-shadow:0 20px 60px rgba(0,0,0,.4); }
        h1 { font-size:22px; color:#fff; margin-bottom:10px; }
        p { color:#94a3b8; font-size:14.5px; line-height:1.55; }
        .phone { color:#fbbf24; font-weight:700; white-space:nowrap; }
        .code-box { text-align:center; margin:18px 0; }
        .code { display:inline-block; background:#0f172a; color:#fbbf24; font-size:24px; font-weight:800; letter-spacing:2px; padding:12px 22px; border-radius:12px; border:1px dashed rgba(251,191,36,.4); }
        .btn { display:block; width:100%; text-align:center; padding:14px; border:none; border-radius:12px; font-size:15px; font-weight:700; cursor:pointer; text-decoration:none; transition:transform .1s, opacity .2s; }
        .btn:active { transform:scale(.98); }
        .btn-wa { background:#22c55e; color:#052e13; margin-top:6px; }
        .btn-mail { background:rgba(255,255,255,.08); color:#e2e8f0; border:1px solid rgba(255,255,255,.12); margin-top:10px; }
        .btn-primary { background:linear-gradient(135deg,#f59e0b,#d97706); color:#1c1305; margin-top:10px; }
        .waiting { display:none; align-items:center; gap:10px; justify-content:center; margin-top:16px; color:#94a3b8; font-size:13.5px; }
        .spinner { width:16px; height:16px; border:2px solid rgba(148,163,184,.3); border-top-color:#fbbf24; border-radius:50%; animation:spin 1s linear infinite; flex-shrink:0; }
        @keyframes spin { to { transform:rotate(360deg); } }
        .divider { display:flex; align-items:center; gap:12px; margin:20px 0 6px; color:#475569; font-size:12px; text-transform:uppercase; letter-spacing:1px; }
        .divider::before, .divider::after { content:''; flex:1; height:1px; background:rgba(255,255,255,.08); }
        input[type=text], input[type=tel] { width:100%; padding:13px 14px; border-radius:12px; background:#0f172a; border:1px solid rgba(255,255,255,.12); color:#fff; font-size:16px; text-align:center; letter-spacing:2px; outline:none; }
        input:focus { border-color:#f59e0b; }
        .hidden { display:none !important; }
        .foot { margin-top:18px; text-align:center; font-size:13px; }
        .foot a { color:#94a3b8; text-decoration:none; }
        .foot a:hover { color:#fbbf24; }
        .msg { margin-top:10px; font-size:13px; text-align:center; border-radius:10px; padding:10px; display:none; }
        .msg--err { display:block; background:rgba(239,68,68,.12); color:#fca5a5; }
        .msg--ok { display:block; background:rgba(34,197,94,.12); color:#86efac; }
        .success { text-align:center; padding:24px 0; }
        .success .big { font-size:52px; margin-bottom:10px; }
    </style>
</head>
<body>
<div class="card" id="card">
    <div id="main-view">
        <h1 id="title">¡Ya casi! 📱</h1>
        <p>Para activar tu cuenta y proteger la comunidad de perfiles falsos, confirma que el número <span class="phone" id="phone-label">—</span> es tuyo.</p>

        <div class="code-box hidden" id="code-box">
            <span class="code" id="code-label">VERIFY-·····</span>
        </div>

        <button class="btn btn-wa" id="btn-wa">💬 Verificar por WhatsApp</button>
        <p style="font-size:12px;color:#64748b;text-align:center;margin-top:6px;">Se abre tu WhatsApp con el código listo — solo pulsa <em>enviar</em>.</p>

        <div class="waiting" id="waiting"><span class="spinner"></span> Esperando tu confirmación… te redirigimos automáticamente al validarla.</div>

        <div class="divider">o por correo</div>
        <button class="btn btn-mail" id="btn-mail">✉️ Enviarme el código al correo</button>
        <div id="mail-form" class="hidden" style="margin-top:12px;">
            <p style="font-size:13px;margin-bottom:8px;text-align:center;" id="mail-sent-label">Te enviamos un código a tu correo.</p>
            <input type="text" id="mail-code" placeholder="VERIFY-XXXXX" autocomplete="one-time-code" maxlength="12">
            <button class="btn btn-primary" id="btn-verify-mail">Activar mi cuenta</button>
        </div>

        <div class="msg" id="msg"></div>

        <div class="foot">
            <a href="#" id="wrong-number">← ¿Número equivocado? Corrígelo aquí</a>
        </div>
        <div id="phone-form" class="hidden" style="margin-top:12px;">
            <input type="tel" id="new-phone" placeholder="Nuevo celular (3001234567)" maxlength="10">
            <button class="btn btn-primary" id="btn-save-phone">Guardar y continuar</button>
        </div>
    </div>

    <div id="success-view" class="success hidden">
        <div class="big">🎉</div>
        <h1>¡Cuenta verificada!</h1>
        <p style="margin-top:8px;">Bienvenido a <?= plataforma_e() ?>. Entrando a tu panel…</p>
    </div>
</div>

<script>
(function () {
    const token = localStorage.getItem('misrifas_token');
    let user = {};
    try { user = JSON.parse(localStorage.getItem('misrifas_user') || '{}'); } catch (e) {}
    if (!token) { window.location.href = BASE_PATH + '/public/index.php'; return; }

    const $ = id => document.getElementById(id);
    const api = (path, opts = {}) => fetch(BASE_PATH + '/api/auth/otp/' + path, {
        headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
        ...opts,
    }).then(r => r.json());

    const firstName = (user.full_name || user.username || '').trim().split(/\s+/)[0];
    if (firstName) $('title').textContent = '¡Ya casi, ' + firstName + '! 📱';
    if (user.phone) $('phone-label').textContent = '+57 ' + String(user.phone).replace(/(\d{3})(\d{3})(\d{4})/, '$1 $2 $3');

    let pollTimer = null;
    function showMsg(text, ok) {
        const m = $('msg');
        m.textContent = text;
        m.className = 'msg ' + (ok ? 'msg--ok' : 'msg--err');
    }
    function onVerified() {
        clearInterval(pollTimer);
        user.verified = true;
        try { localStorage.setItem('misrifas_user', JSON.stringify(user)); } catch (e) {}
        $('main-view').classList.add('hidden');
        $('success-view').classList.remove('hidden');
        setTimeout(() => window.location.href = BASE_PATH + '/public/vendor/index.php', 1600);
    }
    function startPolling() {
        $('waiting').style.display = 'flex';
        clearInterval(pollTimer);
        pollTimer = setInterval(async () => {
            try {
                const r = await api('status.php');
                if (r.success && r.data && r.data.verified) onVerified();
            } catch (e) {}
        }, 3000);
    }

    // Si ya está verificada (p. ej. volvió a esta página), pasar directo.
    api('status.php').then(r => { if (r.success && r.data && r.data.verified) onVerified(); });

    $('btn-wa').addEventListener('click', async () => {
        try {
            const r = await api('start.php', { method: 'POST', body: JSON.stringify({ channel: 'whatsapp' }) });
            if (!r.success) { showMsg(r.message || 'No se pudo iniciar la verificación', false); return; }
            if (r.data.verified) { onVerified(); return; }
            $('code-label').textContent = r.data.code;
            $('code-box').classList.remove('hidden');
            window.open(r.data.wa_link, '_blank');
            startPolling();
        } catch (e) { showMsg('Error de conexión. Intenta de nuevo.', false); }
    });

    $('btn-mail').addEventListener('click', async () => {
        const btn = $('btn-mail');
        btn.disabled = true; btn.textContent = 'Enviando…';
        try {
            const r = await api('start.php', { method: 'POST', body: JSON.stringify({ channel: 'email' }) });
            if (!r.success) { showMsg(r.message || 'No se pudo enviar el código', false); return; }
            if (r.data.verified) { onVerified(); return; }
            $('mail-form').classList.remove('hidden');
            $('mail-sent-label').textContent = r.data.email_sent
                ? 'Te enviamos un código a ' + (r.data.email_masked || 'tu correo') + '. Revisa también el spam.'
                : 'No pudimos enviar el correo en este momento. Intenta por WhatsApp o pide el código de nuevo en unos minutos.';
            $('mail-code').focus();
        } catch (e) { showMsg('Error de conexión. Intenta de nuevo.', false); }
        finally { btn.disabled = false; btn.textContent = '✉️ Reenviar el código al correo'; }
    });

    $('btn-verify-mail').addEventListener('click', async () => {
        const code = $('mail-code').value.trim();
        if (!code) { showMsg('Escribe el código que te llegó al correo', false); return; }
        try {
            const r = await api('verify.php', { method: 'POST', body: JSON.stringify({ code }) });
            if (r.success && r.data && r.data.verified) { onVerified(); return; }
            showMsg(r.message || 'Código incorrecto', false);
        } catch (e) { showMsg('Error de conexión. Intenta de nuevo.', false); }
    });

    $('wrong-number').addEventListener('click', (e) => {
        e.preventDefault();
        $('phone-form').classList.toggle('hidden');
        $('new-phone').focus();
    });

    $('btn-save-phone').addEventListener('click', async () => {
        const phone = $('new-phone').value.replace(/\D+/g, '');
        try {
            const r = await api('update_phone.php', { method: 'POST', body: JSON.stringify({ phone }) });
            if (!r.success) { showMsg(r.message || 'No se pudo actualizar', false); return; }
            user.phone = r.data.phone;
            try { localStorage.setItem('misrifas_user', JSON.stringify(user)); } catch (e2) {}
            $('phone-label').textContent = '+57 ' + r.data.phone.replace(/(\d{3})(\d{3})(\d{4})/, '$1 $2 $3');
            $('phone-form').classList.add('hidden');
            $('code-box').classList.add('hidden');
            showMsg('Teléfono actualizado ✅ — vuelve a pulsar "Verificar por WhatsApp"', true);
        } catch (e) { showMsg('Error de conexión. Intenta de nuevo.', false); }
    });
})();
</script>
</body>
</html>
