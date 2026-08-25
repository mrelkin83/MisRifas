/**
 * core.js
 * Utilidades compartidas, cliente API y manejo de sesión para MisRifas.
 */

// Cliente API estandarizado
const API = {
    async request(endpoint, options = {}) {
        const config = {
            headers: { 
                'Content-Type': 'application/json', 
                'X-Requested-With': 'XMLHttpRequest', 
                ...options.headers 
            },
            ...options
        };
        const response = await fetch('/api' + endpoint, config);
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Error en la petición');
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

// Utilidades generales
const Utils = {
    formatPrice(p) { 
        return new Intl.NumberFormat('es-CO', { 
            style: 'currency', 
            currency: 'COP', 
            minimumFractionDigits: 0 
        }).format(p); 
    },
    formatDate(d) { 
        return new Intl.DateTimeFormat('es-CO', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        }).format(new Date(d)); 
    },
    showNotification(msg, type = 'info') {
        const existing = document.querySelectorAll('.notification');
        existing.forEach(n => n.remove());
        const n = document.createElement('div');
        n.className = `notification notification--${type}`;
        n.innerHTML = `<p class="font-medium">${msg}</p>`;
        document.body.appendChild(n);
        setTimeout(() => n.remove(), 3000);
    },
    fixUrl(url) {
        if (!url) return 'https://images.unsplash.com/photo-1540317580384-e5d4361660bd?w=800';
        if (url.startsWith('http')) return url;
        return (url.startsWith('/') ? '' : '/') + url;
    }
};

// Autenticación y Sesión
const Auth = {
    logout() {
        localStorage.removeItem('misrifas_token');
        localStorage.removeItem('misrifas_user');
        window.location.reload();
    },
    
    check() {
        const userStr = localStorage.getItem('misrifas_user');
        if (userStr) {
            try {
                const user = JSON.parse(userStr);
                const authButtons = document.getElementById('auth-buttons');
                const userMenu = document.getElementById('user-menu');
                const userName = document.getElementById('user-name');
                const userAvatar = document.getElementById('user-avatar');

                if (authButtons) authButtons.classList.add('hidden');
                if (userMenu) userMenu.classList.remove('hidden');
                if (userName) userName.textContent = user.full_name;
                if (userAvatar) userAvatar.textContent = user.full_name.charAt(0).toUpperCase();
            } catch (e) {
                console.error('Error parsing user data:', e);
            }
        }
    }
};

// Exportar globalmente si se necesita en scripts inline remanentes
window.API = API;
window.Utils = Utils;
window.Auth = Auth;
