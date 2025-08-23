<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Frontend assets and AJAX handlers for Markdown Master quizzes
 */

class MM_Frontend {

    public function init_hooks() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_public_assets' ] );

        // AJAX handlers
        add_action( 'wp_ajax_mm_submit_attempt', [ $this, 'ajax_submit_attempt' ] );
        add_action( 'wp_ajax_nopriv_mm_submit_attempt', [ $this, 'ajax_submit_attempt' ] );
    }

    /**
     * Enqueue frontend JS/CSS and localize AJAX url + nonce
     */
    public function enqueue_public_assets() {
        $js_path = 'assets/js/mm-public.js';
        $css_path = 'assets/css/mm-public.css';

        // Register assets (use plugin-relative path; __FILE__ is inside includes/)
        wp_register_script( 'mm-public', plugins_url( $js_path, __DIR__ ), array( 'jquery' ), '1.0', true );
        wp_register_style( 'mm-public-css', plugins_url( $css_path, __DIR__ ), array(), '1.0' );

        // Localize script with AJAX endpoint and nonce
        wp_localize_script( 'mm-public', 'mm_public', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'mm_submit_attempt' ),
            'i18n'     => array(
                'submitting' => __( 'Submitting...', 'markdown-master' ),
                'submit_error' => __( 'Failed to submit quiz. Please try again.', 'markdown-master' ),
            ),
        ) );

        wp_enqueue_script( 'mm-public' );
        wp_enqueue_style( 'mm-public-css' );
    }

    /**
     * AJAX: handle quiz attempt submission
     */
    public function ajax_submit_attempt() {
        // Verify nonce
        $nonce_field = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce_field, 'mm_submit_attempt' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'markdown-master' ) ), 403 );
        }

        // Retrieve & sanitize input
        $quiz_id = isset( $_POST['quiz_id'] ) ? intval( $_POST['quiz_id'] ) : 0;
        $student = isset( $_POST['student'] ) && is_array( $_POST['student'] ) ? wp_unslash( $_POST['student'] ) : array();
        $answers = isset( $_POST['answers'] ) && is_array( $_POST['answers'] ) ? wp_unslash( $_POST['answers'] ) : array();

        if ( $quiz_id <= 0 ) {
            wp_send_json_error( array( 'message' => __( 'Invalid quiz.', 'markdown-master' ) ), 400 );
        }

        // Basic student validation: require name
        $student_name = isset( $student['name'] ) ? sanitize_text_field( $student['name'] ) : '';
        if ( empty( $student_name ) ) {
            wp_send_json_error( array( 'message' => __( 'Please enter your name.', 'markdown-master' ) ), 400 );
        }

        // Ensure MM_Quiz model is available
        if ( ! class_exists( 'MM_Quiz' ) ) {
            $quiz_file = __DIR__ . '/class-mm-quiz.php';
            if ( file_exists( $quiz_file ) ) {
                require_once $quiz_file;
            }
        }

        if ( ! class_exists( 'MM_Quiz' ) ) {
            wp_send_json_error( array( 'message' => __( 'Quiz system is not available.', 'markdown-master' ) ), 500 );
        }

        $quiz_model = new MM_Quiz();

        // Sanitize answers minimally here - the model will handle more checks
        $clean_answers = array();
        foreach ( $answers as $q => $val ) {
            // q is question id (string), value may be array (checkbox) or scalar
            $qid = intval( $q );
            if ( is_array( $val ) ) {
                $arr = array();
                foreach ( $val as $it ) {
                    $arr[] = is_scalar( $it ) ? sanitize_text_field( $it ) : wp_json_encode( $it );
                }
                $clean_answers[ $qid ] = $arr;
            } else {
                $clean_answers[ $qid ] = is_scalar( $val ) ? sanitize_text_field( $val ) : wp_json_encode( $val );
            }
        }

        // Prepare student array
        $clean_student = array(
            'name'    => $student_name,
            'roll'    => isset( $student['roll'] ) ? sanitize_text_field( $student['roll'] ) : '',
            'class'   => isset( $student['class'] ) ? sanitize_text_field( $student['class'] ) : '',
            'section' => isset( $student['section'] ) ? sanitize_text_field( $student['section'] ) : '',
            'school'  => isset( $student['school'] ) ? sanitize_text_field( $student['school'] ) : '',
        );

        // Record attempt using MM_Quiz::record_attempt()
        $result = $quiz_model->record_attempt( $quiz_id, $clean_student, $clean_answers );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
        }

        $response = array(
            'attempt_id' => $result['attempt_id'],
            'score'      => $result['score'],
            'total'      => $result['total'],
            'message'    => __( 'Quiz submitted successfully.', 'markdown-master' ),
        );

        /**
         * Optional: generate PDF automatically if the request asked for it and if Dompdf is installed.
         * The frontend can request a PDF by sending `download = 1`.
         */
        $download_requested = isset( $_POST['download'] ) && intval( $_POST['download'] ) === 1;
        if ( $download_requested ) {
            $pdf_url = $this->maybe_generate_attempt_pdf( $result['attempt_id'] );
            if ( $pdf_url ) {
                $response['download_pdf'] = $pdf_url;
            } else {
                // Not fatal — keep returning success without pdf
                $response['download_pdf'] = '';
            }
        }

        wp_send_json_success( $response );
    }

    /**
     * Try to generate a PDF for an attempt using Dompdf (optional).
     * Returns the URL to the generated PDF on success, or false on failure.
     *
     * Requirements: install dompdf via Composer into plugin's vendor directory, or place its autoloader at plugin root vendor/autoload.php
     */
    protected function maybe_generate_attempt_pdf( $attempt_id ) {
        $attempt_id = intval( $attempt_id );
        if ( $attempt_id <= 0 ) {
            return false;
        }

        // Attempt to locate autoloader (adjust if your vendor folder differs)
        $possible_autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
        if ( ! file_exists( $possible_autoload ) ) {
            return false;
        }

        require_once $possible_autoload;

        if ( ! class_exists( 'Dompdf\\Dompdf' ) ) {
            return false;
        }

        // Now prepare HTML for the pdf
        if ( ! class_exists( 'MM_Quiz' ) ) {
            $quiz_file = __DIR__ . '/class-mm-quiz.php';
            if ( file_exists( $quiz_file ) ) {
                require_once $quiz_file;
            }
        }
        if ( ! class_exists( 'MM_Quiz' ) ) {
            return false;
        }

        $quiz_model = new MM_Quiz();
        $attempt = $quiz_model->get_attempt( $attempt_id );
        if ( ! $attempt ) {
            return false;
        }

        $quiz = $quiz_model->get_quiz( intval( $attempt['quiz_id'] ), true );
        if ( ! $quiz ) {
            return false;
        }

        ob_start();
        ?>
        <h1><?php echo esc_html( $quiz['title'] ); ?></h1>
        <p><strong><?php esc_html_e( 'Student:', 'markdown-master' ); ?></strong> <?php echo esc_html( $attempt['student_name'] ); ?></p>
        <p><strong><?php esc_html_e( 'Roll:', 'markdown-master' ); ?></strong> <?php echo esc_html( $attempt['student_roll'] ); ?></p>
        <p><strong><?php esc_html_e( 'Score:', 'markdown-master' ); ?></strong> <?php echo esc_html( $attempt['obtained_marks'] . ' / ' . $attempt['total_marks'] ); ?></p>

        <hr>

        <?php
        $answers = $attempt['answers'];
        foreach ( $quiz['questions'] as $q ) :
            $qid = intval( $q['id'] );
            $q_text = $q['question_text'];
            $correct = maybe_unserialize( $q['correct_answer'] );
            $given = isset( $answers[ $qid ] ) ? $answers[ $qid ] : null;
            ?>
            <div style="margin-bottom:12px;">
                <div><strong><?php echo wp_strip_all_tags( $q_text ); ?></strong></div>
                <div><?php esc_html_e( 'Student Answer:', 'markdown-master' ); ?> <?php echo is_array( $given ) ? esc_html( implode( ', ', (array) $given ) ) : esc_html( (string) $given ); ?></div>
                <div><?php esc_html_e( 'Correct Answer:', 'markdown-master' ); ?> <?php echo is_array( $correct ) ? esc_html( implode( ', ', (array) $correct ) ) : esc_html( (string) $correct ); ?></div>
            </div>
        <?php endforeach; ?>

        <?php
        $html = ob_get_clean();

        // Use Dompdf to generate
        try {
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml( $html );
            $dompdf->setPaper( 'A4', 'portrait' );
            $dompdf->render();
            $output = $dompdf->output();

            $upload_dir = wp_upload_dir();
            $pdf_dir = trailingslashit( $upload_dir['basedir'] ) . 'mm_quiz_pdfs';
            if ( ! file_exists( $pdf_dir ) ) {
                wp_mkdir_p( $pdf_dir );
            }

            $filename = 'mm_attempt_' . $attempt_id . '.pdf';
            $filepath = $pdf_dir . '/' . $filename;
            file_put_contents( $filepath, $output );

            $url = trailingslashit( $upload_dir['baseurl'] ) . 'mm_quiz_pdfs/' . $filename;
            return esc_url_raw( $url );

        } catch ( Exception $e ) {
            return false;
        }
    }
}
