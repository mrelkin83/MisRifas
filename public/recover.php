<?php
require_once __DIR__ . '/../config/paths.php';
$page_title = "Recuperar Contrasena - MisRifas";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <script>const BASE_PATH = "<?= BASE_PATH ?>";</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="<?= BASE_PATH ?>/public/css/tailwind.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; overflow-x: hidden; }
        .bg-blob {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1;
            background: radial-gradient(circle at 10% 10%, rgba(37, 99, 235, 0.15) 0%, transparent 40%),
                        radial-gradient(circle at 90% 90%, rgba(16, 185, 129, 0.15) 0%, transparent 40%);
        }
        .form-card {
            background: rgba(30, 41, 59, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 32px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            width: 100%; max-width: 450px; padding: 48px;
        }
        input {
            background: rgba(15, 23, 42, 0.5) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: white !important;
            transition: all 0.3s !important;
        }
        input:focus {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2) !important;
        }
        .btn-primary {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            box-shadow: 0 10px 20px -5px rgba(37, 99, 235, 0.4);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 25px -5px rgba(37, 99, 235, 0.6);
        }
    </style>
</head>
<body>
    <div class="bg-blob"></div>
    <div class="container mx-auto px-4 flex justify-center py-10">
        <div class="form-card">
            <div class="text-center mb-10">
                <a href="<?= BASE_PATH ?>/public/index.php" class="inline-flex items-center gap-2 text-3xl font-black mb-4">
                    <span>🎟️</span>
                    <span class="bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-emerald-400">MisRifas</span>
                </a>
                <h1 class="text-2xl font-bold text-white">Recuperar Contraseña</h1>
                <p class="text-slate-400 mt-2">Ingresa tu email y te enviaremos un enlace para restaurar tu contraseña.</p>
            </div>

            <form id="recovery-form" class="space-y-5">
                <div class="space-y-2">
                    <label class="text-sm font-bold text-slate-400 uppercase tracking-widest ml-1">Email</label>
                    <input type="email" id="email" required class="w-full px-5 py-4 rounded-xl outline-none" placeholder="tu@email.com">
                </div>

                <div id="message-area" class="hidden p-4 rounded-xl text-center"></div>

                <div class="pt-4">
                    <button type="submit" id="submit-btn" class="w-full py-4 rounded-2xl btn-primary text-white font-bold text-lg transition-all">
                        Enviar Enlace de Recuperación
                    </button>
                </div>

                <div class="text-center pt-4">
                    <a href="<?= BASE_PATH ?>/public/admin/index.php?auth=login" class="text-blue-400 font-bold hover:underline">Volver a Iniciar Sesión</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('recovery-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('submit-btn');
            const messageArea = document.getElementById('message-area');
            const email = document.getElementById('email').value;
            
            btn.disabled = true;
            btn.innerHTML = '<span class="animate-spin inline-block mr-2">⏳</span> Enviando...';
            messageArea.classList.add('hidden');

            try {
                const response = await fetch('/api/auth/recover.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email })
                });
                
                const json = await response.json();
                
                messageArea.classList.remove('hidden');
                if (json.success) {
                    messageArea.className = 'p-4 rounded-xl text-center bg-green-500/20 border border-green-500/30 text-green-400';
                    messageArea.textContent = '¡Correo de recuperación enviado! Revisa tu bandeja de entrada.';
                    document.getElementById('recovery-form').reset();
                } else {
                    messageArea.className = 'p-4 rounded-xl text-center bg-red-500/20 border border-red-500/30 text-red-400';
                    messageArea.textContent = json.message || 'Error al enviar el correo';
                }
            } catch (error) {
                messageArea.classList.remove('hidden');
                messageArea.className = 'p-4 rounded-xl text-center bg-red-500/20 border border-red-500/30 text-red-400';
                messageArea.textContent = 'Ocurrió un error al conectar con el servidor.';
            } finally {
                btn.disabled = false;
                btn.textContent = 'Enviar Enlace de Recuperación';
            }
        });
    </script>
</body>
</html>
