// /js_scripts/msb-ai-typing.js
(function () {
    'use strict';

    const AI_SELECTOR = '.msb-comment-card.msb-comment-card--child[data-msb-ai-reply="1"]';

    function msbGetParams() {
        try { return new URLSearchParams(window.location.search || ''); }
        catch (e) { return null; }
    }

    function msbIsJustPosted() {
        const p = msbGetParams();
        if (!p) return false;
        return p.get('msb_posted') === '1';
    }

    function msbGetPostedCid() {
        const p = msbGetParams();
        if (!p) return null;
        const v = (p.get('msb_cid') || '').trim();
        return v && /^\d+$/.test(v) ? v : null;
    }

    function msbCleanPostedParamsFromUrl() {
        // убираем ?msb_posted=1&msb_cid=... чтобы при обновлении/копировании не повторялось
        try {
            const url = new URL(window.location.href);
            url.searchParams.delete('msb_posted');
            url.searchParams.delete('msb_cid');
            history.replaceState({}, '', url.toString());
        } catch (e) {}
    }

    function msbMarkAllAiAsAlreadyTypedExcept(exceptEl) {
        const all = document.querySelectorAll(AI_SELECTOR);
        all.forEach(function (item) {
            if (exceptEl && item === exceptEl) return;

            let commentId = item.getAttribute('data-comment-id') || '';
            if (!commentId && item.id && item.id.indexOf('comment-') === 0) {
                commentId = item.id.slice('comment-'.length);
            }
            if (!commentId) return;

            try {
                if (window.localStorage) {
                    localStorage.setItem('msbAiTyped_' + commentId, '1');
                }
            } catch (e) {}
        });
    }

    function msbFindAiReplyForParent(parentEl, parentId) {
        if (!parentEl) return null;

        // 1) Внутри родителя
        let ai = parentEl.querySelector(AI_SELECTOR);
        if (ai) return ai;

        // 2) В ближайшем контейнере (li / wrapper)
        const wrapper = parentEl.closest('li') || parentEl.closest('.msb-comment-card') || null;
        if (wrapper) {
            ai = wrapper.querySelector(AI_SELECTOR);
            if (ai) return ai;
        }

        // 3) По соседям (часто ответы идут сразу после родителя)
        let next = parentEl.nextElementSibling;
        let steps = 0;
        while (next && steps < 40) {
            if (next.matches && next.matches(AI_SELECTOR)) return next;
            if (next.querySelector) {
                ai = next.querySelector(AI_SELECTOR);
                if (ai) return ai;
            }
            next = next.nextElementSibling;
            steps++;
        }

        // 4) Глобальный поиск по data-атрибутам (если у тебя они есть)
        if (parentId) {
            const all = document.querySelectorAll(AI_SELECTOR);
            for (let i = 0; i < all.length; i++) {
                const el = all[i];
                const d = el.dataset || {};
                const pid =
                    d.parentId || d.commentParent || d.parent || d.msbParent || d.msbParentId || '';
                if (pid && String(pid) === String(parentId)) {
                    return el;
                }
            }
        }

        return null;
    }

    document.addEventListener('DOMContentLoaded', function () {

        const i18n = (window.msbI18n && typeof window.msbI18n === 'object')
            ? window.msbI18n
            : { aiTyping: 'Поддержка печатает…' };

        // Если это НЕ отправка — вообще НИЧЕГО не анимируем
        if (!msbIsJustPosted()) {
            // и на всякий случай пометим все AI-ответы как "уже показанные" в этом браузере,
            // чтобы даже если кто-то случайно вызовет msbShowAiTyping — она не сработала.
            msbMarkAllAiAsAlreadyTypedExcept(null);
            return;
        }

        // --- Определяем ID (лучше брать msb_cid, если есть) ---
        const postedCid = msbGetPostedCid();

        const hash = (window.location.hash || '').trim();
        const match = hash.match(/^#comment-(\d+)$/);
        const anchorId = postedCid || (match ? match[1] : null);

        const anchorEl = anchorId ? document.getElementById('comment-' + anchorId) : null;

        // 1) Если якорь прямо на AI-ответ — запускаем сразу
        if (anchorEl && anchorEl.matches && anchorEl.matches(AI_SELECTOR)) {
            if (typeof window.msbShowAiTyping === 'function') {
                window.msbShowAiTyping(anchorEl, true); // force=true
            }
            msbCleanPostedParamsFromUrl();
            return;
        }

        // 2) Иначе ищем AI-ответ “рядом” с тем комментом, на который нас редиректнуло
        const parentEl = anchorEl;

        const run = function () {
            const aiEl = parentEl ? msbFindAiReplyForParent(parentEl, anchorId) : null;

            if (aiEl && typeof window.msbShowAiTyping === 'function') {
                // Перед запуском: пометим ВСЕ остальные AI как уже показанные,
                // чтобы не было “печатает…” где не надо
                msbMarkAllAiAsAlreadyTypedExcept(aiEl);

                window.msbShowAiTyping(aiEl, true); // force=true
                msbCleanPostedParamsFromUrl();
                return true;
            }
            return false;
        };

        // пробуем сразу
        if (run()) return;

        // если AI-ответ появляется чуть позже — ждём
        let tries = 0;
        const maxTries = 20; // ~20 секунд

        const timer = setInterval(function () {
            tries++;
            if (run()) {
                clearInterval(timer);
                return;
            }
            if (tries >= maxTries) {
                clearInterval(timer);
                // даже если не нашли — чистим параметр, чтоб не было “вечной отправки”
                msbCleanPostedParamsFromUrl();
            }
        }, 1000);
    });

    /**
     * Глобальная функция — НЕ удалял, оставил.
     * Добавил 2-й аргумент force: если true — разрешаем анимацию даже после очистки URL.
     */
    window.msbShowAiTyping = function (commentElement, force) {
        if (!commentElement) return;

        // Защита: без force — анимация только если msb_posted=1
        if (!force && !msbIsJustPosted()) {
            return;
        }

        const body = commentElement.querySelector('.msb-comment-content');
        if (!body) return;

        const i18n = (window.msbI18n && typeof window.msbI18n === 'object')
            ? window.msbI18n
            : { aiTyping: 'Поддержка печатает…' };

        let commentId = commentElement.getAttribute('data-comment-id') || '';
        if (!commentId && commentElement.id && commentElement.id.indexOf('comment-') === 0) {
            commentId = commentElement.id.slice('comment-'.length);
        }

        try {
            if (window.localStorage && commentId &&
                localStorage.getItem('msbAiTyped_' + commentId) === '1') {
                return;
            }
        } catch (e) {}

        if (body.dataset.msbAiTypingInit === '1') return;
        body.dataset.msbAiTypingInit = '1';

        const realTextWrapper = document.createElement('div');
        realTextWrapper.className = 'msb-ai-real-text';
        realTextWrapper.innerHTML = body.innerHTML;

        const typing = document.createElement('div');
        typing.className = 'msb-ai-typing';
        typing.textContent = i18n.aiTyping || 'Поддержка печатает…';

        body.innerHTML = '';
        body.appendChild(typing);
        body.appendChild(realTextWrapper);
        realTextWrapper.style.display = 'none';

        setTimeout(function () {
            typing.style.display = 'none';
            realTextWrapper.style.display = '';

            try {
                if (window.localStorage && commentId) {
                    localStorage.setItem('msbAiTyped_' + commentId, '1');
                }
            } catch (e) {}
        }, 15000);
    };

})();
