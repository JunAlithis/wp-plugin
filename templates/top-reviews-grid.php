<?php
/**
 * Template: "Top reviews" widget
 * - /templates/top-reviews-grid.php
 * @param array  $comments           Array of comment objects
 * @param string $comments_page_url  URL of the reviews page
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( empty( $comments ) ) { return; }

$td = defined( 'MSB_COMMENTS_TEXT_DOMAIN' ) ? MSB_COMMENTS_TEXT_DOMAIN : 'msb-comments';

/**
 * Хелпер для UI-строк (Polylang/WPML + gettext).
 */
if ( ! function_exists( 'msb_tpl_ui' ) ) {
    function msb_tpl_ui( $key, $default, $td ) {
        if ( function_exists( 'msb_ui_tr' ) ) {
            return msb_ui_tr( $key, $default );
        }
        return __( $default, $td );
    }
}

if ( empty( $comments_page_url ) ) {
    // Пытаемся использовать текущую страницу как "страницу отзывов"
    $current_id = get_queried_object_id();

    if ( $current_id ) {
        $comments_page_url = get_permalink( $current_id );
    } else {
        // Запасной вариант — главная, чтобы не было "#"
        $comments_page_url = home_url( '/' );
    }
}


/**
 * Русское склонение (1 отзыв / 2-4 отзыва / 5+ отзывов)
 * Уникальное имя, чтобы не ловить конфликты.
 */
if ( ! function_exists( 'msb_comments_ru_plural' ) ) {
    function msb_comments_ru_plural( $number, $one, $few, $many ) {
        $n    = abs( (int) $number );
        $n10  = $n % 10;
        $n100 = $n % 100;

        if ( $n10 === 1 && $n100 !== 11 ) {
            return $one;
        }
        if ( $n10 >= 2 && $n10 <= 4 && ( $n100 < 12 || $n100 > 14 ) ) {
            return $few;
        }
        return $many;
    }
}


// Average rating + positive reviews
$total_rating   = 0;
$rating_count   = 0;
$positive_count = 0;

foreach ( $comments as $comment ) {
    $rating = (int) get_comment_meta( $comment->comment_ID, 'msb_rating', true );
    if ( $rating > 0 ) {
        $total_rating += $rating;
        $rating_count++;

        if ( $rating >= 4 ) {
            $positive_count++;
        }
    }
}

$comments_count      = is_array( $comments ) ? count( $comments ) : 0;
$average_rating      = $rating_count > 0 ? round( $total_rating / $rating_count, 1 ) : 4.9;
$positive_percentage = $comments_count > 0 ? round( ( $positive_count / $comments_count ) * 100 ) : 100;

// Слово "лучший отзыв / лучших отзыва / лучших отзывов"
$top_reviews_word = msb_comments_ru_plural(
    $comments_count,
    msb_tpl_ui( 'top_reviews_one',  'лучший отзыв',      $td ),
    msb_tpl_ui( 'top_reviews_few',  'лучших отзыва',     $td ),
    msb_tpl_ui( 'top_reviews_many', 'лучших отзывов',    $td )
);

// Если по какой-то причине пришла пустая строка из переводов — fallback
if ( '' === trim( $top_reviews_word ) ) {
    $top_reviews_word = msb_comments_ru_plural(
        $comments_count,
        'лучший отзыв',
        'лучших отзыва',
        'лучших отзывов'
    );
}


// Итоговая подпись "6 лучших отзывов"
$top_reviews_label = number_format_i18n( $comments_count ) . ' ' . $top_reviews_word;

?>

<div class="msb-reviews-widget msb-reviews-widget--top" data-msb-widget="top">
    <div class="msb-reviews-widget-inner">

        <div class="msb-reviews-widget-header">
            <div class="msb-reviews-widget-header-main">
                <h3 class="msb-reviews-widget-title">
                    <span class="msb-reviews-widget-title-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M12 15l8.385-8.415a2.1 2.1 0 00-2.97-2.97L9 12v3h3z" stroke-linecap="round"/>
                            <path d="M16 5l3 3" stroke-linecap="round"/>
                            <path d="M9 21H5a2 2 0 01-2-2v-4a2 2 0 012-2h4a2 2 0 012 2v4a2 2 0 01-2 2z" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span><?php echo esc_html( msb_tpl_ui( 'top_widget_title', 'Лучшие отзывы', $td ) ); ?></span>
                </h3>

                <p class="msb-reviews-widget-subtitle">
                    <?php echo esc_html( msb_tpl_ui( 'top_widget_subtitle', 'Самые тёплые и подробные истории клиентов о нашем сервисе', $td ) ); ?>
                </p>
            </div>

            <div class="msb-reviews-widget-tagline">
                <span class="msb-fire-icon" aria-hidden="true">🔥</span>
                <?php echo esc_html( msb_tpl_ui( 'top_widget_tagline', 'Высокий рейтинг', $td ) ); ?>
            </div>
        </div>

        <div class="msb-reviews-slider" data-msb-slider data-msb-autoplay="6000">
            <div class="msb-reviews-grid">
                <?php foreach ( $comments as $index => $comment ) :

                    $country = get_comment_meta( $comment->comment_ID, 'msb_country', true );
                    $service = get_comment_meta( $comment->comment_ID, 'msb_service_type', true );
                    $rating  = (int) get_comment_meta( $comment->comment_ID, 'msb_rating', true );

                    $service_label = '';
                    if ( $service ) {
                        $service_label = function_exists( 'msb_service_type_label' )
                            ? msb_service_type_label( $service )
                            : $service;
                    }

                    $helpful    = (int) get_comment_meta( $comment->comment_ID, 'msb_helpful', true );
                    $react_like = (int) get_comment_meta( $comment->comment_ID, 'msb_react_like', true );
                    $react_love = (int) get_comment_meta( $comment->comment_ID, 'msb_react_love', true );
                    $react_wow  = (int) get_comment_meta( $comment->comment_ID, 'msb_react_wow', true );

                    $children = get_comments(
                        array(
                            'post_id' => $comment->comment_post_ID,
                            'parent'  => $comment->comment_ID,
                            'status'  => 'approve',
                            'number'  => 1,
                            'orderby' => 'comment_date_gmt',
                            'order'   => 'ASC',
                        )
                    );

                    $ai_reply_excerpt = '';
                    $ai_reply_label   = '';
                    $is_ai_reply      = false;

                    if ( $children ) {
                        $child            = $children[0];
                        $ai_reply_excerpt = wp_trim_words( wp_strip_all_tags( $child->comment_content ), 26, '…' );
                        $ai_reply_label   = $child->comment_author
                            ? $child->comment_author
                            : msb_tpl_ui( 'support_label', 'Поддержка', $td );

                        $meta_ai   = get_comment_meta( $child->comment_ID, 'msb_ai_reply', true );
                        $is_ai_reply = ! empty( $meta_ai );

                        if ( ! $is_ai_reply ) {
                            $auth       = strtolower( (string) $child->comment_author );
                            $is_ai_reply = ( strpos( $auth, 'ai' ) !== false ) || ( strpos( $auth, 'искусственный' ) !== false );
                        }
                    }

                    $comment_link = get_comment_link( $comment );
                    $excerpt      = wp_trim_words( wp_strip_all_tags( $comment->comment_content ), 35, '…' );
                    $avatar       = get_avatar( $comment, 40, '', '', array( 'class' => 'msb-review-avatar' ) );
                    $is_verified  = get_comment_meta( $comment->comment_ID, 'msb_verified', true );

                    $date_title = get_comment_date(
                        get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
                        $comment
                    );

                    $aria_rating_tpl = msb_tpl_ui( 'top_rating_aria', 'Оценка: %1$d из 5', $td );
                    ?>

                    <article class="msb-review-card" data-msb-slide="<?php echo (int) $index; ?>">
                        <div class="msb-review-card-inner">

                            <?php if ( $rating === 5 ) : ?>
                                <div class="msb-review-top-badge">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" fill="currentColor"/>
                                    </svg>
                                    <?php echo esc_html( msb_tpl_ui( 'badge_top_review', 'Лучший отзыв', $td ) ); ?>
                                </div>
                            <?php endif; ?>

                            <a class="msb-review-card-link"
                               href="<?php echo esc_url( $comment_link ); ?>"
                               aria-label="<?php echo esc_attr( msb_tpl_ui( 'top_read_full_review', 'Читать полный отзыв', $td ) ); ?>">

                                <header class="msb-review-card-header">
                                    <div class="msb-review-author-block">
                                        <div class="msb-review-author-avatar">
                                            <?php echo $avatar; ?>

                                            <?php if ( ! empty( $is_verified ) ) : ?>
                                                <span class="msb-verified-badge"
                                                      title="<?php echo esc_attr( msb_tpl_ui( 'badge_verified_review', 'Проверенный отзыв', $td ) ); ?>">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" fill="currentColor"/>
                                                    </svg>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <div>
                                            <div class="msb-review-author-name">
                                                <?php echo esc_html( get_comment_author( $comment ) ); ?>

                                                <?php if ( $rating === 5 ) : ?>
                                                    <span class="msb-perfect-rating"
                                                          title="<?php echo esc_attr( msb_tpl_ui( 'badge_perfect_rating', 'Идеальная оценка', $td ) ); ?>">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                            <circle cx="12" cy="12" r="10" fill="#fbbf24"/>
                                                            <path d="M9 12l2 2 4-4" stroke="white" stroke-width="2"/>
                                                        </svg>
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <div class="msb-review-meta-line">
                                                <?php if ( $country ) : ?>
                                                    <span class="msb-review-country">
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" fill="currentColor"/>
                                                            <circle cx="12" cy="9" r="2.5" fill="white"/>
                                                        </svg>
                                                        <?php echo esc_html( $country ); ?>
                                                    </span>
                                                <?php endif; ?>

                                                <?php if ( $service_label ) : ?>
                                                    <span class="msb-review-service">
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                            <path d="M20 6h-4V4c0-1.1-.9-2-2-2h-4c-1.1 0-2 .9-2 2v2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2z" fill="currentColor"/>
                                                        </svg>
                                                        <?php echo esc_html( $service_label ); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="msb-review-rating-block">
                                        <?php if ( $rating ) : ?>
                                            <div class="msb-review-rating"
                                                 aria-label="<?php echo esc_attr( sprintf( $aria_rating_tpl, (int) $rating ) ); ?>">
                                                <?php echo msb_render_stars( $rating ); ?>
                                                <span class="msb-review-rating-number<?php echo $rating === 5 ? ' msb-review-rating-number--perfect' : ''; ?>">
                                                    <?php echo (int) $rating; ?>.0
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </header>

                                <div class="msb-review-body">
                                    <div class="msb-review-excerpt">
                                        <?php echo esc_html( $excerpt ); ?>
                                    </div>

                                    <?php if ( $ai_reply_excerpt ) : ?>
                                        <div class="msb-review-ai-reply">
                                            <div class="msb-review-ai-label">
                                                <?php
                                                // Всегда показываем имя автора ответа
                                                echo esc_html( $ai_reply_label );
                                                ?>
                                            </div>
                                            <p class="msb-review-ai-excerpt">
                                                <?php echo esc_html( $ai_reply_excerpt ); ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>


                                <footer class="msb-review-footer">
                                    <div class="msb-review-date" title="<?php echo esc_attr( $date_title ); ?>">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z" fill="currentColor"/>
                                            <path d="M12.5 7H11v6l5.25 3.15.75-1.23-4.5-2.67z" fill="currentColor"/>
                                        </svg>
                                        <?php echo esc_html( get_comment_date( get_option( 'date_format' ), $comment ) ); ?>
                                    </div>

                                    <?php if ( $helpful > 0 || $react_like > 0 || $react_love > 0 || $react_wow > 0 ) : ?>
                                        <div class="msb-review-stats">

                                            <?php if ( $helpful > 0 ) : ?>
                                                <span class="msb-review-stat msb-review-stat--helpful"
                                                      title="<?php echo esc_attr( msb_tpl_ui( 'tooltip_helpful', 'Полезный отзыв', $td ) ); ?>">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                        <path d="M1 21h4V9H1v12zm22-11c0-1.1-.9-2-2-2h-6.31l.95-4.57.03-.32c0-.41-.17-.79-.44-1.06L14.17 1 7.59 7.59C7.22 7.95 7 8.45 7 9v10c0 1.1.9 2 2 2h9c.83 0 1.54-.5 1.84-1.22l3.02-7.05c.09-.23.14-.47.14-.73v-2z" fill="currentColor"/>
                                                    </svg>
                                                    <span><?php echo (int) $helpful; ?></span>
                                                </span>
                                            <?php endif; ?>

                                            <?php if ( $react_like > 0 ) : ?>
                                                <span class="msb-review-stat"
                                                      title="<?php echo esc_attr( msb_tpl_ui( 'tooltip_like', 'Нравится', $td ) ); ?>">
                                                    👍 <span><?php echo (int) $react_like; ?></span>
                                                </span>
                                            <?php endif; ?>

                                            <?php if ( $react_love > 0 ) : ?>
                                                <span class="msb-review-stat"
                                                      title="<?php echo esc_attr( msb_tpl_ui( 'tooltip_love', 'Обожаю', $td ) ); ?>">
                                                    😍 <span><?php echo (int) $react_love; ?></span>
                                                </span>
                                            <?php endif; ?>

                                            <?php if ( $react_wow > 0 ) : ?>
                                                <span class="msb-review-stat"
                                                      title="<?php echo esc_attr( msb_tpl_ui( 'tooltip_wow', 'Вау', $td ) ); ?>">
                                                    🤯 <span><?php echo (int) $react_wow; ?></span>
                                                </span>
                                            <?php endif; ?>

                                        </div>
                                    <?php endif; ?>
                                </footer>

                            </a>
                        </div>
                    </article>

                <?php endforeach; ?>
            </div>

            <div class="msb-slider-controls">
                <button class="msb-reviews-nav msb-reviews-nav--prev" type="button"
                        aria-label="<?php echo esc_attr( msb_tpl_ui( 'nav_prev_review', 'Предыдущий отзыв', $td ) ); ?>">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z" fill="currentColor"/>
                    </svg>
                </button>

                <div class="msb-slider-dots">
                    <?php
                    $total_groups   = (int) ceil( $comments_count / 3 );
                    $dot_label_tpl  = msb_tpl_ui( 'top_dot_label', 'Перейти к группе отзывов %d', $td );

                    for ( $i = 0; $i < $total_groups; $i++ ) :
                        $dot_label = sprintf( $dot_label_tpl, $i + 1 );
                        ?>
                        <button class="msb-slider-dot<?php echo $i === 0 ? ' msb-slider-dot--active' : ''; ?>"
                                data-msb-slide="<?php echo (int) $i; ?>"
                                aria-label="<?php echo esc_attr( $dot_label ); ?>"></button>
                    <?php endfor; ?>
                </div>

                <button class="msb-reviews-nav msb-reviews-nav--next" type="button"
                        aria-label="<?php echo esc_attr( msb_tpl_ui( 'nav_next_review', 'Следующий отзыв', $td ) ); ?>">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z" fill="currentColor"/>
                    </svg>
                </button>
            </div>
        </div>

        <div class="msb-reviews-widget-footer">
            <div class="msb-reviews-widget-buttons">
                <a href="<?php echo esc_url( add_query_arg( 'msb_open', '1', $comments_page_url ) ); ?>"
                   class="msb-reviews-widget-button msb-reviews-widget-button--primary"
                   data-msb-action="open-review-form">
                    <span class="msb-button-icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z" fill="currentColor"/>
                        </svg>
                    </span>
                    <span><?php echo esc_html( msb_tpl_ui( 'btn_leave_review', 'Оставить отзыв', $td ) ); ?></span>
                </a>

                <a href="<?php echo esc_url( $comments_page_url ); ?>"
                   class="msb-reviews-widget-button msb-reviews-widget-button--secondary">
                    <span><?php echo esc_html( msb_tpl_ui( 'btn_all_reviews', 'Все отзывы', $td ) ); ?></span>
                    <span class="msb-button-icon" aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z" fill="currentColor"/>
                        </svg>
                    </span>
                </a>
            </div>


            <div class="msb-reviews-widget-stats">
                <span class="msb-stat-item">
                    <span class="msb-stat-number"><?php echo number_format_i18n( $comments_count ); ?></span>
                    <span class="msb-stat-label"><?php echo esc_html( $top_reviews_label ); ?></span>
                </span>

                <span class="msb-stat-item">
                    <span class="msb-stat-number"><?php echo esc_html( number_format_i18n( $positive_percentage ) ); ?>%</span>
                    <span class="msb-stat-label">
                        <?php echo esc_html( msb_tpl_ui( 'stat_positive_label', 'положительных', $td ) ); ?>
                    </span>
                </span>
            </div>
        </div>

    </div>
</div>
