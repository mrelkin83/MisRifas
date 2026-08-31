<?php
/**
 * Tab bar inferior del sitio público (solo móvil ≤768px) — patrón app nativa,
 * el mismo de los paneles admin/vendor. Uso:
 *   $tabActive = 'inicio'|'tapazo'|'boletas'|'ganadores'|'cuenta';
 *   include __DIR__ . '/partials/tabbar.php';
 * "Cuenta" apunta al login; si hay sesión (localStorage), el JS lo re-apunta
 * al panel según el rol. No se incluye en pantallas de flujo (raffle/pago/
 * boleta): ahí mandan sus propios CTAs inferiores.
 */
$tabActive = $tabActive ?? '';
$tabs = [
    ['id' => 'inicio',    'label' => 'Inicio',    'href' => BASE_PATH . '/public/index.php',
     'icon' => '<path d="M3 11l9-8 9 8"/><path d="M5 9v11a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9"/>'],
    ['id' => 'tapazo',    'label' => 'Tapazo',    'href' => BASE_PATH . '/tapazo/index.php',
     'icon' => '<path d="M5 3h11l-1 15a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 3Zm11 4h2.5a2 2 0 0 1 2 2.2l-.4 4A2 2 0 0 1 18.1 15H16"/>'],
    ['id' => 'boletas',   'label' => 'Resultados',   'href' => BASE_PATH . '/public/mis-boletos.php',
     'icon' => '<path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v1a2 2 0 0 0 0 4v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1a2 2 0 0 0 0-4Z"/><path d="M13 5v14" stroke-dasharray="2 3"/>'],
    ['id' => 'ganadores', 'label' => 'Ganadores', 'href' => BASE_PATH . '/public/ganadores.php',
     'icon' => '<path d="M8 21h8M12 17v4M7 4h10v4a5 5 0 0 1-10 0V4Z"/><path d="M7 5H4.5a1.5 1.5 0 0 0 0 3H7M17 5h2.5a1.5 1.5 0 0 1 0 3H17"/>'],
    ['id' => 'cuenta',    'label' => 'Cuenta',    'href' => BASE_PATH . '/public/admin/index.php?auth=login',
     'icon' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/>'],
];
?>
<style>
    #site-tabbar { display: none; }
    @media (max-width: 768px) {
        body { padding-bottom: calc(66px + env(safe-area-inset-bottom, 0px)) !important; }
        #site-tabbar {
            display: flex; position: fixed; left: 0; right: 0; bottom: 0; z-index: 90;
            background: rgba(15, 23, 42, 0.98);
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            padding: 6px 4px calc(6px + env(safe-area-inset-bottom, 0px));
            box-shadow: 0 -6px 20px rgba(0, 0, 0, 0.35);
        }
        .stab {
            flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 3px; padding: 6px 2px; border-radius: 12px; text-decoration: none;
            color: #94a3b8; font-size: 11px; font-weight: 600;
        }
        .stab svg { width: 22px; height: 22px; }
        .stab:active { transform: scale(0.94); }
        .stab--on { color: #f59e0b; }
    }
</style>
<nav id="site-tabbar" aria-label="Navegación principal">
    <?php foreach ($tabs as $t): ?>
    <a class="stab<?= $tabActive === $t['id'] ? ' stab--on' : '' ?>" id="stab-<?= $t['id'] ?>" href="<?= $t['href'] ?>"<?= $tabActive === $t['id'] ? ' aria-current="page"' : '' ?>>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $t['icon'] ?></svg>
        <span><?= $t['label'] ?></span>
    </a>
    <?php endforeach; ?>
</nav>
<script>
    // Con sesión iniciada, "Cuenta" lleva al panel según el rol.
    (function () {
        try {
            if (!localStorage.getItem('misrifas_token')) return;
            var u = JSON.parse(localStorage.getItem('misrifas_user') || '{}');
            var tab = document.getElementById('stab-cuenta');
            if (!tab) return;
            tab.href = (u.role === 'buyer')
                ? '<?= BASE_PATH ?>/public/dashboard.php'
                : '<?= BASE_PATH ?>/public/vendor/index.php';
            tab.querySelector('span').textContent = 'Mi Panel';
        } catch (e) { /* sin sesión legible: se queda el login */ }
    })();
</script>
