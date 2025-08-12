<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap">
    <h1>Markdown Master Dashboard</h1>
    <p>Welcome to <strong>Markdown Master</strong>. Use the sections below to manage quizzes, markdown notes, and code snippets.</p>

    <div class="mm-dashboard-cards">
        <div class="mm-card">
            <h2>Quizzes</h2>
            <p>Create, edit, or delete quizzes. Import/export quiz data.</p>
            <a class="button button-primary" href="<?php echo admin_url('admin.php?page=mm_quizzes'); ?>">Manage Quizzes</a>
        </div>
        <div class="mm-card">
            <h2>Markdown Notes</h2>
            <p>Manage all your markdown-based notes.</p>
            <a class="button button-primary" href="<?php echo admin_url('admin.php?page=mm_notes'); ?>">Manage Notes</a>
        </div>
        <div class="mm-card">
            <h2>Code Snippets</h2>
            <p>Create and maintain highlighted code snippets.</p>
            <a class="button button-primary" href="<?php echo admin_url('admin.php?page=mm_snippets'); ?>">Manage Snippets</a>
        </div>
        <div class="mm-card">
            <h2>Results</h2>
            <p>View quiz results and analytics.</p>
            <a class="button button-secondary" href="<?php echo admin_url('admin.php?page=mm_results'); ?>">View Results</a>
        </div>
        <div class="mm-card">
            <h2>Settings</h2>
            <p>Customize how your quizzes, markdown, and code display.</p>
            <a class="button" href="<?php echo admin_url('admin.php?page=mm_settings'); ?>">Settings</a>
        </div>
    </div>
</div>
