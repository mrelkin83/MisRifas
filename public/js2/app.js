/**
 * MisRifas - JavaScript Vanilla App
 * Sistema modular sin frameworks
 */

// ============================================
// CONFIGURACIÓN GLOBAL
// ============================================

const API_BASE_URL = '/api';
const DEBUG = false;
function log(...args) { if (DEBUG) console.log(...args); }

// ============================================
// MÓDULO: API Client
// ============================================

const API = {
    /**
     * Realizar petición HTTP
     */
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
            const response = await fetch(`${API_BASE_URL}${endpoint}`, config);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Error en la petición');
            }

            return data;

        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    },

    /**
     * GET request
     */
    async get(endpoint, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `${endpoint}?${queryString}` : endpoint;
        return this.request(url, { method: 'GET' });
    },

    /**
     * POST request
     */
    async post(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },

    /**
     * PUT request
     */
    async put(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    },

    /**
     * DELETE request
     */
    async delete(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    }
};

// ============================================
// MÓDULO: Gestión de Estado
// ============================================

const State = {
    data: {
        raffles: [],
        currentRaffle: null,
        user: null,
        cart: [],
    },

    listeners: {},

    /**
     * Obtener valor del estado
     */
    get(key) {
        return this.data[key];
    },

    /**
     * Establecer valor del estado
     */
    set(key, value) {
        this.data[key] = value;
        this.notify(key, value);
    },

    /**
     * Suscribirse a cambios
     */
    subscribe(key, callback) {
        if (!this.listeners[key]) {
            this.listeners[key] = [];
        }
        this.listeners[key].push(callback);
    },

    /**
     * Notificar cambios
     */
    notify(key, value) {
        if (this.listeners[key]) {
            this.listeners[key].forEach(callback => callback(value));
        }
    }
};

// ============================================
// MÓDULO: Utilidades
// ============================================

const Utils = {
    /**
     * Formatear precio colombiano
     */
    formatPrice(price) {
        return new Intl.NumberFormat('es-CO', {
            style: 'currency',
            currency: 'COP',
            minimumFractionDigits: 0
        }).format(price);
    },

    /**
     * Formatear fecha
     */
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

    /**
     * Calcular tiempo restante
     */
    getTimeRemaining(dateString) {
        const now = new Date();
        const target = new Date(dateString);
        const diff = target - now;

        if (diff <= 0) {
            return { expired: true };
        }

        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);

        return { expired: false, days, hours, minutes, seconds };
    },

    /**
     * Debounce
     */
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

    /**
     * Validar teléfono colombiano
     */
    validatePhone(phone) {
        const regex = /^(\+?57)?3\d{9}$/;
        return regex.test(phone);
    },

    /**
     * Validar email
     */
    validateEmail(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    },

    /**
     * Mostrar notificación
     */
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification--${type}`;
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            background: ${type === 'error' ? '#f44336' : type === 'success' ? '#4caf50' : '#2196f3'};
            color: white;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            z-index: 10000;
            animation: slideIn 0.3s ease-out;
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    },

    /**
     * Guardar en localStorage
     */
    saveToStorage(key, data) {
        try {
            localStorage.setItem(key, JSON.stringify(data));
        } catch (e) {
            console.error('Error saving to localStorage:', e);
        }
    },

    /**
     * Obtener de localStorage
     */
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
// MÓDULO: Rifas
// ============================================

const Raffles = {
    /**
     * Cargar rifas activas
     */
    async loadRaffles(filters = {}) {
        try {
            log('Fetching raffles...', filters);
            const response = await API.get('/raffles/index.php', filters);
            log('API Response:', response);

            if (response.success) {
                const rafflesData = response.data.raffles || response.data;
                log('Raffles data:', rafflesData);
                State.set('raffles', rafflesData);
                this.renderRaffles(rafflesData);

                if (response.data.pagination) {
                    this.renderPagination(response.data.pagination);
                }
            }

        } catch (error) {
            console.error('Error loading raffles:', error);
            Utils.showNotification('Error al cargar las rifas', 'error');
        }
    },

    /**
     * Renderizar rifas
     */
    renderRaffles(raffles) {
        log('renderRaffles called with:', raffles);
        const container = document.getElementById('raffles-container');
        log('Container:', container);
        if (!container) {
            console.error('Container not found!');
            return;
        }

        if (raffles.length === 0) {
            container.innerHTML = '<p class="no-results">No se encontraron rifas</p>';
            return;
        }

        const html = raffles.map(raffle => `
            <div class="raffle-card" data-id="${raffle.id}">
                <div class="raffle-card__image">
                    <img src="${raffle.image_url}" alt="${raffle.name}" loading="lazy" onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22200%22 fill=%22%23d1d5db%22%3E%3Crect width=%22400%22 height=%22200%22/%3E%3Ctext x=%22200%22 y=%22100%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2216%22 fill=%22%236b7280%22%3ESin imagen%3C/text%3E%3C/svg%3E';">
                    <span class="raffle-card__badge">${raffle.sold_percentage || 0}% vendido</span>
                </div>
                <div class="raffle-card__content">
                    <h3 class="raffle-card__title">${raffle.name}</h3>
                    <p class="raffle-card__city">${raffle.city}</p>
                    <div class="raffle-card__info">
                        <div class="raffle-card__price">
                            ${Utils.formatPrice(raffle.ticket_price)}
                            <span>por boleto</span>
                        </div>
                        <div class="raffle-card__date">
                            ${Utils.formatDate(raffle.draw_date)}
                        </div>
                    </div>
                    <div class="raffle-card__progress">
                        <div class="progress-bar">
                            <div class="progress-bar__fill" style="width: ${raffle.sold_percentage}%"></div>
                        </div>
                        <span>${raffle.sold_tickets} / ${raffle.total_tickets} boletos vendidos</span>
                    </div>
                    <button class="btn btn--primary" onclick="Raffles.viewDetails(${raffle.id})">
                        Ver detalles
                    </button>
                </div>
            </div>
        `).join('');
        
        log('Generated HTML length:', html.length);
        container.innerHTML = html;
        log('Container updated');
    },

    /**
     * Ver detalles de rifa
     */
    async viewDetails(raffleId) {
        try {
            const response = await API.get('/raffles/details.php', { id: raffleId });

            if (response.success) {
                const raffleData = response.data.raffle || response.data;
                State.set('currentRaffle', raffleData);
                window.location.href = `/raffle?id=${raffleId}`;
            }

        } catch (error) {
            Utils.showNotification('Error al cargar los detalles', 'error');
        }
    },

    /**
     * Compartir rifa
     */
    share(raffleId, platform) {
        const url = encodeURIComponent(`${window.location.origin}/raffle.php?id=${raffleId}`);
        const currentRaffle = State.get('currentRaffle');
        const raffleName = encodeURIComponent(currentRaffle?.name || 'Mira esta rifa');
        const price = encodeURIComponent(currentRaffle?.ticket_price || '');
        const text = encodeURIComponent(`¡Mira esta rifa increíble! ${raffleName} - ${price}`);

        const urls = {
            whatsapp: `https://wa.me/?text=${text}%20${url}`,
            facebook: `https://www.facebook.com/sharer/sharer.php?u=${url}`,
            twitter: `https://twitter.com/intent/tweet?text=${text}&url=${url}`,
            telegram: `https://t.me/share/url?url=${url}&text=${text}`,
            instagram: null
        };

        if (platform === 'instagram') {
            alert('Comparte esta rifa en Instagram copiando el enlace manualmente:\n\n' + decodeURIComponent(url));
            API.post('/raffles/share.php', { raffle_id: raffleId, platform });
        } else if (urls[platform]) {
            window.open(urls[platform], '_blank', 'width=600,height=400');

            API.post('/raffles/share.php', { raffle_id: raffleId, platform });
        }
    }
};

// ============================================
// MÓDULO: Tickets
// ============================================

const Tickets = {
    selectedTickets: [],

    /**
     * Cargar boletos disponibles
     */
    async loadAvailableTickets(raffleId) {
        try {
            const response = await API.get('/tickets/available.php', { raffle_id: raffleId });

            if (response.success) {
                this.renderTicketGrid(response.data);
            }

        } catch (error) {
            Utils.showNotification('Error al cargar los boletos', 'error');
        }
    },

    /**
     * Renderizar grilla de boletos
     */
    renderTicketGrid(tickets) {
        const container = document.getElementById('tickets-grid');
        if (!container) return;

        container.innerHTML = tickets.map(ticket => `
            <div class="ticket ${ticket.status === 'available' ? 'ticket--available' : 'ticket--' + ticket.status}"
                 data-id="${ticket.id}"
                 data-number="${ticket.ticket_number}"
                 onclick="Tickets.selectTicket('${ticket.ticket_number}', '${ticket.status}')">
                <span class="ticket__number">${ticket.ticket_number}</span>
            </div>
        `).join('');
    },

    /**
     * Seleccionar boleto
     */
    selectTicket(ticketNumber, status) {
        if (status !== 'available') {
            Utils.showNotification('Este boleto no está disponible', 'error');
            return;
        }

        const index = this.selectedTickets.indexOf(ticketNumber);

        if (index > -1) {
            this.selectedTickets.splice(index, 1);
        } else {
            if (this.selectedTickets.length >= 10) {
                Utils.showNotification('Máximo 10 boletos por compra', 'error');
                return;
            }
            this.selectedTickets.push(ticketNumber);
        }

        this.updateSelectedUI();
    },

    /**
     * Actualizar UI de seleccionados
     */
    updateSelectedUI() {
        // Actualizar visualización de boletos seleccionados
        const selectedContainer = document.getElementById('selected-tickets');
        if (selectedContainer) {
            selectedContainer.innerHTML = this.selectedTickets.length > 0
                ? `Boletos seleccionados: ${this.selectedTickets.join(', ')}`
                : 'Selecciona un boleto';
        }

        // Actualizar clases CSS
        document.querySelectorAll('.ticket').forEach(ticket => {
            const number = ticket.dataset.number;
            if (this.selectedTickets.includes(number)) {
                ticket.classList.add('ticket--selected');
            } else {
                ticket.classList.remove('ticket--selected');
            }
        });
    },

    /**
     * Reservar boleto
     */
    async reserveTicket(raffleId, userData) {
        if (this.selectedTickets.length === 0) {
            Utils.showNotification('Selecciona al menos un boleto', 'error');
            return;
        }

        try {
            const tickets = [...this.selectedTickets];

            const response = await API.post('/tickets/reserve.php', {
                raffle_id: raffleId,
                ticket_numbers: tickets,
                user: userData,
                reservation_hours: 2
            });

            if (response.success) {
                Utils.showNotification('¡Boletos reservados exitosamente!', 'success');

                // Guardar datos de pago
                Utils.saveToStorage('reservation', response.data);

                // Redirigir a instrucciones de pago
                window.location.href = `/payment.php?ticket=${response.data.ticket.id}`;
            }

        } catch (error) {
            Utils.showNotification(error.message || 'Error al reservar el boleto', 'error');
        }
    }
};

// ============================================
// MÓDULO: Buscador
// ============================================

const Search = {
    /**
     * Inicializar buscador
     */
    init() {
        const searchInput = document.getElementById('search-input');
        if (!searchInput) return;

        searchInput.addEventListener('input', Utils.debounce((e) => {
            this.performSearch(e.target.value);
        }, 500));
    },

    /**
     * Realizar búsqueda
     */
    async performSearch(query) {
        if (query.length < 3) {
            Raffles.loadRaffles();
            return;
        }

        await Raffles.loadRaffles({ search: query });
    }
};

// ============================================
// MÓDULO: Filtros
// ============================================

const Filters = {
    currentFilters: {},

    /**
     * Inicializar filtros
     */
    init() {
        const filterForm = document.getElementById('filters-form');
        if (!filterForm) return;

        filterForm.addEventListener('submit', (e) => {
            e.preventDefault();
            this.applyFilters();
        });
    },

    /**
     * Aplicar filtros
     */
    async applyFilters() {
        const city = document.getElementById('filter-city')?.value;
        const minPrice = document.getElementById('filter-min-price')?.value;
        const maxPrice = document.getElementById('filter-max-price')?.value;

        this.currentFilters = {
            ...(city && { city }),
            ...(minPrice && { min_price: minPrice }),
            ...(maxPrice && { max_price: maxPrice }),
        };

        await Raffles.loadRaffles(this.currentFilters);
    },

    /**
     * Limpiar filtros
     */
    clearFilters() {
        this.currentFilters = {};
        document.getElementById('filters-form')?.reset();
        Raffles.loadRaffles();
    }
};

// ============================================
// INICIALIZACIÓN
// ============================================

document.addEventListener('DOMContentLoaded', () => {
    log('MisRifas initialized');
    
    // Inicializar módulos
    Search.init();
    Filters.init();

    // Cargar rifas en la página principal
    if (document.getElementById('raffles-container')) {
        log('Loading raffles...');
        Raffles.loadRaffles().then(() => {
            log('Raffles loaded');
        }).catch(err => {
            console.error('Error loading raffles:', err);
        });
    }

    // Agregar estilos de animación
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
});

// Exportar módulos globalmente
window.MisRifas = {
    API,
    State,
    Utils,
    Raffles,
    Tickets,
    Search,
    Filters
};
