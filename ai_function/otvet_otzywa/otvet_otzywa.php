<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Авто-ответ ИИ на одобренные родительские MSB-отзывы (v1.8.0).
 *
 * Ответ НЕ идёт мгновенно: при публикации отзыва ставится событие WP-Cron
 * со случайной задержкой из диапазона «от–до» (минуты) из настроек.
 * К моменту срабатывания, если на отзыв уже ответили вручную,
 * бот молчит.
 */
$config = __DIR__ . '/2-msb-ai-config.php';
if ( file_exists( $config ) ) {
    require_once $config;
}
require_once __DIR__ . '/msb-ai-reply-prompt.php';

if ( ! function_exists( 'msb_ai_reply_log' ) ) {
    function msb_ai_reply_log( $message ) {
        if ( defined( 'MSB_AI_REPLY_LOG_ENABLED' ) && ! MSB_AI_REPLY_LOG_ENABLED ) {
            return;
        }
        $upload = wp_get_upload_dir();
        if ( empty( $upload['basedir'] ) ) {
            return;
        }
        $file = trailingslashit( $upload['basedir'] ) . 'msb_ai_reply.log';
        $max  = defined( 'MSB_AI_REPLY_LOG_MAX_SIZE' ) ? (int) MSB_AI_REPLY_LOG_MAX_SIZE : 5 * 1024 * 1024;
        if ( file_exists( $file ) && filesize( $file ) > $max ) {
            file_put_contents( $file, '' );
        }
        file_put_contents( $file, '[' . gmdate( 'Y-m-d H:i:s' ) . '] ' . $message . PHP_EOL, FILE_APPEND );
    }
}

/**
 * Диапазон задержки авто-ответа (минуты), нормализованный: от <= до.
 *
 * @return array{0:int,1:int}
 */
function msb_ai_get_reply_delay_range() {
    $from = (int) get_option( 'msb_comments_ai_delay_from', 5 );
    $to   = (int) get_option( 'msb_comments_ai_delay_to', 20 );
    if ( $from < 0 ) $from = 0;
    if ( $to < $from ) $to = $from;
    return array( $from, $to );
}

/**
 * Планируем авто-ответ: новый одобренный родительский MSB-отзыв.
 *
 * @param int    $comment_id ID комментария.
 * @param mixed  $approved   Статус одобрения из comment_post.
 */
function msb_ai_maybe_schedule_reply( $comment_id, $approved ) {
    if ( defined( 'MSB_AI_REPLY_ENABLED' ) && ! MSB_AI_REPLY_ENABLED ) return;
    if ( (string) $approved !== '1' && (int) $approved !== 1 ) return;

    $comment = get_comment( $comment_id );
    if ( ! $comment || (int) $comment->comment_parent > 0 ) return;

    $rating  = get_comment_meta( $comment_id, 'msb_rating', true );
    $service = get_comment_meta( $comment_id, 'msb_service_type', true );
    if ( $rating === '' && $service === '' ) return; // не наш отзыв

    // Планируем один раз.
    if ( get_comment_meta( $comment_id, 'msb_ai_reply_scheduled', true ) ) return;
    if ( get_comment_meta( $comment_id, 'msb_ai_reply_done', true ) ) return;

    $api_key = function_exists( 'msb_ai_get_key' ) ? msb_ai_get_key( 'reply' ) : '';
    if ( ! $api_key ) return;

    update_comment_meta( $comment_id, 'msb_ai_reply_scheduled', 1, true );

    list( $from, $to ) = msb_ai_get_reply_delay_range();
    $delay_minutes = $from + mt_rand( 0, max( 0, $to - $from ) );

    wp_schedule_single_event( time() + $delay_minutes * MINUTE_IN_SECONDS, 'msb_ai_scheduled_reply', array( $comment_id ) );

    msb_ai_reply_log( 'SCHEDULED: comment_id=' . (int) $comment_id . ', delay_minutes=' . $delay_minutes . ' (range ' . $from . '-' . $to . ')' );
}
add_action( 'comment_post', 'msb_ai_maybe_schedule_reply', 20, 2 );

/**
 * То же самое, если отзыв одобрён вручную позже (через админку).
 *
 * @param int $comment_id ID комментария.
 */
function msb_ai_maybe_schedule_reply_on_edit( $comment_id ) {
    $comment = get_comment( $comment_id );
    if ( ! $comment || 'approve' !== $comment->comment_approved ) return;
    if ( (int) $comment->comment_parent > 0 ) return;

    $rating  = get_comment_meta( $comment_id, 'msb_rating', true );
    $service = get_comment_meta( $comment_id, 'msb_service_type', true );
    if ( $rating === '' && $service === '' ) return;

    if ( get_comment_meta( $comment_id, 'msb_ai_reply_scheduled', true ) ) return;
    if ( get_comment_meta( $comment_id, 'msb_ai_reply_done', true ) ) return;
    if ( get_comment_meta( $comment_id, 'msb_ai_reply', true ) ) return;

    $api_key = function_exists( 'msb_ai_get_key' ) ? msb_ai_get_key( 'reply' ) : '';
    if ( ! $api_key ) return;

    // У ручного одобрения задержка короче не нужна — планируем с тем же диапазоном.
    update_comment_meta( $comment_id, 'msb_ai_reply_scheduled', 1, true );

    list( $from, $to ) = msb_ai_get_reply_delay_range();
    $delay_minutes = $from + mt_rand( 0, max( 0, $to - $from ) );

    wp_schedule_single_event( time() + $delay_minutes * MINUTE_IN_SECONDS, 'msb_ai_scheduled_reply', array( $comment_id ) );

    msb_ai_reply_log( 'SCHEDULED(manual-approve): comment_id=' . (int) $comment_id . ', delay_minutes=' . $delay_minutes );
}
add_action( 'edit_comment', 'msb_ai_maybe_schedule_reply_on_edit', 20, 1 );

/**
 * Событие WP-Cron: выполнить авто-ответ.
 *
 * @param int $comment_id ID родительского отзыва.
 */
function msb_ai_run_scheduled_reply( $comment_id ) {
    $comment_id = (int) $comment_id;

    if ( defined( 'MSB_AI_REPLY_ENABLED' ) && ! MSB_AI_REPLY_ENABLED ) return;
    if ( ! function_exists( 'msb_ai_get_key' ) ) return;
    $api_key = msb_ai_get_key( 'reply' );
    if ( ! $api_key ) return;

    $comment = get_comment( $comment_id );
    if ( ! $comment || 'approve' !== $comment->comment_approved ) {
        msb_ai_reply_log( 'SKIPPED: comment ' . $comment_id . ' not approved anymore');
        return;
    }
    if ( (int) $comment->comment_parent > 0 ) return;

    // Если за время задержки на отзыв уже ответили вручную (в любом статусе) — бот молчит.
    $children = get_comments( array( 'parent' => $comment_id, 'status' => 'all' ) );
    if ( is_array( $children ) && count( $children ) > 0 ) {
        msb_ai_reply_log( 'SKIPPED: comment_id=' . $comment_id . ' — manual/other reply already exists');
        return;
    }

    if ( get_comment_meta( $comment_id, 'msb_ai_reply_done', true ) ) return;

    $rating  = get_comment_meta( $comment_id, 'msb_rating', true );
    $service = get_comment_meta( $comment_id, 'msb_service_type', true );
    $country = get_comment_meta( $comment_id, 'msb_country', true );

    $prompt = function_exists( 'msb_ai_get_reply_system_prompt' ) ? msb_ai_get_reply_system_prompt() : 'Ответь вежливо на отзыв клиента.';
    $user_text = "Новый отзыв клиента:\n\n" . $comment->comment_content . "\n\n";
    $user_text .= 'Рейтинг: ' . ( $rating !== '' ? $rating : 'не указан' ) . "\n";
    $user_text .= 'Тип услуги: ' . ( $service !== '' ? $service : 'не указан' ) . "\n";
    if ( $country !== '' ) {
        $user_text .= 'Страна: ' . $country . "\n";
    }
    $user_text .= "\nСоставь короткий вежливый ответ на языке отзыва. Не упоминай ИИ. Обычно 1–4 предложения.";

    try {
        $response = msb_ai_chat( $prompt, $user_text, 'reply', 400, 0.4 );
    } catch ( \Throwable $e ) {
        msb_ai_reply_log( 'EXCEPTION: comment_id=' . $comment_id . ' — ' . $e->getMessage() );
        return;
    }
    if ( ! $response['ok'] ) {
        msb_ai_reply_log( 'ERROR: comment_id=' . $comment_id . ' — ' . $response['error'] );
        // Разблокируем: при следующем событии не будет повторной попытки
        // (событие однократное), но отметка done не ставится.
        return;
    }

    $reply = $response['content'];
    if ( '' === $reply ) return;

    $bot_name = function_exists( 'msb_ai_get_bot_name' ) ? msb_ai_get_bot_name() : ( get_bloginfo( 'name' ) . ' — команда поддержки' );
    $email    = defined( 'MSB_AI_BOT_EMAIL' ) && MSB_AI_BOT_EMAIL ? MSB_AI_BOT_EMAIL : get_option( 'admin_email' );

    $user_id = (int) get_option( 'msb_comments_ai_reply_user_id', 0 );
    if ( ! $user_id ) {
        $admins  = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => array( 'ID' ) ) );
        $user_id = ! empty( $admins[0] ) ? (int) $admins[0]->ID : 0;
    }

    update_comment_meta( $comment_id, 'msb_ai_reply_done', 1, true );

    $reply_id = wp_insert_comment( array(
        'comment_post_ID'      => (int) $comment->comment_post_ID,
        'comment_parent'       => (int) $comment_id,
        'comment_content'      => $reply,
        'comment_author'       => $bot_name,
        'comment_author_email' => $email,
        'user_id'              => $user_id,
        'comment_approved'     => 1,
    ) );

    if ( $reply_id && ! is_wp_error( $reply_id ) ) {
        add_comment_meta( $reply_id, 'msb_ai_reply', '1', true );
        msb_ai_reply_log( 'OK: AI reply created, comment_id=' . $comment_id . ', reply_id=' . $reply_id );
    } else {
        // Не удалось вставить — снимаем отметку, чтобы дать шанс повтору при следующем событии.
        delete_comment_meta( $comment_id, 'msb_ai_reply_done' );
        msb_ai_reply_log( 'ERROR: wp_insert_comment failed for comment_id=' . $comment_id );
    }
}
add_action( 'msb_ai_scheduled_reply', 'msb_ai_run_scheduled_reply' );
