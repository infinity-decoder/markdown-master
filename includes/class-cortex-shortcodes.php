<?php
/**
 * Enhanced Shortcodes for Cortex 2.0
 * 
 * Shortcodes:
 * - [cortex-quiz id="UUID"] - Render quiz by UUID
 * - [cortex-markdown]content[/cortex-markdown] - Inline markdown rendering
 * - [cortex-markdown id="X"] - Render markdown snippet
 * - [cortex-code lang="python"]code[/cortex-code] - Inline code with highlighting
 * - [cortex-code id="X"] - Render code snippet
 * 
 * @package MarkdownMaster
 * @since 2.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Cortex_Shortcodes' ) ) {

class Cortex_Shortcodes {

    public function __construct() {
        // Register all shortcodes
        add_shortcode( 'cortex-quiz', array( $this, 'render_quiz_shortcode' ) );
        add_shortcode( 'cortex-markdown', array( $this, 'render_markdown_shortcode' ) );
        add_shortcode( 'cortex-code', array( $this, 'render_code_shortcode' ) );

        // Legacy Shortcodes (Backward Compatibility)
        add_shortcode( 'mm-quiz', array( $this, 'render_quiz_shortcode' ) );
        add_shortcode( 'mm-markdown', array( $this, 'render_markdown_shortcode' ) );
        add_shortcode( 'mm-code', array( $this, 'render_code_shortcode' ) );
        add_shortcode( 'mm_quiz', array( $this, 'render_quiz_shortcode' ) ); // Handle underscore variants just in case
        add_shortcode( 'mm_markdown', array( $this, 'render_markdown_shortcode' ) );
        add_shortcode( 'mm_code', array( $this, 'render_code_shortcode' ) );
    }

    /**
     * Render quiz shortcode
     * 
     * Usage: [cortex-quiz id="UUID_OR_ID"]
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
            'cortex-quiz'
        );

        if ( empty( $atts['id'] ) ) {
            return '<div class="cortex-error">' . esc_html__( 'Quiz ID is required.', 'cortex' ) . '</div>';
        }

        $quiz_id = sanitize_text_field( $atts['id'] );

        // Load quiz (handles both UUID and numeric ID)
        $quiz_model = new Cortex_Quiz();
        $quiz = $quiz_model->get_quiz( $quiz_id, true ); // with questions

        if ( ! $quiz ) {
            return '<div class="cortex-error">' . esc_html__( 'Quiz not found.', 'cortex' ) . '</div>';
        }

        // Check if quiz is available (scheduling)
        $availability = $quiz_model->check_availability( $quiz );
        if ( ! $availability['available'] ) {
            return '<div class="cortex-quiz-unavailable">' . esc_html( $availability['message'] ) . '</div>';
        }

        // Check login requirement
        if ( $quiz['require_login'] && ! is_user_logged_in() ) {
            $login_url = wp_login_url( get_permalink() );
            return sprintf(
                '<div class="cortex-quiz-login-required"><p>%s</p><p><a href="%s" class="cortex-button">%s</a></p></div>',
                esc_html__( 'You must be logged in to take this quiz.', 'cortex' ),
                esc_url( $login_url ),
                esc_html__( 'Log In', 'cortex' )
            );
        }

        // Check role requirement
        if ( ! empty( $quiz['required_role'] ) && is_user_logged_in() ) {
            $current_user = wp_get_current_user();
            if ( ! in_array( $quiz['required_role'], $current_user->roles, true ) ) {
                return '<div class="cortex-quiz-forbidden">' . esc_html__( 'You do not have permission to access this quiz.', 'cortex' ) . '</div>';
            }
        }

        // Check attempt limits
        $user_id = is_user_logged_in() ? get_current_user_id() : 0;
        $limits = $quiz_model->check_attempt_limits( $quiz['id'], $quiz, $user_id );
        if ( ! $limits['allowed'] ) {
            return '<div class="cortex-quiz-limit-reached">' . esc_html( $limits['message'] ) . '</div>';
        }

        // Check if this is a submission
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( isset( $_POST['cortex_quiz_submit'] ) && isset( $_POST['quiz_id'] ) && absint( $_POST['quiz_id'] ) === absint( $quiz['id'] ) ) {
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
     * - [cortex-markdown id="123"] - renders snippet
     * - [cortex-markdown]# Content[/cortex-markdown] - inline rendering
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
            'cortex-markdown'
        );

        // Snippet rendering
        if ( ! empty( $atts['id'] ) ) {
            $snippet_id = absint( $atts['id'] );
            $snippets = new Cortex_Markdown_Snippets();
            $html = $snippets->get_rendered( $snippet_id );

            if ( empty( $html ) ) {
                return '<div class="cortex-error">' . esc_html__( 'Markdown snippet not found.', 'cortex' ) . '</div>';
            }

            return '<div class="cortex-markdown-content">' . $html . '</div>';
        }

        // Inline rendering
        if ( empty( $content ) ) {
            return '';
        }

        $markdown = new Cortex_Markdown();
        $cache_key = Cortex_Cache::get_markdown_key( $content );
        
        // Try cache first
        $html = Cortex_Cache::get( $cache_key, Cortex_Cache::GROUP_MARKDOWN );
        
        if ( false === $html ) {
            $html = $markdown->render_markdown( $content );
            Cortex_Cache::set( $cache_key, $html, Cortex_Cache::get_ttl(), Cortex_Cache::GROUP_MARKDOWN );
        }

        return '<div class="cortex-markdown-content">' . $html . '</div>';
    }

    /**
     * Render code shortcode
     * 
     * Usage:
     * - [cortex-code id="123"] - renders snippet
     * - [cortex-code lang="python"]code here[/cortex-code] - inline rendering
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
            'cortex-code'
        );

        // Snippet rendering
        if ( ! empty( $atts['id'] ) ) {
            $snippet_id = absint( $atts['id'] );
            $snippet_model = new Cortex_Snippet();
            $snippet = $snippet_model->get_snippet( $snippet_id );

            if ( ! $snippet ) {
                return '<div class="cortex-error">' . esc_html__( 'Code snippet not found.', 'cortex' ) . '</div>';
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

        $html = '<div class="cortex-code-block">';
        
        if ( $show_copy ) {
            $html .= '<button class="cortex-code-copy" data-clipboard-text="' . esc_attr( $code ) . '" aria-label="' . esc_attr__( 'Copy code', 'cortex' ) . '">';
            $html .= '<span class="cortex-copy-icon">📋</span>';
            $html .= '<span class="cortex-copy-text">' . esc_html__( 'Copy', 'cortex' ) . '</span>';
            $html .= '</button>';
        }

        $html .= '<pre><code class="language-' . $language_safe . '">' . $code_escaped . '</code></pre>';
        $html .= '</div>';

        // Enqueue highlighting library
        $this->enqueue_code_assets( $language );

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
            $quiz_model = new Cortex_Quiz();
            foreach ( $questions as &$q ) {
                $q = $quiz_model->randomize_answers( $q );
            }
            unset( $q );
        }

        ?>
        <div class="cortex-quiz-container" data-quiz-id="<?php echo esc_attr( $quiz_id ); ?>">
            
            <?php if ( $quiz['show_welcome_screen'] && ! empty( $quiz['welcome_content'] ) ) : ?>
                <div class="cortex-quiz-welcome">
                    <div class="cortex-welcome-content">
                        <?php echo wp_kses_post( $quiz['welcome_content'] ); ?>
                    </div>
                    <button class="cortex-start-quiz cortex-button-primary">
                        <?php esc_html_e( 'Start Quiz', 'cortex' ); ?>
                    </button>
                </div>
            <?php endif; ?>

            <div class="cortex-quiz-form" <?php echo ( $quiz['show_welcome_screen'] && ! empty( $quiz['welcome_content'] ) ) ? 'style="display:none;"' : ''; ?>>
                
                <div class="cortex-quiz-header">
                    <h2 class="cortex-quiz-title"><?php echo esc_html( $quiz['title'] ); ?></h2>
                    <?php if ( ! empty( $quiz['description'] ) ) : ?>
                        <div class="cortex-quiz-description"><?php echo wp_kses_post( $quiz['description'] ); ?></div>
                    <?php endif; ?>

                    <?php if ( $quiz['time_limit'] > 0 ) : ?>
                        <div class="cortex-quiz-timer" data-seconds="<?php echo esc_attr( $quiz['time_limit'] ); ?>">
                            <span class="cortex-timer-icon">⏱️</span>
                            <span class="cortex-timer-value"><?php echo esc_html( gmdate( 'i:s', $quiz['time_limit'] ) ); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <form method="post" class="cortex-quiz-form-element" id="cortex-quiz-<?php echo esc_attr( $quiz_id ); ?>">
                    <?php wp_nonce_field( 'cortex_quiz_submit_' . $quiz_id, 'cortex_quiz_nonce' ); ?>
                    <input type="hidden" name="quiz_id" value="<?php echo esc_attr( $quiz_id ); ?>">
                    <input type="hidden" name="cortex_quiz_submit" value="1">
                    <input type="hidden" name="start_time" value="<?php echo esc_attr( time() ); ?>">

                    <?php
                    // Lead capture (if enabled and before questions)
                    if ( $quiz['enable_lead_capture'] ) {
                        echo $this->render_lead_capture_form( $quiz );
                    }
                    ?>

                    <div class="cortex-quiz-questions">
                        <?php
                        foreach ( $questions as $index => $question ) {
                            echo $this->render_question( $question, $index, $quiz );
                        }
                        ?>
                    </div>

                    <div class="cortex-quiz-submit-section">
                        <button type="submit" class="cortex-submit-quiz cortex-button-primary">
                            <?php esc_html_e( 'Submit Quiz', 'cortex' ); ?>
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
        <div class="cortex-question" data-question-id="<?php echo esc_attr( $question_id ); ?>" data-type="<?php echo esc_attr( $type ); ?>">
            <div class="cortex-question-header">
                <span class="cortex-question-number"><?php echo esc_html( $question_number ); ?>.</span>
                <div class="cortex-question-text">
                    <?php 
                    // If it's a fill_blank question, we handle the text rendering inside its own method
                    if ( $type !== 'fill_blank' ) {
                        echo wp_kses_post( $question['question_text'] ); 
                    }
                    ?>
                </div>
            </div>

            <div class="cortex-question-body">
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
                        echo '<p class="cortex-error">' . esc_html__( 'Unknown question type.', 'cortex' ) . '</p>';
                }
                ?>
            </div>

            <?php if ( ! empty( $question['hint'] ) ) : ?>
                <div class="cortex-question-hint">
                    <button type="button" class="cortex-hint-toggle" aria-expanded="false">
                        <?php esc_html_e( 'Show Hint', 'cortex' ); ?>
                    </button>
                    <div class="cortex-hint-content" style="display:none;">
                        <?php echo wp_kses_post( $question['hint'] ); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ( $question['allow_comment'] ) : ?>
                <div class="cortex-question-comment">
                    <label for="comment_<?php echo esc_attr( $question_id ); ?>" class="cortex-comment-label">
                        <?php esc_html_e( 'Comments (optional):', 'cortex' ); ?>
                    </label>
                    <textarea name="comments[<?php echo esc_attr( $question_id ); ?>]" id="comment_<?php echo esc_attr( $question_id ); ?>" rows="3" class="cortex-comment-field"></textarea>
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
        <div class="cortex-radio-options">
            <?php foreach ( $options as $index => $option ) : ?>
                <label class="cortex-radio-label">
                    <input type="radio" name="answers[<?php echo esc_attr( $question_id ); ?>]" value="<?php echo esc_attr( $index ); ?>" required>
                    <span class="cortex-radio-text"><?php echo esc_html( $option ); ?></span>
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
        <div class="cortex-checkbox-options">
            <?php foreach ( $options as $index => $option ) : ?>
                <label class="cortex-checkbox-label">
                    <input type="checkbox" name="answers[<?php echo esc_attr( $question_id ); ?>][]" value="<?php echo esc_attr( $index ); ?>">
                    <span class="cortex-checkbox-text"><?php echo esc_html( $option ); ?></span>
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
        <select name="answers[<?php echo esc_attr( $question_id ); ?>]" class="cortex-dropdown" required>
            <option value=""><?php esc_html_e( '-- Select an option --', 'cortex' ); ?></option>
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
        <textarea name="answers[<?php echo esc_attr( $question_id ); ?>]" class="cortex-text-answer" rows="5" required></textarea>
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
        <input type="text" name="answers[<?php echo esc_attr( $question_id ); ?>]" class="cortex-short-text-answer" required>
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
        <input type="number" step="any" name="answers[<?php echo esc_attr( $question_id ); ?>]" class="cortex-number-answer" required>
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
        <input type="date" name="answers[<?php echo esc_attr( $question_id ); ?>]" class="cortex-date-answer" required>
        <?php
        return ob_get_clean();
    }

    /**
     * Render banner (informational) question
     */
    protected function render_banner_question( $question ) {
        // Banner type displays content only, no input required
        return '<div class="cortex-banner-content">' . wp_kses_post( $question['question_text'] ) . '</div>';
    }

    /**
     * Render fill in the blank question
     */
    protected function render_fill_blank_question( $question ) {
        $question_id = absint( $question['id'] );
        $text = $question['question_text'];
        
        // Count blanks and replace them with inputs
        $blank_index = 0;
        $rendered_text = preg_replace_callback( '/\[blank\]/', function( $matches ) use ( $question_id, &$blank_index ) {
            $input = sprintf(
                '<input type="text" name="answers[%1$d][%2$d]" class="cortex-blank-input" required aria-label="%3$s">',
                esc_attr( $question_id ),
                $blank_index,
                esc_attr( sprintf( __( 'Blank %d', 'cortex' ), $blank_index + 1 ) )
            );
            $blank_index++;
            return $input;
        }, wp_kses_post( $text ) );

        return '<div class="cortex-fill-blank-container">' . $rendered_text . '</div>';
    }

    /**
     * Render matching question
     */
    protected function render_matching_question( $question ) {
        $question_id = absint( $question['id'] );
        $metadata = is_array( $question['metadata'] ) ? $question['metadata'] : array();
        $pairs = isset( $metadata['pairs'] ) ? $metadata['pairs'] : array();
        
        $left_items = array_keys( $pairs );
        $right_items = array_values( $pairs );
        
        // Randomize right items for the dropdown
        $shuffled_right = $right_items;
        shuffle( $shuffled_right );

        ob_start();
        ?>
        <div class="cortex-matching-container">
            <div class="cortex-matching-pairs">
                <?php foreach ( $left_items as $index => $left_item ) : ?>
                    <div class="cortex-matching-row">
                        <div class="cortex-matching-left"><?php echo esc_html( $left_item ); ?></div>
                        <div class="cortex-matching-right">
                            <select name="answers[<?php echo esc_attr( $question_id ); ?>][<?php echo esc_attr( $index ); ?>]" required>
                                <option value="">-- <?php esc_html_e( 'Select Match', 'cortex' ); ?> --</option>
                                <?php foreach ( $shuffled_right as $right_item ) : ?>
                                    <option value="<?php echo esc_attr( $right_item ); ?>"><?php echo esc_html( $right_item ); ?></option>
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
     * Render sequence question
     */
    protected function render_sequence_question( $question ) {
        $question_id = absint( $question['id'] );
        $options = is_array( $question['options'] ) ? $question['options'] : array();
        
        // Shuffle options for the user to reorder
        $shuffled_options = $options;
        shuffle( $shuffled_options );

        ob_start();
        ?>
        <div class="cortex-sequence-container" data-question-id="<?php echo esc_attr( $question_id ); ?>">
            <p class="description"><?php esc_html_e( 'Drag and drop items into the correct order:', 'cortex' ); ?></p>
            <ul class="cortex-sequence-list cortex-sortable">
                <?php foreach ( $shuffled_options as $index => $option ) : ?>
                    <li class="cortex-sequence-item" data-value="<?php echo esc_attr( $option ); ?>">
                        <span class="dashicons dashicons-menu"></span>
                        <?php echo esc_html( $option ); ?>
                        <input type="hidden" name="answers[<?php echo esc_attr( $question_id ); ?>][]" value="<?php echo esc_attr( $option ); ?>">
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render lead capture form
     * 
     * @param array $quiz Quiz data
     * @return string HTML
     */
    protected function render_lead_capture_form( $quiz ) {
        $lead_fields = isset( $quiz['lead_fields'] ) ? $quiz['lead_fields'] : array();
        
        // Default fields if none defined
        if ( empty( $lead_fields ) ) {
            $lead_fields = array(
                array( 'id' => 'name', 'label' => __( 'Name', 'cortex' ), 'type' => 'text', 'required' => true ),
                array( 'id' => 'email', 'label' => __( 'Email', 'cortex' ), 'type' => 'email', 'required' => true ),
            );
        }

        ob_start();
        ?>
        <div class="cortex-lead-capture">
            <h3 class="cortex-lead-title"><?php esc_html_e( 'Your Information', 'cortex' ); ?></h3>
            <div class="cortex-lead-fields">
                <?php foreach ( $lead_fields as $field ) : ?>
                    <?php
                    $id = esc_attr( $field['id'] );
                    $label = esc_html( $field['label'] );
                    $type = esc_attr( $field['type'] );
                    $required = ! empty( $field['required'] ) ? 'required' : '';
                    ?>
                    <div class="cortex-field">
                        <label for="lead_<?php echo $id; ?>"><?php echo $label; ?> <?php if ( $required ) echo '<span class="required">*</span>'; ?></label>
                        <?php if ( $type === 'dropdown' ) : ?>
                            <select name="lead[<?php echo $id; ?>]" id="lead_<?php echo $id; ?>" <?php echo $required; ?>>
                                <option value=""><?php esc_html_e( '-- Select --', 'cortex' ); ?></option>
                                <?php foreach ( $field['options'] as $opt ) : ?>
                                    <option value="<?php echo esc_attr( $opt ); ?>"><?php echo esc_html( $opt ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ( $type === 'checkbox' ) : ?>
                             <div class="cortex-checkbox-group">
                                <?php foreach ( $field['options'] as $opt ) : ?>
                                    <label><input type="checkbox" name="lead[<?php echo $id; ?>][]" value="<?php echo esc_attr( $opt ); ?>"> <?php echo esc_html( $opt ); ?></label>
                                <?php endforeach; ?>
                             </div>
                        <?php elseif ( $type === 'textarea' ) : ?>
                            <textarea name="lead[<?php echo $id; ?>]" id="lead_<?php echo $id; ?>" <?php echo $required; ?>></textarea>
                        <?php else : ?>
                            <input type="<?php echo $type; ?>" name="lead[<?php echo $id; ?>]" id="lead_<?php echo $id; ?>" <?php echo $required; ?>>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <div class="cortex-field cortex-consent-field">
                    <label class="cortex-consent-label">
                        <input type="checkbox" name="lead_consent" value="1" required>
                        <span><?php esc_html_e( 'I consent to having this website store my submitted information.', 'cortex' ); ?> <span class="required">*</span></span>
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
        Cortex_Security::verify_nonce_or_die( 'cortex_quiz_submit_' . $quiz_id, 'cortex_quiz_nonce' );

        // Rate limit check
        $rate_key = 'quiz_' . $quiz_id . '_' . Cortex_Security::get_user_ip();
        if ( ! Cortex_Security::check_rate_limit( $rate_key, 5, 3600 ) ) {
            return '<div class="cortex-error">' . esc_html__( 'Too many submissions. Please try again later.', 'cortex' ) . '</div>';
        }

        // Sanitize answers
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $raw_answers = isset( $_POST['answers'] ) ? wp_unslash( $_POST['answers'] ) : array();
        $answers = Cortex_Security::sanitize_answers_array( $raw_answers );

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
        $quiz_model = new Cortex_Quiz();
        $attempt_id = $quiz_model->record_attempt( $quiz_id, $attempt_data );

        if ( ! $attempt_id ) {
            return '<div class="cortex-error">' . esc_html__( 'Failed to record quiz attempt. Please try again.', 'cortex' ) . '</div>';
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

        $raw_lead = isset( $_POST['lead'] ) ? (array) $_POST['lead'] : array();
        $lead_data = array();
        
        // Extract standard fields for potential separate storage if needed,
        // but primarily we wrap everything in custom_fields for dynamic support.
        $lead_data['name']  = isset( $raw_lead['name'] ) ? sanitize_text_field( $raw_lead['name'] ) : '';
        $lead_data['email'] = isset( $raw_lead['email'] ) ? sanitize_email( $raw_lead['email'] ) : '';
        $lead_data['consent_given'] = 1;
        $lead_data['custom_fields'] = $raw_lead; // All lead fields are stored here

        $lead_capture = new Cortex_Lead_Capture();
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
        <div class="cortex-quiz-results" data-tier="<?php echo esc_attr( $tier ); ?>">
            <div class="cortex-results-header">
                <h2><?php esc_html_e( 'Quiz Results', 'cortex' ); ?></h2>
            </div>

            <div class="cortex-results-score">
                <div class="cortex-score-circle">
                    <span class="cortex-score-value"><?php echo esc_html( number_format( $percentage, 1 ) ); ?>%</span>
                </div>
                <div class="cortex-score-details">
                    <p class="cortex-points">
                        <?php
                        echo esc_html( sprintf(
                            __( 'You scored %1$s out of %2$s points', 'cortex' ),
                            number_format( $attempt['obtained_marks'], 2 ),
                            number_format( $attempt['total_marks'], 2 )
                        ) );
                        ?>
                    </p>
                    <?php if ( $attempt['time_taken'] > 0 ) : ?>
                        <p class="cortex-time-taken">
                            <?php echo esc_html( sprintf( __( 'Time taken: %s', 'cortex' ), gmdate( 'i:s', $attempt['time_taken'] ) ) ); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="cortex-results-message cortex-tier-<?php echo esc_attr( $tier ); ?>">
                <?php
                switch ( $tier ) {
                    case 'high':
                        echo '<p class="cortex-congratulations">' . esc_html__( '🎉 Excellent work! You\'ve demonstrated mastery of this material.', 'cortex' ) . '</p>';
                        break;
                    case 'medium':
                        echo '<p class="cortex-good-job">' . esc_html__( '👍 Good job! You have a solid understanding.', 'cortex' ) . '</p>';
                        break;
                    case 'low':
                        echo '<p class="cortex-encouragement">' . esc_html__( '📚 Keep practicing! Review the material and try again.', 'cortex' ) . '</p>';
                        break;
                }
                ?>
            </div>

            <?php
            // Social sharing (privacy-aware)
            $share_text = sprintf( __( 'I scored %s%% on this quiz!', 'cortex' ), number_format( $percentage, 0 ) );
            $share_url = get_permalink();
            ?>
            <div class="cortex-results-share">
                <p class="cortex-share-label"><?php esc_html_e( 'Share your results:', 'cortex' ); ?></p>
                <div class="cortex-share-buttons">
                    <a href="https://twitter.com/intent/tweet?text=<?php echo rawurlencode( $share_text ); ?>&url=<?php echo rawurlencode( $share_url ); ?>" target="_blank" rel="noopener" class="cortex-share-twitter">
                        <?php esc_html_e( 'Twitter', 'cortex' ); ?>
                    </a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo rawurlencode( $share_url ); ?>" target="_blank" rel="noopener" class="cortex-share-facebook">
                        <?php esc_html_e( 'Facebook', 'cortex' ); ?>
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
        wp_enqueue_style( 'cortex-quiz', CORTEX_PLUGIN_URL . 'assets/css/cortex-public.css', array(), CORTEX_VERSION );
        wp_enqueue_script( 'cortex-quiz', CORTEX_PLUGIN_URL . 'assets/js/cortex-public.js', array( 'jquery', 'jquery-ui-sortable' ), CORTEX_VERSION, true );

        // Localize script
        wp_localize_script( 'cortex-quiz', 'mmQuiz', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'cortex_quiz_ajax' ),
        ) );
    }

    /**
     * Enqueue code highlighting assets
     * 
     * @param string $language Specific language to load
     */
    protected function enqueue_code_assets( $language = '' ) {
        if ( class_exists( 'Cortex_Highlighter' ) ) {
            $languages = ! empty( $language ) ? array( $language ) : array();
            Cortex_Highlighter::enqueue_assets( $languages );
        }
    }
}

    // Initialize shortcodes
    add_action( 'init', function() {
        if ( ! isset( $GLOBALS['cortex_shortcodes_loaded'] ) ) {
            $GLOBALS['cortex_shortcodes_loaded'] = new Cortex_Shortcodes();
        }
    } );
}
