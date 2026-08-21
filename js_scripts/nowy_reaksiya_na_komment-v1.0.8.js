// js_scripts/nowy_reaksiya_na_komment.js
// «Полезный отзыв» + реакции 👍😍🤯

document.addEventListener('DOMContentLoaded', function () {

    // === «ПОЛЕЗНЫЙ ОТЗЫВ» (быстрый отклик) ===
    const helpfulButtons = document.querySelectorAll('.msb-helpful-button');

    helpfulButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            if (typeof msbComments === 'undefined' || !msbComments.ajaxUrl) return;

            const commentId = this.dataset.commentId;
            if (!commentId) return;

            // уже голосовал или в процессе
            if (this.dataset.loading === '1') return;
            const removing = this.classList.contains('msb-helpful-button--voted');

            const countSpan = this.querySelector('.msb-helpful-count');
            const oldCount  = countSpan ? parseInt(countSpan.textContent || '0', 10) : 0;

            // оптимистический UI
            if (removing) this.classList.remove('msb-helpful-button--voted'); else this.classList.add('msb-helpful-button--voted');
            this.disabled = false;
            this.dataset.loading = '1';
            if (countSpan) countSpan.textContent = removing ? Math.max(0, oldCount - 1) : oldCount + 1;

            const body = new URLSearchParams();
            body.append('action',     'msb_rate_review');
            body.append('nonce',      msbComments.nonceRate);
            body.append('comment_id', commentId);
            if (removing) body.append('remove', '1');

            fetch(msbComments.ajaxUrl, {
                method:      'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: body.toString()
            })
                .then(r => r.json())
                .then(data => {
                    const card = this.closest('.msb-comment-card');

                    if (data && data.success && data.data && typeof data.data.count !== 'undefined') {
                        const newCount = data.data.count;
                        if (countSpan) {
                            countSpan.textContent = newCount;
                        }
                        // обновляем data-helpful для сортировки
                        if (card) {
                            card.dataset.helpful = String(newCount);
                        }
                    }
                    else if (data && !data.success && data.data && data.data.message === 'already_voted') {
                        // уже голосовал — оставляем как есть
                    }
                    else {
                        // ошибка — откат
                        this.classList.remove('msb-helpful-button--voted');
                        this.disabled = false;
                        this.dataset.loading = '0';
                        if (countSpan) {
                            countSpan.textContent = oldCount;
                        }
                        if (card) {
                            card.dataset.helpful = String(oldCount);
                        }
                    }
                })
                .catch(() => {
                    const card = this.closest('.msb-comment-card');

                    this.classList.remove('msb-helpful-button--voted');
                    this.disabled = false;
                    this.dataset.loading = '0';
                    if (countSpan) {
                        countSpan.textContent = oldCount;
                    }
                    if (card) {
                        card.dataset.helpful = String(oldCount);
                    }
                });
        });
    });


    // === НОВЫЕ РЕАКЦИИ 👍😍🤯 ===
    const reactionButtons = document.querySelectorAll('.msb-reaction-button');

    reactionButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            if (typeof msbComments === 'undefined' || !msbComments.ajaxUrl) return;

            const commentId = this.dataset.commentId;
            const reaction  = this.dataset.reaction;
            if (!commentId || !reaction) return;

            if (this.dataset.loading === '1') { return; }
            const removing = this.classList.contains('msb-reaction-button--voted');

            const countSpan = this.querySelector('.msb-reaction-count');
            const oldCount  = countSpan ? parseInt(countSpan.textContent || '0', 10) : 0;

            // Мгновенный отклик на фронте
            if (removing) { this.classList.remove('msb-reaction-button--voted'); } else { this.classList.add('msb-reaction-button--voted'); }
            if (countSpan) { countSpan.textContent = removing ? Math.max(0, oldCount - 1) : oldCount + 1; }
            this.dataset.loading = '1';

            const body = new URLSearchParams();
            body.append('action',     'msb_react_review');
            body.append('nonce',      msbComments.nonceReact);
            body.append('comment_id', commentId);
            if (removing) body.append('remove', '1');
            body.append('reaction',   reaction);
            if (removing) body.append('remove', '1');

            fetch(msbComments.ajaxUrl, {
                method:      'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: body.toString()
            })
                .then(r => r.json())
                .then(data => {
                    if (data && data.success && data.data && typeof data.data.count !== 'undefined') {
                        if (countSpan) {
                            countSpan.textContent = data.data.count;
                        }
                    }
                    else if (data && !data.success && data.data && data.data.message === 'already_voted') {
                        // уже голосовал — ничего не делаем
                    }
                    else {
                        // ошибка — откат
                        this.classList.remove('msb-reaction-button--voted');
                        if (countSpan) {
                            countSpan.textContent = oldCount;
                        }
                    }
                })
                .catch(() => {
                    this.classList.remove('msb-reaction-button--voted');
                    if (countSpan) {
                        countSpan.textContent = oldCount;
                    }
                })
                .finally(() => {
                    this.dataset.loading = '0';
                });
        });
    });

});
