<?php
/**
 * Конфиг для интеграции отзывов Яндекс.
 * Путь: reviews_gl_yndx/yandex/config_yandex.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Ключ опций (одна опция = массив всех настроек Yandex Reviews)
if ( ! defined( 'MSB_YANDEX_REVIEWS_OPTION_KEY' ) ) {
    define( 'MSB_YANDEX_REVIEWS_OPTION_KEY', 'msb_yandex_reviews_settings' );
}

/**
 * Получить и нормализовать настройки Яндекс-отзывов.
 *
 * @return array
 */
if ( ! function_exists( 'msb_yandex_reviews_get_settings' ) ) {

    function msb_yandex_reviews_get_settings() {

        $defaults = array(
            'enabled'           => 0,
            'target_page_id'    => 0,
            'max_reviews'       => 50,
            'json_file_name'    => 'msb_yandex_reviews.json',
            'append_source_tag' => 1,
            'source_tag_text'   => 'Отзыв с Яндекс.Карт',
            'last_sync'         => '',
            'last_sync_status'  => '',
            'last_sync_error'   => '',
        );

        $stored = get_option( MSB_YANDEX_REVIEWS_OPTION_KEY, array() );
        if ( ! is_array( $stored ) ) {
            $stored = array();
        }

        $settings = array_merge( $defaults, $stored );

        $settings['enabled']        = ! empty( $settings['enabled'] ) ? 1 : 0;
        $settings['target_page_id'] = (int) $settings['target_page_id'];
        if ( $settings['target_page_id'] < 0 ) {
            $settings['target_page_id'] = 0;
        }

        $settings['max_reviews'] = (int) $settings['max_reviews'];
        if ( $settings['max_reviews'] <= 0 ) {
            $settings['max_reviews'] = 50;
        }
        if ( $settings['max_reviews'] > 200 ) {
            $settings['max_reviews'] = 200;
        }

        $settings['json_file_name']    = is_string( $settings['json_file_name'] ) ? trim( $settings['json_file_name'] ) : 'msb_yandex_reviews.json';
        $settings['append_source_tag'] = ! empty( $settings['append_source_tag'] ) ? 1 : 0;
        $settings['source_tag_text']   = is_string( $settings['source_tag_text'] ) ? trim( $settings['source_tag_text'] ) : 'Отзыв с Яндекс.Карт';

        $settings['last_sync']        = isset( $settings['last_sync'] ) ? (string) $settings['last_sync'] : '';
        $settings['last_sync_status'] = isset( $settings['last_sync_status'] ) ? (string) $settings['last_sync_status'] : '';
        $settings['last_sync_error']  = isset( $settings['last_sync_error'] ) ? (string) $settings['last_sync_error'] : '';

        return $settings;
    }
}

/**
 * Сохранить настройки Яндекс-отзывов (если нужно программно менять).
 *
 * @param array $settings
 *
 * @return array
 */
if ( ! function_exists( 'msb_yandex_reviews_save_settings' ) ) {

    function msb_yandex_reviews_save_settings( array $settings ) {
        $current  = msb_yandex_reviews_get_settings();
        $merged   = array_merge( $current, $settings );
        update_option( MSB_YANDEX_REVIEWS_OPTION_KEY, $merged );

        return $merged;
    }
}

/**
 * Обновить служебную информацию по синхронизации (last_sync, status, error).
 *
 * @param string $status        ok|error|disabled
 * @param string $error_message Текст ошибки (опционально)
 */
if ( ! function_exists( 'msb_yandex_reviews_update_sync_meta' ) ) {

    function msb_yandex_reviews_update_sync_meta( $status, $error_message = '' ) {
        $settings = msb_yandex_reviews_get_settings();

        $settings['last_sync']        = current_time( 'mysql' );
        $settings['last_sync_status'] = (string) $status;
        $settings['last_sync_error']  = (string) $error_message;

        update_option( MSB_YANDEX_REVIEWS_OPTION_KEY, $settings );
    }
}
