<?php
/**
 * Página de Procesamiento de Pago (Polling)
 * Esta página muestra el estado del pago y hace polling al backend
 * hasta recibir confirmación del webhook Wompi
 */

require_once __DIR__ . '/../config/app.php';
$reservationId = $_GET['reservation_id'] ?? '';
$paymentIntentId = $_GET['payment_intent_id'] ?? '';

if (!$reservationId && !$paymentIntentId) {
    header('Location: ' . BASE_PATH . '/public/index.php');
    exit;
}

include __DIR__ . '/partials/header.php';
?>

<style>
    .polling-container {
        max-width: 600px;
        margin: 0 auto;
        padding: 2rem;
        background: rgba(15, 23, 42, 0.8);
        border: 2px solid rgba(59, 130, 246, 0.3);
        border-radius: 1.5rem;
        backdrop-filter: blur(20px);
    }

    .spinner {
        width: 50px;
        height: 50px;
        border: 4px solid rgba(59, 130, 246, 0.2);
        border-top-color: #3b82f6;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    .status-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        animation: pulse 2s ease-in-out infinite;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.5; transform: scale(1.1); }
    }

    .progress-step {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .progress-step:last-child {
        border-bottom: none;
    }

    .step-number {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 0.875rem;
    }

    .step-completed {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
    }

    .step-in-progress {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: white;
    }

    .step-pending {
        background: rgba(100, 116, 139, 0.3);
        color: rgba(255, 255, 255, 0.5);
    }
</style>

<div class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-900 py-20 px-4">
    <div class="polling-container">
        <div id="checkout-content">
            <div class="text-center mb-8">
                <div class="text-6xl mb-6">💳</div>
                <h1 class="text-3xl font-bold text-white mb-2">Completa tu pago</h1>
                <p class="text-slate-300">Selecciona tu método de pago</p>
            </div>

            <div id="reservation-info" class="bg-slate-800/50 rounded-xl p-4 mb-6 border border-slate-700">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-slate-400 text-sm">Reserva ID</span>
                    <span class="text-blue-400 font-mono text-sm"><?php echo htmlspecialchars($reservationId); ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-400 text-sm">Total a pagar</span>
                    <span class="text-emerald-400 font-bold text-lg" id="payment-amount">$0</span>
                </div>
            </div>

            <div class="space-y-3 mb-8">
                <button id="wompi-card-btn" class="w-full py-4 bg-gradient-to-r from-blue-500 to-purple-600 text-white font-bold rounded-xl text-lg hover:brightness-110 transition-all flex items-center justify-center gap-2">
                    <span>Tarjeta (Wompi)</span>
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>
                </button>
                <button id="nequi-btn" class="w-full py-4 bg-gradient-to-r from-pink-500 to-rose-600 text-white font-bold rounded-xl text-lg hover:brightness-110 transition-all flex items-center justify-center gap-2">
                    <span>Nequi</span>
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17 2H7c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-5 18c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4zm0-6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
                </button>
                <button id="pse-btn" class="w-full py-4 bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-bold rounded-xl text-lg hover:brightness-110 transition-all flex items-center justify-center gap-2">
                    <span>PSE</span>
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15l-5-5 1.41-1.41L11 14.17l7.59-7.59L20 8l-9 9z"/></svg>
                </button>
            </div>

            <div id="selected-numbers-list" class="bg-slate-900/50 rounded-xl p-4 mb-6">
                <p class="text-slate-400 text-sm mb-2">Números reservados:</p>
                <div id="numbers-display" class="flex flex-wrap gap-2"></div>
            </div>

            <div class="text-center">
                <p class="text-slate-400 text-xs">Expira en <span id="countdown">10:00</span> minutos</p>
                <p class="text-slate-500 text-xs mt-2">Si el pago no se completa, los números serán liberados</p>
            </div>
        </div>

        <div id="polling-content" class="hidden">
            <div class="text-center mb-8">
                <div class="spinner mx-auto mb-4"></div>
                <h1 class="text-2xl font-bold text-white mb-2">Procesando tu pago...</h1>
                <p class="text-slate-300">Por favor no cierres esta página</p>
            </div>

            <div class="progress-steps mb-8">
                <div class="progress-step">
                    <div class="step-number step-completed">1</div>
                    <div class="flex-1">
                        <p class="text-white font-semibold">Números reservados</p>
                        <p class="text-emerald-400 text-sm">✓ Completado</p>
                    </div>
                </div>
                <div class="progress-step">
                    <div class="step-number step-in-progress">2</div>
                    <div class="flex-1">
                        <p class="text-white font-semibold">Verificando pago Wompi</p>
                        <p class="text-blue-400 text-sm" id="polling-status">Verificando...</p>
                    </div>
                </div>
                <div class="progress-step">
                    <div class="step-number step-pending">3</div>
                    <div class="flex-1">
                        <p class="text-slate-400 font-semibold">Confirmación</p>
                        <p class="text-slate-500 text-sm">Pendiente</p>
                    </div>
                </div>
            </div>
        </div>

        <div id="success-content" class="hidden text-center">
            <div class="text-6xl mb-6">✅</div>
            <h1 class="text-3xl font-bold text-white mb-4">¡Pago completado!</h1>
            <p class="text-slate-300 mb-8">Tus números han sido confirmados exitosamente</p>
            <div id="ticket-section" class="bg-gradient-to-r from-emerald-500/20 to-teal-500/20 rounded-xl p-6 mb-6 border border-emerald-500/30">
                <p class="text-emerald-400 font-bold text-lg mb-4">Tus boletos:</p>
                <div id="confirmed-numbers" class="flex flex-wrap gap-2 justify-center"></div>
            </div>
            <a href="<?php echo BASE_PATH; ?>/public/mis-boletos.php" class="inline-block px-8 py-4 bg-gradient-to-r from-emerald-500 to-teal-500 text-white font-bold rounded-xl hover:brightness-110 transition-all">
                Ver mis boletos →
            </a>
        </div>

        <div id="error-content" class="hidden text-center">
            <div class="text-6xl mb-6">❌</div>
            <h1 class="text-3xl font-bold text-red-400 mb-4">Pago fallido o expirado</h1>
            <p class="text-slate-300 mb-8">Los números han sido liberados. Por favor intenta de nuevo.</p>
            <a href="<?php echo BASE_PATH; ?>/public/raffle.php?id=<?php echo $_GET['raffle_id'] ?? ''; ?>" class="inline-block px-8 py-4 bg-gradient-to-r from-red-500 to-pink-500 text-white font-bold rounded-xl hover:brightness-110 transition-all">
                Volver a la rifa
            </a>
        </div>
    </div>
</div>

<script>
const reservationId = '<?php echo $reservationId; ?>';
const paymentIntentId = '<?php echo $paymentIntentId; ?>';
let pollCount = 0;
const maxPolls = 120;
let countdownTimer;
let remainingSeconds = 600;

function simulatePayment() {
    document.getElementById('checkout-content').classList.add('hidden');
    document.getElementById('polling-content').classList.remove('hidden');
    startPolling();
}

function openWompiCheckout() {
    // For demo purposes, simulate payment process
    // In production, this would integrate with Wompi Checkout widget
    simulatePayment();
}

function payWithNequi() {
    // For demo purposes, simulate payment process
    // In production, this would show Nequi payment instructions
    simulatePayment();
}

function payWithPSE() {
    // For demo purposes, simulate payment process
    // In production, this would integrate with PSE
    simulatePayment();
}

function startPolling() {
    document.getElementById('checkout-content').classList.add('hidden');
    document.getElementById('polling-content').classList.remove('hidden');
    checkPaymentStatus();
}

function checkPaymentStatus() {
    if (pollCount >= maxPolls) {
        stopPolling();
        showTimeout();
        return;
    }

    fetch('<?= BASE_PATH ?>/api/payments/check-status.php?payment_intent_id=' + paymentIntentId)
        .then(response => response.json())
        .then(data => {
            pollCount++;

            if (data.success) {
                const status = data.data.status;

                if (status === 'APPROVED') {
                    stopPolling();
                    showSuccess(data.data);
                } else if (status === 'DECLINED' || status === 'EXPIRED') {
                    stopPolling();
                    showError(data.data);
                } else {
                    document.getElementById('polling-status').textContent = 'Verificando... (' + pollCount + '/' + maxPolls + ')';
                    setTimeout(checkPaymentStatus, 2000);
                }
            } else {
                document.getElementById('polling-status').textContent = 'Error: ' + data.message;
                setTimeout(checkPaymentStatus, 2000);
            }
        })
        .catch(error => {
            console.error('Polling error:', error);
            setTimeout(checkPaymentStatus, 2000);
        });
}

function showSuccess(data) {
    document.getElementById('polling-content').classList.add('hidden');
    document.getElementById('success-content').classList.remove('hidden');

    const numbers = data.numeros || [];
    const confirmedNumbers = document.getElementById('confirmed-numbers');
    confirmedNumbers.innerHTML = numbers.map(num =>
        `<span class="px-4 py-2 bg-emerald-600 text-white rounded-lg font-mono font-bold">${num}</span>`
    ).join('');
}

function showError() {
    document.getElementById('checkout-content')?.classList.add('hidden');
    document.getElementById('polling-content')?.classList.add('hidden');
    document.getElementById('error-content').classList.remove('hidden');
}

function showTimeout() {
    document.getElementById('checkout-content')?.classList.add('hidden');
    document.getElementById('polling-content')?.classList.add('hidden');
    document.getElementById('error-content').classList.remove('hidden');
    document.querySelector('#error-content h1').textContent = 'Tiempo de espera excedido';
    document.querySelector('#error-content p').textContent = 'La reserva ha expirado. Por favor intenta de nuevo.';
}

function startCountdown() {
    const countdownEl = document.getElementById('countdown');

    countdownTimer = setInterval(() => {
        remainingSeconds--;

        if (remainingSeconds <= 0) {
            clearInterval(countdownTimer);
            countdownEl.textContent = '00:00';
            stopPolling();
            showTimeout();
            return;
        }

        const minutes = Math.floor(remainingSeconds / 60);
        const seconds = remainingSeconds % 60;
        countdownEl.textContent = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
    }, 1000);
}

function stopPolling() {
    clearInterval(countdownTimer);
}

function loadSelectedNumbers() {
    const stored = localStorage.getItem('selected_numbers');
    const amount = localStorage.getItem('payment_amount');

    if (stored) {
        const numbers = JSON.parse(stored);
        const display = document.getElementById('numbers-display');
        display.innerHTML = numbers.map(num =>
            `<span class="px-3 py-1 bg-blue-600/30 text-blue-300 rounded-lg font-mono text-sm border border-blue-500/30">${num}</span>`
        ).join('');
    }

    if (amount) {
        document.getElementById('payment-amount').textContent = new Intl.NumberFormat('es-CO', {
            style: 'currency',
            currency: 'COP',
            minimumFractionDigits: 0
        }).format(amount);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    loadSelectedNumbers();
    startCountdown();

    const statusParam = new URLSearchParams(window.location.search).get('status');

    if (statusParam === 'pending') {
        startPolling();
    } else {
        document.getElementById('wompi-card-btn')?.addEventListener('click', openWompiCheckout);
        document.getElementById('nequi-btn')?.addEventListener('click', payWithNequi);
        document.getElementById('pse-btn')?.addEventListener('click', payWithPSE);
    }
});
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
