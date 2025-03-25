<?php

if (!defined('ABSPATH')) {
    exit;
}

class Shortcode_Handler {

    public function __construct() {
        add_shortcode('markdown', [$this, 'render_markdown_shortcode']);
        add_shortcode('quiz', [$this, 'render_quiz_shortcode']);
    }

    /**
     * Render Markdown Shortcode
     */
    public function render_markdown_shortcode($atts) {
        $atts = shortcode_atts(['id' => ''], $atts, 'markdown');
        if (!$atts['id']) {
            return '<p>Error: Markdown ID missing.</p>';
        }

        global $wpdb;
        $markdown_table = $wpdb->prefix . "markdown_master_markdown";
        $markdown = $wpdb->get_row($wpdb->prepare("SELECT * FROM $markdown_table WHERE id = %d", intval($atts['id'])), ARRAY_A);

        if (!$markdown) {
            return '<p>Error: Markdown content not found.</p>';
        }

        $parser = new Markdown_Parser();
        return '<div class="markdown-content">' . $parser->parse_markdown(stripslashes($markdown['content'])) . '</div>';
    }

    /**
     * Render Quiz Shortcode
     */
    public function render_quiz_shortcode($atts) {
        $atts = shortcode_atts(['id' => ''], $atts, 'quiz');
        if (!$atts['id']) {
            return '<p>Error: Quiz ID missing.</p>';
        }

        global $wpdb;
        $quiz_table = $wpdb->prefix . "markdown_master_quizzes";
        $quiz = $wpdb->get_row($wpdb->prepare("SELECT * FROM $quiz_table WHERE id = %d", intval($atts['id'])), ARRAY_A);

        if (!$quiz) {
            return '<p>Error: Quiz not found.</p>';
        }

        ob_start();
        ?>
        <div class="quiz-container" id="quiz-<?php echo esc_attr($atts['id']); ?>" data-quiz-id="<?php echo esc_attr($atts['id']); ?>">
            <h3>Quiz</h3>
            <div class="quiz-content">
                <?php echo stripslashes($quiz['content']); ?>
            </div>
            <form class="quiz-form">
                <input type="hidden" name="quiz_id" value="<?php echo esc_attr($atts['id']); ?>">
                <label>Name: <input type="text" name="user_name" required></label>
                <label>Email: <input type="email" name="user_email" required></label>
                <button type="submit">Submit Quiz</button>
            </form>
            <p class="quiz-result"></p>
        </div>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                let quizContainer = document.querySelector("#quiz-<?php echo esc_js($atts['id']); ?> .quiz-form");
                if (quizContainer) {
                    quizContainer.addEventListener("submit", function(event) {
                        event.preventDefault();
                        let formData = new FormData(this);
                        formData.append('action', 'submit_quiz');
                        formData.append('security', '<?php echo wp_create_nonce("submit_quiz_nonce"); ?>');

                        fetch('<?php echo esc_url(admin_url("admin-ajax.php")); ?>', {
                            method: "POST",
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            let result = quizContainer.parentNode.querySelector(".quiz-result");
                            result.textContent = data.message;
                        })
                        .catch(error => console.error("Error submitting quiz:", error));
                    });
                }
            });
        </script>

        <?php
        return ob_get_clean();
    }
}
