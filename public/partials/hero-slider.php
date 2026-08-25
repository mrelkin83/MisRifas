<!-- ===== HERO SLIDER ===== -->
<section class="hero-slider" id="heroSlider" aria-label="Banners promocionales">
    <div class="hero-slider__track" id="sliderTrack">

        <!-- Slide 1 -->
        <div class="hero-slide is-active">
            <div class="hero-slide__bg" style="background-image:url('https://images.unsplash.com/photo-1592921870789-04563d55041c?w=1600&q=80');"></div>
            <div class="hero-slide__overlay"></div>
            <div class="hero-slide__content">
                <span class="hero-slide__tag">&#127903;&#65039; Rifa Destacada del Mes</span>
                <h1 class="hero-slide__title">Gana tu <em>Gran Premio</em><br>esta semana</h1>
                <p class="hero-slide__desc">Participa en las rifas m&aacute;s grandes de Colombia. Boletos desde $5.000 COP. Sorteos verificados con loter&iacute;a oficial.</p>
                <div class="hero-slide__actions">
                    <a href="#search-section" class="hero-slide__btn hero-slide__btn--primary">&#127919; Ver Rifas Activas</a>
                    <a href="<?= BASE_PATH ?>/public/register.php" class="hero-slide__btn hero-slide__btn--ghost">&#128640; Crear mi Rifa</a>
                </div>
            </div>
        </div>

        <!-- Slide 2 -->
        <div class="hero-slide">
            <div class="hero-slide__bg" style="background-image:url('https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=1600&q=80');"></div>
            <div class="hero-slide__overlay" style="background:linear-gradient(135deg,rgba(5,46,22,0.8) 0%,rgba(0,0,0,0.2) 60%,rgba(0,0,0,0.5) 100%);"></div>
            <div class="hero-slide__content">
                <span class="hero-slide__tag">&#128176; Pagos Instant&aacute;neos</span>
                <h1 class="hero-slide__title">Paga con <em>Nequi</em><br>y gana al instante</h1>
                <p class="hero-slide__desc">Integraci&oacute;n directa con Wompi. Tu pago se confirma en segundos y tu boleto queda asegurado sin llamadas ni esperas.</p>
                <div class="hero-slide__actions">
                    <a href="#search-section" class="hero-slide__btn hero-slide__btn--primary" style="background:linear-gradient(135deg,#059669,#10b981);box-shadow:0 8px 30px rgba(16,185,129,0.5);">Ver Rifas &rarr;</a>
                    <a href="<?= BASE_PATH ?>/public/mis-boletos.php" class="hero-slide__btn hero-slide__btn--ghost">Consultar Boletas</a>
                </div>
            </div>
        </div>

        <!-- Slide 3 -->
        <div class="hero-slide">
            <div class="hero-slide__bg" style="background-image:url('https://images.unsplash.com/photo-1547036967-23d11aacaee0?w=1600&q=80');"></div>
            <div class="hero-slide__overlay" style="background:linear-gradient(135deg,rgba(67,20,120,0.85) 0%,rgba(0,0,0,0.15) 60%,rgba(0,0,0,0.4) 100%);"></div>
            <div class="hero-slide__content">
                <span class="hero-slide__tag">&#128663; Rifa de Carros</span>
                <h1 class="hero-slide__title">&iquest;Y si hoy<br>ganas un <em>Carro 0km</em>?</h1>
                <p class="hero-slide__desc">Rifas de carros, motos, electrodom&eacute;sticos y m&aacute;s. Transparencia total: sorteos vinculados a la Loter&iacute;a Nacional.</p>
                <div class="hero-slide__actions">
                    <a href="#search-section" class="hero-slide__btn hero-slide__btn--primary" style="background:linear-gradient(135deg,#7c3aed,#a855f7);box-shadow:0 8px 30px rgba(124,58,237,0.5);">Explorar Rifas</a>
                    <a href="<?= BASE_PATH ?>/public/register.php" class="hero-slide__btn hero-slide__btn--ghost">Vendo mis Rifas</a>
                </div>
            </div>
        </div>

        <!-- Slide 4 -->
        <div class="hero-slide">
            <div class="hero-slide__bg" style="background-image:url('https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=1600&q=80');"></div>
            <div class="hero-slide__overlay" style="background:linear-gradient(135deg,rgba(7,89,133,0.85) 0%,rgba(0,0,0,0.15) 60%,rgba(0,0,0,0.4) 100%);"></div>
            <div class="hero-slide__content">
                <span class="hero-slide__tag">&#128241; Tecnolog&iacute;a</span>
                <h1 class="hero-slide__title">iPhone, MacBook<br>y m&aacute;s <em>gadgets</em></h1>
                <p class="hero-slide__desc">Los mejores premios tecnol&oacute;gicos. Comp&aacute;rtelo en WhatsApp y tus amigos pueden ganar contigo.</p>
                <div class="hero-slide__actions">
                    <a href="#search-section" class="hero-slide__btn hero-slide__btn--primary" style="background:linear-gradient(135deg,#0369a1,#0ea5e9);box-shadow:0 8px 30px rgba(14,165,233,0.5);">Ver Electr&oacute;nicos</a>
                    <a href="<?= BASE_PATH ?>/public/admin/index.php?auth=login" class="hero-slide__btn hero-slide__btn--ghost">Iniciar Sesi&oacute;n</a>
                </div>
            </div>
        </div>

    </div><!-- /track -->

    <!-- Flechas -->
    <button class="hero-slider__arrow hero-slider__arrow--prev" id="sliderPrev" aria-label="Anterior">&#8592;</button>
    <button class="hero-slider__arrow hero-slider__arrow--next" id="sliderNext" aria-label="Siguiente">&#8594;</button>

    <!-- Dots -->
    <div class="hero-slider__dots" id="sliderDots" role="tablist"></div>

    <!-- Barra de progreso -->
    <div class="hero-slider__progress" id="sliderProgress"></div>

    <!-- Miniaturas (desktop) -->
    <div class="hero-slider__thumbs" id="sliderThumbs">
        <div class="hero-slider__thumb is-active" data-slide="0"><img src="https://images.unsplash.com/photo-1592921870789-04563d55041c?w=200&q=60" alt="Slide 1" loading="lazy"></div>
        <div class="hero-slider__thumb" data-slide="1"><img src="https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=200&q=60" alt="Slide 2" loading="lazy"></div>
        <div class="hero-slider__thumb" data-slide="2"><img src="https://images.unsplash.com/photo-1547036967-23d11aacaee0?w=200&q=60" alt="Slide 3" loading="lazy"></div>
        <div class="hero-slider__thumb" data-slide="3"><img src="https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=200&q=60" alt="Slide 4" loading="lazy"></div>
    </div>

</section><!-- /hero-slider -->
