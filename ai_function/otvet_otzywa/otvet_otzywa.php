<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Авто-ответ ИИ на одобренные родительские MSB-отзывы. */
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

function msb_ai_maybe_reply_comment( $comment_id, $approved ) {
    if ( defined( 'MSB_AI_REPLY_ENABLED' ) && ! MSB_AI_REPLY_ENABLED ) return;
    if ( (string) $approved !== '1' && (int) $approved !== 1 ) return;
    if ( ! function_exists( 'msb_ai_get_reply_api_key' ) ) return;
    $api_key = msb_ai_get_reply_api_key();
    if ( ! $api_key ) return;

    $comment = get_comment( $comment_id );
    if ( ! $comment || (int) $comment->comment_parent > 0 ) return;
    $rating = get_comment_meta( $comment_id, 'msb_rating', true );
    $service = get_comment_meta( $comment_id, 'msb_service_type', true );
    if ( $rating === '' && $service === '' ) return;
    if ( get_comments( array( 'parent' => $comment_id, 'meta_key' => 'msb_ai_reply', 'meta_value' => '1', 'count' => true, 'status' => 'approve' ) ) > 0 ) return;

    $prompt = function_exists( 'msb_ai_get_reply_system_prompt' ) ? msb_ai_get_reply_system_prompt() : 'Ответь вежливо на отзыв клиента.';
    $user_text = "Новый отзыв клиента:\n\n" . $comment->comment_content . "\n\n";
    $user_text .= 'Рейтинг: ' . ( $rating !== '' ? $rating : 'не указан' ) . "\nТип услуги: " . ( $service !== '' ? $service : 'не указан' );
    $user_text .= "\n\nСоставь короткий вежливый ответ на языке отзыва. Не упоминай ИИ. Обычно 1–4 предложения.";
    $model = defined( 'MSB_AI_REPLY_MODEL' ) && MSB_AI_REPLY_MODEL ? MSB_AI_REPLY_MODEL : 'deepseek-chat';

    $response = wp_remote_post( 'https://api.deepseek.com/chat/completions', array(
        'headers' => array( 'Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json' ),
        'body' => wp_json_encode( array( 'model' => $model, 'messages' => array(
            array( 'role' => 'system', 'content' => $prompt ),
            array( 'role' => 'user', 'content' => $user_text ),
        ), 'temperature' => 0.4, 'max_tokens' => 400 ) ),
        'timeout' => 20,
    ) );
    if ( is_wp_error( $response ) ) return;
    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    $reply = isset( $data['choices'][0]['message']['content'] ) ? trim( $data['choices'][0]['message']['content'] ) : '';
    if ( $reply === '' ) return;

    $bot_name = function_exists( 'msb_ai_get_bot_name' ) ? msb_ai_get_bot_name() : ( defined( 'MSB_AI_BOT_NAME' ) ? MSB_AI_BOT_NAME : get_bloginfo( 'name' ) . ' — команда поддержки' );
    $email = defined( 'MSB_AI_BOT_EMAIL' ) && MSB_AI_BOT_EMAIL ? MSB_AI_BOT_EMAIL : get_option( 'admin_email' );
    $user_id = (int) get_option( 'msb_comments_ai_reply_user_id', 0 );
    if ( ! $user_id ) {
        $admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => array( 'ID' ) ) );
        $user_id = ! empty( $admins[0]->ID ) ? (int) $admins[0]->ID : 0;
    }
    $reply_id = wp_insert_comment( array( 'comment_post_ID' => (int) $comment->comment_post_ID, 'comment_parent' => (int) $comment_id, 'comment_content' => $reply, 'comment_author' => $bot_name, 'comment_author_email' => $email, 'user_id' => $user_id, 'comment_approved' => 1 ) );
    if ( $reply_id && ! is_wp_error( $reply_id ) ) {
        add_comment_meta( $reply_id, 'msb_ai_reply', '1', true );
        msb_ai_reply_log( 'OK: AI reply created, comment_id=' . (int) $comment_id . ', reply_id=' . (int) $reply_id );
    }
}
add_action( 'comment_post', 'msb_ai_maybe_reply_comment', 20, 2 );
