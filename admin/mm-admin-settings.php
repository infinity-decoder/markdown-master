<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
}

if ( ! class_exists( 'MM_Settings' ) ) {
    require_once MM_INCLUDES . 'class-mm-settings.php';
}

$settings_model = new MM_Settings();
$settings = $settings_model->get_all();

// Handle save
if ( isset( $_POST['mm_settings_submit'] ) ) {
    check_admin_referer( 'mm_settings_save', 'mm_settings_nonce' );

    $new_data = [
        'show_answers' => sanitize_text_field( $_POST['show_answers'] ?? $settings['show_answers'] ),
        'theme' => sanitize_text_field( $_POST['theme'] ?? $settings['theme'] ),
        'timer_enabled' => isset( $_POST['timer_enabled'] ) ? boolval( $_POST['timer_enabled'] ) : false,
        'randomize_questions' => isset( $_POST['randomize_questions'] ) ? boolval( $_POST['randomize_questions'] ) : false,
        'max_attempts' => intval( $_POST['max_attempts'] ?? 0 ),
    ];

    $updated = $settings_model->update( $new_data );
    if ( $updated ) {
        add_settings_error( 'mm_settings_messages', 'mm-settings-saved', __( 'Settings saved.', 'markdown-master' ), 'updated' );
        $settings = $settings_model->get_all(); // refresh
    } else {
        add_settings_error( 'mm_settings_messages', 'mm-settings-failed', __( 'Failed to save settings.', 'markdown-master' ), 'error' );
    }
}

settings_errors( 'mm_settings_messages' );
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Markdown Master Settings', 'markdown-master' ); ?></h1>

    <form method="post" action="">
        <?php wp_nonce_field( 'mm_settings_save', 'mm_settings_nonce' ); ?>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="show_answers"><?php esc_html_e( 'Show Answers', 'markdown-master' ); ?></label></th>
                <td>
                    <select name="show_answers" id="show_answers">
                        <option value="end" <?php selected( $settings['show_answers'] ?? 'end', 'end' ); ?>><?php esc_html_e( 'Show at end of quiz', 'markdown-master' ); ?></option>
                        <option value="instant" <?php selected( $settings['show_answers'] ?? 'end', 'instant' ); ?>><?php esc_html_e( 'Show after each question', 'markdown-master' ); ?></option>
                    </select>
                    <p class="description"><?php esc_html_e( 'Choose when correct answers are revealed to users.', 'markdown-master' ); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="theme"><?php esc_html_e( 'Visual Theme', 'markdown-master' ); ?></label></th>
                <td>
                    <select name="theme" id="theme">
                        <option value="default" <?php selected( $settings['theme'] ?? 'default', 'default' ); ?>><?php esc_html_e( 'Default', 'markdown-master' ); ?></option>
                        <option value="compact" <?php selected( $settings['theme'] ?? 'default', 'compact' ); ?>><?php esc_html_e( 'Compact', 'markdown-master' ); ?></option>
                        <option value="spacious" <?php selected( $settings['theme'] ?? 'default', 'spacious' ); ?>><?php esc_html_e( 'Spacious', 'markdown-master' ); ?></option>
                    </select>
                    <p class="description"><?php esc_html_e( 'Appearance presets for frontend quizzes.', 'markdown-master' ); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e( 'Enable Timer', 'markdown-master' ); ?></th>
                <td>
                    <label><input type="checkbox" name="timer_enabled" value="1" <?php checked( ! empty( $settings['timer_enabled'] ), true ); ?>> <?php esc_html_e( 'Enable global timer control (per-quiz can override).', 'markdown-master' ); ?></label>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e( 'Randomize Questions', 'markdown-master' ); ?></th>
                <td>
                    <label><input type="checkbox" name="randomize_questions" value="1" <?php checked( ! empty( $settings['randomize_questions'] ), true ); ?>> <?php esc_html_e( 'Randomize question order on every attempt.', 'markdown-master' ); ?></label>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="max_attempts"><?php esc_html_e( 'Max Attempts Per User', 'markdown-master' ); ?></label></th>
                <td>
                    <input type="number" name="max_attempts" id="max_attempts" value="<?php echo esc_attr( intval( $settings['max_attempts'] ?? 0 ) ); ?>" min="0">
                    <p class="description"><?php esc_html_e( '0 means unlimited attempts.', 'markdown-master' ); ?></p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" name="mm_settings_submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'markdown-master' ); ?></button>
        </p>
    </form>
</div>
