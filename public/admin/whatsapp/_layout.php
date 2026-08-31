<?php
/**
 * Envoltura común del módulo WhatsApp IA (admin de MisRifas).
 *
 * Portado desde ControlBarMax pero cableado a MisRifas: bootstrap propio,
 * autenticación por SESIÓN de super_admin (no la de CBM), y el helper JS `WA`
 * apunta al router de MisRifas (/api/whatsapp/admin/). SOLO super_admin — los
 * vendedores no acceden hasta que se autorice.
 */

session_set_cookie_params([
    'lifetime' => 0, 'path' => '/',
    'secure' => (getenv('APP_ENV') ?: 'development') === 'production',
    'httponly' => true, 'samesite' => 'Lax',
]);
session_start();

require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/brand.php';

// Gate: solo super_admin. El login de MisRifas guarda user_role en sesión.
if (($_SESSION['user_role'] ?? '') !== 'super_admin') {
    header('Location: ' . BASE_PATH . '/public/admin/index.php?auth=login&error=solo_admin');
    exit;
}

if (!defined('WA_BASE')) {
    define('WA_BASE', BASE_PATH); // prefijo de URL del sitio
}

function waTabs(string $activa): void
{
    $tabs = [
        'dashboard'      => '📊 Panel',
        'conexion'       => '🔗 Conexión',
        'agente'         => '🤖 Agente',
        'llm'            => '🧠 Proveedor IA',
        'voz'            => '🎙️ Voz',
        'pagos'          => '💳 Pagos',
        'conversaciones' => '💬 Conversaciones',
        'logs'           => '📋 Bitácora',
    ];
    echo '<div class="wa-tabs">';
    foreach ($tabs as $file => $label) {
        $on = ($file === $activa);
        echo '<a href="' . WA_BASE . '/public/admin/whatsapp/' . $file . '.php" class="wa-tab' . ($on ? ' wa-tab--on' : '') . '">' . $label . '</a>';
    }
    echo '</div>';
}

function waHeader(string $titulo, string $rutaActiva, string $subtitulo = ''): void
{
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0f172a">
    <title>WhatsApp IA — <?= htmlspecialchars($titulo) ?> | <?= plataforma_e() ?></title>
    <link rel="stylesheet" href="<?= WA_BASE ?>/public/css/tailwind.min.css">
    <style>
        :root{ --primary-color:#f59e0b; --border-color:#334155; --text-muted:#94a3b8; --success-color:#34d399; }
        html{color-scheme:dark;}
        body{background:#0f172a;color:#e2e8f0;font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;margin:0;min-height:100vh;}
        .wa-wrap{max-width:1100px;margin:0 auto;padding:20px 16px;}
        .wa-topbar{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px;flex-wrap:wrap;}
        .wa-back{display:inline-flex;align-items:center;gap:6px;color:var(--text-muted);text-decoration:none;font-size:14px;font-weight:500;}
        .wa-back:hover{color:#fff;}
        .neon-text{color:var(--primary-color);}
        .neon-card{background:rgba(30,41,59,.7);border:1px solid rgba(255,255,255,.06);border-radius:16px;}
        .neon-input,.neon-select,textarea.neon-input{background:rgba(15,23,42,.6);border:1px solid var(--border-color);color:#fff;border-radius:10px;padding:10px 14px;outline:none;transition:border-color .2s;font-size:14px;}
        .neon-input:focus,.neon-select:focus{border-color:var(--primary-color);}
        .neon-btn{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.06);border:1px solid var(--border-color);color:#fff;border-radius:10px;padding:8px 14px;cursor:pointer;font-size:14px;font-weight:600;transition:background-color .2s;}
        .neon-btn:hover{background:rgba(255,255,255,.12);}
        .neon-btn-success{display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#f59e0b,#d97706);color:#1c1305;border:none;border-radius:10px;padding:10px 18px;cursor:pointer;font-weight:700;font-size:14px;}
        .neon-btn-success:hover{filter:brightness(1.08);}
        .neon-transition{transition:all .2s;}
        .wa-tabs{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px;}
        .wa-tab{padding:7px 14px;border-radius:10px;font-size:14px;border:1px solid var(--border-color);color:var(--text-muted);text-decoration:none;transition:all .2s;}
        .wa-tab:hover{color:#fff;}
        .wa-tab--on{border-color:var(--primary-color);color:var(--primary-color);}
        label{font-size:13px;}
        .text-\[var\(--text-muted\)\]{color:var(--text-muted);}
    </style>
</head>
<body>
    <div class="wa-wrap">
        <div class="wa-topbar">
            <div>
                <h2 style="font-size:22px;font-weight:800;margin:0" class="neon-text">💬 WhatsApp IA — <?= htmlspecialchars($titulo) ?></h2>
                <?php if ($subtitulo !== ''): ?><p style="color:var(--text-muted);font-size:13px;margin:2px 0 0"><?= htmlspecialchars($subtitulo) ?></p><?php endif; ?>
            </div>
            <a class="wa-back" href="<?= WA_BASE ?>/public/admin/index.php">← Volver al panel</a>
        </div>
        <?php waTabs($rutaActiva); ?>
    <script>
    // Helper compartido por todas las pantallas. Apunta al router de MisRifas.
    // Auth por cookie de sesión de super_admin (credentials same-origin).
    window.WA = window.WA || {
        async get(ep, params){ const q=params?('?'+new URLSearchParams(params)):''; const r=await fetch('<?= WA_BASE ?>/api/whatsapp/admin/index.php?ep='+ep+(q?'&'+q.slice(1):''),{credentials:'same-origin'}); if(!r.ok) return {success:false,error:'HTTP '+r.status}; return r.json(); },
        async post(ep, body){ const r=await fetch('<?= WA_BASE ?>/api/whatsapp/admin/index.php?ep='+ep,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify(body||{})}); const j=await r.json().catch(()=>({})); if(!r.ok&&!j.error)j.error='HTTP '+r.status; return j; },
        aviso(msg, ok){ let t=document.getElementById('wa-toast'); if(!t){t=document.createElement('div');t.id='wa-toast';t.style.cssText='position:fixed;top:16px;right:16px;z-index:9999;padding:12px 20px;border-radius:10px;font-size:14px;font-weight:600;max-width:420px;transition:opacity .3s;color:#fff';document.body.appendChild(t);} t.textContent=msg; t.style.background=ok?'rgba(22,163,74,.92)':'rgba(220,38,38,.92)'; t.style.opacity='1'; clearTimeout(t._t); t._t=setTimeout(()=>{t.style.opacity='0';},4500); },
        dinero(v){ return '$'+Number(v||0).toLocaleString('es-CO'); },
        esc(s){ const d=document.createElement('div'); d.textContent=s==null?'':String(s); return d.innerHTML; }
    };
    </script>
    <?php
}

function waFooter(): void
{
    echo '</div></body></html>';
}
