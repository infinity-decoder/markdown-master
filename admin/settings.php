<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

?>

<div class="wrap">
    <h1>Markdown Master Settings</h1>

    <h2>Create Markdown</h2>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('create_markdown', 'markdown_nonce'); ?>
        <textarea name="markdown_content" rows="6" style="width:100%;" required></textarea>
        <br>
        <button type="submit" class="button button-primary">Save Markdown</button>
    </form>

    <h2>Create Quiz</h2>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('create_quiz', 'quiz_nonce'); ?>
        <textarea name="quiz_content" rows="6" style="width:100%;" required></textarea>
        <br>
        <button type="submit" class="button button-primary">Save Quiz</button>
    </form>
</div>
