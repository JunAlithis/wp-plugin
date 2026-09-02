/**
 * MSB Comments — PC Repair by Meryosab
 * Синхронизация темы плагина с темой сайта.
 *
 * Сайт хранит текущую тему в атрибутах <html>:
 *   data-theme="light|dark"  (запасной вариант: data-pch-theme)
 * — и каждый блок сайта держит собственный data-theme.
 *
 * Этот скрипт делает то же самое для блоков плагина:
 *  - читает тему из document.documentElement,
 *  - выставляет data-theme на каждом .msb-pcr-block,
 *  - наблюдает за сменой темы (MutationObserver)
 *    и перекрашивает плагин без перезагрузки страницы.
 *
 * Безопасен при нескольких экземплярах шорткода на странице
 * и не конфликтует с собственными синхронизаторами блоков сайта
 * (те работают по id своих блоков, этот — по классу .msb-pcr-block).
 */
(function () {
    'use strict';

    var BLOCK_SELECTOR = '.msb-pcr-block';

    function currentSiteTheme() {
        var docEl = document.documentElement;
        if (!docEl) {
            return 'light';
        }
        var t = docEl.getAttribute('data-theme') || docEl.getAttribute('data-pch-theme');
        return t === 'dark' ? 'dark' : 'light';
    }

    function syncAll() {
        var theme = currentSiteTheme();
        var blocks = document.querySelectorAll(BLOCK_SELECTOR);
        for (var i = 0; i < blocks.length; i++) {
            if (blocks[i].getAttribute('data-theme') !== theme) {
                blocks[i].setAttribute('data-theme', theme);
            }
        }
        return blocks.length > 0;
    }

    var observerStarted = false;

    function startObserver() {
        if (observerStarted || !window.MutationObserver) {
            return;
        }
        observerStarted = true;
        new MutationObserver(function () {
            syncAll();
        }).observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['data-theme', 'data-pch-theme']
        });
    }

    var retryTimer = null;

    function init() {
        if (retryTimer) {
            clearTimeout(retryTimer);
        }
        if (syncAll()) {
            startObserver();
            return;
        }
        // Блок ещё не в DOM (ленивая загрузка) — повторим через 300 мс.
        retryTimer = setTimeout(init, 300);
        startObserver();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
