<?php
/**
 * Plugin Name: Post Comments Single Page (Meryosab)
 * Description: Подстраница /comments/ для комментариев + шорткоды [msb_comments], [msb_recent_reviews], [msb_top_reviews] для отзывов.
 * Version: 1.7.0
 * Author: Meryosab
 * Text Domain: msb-comments
 * Domain Path: /languages

 */ 
//       - ./wp-content:/var/www/html/wp-content
//-     ../comments-page-plugin-2:/var/www/html/wp-content/plugins/comments-page-plugin/comments-page-plugin.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Базовая константа для путей плагина.
if ( ! defined( 'MSB_COMMENTS_PLUGIN_DIR' ) ) {
    define( 'MSB_COMMENTS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

// Базовая константа для URL плагина (для подключённых файлов)
if ( ! defined( 'MSB_COMMENTS_PLUGIN_URL' ) ) {
    define( 'MSB_COMMENTS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// Text Domain для переводов плагина
if ( ! defined( 'MSB_COMMENTS_TEXT_DOMAIN' ) ) {
    define( 'MSB_COMMENTS_TEXT_DOMAIN', 'msb-comments' );
}

// Загрузка переводов из папки /languages
add_action( 'plugins_loaded', function () {
    load_plugin_textdomain(
        MSB_COMMENTS_TEXT_DOMAIN,
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages'
    );
} );

/**
 * ============================================================
 * i18n АДАПТЕР: Polylang / WPML / fallback
 * ============================================================
 * Задача:
 * 1) Переводить обычные строки через __() и text-domain.
 * 2) Переводить динамические строки (options/услуги) через Polylang/WPML string translation.
 */

/**
 * Регистрация строки в Polylang/WPML (если установлены).
 */
if ( ! function_exists( 'msb_i18n_register_string' ) ) {
    function msb_i18n_register_string( $name, $value ) {
        $value = is_string( $value ) ? $value : '';
        if ( $value === '' ) {
            return;
        }

        // Polylang
        if ( function_exists( 'pll_register_string' ) ) {
            pll_register_string( $name, $value, 'MSB Comments' );
            return;
        }

        // WPML
        if ( has_action( 'wpml_register_single_string' ) ) {
            do_action( 'wpml_register_single_string', 'MSB Comments', $name, $value );
            return;
        }
    }
}

/**
 * Перевод зарегистрированной строки.
 */
if ( ! function_exists( 'msb_i18n_translate_string' ) ) {
    function msb_i18n_translate_string( $name, $value ) {
        $value = is_string( $value ) ? $value : '';

        // Polylang (ищет по значению)
        if ( function_exists( 'pll__' ) ) {
            return pll__( $value );
        }

        // WPML (ищет по (context,name))
        if ( $name && has_filter( 'wpml_translate_single_string' ) ) {
            return apply_filters( 'wpml_translate_single_string', $value, 'MSB Comments', $name );
        }

        return $value;
    }
}

/**
 * Получить option + зарегистрировать + перевести.
 */
if ( ! function_exists( 'msb_opt_tr' ) ) {
    function msb_opt_tr( $option_key, $default, $name ) {
        $raw = get_option( $option_key, $default );
        $raw = is_string( $raw ) ? $raw : (string) $default;

        msb_i18n_register_string( $name, $raw );

        return msb_i18n_translate_string( $name, $raw );
    }
}

/**
 * Услуги: регистрируем/переводим каждую строку отдельно (так удобнее переводить).
 */
if ( ! function_exists( 'msb_service_type_label' ) ) {
    function msb_service_type_label( $type_raw ) {
        $type_raw = is_string( $type_raw ) ? trim( $type_raw ) : '';
        if ( $type_raw === '' ) {
            return '';
        }

        $key = 'msb_service_type_' . md5( $type_raw );
        msb_i18n_register_string( $key, $type_raw );

        return msb_i18n_translate_string( $key, $type_raw );
    }
}

/**
 * Регистрируем важные динамические строки заранее, чтобы они сразу появились в Polylang/WPML.
 */
add_action( 'init', function () {

    // Подзаголовок (если хранится в options)
    $td = MSB_COMMENTS_TEXT_DOMAIN;
    $default_subtitle = __( 'Ваш отзыв о нашей работе помогает другим клиентам сделать правильный выбор.', $td );
    $subtitle_raw = get_option( 'msb_comments_subtitle_text', $default_subtitle );
    $subtitle_raw = is_string( $subtitle_raw ) ? $subtitle_raw : $default_subtitle;
    msb_i18n_register_string( 'msb_comments_subtitle_text', $subtitle_raw );

    // Список услуг (многострочный)
    $raw = get_option( 'msb_comments_service_types', '' );
    if ( $raw === '' ) {
        $raw = "Ремонт компьютера\nРемонт ноутбука\nВидеокарта\nМатеринская плата\nСборка / апгрейд\nУстановка Windows\nДругое";
    }
    $lines = preg_split( '/\r\n|\r|\n/', (string) $raw );
    foreach ( $lines as $line ) {
        $line = trim( (string) $line );
        if ( $line !== '' ) {
            msb_service_type_label( $line ); // внутри сам зарегистрирует
        }
    }
} );


/**
 * Админ-страница настроек плагина (опции ИИ, список услуг и т.д.).
 * Файл: admin_control/admin_control.php
 */
if ( is_admin() ) {
    $msb_admin_control = MSB_COMMENTS_PLUGIN_DIR . 'admin_control/admin_control.php';
    if ( file_exists( $msb_admin_control ) ) {
        require_once $msb_admin_control;
    }
}

/**
 * Простая функция для подключения шаблонов плагина.
 * $template_name — имя файла внутри /templates/
 * $vars — массив переменных, которые будут доступны внутри шаблона.
 */
function msb_get_template( $template_name, $vars = array() ) {
    $file = MSB_COMMENTS_PLUGIN_DIR . 'templates/' . ltrim( $template_name, '/\\' );

    if ( ! file_exists( $file ) ) {
        return;
    }

    if ( ! empty( $vars ) && is_array( $vars ) ) {
        extract( $vars, EXTR_SKIP );
    }

    include $file;
}

/**
 * Получить основные отзывы (родительские комментарии) с поддержкой number/offset.
 */
function msb_get_main_reviews( $post_id = 0, $args = array() ) {
    if ( ! $post_id ) {
        $post_id = get_the_ID();
    }

    $defaults = array(
        'post_id' => $post_id,
        'status'  => 'approve',
        'number'  => 10,       // по умолчанию 10 штук на страницу
        'offset'  => 0,
        'orderby' => 'comment_date_gmt',
        'order'   => 'DESC',
        'parent'  => 0,        // только верхний уровень, ответы выводим отдельно
    );

    $args = wp_parse_args( $args, $defaults );

    return get_comments( $args );
}

/**
 * Посчитать количество основных отзывов (родительские комментарии).
 */
function msb_get_main_reviews_count( $post_id = 0 ) {
    if ( ! $post_id ) {
        $post_id = get_the_ID();
    }

    $args = array(
        'post_id' => $post_id,
        'status'  => 'approve',
        'parent'  => 0,
        'count'   => true, // get_comments вернёт число, а не список
    );

    return (int) get_comments( $args );
}

/**
 * Список типов услуг для поля «Услуга» в форме.
 * Значения берутся из настроек плагина (одна услуга — одна строка).
 * ВАЖНО: возвращаем "канонические" строки (без перевода),
 * а переводим уже в HTML-выводе (label), чтобы в meta не сохранялась каша из разных языков.
 */
if ( ! function_exists( 'msb_comments_get_service_types' ) ) {
    function msb_comments_get_service_types() {
        // Берём сырое значение из options (многострочный текст)
        $raw = get_option( 'msb_comments_service_types', '' );

        // Если опция пустая — дефолтный список (услуги PC Repair)
        if ( $raw === '' ) {
            $raw = "Ремонт компьютера\nРемонт ноутбука\nВидеокарта\nМатеринская плата\nСборка / апгрейд\nУстановка Windows\nДругое";
        }

        $lines = preg_split( '/\r\n|\r|\n/', (string) $raw );
        $types = array();

        foreach ( $lines as $line ) {
            $line = trim( (string) $line );
            if ( $line !== '' ) {
                $types[] = $line;
            }
        }

        return $types;
    }
}

// ################################################ Подключение файла с реализацией шорткода [msb_comments] и передача для перевода ################################ //


/**
 * Универсальный хелпер для UI-строк.
 *
 * $key     — внутренний ключ (для панели Polylang/WPML).
 * $default — текст по умолчанию (по-русски).
 *
 * Работает так:
 *  - регистрирует строку в Polylang / WPML (msb_i18n_register_string)
 *  - возвращает переведённое значение (msb_i18n_translate_string)
 */
if ( ! function_exists( 'msb_ui_tr' ) ) {
    function msb_ui_tr( $key, $default ) {
        $default = is_string( $default ) ? $default : '';

        if ( $default === '' ) {
            return '';
        }

        // Регистрируем строку как переводимую
        if ( function_exists( 'msb_i18n_register_string' ) ) {
            msb_i18n_register_string( $key, $default );
        }

        // Пробуем перевести (Polylang / WPML), иначе вернёт $default
        if ( function_exists( 'msb_i18n_translate_string' ) ) {
            return msb_i18n_translate_string( $key, $default );
        }

        // Fallback — обычный gettext (на случай .po/.mo)
        if ( function_exists( '__' ) && defined( 'MSB_COMMENTS_TEXT_DOMAIN' ) ) {
            return __( $default, MSB_COMMENTS_TEXT_DOMAIN );
        }

        return $default;
    }
}

/**
 * Регистрируем все UI-строки плагина в Polylang/WPML,
 * чтобы они появились в "Строки перевода" в группе "MSB Comments".
 */
if ( ! function_exists( 'msb_register_all_ui_strings' ) ) {

    function msb_register_all_ui_strings() {

        if ( ! function_exists( 'msb_i18n_register_string' ) ) {
            return;
        }

        // --- ОБЩЕЕ СООБЩЕНИЕ ---
        msb_i18n_register_string( 'msb_comments_closed', 'Комментарии закрыты.' );

        // --- МОДАЛКА / ФОРМА ---
        // ВАЖНО: НЕ используем один и тот же ключ для двух разных текстов
        msb_i18n_register_string( 'js_modal_title_review', 'Оставьте отзыв' );
        msb_i18n_register_string( 'js_modal_title_reply',  'Ответ на отзыв' );

        // (Опционально) legacy-ключ, если где-то ещё остался старый вызов
        msb_i18n_register_string( 'msb-modal-title', 'Оставьте отзыв' );

        msb_i18n_register_string( 'field_author_label',        'Имя' );
        msb_i18n_register_string( 'field_author_placeholder',  'Ваше имя' );

        msb_i18n_register_string( 'field_email_label',         'E-mail' );
        msb_i18n_register_string( 'field_email_placeholder',   'Контактный e-mail' );

        msb_i18n_register_string( 'field_country_label',       'Страна' );
        msb_i18n_register_string( 'field_country_placeholder', 'Например: Turkmenistan, Türkiye, China' );

        msb_i18n_register_string( 'field_service_label',       'Услуга' );
        msb_i18n_register_string( 'field_service_placeholder', 'Выберите' );

        msb_i18n_register_string( 'field_rating_label',        'Оценка сервиса' );
        msb_i18n_register_string( 'rating_5',                  'Отлично' );
        msb_i18n_register_string( 'rating_4',                  'Хорошо' );
        msb_i18n_register_string( 'rating_3',                  'Нормально' );
        msb_i18n_register_string( 'rating_2',                  'Так себе' );
        msb_i18n_register_string( 'rating_1',                  'Плохо' );

        msb_i18n_register_string( 'field_media_label',         'Добавить фото и видео (до 20 файлов)' );

        msb_i18n_register_string(
            'field_cookies_label',
            'Сохранить моё имя, email и адрес сайта в этом браузере для последующих моих комментариев.'
        );

        msb_i18n_register_string( 'title_reply_short',         'Оставьте короткий отзыв' );

        // Эти два ключа разные — это не дубликат, можно оставить оба, если где-то используются
        msb_i18n_register_string( 'button_submit',             'Отправить' );
        msb_i18n_register_string( 'msb-submit-button',         'Отправить' );

        msb_i18n_register_string( 'field_comment_label',       'Комментарий' );
        msb_i18n_register_string(
            'field_comment_placeholder',
            'Расскажите, что случилось с устройством и как прошла работа.'
        );

        // --- ЭЛЕМЕНТЫ ИНТЕРФЕЙСА comments-page.php ---
        msb_i18n_register_string( 'btn_write_review',      'Написать отзыв' );

        msb_i18n_register_string( 'sort_by_label',         'Сортировать по' );
        msb_i18n_register_string( 'sort_by_relevance',     'по релевантности' );
        msb_i18n_register_string( 'sort_by_date',          'по дате' );
        msb_i18n_register_string( 'sort_by_rating_desc',   'по убыванию рейтинга' );
        msb_i18n_register_string( 'sort_by_rating_asc',    'по возрастанию рейтинга' );
        msb_i18n_register_string( 'sort_by_helpful',       'по полезности (👍)' );

        msb_i18n_register_string( 'btn_reply',             'Ответить' );
        msb_i18n_register_string( 'btn_helpful',           'Полезный отзыв' );

        msb_i18n_register_string( 'pagination_aria',       'Навигация по отзывам' );
        msb_i18n_register_string( 'pagination_page_of',    'Страница %1$s из %2$s' );
        msb_i18n_register_string( 'pagination_prev',       'Предыдущие отзывы' );
        msb_i18n_register_string( 'pagination_next',       'Читать другие отзывы' );

        msb_i18n_register_string(
            'no_comments_text',
            'Отзывов пока нет. Будьте первым, кто поделится своим опытом.'
        );

        msb_i18n_register_string( 'media_open_image',      'Открыть изображение' );
        msb_i18n_register_string( 'media_video_label',     'Видео' );

        // === ВИДЖЕТ "СВЕЖИЕ ОТЗЫВЫ" (recent-reviews-grid.php) ===
        msb_i18n_register_string( 'recent_title',          'Свежие отзывы' );
        msb_i18n_register_string( 'recent_subtitle',       'Последние впечатления клиентов о нашем сервисе' );
        msb_i18n_register_string( 'recent_tagline',        'В эфире' );

        msb_i18n_register_string( 'recent_read_full',      'Читать полный отзыв' );
        msb_i18n_register_string( 'recent_ai_badge',       'ИИ-помощник' );
        msb_i18n_register_string( 'recent_support_label',  'Поддержка' );

        msb_i18n_register_string( 'recent_nav_prev',       'Предыдущий отзыв' );
        msb_i18n_register_string( 'recent_nav_next',       'Следующий отзыв' );
        msb_i18n_register_string( 'recent_dot_label',      'Перейти к группе отзывов %d' );

        msb_i18n_register_string( 'recent_btn_leave',      'Оставить отзыв' );
        msb_i18n_register_string( 'recent_btn_all',        'Все отзывы' );
        msb_i18n_register_string( 'stat_rating_label',      'рейтинг' );

        msb_i18n_register_string( 'recent_stat_helpful',   'Полезный отзыв' );
        msb_i18n_register_string( 'recent_stat_like',      'Нравится' );
        msb_i18n_register_string( 'recent_stat_love',      'Обожаю' );
        msb_i18n_register_string( 'recent_stat_wow',       'Вау' );
        msb_i18n_register_string( 'reviews_one',      'отзыв' );
        msb_i18n_register_string( 'reviews_few',      'отзыва' );
        msb_i18n_register_string( 'reviews_may',       'отзывов' );

        // === ВИДЖЕТ "ЛУЧШИЕ ОТЗЫВЫ" (top-reviews-grid.php) ===
        msb_i18n_register_string( 'top_title',             'Лучшие отзывы' );
        msb_i18n_register_string( 'top_subtitle',          'Самые тёплые и подробные истории клиентов о нашем сервисе' );
        msb_i18n_register_string( 'top_tagline',           'Высокий рейтинг' );

        msb_i18n_register_string( 'badge_top_review',        'Лучший отзыв' );
        msb_i18n_register_string( 'top_ai_answer',         'Ответ ИИ' );
        msb_i18n_register_string( 'top_support_label',     'Поддержка' );
        msb_i18n_register_string( 'top_verified_badge',    'Проверенный отзыв' );
        msb_i18n_register_string( 'top_perfect_rating',    'Идеальная оценка' );

        msb_i18n_register_string( 'top_nav_prev',          'Предыдущий отзыв' );
        msb_i18n_register_string( 'top_nav_next',          'Следующий отзыв' );
        msb_i18n_register_string( 'top_dot_label',         'Перейти к группе отзывов %d' );

        msb_i18n_register_string( 'stat_positive_label',    'положительных' );
        // Подпись "6 лучших отзывов"
        msb_i18n_register_string( 'top_reviews_one',  'лучший отзыв' );
        msb_i18n_register_string( 'top_reviews_few',  'лучших отзыва' );
        msb_i18n_register_string( 'top_reviews_many', 'лучших отзывов' );


        msb_i18n_register_string( 'top_stat_helpful',      'Полезный отзыв' );
        msb_i18n_register_string( 'top_stat_like',         'Нравится' );
        msb_i18n_register_string( 'top_stat_love',         'Обожаю' );
        msb_i18n_register_string( 'top_stat_wow',          'Вау' );

        // === КАРТОЧКА КОММЕНТАРИЯ (comment-card.php) ===
        msb_i18n_register_string( 'comment_relative_time', '%s назад' );
        msb_i18n_register_string( 'comment_like_aria',     'Нравится' );
        msb_i18n_register_string( 'comment_share_aria',    'Поделиться' );

        // === JS: мультизагрузка медиа (photo_add_to_comment.js) ===
        msb_i18n_register_string( 'js_add_more_files', 'Добавить ещё файлы' );
        msb_i18n_register_string( 'js_media_max_files', 'Можно прикрепить максимум %1$s файлов. Уже выбрано: %2$s.' );
        msb_i18n_register_string( 'js_media_max_reached', 'Достигнут максимум: %s файлов.' );

        // === JS: статус набора текста ИИ (msb-ai-typing.js / msb-modal.js) ===
        // СИНХРОН с msb_comments_localize_js(): там используется ключ js_ai_typing
        msb_i18n_register_string( 'js_ai_typing', 'Поддержка печатает…' );

        // (Опционально) legacy-ключ, если где-то остался старый вызов
        msb_i18n_register_string( 'msb-ai-typing', 'Поддержка печатает…' );
    }
}

add_action( 'init', 'msb_register_all_ui_strings' );


/**
 * Локализация строк для JS (msb-modal.js, photo_add_to_comment.js, msb-ai-typing.js)
 * Всё уходит в объект window.msbI18n.
 */
if ( ! function_exists( 'msb_comments_localize_js' ) ) {

    function msb_comments_localize_js() {

        // Скрипты плагина не подключены — выходим
        if (
            ! wp_script_is( 'msb-modal-js', 'enqueued' ) &&
            ! wp_script_is( 'msb-modal-js', 'registered' )
        ) {
            return;
        }

        // Собираем все строки для JS через msb_ui_tr (Polylang/WPML)
        $msb_i18n = array(
            // msb-modal.js (валидация формы и заголовки модалки)
            'modalTitleReview' => msb_ui_tr( 'js_modal_title_review', 'Оставьте отзыв' ),
            'modalTitleReply'  => msb_ui_tr( 'js_modal_title_reply',  'Ответ на отзыв' ),
            'needFillPrefix'   => msb_ui_tr( 'js_need_fill_prefix',   'Нужно заполнить: ' ),
            'errMinComment'    => msb_ui_tr(
                'js_err_min_comment',
                'напишите, пожалуйста, комментарий (минимум 3 символа)'
            ),
            'errName'          => msb_ui_tr( 'js_err_name',  'укажите ваше имя' ),
            'errEmail'         => msb_ui_tr( 'js_err_email', 'укажите e-mail' ),

            // photo_add_to_comment.js
            'addMoreFiles'     => msb_ui_tr( 'js_add_more_files', 'Добавить ещё файлы' ),
            'mediaMaxFiles'    => msb_ui_tr(
                'js_media_max_files',
                'Можно прикрепить максимум %1$s файлов. Уже выбрано: %2$s.'
            ),
            'mediaMaxReached'  => msb_ui_tr(
                'js_media_max_reached',
                'Достигнут максимум: %s файлов.'
            ),

            // msb-ai-typing.js
            'aiTyping'         => msb_ui_tr( 'js_ai_typing', 'Поддержка печатает…' ),

        );

        // ВАЖНО: используем РЕАЛЬНЫЙ handle скрипта — msb-modal-js
        wp_localize_script(
            'msb-modal-js',
            'msbI18n',
            $msb_i18n
        );
    }
}

add_action( 'wp_enqueue_scripts', 'msb_comments_localize_js', 20 );




// Подключение файла с реализацией шорткода [msb_comments]
require_once MSB_COMMENTS_PLUGIN_DIR . 'shortcodes/msb-comments-shortcode.php';

// ################################################ Подключение файла с реализацией шорткода [msb_comments] и передача для перевода ################################ //









/**
 * Класс для отдельной подстраницы /comments/ (Kama Separate Comments Page).
 */
class Kama_Separate_Comments_Page {

    public static $page_title_patt = 'Comments for %s';

    public function init() {
        add_filter( 'query_vars',       array( $this, 'query_vars' ) );
        add_action( 'init',             array( $this, 'add_endpoint' ) );
        add_filter( 'template_include', array( $this, 'template_include' ) );
        add_filter( 'get_comment_link', array( $this, 'get_comment_link' ), 10, 3 );
        add_filter( 'wp_title',         array( $this, 'wp_title' ), 10, 1 );
    }

    public function query_vars( $vars ) {
        $vars[] = 'comments';
        return $vars;
    }

    public function add_endpoint() {
        add_rewrite_endpoint( 'comments', EP_PERMALINK | EP_PAGES );
    }

    public function template_include( $template ) {
        global $wp_query;

        if ( isset( $wp_query->query['comments'] ) && is_singular() ) {

            $templates = locate_template( 'comments-page.php', false );

            if ( ! $templates ) {
                $templates = plugin_dir_path( __FILE__ ) . 'comments-page.php';
            }

            return $templates;
        }

        return $template;
    }

    /**
     * Переписываем ссылки на комментарии ТОЛЬКО для типов post.
     * Для страниц (page), в том числе нашей /comments/, ссылку не трогаем.
     */
    public function get_comment_link( $url, $comment, $args ) {
        $post = get_post( $comment->comment_post_ID );
        if ( ! $post ) {
            return $url;
        }

        // Для страниц вообще не лезем в ссылку
        if ( $post->post_type !== 'post' ) {
            return $url;
        }

        $urlparts = explode( '#', $url );
        $base     = untrailingslashit( $urlparts[0] ) . '/comments/';

        if ( ! empty( $urlparts[1] ) ) {
            $base .= '#' . $urlparts[1];
        }

        return $base;
    }

    public function wp_title( $title ) {
        global $wp_query;

        if ( isset( $wp_query->query['comments'] ) && is_singular() ) {
            $title = sprintf(
                esc_html__( 'Comments for %s', MSB_COMMENTS_TEXT_DOMAIN ),
                $title
            );
        }

        return $title;
    }


    public function activate() {
        $this->add_endpoint();
        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }
}

$Kama_Separate_Comments_Page = new Kama_Separate_Comments_Page();
$Kama_Separate_Comments_Page->init();

register_activation_hook( __FILE__, array( $Kama_Separate_Comments_Page, 'activate' ) );
register_deactivation_hook( __FILE__, array( $Kama_Separate_Comments_Page, 'deactivate' ) );

/**
 * Подключаем отдельный файл, который отвечает за CSS/JS (enqueue).
 */
require_once MSB_COMMENTS_PLUGIN_DIR . 'function_connect_js_css_php/function_connect_js_css.php';


// ################################################ Фикс редиректа после отправки отзыва: сохраняем язык страницы (ru/en/…) ################################ //

// Фикс редиректа после отправки отзыва: сохраняем язык страницы (ru/en/…)
add_filter( 'comment_post_redirect', function ( $location, $comment_obj ) {

    // Если объект комментария пустой – ничего не трогаем
    if ( ! $comment_obj || ! isset( $comment_obj->comment_ID ) ) {
        return $location;
    }

    // Флаг: это именно форма нашего плагина (поля страны/услуги/рейтинга/медиа)
    $is_msb_form =
        ( isset( $_POST['msb_country'] )      ) ||
        ( isset( $_POST['msb_service_type'] ) ) ||
        ( isset( $_POST['msb_rating'] )       ) ||
        ( isset( $_POST['msb_media_ids'] )    );

    if ( ! $is_msb_form ) {
        // Обычные комментарии не трогаем
        return $location;
    }

    // --- 1. Отделяем якорь #comment-xxx от URL, который дал WordPress ---
    $frag    = '';
    $loc_base = $location;

    if ( false !== strpos( $location, '#' ) ) {
        list( $loc_base, $fragment ) = explode( '#', $location, 2 );
        $frag = '#' . $fragment;
    }

    // --- 2. Пытаемся взять URL, с которого пользователь отправлял форму ---
    // Он уже содержит правильный язык (/en/... или /ru/...)
    $referer = wp_get_referer();
    $base    = $referer ? $referer : $loc_base;

    // На всякий случай убираем у referrer старый якорь, если он там есть
    if ( false !== strpos( $base, '#' ) ) {
        $base = strtok( $base, '#' );
    }

    // --- 3. Добавляем наши параметры msb_posted / msb_cid к базовому URL ---
    $base = add_query_arg(
        array(
            'msb_posted' => '1',
            'msb_cid'    => (int) $comment_obj->comment_ID,
        ),
        $base
    );

    // --- 4. Возвращаем URL + исходный якорь (#comment-123) ---
    return $base . $frag;

}, 10, 2 );

// ################################################ Фикс редиректа после отправки отзыва: сохраняем язык страницы (ru/en/…) ################################ //


/**
 * Проверка загружаемых файлов (белый список расширений, размер и т.п.).
 */
require_once MSB_COMMENTS_PLUGIN_DIR . 'proverka_fayl_foto_video/proverka_fayl_foto_video.php';

// --- КОНФИГ ИИ (проверка отзыва) ---

$msb_ai_config = MSB_COMMENTS_PLUGIN_DIR . 'ai_function/proverka_otzywa/msb-ai-config.php';
if ( file_exists( $msb_ai_config ) ) {
    require_once $msb_ai_config;
}

/**
 * ===== DeepSeek AI: откуда брать ключи =====
 *
 * Порядок для анализа (модерация):
 *  1) опция msb_comments_ai_analysis_key (админка)
 *  2) константа MSB_DEEPSEEK_API_KEY (msb-ai-config.php или wp-config.php)
 *  3) env DEEPSEEK_API_KEY
 */
if ( ! defined( 'MSB_DEEPSEEK_API_KEY' ) ) {
    $stored_key = get_option( 'msb_comments_ai_analysis_key', '' );
    $stored_key = is_string( $stored_key ) ? trim( $stored_key ) : '';

    if ( $stored_key !== '' ) {
        define( 'MSB_DEEPSEEK_API_KEY', $stored_key );
    } else {
        $env_key = getenv( 'DEEPSEEK_API_KEY' );
        define( 'MSB_DEEPSEEK_API_KEY', $env_key ? $env_key : '' );
    }
}

/**
 * Отдельный ключ для авто-ответов.
 * Порядок:
 *  1) опция msb_comments_ai_reply_key
 *  2) константа MSB_DEEPSEEK_API_KEY_REPLY
 *  3) общий ключ анализа (MSB_DEEPSEEK_API_KEY)
 */
if ( ! defined( 'MSB_DEEPSEEK_API_KEY_REPLY' ) ) {
    $stored_reply_key = get_option( 'msb_comments_ai_reply_key', '' );
    $stored_reply_key = is_string( $stored_reply_key ) ? trim( $stored_reply_key ) : '';

    if ( $stored_reply_key !== '' ) {
        define( 'MSB_DEEPSEEK_API_KEY_REPLY', $stored_reply_key );
    }
}

/**
 * Хелпер: API-ключ для анализа (модерация).
 */
if ( ! function_exists( 'msb_ai_get_analysis_api_key' ) ) {
    function msb_ai_get_analysis_api_key() {
        $key = get_option( 'msb_comments_ai_analysis_key', '' );
        $key = is_string( $key ) ? trim( $key ) : '';

        if ( $key !== '' ) {
            return $key;
        }

        if ( defined( 'MSB_DEEPSEEK_API_KEY' ) ) {
            return MSB_DEEPSEEK_API_KEY;
        }

        $env_key = getenv( 'DEEPSEEK_API_KEY' );
        return $env_key ? $env_key : '';
    }
}

/**
 * Хелпер: API-ключ для авто-ответа ИИ.
 */
if ( ! function_exists( 'msb_ai_get_reply_api_key' ) ) {
    function msb_ai_get_reply_api_key() {
        $key = get_option( 'msb_comments_ai_reply_key', '' );
        $key = is_string( $key ) ? trim( $key ) : '';

        if ( $key !== '' ) {
            return $key;
        }

        if ( defined( 'MSB_DEEPSEEK_API_KEY_REPLY' ) && MSB_DEEPSEEK_API_KEY_REPLY !== '' ) {
            return MSB_DEEPSEEK_API_KEY_REPLY;
        }

        return msb_ai_get_analysis_api_key();
    }
}

/**
 * Хелпер: Имя бота поддержки (автор авто-ответа ИИ).
 *
 * Порядок:
 *  1) опция msb_comments_ai_bot_name (админка)
 *  2) константа MSB_AI_BOT_NAME
 *  3) "Название сайта — команда поддержки"
 */
if ( ! function_exists( 'msb_ai_get_bot_name' ) ) {
    function msb_ai_get_bot_name() {
        if ( function_exists( 'get_option' ) ) {
            $opt = trim( (string) get_option( 'msb_comments_ai_bot_name', '' ) );
            if ( $opt !== '' ) {
                return $opt;
            }
        }

        if ( defined( 'MSB_AI_BOT_NAME' ) && MSB_AI_BOT_NAME ) {
            return MSB_AI_BOT_NAME;
        }

        return get_bloginfo( 'name' ) . ' — команда поддержки';
    }
}

// Файл с логикой AI-проверки отзыва: функция msb_ai_check_review().
require_once MSB_COMMENTS_PLUGIN_DIR . 'ai_function/proverka_otzywa/proverka_otzywa.php';

// --- КОНФИГ ИИ-АВТООТВЕТА (доп. константы для логов, включения и т.п.) ---
$msb_ai_reply_config = MSB_COMMENTS_PLUGIN_DIR . 'ai_function/otvet_otzywa/2-msb-ai-config.php';
if ( file_exists( $msb_ai_reply_config ) ) {
    require_once $msb_ai_reply_config;
}

// ЛОГИКА АВТО-ОТВЕТА ИИ НА ОДОБРЕННЫЕ ОТЗЫВЫ
require_once MSB_COMMENTS_PLUGIN_DIR . 'ai_function/otvet_otzywa/otvet_otzywa.php';

/**
 * Делаем форму комментариев multipart, чтобы файлы реально отправлялись.
 */
function msb_comment_form_enctype( $defaults ) {
    // В админке вообще не трогаем
    if ( is_admin() ) {
        return $defaults;
    }

    // Если ключа 'form' нет или это не строка — выходим
    if ( empty( $defaults['form'] ) || ! is_string( $defaults['form'] ) ) {
        return $defaults;
    }

    // если уже стоит enctype — не трогаем
    if ( strpos( $defaults['form'], 'enctype=' ) === false ) {
        $defaults['form'] = str_replace(
            '<form',
            '<form enctype="multipart/form-data"',
            $defaults['form']
        );
    }

    return $defaults;
}
add_filter( 'comment_form_defaults', 'msb_comment_form_enctype' );

/**
 * Сохраняем meta: страна, тип услуги, рейтинг + медиа.
 */
function msb_save_comment_meta( $comment_id ) {
    if ( isset( $_POST['msb_country'] ) ) {
        $country = sanitize_text_field( wp_unslash( $_POST['msb_country'] ) );
        if ( $country !== '' ) {
            update_comment_meta( $comment_id, 'msb_country', $country );
        }
    }

    if ( isset( $_POST['msb_service_type'] ) ) {
        $service_type = sanitize_text_field( wp_unslash( $_POST['msb_service_type'] ) );
        if ( $service_type !== '' ) {
            update_comment_meta( $comment_id, 'msb_service_type', $service_type );
        }
    }

    if ( isset( $_POST['msb_rating'] ) && $_POST['msb_rating'] !== '' ) {
        $rating = (int) $_POST['msb_rating'];
        if ( $rating >= 1 && $rating <= 5 ) {
            update_comment_meta( $comment_id, 'msb_rating', $rating );
        }
    }

    // Загрузка фото/видео (до 20 файлов)
    if ( ! empty( $_FILES['msb_media'] ) && ! empty( $_FILES['msb_media']['name'][0] ) ) {
        $files = $_FILES['msb_media'];

        if ( ! function_exists( 'media_handle_upload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $uploaded  = array();
        $max_files = 20;
        $processed = 0;

        foreach ( $files['name'] as $i => $name ) {
            // пропускаем ошибки
            if ( $files['error'][ $i ] !== UPLOAD_ERR_OK ) {
                continue;
            }

            $processed++;
            // не даём загрузить больше 20 файлов
            if ( $processed > $max_files ) {
                break;
            }

            $file_array = array(
                'name'     => $files['name'][ $i ],
                'type'     => $files['type'][ $i ],
                'tmp_name' => $files['tmp_name'][ $i ],
                'error'    => $files['error'][ $i ],
                'size'     => $files['size'][ $i ],
            );

            $_FILES['msb_media_single'] = $file_array;

            $attach_id = media_handle_upload( 'msb_media_single', 0 );
            if ( ! is_wp_error( $attach_id ) ) {
                $uploaded[] = $attach_id;
            }
        }

        if ( $uploaded ) {
            $existing = get_comment_meta( $comment_id, 'msb_media_ids', true );
            if ( ! is_array( $existing ) ) {
                $existing = array();
            }

            $all_ids = array_merge( $existing, $uploaded );
            $all_ids = array_slice( $all_ids, 0, 20 );

            update_comment_meta( $comment_id, 'msb_media_ids', $all_ids );
        }
    }
}
add_action( 'comment_post', 'msb_save_comment_meta', 10, 1 );
add_action( 'edit_comment', 'msb_save_comment_meta', 10, 1 );

/**
 * Рендер звёзд из рейтинга 1–5.
 */
function msb_render_stars( $rating ) {
    $td     = MSB_COMMENTS_TEXT_DOMAIN;
    $rating = max( 1, min( 5, (int) $rating ) );

    $out = '';
    for ( $i = 1; $i <= 5; $i++ ) {
        $out .= ( $i <= $rating ) ? '★' : '☆';
    }

    $aria = sprintf(
        esc_attr__( 'Rating %1$d out of 5', $td ),
        $rating
    );

    return '<span class="msb-stars" aria-label="' . esc_attr( $aria ) . '">' . $out . '</span>';
}


/**
 * AJAX: оценка отзыва («Полезный отзыв»).
 */
function msb_ajax_rate_review() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'msb_rate_review' ) ) wp_send_json_error( array( 'message' => 'bad_nonce' ), 403 );
    $comment_id = isset( $_POST['comment_id'] ) ? (int) $_POST['comment_id'] : 0;
    $remove = ! empty( $_POST['remove'] );
    if ( ! $comment_id || ! get_comment( $comment_id ) ) wp_send_json_error( array( 'message' => 'not_found' ), 404 );
    $cookie_key = 'msb_helpful_' . $comment_id;
    $count = (int) get_comment_meta( $comment_id, 'msb_helpful', true );
    if ( $remove ) {
        $count = max( 0, $count - 1 );
        update_comment_meta( $comment_id, 'msb_helpful', $count );
        setcookie( $cookie_key, '', time() - HOUR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
        wp_send_json_success( array( 'count' => $count, 'removed' => 1 ) );
    }
    if ( isset( $_COOKIE[ $cookie_key ] ) ) wp_send_json_error( array( 'message' => 'already_voted', 'count' => $count ) );
    update_comment_meta( $comment_id, 'msb_helpful', ++$count );
    setcookie( $cookie_key, '1', time() + YEAR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
    wp_send_json_success( array( 'count' => $count, 'removed' => 0 ) );
}
add_action( 'wp_ajax_msb_rate_review',        'msb_ajax_rate_review' );
add_action( 'wp_ajax_nopriv_msb_rate_review', 'msb_ajax_rate_review' );

/**
 * AJAX: разные реакции под отзывом (👍😍🤯).
 */
function msb_ajax_react_review() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'msb_react_review' ) ) {
        wp_send_json_error( array( 'message' => 'bad_nonce' ), 403 );
    }
    $comment_id = isset( $_POST['comment_id'] ) ? (int) $_POST['comment_id'] : 0;
    $reaction = isset( $_POST['reaction'] ) ? sanitize_key( wp_unslash( $_POST['reaction'] ) ) : '';
    $remove = ! empty( $_POST['remove'] );
    if ( ! $comment_id || ! $reaction || ! in_array( $reaction, array( 'like', 'love', 'wow' ), true ) ) {
        wp_send_json_error( array( 'message' => 'bad_data' ), 400 );
    }
    if ( ! get_comment( $comment_id ) ) wp_send_json_error( array( 'message' => 'not_found' ), 404 );
    $meta_key = 'msb_react_' . $reaction;
    $cookie_key = 'msb_react_' . $reaction . '_' . $comment_id;
    $count = (int) get_comment_meta( $comment_id, $meta_key, true );
    if ( $remove ) {
        if ( isset( $_COOKIE[ $cookie_key ] ) ) {
            $count = max( 0, $count - 1 );
            update_comment_meta( $comment_id, $meta_key, $count );
            setcookie( $cookie_key, '', time() - HOUR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
        }
        wp_send_json_success( array( 'reaction' => $reaction, 'count' => $count, 'removed' => 1 ) );
    }
    if ( isset( $_COOKIE[ $cookie_key ] ) ) {
        wp_send_json_error( array( 'message' => 'already_voted', 'count' => $count ) );
    }
    update_comment_meta( $comment_id, $meta_key, ++$count );
    setcookie( $cookie_key, '1', time() + YEAR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
    wp_send_json_success( array( 'reaction' => $reaction, 'count' => $count, 'removed' => 0 ) );
}
add_action( 'wp_ajax_msb_react_review',        'msb_ajax_react_review' );
add_action( 'wp_ajax_nopriv_msb_react_review', 'msb_ajax_react_review' );




/**
* [msb_recent_reviews] — блок свежих отзывов (только родительские).
*
* Универсальная логика:
*  - если задан атрибут page_id — берём отзывы с этой страницы;
*  - если НЕ задан — берём ID текущей страницы (get_queried_object_id / $post->ID);
*  - НИКАКОЙ привязки к slug "comments" больше нет.
*/
function msb_recent_reviews_shortcode( $atts ) {
   $atts = shortcode_atts(
       array(
           'page_id'  => 0,
           'number'   => 3,
           'title'    => '',
           'subtitle' => '',
       ),
       $atts,
       'msb_recent_reviews'
   );

   // 1. Явный page_id из шорткода
   $page_id = (int) $atts['page_id'];

   // 2. Если не задан — берём текущий объект запроса
   if ( ! $page_id ) {
       $page_id = get_queried_object_id();
   }

   // 3. На всякий случай — fallback на глобальный $post
   if ( ! $page_id ) {
       global $post;
       if ( $post instanceof WP_Post ) {
           $page_id = (int) $post->ID;
       }
   }

   if ( ! $page_id ) {
       // Не смогли понять, откуда брать отзывы — тихо выходим
       return '';
   }

   $number = max( 1, min( 10, (int) $atts['number'] ) );

   $comments = get_comments(
       array(
           'post_id' => $page_id,
           'status'  => 'approve',
           'number'  => $number,
           'order'   => 'DESC',
           'parent'  => 0, // только верхний уровень
       )
   );

   if ( empty( $comments ) ) {
       return '';
   }

   $comments_page_url = get_permalink( $page_id );

   ob_start();

   msb_get_template(
       'recent-reviews-grid.php',
       array(
           'comments'          => $comments,
           'comments_page_url' => $comments_page_url,
           'title'             => $atts['title'],
           'subtitle'          => $atts['subtitle'],
       )
   );

   return ob_get_clean();
}
add_shortcode( 'msb_recent_reviews', 'msb_recent_reviews_shortcode' );


/**
 * [msb_top_reviews] — блок ТОП-отзывов.
 *
 * Логика:
 *  - если задан атрибут page_id — берём отзывы с этой страницы;
 *  - если НЕ задан — берём ID текущей страницы (get_queried_object_id / $post->ID).
 *  - НИКАКОЙ привязки к slug "comments" больше нет.
 */
function msb_top_reviews_shortcode( $atts ) {
    $atts = shortcode_atts(
        array(
            'page_id'  => 0,
            'number'   => 5,
            'title'    => '',
            'subtitle' => '',
        ),
        $atts,
        'msb_top_reviews'
    );

    // 1. Явный page_id из шорткода
    $page_id = (int) $atts['page_id'];

    // 2. Если не задан — берём текущий объект запроса
    if ( ! $page_id ) {
        $page_id = get_queried_object_id();
    }

    // 3. На всякий случай — fallback на глобальный $post
    if ( ! $page_id ) {
        global $post;
        if ( $post instanceof WP_Post ) {
            $page_id = (int) $post->ID;
        }
    }

    // Если так и не поняли, откуда брать отзывы — тихо выходим
    if ( ! $page_id ) {
        return '';
    }

    // Берём до 100 одобренных родительских комментариев с этой страницы
    $all_comments = get_comments(
        array(
            'post_id' => $page_id,
            'status'  => 'approve',
            'parent'  => 0,
            'number'  => 100,
            'orderby' => 'comment_date_gmt',
            'order'   => 'DESC',
        )
    );

    if ( empty( $all_comments ) ) {
        return '';
    }

    // Подготовка: считаем рейтинг и длину текста
    $scored = array();

    foreach ( $all_comments as $comment ) {
        $rating = (int) get_comment_meta( $comment->comment_ID, 'msb_rating', true );

        $text = trim( wp_strip_all_tags( $comment->comment_content ) );
        if ( function_exists( 'mb_strlen' ) ) {
            $length = mb_strlen( $text );
        } else {
            $length = strlen( $text );
        }

        $scored[] = array(
            'comment' => $comment,
            'rating'  => $rating,
            'length'  => $length,
        );
    }

    // Сортировка: сначала рейтинг, потом длина, потом дата
    usort(
        $scored,
        function ( $a, $b ) {
            if ( $a['rating'] !== $b['rating'] ) {
                return $b['rating'] <=> $a['rating'];
            }

            if ( $a['length'] !== $b['length'] ) {
                return $b['length'] <=> $a['length'];
            }

            $time_a = strtotime( $a['comment']->comment_date_gmt );
            $time_b = strtotime( $b['comment']->comment_date_gmt );

            return $time_b <=> $time_a;
        }
    );

    // Сколько хотим показать в ТОПе (1–10)
    $number       = max( 1, min( 10, (int) $atts['number'] ) );
    $selected     = array();
    $selected_ids = array();

    // 1) Сначала: рейтинг >= 4 и длина >= 120 символов
    foreach ( $scored as $row ) {
        if ( count( $selected ) >= $number ) {
            break;
        }

        $comment_id = $row['comment']->comment_ID;
        if ( isset( $selected_ids[ $comment_id ] ) ) {
            continue;
        }

        if ( $row['rating'] >= 4 && $row['length'] >= 120 ) {
            $selected[]                  = $row['comment'];
            $selected_ids[ $comment_id ] = true;
        }
    }

    // 2) Если не набрали N — добавляем все с рейтингом >= 4 (даже короткие)
    if ( count( $selected ) < $number ) {
        foreach ( $scored as $row ) {
            if ( count( $selected ) >= $number ) {
                break;
            }

            $comment_id = $row['comment']->comment_ID;
            if ( isset( $selected_ids[ $comment_id ] ) ) {
                continue;
            }

            if ( $row['rating'] >= 4 ) {
                $selected[]                  = $row['comment'];
                $selected_ids[ $comment_id ] = true;
            }
        }
    }

    // 3) Если всё равно меньше N — добиваем любыми отзывами
    if ( count( $selected ) < $number ) {
        foreach ( $scored as $row ) {
            if ( count( $selected ) >= $number ) {
                break;
            }

            $comment_id = $row['comment']->comment_ID;
            if ( isset( $selected_ids[ $comment_id ] ) ) {
                continue;
            }

            $selected[]                  = $row['comment'];
            $selected_ids[ $comment_id ] = true;
        }
    }

    if ( empty( $selected ) ) {
        return '';
    }

    $comments_page_url = get_permalink( $page_id );

    ob_start();

    msb_get_template(
        'top-reviews-grid.php',
        array(
            'comments'          => $selected,
            'comments_page_url' => $comments_page_url,
            'title'             => $atts['title'],
            'subtitle'          => $atts['subtitle'],
        )
    );

    return ob_get_clean();
}
add_shortcode( 'msb_top_reviews', 'msb_top_reviews_shortcode' );




/**
 * Полностью отключаем проверку на "дубликат комментария" по всему сайту.
 * WordPress больше не будет ругаться "Обнаружен дубликат комментария".
 */
function msb_disable_duplicate_comment_check( $dupe_id, $commentdata ) {
    // Всегда говорим WP: "нет дубликата"
    return false;
}
add_filter( 'duplicate_comment_id', 'msb_disable_duplicate_comment_check', 10, 2 );


/**
 * Проверка: это комментарий, отправленный через форму MSB-отзывов?
 * (Наличие наших полей msb_country / msb_service_type / msb_rating / msb_media)
 */
if ( ! function_exists( 'msb_is_msb_review_form' ) ) {
    function msb_is_msb_review_form() {
        return (
            isset( $_POST['msb_country'] )
            || isset( $_POST['msb_service_type'] )
            || isset( $_POST['msb_rating'] )
            || isset( $_POST['msb_media_ids'] )
            || ( ! empty( $_FILES['msb_media']['name'][0] ?? '' ) )
        );
    }
}

if ( ! function_exists( 'msb_is_msb_comment_context' ) ) {
    /**
     * Проверка: относится ли комментарий к системе отзывов MSB.
     *
     * true если:
     *  - это отправка через нашу форму (есть поля msb_*), ИЛИ
     *  - это ответ (parent > 0) на комментарий, у которого есть meta msb_rating/msb_service_type.
     */
    function msb_is_msb_comment_context( $commentdata ) {

        // 1) Прямая отправка через форму отзывов MSB (отзыв или ответ через наш модал)
        if ( msb_is_msb_review_form() ) {
            return true;
        }

        // 2) Ответ на уже существующий отзыв MSB
        if ( ! is_array( $commentdata ) ) {
            return false;
        }

        $parent_id = isset( $commentdata['comment_parent'] ) ? (int) $commentdata['comment_parent'] : 0;
        if ( $parent_id <= 0 ) {
            return false;
        }

        $parent = get_comment( $parent_id );
        if ( ! $parent ) {
            return false;
        }

        $meta_rating  = get_comment_meta( $parent->comment_ID, 'msb_rating', true );
        $meta_service = get_comment_meta( $parent->comment_ID, 'msb_service_type', true );

        // Если у родителя есть рейтинг или тип услуги — считаем, что это наш отзыв
        return ( $meta_rating !== '' || $meta_service !== '' );
    }
}


/**
 * Авто-модерация через ИИ для:
 *  - самих отзывов (топ-уровень),
 *  - ответов на отзывы (дочерние комментарии к отзывам MSB).
 *
 * Обычные комментарии WordPress (не наши отзывы) не трогаем.
 */
function msb_ai_auto_moderate_comment( $approved, $commentdata ) {
    if ( ! is_array( $commentdata ) ) {
        return $approved;
    }

    // Если это не контекст отзывов MSB — выходим.
    if ( ! function_exists( 'msb_is_msb_comment_context' ) || ! msb_is_msb_comment_context( $commentdata ) ) {
        return $approved;
    }

    // Если вдруг нет функции проверки ИИ — держим на модерации
    if ( ! function_exists( 'msb_ai_check_review' ) ) {
        return 0; // "ожидает модерации"
    }

    $verdict = msb_ai_check_review( $commentdata );

    switch ( $verdict ) {
        case 'reject':
            // Отвергнут — в спам
            return 'spam';

        case 'manual':
            // Нужна ручная проверка админом
            return 0;

        case 'approve':
        default:
            // Одобрен — публикуем
            return 1;
    }
}
add_filter( 'pre_comment_approved', 'msb_ai_auto_moderate_comment', 10, 2 );
