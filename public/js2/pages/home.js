/**
 * home.js
 * Lógica exclusiva de la página de inicio (Hero slider, filtros, carga de rifas).
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Inicializar Hero Slider
    initHeroSlider();

    // 2. Inicializar componentes
    Auth.check();
    loadLotteries();
    loadGeography();
    Raffles.loadRaffles();

    // 3. Event Listeners para Filtros
    setupFilters();
});

/* =========================================================================
   HERO SLIDER
   ========================================================================= */
function initHeroSlider() {
    var AUTOPLAY_MS  = 5500;
    var TRANSITION_MS = 750;
    var track      = document.getElementById('sliderTrack');
    if (!track) return;

    var slides     = Array.from(track.querySelectorAll('.hero-slide'));
    var total      = slides.length;
    var dotsEl     = document.getElementById('sliderDots');
    var thumbsEl   = document.getElementById('sliderThumbs');
    var progressEl = document.getElementById('sliderProgress');
    var prevBtn    = document.getElementById('sliderPrev');
    var nextBtn    = document.getElementById('sliderNext');
    var current    = 0;
    var autoTimer  = null;
    var busy       = false;

    if (slides.length === 0) return;

    /* Dots */
    slides.forEach(function(_, i) {
        var d = document.createElement('button');
        d.className = 'hero-slider__dot' + (i === 0 ? ' is-active' : '');
        d.setAttribute('aria-label', 'Slide ' + (i + 1));
        d.addEventListener('click', function() { goTo(i); restart(); });
        dotsEl.appendChild(d);
    });

    /* Thumb clicks */
    if (thumbsEl) {
        thumbsEl.querySelectorAll('.hero-slider__thumb').forEach(function(t, i) {
            t.addEventListener('click', function() { goTo(i); restart(); });
        });
    }

    function goTo(index) {
        if (busy || index === current) return;
        busy = true;
        slides[current].classList.remove('is-active');
        current = ((index % total) + total) % total;
        track.style.transform = 'translateX(-' + (current * 100) + '%)';
        slides[current].classList.add('is-active');
        
        dotsEl.querySelectorAll('.hero-slider__dot').forEach(function(d, i) { 
            d.classList.toggle('is-active', i === current); 
        });
        
        if (thumbsEl) {
            thumbsEl.querySelectorAll('.hero-slider__thumb').forEach(function(t, i) { 
                t.classList.toggle('is-active', i === current); 
            });
        }
        
        setTimeout(function() { busy = false; }, TRANSITION_MS);
        resetProgress();
    }

    if (prevBtn) prevBtn.addEventListener('click', function() { goTo(current - 1); restart(); });
    if (nextBtn) nextBtn.addEventListener('click', function() { goTo(current + 1); restart(); });

    /* Touch */
    var tx = 0;
    var sl = document.getElementById('heroSlider');
    if (sl) {
        sl.addEventListener('touchstart', function(e) { tx = e.touches[0].clientX; }, { passive: true });
        sl.addEventListener('touchend', function(e) {
            var dx = e.changedTouches[0].clientX - tx;
            if (Math.abs(dx) > 50) { goTo(dx < 0 ? current + 1 : current - 1); restart(); }
        }, { passive: true });
        sl.addEventListener('mouseenter', function() { clearInterval(autoTimer); });
        sl.addEventListener('mouseleave', restart);
    }

    /* Progress */
    function resetProgress() {
        if (!progressEl) return;
        progressEl.style.transition = 'none';
        progressEl.style.width = '0%';
        requestAnimationFrame(function() {
            requestAnimationFrame(function() {
                progressEl.style.transition = 'width ' + AUTOPLAY_MS + 'ms linear';
                progressEl.style.width = '100%';
            });
        });
    }

    /* Autoplay */
    function start() { autoTimer = setInterval(function() { goTo(current + 1); }, AUTOPLAY_MS); }
    function restart() { clearInterval(autoTimer); start(); }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowLeft')  { goTo(current - 1); restart(); }
        if (e.key === 'ArrowRight') { goTo(current + 1); restart(); }
    });

    resetProgress();
    start();
}

/* =========================================================================
   GEOGRAFÍA Y LOTERÍAS
   ========================================================================= */
let colombiaData = [];
async function loadGeography() {
    try {
        const res = await fetch(BASE_PATH + '/public/assets/data/colombia.json');
        colombiaData = await res.json();

        const deptSelect = document.getElementById('filter-dept');
        if (!deptSelect) return;

        deptSelect.innerHTML = '<option value="">Selecciona Depto</option>' +
            colombiaData.map(d => `<option value="${d.departamento}">${d.departamento}</option>`).join('');

        deptSelect.addEventListener('change', (e) => {
            const dept = colombiaData.find(d => d.departamento === e.target.value);
            const citySelect = document.getElementById('filter-city');
            if (dept) {
                citySelect.innerHTML = '<option value="">Selecciona Ciudad</option>' +
                    dept.ciudades.map(c => `<option value="${c}">${c}</option>`).join('');
                citySelect.disabled = false;
            } else {
                citySelect.innerHTML = '<option value="">Primero selecciona un departamento</option>';
                citySelect.disabled = true;
            }
        });
    } catch (e) {
        console.error('Error loading geography:', e);
    }
}

async function loadLotteries() {
    try {
        const response = await API.get('/lotteries/index.php');
        if (response.success) {
            const select = document.getElementById('filter-lottery');
            if (!select) return;
            const options = response.data.map(l => `<option value="${l.id}">${l.name}</option>`).join('');
            select.innerHTML = '<option value="">Selecciona Lotería</option>' + options;
        }
    } catch (e) {
        console.error('Error loading lotteries:', e);
    }
}

/* =========================================================================
   FILTROS Y RIFAS
   ========================================================================= */
function getActiveFilters() {
    return {
        search: document.getElementById('search-input')?.value || '',
        department: document.getElementById('filter-dept')?.value || '',
        city: document.getElementById('filter-city')?.value || '',
        min_price: document.getElementById('filter-min-price')?.value || '',
        max_price: document.getElementById('filter-max-price')?.value || '',
        lottery_id: document.getElementById('filter-lottery')?.value || ''
    };
}

let debounceTimer;
function handleFilter() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        Raffles.loadRaffles(getActiveFilters());
    }, 400);
}

window.handleFilter = handleFilter; // Expose to HTML

window.clearFilters = function() {
    document.getElementById('search-input').value = '';
    document.getElementById('filter-dept').value = '';
    document.getElementById('filter-city').value = '';
    document.getElementById('filter-min-price').value = '';
    document.getElementById('filter-max-price').value = '';
    document.getElementById('filter-lottery').value = '';
    
    const citySelect = document.getElementById('filter-city');
    if (citySelect) {
        citySelect.innerHTML = '<option value="">Primero selecciona un departamento</option>';
        citySelect.disabled = true;
    }
    
    Raffles.loadRaffles();
};

function setupFilters() {
    const searchInput = document.getElementById('search-input');
    if (searchInput) searchInput.addEventListener('input', handleFilter);

    const citySelect = document.getElementById('filter-city');
    if (citySelect) citySelect.addEventListener('change', handleFilter);

    ['filter-lottery', 'filter-min-price', 'filter-max-price'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', handleFilter);
    });

    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            const orderMap = {
                populares: 'sold_percentage',
                proximas: 'draw_date',
                nuevas: 'created_at',
                destacadas: 'views'
            };
            const filters = getActiveFilters();
            filters.order_by = orderMap[tab.dataset.tab] || 'views';
            Raffles.loadRaffles(filters);
        });
    });
}

function toggleLoading(isLoading) {
    const container = document.getElementById('raffles-container');
    if (!container) return;
    
    if (isLoading) {
        container.innerHTML = Array(4).fill(0).map(() => `
            <div class="raffle-card animate-pulse">
                <div class="raffle-card__image bg-slate-800/50 h-[220px]"></div>
                <div class="raffle-card__content space-y-4">
                    <div class="h-6 bg-slate-800/50 rounded w-3/4"></div>
                    <div class="h-4 bg-slate-800/50 rounded w-1/2"></div>
                    <div class="h-10 bg-slate-800/50 rounded"></div>
                </div>
            </div>
        `).join('');
    }
}

const Raffles = {
    async loadRaffles(filters = {}) {
        toggleLoading(true);
        try {
            const response = await API.get('/raffles/index.php', filters);
            if (response.success) {
                const raffles = response.data.raffles || [];
                const container = document.getElementById('raffles-container');
                if (!container) return;

                if (raffles.length === 0) {
                    container.innerHTML = `
                        <div class="col-span-full py-20 text-center fade-in">
                            <div class="text-6xl mb-6">🏜️</div>
                            <h3 class="text-2xl font-black text-white mb-2">No encontramos resultados</h3>
                            <p class="text-slate-400">Intenta ajustar los filtros de búsqueda.</p>
                        </div>
                    `;
                    return;
                }

                container.innerHTML = raffles.map((r, index) => {
                    const daysRemaining = Math.max(0, Math.floor((new Date(r.draw_date) - new Date()) / (1000 * 60 * 60 * 24)));
                    
                    return `
                    <div class="raffle-card group fade-in" style="animation-delay: ${index * 0.1}s">
                        <div class="raffle-card__image">
                            <img src="${Utils.fixUrl(r.image_url)}" alt="${r.name}" loading="lazy" class="group-hover:scale-110 transition-transform duration-700 ease-out">
                            <span class="raffle-card__badge">${r.sold_percentage || 0}% vendido</span>
                            <div class="absolute bottom-0 left-0 right-0 h-1/2 bg-gradient-to-t from-slate-900 via-slate-900/40 to-transparent"></div>
                        </div>
                        <div class="raffle-card__content">
                            <h3 class="raffle-card__title group-hover:text-blue-400 transition-colors">${r.name}</h3>
                            <p class="raffle-card__city">📍 ${r.city}${r.department ? ', ' + r.department : ''}</p>
                            <div class="raffle-card__info">
                                <div class="raffle-card__price">${Utils.formatPrice(r.ticket_price)}<span>por boleto</span></div>
                                <div class="raffle-card__date">${Utils.formatDate(r.draw_date)}</div>
                            </div>
                            <div class="progress-bar group-hover:h-2 transition-all"><div class="progress-bar__fill" style="width: ${r.sold_percentage}%"></div></div>
                            <div class="flex justify-between items-center mt-2">
                                <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest">${r.sold_tickets} / ${r.total_tickets} Vendidos</span>
                                <span class="text-[10px] font-black text-blue-500 uppercase tracking-widest">${daysRemaining} Días restantes</span>
                            </div>
                            <button class="btn btn--primary w-full mt-6 shadow-blue-500/20 group-hover:shadow-blue-500/40 group-hover:-translate-y-1 transition-all" onclick="window.location.href='${BASE_PATH}/public/raffle.php?id=${r.id}'">
                                Participar Ahora &rarr;
                            </button>
                        </div>
                    </div>
                `}).join('');
            }
        } catch (e) {
            console.error('Error:', e);
            Utils.showNotification('Error al cargar las rifas', 'error');
        }
    }
};
