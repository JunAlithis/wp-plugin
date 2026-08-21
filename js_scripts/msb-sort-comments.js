// msb-sort-comments.js
// Сортировка отзывов на странице /comments

document.addEventListener('DOMContentLoaded', function () {

    // Обёртка со списком всех карточек отзывов
    const list = document.querySelector('.msb-comments-list');
    if (!list) return;

    // Только родительские карточки
    const cards = Array.from(list.querySelectorAll('.msb-comment-card'))
        .filter(function (c) {
            return !c.classList.contains('msb-comment-card--child');
        });

    // Запоминаем исходный порядок
    cards.forEach(function (card, index) {
        if (!card.dataset.originalIndex) {
            card.dataset.originalIndex = index;
        }
    });

    const chips = document.querySelectorAll('.msb-sort-chip');

    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {

            // Снять активность со всех чипов
            chips.forEach(function (c) {
                c.classList.remove('msb-sort-chip--active');
            });
            // Подсветить текущий
            this.classList.add('msb-sort-chip--active');

            const mode = this.dataset.sort || 'relevance';

            const sorted = cards.slice().sort(function (a, b) {
                const ra = parseFloat(a.dataset.rating || '0'); // рейтинг
                const rb = parseFloat(b.dataset.rating || '0');

                const da = a.dataset.date ? new Date(a.dataset.date) : new Date(0);
                const db = b.dataset.date ? new Date(b.dataset.date) : new Date(0);

                const ia = parseInt(a.dataset.originalIndex || '0', 10);
                const ib = parseInt(b.dataset.originalIndex || '0', 10);

                // если хочешь сортировку по полезности — читаем data-helpful
                const ha = parseInt(a.dataset.helpful || '0', 10);
                const hb = parseInt(b.dataset.helpful || '0', 10);

                switch (mode) {
                    case 'rating_desc':
                        return rb - ra;        // 5 → 1

                    case 'rating_asc':
                        return ra - rb;        // 1 → 5

                    case 'date':
                        return db - da;        // новые выше

                    case 'helpful':
                        return hb - ha;        // больше лайков выше

                    case 'relevance':
                    default:
                        return ia - ib;        // исходный порядок
                }
            });

            // Переставляем карточки в DOM
            sorted.forEach(function (card) {
                list.insertBefore(card, list.firstChild);
            });
        });
    });

});
