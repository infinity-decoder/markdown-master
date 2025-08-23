<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Frontend class for Markdown Master
 * - Registers [mm_quiz id="123"]
 * - Renders student info form + questions
 * - Handles submission, scoring, DB write
 * - Displays results and (optionally) a PDF link (admin-only export)
 *
 * Place this file at: markdown-master/includes/class-mm-frontend.php
 */

class MM_Frontend {

    public function __construct() {
        // Intentionally empty — loader will call init_hooks()
    }

    /**
     * Required by loader. Register all public hooks here.
     */
    public function init_hooks() {
        add_shortcode( 'mm_quiz', [ $this, 'render_quiz_shortcode' ] );
        // Optional: simple CSS for forms
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function enqueue_assets() {
        $handle = 'mm-frontend';
        $css    = '
        .mm-quiz-wrap{max-width:840px;margin:20px auto;padding:20px;border:1px solid #e5e7eb;border-radius:12px;background:#fff}
        .mm-field{margin-bottom:12px}
        .mm-field label{display:block;font-weight:600;margin-bottom:6px}
        .mm-input, .mm-select, .mm-textarea{width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px}
        .mm-question{padding:12px;border:1px solid #e5e7eb;border-radius:10px;margin-bottom:12px}
        .mm-question h4{margin:0 0 8px 0}
        .mm-options ul{list-style:none;padding:0;margin:0}
        .mm-options li{margin:6px 0}
        .mm-btn{display:inline-block;background:#2563eb;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none;border:none;cursor:pointer}
        .mm-btn:disabled{opacity:.5;cursor:not-allowed}
        .mm-result{padding:16px;border-radius:10px;background:#f0fdf4;border:1px solid #bbf7d0;margin-top:16px}
        .mm-answer-row{padding:10px;border:1px dashed #e5e7eb;border-radius:8px;margin:8px 0}
        ';
        // Register and inline
        wp_register_style( $handle, false );
        wp_enqueue_style( $handle );
        wp_add_inline_style( $handle, $css );
    }

    /**
     * Shortcode handler: [mm_quiz id="123"]
     */
    public function render_quiz_shortcode( $atts ) {
        $atts = shortcode_atts( [ 'id' => 0 ], $atts, 'mm_quiz' );
        $quiz_id = intval( $atts['id'] );
        if ( $quiz_id <= 0 ) {
            return '<div class="mm-quiz-wrap"><p>' . esc_html__( 'Invalid quiz ID.', 'markdown-master' ) . '</p></div>';
        }

        // Load model if available
        if ( ! class_exists( 'MM_Quiz' ) ) {
            $model_file = dirname( __FILE__ ) . '/class-mm-quiz.php';
            if ( file_exists( $model_file ) ) {
                require_once $model_file;
            }
        }

        $quiz = $this->load_quiz( $quiz_id );
        if ( ! $quiz ) {
            return '<div class="mm-quiz-wrap"><p>' . esc_html__( 'Quiz not found.', 'markdown-master' ) . '</p></div>';
        }

        // Submission?
        if ( isset( $_POST['mm_quiz_submit'] ) && isset( $_POST['mm_quiz_id'] ) && intval( $_POST['mm_quiz_id'] ) === $quiz_id ) {
            return $this->handle_submission_and_render_result( $quiz );
        }

        // Render attempt form
        return $this->render_attempt_form( $quiz );
    }

    /**
     * Load quiz + questions (via model if present, fallback to DB)
     */
    protected function load_quiz( $quiz_id ) {
        $quiz_id = intval( $quiz_id );

        if ( class_exists( 'MM_Quiz' ) ) {
            try {
                $model = new MM_Quiz();
                $quiz  = $model->get_quiz( $quiz_id, true ); // include questions if model supports
                if ( $quiz && isset( $quiz['id'] ) ) {
                    return $quiz;
                }
            } catch ( \Throwable $e ) {
                // fall back to raw DB
            }
        }

        global $wpdb;
        $quiz_table      = $wpdb->prefix . 'mm_quizzes';
        $questions_table = $wpdb->prefix . 'mm_questions';

        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$quiz_table} WHERE id = %d", $quiz_id ), ARRAY_A );
        if ( ! $row ) {
            return null;
        }

        // Parse settings (serialized or json)
        $settings = [];
        if ( isset( $row['settings'] ) ) {
            $maybe = is_string( $row['settings'] ) ? maybe_unserialize( $row['settings'] ) : $row['settings'];
            if ( is_string( $maybe ) ) {
                $maybe = json_decode( $maybe, true );
            }
            if ( is_array( $maybe ) ) {
                $settings = $maybe;
            }
        }
        // Legacy columns merge
        foreach ( [ 'shuffle', 'time_limit', 'attempts_allowed', 'show_answers' ] as $k ) {
            if ( isset( $row[ $k ] ) && $row[ $k ] !== '' ) {
                $settings[ $k ] = is_numeric( $row[ $k ] ) ? intval( $row[ $k ] ) : $row[ $k ];
            }
        }

        $q_sql = $wpdb->prepare( "SELECT * FROM {$questions_table} WHERE quiz_id = %d ORDER BY id ASC", $quiz_id );
        $questions = $wpdb->get_results( $q_sql, ARRAY_A );
        if ( ! is_array( $questions ) ) {
            $questions = [];
        }

        // Normalize questions
        foreach ( $questions as &$q ) {
            // options may be JSON or serialized
            if ( isset( $q['options'] ) && is_string( $q['options'] ) ) {
                $opts = json_decode( $q['options'], true );
                if ( ! is_array( $opts ) ) {
                    $opts = maybe_unserialize( $q['options'] );
                }
                if ( ! is_array( $opts ) ) {
                    $opts = [];
                }
                $q['options'] = $opts;
            } elseif ( ! isset( $q['options'] ) ) {
                $q['options'] = [];
            }

            if ( isset( $q['correct_answer'] ) && is_string( $q['correct_answer'] ) ) {
                $corr = json_decode( $q['correct_answer'], true );
                if ( $corr === null ) {
                    $corr = maybe_unserialize( $q['correct_answer'] );
                }
                $q['correct_answer'] = $corr;
            }
            if ( empty( $q['type'] ) ) {
                // default to single choice
                $q['type'] = 'single';
            }
        }
        unset( $q );

        return [
            'id'          => intval( $row['id'] ),
            'title'       => $row['title'],
            'description' => $row['description'],
            'settings'    => $settings,
            'questions'   => $questions,
        ];
    }

    protected function get_setting( $quiz, $key, $default = null ) {
        if ( isset( $quiz['settings'][ $key ] ) ) {
            return $quiz['settings'][ $key ];
        }
        return $default;
    }

    protected function render_attempt_form( $quiz ) {
        $quiz_id   = intval( $quiz['id'] );
        $shuffle   = (int) $this->get_setting( $quiz, 'shuffle', 0 );
        $time_limit= (int) $this->get_setting( $quiz, 'time_limit', 0 );

        $questions = $quiz['questions'];
        if ( $shuffle && ! empty( $questions ) ) {
            // Randomize preserving keys
            shuffle( $questions );
        }

        ob_start();
        ?>
        <div class="mm-quiz-wrap">
            <h2><?php echo esc_html( $quiz['title'] ); ?></h2>
            <?php if ( ! empty( $quiz['description'] ) ) : ?>
                <p><?php echo wp_kses_post( $quiz['description'] ); ?></p>
            <?php endif; ?>
            <?php if ( $time_limit > 0 ) : ?>
                <p><em><?php echo esc_html( sprintf( __( 'Time limit: %d minutes', 'markdown-master' ), $time_limit ) ); ?></em></p>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field( 'mm_quiz_submit_' . $quiz_id, 'mm_quiz_nonce' ); ?>
                <input type="hidden" name="mm_quiz_submit" value="1">
                <input type="hidden" name="mm_quiz_id" value="<?php echo esc_attr( $quiz_id ); ?>">

                <div class="mm-field">
                    <label for="mm_student_name"><?php esc_html_e( 'Name', 'markdown-master' ); ?></label>
                    <input class="mm-input" type="text" id="mm_student_name" name="student[name]" required>
                </div>
                <div class="mm-field">
                    <label for="mm_student_class"><?php esc_html_e( 'Class', 'markdown-master' ); ?></label>
                    <input class="mm-input" type="text" id="mm_student_class" name="student[class]" required>
                </div>
                <div class="mm-field">
                    <label for="mm_student_section"><?php esc_html_e( 'Section', 'markdown-master' ); ?></label>
                    <input class="mm-input" type="text" id="mm_student_section" name="student[section]" required>
                </div>
                <div class="mm-field">
                    <label for="mm_student_school"><?php esc_html_e( 'School', 'markdown-master' ); ?></label>
                    <input class="mm-input" type="text" id="mm_student_school" name="student[school]" required>
                </div>
                <div class="mm-field">
                    <label for="mm_student_roll"><?php esc_html_e( 'Roll No (optional)', 'markdown-master' ); ?></label>
                    <input class="mm-input" type="text" id="mm_student_roll" name="student[roll]">
                </div>

                <hr style="margin:16px 0;">

                <?php foreach ( $questions as $idx => $q ) : ?>
                    <div class="mm-question">
                        <h4><?php echo esc_html( ($idx+1) . '. ' . wp_strip_all_tags( $q['question_text'] ?? $q['question'] ?? '' ) ); ?></h4>
                        <div class="mm-options">
                            <?php
                            $qid    = intval( $q['id'] );
                            $type   = $q['type'];
                            $name   = $type === 'multiple' ? "answers[$qid][]" : "answers[$qid]";
                            $opts   = is_array( $q['options'] ) ? $q['options'] : [];
                            if ( in_array( $type, [ 'single', 'multiple' ], true ) ) {
                                echo '<ul>';
                                foreach ( $opts as $opt_idx => $opt_text ) {
                                    $field_id = 'q' . $qid . '_' . $opt_idx;
                                    echo '<li>';
                                    echo '<label for="' . esc_attr( $field_id ) . '">';
                                    if ( $type === 'single' ) {
                                        echo '<input type="radio" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $opt_text ) . '"> ';
                                    } else {
                                        echo '<input type="checkbox" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $opt_text ) . '"> ';
                                    }
                                    echo esc_html( $opt_text );
                                    echo '</label>';
                                    echo '</li>';
                                }
                                echo '</ul>';
                            } else {
                                $field_id = 'q' . $qid . '_text';
                                echo '<input class="mm-input" type="text" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '">';
                            }
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <button class="mm-btn" type="submit"><?php esc_html_e( 'Submit', 'markdown-master' ); ?></button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    protected function handle_submission_and_render_result( $quiz ) {
        $quiz_id = intval( $quiz['id'] );

        if ( ! isset( $_POST['mm_quiz_nonce'] ) || ! wp_verify_nonce( $_POST['mm_quiz_nonce'], 'mm_quiz_submit_' . $quiz_id ) ) {
            return '<div class="mm-quiz-wrap"><p>' . esc_html__( 'Security check failed. Please reload and try again.', 'markdown-master' ) . '</p></div>';
        }

        $student = isset( $_POST['student'] ) && is_array( $_POST['student'] ) ? $_POST['student'] : [];
        $answers = isset( $_POST['answers'] ) ? $_POST['answers'] : [];

        $student_name    = sanitize_text_field( $student['name'] ?? '' );
        $student_class   = sanitize_text_field( $student['class'] ?? '' );
        $student_section = sanitize_text_field( $student['section'] ?? '' );
        $student_school  = sanitize_text_field( $student['school'] ?? '' );
        $student_roll    = sanitize_text_field( $student['roll'] ?? '' );

        if ( $student_name === '' || $student_class === '' || $student_section === '' || $student_school === '' ) {
            return '<div class="mm-quiz-wrap"><p>' . esc_html__( 'Please fill all required fields.', 'markdown-master' ) . '</p></div>';
        }

        // Score
        $total = count( $quiz['questions'] );
        $score = 0;
        $per_question_results = []; // [ question_id => [ 'given' => ..., 'correct' => ..., 'is_correct' => 0/1 ] ]

        foreach ( $quiz['questions'] as $q ) {
            $qid   = intval( $q['id'] );
            $type  = $q['type'];
            $given = isset( $answers[ $qid ] ) ? $answers[ $qid ] : null;
            $corr  = $q['correct_answer'];

            $is_correct = 0;

            if ( $type === 'single' ) {
                $given_val = is_array( $given ) ? reset( $given ) : $given;
                $given_val = is_string( $given_val ) ? trim( wp_unslash( $given_val ) ) : '';
                $corr_val  = is_array( $corr ) ? (string) reset( $corr ) : (string) $corr;
                $corr_val  = trim( (string) $corr_val );
                if ( $given_val !== '' && strcasecmp( $given_val, $corr_val ) === 0 ) {
                    $is_correct = 1;
                }
            } elseif ( $type === 'multiple' ) {
                $given_arr = array_map( 'wp_unslash', (array) $given );
                $given_arr = array_map( 'trim', $given_arr );
                $corr_arr  = is_array( $corr ) ? array_map( 'trim', (array) $corr ) : [ (string) $corr ];
                sort( $given_arr );
                sort( $corr_arr );
                if ( $given_arr === $corr_arr && ! empty( $corr_arr ) ) {
                    $is_correct = 1;
                }
            } else { // text
                $given_val = is_array( $given ) ? reset( $given ) : $given;
                $given_val = is_string( $given_val ) ? trim( wp_unslash( $given_val ) ) : '';
                if ( is_array( $corr ) ) {
                    foreach ( $corr as $c ) {
                        if ( strcasecmp( $given_val, trim( (string) $c ) ) === 0 ) {
                            $is_correct = 1; break;
                        }
                    }
                } else {
                    if ( strcasecmp( $given_val, trim( (string) $corr ) ) === 0 && $corr !== null && $corr !== '' ) {
                        $is_correct = 1;
                    }
                }
            }

            if ( $is_correct ) {
                $score++;
            }

            $per_question_results[ $qid ] = [
                'given'      => $given,
                'correct'    => $corr,
                'is_correct' => $is_correct,
            ];
        }

        // Save attempt (model if available else DB)
        $attempt_id = 0;
        if ( class_exists( 'MM_Quiz' ) ) {
            try {
                $model = new MM_Quiz();
                $attempt_id = $model->record_attempt( $quiz_id, [
                    'student_name'    => $student_name,
                    'student_class'   => $student_class,
                    'student_section' => $student_section,
                    'student_school'  => $student_school,
                    'student_roll'    => $student_roll,
                    'obtained_marks'  => $score,
                    'total_marks'     => $total,
                    'answers'         => $per_question_results,
                ] );
            } catch ( \Throwable $e ) {
                $attempt_id = 0;
            }
        }

        if ( ! $attempt_id ) {
            global $wpdb;
            $attempts_table       = $wpdb->prefix . 'mm_attempts';
            $attempt_answers_table= $wpdb->prefix . 'mm_attempt_answers';

            $wpdb->insert(
                $attempts_table,
                [
                    'quiz_id'        => $quiz_id,
                    'student_name'   => $student_name,
                    'student_class'  => $student_class,
                    'student_section'=> $student_section,
                    'student_school' => $student_school,
                    'student_roll'   => $student_roll,
                    'obtained_marks' => $score,
                    'total_marks'    => $total,
                    'created_at'     => current_time( 'mysql' ),
                ],
                [ '%d','%s','%s','%s','%s','%s','%d','%d','%s' ]
            );
            $attempt_id = intval( $wpdb->insert_id );

            if ( $attempt_id > 0 ) {
                foreach ( $per_question_results as $qid => $row ) {
                    $wpdb->insert(
                        $attempt_answers_table,
                        [
                            'attempt_id'  => $attempt_id,
                            'question_id' => intval( $qid ),
                            'answer'      => maybe_serialize( $row['given'] ),
                            'is_correct'  => intval( $row['is_correct'] ),
                        ],
                        [ '%d','%d','%s','%d' ]
                    );
                }
            }
        }

        // Show results
        $show_answers_flag = (int) $this->get_setting( $quiz, 'show_answers', 0 );

        ob_start();
        ?>
        <div class="mm-quiz-wrap">
            <h2><?php echo esc_html( $quiz['title'] ); ?></h2>

            <div class="mm-result">
                <p><strong><?php esc_html_e( 'Thank you!', 'markdown-master' ); ?></strong></p>
                <p><?php echo esc_html( sprintf( __( 'Your score: %d / %d', 'markdown-master' ), $score, $total ) ); ?></p>
            </div>

            <?php if ( $show_answers_flag ) : ?>
                <h3 style="margin-top:16px;"><?php esc_html_e( 'Correct Answers', 'markdown-master' ); ?></h3>
                <?php foreach ( $quiz['questions'] as $idx => $q ) :
                    $qid   = intval( $q['id'] );
                    $given = $per_question_results[ $qid ]['given'] ?? null;
                    $corr  = $per_question_results[ $qid ]['correct'] ?? null;
                    $ok    = (int) ( $per_question_results[ $qid ]['is_correct'] ?? 0 );
                ?>
                    <div class="mm-answer-row">
                        <div><strong><?php echo esc_html( ($idx+1) . '. ' . wp_strip_all_tags( $q['question_text'] ?? $q['question'] ?? '' ) ); ?></strong></div>
                        <div><?php esc_html_e( 'Your answer:', 'markdown-master' ); ?> <?php echo esc_html( is_array( $given ) ? implode( ', ', (array) $given ) : (string) $given ); ?></div>
                        <div><?php esc_html_e( 'Correct answer:', 'markdown-master' ); ?> <?php echo esc_html( is_array( $corr ) ? implode( ', ', (array) $corr ) : (string) $corr ); ?></div>
                        <div><?php echo $ok ? '<span style="color:green;">' . esc_html__( 'Correct', 'markdown-master' ) . '</span>' : '<span style="color:#b91c1c;">' . esc_html__( 'Incorrect', 'markdown-master' ) . '</span>'; ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ( current_user_can( 'manage_options' ) && $attempt_id ) : ?>
                <p style="margin-top:12px;">
                    <a class="mm-btn" href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'page' => 'mm_results', 'action' => 'pdf', 'attempt_id' => $attempt_id ], admin_url( 'admin.php' ) ), 'mm_pdf_' . $attempt_id ) ); ?>" target="_blank">
                        <?php esc_html_e( 'Download PDF (admin)', 'markdown-master' ); ?>
                    </a>
                </p>
            <?php else : ?>
                <p style="margin-top:12px;"><em><?php esc_html_e( 'Ask your teacher/admin for a PDF if needed.', 'markdown-master' ); ?></em></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
