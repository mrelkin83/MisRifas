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
                        primary: { DEFAULT: '#2563eb', dark: '#1e40af', light: '#3b82f6' }
                    },
                    screens: {
                        'xs': '480px',
                    }
                }
            }
        }
    </script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f9fafb; }
        .payment-method {
            padding: 16px; border: 2px solid #e5e7eb; border-radius: 12px;
            cursor: pointer; transition: all 0.2s; text-align: left;
            width: 100%;
        }
        .payment-method:hover { border-color: #2563eb; background: #eff6ff; }
        .payment-method--selected { border-color: #2563eb; background: #eff6ff; box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1); }
        .notification { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); max-width: 450px; width: 90%; padding: 20px 30px; background: white; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3); z-index: 9999; animation: fadeIn 0.3s ease; text-align: center; font-size: 16px; }
        .notification--error { border: 2px solid #ef4444; color: #991b1b; }
        .notification--success { border: 2px solid #10b981; color: #065f46; }
        .notification--info { border: 2px solid #3b82f6; color: #1e40af; }
        @keyframes fadeIn { from { opacity: 0; transform: translate(-50%, -50%) scale(0.9); } to { opacity: 1; transform: translate(-50%, -50%) scale(1); } }
        .countdown-timer { font-variant-numeric: tabular-nums; }
        
        @media (max-width: 640px) {
            .payment-methods-grid { grid-template-columns: 1fr !important; }
            .glass-card { padding: 1.5rem !important; }
        }
    </style>
</head>
<body class="bg-gray-50">
    <header class="bg-white shadow-sm sticky top-0 z-50">
        <nav class="container mx-auto px-4 h-16 flex items-center justify-between">
            <a href="/" class="flex items-center gap-2 text-xl font-bold text-primary">
                <span class="text-2xl">🎟️</span>
                <span class="hidden xs:inline">MisRifas</span>
            </a>
            <div class="flex items-center gap-4">
                <a href="/mis-boletos" class="text-gray-700 hover:text-primary font-medium text-xs sm:text-sm">Mis Boletas</a>
            </div>
        </nav>
    </header>

    <main class="py-6 sm:py-8">
        <div class="container mx-auto px-4 max-w-2xl">
            <div class="bg-white rounded-2xl shadow-md p-5 sm:p-8">
                <h1 class="text-2xl font-bold mb-6 text-center sm:text-left">Completar Pago</h1>

                <div id="reservation-info" class="bg-blue-50 border border-blue-100 rounded-xl p-4 mb-6">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-gray-600 text-sm">Boleto:</span>
                        <span id="ticket-number" class="text-xl font-black text-primary font-mono">--</span>
                    </div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-gray-600 text-sm">Precio:</span>
                        <span id="ticket-price" class="text-xl font-black text-primary font-mono">$0</span>
                    </div>
                    <div class="flex justify-between items-center pt-2 border-t border-blue-100">
                        <span class="text-gray-600 text-sm">Expira en:</span>
                        <span id="reservation-time" class="text-base font-bold text-red-600 countdown-timer">--:--</span>
                    </div>
                </div>

                <div class="mb-8">
                    <h2 class="text-lg font-bold mb-4">Selecciona método de pago</h2>
                    <div class="grid grid-cols-1 xs:grid-cols-2 gap-3 payment-methods-grid">
                        <button onclick="selectPaymentMethod('NEQUI')" class="payment-method" data-method="NEQUI">
                            <div class="font-bold flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                                Nequi
                            </div>
                            <div class="text-xs text-gray-500 mt-1">Transferencia directa</div>
                        </button>
                        <button onclick="selectPaymentMethod('BANCOLOMBIA_TRANSFER')" class="payment-method" data-method="BANCOLOMBIA_TRANSFER">
                            <div class="font-bold flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-yellow-400"></span>
                                Bancolombia
                            </div>
                            <div class="text-xs text-gray-500 mt-1">Ahorro a la mano/App</div>
                        </button>
                        <button onclick="selectPaymentMethod('DAVIPLATA')" class="payment-method" data-method="DAVIPLATA">
                            <div class="font-bold flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-red-500"></span>
                                Daviplata
                            </div>
                            <div class="text-xs text-gray-500 mt-1">Desde tu celular</div>
                        </button>
                        <button onclick="selectPaymentMethod('EFECTY')" class="payment-method" data-method="EFECTY">
                            <div class="font-bold flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                Efecty
                            </div>
                            <div class="text-xs text-gray-500 mt-1">Pago en efectivo</div>
                        </button>
                    </div>
                </div>

                <div id="payment-instructions" class="hidden mb-8 animate-in fade-in slide-in-from-top-2 duration-300">
                    <h3 class="font-bold mb-3 flex items-center gap-2 text-primary">
                        <span>📝</span> Instrucciones:
                    </h3>
                    <div id="instructions-content" class="bg-gray-50 border border-gray-100 rounded-xl p-5 text-gray-700 text-sm leading-relaxed"></div>
                </div>

                <div class="mb-8">
                    <h3 class="font-bold mb-2">Comprobante de pago</h3>
                    <p class="text-xs text-gray-500 mb-3 italic">Sube una captura de pantalla del pago para agilizar la verificación.</p>
                    
                    <div class="relative">
                        <input type="file" id="payment-proof" accept="image/*" class="hidden">
                        <label for="payment-proof" class="w-full flex flex-col items-center justify-center border-2 border-dashed border-gray-300 rounded-xl p-6 hover:border-primary hover:bg-blue-50 transition-all cursor-pointer">
                            <span class="text-3xl mb-2">📸</span>
                            <span class="text-sm font-medium text-gray-600">Click para subir comprobante</span>
                            <span id="file-name" class="text-xs text-primary mt-2 hidden font-bold"></span>
                        </label>
                    </div>

                    <div id="preview-container" class="hidden mt-4 text-center">
                        <img id="preview-image" class="max-h-64 mx-auto rounded-xl border-4 border-white shadow-lg">
                        <button onclick="removeImage()" class="text-red-500 text-xs font-bold mt-2 hover:underline">Eliminar imagen</button>
                    </div>
                </div>

                <button id="confirm-payment-btn" class="w-full py-5 bg-primary text-white font-black rounded-2xl text-xl hover:bg-primary-dark disabled:opacity-50 disabled:grayscale transition-all shadow-xl hover:shadow-blue-500/20" disabled>
                    Finalizar Compra 🚀
                </button>

                <p class="text-[10px] text-gray-400 text-center mt-6 uppercase tracking-widest">
                    Seguridad cifrada SSL · Verificación humana
                </p>
            </div>

            <div class="mt-8 text-center">
                <p class="text-sm text-gray-500">
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
                btn.textContent = 'Finalizar Compra 🚀';
            }
        } catch (error) {
            Utils.showNotification('Error de conexión', 'error');
            btn.disabled = false;
            btn.textContent = 'Finalizar Compra 🚀';
        }
    });

    function contactSupport() {
        window.open('https://wa.me/573001234567?text=' + encodeURIComponent('Hola, tengo un problema con mi pago en MisRifas.'), '_blank');
    }

    loadReservation();
    </script>
</body>
</html>
