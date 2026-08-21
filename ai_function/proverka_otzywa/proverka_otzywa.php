<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** AI-модерация отзывов и ответов к отзывам (DeepSeek). */
if ( ! defined( 'MSB_AI_LOG_ENABLED' ) ) define( 'MSB_AI_LOG_ENABLED', true );
if ( ! defined( 'MSB_AI_LOG_MAX_SIZE' ) ) define( 'MSB_AI_LOG_MAX_SIZE', 5 * 1024 * 1024 );
if ( ! defined( 'MSB_AI_ENABLED' ) ) define( 'MSB_AI_ENABLED', true );
require_once __DIR__ . '/msb-ai-prompt.php';

function msb_ai_log_review_decision( $commentdata, $verdict, $raw_ai_answer, $extra = array() ) {
    if ( ! MSB_AI_LOG_ENABLED ) return;
    $dir = wp_upload_dir();
    if ( empty( $dir['basedir'] ) ) return;
    $file = trailingslashit( $dir['basedir'] ) . 'msb_ai_moderation.log';
    if ( file_exists( $file ) && filesize( $file ) > MSB_AI_LOG_MAX_SIZE ) file_put_contents( $file, '' );
    $content = isset( $commentdata['comment_content'] ) ? preg_replace( '/\s+/', ' ', $commentdata['comment_content'] ) : '';
    $entry = "==============================\n" . date( 'Y-m-d H:i:s' ) . " msb-ai\n";
    $entry .= 'Post ID: ' . (int) ( $commentdata['comment_post_ID'] ?? 0 ) . "\n";
    $entry .= 'Author: ' . ( $commentdata['comment_author'] ?? '' ) . "\n";
    $entry .= 'Context: ' . ( $extra['context_type'] ?? '' ) . "\n";
    $entry .= 'Country: ' . ( $extra['country'] ?? '' ) . ' | Service: ' . ( $extra['service'] ?? '' ) . ' | Rating: ' . (int) ( $extra['rating'] ?? 0 ) . "\n";
    $entry .= "Content: {$content}\nAI raw answer: {$raw_ai_answer}\nFinal verdict: {$verdict}\n\n";
    file_put_contents( $file, $entry, FILE_APPEND | LOCK_EX );
}

if ( ! function_exists( 'msb_ai_get_analysis_api_key' ) ) {
    function msb_ai_get_analysis_api_key() {
        foreach ( array( 'msb_comments_ai_analysis_key', 'msb_comments_ai_reply_key' ) as $option ) {
            $key = trim( (string) get_option( $option, '' ) );
            if ( $key !== '' ) return $key;
        }
        if ( defined( 'MSB_DEEPSEEK_API_KEY' ) && MSB_DEEPSEEK_API_KEY ) return MSB_DEEPSEEK_API_KEY;
        return getenv( 'DEEPSEEK_API_KEY' ) ?: '';
    }
}

function msb_ai_check_review( $commentdata ) {
    if ( ! MSB_AI_ENABLED || ! is_array( $commentdata ) ) return 'manual';
    $parent_id = (int) ( $commentdata['comment_parent'] ?? 0 );
    $is_form = isset( $_POST['msb_country'] ) || isset( $_POST['msb_service_type'] ) || isset( $_POST['msb_rating'] ) || isset( $_POST['msb_media_ids'] );
    $context = $parent_id > 0 ? 'msb_reply' : ( $is_form ? 'msb_review' : 'other' );
    if ( $context === 'other' ) return 'manual';
    $key = msb_ai_get_analysis_api_key();
    if ( ! $key ) return 'manual';
    $rating = (int) ( $_POST['msb_rating'] ?? 0 );
    $country = sanitize_text_field( wp_unslash( $_POST['msb_country'] ?? '' ) );
    $service = sanitize_text_field( wp_unslash( $_POST['msb_service_type'] ?? '' ) );
    $system = function_exists( 'msb_ai_get_review_system_prompt' ) ? msb_ai_get_review_system_prompt() : 'Ответь approve, reject или manual.';
    $text = "Текст сообщения:\n" . (string) ( $commentdata['comment_content'] ?? '' ) . "\nСтрана: {$country}\nУслуга: {$service}\nРейтинг: {$rating}";
    $model = defined( 'MSB_AI_MODEL' ) && MSB_AI_MODEL ? MSB_AI_MODEL : 'deepseek-chat';
    $response = wp_remote_post( 'https://api.deepseek.com/chat/completions', array( 'headers' => array( 'Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( array( 'model' => $model, 'messages' => array( array( 'role' => 'system', 'content' => $system ), array( 'role' => 'user', 'content' => $text ) ), 'temperature' => 0, 'max_tokens' => 4 ) ), 'timeout' => 15 ) );
    if ( is_wp_error( $response ) ) return 'manual';
    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    $raw = $data['choices'][0]['message']['content'] ?? '';
    $answer = strtolower( trim( $raw ) );
    $verdict = strpos( $answer, 'reject' ) === 0 ? 'reject' : 'approve';
    msb_ai_log_review_decision( $commentdata, $verdict, $raw, array( 'country' => $country, 'service' => $service, 'rating' => $rating, 'context_type' => $context ) );
    return $verdict;
}
