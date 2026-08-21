<?php
/**
 * Админ-страница настроек плагина комментариев Meryosab.
 *
 * Файл: admin_control/admin_control.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Регистрируем страницу настроек в меню «Настройки».
 */
function msb_comments_register_settings_page() {
    add_options_page(
        'Настройки отзывов Meryosab',
        'Отзывы Meryosab',
        'manage_options',
        'msb-comments-settings',
        'msb_comments_render_settings_page'
    );
}
add_action( 'admin_menu', 'msb_comments_register_settings_page' );

/**
 * Прочитать хвост файла лога.
 *
 * @param string $file_path Путь к файлу.
 * @param int    $max_bytes Максимальный размер.
 * @return string
 */
function msb_comments_read_log_tail( $file_path, $max_bytes = 20000 ) {
    if ( ! $file_path || ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
        return '';
    }

    $size = filesize( $file_path );
    if ( $size <= 0 ) {
        return '';
    }

    if ( $size <= $max_bytes ) {
        return file_get_contents( $file_path );
    }

    $handle = fopen( $file_path, 'r' );
    if ( ! $handle ) {
        return '';
    }

    fseek( $handle, -1 * $max_bytes, SEEK_END );
    $data = fread( $handle, $max_bytes );
    fclose( $handle );

    return "... (показаны только последние {$max_bytes} байт) ...\n\n" . $data;
}

/**
 * Вывод страницы настроек.
 */
function msb_comments_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( isset( $_POST['msb_comments_action'] ) ) {
        check_admin_referer( 'msb_comments_save_settings_nonce' );
        $action = sanitize_text_field( wp_unslash( $_POST['msb_comments_action'] ) );

        if ( 'save_settings' === $action ) {
            $text_fields = array(
                'msb_comments_ai_analysis_key',
                'msb_comments_ai_reply_key',
                'msb_comments_ai_bot_name',
                'msb_comments_subtitle_text',
                'msb_comments_service_types',
                'msb_comments_prompt_analysis',
                'msb_comments_prompt_reply',
            );

            foreach ( $text_fields as $field ) {
                $value = isset( $_POST[ $field ] )
                    ? wp_unslash( $_POST[ $field ] )
                    : '';
                update_option( $field, wp_kses_post( $value ) );
            }

            update_option(
                'msb_comments_ai_reply_user_id',
                isset( $_POST['msb_comments_ai_reply_user_id'] )
                    ? absint( $_POST['msb_comments_ai_reply_user_id'] )
                    : 0
            );

            echo '<div class="updated notice is-dismissible"><p>' .
                esc_html__( 'Настройки сохранены.', 'msb-comments' ) .
                '</p></div>';
        }
    }

    $defaults = array(
        'msb_comments_ai_analysis_key' => '',
        'msb_comments_ai_reply_key'    => '',
        'msb_comments_ai_bot_name'     => '',
        'msb_comments_subtitle_text'   => '',
        'msb_comments_service_types'   => "Визовая поддержка\nПриглашение в Туркменистан\nТранзит\nТуризм\nДругое",
        'msb_comments_prompt_analysis' => '',
        'msb_comments_prompt_reply'    => '',
    );

    $values = array();
    foreach ( $defaults as $key => $default ) {
        $values[ $key ] = get_option( $key, $default );
    }
    $reply_user_id = absint( get_option( 'msb_comments_ai_reply_user_id', 0 ) );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Настройки отзывов Meryosab', 'msb-comments' ); ?></h1>
        <form method="post">
            <?php wp_nonce_field( 'msb_comments_save_settings_nonce' ); ?>
            <input type="hidden" name="msb_comments_action" value="save_settings">
            <table class="form-table" role="presentation">
                <?php
                $fields = array(
                    'msb_comments_ai_analysis_key' => 'Ключ для анализа отзывов',
                    'msb_comments_ai_reply_key'    => 'Ключ для ответов на отзывы',
                    'msb_comments_ai_bot_name'     => 'Имя бота поддержки',
                    'msb_comments_subtitle_text'   => 'Подзаголовок блока отзывов',
                );
                foreach ( $fields as $name => $label ) :
                    ?>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label></th>
                        <td><input type="text" class="regular-text" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $values[ $name ] ); ?>"></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <th scope="row"><label for="msb_comments_ai_reply_user_id">Пользователь для авто-ответов ИИ</label></th>
                    <td><input type="number" class="small-text" id="msb_comments_ai_reply_user_id" name="msb_comments_ai_reply_user_id" value="<?php echo esc_attr( $reply_user_id ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="msb_comments_service_types">Список услуг</label></th>
                    <td><textarea class="large-text code" rows="6" id="msb_comments_service_types" name="msb_comments_service_types"><?php echo esc_textarea( $values['msb_comments_service_types'] ); ?></textarea></td>
                </tr>
                <tr>
                    <th scope="row"><label for="msb_comments_prompt_analysis">Промпт анализа отзывов</label></th>
                    <td><textarea class="large-text code" rows="10" id="msb_comments_prompt_analysis" name="msb_comments_prompt_analysis"><?php echo esc_textarea( $values['msb_comments_prompt_analysis'] ); ?></textarea></td>
                </tr>
                <tr>
                    <th scope="row"><label for="msb_comments_prompt_reply">Промпт ответов на отзывы</label></th>
                    <td><textarea class="large-text code" rows="10" id="msb_comments_prompt_reply" name="msb_comments_prompt_reply"><?php echo esc_textarea( $values['msb_comments_prompt_reply'] ); ?></textarea></td>
                </tr>
            </table>
            <?php submit_button( __( 'Сохранить изменения', 'msb-comments' ) ); ?>
        </form>
    </div>
    <?php
}
