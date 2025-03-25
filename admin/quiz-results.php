<?php

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="wrap">
    <h1>Quiz Results</h1>
    <a href="<?php echo esc_url(admin_url('admin-post.php?action=download_quiz_results&quiz_id=' . $_GET['quiz_id'])); ?>" class="button button-primary">
        Download Results
    </a>
</div>
