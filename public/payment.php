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
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT: '#f59e0b', dark: '#b45309', light: '#fbbf24' }
                    },
                    screens: {
                        'xs': '480px',
                    }
                }
            }
        }
    </script>
    <style>
        @font-face {
            font-family: 'Outfit';
            font-style: normal;
            font-weight: 800;
            font-display: swap;
            src: url('<?= BASE_PATH ?>/public/assets/fonts/outfit-800.woff2') format('woff2');
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f172a; color: #f8fafc; }
        h1, h2, h3 { font-family: 'Outfit', 'Inter', sans-serif; }
        .glass-card { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.05); }
        .payment-method {
            padding: 16px; border: 2px solid rgba(255,255,255,0.1); border-radius: 12px;
            cursor: pointer; transition: all 0.2s; text-align: left;
            width: 100%; background: rgba(15,23,42,0.5); color: #f8fafc;
        }
        .payment-method:hover { border-color: rgba(245,158,11,0.5); background: rgba(245,158,11,0.08); }
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
                <h1 class="text-2xl font-bold mb-6 text-center sm:text-left">Completar Pago</h1>

                <div id="reservation-info" class="bg-primary/10 border border-primary/20 rounded-xl p-4 mb-6">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-slate-300 text-sm">Boleto:</span>
                        <span id="ticket-number" class="text-xl font-black text-primary font-mono">--</span>
                    </div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-slate-300 text-sm">Precio:</span>
                        <span id="ticket-price" class="text-xl font-black text-primary font-mono">$0</span>
                    </div>
                    <div class="flex justify-between items-center pt-2 border-t border-primary/20">
                        <span class="text-slate-300 text-sm">Expira en:</span>
                        <span id="reservation-time" class="text-base font-bold text-red-400 countdown-timer">--:--</span>
                    </div>
                </div>

                <div class="mb-8">
                    <h2 class="text-lg font-bold mb-4">Selecciona método de pago</h2>
                    <div class="grid grid-cols-1 xs:grid-cols-2 gap-3 payment-methods-grid">
                        <button onclick="selectPaymentMethod('NEQUI')" class="payment-method" data-method="NEQUI">
                            <div class="font-bold flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-purple-400"></span>
                                Nequi
                            </div>
                            <div class="text-xs text-slate-400 mt-1">Transferencia directa</div>
                        </button>
                        <button onclick="selectPaymentMethod('BANCOLOMBIA_TRANSFER')" class="payment-method" data-method="BANCOLOMBIA_TRANSFER">
                            <div class="font-bold flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-yellow-400"></span>
                                Bancolombia
                            </div>
                            <div class="text-xs text-slate-400 mt-1">Ahorro a la mano/App</div>
                        </button>
                        <button onclick="selectPaymentMethod('DAVIPLATA')" class="payment-method" data-method="DAVIPLATA">
                            <div class="font-bold flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-red-400"></span>
                                Daviplata
                            </div>
                            <div class="text-xs text-slate-400 mt-1">Desde tu celular</div>
                        </button>
                        <button onclick="selectPaymentMethod('EFECTY')" class="payment-method" data-method="EFECTY">
                            <div class="font-bold flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-sky-400"></span>
                                Efecty
                            </div>
                            <div class="text-xs text-slate-400 mt-1">Pago en efectivo</div>
                        </button>
                    </div>
                </div>

                <div id="payment-instructions" class="hidden mb-8 animate-in fade-in slide-in-from-top-2 duration-300">
                    <h3 class="font-bold mb-3 flex items-center gap-2 text-primary">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h6M9 16h6M9 8h1"/><path d="M8 3h8a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/></svg>
                        Instrucciones:
                    </h3>
                    <div id="instructions-content" class="bg-black/20 border border-white/10 rounded-xl p-5 text-slate-300 text-sm leading-relaxed"></div>
                </div>

                <div class="mb-8">
                    <h3 class="font-bold mb-2">Comprobante de pago</h3>
                    <p class="text-xs text-slate-400 mb-3 italic">Sube una captura de pantalla del pago para agilizar la verificación.</p>

                    <div class="relative">
                        <input type="file" id="payment-proof" accept="image/*" class="hidden">
                        <label for="payment-proof" class="w-full flex flex-col items-center justify-center border-2 border-dashed border-white/15 rounded-xl p-6 hover:border-primary hover:bg-primary/5 transition-all cursor-pointer">
                            <svg class="w-8 h-8 mb-2 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4M12 4 7 9M12 4l5 5"/><path d="M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/></svg>
                            <span class="text-sm font-medium text-slate-300">Click para subir comprobante</span>
                            <span id="file-name" class="text-xs text-primary mt-2 hidden font-bold"></span>
                        </label>
                    </div>

                    <div id="preview-container" class="hidden mt-4 text-center">
                        <img id="preview-image" class="max-h-64 mx-auto rounded-xl border-4 border-white/10 shadow-lg">
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
                    <a href="#" onclick="contactSupport()" class="text-primary font-bold hover:underline">Contactar soporte</a>
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
            n.innerHTML = '<p class="font-medium">' + msg + '</p>';
            document.body.appendChild(n);
            setTimeout(() => n.remove(), 3000);
        }
    };

    const urlParams = new URLSearchParams(window.location.search);
    const ticketId = urlParams.get('id') || urlParams.get('ticket');

    let selectedMethod = null;
    let countdownInterval = null;

    const paymentInstructions = {
        'NEQUI': '<ol class="space-y-3">' +
            '<li>1. Abre tu app <strong>Nequi</strong>.</li>' +
            '<li>2. Envía el valor exacto al número del organizador.</li>' +
            '<li>3. Toma una captura de pantalla clara del comprobante.</li>' +
            '<li>4. Sube la imagen aquí abajo y pulsa "Finalizar".</li>' +
            '</ol>',
        'BANCOLOMBIA_TRANSFER': '<ol class="space-y-3">' +
            '<li>1. Transfiere desde tu App Bancolombia o Ahorro a la mano.</li>' +
            '<li>2. Verifica que el valor coincida exactamente.</li>' +
            '<li>3. Envía el comprobante por este medio.</li>' +
            '</ol>',
        'DAVIPLATA': '<ol class="space-y-3">' +
            '<li>1. Usa la opción "Pasar Plata" en tu Daviplata.</li>' +
            '<li>2. Confirma que el número de destino sea correcto.</li>' +
            '<li>3. Adjunta la imagen de confirmación aquí.</li>' +
            '</ol>',
        'EFECTY': '<ol class="space-y-3">' +
            '<li>1. Dirígete a un punto Efecty con el valor en efectivo.</li>' +
            '<li>2. Realiza el giro a los datos proporcionados por el vendedor.</li>' +
            '<li>3. Sube la foto del recibo físico.</li>' +
            '</ol>'
    };

    function loadReservation() {
        const reservation = localStorage.getItem('current_reservation');
        if (reservation) {
            try {
                const data = JSON.parse(reservation);
                document.getElementById('ticket-number').textContent = '#' + (data.ticket_number || '--');
                document.getElementById('ticket-price').textContent = Utils.formatPrice(data.ticket_price || 0);
                if (data.reserved_until) {
                    startExpirationCountdown(data.reserved_until);
                }
            } catch (e) {
                console.error('Error parsing reservation:', e);
            }
        } else if (!ticketId) {
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
            document.getElementById('reservation-time').textContent = m.toString().padStart(2, '0') + ':' + s.toString().padStart(2, '0');
        }, 1000);
    }

    function selectPaymentMethod(method) {
        selectedMethod = method;
        document.querySelectorAll('.payment-method').forEach(btn => {
            btn.classList.remove('payment-method--selected');
        });
        const selected = document.querySelector('[data-method="' + method + '"]');
        if (selected) selected.classList.add('payment-method--selected');

        document.getElementById('instructions-content').innerHTML = paymentInstructions[method] || 'Selecciona un método de pago';
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
        btn.textContent = 'Procesando...';

        try {
            const proofFile = document.getElementById('payment-proof').files[0];
            const reader = new FileReader();
            const proofData = await new Promise((resolve) => {
                reader.onload = (e) => resolve(e.target.result);
                reader.readAsDataURL(proofFile);
            });

            const payload = {
                ticket_id: ticketId,
                payment_method: selectedMethod,
                proof: proofData
            };

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

    function contactSupport() {
        window.open('https://wa.me/573001234567?text=' + encodeURIComponent('Hola, tengo un problema con mi pago en MisRifas.'), '_blank');
    }

    loadReservation();
    </script>
</body>
</html>
