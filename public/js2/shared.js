/**
 * MisRifas - Shared JavaScript Modules
 * Cargar este archivo ANTES que cualquier script inline de pagina.
 * Provee: API client, Utils, log(), fixUrl()
 */

// ============================================
// CONFIGURACION GLOBAL
// ============================================
const API_BASE_URL = '/api';
const DEBUG = false;

// Funcion log global (usada tambien por ws-client.js)
window.log = function(...args) { if (DEBUG) console.log(...args); };

// ============================================
// MODULO: API Client
// ============================================
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

        try {
            const response = await fetch(API_BASE_URL + endpoint, config);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Error en la peticion');
            }
            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    },

    async get(endpoint, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? endpoint + '?' + queryString : endpoint;
        return this.request(url, { method: 'GET' });
    },

    async post(endpoint, data = {}) {
        return this.request(endpoint, { method: 'POST', body: JSON.stringify(data) });
    },

    async put(endpoint, data = {}) {
        return this.request(endpoint, { method: 'PUT', body: JSON.stringify(data) });
    },

    async delete(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    }
};

// ============================================
// MODULO: Utilidades
// ============================================
const Utils = {
    formatPrice(price) {
        return new Intl.NumberFormat('es-CO', {
            style: 'currency',
            currency: 'COP',
            minimumFractionDigits: 0
        }).format(price);
    },

    formatDate(dateString) {
        const date = new Date(dateString);
        return new Intl.DateTimeFormat('es-CO', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }).format(date);
    },

    formatDateShort(dateString) {
        const date = new Date(dateString);
        return new Intl.DateTimeFormat('es-CO', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        }).format(date);
    },

    getTimeRemaining(dateString) {
        const now = new Date();
        const target = new Date(dateString);
        const diff = target - now;

        if (diff <= 0) return { expired: true };

        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);

        return { expired: false, days, hours, minutes, seconds };
    },

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    validatePhone(phone) {
        const regex = /^(\+?57)?3\d{9}$/;
        return regex.test(phone);
    },

    validateEmail(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    },

    showNotification(message, type = 'info') {
        const existing = document.querySelectorAll('.notification');
        existing.forEach(n => n.remove());

        const notification = document.createElement('div');
        notification.className = 'notification notification--' + type;
        notification.innerHTML = '<p class="font-medium">' + message + '</p>';
        notification.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            max-width: 450px;
            width: 90%;
            padding: 20px 30px;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3);
            z-index: 9999;
            animation: fadeIn 0.3s ease;
            text-align: center;
            font-size: 16px;
            background: rgba(30, 41, 59, 0.95);
            backdrop-filter: blur(12px);
            color: #f8fafc;
        `;

        if (type === 'error') {
            notification.style.border = '2px solid #ef4444';
            notification.style.color = '#fca5a5';
        } else if (type === 'success') {
            notification.style.border = '2px solid #10b981';
            notification.style.color = '#6ee7b7';
        } else {
            notification.style.border = '2px solid #3b82f6';
            notification.style.color = '#93c5fd';
        }

        document.body.appendChild(notification);
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease-out forwards';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    },

    saveToStorage(key, data) {
        try {
            localStorage.setItem(key, JSON.stringify(data));
        } catch (e) {
            console.error('Error saving to localStorage:', e);
        }
    },

    getFromStorage(key) {
        try {
            const data = localStorage.getItem(key);
            return data ? JSON.parse(data) : null;
        } catch (e) {
            console.error('Error reading from localStorage:', e);
            return null;
        }
    }
};

// ============================================
// UTILIDAD: fixUrl para imagenes
// ============================================
window.fixUrl = function(url) {
    if (!url) return '';
    if (url.startsWith('http')) return url;
    const base = typeof BASE_PATH !== 'undefined' ? BASE_PATH : '';
    if (url.startsWith('/public/') || url.startsWith('public/')) {
        return base + '/' + url.replace(/^\/?public\//, 'public/');
    }
    return base + '/public/' + url.replace(/^\//, '');
};

// ============================================
// ANIMACIONES GLOBALES
// ============================================
document.addEventListener('DOMContentLoaded', () => {
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeIn {
            from { opacity: 0; transform: translate(-50%, -50%) scale(0.9); }
            to { opacity: 1; transform: translate(-50%, -50%) scale(1); }
        }
        @keyframes slideOut {
            from { opacity: 1; transform: translate(-50%, -50%) scale(1); }
            to { opacity: 0; transform: translate(-50%, -50%) scale(0.9); }
        }
    `;
    document.head.appendChild(style);
});

// Exportar a window
window.API = API;
window.Utils = Utils;
