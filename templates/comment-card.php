<?php
/**
 * Шаблон карточки одного комментария в стиле Google Reviews.
 * - /templates/comment-card.php
 *
 * @var WP_Comment $comment
 * @var array      $args
 * @var int        $depth
 * @var string     $country
 * @var string     $service_type
 * @var int        $rating
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$td = defined( 'MSB_COMMENTS_TEXT_DOMAIN' ) ? MSB_COMMENTS_TEXT_DOMAIN : 'msb-comments';

/**
 * Хелпер для UI-строк.
 */
if ( ! function_exists( 'msb_tpl_ui' ) ) {
    function msb_tpl_ui( $key, $default, $td ) {
        if ( function_exists( 'msb_ui_tr' ) ) {
            return msb_ui_tr( $key, $default );
        }
        return __( $default, $td );
    }
}

/**
 * Склонение (1 отзыв / 2-4 / 5+)
 */
if ( ! function_exists( 'msb_ru_plural' ) ) {
    function msb_ru_plural( $number, $one, $few, $many ) {
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

$timestamp = (int) get_comment_date( 'U', $comment );

$diff_human      = human_time_diff( $timestamp, current_time( 'timestamp' ) );
$relative_time_t = msb_tpl_ui( 'comment_relative_time', '%s назад', $td );
$relative_time   = sprintf( $relative_time_t, $diff_human );

// Кол-во отзывов этого же автора (по email)
$author_reviews_count = 0;
if ( ! empty( $comment->comment_author_email ) ) {
    $author_reviews_count = (int) get_comments(
        array(
            'author_email' => $comment->comment_author_email,
            'count'        => true,
            'status'       => 'approve',
        )
    );
}

$author_reviews_label = '';
if ( $author_reviews_count > 0 ) {
    $author_reviews_label = number_format_i18n( $author_reviews_count ) . ' ' . msb_ru_plural(
        $author_reviews_count,
        msb_tpl_ui( 'author_reviews_one', 'отзыв', $td ),
        msb_tpl_ui( 'author_reviews_few', 'отзыва', $td ),
        msb_tpl_ui( 'author_reviews_many', 'отзывов', $td )
    );
}

// Перевод "услуги"
$service_type_label = '';
if ( ! empty( $service_type ) ) {
    $service_type_label = function_exists( 'msb_service_type_label' )
        ? msb_service_type_label( $service_type )
        : $service_type;
}
?>
<div
    <?php comment_class( 'msb-comment-card', $comment ); ?>
    id="comment-<?php comment_ID(); ?>"

    data-rating="<?php echo esc_attr( (int) $rating ); ?>"
    data-date="<?php echo esc_attr( $timestamp ); ?>"
>
    <div class="msb-comment-header">
        <div class="msb-comment-avatar">
            <?php echo get_avatar( $comment, 40 ); ?>
        </div>

        <div class="msb-comment-author-meta">
            <div class="msb-comment-author-name">
                <?php echo esc_html( get_comment_author( $comment ) ); ?>
            </div>

            <div class="msb-comment-subline">
                <?php if ( $author_reviews_label ) : ?>
                    <span class="msb-comment-author-reviews">
                        <?php echo esc_html( $author_reviews_label ); ?>
                    </span>
                    <span class="msb-dot" aria-hidden="true">·</span>
                <?php endif; ?>

                <?php if ( $rating ) : ?>
                    <span class="msb-comment-sub-rating">
                        <?php echo msb_render_stars( $rating ); ?>
                    </span>
                    <span class="msb-dot" aria-hidden="true">·</span>
                <?php endif; ?>

                <span class="msb-comment-time">
                    <?php echo esc_html( $relative_time ); ?>
                </span>
            </div>
        </div>
    </div>

    <?php if ( $country || $service_type_label ) : ?>
        <div class="msb-comment-extra">
            <?php if ( $country ) : ?>
                <span class="msb-comment-chip"><?php echo esc_html( $country ); ?></span>
            <?php endif; ?>

            <?php if ( $service_type_label ) : ?>
                <span class="msb-comment-chip"><?php echo esc_html( $service_type_label ); ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="msb-comment-content">
        <?php comment_text( $comment ); ?>
    </div>

    <div class="msb-comment-actions">
        <button type="button"
                class="msb-icon-btn"
                aria-label="<?php echo esc_attr( msb_tpl_ui( 'btn_like_aria', 'Нравится', $td ) ); ?>">
            <span class="msb-icon-heart" aria-hidden="true">❤</span>
            <span class="msb-like-count">1</span>
        </button>

        <button type="button"
                class="msb-icon-btn"
                aria-label="<?php echo esc_attr( msb_tpl_ui( 'btn_share_aria', 'Поделиться', $td ) ); ?>">
            <span class="msb-icon-share" aria-hidden="true">↗</span>
        </button>
    </div>
</div>
