<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../config/brand.php';
$page_title = "Mi Perfil - " . plataforma('nombre');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <meta name="theme-color" content="#0f172a">
    <script>const BASE_PATH = "<?= BASE_PATH ?>";</script>
    <link rel="stylesheet" href="<?= BASE_PATH ?>/public/css/tailwind.min.css">
    <style>
        @font-face {
            font-family: 'Outfit';
            font-style: normal;
            font-weight: 800;
            font-display: swap;
            src: url('<?= BASE_PATH ?>/public/assets/fonts/outfit-800.woff2') format('woff2');
        }
        html { color-scheme: dark; }
        body { background: #0f172a; color: white; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        h1, h3 { font-family: 'Outfit', 'Inter', sans-serif; }
        .glass-card { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 24px; }
        .form-input { background: rgba(15, 23, 42, 0.8); border: 1px solid rgba(255, 255, 255, 0.1); color: white; padding: 12px 16px; border-radius: 12px; width: 100%; outline: none; transition: border-color 0.2s; }
        .form-input:focus { border-color: #f59e0b; }
        .btn-primary { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: #1c1305; padding: 12px 24px; border-radius: 12px; font-weight: 700; transition: opacity 0.2s, transform 0.2s, box-shadow 0.2s; width: 100%; }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

        @media (max-width: 640px) {
            .glass-card { padding: 1.5rem !important; }
            .profile-header { flex-direction: column; text-align: center; gap: 1rem !important; }
            .h-20 { height: auto !important; min-height: 5rem; padding: 1rem !important; }
        }
    </style>
</head>
<body>
    <header class="h-20 flex items-center justify-between px-6 border-b border-white/5 sticky top-0 bg-[#0f172a]/80 backdrop-blur-md z-50">
        <a href="<?= BASE_PATH ?>/public/index.php" class="text-2xl font-black text-primary flex items-center gap-2">
            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v1a2 2 0 0 0 0 4v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1a2 2 0 0 0 0-4Z"/><path d="M13 5v14" stroke-dasharray="2 3"/></svg>
            <?= plataforma_e() ?>
        </a>
        <div class="flex items-center gap-4">
            <a href="<?= BASE_PATH ?>/public/index.php" class="text-slate-400 hover:text-white text-sm hidden sm:block">Inicio</a>
            <button onclick="logout()" class="text-slate-400 hover:text-red-400 text-sm font-bold">Cerrar Sesión</button>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8 md:py-12 max-w-2xl">
        <div class="glass-card p-8">
            <div class="flex items-center gap-6 mb-10 profile-header">
                <label class="relative group cursor-pointer block w-fit" aria-label="Cambiar foto de perfil">
                    <input type="file" id="p-image" class="sr-only peer" accept="image/*">
                    <div id="profile-avatar" class="w-24 h-24 bg-primary text-slate-950 rounded-full flex items-center justify-center text-4xl font-bold overflow-hidden border-4 border-white/10 group-hover:border-primary peer-focus-visible:border-primary peer-focus-visible:ring-4 peer-focus-visible:ring-amber-500/40 transition-all">
                        <span id="avatar-text">U</span>
                        <img id="avatar-img" class="w-full h-full object-cover hidden" alt="Profile">
                    </div>
                    <div class="absolute inset-0 bg-black/40 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 peer-focus-visible:opacity-100 transition-opacity">
                        <span class="text-white text-[10px] font-black uppercase">Cambiar</span>
                    </div>
                </label>
                <div>
                    <h1 class="text-3xl font-black" id="profile-name-display">Cargando…</h1>
                    <p class="text-slate-400" id="profile-email-display">email@ejemplo.com</p>
                </div>
            </div>

            <form id="profile-form" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="p-name" class="block text-sm font-bold text-slate-400 mb-2 uppercase tracking-widest">Nombre Completo</label>
                        <input type="text" id="p-name" name="name" autocomplete="name" class="form-input" required>
                    </div>
                    <div>
                        <label for="p-phone" class="block text-sm font-bold text-slate-400 mb-2 uppercase tracking-widest">WhatsApp / Celular</label>
                        <input type="tel" id="p-phone" name="phone" autocomplete="tel" inputmode="numeric" class="form-input" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="p-dept" class="block text-sm font-bold text-slate-400 mb-2 uppercase tracking-widest">Departamento</label>
                        <select id="p-dept" name="department" class="form-input"></select>
                    </div>
                    <div>
                        <label for="p-city" class="block text-sm font-bold text-slate-400 mb-2 uppercase tracking-widest">Ciudad</label>
                        <select id="p-city" name="city" class="form-input"></select>
                    </div>
                </div>

                <div class="pt-4 border-t border-white/5">
                    <label for="p-pass" class="block text-sm font-bold text-slate-400 mb-2 uppercase tracking-widest">Nueva Contraseña (Opcional)</label>
                    <input type="password" id="p-pass" name="password" autocomplete="new-password" class="form-input" placeholder="••••••••">
                    <small class="text-slate-500 block mt-2">Déjalo en blanco para mantener la actual.</small>
                </div>

                <div class="pt-8 border-t border-white/10 mt-10">
                    <a href="<?= BASE_PATH ?>/public/mis-boletos.php" class="flex items-center justify-between p-4 md:p-6 bg-primary/10 border border-primary/20 rounded-3xl group hover:bg-primary/20 transition-all">
                        <div class="flex items-center gap-4">
                            <svg class="w-7 h-7 md:w-8 md:h-8 text-primary group-hover:scale-125 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v1a2 2 0 0 0 0 4v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1a2 2 0 0 0 0-4Z"/><path d="M13 5v14" stroke-dasharray="2 3"/></svg>
                            <div class="text-left">
                                <h3 class="text-base md:text-lg font-black text-white leading-tight">Mis Boletas</h3>
                                <p class="text-xs md:text-sm text-slate-400">Consulta tus números y estado.</p>
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-primary group-hover:translate-x-2 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                </div>

                <button type="submit" id="save-btn" class="btn-primary mt-10">Guardar Cambios</button>
            </form>
        </div>
    </main>

    <script>
        const fixUrl = (url) => {
            if (!url) return '';
            if (url.startsWith('http')) return url;
            return BASE_PATH + '/public/' + url.replace(/^\//, '');
        };

        const API = {
            async get(endpoint) {
                const token = localStorage.getItem('misrifas_token');
                const res = await fetch(BASE_PATH + '/api' + endpoint, { headers: { 'Authorization': 'Bearer ' + token } });
                return res.json();
            },
            async post(endpoint, data, isMultipart = false) {
                const token = localStorage.getItem('misrifas_token');
                const headers = { 'Authorization': 'Bearer ' + token };
                if (!isMultipart) headers['Content-Type'] = 'application/json';
                
                const res = await fetch(BASE_PATH + '/api' + endpoint, {
                    method: 'POST',
                    headers: headers,
                    body: isMultipart ? data : JSON.stringify(data)
                });
                return res.json();
            }
        };

        // Previsualización de imagen
        document.getElementById('p-image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const img = document.getElementById('avatar-img');
                    img.src = e.target.result;
                    img.classList.remove('hidden');
                    document.getElementById('avatar-text').classList.add('hidden');
                };
                reader.readAsDataURL(file);
            }
        });

        let colombiaData = [];
        async function loadGeo() {
            try {
                const res = await fetch(BASE_PATH + '/public/assets/data/colombia.json?v=dc1', { cache: 'no-cache' });
                colombiaData = await res.json();
                const deptSelect = document.getElementById('p-dept');
                deptSelect.innerHTML = '<option value="">Selecciona Depto</option>' + 
                    colombiaData.map(d => `<option value="${d.departamento}">${d.departamento}</option>`).join('');
                deptSelect.addEventListener('change', updateCities);
            } catch (e) { console.error('Geo error:', e); }
        }

        function updateCities() {
            const deptName = document.getElementById('p-dept').value;
            const dept = colombiaData.find(d => d.departamento === deptName);
            const citySelect = document.getElementById('p-city');
            if (dept) {
                citySelect.innerHTML = dept.ciudades.map(c => `<option value="${c}">${c}</option>`).join('');
            } else {
                citySelect.innerHTML = '<option value="">Selecciona Ciudad</option>';
            }
        }

        async function init() {
            const token = localStorage.getItem('misrifas_token');
            if (!token) {
                window.location.href = BASE_PATH + '/public/admin/index.php?auth=login';
                return;
            }

            await loadGeo();
            try {
                const res = await API.get('/user/get_profile.php');
                if (res.success) {
                    const u = res.data;
                    document.getElementById('p-name').value = u.name || '';
                    document.getElementById('p-phone').value = u.phone || '';
                    document.getElementById('p-dept').value = u.department || '';
                    updateCities();
                    document.getElementById('p-city').value = u.city || '';
                    
                    document.getElementById('profile-name-display').textContent = u.name || 'Usuario';
                    document.getElementById('profile-email-display').textContent = u.email || '';
                    
                    if (u.profile_image) {
                        const img = document.getElementById('avatar-img');
                        img.src = fixUrl(u.profile_image);
                        img.classList.remove('hidden');
                        document.getElementById('avatar-text').classList.add('hidden');
                    } else {
                        document.getElementById('avatar-text').textContent = (u.name || 'U').charAt(0).toUpperCase();
                        document.getElementById('avatar-img').classList.add('hidden');
                        document.getElementById('avatar-text').classList.remove('hidden');
                    }
                }
            } catch (e) { console.error('Init error:', e); }
        }

        document.getElementById('profile-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('save-btn');
            btn.disabled = true;
            btn.textContent = 'Guardando…';

            const formData = new FormData();
            formData.append('name', document.getElementById('p-name').value);
            formData.append('phone', document.getElementById('p-phone').value);
            formData.append('department', document.getElementById('p-dept').value);
            formData.append('city', document.getElementById('p-city').value);
            formData.append('password', document.getElementById('p-pass').value);
            
            const fileInput = document.getElementById('p-image');
            if (fileInput.files[0]) {
                formData.append('profile_image', fileInput.files[0]);
            }

            try {
                const res = await API.post('/user/update_profile.php', formData, true);
                if (res.success) {
                    alert('¡Perfil actualizado con éxito!');
                    location.reload();
                } else {
                    alert('Error: ' + res.message);
                }
            } catch (e) { alert('Error de conexión'); }
            btn.disabled = false;
            btn.textContent = 'Guardar Cambios';
        });

        function logout() {
            localStorage.removeItem('misrifas_token');
            localStorage.removeItem('misrifas_user');
            window.location.href = BASE_PATH + '/public/index.php';
        }

        init();
    </script>
</body>
</html>
