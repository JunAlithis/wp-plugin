<?php
/**
 * Plugin Name: Post Comments Single Page (Meryosab)
 * Plugin URI: https://pcrepair.meryosab.com/
 * Description: Подстраница /comments/ для комментариев и отзывов + шорткоды отзывов. Адаптировано под PC Repair by Meryosab: палитра и сетка сайта, тема light/dark, карусель отзывов с пагинацией и свайпом по страницам, ИИ-модерация (DeepSeek / Gemini / OpenAI-совместимый) и авто-ответы с задержкой.
 * * Version: 1.8.1
 * Author: Meryosab
 * Text Domain: msb-comments
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'MSB_COMMENTS_PLUGIN_FILE' ) ) {
    define( 'MSB_COMMENTS_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'MSB_COMMENTS_PLUGIN_DIR' ) ) {
    define( 'MSB_COMMENTS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'MSB_COMMENTS_PLUGIN_URL' ) ) {
    define( 'MSB_COMMENTS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'MSB_COMMENTS_TEXT_DOMAIN' ) ) {
    define( 'MSB_COMMENTS_TEXT_DOMAIN', 'msb-comments' );
}

load_plugin_textdomain(
    MSB_COMMENTS_TEXT_DOMAIN,
    false,
    dirname( plugin_basename( __FILE__ ) ) . '/languages'
);

$msb_comments_files = array(
    'ai_function/msb-ai-client.php',
    'ai_function/proverka_otzywa/msb-ai-config.php',
    'ai_function/proverka_otzywa/proverka_otzywa.php',
    'ai_function/otvet_otzywa/otvet_otzywa.php',
    'reviews_gl_yndx/google/config_google.php',
    'reviews_gl_yndx/google/modul_google.php',
    'reviews_gl_yndx/yandex/config_yandex.php',
    'proverka_fayl_foto_video/proverka_fayl_foto_video.php',
    'function_connect_js_css_php/function_connect_js_css.php',
    'admin_control/admin_control.php',
    'templates/comments-page-plugin.php',
    'shortcodes/msb-comments-shortcode.php',
);

foreach ( $msb_comments_files as $msb_comments_file ) {
    $msb_comments_path = MSB_COMMENTS_PLUGIN_DIR . $msb_comments_file;
    if ( file_exists( $msb_comments_path ) ) {
        require_once $msb_comments_path;
    }
}
