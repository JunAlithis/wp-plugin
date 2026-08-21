document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('commentform');
    if (!form) return;

    const nameInput   = form.querySelector('#author');
    const emailInput  = form.querySelector('#email');
    const consentBox  = form.querySelector('#wp-comment-cookies-consent');

    // --- 1. Прочитать сохранённые данные, если есть ---
    try {
        if (window.localStorage) {
            const savedName  = localStorage.getItem('msb_user_name');
            const savedEmail = localStorage.getItem('msb_user_email');

            if (savedName && nameInput && !nameInput.value) {
                nameInput.value = savedName;
            }
            if (savedEmail && emailInput && !emailInput.value) {
                emailInput.value = savedEmail;
            }
        }
    } catch (e) {
        // без паники, просто молчим
    }

    // --- 2. При отправке формы сохраняем / очищаем ---
    form.addEventListener('submit', function () {
        try {
            if (!window.localStorage) return;

            // Если есть чекбокс "сохранить данные" — уважаем его
            const canStore = !consentBox || consentBox.checked;

            if (canStore) {
                if (nameInput && nameInput.value) {
                    localStorage.setItem('msb_user_name', nameInput.value);
                }
                if (emailInput && emailInput.value) {
                    localStorage.setItem('msb_user_email', emailInput.value);
                }
            } else {
                // Пользователь не хочет сохранять — очищаем
                localStorage.removeItem('msb_user_name');
                localStorage.removeItem('msb_user_email');
            }
        } catch (e) {
            // тоже молчим
        }
    });
});
