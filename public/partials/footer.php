<?php
/**
 * MisRifas - Footer compartido mejorado
 * Incluir en todas las paginas del frontend publico.
 */
$basePath = defined('BASE_PATH') ? BASE_PATH : '';
?>
<style>
    /* Footer estilo app en móvil: compacto y centrado. Las columnas de
       enlaces duplican la tab bar/hamburguesa — se ocultan. */
    @media (max-width: 768px) {
        #shared-footer { margin-top: 40px; }
        #shared-footer .grid { grid-template-columns: 1fr !important; gap: 14px; padding-top: 26px; padding-bottom: 12px; text-align: center; }
        #shared-footer .grid > div:nth-child(2),
        #shared-footer .grid > div:nth-child(3) { display: none; }
        #shared-footer .grid > div:first-child a { justify-content: center; }
        #shared-footer .grid > div:last-child ul { display: flex; justify-content: center; gap: 16px; }
        #shared-footer .grid h4 { margin-bottom: 8px; }
        #shared-footer > div > div:last-child { padding-top: 10px; padding-bottom: 12px; gap: 4px; }
        #shared-footer > div > div:last-child p { font-size: 11px; text-align: center; }
    }
</style>
<footer class="bg-[#0b1120] text-slate-400 border-t border-slate-800/50 mt-20" id="shared-footer">
    <div class="container mx-auto px-4">
        <!-- Main footer content -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-10 py-16">
            <!-- Brand -->
            <div class="lg:col-span-1">
                <a href="<?= $basePath ?>/public/index.php" class="flex items-center gap-2 mb-4">
                    <svg class="w-6 h-6 text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v1a2 2 0 0 0 0 4v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1a2 2 0 0 0 0-4Z"/><path d="M13 5v14" stroke-dasharray="2 3"/></svg>
                    <span class="text-xl font-black text-amber-400">MisRifas</span>
                </a>
                <p class="text-sm text-slate-500 leading-relaxed">
                    La plataforma más confiable para rifas digitales en Colombia. Transparencia y seguridad garantizada.
                </p>
            </div>

            <!-- Navegación -->
            <div>
                <h4 class="text-white font-bold mb-4 text-sm uppercase tracking-wider">Navegación</h4>
                <ul class="space-y-2.5">
                    <li><a href="<?= $basePath ?>/public/index.php" class="text-slate-400 hover:text-white transition-colors text-sm">Inicio</a></li>
                    <li><a href="<?= $basePath ?>/public/mis-boletos.php" class="text-slate-400 hover:text-white transition-colors text-sm">Resultados</a></li>
                    <li><a href="<?= $basePath ?>/public/ganadores.php" class="text-slate-400 hover:text-white transition-colors text-sm">Ganadores</a></li>
                    <li><a href="<?= $basePath ?>/public/que-es.php" class="text-slate-400 hover:text-white transition-colors text-sm">¿Qué es MisRifas?</a></li>
                </ul>
            </div>

            <!-- Ayuda -->
            <div>
                <h4 class="text-white font-bold mb-4 text-sm uppercase tracking-wider">Ayuda</h4>
                <ul class="space-y-2.5">
                    <li><a href="<?= $basePath ?>/public/recover.php" class="text-slate-400 hover:text-white transition-colors text-sm">Recuperar Cuenta</a></li>
                    <li><a href="<?= $basePath ?>/public/admin/index.php?auth=login" class="text-slate-400 hover:text-white transition-colors text-sm">Iniciar Sesión</a></li>
                    <li><a href="<?= $basePath ?>/public/register.php" class="text-slate-400 hover:text-white transition-colors text-sm">Crear Cuenta</a></li>
                </ul>
            </div>

            <!-- Contacto -->
            <div>
                <h4 class="text-white font-bold mb-4 text-sm uppercase tracking-wider">Contacto</h4>
                <ul class="space-y-2.5 text-sm text-slate-400">
                    <li class="flex items-center gap-2">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg> Colombia
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92Z"/></svg> WhatsApp
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/></svg> soporte@misrifas.com
                    </li>
                </ul>
            </div>
        </div>

        <!-- Bottom bar -->
        <div class="border-t border-slate-800/50 py-6 flex flex-col sm:flex-row items-center justify-between gap-4">
            <p class="text-xs text-slate-500">
                &copy; <?= date('Y') ?> MisRifas Colombia. Todos los derechos reservados.
            </p>
            <p class="text-xs text-slate-600">
                Tecnología para Soñadores — Sorteos verificados con lotería oficial
            </p>
        </div>
    </div>
</footer>
