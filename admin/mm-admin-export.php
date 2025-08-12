<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
}

if ( ! class_exists( 'MM_Import_Export' ) ) {
    require_once MM_INCLUDES . 'class-mm-import-export.php';
}
if ( ! class_exists( 'MM_Quiz' ) ) {
    require_once MM_INCLUDES . 'class-mm-quiz.php';
}

$ix = new MM_Import_Export();
$quiz_model = new MM_Quiz();
$notice = '';

// Handle export form
if ( isset( $_POST['mm_export_submit'] ) ) {
    check_admin_referer( 'mm_export', 'mm_export_nonce' );

    $export_type = sanitize_text_field( $_POST['mm_export_type'] ?? '' );
    $quiz_id = intval( $_POST['quiz_id'] ?? 0 );

    if ( $export_type === 'single' && $quiz_id ) {
        $path = $ix->export_quiz_to_csv( $quiz_id );
        if ( is_wp_error( $path ) ) {
            $notice = '<div class="notice notice-error"><p>' . esc_html( $path->get_error_message() ) . '</p></div>';
        } else {
            $upload_base = mm_get_upload_base();
            $url = str_replace( $upload_base['path'], $upload_base['url'], $path );
            $notice = '<div class="notice notice-success"><p>' . sprintf( __( 'Export created: <a href="%s">Download</a>', 'markdown-master' ), esc_url( $url ) ) . '</p></div>';
        }
    } elseif ( $export_type === 'all' ) {
        $path = $ix->export_all_quizzes_csv();
        if ( is_wp_error( $path ) ) {
            $notice = '<div class="notice notice-error"><p>' . esc_html( $path->get_error_message() ) . '</p></div>';
        } else {
            $upload_base = mm_get_upload_base();
            $url = str_replace( $upload_base['path'], $upload_base['url'], $path );
            $notice = '<div class="notice notice-success"><p>' . sprintf( __( 'Export created: <a href="%s">Download</a>', 'markdown-master' ), esc_url( $url ) ) . '</p></div>';
        }
    } else {
        $notice = '<div class="notice notice-error"><p>' . esc_html__( 'Invalid export request.', 'markdown-master' ) . '</p></div>';
    }
}

echo $notice;

// Get quizzes for dropdown
$quizzes = $quiz_model->get_all_quizzes( [ 'per_page' => 1000, 'page' => 1 ] );
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Export Quizzes', 'markdown-master' ); ?></h1>

    <form method="post">
        <?php wp_nonce_field( 'mm_export', 'mm_export_nonce' ); ?>

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Export Type', 'markdown-master' ); ?></th>
                <td>
                    <label><input type="radio" name="mm_export_type" value="single" checked> <?php esc_html_e( 'Single Quiz (CSV)', 'markdown-master' ); ?></label><br>
                    <label><input type="radio" name="mm_export_type" value="all"> <?php esc_html_e( 'All Quizzes (Single CSV)', 'markdown-master' ); ?></label>
                </td>
            </tr>

            <tr class="mm-export-quiz-select">
                <th scope="row"><label for="quiz_id"><?php esc_html_e( 'Select Quiz', 'markdown-master' ); ?></label></th>
                <td>
                    <select name="quiz_id" id="quiz_id">
                        <?php foreach ( $quizzes as $q ): ?>
                            <option value="<?php echo intval( $q->id ); ?>"><?php echo esc_html( $q->title ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'Choose the quiz you want to export.', 'markdown-master' ); ?></p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" name="mm_export_submit" class="button button-primary"><?php esc_html_e( 'Export', 'markdown-master' ); ?></button>
        </p>
    </form>
</div>
