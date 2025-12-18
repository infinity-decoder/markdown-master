<?php
/**
 * Enhanced Shortcodes for Markdown Master 2.0
 * 
 * Shortcodes:
 * - [mm-quiz id="UUID"] - Render quiz by UUID
 * - [mm-markdown]content[/mm-markdown] - Inline markdown rendering
 * - [mm-markdown id="X"] - Render markdown snippet
 * - [mm-code lang="python"]code[/mm-code] - Inline code with highlighting
 * - [mm-code id="X"] - Render code snippet
 * 
 * @package MarkdownMaster
 * @since 2.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'MM_Shortcodes' ) ) {

class MM_Shortcodes {

    public function __construct() {
        // Register all shortcodes
        add_shortcode( 'mm-quiz', array( $this, 'render_quiz_shortcode' ) );
        add_shortcode( 'mm-markdown', array( $this, 'render_markdown_shortcode' ) );
        add_shortcode( 'mm-code', array( $this, 'render_code_shortcode' ) );
    }

    /**
     * Render quiz shortcode
     * 
     * Usage: [mm-quiz id="UUID_OR_ID"]
     * 
     * @param array $atts Shortcode attributes
     * @return string Rendered quiz HTML
     */
    public function render_quiz_shortcode( $atts = array() ) {
        $atts = shortcode_atts(
            array(
                'id' => '',
            ),
            $atts,
            'mm-quiz'
        );

        if ( empty( $atts['id'] ) ) {
            return '<div class="mm-error">' . esc_html__( 'Quiz ID is required.', 'markdown-master' ) . '</div>';
        }

        $quiz_id = sanitize_text_field( $atts['id'] );

        // Load quiz (handles both UUID and numeric ID)
        $quiz_model = new MM_Quiz();
        $quiz = $quiz_model->get_quiz( $quiz_id, true ); // with questions

        if ( ! $quiz ) {
            return '<div class="mm-error">' . esc_html__( 'Quiz not found.', 'markdown-master' ) . '</div>';
        }

        // Check if quiz is available (scheduling)
        $availability = $quiz_model->check_availability( $quiz );
        if ( ! $availability['available'] ) {
            return '<div class="mm-quiz-unavailable">' . esc_html( $availability['message'] ) . '</div>';
        }

        // Check login requirement
        if ( $quiz['require_login'] && ! is_user_logged_in() ) {
            $login_url = wp_login_url( get_permalink() );
            return sprintf(
                '<div class="mm-quiz-login-required"><p>%s</p><p><a href="%s" class="mm-button">%s</a></p></div>',
                esc_html__( 'You must be logged in to take this quiz.', 'markdown-master' ),
                esc_url( $login_url ),
                esc_html__( 'Log In', 'markdown-master' )
            );
        }

        // Check role requirement
        if ( ! empty( $quiz['required_role'] ) && is_user_logged_in() ) {
            $current_user = wp_get_current_user();
            if ( ! in_array( $quiz['required_role'], $current_user->roles, true ) ) {
                return '<div class="mm-quiz-forbidden">' . esc_html__( 'You do not have permission to access this quiz.', 'markdown-master' ) . '</div>';
            }
        }

        // Check attempt limits
        $user_id = is_user_logged_in() ? get_current_user_id() : 0;
        $limits = $quiz_model->check_attempt_limits( $quiz['id'], $quiz, $user_id );
        if ( ! $limits['allowed'] ) {
            return '<div class="mm-quiz-limit-reached">' . esc_html( $limits['message'] ) . '</div>';
        }

        // Check if this is a submission
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( isset( $_POST['mm_quiz_submit'] ) && isset( $_POST['quiz_id'] ) && absint( $_POST['quiz_id'] ) === absint( $quiz['id'] ) ) {
            return $this->handle_quiz_submission( $quiz );
        }

        // Enqueue assets
        $this->enqueue_quiz_assets();

        // Render quiz
        return $this->render_quiz_form( $quiz );
    }

    /**
     * Render markdown shortcode
     * 
     * Usage: 
     * - [mm-markdown id="123"] - renders snippet
     * - [mm-markdown]# Content[/mm-markdown] - inline rendering
     * 
     * @param array $atts Shortcode attributes
     * @param string $content Enclosed content
     * @return string Rendered HTML
     */
    public function render_markdown_shortcode( $atts = array(), $content = null ) {
        $atts = shortcode_atts(
            array(
                'id' => '',
            ),
            $atts,
            'mm-markdown'
        );

        // Snippet rendering
        if ( ! empty( $atts['id'] ) ) {
            $snippet_id = absint( $atts['id'] );
            $snippets = new MM_Markdown_Snippets();
            $html = $snippets->get_rendered( $snippet_id );

            if ( empty( $html ) ) {
                return '<div class="mm-error">' . esc_html__( 'Markdown snippet not found.', 'markdown-master' ) . '</div>';
            }

            return '<div class="mm-markdown-content">' . $html . '</div>';
        }

        // Inline rendering
        if ( empty( $content ) ) {
            return '';
        }

        $markdown = new MM_Markdown();
        $cache_key = MM_Cache::get_markdown_key( $content );
        
        // Try cache first
        $html = MM_Cache::get( $cache_key, MM_Cache::GROUP_MARKDOWN );
        
        if ( false === $html ) {
            $html = $markdown->render_markdown( $content );
            MM_Cache::set( $cache_key, $html, MM_Cache::get_ttl(), MM_Cache::GROUP_MARKDOWN );
        }

        return '<div class="mm-markdown-content">' . $html . '</div>';
    }

    /**
     * Render code shortcode
     * 
     * Usage:
     * - [mm-code id="123"] - renders snippet
     * - [mm-code lang="python"]code here[/mm-code] - inline rendering
     * 
     * @param array $atts Shortcode attributes
     * @param string $content Enclosed content
     * @return string Rendered HTML
     */
    public function render_code_shortcode( $atts = array(), $content = null ) {
        $atts = shortcode_atts(
            array(
                'id'   => '',
                'lang' => 'text',
            ),
            $atts,
            'mm-code'
        );

        // Snippet rendering
        if ( ! empty( $atts['id'] ) ) {
            $snippet_id = absint( $atts['id'] );
            $snippet_model = new MM_Snippet();
            $snippet = $snippet_model->get_snippet( $snippet_id );

            if ( ! $snippet ) {
                return '<div class="mm-error">' . esc_html__( 'Code snippet not found.', 'markdown-master' ) . '</div>';
            }

            return $this->render_code_block( $snippet->code, $snippet->language, absint( $snippet->show_copy_button ) );
        }

        // Inline rendering
        if ( empty( $content ) ) {
            return '';
        }

        $language = sanitize_text_field( $atts['lang'] );
        return $this->render_code_block( $content, $language, true );
    }

    /**
     * Render code block with syntax highlighting
     * 
     * @param string $code Code content
     * @param string $language Language for highlighting
     * @param bool $show_copy Show copy button
     * @return string HTML
     */
    protected function render_code_block( $code, $language, $show_copy = true ) {
        $code_escaped = esc_html( $code );
        $language_safe = esc_attr( $language );

        $html = '<div class="mm-code-block">';
        
        if ( $show_copy ) {
            $html .= '<button class="mm-code-copy" data-clipboard-text="' . esc_attr( $code ) . '" aria-label="' . esc_attr__( 'Copy code', 'markdown-master' ) . '">';
            $html .= '<span class="mm-copy-icon">📋</span>';
            $html .= '<span class="mm-copy-text">' . esc_html__( 'Copy', 'markdown-master' ) . '</span>';
            $html .= '</button>';
        }

        $html .= '<pre><code class="language-' . $language_safe . '">' . $code_escaped . '</code></pre>';
        $html .= '</div>';

        // Enqueue highlighting library
        $this->enqueue_code_assets();

        return $html;
    }

    /**
     * Render quiz form
     * 
     * @param array $quiz Quiz data with questions
     * @return string HTML
     */
    protected function render_quiz_form( $quiz ) {
        ob_start();

        $quiz_id = absint( $quiz['id'] );
        $questions = $quiz['questions'];

        // Randomize questions if enabled
        if ( $quiz['randomize_questions'] ) {
            shuffle( $questions );
        }

        // Randomize answers if enabled
        if ( $quiz['randomize_answers'] ) {
            $quiz_model = new MM_Quiz();
            foreach ( $questions as &$q ) {
                $q = $quiz_model->randomize_answers( $q );
            }
            unset( $q );
        }

        ?>
        <div class="mm-quiz-container" data-quiz-id="<?php echo esc_attr( $quiz_id ); ?>">
            
            <?php if ( $quiz['show_welcome_screen'] && ! empty( $quiz['welcome_content'] ) ) : ?>
                <div class="mm-quiz-welcome">
                    <div class="mm-welcome-content">
                        <?php echo wp_kses_post( $quiz['welcome_content'] ); ?>
                    </div>
                    <button class="mm-start-quiz mm-button-primary">
                        <?php esc_html_e( 'Start Quiz', 'markdown-master' ); ?>
                    </button>
                </div>
            <?php endif; ?>

            <div class="mm-quiz-form" <?php echo ( $quiz['show_welcome_screen'] && ! empty( $quiz['welcome_content'] ) ) ? 'style="display:none;"' : ''; ?>>
                
                <div class="mm-quiz-header">
                    <h2 class="mm-quiz-title"><?php echo esc_html( $quiz['title'] ); ?></h2>
                    <?php if ( ! empty( $quiz['description'] ) ) : ?>
                        <div class="mm-quiz-description"><?php echo wp_kses_post( $quiz['description'] ); ?></div>
                    <?php endif; ?>

                    <?php if ( $quiz['time_limit'] > 0 ) : ?>
                        <div class="mm-quiz-timer" data-seconds="<?php echo esc_attr( $quiz['time_limit'] ); ?>">
                            <span class="mm-timer-icon">⏱️</span>
                            <span class="mm-timer-value"><?php echo esc_html( gmdate( 'i:s', $quiz['time_limit'] ) ); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <form method="post" class="mm-quiz-form-element" id="mm-quiz-<?php echo esc_attr( $quiz_id ); ?>">
                    <?php wp_nonce_field( 'mm_quiz_submit_' . $quiz_id, 'mm_quiz_nonce' ); ?>
                    <input type="hidden" name="quiz_id" value="<?php echo esc_attr( $quiz_id ); ?>">
                    <input type="hidden" name="mm_quiz_submit" value="1">
                    <input type="hidden" name="start_time" value="<?php echo esc_attr( time() ); ?>">

                    <?php
                    // Lead capture (if enabled and before questions)
                    if ( $quiz['enable_lead_capture'] ) {
                        echo $this->render_lead_capture_form( $quiz );
                    }
                    ?>

                    <div class="mm-quiz-questions">
                        <?php
                        foreach ( $questions as $index => $question ) {
                            echo $this->render_question( $question, $index, $quiz );
                        }
                        ?>
                    </div>

                    <div class="mm-quiz-submit-section">
                        <button type="submit" class="mm-submit-quiz mm-button-primary">
                            <?php esc_html_e( 'Submit Quiz', 'markdown-master' ); ?>
                        </button>
                    </div>
                </form>

            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Render single question
     * 
     * @param array $question Question data
     * @param int $index Question index
     * @param array $quiz Quiz data
     * @return string HTML
     */
    protected function render_question( $question, $index, $quiz ) {
        $question_id = absint( $question['id'] );
        $type = sanitize_text_field( $question['type'] );
        $question_number = $index + 1;

        ob_start();
        ?>
        <div class="mm-question" data-question-id="<?php echo esc_attr( $question_id ); ?>" data-type="<?php echo esc_attr( $type ); ?>">
            <div class="mm-question-header">
                <span class="mm-question-number"><?php echo esc_html( $question_number ); ?>.</span>
                <div class="mm-question-text"><?php echo wp_kses_post( $question['question_text'] ); ?></div>
            </div>

            <div class="mm-question-body">
                <?php
                switch ( $type ) {
                    case 'radio':
                        echo $this->render_radio_question( $question );
                        break;
                    case 'checkbox':
                        echo $this->render_checkbox_question( $question );
                        break;
                    case 'dropdown':
                        echo $this->render_dropdown_question( $question );
                        break;
                    case 'text':
                        echo $this->render_text_question( $question );
                        break;
                    case 'short_text':
                        echo $this->render_short_text_question( $question );
                        break;
                    case 'number':
                        echo $this->render_number_question( $question );
                        break;
                    case 'date':
                        echo $this->render_date_question( $question );
                        break;
                    case 'banner':
                        echo $this->render_banner_question( $question );
                        break;
                    case 'fill_blank':
                        echo $this->render_fill_blank_question( $question );
                        break;
                    case 'matching':
                        echo $this->render_matching_question( $question );
                        break;
                    default:
                        echo '<p class="mm-error">' . esc_html__( 'Unknown question type.', 'markdown-master' ) . '</p>';
                }
                ?>
            </div>

            <?php if ( ! empty( $question['hint'] ) ) : ?>
                <div class="mm-question-hint">
                    <button type="button" class="mm-hint-toggle" aria-expanded="false">
                        <?php esc_html_e( 'Show Hint', 'markdown-master' ); ?>
                    </button>
                    <div class="mm-hint-content" style="display:none;">
                        <?php echo wp_kses_post( $question['hint'] ); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ( $question['allow_comment'] ) : ?>
                <div class="mm-question-comment">
                    <label for="comment_<?php echo esc_attr( $question_id ); ?>" class="mm-comment-label">
                        <?php esc_html_e( 'Comments (optional):', 'markdown-master' ); ?>
                    </label>
                    <textarea name="comments[<?php echo esc_attr( $question_id ); ?>]" id="comment_<?php echo esc_attr( $question_id ); ?>" rows="3" class="mm-comment-field"></textarea>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render radio question
     */
    protected function render_radio_question( $question ) {
        $question_id = absint( $question['id'] );
        $options = is_array( $question['options'] ) ? $question['options'] : array();

        ob_start();
        ?>
        <div class="mm-radio-options">
            <?php foreach ( $options as $index => $option ) : ?>
                <label class="mm-radio-label">
                    <input type="radio" name="answers[<?php echo esc_attr( $question_id ); ?>]" value="<?php echo esc_attr( $index ); ?>" required>
                    <span class="mm-radio-text"><?php echo esc_html( $option ); ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render checkbox question
     */
    protected function render_checkbox_question( $question ) {
        $question_id = absint( $question['id'] );
        $options = is_array( $question['options'] ) ? $question['options'] : array();

        ob_start();
        ?>
        <div class="mm-checkbox-options">
            <?php foreach ( $options as $index => $option ) : ?>
                <label class="mm-checkbox-label">
                    <input type="checkbox" name="answers[<?php echo esc_attr( $question_id ); ?>][]" value="<?php echo esc_attr( $index ); ?>">
                    <span class="mm-checkbox-text"><?php echo esc_html( $option ); ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render dropdown question
     */
    protected function render_dropdown_question( $question ) {
        $question_id = absint( $question['id'] );
        $options = is_array( $question['options'] ) ? $question['options'] : array();

        ob_start();
        ?>
        <select name="answers[<?php echo esc_attr( $question_id ); ?>]" class="mm-dropdown" required>
            <option value=""><?php esc_html_e( '-- Select an option --', 'markdown-master' ); ?></option>
            <?php foreach ( $options as $index => $option ) : ?>
                <option value="<?php echo esc_attr( $index ); ?>"><?php echo esc_html( $option ); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
        return ob_get_clean();
    }

    /**
     * Render text (long answer) question
     */
    protected function render_text_question( $question ) {
        $question_id = absint( $question['id'] );

        ob_start();
        ?>
        <textarea name="answers[<?php echo esc_attr( $question_id ); ?>]" class="mm-text-answer" rows="5" required></textarea>
        <?php
        return ob_get_clean();
    }

    /**
     * Render short text question
     */
    protected function render_short_text_question( $question ) {
        $question_id = absint( $question['id'] );

        ob_start();
        ?>
        <input type="text" name="answers[<?php echo esc_attr( $question_id ); ?>]" class="mm-short-text-answer" required>
        <?php
        return ob_get_clean();
    }

    /**
     * Render number question
     */
    protected function render_number_question( $question ) {
        $question_id = absint( $question['id'] );

        ob_start();
        ?>
        <input type="number" step="any" name="answers[<?php echo esc_attr( $question_id ); ?>]" class="mm-number-answer" required>
        <?php
        return ob_get_clean();
    }

    /**
     * Render date question
     */
    protected function render_date_question( $question ) {
        $question_id = absint( $question['id'] );

        ob_start();
        ?>
        <input type="date" name="answers[<?php echo esc_attr( $question_id ); ?>]" class="mm-date-answer" required>
        <?php
        return ob_get_clean();
    }

    /**
     * Render banner (informational) question
     */
    protected function render_banner_question( $question ) {
        // Banner type displays content only, no input required
        return '<div class="mm-banner-content">' . wp_kses_post( $question['question_text'] ) . '</div>';
    }

    /**
     * Render fill in the blank question
     */
    protected function render_fill_blank_question( $question ) {
        $question_id = absint( $question['id'] );
        $metadata = is_array( $question['metadata'] ) ? $question['metadata'] : array();
        $blank_count = isset( $metadata['blank_count'] ) ? absint( $metadata['blank_count'] ) : 1;

        ob_start();
        ?>
        <div class="mm-fill-blank-inputs">
            <?php for ( $i = 0; $i < $blank_count; $i++ ) : ?>
                <div class="mm-blank-input-group">
                    <label for="blank_<?php echo esc_attr( $question_id . '_' . $i ); ?>">
                        <?php echo esc_html( sprintf( __( 'Blank %d:', 'markdown-master' ), $i + 1 ) ); ?>
                    </label>
                    <input type="text" name="answers[<?php echo esc_attr( $question_id ); ?>][<?php echo esc_attr( $i ); ?>]" id="blank_<?php echo esc_attr( $question_id . '_' . $i ); ?>" class="mm-blank-input" required>
                </div>
            <?php endfor; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render matching question
     */
    protected function render_matching_question( $question ) {
        $question_id = absint( $question['id'] );
        $metadata = is_array( $question['metadata'] ) ? $question['metadata'] : array();
        $left_items = isset( $metadata['left_items'] ) ? $metadata['left_items'] : array();
        $right_items = isset( $metadata['right_items'] ) ? $metadata['right_items'] : array();

        ob_start();
        ?>
        <div class="mm-matching-container">
            <div class="mm-matching-instructions">
                <p><?php esc_html_e( 'Match each item on the left with one on the right:', 'markdown-master' ); ?></p>
            </div>
            <div class="mm-matching-pairs">
                <?php foreach ( $left_items as $left_index => $left_item ) : ?>
                    <div class="mm-matching-row">
                        <div class="mm-matching-left">
                            <?php echo esc_html( $left_item ); ?>
                        </div>
                        <div class="mm-matching-arrow">→</div>
                        <div class="mm-matching-right">
                            <select name="answers[<?php echo esc_attr( $question_id ); ?>][<?php echo esc_attr( $left_index ); ?>]" required>
                                <option value="">-- <?php esc_html_e( 'Select', 'markdown-master' ); ?> --</option>
                                <?php foreach ( $right_items as $right_index => $right_item ) : ?>
                                    <option value="<?php echo esc_attr( $right_index ); ?>"><?php echo esc_html( $right_item ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render lead capture form
     */
    protected function render_lead_capture_form( $quiz ) {
        ob_start();
        ?>
        <div class="mm-lead-capture">
            <h3 class="mm-lead-title"><?php esc_html_e( 'Your Information', 'markdown-master' ); ?></h3>
            <div class="mm-lead-fields">
                <div class="mm-field">
                    <label for="lead_name"><?php esc_html_e( 'Name', 'markdown-master' ); ?> <span class="required">*</span></label>
                    <input type="text" name="lead_name" id="lead_name" required>
                </div>
                <div class="mm-field">
                    <label for="lead_email"><?php esc_html_e( 'Email', 'markdown-master' ); ?> <span class="required">*</span></label>
                    <input type="email" name="lead_email" id="lead_email" required>
                </div>
                <div class="mm-field">
                    <label for="lead_phone"><?php esc_html_e( 'Phone (optional)', 'markdown-master' ); ?></label>
                    <input type="tel" name="lead_phone" id="lead_phone">
                </div>
                <div class="mm-field mm-consent-field">
                    <label class="mm-consent-label">
                        <input type="checkbox" name="lead_consent" value="1" required>
                        <span><?php esc_html_e( 'I consent to having this website store my submitted information.', 'markdown-master' ); ?> <span class="required">*</span></span>
                    </label>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle quiz submission
     */
    protected function handle_quiz_submission( $quiz ) {
        // Verify nonce
        $quiz_id = absint( $quiz['id'] );
        MM_Security::verify_nonce_or_die( 'mm_quiz_submit_' . $quiz_id, 'mm_quiz_nonce' );

        // Rate limit check
        $rate_key = 'quiz_' . $quiz_id . '_' . MM_Security::get_user_ip();
        if ( ! MM_Security::check_rate_limit( $rate_key, 5, 3600 ) ) {
            return '<div class="mm-error">' . esc_html__( 'Too many submissions. Please try again later.', 'markdown-master' ) . '</div>';
        }

        // Sanitize answers
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $raw_answers = isset( $_POST['answers'] ) ? wp_unslash( $_POST['answers'] ) : array();
        $answers = MM_Security::sanitize_answers_array( $raw_answers );

        // Calculate time taken
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $start_time = isset( $_POST['start_time'] ) ? absint( $_POST['start_time'] ) : time();
        $time_taken = time() - $start_time;

        // Prepare attempt data
        $attempt_data = array(
            'answers'    => $answers,
            'time_taken' => $time_taken,
        );

        // Add user ID if logged in
        if ( is_user_logged_in() ) {
            $attempt_data['user_id'] = get_current_user_id();
        }

        // Record attempt
        $quiz_model = new MM_Quiz();
        $attempt_id = $quiz_model->record_attempt( $quiz_id, $attempt_data );

        if ( ! $attempt_id ) {
            return '<div class="mm-error">' . esc_html__( 'Failed to record quiz attempt. Please try again.', 'markdown-master' ) . '</div>';
        }

        // Handle lead capture if enabled
        if ( $quiz['enable_lead_capture'] ) {
            $this->handle_lead_capture( $quiz_id, $attempt_id );
        }

        // Get attempt details
        $attempt = $quiz_model->get_attempt( $attempt_id );

        // Render results
        return $this->render_quiz_results( $quiz, $attempt );
    }

    /**
     * Handle lead capture submission
     */
    protected function handle_lead_capture( $quiz_id, $attempt_id ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( ! isset( $_POST['lead_consent'] ) || '1' !== $_POST['lead_consent'] ) {
            return; // No consent, skip lead capture
        }

        $lead_data = array(
            'name'          => isset( $_POST['lead_name'] ) ? sanitize_text_field( wp_unslash( $_POST['lead_name'] ) ) : '',
            'email'         => isset( $_POST['lead_email'] ) ? sanitize_email( wp_unslash( $_POST['lead_email'] ) ) : '',
            'phone'         => isset( $_POST['lead_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['lead_phone'] ) ) : '',
            'consent_given' => 1,
        );

        $lead_capture = new MM_Lead_Capture();
        $lead_capture->capture_lead( $quiz_id, $attempt_id, $lead_data );
    }

    /**
     * Render quiz results
     */
    protected function render_quiz_results( $quiz, $attempt ) {
        $percentage = ( $attempt['total_marks'] > 0 ) ? ( $attempt['obtained_marks'] / $attempt['total_marks'] ) * 100 : 0;
        $tier = $attempt['result_tier'];

        ob_start();
        ?>
        <div class="mm-quiz-results" data-tier="<?php echo esc_attr( $tier ); ?>">
            <div class="mm-results-header">
                <h2><?php esc_html_e( 'Quiz Results', 'markdown-master' ); ?></h2>
            </div>

            <div class="mm-results-score">
                <div class="mm-score-circle">
                    <span class="mm-score-value"><?php echo esc_html( number_format( $percentage, 1 ) ); ?>%</span>
                </div>
                <div class="mm-score-details">
                    <p class="mm-points">
                        <?php
                        echo esc_html( sprintf(
                            __( 'You scored %1$s out of %2$s points', 'markdown-master' ),
                            number_format( $attempt['obtained_marks'], 2 ),
                            number_format( $attempt['total_marks'], 2 )
                        ) );
                        ?>
                    </p>
                    <?php if ( $attempt['time_taken'] > 0 ) : ?>
                        <p class="mm-time-taken">
                            <?php echo esc_html( sprintf( __( 'Time taken: %s', 'markdown-master' ), gmdate( 'i:s', $attempt['time_taken'] ) ) ); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mm-results-message mm-tier-<?php echo esc_attr( $tier ); ?>">
                <?php
                switch ( $tier ) {
                    case 'high':
                        echo '<p class="mm-congratulations">' . esc_html__( '🎉 Excellent work! You\'ve demonstrated mastery of this material.', 'markdown-master' ) . '</p>';
                        break;
                    case 'medium':
                        echo '<p class="mm-good-job">' . esc_html__( '👍 Good job! You have a solid understanding.', 'markdown-master' ) . '</p>';
                        break;
                    case 'low':
                        echo '<p class="mm-encouragement">' . esc_html__( '📚 Keep practicing! Review the material and try again.', 'markdown-master' ) . '</p>';
                        break;
                }
                ?>
            </div>

            <?php
            // Social sharing (privacy-aware)
            $share_text = sprintf( __( 'I scored %s%% on this quiz!', 'markdown-master' ), number_format( $percentage, 0 ) );
            $share_url = get_permalink();
            ?>
            <div class="mm-results-share">
                <p class="mm-share-label"><?php esc_html_e( 'Share your results:', 'markdown-master' ); ?></p>
                <div class="mm-share-buttons">
                    <a href="https://twitter.com/intent/tweet?text=<?php echo rawurlencode( $share_text ); ?>&url=<?php echo rawurlencode( $share_url ); ?>" target="_blank" rel="noopener" class="mm-share-twitter">
                        <?php esc_html_e( 'Twitter', 'markdown-master' ); ?>
                    </a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo rawurlencode( $share_url ); ?>" target="_blank" rel="noopener" class="mm-share-facebook">
                        <?php esc_html_e( 'Facebook', 'markdown-master' ); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue quiz assets
     */
    protected function enqueue_quiz_assets() {
        wp_enqueue_style( 'mm-quiz', MM_PLUGIN_URL . 'assets/css/mm-public.css', array(), MM_VERSION );
        wp_enqueue_script( 'mm-quiz', MM_PLUGIN_URL . 'assets/js/mm-public.js', array( 'jquery' ), MM_VERSION, true );

        // Localize script
        wp_localize_script( 'mm-quiz', 'mmQuiz', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'mm_quiz_ajax' ),
        ) );
    }

    /**
     * Enqueue code highlighting assets
     */
    protected function enqueue_code_assets() {
        if ( class_exists( 'MM_Highlighter' ) ) {
            MM_Highlighter::enqueue_assets();
        }
    }
}

    // Initialize shortcodes
    add_action( 'init', function() {
        if ( ! isset( $GLOBALS['mm_shortcodes_loaded'] ) ) {
            $GLOBALS['mm_shortcodes_loaded'] = new MM_Shortcodes();
        }
    } );
}
