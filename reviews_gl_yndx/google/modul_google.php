<?php
/** Модуль импорта отзывов Google в комментарии WordPress. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! function_exists( 'msb_google_reviews_get_settings' ) ) {
    $config_file = trailingslashit( dirname( __FILE__ ) ) . 'config_google.php';
    if ( file_exists( $config_file ) ) require_once $config_file;
}

if ( ! function_exists( 'msb_google_reviews_sync_now' ) ) {
    function msb_google_reviews_sync_now( $force = false ) {
        if ( ! function_exists( 'msb_google_reviews_get_settings' ) ) return new WP_Error( 'config_missing', 'config_google.php не подключён.' );
        $settings = msb_google_reviews_get_settings();
        if ( ! $force && empty( $settings['enabled'] ) ) return new WP_Error( 'disabled', 'Импорт отзывов Google отключён.' );
        if ( empty( $settings['place_id'] ) ) return new WP_Error( 'no_place_id', 'Не указан Place ID.' );
        if ( empty( $settings['api_key'] ) ) return new WP_Error( 'no_api_key', 'Не указан API-ключ Google Places API.' );
        if ( empty( $settings['target_page_id'] ) ) return new WP_Error( 'no_target_page', 'Не указан ID страницы.' );
        $args = array( 'place_id' => $settings['place_id'], 'fields' => 'name,rating,user_ratings_total,reviews', 'key' => $settings['api_key'] );
        if ( ! empty( $settings['language'] ) ) $args['language'] = $settings['language'];
        $response = wp_remote_get( add_query_arg( $args, 'https://maps.googleapis.com/maps/api/place/details/json' ), array( 'timeout' => 20 ) );
        if ( is_wp_error( $response ) ) return $response;
        if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) return new WP_Error( 'bad_http', 'Ошибка HTTP при запросе Google.' );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) ) return new WP_Error( 'bad_json', 'Не удалось декодировать ответ Google.' );
        $reviews = isset( $data['result']['reviews'] ) && is_array( $data['result']['reviews'] ) ? $data['result']['reviews'] : array();
        $reviews = array_slice( $reviews, 0, max( 1, (int) $settings['max_reviews'] ) );
        $created = 0; $skipped = 0;
        foreach ( $reviews as $review ) {
            if ( empty( $review['text'] ) ) { $skipped++; continue; }
            $author = (string) ( $review['author_name'] ?? '' ); $rating = (int) ( $review['rating'] ?? 0 ); $time = (int) ( $review['time'] ?? 0 );
            $hash = md5( $author . '|' . $rating . '|' . $time . '|' . $review['text'] );
            if ( get_comments( array( 'meta_key' => 'msb_google_review_hash', 'meta_value' => $hash, 'count' => true ) ) > 0 ) { $skipped++; continue; }
            $content = (string) $review['text'];
            if ( ! empty( $settings['append_source_tag'] ) ) $content .= "\n\n[" . ( $settings['source_tag_text'] ?: 'Отзыв с Google' ) . ']';
            $gmt = $time ? gmdate( 'Y-m-d H:i:s', $time ) : current_time( 'mysql', true );
            $id = wp_insert_comment( wp_slash( array( 'comment_post_ID' => (int) $settings['target_page_id'], 'comment_author' => $author ?: 'Google User', 'comment_author_email' => '', 'comment_author_url' => esc_url_raw( $review['author_url'] ?? '' ), 'comment_content' => $content, 'comment_agent' => 'msb-google-import', 'comment_date' => get_date_from_gmt( $gmt ), 'comment_date_gmt' => $gmt, 'comment_approved' => 1 ) ) );
            if ( ! $id || is_wp_error( $id ) ) { $skipped++; continue; }
            if ( $rating > 0 ) update_comment_meta( $id, 'msb_rating', $rating );
            update_comment_meta( $id, 'msb_google_review_hash', $hash ); update_comment_meta( $id, 'msb_google_review_raw', $review ); update_comment_meta( $id, 'msb_external_source', 'google' ); $created++;
        }
        if ( function_exists( 'msb_google_reviews_update_sync_meta' ) ) msb_google_reviews_update_sync_meta( 'ok', sprintf( 'Импортировано: %d, пропущено: %d, всего: %d.', $created, $skipped, count( $reviews ) ) );
        return array( 'created' => $created, 'skipped' => $skipped, 'total' => count( $reviews ) );
    }
}
