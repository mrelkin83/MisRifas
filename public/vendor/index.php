<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/../../config/database.php';
$page_title = "Panel de Administración - MisRifas";
$is_auth_page = isset($_GET['auth']) && in_array($_GET['auth'], ['login', 'register']);
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
    <meta name="theme-color" content="#f3f4f6">
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
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f3f4f6; color: #111827; }

        .admin-layout { display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: #1e293b; color: white; display: flex; flex-direction: column; position: fixed; top: 0; left: 0; bottom: 0; z-index: 40; }
        .sidebar-header { padding: 20px; border-bottom: 1px solid #334155; }
        .logo { display: flex; align-items: center; gap: 10px; font-size: 20px; font-weight: 700; font-family: 'Outfit', 'Inter', sans-serif; }
        .logo__icon { width: 26px; height: 26px; color: #f59e0b; flex-shrink: 0; }
        .sidebar-nav { flex: 1; padding: 12px 0; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 20px; padding-left: 17px; border-left: 3px solid transparent; color: #94a3b8; text-decoration: none; transition: background-color 0.2s, color 0.2s, border-color 0.2s; cursor: pointer; }
        .nav-item:hover { background: #334155; color: white; }
        .nav-item--active { background: rgba(245, 158, 11, 0.12); color: #fbbf24; border-left-color: #f59e0b; }
        .nav-icon { width: 20px; height: 20px; flex-shrink: 0; }
        .nav-text { font-size: 14px; font-weight: 500; }
        .sidebar-footer { padding: 16px 20px; border-top: 1px solid #334155; }
        .logout-btn { display: flex; align-items: center; gap: 8px; width: 100%; padding: 10px; background: #dc2626; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; transition: background 0.2s; }
        .logout-btn:hover { background: #b91c1c; }

        .admin-main { flex: 1; margin-left: 260px; }
        .admin-header { background: white; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb; position: sticky; top: 0; z-index: 30; }
        .admin-header h1 { font-size: 24px; font-weight: 700; color: #111827; font-family: 'Outfit', 'Inter', sans-serif; }
        .user-menu { display: flex; align-items: center; gap: 12px; }
        .user-name { font-size: 14px; color: #6b7280; }
        .user-avatar { width: 36px; height: 36px; background: #fef3c7; color: #b45309; font-weight: 700; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 15px; }
        .user-menu { position: relative; }
        .user-menu-btn { display: flex; align-items: center; gap: 10px; background: none; border: none; cursor: pointer; padding: 4px 8px; border-radius: 10px; transition: background 0.2s; }
        .user-menu-btn:hover { background: #f3f4f6; }
        .user-menu-caret { color: #9ca3af; transition: transform 0.2s; }
        .user-menu-btn[aria-expanded="true"] .user-menu-caret { transform: rotate(180deg); }
        .user-dropdown { position: absolute; right: 0; top: calc(100% + 8px); background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 12px 32px rgba(0,0,0,0.14); min-width: 190px; padding: 6px; z-index: 60; }
        .user-dropdown.hidden { display: none; }
        .user-dropdown button { display: block; width: 100%; text-align: left; padding: 11px 12px; border: none; background: none; border-radius: 8px; cursor: pointer; font-size: 14px; color: #374151; font-weight: 500; }
        .user-dropdown button:hover { background: #f3f4f6; }
        .user-dropdown__logout { color: #dc2626 !important; }

        .admin-content { padding: 24px; }
        .admin-section { display: block; }
        .admin-section.hidden { display: none; }
        .hidden { display: none !important; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: white; border-radius: 12px; padding: 20px; display: flex; align-items: center; gap: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .stat-icon svg { width: 24px; height: 24px; color: white; }
        .stat-value { font-size: 28px; font-weight: 700; color: #111827; font-family: 'Outfit', 'Inter', sans-serif; }
        .stat-label { font-size: 14px; color: #6b7280; }

        .section-card { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 24px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .section-header h2 { font-size: 18px; font-weight: 700; color: #111827; font-family: 'Outfit', 'Inter', sans-serif; }

        .table-responsive { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 12px 16px; font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid #e5e7eb; }
        .data-table td { padding: 12px 16px; font-size: 14px; color: #374151; border-bottom: 1px solid #f3f4f6; }
        .data-table tr:hover td { background: #f9fafb; }

        .badge { display: inline-block; padding: 4px 10px; border-radius: 9999px; font-size: 12px; font-weight: 600; }
        .badge--active { background: #d1fae5; color: #065f46; }
        .badge--completed { background: #dbeafe; color: #1e40af; }
        .badge--cancelled { background: #fee2e2; color: #991b1b; }
        .badge--pending { background: #fef3c7; color: #92400e; }

        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px; font-size: 14px; font-weight: 600; border-radius: 8px; cursor: pointer; border: none; transition: background-color 0.2s, color 0.2s, box-shadow 0.2s; }
        .btn--primary { background: #f59e0b; color: #1c1305; }
        .btn--primary:hover { background: #d97706; }
        .btn--primary:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn--outline { background: white; color: #b45309; border: 1px solid #f59e0b; }
        .btn--outline:hover { background: #fffbeb; }
        .btn--sm { padding: 4px 12px; font-size: 12px; }
        .btn--lg { padding: 12px 24px; font-size: 16px; }

        .form-stack { display: flex; flex-direction: column; gap: 16px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { display: flex; flex-direction: column; gap: 4px; }
        .form-group label { font-size: 14px; font-weight: 500; color: #374151; }
        .form-group input, .form-group select, .form-group textarea {
            padding: 10px 14px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px;
            transition: border-color 0.2s; outline: none; width: 100%;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #f59e0b; }
        .form-group select option:disabled { color: #9ca3af; background: #f3f4f6; }
        .form-group small { font-size: 12px; color: #6b7280; }
        #image-drop-zone:focus-visible { outline: 3px solid #f59e0b; outline-offset: 2px; border-color: #f59e0b; }

        .toggle-label { display: flex; align-items: center; gap: 12px; cursor: pointer; }
        .toggle-slider { position: relative; width: 48px; height: 24px; background: #e5e7eb; border-radius: 12px; transition: background 0.2s; }
        .toggle-slider::after { content: ''; position: absolute; top: 2px; left: 2px; width: 20px; height: 20px; background: white; border-radius: 50%; transition: transform 0.2s; }
        /* Ocultar el checkbox de forma accesible y SOLO dentro de .toggle-label:
           la regla global display:none escondía cualquier checkbox normal del
           panel y sacaba los toggles del orden de tabulación. */
        .toggle-label { position: relative; }
        .toggle-label input[type="checkbox"] { position: absolute; opacity: 0; width: 1px; height: 1px; overflow: hidden; }
        input[type="checkbox"]:checked + .toggle-slider { background: #f59e0b; }
        input[type="checkbox"]:checked + .toggle-slider::after { transform: translateX(24px); }
        input[type="checkbox"]:focus-visible + .toggle-slider { outline: 2px solid #f59e0b; outline-offset: 2px; }

        .notification { 
            position: fixed; 
            top: 50%; 
            left: 50%; 
            transform: translate(-50%, -50%);
            max-width: 450px; 
            width: 90%;
            padding: 20px 30px; 
            background: white; 
            border-radius: 16px; 
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3); 
            z-index: 9999; 
            animation: fadeIn 0.3s ease;
            text-align: center;
            font-size: 16px;
        }
        .notification--error { border: 2px solid #ef4444; color: #991b1b; }
        .notification--success { border: 2px solid #10b981; color: #065f46; }
        .notification--info { border: 2px solid #3b82f6; color: #1e40af; }
        .notification--warning { border: 2px solid #f59e0b; color: #92400e; }
        @keyframes fadeIn { from { opacity: 0; transform: translate(-50%, -50%) scale(0.9); } to { opacity: 1; transform: translate(-50%, -50%) scale(1); } }

        @media (max-width: 768px) {
            .sidebar { 
                position: fixed;
                top: 0;
                left: 0;
                bottom: 0;
                width: 260px; 
                transform: translateX(-100%); 
                transition: transform 0.3s;
                z-index: 100;
            }
            .sidebar.sidebar--active { transform: translateX(0); }
            .admin-main { margin-left: 0 !important; width: 100%; overflow-x: hidden; }
            .banner-file { max-width: 100%; box-sizing: border-box; }
            .admin-header { padding-left: 60px !important; }
            .form-row { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            /* Las tarjetas de estadística se salían: el número grande no encogía. */
            .stat-card { padding: 14px; gap: 10px; min-width: 0; }
            .stat-content { min-width: 0; }
            .stat-value { font-size: 18px; word-break: break-word; }
            .stat-label { font-size: 12px; }
            /* Tablas anchas (rifas, pagos, etc.): scroll horizontal interno en
               vez de desbordar y empujar toda la página. */
            .data-table { display: block; overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch; }
            .mobile-toggle { 
                display: flex !important; 
                position: absolute; 
                left: 16px; 
                top: 50%; 
                transform: translateY(-50%); 
                z-index: 110;
                background: #f3f4f6;
                padding: 8px;
                border-radius: 8px;
            }
            .sidebar-overlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.5);
                backdrop-filter: blur(4px);
                z-index: 90;
            }
            .sidebar-overlay.active { display: block; }
        }

        /* Social Login Buttons */
        .social-divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 24px 0;
        }
        .social-divider::before,
        .social-divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e5e7eb;
        }
        .social-divider span {
            padding: 0 12px;
            color: #6b7280;
            font-size: 14px;
        }
        .social-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            background: white;
            color: #374151;
            cursor: pointer;
            transition: background-color 0.2s, border-color 0.2s, transform 0.2s, box-shadow 0.2s;
            margin-bottom: 12px;
        }
        .social-btn:hover {
            border-color: #d1d5db;
            background: #f9fafb;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .social-btn svg {
            width: 20px;
            height: 20px;
        }
        .social-btn--google:hover {
            border-color: #4285f4;
        }
        .social-btn--facebook {
            background: #1877f2;
            color: white;
            border-color: #1877f2;
        }
        .social-btn--facebook:hover {
            background: #166fe5;
            border-color: #166fe5;
        }
    </style>
</head>
<body>
<?php if ($is_auth_page): ?>
    <div class="min-h-screen flex items-center justify-center bg-gray-100 p-4">
        <div class="bg-white rounded-2xl shadow-xl p-8 w-full max-w-md">
            <?php if (isset($_GET['auth']) && $_GET['auth'] === 'login'): ?>
            <div class="text-center mb-8">
                <span class="text-5xl" aria-hidden="true">🎟️</span>
                <h1 class="text-2xl font-bold mt-4" style="color:#111827">MisRifas</h1>
                <p class="text-gray-500">Inicia sesión en tu cuenta</p>
            </div>
            <form id="login-form" class="space-y-4">
                <div>
                    <label for="login-email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" id="login-email" name="email" autocomplete="email" spellcheck="false" required class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary" placeholder="tu@email.com">
                </div>
                <div>
                    <label for="login-password" class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
                    <input type="password" id="login-password" name="password" autocomplete="current-password" required class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary" placeholder="••••••••">
                </div>
                <div id="login-error" class="hidden text-red-500 text-sm text-center"></div>
                <button type="submit" id="login-btn" class="w-full py-4 bg-primary text-slate-950 font-bold rounded-xl hover:bg-primary-dark disabled:opacity-50">
                    Iniciar Sesión
                </button>
            </form>

            <div class="social-divider">
                <span>O continúa con</span>
            </div>

            <button onclick="loginWithGoogle()" class="social-btn social-btn--google">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                </svg>
                Continuar con Google
            </button>

            <button onclick="loginWithFacebook()" class="social-btn social-btn--facebook">
                <svg viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg">
                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                </svg>
                Continuar con Facebook
            </button>

            <p class="text-center mt-4 text-gray-600">
                ¿Olvidaste tu contraseña?
                <a href="<?= BASE_PATH ?>/public/recover.php" class="text-primary font-medium hover:underline">Recupérala aquí</a>
            </p>
            <p class="text-center mt-6 text-gray-600">
                ¿No tienes cuenta?
                <a href="?auth=register" class="text-primary font-medium hover:underline">Regístrate gratis</a>
            </p>
            <?php else: ?>
            <div class="text-center mb-8">
                <span class="text-5xl" aria-hidden="true">🎟️</span>
                <h1 class="text-2xl font-bold mt-4" style="color:#111827">Crear Cuenta</h1>
                <p class="text-gray-500">Regístrate para crear tus rifas</p>
            </div>
            <form id="register-form" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="reg-first_name" class="block text-sm font-medium text-gray-700 mb-1">Nombres *</label>
                        <input type="text" id="reg-first_name" name="first_name" autocomplete="given-name" required class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary" placeholder="Juan">
                    </div>
                    <div>
                        <label for="reg-last_name" class="block text-sm font-medium text-gray-700 mb-1">Apellidos *</label>
                        <input type="text" id="reg-last_name" name="last_name" autocomplete="family-name" required class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary" placeholder="Pérez">
                    </div>
                </div>
                <div>
                    <label for="reg-document_id" class="block text-sm font-medium text-gray-700 mb-1">Documento de identidad *</label>
                    <input type="text" id="reg-document_id" name="document_id" inputmode="numeric" spellcheck="false" autocomplete="off" required class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary" placeholder="1234567890">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="reg-department" class="block text-sm font-medium text-gray-700 mb-1">Departamento *</label>
                        <select id="reg-department" name="department" required onchange="loadCitiesForRegister()" class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                            <option value="">Seleccionar</option>
                        </select>
                    </div>
                    <div>
                        <label for="reg-city" class="block text-sm font-medium text-gray-700 mb-1">Ciudad *</label>
                        <select id="reg-city" name="city" required class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                            <option value="">Seleccionar</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label for="reg-phone" class="block text-sm font-medium text-gray-700 mb-1">WhatsApp *</label>
                    <input type="tel" id="reg-phone" name="phone" autocomplete="tel" inputmode="numeric" required class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary" placeholder="3001234567">
                </div>
                <div>
                    <label for="reg-email" class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                    <input type="email" id="reg-email" name="email" autocomplete="email" spellcheck="false" required class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary" placeholder="tu@email.com">
                </div>
                <div>
                    <label for="reg-password" class="block text-sm font-medium text-gray-700 mb-1">Contraseña * (mínimo 8 caracteres)</label>
                    <input type="password" id="reg-password" name="password" autocomplete="new-password" required minlength="8" class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary" placeholder="••••••••">
                </div>
                <div id="register-error" class="hidden text-red-500 text-sm text-center"></div>
                <button type="submit" id="register-btn" class="w-full py-4 bg-primary text-slate-950 font-bold rounded-xl hover:bg-primary-dark disabled:opacity-50">
                    Crear Cuenta
                </button>
            </form>

            <div class="social-divider">
                <span>O regístrate con</span>
            </div>

            <button onclick="loginWithGoogle()" class="social-btn social-btn--google">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                </svg>
                Continuar con Google
            </button>

            <button onclick="loginWithFacebook()" class="social-btn social-btn--facebook">
                <svg viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg">
                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                </svg>
                Continuar con Facebook
            </button>

            <p class="text-center mt-6 text-gray-600">
                ¿Ya tienes cuenta?
                <a href="?auth=login" class="text-primary font-medium hover:underline">Inicia sesión</a>
            </p>
            <?php endif; ?>
        </div>
    </div>
    <script>
    function showAuthNotification(msg, type) {
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

    document.getElementById('login-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const email = document.getElementById('login-email').value.trim();
        const password = document.getElementById('login-password').value;
        const btn = document.getElementById('login-btn');
        const errorDiv = document.getElementById('login-error');

        btn.disabled = true;
        btn.textContent = 'Iniciando sesión…';
        errorDiv.classList.add('hidden');

        try {
            const res = await fetch(BASE_PATH + '/api/auth/login.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, password })
            });
            const data = await res.json();
            if (data.success) {
                const user = data.data.user;
                localStorage.setItem('misrifas_token', data.data.token);
                localStorage.setItem('misrifas_user', JSON.stringify(user));
                showAuthNotification('¡Bienvenido!', 'success');

                if (user.role === 'buyer') {
                    setTimeout(() => window.location.href = BASE_PATH + '/public/dashboard.php', 500);
                } else {
                    setTimeout(() => window.location.href = BASE_PATH + '/public/vendor/index.php', 500);
                }
            } else {
                errorDiv.textContent = data.message || 'Credenciales incorrectas';
                errorDiv.classList.remove('hidden');
            }
        } catch (err) {
            errorDiv.textContent = 'Error de conexión. Intenta de nuevo.';
            errorDiv.classList.remove('hidden');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Iniciar Sesión';
        }
    });

    document.getElementById('register-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        // Combinar nombre y apellido
        const firstName = document.getElementById('reg-first_name').value.trim();
        const lastName = document.getElementById('reg-last_name').value.trim();
        const fullName = (firstName + ' ' + lastName).trim();
        
        const data = {
            role: 'vendor',
            name: fullName,
            first_name: firstName,
            last_name: lastName,
            document_id: document.getElementById('reg-document_id').value.trim(),
            department: document.getElementById('reg-department').value,
            city: document.getElementById('reg-city').value,
            phone: document.getElementById('reg-phone').value.trim(),
            email: document.getElementById('reg-email').value.trim(),
            password: document.getElementById('reg-password').value
        };
        const btn = document.getElementById('register-btn');
        const errorDiv = document.getElementById('register-error');

        btn.disabled = true;
        btn.textContent = 'Creando cuenta…';
        errorDiv.classList.add('hidden');

        try {
            const res = await fetch(BASE_PATH + '/api/auth/register.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await res.json();
            if (result.success) {
                showAuthNotification('¡Registro exitoso! Ahora puedes iniciar sesión.', 'success');
                setTimeout(() => window.location.href = '?auth=login', 1500);
            } else {
                errorDiv.textContent = result.message || 'Error al registrar';
                errorDiv.classList.remove('hidden');
            }
        } catch (err) {
            errorDiv.textContent = 'Error de conexión. Intenta de nuevo.';
            errorDiv.classList.remove('hidden');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Crear Cuenta';
        }
    });

    // Load geography data for registration form
    let registerColombiaData = [];
    async function loadGeographyForRegister() {
        try {
            const res = await fetch(BASE_PATH + '/public/assets/data/colombia.json');
            registerColombiaData = await res.json();

            const deptSelect = document.getElementById('reg-department');
            if (deptSelect) {
                deptSelect.innerHTML = '<option value="">Seleccionar</option>' +
                    registerColombiaData.map(d => `<option value="${d.departamento}">${d.departamento}</option>`).join('');
            }
        } catch (e) {
            console.error('Error loading geography:', e);
            showAuthNotification('Error al cargar departamentos', 'error');
        }
    }

    function loadCitiesForRegister() {
        const deptSelect = document.getElementById('reg-department');
        const citySelect = document.getElementById('reg-city');
        const dept = registerColombiaData.find(d => d.departamento === deptSelect.value);
        
        if (dept && citySelect) {
            citySelect.innerHTML = '<option value="">Seleccionar</option>' + 
                dept.ciudades.map(c => `<option value="${c}">${c}</option>`).join('');
        } else if (citySelect) {
            citySelect.innerHTML = '<option value="">Selecciona departamento primero</option>';
        }
    }

    // Load geography when showing register form
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('auth') === 'register') {
        loadGeographyForRegister();
    }

    // Social Login Functions
    function loginWithGoogle() {
        showAuthNotification('Redirigiendo a Google...', 'info');
        // Redirect to Google OAuth
        window.location.href = BASE_PATH + '/api/auth/google.php';
    }

    function loginWithFacebook() {
        showAuthNotification('Redirigiendo a Facebook...', 'info');
        // Redirect to Facebook OAuth
        window.location.href = BASE_PATH + '/api/auth/facebook.php';
    }

    // Mostrar errores devueltos por los callbacks/iniciadores OAuth (antes se
    // ignoraban y el usuario aterrizaba en el login sin explicación, o veía
    // el crudo 400 de Google cuando las credenciales no están configuradas).
    (function () {
        var err = new URLSearchParams(window.location.search).get('error');
        if (!err) return;
        var msgs = {
            google_no_configurado:   'El inicio de sesión con Google no está disponible todavía.',
            facebook_no_configurado: 'El inicio de sesión con Facebook no está disponible todavía.',
            invalid_state:           'La sesión de acceso expiró. Intenta de nuevo.',
            no_code:                 'No se recibió el código de autorización. Intenta de nuevo.',
            oauth_failed:            'No se pudo completar el inicio de sesión social. Intenta de nuevo.'
        };
        if (msgs[err]) showAuthNotification(msgs[err], 'error');
    })();
    </script>
<?php else: ?>
    <div class="admin-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <svg class="logo__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v1a2 2 0 0 0 0 4v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1a2 2 0 0 0 0-4Z"/><path d="M13 5v14" stroke-dasharray="2 3"/></svg>
                    <span class="logo__text">MisRifas</span>
                </div>
            </div>
            <nav class="sidebar-nav">
                <a href="#dashboard" class="nav-item nav-item--active" data-section="dashboard" onclick="switchTo('dashboard')">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 15l4-5 3 3 5-7"/></svg>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="#crear" class="nav-item" data-section="crear" onclick="switchTo('crear')">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></svg>
                    <span class="nav-text">Crear Rifa</span>
                </a>
                <a href="#pagos" class="nav-item" data-section="pagos" onclick="switchTo('pagos')">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="3"/></svg>
                    <span class="nav-text">Pagos Recibidos</span>
                </a>
                <a href="#boletas-compradas" class="nav-item" data-section="boletas-compradas" onclick="switchTo('boletas-compradas')">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v1a2 2 0 0 0 0 4v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1a2 2 0 0 0 0-4Z"/><path d="M9 8l6 8M15 8l-6 8" stroke-dasharray="1 2.5"/></svg>
                    <span class="nav-text">Boletas Compradas</span>
                </a>
                <a href="#mi-perfil" class="nav-item" data-section="mi-perfil" onclick="switchTo('mi-perfil')">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg>
                    <span class="nav-text">Mi Perfil (Integraciones)</span>
                </a>
                <a href="#comisiones" class="nav-item" data-section="comisiones" id="nav-comisiones" onclick="switchTo('comisiones')">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                    <span class="nav-text">Comisiones</span>
                </a>
                <a href="#gestion-rifas" class="nav-item" data-section="gestion-rifas" id="nav-gestion-rifas" onclick="switchTo('gestion-rifas')">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 3h6v3H9zM8 10h8M8 14h8M8 18h5"/></svg>
                    <span class="nav-text">Gestión de Rifas</span>
                </a>
                <a href="#usuarios" class="nav-item" data-section="usuarios" id="nav-usuarios" onclick="switchTo('usuarios')">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span class="nav-text">Usuarios</span>
                </a>
                <a href="#configuracion" class="nav-item" data-section="configuracion" onclick="switchTo('configuracion')">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/></svg>
                    <span class="nav-text">Configuración Generales</span>
                </a>
                <a href="#email-campaigns" class="nav-item" data-section="email-campaigns" id="nav-campaigns" onclick="switchTo('email-campaigns')">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/></svg>
                    <span class="nav-text">Campañas de Email</span>
                </a>
                <a href="#banners" class="nav-item" data-section="banners" id="nav-banners" onclick="switchTo('banners')">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="14" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="m21 15-5-5-9 9"/></svg>
                    <span class="nav-text">Gestión de Portada</span>
                </a>
                <a href="#tapazo" class="nav-item" data-section="tapazo" onclick="switchTo('tapazo')">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3h11l-1 15a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 3Zm11 4h2.5a2 2 0 0 1 2 2.2l-.4 4A2 2 0 0 1 18.1 15H16"/></svg>
                    <span class="nav-text">El Tapazo</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <button class="logout-btn" onclick="logout()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/></svg>
                    <span>Salir</span>
                </button>
            </div>
        </aside>

        <main class="admin-main">
            <header class="admin-header">
                <button id="sidebar-toggle" class="mobile-toggle" aria-label="Abrir menú de navegación">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                <h1>Dashboard</h1>
                <div class="user-menu" id="user-menu">
                    <button type="button" id="user-menu-btn" class="user-menu-btn" aria-haspopup="true" aria-expanded="false" aria-label="Menú de usuario">
                        <span class="user-name" id="user-name">Usuario</span>
                        <div class="user-avatar"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg></div>
                        <svg class="user-menu-caret" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div class="user-dropdown hidden" id="user-dropdown" role="menu">
                        <button type="button" role="menuitem" onclick="switchTo('mi-perfil'); toggleUserMenu(false);">Mi Perfil</button>
                        <button type="button" role="menuitem" class="user-dropdown__logout" onclick="logout()">Salir</button>
                    </div>
                </div>
            </header>

            <div id="sidebar-overlay" class="sidebar-overlay"></div>

            <div class="admin-content">
                <div id="section-dashboard" class="admin-section">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #3b82f6;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v1a2 2 0 0 0 0 4v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1a2 2 0 0 0 0-4Z"/></svg></div>
                            <div class="stat-content">
                                <div class="stat-value" id="stat-active-raffles">0</div>
                                <div class="stat-label">Rifas Activas</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #10b981;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="3"/></svg></div>
                            <div class="stat-content">
                                <div class="stat-value" id="stat-total-sales">$0</div>
                                <div class="stat-label">Ventas Totales</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #f59e0b;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v1a2 2 0 0 0 0 4v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1a2 2 0 0 0 0-4Z"/><path d="M13 5v14" stroke-dasharray="2 3"/></svg></div>
                            <div class="stat-content">
                                <div class="stat-value" id="stat-tickets-sold">0</div>
                                <div class="stat-label">Boletos Vendidos</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #8b5cf6;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                            <div class="stat-content">
                                <div class="stat-value" id="stat-total-buyers">0</div>
                                <div class="stat-label">Compradores</div>
                            </div>
                        </div>
                    </div>

                    <!-- Comisiones Globales -->
                    <div class="stats-grid" id="commission-stats" style="display:none;">
                        <div class="stat-card" style="border:2px solid #10b981;">
                            <div class="stat-icon" style="background: #10b981;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="3"/></svg></div>
                            <div class="stat-content">
                                <div class="stat-value" id="stat-commission-total">$0</div>
                                <div class="stat-label">Utilidad Total Comisiones</div>
                            </div>
                        </div>
                        <div class="stat-card" style="border:2px solid #f59e0b;">
                            <div class="stat-icon" style="background: #f59e0b;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg></div>
                            <div class="stat-content">
                                <div class="stat-value" id="stat-commission-pending">$0</div>
                                <div class="stat-label">Comisiones Pendientes</div>
                            </div>
                        </div>
                        <div class="stat-card" style="border:2px solid #3b82f6;">
                            <div class="stat-icon" style="background: #3b82f6;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></div>
                            <div class="stat-content">
                                <div class="stat-value" id="stat-commission-paid">$0</div>
                                <div class="stat-label">Comisiones Cobradas</div>
                            </div>
                        </div>
                        <div class="stat-card" style="border:2px solid #ef4444;">
                            <div class="stat-icon" style="background: #ef4444;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg></div>
                            <div class="stat-content">
                                <div class="stat-value" id="stat-commission-overdue">0</div>
                                <div class="stat-label">Comisiones Vencidas</div>
                            </div>
                        </div>
                    </div>

                    <!-- Reporte de Todas las Rifas -->
                    <div class="section-card" id="all-raffles-report" style="display:none;">
                        <div class="section-header">
                            <div>
                                <h2 class="flex items-center gap-2"><svg class="w-5 h-5 text-primary shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 15l4-5 3 3 5-7"/></svg>Reporte General de Rifas</h2>
                                <p class="text-sm text-gray-500 mt-1">Todas las rifas de todos los usuarios organizadores</p>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Rifa</th>
                                        <th>Organizador</th>
                                        <th>Ciudad</th>
                                        <th>Estado</th>
                                        <th>Tickets</th>
                                        <th>Vendidos</th>
                                        <th>Precio</th>
                                        <th>Ventas</th>
                                        <th>Comisión</th>
                                        <th>Fecha Sorteo</th>
                                    </tr>
                                </thead>
                                <tbody id="all-raffles-table">
                                    <tr><td colspan="11" class="text-center">Cargando…</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="section-card">
                        <div class="section-header">
                            <h2>Rifas Recientes</h2>
                        </div>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Estado</th>
                                        <th>Vendidos</th>
                                        <th>Fecha Sorteo</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="raffles-table">
                                    <tr><td colspan="5" class="text-center">Cargando…</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div id="section-boletas-compradas" class="admin-section hidden">
                    <div class="section-card">
                        <div class="section-header">
                            <h2 class="flex items-center gap-2"><svg class="w-5 h-5 text-primary shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v1a2 2 0 0 0 0 4v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1a2 2 0 0 0 0-4Z"/></svg>Mis Boletas Compradas</h2>
                        </div>
                        <div id="user-tickets-loading" class="text-center py-8">
                            <div class="spinner"></div>
                            <p class="text-gray-500 mt-4">Buscando tus boletos…</p>
                        </div>
                        <div id="user-tickets-container" class="space-y-4"></div>
                        <div id="no-user-tickets" class="hidden text-center py-8">
                            <p class="text-gray-500 text-lg">No has comprado boletos aún</p>
                            <a href="<?= BASE_PATH ?>/public/index.php" class="btn btn--primary mt-4 inline-block">Ver rifas disponibles</a>
                        </div>
                    </div>
                </div>

                <div id="section-crear" class="admin-section hidden">
                    <div class="section-card">
                        <h2 class="text-lg font-bold mb-6">Crear Nueva Rifa</h2>
                        <div id="create-date-error" class="hidden" style="background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#b91c1c;font-size:14px;"></div>
                        <form id="create-raffle-form" class="form-stack" enctype="multipart/form-data">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="raffle-name">Nombre de la Rifa *</label>
                                    <input type="text" id="raffle-name" name="name" required placeholder="ej: Carro 0km">
                                </div>
                                <div class="form-group">
                                    <label for="raffle-department">Departamento *</label>
                                    <select id="raffle-department" name="department" required onchange="loadCitiesForCreate()">
                                        <option value="">Seleccionar</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="raffle-city">Municipio/Ciudad *</label>
                                    <select id="raffle-city" name="city" required>
                                        <option value="">Seleccionar departamento primero</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="raffle-scope">Alcance de la Rifa *</label>
                                    <select id="raffle-scope" name="scope" required>
                                        <option value="municipal">Municipal</option>
                                        <option value="departamental">Departamental</option>
                                        <option value="national">Nacional</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="raffle-description">Descripción *</label>
                                <textarea id="raffle-description" name="description" required rows="3"></textarea>
                            </div>

                            <!-- IMAGENES DE LA RIFA (MAX 10) -->
                            <div class="form-group">
                                <label>Fotos de la Rifa (Máx 10)</label>
                                <div id="image-drop-zone" tabindex="0" role="button" aria-label="Subir fotos de la rifa" style="border:2px dashed #cbd5e1;border-radius:12px;padding:24px;text-align:center;cursor:pointer;transition:border-color .2s, background-color .2s;background:#f8fafc;display:flex;flex-direction:column;gap:16px;">
                                    <div id="image-placeholder">
                                        <div style="margin-bottom:4px;"><svg class="w-8 h-8 text-primary mx-auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4M12 4 7 9M12 4l5 5"/><path d="M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/></svg></div>
                                        <p style="color:#64748b;font-size:13px;">Arrastra las fotos aquí o <span style="color:#2563eb;font-weight:600;">haz clic para seleccionar</span></p>
                                        <p style="color:#94a3b8;font-size:11px;">Máx 10 fotos · JPG, PNG, WEBP · 5MB/u</p>
                                    </div>
                                    <div id="images-preview-grid" class="grid grid-cols-5 gap-3"></div>
                                    <input type="file" id="raffle-image-file" accept="image/*" multiple style="display:none;">
                                </div>
                                <div id="image-upload-status" style="font-size:12px;margin-top:6px;color:#64748b;font-weight:500;"></div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="ticket-price">Precio por Boleta (COP) *</label>
                                    <input type="number" id="ticket-price" name="ticket_price" required min="1000" step="1000">
                                </div>
                                <div class="form-group">
                                    <label for="total-tickets">Total Boletas *</label>
                                    <select id="total-tickets" name="total_tickets" required onchange="onTotalTicketsChange();">
                                        <option value="100">100</option>
                                        <option value="1000" selected>1,000</option>
                                        <option value="10000">10,000</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="digits">Cifras *</label>
                                    <select id="digits" name="digits" required onchange="onDigitsChange();">
                                        <option value="2">2 cifras (00-99)</option>
                                        <option value="3" selected>3 cifras (000-999)</option>
                                        <option value="4">4 cifras (0000-9999)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="opportunities">Oportunidades *</label>
                                    <select id="opportunities" name="opportunities" required onchange="onOpportunitiesChange()">
                                        <option value="1">1 oportunidad</option>
                                        <option value="2">2 oportunidades</option>
                                        <option value="4">4 oportunidades</option>
                                        <option value="5">5 oportunidades</option>
                                    </select>
                                    <small id="opportunities-hint" style="color:#64748b;font-size:12px;margin-top:4px;display:block;"></small>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="winning-mode">Modo de Ganar *</label>
                                    <select id="winning-mode" name="winning_mode" required>
                                    </select>
                                    <small id="winning-mode-hint" style="color:#64748b;font-size:12px;margin-top:4px;display:block;"></small>
                                </div>
                                <div class="form-group">
                                    <label for="lottery-id">Lotería *</label>
                                    <select id="lottery-id" name="lottery_id" required>
                                        <option value="">Seleccionar lotería…</option>
                                    </select>
                                    <small id="lottery-day-hint" style="color:#64748b;font-size:12px;margin-top:4px;display:block;"></small>
                                </div>
                                <div class="form-group">
                                    <label for="draw-date">Fecha del Sorteo *</label>
                                    <input type="date" id="draw-date" name="draw_date" required>
                                    <small style="color:#64748b;font-size:12px;margin-top:4px;display:block;">La hora se asigna automáticamente según la lotería seleccionada.</small>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="whatsapp-contact">WhatsApp de Contacto *</label>
                                    <input type="tel" id="whatsapp-contact" name="whatsapp_contact" required placeholder="3001234567">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="responsible-person">Responsable *</label>
                                <input type="text" id="responsible-person" name="responsible_person" required placeholder="Nombre del responsable">
                            </div>
                            <button type="submit" id="create-btn" class="btn btn--primary btn--lg">
                                Crear Rifa
                            </button>
                        </form>
                    </div>
                </div>

                <div id="section-pagos" class="admin-section hidden">
                    <div class="section-card">
                        <div class="section-header">
                            <h2>Validación de Pagos Manuales (Contraentrega/Transferencia)</h2>
                        </div>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Rifa</th>
                                        <th>Boleto</th>
                                        <th>Comprador</th>
                                        <th>Método</th>
                                        <th>Estado</th>
                                        <th>Comprobante</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="payments-table">
                                    <tr><td colspan="7" class="text-center">Cargando…</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="section-mi-perfil" class="admin-section hidden">
                    <div class="section-card mb-6">
                        <h2 class="text-xl font-bold mb-2">Mi Perfil</h2>
                        <p class="text-sm text-gray-500 mb-6">Gestiona tu información personal, credenciales de acceso y configuraciones de pago.</p>
                        
                        <form id="admin-profile-form" class="space-y-6">
                            <div class="flex items-center gap-6 mb-8 p-4 bg-gray-50 rounded-2xl border border-dashed border-gray-200">
                                <label class="relative group cursor-pointer block w-fit" aria-label="Cambiar foto de perfil">
                                    <input type="file" id="p-image-input" class="sr-only peer" accept="image/*">
                                    <div class="w-24 h-24 bg-blue-600 rounded-full flex items-center justify-center text-4xl font-bold overflow-hidden border-4 border-white shadow-lg peer-focus-visible:ring-4 peer-focus-visible:ring-blue-400">
                                        <span id="p-avatar-text">A</span>
                                        <img id="p-avatar-img" class="w-full h-full object-cover hidden">
                                    </div>
                                    <div class="absolute inset-0 bg-black/40 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 peer-focus-visible:opacity-100 transition-opacity">
                                        <span class="text-white text-[10px] font-bold uppercase">Subir</span>
                                    </div>
                                </label>
                                <div>
                                    <h3 class="font-bold text-lg" id="p-display-name">Tu Nombre</h3>
                                    <p class="text-sm text-gray-500" id="p-display-role">Administrador</p>
                                    <p class="text-xs text-gray-400" id="p-display-email">tu@email.com</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="form-group">
                                    <label>Nombre Completo / Empresa</label>
                                    <input type="text" id="p-name" class="w-full px-4 py-2 border rounded-lg" required>
                                </div>
                                <div class="form-group">
                                    <label>Teléfono WhatsApp</label>
                                    <input type="tel" id="p-phone" class="w-full px-4 py-2 border rounded-lg" required>
                                </div>
                                <div class="form-group">
                                    <label>Ciudad</label>
                                    <input type="text" id="p-city" class="w-full px-4 py-2 border rounded-lg">
                                </div>
                                <div class="form-group">
                                    <label>Username</label>
                                    <input type="text" id="p-username" class="w-full px-4 py-2 border rounded-lg bg-gray-100" readonly>
                                </div>
                            </div>

                            <div class="flex justify-start">
                                <button type="submit" class="btn btn--primary px-8 h-12" id="btn-save-p">Guardar Mis Datos</button>
                            </div>
                        </form>
                    </div>

                    <div class="section-card mb-6">
                        <h2 class="text-lg font-bold mb-4 flex items-center gap-2"><svg class="w-5 h-5 text-primary shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>Cambiar Contraseña</h2>
                        <p class="text-sm text-gray-500 mb-4">Actualiza tu contraseña de acceso al panel.</p>
                        <form id="change-password-form" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="form-group">
                                    <label>Contraseña Actual</label>
                                    <input type="password" id="cp-current" class="w-full px-4 py-2 border rounded-lg" required placeholder="••••••••">
                                </div>
                                <div class="form-group">
                                    <label>Nueva Contraseña</label>
                                    <input type="password" id="cp-new" autocomplete="new-password" class="w-full px-4 py-2 border rounded-lg" required placeholder="Mínimo 8 caracteres" minlength="8">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Confirmar Nueva Contraseña</label>
                                <input type="password" id="cp-confirm" autocomplete="new-password" class="w-full px-4 py-2 border rounded-lg" required placeholder="Repite la nueva contraseña" minlength="8">
                            </div>
                            <button type="submit" class="btn btn--primary px-8 h-12" id="btn-save-cp">Cambiar Contraseña</button>
                        </form>
                    </div>

                    <div class="section-card mb-6">
                        <h2 class="text-lg font-bold mb-4">Credenciales API Nequi (Automático)</h2>
                        <p class="text-sm text-gray-500 mb-4">Ingresa tus credenciales API de Nequi/Bancolombia. Si las proporcionas, el sistema habilitará el botón de "Pago Directo Pse/Nequi" en tus rifas.</p>
                        <form id="nequi-config-form" class="space-y-4">
                            <div class="form-group">
                                <label>Nequi API Key / Client ID</label>
                                <input type="text" id="cfg-nequi-key" class="w-full px-4 py-2 border rounded" placeholder="ej: nequi_test_xyz123">
                            </div>
                            <div class="form-group">
                                <label>Nequi API Secret</label>
                                <input type="password" id="cfg-nequi-secret" class="w-full px-4 py-2 border rounded" placeholder="••••••••">
                            </div>
                            <div class="form-group">
                                <label>Número Celular Nequi / Cuenta Vendedor</label>
                                <input type="tel" id="cfg-nequi-phone" class="w-full px-4 py-2 border rounded" placeholder="3001234567">
                            </div>
                            <button type="submit" class="btn btn--primary" id="btn-save-nequi">Guardar Nequi API</button>
                        </form>
                    </div>

                    <div class="section-card">
                        <h2 class="text-lg font-bold mb-4">Bot WhatsApp Vendedor (EvolutionAPI)</h2>
                        <p class="text-sm text-gray-500 mb-4">Si tienes una instancia propia de EvolutionAPI, ingresa los datos para notificar automáticamente a tus compradores desde TU número celular.</p>
                        <form id="wa-config-form" class="space-y-4">
                            <div class="form-group">
                                <label>EvolutionAPI Base URL</label>
                                <input type="text" id="cfg-wa-url" class="w-full px-4 py-2 border rounded" placeholder="http://tu-vps:8080">
                            </div>
                            <div class="form-group">
                                <label>Global API Key</label>
                                <input type="password" id="cfg-wa-apikey" class="w-full px-4 py-2 border rounded" placeholder="Tu Global ApiKey">
                            </div>
                            <div class="form-group">
                                <label>Nombre Instancia (Instance Name)</label>
                                <input type="text" id="cfg-wa-instance" class="w-full px-4 py-2 border rounded" placeholder="InstanciaX">
                            </div>
                            <button type="submit" class="btn" style="background:#25D366;color:white;" id="btn-save-wa">Guardar Bot WhatsApp</button>
                        </form>
                    </div>
                </div>

                <!-- SECCIÓN: GESTIÓN DE RIFAS (CRUD) -->
                <div id="section-gestion-rifas" class="admin-section hidden">
                    <!-- Resultado de lotería (manual) — respaldo del scraper -->
                    <div class="section-card">
                        <div class="section-header">
                            <div>
                                <h2 class="text-xl font-bold">Resultado de Lotería (manual)</h2>
                                <p class="text-sm text-gray-500 mt-1">Si el scraper no obtuvo el número ganador, cárgalo aquí (verifícalo en la fuente oficial). Los sorteos pendientes se procesarán en la próxima corrida.</p>
                            </div>
                        </div>
                        <div id="pending-draws" class="mb-4 text-sm"></div>
                        <form id="lottery-result-form" class="flex flex-col md:flex-row md:items-end gap-3">
                            <div class="form-group flex-1">
                                <label>Lotería</label>
                                <select id="lr-lottery" class="w-full px-4 py-2 border rounded-lg" required></select>
                            </div>
                            <div class="form-group">
                                <label>Fecha del sorteo</label>
                                <input type="date" id="lr-date" class="w-full px-4 py-2 border rounded-lg" required>
                            </div>
                            <div class="form-group">
                                <label>Número ganador</label>
                                <input type="text" id="lr-number" inputmode="numeric" pattern="\d{2,6}" maxlength="6" placeholder="ej: 1234" class="w-full px-4 py-2 border rounded-lg" required>
                            </div>
                            <button type="button" id="lr-suggest" class="btn h-11 px-4" title="Busca el número real y lo precarga como sugerencia; tú lo verificas antes de guardar">🤖 Sugerir con IA</button>
                            <button type="submit" class="btn btn--primary h-11 px-6">Guardar resultado</button>
                        </form>
                        <p id="lr-suggest-note" class="text-xs text-gray-500 mt-2 hidden"></p>
                    </div>

                    <div class="section-card">
                        <div class="section-header flex justify-between items-center mb-6">
                            <div>
                                <h2 class="text-xl font-bold">Gestión de Todas las Rifas</h2>
                                <p class="text-sm text-gray-500 mt-1">Administra, edita y elimina rifas del sistema</p>
                            </div>
                        </div>
                        <div class="flex gap-3 mb-6 flex-wrap">
                            <select id="filter-status" class="px-4 py-2 border rounded-lg text-sm" onchange="filterRafflesTable()">
                                <option value="">Todos los estados</option>
                                <option value="draft">Borrador</option>
                                <option value="active">Activa</option>
                                <option value="blocked">Bloqueada</option>
                                <option value="completed">Completada</option>
                                <option value="cancelled">Cancelada</option>
                            </select>
                            <select id="filter-organizer" class="px-4 py-2 border rounded-lg text-sm" onchange="filterRafflesTable()">
                                <option value="">Todos los organizadores</option>
                            </select>
                            <input type="text" id="filter-search" class="px-4 py-2 border rounded-lg text-sm flex-1 min-w-[200px]" placeholder="Buscar por nombre, ciudad..." oninput="filterRafflesTable()">
                        </div>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Rifa</th>
                                        <th>Organizador</th>
                                        <th>Ciudad</th>
                                        <th>Estado</th>
                                        <th>Vendidos</th>
                                        <th>Fecha Sorteo</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="gestion-raffles-table">
                                    <tr><td colspan="8" class="text-center">Cargando…</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- MODAL: Editar Rifa -->
                <div id="edit-raffle-modal" role="dialog" aria-modal="true" aria-labelledby="edit-raffle-title" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto" style="overscroll-behavior: contain;">
                        <div class="flex justify-between items-center p-6 border-b">
                            <h3 id="edit-raffle-title" class="text-xl font-bold">Editar Rifa</h3>
                            <button onclick="closeEditModal()" aria-label="Cerrar" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                        </div>
                        <form id="edit-raffle-form" class="p-6 space-y-4">
                            <input type="hidden" id="edit-raffle-id">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="form-group">
                                    <label>Nombre de la Rifa</label>
                                    <input type="text" id="edit-name" class="w-full px-4 py-2 border rounded-lg" required>
                                </div>
                                <div class="form-group">
                                    <label>Estado</label>
                                    <select id="edit-status" class="w-full px-4 py-2 border rounded-lg">
                                        <option value="draft">Borrador</option>
                                        <option value="active">Activa</option>
                                        <option value="blocked">Bloqueada</option>
                                        <option value="completed">Completada</option>
                                        <option value="cancelled">Cancelada</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Precio del Boleto (COP)</label>
                                    <input type="number" id="edit-price" class="w-full px-4 py-2 border rounded-lg" required min="1000">
                                </div>
                                <div class="form-group">
                                    <label>Total de Boletos</label>
                                    <input type="number" id="edit-total-tickets" class="w-full px-4 py-2 border rounded-lg" required min="1">
                                </div>
                                <div class="form-group">
                                    <label>Fecha del Sorteo</label>
                                    <input type="datetime-local" id="edit-draw-date" class="w-full px-4 py-2 border rounded-lg" required>
                                </div>
                                <div class="form-group">
                                    <label>Lotería</label>
                                    <select id="edit-lottery-id" class="w-full px-4 py-2 border rounded-lg"></select>
                                </div>
                                <div class="form-group">
                                    <label>WhatsApp de Contacto</label>
                                    <input type="text" id="edit-whatsapp" class="w-full px-4 py-2 border rounded-lg">
                                </div>
                                <div class="form-group">
                                    <label>Responsable</label>
                                    <input type="text" id="edit-responsible" class="w-full px-4 py-2 border rounded-lg">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Descripción</label>
                                <textarea id="edit-description" rows="4" class="w-full px-4 py-2 border rounded-lg"></textarea>
                            </div>
                            <div class="flex justify-end gap-3 pt-4 border-t">
                                <button type="button" onclick="closeEditModal()" class="btn btn--outline px-6">Cancelar</button>
                                <button type="submit" class="btn btn--primary px-8 h-12">Guardar Cambios</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- SECCIÓN: GESTIÓN DE USUARIOS -->
                <div id="section-usuarios" class="admin-section hidden">
                    <div class="section-card">
                        <div class="section-header">
                            <div>
                                <h2 class="text-xl font-bold">Gestión de Usuarios</h2>
                                <p class="text-sm text-gray-500 mt-1">Organizadores y compradores registrados. Editar, suspender, activar o eliminar cuentas.</p>
                            </div>
                        </div>
                        <div class="flex gap-3 mb-6 flex-wrap">
                            <select id="user-filter-type" class="px-4 py-2 border rounded-lg text-sm" onchange="filterUsersTable()">
                                <option value="">Todos los tipos</option>
                                <option value="vendor">Organizadores</option>
                                <option value="buyer">Compradores</option>
                            </select>
                            <select id="user-filter-status" class="px-4 py-2 border rounded-lg text-sm" onchange="filterUsersTable()">
                                <option value="">Todos los estados</option>
                                <option value="active">Activos</option>
                                <option value="suspended">Suspendidos</option>
                            </select>
                            <input type="text" id="user-filter-search" class="px-4 py-2 border rounded-lg text-sm flex-1 min-w-[200px]" placeholder="Buscar por nombre, email, teléfono..." oninput="filterUsersTable()">
                        </div>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Nombre</th>
                                        <th>Email</th>
                                        <th>Teléfono</th>
                                        <th>Rol</th>
                                        <th>Estado</th>
                                        <th>Registro</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="users-table">
                                    <tr><td colspan="8" class="text-center">Cargando…</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- MODAL: Editar Usuario -->
                <div id="edit-user-modal" role="dialog" aria-modal="true" aria-labelledby="edit-user-title" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto" style="overscroll-behavior: contain;">
                        <div class="flex justify-between items-center p-6 border-b">
                            <h3 id="edit-user-title" class="text-xl font-bold">Editar Usuario</h3>
                            <button onclick="closeUserModal()" aria-label="Cerrar" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                        </div>
                        <form id="edit-user-form" class="p-6 space-y-4">
                            <input type="hidden" id="eu-type">
                            <input type="hidden" id="eu-id">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="form-group">
                                    <label>Nombre <span id="eu-name-hint" class="text-gray-400 text-xs"></span></label>
                                    <input type="text" id="eu-name" class="w-full px-4 py-2 border rounded-lg" required>
                                </div>
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" id="eu-email" class="w-full px-4 py-2 border rounded-lg">
                                </div>
                                <div class="form-group">
                                    <label>Teléfono</label>
                                    <input type="tel" id="eu-phone" class="w-full px-4 py-2 border rounded-lg">
                                </div>
                                <div class="form-group" id="eu-role-group">
                                    <label>Rol</label>
                                    <select id="eu-role" class="w-full px-4 py-2 border rounded-lg">
                                        <option value="vendor">Organizador (vendor)</option>
                                        <option value="super_admin">Super Admin</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Ciudad</label>
                                    <input type="text" id="eu-city" class="w-full px-4 py-2 border rounded-lg">
                                </div>
                                <div class="form-group">
                                    <label>Departamento</label>
                                    <input type="text" id="eu-department" class="w-full px-4 py-2 border rounded-lg">
                                </div>
                            </div>
                            <div class="flex justify-end gap-3 pt-4 border-t">
                                <button type="button" onclick="closeUserModal()" class="btn btn--outline px-6">Cancelar</button>
                                <button type="submit" class="btn btn--primary px-8 h-12">Guardar Cambios</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- SECCIÓN: COMISIONES -->
                <div id="section-comisiones" class="admin-section hidden">
                    <div class="section-card">
                        <div class="section-header">
                            <h2>Configuración de Comisiones</h2>
                        </div>
                        <div class="p-6 bg-slate-50 rounded-xl border border-slate-200 mb-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h3 class="font-bold text-lg">Cobro de Comisiones</h3>
                                    <p class="text-sm text-slate-500">Activa o desactiva el cobro de comisión sobre las ventas de rifas</p>
                                </div>
                                <label class="toggle-label" style="cursor:pointer;">
                                    <input type="checkbox" id="commission-enabled" onchange="toggleCommissionUI()">
                                    <span class="toggle-slider"></span>
                                    <span class="font-bold text-sm" id="commission-status-text">Desactivado</span>
                                </label>
                            </div>
                            <div id="commission-settings" class="pt-4 border-t border-slate-200">
                                <div class="flex items-center gap-6">
                                    <div class="flex-1">
                                        <label class="text-sm font-bold text-slate-600 block mb-2">Porcentaje de Comisión (%)</label>
                                        <div class="flex items-center gap-4">
                                            <input type="range" id="commission-percentage-slider" min="0" max="30" value="5" class="flex-1" oninput="document.getElementById('commission-percentage').value=this.value;updateCommissionPreview();">
                                            <input type="number" id="commission-percentage" min="0" max="30" value="5" class="w-20 px-3 py-2 border border-slate-300 rounded-lg text-center font-bold text-lg" oninput="document.getElementById('commission-percentage-slider').value=this.value;updateCommissionPreview();">
                                            <span class="text-2xl font-black text-slate-700">%</span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs text-slate-400 uppercase font-bold">Comisión sobre $1,000,000</p>
                                        <p class="text-2xl font-black text-blue-600" id="commission-preview">$50,000</p>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-6">
                                <button type="button" onclick="saveCommissionSettings()" id="save-commission-btn" class="btn btn--primary px-8 h-12">
                                    Guardar Configuración de Comisiones
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="section-card">
                        <div class="section-header">
                            <h2>Historial de Comisiones</h2>
                        </div>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Rifa</th>
                                        <th>Organizador</th>
                                        <th>Ventas</th>
                                        <th>Comisión</th>
                                        <th>Monto</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody id="commissions-table">
                                    <tr><td colspan="6" class="text-center">Cargando…</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN: GESTIÓN DE BANNERS -->
                <div id="section-banners" class="admin-section hidden">
                    <div class="section-card">
                        <h2 class="text-xl font-bold mb-2">Gestión de Portada (Slides)</h2>
                        <p class="text-sm text-gray-500 mb-6">Configura los 4 banners del slider principal y hasta 6 banners adicionales.</p>
                        <form id="banners-form">
                            <div id="banners-container" class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4"></div>
                            <div class="flex justify-end pt-8 border-t mt-8">
                                <button type="submit" class="btn btn--primary btn--lg px-12 h-14 text-lg shadow-xl shadow-blue-500/20">
                                    Guardar Configuración de Portada
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- SECCIÓN: CAMPAÑAS DE EMAIL -->
                <div id="section-email-campaigns" class="admin-section hidden">
                    <div class="section-card mb-8">
                        <div class="section-header">
                            <div>
                                <h2 class="text-xl font-bold">Crear Nueva Campaña de Email</h2>
                                <p class="text-sm text-gray-500">Envío masivo a tus usuarios registrados.</p>
                            </div>
                        </div>
                        <form id="campaign-form" class="space-y-6 mt-4">
                            <div class="form-group">
                                <label>Segmento de Destinatarios</label>
                                <select id="c-segment" class="w-full px-4 py-2 border rounded-lg">
                                    <option value="all">Todos los Usuarios</option>
                                    <option value="buyers">Solo Compradores</option>
                                    <option value="sellers">Solo Vendedores</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Asunto del Email</label>
                                <input type="text" id="c-subject" class="w-full px-4 py-2 border rounded-lg" placeholder="¡Nuevas rifas disponibles!" required>
                            </div>
                            <div class="form-group">
                                <label>Mensaje</label>
                                <textarea id="c-body" rows="6" class="w-full px-4 py-2 border rounded-lg" placeholder="Escribe tu mensaje aquí..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn--primary" id="btn-send-campaign">Enviar Campaña</button>
                        </form>
                    </div>
                    <div class="section-card">
                        <h2 class="text-xl font-bold mb-4">Configuración del Servidor de Correos (SMTP)</h2>
                        <p class="text-sm text-gray-500 mb-6">Configura tu servidor SMTP para enviar campañas de email y notificaciones automáticas.</p>
                        <form id="email-settings-form" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="form-group">
                                    <label>Servidor SMTP (Host)</label>
                                    <input type="text" id="smtp-host" class="w-full px-4 py-2 border rounded-lg" placeholder="smtp.gmail.com">
                                </div>
                                <div class="form-group">
                                    <label>Puerto SMTP</label>
                                    <input type="number" id="smtp-port" class="w-full px-4 py-2 border rounded-lg" placeholder="587" value="587">
                                </div>
                                <div class="form-group">
                                    <label>Usuario SMTP (Email)</label>
                                    <input type="email" id="smtp-user" class="w-full px-4 py-2 border rounded-lg" placeholder="tu@email.com">
                                </div>
                                <div class="form-group">
                                    <label>Contraseña SMTP</label>
                                    <input type="password" id="smtp-pass" class="w-full px-4 py-2 border rounded-lg" placeholder="••••••••">
                                </div>
                                <div class="form-group">
                                    <label>Email Remitente (From)</label>
                                    <input type="email" id="smtp-from" class="w-full px-4 py-2 border rounded-lg" placeholder="noreply@misrifas.com">
                                </div>
                                <div class="form-group">
                                    <label>Nombre Remitente</label>
                                    <input type="text" id="smtp-from-name" class="w-full px-4 py-2 border rounded-lg" placeholder="MisRifas">
                                </div>
                            </div>
                            <button type="submit" class="btn btn--primary px-8 h-12">Guardar Configuración SMTP</button>
                        </form>
                    </div>
                    <div class="section-card">
                        <h2 class="text-xl font-bold mb-4">Historial de Campañas</h2>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Asunto</th>
                                        <th>Segmento</th>
                                        <th>Enviados</th>
                                        <th>Fecha</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody id="campaigns-table">
                                    <tr><td colspan="5" class="text-center">Cargando…</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ===== SECCIÓN TAPAZO ===== -->
                <div id="section-tapazo" class="admin-section hidden">
                    <div class="section-card">
                        <div class="section-header">
                            <h2 class="flex items-center gap-2"><svg class="w-5 h-5 text-primary shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3h11l-1 15a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 3Zm11 4h2.5a2 2 0 0 1 2 2.2l-.4 4A2 2 0 0 1 18.1 15H16"/></svg>El Tapazo - Crear Nueva Rifa Rápida</h2>
                        </div>
                        <form id="tapazo-form" class="form-stack">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Nombre del Tapazo *</label>
                                    <input type="text" id="tapazo-name" placeholder="Ej: Tapazo de Cerveza Corona" required class="w-full px-4 py-2 border rounded-lg">
                                </div>
                                <div class="form-group">
                                    <label>Premio *</label>
                                    <input type="text" id="tapazo-prize" placeholder="Ej: Caja de 6 cervezas" required class="w-full px-4 py-2 border rounded-lg">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Descripción *</label>
                                <textarea id="tapazo-desc" rows="3" placeholder="Describe el tapazo y cómo funciona" required class="w-full px-4 py-2 border rounded-lg"></textarea>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Total de Participantes *</label>
                                    <input type="number" id="tapazo-total" min="2" max="100" value="10" required class="w-full px-4 py-2 border rounded-lg">
                                    <small>Cantidad de personas que pueden participar</small>
                                </div>
                                <div class="form-group">
                                    <label>Modo de Ganar *</label>
                                    <select id="tapazo-mode" required class="w-full px-4 py-2 border rounded-lg">
                                        <option value="highest">🔼 Número más alto gana</option>
                                        <option value="lowest">🔽 Número más bajo gana</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>WhatsApp de Contacto *</label>
                                <input type="tel" id="tapazo-wa" placeholder="3001234567" pattern="[3][0-9]{9}" required class="w-full px-4 py-2 border rounded-lg">
                                <small>Número de WhatsApp para coordinar la entrega del premio</small>
                            </div>
                            <button type="submit" class="btn btn--primary btn--lg">
                                Crear Tapazo
                            </button>
                        </form>
                    </div>

                    <div class="section-card">
                        <div class="section-header">
                            <h2>Tapazos Creados</h2>
                        </div>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Participantes</th>
                                        <th>Premio</th>
                                        <th>Modo</th>
                                        <th>Estado</th>
                                        <th>Fecha</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tapazos-table">
                                    <tr><td colspan="7" class="text-center">Cargando…</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ===== SECCIÓN CONFIGURACIÓN ===== -->
                <div id="section-configuracion" class="admin-section hidden">

                    <!-- Wompi -->
                    <div class="section-card" style="margin-bottom:24px;">
                        <h2 class="text-lg font-bold mb-2 flex items-center gap-2"><svg class="w-5 h-5 text-primary shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>Configuración Wompi</h2>
                        <p style="color:#94a3b8;font-size:13px;margin-bottom:20px;">Configura tus credenciales de Wompi para recibir pagos directamente en tu cuenta.</p>
                        <form id="wompi-config-form" class="form-stack">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="wompi-public-key">Public Key</label>
                                    <input type="text" id="wompi-public-key" placeholder="pub_prod_...">
                                </div>
                                <div class="form-group">
                                    <label for="wompi-private-key">Private Key</label>
                                    <input type="password" id="wompi-private-key" placeholder="••••••••">
                                </div>
                            </div>
                            <button type="submit" class="btn btn--primary" id="btn-save-wompi">Guardar Wompi</button>
                        </form>
                    </div>

                    <!-- Configuración General (solo super_admin: la API no aplica cambios para vendedores) -->
                    <div class="section-card" id="section-platform-settings">
                        <h2 class="text-lg font-bold mb-2 flex items-center gap-2"><svg class="w-5 h-5 text-primary shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18 14 14 0 0 1 0-18Z"/></svg>Configuración General de la Plataforma</h2>
                        <p style="color:#94a3b8;font-size:13px;margin-bottom:20px;">Ajusta los par&aacute;metros globales de la plataforma.</p>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px;">
                            <div class="form-group">
                                <label for="cfg-platform-name">Nombre de la Plataforma</label>
                                <input type="text" id="cfg-platform-name" value="MisRifas" class="w-full px-4 py-2 border rounded-lg">
                            </div>
                            <div class="form-group">
                                <label for="cfg-platform-email">Email de la Plataforma</label>
                                <input type="email" id="cfg-platform-email" value="no-reply@misrifas.com" class="w-full px-4 py-2 border rounded-lg">
                            </div>
                            <div class="form-group">
                                <label for="cfg-min-ticket-price">Precio M&iacute;nimo Boleto (COP)</label>
                                <input type="number" id="cfg-min-ticket-price" value="1000" min="500" step="500" class="w-full px-4 py-2 border rounded-lg">
                            </div>
                            <div class="form-group">
                                <label for="cfg-max-ticket-price">Precio M&aacute;ximo Boleto (COP)</label>
                                <input type="number" id="cfg-max-ticket-price" value="1000000" min="1000" step="1000" class="w-full px-4 py-2 border rounded-lg">
                            </div>
                            <div class="form-group">
                                <label for="cfg-reservation-minutes">Tiempo Reserva Boleto (minutos)</label>
                                <input type="number" id="cfg-reservation-minutes" value="15" min="5" max="60" class="w-full px-4 py-2 border rounded-lg">
                            </div>
                            <div class="form-group">
                                <label for="cfg-max-tickets-buyer">M&aacute;x. Boletos por Compra</label>
                                <input type="number" id="cfg-max-tickets-buyer" value="10" min="1" max="100" class="w-full px-4 py-2 border rounded-lg">
                            </div>
                        </div>
                        <button class="btn btn--primary mt-4" onclick="saveGeneralSettings()">Guardar Configuraci&oacute;n General</button>
                    </div>

                </div><!-- /section-configuracion -->
            </div>
        </main>
    </div>

    <script>
    const API = {
        async request(endpoint, options = {}) {
            const token = localStorage.getItem('misrifas_token');
            const isMultipart = options.body instanceof FormData;
            
            const config = {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    ...options.headers
                },
                ...options
            };

            if (!isMultipart) {
                config.headers['Content-Type'] = 'application/json';
            }

            if (token) config.headers['Authorization'] = 'Bearer ' + token;
            
            const response = await fetch(BASE_PATH + '/api' + endpoint, config);
            if (response.status === 401) { logout(); throw new Error('No autorizado'); }
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

    function fixUrl(url) {
        if (!url) return '';
        if (url.startsWith('http')) return url;
        return BASE_PATH + '/public/' + url.replace(/^\//, '');
    }

    const token = localStorage.getItem('misrifas_token');
    if (!token) {
        window.location.href = BASE_PATH + '/public/vendor/index.php?auth=login';
    } else {
        // Guarda: un comprador que llega aquí (URL/marcador) NO debe entrar al
        // panel de vendedor —sus llamadas darían 401 y se cerraría su sesión—.
        // Se le envía a su panel de comprador sin destruir el token.
        try {
            var _u = JSON.parse(localStorage.getItem('misrifas_user') || '{}');
            if (_u && _u.role === 'buyer') {
                window.location.href = BASE_PATH + '/public/dashboard.php';
            }
        } catch (e) {}
    }

    function logout() {
        localStorage.removeItem('misrifas_token');
        localStorage.removeItem('misrifas_user');
        window.location.href = BASE_PATH + '/public/vendor/index.php?auth=login';
    }

    // Menú del círculo de usuario (antes el avatar no hacía nada).
    window.toggleUserMenu = function (force) {
        var d = document.getElementById('user-dropdown');
        var b = document.getElementById('user-menu-btn');
        if (!d || !b) return;
        var show = (force === undefined) ? d.classList.contains('hidden') : force;
        d.classList.toggle('hidden', !show);
        b.setAttribute('aria-expanded', show ? 'true' : 'false');
    };
    document.getElementById('user-menu-btn')?.addEventListener('click', function (e) {
        e.stopPropagation();
        window.toggleUserMenu();
    });
    document.addEventListener('click', function () { window.toggleUserMenu(false); });

    let currentSection = 'dashboard';

    // Nota: onDigitsChange / onTotalTicketsChange / onOpportunitiesChange y
    // updateOpportunitiesOptions / updateWinningModeOptions se definen mas
    // abajo como window.* (aqui habia una copia duplicada completa).

    function switchTo(section) {
        // Remove active from all links
        var links = document.querySelectorAll('.nav-item');
        for (var i = 0; i < links.length; i++) {
            links[i].classList.remove('nav-item--active');
        }
        
        // Add active to clicked link
        var activeLink = document.querySelector('[data-section="' + section + '"]');
        if (activeLink) activeLink.classList.add('nav-item--active');

        // Reflejar la pestaña activa en la tab bar móvil.
        if (window.syncVendorTab) window.syncVendorTab(section);

        // Hide all sections
        var sections = document.querySelectorAll('.admin-section');
        for (var j = 0; j < sections.length; j++) {
            sections[j].classList.add('hidden');
        }
        
        // Show target section
        var target = document.getElementById('section-' + section);
        if (target) {
            target.classList.remove('hidden');
        }
        
        // Update header title
        var h1 = document.querySelector('.admin-header h1');
        if (h1) {
            var titles = {
                dashboard: 'Dashboard',
                rifas: 'Mis Rifas',
                crear: 'Crear Rifa',
                comisiones: 'Comisiones',
                configuracion: 'Configuración',
                pagos: 'Pagos Recibidos',
                'mi-perfil': 'Mi Perfil (Integraciones)',
                banners: 'Gestión de Portada',
                'gestion-rifas': 'Gestión de Rifas',
                usuarios: 'Gestión de Usuarios',
                'email-campaigns': 'Campañas de Email',
                tapazo: 'El Tapazo'
            };
            if (section === 'boletas-compradas') { h1.textContent = 'Boletas Compradas'; } else { h1.textContent = titles[section] || section; }
        }
        
        currentSection = section;
        
        // Load section data
        if (section === 'dashboard') loadDashboard();
        if (section === 'boletas-compradas') loadUserBoughtTickets();
        if (section === 'rifas') loadAllRaffles();
        if (section === 'comisiones') loadCommissions();
        if (section === 'pagos') loadPayments();
        if (section === 'mi-perfil') loadPerfilAPI();
        if (section === 'banners') loadBannersConfig();
        if (section === 'gestion-rifas') { loadGestionRaffles(); loadLotteryResultUI(); }
        if (section === 'usuarios') loadUsers();
        if (section === 'crear') {
            // Initialize form with user data
            var user = JSON.parse(localStorage.getItem('misrifas_user') || '{}');
            if (user.phone && document.getElementById('whatsapp-contact')) {
                document.getElementById('whatsapp-contact').value = user.phone;
            }
            if (user.full_name && document.getElementById('responsible-person')) {
                document.getElementById('responsible-person').value = user.full_name;
            }
            
            // Load geography data if not loaded yet
            if (colombiaData.length === 0) {
                loadGeographyData();
            }
            
            // Trigger logic - the section is now visible so elements should exist
            console.log('Running onTotalTicketsChange for section crear');
            onTotalTicketsChange();
        }
        if (section === 'configuracion') loadSettings();
        if (section === 'tapazo') loadTapazos();
        if (section === 'email-campaigns') { loadCampaigns(); loadEmailSettings(); }
    }

    async function loadCommissions() {
        try {
            const response = await API.get('/admin/commissions.php');
            if (response.success) {
                const tbody = document.getElementById('commissions-table');
                const data  = response.data || [];
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-gray-500 py-6">No hay comisiones registradas</td></tr>';
                    return;
                }
                tbody.innerHTML = data.map(c => {
                    const isPaid    = parseInt(c.commission_paid) === 1;
                    const isOverdue = !isPaid && new Date(c.commission_due_date) < new Date();
                    const statusClass = isPaid ? 'completed' : (isOverdue ? 'cancelled' : 'pending');
                    const statusText  = isPaid ? 'Pagada'    : (isOverdue ? 'Vencida'   : 'Pendiente');
                    const commissionAmount = parseFloat(c.commission_amount || 0);
                    const commissionEditable = !isPaid
                        ? `<input type="number" value="${commissionAmount}" min="0" step="1000" class="commission-edit-input w-28 px-2 py-1 border border-slate-300 rounded text-sm font-bold" data-raffle-id="${c.raffle_id}" onchange="updateCommissionAmount(${c.raffle_id}, this.value)">`
                        : `<span class="font-bold text-slate-700">$${commissionAmount.toLocaleString('es-CO')}</span>`;
                    const actionBtn   = !isPaid
                        ? `<button class="btn btn--sm" style="background:#10b981;color:white;margin-right:4px;" onclick="markCommissionPaid(${c.raffle_id})">✅ Pagar</button>`
                        : '<span style="color:#10b981;font-size:12px;">&#10003; Cobrada</span>';
                    return '<tr>' +
                        '<td class="font-medium">' + (c.raffle_name || '') + '</td>' +
                        '<td style="color:#94a3b8;font-size:13px;">' + (c.creator_name || 'Vendedor') + '</td>' +
                        '<td>' + Utils.formatPrice(c.total_sales || 0) + '</td>' +
                        '<td>' + commissionEditable + '</td>' +
                        '<td><span class="badge badge--' + statusClass + '">' + statusText + '</span></td>' +
                        '<td>' + actionBtn + '</td>' +
                    '</tr>';
                }).join('');
            }
        } catch (error) { console.error('Error loading commissions:', error); }
    }

    window.updateCommissionAmount = async (raffleId, newAmount) => {
        try {
            await API.post('/admin/commissions.php', { raffle_id: raffleId, action: 'update_amount', amount: parseFloat(newAmount) });
            Utils.showNotification('Monto de comisión actualizado ✅', 'success');
        } catch (error) { 
            Utils.showNotification('Error al actualizar el monto', 'error'); 
            loadCommissions();
        }
    };

    async function loadDashboard() {
        try {
            const response = await API.get('/admin/dashboard.php');
            if (response.success) {
                document.getElementById('stat-active-raffles').textContent = response.data.active_raffles || 0;
                document.getElementById('stat-total-sales').textContent = Utils.formatPrice(response.data.total_sales || 0);
                document.getElementById('stat-tickets-sold').textContent = response.data.tickets_sold || 0;
                document.getElementById('stat-total-buyers').textContent = response.data.total_buyers || 0;
            }
        } catch (error) { console.error('Error loading dashboard:', error); }

        // Reporte general de rifas y comisiones (solo super_admin)
        try {
            const user = JSON.parse(localStorage.getItem('misrifas_user') || '{}');
            if (user.role === 'super_admin') {
                const allRaffles = await API.get('/admin/raffles.php');
                if (allRaffles.success) {
                    const raffles = allRaffles.data || [];
                    let totalCommission = 0, pendingCommission = 0, paidCommission = 0, overdueCount = 0;
                    
                    const tbody = document.getElementById('all-raffles-table');
                    tbody.innerHTML = raffles.map(r => {
                        const sold = r.sold_tickets || 0;
                        const price = parseFloat(r.ticket_price || 0);
                        const sales = sold * price;
                        const commission = parseFloat(r.commission_amount || 0);
                        const isPaid = parseInt(r.commission_paid) === 1;
                        const isOverdue = !isPaid && r.commission_due_date && new Date(r.commission_due_date) < new Date();
                        
                        totalCommission += commission;
                        if (isPaid) paidCommission += commission;
                        else { pendingCommission += commission; if (isOverdue) overdueCount++; }
                        
                        const statusMap = { draft: 'Borrador', active: 'Activa', blocked: 'Bloqueada', completed: 'Completada', cancelled: 'Cancelada' };
                        const statusClass = r.status === 'active' ? 'active' : (r.status === 'completed' ? 'completed' : (r.status === 'cancelled' ? 'cancelled' : 'pending'));
                        
                        return '<tr>' +
                            '<td class="text-gray-400 text-sm">' + (r.id || '') + '</td>' +
                            '<td class="font-medium">' + (r.name || '') + '</td>' +
                            '<td style="color:#64748b;font-size:13px;">' + (r.creator_name || r.organizer || '--') + '</td>' +
                            '<td>' + (r.city || '--') + '</td>' +
                            '<td><span class="badge badge--' + statusClass + '">' + (statusMap[r.status] || r.status) + '</span></td>' +
                            '<td>' + (r.total_tickets || 0) + '</td>' +
                            '<td class="font-bold">' + sold + '</td>' +
                            '<td>' + Utils.formatPrice(price) + '</td>' +
                            '<td class="font-bold text-emerald-600">' + Utils.formatPrice(sales) + '</td>' +
                            '<td class="font-bold ' + (isPaid ? 'text-blue-600' : 'text-amber-600') + '">' + Utils.formatPrice(commission) + '</td>' +
                            '<td>' + (r.draw_date ? new Date(r.draw_date).toLocaleDateString('es-CO') : '--') + '</td>' +
                        '</tr>';
                    }).join('');

                    // Mostrar totales
                    document.getElementById('commission-stats').style.display = 'grid';
                    document.getElementById('all-raffles-report').style.display = 'block';
                    document.getElementById('stat-commission-total').textContent = Utils.formatPrice(totalCommission);
                    document.getElementById('stat-commission-pending').textContent = Utils.formatPrice(pendingCommission);
                    document.getElementById('stat-commission-paid').textContent = Utils.formatPrice(paidCommission);
                    document.getElementById('stat-commission-overdue').textContent = overdueCount;
                }
            }
        } catch (error) { console.error('Error loading all raffles report:', error); }

        try {
            const response = await API.get('/admin/raffles.php');
            if (response.success) {
                renderRafflesTable(response.data.slice(0, 5));
            }
        } catch (error) { console.error('Error loading raffles:', error); }
    }

    function renderRafflesTable(raffles) {
        const tbody = document.getElementById('raffles-table');
        if (!raffles || raffles.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center">No hay rifas</td></tr>';
            return;
        }
        tbody.innerHTML = raffles.map(r => {
            const statusClass = r.status || 'pending';
            return '<tr>' +
                '<td class="font-medium">' + (r.name || '') + '</td>' +
                '<td><span class="badge badge--' + statusClass + '">' + statusClass + '</span></td>' +
                '<td>' + (r.sold_tickets || 0) + '</td>' +
                '<td>' + (r.draw_date ? new Date(r.draw_date).toLocaleDateString('es-CO') : '--') + '</td>' +
                '<td><button class="btn btn--sm btn--outline" onclick="viewRaffle(' + r.id + ')">Ver</button></td>' +
            '</tr>';
        }).join('');
    }

    async function loadAllRaffles() {
        try {
            const response = await API.get('/admin/raffles.php');
            if (response.success) {
                const tbody = document.getElementById('all-raffles-table');
                if (!response.data || response.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center">No hay rifas</td></tr>';
                    return;
                }
                tbody.innerHTML = response.data.map(r => {
                    const statusClass = r.status || 'pending';
                    return '<tr>' +
                        '<td class="font-medium">' + (r.name || '') + '</td>' +
                        '<td>' + (r.city || '') + '</td>' +
                        '<td><span class="badge badge--' + statusClass + '">' + statusClass + '</span></td>' +
                        '<td>' + (r.sold_tickets || 0) + '</td>' +
                        '<td>' + Utils.formatPrice(r.ticket_price || 0) + '</td>' +
                        '<td>' + (r.draw_date ? new Date(r.draw_date).toLocaleDateString('es-CO') : '--') + '</td>' +
                        '<td><button class="btn btn--sm btn--outline" onclick="viewRaffle(' + r.id + ')">Ver</button></td>' +
                    '</tr>';
                }).join('');
            }
        } catch (error) { console.error('Error loading all raffles:', error); }
    }

    // ================================================================
    // GESTIÓN DE RIFAS - CRUD con cambio de estado
    // ================================================================
    let allGestionRaffles = [];

    // ================================================================
    // EL TAPAZO - Rifas rápidas sin costo
    // ================================================================
    async function loadTapazos() {
        try {
            const res = await API.get('/tapazo/admin_list.php');
            const tbody = document.getElementById('tapazos-table');
            if (res.success && res.data && res.data.length > 0) {
                const modeMap = { highest: '🔼 Más alto gana', lowest: '🔽 Más bajo gana' };
                const statusMap = { draft: 'Borrador', active: 'Activo', completed: 'Completado', cancelled: 'Cancelado' };
                const statusClass = { draft: 'pending', active: 'active', completed: 'completed', cancelled: 'cancelled' };
                tbody.innerHTML = res.data.map(t => {
                    return '<tr>' +
                        '<td class="font-bold">' + (t.name || '') + '</td>' +
                        '<td>' + (t.joined_count || 0) + ' / ' + t.total_participants + '</td>' +
                        '<td>' + (t.prize || '--') + '</td>' +
                        '<td>' + (modeMap[t.win_mode] || t.win_mode) + '</td>' +
                        '<td><span class="badge badge--' + (statusClass[t.status] || 'pending') + '">' + (statusMap[t.status] || t.status) + '</span></td>' +
                        '<td>' + new Date(t.created_at).toLocaleDateString('es-CO') + '</td>' +
                        '<td class="flex gap-2">' +
                            '<button onclick="viewTapazo(' + t.id + ')" class="btn btn--sm" style="background:#3b82f6;color:white;">👁️ Ver</button>' +
                            (t.status === 'active' ? '<button onclick="completeTapazo(' + t.id + ')" class="btn btn--sm" style="background:#10b981;color:white;">✅ Completar</button>' : '') +
                        '</td>' +
                    '</tr>';
                }).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-gray-500 py-6">No hay tapazos creados</td></tr>';
            }
        } catch (e) { console.error('Error loading tapazos', e); }
    }

    document.getElementById('tapazo-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = e.target.querySelector('button[type="submit"]');
        btn.disabled = true; btn.textContent = 'Creando…';
        try {
            await API.post('/tapazo/admin_list.php', {
                name: document.getElementById('tapazo-name').value,
                description: document.getElementById('tapazo-desc').value,
                prize: document.getElementById('tapazo-prize').value,
                total_participants: parseInt(document.getElementById('tapazo-total').value),
                win_mode: document.getElementById('tapazo-mode').value,
                whatsapp: document.getElementById('tapazo-wa').value
            });
            Utils.showNotification('🍺 Tapazo creado exitosamente', 'success');
            e.target.reset();
            loadTapazos();
        } catch (error) {
            Utils.showNotification(error.message || 'Error al crear tapazo', 'error');
        } finally {
            btn.disabled = false; btn.textContent = 'Crear Tapazo';
        }
    });

    window.viewTapazo = async (tapazoId) => {
        try {
            const res = await API.get('/tapazo/admin_participants.php', { tapazo_id: tapazoId });
            if (res.success && res.data && res.data.length > 0) {
                let msg = '🍺 Participantes del Tapazo:\n\n';
                res.data.forEach((p, i) => {
                    const status = p.status === 'confirmed' ? '✅' : (p.status === 'revealed' ? '👁️' : '⏳');
                    msg += status + ' #' + p.cap_number + ' - ' + (p.participant_name || 'Disponible') + '\n';
                });
                alert(msg);
            }
        } catch (e) { Utils.showNotification('Error al cargar participantes', 'error'); }
    };

    window.completeTapazo = async (tapazoId) => {
        if (!confirm('¿Completar este tapazo y determinar el ganador?')) return;
        try {
            const res = await API.post('/tapazo/admin_participants.php', { action: 'complete', tapazo_id: tapazoId });
            if (res.success && res.data.winner) {
                const w = res.data.winner;
                Utils.showNotification('🏆 Ganador: ' + w.participant_name + ' con tapa #' + w.cap_number + ' - Premio: ' + (w.prize || ''), 'success');
            }
            loadTapazos();
        } catch (e) { Utils.showNotification('Error al completar tapazo', 'error'); }
    };

    async function loadUserBoughtTickets() {
        const loading = document.getElementById('user-tickets-loading');
        const container = document.getElementById('user-tickets-container');
        const empty = document.getElementById('no-user-tickets');
        
        loading.classList.remove('hidden');
        container.innerHTML = '';
        empty.classList.add('hidden');
        
        try {
            const user = JSON.parse(localStorage.getItem('misrifas_user') || '{}');
            const token = localStorage.getItem('misrifas_token');
            if (!token) return;

            // El endpoint busca por teléfono o código único (no por user_id, que
            // daba 400). Si el usuario no tiene teléfono asociado, no hay nada que
            // consultar: estado vacío en vez de error.
            if (!user.phone) { loading.classList.add('hidden'); empty.classList.remove('hidden'); return; }

            const res = await API.get('/user/tickets.php', { phone: user.phone });
            loading.classList.add('hidden');
            
            if (res.success && res.data && res.data.tickets && res.data.tickets.length > 0) {
                container.innerHTML = res.data.tickets.map(ticket => {
                    const opps = typeof ticket.opportunities === 'string' ? JSON.parse(ticket.opportunities) : (ticket.opportunities || []);
                    const statusClass = ticket.status === 'paid' ? 'completed' : 'pending';
                    const statusText = ticket.status === 'paid' ? 'Pagada' : 'Reservada';
                    
                    const oppNumbersHtml = opps.length > 0 
                        ? opps.map(n => `<span class="badge badge--pending" style="margin-right:4px;">${n}</span>`).join('')
                        : '<span class="text-gray-400">--</span>';
                        
                    return `
                        <div class="section-card" style="border-left: 5px solid #3b82f6; background: #f8fafc;">
                            <div class="flex justify-between items-start mb-2">
                                <h3 class="font-bold text-lg">${ticket.raffle_name || 'Rifa'}</h3>
                                <span class="badge badge--${statusClass}">${statusText}</span>
                            </div>
                            <div class="flex items-center gap-6">
                                <div style="font-size: 2.5rem; font-weight: 900; color: #2563eb; background: white; padding: 10px 20px; border-radius: 12px; border: 2px solid #e2e8f0;">
                                    ${ticket.ticket_number || '--'}
                                </div>
                                <div>
                                    <div class="text-sm font-bold text-gray-500 mb-1 uppercase tracking-wider">Mis Números Random:</div>
                                    <div class="flex flex-wrap">${oppNumbersHtml}</div>
                                    <div class="text-sm text-gray-500 mt-2">
                                        Precio: $${parseFloat(ticket.ticket_price || 0).toLocaleString('es-CO')} | 
                                        Sorteo: ${new Date(ticket.draw_date).toLocaleDateString()}
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
            } else {
                empty.classList.remove('hidden');
            }
        } catch (e) {
            // "Usuario no encontrado" (no compró boletos con este teléfono) no es
            // un error: se muestra el estado vacío en vez de un mensaje rojo.
            loading.classList.add('hidden');
            container.innerHTML = '';
            empty.classList.remove('hidden');
        }
    }

    // ================================================================
    // RESULTADO DE LOTERÍA MANUAL (solo super_admin)
    // ================================================================
    async function loadLotteryResultUI() {
        try {
            const res = await API.get('/lotteries/index.php');
            if (res.success) {
                document.getElementById('lr-lottery').innerHTML = '<option value="">Selecciona…</option>' +
                    res.data.map(l => '<option value="' + l.id + '">' + userEsc(l.name) + '</option>').join('');
            }
        } catch (e) {}
        try {
            const p = await API.get('/admin/lottery-results/pending.php');
            const box = document.getElementById('pending-draws');
            if (p.success && (p.data || []).length) {
                box.innerHTML = '<div class="font-bold text-amber-700 mb-2">Sorteos pendientes de resultado:</div>' +
                    p.data.map(d => '<button type="button" class="inline-block mr-2 mb-2 px-3 py-1 bg-amber-100 text-amber-800 rounded-lg hover:bg-amber-200" onclick="fillPendingResult(' + d.lottery_id + ',\'' + d.draw_date + '\')">' + userEsc(d.lottery_name) + ' · ' + d.draw_date + ' (' + d.rifas + ' rifa' + (d.rifas > 1 ? 's' : '') + ')</button>').join('');
            } else if (box) {
                box.innerHTML = '<span class="text-gray-400">No hay sorteos pendientes de resultado.</span>';
            }
        } catch (e) {}
    }

    window.fillPendingResult = (lotteryId, date) => {
        document.getElementById('lr-lottery').value = lotteryId;
        document.getElementById('lr-date').value = date;
        document.getElementById('lr-number').focus();
    };

    document.getElementById('lottery-result-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = e.target.querySelector('button[type="submit"]');
        btn.disabled = true; btn.textContent = 'Guardando…';
        try {
            await API.post('/admin/lottery-results/set.php', {
                lottery_id: parseInt(document.getElementById('lr-lottery').value),
                draw_date: document.getElementById('lr-date').value,
                winning_number: document.getElementById('lr-number').value
            });
            Utils.showNotification('Resultado guardado ✅', 'success');
            e.target.reset();
            loadLotteryResultUI();
        } catch (err) {
            Utils.showNotification(err.message || 'Error al guardar el resultado', 'error');
        } finally {
            btn.disabled = false; btn.textContent = 'Guardar resultado';
        }
    });

    // Sugerir con IA: busca el número REAL (scraper, y si no da, IA leyendo la
    // página) y lo precarga como sugerencia. Nunca guarda ni verifica: se
    // revisa y se confirma con "Guardar resultado".
    document.getElementById('lr-suggest')?.addEventListener('click', async () => {
        const lotteryId = parseInt(document.getElementById('lr-lottery').value);
        const date = document.getElementById('lr-date').value;
        const note = document.getElementById('lr-suggest-note');
        if (!lotteryId || !date) { Utils.showNotification('Elige la lotería y la fecha primero', 'error'); return; }
        const btn = document.getElementById('lr-suggest');
        const orig = btn.textContent;
        btn.disabled = true; btn.textContent = 'Buscando…';
        note.classList.add('hidden');
        try {
            const res = await API.get('/admin/lottery-results/suggest.php', { lottery_id: lotteryId, draw_date: date });
            if (res && res.success && res.number) {
                document.getElementById('lr-number').value = res.number;
                const via = res.method === 'ia' ? 'IA (leyó la página)' : 'búsqueda automática';
                note.textContent = 'Sugerido por ' + via + ' · fuente: ' + (res.source || '—') + '. ' + (res.note || 'Verifícalo antes de guardar.');
                note.classList.remove('hidden');
                Utils.showNotification('Número sugerido: ' + res.number + ' — verifícalo antes de guardar', 'success');
            } else {
                note.textContent = (res && res.message) || 'No se pudo sugerir automáticamente. Ingrésalo manualmente.';
                note.classList.remove('hidden');
                Utils.showNotification((res && res.message) || 'Sin sugerencia', 'info');
            }
        } catch (err) {
            Utils.showNotification(err.message || 'Error al generar la sugerencia', 'error');
        } finally {
            btn.disabled = false; btn.textContent = orig;
        }
    });

    // ================================================================
    // GESTIÓN DE USUARIOS (solo super_admin)
    // ================================================================
    let allUsers = [];

    function userEsc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    async function loadUsers() {
        const tbody = document.getElementById('users-table');
        try {
            const res = await API.get('/admin/users/list.php');
            if (res.success) {
                allUsers = res.data || [];
                renderUsersTable(allUsers);
            }
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-red-500">Error al cargar usuarios</td></tr>';
        }
    }

    function renderUsersTable(users) {
        const tbody = document.getElementById('users-table');
        if (!users || users.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-gray-500 py-6">No hay usuarios</td></tr>';
            return;
        }
        const typeLabel = { vendor: 'Organizador', buyer: 'Comprador' };
        const roleLabel = { super_admin: 'Super Admin', vendor: 'Organizador', buyer: 'Comprador' };
        tbody.innerHTML = users.map(u => {
            const suspended = u.status !== 'active';
            const statusBadge = suspended
                ? '<span class="badge badge--cancelled">Suspendido</span>'
                : '<span class="badge badge--active">Activo</span>';
            const toggleBtn = suspended
                ? '<button onclick="toggleUserStatus(\'' + u.type + '\',' + u.id + ',\'activate\')" class="btn btn--sm" style="background:#10b981;color:white;" title="Activar">Activar</button>'
                : '<button onclick="toggleUserStatus(\'' + u.type + '\',' + u.id + ',\'suspend\')" class="btn btn--sm" style="background:#f59e0b;color:#1c1305;" title="Suspender">Suspender</button>';
            return '<tr>' +
                '<td><span class="badge badge--' + (u.type === 'vendor' ? 'completed' : 'pending') + '">' + typeLabel[u.type] + '</span></td>' +
                '<td class="font-medium">' + userEsc(u.name) + (u.deps ? ' <span class="text-gray-400 text-xs">(' + u.deps + ')</span>' : '') + '</td>' +
                '<td style="color:#64748b;font-size:13px;">' + userEsc(u.email || '—') + '</td>' +
                '<td>' + userEsc(u.phone || '—') + '</td>' +
                '<td>' + (roleLabel[u.role] || u.role) + '</td>' +
                '<td>' + statusBadge + '</td>' +
                '<td style="color:#94a3b8;font-size:12px;">' + (u.created_at ? new Date(u.created_at).toLocaleDateString('es-CO') : '—') + '</td>' +
                '<td class="flex gap-2 flex-wrap">' +
                    '<button onclick="openUserEdit(\'' + u.type + '\',' + u.id + ')" class="btn btn--sm" style="background:#3b82f6;color:white;" title="Editar">Editar</button>' +
                    toggleBtn +
                    '<button onclick="deleteUser(\'' + u.type + '\',' + u.id + ',\'' + userEsc(u.name).replace(/'/g, "\\'") + '\')" class="btn btn--sm" style="background:#ef4444;color:white;" title="Eliminar">Eliminar</button>' +
                '</td>' +
            '</tr>';
        }).join('');
    }

    window.filterUsersTable = () => {
        const type = document.getElementById('user-filter-type').value;
        const status = document.getElementById('user-filter-status').value;
        const search = document.getElementById('user-filter-search').value.toLowerCase();
        let filtered = allUsers;
        if (type) filtered = filtered.filter(u => u.type === type);
        if (status) filtered = filtered.filter(u => (status === 'active' ? u.status === 'active' : u.status !== 'active'));
        if (search) filtered = filtered.filter(u =>
            (u.name || '').toLowerCase().includes(search) ||
            (u.email || '').toLowerCase().includes(search) ||
            (u.phone || '').toLowerCase().includes(search));
        renderUsersTable(filtered);
    };

    window.openUserEdit = (type, id) => {
        const u = allUsers.find(x => x.type === type && x.id === id);
        if (!u) return;
        document.getElementById('eu-type').value = u.type;
        document.getElementById('eu-id').value = u.id;
        document.getElementById('eu-name').value = u.name || '';
        document.getElementById('eu-email').value = u.email || '';
        document.getElementById('eu-phone').value = u.phone || '';
        document.getElementById('eu-city').value = u.city || '';
        document.getElementById('eu-department').value = u.department || '';
        const roleGroup = document.getElementById('eu-role-group');
        if (u.type === 'vendor') {
            roleGroup.style.display = '';
            document.getElementById('eu-role').value = u.role;
        } else {
            roleGroup.style.display = 'none';
        }
        document.getElementById('edit-user-title').textContent = 'Editar ' + (u.type === 'vendor' ? 'Organizador' : 'Comprador');
        document.getElementById('edit-user-modal').classList.remove('hidden');
    };

    window.closeUserModal = () => document.getElementById('edit-user-modal').classList.add('hidden');

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const m = document.getElementById('edit-user-modal');
            if (m && !m.classList.contains('hidden')) closeUserModal();
        }
    });

    document.getElementById('edit-user-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = e.target.querySelector('button[type="submit"]');
        btn.disabled = true; btn.textContent = 'Guardando…';
        const type = document.getElementById('eu-type').value;
        const payload = {
            type,
            id: parseInt(document.getElementById('eu-id').value),
            name: document.getElementById('eu-name').value,
            email: document.getElementById('eu-email').value,
            phone: document.getElementById('eu-phone').value,
            city: document.getElementById('eu-city').value,
            department: document.getElementById('eu-department').value
        };
        if (type === 'vendor') payload.role = document.getElementById('eu-role').value;
        try {
            await API.post('/admin/users/update.php', payload);
            Utils.showNotification('Usuario actualizado ✅', 'success');
            closeUserModal();
            loadUsers();
        } catch (err) {
            Utils.showNotification(err.message || 'Error al actualizar', 'error');
        } finally {
            btn.disabled = false; btn.textContent = 'Guardar Cambios';
        }
    });

    window.toggleUserStatus = async (type, id, action) => {
        const verb = action === 'suspend' ? 'suspender' : 'activar';
        if (!confirm('¿Seguro que deseas ' + verb + ' este usuario?')) return;
        try {
            await API.post('/admin/users/status.php', { type, id, action });
            Utils.showNotification('Usuario ' + (action === 'suspend' ? 'suspendido' : 'activado') + ' ✅', 'success');
            loadUsers();
        } catch (err) {
            Utils.showNotification(err.message || 'Error al cambiar estado', 'error');
        }
    };

    window.deleteUser = async (type, id, name) => {
        if (!confirm('¿Eliminar definitivamente a "' + name + '"?\n\nSi tiene rifas o boletos asociados, no se podrá borrar (suspéndelo en su lugar).')) return;
        try {
            await API.post('/admin/users/delete.php', { type, id });
            Utils.showNotification('Usuario eliminado ✅', 'success');
            loadUsers();
        } catch (err) {
            Utils.showNotification(err.message || 'Error al eliminar', 'error');
        }
    };

    async function loadGestionRaffles() {
        try {
            const response = await API.get('/admin/raffles.php');
            if (response.success) {
                allGestionRaffles = response.data || [];
                populateOrganizerFilter();
                renderGestionTable(allGestionRaffles);
            }
        } catch (error) { console.error('Error loading gestion raffles:', error); }
    }

    function renderGestionTable(raffles) {
        const tbody = document.getElementById('gestion-raffles-table');
        if (!raffles || raffles.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-gray-500 py-6">No hay rifas</td></tr>';
            return;
        }
        const statusMap = { draft: 'Borrador', active: 'Activa', blocked: 'Bloqueada', completed: 'Completada', cancelled: 'Cancelada' };
        tbody.innerHTML = raffles.map(r => {
            const statusClass = r.status === 'active' ? 'active' : (r.status === 'completed' ? 'completed' : (r.status === 'cancelled' ? 'cancelled' : (r.status === 'blocked' ? 'pending' : 'pending')));
            return '<tr>' +
                '<td class="text-gray-400 text-sm">' + (r.id || '') + '</td>' +
                '<td class="font-medium">' + (r.name || '') + '</td>' +
                '<td style="color:#64748b;font-size:13px;">' + (r.creator_name || '--') + '</td>' +
                '<td>' + (r.city || '--') + '</td>' +
                '<td><span class="badge badge--' + statusClass + '">' + (statusMap[r.status] || r.status) + '</span></td>' +
                '<td class="font-bold">' + (r.sold_tickets || 0) + '</td>' +
                '<td>' + (r.draw_date ? new Date(r.draw_date).toLocaleDateString('es-CO') : '--') + '</td>' +
                '<td class="flex gap-2 flex-wrap">' +
                    '<button onclick="openEditModal(' + r.id + ')" class="btn btn--sm" style="background:#3b82f6;color:white;" title="Editar">✏️ Editar</button>' +
                    '<button onclick="deleteRaffle(' + r.id + ', \'' + (r.name || '').replace(/'/g, "\\'") + '\')" class="btn btn--sm" style="background:#ef4444;color:white;" title="Eliminar">🗑️ Eliminar</button>' +
                '</td>' +
            '</tr>';
        }).join('');
    }

    window.changeRaffleStatus = async (raffleId, newStatus) => {
        try {
            await API.post('/admin/raffles/update_status.php', { raffle_id: raffleId, status: newStatus });
            Utils.showNotification('Estado de la rifa actualizado a "' + newStatus + '" ✅', 'success');
            loadGestionRaffles();
        } catch (error) { 
            Utils.showNotification(error.message || 'Error al cambiar estado', 'error'); 
            loadGestionRaffles();
        }
    };

    window.filterRafflesTable = () => {
        const status = document.getElementById('filter-status').value;
        const organizer = document.getElementById('filter-organizer').value;
        const search = document.getElementById('filter-search').value.toLowerCase();
        let filtered = allGestionRaffles;
        if (status) filtered = filtered.filter(r => r.status === status);
        if (organizer) filtered = filtered.filter(r => String(r.created_by) === organizer);
        if (search) filtered = filtered.filter(r => (r.name || '').toLowerCase().includes(search) || (r.city || '').toLowerCase().includes(search) || (r.creator_name || '').toLowerCase().includes(search));
        renderGestionTable(filtered);
    };

    // Populate organizer filter
    function populateOrganizerFilter() {
        const select = document.getElementById('filter-organizer');
        const organizers = [...new Map(allGestionRaffles.map(r => [r.created_by, r.creator_name])).entries()];
        select.innerHTML = '<option value="">Todos los organizadores</option>' + 
            organizers.map(([id, name]) => `<option value="${id}">${name || 'Usuario ' + id}</option>`).join('');
    }

    // --- EDIT MODAL ---
    window.openEditModal = async (raffleId) => {
        const raffle = allGestionRaffles.find(r => r.id === raffleId);
        if (!raffle) return;
        
        document.getElementById('edit-raffle-id').value = raffle.id;
        document.getElementById('edit-name').value = raffle.name || '';
        document.getElementById('edit-status').value = raffle.status || 'draft';
        document.getElementById('edit-price').value = raffle.ticket_price || 0;
        document.getElementById('edit-total-tickets').value = raffle.total_tickets || 0;
        document.getElementById('edit-draw-date').value = raffle.draw_date ? raffle.draw_date.substring(0, 16) : '';
        document.getElementById('edit-lottery-id').value = raffle.lottery_id || '';
        document.getElementById('edit-whatsapp').value = raffle.whatsapp_contact || '';
        document.getElementById('edit-responsible').value = raffle.responsible_person || '';
        document.getElementById('edit-description').value = raffle.description || '';
        
        // Populate lottery dropdown
        const lotterySelect = document.getElementById('edit-lottery-id');
        lotterySelect.innerHTML = '<option value="">Seleccionar...</option>' +
            Object.entries(LOTTERY_DAYS).map(([id, l]) => `<option value="${id}">${l.name || ''}</option>`).join('');
        
        document.getElementById('edit-raffle-modal').classList.remove('hidden');
    };

    window.closeEditModal = () => {
        document.getElementById('edit-raffle-modal').classList.add('hidden');
    };

    // Cerrar el modal con Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const modal = document.getElementById('edit-raffle-modal');
            if (modal && !modal.classList.contains('hidden')) closeEditModal();
        }
    });

    document.getElementById('edit-raffle-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = e.target.querySelector('button[type="submit"]');
        btn.disabled = true; btn.textContent = 'Guardando…';
        
        try {
            await API.post('/admin/raffles/update.php', {
                id: parseInt(document.getElementById('edit-raffle-id').value),
                name: document.getElementById('edit-name').value,
                status: document.getElementById('edit-status').value,
                ticket_price: parseFloat(document.getElementById('edit-price').value),
                total_tickets: parseInt(document.getElementById('edit-total-tickets').value),
                draw_date: document.getElementById('edit-draw-date').value,
                lottery_id: parseInt(document.getElementById('edit-lottery-id').value),
                whatsapp_contact: document.getElementById('edit-whatsapp').value,
                responsible_person: document.getElementById('edit-responsible').value,
                description: document.getElementById('edit-description').value
            });
            Utils.showNotification('Rifa actualizada exitosamente ✅', 'success');
            closeEditModal();
            loadGestionRaffles();
        } catch (error) {
            Utils.showNotification(error.message || 'Error al actualizar rifa', 'error');
        } finally {
            btn.disabled = false; btn.textContent = 'Guardar Cambios';
        }
    });

    // --- DELETE RAFFLE ---
    window.deleteRaffle = async (raffleId, raffleName) => {
        if (!confirm('¿Estás seguro de eliminar la rifa "' + raffleName + '"?\n\nEsta acción no se puede deshacer.')) return;
        
        try {
            await API.post('/admin/raffles/delete.php', { raffle_id: raffleId });
            Utils.showNotification('Rifa "' + raffleName + '" eliminada ✅', 'success');
            loadGestionRaffles();
        } catch (error) {
            Utils.showNotification(error.message || 'Error al eliminar rifa', 'error');
        }
    };

    async function loadLotteries() {
        try {
            const response = await API.get('/lotteries/index.php');
            if (response.success) {
                response.data.forEach(l => { LOTTERY_DAYS[l.id] = l; });
                const select = document.getElementById('lottery-id');
                select.innerHTML = '<option value="">Seleccionar lotería…</option>' +
                    response.data.map(l => '<option value="' + l.id + '">' + (l.name || '') + '</option>').join('');
            }
        } catch (error) { console.error('Error loading lotteries:', error); }
        // Don't call onDigitsChange here - let switchTo handle initialization
    }

    // ================================================================
    // LÓGICA DINÁMICA: Cifras, Oportunidades, Modo de Ganar
    // ================================================================
    window.onDigitsChange = function() {
        const digits = parseInt(document.getElementById('digits').value);
        
        // Configurar opciones de boletos según cifras
        const ticketsSelect = document.getElementById('total-tickets');
        if (digits === 2) {
            ticketsSelect.innerHTML = '<option value="100" selected>100</option>';
        } else if (digits === 3) {
            ticketsSelect.innerHTML = '<option value="1000" selected>1,000</option>';
        } else {
            ticketsSelect.innerHTML = '<option value="10000" selected>10,000</option>';
        }
        
        // Deshabilitar y aplicar estilo a opciones de boletos no válidas
        ticketsSelect.querySelectorAll('option').forEach(opt => {
            if (digits === 2) {
                opt.disabled = opt.value !== '100';
            } else if (digits === 3) {
                opt.disabled = opt.value !== '1000';
            } else {
                opt.disabled = opt.value !== '10000';
            }
        });
        
        updateOpportunitiesOptions();
        updateWinningModeOptions();
    };
    
    window.onOpportunitiesChange = function() {
        const opp = parseInt(document.getElementById('opportunities').value);
        const digits = parseInt(document.getElementById('digits').value);
        const maxNumbers = Math.pow(10, digits);
        const actualTickets = Math.floor(maxNumbers / opp);
        const hint = document.getElementById('opportunities-hint');
        if (hint) {
            hint.textContent = 'Se generarán ' + actualTickets + ' boletos con ' + opp + ' número(s) random únicos cada uno (de ' + maxNumbers + ' posibles).';
        }
    };
    
    // Make functions globally accessible for inline event handlers
    onDigitsChange = window.onDigitsChange;
    onOpportunitiesChange = window.onOpportunitiesChange;

    window.onTotalTicketsChange = function() {
        const totalTickets = parseInt(document.getElementById('total-tickets').value);
        const digitsSelect = document.getElementById('digits');
        
        console.log('onTotalTicketsChange called, totalTickets:', totalTickets);
        
        // 100 boletas = 2 cifras, 1000 = 3 cifras, 10000 = 4 cifras
        if (totalTickets === 100) {
            digitsSelect.value = '2';
        } else if (totalTickets === 1000) {
            digitsSelect.value = '3';
        } else if (totalTickets === 10000) {
            digitsSelect.value = '4';
        }
        
        console.log('digits value after change:', digitsSelect.value);
        
        // Deshabilitar opciones de cifras no válidas
        digitsSelect.querySelectorAll('option').forEach(opt => {
            if (totalTickets === 100) {
                opt.disabled = opt.value !== '2';
            } else if (totalTickets === 1000) {
                opt.disabled = opt.value !== '3';
            } else if (totalTickets === 10000) {
                opt.disabled = opt.value !== '4';
            }
        });
        
        console.log('Calling updateOpportunitiesOptions');
        updateOpportunitiesOptions();
        console.log('Calling updateWinningModeOptions');
        updateWinningModeOptions();
    };
    
    // Also make it globally accessible for inline event handlers
    onTotalTicketsChange = window.onTotalTicketsChange;

    function updateOpportunitiesOptions() {
        const digits = parseInt(document.getElementById('digits').value);
        const oppSelect = document.getElementById('opportunities');
        const hint = document.getElementById('opportunities-hint');
        
        if (digits === 2) {
            oppSelect.innerHTML = '<option value="1" selected>1 oportunidad</option>';
            oppSelect.value = '1';
            hint.textContent = '100 boletos con 1 número random único cada uno (00-99).';
        } else if (digits === 3) {
            oppSelect.innerHTML = 
                '<option value="1" selected>1 oportunidad</option>' +
                '<option value="2">2 oportunidades</option>' +
                '<option value="4">4 oportunidades</option>' +
                '<option value="5">5 oportunidades</option>';
            hint.textContent = '1,000 boletos con 1 número random único cada uno (000-999).';
        } else {
            oppSelect.innerHTML = 
                '<option value="1" selected>1 oportunidad</option>' +
                '<option value="2">2 oportunidades</option>' +
                '<option value="4">4 oportunidades</option>' +
                '<option value="5">5 oportunidades</option>';
            hint.textContent = '10,000 boletos con 1 número random único cada uno (0000-9999).';
        }
        onOpportunitiesChange();
    }

    function updateWinningModeOptions() {
        const digits = parseInt(document.getElementById('digits').value);
        const modeSelect = document.getElementById('winning-mode');
        const hint = document.getElementById('winning-mode-hint');
        
        if (digits === 2) {
            // 2 cifras: solo últimas 2 y primeras 2
            modeSelect.innerHTML = 
                '<option value="last_2">Últimas 2 cifras</option>' +
                '<option value="first_2">Primeras 2 cifras</option>';
            modeSelect.disabled = false;
            if (hint) hint.textContent = 'Gana con las 2 cifras del número sorteado.';
        } else if (digits === 3) {
            // 3 cifras: últimas 3 y primeras 3
            modeSelect.innerHTML = 
                '<option value="last_3">Últimas 3 cifras</option>' +
                '<option value="first_3">Primeras 3 cifras</option>';
            modeSelect.disabled = false;
            if (hint) hint.textContent = 'Gana con las 3 cifras del número sorteado.';
        } else {
            // 4 cifras: solo últimas 4
            modeSelect.innerHTML = 
                '<option value="last_4">Últimas 4 cifras</option>';
            modeSelect.disabled = false;
            if (hint) hint.textContent = 'Gana con las 4 cifras del número sorteado.';
        }
    }

    async function loadSettings() {
        try {
            const response = await API.get('/admin/settings.php');
            if (response.success) {
                const d = response.data;
                // Wompi
                if (d.wompi_public_key) document.getElementById('wompi-public-key').value = d.wompi_public_key;
                // General
                if (d.platform_name) document.getElementById('cfg-platform-name').value = d.platform_name;
                if (d.platform_email) document.getElementById('cfg-platform-email').value = d.platform_email;
                if (d.min_ticket_price) document.getElementById('cfg-min-ticket-price').value = d.min_ticket_price;
                if (d.max_ticket_price) document.getElementById('cfg-max-ticket-price').value = d.max_ticket_price;
                if (d.reservation_minutes) document.getElementById('cfg-reservation-minutes').value = d.reservation_minutes;
                if (d.max_tickets_per_purchase) document.getElementById('cfg-max-tickets-buyer').value = d.max_tickets_per_purchase;
                // Comisiones
                if (d.commission_enabled !== undefined) {
                    document.getElementById('commission-enabled').checked = d.commission_enabled === '1';
                }
                if (d.commission_percentage) {
                    document.getElementById('commission-percentage').value = d.commission_percentage;
                    document.getElementById('commission-percentage-slider').value = d.commission_percentage;
                }
                toggleCommissionUI();
                updateCommissionPreview();
            }
        } catch (error) { console.error('Error loading settings:', error); }
    }

    async function saveGeneralSettings() {
        try {
            await API.post('/admin/settings.php', {
                platform_name: document.getElementById('cfg-platform-name').value,
                platform_email: document.getElementById('cfg-platform-email').value,
                min_ticket_price: document.getElementById('cfg-min-ticket-price').value,
                max_ticket_price: document.getElementById('cfg-max-ticket-price').value,
                reservation_minutes: document.getElementById('cfg-reservation-minutes').value,
                max_tickets_per_purchase: document.getElementById('cfg-max-tickets-buyer').value
            });
            Utils.showNotification('Configuración general guardada ✅', 'success');
        } catch (error) { Utils.showNotification('Error al guardar', 'error'); }
    }

    async function markCommissionPaid(raffleId) {
        try {
            await API.post('/admin/commissions.php', { raffle_id: raffleId, action: 'mark_paid' });
            Utils.showNotification('Comisión marcada como pagada ✅', 'success');
            loadCommissions();
        } catch (error) { Utils.showNotification('Error al actualizar', 'error'); }
    }

    function toggleCommissionUI() {
        const enabled = document.getElementById('commission-enabled').checked;
        const settings = document.getElementById('commission-settings');
        const statusText = document.getElementById('commission-status-text');
        if (settings) settings.style.display = enabled ? 'block' : 'none';
        if (statusText) statusText.textContent = enabled ? 'Activado' : 'Desactivado';
    }

    function updateCommissionPreview() {
        const pct = parseFloat(document.getElementById('commission-percentage').value || 0);
        const amount = Math.round(1000000 * pct / 100);
        const el = document.getElementById('commission-preview');
        if (el) el.textContent = '$' + amount.toLocaleString('es-CO');
    }

    async function saveCommissionSettings() {
        const enabled = document.getElementById('commission-enabled').checked;
        const percentage = document.getElementById('commission-percentage').value;
        const btn = document.getElementById('save-commission-btn');
        btn.disabled = true; btn.textContent = 'Guardando…';
        try {
            await API.post('/admin/settings.php', {
                commission_enabled: enabled ? '1' : '0',
                commission_percentage: percentage
            });
            Utils.showNotification('Configuración de comisiones guardada ✅', 'success');
        } catch (error) { Utils.showNotification('Error al guardar', 'error'); }
        finally { btn.disabled = false; btn.textContent = 'Guardar Configuración de Comisiones'; }
    }

    async function loadEmailSettings() {
        try {
            const keys = ['mailing_smtp_host', 'mailing_smtp_port', 'mailing_smtp_user', 'mailing_smtp_from', 'mailing_from_name'];
            for (const key of keys) {
                const res = await API.get('/settings/get.php?key=' + key);
                if (res.success) {
                    const id = key.replace('mailing_smtp_', 'smtp-').replace('mailing_from_name', 'smtp-from-name');
                    const input = document.getElementById(id);
                    if (input && res.data) input.value = res.data;
                }
            }
        } catch (e) { console.error('Error loading email settings', e); }
    }

    document.getElementById('email-settings-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fields = [
            { key: 'mailing_smtp_host', id: 'smtp-host' },
            { key: 'mailing_smtp_port', id: 'smtp-port' },
            { key: 'mailing_smtp_user', id: 'smtp-user' },
            { key: 'mailing_smtp_pass', id: 'smtp-pass' },
            { key: 'mailing_smtp_from', id: 'smtp-from' },
            { key: 'mailing_from_name', id: 'smtp-from-name' }
        ];
        try {
            for (const f of fields) {
                const input = document.getElementById(f.id);
                if (!input) continue;
                const val = input.value;
                if (val || f.key.includes('pass')) {
                    await API.post('/admin/settings/update.php', { key: f.key, value: val });
                }
            }
            Utils.showNotification('Configuración SMTP guardada ✅', 'success');
        } catch (err) { Utils.showNotification('Error al guardar configuración SMTP', 'error'); }
    });

    /* ============================================================
     * LOTERÍA → validación día de semana + hora automática
     * ============================================================ */
    const LOTTERY_DAYS = {}; // { id: { day_of_week, draw_time, name } }
    const DAY_NAMES_ES = {
        'monday':'lunes','tuesday':'martes','wednesday':'miércoles',
        'thursday':'jueves','friday':'viernes','saturday':'sábado','sunday':'domingo'
    };
    const DAY_ISO = { 'sunday':0,'monday':1,'tuesday':2,'wednesday':3,'thursday':4,'friday':5,'saturday':6 };

    document.getElementById('lottery-id').addEventListener('change', function() {
        const lottery = LOTTERY_DAYS[this.value];
        const hint = document.getElementById('lottery-day-hint');
        if (lottery) {
            hint.textContent = '🗓️ Juega los ' + DAY_NAMES_ES[lottery.day_of_week] + ' a las ' + lottery.draw_time.substring(0,5);
        } else {
            hint.textContent = '';
        }
        validateLotteryDate();
    });

    document.getElementById('draw-date').addEventListener('change', validateLotteryDate);

    function validateLotteryDate() {
        const lotteryId = document.getElementById('lottery-id').value;
        const dateVal   = document.getElementById('draw-date').value;
        const errDiv    = document.getElementById('create-date-error');
        errDiv.classList.add('hidden');
        if (!lotteryId || !dateVal) return true;
        const lottery = LOTTERY_DAYS[lotteryId];
        if (!lottery) return true;
        // getDay() returns 0=Sun, 1=Mon ... 6=Sat
        const selected = new Date(dateVal + 'T12:00:00');
        const selectedDay = selected.getDay();
        const expectedDay = DAY_ISO[lottery.day_of_week];
        if (selectedDay !== expectedDay) {
            const dayNamesErr = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
            const msg = '⚠️ La ' + lottery.name + ' sortea los ' +
                DAY_NAMES_ES[lottery.day_of_week] + ', pero seleccionaste un ' +
                dayNamesErr[selectedDay] + '. Elige una fecha que caiga en ' +
                DAY_NAMES_ES[lottery.day_of_week] + '.';
            Utils.showNotification(msg, 'error');
            return false;
        }
        return true;
    }

    /* ============================================================
     * CARGA DE IMAGEN
     * ============================================================ */
    const dropZone  = document.getElementById('image-drop-zone');
    const fileInput = document.getElementById('raffle-image-file');
    const placeholder = document.getElementById('image-placeholder');
    const statusEl  = document.getElementById('image-upload-status');

    let raffleImages = []; // Array de URLs finales

    async function uploadImages(files) {
        if (raffleImages.length + files.length > 10) {
            Utils.showNotification('¡Solo puedes subir hasta 10 fotos! 📸', 'warning');
            return;
        }

        statusEl.textContent = `⏳ Subiendo ${files.length} imagen(es)…`;
        const fd = new FormData();
        Array.from(files).forEach(f => fd.append('image[]', f));

        try {
            const token = localStorage.getItem('misrifas_token');
            const res = await fetch(BASE_PATH + '/api/upload/image.php', {
                method: 'POST',
                headers: { 
                    'Authorization': 'Bearer ' + token
                },
                body: fd
            });
            const json = await res.json();
            console.log('Upload response:', json);
            if (json.success) {
                raffleImages = raffleImages.concat(json.data.urls);
                renderThumbnails();
                statusEl.textContent = `✅ ${raffleImages.length}/10 fotos cargadas`;
                Utils.showNotification('Foto(s) cargada(s) exitosamente', 'success');
            } else {
                Utils.showNotification(json.message || 'Error al cargar las fotos', 'error');
            }
        } catch (e) {
            console.error('Upload error:', e);
            Utils.showNotification('Error de conexión al subir fotos: ' + e.message, 'error');
        }
    }

    function renderThumbnails() {
        const grid = document.getElementById('images-preview-grid');
        const placeholder = document.getElementById('image-placeholder');
        grid.innerHTML = raffleImages.map((url, i) => `
            <div class="relative w-full aspect-square rounded-lg overflow-hidden border border-slate-200">
                <img src="${fixUrl(url)}" class="w-full h-full object-cover">
                <button type="button" onclick="removeRafflePhoto(${i})" class="absolute top-1 right-1 bg-red-500 text-white w-5 h-5 rounded-full flex items-center justify-center text-[10px] shadow-lg">×</button>
            </div>
        `).join('');
        if (placeholder) placeholder.style.display = raffleImages.length > 0 ? 'none' : 'block';
    }

    window.removeRafflePhoto = (i) => {
        raffleImages.splice(i, 1);
        renderThumbnails();
        statusEl.textContent = raffleImages.length > 0 ? `✅ ${raffleImages.length}/10 fotos cargadas` : '';
    };

    dropZone.addEventListener('click', () => fileInput.click());
    dropZone.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInput.click(); }
    });
    dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.style.borderColor='#2563eb'; });
    dropZone.addEventListener('dragleave', () => { dropZone.style.borderColor='#cbd5e1'; });
    dropZone.addEventListener('drop', e => {
        e.preventDefault();
        dropZone.style.borderColor='#cbd5e1';
        if (e.dataTransfer.files.length) uploadImages(e.dataTransfer.files);
    });
    fileInput.addEventListener('change', () => { if (fileInput.files.length) uploadImages(fileInput.files); });

    /* ============================================================
     * SUBMIT FORM CREAR RIFA
     * ============================================================ */
    document.getElementById('create-raffle-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!validateLotteryDate()) return;

        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);
        
        // Calcular total_tickets real según oportunidades y cifras
        const digits = parseInt(data.digits);
        const opportunities = parseInt(data.opportunities);
        const maxNumbers = Math.pow(10, digits);
        const actualTickets = Math.floor(maxNumbers / opportunities);
        
        data.total_tickets = actualTickets;
        data.image_urls = raffleImages;
        data.image_url = raffleImages.length > 0 ? raffleImages[0] : '/assets/images/placeholder.jpg';
        data.ticket_price  = parseFloat(data.ticket_price);
        data.digits        = digits;
        data.opportunities = opportunities;
        data.lottery_id    = parseInt(data.lottery_id);

        // Agregar hora automática según lotería
        const lottery = LOTTERY_DAYS[data.lottery_id];
        if (lottery && data.draw_date) {
            data.draw_date = data.draw_date + ' ' + lottery.draw_time;
        }

        const btn = document.getElementById('create-btn');
        btn.disabled = true;
        btn.textContent = 'Creando…';

        try {
            const response = await API.post('/raffles/create.php', data);
            Utils.showNotification('Rifa creada exitosamente (' + actualTickets + ' boletos con ' + opportunities + ' oportunidad(es))', 'success');
            e.target.reset();
            raffleImages = [];
            renderThumbnails();
            statusEl.textContent = '';
            document.getElementById('create-date-error').classList.add('hidden');
            document.getElementById('lottery-day-hint').textContent = '';
            onDigitsChange();
            setTimeout(() => switchTo('dashboard'), 1500);
        } catch (error) {
            Utils.showNotification(error.message || 'Error al crear rifa', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Crear Rifa';
        }
    });

    document.getElementById('wompi-config-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = {
            wompi_public_key: document.getElementById('wompi-public-key').value,
            wompi_private_key: document.getElementById('wompi-private-key').value
        };
        try {
            await API.post('/admin/settings.php', data);
            Utils.showNotification('Configuración de Wompi guardada', 'success');
        } catch (error) { Utils.showNotification('Error al guardar', 'error'); }
    });

    // ================================================================
    // PAGOS MANUALES: Cargar tabla y aprobar/rechazar
    // ================================================================
    async function loadPayments() {
        const tbody = document.getElementById('payments-table');
        try {
            const response = await API.get('/admin/payments.php');
            if (response.success) {
                const data = response.data || [];
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-gray-500 py-6">No hay pagos pendientes de validar</td></tr>';
                    return;
                }
                tbody.innerHTML = data.map(p => {
                    const statusMap = { pending: 'Pendiente', verifying: 'Por verificar', approved: 'Aprobado', rejected: 'Rechazado' };
                    const proofBtn = p.payment_proof_url
                        ? `<a href="<?= BASE_PATH ?>/public${p.payment_proof_url}" target="_blank" style="color:#2563eb;text-decoration:underline;font-size:13px;">Ver comprobante</a>`
                        : `<span style="color:#9ca3af;font-size:12px;">Sin comprobante</span>`;
                    const actions = (p.payment_status === 'pending' || p.payment_status === 'verifying')
                        ? `<button class="btn btn--sm" style="background:#10b981;color:white;margin-right:6px;" onclick="approvePayment(${p.ticket_id})">✅ Aprobar</button>
                           <button class="btn btn--sm" style="background:#ef4444;color:white;" onclick="rejectPayment(${p.ticket_id})">❌ Rechazar</button>`
                        : `<span style="color:#94a3b8;font-size:12px;">${statusMap[p.payment_status] || p.payment_status}</span>`;
                    return `<tr>
                        <td class="font-medium">${p.raffle_name || ''}</td>
                        <td><strong>#${p.ticket_number}</strong></td>
                        <td>${p.buyer_name || '—'}<br><span style="color:#94a3b8;font-size:12px;">${p.buyer_phone || ''}</span></td>
                        <td>${p.payment_method || '—'}</td>
                        <td><span class="badge badge--pending">${statusMap[p.payment_status] || p.payment_status}</span></td>
                        <td>${proofBtn}</td>
                        <td>${actions}</td>
                    </tr>`;
                }).join('');
            }
        } catch (error) { tbody.innerHTML = '<tr><td colspan="7" class="text-center text-red-500">Error al cargar pagos</td></tr>'; }
    }

    async function approvePayment(ticketId) {
        if (!confirm('¿Aprobar este pago? El boleto pasará a VENDIDO (rojo).')) return;
        try {
            await API.post('/admin/payments.php', { action: 'approve', ticket_id: ticketId });
            Utils.showNotification('✅ Pago aprobado. Boleto marcado como vendido.', 'success');
            loadPayments();
        } catch (e) { Utils.showNotification('Error al aprobar pago', 'error'); }
    }

    async function rejectPayment(ticketId) {
        if (!confirm('¿Rechazar este pago? El boleto volverá a estar disponible.')) return;
        try {
            await API.post('/admin/payments.php', { action: 'reject', ticket_id: ticketId });
            Utils.showNotification('❌ Pago rechazado. Boleto liberado.', 'info');
            loadPayments();
        } catch (e) { Utils.showNotification('Error al rechazar', 'error'); }
    }

    // ================================================================
    // MI PERFIL: Cargar y guardar Nequi + EvolutionAPI
    // ================================================================
    async function loadPerfilAPI() {
        try {
            // 1. Configuración de Pago/WA
            const res = await API.get('/admin/profile_api.php');
            if (res.success) {
                const p = res.data.payment_config || {};
                const w = res.data.wa_config || {};
                if (p.nequi_key)   document.getElementById('cfg-nequi-key').value   = p.nequi_key;
                if (p.nequi_secret) document.getElementById('cfg-nequi-secret').value = p.nequi_secret;
                if (p.nequi_phone) document.getElementById('cfg-nequi-phone').value  = p.nequi_phone;
                if (w.evo_api_url) document.getElementById('cfg-wa-url').value       = w.evo_api_url;
                if (w.evo_api_key) document.getElementById('cfg-wa-apikey').value    = w.evo_api_key;
                if (w.evo_instance) document.getElementById('cfg-wa-instance').value = w.evo_instance;
            }

            // 2. Datos Personales
            const resUser = await API.get('/user/get_profile.php');
            if (resUser.success) {
                const u = resUser.data;
                document.getElementById('p-name').value = u.full_name || u.name || '';
                document.getElementById('p-phone').value = u.phone || '';
                document.getElementById('p-city').value = u.city || '';
                document.getElementById('p-username').value = u.username || '';
                document.getElementById('p-display-name').textContent = u.full_name || u.name || 'Usuario';
                document.getElementById('p-display-role').textContent = (u.role || 'admin').toUpperCase();
                document.getElementById('p-display-email').textContent = u.email || '';

                if (u.profile_image) {
                    const img = document.getElementById('p-avatar-img');
                    img.src = fixUrl(u.profile_image);
                    img.classList.remove('hidden');
                    document.getElementById('p-avatar-text').classList.add('hidden');
                } else {
                    const name = u.full_name || u.name || 'U';
                    document.getElementById('p-avatar-text').textContent = name.charAt(0).toUpperCase();
                }
            }
        } catch (e) { console.error('Error al cargar perfil API', e); }
    }

    // Previsualización y Guardado Perfil Admin
    document.getElementById('p-image-input')?.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (ev) => {
                const img = document.getElementById('p-avatar-img');
                img.src = ev.target.result;
                img.classList.remove('hidden');
                document.getElementById('p-avatar-text').classList.add('hidden');
            };
            reader.readAsDataURL(file);
        }
    });

    document.getElementById('admin-profile-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('btn-save-p');
        btn.disabled = true; btn.textContent = 'Guardando…';

        const formData = new FormData();
        formData.append('name', document.getElementById('p-name').value);
        formData.append('phone', document.getElementById('p-phone').value);
        formData.append('city', document.getElementById('p-city').value);
        
        const file = document.getElementById('p-image-input').files[0];
        if (file) formData.append('profile_image', file);

        try {
            await API.post('/user/update_profile.php', formData);
            Utils.showNotification('¡Perfil actualizado con éxito! ✅', 'success');
            loadPerfilAPI();
        } catch (err) { Utils.showNotification('Error al actualizar datos', 'error'); }
        finally { btn.disabled = false; btn.textContent = 'Guardar Mis Datos'; }
    });

    document.getElementById('change-password-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('btn-save-cp');
        const current = document.getElementById('cp-current').value;
        const newPass = document.getElementById('cp-new').value;
        const confirm = document.getElementById('cp-confirm').value;

        if (newPass !== confirm) {
            Utils.showNotification('Las contraseñas nuevas no coinciden', 'error');
            return;
        }
        if (newPass.length < 8) {
            Utils.showNotification('La contraseña debe tener al menos 8 caracteres', 'error');
            return;
        }

        btn.disabled = true; btn.textContent = 'Cambiando…';
        try {
            await API.post('/user/change_password.php', {
                current_password: current,
                new_password: newPass
            });
            Utils.showNotification('¡Contraseña cambiada exitosamente! ✅', 'success');
            document.getElementById('change-password-form').reset();
        } catch (err) { Utils.showNotification(err.message || 'Error al cambiar contraseña', 'error'); }
        finally { btn.disabled = false; btn.textContent = 'Cambiar Contraseña'; }
    });

    document.getElementById('nequi-config-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('btn-save-nequi');
        btn.disabled = true; btn.textContent = 'Guardando…';
        try {
            await API.post('/admin/profile_api.php', {
                type: 'nequi',
                nequi_key:    document.getElementById('cfg-nequi-key').value,
                nequi_secret: document.getElementById('cfg-nequi-secret').value,
                nequi_phone:  document.getElementById('cfg-nequi-phone').value
            });
            Utils.showNotification('Credenciales Nequi guardadas ✅', 'success');
        } catch (err) { Utils.showNotification('Error al guardar Nequi', 'error'); }
        finally { btn.disabled = false; btn.textContent = 'Guardar Nequi API'; }
    });

    document.getElementById('wa-config-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('btn-save-wa');
        btn.disabled = true; btn.textContent = 'Guardando…';
        try {
            await API.post('/admin/profile_api.php', {
                type: 'whatsapp',
                evo_api_url:  document.getElementById('cfg-wa-url').value,
                evo_api_key:  document.getElementById('cfg-wa-apikey').value,
                evo_instance: document.getElementById('cfg-wa-instance').value
            });
            Utils.showNotification('Bot WhatsApp configurado ✅', 'success');
        } catch (err) { Utils.showNotification('Error al guardar WhatsApp', 'error'); }
        finally { btn.disabled = false; btn.textContent = 'Guardar Bot WhatsApp'; }
    });

    // ================================================================
    // GESTIÓN DE BANNERS (SLIDES) - 10 SLIDES CONFIGURABLES
    // ================================================================

    function renderBannerSlide(data, index) {
        const b = data || { image: '', title: '', subtitle: '', button_text: 'Ver más', button_link: '#' };
        const isMain = index < 4;
        const badgeClass = isMain ? 'background:#3b82f6;color:white;' : 'background:#e2e8f0;color:#475569;';
        const badgeText = isMain ? 'Slide Principal ' + (index+1) : 'Banner Extra ' + (index+1);
        
        return `
            <div class="slide-block" data-index="${index}" style="padding:20px;border:1px solid #e2e8f0;border-radius:16px;background:white;position:relative;">
                <div style="position:absolute;top:12px;left:12px;padding:4px 10px;border-radius:6px;font-size:10px;font-weight:800;text-transform:uppercase;${badgeClass}">
                    ${badgeText}
                </div>
                <div style="margin-top:32px;">
                    <div style="display:flex;align-items:center;gap:16px;padding:16px;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;margin-bottom:16px;">
                        <div style="width:96px;height:48px;background:#e2e8f0;border-radius:8px;overflow:hidden;border:1px solid #cbd5e1;flex-shrink:0;">
                            <img src="${fixUrl(b.image)}" id="banner-preview-${index}" style="width:100%;height:100%;object-fit:cover;${b.image ? '' : 'display:none;'}">
                        </div>
                        <div style="flex:1;">
                            <label style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;display:block;margin-bottom:4px;">Imagen (Archivo)</label>
                            <input type="file" class="banner-file" data-index="${index}" accept="image/*" style="font-size:12px;">
                            <input type="hidden" class="banner-img-url" value="${b.image}">
                        </div>
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;display:block;margin-bottom:4px;">Título Principal</label>
                        <input type="text" class="banner-title" value="${b.title || ''}" placeholder="Título del banner" style="width:100%;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;background:white;color:#1e293b;outline:none;">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;display:block;margin-bottom:4px;">Subtítulo</label>
                        <input type="text" class="banner-sub" value="${b.subtitle || ''}" placeholder="Descripción corta" style="width:100%;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;background:white;color:#1e293b;outline:none;">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div>
                            <label style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;display:block;margin-bottom:4px;">Texto Botón</label>
                            <input type="text" class="banner-btn-text" value="${b.button_text || 'Ver más'}" placeholder="Texto del botón" style="width:100%;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;background:white;color:#1e293b;outline:none;">
                        </div>
                        <div>
                            <label style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;display:block;margin-bottom:4px;">Link Botón</label>
                            <input type="text" class="banner-btn-link" value="${b.button_link || '#'}" placeholder="# o URL" style="width:100%;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;background:white;color:#1e293b;outline:none;">
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    async function loadBannersConfig() {
        try {
            const container = document.getElementById('banners-container');
            if (!container) return;
            
            const res = await API.get('/settings/get.php?key=home_banners');
            container.innerHTML = '';
            
            let banners = [];
            if (res.success && Array.isArray(res.data) && res.data.length > 0) {
                banners = res.data;
            }
            
            while (banners.length < 10) {
                banners.push({ image: '', title: '', subtitle: '', button_text: 'Ver más', button_link: '#' });
            }
            
            banners.forEach((b, i) => {
                container.insertAdjacentHTML('beforeend', renderBannerSlide(b, i));
                
                const input = container.querySelector(`.banner-file[data-index="${i}"]`);
                if (input) {
                    input.addEventListener('change', function(e) {
                        const file = e.target.files[0];
                        if (file) {
                            const reader = new FileReader();
                            reader.onload = (ev) => {
                                const img = document.getElementById(`banner-preview-${i}`);
                                if (img) {
                                    img.src = ev.target.result;
                                    img.style.display = 'block';
                                }
                            };
                            reader.readAsDataURL(file);
                        }
                    });
                }
            });
        } catch (e) { 
            console.error('Error loading banners', e); 
            const container = document.getElementById('banners-container');
            if (container) {
                container.innerHTML = '';
                const banners = [];
                for (let i = 0; i < 10; i++) {
                    banners.push({ image: '', title: '', subtitle: '', button_text: 'Ver más', button_link: '#' });
                }
                banners.forEach((b, i) => {
                    container.insertAdjacentHTML('beforeend', renderBannerSlide(b, i));
                });
            }
        }
    }

    document.getElementById('banners-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = e.target.querySelector('button[type="submit"]');
        btn.disabled = true; 
        btn.textContent = 'Guardando Portadas…';

        const formData = new FormData();
        document.querySelectorAll('.slide-block').forEach((card, i) => {
            formData.append(`banner_${i}_title`, card.querySelector('.banner-title').value);
            formData.append(`banner_${i}_subtitle`, card.querySelector('.banner-sub').value);
            formData.append(`banner_${i}_btn_text`, card.querySelector('.banner-btn-text').value);
            formData.append(`banner_${i}_btn_link`, card.querySelector('.banner-btn-link').value);
            formData.append(`banner_${i}_img_url`, card.querySelector('.banner-img-url').value);
            
            const file = card.querySelector('.banner-file').files[0];
            if (file) formData.append(`banner_${i}_file`, file);
        });

        try {
            const token = localStorage.getItem('misrifas_token');
            const res = await fetch(BASE_PATH + '/api/admin/banners/update.php', {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + token },
                body: formData
            }).then(r => r.json());

            if (res.success) {
                Utils.showNotification('¡Portadas actualizadas! ✅', 'success');
                loadBannersConfig();
            } else { 
                Utils.showNotification(res.message || 'Error al guardar', 'error'); 
            }
        } catch (err) { 
            console.error(err);
            Utils.showNotification('Error interno al guardar banners', 'error'); 
        }
        finally { 
            btn.disabled = false; 
            btn.textContent = 'Guardar Configuración de Portada'; 
        }
    });

    // --- Campañas de Email ---
    async function loadCampaigns() {
        try {
            const res = await API.get('/admin/campaigns/list.php');
            if (res.success) {
                const html = res.data.map(c => `
                    <tr>
                        <td>${new Date(c.created_at).toLocaleString()}</td>
                        <td class="font-bold">${c.subject}</td>
                        <td>${c.total_recipients}</td>
                        <td class="text-green-600 font-bold">${c.sent_count}</td>
                        <td class="text-red-500 font-bold">${c.error_count}</td>
                        <td><span class="badge badge--${c.status}">${c.status.toUpperCase()}</span></td>
                    </tr>
                `).join('');
                if (html) document.getElementById('campaigns-table').innerHTML = html;
            }
        } catch (e) { console.error(e); }
    }

    document.getElementById('campaign-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('btn-send-campaign');
        btn.disabled = true; btn.textContent = 'Encolando…';
        try {
            const res = await API.post('/admin/campaigns/create.php', {
                subject: document.getElementById('c-subject').value,
                body: document.getElementById('c-body').value,
                segment: document.getElementById('c-segment').value
            });
            if (res.success) {
                alert('¡Campaña iniciada con éxito! Los correos se enviarán en segundo plano.');
                loadCampaigns();
                e.target.reset();
            } else { alert('Error: ' + res.message); }
        } catch (err) { alert('Error al crear campaña.'); }
        finally { btn.disabled = false; btn.textContent = 'Enviar Campaña'; }
    });

    function viewRaffle(id) { window.location.href = BASE_PATH + '/public/raffle.php?id=' + id; }


    let colombiaData = [];

    async function loadGeographyData() {
        try {
            const res = await fetch(BASE_PATH + '/public/assets/data/colombia.json');
            colombiaData = await res.json();
            
            const deptSelect = document.getElementById('raffle-department');
            if (deptSelect) {
                deptSelect.innerHTML = '<option value="">Seleccionar</option>' + 
                    colombiaData.map(d => `<option value="${d.departamento}">${d.departamento}</option>`).join('');
            }
        } catch (e) { console.error('Error loading geography', e); }
    }

    function loadCitiesForCreate() {
        const deptSelect = document.getElementById('raffle-department');
        const citySelect = document.getElementById('raffle-city');
        const dept = colombiaData.find(d => d.departamento === deptSelect.value);
        
        if (dept && citySelect) {
            citySelect.innerHTML = '<option value="">Seleccionar</option>' + 
                dept.ciudades.map(c => `<option value="${c}">${c}</option>`).join('');
        } else if (citySelect) {
            citySelect.innerHTML = '<option value="">Seleccionar departamento primero</option>';
        }
    }


    document.addEventListener('DOMContentLoaded', function() {
        var token = localStorage.getItem('misrifas_token');
        if (!token) {
            window.location.href = BASE_PATH + '/public/vendor/index.php?auth=login';
            return;
        }

        var user = JSON.parse(localStorage.getItem('misrifas_user') || '{}');

        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebar = document.querySelector('.sidebar');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        if (sidebarToggle && sidebar) {
            function toggleSidebar() {
                sidebar.classList.toggle('sidebar--active');
                if (sidebarOverlay) sidebarOverlay.style.display = sidebar.classList.contains('sidebar--active') ? 'block' : 'none';
            }
            sidebarToggle.addEventListener('click', toggleSidebar);
            if (sidebarOverlay) sidebarOverlay.addEventListener('click', toggleSidebar);
        }
        
        // Ocultar secciones exclusivas de super_admin (comisiones, campaigns, banners, gestion-rifas)
        // Solo super_admin puede ver estas secciones - vendedor NO las ve
        var userRole = user.role || 'unknown';
        
        if (userRole !== 'super_admin') {
            // Ocultar usando style.display
            var navComisiones = document.getElementById('nav-comisiones');
            var navCampaigns = document.getElementById('nav-campaigns');
            var navBanners = document.getElementById('nav-banners');
            var navGestionRifas = document.getElementById('nav-gestion-rifas');
            
            if (navComisiones) navComisiones.style.display = 'none';
            if (navCampaigns) navCampaigns.style.display = 'none';
            if (navBanners) navBanners.style.display = 'none';
            if (navGestionRifas) navGestionRifas.style.display = 'none';
            var navUsuarios = document.getElementById('nav-usuarios');
            if (navUsuarios) navUsuarios.style.display = 'none';

            // La API (api/admin/settings.php) solo aplica cambios de
            // system_settings para super_admin; mostrarle este formulario a
            // un vendedor lo dejaba "guardar" cambios que nunca se aplicaban.
            var platformSettings = document.getElementById('section-platform-settings');
            if (platformSettings) platformSettings.style.display = 'none';
        }
        var userName = user.full_name || user.name || user.email || 'Usuario';
        if (document.getElementById('user-name')) {
            document.getElementById('user-name').textContent = userName;
        }

        // Autocompletar WhatsApp y Responsable con datos del usuario
        if (user.phone) {
            document.getElementById('whatsapp-contact').value = user.phone;
        }
        if (user.full_name) {
            document.getElementById('responsible-person').value = user.full_name;
        }

        // Also verify token is valid by fetching user data
        fetch(BASE_PATH + '/api/auth/me.php', {
            headers: { 'Authorization': 'Bearer ' + token }
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success && data.data) {
                var freshUser = data.data;
                var freshName = freshUser.full_name || freshUser.name || freshUser.email || 'Usuario';
                document.getElementById('user-name').textContent = freshName;
                localStorage.setItem('misrifas_user', JSON.stringify(freshUser));

                // Re-verificar rol por si cambió
                if (freshUser.role !== 'super_admin') {
                    document.getElementById('nav-comisiones').style.display = 'none';
                    document.getElementById('nav-campaigns').style.display = 'none';
                    document.getElementById('nav-banners').style.display = 'none';
                    document.getElementById('nav-gestion-rifas').style.display = 'none';
                    document.getElementById('nav-usuarios').style.display = 'none';
                }
            }
        })
        .catch(function(e) { console.log('Could not refresh user:', e); });

        loadDashboard();
        loadLotteries();
        loadSettings();
        loadGeographyData();

        // Deep-link: los nav-items ya escriben #seccion en la URL al hacer
        // click; restaurarla al cargar para que el enlace sea compartible.
        var initialSection = window.location.hash.replace('#', '');
        if (initialSection && initialSection !== 'dashboard' && document.getElementById('section-' + initialSection)) {
            switchTo(initialSection);
        }
    });
    </script>
    <!-- ============ Navegación móvil: tab bar inferior + FAB ============ -->
    <style>
        #vendor-fab, #vendor-tabbar { display: none; }
        @media (max-width: 768px) {
            .admin-main { padding-bottom: 78px; }
            #vendor-tabbar {
                display: flex; position: fixed; left: 0; right: 0; bottom: 0; z-index: 90;
                background: #0f172a; border-top: 1px solid #1e293b;
                padding: 6px 4px calc(6px + env(safe-area-inset-bottom, 0px));
                box-shadow: 0 -6px 20px rgba(0,0,0,.25);
            }
            .vtab {
                flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
                gap: 3px; background: none; border: none; cursor: pointer; padding: 6px 2px;
                color: #94a3b8; font-size: 11px; font-weight: 600; border-radius: 12px;
            }
            .vtab svg { width: 22px; height: 22px; }
            .vtab--on { color: #f59e0b; }
            #vendor-fab {
                display: flex; align-items: center; justify-content: center;
                position: fixed; right: 18px; bottom: 84px; z-index: 91;
                width: 58px; height: 58px; border-radius: 50%; border: none; cursor: pointer;
                background: linear-gradient(135deg, #f59e0b, #d97706); color: #1c1305;
                box-shadow: 0 10px 25px rgba(245,158,11,.5);
            }
            #vendor-fab:active { transform: scale(.94); }
            #vendor-fab svg { width: 28px; height: 28px; }
        }
    </style>
    <button id="vendor-fab" type="button" aria-label="Crear rifa" onclick="switchTo('crear')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
    </button>
    <nav id="vendor-tabbar" aria-label="Navegación">
        <button class="vtab" data-tab="dashboard" onclick="switchTo('dashboard')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
            <span>Panel</span>
        </button>
        <button class="vtab" data-tab="pagos" onclick="switchTo('pagos')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
            <span>Pagos</span>
        </button>
        <button class="vtab" data-tab="boletas-compradas" onclick="switchTo('boletas-compradas')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v1a2 2 0 0 0 0 4v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1a2 2 0 0 0 0-4Z"/><path d="M13 5v14" stroke-dasharray="2 3"/></svg>
            <span>Boletas</span>
        </button>
        <button class="vtab" data-tab="mi-perfil" onclick="switchTo('mi-perfil')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg>
            <span>Perfil</span>
        </button>
    </nav>
    <script>
        window.syncVendorTab = function (section) {
            document.querySelectorAll('#vendor-tabbar .vtab').forEach(function (t) {
                t.classList.toggle('vtab--on', t.getAttribute('data-tab') === section);
            });
        };
    </script>
</body>
</html>
<?php endif; ?>

