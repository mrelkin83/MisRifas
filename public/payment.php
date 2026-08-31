<?php
require_once __DIR__ . '/../config/paths.php';
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
$page_title = "Pago - MisRifas";
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
        .payment-method {
            padding: 16px; border: 2px solid rgba(255,255,255,0.1); border-radius: 12px;
            cursor: pointer; transition: border-color 0.2s, background-color 0.2s, box-shadow 0.2s; text-align: left;
            width: 100%; background: rgba(15,23,42,0.5); color: #f8fafc;
        }
        .payment-method:hover { border-color: rgba(245,158,11,0.5); background: rgba(245,158,11,0.08); }
        .payment-method:focus-visible { outline: none; border-color: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,0.4); }
        .payment-method--selected { border-color: #f59e0b; background: rgba(245,158,11,0.12); box-shadow: 0 0 0 4px rgba(245,158,11,0.15); }
        .notification { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); max-width: 450px; width: 90%; padding: 20px 30px; background: #1e293b; color: #f8fafc; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); z-index: 9999; animation: fadeIn 0.3s ease; text-align: center; font-size: 16px; border: 1px solid rgba(255,255,255,0.1); }
        .notification--error { border: 2px solid #ef4444; }
        .notification--success { border: 2px solid #10b981; }
        .notification--info { border: 2px solid #3b82f6; }
        @keyframes fadeIn { from { opacity: 0; transform: translate(-50%, -50%) scale(0.9); } to { opacity: 1; transform: translate(-50%, -50%) scale(1); } }
        .countdown-timer { font-variant-numeric: tabular-nums; }

        @media (max-width: 640px) {
            .payment-methods-grid { grid-template-columns: 1fr !important; }
            .glass-card { padding: 1.5rem !important; }
        }
    </style>
</head>
<body class="bg-[#0f172a]">
    <header class="glass-card shadow-sm sticky top-0 z-50 !rounded-none">
        <nav class="container mx-auto px-4 h-16 flex items-center justify-between">
            <a href="<?= BASE_PATH ?>/public/index.php" class="flex items-center gap-2 text-xl font-bold text-primary">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v1a2 2 0 0 0 0 4v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1a2 2 0 0 0 0-4Z"/><path d="M13 5v14" stroke-dasharray="2 3"/></svg>
                <span class="hidden xs:inline">MisRifas</span>
            </a>
            <div class="flex items-center gap-4">
                <a href="<?= BASE_PATH ?>/public/mis-boletos.php" class="text-slate-300 hover:text-primary font-medium text-xs sm:text-sm">Mis Boletas</a>
            </div>
        </nav>
    </header>

    <main class="py-6 sm:py-8">
        <div class="container mx-auto px-4 max-w-2xl">
            <div class="glass-card rounded-2xl shadow-md p-5 sm:p-8">
                <h1 class="text-2xl font-bold mb-1 text-center sm:text-left">Completar Pago</h1>
                <p id="raffle-name" class="hidden text-sm text-slate-400 mb-5 text-center sm:text-left"></p>

                <div id="reservation-info" class="bg-primary/10 border border-primary/20 rounded-xl p-4 mb-6">
                    <div class="flex justify-between items-center mb-2">
                        <span id="ticket-number-label" class="text-slate-300 text-sm">Boleto:</span>
                        <span id="ticket-number" class="text-xl font-black text-primary font-mono text-right">--</span>
                    </div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-slate-300 text-sm">Total:</span>
                        <span id="ticket-price" class="text-xl font-black text-primary font-mono">$0</span>
                    </div>
                    <div class="flex justify-between items-center pt-2 border-t border-primary/20">
                        <span class="text-slate-300 text-sm">Expira en:</span>
                        <span id="reservation-time" class="text-base font-bold text-slate-200 countdown-timer">--:--</span>
                    </div>
                </div>

                <div class="mb-8">
                    <h2 class="text-lg font-bold mb-4">¿Cómo vas a pagar?</h2>
                    <div id="methods-grid" class="grid grid-cols-1 xs:grid-cols-2 gap-3 payment-methods-grid">
                        <!-- Solo los métodos que el organizador configuró (§5.3) -->
                    </div>
                    <p id="no-methods" class="hidden text-sm text-amber-300 bg-amber-500/10 border border-amber-500/30 rounded-xl p-4">
                        El organizador aún no publicó sus datos de pago. Escríbele por WhatsApp para coordinar.
                    </p>
                </div>

                <!-- Una selección, un dato, un botón de copiar (§5.3) -->
                <div id="payment-instructions" class="hidden mb-8 animate-in fade-in slide-in-from-top-2 duration-300">
                    <div class="bg-black/20 border border-primary/30 rounded-xl p-5 text-center">
                        <p class="text-xs uppercase tracking-widest text-slate-400 mb-2" id="dest-label">Transfiere a</p>
                        <div class="flex items-center justify-center gap-3 flex-wrap">
                            <span id="dest-value" class="text-2xl sm:text-3xl font-black text-primary font-mono break-all"></span>
                            <button type="button" id="copy-dest" onclick="copyDestination()" class="px-3 py-2 bg-white/10 border border-white/15 rounded-lg text-sm font-bold hover:bg-white/20 active:scale-95 transition-all">📋 Copiar</button>
                        </div>
                        <p class="text-sm text-slate-300 mt-4">
                            Transfiere <strong class="text-primary">exactamente</strong>
                            <span id="exact-amount" class="font-black font-mono"></span>
                            <span id="amount-hint"> — los últimos dígitos identifican TU compra. Luego vuelve aquí y sube el comprobante.</span>
                        </p>
                    </div>
                </div>

                <div class="mb-8">
                    <h3 class="font-bold mb-2">Comprobante de pago</h3>
                    <p class="text-xs text-slate-400 mb-3 italic">Sube una captura de pantalla del pago para agilizar la verificación.</p>

                    <div class="relative">
                        <input type="file" id="payment-proof" accept="image/*" class="sr-only peer">
                        <label for="payment-proof" class="w-full flex flex-col items-center justify-center border-2 border-dashed border-white/15 rounded-xl p-6 hover:border-primary hover:bg-primary/5 peer-focus-visible:border-primary peer-focus-visible:ring-2 peer-focus-visible:ring-primary/50 transition-all cursor-pointer">
                            <svg class="w-8 h-8 mb-2 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4M12 4 7 9M12 4l5 5"/><path d="M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/></svg>
                            <span class="text-sm font-medium text-slate-300">Click para subir comprobante</span>
                            <span id="file-name" class="text-xs text-primary mt-2 hidden font-bold"></span>
                        </label>
                    </div>

                    <div id="preview-container" class="hidden mt-4 text-center">
                        <img id="preview-image" alt="Vista previa del comprobante de pago" class="max-h-64 mx-auto rounded-xl border-4 border-white/10 shadow-lg">
                        <button onclick="removeImage()" class="text-red-400 text-xs font-bold mt-2 hover:underline">Eliminar imagen</button>
                    </div>
                </div>

                <button id="confirm-payment-btn" class="w-full py-5 bg-primary text-slate-950 font-black rounded-2xl text-xl hover:bg-primary-light disabled:opacity-50 disabled:grayscale transition-all shadow-xl hover:shadow-primary/30" disabled>
                    Finalizar Compra
                </button>

                <p class="text-[10px] text-slate-500 text-center mt-6 uppercase tracking-widest">
                    Seguridad cifrada SSL · Verificación humana
                </p>
            </div>

            <div class="mt-8 text-center">
                <p class="text-sm text-slate-400">
                    ¿Necesitas ayuda?
                    <a href="https://wa.me/573001234567?text=<?= rawurlencode('Hola, tengo un problema con mi pago en MisRifas.') ?>" target="_blank" rel="noopener" class="text-primary font-bold hover:underline">Contactar soporte</a>
                </p>
            </div>
        </div>
    </main>

    <footer class="bg-gray-900 text-white py-10 mt-12 border-t border-white/5">
        <div class="container mx-auto px-4 text-center">
            <p class="text-xs opacity-50">&copy; 2026 MisRifas Colombia. Todos los derechos reservados.</p>
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
        }
    };

    const urlParams = new URLSearchParams(window.location.search);
    const ticketId = urlParams.get('id') || urlParams.get('ticket');
    const reservationId = urlParams.get('reservation_id');

    let selectedMethod = null;
    let countdownInterval = null;
    let reservationData = null;

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    const METHOD_META = {
        nequi:     { label: 'Nequi',     hint: 'Envía plata al celular',       dot: 'bg-purple-400', destLabel: 'Transfiere al Nequi' },
        daviplata: { label: 'DaviPlata', hint: 'Pasar plata al celular',       dot: 'bg-red-400',    destLabel: 'Transfiere al DaviPlata' },
        breb:      { label: 'Bre-B',     hint: 'Transferencia con llave',      dot: 'bg-sky-400',    destLabel: 'Transfiere a la llave Bre-B' },
    };

    // §5.3: solo los métodos configurados por el organizador, como tarjetas.
    function renderMethods(methods) {
        const grid = document.getElementById('methods-grid');
        const none = document.getElementById('no-methods');
        grid.innerHTML = '';
        const usables = (methods || []).filter(m => METHOD_META[m.method]);
        if (!usables.length) {
            none.classList.remove('hidden');
            return;
        }
        usables.forEach(m => {
            const meta = METHOD_META[m.method];
            const btn = document.createElement('button');
            btn.className = 'payment-method';
            btn.setAttribute('data-method', m.method);
            btn.innerHTML = '<div class="font-bold flex items-center gap-2">' +
                '<span class="w-2 h-2 rounded-full ' + meta.dot + '"></span>' + meta.label + '</div>' +
                '<div class="text-xs text-slate-400 mt-1">' + meta.hint + '</div>';
            btn.addEventListener('click', () => selectPaymentMethod(m));
            grid.appendChild(btn);
        });
    }

    function copyDestination() {
        const val = document.getElementById('dest-value').textContent;
        navigator.clipboard.writeText(val).then(() => {
            const b = document.getElementById('copy-dest');
            b.textContent = '✅ Copiado';
            setTimeout(() => { b.textContent = '📋 Copiar'; }, 1800);
        }).catch(() => Utils.showNotification('Copia manualmente: ' + val, 'info'));
    }

    function loadReservation() {
        const reservation = localStorage.getItem('current_reservation');
        if (reservation) {
            try {
                const data = JSON.parse(reservation);
                reservationData = data;
                renderMethods(data.payment_methods);
                if (data.numeros && data.numeros.length) {
                    // Reserva de varios boletos (selector multiple de raffle.php)
                    document.getElementById('ticket-number-label').textContent = data.numeros.length > 1 ? 'Boletos:' : 'Boleto:';
                    document.getElementById('ticket-number').textContent = data.numeros.map(n => '#' + n).join(', ');
                    document.getElementById('ticket-price').textContent = Utils.formatPrice(data.total_amount || 0);
                } else {
                    document.getElementById('ticket-number').textContent = '#' + (data.ticket_number || '--');
                    document.getElementById('ticket-price').textContent = Utils.formatPrice(data.ticket_price || 0);
                }
                if (data.raffle_name) {
                    const raffleNameEl = document.getElementById('raffle-name');
                    raffleNameEl.textContent = data.raffle_name;
                    raffleNameEl.classList.remove('hidden');
                }
                if (data.reserved_until) {
                    startExpirationCountdown(data.reserved_until);
                }
            } catch (e) {
                console.error('Error parsing reservation:', e);
            }
        } else if (!ticketId && !reservationId) {
            // Si no hay nada, mandarlo al inicio
            window.location.href = BASE_PATH + '/public/index.php';
        }
    }

    function startExpirationCountdown(reservedUntil) {
        if (countdownInterval) clearInterval(countdownInterval);
        const target = new Date(reservedUntil).getTime();

        countdownInterval = setInterval(() => {
            const now = Date.now();
            const diff = target - now;

            if (diff <= 0) {
                clearInterval(countdownInterval);
                document.getElementById('reservation-time').textContent = 'Expirada';
                document.getElementById('confirm-payment-btn').disabled = true;
                Utils.showNotification('Tu reserva ha expirado', 'error');
                setTimeout(() => window.location.href = BASE_PATH + '/public/index.php', 2000);
                return;
            }

            const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const s = Math.floor((diff % (1000 * 60)) / 1000);
            const timeEl = document.getElementById('reservation-time');
            timeEl.textContent = m.toString().padStart(2, '0') + ':' + s.toString().padStart(2, '0');
            timeEl.classList.toggle('text-red-400', diff <= 5 * 60 * 1000);
            timeEl.classList.toggle('text-slate-200', diff > 5 * 60 * 1000);
        }, 1000);
    }

    // Una selección → UN dato de destino en grande + copiar (§5.3).
    function selectPaymentMethod(m) {
        selectedMethod = m.method;
        document.querySelectorAll('.payment-method').forEach(btn => {
            btn.classList.toggle('payment-method--selected', btn.getAttribute('data-method') === m.method);
        });
        const meta = METHOD_META[m.method];
        document.getElementById('dest-label').textContent = meta.destLabel;
        document.getElementById('dest-value').textContent = m.destination;
        document.getElementById('exact-amount').textContent =
            '$' + Number(reservationData?.total_amount || 0).toLocaleString('es-CO');
        // Con sufijo (1 número): el monto identifica la compra. Sin sufijo
        // (varios números): identifica la referencia de la transferencia.
        document.getElementById('amount-hint').textContent =
            Number(reservationData?.payment_suffix || 0) > 0
                ? ' — los últimos dígitos identifican TU compra. Luego vuelve aquí y sube el comprobante.'
                : ' y sube aquí el comprobante: la referencia de la transferencia identifica tu pago.';
        document.getElementById('payment-instructions').classList.remove('hidden');
        checkCanConfirm();
    }

    document.getElementById('payment-proof').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            document.getElementById('file-name').textContent = file.name;
            document.getElementById('file-name').classList.remove('hidden');
            const reader = new FileReader();
            reader.onload = function(ev) {
                document.getElementById('preview-image').src = ev.target.result;
                document.getElementById('preview-container').classList.remove('hidden');
                checkCanConfirm();
            };
            reader.readAsDataURL(file);
        }
    });

    function removeImage() {
        document.getElementById('payment-proof').value = "";
        document.getElementById('preview-container').classList.add('hidden');
        document.getElementById('file-name').classList.add('hidden');
        checkCanConfirm();
    }

    function checkCanConfirm() {
        const hasMethod = selectedMethod !== null;
        const hasFile = document.getElementById('payment-proof').files.length > 0;
        document.getElementById('confirm-payment-btn').disabled = !(hasMethod && hasFile);
    }

    document.getElementById('confirm-payment-btn').addEventListener('click', async () => {
        const btn = document.getElementById('confirm-payment-btn');
        btn.disabled = true;
        btn.textContent = 'Procesando…';

        try {
            const proofFile = document.getElementById('payment-proof').files[0];
            const reader = new FileReader();
            const proofData = await new Promise((resolve) => {
                reader.onload = (e) => resolve(e.target.result);
                reader.readAsDataURL(proofFile);
            });

            const payload = {
                payment_method: selectedMethod,
                proof: proofData
            };
            if (reservationId) {
                payload.reservation_id = reservationId;
            } else {
                payload.ticket_id = ticketId;
            }

            const response = await API.post('/tickets/confirm-payment.php', payload);

            if (response.success) {
                Utils.showNotification('¡Pago registrado con éxito!', 'success');
                localStorage.removeItem('current_reservation');
                setTimeout(() => window.location.href = BASE_PATH + '/public/mis-boletos.php', 1500);
            } else {
                Utils.showNotification(response.message || 'Error', 'error');
                btn.disabled = false;
                btn.textContent = 'Finalizar Compra';
            }
        } catch (error) {
            Utils.showNotification('Error de conexión', 'error');
            btn.disabled = false;
            btn.textContent = 'Finalizar Compra';
        }
    });

    loadReservation();
    </script>
<?php $tabActive = 'inicio'; include __DIR__ . '/partials/tabbar.php'; ?>
</body>
</html>
