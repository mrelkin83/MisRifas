<?php
$page_title = "Mi Perfil - MisRifas";
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/paths.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <script>const BASE_PATH = "<?= BASE_PATH ?>";</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: #0f172a; color: white; }
        .glass-card { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 24px; }
        .form-input { background: rgba(15, 23, 42, 0.8); border: 1px solid rgba(255, 255, 255, 0.1); color: white; padding: 12px 16px; border-radius: 12px; width: 100%; outline: none; transition: border-color 0.2s; }
        .form-input:focus { border-color: #3b82f6; }
        .btn-primary { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; padding: 12px 24px; border-radius: 12px; font-weight: 700; transition: all 0.2s; width: 100%; }
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
        <a href="index.php" class="text-2xl font-black text-blue-400">🎟️ MisRifas</a>
        <div class="flex items-center gap-4">
            <a href="index.php" class="text-slate-400 hover:text-white text-sm hidden sm:block">Inicio</a>
            <button onclick="logout()" class="text-slate-400 hover:text-red-400 text-sm font-bold">Cerrar Sesión</button>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8 md:py-12 max-w-2xl">
        <div class="glass-card p-8">
            <div class="flex items-center gap-6 mb-10 profile-header">
                <div class="relative group cursor-pointer" onclick="document.getElementById('p-image').click()">
                    <div id="profile-avatar" class="w-24 h-24 bg-blue-600 rounded-full flex items-center justify-center text-4xl font-bold overflow-hidden border-4 border-white/10 group-hover:border-blue-500 transition-all">
                        <span id="avatar-text">U</span>
                        <img id="avatar-img" class="w-full h-full object-cover hidden" alt="Profile">
                    </div>
                    <div class="absolute inset-0 bg-black/40 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                        <span class="text-white text-[10px] font-black uppercase">Cambiar</span>
                    </div>
                </div>
                <div>
                    <h1 class="text-3xl font-black" id="profile-name-display">Cargando...</h1>
                    <p class="text-slate-400" id="profile-email-display">email@ejemplo.com</p>
                </div>
            </div>

            <form id="profile-form" class="space-y-6">
                <!-- Input oculto para la imagen -->
                <input type="file" id="p-image" class="hidden" accept="image/*">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-bold text-slate-400 mb-2 uppercase tracking-widest">Nombre Completo</label>
                        <input type="text" id="p-name" class="form-input" required>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-400 mb-2 uppercase tracking-widest">WhatsApp / Celular</label>
                        <input type="tel" id="p-phone" class="form-input" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-bold text-slate-400 mb-2 uppercase tracking-widest">Departamento</label>
                        <select id="p-dept" class="form-input"></select>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-400 mb-2 uppercase tracking-widest">Ciudad</label>
                        <select id="p-city" class="form-input"></select>
                    </div>
                </div>

                <div class="pt-4 border-t border-white/5">
                    <label class="block text-sm font-bold text-slate-400 mb-2 uppercase tracking-widest">Nueva Contraseña (Opcional)</label>
                    <input type="password" id="p-pass" class="form-input" placeholder="••••••••">
                    <small class="text-slate-500 block mt-2">Desealo en blanco para mantener la actual.</small>
                </div>

                <div class="pt-8 border-t border-white/10 mt-10">
                    <a href="mis-boletos.php" class="flex items-center justify-between p-4 md:p-6 bg-blue-600/10 border border-blue-500/20 rounded-3xl group hover:bg-blue-600/20 transition-all">
                        <div class="flex items-center gap-4">
                            <span class="text-2xl md:text-3xl text-blue-400 group-hover:scale-125 transition-transform">🎫</span>
                            <div class="text-left">
                                <h3 class="text-base md:text-lg font-black text-white leading-tight">Mis Boletas</h3>
                                <p class="text-xs md:text-sm text-slate-400">Consulta tus números y estado.</p>
                            </div>
                        </div>
                        <span class="text-blue-400 group-hover:translate-x-2 transition-transform">→</span>
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
                const res = await fetch('assets/data/colombia.json');
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
                window.location.href = 'admin/index.php?auth=login';
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
            btn.textContent = 'Guardando...';

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
            window.location.href = 'index.php';
        }

        init();
    </script>
</body>
</html>
