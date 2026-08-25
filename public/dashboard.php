<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => (getenv('APP_ENV') ?: 'development') === 'production',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/paths.php';

$current_page = 'dashboard';
$basePath = defined('BASE_PATH') ? BASE_PATH : '';

$db = Database::getInstance()->getConnection();

$user = null;
$tickets = [];
$wins = [];
$stats = ['total_tickets' => 0, 'paid_tickets' => 0, 'reserved_tickets' => 0, 'total_wins' => 0, 'total_spent' => 0];

$userId = $_SESSION['user_id'] ?? null;
$userEmail = $_SESSION['user_email'] ?? null;

if ($userId) {
    $userRole = $_SESSION['user_role'] ?? '';

    if ($userRole === 'vendor' || $userRole === 'super_admin') {
        header('Location: ' . $basePath . '/public/vendor/index.php');
        exit;
    }

    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($user) {
    $stmt = $db->prepare("
        SELECT t.*, r.name as raffle_title, r.ticket_price, r.winning_ticket_number, r.winning_mode,
               r.status as raffle_status, l.name as lottery_name,
               rw.id as winner_id
        FROM tickets t
        JOIN raffles r ON t.raffle_id = r.id
        LEFT JOIN lotteries l ON r.lottery_id = l.id
        LEFT JOIN raffle_winners rw ON rw.ticket_id = t.id
        WHERE t.user_id = ?
        ORDER BY t.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$userId]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("
        SELECT COUNT(*) as total,
               SUM(CASE WHEN t.status = 'paid' THEN 1 ELSE 0 END) as paid,
               SUM(CASE WHEN t.status = 'reserved' THEN 1 ELSE 0 END) as reserved,
               SUM(CASE WHEN t.status = 'paid' THEN r.ticket_price ELSE 0 END) as spent
        FROM tickets t
        JOIN raffles r ON t.raffle_id = r.id
        WHERE t.user_id = ?
    ");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $stats['total_tickets'] = (int)$row['total'];
        $stats['paid_tickets'] = (int)$row['paid'];
        $stats['reserved_tickets'] = (int)$row['reserved'];
        $stats['total_spent'] = (float)$row['spent'];
    }

    $stmt = $db->prepare("
        SELECT rw.*, r.name as raffle_title, t.ticket_number, r.ticket_price
        FROM raffle_winners rw
        JOIN raffles r ON rw.raffle_id = r.id
        JOIN tickets t ON rw.ticket_id = t.id
        WHERE t.user_id = ?
        ORDER BY rw.created_at DESC
    ");
    $stmt->execute([$userId]);
    $wins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stats['total_wins'] = count($wins);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <script>const BASE_PATH = "<?= $basePath ?>";</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Panel - MisRifas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT: '#2563eb', dark: '#1e40af', light: '#3b82f6' }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f172a; }
        .glass-nav { background: rgba(15,23,42,0.75); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border-bottom: 1px solid rgba(255,255,255,0.08); }
    </style>
</head>
<body class="min-h-screen text-white">

<?php include __DIR__ . '/partials/header.php'; ?>

<main class="container mx-auto px-4 py-8 max-w-6xl">

    <?php if (!$user): ?>

    <div class="max-w-md mx-auto mt-16 text-center">
        <div class="bg-slate-800/50 backdrop-blur border border-white/10 rounded-2xl p-8">
            <div class="text-5xl mb-4">🎟️</div>
            <h1 class="text-2xl font-bold mb-2">Mi Panel</h1>
            <p class="text-slate-400 mb-6">Inicia sesion para ver tus boletos, rifas y premios.</p>

            <form id="login-form" class="space-y-4 text-left">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Email o Telefono</label>
                    <input type="text" id="login-identifier" required
                           class="w-full px-4 py-3 bg-slate-700/50 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Contrasena</label>
                    <input type="password" id="login-password" required
                           class="w-full px-4 py-3 bg-slate-700/50 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <button type="submit" id="login-btn"
                        class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl transition-colors">
                    Iniciar Sesion
                </button>
            </form>

            <div id="login-msg" class="mt-4 text-sm hidden"></div>

            <p class="mt-4 text-slate-500 text-sm">
                ¿No tienes cuenta?
                <a href="<?= $basePath ?>/public/index.php" class="text-blue-400 hover:text-blue-300">Participa en una rifa</a> y se crea automaticamente.
            </p>
        </div>
    </div>

    <?php else: ?>

    <div class="mb-8">
        <h1 class="text-3xl font-bold">Hola, <?= htmlspecialchars($user['name'] ?? $user['email'] ?? 'Usuario') ?></h1>
        <p class="text-slate-400 mt-1">Bienvenido a tu panel de rifas</p>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-slate-800/50 border border-white/10 rounded-xl p-4 text-center">
            <div class="text-3xl font-bold text-blue-400"><?= $stats['total_tickets'] ?></div>
            <div class="text-sm text-slate-400 mt-1">Boletos</div>
        </div>
        <div class="bg-slate-800/50 border border-white/10 rounded-xl p-4 text-center">
            <div class="text-3xl font-bold text-green-400"><?= $stats['paid_tickets'] ?></div>
            <div class="text-sm text-slate-400 mt-1">Pagados</div>
        </div>
        <div class="bg-slate-800/50 border border-white/10 rounded-xl p-4 text-center">
            <div class="text-3xl font-bold text-yellow-400"><?= $stats['total_wins'] ?></div>
            <div class="text-sm text-slate-400 mt-1">Premios</div>
        </div>
        <div class="bg-slate-800/50 border border-white/10 rounded-xl p-4 text-center">
            <div class="text-3xl font-bold text-emerald-400">$<?= number_format($stats['total_spent'], 0, ',', '.') ?></div>
            <div class="text-sm text-slate-400 mt-1">Total Invertido</div>
        </div>
    </div>

    <?php if (!empty($wins)): ?>
    <section class="mb-8">
        <h2 class="text-xl font-bold mb-4 flex items-center gap-2">🏆 Mis Premios</h2>
        <div class="space-y-3">
            <?php foreach ($wins as $w): ?>
            <div class="bg-gradient-to-r from-yellow-500/10 to-orange-500/10 border border-yellow-500/30 rounded-xl p-4 flex items-center justify-between">
                <div>
                    <div class="font-bold"><?= htmlspecialchars($w['raffle_title']) ?></div>
                    <div class="text-sm text-slate-400">
                        Boleta #<?= str_pad($w['ticket_number'], 4, '0', STR_PAD_LEFT) ?>
                        <?php if (!empty($w['prize_description'])): ?>
                            — <?= htmlspecialchars($w['prize_description']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="text-right">
                    <?php if (!empty($w['prize_amount']) && $w['prize_amount'] > 0): ?>
                        <div class="text-lg font-bold text-yellow-400">$<?= number_format($w['prize_amount'], 0, ',', '.') ?></div>
                    <?php else: ?>
                        <div class="text-sm font-bold text-yellow-400">Premio</div>
                    <?php endif; ?>
                    <div class="text-xs text-slate-500"><?= date('d/m/Y', strtotime($w['created_at'])) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <section>
        <h2 class="text-xl font-bold mb-4 flex items-center gap-2">🎫 Mis Boletos</h2>

        <?php if (empty($tickets)): ?>
        <div class="bg-slate-800/50 border border-white/10 rounded-xl p-8 text-center">
            <div class="text-4xl mb-3">🎪</div>
            <p class="text-slate-400">Aun no tienes boletos.</p>
            <a href="<?= $basePath ?>/public/index.php" class="inline-block mt-4 px-6 py-3 bg-blue-600 hover:bg-blue-700 rounded-xl font-bold transition-colors">
                Ver Rifas Disponibles
            </a>
        </div>
        <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($tickets as $t): ?>
            <?php
                $isWinner = !empty($t['winner_id']);
                $statusColors = [
                    'reserved' => 'bg-yellow-500/20 text-yellow-300 border-yellow-500/30',
                    'paid' => 'bg-green-500/20 text-green-300 border-green-500/30',
                    'available' => 'bg-slate-500/20 text-slate-300 border-slate-500/30',
                ];
                $statusLabel = [
                    'reserved' => 'Reservado',
                    'paid' => 'Pagado',
                    'available' => 'Disponible',
                ];
                $sc = $statusColors[$t['status']] ?? $statusColors['available'];
                $sl = $statusLabel[$t['status']] ?? $t['status'];
                $borderClass = $isWinner ? 'border-yellow-500/40 bg-gradient-to-r from-yellow-500/5' : 'border-white/10 bg-slate-800/50';
            ?>
            <div class="<?= $borderClass ?> border rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex-1">
                    <div class="font-bold flex items-center gap-2">
                        <?= htmlspecialchars($t['raffle_title']) ?>
                        <?php if ($isWinner): ?>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-yellow-500/20 text-yellow-300 font-bold">GANADOR</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-sm text-slate-400 mt-1 flex flex-wrap gap-3">
                        <span>Boleta: <strong class="text-white"><?= str_pad($t['ticket_number'], 4, '0', STR_PAD_LEFT) ?></strong></span>
                        <span>Loteria: <?= htmlspecialchars($t['lottery_name'] ?? 'N/A') ?></span>
                        <?php if ($t['winning_number']): ?>
                            <span>Ganador: <strong class="text-yellow-400"><?= $t['winning_number'] ?></strong></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-sm font-medium">$<?= number_format($t['ticket_price'], 0, ',', '.') ?></span>
                    <span class="text-xs px-3 py-1 rounded-full border <?= $sc ?>"><?= $sl ?></span>
                    <?php if ($t['raffle_status'] === 'completed' && !$isWinner && $t['status'] === 'paid'): ?>
                        <span class="text-xs text-slate-500">No ganador</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <?php endif; ?>

</main>

<?php include __DIR__ . '/partials/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('login-form');
    if (!form) return;

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('login-btn');
        const msg = document.getElementById('login-msg');
        const identifier = document.getElementById('login-identifier').value.trim();
        const password = document.getElementById('login-password').value;

        btn.disabled = true;
        btn.textContent = 'Ingresando...';
        msg.classList.add('hidden');

        try {
            const res = await fetch(BASE_PATH + '/api/auth/login.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ identifier, password })
            });
            const data = await res.json();

            if (data.success) {
                if (data.data && data.data.token) {
                    localStorage.setItem('misrifas_token', data.data.token);
                }
                if (data.data && data.data.user) {
                    localStorage.setItem('misrifas_user', JSON.stringify(data.data.user));
                }
                msg.textContent = 'Inicio de sesion exitoso. Redirigiendo...';
                msg.className = 'mt-4 text-sm text-green-400';
                msg.classList.remove('hidden');
                setTimeout(() => location.reload(), 1000);
            } else {
                msg.textContent = data.message || 'Credenciales incorrectas.';
                msg.className = 'mt-4 text-sm text-red-400';
                msg.classList.remove('hidden');
            }
        } catch (err) {
            msg.textContent = 'Error de conexion.';
            msg.className = 'mt-4 text-sm text-red-400';
            msg.classList.remove('hidden');
        }

        btn.disabled = false;
        btn.textContent = 'Iniciar Sesion';
    });
});
</script>

</body>
</html>
