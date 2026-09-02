<?php
// shortcodes/msb-comments-shortcode.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * - /shortcodes/msb-comments-shortcode.php
 * [msb_comments] — главная страница отзывов: список (по 10) + форма в модальном окне.
 *
 * Пример использования:
 *   [msb_comments]              — как раньше (комменты привязаны к текущей странице)
 *   [msb_comments page_id="9"]  — всегда использовать страницу ID=9 как источник отзывов
 */
if ( ! function_exists( 'msb_comments_shortcode' ) ) {

    function msb_comments_shortcode( $atts = array() ) {

        $td = MSB_COMMENTS_TEXT_DOMAIN;

        // --- Атрибуты шорткода ---
        $atts = shortcode_atts(
            array(
                'page_id' => 0, // каноническая страница с отзывами
            ),
            $atts,
            'msb_comments'
        );

        global $post;

        // ID страницы, к которой реально привязаны комментарии
        $source_post_id = (int) $atts['page_id'];

        // если не передали page_id в шорткод — берём текущий пост (старое поведение)
        if ( ! $source_post_id && $post instanceof WP_Post ) {
            $source_post_id = (int) $post->ID;
        }

        // подстрахуемся: если вообще ничего нет — выходим
        if ( ! $source_post_id ) {
            return '';
        }

        // --- ТЕКСТ "Комментарии закрыты" через Polylang/WPML ---
        if ( ! comments_open( $source_post_id ) && ! get_comments_number( $source_post_id ) ) {
            $msg_closed = function_exists( 'msb_ui_tr' )
                ? msb_ui_tr( 'msb_comments_closed', 'Комментарии закрыты.' )
                : __( 'Комментарии закрыты.', $td );

            return '<p>' . esc_html( $msg_closed ) . '</p>';
        }

        ob_start();

        $commenter = wp_get_current_commenter();
        $req       = get_option( 'require_name_email' );
        $aria_req  = $req ? " aria-required='true'" : '';

        // Если пользователь залогинен — подставляем его данные как дефолт
        if ( is_user_logged_in() ) {
            $current_user = wp_get_current_user();

            if ( empty( $commenter['comment_author'] ) ) {
                $commenter['comment_author'] = $current_user->display_name
                    ? $current_user->display_name
                    : $current_user->user_login;
            }

            if ( empty( $commenter['comment_author_email'] ) ) {
                $commenter['comment_author_email'] = $current_user->user_email;
            }
        }

        // --- ПАГИНАЦИЯ ---
        $per_page     = 10;
        $current_page = isset( $_GET['msb_page'] ) ? (int) $_GET['msb_page'] : 1;
        if ( $current_page < 1 ) {
            $current_page = 1;
        }

        // считаем по source_post_id, а не по текущей странице
        $total_main  = msb_get_main_reviews_count( $source_post_id );
        $total_pages = max( 1, (int) ceil( $total_main / $per_page ) );

        if ( $current_page > $total_pages ) {
            $current_page = $total_pages;
        }

        $offset   = ( $current_page - 1 ) * $per_page;
        $comments = msb_get_main_reviews(
            $source_post_id,
            array(
                'number' => $per_page,
                'offset' => $offset,
            )
        );

        // ---------- ТЕКСТЫ ИНТЕРФЕЙСА (через msb_ui_tr) ----------
        $label_author            = function_exists( 'msb_ui_tr' ) ? msb_ui_tr( 'field_author_label', 'Имя' ) : __( 'Имя', $td );
        $placeholder_author      = function_exists( 'msb_ui_tr' ) ? msb_ui_tr( 'field_author_placeholder', 'Ваше имя' ) : __( 'Ваше имя', $td );

        $label_email             = function_exists( 'msb_ui_tr' ) ? msb_ui_tr( 'field_email_label', 'E-mail' ) : __( 'E-mail', $td );
        $placeholder_email       = function_exists( 'msb_ui_tr' ) ? msb_ui_tr( 'field_email_placeholder', 'Контактный e-mail' ) : __( 'Контактный e-mail', $td );

        $label_country           = function_exists( 'msb_ui_tr' ) ? msb_ui_tr( 'field_country_label', 'Страна' ) : __( 'Страна', $td );
        $placeholder_country     = function_exists( 'msb_ui_tr' ) ? msb_ui_tr( 'field_country_placeholder', 'Например: Turkmenistan, Türkiye, China' ) : __( 'Например: Turkmenistan, Türkiye, China', $td );

        $label_service           = function_exists( 'msb_ui_tr' ) ? msb_ui_tr( 'field_service_label', 'Услуга' ) : __( 'Услуга', $td );
        $placeholder_service_sel = function_exists( 'msb_ui_tr' ) ? msb_ui_tr( 'field_service_placeholder', 'Выберите' ) : __( 'Выберите', $td );

        $label_rating            = function_exists( 'msb_ui_tr' ) ? msb_ui_tr( 'field_rating_label', 'Оценка сервиса' ) : __( 'Оценка сервиса', $td );
        $rating_title_5          = function_exists( 'msb_ui_tr' ) ? msb_ui_tr( 'rating_5', 'Отлично' ) : __( 'Отлично', $td );
        $rating_title_4          = function_exists( 'msb_ui_tr' ) ? msb_ui_tr( 'rating_4', 'Хорошо' ) : __( 'Хорошо', $td );
        $rating_title_3          = function_exists( 'msb_ui_tr' ) ? msb_ui_tr( 'rating_3', 'Нормально' ) : __( 'Нормально', $td );
        $rating_title_2          = function_exists( 'msb_ui_tr' ) ? msb_ui_tr( 'rating_2', 'Так себе' ) : __( 'Так себе', $td );
        $rating_title_1          = function_exists( 'msb_ui_tr' ) ? msb_ui_tr( 'rating_1', 'Плохо' ) : __( 'Плохо', $td );

        $label_media             = function_exists( 'msb_ui_tr' ) ? msb_ui_tr( 'field_media_label', 'Добавить фото и видео (до 20 файлов)' ) : __( 'Добавить фото и видео (до 20 файлов)', $td );

        $label_cookies           = function_exists( 'msb_ui_tr' ) ? msb_ui_tr(
            'field_cookies_label',
            'Сохранить моё имя, email и адрес сайта в этом браузере для последующих моих комментариев.'
        ) : __( 'Сохранить моё имя, email и адрес сайта в этом браузере для последующих моих комментариев.', $td );

        $title_reply             = function_exists( 'msb_ui_tr' ) ? msb_ui_tr( 'title_reply_short', 'Оставьте короткий отзыв' ) : __( 'Оставьте короткий отзыв', $td );
        $label_submit            = function_exists( 'msb_ui_tr' ) ? msb_ui_tr( 'button_submit', 'Отправить' ) : __( 'Отправить', $td );

        $label_comment           = function_exists( 'msb_ui_tr' ) ? msb_ui_tr( 'field_comment_label', 'Комментарий' ) : __( 'Комментарий', $td );
        $placeholder_comment     = function_exists( 'msb_ui_tr' ) ? msb_ui_tr( 'field_comment_placeholder', 'Расскажите, что случилось с устройством и как прошла работа.' ) : __( 'Расскажите, что случилось с устройством и как прошла работа.', $td );

        // ---------- ПОЛЯ ФОРМЫ ----------
        $fields = array();

        // Имя + email в одной строке
        $fields['author'] =
            '<div class="msb-form-row msb-form-row-compact">' .
                '<div class="msb-form-field">' .
                    '<label for="author">' . esc_html( $label_author ) . '</label>' .
                    '<input id="author" name="author" type="text" ' .
                        'value="' . esc_attr( (string) $commenter['comment_author'] ) . '" ' . $aria_req . ' ' .
                        'placeholder="' . esc_attr( $placeholder_author ) . '" ' .
                        'autocomplete="name" ' .
                        'autocapitalize="words">' .
                '</div>';

        $fields['email'] =
                '<div class="msb-form-field">' .
                    '<label for="email">' . esc_html( $label_email ) . '</label>' .
                    '<input id="email" name="email" type="email" ' .
                        'value="' . esc_attr( (string) $commenter['comment_author_email'] ) . '" ' . $aria_req . ' ' .
                        'placeholder="' . esc_attr( $placeholder_email ) . '" ' .
                        'autocomplete="email" ' .
                        'inputmode="email">' .
                '</div>' .
            '</div>';

        // Страна + тип услуги (в ОДНОЙ строке)
        $fields['_removed_msb_country'] =
            '<div class="msb-form-row msb-form-row-compact">' .
                '<div class="msb-form-field">' .
                    '<label for="msb_country">' . esc_html( $label_country ) . '</label>' .
                    '<input id="msb_country" name="msb_country" type="text" ' .
                        'placeholder="' . esc_attr( $placeholder_country ) . '" ' .
                        'autocomplete="country-name">' .
                '</div>';

        $service_types = function_exists( 'msb_comments_get_service_types' )
            ? msb_comments_get_service_types()
            : array( 'Ремонт компьютера', 'Ремонт ноутбука', 'Видеокарта', 'Материнская плата', 'Сборка / апгрейд', 'Установка Windows', 'Другое' );

        $options_html  = '<option value="">' . esc_html( $placeholder_service_sel ) . '</option>';

        foreach ( $service_types as $type_raw ) {
            $type_raw = is_string( $type_raw ) ? trim( $type_raw ) : '';
            if ( $type_raw === '' ) {
                continue;
            }

            $label = msb_service_type_label( $type_raw ); // тут уже i18n-адаптер

            // value = каноническое (сырьё), label = перевод
            $options_html .= sprintf(
                '<option value="%1$s">%2$s</option>',
                esc_attr( $type_raw ),
                esc_html( $label )
            );
        }

        $fields['_removed_msb_service_type'] =
                '<div class="msb-form-field">' .
                    '<label for="msb_service_type">' . esc_html( $label_service ) . '</label>' .
                    '<select id="msb_service_type" name="msb_service_type">' .
                        $options_html .
                    '</select>' .
                '</div>' .
            '</div>';

        // Рейтинг — звёзды
        $fields['msb_rating'] =
            '<div class="msb-form-row">' .
                '<div class="msb-form-field msb-form-field-full">' .
                    '<label>' . esc_html( $label_rating ) . '</label>' .
                    '<div class="msb-rating-row">' .
                        '<div class="msb-rating-stars">' .
                            '<input type="radio" id="msb_rating_5" name="msb_rating" value="5">' .
                            '<label for="msb_rating_5" title="' . esc_attr( $rating_title_5 ) . '">★</label>' .
                            '<input type="radio" id="msb_rating_4" name="msb_rating" value="4">' .
                            '<label for="msb_rating_4" title="' . esc_attr( $rating_title_4 ) . '">★</label>' .
                            '<input type="radio" id="msb_rating_3" name="msb_rating" value="3">' .
                            '<label for="msb_rating_3" title="' . esc_attr( $rating_title_3 ) . '">★</label>' .
                            '<input type="radio" id="msb_rating_2" name="msb_rating" value="2">' .
                            '<label for="msb_rating_2" title="' . esc_attr( $rating_title_2 ) . '">★</label>' .
                            '<input type="radio" id="msb_rating_1" name="msb_rating" value="1">' .
                            '<label for="msb_rating_1" title="' . esc_attr( $rating_title_1 ) . '">★</label>' .
                        '</div>' .
                    '</div>' .
                '</div>' .
            '</div>';

        // Добавить фото и видео
        $fields['msb_media'] =
            '<div class="msb-form-row">' .
                '<div class="msb-form-field msb-form-field-full msb-media-field">' .
                    '<label for="msb_media">' . esc_html( $label_media ) . '</label>' .
                    '<input id="msb_media" name="msb_media[]" type="file" multiple accept="image/*,video/*">' .
                '</div>' .
            '</div>';

        // Чекбокс «запомнить меня»
        $fields['cookies'] =
            '<p class="comment-form-cookies-consent">' .
                '<input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes" />' .
                '<label for="wp-comment-cookies-consent">' . esc_html( $label_cookies ) . '</label>' .
            '</p>';

        unset( $fields['_removed_msb_country'], $fields['_removed_msb_service_type'] );

        // ---------- АРГУМЕНТЫ ФОРМЫ ----------
        $comment_form_args = array(
            'class_form'           => 'msb-comment-form',
            'title_reply'          => $title_reply,
            'title_reply_before'   => '<h3 class="msb-form-title">',
            'title_reply_after'    => '</h3>',
            'label_submit'         => $label_submit,
            'comment_notes_before' => '',
            'comment_notes_after'  => '',
            'fields'               => $fields,
            'comment_field'        =>
                '<div class="msb-form-field msb-form-field-full">' .
                    '<label for="comment">' . esc_html( $label_comment ) . '</label>' .
                    '<textarea id="comment" name="comment" rows="4" placeholder="' . esc_attr( $placeholder_comment ) . '"></textarea>' .
                '</div>',
            'submit_button' => '<button name="%1$s" type="submit" id="%2$s" class="msb-submit-button %3$s">%4$s</button>',
            'submit_field'  => '<div class="msb-form-row msb-form-submit">%1$s %2$s</div>',
        );

        msb_get_template(
            'comments-page.php',
            array(
                'comments'           => $comments,
                'comment_form_args'  => $comment_form_args,
                'pagination'         => array(
                    'current'    => $current_page,
                    'total'      => $total_pages,
                    'per_page'   => $per_page,
                    'total_main' => $total_main,
                ),
                // ВАЖНО: передаём ID, к которому реально привязаны комментарии
                'msb_source_post_id' => $source_post_id,
            )
        );

        return ob_get_clean();
    }

    add_shortcode( 'msb_comments', 'msb_comments_shortcode' );
}
