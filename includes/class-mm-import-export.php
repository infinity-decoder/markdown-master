<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * MM_Import_Export
 * Robust import/export of quizzes, questions and results.
 * - Handles CSV/XLSX import/export using PhpSpreadsheet when available.
 * - Handles PDF export using Dompdf when available; otherwise provides printable HTML.
 * - Hooks into admin UI (Quizzes screen) to show Import box and handle Export links.
 *
 * This class is careful to work even when libraries are missing (degrades to CSV and printable HTML).
 */

if ( ! class_exists( 'MM_Quiz' ) ) {
    require_once dirname( __FILE__ ) . '/class-mm-quiz.php';
}

class MM_Import_Export {

    /** @var MM_Quiz */
    protected $quiz;

    /** @var string */
    protected $upload_dir;

    /** @var bool */
    protected $has_spreadsheet = false;

    /** @var bool */
    protected $has_dompdf = false;

    public function __construct() {
        $this->quiz = new MM_Quiz();

        // Prepare upload dir
        $wp_up = wp_upload_dir();
        $this->upload_dir = trailingslashit( $wp_up['basedir'] ) . 'markdown-master/';
        if ( ! file_exists( $this->upload_dir ) ) {
            wp_mkdir_p( $this->upload_dir );
        }

        // Try to load Composer autoloader (preferred)
        $autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
        if ( file_exists( $autoload ) ) {
            require_once $autoload;
        }

        // Detect libraries
        $this->has_spreadsheet = class_exists( '\PhpOffice\PhpSpreadsheet\Spreadsheet' );
        $this->has_dompdf      = class_exists( '\Dompdf\Dompdf' );

        // Admin routes (no need to touch admin file)
        add_action( 'admin_init', [ $this, 'maybe_route_from_quizzes_page' ] );

        // Admin-post handlers for explicit actions
        add_action( 'admin_post_mm_import_questions', [ $this, 'handle_import_post' ] );
        add_action( 'admin_post_mm_export_quiz', [ $this, 'handle_export_quiz_post' ] );
        add_action( 'admin_post_mm_export_all_quizzes', [ $this, 'handle_export_all_quizzes_post' ] );
        add_action( 'admin_post_mm_export_results_xlsx', [ $this, 'handle_export_results_xlsx_post' ] );

        // Show Import box on Quizzes admin screen
        add_action( 'admin_notices', [ $this, 'render_quizzes_import_box' ] );
    }

    /**
     * If user clicks "Export" in Quizzes table (page=mm_quizzes&action=export&id=...), route it here
     * so we can produce CSV/XLSX/PDF.
     */
    public function maybe_route_from_quizzes_page() {
        if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $page   = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
        $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
        $id     = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

        if ( $page === 'mm_quizzes' && $action === 'export' && $id > 0 ) {
            // format can be csv|xlsx|pdf (default csv)
            $format = isset( $_GET['format'] ) ? sanitize_key( $_GET['format'] ) : 'csv';
            $this->stream_quiz_export( $id, $format );
            exit;
        }
    }

    /**
     * Render a compact “Import Questions” box on the Quizzes screen.
     */
    public function render_quizzes_import_box() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== 'toplevel_page_mm_quizzes' ) {
            return;
        }

        global $wpdb;
        $quizzes = $wpdb->get_results( "SELECT id, title FROM {$wpdb->prefix}mm_quizzes ORDER BY id DESC" );

        ?>
        <div class="notice notice-info" style="padding:16px;">
            <h2 style="margin-top:0;"><?php esc_html_e( 'Import Questions (CSV/XLSX)', 'markdown-master' ); ?></h2>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field( 'mm_import_questions', 'mm_import_questions_nonce' ); ?>
                <input type="hidden" name="action" value="mm_import_questions"/>
                <p>
                    <label><strong><?php esc_html_e( 'Choose file', 'markdown-master' ); ?>:</strong></label>
                    <input type="file" name="mm_import_file" accept=".csv,.xlsx" required>
                </p>
                <p>
                    <label><strong><?php esc_html_e( 'Import to quiz', 'markdown-master' ); ?>:</strong></label>
                    <select name="quiz_id">
                        <option value="0"><?php esc_html_e( '— Create new quiz —', 'markdown-master' ); ?></option>
                        <?php foreach ( (array) $quizzes as $q ): ?>
                            <option value="<?php echo esc_attr( (int) $q->id ); ?>"><?php echo esc_html( $q->title . ' (#' . $q->id . ')' ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <div id="mm-new-quiz-fields">
                    <p>
                        <label><strong><?php esc_html_e( 'New Quiz Title', 'markdown-master' ); ?>:</strong></label>
                        <input type="text" name="new_quiz_title" class="regular-text">
                    </p>
                    <p>
                        <label><strong><?php esc_html_e( 'New Quiz Description', 'markdown-master' ); ?>:</strong></label>
                        <textarea name="new_quiz_description" rows="3" class="large-text"></textarea>
                    </p>
                </div>
                <p class="description">
                    <?php
                    esc_html_e( 'CSV headers supported: quiz_title, quiz_description, question_text (or question), question_type, option_1..option_8 (or options as pipe-separated), correct_option_index (1-based) or correct_answer (text or JSON array).', 'markdown-master' );
                    ?>
                </p>
                <p>
                    <button class="button button-primary" type="submit"><?php esc_html_e( 'Import', 'markdown-master' ); ?></button>
                    <span style="margin-left:12px;">
                        <?php echo $this->has_spreadsheet ? esc_html__( 'Excel import enabled.', 'markdown-master' ) : esc_html__( 'PhpSpreadsheet not found – CSV only.', 'markdown-master' ); ?>
                    </span>
                </p>
            </form>
            <script>
                (function(){
                    const sel = document.querySelector('select[name="quiz_id"]');
                    const block = document.getElementById('mm-new-quiz-fields');
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
     * Admin-post: Import handler
     */
    public function handle_import_post() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permission denied', 'markdown-master' ) );
        }
        check_admin_referer( 'mm_import_questions', 'mm_import_questions_nonce' );

        if ( empty( $_FILES['mm_import_file']['tmp_name'] ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=mm_quizzes&mm_msg=' . urlencode( __( 'No file uploaded', 'markdown-master' ) ) ) );
            exit;
        }

        $tmp  = $_FILES['mm_import_file']['tmp_name'];
        $name = $_FILES['mm_import_file']['name'];
        $ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );

        $quiz_id = isset( $_POST['quiz_id'] ) ? (int) $_POST['quiz_id'] : 0;

        // Create quiz if needed
        if ( $quiz_id <= 0 ) {
            $title = isset( $_POST['new_quiz_title'] ) ? sanitize_text_field( wp_unslash( $_POST['new_quiz_title'] ) ) : '';
            $desc  = isset( $_POST['new_quiz_description'] ) ? wp_kses_post( wp_unslash( $_POST['new_quiz_description'] ) ) : '';

            if ( $title === '' ) {
                $title = 'Imported Quiz ' . date( 'Y-m-d H:i' );
            }

            $quiz_id = $this->quiz->create_quiz( [
                'title'       => $title,
                'description' => $desc,
                'settings'    => [],
                'shuffle'     => 0,
                'time_limit'  => 0,
                'attempts_allowed' => 0,
                'show_answers'=> 1,
            ] );
        }

        // Read rows
        try {
            $rows = [];
            if ( $ext === 'csv' ) {
                $rows = $this->read_csv_rows( $tmp );
            } elseif ( $ext === 'xlsx' ) {
                if ( ! $this->has_spreadsheet ) {
                    wp_safe_redirect( admin_url( 'admin.php?page=mm_quizzes&mm_msg=' . urlencode( __( 'PhpSpreadsheet not installed – import CSV instead.', 'markdown-master' ) ) ) );
                    exit;
                }
                $rows = $this->read_xlsx_rows( $tmp );
            } else {
                wp_safe_redirect( admin_url( 'admin.php?page=mm_quizzes&mm_msg=' . urlencode( __( 'Unsupported file type. Use CSV or XLSX.', 'markdown-master' ) ) ) );
                exit;
            }

            $count = $this->import_rows_into_quiz( $quiz_id, $rows );

            wp_safe_redirect( admin_url( 'admin.php?page=mm_quizzes&mm_msg=' . urlencode( sprintf( __( 'Imported %d questions into quiz #%d', 'markdown-master' ), $count, $quiz_id ) ) ) );
            exit;

        } catch ( Exception $e ) {
            wp_die( esc_html__( 'Import failed: ', 'markdown-master' ) . esc_html( $e->getMessage() ) );
        }
    }

    /**
     * Read CSV to array-of-assoc
     */
    protected function read_csv_rows( $file_path ) {
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
            $row = array_map( 'trim', $row );
            $assoc = [];
            foreach ( $header as $i => $key ) {
                $assoc[ $key ] = $row[ $i ] ?? '';
            }
            $rows[] = $assoc;
        }
        fclose( $fh );
        return $rows;
    }

    /**
     * Read XLSX to array-of-assoc (PhpSpreadsheet)
     */
    protected function read_xlsx_rows( $file_path ) {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile( $file_path );
        $spreadsheet = $reader->load( $file_path );
        $sheet = $spreadsheet->getActiveSheet();
        $matrix = $sheet->toArray( null, true, true, true );
        if ( empty( $matrix ) ) {
            return [];
        }
        // first row header
        $header = array_shift( $matrix );
        $header = array_map( function( $v ){ return strtolower( trim( (string) $v ) ); }, $header );

        $rows = [];
        foreach ( $matrix as $row ) {
            $assoc = [];
            $i = 0;
            foreach ( $row as $cell ) {
                $key = $header[ array_keys( $header )[ $i ] ] ?? 'col' . $i;
                $assoc[ $key ] = is_null( $cell ) ? '' : (string) $cell;
                $i++;
            }
            $rows[] = $assoc;
        }
        return $rows;
    }

    /**
     * Import rows into given quiz.
     * Accepts flexible headers:
     * - question_text OR question
     * - question_type (mcq/single, multiple, text)
     * - options (pipe-separated) OR option_1..option_8
     * - correct_option_index (1-based) OR correct_answer (text or JSON array)
     */
    protected function import_rows_into_quiz( $quiz_id, array $rows ) : int {
        $imported = 0;

        foreach ( $rows as $row ) {
            // Normalize names
            $qtext = $row['question_text'] ?? ( $row['question'] ?? '' );
            $qtext = trim( (string) $qtext );

            if ( $qtext === '' ) {
                continue;
            }

            $type_raw = strtolower( trim( (string) ( $row['question_type'] ?? '' ) ) );
            $type = in_array( $type_raw, [ 'multiple', 'checkbox', 'multi' ], true ) ? 'multiple'
                  : ( in_array( $type_raw, [ 'text', 'short', 'open' ], true ) ? 'text' : 'single' );

            // Options
            $options = [];
            if ( ! empty( $row['options'] ) ) {
                $options = array_values( array_filter( array_map( 'trim', explode( '|', $row['options'] ) ), 'strlen' ) );
            } else {
                for ( $i = 1; $i <= 8; $i++ ) {
                    $key = 'option_' . $i;
                    if ( isset( $row[ $key ] ) && $row[ $key ] !== '' ) {
                        $options[] = (string) $row[ $key ];
                    }
                }
            }

            // Correct answer
            $correct = null;
            if ( isset( $row['correct_option_index'] ) && $row['correct_option_index'] !== '' ) {
                $idx = (int) $row['correct_option_index'];
                if ( $idx > 0 ) {
                    $correct = $type === 'multiple' ? [ $idx - 1 ] : ( $idx - 1 );
                }
            } elseif ( isset( $row['correct_answer'] ) && $row['correct_answer'] !== '' ) {
                $raw = trim( (string) $row['correct_answer'] );
                // Accept JSON array, comma-separated, pipe-separated, or exact text
                if ( $this->looks_like_json_array( $raw ) ) {
                    $arr = json_decode( $raw, true );
                    $indices = [];
                    foreach ( (array) $arr as $ansText ) {
                        $pos = array_search( (string) $ansText, $options, true );
                        if ( $pos !== false ) {
                            $indices[] = (int) $pos;
                        }
                    }
                    $correct = $type === 'multiple' ? $indices : ( $indices[0] ?? null );
                } elseif ( strpos( $raw, ',' ) !== false || strpos( $raw, '|' ) !== false ) {
                    $parts = preg_split( '/[|,]/', $raw );
                    $indices = [];
                    foreach ( $parts as $p ) {
                        $p = trim( $p );
                        if ( $p === '' ) continue;
                        if ( is_numeric( $p ) ) {
                            $indices[] = max( 0, (int) $p - 1 );
                        } else {
                            $pos = array_search( $p, $options, true );
                            if ( $pos !== false ) $indices[] = (int) $pos;
                        }
                    }
                    $correct = $type === 'multiple' ? array_values( array_unique( $indices ) ) : ( $indices[0] ?? null );
                } else {
                    // single text; try to match to options or keep as text (for text questions)
                    if ( $type === 'text' ) {
                        $correct = $raw;
                    } else {
                        $pos = array_search( $raw, $options, true );
                        if ( $pos !== false ) $correct = (int) $pos;
                    }
                }
            }

            // Insert question
            $q_id = $this->quiz->add_question( $quiz_id, [
                'question_text'  => $qtext,
                'type'           => $type,
                'options'        => $options,
                'correct_answer' => $correct,
                'image'          => '',
                'points'         => 1,
            ] );

            // (Optional) add separate answer option rows if your model supports it
            if ( method_exists( $this->quiz, 'add_answer_option' ) && ! empty( $options ) ) {
                foreach ( $options as $i => $opt ) {
                    $is_correct = 0;
                    if ( $type === 'multiple' && is_array( $correct ) ) {
                        $is_correct = in_array( $i, $correct, true ) ? 1 : 0;
                    } elseif ( is_int( $correct ) ) {
                        $is_correct = ( $i === $correct ) ? 1 : 0;
                    }
                    $this->quiz->add_answer_option( $q_id, $opt, '', $is_correct );
                }
            }

            // If options are stored separately, also persist in question (already done above).
            if ( method_exists( $this->quiz, 'update_question' ) && ! empty( $options ) ) {
                $this->quiz->update_question( $q_id, [ 'options' => $options ] );
            }

            $imported++;
        }

        return $imported;
    }

    protected function looks_like_json_array( $str ) : bool {
        $s = trim( $str );
        return strlen( $s ) >= 2 && $s[0] === '[' && substr( $s, -1 ) === ']';
    }

    /**
     * Admin-post: Export a single quiz (csv/xlsx/pdf) when posting to admin-post.php?action=mm_export_quiz
     */
    public function handle_export_quiz_post() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permission denied', 'markdown-master' ) );
        }
        $quiz_id = isset( $_REQUEST['quiz_id'] ) ? (int) $_REQUEST['quiz_id'] : 0;
        $format  = isset( $_REQUEST['format'] ) ? sanitize_key( $_REQUEST['format'] ) : 'csv';
        if ( $quiz_id <= 0 ) {
            wp_safe_redirect( admin_url( 'admin.php?page=mm_quizzes&mm_msg=' . urlencode( __( 'Invalid quiz id', 'markdown-master' ) ) ) );
            exit;
        }
        $this->stream_quiz_export( $quiz_id, $format );
        exit;
    }

    /**
     * Admin-post: Export all quizzes to CSV (flat)
     */
    public function handle_export_all_quizzes_post() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permission denied', 'markdown-master' ) );
        }
        $filepath = $this->export_all_quizzes_csv_file();
        $this->stream_file_download( $filepath, 'text/csv' );
        exit;
    }

    /**
     * Admin-post: Export results (attempts) to XLSX (if available) else CSV.
     * action=mm_export_results_xlsx&quiz_id=123
     */
    public function handle_export_results_xlsx_post() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permission denied', 'markdown-master' ) );
        }
        $quiz_id = isset( $_REQUEST['quiz_id'] ) ? (int) $_REQUEST['quiz_id'] : 0;
        if ( $quiz_id <= 0 ) {
            wp_safe_redirect( admin_url( 'admin.php?page=mm_results&mm_msg=' . urlencode( __( 'Invalid quiz id', 'markdown-master' ) ) ) );
            exit;
        }
        $filepath = $this->export_results_for_quiz_file( $quiz_id );
        $mime = ( substr( $filepath, -5 ) === '.xlsx' ) ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv';
        $this->stream_file_download( $filepath, $mime );
        exit;
    }

    /**
     * Core: stream one quiz export.
     */
    protected function stream_quiz_export( int $quiz_id, string $format = 'csv' ) {
        $quiz = $this->quiz->get_quiz( $quiz_id, true );
        if ( ! $quiz ) {
            wp_die( __( 'Quiz not found', 'markdown-master' ) );
        }

        $questions = isset( $quiz['questions'] ) ? $quiz['questions'] : $this->quiz->get_questions_by_quiz( $quiz_id );

        if ( $format === 'xlsx' ) {
            if ( $this->has_spreadsheet ) {
                $filepath = $this->build_quiz_xlsx( $quiz, $questions );
                $this->stream_file_download( $filepath, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
                return;
            }
            // Fallback to CSV
            $format = 'csv';
        }

        if ( $format === 'pdf' ) {
            if ( $this->has_dompdf ) {
                $this->stream_quiz_pdf( $quiz, $questions );
                return;
            }
            // Fallback: printable HTML
            $this->render_quiz_printable_html( $quiz, $questions );
            return;
        }

        // Default CSV
        $this->stream_quiz_csv( $quiz, $questions );
    }

    /**
     * Build and stream CSV
     */
    protected function stream_quiz_csv( $quiz, $questions ) {
        $filename = 'mm-quiz-' . (int) $quiz['id'] . '-' . date( 'Ymd-His' ) . '.csv';
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, [ 'quiz_title', 'quiz_description', 'question_text', 'question_type', 'options', 'correct_answer' ] );

        foreach ( (array) $questions as $q ) {
            $opts = isset( $q['options'] ) ? (array) maybe_unserialize( $q['options'] ) : ( isset( $q->options ) ? (array) maybe_unserialize( $q->options ) : [] );
            $correct = isset( $q['correct_answer'] ) ? $q['correct_answer'] : ( $q->correct_answer ?? null );
            if ( is_array( $correct ) || is_object( $correct ) ) {
                $correct = wp_json_encode( $correct );
            }
            $type = isset( $q['type'] ) ? $q['type'] : ( $q->type ?? 'single' );
            $question_text = isset( $q['question_text'] ) ? $q['question_text'] : ( $q->question_text ?? ( $q->question ?? '' ) );
            fputcsv( $out, [
                $quiz['title'],
                $quiz['description'] ?? '',
                $question_text,
                $type,
                implode( '|', $opts ),
                (string) $correct,
            ] );
        }
        fclose( $out );
        exit;
    }

    /**
     * Build XLSX to a temp file (PhpSpreadsheet)
     */
    protected function build_quiz_xlsx( $quiz, $questions ) : string {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray( [ [ 'Quiz Title', $quiz['title'] ], [ 'Description', $quiz['description'] ?? '' ] ], null, 'A1' );
        $sheet->setCellValue( 'A4', 'Question' );
        $sheet->setCellValue( 'B4', 'Type' );
        $sheet->setCellValue( 'C4', 'Options' );
        $sheet->setCellValue( 'D4', 'Correct Answer' );

        $row = 5;
        foreach ( (array) $questions as $q ) {
            $opts = isset( $q['options'] ) ? (array) maybe_unserialize( $q['options'] ) : ( isset( $q->options ) ? (array) maybe_unserialize( $q->options ) : [] );
            $correct = isset( $q['correct_answer'] ) ? $q['correct_answer'] : ( $q->correct_answer ?? null );
            $type = isset( $q['type'] ) ? $q['type'] : ( $q->type ?? 'single' );
            $question_text = isset( $q['question_text'] ) ? $q['question_text'] : ( $q->question_text ?? ( $q->question ?? '' ) );

            if ( is_array( $correct ) || is_object( $correct ) ) {
                $correct = wp_json_encode( $correct );
            }

            $sheet->setCellValue( "A{$row}", $question_text );
            $sheet->setCellValue( "B{$row}", $type );
            $sheet->setCellValue( "C{$row}", implode( '|', $opts ) );
            $sheet->setCellValue( "D{$row}", (string) $correct );
            $row++;
        }

        $filename = 'mm-quiz-' . (int) $quiz['id'] . '-' . date( 'Ymd-His' ) . '.xlsx';
        $filepath = $this->upload_dir . $filename;
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet );
        $writer->save( $filepath );
        return $filepath;
    }

    /**
     * Stream PDF (Dompdf)
     */
    protected function stream_quiz_pdf( $quiz, $questions ) {
        $html = $this->quiz_to_html( $quiz, $questions, true );

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml( $html );
        $dompdf->setPaper( 'A4', 'portrait' );
        $dompdf->render();
        $dompdf->stream( 'mm-quiz-' . (int) $quiz['id'] . '.pdf' );
        exit;
    }

    /**
     * Printable HTML fallback when Dompdf is missing.
     */
    protected function render_quiz_printable_html( $quiz, $questions ) {
        $html = $this->quiz_to_html( $quiz, $questions, false );
        header( 'Content-Type: text/html; charset=utf-8' );
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    protected function quiz_to_html( $quiz, $questions, $pdf_mode = false ) : string {
        ob_start();
        ?>
        <html>
        <head>
            <meta charset="utf-8">
            <title><?php echo esc_html( $quiz['title'] ); ?></title>
            <style>
                body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 14px; }
                h1 { margin-bottom: 8px; }
                .q { margin: 12px 0; }
                ul { margin: 4px 0 8px 20px; }
                .small { color:#666; font-size:12px; }
            </style>
        </head>
        <body>
            <h1><?php echo esc_html( $quiz['title'] ); ?></h1>
            <?php if ( ! empty( $quiz['description'] ) ): ?>
                <p class="small"><?php echo esc_html( $quiz['description'] ); ?></p>
            <?php endif; ?>
            <hr>
            <?php foreach ( (array) $questions as $i => $q ):
                $question_text = isset( $q['question_text'] ) ? $q['question_text'] : ( $q->question_text ?? ( $q->question ?? '' ) );
                $opts = isset( $q['options'] ) ? (array) maybe_unserialize( $q['options'] ) : ( isset( $q->options ) ? (array) maybe_unserialize( $q->options ) : [] );
                $correct = isset( $q['correct_answer'] ) ? $q['correct_answer'] : ( $q->correct_answer ?? null );
                ?>
                <div class="q">
                    <div><strong><?php echo ( $i + 1 ) . '. '; ?></strong><?php echo esc_html( $question_text ); ?></div>
                    <?php if ( ! empty( $opts ) ): ?>
                        <ul>
                            <?php foreach ( $opts as $opt ): ?>
                                <li><?php echo esc_html( (string) $opt ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if ( $pdf_mode ): ?>
                        <div class="small"><em><?php esc_html_e( 'Correct:', 'markdown-master' ); ?></em>
                            <?php
                            if ( is_array( $correct ) ) {
                                echo esc_html( implode( ', ', $correct ) );
                            } else {
                                echo esc_html( (string) $correct );
                            }
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </body>
        </html>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Export ALL quizzes to a single CSV file on disk and return the path.
     * (Flat structure for archival)
     */
    protected function export_all_quizzes_csv_file() {
        $filename = 'mm-quizzes-all-' . date( 'Ymd-His' ) . '.csv';
        $filepath = $this->upload_dir . $filename;

        $fh = fopen( $filepath, 'w' );
        if ( ! $fh ) {
            throw new RuntimeException( 'Unable to create export file' );
        }
        fputcsv( $fh, [ 'quiz_id', 'quiz_title', 'question_id', 'question_text', 'question_type', 'options', 'correct_answer' ] );

        global $wpdb;
        $quizzes = $wpdb->get_results( "SELECT id, title FROM {$wpdb->prefix}mm_quizzes ORDER BY id DESC" );
        foreach ( (array) $quizzes as $qz ) {
            $quiz = $this->quiz->get_quiz( (int) $qz->id, true );
            if ( ! $quiz ) {
                continue;
            }
            $questions = isset( $quiz['questions'] ) ? $quiz['questions'] : [];
            foreach ( $questions as $q ) {
                $opts = isset( $q['options'] ) ? (array) maybe_unserialize( $q['options'] ) : [];
                $correct = isset( $q['correct_answer'] ) ? $q['correct_answer'] : null;
                if ( is_array( $correct ) ) {
                    $correct = wp_json_encode( $correct );
                }
                fputcsv( $fh, [
                    (int) $qz->id,
                    $quiz['title'],
                    (int) ( $q['id'] ?? 0 ),
                    $q['question_text'] ?? '',
                    $q['type'] ?? 'single',
                    implode( '|', $opts ),
                    (string) $correct,
                ] );
            }
        }
        fclose( $fh );
        return $filepath;
    }

    /**
     * Export results for a quiz to XLSX (if library present) else CSV — return path to file.
     */
    public function export_results_for_quiz_file( int $quiz_id ) : string {
        global $wpdb;
        $attempts_table = $wpdb->prefix . 'mm_attempts';

        $attempts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$attempts_table} WHERE quiz_id = %d ORDER BY created_at DESC",
                $quiz_id
            )
        );

        $rows = [];
        foreach ( (array) $attempts as $a ) {
            $rows[] = [
                'Student Name'  => $a->student_name,
                'Roll No'       => $a->student_roll,
                'Class'         => $a->student_class,
                'Section'       => $a->student_section,
                'School'        => $a->student_school,
                'Obtained'      => $a->obtained_marks,
                'Total'         => $a->total_marks,
                'Date'          => $a->created_at,
            ];
        }

        if ( $this->has_spreadsheet ) {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            if ( ! empty( $rows ) ) {
                $sheet->fromArray( array_keys( $rows[0] ), null, 'A1' );
                $r = 2;
                foreach ( $rows as $row ) {
                    $sheet->fromArray( array_values( $row ), null, 'A' . $r );
                    $r++;
                }
            } else {
                $sheet->fromArray( [ 'No attempts yet.' ], null, 'A1' );
            }
            $filename = 'mm-quiz-results-' . $quiz_id . '-' . date( 'Ymd-His' ) . '.xlsx';
            $filepath = $this->upload_dir . $filename;
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet );
            $writer->save( $filepath );
            return $filepath;
        }

        // CSV fallback
        $filename = 'mm-quiz-results-' . $quiz_id . '-' . date( 'Ymd-His' ) . '.csv';
        $filepath = $this->upload_dir . $filename;
        $fh = fopen( $filepath, 'w' );
        if ( ! $fh ) {
            throw new RuntimeException( 'Unable to create results file' );
        }
        if ( ! empty( $rows ) ) {
            fputcsv( $fh, array_keys( $rows[0] ) );
            foreach ( $rows as $row ) {
                fputcsv( $fh, array_values( $row ) );
            }
        } else {
            fputcsv( $fh, [ 'No attempts yet.' ] );
        }
        fclose( $fh );
        return $filepath;
    }

    /**
     * Stream a file path to browser and delete after sending.
     */
    protected function stream_file_download( string $filepath, string $content_type ) {
        if ( ! file_exists( $filepath ) ) {
            wp_die( __( 'Export file missing', 'markdown-master' ) );
        }
        header( 'Content-Type: ' . $content_type );
        header( 'Content-Disposition: attachment; filename=' . basename( $filepath ) );
        readfile( $filepath );
        @unlink( $filepath );
        exit;
    }
}

new MM_Import_Export();
