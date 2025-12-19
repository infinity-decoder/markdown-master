<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="mm-admin-wrap">
    <header class="mm-header">
        <h1>Markdown Master</h1>
        <div class="mm-header-actions">
            <a href="<?php echo admin_url('admin.php?page=mm_settings'); ?>" class="mm-btn mm-btn-light">Settings</a>
        </div>
    </header>

    <div class="mm-dashboard-grid">
        <a href="<?php echo admin_url('admin.php?page=mm_quizzes'); ?>" class="mm-dashboard-card">
            <div class="mm-card-icon">📝</div>
            <h3>Quizzes</h3>
            <p>Create and manage advanced quizzes with 11 question types, timers, and lead capture.</p>
            <div class="mm-card-footer">
                <span>Manage Quizzes</span>
                <i class="dashicons dashicons-arrow-right-alt2"></i>
            </div>
        </a>

        <a href="<?php echo admin_url('admin.php?page=mm_markdown_snippets'); ?>" class="mm-dashboard-card">
            <div class="mm-card-icon">📄</div>
            <h3>Markdown</h3>
            <p>Write reusable markdown snippets with KaTeX support for beautiful math rendering.</p>
            <div class="mm-card-footer">
                <span>Manage Snippets</span>
                <i class="dashicons dashicons-arrow-right-alt2"></i>
            </div>
        </a>

        <a href="<?php echo admin_url('admin.php?page=mm_code_snippets'); ?>" class="mm-dashboard-card">
            <div class="mm-card-icon">💻</div>
            <h3>Code Snippets</h3>
            <p>Save and embed syntax-highlighted code using Prism.js or Highlight.js.</p>
            <div class="mm-card-footer">
                <span>Manage Code</span>
                <i class="dashicons dashicons-arrow-right-alt2"></i>
            </div>
        </a>

        <a href="<?php echo admin_url('admin.php?page=mm_results'); ?>" class="mm-dashboard-card">
            <div class="mm-card-icon">📊</div>
            <h3>Results</h3>
            <p>View quiz attempts, scores, and captured lead data with deep analytics.</p>
            <div class="mm-card-footer">
                <span>View Analytics</span>
                <i class="dashicons dashicons-arrow-right-alt2"></i>
            </div>
        </a>
    </div>
</div>
