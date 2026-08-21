/**
 * msb-reviews-slider.js
 * Современный слайдер для виджетов отзывов
 * Прокручивает по 3 отзыва за раз
 */

(function() {
    'use strict';

    class MsbReviewsSlider {
        constructor(grid) {
            this.grid = grid;
            this.widget = grid.closest('.msb-reviews-widget');
            if (!this.widget) return;

            this.slides = Array.from(this.grid.querySelectorAll('.msb-review-card'));
            if (this.slides.length <= 1) return;

            this.currentIndex   = 0;
            this.isAnimating    = false;
            this.autoplayTimer  = null;
            this.autoplayDelay  = this.grid.dataset.msbAutoplay ? parseInt(this.grid.dataset.msbAutoplay, 10) : 5000;
            this.touchStartX    = 0;
            this.touchEndX      = 0;
            this.isMouseDown    = false;
            this.dragStartX     = 0;

            // Сколько карточек показываем за раз
            this.slidesPerView  = this.getSlidesPerView();
            this.totalSlides    = Math.ceil(this.slides.length / this.slidesPerView);

            this.initSlider();
            this.initControls();
            this.bindEvents();
            this.startAutoplay();
        }

        getSlidesPerView() {
            const width = window.innerWidth;
            if (width < 640)  return 1; // мобилка
            if (width < 1024) return 2; // планшет
            return 3;                   // десктоп
        }

        initSlider() {
            // Создаём контейнер для слайдов
            const container = document.createElement('div');
            container.className = 'msb-slider-container';
            container.style.display    = 'flex';
            container.style.transition = 'transform 0.45s cubic-bezier(0.22, 0.61, 0.36, 1)';
            container.style.width      = `${(this.slides.length / this.slidesPerView) * 100}%`;
            container.style.willChange = 'transform';

            // Перекладываем карточки внутрь контейнера
            this.slides.forEach((slide, index) => {
                const widthPercent = 100 / this.slidesPerView;
                slide.style.flex       = `0 0 ${widthPercent}%`;
                slide.style.width      = `${widthPercent}%`;
                slide.style.boxSizing  = 'border-box';
                slide.setAttribute('data-slide-index', index);
                container.appendChild(slide);
            });

            // Очищаем grid и суём туда контейнер
            this.grid.innerHTML = '';
            this.grid.appendChild(container);
            this.container = container;

            this.updateGridStyles();
            this.updateSliderPosition();
        }

        updateGridStyles() {
            this.grid.style.display       = 'flex';
            this.grid.style.overflow      = 'hidden';
            this.grid.style.width         = '100%';
            this.grid.style.scrollbarWidth = 'none';
            this.grid.style.msOverflowStyle = 'none';
            // Подсказка браузеру: вертикальный скролл разрешён
            this.grid.style.touchAction   = 'pan-y';
        }

        initControls() {
            let controls = this.widget.querySelector('.msb-slider-controls');
            if (!controls) {
                controls = document.createElement('div');
                controls.className = 'msb-slider-controls';

                // Кнопка "назад"
                this.prevBtn = document.createElement('button');
                this.prevBtn.type = 'button';
                this.prevBtn.className = 'msb-reviews-nav msb-reviews-nav--prev';
                this.prevBtn.setAttribute('aria-label', 'Предыдущие отзывы');
                this.prevBtn.innerHTML = `
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z" fill="currentColor"/>
                    </svg>
                `;

                // Кнопка "вперёд"
                this.nextBtn = document.createElement('button');
                this.nextBtn.type = 'button';
                this.nextBtn.className = 'msb-reviews-nav msb-reviews-nav--next';
                this.nextBtn.setAttribute('aria-label', 'Следующие отзывы');
                this.nextBtn.innerHTML = `
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z" fill="currentColor"/>
                    </svg>
                `;

                this.dotsContainer = document.createElement('div');
                this.dotsContainer.className = 'msb-slider-dots';

                controls.appendChild(this.prevBtn);
                controls.appendChild(this.dotsContainer);
                controls.appendChild(this.nextBtn);

                const slider = this.widget.querySelector('.msb-reviews-slider');
                if (slider) {
                    slider.appendChild(controls);
                }

                this.createDots();
            } else {
                this.prevBtn      = controls.querySelector('.msb-reviews-nav--prev');
                this.nextBtn      = controls.querySelector('.msb-reviews-nav--next');
                this.dotsContainer = controls.querySelector('.msb-slider-dots');
                this.dots          = Array.from(this.dotsContainer.querySelectorAll('.msb-slider-dot'));
            }

            this.updateButtons();
        }

        createDots() {
            this.dotsContainer.innerHTML = '';
            this.dots = [];

            for (let i = 0; i < this.totalSlides; i++) {
                const dot = document.createElement('button');
                dot.type = 'button';
                dot.className = 'msb-slider-dot';
                dot.setAttribute('aria-label', `Перейти к группе отзывов ${i + 1}`);
                if (i === 0) dot.classList.add('msb-slider-dot--active');

                dot.addEventListener('click', () => {
                    this.pauseAutoplay();
                    this.goToSlide(i);
                    setTimeout(() => this.startAutoplay(), 3000);
                });

                this.dotsContainer.appendChild(dot);
                this.dots.push(dot);
            }
        }

        updateDots() {
            if (!this.dots) return;
            this.dots.forEach((dot, index) => {
                dot.classList.toggle('msb-slider-dot--active', index === this.currentIndex);
            });
        }

        updateButtons() {
            if (this.prevBtn) {
                const disabled = this.totalSlides <= 1;
                this.prevBtn.disabled = disabled;
                this.prevBtn.style.opacity = disabled ? '0.5' : '1';
                this.prevBtn.style.cursor  = disabled ? 'default' : 'pointer';
            }

            if (this.nextBtn) {
                const disabled = this.totalSlides <= 1;
                this.nextBtn.disabled = disabled;
                this.nextBtn.style.opacity = disabled ? '0.5' : '1';
                this.nextBtn.style.cursor  = disabled ? 'default' : 'pointer';
            }
        }

        updateSliderPosition() {
            if (!this.container) return;
            const translateX = -(this.currentIndex * 100);
            this.container.style.transition = this.container.style.transition || 'transform 0.45s cubic-bezier(0.22, 0.61, 0.36, 1)';
            this.container.style.transform  = `translateX(${translateX}%)`;
        }

        goToSlide(newIndex) {
            if (this.isAnimating) return;
            if (newIndex === this.currentIndex) return;
            if (newIndex < 0 || newIndex >= this.totalSlides) return;

            this.isAnimating = true;
            this.currentIndex = newIndex;

            this.updateSliderPosition();
            this.updateDots();
            this.updateButtons();

            // Флаг анимации снимаем после transition
            setTimeout(() => {
                this.isAnimating = false;
            }, 460);
        }

        nextSlide() {
            if (this.totalSlides <= 1) return;
            const next = (this.currentIndex + 1) % this.totalSlides;
            this.goToSlide(next);
        }

        prevSlide() {
            if (this.totalSlides <= 1) return;
            const prev = (this.currentIndex - 1 + this.totalSlides) % this.totalSlides;
            this.goToSlide(prev);
        }

        startAutoplay() {
            if (this.autoplayTimer) return;
            if (this.slides.length <= this.slidesPerView) return;

            this.autoplayTimer = setInterval(() => {
                if (!this.isMouseDown && !this.isAnimating) {
                    this.nextSlide();
                }
            }, this.autoplayDelay);
        }

        pauseAutoplay() {
            if (!this.autoplayTimer) return;
            clearInterval(this.autoplayTimer);
            this.autoplayTimer = null;
        }

        /* ====== TOUCH ====== */

        handleTouchStart(e) {
            if (!e.touches || e.touches.length === 0) return;
            this.touchStartX = e.touches[0].clientX;
            this.touchEndX   = this.touchStartX;
            this.pauseAutoplay();
        }

        handleTouchMove(e) {
            if (!this.touchStartX || !e.touches || e.touches.length === 0) return;

            this.touchEndX = e.touches[0].clientX;
            const diff = Math.abs(this.touchStartX - this.touchEndX);

            // ВАЖНО: проверяем, можно ли вообще отменять событие,
            // иначе Chrome выдаёт "cancelable=false"
            if (diff > 10 && e.cancelable) {
                e.preventDefault();
            }
        }

        handleTouchEnd() {
            if (!this.touchStartX || !this.touchEndX) {
                this.touchStartX = 0;
                this.touchEndX   = 0;
                return;
            }

            const swipeThreshold = 50;
            const diff = this.touchStartX - this.touchEndX;

            if (Math.abs(diff) > swipeThreshold) {
                if (diff > 0) {
                    this.nextSlide();
                } else {
                    this.prevSlide();
                }
            }

            this.touchStartX = 0;
            this.touchEndX   = 0;

            setTimeout(() => {
                this.startAutoplay();
            }, 3000);
        }

        /* ====== MOUSE DRAG ====== */

        handleMouseDown(e) {
            if (e.button !== 0) return; // только ЛКМ
            this.isMouseDown = true;
            this.dragStartX  = e.clientX;
            this.pauseAutoplay();
            // чтобы не выделялся текст
            e.preventDefault();
        }

        handleMouseMove(e) {
            if (!this.isMouseDown || !this.container) return;

            const dragEndX = e.clientX;
            const diff = this.dragStartX - dragEndX;

            if (Math.abs(diff) > 10) {
                const slideWidth   = this.grid.offsetWidth;
                const baseOffsetPx = -this.currentIndex * slideWidth;
                const tempTranslate = baseOffsetPx + diff;

                this.container.style.transition = 'none';
                this.container.style.transform  = `translateX(${tempTranslate}px)`;
            }
        }

        handleMouseUp(e) {
            if (!this.isMouseDown) return;
            this.isMouseDown = false;

            const dragEndX = e.clientX;
            const diff = this.dragStartX - dragEndX;
            const swipeThreshold = 50;

            // Возвращаем нормальную анимацию
            this.container.style.transition = 'transform 0.45s cubic-bezier(0.22, 0.61, 0.36, 1)';

            if (Math.abs(diff) > swipeThreshold) {
                if (diff > 0) {
                    this.nextSlide();
                } else {
                    this.prevSlide();
                }
            } else {
                // Вернуть на место, если свайп слабый
                this.updateSliderPosition();
            }

            setTimeout(() => {
                this.startAutoplay();
            }, 3000);
        }

        /* ====== RESIZE ====== */

        handleResize() {
            const oldSlidesPerView = this.slidesPerView;
            this.slidesPerView = this.getSlidesPerView();
            this.totalSlides   = Math.ceil(this.slides.length / this.slidesPerView);

            if (oldSlidesPerView !== this.slidesPerView) {
                this.currentIndex = 0;
                this.initSlider();
                this.createDots();
                this.updateButtons();
            }
        }

        /* ====== KEYBOARD & VISIBILITY ====== */

        handleKeyDown(e) {
            switch (e.key) {
                case 'ArrowLeft':
                    e.preventDefault();
                    this.pauseAutoplay();
                    this.prevSlide();
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    this.pauseAutoplay();
                    this.nextSlide();
                    break;
                case 'Home':
                    e.preventDefault();
                    this.pauseAutoplay();
                    this.goToSlide(0);
                    break;
                case 'End':
                    e.preventDefault();
                    this.pauseAutoplay();
                    this.goToSlide(this.totalSlides - 1);
                    break;
            }
        }

        handleVisibilityChange() {
            if (document.hidden) {
                this.pauseAutoplay();
            } else {
                this.startAutoplay();
            }
        }

        bindEvents() {
            // Навигация
            if (this.prevBtn) {
                this.prevBtn.addEventListener('click', () => {
                    this.pauseAutoplay();
                    this.prevSlide();
                    setTimeout(() => this.startAutoplay(), 3000);
                });
            }

            if (this.nextBtn) {
                this.nextBtn.addEventListener('click', () => {
                    this.pauseAutoplay();
                    this.nextSlide();
                    setTimeout(() => this.startAutoplay(), 3000);
                });
            }

            // Touch (passive: false только там, где реально нужно)
            this.grid.addEventListener('touchstart', (e) => this.handleTouchStart(e), { passive: true });
            this.grid.addEventListener('touchmove',  (e) => this.handleTouchMove(e),  { passive: false });
            this.grid.addEventListener('touchend',   () => this.handleTouchEnd());

            // Mouse drag
            this.grid.addEventListener('mousedown', (e) => this.handleMouseDown(e));
            document.addEventListener('mousemove', (e) => this.handleMouseMove(e));
            document.addEventListener('mouseup',   (e) => this.handleMouseUp(e));

            // Пауза при наведении
            this.widget.addEventListener('mouseenter', () => this.pauseAutoplay());
            this.widget.addEventListener('mouseleave', () => this.startAutoplay());

            // Ресайз окна
            let resizeTimer;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(() => this.handleResize(), 250);
            });

            // Клавиатура
            this.widget.setAttribute('tabindex', '0');
            this.widget.addEventListener('keydown', (e) => this.handleKeyDown(e));

            // Смена видимости вкладки
            document.addEventListener('visibilitychange', () => this.handleVisibilityChange());
        }

        destroy() {
            this.pauseAutoplay();
            // Если когда-нибудь захочешь реально уничтожать слайдер – сюда можно дописать removeEventListener
        }
    }

    // Инициализация всех слайдеров на странице
    document.addEventListener('DOMContentLoaded', function() {
        const grids = document.querySelectorAll('.msb-reviews-slider .msb-reviews-grid');

        grids.forEach((grid) => {
            if (!grid.classList.contains('msb-slider-initialized')) {
                grid.classList.add('msb-slider-initialized');
                new MsbReviewsSlider(grid);
            }
        });
    });

    // Экспорт
    if (typeof window !== 'undefined') {
        window.MsbReviewsSlider = MsbReviewsSlider;
    }

    // Автоинициализация для динамически добавленных элементов
    if (typeof MutationObserver !== 'undefined') {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType !== 1) return;

                    const grids = [];

                    if (node.matches && node.matches('.msb-reviews-slider .msb-reviews-grid')) {
                        grids.push(node);
                    }

                    if (node.querySelectorAll) {
                        const childGrids = node.querySelectorAll('.msb-reviews-slider .msb-reviews-grid');
                        grids.push(...Array.from(childGrids));
                    }

                    grids.forEach((grid) => {
                        if (!grid.classList.contains('msb-slider-initialized')) {
                            grid.classList.add('msb-slider-initialized');
                            new MsbReviewsSlider(grid);
                        }
                    });
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

})();
