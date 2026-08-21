<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Безопасность загрузок из формы отзывов:
 * - разрешаем только изображения и видео (JPG, PNG, WEBP, GIF, MP4, MOV, WEBM)
 * - ограничиваем размер файла
 * Работает только на фронтенде (не ломает загрузки в админке).
 */
function msb_secure_comment_uploads( $file ) {

    // В админке (загрузка плагинов, тем, медиа) — НИЧЕГО не трогаем
    if ( is_admin() ) {
        return $file;
    }

    // Максимальный размер файла, например 10 МБ
    $max_size = 10 * 1024 * 1024; // 10 MB

    if ( ! empty( $file['size'] ) && $file['size'] > $max_size ) {
        $file['error'] = 'Файл слишком большой. Максимальный размер — 10 МБ.';
        return $file;
    }

    // Белый список разрешённых расширений
    $allowed_exts = array( 'jpg', 'jpeg', 'jpe', 'png', 'webp', 'gif', 'mp4', 'mov', 'webm' );

    // Проверяем реальный тип файла
    $checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );

    // Если WordPress не смог определить тип — блокируем
    if ( empty( $checked['ext'] ) || empty( $checked['type'] ) ) {
        $file['error'] = 'Этот тип файла не поддерживается. Разрешены только изображения и видео.';
        return $file;
    }

    // Если расширение не в белом списке — блокируем
    if ( ! in_array( strtolower( $checked['ext'] ), $allowed_exts, true ) ) {
        $file['error'] = 'Разрешены только файлы: JPG, PNG, WEBP, GIF, MP4, MOV, WEBM.';
        return $file;
    }

    // Подменяем MIME-тип на проверенный (на всякий случай)
    $file['type'] = $checked['type'];

    return $file;
}
add_filter( 'wp_handle_upload_prefilter', 'msb_secure_comment_uploads' );
