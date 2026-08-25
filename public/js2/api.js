/**
 * MisRifas - API Client (compatibilidad)
 * Redirige a shared.js para mantener compatibilidad con perfil.php
 * que referencia assets/js/api.js
 */
// Si shared.js ya cargo el API, no hacer nada
if (typeof window.API === 'undefined') {
    console.warn('[MisRifas] Cargando API desde api.js (fallback). Considera cargar shared.js primero.');
    // Intentar cargar shared.js dinamicamente
    const script = document.createElement('script');
    script.src = (typeof BASE_PATH !== 'undefined' ? BASE_PATH : '') + '/js/shared.js';
    document.head.appendChild(script);
}
