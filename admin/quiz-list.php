<?php

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$quiz_table = $wpdb->prefix . "markdown_master_quizzes";
$quizzes = $wpdb->get_results("SELECT * FROM $quiz_table", ARRAY_A);

?>

<div class="wrap">
    <h1>Quizzes</h1>
    <table class="widefat">
        <thead>
            <tr>
                <th>ID</th>
                <th>Content</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($quizzes as $quiz): ?>
            <tr>
                <td><?php echo esc_html($quiz['id']); ?></td>
                <td><?php echo esc_html(wp_trim_words($quiz['content'], 10)); ?></td>
                <td>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=quiz-results&quiz_id=' . $quiz['id'])); ?>">View Results</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
