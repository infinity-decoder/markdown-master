<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shortcodes: [mm_quiz id="123"]
 *
 * Renders quiz form on frontend and lets the front-end JS submit attempts.
 * Relies on MM_Quiz model (includes/class-mm-quiz.php) to fetch quizzes & questions.
 */

if ( ! class_exists( 'MM_Shortcodes' ) ) {

    class MM_Shortcodes {

        protected $quiz_model;

        public function __construct() {
            // Ensure MM_Quiz class is available
            if ( ! class_exists( 'MM_Quiz' ) ) {
                $quiz_file = __DIR__ . '/class-mm-quiz.php';
                if ( file_exists( $quiz_file ) ) {
                    require_once $quiz_file;
                }
            }

            $this->quiz_model = class_exists( 'MM_Quiz' ) ? new MM_Quiz() : null;

            add_shortcode( 'mm_quiz', [ $this, 'render_quiz_shortcode' ] );
        }

        /**
         * Render the quiz shortcode
         *
         * Usage: [mm_quiz id="123"]
         */
        public function render_quiz_shortcode( $atts = array() ) {
            $atts = shortcode_atts( array(
                'id' => 0,
            ), $atts, 'mm_quiz' );

            $quiz_id = intval( $atts['id'] );
            if ( $quiz_id <= 0 ) {
                return '<p>' . esc_html__( 'Invalid quiz ID.', 'markdown-master' ) . '</p>';
            }

            if ( ! $this->quiz_model ) {
                return '<p>' . esc_html__( 'Quiz module is unavailable.', 'markdown-master' ) . '</p>';
            }

            $quiz = $this->quiz_model->get_quiz( $quiz_id, true );
            if ( ! $quiz ) {
                return '<p>' . esc_html__( 'Quiz not found.', 'markdown-master' ) . '</p>';
            }

            // Enqueue public assets (script & style) - MM_Frontend also enqueues but ensure present
            if ( function_exists( 'wp_enqueue_script' ) ) {
                // Registering is fine even if done twice, WP handles it.
                $frontend_file = __DIR__ . '/class-mm-frontend.php';
                // Try to let frontend class handle enqueues; if not loaded, just enqueue directly
                wp_enqueue_script( 'mm-public' ); // noop if not registered
                wp_enqueue_style( 'mm-public-css' );
            }

            ob_start();
            ?>
            <div class="mm-quiz-wrap" id="mm-quiz-<?php echo esc_attr( $quiz_id ); ?>">
                <form class="mm-quiz-form" data-quiz-id="<?php echo esc_attr( $quiz_id ); ?>" method="post" onsubmit="return false;">
                    <input type="hidden" name="quiz_id" value="<?php echo esc_attr( $quiz_id ); ?>">

                    <h3 class="mm-quiz-title"><?php echo esc_html( $quiz['title'] ); ?></h3>

                    <div class="mm-student-fields">
                        <p><label><?php esc_html_e( 'Name', 'markdown-master' ); ?> <input type="text" name="student[name]" required></label></p>
                        <p><label><?php esc_html_e( 'Class', 'markdown-master' ); ?> <input type="text" name="student[class]"></label></p>
                        <p><label><?php esc_html_e( 'Section', 'markdown-master' ); ?> <input type="text" name="student[section]"></label></p>
                        <p><label><?php esc_html_e( 'School', 'markdown-master' ); ?> <input type="text" name="student[school]"></label></p>
                        <p><label><?php esc_html_e( 'Roll No (optional)', 'markdown-master' ); ?> <input type="text" name="student[roll]"></label></p>
                    </div>

                    <div class="mm-questions">
                        <?php
                        if ( empty( $quiz['questions'] ) ) {
                            echo '<p>' . esc_html__( 'No questions added to this quiz yet.', 'markdown-master' ) . '</p>';
                        } else {
                            foreach ( $quiz['questions'] as $q ) :
                                $q_id = intval( $q['id'] );
                                $q_type = isset( $q['question_type'] ) ? $q['question_type'] : 'mcq';
                                $question_text = wp_kses_post( $q['question_text'] );
                                $options = isset( $q['options'] ) ? $q['options'] : array();
                                ?>
                                <div class="mm-question" data-qid="<?php echo esc_attr( $q_id ); ?>" data-qtype="<?php echo esc_attr( $q_type ); ?>">
                                    <div class="mm-question-text"><?php echo $question_text; ?></div>

                                    <div class="mm-question-inputs">
                                        <?php if ( $q_type === 'mcq' ) : ?>
                                            <?php if ( ! empty( $options ) && is_array( $options ) ) : ?>
                                                <?php foreach ( $options as $opt_index => $opt_val ) : 
                                                    $opt_val = is_scalar( $opt_val ) ? (string) $opt_val : wp_json_encode( $opt_val);
                                                    ?>
                                                    <label class="mm-opt">
                                                        <input type="radio"
                                                               name="answers[<?php echo esc_attr( $q_id ); ?>]"
                                                               value="<?php echo esc_attr( $opt_val ); ?>">
                                                        <?php echo esc_html( $opt_val ); ?>
                                                    </label>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <em><?php esc_html_e( 'No options for this question.', 'markdown-master' ); ?></em>
                                            <?php endif; ?>

                                        <?php elseif ( $q_type === 'checkbox' ) : ?>
                                            <?php if ( ! empty( $options ) && is_array( $options ) ) : ?>
                                                <?php foreach ( $options as $opt_index => $opt_val ) : 
                                                    $opt_val = is_scalar( $opt_val ) ? (string) $opt_val : wp_json_encode( $opt_val);
                                                    ?>
                                                    <label class="mm-opt">
                                                        <input type="checkbox"
                                                               name="answers[<?php echo esc_attr( $q_id ); ?>][]"
                                                               value="<?php echo esc_attr( $opt_val ); ?>">
                                                        <?php echo esc_html( $opt_val ); ?>
                                                    </label>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <em><?php esc_html_e( 'No options for this question.', 'markdown-master' ); ?></em>
                                            <?php endif; ?>

                                        <?php elseif ( $q_type === 'text' ) : ?>
                                            <input type="text" name="answers[<?php echo esc_attr( $q_id ); ?>]" class="mm-text-answer">

                                        <?php elseif ( $q_type === 'textarea' ) : ?>
                                            <textarea name="answers[<?php echo esc_attr( $q_id ); ?>]" class="mm-text-answer"></textarea>

                                        <?php else : ?>
                                            <input type="text" name="answers[<?php echo esc_attr( $q_id ); ?>]">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php } ?>
                    </div>

                    <div class="mm-quiz-actions">
                        <button type="button" class="button mm-submit-quiz"><?php esc_html_e( 'Submit Quiz', 'markdown-master' ); ?></button>
                        <button type="button" class="button mm-download-pdf" style="display:none;"><?php esc_html_e( 'Download PDF', 'markdown-master' ); ?></button>
                    </div>

                    <div class="mm-quiz-result" style="display:none;"></div>
                </form>
            </div>
            <?php
            return ob_get_clean();
        }
    }

    // Initialize shortcode class once
    add_action( 'init', function() {
        if ( ! isset( $GLOBALS['mm_shortcodes_loaded'] ) ) {
            $GLOBALS['mm_shortcodes_loaded'] = new MM_Shortcodes();
        }
    } );
}
