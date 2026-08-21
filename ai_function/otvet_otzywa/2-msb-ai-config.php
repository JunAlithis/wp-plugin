<?php
/**
 * 2-msb-ai-config.php
 * Конфиг ИИ для АВТО-ОТВЕТА на отзывы.
 * Живёт в: ai_function/otvet_otzywa/2-msb-ai-config.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Включён ли ИИ-ответ на отзывы.
 * Если хочешь полностью отключить авто-ответы — поставь false.
 */
if ( ! defined( 'MSB_AI_REPLY_ENABLED' ) ) {
    define( 'MSB_AI_REPLY_ENABLED', true );
}

/**
 * Отдельный API-ключ для авто-ответа.
 *
 * ВАЖНО:
 *  - Основной рекомендованный способ теперь такой:
 *      зайти в настройки плагина в админке и указать ключи в полях
 *      «AI ключ для анализа» и «AI ключ для ответа».
 *  - Эта константа — только запасной вариант, если опции в админке пустые.
 *
 * Если очень хочешь задать ключ здесь, можно раскомментировать и прописать:




 *
 * if ( ! defined( 'MSB_DEEPSEEK_API_KEY_REPLY' ) ) {
 *     define( 'MSB_DEEPSEEK_API_KEY_REPLY', 'sk-...your-key-here...' );
 * }

 */
if ( ! defined( 'MSB_DEEPSEEK_API_KEY_REPLY' ) ) {
    define( 'MSB_DEEPSEEK_API_KEY_REPLY', '' );
}

/**
 * Модель DeepSeek для авто-ответа.
 * Можно использовать ту же, что и для модерации, или другую.
 */
if ( ! defined( 'MSB_AI_REPLY_MODEL' ) ) {
    define( 'MSB_AI_REPLY_MODEL', 'deepseek-chat' );
}

/**
 * Имя "бота поддержки" в авто-ответе.
 * Это будет отображаться как author у комментария-ответа.
 */
if ( ! defined( 'MSB_AI_BOT_NAME' ) ) {
    define( 'MSB_AI_BOT_NAME', 'MERYOSAB Travel — поддержка' );
}

/**
 * Email "бота поддержки". По умолчанию берётся admin_email, если пусто.
 */
if ( ! defined( 'MSB_AI_BOT_EMAIL' ) ) {
    define( 'MSB_AI_BOT_EMAIL', '' );
}

/**
 * Логирование решений ИИ-ответа (в файл: wp-content/uploads/msb_ai_reply.log).
 */
if ( ! defined( 'MSB_AI_REPLY_LOG_ENABLED' ) ) {
    define( 'MSB_AI_REPLY_LOG_ENABLED', true ); // или false, если лог не нужен
}

/**
 * Максимальный размер лог-файла для ответов (байты).
 * По умолчанию 20 МБ. Когда превысит — файл будет очищен.
 */
if ( ! defined( 'MSB_AI_REPLY_LOG_MAX_SIZE' ) ) {
    define( 'MSB_AI_REPLY_LOG_MAX_SIZE', 20 * 1024 * 1024 );
}
