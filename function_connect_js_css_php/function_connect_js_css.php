<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once MSB_COMMENTS_PLUGIN_DIR . 'proverka_fayl_foto_video/proverka_fayl_foto_video.php';

function msb_comments_enqueue_assets() {

    if ( is_admin() ) { return; }

    $td = defined('MSB_COMMENTS_TEXT_DOMAIN') ? MSB_COMMENTS_TEXT_DOMAIN : 'msb-comments';

    $plugin_url  = trailingslashit( MSB_COMMENTS_PLUGIN_URL );
    $plugin_path = trailingslashit( MSB_COMMENTS_PLUGIN_DIR );

    // --- CSS ---
    // v1.6.0 — единый файл для PC Repair by Meryosab:
    // палитра сайта (light/dark), сетка страницы 1540px,
    // карточки/форма/модалка + карусель отзывов (v1.6.0).
    // Заменяет comments-page-v1.4.0.css, ekran_nadpis_otzyw.css и comment-card.css.
    wp_enqueue_style('msb-pcr-comments', $plugin_url . 'static_css/comments-page-v1.6.0.css', array(), '1.6.0');

    wp_enqueue_style('msb-recent-reviews', $plugin_url . 'static_css/recent-reviews-grid.css', array(), '1.0.0');
    wp_enqueue_style('msb-top-reviews',    $plugin_url . 'static_css/top-reviews-grid.css', array('msb-recent-reviews'), '1.0.0');

    // --- JS: синхронизация темы плагина с темой сайта (data-theme на <html>) ---
    wp_enqueue_script(
        'msb-pcr-theme-sync',
        $plugin_url . 'js_scripts/msb-pcr-theme-sync.js',
        array(),
        '1.0.0',
        true
    );

    // --- JS: карусель отзывов (стрелки + точки + свайп,
    //        свайп за краем карусели меняет страницу отзывов) ---
    wp_enqueue_script(
        'msb-carousel-js',
        $plugin_url . 'js_scripts/msb-carousel.js',
        array(),
        '1.1.0',
        true
    );

    // --- JS: Модалка ---
    wp_enqueue_script(
        'msb-modal-js',
        $plugin_url . 'js_scripts/msb-modal.js',
        array('jquery'),
        '1.0.0',
        true
    );

    // // ✅ Переводы для JS (именно на msb-modal-js)
    // wp_localize_script('msb-modal-js', 'msbI18n', array(
    //     'modalTitleReview' => __( 'Оставьте отзыв', $td ),
    //     'modalTitleReply'  => __( 'Ответ на отзыв', $td ),
    //     'needFillPrefix'   => __( 'Нужно заполнить: ', $td ),
    //     'errMinComment'    => __( 'Напишите, пожалуйста, комментарий (минимум 3 символа)', $td ),
    //     'errName'          => __( 'Укажите ваше имя', $td ),
    //     'errEmail'         => __( 'Укажите e-mail', $td ),
    //     'aiTyping'         => __('Поддержка печатает…', 'msb-comments'),
    // ));

    // --- Автозаполнение ---
    wp_enqueue_script(
        'msb-autofill-js',
        $plugin_url . 'js_scripts/chitat_auto_zap_email.js',
        array('jquery'),
        '1.0.1',
        true
    );

    // --- Сортировка ---
    wp_enqueue_script(
        'msb-sort-comments-js',
        $plugin_url . 'js_scripts/msb-sort-comments.js',
        array('jquery'),
        '1.0.0',
        true
    );

    // --- Мультизагрузка ---
    wp_enqueue_script(
        'msb-photo-add-js',
        $plugin_url . 'js_scripts/photo_add_to_comment.js',
        array('jquery'),
        '1.0.0',
        true
    );

    // --- Реакции ---
    wp_enqueue_script(
        'msb-reactions-js',
        $plugin_url . 'js_scripts/nowy_reaksiya_na_komment.js',
        array('jquery'),
        '1.2.1',
        true
    );

    wp_localize_script('msb-reactions-js', 'msbComments', array(
        'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
        'nonceRate'  => wp_create_nonce( 'msb_rate_review' ),
        'nonceReact' => wp_create_nonce( 'msb_react_review' ),
    ));

    // --- "печатает..." (если использует msbI18n — можно зависимость поставить) ---
    wp_enqueue_script(
        'msb-ai-typing-js',
        $plugin_url . 'js_scripts/msb-ai-typing.js',
        array('jquery', 'msb-modal-js'),
        '1.0.0',
        true
    );

    // --- Слайдер виджета ---
    wp_enqueue_script(
        'msb-reviews-slider-js',
        $plugin_url . 'js_scripts/msb-reviews-slider.js',
        array(),
        '1.0.0',
        true
    );
}

add_action( 'wp_enqueue_scripts', 'msb_comments_enqueue_assets' );
