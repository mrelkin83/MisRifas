<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
$page_title = "Boletas Compradas - MisRifas";
require_once __DIR__ . '/../config/database.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <script>const BASE_PATH = "<?= BASE_PATH ?>";</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?= $page_title ?></title>
    <meta name="theme-color" content="#0f172a">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/public/css/tailwind.min.css">
    <style>
        @font-face {
            font-family: 'Outfit';
            font-style: normal;
            font-weight: 800;
            font-display: swap;
            src: url('<?= BASE_PATH ?>/public/assets/fonts/outfit-800.woff2') format('woff2');
        }
        @layer base {
            * { box-sizing: border-box; margin: 0; padding: 0; }
        }
        html { color-scheme: dark; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f172a; color: #f8fafc; }
        h1, h2, h3 { font-family: 'Outfit', 'Inter', sans-serif; }
        .glass-card { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.05); }
        .ticket-card { border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 16px; transition: box-shadow 0.2s, border-color 0.2s; background: rgba(15,23,42,0.5); }
        .ticket-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.3); border-color: rgba(245,158,11,0.3); }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 9999px; font-size: 12px; font-weight: 700; }
        .badge--reserved { background: rgba(234,179,8,0.15); color: #eab308; }
        .badge--paid { background: rgba(16,185,129,0.15); color: #10b981; }
        .badge--available { background: rgba(245,158,11,0.15); color: #f59e0b; }
        .notification { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); max-width: 450px; width: 90%; padding: 20px 30px; background: #1e293b; color: #f8fafc; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); z-index: 9999; animation: fadeIn 0.3s ease; text-align: center; font-size: 16px; border: 1px solid rgba(255,255,255,0.1); }
        .notification--error { border: 2px solid #ef4444; }
        .notification--success { border: 2px solid #10b981; }
        .notification--info { border: 2px solid #3b82f6; }
        @keyframes fadeIn { from { opacity: 0; transform: translate(-50%, -50%) scale(0.9); } to { opacity: 1; transform: translate(-50%, -50%) scale(1); } }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .spinner { border: 4px solid rgba(255,255,255,0.1); border-top: 4px solid #f59e0b; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto; }
        @keyframes spin { to { transform: rotate(360deg); } }

        @media (max-width: 767px) {
            #nav-menu {
                position: fixed;
                top: 64px;
                left: 0;
                right: 0;
                background: #0f172a;
                flex-direction: column;
                padding: 1.5rem;
                border-bottom: 1px solid rgba(255,255,255,0.08);
                z-index: 100;
                gap: 1rem;
                display: none;
                box-shadow: 0 10px 15px -3px rgba(0,0,0,0.4);
            }
            #nav-menu.active {
                display: flex;
            }
            .ticket-card { padding: 12px; }
            .ticket-card h3 { font-size: 1rem; }
            .ticket-card .text-3xl { font-size: 1.75rem; }
        }
    </style>
</head>
<body class="bg-[#0f172a]">
    <header class="glass-card shadow-sm sticky top-0 z-50 !rounded-none">
        <nav class="container mx-auto px-4 h-16 flex items-center justify-between">
            <a href="<?= BASE_PATH ?>/public/index.php" class="flex items-center gap-2 text-xl font-bold text-primary">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v1a2 2 0 0 0 0 4v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1a2 2 0 0 0 0-4Z"/><path d="M13 5v14" stroke-dasharray="2 3"/></svg>
                <span>MisRifas</span>
            </a>

            <button id="mobile-menu-btn" class="md:hidden text-slate-300 p-2 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500" aria-label="Menu">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path>
                </svg>
            </button>

            <div class="hidden md:flex items-center gap-4" id="nav-menu">
                <a href="<?= BASE_PATH ?>/public/index.php" class="text-slate-300 hover:text-primary font-medium">Inicio</a>
                <a href="<?= BASE_PATH ?>/public/admin/index.php?auth=login" class="text-slate-300 hover:text-primary transition font-medium text-sm">Iniciar Sesión</a>
                <a href="<?= BASE_PATH ?>/public/register.php" class="px-4 py-2 bg-primary text-slate-950 rounded-lg hover:bg-primary-light transition font-medium text-sm shadow-md text-center">Crear Cuenta</a>
            </div>
        </nav>
    </header>

    <main class="py-8">
        <div class="container mx-auto px-4 max-w-2xl">
            <h1 class="text-3xl font-bold text-center mb-8">Boletas Compradas</h1>

            <div class="glass-card rounded-2xl shadow-md p-6 md:p-8 mb-8">
                <h2 class="text-xl font-bold mb-4">Consulta tus Boletas</h2>
                <p class="text-slate-300 mb-6 text-sm sm:text-base">Ingresa tu WhatsApp o código único para ver tus boletos</p>

                <form id="lookup-form" class="space-y-4">
                    <div>
                        <label for="phone" class="block text-sm font-medium text-slate-300 mb-1">WhatsApp</label>
                        <input type="tel" id="phone" name="phone" autocomplete="tel" inputmode="numeric" placeholder="3001234567" pattern="[3][0-9]{9}"
                            class="w-full px-4 py-3 bg-black/20 border-2 border-white/10 rounded-lg text-slate-100 placeholder:text-slate-500 focus:outline-none focus:border-primary">
                    </div>

                    <div class="flex items-center gap-4 py-2">
                        <div class="flex-1 h-px bg-white/10"></div>
                        <span class="text-slate-500 text-sm">O</span>
                        <div class="flex-1 h-px bg-white/10"></div>
                    </div>

                    <div>
                        <label for="unique-id" class="block text-sm font-medium text-slate-300 mb-1">Código Único</label>
                        <input type="text" id="unique-id" name="unique_id" autocomplete="off" spellcheck="false" placeholder="XXXXXXXX-XXXX-XXXX"
                            class="w-full px-4 py-3 bg-black/20 border-2 border-white/10 rounded-lg text-slate-100 placeholder:text-slate-500 focus:outline-none focus:border-primary">
                    </div>

                    <button type="submit" id="search-btn" class="w-full py-4 bg-primary text-slate-950 font-bold rounded-xl text-lg hover:bg-primary-light disabled:opacity-50 transition-colors">
                        Buscar mis Boletas
                    </button>
                </form>
            </div>

            <div id="loading" class="hidden text-center py-8">
                <div class="spinner"></div>
                <p class="text-slate-400 mt-4">Buscando tus boletos…</p>
            </div>

            <div id="no-results" class="hidden text-center py-8">
                <p class="text-slate-300 text-lg">No se encontraron boletos</p>
                <p class="text-slate-500 text-sm mt-2">Verifica los datos e intenta de nuevo</p>
                <a href="<?= BASE_PATH ?>/public/index.php" class="inline-block mt-5 text-primary font-bold hover:underline text-sm">Explorar rifas activas &rarr;</a>
            </div>

            <div id="tickets-container" class="hidden">
                <div class="glass-card rounded-2xl shadow-md p-4 sm:p-6 mb-6">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-2">
                        <h2 class="text-xl font-bold">Tus Boletas</h2>
                        <span id="user-info" class="text-xs sm:text-sm text-slate-400"></span>
                    </div>
                    <div id="tickets-list" class="space-y-4"></div>
                </div>

                <div class="text-center">
                    <p class="text-slate-400 text-xs sm:text-sm mb-4">Guarda tu código único para consultar más fácil</p>
                    <div id="unique-id-display" class="bg-black/30 border border-white/10 inline-block px-4 sm:px-6 py-2 sm:py-3 rounded-lg font-mono text-base sm:text-lg break-all"></div>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-black/30 text-white py-8 mt-12 border-t border-white/5">
        <div class="container mx-auto px-4 text-center">
            <p class="text-sm text-slate-400">&copy; 2026 MisRifas Colombia. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script>
    const API = {
        async request(endpoint, options = {}) {
            const config = {
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...options.headers },
                ...options
            };
            const response = await fetch(BASE_PATH + '/api' + endpoint, config);
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Error');
            return data;
        },
        async get(endpoint, params = {}) {
            const qs = new URLSearchParams(params).toString();
            const url = qs ? endpoint + '?' + qs : endpoint;
            return this.request(url, { method: 'GET' });
        },
        async post(endpoint, data = {}) {
            return this.request(endpoint, { method: 'POST', body: JSON.stringify(data) });
        }
    };

    const Utils = {
        formatPrice(p) { return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 }).format(p); },
        formatDate(d) { return new Intl.DateTimeFormat('es-CO', { year: 'numeric', month: 'long', day: 'numeric' }).format(new Date(d)); },
        showNotification(msg, type = 'info') {
            const existing = document.querySelectorAll('.notification');
            existing.forEach(n => n.remove());
            const n = document.createElement('div');
            n.className = 'notification notification--' + type;
            n.setAttribute('role', 'status');
            n.setAttribute('aria-live', 'polite');
            const p = document.createElement('p');
            p.className = 'font-medium';
            p.textContent = msg;
            n.appendChild(p);
            document.body.appendChild(n);
            setTimeout(() => n.remove(), 3000);
        },
        esc(s) {
            return String(s ?? '').replace(/[&<>"']/g, c =>
                ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        }
    };

    function translateStatus(status) {
        const t = { 'reserved': 'Reservado', 'paid': 'Pagado', 'available': 'Disponible' };
        return t[status] || status;
    }

    let ticketsById = {};

    function renderTickets(data) {
        const container = document.getElementById('tickets-list');
        document.getElementById('user-info').textContent = (data.user?.name || '') + ' — ' + (data.user?.phone || '');
        document.getElementById('unique-id-display').textContent = data.user?.unique_id || '';

        ticketsById = {};
        data.tickets.forEach(t => { ticketsById[t.id] = t; });

        container.innerHTML = data.tickets.map(ticket => {
            const opps = typeof ticket.opportunities === 'string' ? JSON.parse(ticket.opportunities) : (ticket.opportunities || []);
            const reservedHtml = (ticket.status === 'reserved' && ticket.reserved_until)
                ? '<div class="text-amber-400 text-xs mt-1">Expira: ' + new Date(ticket.reserved_until).toLocaleString('es-CO') + '</div>' : '';

            // Mostrar números de oportunidad con estilo
            const oppNumbersHtml = opps.length > 0
                ? opps.map(n => '<span class="inline-block bg-primary/15 text-primary text-[10px] font-bold px-2 py-0.5 rounded mr-1 mb-1">' + n + '</span>').join('')
                : '<span class="text-slate-500">--</span>';

            return '<div class="ticket-card">' +
                '<div class="flex justify-between items-start mb-2">' +
                    '<h3 class="font-bold text-base sm:text-lg">' + Utils.esc(ticket.raffle_name || 'Rifa') + '</h3>' +
                    '<span class="badge badge--' + ticket.status + '">' + translateStatus(ticket.status) + '</span>' +
                '</div>' +
                '<div class="flex items-center gap-4 mb-2">' +
                    '<div class="text-2xl sm:text-3xl font-bold text-primary">' + (ticket.ticket_number || '--') + '</div>' +
                    '<div class="text-xs sm:text-sm text-slate-400">' +
                        '<div class="mb-1 font-semibold text-slate-300">Mis números:</div>' +
                        '<div>' + oppNumbersHtml + '</div>' +
                        '<div class="mt-1">Precio: ' + Utils.formatPrice(ticket.ticket_price || 0) + '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="text-[10px] sm:text-xs text-slate-500">' +
                    (ticket.draw_date ? '<div>Sorteo: ' + Utils.formatDate(ticket.draw_date) + '</div>' : '') +
                    reservedHtml +
                '</div>' +
                (ticket.status === 'reserved' ? '<button type="button" data-pay-ticket-id="' + ticket.id + '" class="mt-3 block w-full text-center py-2 bg-emerald-500 text-white rounded-lg font-bold text-sm hover:bg-emerald-600 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300">Pagar Ahora</button>' : '') +
            '</div>';
        }).join('');
    }

    document.getElementById('lookup-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const phone = document.getElementById('phone').value.trim();
        const uniqueId = document.getElementById('unique-id').value.trim();

        if (!phone && !uniqueId) {
            Utils.showNotification('Ingresa al menos un dato para buscar', 'error');
            return;
        }

        document.getElementById('loading').classList.remove('hidden');
        document.getElementById('no-results').classList.add('hidden');
        document.getElementById('tickets-container').classList.add('hidden');

        try {
            const response = await API.get('/user/tickets.php', { phone, unique_id: uniqueId });
            if (response.success && response.data.tickets.length > 0) {
                renderTickets(response.data);
                document.getElementById('tickets-container').classList.remove('hidden');
            } else {
                document.getElementById('no-results').classList.remove('hidden');
            }
        } catch (error) {
            Utils.showNotification(error.message || 'Error al buscar boletos', 'error');
        } finally {
            document.getElementById('loading').classList.add('hidden');
        }
    });

    // Ir a pagar un boleto reservado: guarda sus datos reales para que
    // payment.php no dependa de que ya existiera una reserva en localStorage.
    document.getElementById('tickets-list').addEventListener('click', (e) => {
        const btn = e.target.closest('[data-pay-ticket-id]');
        if (!btn) return;
        const ticket = ticketsById[btn.dataset.payTicketId];
        if (!ticket) return;

        localStorage.setItem('current_reservation', JSON.stringify({
            ticket_number: ticket.ticket_number,
            ticket_price: ticket.ticket_price,
            reserved_until: ticket.reserved_until,
            raffle_name: ticket.raffle_name
        }));
        window.location.href = BASE_PATH + '/public/payment.php?id=' + ticket.id;
    });

    // Mobile Menu Toggle
    document.getElementById('mobile-menu-btn')?.addEventListener('click', () => {
        document.getElementById('nav-menu').classList.toggle('active');
    });

    // Cerrar menú al hacer click fuera
    document.addEventListener('click', (e) => {
        const navMenu = document.getElementById('nav-menu');
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        if (navMenu?.classList.contains('active') && !navMenu.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
            navMenu.classList.remove('active');
        }
    });
    </script>
</body>
</html>
