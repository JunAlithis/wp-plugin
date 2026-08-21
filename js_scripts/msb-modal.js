// js_scripts/msb-modal.js
(function () {
    'use strict';

    // === i18n: из PHP (wp_localize_script) + fallback ===
    function getI18n() {
        const base = (window.msbI18n && typeof window.msbI18n === 'object') ? window.msbI18n : {};
        const fallback = {
            modalTitleReview: 'Оставьте отзыв',
            modalTitleReply:  'Ответ на отзыв',
            needFillPrefix:   'Нужно заполнить: ',
            errMinComment:    'напишите, пожалуйста, комментарий (минимум 3 символа)',
            errName:          'укажите ваше имя',
            errEmail:         'укажите e-mail',
            aiTyping:         'Поддержка печатает…',
            btnSending:       'Отправка…'   // <-- новая строка
        };

        // base перекрывает fallback
        return Object.assign({}, fallback, base);
    }

    document.addEventListener('DOMContentLoaded', function () {

        // === МОДАЛКА «НАПИСАТЬ ОТЗЫВ / ОТВЕТИТЬ» ===
        const modal       = document.getElementById('msb-review-modal');
        const openButtons = document.querySelectorAll('.msb-open-review-modal');
        const closeButton = modal ? modal.querySelector('.msb-modal-close') : null;
        const form        = modal ? modal.querySelector('form#commentform, form.comment-form') : null;
        const parentInput = form ? form.querySelector('#comment_parent') : null;
        const modalTitle  = modal ? modal.querySelector('.msb-modal-title') : null;

        // Текущее состояние модалки (чтобы при смене языка обновлять заголовок)
        let currentMode = 'review'; // 'review' | 'reply'

        // --- Находим строки формы, которые надо скрывать в режиме "Ответ" ---
        let metaRow   = null; // Страна + Услуга
        let ratingRow = null; // Оценка сервиса (звёзды)
        let mediaRow  = null; // Добавить фото и видео

        if (form) {
            const countryInput = form.querySelector('#msb_country');
            if (countryInput) metaRow = countryInput.closest('.msb-form-row');

            const ratingStars = form.querySelector('.msb-rating-stars');
            if (ratingStars) ratingRow = ratingStars.closest('.msb-form-row');

            const mediaField = form.querySelector('.msb-media-field');
            if (mediaField) mediaRow = mediaField.closest('.msb-form-row') || mediaField;
        }

        function refreshModalTitle() {
            if (!modalTitle) return;
            const i18n = getI18n();
            modalTitle.textContent = (currentMode === 'reply')
                ? i18n.modalTitleReply
                : i18n.modalTitleReview;
        }

        /**
         * Переключает форму между режимами:
         *  - isReply = false → новый отзыв (видны все поля)
         *  - isReply = true  → ответ (прячем: страна, услуга, рейтинг, фото/видео)
         */
        function switchFormMode(isReply) {
            if (!form) return;

            if (metaRow)   metaRow.style.display   = isReply ? 'none' : '';
            if (ratingRow) ratingRow.style.display = isReply ? 'none' : '';
            if (mediaRow)  mediaRow.style.display  = isReply ? 'none' : '';

            // В режиме ответа чистим лишние поля
            if (isReply) {
                const country = form.querySelector('#msb_country');
                const service = form.querySelector('#msb_service_type');
                if (country) country.value = '';
                if (service) service.value = '';

                const ratingInputs = form.querySelectorAll('input[name="msb_rating"]');
                ratingInputs.forEach(function (r) { r.checked = false; });

                const mediaInputs = form.querySelectorAll('.msb-media-field input[type="file"]');
                mediaInputs.forEach(function (inp) { inp.value = ''; });

                const chipsContainer = form.querySelector('.msb-media-selected-list');
                if (chipsContainer) chipsContainer.innerHTML = '';
            }
        }

        /**
         * Открыть модальное окно.
         * @param {number|string} parentId  ID родительского комментария (0 — новый отзыв)
         */
        function openModal(parentId) {
            if (!modal) return;

            modal.classList.add('is-open');

            if (parentInput) parentInput.value = parentId || 0;

            const isReply = parentId && parseInt(parentId, 10) > 0;
            currentMode = isReply ? 'reply' : 'review';

            switchFormMode(isReply);
            refreshModalTitle();

            if (form) form.scrollTop = 0;
        }

        function closeModal() {
            if (!modal) return;
            modal.classList.remove('is-open');
        }

        // Кнопка "Написать отзыв" — всегда режим НОВЫЙ ОТЗЫВ (parentId = 0)
        openButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                openModal(0);
            });
        });

        // Крестик
        if (closeButton) closeButton.addEventListener('click', closeModal);

        // Клик по подложке
        if (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) closeModal();
            });
        }

        // Кнопки "Ответить" под отзывами
        const replyButtons = document.querySelectorAll('.msb-reply-button');
        replyButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const commentId = this.dataset.commentId;
                openModal(commentId);
            });
        });

        // === БЛОКИРОВКА ОТПРАВКИ ПУСТОГО ОТЗЫВА / НЕЗАПОЛНЕННЫХ ПОЛЕЙ ===
        if (form) {
            const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
            const commentField = form.querySelector('#comment');
            const authorField  = form.querySelector('#author');
            const emailField   = form.querySelector('#email');

            // флаг: форма уже отправляется
            let isSubmitting = false;

            // маленький блок для сообщений об ошибке под текстовым полем
            let errorBox = form.querySelector('.msb-form-error');
            if (!errorBox && commentField && commentField.parentNode) {
                errorBox = document.createElement('p');
                errorBox.className = 'msb-form-error';
                errorBox.style.display = 'none';
                errorBox.style.color = '#dc2626';
                errorBox.style.fontSize = '0.9rem';
                errorBox.style.marginTop = '8px';
                commentField.parentNode.appendChild(errorBox);
            }

            function setError(message) {
                if (!errorBox) return;
                if (!message) {
                    errorBox.style.display = 'none';
                    errorBox.textContent = '';
                    return;
                }
                errorBox.textContent = message;
                errorBox.style.display = 'block';
            }

            function updateSubmitState(showErrors) {
                if (!submitButton) return true;

                const i18n = getI18n();

                // если уже отправляем — не трогаем кнопку
                if (isSubmitting) {
                    submitButton.disabled = true;
                    submitButton.classList.add('msb-button-disabled');
                    return true;
                }

                const commentOk = commentField && commentField.value.trim().length >= 3;
                const authorOk  = !authorField || authorField.value.trim() !== '';
                const emailOk   = !emailField || emailField.value.trim() !== '';

                const canSubmit = commentOk && authorOk && emailOk;

                submitButton.disabled = !canSubmit;
                submitButton.classList.toggle('msb-button-disabled', !canSubmit);

                if (showErrors && !canSubmit) {
                    const messages = [];
                    if (!commentOk) messages.push(i18n.errMinComment);
                    if (!authorOk)  messages.push(i18n.errName);
                    if (!emailOk)   messages.push(i18n.errEmail);

                    setError((i18n.needFillPrefix || 'Нужно заполнить: ') + messages.join(', '));
                } else if (!showErrors) {
                    setError('');
                }

                return canSubmit;
            }

            // Первичная инициализация — сразу блокируем кнопку, если поля пустые
            updateSubmitState(false);

            // Слушаем изменения полей
            const watchFields = [commentField, authorField, emailField].filter(Boolean);
            ['input', 'change', 'keyup'].forEach(function (evtName) {
                watchFields.forEach(function (field) {
                    field.addEventListener(evtName, function () {
                        updateSubmitState(false);
                    });
                });
            });

            // Перехватываем submit, чтобы:
            // 1) не отправлять пустое
            // 2) заблокировать повторное нажатие во время отправки
            form.addEventListener('submit', function (e) {
                // Если уже отправляем — просто игнорируем повторный submit
                if (isSubmitting) {
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }

                const ok = updateSubmitState(true);
                if (!ok) {
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }

                // Всё ок — ставим флаг и меняем кнопку
                isSubmitting = true;

                if (submitButton) {
                    const i18n = getI18n();

                    // запомним оригинальный текст (чтобы в будущем можно было восстановить, если понадобится)
                    if (!submitButton.dataset.originalText) {
                        submitButton.dataset.originalText = submitButton.textContent;
                    }

                    submitButton.disabled = true;
                    submitButton.classList.add('msb-button-disabled', 'msb-button-loading');
                    submitButton.textContent = i18n.btnSending || 'Отправка…';
                }
                // дальше форма уходит стандартно, WP делает редирект → страница перезагрузится
            });
        }


        // Автооткрытие формы, если в URL есть ?msb_open=1
        (function () {
            if (!modal || !openButtons || !openButtons.length) return;

            try {
                var params = new URLSearchParams(window.location.search);
                if (params.get('msb_open') === '1') {
                    openButtons[0].click();
                }
            } catch (e) {
                // тихо игнорируем
            }
        })();

        // === Поддержка смены языка "на лету" ===
        // Твой i18n-движок может делать так:
        // document.dispatchEvent(new CustomEvent('msb:i18n', { detail: { modalTitleReview: '...', ... } }));
        document.addEventListener('msb:i18n', function (e) {
            if (e && e.detail && typeof e.detail === 'object') {
                window.msbI18n = Object.assign({}, window.msbI18n || {}, e.detail);

                // обновим заголовок модалки, если она открыта
                if (modal && modal.classList.contains('is-open')) {
                    refreshModalTitle();
                }
            }
        });

        // На всякий случай — ручной “пинок” из консоли/кода
        window.msbModalRefreshI18n = function () {
            if (modal && modal.classList.contains('is-open')) {
                refreshModalTitle();
            }
        };
    });

})();
