<?php
/**
 * Единый клиент ИИ для плагина отзывов Meryosab (v1.8.0).
 *
 * Провайдеры:
 *  - DeepSeek            — https://api.deepseek.com/chat/completions
 *  - Google Gemini       — OpenAI-совместимый эндпоинт
 *                          https://generativelanguage.googleapis.com/v1beta/openai/chat/completions
 *  - OpenAI-совместимый  — свой базовый URL + /chat/completions
 *
 * Все вызовы идут через одну функцию msb_ai_chat(),
 * которая возвращает массив: ok, content, error, http_status, provider, model, endpoint, elapsed_ms.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Список провайдеров: id => [ 'label' => ..., 'flash' => модель, 'pro' => модель, 'hint' => ... ].
 * «По умолчанию» = flash-модель провайдера.
 */
function msb_ai_providers() {
    return array(
        'deepseek'      => array(
            'label' => 'DeepSeek',
            'flash' => 'deepseek-v4-flash',
            'pro'   => 'deepseek-v4-pro',
            'hint'  => 'DeepSeek — ключ на platform.deepseek.com.',
        ),
        'gemini'        => array(
            'label' => 'Google Gemini (Flash)',
            'flash' => 'gemini-2.5-flash',
            'pro'   => 'gemini-2.5-pro',
            'hint'  => 'Gemini — ключ в Google AI Studio (aistudio.google.com/apikey).',
        ),
        'openai_compat' => array(
            'label' => 'OpenAI-совместимый (свой URL)',
            'flash' => 'gpt-4o-mini',
            'pro'   => 'gpt-4o',
            'hint'  => 'Укажите базовый URL провайдера, например https://api.openai.com/v1',
        ),
    );
}

/**
 * Текущий провайдер из настроек.
 */
function msb_ai_get_provider() {
    $provider = (string) get_option( 'msb_comments_ai_provider', 'deepseek' );
    $known    = msb_ai_providers();
    return isset( $known[ $provider ] ) ? $provider : 'deepseek';
}

/**
 * ID модели для цели 'check' (проверка отзывов) или 'reply' (ответы).
 *
 * Опция хранит выбор: 'default' | 'flash' | 'pro'.
 * 'default' = рекомендуемая flash-модель провайдера.
 */
function msb_ai_get_model_id( $purpose ) {
    $providers = msb_ai_providers();
    $provider  = msb_ai_get_provider();

    $choice = (string) get_option( 'msb_comments_ai_model_' . ( 'reply' === $purpose ? 'reply' : 'check' ), 'default' );
    if ( 'pro' !== $choice ) {
        $choice = 'flash';
    }

    return $providers[ $provider ][ $choice ];
}

/**
 * Flash-модель текущего провайдера (для подписи «По умолчанию (…)» в админке).
 */
function msb_ai_default_model_label() {
    $providers = msb_ai_providers();
    return $providers[ msb_ai_get_provider() ]['flash'];
}

/**
 * URL API для текущего провайдера.
 */
function msb_ai_get_endpoint() {
    $provider = msb_ai_get_provider();

    switch ( $provider ) {
        case 'gemini':
            return 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions';

        case 'openai_compat':
            $base = rtrim( (string) get_option( 'msb_comments_ai_openai_url', '' ), '/' );
            return '' !== $base ? $base . '/chat/completions' : '';

        case 'deepseek':
        default:
            return 'https://api.deepseek.com/chat/completions';
    }
}

/**
 * API-ключ для цели. Для ответов: свой ключ, а если пусто — ключ анализа.
 */
function msb_ai_get_key( $purpose ) {
    if ( 'reply' === $purpose ) {
        $key = trim( (string) get_option( 'msb_comments_ai_reply_key', '' ) );
        if ( '' !== $key ) {
            return $key;
        }
    }

    $key = trim( (string) get_option( 'msb_comments_ai_analysis_key', '' ) );
    if ( '' !== $key ) {
        return $key;
    }

    // Запасные варианты (составная совместимость со старыми версиями).
    if ( defined( 'MSB_DEEPSEEK_API_KEY' ) && MSB_DEEPSEEK_API_KEY ) {
        $key = MSB_DEEPSEEK_API_KEY;
    }
    if ( '' === $key ) {
        $key = getenv( 'DEEPSEEK_API_KEY' ) ?: '';
    }

    return $key;
}

/**
 * Имя бота поддержки для авто-ответов.
 */
function msb_ai_get_bot_name() {
    $name = trim( (string) get_option( 'msb_comments_ai_bot_name', '' ) );
    return '' !== $name ? $name : 'PC Repair — команда поддержки';
}

/**
 * Человекочитаемое объяснение HTTP-кода ошибки.
 */
function msb_ai_explain_status( $status ) {
    $status = (int) $status;

    if ( 400 === $status ) {
        return 'некорректный запрос (проверьте формат ключа, URL и модель)';
    }
    if ( 401 === $status || 403 === $status ) {
        return 'неверный или недействительный API-ключ';
    }
    if ( 402 === $status ) {
        return 'недостаточно средств на аккаунте провайдера';
    }
    if ( 404 === $status ) {
        return 'модель или эндпоинт не найдены (проверьте название модели и URL)';
    }
    if ( 408 === $status ) {
        return 'таймаут запроса';
    }
    if ( 429 === $status ) {
        return 'превышен лимит запросов (попробуйте позже)';
    }
    if ( $status >= 500 ) {
        return 'ошибка сервера провайдера (попробуйте позже)';
    }

    return '';
}

/**
 * Основной вызов ИИ.
 *
 * @param string $system_prompt Системный промпт.
 * @param string $user_prompt   Текст (отзыв / тест).
 * @param string $purpose       'check' | 'reply' — выбирает модель и ключ.
 * @param int    $max_tokens    Лимит токенов ответа.
 * @param float  $temperature   Температура.
 * @return array{ok:bool, content:string, error:string, http_status:int, provider:string, model:string, endpoint:string, elapsed_ms:int}
 */
function msb_ai_chat( $system_prompt, $user_prompt, $purpose = 'check', $max_tokens = 400, $temperature = 0 ) {
    $providers = msb_ai_providers();
    $provider  = msb_ai_get_provider();
    $label     = $providers[ $provider ]['label'];
    $model     = msb_ai_get_model_id( $purpose );
    $endpoint  = msb_ai_get_endpoint();
    $key       = msb_ai_get_key( $purpose );

    $result = array(
        'ok'          => false,
        'content'     => '',
        'error'       => '',
        'http_status' => 0,
        'provider'    => $label,
        'model'       => $model,
        'endpoint'    => $endpoint,
        'elapsed_ms'  => 0,
    );

    if ( '' === $endpoint ) {
        $result['error'] = 'Не задан URL провайдера (поле «OpenAI-совместимый URL» в настройках).';
        return $result;
    }
    if ( '' === $key ) {
        $result['error'] = 'Не задан API-ключ (' . ( 'reply' === $purpose ? 'ключ для ответов и ключ для анализа пустые' : 'ключ для анализа пуст') . ').' ;
        return $result;
    }

    $started = function_exists( 'hrtime' ) ? hrtime() : (int) ( microtime( true ) * 1000000 );

    $response = wp_remote_post(
        $endpoint,
        array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode(
                array(
                    'model'       => $model,
                    'messages'    => array(
                        array( 'role' => 'system', 'content' => $system_prompt ),
                        array( 'role' => 'user', 'content'  => $user_prompt ),
                    ),
                    'temperature' => $temperature,
                    'max_tokens'  => (int) $max_tokens,
                )
            ),
            'timeout' => 30,
        )
    );

    $elapsed_ms = (int) ( ( ( function_exists( 'hrtime' ) ? hrtime() : (int) ( microtime( true ) * 1000000 ) ) - $started ) / 1000000 );
    $result['elapsed_ms'] = $elapsed_ms;

    if ( is_wp_error( $response ) ) {
        $result['error'] = sprintf(
            'Сетевая ошибка при обращении к %s: %s. Проверьте доступ сервера сайта к интернету и SSL.',
            $label,
            $response->get_error_message()
        );
        return $result;
    }

    $status = (int) wp_remote_retrieve_response_code( $response );
    $body   = (string) wp_remote_retrieve_body( $response );
    $result['http_status'] = $status;

    if ( $status < 200 || $status >= 300 ) {
        $explain = msb_ai_explain_status( $status );
        $result['error'] = sprintf(
            '%s: HTTP %d — %s.',
            $label,
            $status,
            $explain
        );

        $snippet = trim( preg_replace( '/\s+/', ' ', $body ) );
        if ( '' !== $snippet ) {
            $result['error'] .= ' Ответ провайдера: «' . mb_substr( $snippet, 0, 300 ) . '»';
        }
        return $result;
    }

    $data = json_decode( $body, true );

    // OpenAI-совместимый формат: choices[0].message.content
    if ( is_array( $data ) && isset( $data['choices'][0]['message']['content'] ) ) {
        $result['content'] = trim( (string) $data['choices'][0]['message']['content'] );
    }

    if ( '' === $result['content'] ) {
        $result['error'] = sprintf(
            '%s: неожиданный формат ответа (HTTP %d). Начало ответа: «%s»',
            $label,
            $status,
            mb_substr( trim( preg_replace( '/\s+/', ' ', $body ) ), 0, 300 )
        );
        return $result;
    }

    $result['ok'] = true;
    return $result;
}

/**
 * Диагностика: реальный тестовый запрос к провайдеру.
 *
 * @return array{ok:bool, message:string}
 */
function msb_ai_diagnose() {
    $result = msb_ai_chat(
        'Ты — диагностический инструмент. Отвечай только одним словом: OK.',
        'Отвечай только одним словом: OK.',
        'check',
        16
    );

    if ( $result['ok'] ) {
        return array(
            'ok'      => true,
            'message' => sprintf(
                'Всё работает. %s: модель %s, HTTP %d, ответ получен за %d мс. Ответ модели: «%s»',
                $result['provider'],
                $result['model'],
                $result['http_status'],
                $result['elapsed_ms'],
                mb_substr( $result['content'], 0, 120 )
            ),
        );
    }

    return array(
        'ok'      => false,
        'message' => $result['error'] . ' URL запроса: ' . $result['endpoint'] . '. Модель: ' . $result['model'] . '.',
    );
}
