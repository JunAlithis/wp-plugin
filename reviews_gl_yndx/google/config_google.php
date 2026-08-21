<?php
/**
 * Конфиг для интеграции отзывов Google.
 * Путь: reviews_gl_yndx/google/config_google.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'MSB_GOOGLE_REVIEWS_OPTION_KEY' ) ) {
    define( 'MSB_GOOGLE_REVIEWS_OPTION_KEY', 'msb_google_reviews_settings' );
}

if ( ! function_exists( 'msb_google_reviews_get_settings' ) ) {
    function msb_google_reviews_get_settings() {
        $defaults = array(
            'enabled'           => 0,
            'place_id'          => '',
            'api_key'           => '',
            'target_page_id'    => 0,
            'max_reviews'       => 20,
            'language'          => '',
            'append_source_tag' => 1,
            'source_tag_text'   => 'Отзыв с Google',
            'last_sync'         => '',
            'last_sync_status'  => '',
            'last_sync_error'   => '',
        );
        $stored = get_option( MSB_GOOGLE_REVIEWS_OPTION_KEY, array() );
        if ( ! is_array( $stored ) ) $stored = array();
        $settings = array_merge( $defaults, $stored );
        $settings['enabled'] = ! empty( $settings['enabled'] ) ? 1 : 0;
        $settings['place_id'] = is_string( $settings['place_id'] ) ? trim( $settings['place_id'] ) : '';
        $settings['api_key'] = is_string( $settings['api_key'] ) ? trim( $settings['api_key'] ) : '';
        $settings['target_page_id'] = max( 0, (int) $settings['target_page_id'] );
        $settings['max_reviews'] = (int) $settings['max_reviews'];
        if ( $settings['max_reviews'] <= 0 ) $settings['max_reviews'] = 20;
        if ( $settings['max_reviews'] > 50 ) $settings['max_reviews'] = 50;
        $settings['language'] = is_string( $settings['language'] ) ? trim( $settings['language'] ) : '';
        $settings['append_source_tag'] = ! empty( $settings['append_source_tag'] ) ? 1 : 0;
        $settings['source_tag_text'] = is_string( $settings['source_tag_text'] ) ? trim( $settings['source_tag_text'] ) : 'Отзыв с Google';
        $settings['last_sync'] = (string) $settings['last_sync'];
        $settings['last_sync_status'] = (string) $settings['last_sync_status'];
        $settings['last_sync_error'] = (string) $settings['last_sync_error'];
        return $settings;
    }
}

if ( ! function_exists( 'msb_google_reviews_save_settings' ) ) {
    function msb_google_reviews_save_settings( array $settings ) {
        $merged = array_merge( msb_google_reviews_get_settings(), $settings );
        update_option( MSB_GOOGLE_REVIEWS_OPTION_KEY, $merged );
        return $merged;
    }
}

if ( ! function_exists( 'msb_google_reviews_update_sync_meta' ) ) {
    function msb_google_reviews_update_sync_meta( $status, $error_message = '' ) {
        $settings = msb_google_reviews_get_settings();
        $settings['last_sync'] = current_time( 'mysql' );
        $settings['last_sync_status'] = (string) $status;
        $settings['last_sync_error'] = (string) $error_message;
        update_option( MSB_GOOGLE_REVIEWS_OPTION_KEY, $settings );
    }
}
