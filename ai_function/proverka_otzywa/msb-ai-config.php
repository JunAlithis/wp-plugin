<?php
/**
 * Конфиг ИИ для плагина комментариев.
 * Здесь можно задать включение/выключение ИИ, модель и настройки логов.
 *
 * ВАЖНО:
 *  - API-ключ теперь РЕКОМЕНДУЕТСЯ указывать через настройки плагина
 *    в админке (поле «AI ключ для анализа»).
 *  - Константа MSB_DEEPSEEK_API_KEY — только запасной вариант.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Включён ли ИИ-модератор.
 * Если хочешь полностью отключить — поставь false.
 */
if ( ! defined( 'MSB_AI_ENABLED' ) ) {
    define( 'MSB_AI_ENABLED', true );
}

/**
 * API-ключ DeepSeek (запасной вариант).
 *
 * Основной способ — через настройки плагина:
 *   msb_comments_ai_analysis_key  — для модерации
 *   msb_comments_ai_reply_key     — для автоответов
 *
 * Если по каким-то причинам нужно задать ключ константой, можно:
 *   - в wp-config.php:
 *       define( 'MSB_DEEPSEEK_API_KEY', 'sk-...your-key-here...' );
 *   - или здесь, раскомментировав и вписав значение.
 */
if ( ! defined( 'MSB_DEEPSEEK_API_KEY' ) ) {
    define( 'MSB_DEEPSEEK_API_KEY', '' ); // оставляем пустым по умолчанию
}

/**
 * Модель DeepSeek.
 * Можно сменить на другую, если нужно.
 */
if ( ! defined( 'MSB_AI_MODEL' ) ) {
    define( 'MSB_AI_MODEL', 'deepseek-chat' );
}

/**
 * Логирование решений ИИ (в файл wp-content/uploads/msb_ai_moderation.log).
 */
if ( ! defined( 'MSB_AI_LOG_ENABLED' ) ) {
    define( 'MSB_AI_LOG_ENABLED', true ); // или false, если лог не нужен
}

/**
 * Максимальный размер лог-файла (байты).
 * По умолчанию 20 МБ. Когда превысит — файл будет очищен.
 */
if ( ! defined( 'MSB_AI_LOG_MAX_SIZE' ) ) {
    define( 'MSB_AI_LOG_MAX_SIZE', 20 * 1024 * 1024 );
}
