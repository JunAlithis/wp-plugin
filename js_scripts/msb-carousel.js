/**
 * MSB Comments — PC Repair by Meryosab
 * Карусель отзывов с пагинацией (стрелки + точки).
 *
 * Как работает:
 *  - берёт .msb-carousel[data-msb-carousel], внутри:
 *      .msb-carousel-viewport  — «окно» (overflow hidden);
 *      .msb-carousel-track     — лента карточек (children — .msb-comment-card);
 *      .msb-carousel-controls  — кнопки prev/next и точки (создаёт JS);
 *  - карточек на «экране»: 3 (ширина >= 1200px), 2 (>= 760px), 1 (остальное);
 *  - шаг — одна карточка; точки — по числу позиций;
 *  - свайп пальцем/мышью (порог 40px, до 600мс), кнопки клавиатурой;
 *  - при ≤ perView карточек управление скрывается;
 *  - если JS выключен — CSS показывает список сеткой (fallback).
 *
 * Безопасен для нескольких экземпляров шорткода на странице.
 */
(function () {
    'use strict';

    var SELECTOR = '.msb-carousel[data-msb-carousel]';
    var GAP = 16;              // отступ между карточками, px (совпадает с CSS gap)
    var SWIPE_MIN = 40;        // минимальное смещение свайпа, px
    var SWIPE_MAX_MS = 600;    // максимальная длительность свайпа, ms

    function perViewFor(width) {
        if (width >= 1200) { return 3; }
        if (width >= 760)  { return 2; }
        return 1;
    }

    function initCarousel(root) {
        if (root.getAttribute('data-msb-carousel-init') === '1') { return; }
        root.setAttribute('data-msb-carousel-init', '1');

        var viewport = root.querySelector('.msb-carousel-viewport');
        var track    = root.querySelector('.msb-carousel-track');
        var prevBtn  = root.querySelector('.msb-carousel-btn--prev');
        var nextBtn  = root.querySelector('.msb-carousel-btn--next');
        var dotsWrap = root.querySelector('.msb-carousel-dots');

        if (!viewport || !track || !prevBtn || !nextBtn || !dotsWrap) { return; }

        var cards = Array.prototype.slice.call(track.children);
        if (!cards.length) { root.style.display = 'none'; return; }

        var index = 0;
        var perView = 0; // 0 = ещё не раскладывал — пересчитаем в layout()
        var maxIndex = 0;
        var dots = [];
        var reduced = window.matchMedia &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        function buildDots() {
            dotsWrap.innerHTML = '';
            dots = [];
            for (var i = 0; i <= maxIndex; i++) {
                (function (pos) {
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'msb-carousel-dot';
                    b.setAttribute('aria-label', (pos + 1) + ' из ' + (maxIndex + 1));
                    b.addEventListener('click', function () {
                        index = pos;
                        render();
                    });
                    dotsWrap.appendChild(b);
                    dots.push(b);
                })(i);
            }
        }

        function layout() {
            var newPerView = perViewFor(viewport.clientWidth);
            if (newPerView !== perView) {
                perView = newPerView;
                // Ширина карточек под количество «на экране»
                var basis = 'calc((100% - ' + ((perView - 1) * GAP) + 'px) / ' + perView + ')';
                for (var i = 0; i < cards.length; i++) {
                    cards[i].style.flex = '0 0 ' + basis;
                }
            }

            // Переключаем режим: сетка (fallback) → лента карусели
            root.classList.add('is-carousel');

            var cardWidth = cards[0].getBoundingClientRect().width;
            var step = cardWidth + GAP;
            maxIndex = Math.max(0, cards.length - perView);
            if (index > maxIndex) { index = maxIndex; }

            var needDots = dots.length !== (maxIndex + 1);
            if (maxIndex <= 0) {
                root.classList.add('msb-carousel--static');
            } else {
                root.classList.remove('msb-carousel--static');
            }
            if (needDots) { buildDots(); }
            render(step);
        }

        function render(step) {
            if (!step) {
                step = (cards[0].getBoundingClientRect().width + GAP);
            }
            track.style.transform = 'translateX(' + (-index * step) + 'px)';
            track.style.transition = reduced ? 'none' : '';

            prevBtn.disabled = index <= 0;
            nextBtn.disabled = index >= maxIndex;

            for (var i = 0; i < dots.length; i++) {
                dots[i].classList.toggle('is-active', i === index);
                if (i === index) {
                    dots[i].setAttribute('aria-current', 'true');
                } else {
                    dots[i].removeAttribute('aria-current');
                }
            }
        }

        prevBtn.addEventListener('click', function () {
            if (index > 0) { index--; render(); }
        });
        nextBtn.addEventListener('click', function () {
            if (index < maxIndex) { index++; render(); }
        });

        /* ---------- Свайп (pointer events: мышь + тач) ---------- */
        var startX = null;
        var startTime = 0;
        var dragging = false;

        viewport.addEventListener('pointerdown', function (e) {
            // Не мешаем кликам по ссылкам/кнопкам внутри карточки
            var t = e.target;
            while (t && t !== viewport) {
                if (t.tagName === 'A' || t.tagName === 'BUTTON' || t.tagName === 'INPUT') { return; }
                t = t.parentNode;
            }
            startX = e.clientX;
            startTime = Date.now();
            dragging = true;
            track.style.transition = 'none';
        });

        viewport.addEventListener('pointerup', function (e) {
            if (!dragging || startX === null) { return; }
            dragging = false;
            var dx = e.clientX - startX;
            var dt = Date.now() - startTime;
            startX = null;
            track.style.transition = reduced ? 'none' : '';
            if (Math.abs(dx) < SWIPE_MIN || dt > SWIPE_MAX_MS) { return; }
            if (dx < 0 && index < maxIndex) { index++; }
            else if (dx > 0 && index > 0) { index--; }
            render();
        });

        viewport.addEventListener('pointerleave', function () {
            if (dragging) {
                dragging = false;
                startX = null;
                track.style.transition = reduced ? 'none' : '';
            }
        });
        viewport.addEventListener('pointercancel', function () {
            dragging = false;
            startX = null;
            track.style.transition = reduced ? 'none' : '';
        });

        /* ---------- Ресайз ---------- */
        var resizeTimer = null;
        window.addEventListener('resize', function () {
            if (resizeTimer) { clearTimeout(resizeTimer); }
            resizeTimer = setTimeout(function () {
                var wasPerView = perView;
                layout();
                if (perView !== wasPerView) { render(); }
            }, 120);
        });

        layout();
    }

    function initAll() {
        var roots = document.querySelectorAll(SELECTOR);
        var found = 0;
        for (var i = 0; i < roots.length; i++) {
            initCarousel(roots[i]);
            found++;
        }
        return found;
    }

    /* Инициализация + повтор (на случай позднего рендера контента) */
    var retryTimer = null;
    var attempts = 0;
    function init() {
        if (retryTimer) { clearTimeout(retryTimer); }
        if (initAll() > 0) { return; }
        attempts++;
        if (attempts < 10) {
            retryTimer = setTimeout(init, 500);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }

    /* Публичный хук: перезапустить после динамической подгрузки отзывов */
    window.msbCarouselRefresh = initAll;
})();
