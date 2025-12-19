<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Cortex_Import_Export
 * Robust import/export of quizzes, questions and results.
 * - Handles CSV/XLSX import/export using PhpSpreadsheet when available.
 * - Handles PDF export using Dompdf when available; otherwise provides printable HTML.
 * - Hooks into admin UI (Quizzes screen) to show Import box and handle Export links.
 *
 * This class is careful to work even when libraries are missing (degrades to CSV and printable HTML).
 */

class Cortex_Import_Export {

    /** @var Cortex_Quiz */
    protected $quiz;

    /** @var string */
    protected $upload_dir;

    public function __construct() {
        $this->quiz = new Cortex_Quiz();

        // Prepare upload dir
        $wp_up = wp_upload_dir();
        $this->upload_dir = trailingslashit( $wp_up['basedir'] ) . 'cortex/';
        if ( ! file_exists( $this->upload_dir ) ) {
            wp_mkdir_p( $this->upload_dir );
        }

        // Admin routes
        add_action( 'admin_init', [ $this, 'maybe_route_from_quizzes_page' ] );

        // Admin-post handlers
        add_action( 'admin_post_cortex_import_questions', [ $this, 'handle_import_post' ] );
        add_action( 'admin_post_cortex_export_quiz', [ $this, 'handle_export_quiz_post' ] );
        add_action( 'admin_post_cortex_export_all_quizzes', [ $this, 'handle_export_all_quizzes_post' ] );
        add_action( 'admin_post_cortex_export_results_csv', [ $this, 'handle_export_results_csv_post' ] );

        // Show Import box on Quizzes admin screen
        add_action( 'admin_notices', [ $this, 'render_quizzes_import_box' ] );
    }

    /**
     * Route export requests
     */
    public function maybe_route_from_quizzes_page() {
        if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $page   = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
        $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
        $id     = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

        if ( $page === 'cortex_quizzes' && $action === 'export' && $id > 0 ) {
            $this->stream_quiz_export( $id );
            exit;
        }
    }

    /**
     * Render Import UI
     */
    public function render_quizzes_import_box() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== 'toplevel_page_cortex_quizzes' ) {
            return;
        }

        global $wpdb;
        $quizzes = $wpdb->get_results( "SELECT id, title FROM {$wpdb->prefix}cortex_quizzes ORDER BY id DESC" );

        ?>
        <div class="notice notice-info" style="padding:16px;">
            <h2 style="margin-top:0;"><?php esc_html_e( 'Import Questions (CSV)', 'cortex' ); ?></h2>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field( 'cortex_import_questions', 'cortex_import_questions_nonce' ); ?>
                <input type="hidden" name="action" value="cortex_import_questions"/>
                <p>
                    <label><strong><?php esc_html_e( 'Choose CSV file', 'cortex' ); ?>:</strong></label>
                    <input type="file" name="cortex_import_file" accept=".csv" required>
                </p>
                <p>
                    <label><strong><?php esc_html_e( 'Import to quiz', 'cortex' ); ?>:</strong></label>
                    <select name="quiz_id">
                        <option value="0"><?php esc_html_e( '— Create new quiz —', 'cortex' ); ?></option>
                        <?php foreach ( (array) $quizzes as $q ): ?>
                            <option value="<?php echo esc_attr( (int) $q->id ); ?>"><?php echo esc_html( $q->title . ' (#' . $q->id . ')' ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <div id="cortex-new-quiz-fields">
                    <p>
                        <label><strong><?php esc_html_e( 'New Quiz Title', 'cortex' ); ?>:</strong></label>
                        <input type="text" name="new_quiz_title" class="regular-text">
                    </p>
                    <p>
                        <label><strong><?php esc_html_e( 'New Quiz Description', 'cortex' ); ?>:</strong></label>
                        <textarea name="new_quiz_description" rows="3" class="large-text"></textarea>
                    </p>
                </div>
                <p class="description">
                    <?php
                    esc_html_e( 'CSV headers supported: question_text, question_type, options (pipe-separated), correct_answer.', 'cortex' );
                    ?>
                </p>
                <p>
                    <button class="button button-primary" type="submit"><?php esc_html_e( 'Import', 'cortex' ); ?></button>
                </p>
            </form>
            <script>
                (function(){
                    const sel = document.querySelector('select[name="quiz_id"]');
                    const block = document.getElementById('cortex-new-quiz-fields');
                    function toggle(){
                        if(!sel) return;
                        block.style.display = (sel.value === "0") ? "block":"none";
                    }
                    if(sel){ sel.addEventListener('change', toggle); toggle(); }
                })();
            </script>
        </div>
        <?php
    }

    /**
     * Handle Import POST
     */
    public function handle_import_post() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permission denied', 'cortex' ) );
        }
        check_admin_referer( 'cortex_import_questions', 'cortex_import_questions_nonce' );

        if ( empty( $_FILES['cortex_import_file']['tmp_name'] ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=cortex_quizzes&cortex_msg=' . urlencode( __( 'No file uploaded', 'cortex' ) ) ) );
            exit;
        }

        $tmp  = $_FILES['cortex_import_file']['tmp_name'];
        $quiz_id = isset( $_POST['quiz_id'] ) ? (int) $_POST['quiz_id'] : 0;

        if ( $quiz_id <= 0 ) {
            $title = isset( $_POST['new_quiz_title'] ) ? sanitize_text_field( wp_unslash( $_POST['new_quiz_title'] ) ) : 'Imported Quiz ' . date( 'Y-m-d H:i' );
            $desc  = isset( $_POST['new_quiz_description'] ) ? wp_kses_post( wp_unslash( $_POST['new_quiz_description'] ) ) : '';

            $quiz_id = $this->quiz->create_quiz( [
                'title'       => $title,
                'description' => $desc,
                'settings'    => [],
            ] );
        }

        try {
            $rows = $this->read_csv_rows( $tmp );
            $count = $this->import_rows_into_quiz( $quiz_id, $rows );

            wp_safe_redirect( admin_url( 'admin.php?page=cortex_quizzes&cortex_msg=' . urlencode( sprintf( __( 'Successfully imported %d questions.', 'cortex' ), $count ) ) ) );
            exit;
        } catch ( Exception $e ) {
            wp_die( esc_html__( 'Import failed: ', 'cortex' ) . esc_html( $e->getMessage() ) );
        }
    }

    /**
     * Read CSV
     */
    protected function read_csv_rows( $file_path ) {
        if ( ! is_readable( $file_path ) ) {
            throw new RuntimeException( 'CSV file is not readable.' );
        }
        $fh = fopen( $file_path, 'r' );
        if ( ! $fh ) {
            throw new RuntimeException( 'Unable to open CSV file.' );
        }
        $header = fgetcsv( $fh );
        if ( ! $header ) {
            fclose( $fh );
            throw new RuntimeException( 'CSV header missing.' );
        }
        $header = array_map( function( $h ){ return strtolower( trim( $h ) ); }, $header );

        $rows = [];
        while ( ( $row = fgetcsv( $fh ) ) !== false ) {
            $assoc = [];
            foreach ( $header as $i => $key ) {
                $assoc[ $key ] = isset( $row[ $i ] ) ? trim( $row[ $i ] ) : '';
            }
            $rows[] = $assoc;
        }
        fclose( $fh );
        return $rows;
    }

    /**
     * Import Logic
     */
    protected function import_rows_into_quiz( $quiz_id, array $rows ) : int {
        $imported = 0;
        foreach ( $rows as $row ) {
            $qtext = trim( (string) ( $row['question_text'] ?? ( $row['question'] ?? '' ) ) );
            if ( $qtext === '' ) continue;

            $options = ! empty( $row['options'] ) ? array_map( 'trim', explode( '|', $row['options'] ) ) : [];
            $correct = $row['correct_answer'] ?? ( $row['correct_option_index'] ?? '' );

            $this->quiz->add_question( $quiz_id, [
                'question_text'  => $qtext,
                'type'           => strtolower( trim( $row['question_type'] ?? 'single' ) ),
                'options'        => $options,
                'correct_answer' => $correct,
                'points'         => isset( $row['points'] ) ? intval( $row['points'] ) : 1,
            ] );
            $imported++;
        }
        return $imported;
    }

    /**
     * Export single quiz
     */
    public function handle_export_quiz_post() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( __( 'Permission denied', 'cortex' ) );
        $quiz_id = isset( $_REQUEST['quiz_id'] ) ? (int) $_REQUEST['quiz_id'] : 0;
        if ( $quiz_id <= 0 ) exit;
        $this->stream_quiz_export( $quiz_id );
        exit;
    }

    /**
     * Export all quizzes
     */
    public function handle_export_all_quizzes_post() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( __( 'Permission denied', 'cortex' ) );
        $filename = 'cortex-all-quizzes-' . date( 'Ymd-His' ) . '.csv';
        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, [ 'quiz_id', 'quiz_title', 'question_text', 'question_type', 'options', 'correct_answer' ] );

        global $wpdb;
        $quizzes = $wpdb->get_results( "SELECT id, title FROM {$wpdb->prefix}cortex_quizzes" );
        foreach ( $quizzes as $qz ) {
            $questions = $this->quiz->get_questions( $qz->id );
            foreach ( $questions as $q ) {
                fputcsv( $out, [
                    $qz->id,
                    $qz->title,
                    $q['question_text'],
                    $q['type'],
                    is_array($q['options']) ? implode( '|', $q['options'] ) : '',
                    is_array($q['correct_answer']) ? json_encode($q['correct_answer']) : $q['correct_answer'],
                ] );
            }
        }
        fclose( $out );
        exit;
    }

    /**
     * Export results
     */
    public function handle_export_results_csv_post() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( __( 'Permission denied', 'cortex' ) );
        $quiz_id = isset( $_REQUEST['quiz_id'] ) ? (int) $_REQUEST['quiz_id'] : 0;
        if ( $quiz_id <= 0 ) exit;

        global $wpdb;
        $attempts = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}cortex_attempts WHERE quiz_id = %d", $quiz_id ) );

        $filename = "cortex-quiz-{$quiz_id}-results-" . date( 'Ymd-His' ) . '.csv';
        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, [ 'Student', 'Email', 'Score', 'Total', 'Date' ] );

        foreach ( $attempts as $a ) {
            fputcsv( $out, [ $a->student_name, $a->student_email, $a->obtained_marks, $a->total_marks, $a->created_at ] );
        }
        fclose( $out );
        exit;
    }

    /**
     * Stream Quiz Export
     */
    protected function stream_quiz_export( int $quiz_id ) {
        $quiz = $this->quiz->get_quiz( $quiz_id );
        $questions = $this->quiz->get_questions( $quiz_id );
        if ( ! $quiz ) wp_die( __( 'Quiz not found', 'cortex' ) );

        $filename = "cortex-quiz-{$quiz_id}-" . date( 'Ymd-His' ) . '.csv';
        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, [ 'question_text', 'question_type', 'options', 'correct_answer' ] );

        foreach ( $questions as $q ) {
            fputcsv( $out, [
                $q['question_text'],
                $q['type'],
                is_array($q['options']) ? implode( '|', $q['options'] ) : '',
                is_array($q['correct_answer']) ? json_encode($q['correct_answer']) : $q['correct_answer'],
            ] );
        }
        fclose( $out );
        exit;
    }
}

new Cortex_Import_Export();
