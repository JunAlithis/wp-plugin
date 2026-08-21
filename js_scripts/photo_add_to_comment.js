// js_scripts/photo_add_to_comment.js
// Мультизагрузка фото/видео (до 20 файлов) для формы отзыва

document.addEventListener('DOMContentLoaded', function () {

    const mediaField = document.querySelector('.msb-media-field');
    if (!mediaField) return;

    const maxFiles  = 20;
    const baseInput = mediaField.querySelector('input[type="file"]');

    if (!baseInput) return;

    // i18n из PHP
    const i18n = (window.msbI18n && typeof window.msbI18n === 'object') ? window.msbI18n : {};

    const addMoreFilesLabel      = i18n.addMoreFiles     || 'Добавить ещё файлы';
    const mediaMaxFilesTemplate  = i18n.mediaMaxFiles    || 'Можно прикрепить максимум %1$s файлов. Уже выбрано: %2$s.';
    const mediaMaxReachedTemplate = i18n.mediaMaxReached || 'Достигнут максимум: %s файлов.';

    function formatTemplate(template, a, b) {
        if (!template || typeof template !== 'string') return '';
        let result = template;
        if (typeof a !== 'undefined') {
            result = result.replace('%1$s', a).replace('%s', a);
        }
        if (typeof b !== 'undefined') {
            result = result.replace('%2$s', b);
        }
        return result;
    }

    // базовый инпут помечаем классом
    baseInput.classList.add('msb-media-input');

    // список выбранных файлов (чипы с именами)
    const selectedList = document.createElement('div');
    selectedList.className = 'msb-media-selected-list';
    mediaField.appendChild(selectedList);

    // кнопка "Добавить ещё файлы"
    const addBtn = document.createElement('button');
    addBtn.type = 'button';
    addBtn.className = 'msb-secondary-button msb-media-add-btn';
    addBtn.textContent = addMoreFilesLabel;
    mediaField.appendChild(addBtn);

    function getSelectedCount() {
        return selectedList.querySelectorAll('.msb-media-file-chip').length;
    }

    function handleChange(input) {
        const files = input.files;
        if (!files || !files.length) return;

        const already  = getSelectedCount();
        const newTotal = already + files.length;

        if (newTotal > maxFiles) {
            const msg = formatTemplate(mediaMaxFilesTemplate, maxFiles, already) ||
                        'Можно прикрепить максимум ' + maxFiles + ' файлов. Уже выбрано: ' + already + '.';

            alert(msg);

            // сбрасываем выбор у этого инпута
            input.value = '';
            return;
        }

        Array.from(files).forEach(function (file) {
            const chip = document.createElement('div');
            chip.className = 'msb-media-file-chip';
            chip.textContent = file.name;
            selectedList.appendChild(chip);
        });

        // инпут с выбранными файлами прячем, чтобы его больше не трогали
        input.classList.add('msb-media-input--hidden');
    }

    // первый выбор файлов
    baseInput.addEventListener('change', function () {
        handleChange(baseInput);
    });

    // "Добавить ещё файлы"
    addBtn.addEventListener('click', function (e) {
        e.preventDefault();

        if (getSelectedCount() >= maxFiles) {
            const msg = formatTemplate(mediaMaxReachedTemplate, maxFiles) ||
                        ('Достигнут максимум: ' + maxFiles + ' файлов.');
            alert(msg);
            return;
        }

        const newInput = document.createElement('input');
        newInput.type      = 'file';
        newInput.name      = 'msb_media[]';
        newInput.multiple  = true;
        newInput.accept    = baseInput.accept || 'image/*,video/*';
        newInput.className = 'msb-media-input';

        newInput.addEventListener('change', function () {
            handleChange(newInput);
        });

        // вставляем новый инпут перед кнопкой
        mediaField.insertBefore(newInput, addBtn);

        // сразу открываем диалог выбора
        newInput.click();
    });

});
