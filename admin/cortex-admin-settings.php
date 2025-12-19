<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
}

if ( ! class_exists( 'Cortex_Settings' ) ) {
    require_once CORTEX_INCLUDES . 'class-cortex-settings.php';
}

$settings_model = new Cortex_Settings();
$settings = $settings_model->get_all();

// Handle save
if ( isset( $_POST['cortex_settings_submit'] ) ) {
    check_admin_referer( 'cortex_settings_save', 'cortex_settings_nonce' );

    $new_data = [
        'show_answers' => sanitize_text_field( $_POST['show_answers'] ?? $settings['show_answers'] ),
        'theme' => sanitize_text_field( $_POST['theme'] ?? $settings['theme'] ),
        'timer_enabled' => isset( $_POST['timer_enabled'] ) ? boolval( $_POST['timer_enabled'] ) : false,
        'randomize_questions' => isset( $_POST['randomize_questions'] ) ? boolval( $_POST['randomize_questions'] ) : false,
        'max_attempts' => intval( $_POST['max_attempts'] ?? 0 ),
    ];

    $updated = $settings_model->update( $new_data );
    if ( $updated ) {
        add_settings_error( 'cortex_settings_messages', 'cortex-settings-saved', __( 'Settings saved.', 'cortex' ), 'updated' );
        $settings = $settings_model->get_all(); // refresh
    } else {
        add_settings_error( 'cortex_settings_messages', 'cortex-settings-failed', __( 'Failed to save settings.', 'cortex' ), 'error' );
    }
}

settings_errors( 'cortex_settings_messages' );
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Cortex Settings', 'cortex' ); ?></h1>

    <form method="post" action="">
        <?php wp_nonce_field( 'cortex_settings_save', 'cortex_settings_nonce' ); ?>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="show_answers"><?php esc_html_e( 'Show Answers', 'cortex' ); ?></label></th>
                <td>
                    <select name="show_answers" id="show_answers">
                        <option value="end" <?php selected( $settings['show_answers'] ?? 'end', 'end' ); ?>><?php esc_html_e( 'Show at end of quiz', 'cortex' ); ?></option>
                        <option value="instant" <?php selected( $settings['show_answers'] ?? 'end', 'instant' ); ?>><?php esc_html_e( 'Show after each question', 'cortex' ); ?></option>
                    </select>
                    <p class="description"><?php esc_html_e( 'Choose when correct answers are revealed to users.', 'cortex' ); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="theme"><?php esc_html_e( 'Visual Theme', 'cortex' ); ?></label></th>
                <td>
                    <select name="theme" id="theme">
                        <option value="default" <?php selected( $settings['theme'] ?? 'default', 'default' ); ?>><?php esc_html_e( 'Default', 'cortex' ); ?></option>
                        <option value="compact" <?php selected( $settings['theme'] ?? 'default', 'compact' ); ?>><?php esc_html_e( 'Compact', 'cortex' ); ?></option>
                        <option value="spacious" <?php selected( $settings['theme'] ?? 'default', 'spacious' ); ?>><?php esc_html_e( 'Spacious', 'cortex' ); ?></option>
                    </select>
                    <p class="description"><?php esc_html_e( 'Appearance presets for frontend quizzes.', 'cortex' ); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e( 'Enable Timer', 'cortex' ); ?></th>
                <td>
                    <label><input type="checkbox" name="timer_enabled" value="1" <?php checked( ! empty( $settings['timer_enabled'] ), true ); ?>> <?php esc_html_e( 'Enable global timer control (per-quiz can override).', 'cortex' ); ?></label>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e( 'Randomize Questions', 'cortex' ); ?></th>
                <td>
                    <label><input type="checkbox" name="randomize_questions" value="1" <?php checked( ! empty( $settings['randomize_questions'] ), true ); ?>> <?php esc_html_e( 'Randomize question order on every attempt.', 'cortex' ); ?></label>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="max_attempts"><?php esc_html_e( 'Max Attempts Per User', 'cortex' ); ?></label></th>
                <td>
                    <input type="number" name="max_attempts" id="max_attempts" value="<?php echo esc_attr( intval( $settings['max_attempts'] ?? 0 ) ); ?>" min="0">
                    <p class="description"><?php esc_html_e( '0 means unlimited attempts.', 'cortex' ); ?></p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" name="cortex_settings_submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'cortex' ); ?></button>
        </p>
    </form>
</div>
