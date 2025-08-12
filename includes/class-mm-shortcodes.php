<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MM_Shortcodes
 * Register and render shortcodes for quizzes, notes and code snippets.
 */
class MM_Shortcodes {

    protected $quiz;
    protected $note;
    protected $snippet;

    public function __construct() {
        // Ensure required classes exist
        if ( ! class_exists( 'MM_Quiz' ) ) {
            require_once MM_INCLUDES . 'class-mm-quiz.php';
        }
        if ( ! class_exists( 'MM_Note' ) ) {
            require_once MM_INCLUDES . 'class-mm-note.php';
        }
        if ( ! class_exists( 'MM_Snippet' ) ) {
            require_once MM_INCLUDES . 'class-mm-snippet.php';
        }

        $this->quiz = new MM_Quiz();
        $this->note = new MM_Note();
        $this->snippet = new MM_Snippet();

        add_shortcode( 'mm_quiz', [ $this, 'render_quiz_shortcode' ] );
        add_shortcode( 'mm_note', [ $this, 'render_note_shortcode' ] );
        add_shortcode( 'mm_code', [ $this, 'render_code_shortcode' ] );
    }

    /**
     * Render quiz by ID: [mm_quiz id="123"]
     */
    public function render_quiz_shortcode( $atts ) {
        $atts = shortcode_atts( [ 'id' => 0, 'show_title' => 'true' ], $atts, 'mm_quiz' );
        $quiz_id = intval( $atts['id'] );
        if ( ! $quiz_id ) {
            return '<div class="mm-alert mm-alert-error">Quiz ID not provided.</div>';
        }

        $quiz = $this->quiz->get_quiz( $quiz_id );
        if ( ! $quiz ) {
            return '<div class="mm-alert mm-alert-error">Quiz not found.</div>';
        }

        // Basic HTML for frontend quiz rendering.
        ob_start();
        if ( filter_var( $atts['show_title'], FILTER_VALIDATE_BOOLEAN ) ) {
            echo '<h3 class="mm-quiz-title">' . esc_html( $quiz->title ) . '</h3>';
        }
        echo '<div class="mm-quiz" data-quiz-id="' . esc_attr( $quiz_id ) . '">';
        echo '<div class="mm-quiz-desc">' . wp_kses_post( $quiz->description ) . '</div>';

        // Questions
        $questions = $this->quiz->get_questions_by_quiz( $quiz_id );
        if ( empty( $questions ) ) {
            echo '<p>' . esc_html__( 'No questions available for this quiz.', 'markdown-master' ) . '</p>';
            return ob_get_clean();
        }

        echo '<form class="mm-quiz-form" data-quiz-id="' . esc_attr( $quiz_id ) . '">';
        // optional user info fields (simple)
        echo '<div class="mm-user-info">';
        echo '<label>' . esc_html__( 'Name', 'markdown-master' ) . '<input type="text" name="mm_user_name" required></label>';
        echo '<label>' . esc_html__( 'Email', 'markdown-master' ) . '<input type="email" name="mm_user_email" required></label>';
        echo '<label>' . esc_html__( 'Class', 'markdown-master' ) . '<input type="text" name="mm_user_class"></label>';
        echo '<label>' . esc_html__( 'Section', 'markdown-master' ) . '<input type="text" name="mm_user_section"></label>';
        echo '</div>';

        foreach ( $questions as $index => $q ) {
            $q = $this->quiz->get_question( $q->id ); // ensure options and correct parsed
            echo '<div class="mm-question" data-question-id="' . esc_attr( $q->id ) . '">';
            echo '<div class="mm-question-text">' . wp_kses_post( $q->question ) . '</div>';
            if ( ! empty( $q->image ) ) {
                echo '<div class="mm-question-image"><img src="' . esc_url( $q->image ) . '" alt=""></div>';
            }

            if ( $q->type === 'mcq' ) {
                $answers = $this->quiz->get_answers_by_question( $q->id );
                if ( ! empty( $answers ) ) {
                    foreach ( $answers as $a ) {
                        $input_name = 'q_' . $q->id;
                        echo '<label class="mm-option">';
                        echo '<input type="radio" name="' . esc_attr( $input_name ) . '" value="' . esc_attr( $a->id ) . '"> ';
                        if ( ! empty( $a->answer_image ) ) {
                            echo '<img src="' . esc_url( $a->answer_image ) . '" alt="" style="max-width:100px;display:block;">';
                        }
                        echo wp_kses_post( $a->answer_text );
                        echo '</label>';
                    }
                }
            } else {
                // short/survey
                $input_name = 'q_' . $q->id;
                echo '<textarea name="' . esc_attr( $input_name ) . '" rows="3"></textarea>';
            }

            echo '</div>'; // .mm-question
        }

        echo '<div class="mm-quiz-actions"><button type="submit" class="mm-submit-quiz">' . esc_html__( 'Submit Quiz', 'markdown-master' ) . '</button></div>';
        echo '</form>';
        echo '</div>'; // .mm-quiz

        // Provide nonce for AJAX submission
        wp_nonce_field( 'mm_submit_quiz_' . $quiz_id, 'mm_quiz_nonce' );

        return ob_get_clean();
    }

    /**
     * Render note by ID: [mm_note id="123"]
     */
    public function render_note_shortcode( $atts ) {
        $atts = shortcode_atts( [ 'id' => 0 ], $atts, 'mm_note' );
        $id = intval( $atts['id'] );
        if ( ! $id ) {
            return '<div class="mm-alert mm-alert-error">Note ID not provided.</div>';
        }
        $note = $this->note->get_note( $id );
        if ( ! $note ) {
            return '<div class="mm-alert mm-alert-error">Note not found.</div>';
        }

        // Render markdown
        require_once MM_INCLUDES . 'class-mm-markdown.php';
        $md = new MM_Markdown();
        $html = $md->render_markdown( $note->content );

        return '<div class="mm-note">' . $html . '</div>';
    }

    /**
     * Render code snippet by ID: [mm_code id="123"]
     */
    public function render_code_shortcode( $atts ) {
        $atts = shortcode_atts( [ 'id' => 0 ], $atts, 'mm_code' );
        $id = intval( $atts['id'] );
        if ( ! $id ) {
            return '<div class="mm-alert mm-alert-error">Snippet ID not provided.</div>';
        }
        $s = $this->snippet->get_snippet( $id );
        if ( ! $s ) {
            return '<div class="mm-alert mm-alert-error">Snippet not found.</div>';
        }

        require_once MM_INCLUDES . 'class-mm-highlighter.php';
        $hl = new MM_Highlighter();
        return $hl->render_code( $s->code, $s->language );
    }
}

// initialize shortcodes
add_action( 'init', function() {
    if ( ! class_exists( 'MM_Shortcodes' ) ) {
        require_once MM_INCLUDES . 'class-mm-shortcodes.php';
    }
    // instantiate only once
    if ( class_exists( 'MM_Shortcodes' ) && ! isset( $GLOBALS['mm_shortcodes_loaded'] ) ) {
        $GLOBALS['mm_shortcodes_loaded'] = new MM_Shortcodes();
    }
} );
