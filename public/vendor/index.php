<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/brand.php';
$page_title = "Panel de Administración - " . plataforma('nombre');
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
        /* Avisos clave del formulario de crear rifa: resaltados para que no
           pasen desapercibidos (calendario de la lotería, boletos a generar,
           modo de ganar). Vacíos no ocupan espacio. */
        .hint-resaltado{display:block;background:#fef3c7;color:#92400e;border:1px solid #fcd34d;border-radius:8px;padding:6px 10px;font-size:12.5px;font-weight:600;margin-top:6px;line-height:1.45;}
        .hint-resaltado:empty{display:none;}
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
        /* El sidebar es fixed y el menú creció: SIN overflow-y los items de
           abajo (Comisiones, Configuración, Mi Perfil…) quedaban INALCANZABLES
           en pantallas bajas — parecía que "no existían". */
        .sidebar-nav { flex: 1; padding: 12px 0; overflow-y: auto; min-height: 0; scrollbar-width: thin; scrollbar-color: #475569 transparent; }
        .sidebar-nav::-webkit-scrollbar { width: 5px; }
        .sidebar-nav::-webkit-scrollbar-thumb { background: #475569; border-radius: 99px; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 20px; padding-left: 17px; border-left: 3px solid transparent; color: #94a3b8; text-decoration: none; transition: background-color 0.2s, color 0.2s, border-color 0.2s; cursor: pointer; }
        .nav-item:hover { background: #334155; color: white; }
        .nav-item--active { background: rgba(245, 158, 11, 0.12); color: #fbbf24; border-left-color: #f59e0b; }
        .nav-icon { width: 20px; height: 20px; flex-shrink: 0; }
        .nav-text { font-size: 14px; font-weight: 500; }
        .nav-group { padding: 16px 20px 6px; font-size: 10px; font-weight: 800; letter-spacing: 1.2px; color: #64748b; text-transform: uppercase; }
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
                <h1 class="text-2xl font-bold mt-4" style="color:#111827"><?= plataforma_e() ?></h1>
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

                if (user.verified === false) {
                    // Cuenta sin verificar: pasa por la pantalla OTP.
                    setTimeout(() => window.location.href = BASE_PATH + '/public/verificar.php', 500);
                } else if (user.role === 'buyer') {
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
                // Guardar la sesión recién creada e ir directo a la
                // verificación OTP (WhatsApp o correo).
                localStorage.setItem('misrifas_token', result.data.token);
                localStorage.setItem('misrifas_user', JSON.stringify(result.data.user));
                showAuthNotification('¡Registro exitoso! Verifica tu cuenta para activarla.', 'success');
                setTimeout(() => window.location.href = BASE_PATH + '/public/verificar.php', 900);
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
            const res = await fetch(BASE_PATH + '/public/assets/data/colombia.json?v=dc1', { cache: 'no-cache' });
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
<?php $tabActive = 'cuenta'; include __DIR__ . '/../partials/tabbar.php'; ?>
<?php else: ?>
    <div class="admin-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <svg class="logo__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v1a2 2 0 0 0 0 4v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1a2 2 0 0 0 0-4Z"/><path d="M13 5v14" stroke-dasharray="2 3"/></svg>
                    <span class="logo__text"><?= plataforma_e() ?></span>
                </div>
            </div>
            <nav class="sidebar-nav">
                <a href="#dashboard" class="nav-item nav-item--active" data-section="dashboard" onclick="switchTo('dashboard')">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 15l4-5 3 3 5-7"/></svg>
                    <span class="nav-text">Dashboard</span>
                </a>
                <div class="nav-group">Operación diaria</div>
                <a href="#pagos" class="nav-item" data-section="pagos" onclick="switchTo('pagos')">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="3"/></svg>
                    <span class="nav-text">Pagos Recibidos</span>
                </a>
                <a href="#mis-rifas" class="nav-item" data-section="mis-rifas" onclick="switchTo('mis-rifas')">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3h11l-1 15a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 3Zm11 4h2.5a2 2 0 0 1 2 2.2l-.4 4A2 2 0 0 1 18.1 15H16"/></svg>
                    <span class="nav-text">Mis Rifas</span>
                </a>
                <a href="#crear" class="nav-item" data-section="crear" onclick="switchTo('crear')">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></svg>
                    <span class="nav-text">Crear Rifa</span>
                </a>
                <a href="#boletas-compradas" class="nav-item" data-section="boletas-compradas" onclick="switchTo('boletas-compradas')">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v1a2 2 0 0 0 0 4v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1a2 2 0 0 0 0-4Z"/><path d="M9 8l6 8M15 8l-6 8" stroke-dasharray="1 2.5"/></svg>
                    <span class="nav-text">Boletas Compradas</span>
                </a>
                <div class="nav-group" id="nav-group-sorteos">Sorteos y control</div>
                <a href="#gestion-rifas" class="nav-item" data-section="gestion-rifas" id="nav-gestion-rifas" onclick="switchTo('gestion-rifas')">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 3h6v3H9zM8 10h8M8 14h8M8 18h5"/></svg>
                    <span class="nav-text">Sorteos y Resultados</span>
                </a>
                <a href="#usuarios" class="nav-item" data-section="usuarios" id="nav-usuarios" onclick="switchTo('usuarios')">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span class="nav-text">Usuarios</span>
                </a>
                <!-- WhatsApp IA: SOLO super_admin. Oculto por defecto (fail-closed). -->
                <a href="<?= BASE_PATH ?>/public/admin/whatsapp/dashboard.php" class="nav-item" id="nav-whatsapp" style="display:none">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5Z"/></svg>
                    <span class="nav-text">WhatsApp IA</span>
                </a>
                <div class="nav-group">Crecimiento</div>
                <a href="#tapazo" class="nav-item" data-section="tapazo" onclick="switchTo('tapazo')">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3h11l-1 15a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 3Zm11 4h2.5a2 2 0 0 1 2 2.2l-.4 4A2 2 0 0 1 18.1 15H16"/></svg>
                    <span class="nav-text">El Tapazo</span>
                </a>
                <a href="#email-campaigns" class="nav-item" data-section="email-campaigns" id="nav-campaigns" onclick="switchTo('email-campaigns')">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/></svg>
                    <span class="nav-text">Campañas de Email</span>
                </a>
                <a href="#banners" class="nav-item" data-section="banners" id="nav-banners" onclick="switchTo('banners')">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="14" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="m21 15-5-5-9 9"/></svg>
                    <span class="nav-text">Gestión de Portada</span>
                </a>
                <div class="nav-group">Plataforma</div>
                <a href="#comisiones" class="nav-item" data-section="comisiones" id="nav-comisiones" onclick="switchTo('comisiones')">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                    <span class="nav-text">Comisiones</span>
                </a>
                <a href="#configuracion" class="nav-item" data-section="configuracion" onclick="switchTo('configuracion')">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/></svg>
                    <span class="nav-text">Configuración Generales</span>
                </a>
                <a href="#mi-perfil" class="nav-item" data-section="mi-perfil" onclick="switchTo('mi-perfil')">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg>
                    <span class="nav-text">Mi Perfil</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <a href="<?= BASE_PATH ?>/public/index.php" class="logout-btn" style="text-decoration:none;margin-bottom:6px;background:#334155;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l9-8 9 8"/><path d="M5 9v11a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9"/></svg>
                    <span>Volver al sitio</span>
                </a>
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
                        <button type="button" role="menuitem" onclick="window.location.href = BASE_PATH + '/public/index.php';">🌐 Volver al sitio</button>
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
                                    <small id="opportunities-hint" class="hint-resaltado"></small>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="winning-mode">Modo de Ganar *</label>
                                    <select id="winning-mode" name="winning_mode" required>
                                    </select>
                                    <small id="winning-mode-hint" class="hint-resaltado"></small>
                                </div>
                                <div class="form-group">
                                    <label for="lottery-id">Lotería *</label>
                                    <select id="lottery-id" name="lottery_id" required>
                                        <option value="">Seleccionar lotería…</option>
                                    </select>
                                    <small id="lottery-day-hint" class="hint-resaltado"></small>
                                </div>
                                <div class="form-group">
                                    <label for="draw-date">Fecha del Sorteo *</label>
                                    <input type="date" id="draw-date" name="draw_date" required>
                                    <small style="color:#64748b;font-size:12px;margin-top:4px;display:block;">La hora se asigna automáticamente según la lotería seleccionada.</small>
                                </div>
                                <div class="form-group">
                                    <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;font-weight:normal;">
                                        <input type="checkbox" id="auto-notify" checked style="margin-top:3px;width:16px;height:16px;flex-shrink:0;">
                                        <span style="font-size:13px;color:#92400e;background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;padding:6px 10px;display:block;line-height:1.45;">
                                            <strong style="color:#78350f;">Verificar el resultado y notificar automáticamente</strong><br>
                                            El día del sorteo, el sistema consulta el número ganador de la lotería y le avisa a cada participante de ESTA rifa por correo (y WhatsApp si lo vinculas): felicita al ganador y agradece a los demás. Si lo desactivas, tú te encargas de avisarles.
                                        </span>
                                    </label>
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

                <div id="section-mis-rifas" class="admin-section hidden">
                    <style>
                        .mr-card { background:#fff; border:1px solid #eef2f7; border-radius:16px; overflow:hidden; box-shadow:0 1px 2px rgba(15,23,42,.06); display:flex; flex-direction:column; transition:box-shadow .15s ease, transform .15s ease; }
                        .mr-card:hover { box-shadow:0 8px 24px rgba(15,23,42,.12); transform:translateY(-2px); }
                        .mr-media { position:relative; aspect-ratio:16/9; background:linear-gradient(135deg,#0f172a,#334155); display:flex; align-items:center; justify-content:center; font-size:36px; }
                        .mr-media img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
                        .mr-badge { position:absolute; top:10px; left:10px; z-index:2; }
                        .mr-kebab { position:absolute; top:8px; right:8px; z-index:2; width:34px; height:34px; border-radius:50%; background:rgba(255,255,255,.94); border:none; cursor:pointer; font-size:18px; font-weight:700; line-height:1; color:#0f172a; box-shadow:0 2px 8px rgba(0,0,0,.22); }
                        .mr-kebab:active { transform:scale(.92); }
                        .mr-body { padding:12px 14px 14px; display:flex; flex-direction:column; gap:9px; flex:1; }
                        .mr-name { font-weight:800; font-size:15px; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
                        .mr-place { font-size:12px; color:#94a3b8; margin-top:-5px; }
                        .mr-row { display:flex; align-items:center; justify-content:space-between; gap:8px; font-size:13px; color:#475569; }
                        .mr-chip { font-size:11.5px; font-weight:700; padding:3px 9px; border-radius:99px; white-space:nowrap; }
                        .mr-chip--soon { background:#fef3c7; color:#92400e; }
                        .mr-chip--ok { background:#e0f2fe; color:#075985; }
                        .mr-chip--past { background:#f1f5f9; color:#64748b; }
                        .mr-progress { height:8px; border-radius:99px; background:#e2e8f0; overflow:hidden; }
                        .mr-progress > div { height:100%; border-radius:99px; background:linear-gradient(90deg,#f59e0b,#d97706); }
                        .mr-progress > div.mr-full { background:linear-gradient(90deg,#10b981,#059669); }
                        .mr-meta { display:flex; justify-content:space-between; align-items:baseline; font-size:12px; color:#64748b; }
                        .mr-meta strong { color:#0f172a; }
                        .mr-revenue { margin-top:auto; padding-top:9px; border-top:1px dashed #e2e8f0; display:flex; justify-content:space-between; font-size:12.5px; color:#64748b; }
                        .mr-revenue b { color:#059669; font-size:13.5px; }
                    </style>
                    <div class="section-card">
                        <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                            <h2 class="text-lg font-bold">Mis Rifas</h2>
                            <button class="btn btn--primary btn--sm" onclick="switchTo('crear')">+ Crear rifa</button>
                        </div>
                        <div id="my-raffles-loading" class="text-center text-gray-400 py-10">Cargando tus rifas…</div>
                        <div id="my-raffles-empty" class="text-center py-10 hidden">
                            <div style="font-size:44px;margin-bottom:8px;">🎟️</div>
                            <p class="text-gray-500 mb-4">Aún no has creado rifas.</p>
                            <button class="btn btn--primary" onclick="switchTo('crear')">Crear mi primera rifa</button>
                        </div>
                        <div id="my-raffles-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4"></div>
                    </div>
                </div>

                <div id="section-pagos" class="admin-section hidden">
                    <div class="section-card">
                        <div class="section-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                            <h2>Pagos recibidos</h2>
                            <button type="button" id="approve-all-btn" class="btn btn--sm hidden" style="background:#10b981;color:#fff;" onclick="approveAllPayments()">✅ Confirmar todos</button>
                        </div>
                        <div class="flex items-center gap-2 mb-4" style="flex-wrap:wrap;">
                            <input type="text" id="pagos-buscar" class="px-4 py-2 border rounded-lg" placeholder="Buscar comprador, boleto, celular o rifa…" style="max-width:290px;" oninput="pagosBuscarDebounce()">
                            <div id="pagos-filtros" class="flex gap-1" style="flex-wrap:wrap;">
                                <button type="button" class="btn btn--sm pagos-filtro" data-status="pending">⏳ Pendientes</button>
                                <button type="button" class="btn btn--sm pagos-filtro" data-status="completed">✅ Aprobados</button>
                                <button type="button" class="btn btn--sm pagos-filtro" data-status="failed">❌ Rechazados</button>
                                <button type="button" class="btn btn--sm pagos-filtro" data-status="all">Todos</button>
                            </div>
                        </div>
                        <div id="wa-pagos-banner" class="hidden" style="margin-bottom:14px;padding:10px 14px;border-radius:10px;background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.4);color:#92400e;font-size:13px;font-weight:600;">
                            📵 WhatsApp desconectado — confirma tus pagos desde aquí.
                        </div>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Rifa</th>
                                        <th>Boleto</th>
                                        <th>Comprador</th>
                                        <th>Monto exacto</th>
                                        <th>Hace</th>
                                        <th>Comprobante</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="payments-table">
                                    <tr><td colspan="8" class="text-center">Cargando…</td></tr>
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

                    <div class="section-card mb-6" id="payment-keys-card">
                        <h2 class="text-lg font-bold mb-2">💰 Cómo te pagan tus compradores</h2>
                        <p class="text-sm text-gray-500 mb-4">Tus compradores te transfieren DIRECTO (la plataforma nunca toca tu plata). Configura al menos un método para poder publicar rifas; al comprador solo se le muestran los que llenes.</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="form-group">
                                <label for="pk-nequi">Celular Nequi</label>
                                <input type="tel" id="pk-nequi" class="w-full px-4 py-2 border rounded" placeholder="3001234567" maxlength="10">
                            </div>
                            <div class="form-group">
                                <label for="pk-daviplata">Celular DaviPlata</label>
                                <input type="tel" id="pk-daviplata" class="w-full px-4 py-2 border rounded" placeholder="3001234567" maxlength="10">
                            </div>
                            <div class="form-group">
                                <label for="pk-breb">Llave Bre-B</label>
                                <input type="text" id="pk-breb" class="w-full px-4 py-2 border rounded" placeholder="@tullave, celular, cédula o correo">
                            </div>
                            <div class="form-group" style="display:flex;align-items:end;">
                                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-weight:normal;">
                                    <input type="checkbox" id="pk-cash" style="width:16px;height:16px;">
                                    <span>Acepto pagos en <strong>efectivo</strong> (los registro yo desde el panel)</span>
                                </label>
                            </div>
                        </div>
                        <button type="button" class="btn btn--primary mt-4" id="pk-save" onclick="savePaymentKeys()">Guardar llaves de cobro</button>
                    </div>

                    <div class="section-card" id="wa-link-card">
                        <h2 class="text-lg font-bold mb-2">📱 WhatsApp para notificaciones</h2>
                        <p class="text-sm text-gray-500 mb-4">Vincula tu WhatsApp escaneando un código QR. El día del sorteo, el sistema enviará desde TU número el resultado a los participantes de tu rifa (además del correo, que va siempre). Puedes desvincularlo cuando quieras.</p>
                        <div class="flex items-center gap-3 flex-wrap mb-3">
                            <span id="wa-link-estado" class="badge badge--pending">Consultando…</span>
                            <span id="wa-link-numero" class="text-sm text-gray-500"></span>
                        </div>
                        <div id="wa-link-qr" class="hidden text-center py-3">
                            <img id="wa-link-qr-img" alt="Código QR de WhatsApp" style="width:240px;height:240px;margin:0 auto;border-radius:12px;border:1px solid #e2e8f0;">
                            <p class="text-sm text-gray-500 mt-2">Abre WhatsApp → Dispositivos vinculados → Vincular dispositivo, y escanea.</p>
                        </div>
                        <div class="flex gap-2 flex-wrap">
                            <button type="button" class="btn btn--primary btn--sm" id="wa-link-btn" onclick="waLinkQr()">🔗 Vincular con código QR</button>
                            <button type="button" class="btn btn--sm hidden" id="wa-unlink-btn" style="background:#ef4444;color:#fff;" onclick="waUnlink()">Desvincular</button>
                        </div>
                        <p id="wa-link-msg" class="text-sm mt-3" style="color:#94a3b8;"></p>
                    </div>

                    <!-- La configuración técnica del canal (URL de EvolutionAPI, API key,
                         nombre de instancia) es 100% gestionada por la plataforma: viene
                         por defecto del .env y la instancia se auto-genera con datos
                         únicos del organizador. Aquí solo existe el QR de arriba. -->
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

                    <!-- Scraper de resultados — 100% administrable (solo super_admin) -->
                    <div class="section-card" id="scraper-card">
                        <div class="section-header">
                            <div>
                                <h2 class="text-xl font-bold">🎰 Loter&iacute;as y scraper de resultados</h2>
                                <p class="text-sm text-gray-500 mt-1">El calendario (d&iacute;a y hora de cada sorteo) manda sobre las rifas nuevas y las reprogramaciones; las rifas ya creadas conservan su fecha. El scraper lee los n&uacute;meros ganadores de colombia.com y, si falla, NUNCA inventa un n&uacute;mero: el sorteo queda pendiente (arriba) y se reintenta o lo cargas manual.</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 mb-4" style="flex-wrap:wrap;">
                            <label class="flex items-center gap-2 text-sm font-medium" style="cursor:pointer;">
                                <input type="checkbox" id="scraper-enabled"> Scraper encendido
                            </label>
                            <span id="scraper-last-run" class="text-xs text-gray-500"></span>
                            <button type="button" id="scraper-run" class="btn h-11 px-4">▶ Ejecutar ahora</button>
                            <button type="button" id="scraper-save" class="btn btn--primary h-11 px-4">Guardar configuraci&oacute;n</button>
                        </div>
                        <div style="overflow-x:auto;">
                            <table class="w-full text-sm">
                                <thead><tr class="text-gray-500" style="text-align:left;">
                                    <th style="padding:8px 12px 8px 0;">Loter&iacute;a</th>
                                    <th style="padding:8px 12px 8px 0;">D&iacute;a del sorteo</th>
                                    <th style="padding:8px 12px 8px 0;">Hora</th>
                                    <th style="padding:8px 12px 8px 0;">Activa</th>
                                    <th style="padding:8px 12px 8px 0;">Fuente autom&aacute;tica</th>
                                    <th style="padding:8px 12px 8px 0;">Fuente propia (opcional)</th>
                                    <th style="padding:8px 0;">Prueba en vivo</th>
                                </tr></thead>
                                <tbody id="scraper-lot-rows"></tbody>
                            </table>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">La fuente es el tramo final de la URL en colombia.com/loterias/<b>&lt;fuente&gt;</b>. D&eacute;jala vac&iacute;a para usar la autom&aacute;tica. Desactivar una loter&iacute;a la oculta al crear rifas nuevas (las existentes no se tocan).</p>
                        <div class="flex items-center gap-2 mt-4" style="flex-wrap:wrap;border-top:1px solid #e5e7eb;padding-top:12px;">
                            <span class="text-sm font-medium">Nueva loter&iacute;a:</span>
                            <input type="text" id="scraper-new-name" class="px-4 py-2 border rounded-lg" placeholder="Nombre (ej: Loter&iacute;a del Caribe)" style="max-width:240px;">
                            <select id="scraper-new-day" class="px-4 py-2 border rounded-lg"></select>
                            <input type="time" id="scraper-new-time" class="px-4 py-2 border rounded-lg" value="22:30">
                            <button type="button" id="scraper-new-btn" class="btn h-11 px-4">＋ Crear</button>
                        </div>
                        <div id="scraper-recientes" class="text-xs text-gray-500 mt-4"></div>
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
                                    <label>Cifras</label>
                                    <select id="edit-digits" class="w-full px-4 py-2 border rounded-lg" onchange="editStructureChanged()">
                                        <option value="2">2 cifras (00-99)</option>
                                        <option value="3">3 cifras (000-999)</option>
                                        <option value="4">4 cifras (0000-9999)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Oportunidades por boleto</label>
                                    <select id="edit-opportunities" class="w-full px-4 py-2 border rounded-lg" onchange="editStructureChanged()">
                                        <option value="1">1</option><option value="2">2</option>
                                        <option value="4">4</option><option value="5">5</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Modo de Ganar</label>
                                    <select id="edit-winning-mode" class="w-full px-4 py-2 border rounded-lg"></select>
                                </div>
                                <div class="form-group">
                                    <label>Departamento</label>
                                    <select id="edit-department" class="w-full px-4 py-2 border rounded-lg" onchange="editLoadCities()"></select>
                                </div>
                                <div class="form-group">
                                    <label>Ciudad</label>
                                    <select id="edit-city" class="w-full px-4 py-2 border rounded-lg"></select>
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
                            <p id="edit-structure-hint" class="hint-resaltado"></p>
                            <div class="form-group">
                                <label>Fotos de la rifa</label>
                                <div id="edit-images-grid" class="grid grid-cols-4 gap-2 mb-2"></div>
                                <input type="file" id="edit-image-file" accept="image/*" multiple style="display:none;">
                                <button type="button" class="btn px-4" onclick="document.getElementById('edit-image-file').click()">📷 Agregar fotos</button>
                                <span id="edit-image-status" class="text-xs text-gray-500 ml-2"></span>
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
                                    <h3 class="font-bold text-lg">Cobro de la Plataforma</h3>
                                    <p class="text-sm text-slate-500">Activa o desactiva el cobro por las rifas creadas (comisión o tarifa por talonario)</p>
                                </div>
                                <label class="toggle-label" style="cursor:pointer;">
                                    <input type="checkbox" id="commission-enabled" onchange="toggleCommissionUI()">
                                    <span class="toggle-slider"></span>
                                    <span class="font-bold text-sm" id="commission-status-text">Desactivado</span>
                                </label>
                            </div>
                            <div id="commission-settings" class="pt-4 border-t border-slate-200">
                                <div class="mb-5">
                                    <label class="text-sm font-bold text-slate-600 block mb-2">Modalidad de cobro</label>
                                    <div class="flex gap-6 flex-wrap">
                                        <label class="flex items-center gap-2" style="cursor:pointer;">
                                            <input type="radio" name="billing-mode" id="billing-mode-commission" value="commission" onchange="toggleBillingModeUI()">
                                            <span class="text-sm font-medium">Comisión % sobre el valor total</span>
                                        </label>
                                        <label class="flex items-center gap-2" style="cursor:pointer;">
                                            <input type="radio" name="billing-mode" id="billing-mode-talonario" value="talonario" onchange="toggleBillingModeUI()">
                                            <span class="text-sm font-medium">Tarifa fija por talonario</span>
                                        </label>
                                    </div>
                                </div>
                                <div id="billing-commission-ui" class="flex items-center gap-6">
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
                                <div id="billing-talonario-ui" style="display:none;">
                                    <label class="text-sm font-bold text-slate-600 block mb-2" for="talonario-fee">Tarifa por talonario (COP)</label>
                                    <input type="number" id="talonario-fee" min="0" step="500" value="0" class="w-40 px-3 py-2 border border-slate-300 rounded-lg text-center font-bold text-lg">
                                    <p class="text-xs text-slate-400 mt-2">Un <strong>talonario</strong> es la rifa completa con su rango de números (ej. 2 cifras: 00–99). La tarifa se cobra al crear la rifa, sin importar el precio del boleto ni cuánto venda.</p>
                                </div>
                            </div>
                            <div class="mt-6">
                                <button type="button" onclick="saveCommissionSettings()" id="save-commission-btn" class="btn btn--primary px-8 h-12">
                                    Guardar Configuración de Comisiones
                                </button>
                            </div>
                            <div id="wompi-plat-box" class="mt-6" style="border-top:1px solid #e5e7eb;padding-top:16px;">
                                <h3 class="text-sm font-bold mb-1">💳 Pago autom&aacute;tico con Wompi (llaves de la PLATAFORMA)</h3>
                                <p class="text-xs text-gray-500 mb-3">Con las llaves configuradas, el organizador paga su cobro con un link Wompi y la reactivaci&oacute;n es AUTOM&Aacute;TICA al aprobarse (el webhook verifica firma y monto). "Marcar como pagada" sigue disponible como contingencia manual. Los secretos guardados no se muestran: vac&iacute;o = no cambiar.</p>
                                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;">
                                    <div class="form-group"><label class="text-xs">Llave p&uacute;blica</label>
                                        <input type="text" id="wompi-plat-public" class="w-full px-4 py-2 border rounded-lg" placeholder="pub_prod_… o pub_test_…"></div>
                                    <div class="form-group"><label class="text-xs">Secreto de integridad</label>
                                        <input type="password" id="wompi-plat-integrity" class="w-full px-4 py-2 border rounded-lg" placeholder="Vac&iacute;o = no cambiar"></div>
                                    <div class="form-group"><label class="text-xs">Secreto de eventos (webhook)</label>
                                        <input type="password" id="wompi-plat-events" class="w-full px-4 py-2 border rounded-lg" placeholder="Vac&iacute;o = no cambiar"></div>
                                    <div class="form-group"><label class="text-xs">Llave privada (opcional, conciliaci&oacute;n)</label>
                                        <input type="password" id="wompi-plat-private" class="w-full px-4 py-2 border rounded-lg" placeholder="Vac&iacute;o = no cambiar"></div>
                                </div>
                                <div class="form-group mt-2"><label class="text-xs">URL del webhook — reg&iacute;strala en Wompi &rarr; Eventos</label>
                                    <input type="text" id="wompi-plat-webhook" readonly class="w-full px-4 py-2 border rounded-lg" style="background:#f8fafc;font-size:12px;" onclick="this.select()"></div>
                                <button type="button" id="wompi-plat-save" class="btn btn--primary px-6 h-11 mt-2">Guardar llaves Wompi</button>
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

                <!-- ===== SECCIÓN TAPAZO (diseño canónico de tapazo/index.php) ===== -->
                <div id="section-tapazo" class="admin-section hidden">
                    <style>
                        .tpz-wrap { background:#0f172a; border-radius:20px; padding:22px 14px 26px; color:#f8fafc;
                                    background-image:radial-gradient(circle at 20% 10%, rgba(245,158,11,.12) 0%, transparent 40%),
                                                     radial-gradient(circle at 85% 90%, rgba(146,64,14,.12) 0%, transparent 40%); }
                        .tpz-hero { text-align:center; margin-bottom:18px; }
                        .tpz-hero h2 { font-size:26px; font-weight:900; display:inline-flex; align-items:center; gap:8px; color:#fff; }
                        .tpz-grad { background:linear-gradient(90deg,#fbbf24,#f97316); -webkit-background-clip:text; background-clip:text; -webkit-text-fill-color:transparent; color:transparent; }
                        .tpz-hero p { color:#94a3b8; font-size:13px; margin-top:2px; }
                        .tpz-card { background:rgba(30,41,59,.8); border:1px solid rgba(255,255,255,.06); border-radius:24px; padding:20px; margin:0 auto 18px; max-width:720px; }
                        .tpz-label { display:block; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.6px; color:#94a3b8; margin-bottom:6px; }
                        .tpz-in { width:100%; padding:12px 16px; border-radius:12px; background:rgba(15,23,42,.6); border:1px solid #334155; color:#fff; outline:none; font-size:14px; }
                        .tpz-in:focus { border-color:#f59e0b; }
                        .tpz-in::placeholder { color:#64748b; }
                        .tpz-field { margin-bottom:14px; }
                        .tpz-row2 { display:grid; grid-template-columns:1fr; gap:14px; margin-bottom:14px; }
                        @media (min-width:640px){ .tpz-row2 { grid-template-columns:1fr 1fr; } }
                        .tpz-btn { width:100%; padding:14px; border:none; border-radius:16px; cursor:pointer;
                                   background:linear-gradient(90deg,#f59e0b,#ea580c); color:#fff; font-weight:900; font-size:15px;
                                   text-transform:uppercase; letter-spacing:.8px; box-shadow:0 10px 24px rgba(245,158,11,.28); transition:transform .15s; }
                        .tpz-btn:active { transform:scale(.97); }
                        .tpz-btn[disabled] { opacity:.6; cursor:not-allowed; }
                        .tpz-link { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border-radius:10px;
                                    background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.12); color:#e2e8f0;
                                    font-size:12px; font-weight:700; text-decoration:none; }
                        .tpz-link:hover { background:rgba(255,255,255,.12); }
                        .tpz-upl { width:100%; height:88px; border:2px dashed #334155; border-radius:12px; display:flex; align-items:center;
                                   justify-content:center; color:#64748b; font-size:12px; cursor:pointer; overflow:hidden; }
                        .tpz-upl:hover { border-color:#f59e0b; }
                        .tpz-grid { display:grid; grid-template-columns:1fr; gap:14px; max-width:980px; margin:0 auto; }
                        @media (min-width:640px){ .tpz-grid { grid-template-columns:repeat(2,1fr); } }
                        @media (min-width:1100px){ .tpz-grid { grid-template-columns:repeat(3,1fr); } }
                        .tpz-item { position:relative; background:rgba(30,41,59,.8); border:1px solid rgba(255,255,255,.08); border-radius:18px; overflow:hidden; display:flex; flex-direction:column; }
                        .tpz-media { position:relative; aspect-ratio:16/9; background:linear-gradient(135deg,#78350f,#451a03); display:flex; align-items:center; justify-content:center; font-size:38px; }
                        .tpz-media img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
                        .tpz-kebab { position:absolute; top:8px; right:8px; z-index:2; width:34px; height:34px; border-radius:50%; background:rgba(255,255,255,.94); color:#0f172a; border:none; cursor:pointer; font-size:18px; font-weight:700; box-shadow:0 2px 8px rgba(0,0,0,.35); }
                        .tpz-kebab:active { transform:scale(.94); }
                        .tpz-chip { position:absolute; top:8px; left:8px; z-index:2; padding:3px 10px; border-radius:99px; font-size:11px; font-weight:800; }
                        .tpz-body { padding:14px 14px 16px; display:flex; flex-direction:column; gap:8px; }
                        .tpz-name { font-weight:800; font-size:15px; color:#fff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
                        .tpz-meta { display:flex; flex-wrap:wrap; gap:6px; font-size:11px; color:#94a3b8; font-weight:700; }
                        .tpz-meta span { padding:2px 8px; border-radius:99px; background:rgba(255,255,255,.06); }
                        .tpz-bar { height:6px; border-radius:99px; background:rgba(255,255,255,.1); overflow:hidden; }
                        .tpz-bar i { display:block; height:100%; background:linear-gradient(90deg,#f59e0b,#fbbf24); }
                        .tpz-bar i.tpz-full { background:#22c55e; }
                        .tpz-empty { text-align:center; color:#94a3b8; padding:36px 10px; }
                    </style>
                    <div class="tpz-wrap">
                        <div class="tpz-hero">
                            <h2><span>🍺</span><span class="tpz-grad">El Tapazo</span></h2>
                            <p>El ritual de la tapita de cerveza, ahora digital</p>
                            <div style="margin-top:10px;"><a class="tpz-link" href="<?= BASE_PATH ?>/tapazo/index.php" target="_blank" rel="noopener">🍻 Pantalla pública del Tapazo</a></div>
                        </div>

                        <div class="tpz-card">
                            <form id="tapazo-form">
                                <div class="tpz-field">
                                    <label class="tpz-label" for="tpz-titulo">Título del Tapazo *</label>
                                    <input type="text" id="tpz-titulo" required maxlength="120" class="tpz-in" placeholder="ej: Tapazo del Viernes">
                                </div>
                                <div class="tpz-field">
                                    <label class="tpz-label" for="tpz-desc">Descripción</label>
                                    <input type="text" id="tpz-desc" maxlength="255" class="tpz-in" placeholder="ej: El que saque el número más alto invita las cervezas">
                                </div>
                                <div class="tpz-row2">
                                    <div>
                                        <label class="tpz-label" for="tpz-cantidad">Jugadores *</label>
                                        <input type="number" id="tpz-cantidad" required min="2" max="50" value="6" class="tpz-in">
                                    </div>
                                    <div>
                                        <label class="tpz-label" for="tpz-valor">Valor Cupo</label>
                                        <input type="number" id="tpz-valor" min="0" step="500" class="tpz-in" placeholder="5000">
                                    </div>
                                </div>
                                <div class="tpz-field">
                                    <label class="tpz-label">Imagen (opcional)</label>
                                    <div class="tpz-row2" style="margin-bottom:0;">
                                        <label class="tpz-upl" id="tpz-imagen-prev">Click para subir imagen<input type="file" id="tpz-imagen" accept="image/*" class="sr-only" style="display:none;"></label>
                                        <input type="text" id="tpz-imagen-url" class="tpz-in" placeholder="O pega una URL de imagen">
                                    </div>
                                </div>
                                <div class="tpz-row2">
                                    <div>
                                        <label class="tpz-label" for="tpz-fecha">Fecha y Hora del Destape *</label>
                                        <input type="datetime-local" id="tpz-fecha" required class="tpz-in" style="color-scheme:dark;">
                                    </div>
                                    <div>
                                        <label class="tpz-label" for="tpz-regla">Regla del Juego *</label>
                                        <select id="tpz-regla" required class="tpz-in">
                                            <option value="alto_gana">🔼 El número más ALTO GANA</option>
                                            <option value="bajo_gana">🔽 El número más BAJO GANA</option>
                                        </select>
                                    </div>
                                </div>
                                <button type="submit" class="tpz-btn">🍺 Crear El Tapazo</button>
                            </form>
                        </div>

                        <div id="tpz-loading" class="tpz-empty">Cargando tus tapazos…</div>
                        <div id="tpz-empty" class="tpz-empty" style="display:none;">🍺 Aún no has creado tapazos.<br><span style="font-size:12px;">Crea el primero con el formulario de arriba.</span></div>
                        <div id="tpz-grid" class="tpz-grid" style="display:none;" aria-live="polite"></div>
                    </div>
                </div>

                <!-- ===== SECCIÓN CONFIGURACIÓN ===== -->
                <div id="section-configuracion" class="admin-section hidden">

                    <!-- Configuración del ORGANIZADOR (vendedor): resumen de su cuenta,
                         parámetros de la plataforma que le aplican (lectura) y accesos
                         directos. La tarjeta de plataforma de abajo es solo super_admin. -->
                    <div class="section-card hidden" id="vendor-settings-card">
                        <h2 class="text-lg font-bold mb-2 flex items-center gap-2"><svg class="w-5 h-5 text-primary shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1"/></svg>Mi configuración</h2>
                        <p class="text-sm text-gray-500 mb-5">Resumen de tu cuenta y de las reglas de la plataforma que aplican a tus rifas.</p>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:14px;">
                            <div style="padding:16px;border:1px solid #e2e8f0;border-radius:14px;min-width:0;">
                                <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:#94a3b8;letter-spacing:.5px;">👤 Cuenta</div>
                                <div id="vcfg-nombre" class="font-bold mt-1" style="word-break:break-word;">—</div>
                                <div id="vcfg-email" class="text-sm text-gray-500" style="word-break:break-word;">—</div>
                                <div id="vcfg-phone" class="text-sm text-gray-500">—</div>
                            </div>
                            <div style="padding:16px;border:1px solid #e2e8f0;border-radius:14px;min-width:0;">
                                <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:#94a3b8;letter-spacing:.5px;">💰 Costos</div>
                                <div id="vcfg-billing" class="font-bold mt-1">—</div>
                                <div class="text-sm text-gray-500">El dinero de tus compradores va directo a tus billeteras digitales <strong>Nequi o DaviPlata</strong>, o a tu <strong>llave Bre-B</strong> — la plataforma nunca lo toca.</div>
                            </div>
                            <div style="padding:16px;border:1px solid #e2e8f0;border-radius:14px;min-width:0;">
                                <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:#94a3b8;letter-spacing:.5px;">⏱️ Reservas</div>
                                <div id="vcfg-ttl" class="font-bold mt-1">—</div>
                                <div class="text-sm text-gray-500">Tiempo que un comprador tiene para reportar su pago antes de que el número se libere.</div>
                            </div>
                            <div style="padding:16px;border:1px solid #e2e8f0;border-radius:14px;min-width:0;">
                                <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:#94a3b8;letter-spacing:.5px;">📱 WhatsApp</div>
                                <div id="vcfg-wa" class="font-bold mt-1">—</div>
                                <div class="text-sm text-gray-500">La conexión es automática: solo escaneas un QR. La plataforma gestiona lo técnico.</div>
                            </div>
                        </div>
                        <div class="flex gap-2 flex-wrap mt-5">
                            <button type="button" class="btn btn--primary btn--sm" onclick="switchTo('mi-perfil')">💳 Llaves de cobro</button>
                            <button type="button" class="btn btn--sm btn--outline" onclick="switchTo('mi-perfil')">📱 Vincular WhatsApp</button>
                            <button type="button" class="btn btn--sm btn--outline" onclick="switchTo('crear')">➕ Crear rifa</button>
                            <a href="<?= BASE_PATH ?>/public/index.php" class="btn btn--sm btn--outline" style="text-decoration:none;">🌐 Volver al sitio</a>
                        </div>
                    </div>


                    <!-- Centro de COMUNICACIONES (solo super_admin): dónde se
                         configura cada canal y QUÉ mensajes envía el sistema. -->
                    <div class="section-card" id="comms-card">
                        <h2 class="text-lg font-bold mb-2 flex items-center gap-2"><svg class="w-5 h-5 text-primary shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m3 11 18-8-8 18-2-8-8-2Z"/></svg>Comunicaciones y notificaciones</h2>
                        <p class="text-sm text-gray-500 mb-4">Mapa de TODO lo que el sistema envía y dónde se configura cada canal.</p>

                        <!-- Diagnóstico EN VIVO: cada chip sale de una verificación real
                             (socket SMTP, API Evolution, binario gammu, settings BD) —
                             nunca de un texto estático que "asume" que funciona. -->
                        <div style="padding:14px;border:1px solid #e2e8f0;border-radius:12px;margin-bottom:14px;background:#f8fafc;">
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                                <div style="font-weight:800;font-size:13px;">🩺 Diagnóstico en vivo</div>
                                <button type="button" onclick="loadSystemStatus()" style="font-size:12px;font-weight:700;color:#f59e0b;background:none;border:none;cursor:pointer;">↻ Verificar de nuevo</button>
                            </div>
                            <p style="font-size:12px;color:#64748b;margin:4px 0 10px;">Nada se asume: cada estado se comprueba en este instante contra el servidor real.</p>
                            <div id="sys-status" style="display:grid;gap:8px;"><p style="font-size:12px;color:#94a3b8;">Verificando…</p></div>
                        </div>

                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;margin-bottom:18px;">
                            <div style="padding:14px;border:1px solid #e2e8f0;border-radius:12px;">
                                <div style="font-weight:800;font-size:13px;">🏆 Resultados del sorteo</div>
                                <p style="font-size:12px;color:#64748b;margin-top:4px;">Automático: 10:30pm se trae el resultado oficial y 11:00pm corre el sorteo — solo un boleto <strong>pagado</strong> gana. Se notifica a TODOS los compradores por correo y WhatsApp (cola cada 10 min). Respaldo manual: <a href="#gestion-rifas" onclick="switchTo('gestion-rifas')" style="color:#f59e0b;font-weight:700;">Sorteos y Resultados</a>.</p>
                            </div>
                            <div style="padding:14px;border:1px solid #e2e8f0;border-radius:12px;">
                                <div style="font-weight:800;font-size:13px;">📧 Correo (SMTP)</div>
                                <p style="font-size:12px;color:#64748b;margin-top:4px;">Se configura <strong>aquí abajo</strong> ⬇. Lo usan: resultados, boleta digital, OTP de registro, recordatorios y campañas.</p>
                            </div>
                            <div style="padding:14px;border:1px solid #e2e8f0;border-radius:12px;">
                                <div style="font-weight:800;font-size:13px;">📱 WhatsApp</div>
                                <p style="font-size:12px;color:#64748b;margin-top:4px;">Cada organizador vincula SU número con QR en <a href="#mi-perfil" onclick="switchTo('mi-perfil')" style="color:#f59e0b;font-weight:700;">Mi Perfil</a> (la plataforma pone servidor e instancia). La configuración avanzada del motor y la IA viven en <strong>WhatsApp IA</strong> (menú lateral).</p>
                            </div>
                            <div style="padding:14px;border:1px solid #e2e8f0;border-radius:12px;">
                                <div style="font-weight:800;font-size:13px;">🔐 OTP de registro</div>
                                <p style="font-size:12px;color:#64748b;margin-top:4px;">Dos canales: por <strong>correo</strong> (usa el SMTP de abajo) el usuario recibe el código <code>VERIFY-XXXXX</code>; por <strong>WhatsApp</strong> (OTP inverso) el usuario ENVÍA ese código al número de la plataforma configurado aquí. Sin número, el canal WhatsApp se ofrece como "no disponible" y el registro usa solo el correo.</p>
                                <div style="margin-top:8px;display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                                    <input type="text" id="otp-wa-number" placeholder="573001234567 (sin +)" style="flex:1;min-width:140px;font-size:12px;padding:6px 10px;border:1px solid #e2e8f0;border-radius:8px;">
                                    <button type="button" onclick="saveOtpNumber()" style="font-size:12px;font-weight:700;padding:6px 12px;border-radius:8px;background:#f59e0b;color:#fff;border:none;cursor:pointer;">Guardar</button>
                                </div>
                                <p style="font-size:11px;color:#94a3b8;margin-top:4px;">Número WhatsApp de la plataforma que RECIBE los códigos; debe estar vinculado al motor (QR) para poder leerlos.</p>
                            </div>
                            <div style="padding:14px;border:1px solid #e2e8f0;border-radius:12px;">
                                <div style="font-weight:800;font-size:13px;">✉️ SMS (Gammu)</div>
                                <p style="font-size:12px;color:#64748b;margin-top:4px;">Canal opcional de respaldo que exige un <strong>módem GSM físico con SIM</strong> conectado al servidor — en un VPS de nube no puede operar. Se activa instalando <code>gammu</code> y poniendo <code>SMS_ENABLED=true</code> en el <code>.env</code>. Su estado real lo dice el diagnóstico de arriba.</p>
                            </div>
                        </div>

                        <h3 class="text-md font-bold mb-2">📝 Editor de plantillas</h3>
                        <p class="text-sm text-gray-500 mb-3">Edita el texto que envía el sistema por WhatsApp y correo. Las palabras entre llaves <code>{así}</code> se reemplazan solas al enviar — toca una para insertarla. Si borras una variable crítica (como el enlace del ganador), el sistema la repone al enviar.</p>
                        <div id="tpl-editor" class="space-y-3"><p class="text-sm text-gray-400">Cargando plantillas…</p></div>

                        <details style="border:1px solid #e2e8f0;border-radius:12px;padding:10px 14px;margin-top:12px;">
                            <summary style="font-weight:700;font-size:13px;cursor:pointer;">🔒 Mensajes fijos del sistema (no editables, por seguridad)</summary>
                            <div style="font-size:12px;color:#64748b;margin-top:8px;line-height:1.6;">
                                <p><strong>📦 Entrega del premio:</strong> "El organizador reporta que TE ENTREGÓ el premio… confírmalo aquí: {enlace}" — lleva el enlace tokenizado y la foto de evidencia; editarlo podría romper la cadena de confirmación.</p>
                                <p style="margin-top:6px;"><strong>🧾 Pago por confirmar:</strong> incluye los comandos <code>SI {id}</code> / <code>NO {id} {motivo}</code> que el sistema interpreta al responder.</p>
                                <p style="margin-top:6px;"><strong>🔐 OTP:</strong> el código <code>VERIFY-XXXXX</code> tiene formato fijo para la verificación automática.</p>
                            </div>
                        </details>
                    </div>

                    <div class="section-card">
                        <h2 class="text-xl font-bold mb-4">📧 Correo del sistema (SMTP)</h2>
                        <p class="text-sm text-gray-500 mb-6">Este correo envía TODO: resultados de sorteos, boletas, OTP de registro, recordatorios y campañas.</p>
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
                                    <input type="email" id="smtp-from" class="w-full px-4 py-2 border rounded-lg" placeholder="no-reply@tudominio.com">
                                </div>
                                <div class="form-group">
                                    <label>Nombre Remitente</label>
                                    <input type="text" id="smtp-from-name" class="w-full px-4 py-2 border rounded-lg" placeholder="Nombre visible del remitente">
                                </div>
                            </div>
                            <button type="submit" class="btn btn--primary px-8 h-12">Guardar Configuración SMTP</button>
                        </form>
                    </div>

                    <!-- Configuración General (solo super_admin: la API no aplica cambios para vendedores) -->
                    <div class="section-card" id="section-platform-settings">
                        <h2 class="text-lg font-bold mb-2 flex items-center gap-2"><svg class="w-5 h-5 text-primary shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18 14 14 0 0 1 0-18Z"/></svg>Configuración General de la Plataforma</h2>
                        <p style="color:#94a3b8;font-size:13px;margin-bottom:20px;">Ajusta los par&aacute;metros globales. El nombre y el correo se usan EN TODO: t&iacute;tulos, correos, plantillas, boletas y p&aacute;ginas p&uacute;blicas — c&aacute;mbialos aqu&iacute; cuando definas la marca/dominio finales.</p>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px;">
                            <div class="form-group">
                                <label for="cfg-platform-name">Nombre de la Plataforma</label>
                                <input type="text" id="cfg-platform-name" value="<?= plataforma_e() ?>" class="w-full px-4 py-2 border rounded-lg">
                            </div>
                            <div class="form-group">
                                <label for="cfg-platform-email">Email de la Plataforma</label>
                                <input type="email" id="cfg-platform-email" value="<?= plataforma_e('email_config') ?>" placeholder="vac&iacute;o = no-reply@<?= plataforma_e('dominio') ?>" class="w-full px-4 py-2 border rounded-lg">
                            </div>
                            <div class="form-group">
                                <label for="cfg-contact-whatsapp">WhatsApp de soporte</label>
                                <input type="text" id="cfg-contact-whatsapp" value="<?= plataforma_e('whatsapp') ?>" placeholder="57300XXXXXXX (vac&iacute;o = sin bot&oacute;n de soporte)" class="w-full px-4 py-2 border rounded-lg">
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
                            <div class="form-group" style="display:flex;align-items:end;">
                                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-weight:normal;">
                                    <input type="checkbox" id="cfg-reviews-enabled" style="width:16px;height:16px;">
                                    <span>Rese&ntilde;as de compradores <strong>habilitadas</strong> (perfiles de organizador)</span>
                                </label>
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
            // FormData pasa tal cual (subidas de archivos/perfil): pasarlo por
            // JSON.stringify lo convertía en "{}" y el servidor recibía vacío.
            const body = data instanceof FormData ? data : JSON.stringify(data);
            return this.request(endpoint, { method: 'POST', body });
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

    // El SUPER ADMIN gestiona sus números en WhatsApp IA → Conexión (hasta 5
    // instancias): la tarjeta de vinculación personal se reemplaza por el
    // enlace, para no tener DOS lugares haciendo lo mismo.
    try {
        var __wu = JSON.parse(localStorage.getItem('misrifas_user') || '{}');
        if (__wu && __wu.role === 'super_admin') {
            var __wc = document.getElementById('wa-link-card');
            if (__wc) __wc.innerHTML = '<h2 class="text-lg font-bold mb-2">📱 WhatsApp de la plataforma</h2>'
                + '<p class="text-sm text-gray-500 mb-4">Como super administrador, tus números se gestionan en el módulo WhatsApp IA: instancias (hasta 5), códigos QR, webhook y motor.</p>'
                + '<a class="btn btn--primary" href="' + BASE_PATH + '/public/admin/whatsapp/conexion.php">Abrir WhatsApp IA → Conexión</a>';
        }
    } catch (e) {}

    function logout() {
        localStorage.removeItem('misrifas_token');
        localStorage.removeItem('misrifas_user');
        // Al cerrar sesión se vuelve a la página inicial, no al login.
        window.location.href = BASE_PATH + '/public/index.php';
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
                'mis-rifas': 'Mis Rifas',
                crear: 'Crear Rifa',
                comisiones: 'Comisiones',
                configuracion: 'Configuración',
                pagos: 'Pagos Recibidos',
                'mi-perfil': 'Mi Perfil',
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
        if (section === 'mis-rifas') loadMyRaffles();
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
        if (section === 'configuracion') { loadSettings(); loadEmailSettings(); loadTemplates(); loadSystemStatus(); }
        if (section === 'tapazo') loadTapazos();
        if (section === 'email-campaigns') { loadCampaigns(); loadEmailSettings(); }
    }

    // ── Cobro Wompi de la plataforma (reactivación automática) ──
    window.pagarCobroWompi = async function (raffleId) {
        try {
            const r = await API.post('/vendor/pagar-cobro.php', { raffle_id: parseInt(raffleId) });
            if (r.data && r.data.url) {
                window.open(r.data.url, '_blank', 'noopener');
                Utils.showNotification('Link de pago abierto. Al aprobarse el pago, la reactivación es automática.', 'success');
            }
        } catch (err) { Utils.showNotification(err.message || 'No se pudo generar el link de pago', 'error'); }
    };

    async function loadWompiPlatform() {
        const box = document.getElementById('wompi-plat-box');
        if (!box) return;
        try {
            const res = await API.get('/admin/settings.php');
            if (!res.success) return;
            const d = res.data || {};
            const pk = document.getElementById('wompi-plat-public');
            if (pk && d.wompi_platform_public_key) pk.value = d.wompi_platform_public_key;
            const wh = document.getElementById('wompi-plat-webhook');
            if (wh) wh.value = window.location.origin + BASE_PATH + '/api/payments/wompi-billing-webhook.php';
        } catch (e) { console.error('wompi plat', e); }
    }

    document.getElementById('wompi-plat-save')?.addEventListener('click', async () => {
        const campos = [
            ['wompi_platform_public_key', 'wompi-plat-public', false],
            ['wompi_platform_integrity_secret', 'wompi-plat-integrity', true],
            ['wompi_platform_events_secret', 'wompi-plat-events', true],
            ['wompi_platform_private_key', 'wompi-plat-private', true],
        ];
        try {
            for (const [key, id, esSecreto] of campos) {
                const val = (document.getElementById(id) || {}).value || '';
                if (esSecreto && val.trim() === '') continue; // vacío = no cambiar
                await API.post('/admin/settings/update.php', { key, value: val.trim() });
            }
            Utils.showNotification('Llaves Wompi guardadas ✅', 'success');
            ['wompi-plat-integrity', 'wompi-plat-events', 'wompi-plat-private'].forEach(id => {
                const el = document.getElementById(id); if (el) el.value = '';
            });
        } catch (err) { Utils.showNotification(err.message || 'Error al guardar llaves', 'error'); }
    });

    async function loadCommissions() {
        loadWompiPlatform();
        try {
            const response = await API.get('/admin/commissions.php');
            if (response.success) {
                const tbody = document.getElementById('commissions-table');
                const data  = response.data || [];
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-gray-500 py-6">No hay comisiones registradas</td></tr>';
                    return;
                }
                window.__commissions = data;
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
                        ? `<button class="btn btn--sm" aria-label="Acciones" title="Acciones" onclick="openCommissionSheet(${parseInt(c.raffle_id, 10)})" style="font-size:20px;line-height:1;padding:2px 10px;">⋮</button>`
                        : '<span style="color:#10b981;font-size:12px;">&#10003; Cobrada</span>';
                    return '<tr>' +
                        '<td class="font-medium">' + userEsc(c.raffle_name || '') + '</td>' +
                        '<td style="color:#94a3b8;font-size:13px;">' + userEsc(c.creator_name || 'Vendedor') + '</td>' +
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
                        
                        const statusMap = { draft: 'Borrador', active: 'Activa', blocked: 'Bloqueada', pending_reschedule: 'Por reprogramar', completed: 'Completada', cancelled: 'Cancelada' };
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
        window.__dashRaffles = raffles;
        tbody.innerHTML = raffles.map(r => {
            const statusClass = r.status || 'pending';
            return '<tr>' +
                '<td class="font-medium">' + (r.name || '') + '</td>' +
                '<td><span class="badge badge--' + statusClass + '">' + statusClass + '</span></td>' +
                '<td>' + (r.sold_tickets || 0) + '</td>' +
                '<td>' + (r.draw_date ? new Date(r.draw_date).toLocaleDateString('es-CO') : '--') + '</td>' +
                '<td><button class="btn btn--sm" aria-label="Acciones" title="Acciones" onclick="openRaffleSheet(' + r.id + ')" style="font-size:20px;line-height:1;padding:2px 10px;">⋮</button></td>' +
            '</tr>';
        }).join('');
    }

    // Sección "Mis Rifas": lista completa del vendedor en tarjetas, con menú ⋮.
    async function loadMyRaffles() {
        const loading = document.getElementById('my-raffles-loading');
        const empty = document.getElementById('my-raffles-empty');
        const grid = document.getElementById('my-raffles-grid');
        loading.classList.remove('hidden');
        empty.classList.add('hidden');
        grid.innerHTML = '';
        try {
            const res = await API.get('/vendor/list_raffles.php');
            loading.classList.add('hidden');
            const raffles = (res.success && res.data) ? res.data : [];
            if (!raffles.length) { empty.classList.remove('hidden'); return; }
            window.__myRaffles = raffles;
            const statusMap = { draft: 'Borrador', active: 'Activa', blocked: 'Bloqueada', pending_reschedule: 'Por reprogramar', completed: 'Completada', cancelled: 'Cancelada' };
            grid.innerHTML = raffles.map(r => {
                const sold = parseInt(r.sold_tickets || 0, 10);
                const total = parseInt(r.total_tickets || 0, 10);
                const pct = total > 0 ? Math.min(100, Math.round(sold * 100 / total)) : 0;
                const statusClass = r.status === 'active' ? 'active' : (r.status === 'completed' ? 'completed' : (r.status === 'cancelled' ? 'cancelled' : 'pending'));
                const place = [r.city, r.department].filter(Boolean).join(', ');
                const price = parseFloat(r.ticket_price || 0);
                const img = r.image_url ? fixUrl(r.image_url) : '';

                // Chip de tiempo al sorteo (solo rifas no terminadas)
                let chip = '';
                if (r.draw_date && r.status !== 'completed' && r.status !== 'cancelled') {
                    const days = Math.ceil((new Date(r.draw_date) - Date.now()) / 86400000);
                    if (days < 0)       chip = '<span class="mr-chip mr-chip--past">Sorteo vencido</span>';
                    else if (days === 0) chip = '<span class="mr-chip mr-chip--soon">¡Sorteo hoy!</span>';
                    else if (days === 1) chip = '<span class="mr-chip mr-chip--soon">Sorteo mañana</span>';
                    else if (days <= 7)  chip = '<span class="mr-chip mr-chip--soon">Faltan ' + days + ' días</span>';
                    else                 chip = '<span class="mr-chip mr-chip--ok">Faltan ' + days + ' días</span>';
                } else if (r.status === 'completed') {
                    chip = '<span class="mr-chip mr-chip--past">Finalizada</span>';
                }

                return '<div class="mr-card">' +
                    '<div class="mr-media">🎟️' +
                        (img ? '<img src="' + userEsc(img) + '" alt="" loading="lazy" onerror="this.remove()">' : '') +
                        '<span class="mr-badge"><span class="badge badge--' + statusClass + '">' + (statusMap[r.status] || r.status || '') + '</span></span>' +
                        '<button class="mr-kebab" aria-label="Acciones" title="Acciones" onclick="openRaffleSheet(' + parseInt(r.id, 10) + ')">⋮</button>' +
                    '</div>' +
                    '<div class="mr-body">' +
                        '<div class="mr-name">' + userEsc(r.name || 'Rifa') + '</div>' +
                        (place ? '<div class="mr-place">📍 ' + userEsc(place) + '</div>' : '') +
                        '<div class="mr-row">' +
                            '<span>' + Utils.formatPrice(price) + ' / boleta</span>' + chip +
                        '</div>' +
                        '<div>' +
                            '<div class="mr-meta" style="margin-bottom:4px;">' +
                                '<span><strong>' + sold + '</strong> de ' + total + ' vendidas</span><span>' + pct + '%</span>' +
                            '</div>' +
                            '<div class="mr-progress"><div class="' + (pct >= 100 ? 'mr-full' : '') + '" style="width:' + pct + '%;"></div></div>' +
                        '</div>' +
                        '<div class="mr-revenue">' +
                            '<span>Sorteo: ' + (r.draw_date ? new Date(r.draw_date).toLocaleDateString('es-CO') : '--') + '</span>' +
                            '<span>Recaudado <b>' + Utils.formatPrice(sold * price) + '</b></span>' +
                        '</div>' +
                    '</div>' +
                '</div>';
            }).join('');
        } catch (e) {
            loading.classList.add('hidden');
            empty.classList.remove('hidden');
            console.error('Error loading my raffles:', e);
        }
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
                window.__allRaffles = response.data;
                tbody.innerHTML = response.data.map(r => {
                    const statusClass = r.status || 'pending';
                    return '<tr>' +
                        '<td class="font-medium">' + userEsc(r.name || '') + '</td>' +
                        '<td>' + userEsc(r.city || '') + '</td>' +
                        '<td><span class="badge badge--' + statusClass + '">' + statusClass + '</span></td>' +
                        '<td>' + (r.sold_tickets || 0) + '</td>' +
                        '<td>' + Utils.formatPrice(r.ticket_price || 0) + '</td>' +
                        '<td>' + (r.draw_date ? new Date(r.draw_date).toLocaleDateString('es-CO') : '--') + '</td>' +
                        '<td><button class="btn btn--sm" aria-label="Acciones" title="Acciones" onclick="openRaffleSheet(' + parseInt(r.id, 10) + ')" style="font-size:20px;line-height:1;padding:2px 10px;">⋮</button></td>' +
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
    // Estados reales del modelo público → etiqueta y color del chip.
    const TPZ_ESTADOS = {
        creado: ['Abierto', '#22c55e'], lleno: ['Completo', '#f59e0b'],
        esperando: ['Esperando destape', '#f59e0b'], destapando: ['Destapando…', '#f97316'],
        finalizado: ['Finalizado', '#94a3b8']
    };
    async function loadTapazos() {
        const loading = document.getElementById('tpz-loading');
        const empty = document.getElementById('tpz-empty');
        const grid = document.getElementById('tpz-grid');
        loading.style.display = ''; empty.style.display = 'none'; grid.style.display = 'none';
        try {
            const res = await API.get('/tapazo/admin_list.php');
            loading.style.display = 'none';
            const data = (res.success && res.data) ? res.data : [];
            window.__tapazos = data;
            if (!data.length) { empty.style.display = ''; return; }
            grid.style.display = '';
            grid.innerHTML = data.map(t => {
                const est = TPZ_ESTADOS[t.estado] || [t.estado || '—', '#94a3b8'];
                const pct = t.total_participants ? Math.round((t.joined_count || 0) * 100 / t.total_participants) : 0;
                const fecha = t.fecha_hora_destape ? new Date(String(t.fecha_hora_destape).replace(' ', 'T')) : null;
                return '<div class="tpz-item">' +
                    '<div class="tpz-media">' +
                        (t.imagen_url ? '<img src="' + userEsc(t.imagen_url) + '" alt="" loading="lazy" onerror="this.remove()">' : '🍺') +
                        '<span class="tpz-chip" style="background:' + est[1] + '22;color:' + est[1] + ';border:1px solid ' + est[1] + '55;">' + est[0] + '</span>' +
                        '<button class="tpz-kebab" aria-label="Acciones" title="Acciones" onclick="openTapazoSheet(' + parseInt(t.id, 10) + ')">⋮</button>' +
                    '</div>' +
                    '<div class="tpz-body">' +
                        '<div class="tpz-name">' + userEsc(t.name || '') + '</div>' +
                        '<div class="tpz-meta">' +
                            '<span>👥 ' + (t.joined_count || 0) + ' / ' + t.total_participants + '</span>' +
                            '<span>' + (t.regla === 'bajo_gana' ? '🔽 Más bajo gana' : '🔼 Más alto gana') + '</span>' +
                            (t.valor_cupo > 0 ? '<span>💵 $' + parseFloat(t.valor_cupo).toLocaleString('es-CO') + '</span>' : '') +
                            (fecha ? '<span>⏰ ' + fecha.toLocaleDateString('es-CO', { day: 'numeric', month: 'short' }) + ' ' + fecha.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' }) + '</span>' : '') +
                        '</div>' +
                        '<div class="tpz-bar"><i' + (pct >= 100 ? ' class="tpz-full"' : '') + ' style="width:' + Math.min(100, pct) + '%"></i></div>' +
                    '</div>' +
                '</div>';
            }).join('');
        } catch (e) {
            loading.style.display = 'none'; empty.style.display = '';
            console.error('Error loading tapazos', e);
        }
    }

    // Subida de imagen: mismo endpoint público que usa tapazo/index.php.
    document.getElementById('tpz-imagen').addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (!file) return;
        const prev = document.getElementById('tpz-imagen-prev');
        prev.textContent = 'Subiendo imagen…';
        try {
            const fd = new FormData();
            fd.append('imagen', file);
            const r = await fetch(BASE_PATH + '/api/tapazo/upload.php', { method: 'POST', body: fd });
            const j = await r.json();
            if (j.success) {
                document.getElementById('tpz-imagen-url').value = j.data.url;
                prev.innerHTML = '<img src="' + j.data.url + '" style="width:100%;height:100%;object-fit:cover;" alt="Imagen del tapazo">';
            } else {
                prev.textContent = 'Click para subir imagen';
                Utils.showNotification(j.message || 'Error al subir imagen', 'error');
            }
        } catch (err) {
            prev.textContent = 'Click para subir imagen';
            Utils.showNotification('Error al subir imagen', 'error');
        }
    });

    // Mínimo del selector de fecha: ahora (igual que la pantalla pública).
    (function () {
        const f = document.getElementById('tpz-fecha');
        if (!f) return;
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        f.min = now.toISOString().slice(0, 16);
    })();

    document.getElementById('tapazo-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = e.target.querySelector('button[type="submit"]');
        btn.disabled = true; btn.textContent = 'Creando…';
        try {
            const created = await API.post('/tapazo/admin_list.php', {
                titulo: document.getElementById('tpz-titulo').value,
                descripcion: document.getElementById('tpz-desc').value,
                cantidad_jugadores: parseInt(document.getElementById('tpz-cantidad').value, 10),
                valor_cupo: parseFloat(document.getElementById('tpz-valor').value) || 0,
                regla: document.getElementById('tpz-regla').value,
                imagen_url: document.getElementById('tpz-imagen-url').value,
                fecha_hora_destape: document.getElementById('tpz-fecha').value ? (document.getElementById('tpz-fecha').value + ':00') : ''
            });
            Utils.showNotification('🍺 Tapazo creado exitosamente', 'success');
            e.target.reset();
            document.getElementById('tpz-imagen-prev').textContent = 'Click para subir imagen';
            loadTapazos();
            // Llevar al organizador a la pantalla pública original del Tapazo
            // (la experiencia de juego/compartir canónica) con su código.
            if (created && created.data && created.data.codigo) {
                window.open(BASE_PATH + '/tapazo/index.php?codigo=' + encodeURIComponent(created.data.codigo), '_blank');
            }
        } catch (error) {
            Utils.showNotification(error.message || 'Error al crear tapazo', 'error');
        } finally {
            btn.disabled = false; btn.textContent = '🍺 Crear El Tapazo';
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
                window.__userTickets = res.data.tickets;
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
                                <h3 class="font-bold text-lg">${userEsc(ticket.raffle_name || 'Rifa')}</h3>
                                <div class="flex items-center gap-2">
                                    <span class="badge badge--${statusClass}">${statusText}</span>
                                    <button class="btn btn--sm" aria-label="Acciones" title="Acciones" onclick="openTicketSheet(${parseInt(ticket.id, 10)})" style="font-size:20px;line-height:1;padding:2px 10px;">⋮</button>
                                </div>
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
        loadScraperUI();
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
    // SCRAPER DE RESULTADOS — configuración y estado en vivo (super_admin)
    // ================================================================
    async function loadScraperUI() {
        const card = document.getElementById('scraper-card');
        if (!card) return;
        try {
            const r = await API.get('/admin/scraper.php');
            if (!r.success) return;
            const d = r.data || {};
            document.getElementById('scraper-enabled').checked = !!d.enabled;
            const lr = d.last_run;
            document.getElementById('scraper-last-run').textContent = (lr && lr.at)
                ? ('Última corrida: ' + lr.at + ' — ' + lr.saved + ' guardado(s), ' + lr.pending + ' pendiente(s), ' + lr.errors + ' error(es).')
                : 'Aún no hay corridas registradas.';
            document.getElementById('scraper-lot-rows').innerHTML = (d.loterias || []).map(l =>
                '<tr style="border-top:1px solid #e5e7eb;">'
                + '<td style="padding:8px 12px 8px 0;"><input type="text" class="px-4 py-2 border rounded-lg scraper-name" data-id="' + l.id + '" value="' + userEsc(l.name) + '" maxlength="100" style="width:100%;min-width:170px;font-weight:600;"></td>'
                + '<td style="padding:8px 12px 8px 0;">' + diaSelectHtml('scraper-day', l.id, l.day_of_week) + '</td>'
                + '<td style="padding:8px 12px 8px 0;"><input type="time" class="px-4 py-2 border rounded-lg scraper-time" data-id="' + l.id + '" value="' + userEsc(l.draw_time || '22:30') + '"></td>'
                + '<td style="padding:8px 12px 8px 0;text-align:center;"><input type="checkbox" class="scraper-active" data-id="' + l.id + '"' + (l.active ? ' checked' : '') + '></td>'
                + '<td style="padding:8px 12px 8px 0;"><code class="text-xs">' + userEsc(l.slug_auto || '—') + '</code></td>'
                + '<td style="padding:8px 12px 8px 0;"><input type="text" class="px-4 py-2 border rounded-lg scraper-src" data-id="' + l.id + '" value="' + userEsc(l.api_source || '') + '" placeholder="(automática)" style="width:100%;max-width:230px;"></td>'
                + '<td style="padding:8px 0;white-space:nowrap;"><button type="button" class="btn text-sm scraper-test" data-id="' + l.id + '">Probar</button> <button type="button" class="btn text-sm scraper-del" data-id="' + l.id + '" data-name="' + userEsc(l.name) + '" title="Eliminar lotería" style="color:#dc2626;">🗑</button> <span class="text-xs" id="scraper-out-' + l.id + '"></span></td>'
                + '</tr>').join('');
            document.getElementById('scraper-recientes').innerHTML = (d.recientes || []).length
                ? '<b>Últimos resultados guardados:</b> ' + d.recientes.map(x =>
                    userEsc(x.lottery_name) + ' ' + x.draw_date + ' → <b>' + userEsc(x.winning_number) + '</b>').join(' · ')
                : 'Aún no hay resultados guardados por el scraper.';
        } catch (e) { console.error('scraper', e); }
    }

    const DIA_OPCIONES = [['monday','Lunes'],['tuesday','Martes'],['wednesday','Miércoles'],['thursday','Jueves'],['friday','Viernes'],['saturday','Sábado'],['sunday','Domingo']];
    function diaSelectHtml(clase, id, val) {
        return '<select class="px-4 py-2 border rounded-lg ' + clase + '" data-id="' + id + '">'
            + DIA_OPCIONES.map(([v, l]) => '<option value="' + v + '"' + (v === val ? ' selected' : '') + '>' + l + '</option>').join('')
            + '</select>';
    }

    document.getElementById('scraper-save')?.addEventListener('click', async () => {
        // Calendario (día/hora/activa) + configuración del scraper, juntos.
        const loterias = [];
        document.querySelectorAll('.scraper-day').forEach(sel => {
            const id = sel.dataset.id;
            loterias.push({
                id: parseInt(id),
                name: ((document.querySelector('.scraper-name[data-id="' + id + '"]') || {}).value || '').trim(),
                day_of_week: sel.value,
                draw_time: (document.querySelector('.scraper-time[data-id="' + id + '"]') || {}).value || '22:30',
                active: !!(document.querySelector('.scraper-active[data-id="' + id + '"]') || {}).checked
            });
        });
        const sources = {};
        document.querySelectorAll('.scraper-src').forEach(i => { sources[i.dataset.id] = i.value.trim(); });
        try {
            if (loterias.length) await API.post('/admin/lotteries.php', { action: 'guardar', loterias });
            await API.post('/admin/scraper.php', {
                action: 'guardar',
                enabled: document.getElementById('scraper-enabled').checked,
                sources
            });
            Utils.showNotification('Calendario y scraper guardados ✅', 'success');
            loadScraperUI();
            if (typeof loadLotteries === 'function') loadLotteries();   // refresca el selector al crear rifas
            if (typeof loadLotteryResultUI === 'function') loadLotteryResultUI();
        } catch (err) { Utils.showNotification(err.message || 'Error al guardar', 'error'); }
    });

    (function () {
        const sel = document.getElementById('scraper-new-day');
        if (sel) sel.innerHTML = DIA_OPCIONES.map(([v, l]) => '<option value="' + v + '">' + l + '</option>').join('');
    })();

    document.getElementById('scraper-new-btn')?.addEventListener('click', async () => {
        const name = document.getElementById('scraper-new-name').value.trim();
        if (!name) { Utils.showNotification('Escribe el nombre de la lotería', 'error'); return; }
        try {
            const r = await API.post('/admin/lotteries.php', {
                action: 'crear', name,
                day_of_week: document.getElementById('scraper-new-day').value,
                draw_time: document.getElementById('scraper-new-time').value || '22:30'
            });
            Utils.showNotification((r.data && r.data.message) || 'Lotería creada ✅', 'success');
            document.getElementById('scraper-new-name').value = '';
            loadScraperUI();
            if (typeof loadLotteries === 'function') loadLotteries();
        } catch (err) { Utils.showNotification(err.message || 'Error al crear', 'error'); }
    });

    document.getElementById('scraper-run')?.addEventListener('click', async (e) => {
        const btn = e.target;
        btn.disabled = true; btn.textContent = 'Ejecutando…';
        try {
            const r = await API.post('/admin/scraper.php', { action: 'ejecutar' });
            Utils.showNotification((r.data && r.data.message) || 'Corrida terminada', 'success');
            loadScraperUI();
            loadLotteryResultUI();
        } catch (err) { Utils.showNotification(err.message || 'Error al ejecutar', 'error'); }
        finally { btn.disabled = false; btn.textContent = '▶ Ejecutar ahora'; }
    });

    document.getElementById('scraper-card')?.addEventListener('click', async (e) => {
        const del = e.target.closest('.scraper-del');
        if (del) {
            if (!confirm('¿Eliminar la lotería "' + (del.dataset.name || '') + '"?\n\nSolo se permite si NINGUNA rifa la usa (el historial de sorteos la referencia). Si tiene rifas, desactívala en su lugar.')) return;
            try {
                const r = await API.post('/admin/lotteries.php', { action: 'eliminar', id: parseInt(del.dataset.id) });
                Utils.showNotification((r.data && r.data.message) || 'Lotería eliminada', 'success');
                loadScraperUI();
                if (typeof loadLotteries === 'function') loadLotteries();
            } catch (err) { Utils.showNotification(err.message || 'No se pudo eliminar', 'error'); }
            return;
        }
        const btn = e.target.closest('.scraper-test');
        if (!btn) return;
        const id = btn.dataset.id;
        const out = document.getElementById('scraper-out-' + id);
        const slug = (document.querySelector('.scraper-src[data-id="' + id + '"]') || {}).value || '';
        btn.disabled = true; out.textContent = 'consultando en vivo…'; out.style.color = '';
        try {
            const r = await API.post('/admin/scraper.php', { action: 'probar', lottery_id: parseInt(id), slug: slug.trim() });
            const d = r.data || {};
            out.textContent = d.number ? ('✅ ' + d.number + (d.fecha ? ' · sorteo ' + d.fecha : '') + ' (' + d.slug + ')') : ('❌ sin número — revisa ' + d.slug);
            out.style.color = d.number ? '#059669' : '#dc2626';
        } catch (err) {
            out.textContent = '❌ ' + (err.message || 'error');
            out.style.color = '#dc2626';
        } finally { btn.disabled = false; }
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
            return '<tr>' +
                '<td><span class="badge badge--' + (u.type === 'vendor' ? 'completed' : 'pending') + '">' + typeLabel[u.type] + '</span></td>' +
                '<td class="font-medium">' + userEsc(u.name) + (u.deps ? ' <span class="text-gray-400 text-xs">(' + u.deps + ')</span>' : '') + '</td>' +
                '<td style="color:#64748b;font-size:13px;">' + userEsc(u.email || '—') + '</td>' +
                '<td>' + userEsc(u.phone || '—') + '</td>' +
                '<td>' + (roleLabel[u.role] || u.role) + '</td>' +
                '<td>' + statusBadge + '</td>' +
                '<td style="color:#94a3b8;font-size:12px;">' + (u.created_at ? new Date(u.created_at).toLocaleDateString('es-CO') : '—') + '</td>' +
                '<td><button class="btn btn--sm" aria-label="Acciones" title="Acciones" onclick="openUserSheet(\'' + u.type + '\',' + parseInt(u.id, 10) + ')" style="font-size:20px;line-height:1;padding:2px 10px;">⋮</button></td>' +
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
        const statusMap = { draft: 'Borrador', active: 'Activa', blocked: 'Bloqueada', pending_reschedule: 'Por reprogramar', completed: 'Completada', cancelled: 'Cancelada' };
        tbody.innerHTML = raffles.map(r => {
            const statusClass = r.status === 'active' ? 'active' : (r.status === 'completed' ? 'completed' : (r.status === 'cancelled' ? 'cancelled' : (r.status === 'blocked' ? 'pending' : 'pending')));
            return '<tr>' +
                '<td class="text-gray-400 text-sm">' + (r.id || '') + '</td>' +
                '<td class="font-medium">' + userEsc(r.name || '') + '</td>' +
                '<td style="color:#64748b;font-size:13px;">' + userEsc(r.creator_name || '--') + '</td>' +
                '<td>' + userEsc(r.city || '--') + '</td>' +
                '<td><span class="badge badge--' + statusClass + '">' + (statusMap[r.status] || r.status) + '</span></td>' +
                '<td class="font-bold">' + (r.sold_tickets || 0) + '</td>' +
                '<td>' + (r.draw_date ? new Date(r.draw_date).toLocaleDateString('es-CO') : '--') + '</td>' +
                '<td><button class="btn btn--sm" aria-label="Acciones" title="Acciones" onclick="openGestionSheet(' + parseInt(r.id, 10) + ')" style="font-size:20px;line-height:1;padding:2px 10px;">⋮</button></td>' +
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
        // La rifa puede venir de CUALQUIER lista (Mis Rifas, dashboard,
        // Gestión); si ninguna está cargada, se pide al API. Antes solo
        // miraba allGestionRaffles y desde "Mis Rifas" no hacía NADA.
        let raffle = (allGestionRaffles || []).concat(window.__dashRaffles || [], window.__myRaffles || [], window.__allRaffles || [])
            .find(r => String(r.id) === String(raffleId));
        if (!raffle) {
            try {
                const res = await API.get('/raffles/details.php', { id: raffleId });
                if (res.success) raffle = res.data;
            } catch (e) {}
        }
        if (!raffle) { Utils.showNotification('No se pudo cargar la rifa para editar', 'error'); return; }

        // Los datos "vivos" (galería, vendidos) salen del API aunque la rifa
        // venga de una lista: el modal edita TODO el contenido.
        try {
            const res = await API.get('/raffles/details.php', { id: raffle.id });
            if (res.success) raffle = res.data;
        } catch (e) {}

        // El catálogo geográfico puede no estar cargado si no se ha abierto
        // "Crear rifa" en esta sesión.
        if (!colombiaData || !colombiaData.length) { try { await loadGeographyData(); } catch (e) {} }

        // Las opciones de los selectores se llenan ANTES de asignar valores
        // (al revés, innerHTML borraba la selección).
        const lotterySelect = document.getElementById('edit-lottery-id');
        lotterySelect.innerHTML = '<option value="">Seleccionar...</option>' +
            Object.entries(LOTTERY_DAYS).map(([id, l]) => `<option value="${id}">${l.name || ''}</option>`).join('');
        const deptSel = document.getElementById('edit-department');
        deptSel.innerHTML = '<option value="">Seleccionar</option>' +
            (colombiaData || []).map(d => `<option value="${d.departamento}">${d.departamento}</option>`).join('');

        document.getElementById('edit-raffle-id').value = raffle.id;
        document.getElementById('edit-name').value = raffle.name || '';
        document.getElementById('edit-status').value = raffle.status || 'draft';
        document.getElementById('edit-price').value = raffle.ticket_price || 0;
        // datetime-local exige "YYYY-MM-DDTHH:MM"; la BD trae espacio.
        document.getElementById('edit-draw-date').value = raffle.draw_date ? raffle.draw_date.replace(' ', 'T').substring(0, 16) : '';
        document.getElementById('edit-lottery-id').value = raffle.lottery_id || '';
        document.getElementById('edit-whatsapp').value = raffle.whatsapp_contact || '';
        document.getElementById('edit-responsible').value = raffle.responsible_person || '';
        document.getElementById('edit-description').value = raffle.description || '';
        deptSel.value = raffle.department || '';
        editLoadCities();
        document.getElementById('edit-city').value = raffle.city || '';
        document.getElementById('edit-digits').value = String(raffle.digits || 2);
        document.getElementById('edit-opportunities').value = String(raffle.opportunities || 1);
        editStructureChanged();
        document.getElementById('edit-winning-mode').value = raffle.winning_mode || '';

        // Estructura bloqueada con ventas: cambiar reglas con boletos
        // comprometidos le movería el piso a quienes ya compraron.
        window.__editHasSales = (parseInt(raffle.sold_tickets || 0) + parseInt(raffle.reserved_tickets || 0)) > 0;
        window.__editOriginal = { digits: String(raffle.digits || 2), opportunities: String(raffle.opportunities || 1) };
        ['edit-digits', 'edit-opportunities', 'edit-winning-mode', 'edit-price'].forEach(id => {
            document.getElementById(id).disabled = window.__editHasSales;
        });
        document.getElementById('edit-structure-hint').textContent = window.__editHasSales
            ? '🔒 Ya hay boletos reservados o vendidos: cifras, oportunidades, modo de ganar y precio quedan fijos.'
            : '';

        // Fotos actuales (principal + galería, sin duplicar).
        const fotos = [];
        if (raffle.image_url && raffle.image_url.indexOf('placeholder') === -1) fotos.push(raffle.image_url);
        (raffle.images || []).forEach(u => { if (fotos.indexOf(u) === -1) fotos.push(u); });
        window.__editImages = fotos;
        renderEditImages();

        document.getElementById('edit-raffle-modal').classList.remove('hidden');
    };

    window.editLoadCities = function () {
        const dep = (colombiaData || []).find(d => d.departamento === document.getElementById('edit-department').value);
        document.getElementById('edit-city').innerHTML = '<option value="">Seleccionar</option>' +
            ((dep && dep.ciudades) || []).map(c => `<option value="${c}">${c}</option>`).join('');
    };

    // Modos válidos según cifras + aviso del talonario resultante.
    window.editStructureChanged = function () {
        const d = parseInt(document.getElementById('edit-digits').value);
        const modos = d === 2 ? [['last_2','Últimas 2 cifras'],['first_2','Primeras 2 cifras']]
                    : d === 3 ? [['last_3','Últimas 3 cifras'],['first_3','Primeras 3 cifras']]
                    : [['last_4','Últimas 4 cifras']];
        const sel = document.getElementById('edit-winning-mode');
        const previo = sel.value;
        sel.innerHTML = modos.map(([v, l]) => `<option value="${v}">${l}</option>`).join('');
        if (modos.some(([v]) => v === previo)) sel.value = previo;
        const opp = parseInt(document.getElementById('edit-opportunities').value) || 1;
        const total = Math.floor(Math.pow(10, d) / opp);
        const cambia = window.__editOriginal &&
            (String(d) !== window.__editOriginal.digits || String(opp) !== window.__editOriginal.opportunities);
        if (!window.__editHasSales) {
            document.getElementById('edit-structure-hint').textContent =
                'Se generarán ' + total.toLocaleString('es-CO') + ' boletos con ' + opp + ' número(s) cada uno.'
                + (cambia ? ' ⚠️ Cambiar cifras u oportunidades REGENERA el talonario completo.' : '');
        }
    };

    window.renderEditImages = function () {
        const grid = document.getElementById('edit-images-grid');
        grid.innerHTML = (window.__editImages || []).map((u, i) => `
            <div class="relative rounded-lg overflow-hidden border border-slate-200" style="aspect-ratio:1;">
                <img src="${fixUrl(u)}" class="w-full h-full object-cover">
                <button type="button" onclick="removeEditImage(${i})" class="absolute top-1 right-1 bg-red-500 text-white w-5 h-5 rounded-full flex items-center justify-center text-[10px]">×</button>
            </div>`).join('');
    };
    window.removeEditImage = function (i) { window.__editImages.splice(i, 1); renderEditImages(); };

    document.getElementById('edit-image-file')?.addEventListener('change', async function () {
        if (!this.files.length) return;
        const st = document.getElementById('edit-image-status');
        st.textContent = '⏳ Subiendo…';
        const fd = new FormData();
        Array.from(this.files).forEach(f => { if (f.size <= 5 * 1048576) fd.append('image[]', f); });
        try {
            const r = await API.post('/upload/image.php', fd);
            (r.data.urls || []).forEach(u => window.__editImages.push(u));
            renderEditImages();
            st.textContent = '✅ ' + window.__editImages.length + ' foto(s)';
            if ((r.data.fallidas || []).length) Utils.showNotification('No se subieron: ' + r.data.fallidas.join('; '), 'warning');
        } catch (err) {
            st.textContent = '';
            Utils.showNotification(err.message || 'Error al subir fotos', 'error');
        } finally { this.value = ''; }
    });

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
            const body = {
                id: parseInt(document.getElementById('edit-raffle-id').value),
                name: document.getElementById('edit-name').value,
                status: document.getElementById('edit-status').value,
                draw_date: document.getElementById('edit-draw-date').value,
                lottery_id: parseInt(document.getElementById('edit-lottery-id').value),
                whatsapp_contact: document.getElementById('edit-whatsapp').value,
                responsible_person: document.getElementById('edit-responsible').value,
                description: document.getElementById('edit-description').value,
                department: document.getElementById('edit-department').value,
                city: document.getElementById('edit-city').value,
                image_url: (window.__editImages || [])[0] || '',
                image_urls: window.__editImages || []
            };
            if (!window.__editHasSales) {
                body.ticket_price = parseFloat(document.getElementById('edit-price').value);
                body.digits = parseInt(document.getElementById('edit-digits').value);
                body.opportunities = parseInt(document.getElementById('edit-opportunities').value);
                body.winning_mode = document.getElementById('edit-winning-mode').value;
                const cambia = String(body.digits) !== window.__editOriginal.digits
                    || String(body.opportunities) !== window.__editOriginal.opportunities;
                if (cambia && !confirm('Cambiaste cifras u oportunidades: el talonario completo se REGENERA con números nuevos. ¿Continuar?')) {
                    btn.disabled = false; btn.textContent = 'Guardar Cambios'; return;
                }
            }
            await API.post('/admin/raffles/update.php', body);
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
            loadDashboard();
            const secMR = document.getElementById('section-mis-rifas');
            if (secMR && !secMR.classList.contains('hidden')) loadMyRaffles();
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

        if (digits === 2) {
            // 2 cifras: solo últimas 2 y primeras 2
            modeSelect.innerHTML = 
                '<option value="last_2">Últimas 2 cifras</option>' +
                '<option value="first_2">Primeras 2 cifras</option>';
        } else if (digits === 3) {
            // 3 cifras: últimas 3 y primeras 3
            modeSelect.innerHTML = 
                '<option value="last_3">Últimas 3 cifras</option>' +
                '<option value="first_3">Primeras 3 cifras</option>';
        } else {
            // 4 cifras: solo últimas 4
            modeSelect.innerHTML = 
                '<option value="last_4">Últimas 4 cifras</option>';
        }
        modeSelect.disabled = false;
        updateWinningModeHint();
    }

    // El aviso describe el MODO ELEGIDO, no solo la cantidad de cifras:
    // "últimas 3" y "primeras 3" son reglas distintas y el organizador debe
    // ver exactamente cuál está eligiendo.
    function updateWinningModeHint() {
        const hint = document.getElementById('winning-mode-hint');
        if (!hint) return;
        const textos = {
            last_2:  'Gana con las 2 ÚLTIMAS cifras del número ganador del sorteo.',
            first_2: 'Gana con las 2 PRIMERAS cifras del número ganador del sorteo.',
            last_3:  'Gana con las 3 ÚLTIMAS cifras del número ganador del sorteo.',
            first_3: 'Gana con las 3 PRIMERAS cifras del número ganador del sorteo.',
            last_4:  'Gana con las 4 ÚLTIMAS cifras del número ganador del sorteo.'
        };
        hint.textContent = textos[document.getElementById('winning-mode').value] || '';
    }
    document.getElementById('winning-mode')?.addEventListener('change', updateWinningModeHint);

    async function loadSettings() {
        try {
            const response = await API.get('/admin/settings.php');
            if (response.success) {
                const d = response.data;
                // General
                if (d.platform_name) document.getElementById('cfg-platform-name').value = d.platform_name;
                if (d.platform_email !== undefined) document.getElementById('cfg-platform-email').value = d.platform_email;
                if (d.contact_whatsapp !== undefined && document.getElementById('cfg-contact-whatsapp')) document.getElementById('cfg-contact-whatsapp').value = d.contact_whatsapp;
                if (d.min_ticket_price) document.getElementById('cfg-min-ticket-price').value = d.min_ticket_price;
                if (d.max_ticket_price) document.getElementById('cfg-max-ticket-price').value = d.max_ticket_price;
                if (d.reservation_minutes) document.getElementById('cfg-reservation-minutes').value = d.reservation_minutes;
                if (d.max_tickets_per_purchase) document.getElementById('cfg-max-tickets-buyer').value = d.max_tickets_per_purchase;
                if (d.reviews_enabled !== undefined) document.getElementById('cfg-reviews-enabled').checked = d.reviews_enabled === '1';
                // Comisiones
                if (d.commission_enabled !== undefined) {
                    document.getElementById('commission-enabled').checked = d.commission_enabled === '1';
                }
                if (d.commission_percentage) {
                    document.getElementById('commission-percentage').value = d.commission_percentage;
                    document.getElementById('commission-percentage-slider').value = d.commission_percentage;
                }
                // Modalidad de cobro: comisión % o tarifa por talonario.
                const mode = d.billing_mode === 'talonario' ? 'talonario' : 'commission';
                const radio = document.getElementById('billing-mode-' + mode);
                if (radio) radio.checked = true;
                if (d.talonario_fee !== undefined) {
                    const fee = document.getElementById('talonario-fee');
                    if (fee) fee.value = d.talonario_fee;
                }
                toggleCommissionUI();
                toggleBillingModeUI();
                updateCommissionPreview();

                // Tarjeta "Mi configuración" del vendedor (lectura).
                if (document.getElementById('vcfg-billing')) {
                    if (d.commission_enabled !== '1') {
                        document.getElementById('vcfg-billing').textContent = '¡100% gratis! 🎉';
                    } else if (d.billing_mode === 'talonario') {
                        document.getElementById('vcfg-billing').textContent = 'Tarifa por talonario: $' + parseFloat(d.talonario_fee || 0).toLocaleString('es-CO');
                    } else {
                        document.getElementById('vcfg-billing').textContent = 'Comisión del ' + (d.commission_percentage || 5) + '% por rifa';
                    }
                    document.getElementById('vcfg-ttl').textContent = (d.reservation_ttl_minutes || d.reservation_minutes || 45) + ' minutos';
                }
            }
        } catch (error) { console.error('Error loading settings:', error); }
        // Datos de cuenta y estado del canal WA para la tarjeta del vendedor.
        try {
            if (document.getElementById('vcfg-nombre')) {
                var vu = JSON.parse(localStorage.getItem('misrifas_user') || '{}');
                document.getElementById('vcfg-nombre').textContent = vu.full_name || vu.name || vu.business_name || '—';
                document.getElementById('vcfg-email').textContent = vu.email || '—';
                document.getElementById('vcfg-phone').textContent = vu.phone ? ('📞 ' + vu.phone) : '';
                const rw = await API.get('/vendor/whatsapp.php', { action: 'estado' });
                const est = rw && rw.data ? rw.data.estado : '';
                document.getElementById('vcfg-wa').textContent =
                    est === 'conectado' ? 'Conectado ✓' + (rw.data.numero ? (' (+' + rw.data.numero + ')') : '')
                    : est === 'no_disponible' ? 'Próximamente'
                    : 'Sin vincular';
            }
        } catch (e) { console.error('vcfg', e); }
    }

    async function saveGeneralSettings() {
        try {
            await API.post('/admin/settings.php', {
                platform_name: document.getElementById('cfg-platform-name').value,
                platform_email: document.getElementById('cfg-platform-email').value,
                contact_whatsapp: (document.getElementById('cfg-contact-whatsapp') || {}).value || '',
                min_ticket_price: document.getElementById('cfg-min-ticket-price').value,
                max_ticket_price: document.getElementById('cfg-max-ticket-price').value,
                reservation_minutes: document.getElementById('cfg-reservation-minutes').value,
                max_tickets_per_purchase: document.getElementById('cfg-max-tickets-buyer').value,
                reviews_enabled: document.getElementById('cfg-reviews-enabled').checked ? '1' : '0'
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

    function toggleBillingModeUI() {
        const talonario = document.getElementById('billing-mode-talonario')?.checked;
        const commUI = document.getElementById('billing-commission-ui');
        const talUI = document.getElementById('billing-talonario-ui');
        if (commUI) commUI.style.display = talonario ? 'none' : 'flex';
        if (talUI) talUI.style.display = talonario ? 'block' : 'none';
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
                commission_percentage: percentage,
                billing_mode: document.getElementById('billing-mode-talonario')?.checked ? 'talonario' : 'commission',
                talonario_fee: document.getElementById('talonario-fee')?.value || '0'
            });
            Utils.showNotification('Configuración de comisiones guardada ✅', 'success');
        } catch (error) { Utils.showNotification('Error al guardar', 'error'); }
        finally { btn.disabled = false; btn.textContent = 'Guardar Configuración de Comisiones'; }
    }


    // ── Editor de plantillas (v4.13, solo super_admin) ──
    // ── Diagnóstico en vivo: consulta system_status.php (verificaciones
    // reales, no afirmaciones) y pinta un chip por subsistema. ──
    async function loadSystemStatus() {
        const box = document.getElementById('sys-status');
        if (!box) return;
        box.innerHTML = '<p style="font-size:12px;color:#94a3b8;">Verificando…</p>';
        try {
            const res = await API.get('/admin/system_status.php');
            const iconos = { ok: '✅', warn: '⚠️', fail: '❌' };
            const colores = { ok: '#16a34a', warn: '#d97706', fail: '#dc2626' };
            box.innerHTML = (res.data.checks || []).map(c =>
                '<div style="display:flex;gap:8px;align-items:flex-start;font-size:12px;line-height:1.5;">' +
                    '<span style="flex-shrink:0;">' + iconos[c.estado] + '</span>' +
                    '<div><strong style="color:' + colores[c.estado] + ';">' + userEsc(c.nombre) + '</strong> — ' + userEsc(c.detalle) +
                    (c.arreglo ? ' <em style="color:#64748b;">→ ' + userEsc(c.arreglo) + '</em>' : '') +
                    '</div></div>').join('');
        } catch (e) {
            box.innerHTML = '<p style="font-size:12px;color:#94a3b8;">Diagnóstico disponible solo para el super administrador.</p>';
        }
    }

    async function saveOtpNumber() {
        const num = (document.getElementById('otp-wa-number').value || '').replace(/\D/g, '');
        try {
            await API.post('/admin/settings.php', { otp_whatsapp_number: num });
            Utils.showNotification(num ? 'Número OTP guardado ✅' : 'Número borrado: canal WhatsApp del OTP desactivado', 'success');
            loadSystemStatus();
        } catch (e) { Utils.showNotification(e.message || 'No se pudo guardar', 'error'); }
    }

    async function loadTemplates() {
        const box = document.getElementById('tpl-editor');
        if (!box) return;
        try {
            const res = await API.get('/admin/templates.php');
            const tpls = res.data || [];
            box.innerHTML = '';
            tpls.forEach(t => {
                const d = document.createElement('details');
                d.style.cssText = 'border:1px solid #e2e8f0;border-radius:12px;padding:10px 14px;';
                const badge = t.custom_text
                    ? '<span style="margin-left:8px;padding:2px 8px;border-radius:99px;background:#fef3c7;color:#92400e;font-size:10px;font-weight:800;">PERSONALIZADA</span>'
                    : '<span style="margin-left:8px;padding:2px 8px;border-radius:99px;background:#e2e8f0;color:#475569;font-size:10px;font-weight:800;">ORIGINAL</span>';
                d.innerHTML =
                    '<summary style="font-weight:700;font-size:13px;cursor:pointer;">' + userEsc(t.nombre) + badge + '</summary>' +
                    '<p style="font-size:11.5px;color:#64748b;margin:8px 0;">' + userEsc(t.descripcion) + '</p>' +
                    '<div style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:8px;">' +
                        t.vars.map(v => '<button type="button" class="tpl-var" data-var="{' + v + '}" style="padding:2px 8px;border-radius:99px;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;font-size:11px;font-weight:700;cursor:pointer;">{' + v + '}</button>').join('') +
                    '</div>' +
                    '<textarea data-key="' + t.key + '" style="width:100%;min-height:96px;padding:10px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:13px;line-height:1.5;resize:vertical;"></textarea>' +
                    '<div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;">' +
                        '<button type="button" class="btn btn--primary btn--sm tpl-save" data-key="' + t.key + '">Guardar</button>' +
                        (t.custom_text ? '<button type="button" class="btn btn--sm btn--outline tpl-restore" data-key="' + t.key + '">↩ Restaurar original</button>' : '') +
                        '<button type="button" class="btn btn--sm btn--outline tpl-preview" data-key="' + t.key + '">👁 Vista previa</button>' +
                    '</div>' +
                    '<pre class="tpl-prev" style="display:none;white-space:pre-wrap;font-size:12px;background:#f8fafc;border-radius:8px;padding:10px;margin-top:8px;"></pre>';
                d.querySelector('textarea').value = t.custom_text || t.default_text;
                box.appendChild(d);
            });
            window.__tpls = tpls;

            box.onclick = async (e) => {
                const varBtn = e.target.closest('.tpl-var');
                if (varBtn) {
                    const ta = varBtn.closest('details').querySelector('textarea');
                    const pos = ta.selectionStart || ta.value.length;
                    ta.value = ta.value.slice(0, pos) + varBtn.dataset.var + ta.value.slice(pos);
                    ta.focus();
                    return;
                }
                const prevBtn = e.target.closest('.tpl-preview');
                if (prevBtn) {
                    const det = prevBtn.closest('details');
                    const pre = det.querySelector('.tpl-prev');
                    const ejemplo = { nombre: 'Camila', raffle_name: 'iPhone 15', ticket_number: '0007', lottery_name: 'Lotería de Bogotá', winning_number: '7307', full_number: '7307', draw_date: '31/08/2026', confirm_url: 'https://tu-dominio/…', tickets: '0003, 0011', winner_name: 'Camila T.', winner_ticket: '0007', next_date: '07/09/2026', price: '$10.000', whatsapp: '3001234567', winner_phone: '3102000008' };
                    pre.textContent = det.querySelector('textarea').value.replace(/\{(\w+)\}/g, (m, v) => ejemplo[v] || m);
                    pre.style.display = 'block';
                    return;
                }
                const saveBtn = e.target.closest('.tpl-save');
                if (saveBtn) {
                    saveBtn.disabled = true;
                    try {
                        const ta = saveBtn.closest('details').querySelector('textarea');
                        const r = await API.post('/admin/templates.php', { key: saveBtn.dataset.key, body_text: ta.value });
                        Utils.showNotification(r.message || 'Plantilla guardada ✅', (r.data && r.data.variables_sin_usar && r.data.variables_sin_usar.length) ? 'warning' : 'success');
                        loadTemplates();
                    } catch (err) { Utils.showNotification(err.message || 'Error al guardar', 'error'); }
                    saveBtn.disabled = false;
                    return;
                }
                const restBtn = e.target.closest('.tpl-restore');
                if (restBtn) {
                    if (!confirm('¿Volver al texto original de esta plantilla?')) return;
                    try {
                        await API.post('/admin/templates.php', { key: restBtn.dataset.key, restore: true });
                        Utils.showNotification('Plantilla restaurada ↩', 'success');
                        loadTemplates();
                    } catch (err) { Utils.showNotification(err.message || 'Error', 'error'); }
                }
            };
        } catch (e) {
            box.innerHTML = '<p class="text-sm text-gray-400">Solo el super administrador puede editar plantillas.</p>';
        }
    }

    async function loadEmailSettings() {
        try {
            // El endpoint público /settings/get.php no expone (ni debe exponer)
            // las claves mailing_*: se cargan por el GET autenticado del admin.
            const res = await API.get('/admin/settings.php');
            if (!res.success) return;
            const d = res.data || {};
            const map = {
                'mailing_smtp_host': 'smtp-host',
                'mailing_smtp_port': 'smtp-port',
                'mailing_smtp_user': 'smtp-user',
                'mailing_smtp_from': 'smtp-from',
                'mailing_from_name': 'smtp-from-name'
            };
            for (const [key, id] of Object.entries(map)) {
                const input = document.getElementById(id);
                if (input && d[key]) input.value = d[key];
            }
            // La contraseña NUNCA viaja de vuelta al navegador.
            const pass = document.getElementById('smtp-pass');
            if (pass) pass.placeholder = 'Deja vacío para no cambiarla';
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
                // Contraseña vacía = "no cambiarla" (nunca se precarga).
                if (f.key === 'mailing_smtp_pass' && val === '') continue;
                await API.post('/admin/settings/update.php', { key: f.key, value: val });
            }
            Utils.showNotification('Configuración SMTP guardada ✅', 'success');
        } catch (err) { Utils.showNotification(err.message || 'Error al guardar configuración SMTP', 'error'); }
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

        // Filtro previo: el servidor acepta 5MB por foto — avisar AQUÍ evita
        // descubrir el límite tras esperar la subida completa.
        const MAX_MB = 5;
        const lista = Array.from(files);
        const grandes = lista.filter(f => f.size > MAX_MB * 1048576);
        const validas = lista.filter(f => f.size <= MAX_MB * 1048576);
        if (grandes.length) {
            Utils.showNotification('Estas fotos superan ' + MAX_MB + 'MB y no se subirán: ' + grandes.map(f => f.name).join(', '), 'warning');
        }
        if (!validas.length) return;

        statusEl.textContent = `⏳ Subiendo ${validas.length} imagen(es)…`;
        const fd = new FormData();
        validas.forEach(f => fd.append('image[]', f));

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
                if ((json.data.fallidas || []).length) {
                    Utils.showNotification('Algunas no se subieron: ' + json.data.fallidas.join('; '), 'warning');
                } else {
                    Utils.showNotification('Foto(s) cargada(s) exitosamente', 'success');
                }
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
        data.auto_notify   = document.getElementById('auto-notify')?.checked !== false;

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


    // ================================================================
    // PAGOS MANUALES: Cargar tabla y aprobar/rechazar
    // ================================================================
    // Trazabilidad completa: pendientes, aprobados y rechazados con buscador.
    let pagosStatus = 'pending';
    let pagosQ = '';
    let pagosT = null;
    window.pagosBuscarDebounce = function () {
        clearTimeout(pagosT);
        pagosT = setTimeout(() => {
            pagosQ = (document.getElementById('pagos-buscar') || {}).value?.trim() || '';
            loadPayments();
        }, 300);
    };
    document.getElementById('pagos-filtros')?.addEventListener('click', (e) => {
        const b = e.target.closest('.pagos-filtro');
        if (!b) return;
        pagosStatus = b.dataset.status;
        loadPayments();
    });
    function pintarFiltrosPagos() {
        document.querySelectorAll('.pagos-filtro').forEach(b => {
            const on = b.dataset.status === pagosStatus;
            b.style.background = on ? '#2563eb' : '';
            b.style.color = on ? '#fff' : '';
        });
    }

    async function loadPayments() {
        const tbody = document.getElementById('payments-table');
        pintarFiltrosPagos();
        try {
            const response = await API.get('/admin/payments.php', { status: pagosStatus, q: pagosQ });
            if (response.success) {
                const data = response.data || [];
                if (data.length === 0) {
                    const vacios = { pending: 'No hay pagos pendientes de validar', completed: 'Aún no hay pagos aprobados',
                                     failed: 'No hay pagos rechazados', all: 'Aún no hay pagos registrados' };
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-gray-500 py-6">' + (vacios[pagosStatus] || vacios.all) + (pagosQ ? ' con esa búsqueda' : '') + '</td></tr>';
                    document.getElementById('approve-all-btn')?.classList.add('hidden');
                    return;
                }
                window.__payments = data;
                document.getElementById('approve-all-btn')?.classList.toggle('hidden', pagosStatus !== 'pending' || data.filter(p => p.payment_status === 'pending').length < 2);
                tbody.innerHTML = data.map(p => {
                    // Miniatura del comprobante (§10.2) servida por controlador (§16).
                    const proofCell = p.proof_link
                        ? `<a href="<?= BASE_PATH ?>${p.proof_link}" target="_blank"><img src="<?= BASE_PATH ?>${p.proof_link}" alt="Comprobante" loading="lazy" style="width:44px;height:44px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0;"></a>`
                        : `<span style="color:#9ca3af;font-size:12px;">Sin comprobante</span>`;
                    // §16: señales antifraude — informan, el vendedor decide.
                    const FLAG_LABELS = { comprobante_repetido: 'Comprobante repetido', fecha_fuera_de_rango: 'Fecha sospechosa', comprador_con_rechazos: 'Comprador con rechazos' };
                    const flagChips = (p.flags || []).map(f =>
                        `<span style="display:inline-block;margin-top:3px;padding:1px 7px;border-radius:99px;background:#fef3c7;color:#92400e;font-size:10.5px;font-weight:700;">⚠️ ${FLAG_LABELS[f] || f}</span>`
                    ).join(' ');
                    const monto = p.order_amount != null ? p.order_amount : p.amount;
                    const mins = parseInt(p.age_minutes || 0, 10);
                    const age = mins < 60 ? (mins + ' min') : (mins < 1440 ? Math.floor(mins / 60) + ' h' : Math.floor(mins / 1440) + ' d');
                    const ageColor = mins > 600 ? '#dc2626' : (mins > 120 ? '#d97706' : '#64748b');
                    const esPendiente = p.payment_status === 'pending';
                    const estadoBadge = esPendiente
                        ? '<span class="badge badge--pending">Pendiente</span>'
                        : (p.payment_status === 'completed'
                            ? '<span class="badge badge--completed">Aprobado</span>' + (p.verified_at ? '<br><span style="color:#94a3b8;font-size:11px;">' + userEsc(String(p.verified_at).substring(0, 16)) + '</span>' : '')
                            : '<span class="badge badge--cancelled">Rechazado</span>');
                    const accion = esPendiente
                        ? `<button class="btn btn--sm" aria-label="Acciones" title="Acciones" onclick="openPaymentSheet(${parseInt(p.ticket_id, 10)})" style="font-size:20px;line-height:1;padding:2px 10px;">⋮</button>`
                        : (p.payment_status === 'completed' && p.ticket_code
                            ? `<a class="btn btn--sm" title="Ver boleta emitida" href="<?= BASE_PATH ?>/public/boleta.php?c=${encodeURIComponent(p.ticket_code)}" target="_blank">🎟️</a>`
                            : '<span style="color:#cbd5e1;">—</span>');
                    return `<tr>
                        <td class="font-medium">${userEsc(p.raffle_name || '')}${flagChips ? '<br>' + flagChips : ''}</td>
                        <td><strong>#${userEsc(p.ticket_number)}</strong></td>
                        <td>${userEsc(p.buyer_name || '—')}<br><span style="color:#94a3b8;font-size:12px;">${userEsc(p.buyer_phone || '')}</span></td>
                        <td class="font-bold" style="font-variant-numeric:tabular-nums;">$${Number(monto || 0).toLocaleString('es-CO')}<br><span style="color:#94a3b8;font-size:11px;font-weight:400;">${userEsc(p.payment_method || '')}</span></td>
                        <td style="color:${ageColor};font-size:13px;font-weight:600;">${age}</td>
                        <td>${proofCell}</td>
                        <td>${estadoBadge}</td>
                        <td>${accion}</td>
                    </tr>`;
                }).join('');
                // §10.2: si WhatsApp está caído, avisar que el panel es la vía.
                try {
                    const wa = await API.get('/vendor/whatsapp.php', { action: 'estado' });
                    const caido = !(wa.success && wa.data && wa.data.estado === 'conectado');
                    document.getElementById('wa-pagos-banner')?.classList.toggle('hidden', !caido || data.length === 0);
                } catch (e) {}
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

    // §10.2: el rechazo SIEMPRE lleva motivo de la lista corta.
    const REJECT_REASONS = { no_llego: 'La plata no llegó', monto: 'El monto no coincide', ilegible: 'Comprobante ilegible', repetido: 'Comprobante repetido', otro: 'Otro motivo' };
    function chooseRejectReason(ticketId) {
        const items = Object.entries(REJECT_REASONS).map(([key, label]) => ({
            label: '❌  ' + label,
            danger: key !== 'otro',
            onClick: () => rejectPayment(ticketId, key)
        }));
        openActionSheet('¿Por qué rechazas este pago?', 'El número volverá a la venta', items);
    }

    async function rejectPayment(ticketId, reason) {
        try {
            await API.post('/admin/payments.php', { action: 'reject', ticket_id: ticketId, reason: reason });
            Utils.showNotification('❌ Pago rechazado (' + (REJECT_REASONS[reason] || reason) + '). Boleto liberado.', 'info');
            loadPayments();
        } catch (e) { Utils.showNotification(e.message || 'Error al rechazar', 'error'); }
    }

    // §10.2: confirmación en lote.
    async function approveAllPayments() {
        const pendientes = (window.__payments || []);
        if (!pendientes.length) return;
        if (!confirm('¿Confirmar TODOS los pagos pendientes (' + pendientes.length + ')? Cada boleto quedará vendido y con boleta emitida.')) return;
        const btn = document.getElementById('approve-all-btn');
        btn.disabled = true; btn.textContent = 'Confirmando…';
        let ok = 0, fail = 0;
        for (const p of pendientes) {
            try { await API.post('/admin/payments.php', { action: 'approve', ticket_id: p.ticket_id }); ok++; }
            catch (e) { fail++; }
        }
        btn.disabled = false; btn.textContent = '✅ Confirmar todos';
        Utils.showNotification('Lote: ' + ok + ' confirmados' + (fail ? (', ' + fail + ' fallidos') : ''), fail ? 'warning' : 'success');
        loadPayments();
    }

    // ================================================================
    // MI PERFIL: datos personales + WhatsApp/EvolutionAPI
    // ================================================================

    // ── WhatsApp autoservicio (QR gestionado por la plataforma) ──
    let waLinkPoll = null;
    async function loadWaLinkStatus() {
        try {
            const r = await API.get('/vendor/whatsapp.php', { action: 'estado' });
            if (!r.success) return;
            const d = r.data;
            const badge = document.getElementById('wa-link-estado');
            const numero = document.getElementById('wa-link-numero');
            const btn = document.getElementById('wa-link-btn');
            const unlink = document.getElementById('wa-unlink-btn');
            const msg = document.getElementById('wa-link-msg');
            if (!badge) return;
            if (d.estado === 'no_disponible') {
                badge.className = 'badge badge--pending';
                badge.textContent = 'Próximamente';
                btn.disabled = true;
                msg.textContent = d.mensaje || '';
                return;
            }
            if (d.estado === 'conectado') {
                badge.className = 'badge badge--active';
                badge.textContent = 'Conectado ✓';
                numero.textContent = d.numero ? ('+' + d.numero) : '';
                btn.classList.add('hidden');
                unlink.classList.remove('hidden');
                document.getElementById('wa-link-qr').classList.add('hidden');
                if (waLinkPoll) { clearInterval(waLinkPoll); waLinkPoll = null; }
            } else {
                badge.className = 'badge badge--pending';
                badge.textContent = 'Sin vincular';
                btn.classList.remove('hidden');
                unlink.classList.add('hidden');
            }
        } catch (e) { console.error('wa estado', e); }
    }
    window.waLinkQr = async () => {
        const btn = document.getElementById('wa-link-btn');
        const msg = document.getElementById('wa-link-msg');
        btn.disabled = true; btn.textContent = 'Generando QR…';
        try {
            const r = await API.post('/vendor/whatsapp.php', { action: 'qr' });
            if (r.success && r.data.qr) {
                const img = document.getElementById('wa-link-qr-img');
                img.src = r.data.qr.startsWith('data:') ? r.data.qr : ('data:image/png;base64,' + r.data.qr);
                document.getElementById('wa-link-qr').classList.remove('hidden');
                msg.textContent = 'Esperando el escaneo… esta tarjeta se actualizará sola al conectar.';
                if (waLinkPoll) clearInterval(waLinkPoll);
                waLinkPoll = setInterval(loadWaLinkStatus, 4000);
            } else {
                msg.textContent = r.message || r.data?.error || 'No se pudo generar el QR';
            }
        } catch (e) { msg.textContent = e.message || 'No se pudo generar el QR'; }
        finally { btn.disabled = false; btn.textContent = '🔗 Vincular con código QR'; }
    };
    window.waUnlink = async () => {
        if (!confirm('¿Desvincular tu WhatsApp? Las notificaciones seguirán saliendo por correo.')) return;
        try {
            await API.post('/vendor/whatsapp.php', { action: 'desconectar' });
            Utils.showNotification('WhatsApp desvinculado', 'info');
            document.getElementById('wa-link-qr').classList.add('hidden');
            loadWaLinkStatus();
        } catch (e) { Utils.showNotification('Error al desvincular', 'error'); }
    };

    window.savePaymentKeys = async () => {
        const btn = document.getElementById('pk-save');
        btn.disabled = true; btn.textContent = 'Guardando…';
        try {
            await API.post('/admin/profile_api.php', {
                type: 'payment_keys',
                nequi_phone: document.getElementById('pk-nequi').value.trim(),
                daviplata_phone: document.getElementById('pk-daviplata').value.trim(),
                breb_key: document.getElementById('pk-breb').value.trim(),
                accepts_cash: document.getElementById('pk-cash').checked
            });
            Utils.showNotification('Llaves de cobro guardadas ✅', 'success');
        } catch (e) { Utils.showNotification(e.message || 'Error al guardar', 'error'); }
        finally { btn.disabled = false; btn.textContent = 'Guardar llaves de cobro'; }
    };

    async function loadPerfilAPI() {
        loadWaLinkStatus();
        try {
            // 1. Configuración de Pago/WA
            const res = await API.get('/admin/profile_api.php');
            if (res.success) {
                const p = res.data.payment_config || {};
                if (document.getElementById('pk-nequi')) {
                    document.getElementById('pk-nequi').value = p.nequi_phone || '';
                    document.getElementById('pk-daviplata').value = p.daviplata_phone || '';
                    document.getElementById('pk-breb').value = p.breb_key || '';
                    document.getElementById('pk-cash').checked = !!p.accepts_cash;
                }
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
        } catch (err) { Utils.showNotification(err.message || 'Error al actualizar datos', 'error'); }
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
            const res = await fetch(BASE_PATH + '/public/assets/data/colombia.json?v=dc1', { cache: 'no-cache' });
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

        // WhatsApp IA: solo super_admin (fail-closed, oculto por defecto)
        var navWhatsapp = document.getElementById('nav-whatsapp');
        if (navWhatsapp) navWhatsapp.style.display = (userRole === 'super_admin') ? 'flex' : 'none';

        if (userRole !== 'super_admin') {
            var grpSorteos = document.getElementById('nav-group-sorteos');
            if (grpSorteos) grpSorteos.style.display = 'none';
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
            var commsCard = document.getElementById('comms-card');
            if (commsCard) commsCard.style.display = 'none';
            var emailForm = document.getElementById('email-settings-form');
            if (emailForm && emailForm.closest('.section-card')) emailForm.closest('.section-card').style.display = 'none';
            // En su lugar, el vendedor ve SU configuración (lectura + atajos).
            var vendorSettings = document.getElementById('vendor-settings-card');
            if (vendorSettings) vendorSettings.classList.remove('hidden');
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
                var navWa2 = document.getElementById('nav-whatsapp');
                if (navWa2) navWa2.style.display = (freshUser.role === 'super_admin') ? 'flex' : 'none';
                if (freshUser.role !== 'super_admin') {
                    var grpS2 = document.getElementById('nav-group-sorteos');
                    if (grpS2) grpS2.style.display = 'none';
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
            a.vtab { text-decoration: none; }
            /* Avatar del usuario junto a la hamburguesa (header estilo app):
               [☰][avatar] a la izquierda, título a la derecha. */
            .admin-header { justify-content: flex-end; padding-left: 112px !important; }
            .user-menu { position: absolute; left: 62px; top: 50%; transform: translateY(-50%); }
            .user-name, .user-menu-caret { display: none; }
            .user-dropdown { left: 0; right: auto; }
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
        <a class="vtab" href="<?= BASE_PATH ?>/public/index.php" title="Ir al sitio público">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l9-8 9 8"/><path d="M5 9v11a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9"/></svg>
            <span>Inicio</span>
        </a>
        <button class="vtab" data-tab="dashboard" onclick="switchTo('dashboard')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
            <span>Panel</span>
        </button>
        <button class="vtab" data-tab="mis-rifas" onclick="switchTo('mis-rifas')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3h11l-1 15a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 3Zm11 4h2.5a2 2 0 0 1 2 2.2l-.4 4A2 2 0 0 1 18.1 15H16"/></svg>
            <span>Rifas</span>
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

    <!-- ============ Bottom sheet de acciones de rifa (menú 3 puntos) ============ -->
    <style>
        #raffle-sheet-backdrop { display:none; position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:120; }
        #raffle-sheet { display:none; position:fixed; left:0; right:0; bottom:0; z-index:121; background:#fff; border-radius:20px 20px 0 0; box-shadow:0 -12px 40px rgba(0,0,0,.25); padding:10px 12px calc(12px + env(safe-area-inset-bottom,0px)); animation:rsUp .22s ease-out; max-width:520px; margin:0 auto; }
        @keyframes rsUp { from{transform:translateY(100%);} to{transform:translateY(0);} }
        #raffle-sheet .rs-handle { width:40px; height:4px; border-radius:99px; background:#e5e7eb; margin:4px auto 10px; }
        #raffle-sheet .rs-head { padding:0 8px 10px; border-bottom:1px solid #f1f5f9; margin-bottom:6px; }
        #raffle-sheet .rs-title { font-weight:800; color:#111827; font-size:16px; }
        #raffle-sheet .rs-sub { font-size:12px; color:#6b7280; margin-top:2px; }
        .rs-item { display:flex; align-items:center; gap:12px; width:100%; text-align:left; background:none; border:none; cursor:pointer; padding:14px 10px; border-radius:12px; font-size:15px; font-weight:600; color:#374151; }
        .rs-item:hover { background:#f3f4f6; }
        .rs-item--danger { color:#dc2626; }
    </style>
    <div id="raffle-sheet-backdrop" onclick="closeRaffleSheet()"></div>
    <div id="raffle-sheet" role="dialog" aria-modal="true">
        <div class="rs-handle"></div>
        <div class="rs-head">
            <div class="rs-title" id="raffle-sheet-title">Rifa</div>
            <div class="rs-sub" id="raffle-sheet-status"></div>
        </div>
        <div id="raffle-sheet-actions"></div>
    </div>
    <!-- Modal: registrar venta en efectivo (§5.2 — solo el vendedor) -->
    <div id="cash-modal-backdrop" onclick="closeCashSale()" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:130;"></div>
    <div id="cash-modal" style="display:none;position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);z-index:131;background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.35);padding:22px;width:min(92vw,380px);">
        <h3 style="font-weight:800;font-size:16px;margin-bottom:4px;">💵 Venta en efectivo</h3>
        <p style="font-size:12.5px;color:#6b7280;margin-bottom:14px;">El número queda PAGADO de inmediato. Nombre y celular son obligatorios: sin ellos no hay boleta ni trazabilidad.</p>
        <div style="display:flex;flex-direction:column;gap:10px;">
            <input type="text" id="cash-number" placeholder="Número del boleto (ej: 37)" maxlength="4" inputmode="numeric" style="width:100%;padding:11px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:15px;">
            <input type="text" id="cash-name" placeholder="Nombre del comprador *" style="width:100%;padding:11px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:15px;">
            <input type="tel" id="cash-phone" placeholder="Celular del comprador *" maxlength="10" inputmode="numeric" style="width:100%;padding:11px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:15px;">
            <input type="email" id="cash-email" placeholder="Correo (opcional, para avisarle el resultado)" style="width:100%;padding:11px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:15px;">
        </div>
        <p id="cash-msg" style="display:none;font-size:12.5px;margin-top:10px;padding:8px 10px;border-radius:8px;"></p>
        <div style="display:flex;gap:8px;margin-top:14px;">
            <button type="button" onclick="closeCashSale()" class="btn btn--sm" style="flex:1;background:#f1f5f9;">Cancelar</button>
            <button type="button" id="cash-submit" onclick="submitCashSale()" class="btn btn--primary btn--sm" style="flex:2;">Registrar venta</button>
        </div>
    </div>
    <!-- Modal: cartera de apartados (§8.4 — el "fiado" del vendedor) -->
    <div id="holds-backdrop" onclick="closeHoldsModal()" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:130;"></div>
    <div id="holds-modal" style="display:none;position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);z-index:131;background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.35);padding:20px;width:min(94vw,460px);max-height:88vh;overflow-y:auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
            <h3 style="font-weight:800;font-size:16px;">🤝 Apartados</h3>
            <button type="button" onclick="closeHoldsModal()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#64748b;">✕</button>
        </div>
        <p id="holds-resumen" style="font-size:12.5px;color:#6b7280;margin-bottom:12px;"></p>
        <div id="holds-lista" style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px;"></div>
        <div style="border-top:1px dashed #e2e8f0;padding-top:14px;">
            <p style="font-weight:700;font-size:13.5px;margin-bottom:8px;">➕ Apartar un número</p>
            <div style="display:flex;flex-direction:column;gap:8px;">
                <input type="text" id="hold-number" placeholder="Número (ej: 37)" maxlength="4" inputmode="numeric" style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:15px;">
                <input type="text" id="hold-name" placeholder="Nombre de la persona *" style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:15px;">
                <input type="tel" id="hold-phone" placeholder="Celular *" maxlength="10" inputmode="numeric" style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:15px;">
                <input type="text" id="hold-note" placeholder="Nota (opcional)" maxlength="100" style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:14px;">
            </div>
            <p id="holds-msg" style="display:none;font-size:12.5px;margin-top:8px;padding:8px 10px;border-radius:8px;"></p>
            <button type="button" id="hold-submit" onclick="submitHold()" class="btn btn--primary btn--sm" style="width:100%;margin-top:10px;">Apartar número</button>
        </div>
    </div>
    <!-- Modal: reprogramar sorteo (§12.2 — solo cuando el sistema lo habilitó) -->
    <div id="resched-backdrop" onclick="closeRescheduleModal()" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:130;"></div>
    <div id="resched-modal" style="display:none;position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);z-index:131;background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.35);padding:20px;width:min(94vw,420px);max-height:88vh;overflow-y:auto;">
        <h3 style="font-weight:800;font-size:16px;margin-bottom:4px;">📅 Reprogramar sorteo</h3>
        <p id="resched-info" style="font-size:12.5px;color:#6b7280;margin-bottom:12px;"></p>
        <div id="resched-hist" style="margin-bottom:12px;"></div>
        <p style="font-weight:700;font-size:13.5px;margin-bottom:8px;">Nueva fecha (misma lotería):</p>
        <div id="resched-fechas" style="display:flex;flex-direction:column;gap:8px;margin-bottom:12px;"></div>
        <p id="resched-msg" style="display:none;font-size:12.5px;margin-top:4px;padding:8px 10px;border-radius:8px;"></p>
        <div style="display:flex;gap:8px;margin-top:8px;">
            <button type="button" onclick="closeRescheduleModal()" class="btn btn--sm" style="flex:1;background:#f1f5f9;">Cancelar</button>
            <button type="button" id="resched-submit" onclick="submitReschedule()" class="btn btn--primary btn--sm" style="flex:2;">Confirmar reprogramación</button>
        </div>
    </div>
    <script>
        (function () {
            function el(id){ return document.getElementById(id); }
            function rsItem(label, onClick, danger){
                var b = document.createElement('button');
                b.className = 'rs-item' + (danger ? ' rs-item--danger' : '');
                b.textContent = label;
                b.addEventListener('click', onClick);
                return b;
            }
            window.showRaffleSheet = function(){ el('raffle-sheet').style.display='block'; el('raffle-sheet-backdrop').style.display='block'; document.body.style.overflow='hidden'; };
            window.closeRaffleSheet = function(){ el('raffle-sheet').style.display='none'; el('raffle-sheet-backdrop').style.display='none'; document.body.style.overflow=''; };

            // Sheet genérico: título, subtítulo y lista de acciones {label, onClick, danger}.
            window.openActionSheet = function(title, sub, items){
                el('raffle-sheet-title').textContent = title || '';
                el('raffle-sheet-status').textContent = sub || '';
                var body = el('raffle-sheet-actions');
                body.innerHTML = '';
                (items || []).forEach(function(it){
                    body.appendChild(rsItem(it.label, function(){ closeRaffleSheet(); it.onClick(); }, it.danger));
                });
                showRaffleSheet();
            };

            function visible(id){
                var el2 = document.getElementById(id);
                return el2 && !el2.classList.contains('hidden');
            }
            function refreshRaffleViews(){
                if (window.loadDashboard) loadDashboard();
                if (window.loadMyRaffles && visible('section-mis-rifas')) loadMyRaffles();
                if (window.loadAllRaffles && visible('section-rifas')) loadAllRaffles();
                if (window.loadGestionRaffles && visible('section-gestion-rifas')) loadGestionRaffles();
            }

            window.raffleSheetStatus = async function(id, status){
                try {
                    await API.post('/admin/raffles/update_status.php', { raffle_id: id, status: status });
                    Utils.showNotification(status === 'active' ? 'Rifa publicada ✅' : 'Rifa ocultada (borrador)', 'success');
                } catch (e) { Utils.showNotification(e.message || 'Error al cambiar estado', 'error'); }
                refreshRaffleViews();
            };

            window.openRaffleSheet = function(id){
                var list = (window.__dashRaffles || []).concat(window.__myRaffles || [], window.__allRaffles || []);
                var r = list.find(function(x){ return String(x.id) === String(id); });
                if (!r) return;
                var items = [
                    { label: '👁️  Ver rifa', onClick: function(){ viewRaffle(id); } }
                ];
                if (r.status === 'draft') {
                    items.push({ label: '✏️  Editar rifa (borrador)', onClick: function(){ openEditModal(id); } });
                }
                if (r.status === 'active') {
                    items.push({ label: '🙈  Ocultar (pasar a borrador)', onClick: function(){ raffleSheetStatus(id, 'draft'); } });
                } else {
                    items.push({ label: '🚀  Publicar rifa', onClick: function(){ raffleSheetStatus(id, 'active'); } });
                }
                if (r.status === 'active') {
                    items.push({ label: '💵  Registrar venta en efectivo', onClick: function(){ openCashSale(id); } });
                    items.push({ label: '🤝  Apartados (fiado)', onClick: function(){ openHoldsModal(id); } });
                }
                if (r.status === 'pending_reschedule') {
                    items.push({ label: '📅  Reprogramar sorteo', onClick: function(){ openRescheduleModal(id); } });
                }
                if (r.status === 'completed') {
                    items.push({ label: '📦  Reportar entrega del premio', onClick: function(){ reportDelivery(id); } });
                }
                items.push({ label: '🗑️  Eliminar', danger: true, onClick: function(){ if (window.deleteRaffle) deleteRaffle(id, r.name); } });
                openActionSheet(r.name || 'Rifa', 'Estado: ' + (r.status || '') + ' · ' + (r.sold_tickets || 0) + ' vendidos', items);
            };

            // ⋮ en "Pagos Recibidos": comprobante + aprobar/rechazar en sheet.
            window.openPaymentSheet = function(ticketId){
                var p = (window.__payments || []).find(function(x){ return String(x.ticket_id) === String(ticketId); });
                if (!p) return;
                var items = [];
                if (p.payment_proof_url) {
                    items.push({ label: '🧾  Ver comprobante', onClick: function(){ window.open(BASE_PATH + '/public' + p.payment_proof_url, '_blank'); } });
                }
                items.push({ label: '✅  Aprobar pago', onClick: function(){ approvePayment(p.ticket_id); } });
                items.push({ label: '❌  Rechazar pago…', danger: true, onClick: function(){ chooseRejectReason(p.ticket_id); } });
                var monto = p.order_amount != null ? p.order_amount : p.amount;
                openActionSheet('Boleto #' + (p.ticket_number || ''),
                    (p.raffle_name || '') + ' · $' + Number(monto || 0).toLocaleString('es-CO') + (p.buyer_name ? ' · ' + p.buyer_name : ''), items);
            };

            // §5.2: venta en efectivo — la registra SOLO el vendedor.
            var cashRaffleId = null;
            window.openCashSale = function(raffleId){
                cashRaffleId = raffleId;
                ['cash-number','cash-name','cash-phone','cash-email'].forEach(function(i){ el(i).value = ''; });
                el('cash-msg').style.display = 'none';
                el('cash-modal').style.display = 'block';
                el('cash-modal-backdrop').style.display = 'block';
                el('cash-number').focus();
            };
            window.closeCashSale = function(){
                el('cash-modal').style.display = 'none';
                el('cash-modal-backdrop').style.display = 'none';
            };
            window.submitCashSale = async function(){
                var msg = el('cash-msg');
                function showErr(t){ msg.textContent = t; msg.style.display = 'block'; msg.style.background = '#fee2e2'; msg.style.color = '#b91c1c'; }
                var numero = el('cash-number').value.trim();
                var nombre = el('cash-name').value.trim();
                var cel = el('cash-phone').value.replace(/\D+/g, '');
                if (!/^\d{2,4}$/.test(numero)) { showErr('Escribe el número del boleto (2 a 4 cifras).'); return; }
                if (nombre.length < 3) { showErr('El nombre del comprador es obligatorio.'); return; }
                if (!/^3\d{9}$/.test(cel)) { showErr('El celular del comprador es obligatorio (10 dígitos, empieza por 3).'); return; }
                var btn = el('cash-submit');
                btn.disabled = true; btn.textContent = 'Registrando…';
                try {
                    await API.post('/vendor/cash_sale.php', {
                        raffle_id: cashRaffleId, ticket_number: numero,
                        buyer_name: nombre, buyer_phone: cel,
                        buyer_email: el('cash-email').value.trim()
                    });
                    Utils.showNotification('💵 Venta registrada: boleto ' + numero + ' pagado', 'success');
                    closeCashSale();
                    if (window.loadDashboard) loadDashboard();
                    var secMR = document.getElementById('section-mis-rifas');
                    if (window.loadMyRaffles && secMR && !secMR.classList.contains('hidden')) loadMyRaffles();
                } catch (e) {
                    showErr(e.message || 'No se pudo registrar la venta');
                } finally {
                    btn.disabled = false; btn.textContent = 'Registrar venta';
                }
            };

            // §8: cartera de apartados del vendedor.
            var holdsRaffleId = null;
            function holdsEsc(s){ return String(s ?? '').replace(/[&<>"']/g, function(c){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]; }); }
            function holdsMsg(t, ok){ var m = el('holds-msg'); m.textContent = t; m.style.display = 'block'; m.style.background = ok ? '#dcfce7' : '#fee2e2'; m.style.color = ok ? '#166534' : '#b91c1c'; }
            window.openHoldsModal = async function(raffleId){
                holdsRaffleId = raffleId;
                ['hold-number','hold-name','hold-phone','hold-note'].forEach(function(i){ el(i).value = ''; });
                el('holds-msg').style.display = 'none';
                el('holds-modal').style.display = 'block';
                el('holds-backdrop').style.display = 'block';
                await refreshHolds();
            };
            window.closeHoldsModal = function(){
                el('holds-modal').style.display = 'none';
                el('holds-backdrop').style.display = 'none';
            };
            window.refreshHolds = async function(){
                try {
                    var r = await API.get('/vendor/holds.php', { raffle_id: holdsRaffleId });
                    if (!r.success) return;
                    var d = r.data;
                    el('holds-resumen').innerHTML = '<strong>' + d.cantidad + '</strong> apartado(s) sin cobrar · <strong>$' +
                        Number(d.total_apartado || 0).toLocaleString('es-CO') + '</strong>' +
                        (d.dias_para_corte != null ? ' · corte en <strong>' + d.dias_para_corte + '</strong> día(s)' : '');
                    var lista = el('holds-lista');
                    if (!d.holds.length) {
                        lista.innerHTML = '<p style="color:#94a3b8;font-size:13px;text-align:center;padding:8px 0;">No hay números apartados.</p>';
                        return;
                    }
                    lista.innerHTML = d.holds.map(function(h){
                        return '<div style="border:1px solid #eef2f7;border-radius:12px;padding:10px 12px;">' +
                            '<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">' +
                                '<div><strong style="font-size:15px;">#' + holdsEsc(h.ticket_number) + '</strong> · ' + holdsEsc(h.holder_name) +
                                '<br><span style="font-size:12px;color:#94a3b8;">' + holdsEsc(h.holder_phone) + ' · hace ' + h.dias + ' día(s)' +
                                (h.held_note ? ' · ' + holdsEsc(h.held_note) : '') + '</span></div>' +
                            '</div>' +
                            '<div style="display:flex;gap:6px;margin-top:8px;">' +
                                '<button type="button" class="btn btn--sm" style="flex:1;background:#10b981;color:#fff;" onclick="holdAction(' + h.ticket_id + ', \'mark_paid\')">💵 Pagado</button>' +
                                '<button type="button" class="btn btn--sm" style="flex:1;background:#f1f5f9;" onclick="holdAction(' + h.ticket_id + ', \'remind\')">🔔 Recordar</button>' +
                                '<button type="button" class="btn btn--sm" style="flex:1;background:#fee2e2;color:#b91c1c;" onclick="holdAction(' + h.ticket_id + ', \'release\')">🔓 Liberar</button>' +
                            '</div>' +
                        '</div>';
                    }).join('');
                } catch (e) { holdsMsg(e.message || 'Error al cargar la cartera', false); }
            };
            window.holdAction = async function(ticketId, accion){
                if (accion === 'release' && !confirm('¿Liberar este número? Volverá a la venta.')) return;
                if (accion === 'mark_paid' && !confirm('¿Marcar como PAGADO? El boleto queda vendido y con boleta emitida.')) return;
                try {
                    var r = await API.post('/vendor/holds.php', { action: accion, ticket_id: ticketId });
                    holdsMsg(r.message || 'Listo', true);
                    if (accion !== 'remind') await refreshHolds();
                    if (window.loadDashboard) loadDashboard();
                } catch (e) { holdsMsg(e.message || 'No se pudo completar', false); }
            };
            window.submitHold = async function(){
                var numero = el('hold-number').value.trim();
                var nombre = el('hold-name').value.trim();
                var cel = el('hold-phone').value.replace(/[^0-9]/g, '');
                if (!/^[0-9]{2,4}$/.test(numero)) { holdsMsg('Escribe el número (2 a 4 cifras).', false); return; }
                if (nombre.length < 3) { holdsMsg('El nombre es obligatorio.', false); return; }
                if (!/^3[0-9]{9}$/.test(cel)) { holdsMsg('El celular es obligatorio (10 dígitos, empieza por 3).', false); return; }
                var btn = el('hold-submit');
                btn.disabled = true; btn.textContent = 'Apartando…';
                try {
                    var r = await API.post('/vendor/holds.php', { action: 'hold', raffle_id: holdsRaffleId, ticket_number: numero, holder_name: nombre, holder_phone: cel, note: el('hold-note').value.trim() });
                    holdsMsg(r.message || 'Apartado', true);
                    ['hold-number','hold-name','hold-phone','hold-note'].forEach(function(i){ el(i).value = ''; });
                    await refreshHolds();
                } catch (e) { holdsMsg(e.message || 'No se pudo apartar', false); }
                finally { btn.disabled = false; btn.textContent = 'Apartar número'; }
            };

            // §12.2: reprogramación manual — el botón solo existe si el SISTEMA
            // dejó la rifa en pending_reschedule (verificó que el ganador no
            // estaba pagado). El servidor re-valida todas las guardas.
            var reschedRaffleId = null;
            function reschedMsg(t, ok){ var m = el('resched-msg'); m.textContent = t; m.style.display = 'block'; m.style.background = ok ? '#dcfce7' : '#fee2e2'; m.style.color = ok ? '#166534' : '#b91c1c'; }
            window.openRescheduleModal = async function(raffleId){
                reschedRaffleId = raffleId;
                el('resched-msg').style.display = 'none';
                el('resched-modal').style.display = 'block';
                el('resched-backdrop').style.display = 'block';
                try {
                    var r = await API.get('/vendor/reschedule.php', { raffle_id: raffleId });
                    if (!r.success) { reschedMsg(r.message || 'No disponible', false); return; }
                    var d = r.data;
                    el('resched-info').innerHTML = '<strong>' + d.raffle.name + '</strong> · ' + d.raffle.lottery +
                        ' · te quedan <strong>' + d.reprogramaciones_restantes + '</strong> reprogramación(es). Los boletos pagados se conservan.';
                    el('resched-hist').innerHTML = (d.historial || []).map(function(h){
                        return '<div style="font-size:12px;color:#64748b;border-left:3px solid #e2e8f0;padding-left:8px;margin-bottom:4px;">Intento ' + h.attempt + ' · ' +
                            new Date(h.draw_date).toLocaleDateString('es-CO') + ' · salió <strong>' + h.winning_number + '</strong> · ' +
                            (h.outcome === 'not_sold' ? 'número sin vender' : 'boleto en ' + (h.ticket_status || '?')) + '</div>';
                    }).join('');
                    el('resched-fechas').innerHTML = (d.fechas_validas || []).map(function(f, i){
                        return '<label style="display:flex;align-items:center;gap:10px;border:1px solid #e2e8f0;border-radius:10px;padding:10px 12px;cursor:pointer;">' +
                            '<input type="radio" name="resched-fecha" value="' + f + '"' + (i === 0 ? ' checked' : '') + '> ' +
                            new Date(f + 'T12:00:00').toLocaleDateString('es-CO', { weekday: 'long', day: 'numeric', month: 'long' }) + '</label>';
                    }).join('');
                } catch (e) { reschedMsg(e.message || 'No disponible', false); }
            };
            window.closeRescheduleModal = function(){
                el('resched-modal').style.display = 'none';
                el('resched-backdrop').style.display = 'none';
            };
            window.submitReschedule = async function(){
                var sel = document.querySelector('input[name=resched-fecha]:checked');
                if (!sel) { reschedMsg('Elige una fecha', false); return; }
                var btn = el('resched-submit');
                btn.disabled = true; btn.textContent = 'Reprogramando…';
                try {
                    var r = await API.post('/vendor/reschedule.php', { raffle_id: reschedRaffleId, new_draw_date: sel.value });
                    Utils.showNotification(r.message || 'Sorteo reprogramado ✅', 'success');
                    closeRescheduleModal();
                    if (window.loadDashboard) loadDashboard();
                    var secMR = document.getElementById('section-mis-rifas');
                    if (window.loadMyRaffles && secMR && !secMR.classList.contains('hidden')) loadMyRaffles();
                } catch (e) { reschedMsg(e.message || 'No se pudo reprogramar', false); }
                finally { btn.disabled = false; btn.textContent = 'Confirmar reprogramación'; }
            };

            // §13.4 paso 3: el vendedor declara que entregó; el GANADOR es
            // quien confirma con SU enlace (token distinto al de aceptación).
            // Reportar entrega: la FOTO de evidencia es OBLIGATORIA (§13.4).
            window.reportDelivery = function(raffleId){
                var old = document.getElementById('delivery-modal');
                if (old) old.remove();
                var wrap = document.createElement('div');
                wrap.id = 'delivery-modal';
                wrap.innerHTML =
                    '<div id="dlv-backdrop" style="position:fixed;inset:0;background:rgba(15,23,42,.6);z-index:140;"></div>' +
                    '<div style="position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);z-index:141;background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.35);padding:22px;width:min(92vw,400px);">' +
                        '<h3 style="font-weight:800;font-size:16px;margin-bottom:4px;">📦 Reportar entrega del premio</h3>' +
                        '<p style="font-size:12.5px;color:#6b7280;margin-bottom:12px;">La <strong>foto de evidencia es obligatoria</strong>: el premio entregado, el acta o el ganador recibiéndolo. El ganador la verá en su enlace de confirmación.</p>' +
                        '<label style="display:block;padding:14px;border:2px dashed #cbd5e1;border-radius:12px;text-align:center;font-size:13px;color:#64748b;cursor:pointer;">📷 Toca para elegir la foto' +
                            '<input type="file" id="dlv-photo" accept="image/*" style="display:none;"></label>' +
                        '<img id="dlv-preview" style="display:none;width:100%;max-height:180px;object-fit:cover;border-radius:12px;margin-top:10px;" alt="Evidencia">' +
                        '<div style="display:flex;gap:8px;margin-top:14px;">' +
                            '<button type="button" id="dlv-send" disabled style="flex:1;padding:12px;border:none;border-radius:10px;background:#22c55e;color:#052e13;font-weight:800;cursor:pointer;opacity:.5;">Reportar entrega</button>' +
                            '<button type="button" id="dlv-cancel" style="padding:12px 16px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;font-weight:700;cursor:pointer;">Cancelar</button>' +
                        '</div>' +
                    '</div>';
                document.body.appendChild(wrap);
                var close = function(){ wrap.remove(); };
                document.getElementById('dlv-backdrop').addEventListener('click', close);
                document.getElementById('dlv-cancel').addEventListener('click', close);
                var b64 = null;
                document.getElementById('dlv-photo').addEventListener('change', function(e){
                    var f = e.target.files[0];
                    if (!f) return;
                    var rd = new FileReader();
                    rd.onload = function(){
                        b64 = rd.result;
                        var img = document.getElementById('dlv-preview');
                        img.src = b64; img.style.display = 'block';
                        var btn = document.getElementById('dlv-send');
                        btn.disabled = false; btn.style.opacity = '1';
                    };
                    rd.readAsDataURL(f);
                });
                document.getElementById('dlv-send').addEventListener('click', async function(){
                    if (!b64) return;
                    this.disabled = true; this.textContent = 'Reportando…';
                    try {
                        var r = await API.post('/vendor/delivery.php', { raffle_id: raffleId, photo: b64 });
                        Utils.showNotification(r.message || 'Entrega reportada 📦', 'success');
                        close();
                    } catch (e) {
                        Utils.showNotification(e.message || 'No se pudo reportar', 'error');
                        this.disabled = false; this.textContent = 'Reportar entrega';
                    }
                });
            };

            // ⋮ en "Comisiones": ver rifa / marcar como pagada.
            window.openCommissionSheet = function(raffleId){
                var c = (window.__commissions || []).find(function(x){ return String(x.raffle_id) === String(raffleId); });
                if (!c) return;
                var items = [
                    { label: '💳  Pagar con Wompi (reactivación automática)', onClick: function(){ pagarCobroWompi(c.raffle_id); } },
                    { label: '👁️  Ver rifa', onClick: function(){ viewRaffle(c.raffle_id); } },
                    { label: '✅  Marcar como pagada (manual — contingencia)', onClick: function(){ markCommissionPaid(c.raffle_id); } }
                ];
                openActionSheet(c.raffle_name || 'Comisión',
                    'Comisión: ' + Utils.formatPrice(c.commission_amount || 0) + ' · ' + (c.creator_name || ''), items);
            };

            // ⋮ en "El Tapazo": ver participantes / completar si está activo.
            window.openTapazoSheet = function(id){
                var t = (window.__tapazos || []).find(function(x){ return String(x.id) === String(id); });
                if (!t) return;
                var items = [];
                // La pantalla pública original (/tapazo) es la experiencia de juego
                // canónica; el panel solo administra.
                if (t.codigo) {
                    var link = window.location.origin + BASE_PATH + '/tapazo/index.php?codigo=' + encodeURIComponent(t.codigo);
                    items.push({ label: '🍻  Abrir Tapazo (pantalla de juego)', onClick: function(){ window.open(link, '_blank'); } });
                    items.push({ label: '🔗  Copiar link para compartir', onClick: function(){
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(link).then(
                                function(){ Utils.showNotification('Link copiado 🔗', 'success'); },
                                function(){ window.prompt('Copia el link:', link); });
                        } else { window.prompt('Copia el link:', link); }
                    } });
                }
                items.push({ label: '👁️  Ver participantes', onClick: function(){ viewTapazo(t.id); } });
                if (t.estado !== 'finalizado') {
                    items.push({ label: '✅  Completar (sortear ganador)', onClick: function(){ completeTapazo(t.id); } });
                }
                var estados = { creado: 'Abierto', lleno: 'Completo', esperando: 'Esperando destape', destapando: 'Destapando…', finalizado: 'Finalizado' };
                openActionSheet(t.name || 'Tapazo',
                    (t.joined_count || 0) + ' / ' + (t.total_participants || 0) + ' jugadores · ' + (estados[t.estado] || t.estado || ''), items);
            };

            // ⋮ en "Usuarios": editar / activar-suspender / eliminar.

            // Restablecer contraseña (solo super_admin): genera una temporal y
            // la muestra UNA vez para copiarla — no se envía por ningún canal.
            window.resetUserPassword = async function(type, id, nombre){
                if (!confirm('¿Restablecer la contraseña de ' + (nombre || 'este usuario') + '?\n\nSe genera una temporal (se muestra UNA sola vez) y su sesión actual se cierra.')) return;
                try {
                    var r = await API.post('/admin/users/reset_password.php', { type: type, id: id });
                    var pwd = r.data && r.data.password;
                    if (pwd) window.prompt('Contraseña temporal de ' + nombre + ' — cópiala AHORA (no se vuelve a mostrar):', pwd);
                } catch (e) { Utils.showNotification(e.message || 'No se pudo restablecer', 'error'); }
            };
            window.openUserSheet = function(type, id){
                // allUsers es un `let` de otro <script> (no cuelga de window); typeof evita ReferenceError.
                var u = (typeof allUsers !== 'undefined' ? allUsers : []).find(function(x){ return x.type === type && String(x.id) === String(id); });
                if (!u) return;
                var suspended = u.status !== 'active';
                var items = [
                    { label: '✏️  Editar', onClick: function(){ openUserEdit(u.type, u.id); } },
                    { label: '🔑  Restablecer contraseña', onClick: function(){ resetUserPassword(u.type, u.id, u.name || u.email || ''); } }
                ];
                if (suspended) {
                    items.push({ label: '🔓  Activar', onClick: function(){ toggleUserStatus(u.type, u.id, 'activate'); } });
                } else {
                    items.push({ label: '⏸️  Suspender', onClick: function(){ toggleUserStatus(u.type, u.id, 'suspend'); } });
                }
                items.push({ label: '🗑️  Eliminar', danger: true, onClick: function(){ deleteUser(u.type, u.id, u.name || ''); } });
                openActionSheet(u.name || 'Usuario',
                    (u.email || u.phone || '') + ' · ' + (suspended ? 'Suspendido' : 'Activo'), items);
            };

            // ⋮ en "Gestión de Rifas" (super_admin): ver / editar / estado / eliminar.
            window.openGestionSheet = function(id){
                var r = (typeof allGestionRaffles !== 'undefined' ? allGestionRaffles : []).find(function(x){ return String(x.id) === String(id); });
                if (!r) return;
                var items = [
                    { label: '👁️  Ver rifa', onClick: function(){ viewRaffle(r.id); } },
                    { label: '✏️  Editar', onClick: function(){ openEditModal(r.id); } }
                ];
                if (r.status === 'active') {
                    items.push({ label: '🙈  Ocultar (pasar a borrador)', onClick: function(){ changeRaffleStatus(r.id, 'draft'); } });
                } else if (r.status === 'draft' || r.status === 'blocked') {
                    items.push({ label: '🚀  Publicar rifa', onClick: function(){ changeRaffleStatus(r.id, 'active'); } });
                }
                items.push({ label: '🗑️  Eliminar', danger: true, onClick: function(){ deleteRaffle(r.id, r.name || ''); } });
                openActionSheet(r.name || 'Rifa',
                    'Estado: ' + (r.status || '') + ' · ' + (r.sold_tickets || 0) + ' vendidos · ' + (r.creator_name || ''), items);
            };

            // ⋮ en "Boletas Compradas": ir a la rifa del boleto.
            window.openTicketSheet = function(ticketId){
                var t = (window.__userTickets || []).find(function(x){ return String(x.id) === String(ticketId); });
                if (!t) return;
                var items = [];
                if (t.raffle_id) items.push({ label: '👁️  Ver rifa', onClick: function(){ viewRaffle(t.raffle_id); } });
                if (!items.length) return;
                openActionSheet('Boleto #' + (t.ticket_number || ''), t.raffle_name || '', items);
            };
        })();
    </script>
</body>
</html>
<?php endif; ?>

