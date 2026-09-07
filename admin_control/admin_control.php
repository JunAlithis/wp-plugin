<?php
/**
 * Админ-страница настроек плагина комментариев Meryosab.
 *
 * Файл: admin_control/admin_control.php
 *
 * Секции:
 *  - Искусственный интеллект (провайдер, модели, ключи, бот, задержка, промпты)
 *  - Блок отзывов (подзаголовок, список услуг)
 *  - Диагностика (реальный тестовый запрос к ИИ)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Регистрируем страницу настроек в меню «Настройки».
 */
function msb_comments_register_settings_page() {
    add_options_page(
        'Настройки отзывов Meryosab',
        'Отзывы Meryosab',
        'manage_options',
        'msb-comments-settings',
        'msb_comments_render_settings_page'
    );
}
add_action( 'admin_menu', 'msb_comments_register_settings_page' );

/**
 * AJAX: тестовый запрос к ИИ (диагностика).
 */
function msb_ajax_ai_diagnose() {
    check_ajax_referer( 'msb_ai_diagnose', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Недостаточно прав.' ), 403 );
    }

    if ( ! function_exists( 'msb_ai_diagnose' ) ) {
        wp_send_json_error( array( 'message' => 'Модуль ИИ не загружен (ai_function/msb-ai-client.php отсутствует).' ), 500 );
    }

    $result = msb_ai_diagnose();

    if ( $result['ok'] ) {
        wp_send_json_success( array( 'message' => $result['message'] ) );
    }

    wp_send_json_error( array( 'message' => $result['message'] ) );
}
add_action( 'wp_ajax_msb_ai_diagnose', 'msb_ajax_ai_diagnose' );

/**
 * Сохранение настроек.
 */
function msb_comments_save_settings() {
    check_admin_referer( 'msb_comments_save_settings_nonce' );

    $text_fields = array(
        'msb_comments_ai_analysis_key',
        'msb_comments_ai_reply_key',
        'msb_comments_ai_bot_name',
        'msb_comments_subtitle_text',
        'msb_comments_service_types',
        'msb_comments_prompt_analysis',
        'msb_comments_prompt_reply',
    );

    foreach ( $text_fields as $field ) {
        $value = isset( $_POST[ $field ] ) ? wp_unslash( $_POST[ $field ] ) : '';
        update_option( $field, wp_kses_post( $value ) );
    }

    // Провайдер.
    $provider = isset( $_POST['msb_comments_ai_provider'] ) ? sanitize_key( wp_unslash( $_POST['msb_comments_ai_provider'] ) ) : 'deepseek';
    $known    = function_exists( 'msb_ai_providers' ) ? array_keys( msb_ai_providers() ) : array( 'deepseek' );
    if ( ! in_array( $provider, $known, true ) ) {
        $provider = 'deepseek';
    }
    update_option( 'msb_comments_ai_provider', $provider );

    // Модели: default | flash | pro.
    foreach ( array( 'msb_comments_ai_model_check', 'msb_comments_ai_model_reply' ) as $field ) {
        $model = isset( $_POST[ $field ] ) ? sanitize_key( wp_unslash( $_POST[ $field ] ) ) : 'default';
        if ( ! in_array( $model, array( 'default', 'flash', 'pro' ), true ) ) {
            $model = 'default';
        }
        update_option( $field, $model );
    }

    // URL OpenAI-совместимого провайдера.
    $openai_url = isset( $_POST['msb_comments_ai_openai_url'] ) ? esc_url_raw( wp_unslash( $_POST['msb_comments_ai_openai_url'] ) ) : '';
    update_option( 'msb_comments_ai_openai_url', $openai_url );

    // Задержка авто-ответа (минуты).
    $delay_from = isset( $_POST['msb_comments_ai_delay_from'] ) ? absint( $_POST['msb_comments_ai_delay_from'] ) : 5;
    $delay_to   = isset( $_POST['msb_comments_ai_delay_to'] ) ? absint( $_POST['msb_comments_ai_delay_to'] ) : 20;
    if ( $delay_from < 0 ) $delay_from = 0;
    if ( $delay_to < $delay_from ) $delay_to = $delay_from;
    update_option( 'msb_comments_ai_delay_from', $delay_from );
    update_option( 'msb_comments_ai_delay_to', $delay_to );

    // Пользователь для авто-ответов.
    update_option(
        'msb_comments_ai_reply_user_id',
        isset( $_POST['msb_comments_ai_reply_user_id'] ) ? absint( $_POST['msb_comments_ai_reply_user_id'] ) : 0
    );
}

/**
 * Вывод страницы настроек.
 */
function msb_comments_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( isset( $_POST['msb_comments_action'] ) ) {
        $action = sanitize_text_field( wp_unslash( $_POST['msb_comments_action'] ) );
        if ( 'save_settings' === $action ) {
            msb_comments_save_settings();
            echo '<div class="updated notice is-dismissible"><p>' .
                esc_html__( 'Настройки сохранены.', 'msb-comments' ) .
                '</p></div>';
        }
    }

    $defaults = array(
        'msb_comments_ai_analysis_key' => '',
        'msb_comments_ai_reply_key'    => '',
        'msb_comments_ai_bot_name'     => '',
        'msb_comments_subtitle_text'   => '',
        'msb_comments_service_types'   => "Ремонт компьютера\nРемонт ноутбука\nВидеокарта\nМатеринская плата\nСборка / апгрейд\nУстановка Windows\nДругое",
        'msb_comments_prompt_analysis' => '',
        'msb_comments_prompt_reply'    => '',
    );

    $values = array();
    foreach ( $defaults as $key => $default ) {
        $values[ $key ] = get_option( $key, $default );
    }

    // ИИ: провайдер, модели, URL, задержки.
    $providers      = function_exists( 'msb_ai_providers' ) ? msb_ai_providers() : array(
        'deepseek' => array( 'label' => 'DeepSeek', 'flash' => 'deepseek-v4-flash', 'pro' => 'deepseek-v4-pro', 'hint' => 'DeepSeek — ключ на platform.deepseek.com.' ),
    );
    $provider       = (string) get_option( 'msb_comments_ai_provider', 'deepseek' );
    if ( ! isset( $providers[ $provider ] ) ) {
        $provider = 'deepseek';
    }
    $current        = $providers[ $provider ];
    $model_check    = (string) get_option( 'msb_comments_ai_model_check', 'default' );
    $model_reply    = (string) get_option( 'msb_comments_ai_model_reply', 'default' );
    $openai_url     = (string) get_option( 'msb_comments_ai_openai_url', '' );
    $delay_from     = (int) get_option( 'msb_comments_ai_delay_from', 5 );
    $delay_to       = (int) get_option( 'msb_comments_ai_delay_to', 20 );
    $reply_user_id  = absint( get_option( 'msb_comments_ai_reply_user_id', 0 ) );

    if ( ! in_array( $model_check, array( 'default', 'flash', 'pro' ), true ) ) $model_check = 'default';
    if ( ! in_array( $model_reply, array( 'default', 'flash', 'pro' ), true ) ) $model_reply = 'default';

    $model_options = array(
        'default' => sprintf( 'По умолчанию (%s)', $current['flash'] ),
        'flash'   => sprintf( '%s — Flash, быстрая и дешёвая (рекомендуется)', $current['flash'] ),
        'pro'     => sprintf( '%s — Pro, мощнее и дороже', $current['pro'] ),
    );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Настройки отзывов Meryosab', 'msb-comments' ); ?></h1>
        <form method="post">
            <?php wp_nonce_field( 'msb_comments_save_settings_nonce' ); ?>
            <input type="hidden" name="msb_comments_action" value="save_settings">

            <h2><?php esc_html_e( 'Искусственный интеллект', 'msb-comments' ); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="msb_comments_ai_provider"><?php esc_html_e( 'Провайдер ИИ', 'msb-comments' ); ?></label></th>
                    <td>
                        <select id="msb_comments_ai_provider" name="msb_comments_ai_provider">
                            <?php foreach ( $providers as $pid => $pinfo ) : ?>
                                <option value="<?php echo esc_attr( $pid ); ?>" <?php selected( $provider, $pid ); ?>><?php echo esc_html( $pinfo['label'] ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description" id="msb-ai-provider-hint"><?php echo esc_html( $current['hint'] ); ?></p>
                    </td>
                </tr>
                <tr id="msb-ai-url-row" style="<?php echo 'openai_compat' === $provider ? '' : 'display:none;'; ?>">
                    <th scope="row"><label for="msb_comments_ai_openai_url"><?php esc_html_e( 'OpenAI-совместимый URL', 'msb-comments' ); ?></label></th>
                    <td>
                        <input type="url" class="large-text code" id="msb_comments_ai_openai_url" name="msb_comments_ai_openai_url"
                               value="<?php echo esc_attr( $openai_url ); ?>" placeholder="https://api.openai.com/v1">
                        <p class="description"><?php esc_html_e( 'Базовый URL провайдера (без /chat/completions). Пример: https://api.openai.com/v1', 'msb-comments' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="msb_comments_ai_model_check"><?php esc_html_e( 'Модель для проверки отзывов', 'msb-comments' ); ?></label></th>
                    <td>
                        <select id="msb_comments_ai_model_check" name="msb_comments_ai_model_check">
                            <?php foreach ( $model_options as $mval => $mlabel ) : ?>
                                <option value="<?php echo esc_attr( $mval ); ?>" <?php selected( $model_check, $mval ); ?>><?php echo esc_html( $mlabel ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( '«По умолчанию» = рекомендуемая Flash-модель провайдера.', 'msb-comments' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="msb_comments_ai_model_reply"><?php esc_html_e( 'Модель для ответов на отзывы', 'msb-comments' ); ?></label></th>
                    <td>
                        <select id="msb_comments_ai_model_reply" name="msb_comments_ai_model_reply">
                            <?php foreach ( $model_options as $mval => $mlabel ) : ?>
                                <option value="<?php echo esc_attr( $mval ); ?>" <?php selected( $model_reply, $mval ); ?>><?php echo esc_html( $mlabel ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( '«По умолчанию» = рекомендуемая Flash-модель провайдера.', 'msb-comments' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="msb_comments_ai_analysis_key"><?php esc_html_e( 'Ключ для анализа отзывов', 'msb-comments' ); ?></label></th>
                    <td>
                        <input type="password" class="large-text code" id="msb_comments_ai_analysis_key" name="msb_comments_ai_analysis_key"
                               value="<?php echo esc_attr( $values['msb_comments_ai_analysis_key'] ); ?>" autocomplete="new-password">
                        <p class="description"><?php esc_html_e( 'API-ключ выбранного провайдера. Без ключа все отзывы уходят на ручную модерацию.', 'msb-comments' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="msb_comments_ai_reply_key"><?php esc_html_e( 'Ключ для ответов на отзывы', 'msb-comments' ); ?></label></th>
                    <td>
                        <input type="password" class="large-text code" id="msb_comments_ai_reply_key" name="msb_comments_ai_reply_key"
                               value="<?php echo esc_attr( $values['msb_comments_ai_reply_key'] ); ?>" autocomplete="new-password">
                        <p class="description"><?php esc_html_e( 'Можно оставить пустым — возьмётся ключ анализа.', 'msb-comments' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="msb_comments_ai_bot_name"><?php esc_html_e( 'Имя бота поддержки', 'msb-comments' ); ?></label></th>
                    <td>
                        <input type="text" class="regular-text" id="msb_comments_ai_bot_name" name="msb_comments_ai_bot_name"
                               value="<?php echo esc_attr( $values['msb_comments_ai_bot_name'] ); ?>" placeholder="PC Repair — команда поддержки">
                        <p class="description"><?php esc_html_e( 'Имя, под которым появляются авто-ответы ИИ. Пусто = «PC Repair — команда поддержки».', 'msb-comments' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="msb_comments_ai_reply_user_id"><?php esc_html_e( 'Пользователь для авто-ответов ИИ', 'msb-comments' ); ?></label></th>
                    <td>
                        <input type="number" class="small-text" id="msb_comments_ai_reply_user_id" name="msb_comments_ai_reply_user_id" value="<?php echo esc_attr( $reply_user_id ); ?>">
                        <p class="description"><?php esc_html_e( 'ID пользователя WordPress; 0/пусто — первый администратор.', 'msb-comments' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Задержка автоответа (минуты)', 'msb-comments' ); ?></th>
                    <td>
                        <label for="msb_comments_ai_delay_from"><?php esc_html_e( 'от', 'msb-comments' ); ?></label>
                        <input type="number" class="small-text" min="0" id="msb_comments_ai_delay_from" name="msb_comments_ai_delay_from" value="<?php echo esc_attr( $delay_from ); ?>">
                        <label for="msb_comments_ai_delay_to"><?php esc_html_e( 'до', 'msb-comments' ); ?></label>
                        <input type="number" class="small-text" min="0" id="msb_comments_ai_delay_to" name="msb_comments_ai_delay_to" value="<?php echo esc_attr( $delay_to ); ?>">
                        <p class="description"><?php esc_html_e( 'Ответ появится через случайный интервал из этого диапазона после публикации отзыва — выглядит естественно. Точность зависит от посещаемости сайта (стандартный WP-Cron). Если за это время на отзыв ответили вручную — бот промолчит.', 'msb-comments' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="msb_comments_prompt_analysis"><?php esc_html_e( 'Промпт анализа отзывов', 'msb-comments' ); ?></label></th>
                    <td>
                        <textarea class="large-text code" rows="10" id="msb_comments_prompt_analysis" name="msb_comments_prompt_analysis"><?php echo esc_textarea( $values['msb_comments_prompt_analysis'] ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Пусто = встроенный универсальный промпт (approve / reject / manual).', 'msb-comments' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="msb_comments_prompt_reply"><?php esc_html_e( 'Промпт ответов на отзывы', 'msb-comments' ); ?></label></th>
                    <td>
                        <textarea class="large-text code" rows="10" id="msb_comments_prompt_reply" name="msb_comments_prompt_reply"><?php echo esc_textarea( $values['msb_comments_prompt_reply'] ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Пусто = встроенный промпт (короткий вежливый ответ на языке отзыва).', 'msb-comments' ); ?></p>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e( 'Блок отзывов', 'msb-comments' ); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="msb_comments_subtitle_text"><?php esc_html_e( 'Подзаголовок блока отзывов', 'msb-comments' ); ?></label></th>
                    <td><input type="text" class="regular-text" id="msb_comments_subtitle_text" name="msb_comments_subtitle_text" value="<?php echo esc_attr( $values['msb_comments_subtitle_text'] ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="msb_comments_service_types"><?php esc_html_e( 'Список услуг', 'msb-comments' ); ?></label></th>
                    <td><textarea class="large-text code" rows="6" id="msb_comments_service_types" name="msb_comments_service_types"><?php echo esc_textarea( $values['msb_comments_service_types'] ); ?></textarea></td>
                </tr>
            </table>

            <h2><?php esc_html_e( 'Диагностика', 'msb-comments' ); ?></h2>
            <p><?php esc_html_e( 'Если отзывы не публикуются автоматически — нажмите кнопку: плагин сделает реальный тестовый запрос к ИИ и покажет точную причину сбоя (неверный ключ, баланс, сеть и т.д.).', 'msb-comments' ); ?></p>
            <p>
                <button type="button" class="button button-primary" id="msb-ai-test-btn"><?php esc_html_e( 'Запустить тест ИИ', 'msb-comments' ); ?></button>
                <span id="msb-ai-test-busy" style="display:none; color:#666;"><?php esc_html_e( 'Отправляем тестовый запрос…', 'msb-comments' ); ?></span>
            </p>
            <pre id="msb-ai-test-result" style="display:none; max-width:900px; white-space:pre-wrap; padding:12px; background:#f6f7f7; border:1px solid #c3c4c7; border-radius:4px; margin:8px 0 24px;"></pre>

            <?php submit_button( __( 'Сохранить изменения', 'msb-comments' ) ); ?>
        </form>

        <script>
        (function () {
            'use strict';
            var MSB_AI_ADMIN = <?php echo wp_json_encode(
                array(
                    'ajax_url'   => admin_url( 'admin-ajax.php' ),
                    'nonce'      => wp_create_nonce( 'msb_ai_diagnose' ),
                    'providers'  => $providers,
                )
            ); ?>;

            /* ---- Смена провайдера: обновляем варианты моделей, подсказку и строку URL ---- */
            var providerSelect = document.getElementById('msb_comments_ai_provider');
            var modelCheckSel  = document.getElementById('msb_comments_ai_model_check');
            var modelReplySel  = document.getElementById('msb_comments_ai_model_reply');
            var urlRow         = document.getElementById('msb-ai-url-row');
            var hint           = document.getElementById('msb-ai-provider-hint');

            function buildModelSelect(sel, providerKey) {
                var p = MSB_AI_ADMIN.providers[providerKey];
                if (!p || !sel) return;
                var prev = sel.value;
                if (['default', 'flash', 'pro'].indexOf(prev) === -1) prev = 'default';
                var opts = [
                    ['default', 'По умолчанию (' + p.flash + ')'],
                    ['flash', p.flash + ' — Flash, быстрая и дешёвая (рекомендуется)'],
                    ['pro', p.pro + ' — Pro, мощнее и дороже']
                ];
                sel.innerHTML = '';
                opts.forEach(function (o) {
                    var opt = document.createElement('option');
                    opt.value = o[0];
                    opt.textContent = o[1];
                    if (o[0] === prev) opt.selected = true;
                    sel.appendChild(opt);
                });
            }

            function syncProviderUi(providerKey) {
                var p = MSB_AI_ADMIN.providers[providerKey];
                if (!p) return;
                buildModelSelect(modelCheckSel, providerKey);
                buildModelSelect(modelReplySel, providerKey);
                if (hint) hint.textContent = p.hint;
                if (urlRow) urlRow.style.display = (providerKey === 'openai_compat') ? '' : 'none';
            }

            if (providerSelect) {
                providerSelect.addEventListener('change', function () {
                    syncProviderUi(this.value);
                });
                syncProviderUi(providerSelect.value);
            }

            /* ---- Диагностика: реальный тестовый запрос ---- */
            var btn = document.getElementById('msb-ai-test-btn');
            if (btn) {
                var result = document.getElementById('msb-ai-test-result');
                var busy = document.getElementById('msb-ai-test-busy');

                function showResult(message, failed) {
                    if (result) {
                        result.textContent = (failed ? '✗ ' : '✓ ') + message;
                        result.style.borderColor = failed ? '#d63638' : '#00a32a';
                        result.style.background = failed ? '#fcf0f1' : '#edfaef';
                        result.style.color = failed ? '#8a2426' : '#005c12';
                        result.style.display = 'block';
                    }
                }

                function handleData(data) {
                    var msg = (data && data.data && data.data.message) ? data.data.message
                            : (data && data.message) ? data.message : 'Неизвестный ответ.';
                    showResult(msg, data && data.success === false);
                }

                function finish() {
                    btn.disabled = false;
                    if (busy) busy.style.display = 'none';
                }

                function failText(status, body) {
                    var clean = body ? String(body).replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim() : '';
                    return 'Ошибка запроса (HTTP ' + status + ') к ' + MSB_AI_ADMIN.ajax_url +
                        '. Сервер вернул HTML-страницу вместо JSON — обычно это WAF/кэш/защита хостинга ' +
                        'блокирует запрос к admin-ajax.php. ' +
                        (clean ? 'Начало ответа: «' + clean.slice(0, 200) + '»' : '') +
                        ' Попробуйте ещё раз или временно отключите кэширование/WAF.';
                }

                btn.addEventListener('click', function () {
                    btn.disabled = true;
                    if (busy) busy.style.display = 'inline';
                    if (result) result.style.display = 'none';

                    var data = { action: 'msb_ai_diagnose', nonce: MSB_AI_ADMIN.nonce };

                    /* Вариант 1: jQuery (есть на всех админ-страницах) —
                       запрос идёт так же, как штатные AJAX-вызовы WordPress:
                       X-Requested-With + x-www-form-urlencoded. */
                    if (window.jQuery) {
                        jQuery.post(MSB_AI_ADMIN.ajax_url, data)
                            .done(function (r) {
                                if (typeof r === 'string') {
                                    // admin-ajax отвечает строкой: 0 (нет хука), -1 (nonce/права)
                                    showResult('Не-JSON ответ от сервера: «' + String(r).slice(0, 120) +
                                        '». Если «-1» — сбой nonce/прав, перезагрузите страницу и повторите.', true);
                                    return;
                                }
                                handleData(r);
                            })
                            .fail(function (xhr) {
                                showResult(failText(xhr && xhr.status ? xhr.status : 'нет', xhr && xhr.responseText), true);
                            })
                            .always(finish);
                        return;
                    }

                    /* Вариант 2: fetch с тем же оформлением запроса, что и jQuery. */
                    fetch(MSB_AI_ADMIN.ajax_url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                        },
                        body: 'action=msb_ai_diagnose&nonce=' + encodeURIComponent(MSB_AI_ADMIN.nonce)
                    })
                        .then(function (r) {
                            return r.text().then(function (text) {
                                return { status: r.status, text: text };
                            });
                        })
                        .then(function (res) {
                            if (res.status < 200 || res.status >= 300) {
                                showResult(failText(res.status, res.text), true);
                                return;
                            }
                            var data2;
                            try {
                                data2 = JSON.parse(res.text);
                            } catch (e) {
                                showResult(failText(res.status, res.text), true);
                                return;
                            }
                            handleData(data2);
                        })
                        .catch(function (e) {
                            showResult('Ошибка запроса: ' + e + '. URL: ' + MSB_AI_ADMIN.ajax_url, true);
                        })
                        .then(finish);
                });
            }
        })();
        </script>
    </div>
    <?php
}
