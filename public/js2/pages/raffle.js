/**
 * raffle.js
 * Lógica exclusiva de la página de detalle de Rifa (Galería, boletos, reserva).
 */

let currentRaffle = null;
let currentGalleryIndex = 0;
let selectedTicket = null;
let countdownInterval = null;

const urlParams = new URLSearchParams(window.location.search);
const raffleId = urlParams.get('id');

document.addEventListener('DOMContentLoaded', () => {
    if (!raffleId) {
        window.location.href = '/';
        return;
    }
    
    // Configurar event listeners iniciales
    setupRaffleListeners();
    
    // Cargar info de la rifa
    loadRaffleDetails();
});

/* =========================================================================
   CARGA DE DATOS
   ========================================================================= */
async function loadRaffleDetails() {
    try {
        const response = await API.get('/raffles/details.php', { id: raffleId });
        if (response.success) {
            currentRaffle = response.data;
            renderRaffleDetails();
            loadTickets();
            startCountdown();
            document.getElementById('raffle-content').classList.remove('hidden');
        } else {
            showError();
        }
    } catch (error) {
        showError();
    }
}

function showError() {
    document.getElementById('error-msg').classList.remove('hidden');
    setTimeout(() => window.location.href = '/', 2000);
}

/* =========================================================================
   RENDERIZADO DE VISTA
   ========================================================================= */
function renderRaffleDetails() {
    const r = currentRaffle;
    
    // Galería
    const images = r.images && r.images.length > 0 ? r.images.map(img => img.image_url) : [];
    if (r.image_url) images.unshift(r.image_url);
    const uniqueImages = [...new Set(images)];
    
    const track = document.getElementById('gallery-track');
    const dots = document.getElementById('gallery-dots');
    const prevBtn = document.getElementById('gallery-prev');
    const nextBtn = document.getElementById('gallery-next');
    
    if (uniqueImages.length > 0) {
        track.innerHTML = uniqueImages.map(url => 
            `<div class="min-w-full flex-shrink-0 flex items-center justify-center bg-slate-900">
                <img src="${url}" alt="${r.name || ''}" class="max-w-full max-h-[500px] w-auto h-auto object-contain">
            </div>`
        ).join('');
        
        dots.innerHTML = uniqueImages.map((_, i) => 
            `<button onclick="goToImage(${i})" class="w-2 h-2 rounded-full transition-all ${i === 0 ? 'bg-white w-6' : 'bg-white/40'}"></button>`
        ).join('');
        
        prevBtn.style.display = uniqueImages.length > 1 ? 'flex' : 'none';
        nextBtn.style.display = uniqueImages.length > 1 ? 'flex' : 'none';
        currentGalleryIndex = 0;
    } else {
        track.innerHTML = '<div class="min-w-full flex-shrink-0"><div class="w-full h-[400px] bg-slate-800 flex items-center justify-center text-slate-500 text-6xl">🎟️</div></div>';
        dots.innerHTML = '';
        prevBtn.style.display = 'none';
        nextBtn.style.display = 'none';
    }
    
    // Textos
    document.getElementById('raffle-title').textContent = r.name || '';
    document.getElementById('raffle-city').textContent = '📍 ' + (r.city || '');
    document.getElementById('ticket-price').textContent = Utils.formatPrice(r.ticket_price || 0);
    document.getElementById('draw-date').textContent = Utils.formatDate(r.draw_date);
    document.getElementById('lottery-name').textContent = r.lottery_name || '';
    document.getElementById('raffle-description').textContent = r.description || '';

    // Progreso
    const soldPct = r.sold_percentage || 0;
    document.getElementById('sold-percentage-badge').textContent = soldPct + '% vendido';
    document.getElementById('progress-fill').style.width = soldPct + '%';
    document.getElementById('sold-count').textContent = (r.sold_tickets || 0) + ' / ' + (r.total_tickets || 0);
    document.title = (r.name || 'Rifa') + ' - MisRifas';
}

/* =========================================================================
   BOLETOS (TICKETS)
   ========================================================================= */
async function loadTickets() {
    try {
        const response = await API.get('/tickets/available.php', { raffle_id: raffleId });
        if (response.success) {
            renderTickets(response.data);
        }
    } catch (error) {
        document.getElementById('tickets-grid').innerHTML = '<p class="col-span-full text-center text-red-500 py-8">Error al cargar los boletos</p>';
    }
}

function renderTickets(tickets) {
    const grid = document.getElementById('tickets-grid');
    grid.innerHTML = '';
    
    if (!tickets || tickets.length === 0) {
        grid.innerHTML = '<p class="col-span-full text-center text-gray-500 py-8">No hay boletos disponibles</p>';
        return;
    }
    
    // Usando fragment para optimizar el DOM insertion
    const fragment = document.createDocumentFragment();
    
    tickets.forEach((ticket, i) => {
        const div = document.createElement('div');
        let statusClass = 'ticket-btn--paid'; // default fallback
        
        if (ticket.status === 'available') statusClass = 'ticket-btn--available hover:-translate-y-1';
        else if (ticket.status === 'reserved') statusClass = 'ticket-btn--reserved';
        
        div.className = `ticket-btn ${statusClass} fade-in`;
        div.style.animationDelay = `${(i % 50) * 0.02}s`; // Efecto cascada optimizado
        
        const opps = typeof ticket.opportunities === 'string' ? JSON.parse(ticket.opportunities) : (ticket.opportunities || []);
        
        let htmlContent = '<div class="text-center">';
        htmlContent += `<div class="text-xs opacity-70 mb-1 font-bold tracking-wider">#${ticket.ticket_number}</div>`;
        
        if (opps.length > 0) {
            htmlContent += '<div class="flex flex-wrap gap-1 justify-center">';
            opps.slice(0, 3).forEach(num => { // Mostrar max 3 en el grid para no desbordar
                htmlContent += `<span class="inline-block bg-white/20 rounded px-1.5 py-0.5 text-[10px] font-mono font-bold">${num}</span>`;
            });
            if(opps.length > 3) htmlContent += `<span class="text-[10px] opacity-70">+${opps.length - 3}</span>`;
            htmlContent += '</div>';
        }
        htmlContent += '</div>';
        
        div.innerHTML = htmlContent;
        div.dataset.id = ticket.id;
        div.dataset.number = ticket.ticket_number;
        div.dataset.opportunities = typeof ticket.opportunities === 'string' ? ticket.opportunities : JSON.stringify(ticket.opportunities || []);
        div.dataset.status = ticket.status;
        
        if (ticket.status === 'available') {
            div.onclick = () => selectTicket(ticket);
        }
        fragment.appendChild(div);
    });
    
    grid.appendChild(fragment);
}

function selectTicket(ticket) {
    selectedTicket = ticket;
    document.querySelectorAll('.ticket-btn').forEach(t => t.classList.remove('ticket-btn--selected', 'ring-2', 'ring-white', 'scale-105'));
    
    const el = document.querySelector(`[data-number="${ticket.ticket_number}"]`);
    if (el) {
        el.classList.add('ticket-btn--selected', 'ring-2', 'ring-white', 'scale-105');
    }
    
    const opps = typeof ticket.opportunities === 'string' ? JSON.parse(ticket.opportunities) : (ticket.opportunities || []);
    
    document.getElementById('selected-info').innerHTML = `
        <p class="text-slate-300 mb-1 text-sm font-medium uppercase tracking-wider">Boleto Seleccionado:</p>
        <p class="text-5xl font-black text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-emerald-400 font-mono mb-2 drop-shadow-lg">${ticket.ticket_number}</p>
        <p class="text-sm text-emerald-400/80 font-mono tracking-widest bg-emerald-900/30 inline-block px-3 py-1 rounded-lg">Números extra: ${opps.join(', ') || 'Ninguno'}</p>
    `;
    
    document.getElementById('continue-btn').disabled = false;
}

/* =========================================================================
   RESERVA Y PAGO
   ========================================================================= */
function setupRaffleListeners() {
    // Buscador de boletos
    const searchInput = document.getElementById('ticket-search');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const search = e.target.value.toLowerCase();
            document.querySelectorAll('.ticket-btn').forEach(ticket => {
                const number = ticket.dataset.number.toLowerCase();
                ticket.style.display = number.includes(search) ? 'flex' : 'none';
            });
        });
    }

    // Modal triggers
    document.getElementById('continue-btn')?.addEventListener('click', () => {
        if (!selectedTicket) return;
        document.getElementById('modal-ticket-number').textContent = selectedTicket.ticket_number;
        const opps = typeof selectedTicket.opportunities === 'string' ? JSON.parse(selectedTicket.opportunities) : (selectedTicket.opportunities || []);
        document.getElementById('modal-opportunities').textContent = opps.join(', ') || 'Ninguno';
        document.getElementById('purchase-modal').classList.remove('hidden');
    });

    // Form submission
    document.getElementById('purchase-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const name = document.getElementById('buyer-name').value.trim();
        const phone = document.getElementById('buyer-phone').value.trim();
        const email = document.getElementById('buyer-email').value.trim();
        const hours = parseInt(document.getElementById('reservation-hours').value) || 2;

        if (!name || !phone) {
            Utils.showNotification('Completa los campos obligatorios', 'error');
            return;
        }

        const btn = document.getElementById('reserve-btn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner inline-block w-5 h-5 mr-2 border-2 border-white/30 border-t-white rounded-full animate-spin"></span> Procesando...';

        try {
            const data = {
                raffle_id: raffleId,
                ticket_number: selectedTicket.ticket_number,
                user: { name, phone, email: email || null },
                reservation_hours: hours
            };
            const response = await API.post('/tickets/reserve.php', data);
            
            if (response.success) {
                Utils.showNotification('¡Boleto reservado exitosamente!', 'success');
                window.closePurchaseModal();
                
                if (response.data && response.data.ticket && response.data.raffle && response.data.payment_url) {
                    const reservationData = {
                        ticket_id: response.data.ticket.id,
                        ticket_number: response.data.ticket.ticket_number,
                        ticket_price: response.data.raffle.ticket_price,
                        reserved_until: response.data.ticket.reserved_until,
                        raffle_name: response.data.raffle.name
                    };
                    localStorage.setItem('current_reservation', JSON.stringify(reservationData));
                }

                setTimeout(() => {
                    if (response.data && response.data.payment_url) {
                        window.location.href = response.data.payment_url;
                    } else {
                        localStorage.removeItem('current_reservation');
                        window.location.href = (typeof BASE_PATH !== 'undefined' ? BASE_PATH : '') + '/public/mis-boletos.php';
                    }
                }, 1500);
            } else {
                Utils.showNotification(response.message || 'Error al reservar', 'error');
                btn.disabled = false;
                btn.innerHTML = 'Ir a Pagar Seguro 🔒';
            }
        } catch (error) {
            Utils.showNotification(error.message || 'Error al reservar el boleto', 'error');
            btn.disabled = false;
            btn.innerHTML = 'Ir a Pagar Seguro 🔒';
        }
    });
}

window.closePurchaseModal = function() {
    document.getElementById('purchase-modal').classList.add('hidden');
};

/* =========================================================================
   GALERÍA
   ========================================================================= */
window.goToImage = function(index) {
    const track = document.getElementById('gallery-track');
    const dots = document.getElementById('gallery-dots');
    if (!track) return;
    
    currentGalleryIndex = index;
    track.style.transform = `translateX(-${index * 100}%)`;
    
    if (dots) {
        Array.from(dots.children).forEach((d, i) => {
            d.className = `w-2 h-2 rounded-full transition-all ${i === index ? 'bg-white w-6 shadow-[0_0_8px_rgba(255,255,255,0.8)]' : 'bg-white/40'}`;
        });
    }
};

window.nextImage = function() {
    const track = document.getElementById('gallery-track');
    if (!track) return;
    const total = track.children.length;
    goToImage((currentGalleryIndex + 1) % total);
};

window.prevImage = function() {
    const track = document.getElementById('gallery-track');
    if (!track) return;
    const total = track.children.length;
    goToImage((currentGalleryIndex - 1 + total) % total);
};

// Touch/Swipe gallery support
let touchStartX = 0;
let touchEndX = 0;
document.addEventListener('touchstart', e => {
    if (e.target.closest('#gallery-container')) touchStartX = e.changedTouches[0].screenX;
}, { passive: true });

document.addEventListener('touchend', e => {
    if (e.target.closest('#gallery-container')) {
        touchEndX = e.changedTouches[0].screenX;
        const diff = touchStartX - touchEndX;
        if (Math.abs(diff) > 50) {
            if (diff > 0) nextImage(); else prevImage();
        }
    }
}, { passive: true });

/* =========================================================================
   UTILIDADES
   ========================================================================= */
function startCountdown() {
    if (countdownInterval) clearInterval(countdownInterval);
    countdownInterval = setInterval(() => {
        const drawDate = new Date(currentRaffle.draw_date).getTime();
        const now = Date.now();
        const diff = drawDate - now;

        if (diff <= 0) {
            clearInterval(countdownInterval);
            ['days','hours','minutes','seconds'].forEach(id => {
                const el = document.getElementById(id);
                if(el) el.textContent = '0';
            });
            return;
        }

        const d = Math.floor(diff / (1000 * 60 * 60 * 24));
        const h = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const s = Math.floor((diff % (1000 * 60)) / 1000);

        const setTime = (id, val) => {
            const el = document.getElementById(id);
            if(el) el.textContent = val.toString().padStart(2, '0');
        };

        setTime('days', d);
        setTime('hours', h);
        setTime('minutes', m);
        setTime('seconds', s);
    }, 1000);
}

window.shareRaffle = function(platform) {
    const url = window.location.href;
    const text = 'Mira esta rifa: ' + (currentRaffle?.name || '');
    const links = {
        whatsapp: 'https://wa.me/?text=' + encodeURIComponent(text + ' ' + url),
        facebook: 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url)
    };
    if (links[platform]) {
        window.open(links[platform], '_blank', 'width=600,height=400');
    }
};

window.copyLink = function() {
    navigator.clipboard.writeText(window.location.href).then(() => {
        Utils.showNotification('Link copiado al portapapeles', 'success');
    }).catch(() => {
        Utils.showNotification('No se pudo copiar el link', 'error');
    });
};

window.shareOnWhatsApp = function(e) {
    if (e) e.preventDefault();
    const url     = window.location.href;
    const name    = document.getElementById('raffle-title')?.textContent?.trim() 
                    || document.title.replace(' | MisRifas', '').replace('🎫 ', '');
    const message = '🎉 ¡Participa en esta rifa!\n\n' + name + '\n\n' + url;
    window.open('https://wa.me/?text=' + encodeURIComponent(message), '_blank');
};
