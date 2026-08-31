<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/brand.php';
$page_title = "Confirmar premio - " . plataforma('nombre');
$token = preg_match('/^[a-f0-9]{16,64}$/', $_GET['t'] ?? '') ? $_GET['t'] : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <script>const BASE_PATH = "<?= BASE_PATH ?>";</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <meta name="theme-color" content="#0f172a">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/public/css/tailwind.min.css">
    <style>
        html { color-scheme: dark; }
        body { background:#0f172a; color:#f8fafc; font-family:'Inter',sans-serif; margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
        .card { background:rgba(30,41,59,.6); border:1px solid rgba(255,255,255,.08); border-radius:24px; max-width:520px; width:100%; padding:32px; }
        .num { font-size:2.5rem; font-weight:900; color:#22c55e; letter-spacing:.05em; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; border-radius:12px; padding:14px 20px; font-weight:800; cursor:pointer; border:none; font-size:15px; transition:filter .15s, opacity .15s; width:100%; }
        .btn:disabled { opacity:.5; cursor:not-allowed; }
        .btn-accept { background:linear-gradient(135deg,#22c55e,#16a34a); color:#052e13; }
        .btn-decline { background:transparent; border:1px solid rgba(255,255,255,.2); color:#e2e8f0; margin-top:10px; }
        .btn-accept:hover { filter:brightness(1.08); }
        .muted { color:#94a3b8; }
        .badge { display:inline-flex; align-items:center; gap:6px; border-radius:999px; padding:6px 14px; font-weight:700; font-size:14px; }
        .badge-ok { background:rgba(34,197,94,.15); color:#4ade80; }
        .badge-no { background:rgba(148,163,184,.15); color:#cbd5e1; }
    </style>
</head>
<body>
    <div class="card" id="card">
        <div id="loading" class="text-center muted">Cargando tu premio…</div>
        <div id="content" style="display:none"></div>
    </div>

<script>
const token = <?= json_encode($token) ?>;
const $ = (id) => document.getElementById(id);
const esc = (s) => { const d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };

function fmtDate(d) { try { return new Date(d).toLocaleDateString('es-CO', { year:'numeric', month:'long', day:'numeric' }); } catch (e) { return d || ''; } }

function renderDecided(w) {
    const ok = w.status === 'accepted';
    return `
        <div class="text-center">
            <div class="badge ${ok ? 'badge-ok' : 'badge-no'} mb-4">${ok ? '✓ Premio aceptado' : 'Premio rechazado'}</div>
            <h1 class="text-2xl font-black mb-1">${ok ? '¡Gracias, ' : 'Registrado, '}${esc(w.winner_name) || 'ganador'}!</h1>
            <p class="muted mb-6">${ok
                ? 'Tu aceptación quedó registrada públicamente. El organizador te contactará para la entrega.'
                : 'Registramos que rechazaste el premio. Si fue un error, contacta al organizador.'}</p>
            <div class="rounded-2xl p-5" style="background:rgba(15,23,42,.5)">
                <p class="muted text-sm mb-1">${esc(w.raffle_name)}</p>
                <p class="text-sm">Lotería ${esc(w.lottery_name)} · ${fmtDate(w.draw_date)}</p>
                <p class="mt-3 text-sm muted">Número ganador</p>
                <div class="num">${esc(w.winning_number)}</div>
                ${w.ticket_number ? `<p class="muted text-sm mt-2">Tu boleto: <strong>${esc(w.ticket_number)}</strong></p>` : ''}
            </div>
        </div>`;
}

function renderPending(w) {
    return `
        <div class="text-center mb-6">
            <div class="text-5xl mb-2">🏆</div>
            <h1 class="text-2xl font-black mb-1">¡Felicitaciones${w.winner_name ? ', ' + esc(w.winner_name) : ''}!</h1>
            <p class="muted">Ganaste la rifa. Confirma que aceptas tu premio y el resultado del sorteo.</p>
        </div>
        <div class="rounded-2xl p-5 mb-6 text-center" style="background:rgba(15,23,42,.5)">
            <p class="muted text-sm mb-1">${esc(w.raffle_name)}</p>
            <p class="text-sm">Lotería ${esc(w.lottery_name)} · ${fmtDate(w.draw_date)}</p>
            <p class="mt-3 text-sm muted">Número ganador</p>
            <div class="num">${esc(w.winning_number)}</div>
            ${w.ticket_number ? `<p class="muted text-sm mt-2">Tu boleto: <strong>${esc(w.ticket_number)}</strong></p>` : ''}
        </div>
        <button class="btn btn-accept" id="btn-accept">✓ Acepto el premio y el resultado</button>
        <button class="btn btn-decline" id="btn-decline">Rechazar</button>
        <p class="muted text-xs text-center mt-4">Al aceptar dejas constancia pública y verificable de tu conformidad.</p>`;
}

async function decide(action) {
    $('btn-accept').disabled = true;
    $('btn-decline').disabled = true;
    try {
        const res = await fetch(BASE_PATH + '/api/winners/accept.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token, action })
        });
        const data = await res.json();
        if (data.success && data.winner) {
            $('content').innerHTML = renderDecided(data.winner);
        } else {
            alert(data.message || 'No se pudo registrar. Intenta de nuevo.');
            $('btn-accept').disabled = false;
            $('btn-decline').disabled = false;
        }
    } catch (e) {
        alert('Error de conexión. Intenta de nuevo.');
        $('btn-accept').disabled = false;
        $('btn-decline').disabled = false;
    }
}

async function load() {
    if (!token) { $('loading').textContent = 'Enlace inválido.'; return; }
    try {
        const res = await fetch(BASE_PATH + '/api/winners/accept.php?t=' + encodeURIComponent(token));
        const data = await res.json();
        if (!data.success || !data.winner) { $('loading').textContent = data.message || 'No encontramos este premio.'; return; }
        const w = data.winner;
        $('content').innerHTML = (w.status === 'pending') ? renderPending(w) : renderDecided(w);
        $('loading').style.display = 'none';
        $('content').style.display = 'block';
        if (w.status === 'pending') {
            $('btn-accept').addEventListener('click', () => decide('accept'));
            $('btn-decline').addEventListener('click', () => { if (confirm('¿Seguro que quieres rechazar el premio?')) decide('decline'); });
        }
    } catch (e) {
        $('loading').textContent = 'Error de conexión. Recarga la página.';
    }
}
load();
</script>
</body>
</html>
