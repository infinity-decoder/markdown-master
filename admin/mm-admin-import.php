<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
}

if ( ! class_exists( 'MM_Import_Export' ) ) {
    require_once MM_INCLUDES . 'class-mm-import-export.php';
}
$ix = new MM_Import_Export();

$notice = '';

// Handle upload and import
if ( isset( $_POST['mm_import_submit'] ) ) {
    check_admin_referer( 'mm_import', 'mm_import_nonce' );

    if ( ! empty( $_FILES['mm_import_file']['tmp_name'] ) ) {
        $uploaded = $_FILES['mm_import_file'];

        // Validate file types (allow csv, txt, xls, xlsx)
        $allowed_types = [ 'text/csv', 'text/plain', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ];
        $filetype = wp_check_filetype( $uploaded['name'] );
        $ext = strtolower( pathinfo( $uploaded['name'], PATHINFO_EXTENSION ) );

        // Move file into plugin upload dir
        $upload_base = mm_get_upload_base();
        if ( ! file_exists( $upload_base['path'] ) ) {
            wp_mkdir_p( $upload_base['path'] );
        }
        $dest = $upload_base['path'] . 'import-' . time() . '-' . sanitize_file_name( $uploaded['name'] );
        if ( move_uploaded_file( $uploaded['tmp_name'], $dest ) ) {
            // For now only CSV import implemented (class handles CSV)
            if ( in_array( $ext, [ 'csv', 'txt' ], true ) ) {
                $res = $ix->import_quiz_from_csv( $dest );
                if ( is_wp_error( $res ) ) {
                    $notice = '<div class="notice notice-error"><p>' . esc_html( $res->get_error_message() ) . '</p></div>';
                } else {
                    $notice = '<div class="notice notice-success"><p>' . sprintf( __( 'Quiz imported successfully (ID: %d).', 'markdown-master' ), intval( $res ) ) . '</p></div>';
                }
            } else {
                $notice = '<div class="notice notice-warning"><p>' . esc_html__( 'Only CSV/TXT import is supported in this version. For Excel import please install PhpSpreadsheet and enhance import logic.', 'markdown-master' ) . '</p></div>';
            }
        } else {
            $notice = '<div class="notice notice-error"><p>' . esc_html__( 'Failed to move uploaded file.', 'markdown-master' ) . '</p></div>';
        }
    } else {
        $notice = '<div class="notice notice-error"><p>' . esc_html__( 'No file uploaded.', 'markdown-master' ) . '</p></div>';
    }
}

echo $notice;
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Import Quiz', 'markdown-master' ); ?></h1>

    <p><?php esc_html_e( 'You can import quizzes from CSV or TXT files. The CSV should contain headers like: quiz_title, quiz_description, question_text, question_type, option_1, option_2, option_3, option_4, correct_option_index, question_image', 'markdown-master' ); ?></p>

    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field( 'mm_import', 'mm_import_nonce' ); ?>
        <table class="form-table">
            <tr>
                <th><label for="mm_import_file"><?php esc_html_e( 'Choose file', 'markdown-master' ); ?></label></th>
                <td><input type="file" name="mm_import_file" id="mm_import_file" accept=".csv,.txt"></td>
            </tr>
        </table>
        <p class="submit">
            <button type="submit" name="mm_import_submit" class="button button-primary"><?php esc_html_e( 'Import', 'markdown-master' ); ?></button>
        </p>
    </form>
</div>
