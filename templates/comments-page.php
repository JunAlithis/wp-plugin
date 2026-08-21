
<?php
/**
 * Шаблон основной страницы отзывов для [msb_comments]
 *
 * @var WP_Comment[] $comments
 * @var array        $comment_form_args
 * @var array        $pagination (опционально: current, total, per_page, total_main)
 * @var int          $msb_source_post_id  ID поста, к которому реально привязаны комментарии
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$td = defined( 'MSB_COMMENTS_TEXT_DOMAIN' ) ? MSB_COMMENTS_TEXT_DOMAIN : 'msb-comments';

/**
 * Хелпер: безопасно получить строку UI (через msb_ui_tr, если она есть).
 * ВАЖНО: с защитой от повторного объявления (иначе 500).
 */
if ( ! function_exists( 'msb_tpl_ui' ) ) {
    function msb_tpl_ui( $key, $default, $td = 'msb-comments' ) {
        if ( function_exists( 'msb_ui_tr' ) ) {
            return msb_ui_tr( $key, $default );
        }

        return __( $default, $td );
    }
}

// --- UI-строки, которые должны быть в Polylang/WPML ---
// (ключи синхронизированы с msb_register_all_ui_strings)

$btn_write_review    = msb_tpl_ui( 'btn_write_review', 'Написать отзыв', $td );

$sort_label          = msb_tpl_ui( 'sort_by_label', 'Сортировать по', $td );
$sort_by_relevance   = msb_tpl_ui( 'sort_by_relevance', 'по релевантности', $td );
$sort_by_date        = msb_tpl_ui( 'sort_by_date', 'по дате', $td );
$sort_by_rating_desc = msb_tpl_ui( 'sort_by_rating_desc', 'по убыванию рейтинга', $td );
$sort_by_rating_asc  = msb_tpl_ui( 'sort_by_rating_asc', 'по возрастанию рейтинга', $td );
$sort_by_helpful     = msb_tpl_ui( 'sort_by_helpful', 'по полезности (👍)', $td );

$aria_open_image     = msb_tpl_ui( 'media_open_image', 'Открыть изображение', $td );
$aria_video          = msb_tpl_ui( 'media_video_label', 'Видео', $td );

// Для реакций используем уже существующие ключи, чтобы не плодить дубликаты
$aria_react_like     = msb_tpl_ui( 'recent_stat_like', 'Нравится', $td );
$aria_react_love     = msb_tpl_ui( 'recent_stat_love', 'Обожаю', $td );
$aria_react_wow      = msb_tpl_ui( 'recent_stat_wow',  'Вау', $td );

$btn_helpful         = msb_tpl_ui( 'btn_helpful', 'Полезный отзыв', $td );

$aria_pagination     = msb_tpl_ui( 'pagination_aria', 'Навигация по отзывам', $td );
$pagination_label    = msb_tpl_ui( 'pagination_page_of', 'Страница %1$s из %2$s', $td );
$pagination_prev     = msb_tpl_ui( 'pagination_prev', 'Предыдущие отзывы', $td );
$pagination_next     = msb_tpl_ui( 'pagination_next', 'Читать другие отзывы', $td );

$no_comments         = msb_tpl_ui(
    'no_comments_text',
    'Отзывов пока нет. Будьте первым, кто поделится своим опытом.',
    $td
);

$modal_title_default = msb_tpl_ui( 'js_modal_title_review', 'Оставьте отзыв', $td );
$modal_close_label   = msb_tpl_ui( 'modal_close', 'Закрыть', $td );
?>

<div class="msb-comments-wrapper">
    <div class="home-page-block-34__head">
        <div class="home-page-block-34__kicker">Отзывы клиентов</div>
        <h2 id="home-page-block-34-title">Отзывы о наших услугах</h2>
        <p>Читайте реальные отзывы клиентов о ремонте и обслуживании телевизоров. Поделитесь своим опытом, чтобы помочь другим клиентам сделать правильный выбор.</p>
    </div>

    <div class="msb-review-topbar">
        <div></div>
        <button type="button" class="msb-open-review-modal msb-primary-button">
            <?php echo esc_html( $btn_write_review ); ?>
        </button>
    </div>

    <?php if ( ! empty( $comments ) ) : ?>

        <div class="msb-comments-list">
            <?php foreach ( $comments as $index => $comment ) : ?>
                <?php
                $comment_id   = $comment->comment_ID;
                $author       = get_comment_author( $comment );
                $rating       = (int) get_comment_meta( $comment_id, 'msb_rating', true );
                $country      = get_comment_meta( $comment_id, 'msb_country', true );
                $service_type = get_comment_meta( $comment_id, 'msb_service_type', true );
                $helpful      = (int) get_comment_meta( $comment_id, 'msb_helpful', true );

                $date_iso   = get_comment_date( 'c', $comment );
                $date_human = get_comment_date( 'd.m.Y', $comment );

                $rating_attr = $rating > 0 ? $rating : 0;

                // медиа
                $media_ids = get_comment_meta( $comment_id, 'msb_media_ids', true );
                if ( ! is_array( $media_ids ) ) {
                    $media_ids = array();
                }

                $react_like = (int) get_comment_meta( $comment_id, 'msb_react_like', true );
                $react_love = (int) get_comment_meta( $comment_id, 'msb_react_love', true );
                $react_wow  = (int) get_comment_meta( $comment_id, 'msb_react_wow', true );

                // Перевод услуги (label), но value в БД остаётся каноническим
                $service_type_label = '';
                if ( $service_type ) {
                    if ( function_exists( 'msb_service_type_label' ) ) {
                        $service_type_label = msb_service_type_label( $service_type );
                    } else {
                        $service_type_label = $service_type;
                    }
                }
                ?>
                <article id="comment-<?php echo esc_attr( $comment_id ); ?>"
                         class="msb-comment-card"
                         data-rating="<?php echo esc_attr( $rating_attr ); ?>"
                         data-date="<?php echo esc_attr( $date_iso ); ?>"
                         data-helpful="<?php echo esc_attr( $helpful ); ?>"
                         data-original-index="<?php echo esc_attr( $index ); ?>">

                    <div class="msb-comment-card-header">
                        <div class="msb-comment-author-block">
                            <div class="msb-comment-avatar">
                                <?php echo get_avatar( $comment, 40 ); ?>
                            </div>
                            <div class="msb-comment-author-meta">
                                <div class="msb-comment-author-name">
                                    <?php echo esc_html( $author ); ?>
                                </div>
                                <div class="msb-comment-meta-line">
                                    <time datetime="<?php echo esc_attr( $date_iso ); ?>">
                                        <?php echo esc_html( $date_human ); ?>
                                    </time>
                                </div>
                            </div>
                        </div>

                        <?php if ( $rating > 0 ) : ?>
                            <div class="msb-comment-rating">
                                <?php echo msb_render_stars( $rating ); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="msb-comment-content">
                        <?php echo wpautop( esc_html( $comment->comment_content ) ); ?>

                        <?php if ( $media_ids ) : ?>
                            <div class="msb-comment-media">
                                <?php foreach ( $media_ids as $mid ) : ?>
                                    <?php
                                    $mime = get_post_mime_type( $mid );
                                    $url  = wp_get_attachment_url( $mid );
                                    if ( ! $url ) {
                                        continue;
                                    }
                                    ?>
                                    <?php if ( strpos( (string) $mime, 'image/' ) === 0 ) : ?>
                                        <a href="<?php echo esc_url( $url ); ?>"
                                           target="_blank"
                                           class="msb-comment-media-item"
                                           aria-label="<?php echo esc_attr( $aria_open_image ); ?>">
                                            <?php echo wp_get_attachment_image( $mid, 'thumbnail' ); ?>
                                        </a>
                                    <?php elseif ( strpos( (string) $mime, 'video/' ) === 0 ) : ?>
                                        <video class="msb-comment-media-item"
                                               src="<?php echo esc_url( $url ); ?>"
                                               controls
                                               aria-label="<?php echo esc_attr( $aria_video ); ?>"></video>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="msb-comment-actions">
                        <div class="msb-comment-actions-left">
                            <button type="button"
                                    class="msb-reply-button"
                                    data-comment-id="<?php echo esc_attr( $comment_id ); ?>">
                                <?php echo esc_html( msb_tpl_ui( 'btn_reply', 'Ответить', $td ) ); ?>
                            </button>
                        </div>

                        
                    </div>

                    <?php
                    // ответы на этот отзыв
                    $children = get_comments(
                        array(
                            'post_id' => $comment->comment_post_ID,
                            'status'  => 'approve',
                            'parent'  => $comment_id,
                            'order'   => 'ASC',
                            'number'  => 0,
                        )
                    );
                    ?>

                    <?php if ( $children ) : ?>
                        <div class="msb-comment-children">
                            <?php foreach ( $children as $child ) : ?>
                                <?php
                                $child_id         = $child->comment_ID;
                                $child_auth       = get_comment_author( $child );
                                $child_date_iso   = get_comment_date( 'c', $child );
                                $child_date_human = get_comment_date( 'd.m.Y', $child );

                                // Проверяем, это ли AI-ответ
                                $is_ai_reply = get_comment_meta( $child_id, 'msb_ai_reply', true );
                                ?>
                                <article id="comment-<?php echo esc_attr( $child_id ); ?>"
                                         class="msb-comment-card msb-comment-card--child"
                                         data-comment-id="<?php echo esc_attr( $child_id ); ?>"
                                         <?php echo $is_ai_reply ? ' data-msb-ai-reply="1"' : ''; ?>>

                                    <div class="msb-comment-card-header">
                                        <div class="msb-comment-author-block">
                                            <div class="msb-comment-avatar">
                                                <?php echo get_avatar( $child, 32 ); ?>
                                            </div>
                                            <div class="msb-comment-author-meta">
                                                <div class="msb-comment-author-name">
                                                    <?php echo esc_html( $child_auth ); ?>
                                                </div>
                                                <div class="msb-comment-meta-line">
                                                    <time datetime="<?php echo esc_attr( $child_date_iso ); ?>">
                                                        <?php echo esc_html( $child_date_human ); ?>
                                                    </time>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="msb-comment-content">
                                        <?php echo wpautop( esc_html( $child->comment_content ) ); ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>

        <?php
        // ===== ПАГИНАЦИЯ ПО 10 ОТЗЫВОВ =====
        if ( isset( $pagination ) && ! empty( $pagination ) && ! empty( $pagination['total'] ) && (int) $pagination['total'] > 1 ) :

            $current = (int) $pagination['current'];
            $total   = (int) $pagination['total'];

            // Базовый URL страницы с отзывами (без msb_page)
            $base_url = remove_query_arg( 'msb_page' );
            ?>
            <nav class="msb-pagination" aria-label="<?php echo esc_attr( $aria_pagination ); ?>">
                <div class="msb-pagination__info">
                    <?php
                    echo sprintf(
                        esc_html( $pagination_label ),
                        esc_html( (string) $current ),
                        esc_html( (string) $total )
                    );
                    ?>
                </div>

                <div class="msb-pagination__links">
                    <?php if ( $current > 1 ) : ?>
                        <a class="msb-pagination__prev"
                           href="<?php echo esc_url( add_query_arg( 'msb_page', $current - 1, $base_url ) ); ?>">
                            ← <?php echo esc_html( $pagination_prev ); ?>
                        </a>
                    <?php endif; ?>

                    <?php if ( $current < $total ) : ?>
                        <a class="msb-pagination__next"
                           href="<?php echo esc_url( add_query_arg( 'msb_page', $current + 1, $base_url ) ); ?>">
                            <?php echo esc_html( $pagination_next ); ?> →
                        </a>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>

    <?php else : ?>
        <p class="msb-no-comments">
            <?php echo esc_html( $no_comments ); ?>
        </p>
    <?php endif; ?>
</div>

<!-- Модальное окно "Написать отзыв / Ответить" -->
<div class="msb-modal-overlay" id="msb-review-modal">
    <div class="msb-modal-dialog">
        <div class="msb-modal-header">
            <div class="msb-modal-title"><?php echo esc_html( $modal_title_default ); ?></div>

            <button type="button"
                    class="msb-modal-close"
                    aria-label="<?php echo esc_attr( $modal_close_label ); ?>">×</button>
        </div>
        <div class="msb-modal-body">
            <?php
            // К какому посту привязываем комментарии (ID приходит из шорткода)
            $target_post_id = 0;

            if ( isset( $msb_source_post_id ) && (int) $msb_source_post_id > 0 ) {
                $target_post_id = (int) $msb_source_post_id;
            } else {
                $target_post_id = get_the_ID();
            }

            // Просто выводим форму: enctype добавит фильтр msb_comment_form_enctype
            comment_form( $comment_form_args, $target_post_id );
            ?>
        </div>
    </div>
</div>
