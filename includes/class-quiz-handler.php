<?php

if (!defined('ABSPATH')) {
    exit;
}

class Quiz_Handler {

    public function __construct() {
        add_shortcode('quiz', [$this, 'render_quiz_shortcode']);
        add_action('admin_post_save_quiz', [$this, 'save_quiz']);
        add_action('wp_ajax_submit_quiz', [$this, 'submit_quiz']);
        add_action('wp_ajax_nopriv_submit_quiz', [$this, 'submit_quiz']);
    }

    public function render_quiz_shortcode($atts) {
        $atts = shortcode_atts(['id' => ''], $atts, 'quiz');
        if (!$atts['id']) {
            return '<p>Error: Quiz ID missing.</p>';
        }

        global $wpdb;
        $quiz_table = $wpdb->prefix . "markdown_master_quizzes";
        $quiz = $wpdb->get_row($wpdb->prepare("SELECT * FROM $quiz_table WHERE id = %d", $atts['id']), ARRAY_A);

        if (!$quiz) {
            return '<p>Error: Quiz not found.</p>';
        }

        ob_start();
        ?>
        <div class="quiz-container" data-quiz-id="<?php echo esc_attr($atts['id']); ?>">
            <form id="quiz-form-<?php echo esc_attr($atts['id']); ?>">
                <?php echo stripslashes($quiz['content']); ?>
                <input type="text" name="user_name" placeholder="Your Name" required>
                <input type="email" name="user_email" placeholder="Your Email" required>
                <input type="hidden" name="quiz_id" value="<?php echo esc_attr($atts['id']); ?>">
                <button type="submit">Submit</button>
            </form>
            <div class="quiz-result"></div>
        </div>
        <script>
            document.getElementById("quiz-form-<?php echo esc_attr($atts['id']); ?>").addEventListener("submit", function(e) {
                e.preventDefault();
                let formData = new FormData(this);
                fetch("<?php echo admin_url('admin-ajax.php'); ?>", {
                    method: "POST",
                    body: formData
                }).then(response => response.json()).then(data => {
                    document.querySelector(".quiz-result").innerHTML = data.message;
                });
            });
        </script>
        <?php
        return ob_get_clean();
    }

    public function save_quiz() {
        if (!isset($_POST['quiz_content']) || !current_user_can('manage_options')) {
            return;
        }

        global $wpdb;
        $quiz_table = $wpdb->prefix . "markdown_master_quizzes";
        $wpdb->insert($quiz_table, ['content' => wp_kses_post($_POST['quiz_content'])]);

        wp_redirect(admin_url('admin.php?page=markdown-master&quiz_saved=true'));
        exit;
    }

    public function submit_quiz() {
    check_ajax_referer('submit_quiz_nonce', 'security'); // Verify nonce

    global $wpdb;
    $responses_table = $wpdb->prefix . "markdown_master_responses";

    $quiz_id = intval($_POST['quiz_id']);
    $user_name = sanitize_text_field($_POST['user_name']);
    $user_email = sanitize_email($_POST['user_email']);

    if (empty($user_name) || empty($user_email)) {
        wp_send_json(['message' => 'Invalid input.'], 400);
    }

    $wpdb->insert($responses_table, [
        'quiz_id' => $quiz_id,
        'user_name' => $user_name,
        'user_email' => $user_email,
        'score' => 0 // Future: Calculate score
    ]);

        wp_send_json(['message' => 'Quiz submitted successfully.']);
    }
}
