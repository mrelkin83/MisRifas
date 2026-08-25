<?php
/**
 * MisRifas - Footer compartido mejorado
 * Incluir en todas las paginas del frontend publico.
 */
$basePath = defined('BASE_PATH') ? BASE_PATH : '';
?>
<footer class="bg-[#0b1120] text-slate-400 border-t border-slate-800/50 mt-20">
    <div class="container mx-auto px-4">
        <!-- Main footer content -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-10 py-16">
            <!-- Brand -->
            <div class="lg:col-span-1">
                <a href="<?= $basePath ?>/public/index.php" class="flex items-center gap-2 mb-4">
                    <span class="text-2xl">🎟️</span>
                    <span class="text-xl font-black bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-emerald-400">MisRifas</span>
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
                    <li><a href="<?= $basePath ?>/public/mis-boletos.php" class="text-slate-400 hover:text-white transition-colors text-sm">Consultar Boletas</a></li>
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
                        <span>📍</span> Colombia
                    </li>
                    <li class="flex items-center gap-2">
                        <span>📱</span> WhatsApp
                    </li>
                    <li class="flex items-center gap-2">
                        <span>✉️</span> soporte@misrifas.com
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
