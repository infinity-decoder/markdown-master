<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="cortex-admin-wrap">
    <header class="cortex-header">
        <h1>Cortex</h1>
        <div class="cortex-header-actions">
            <a href="<?php echo admin_url('admin.php?page=cortex_settings'); ?>" class="cortex-btn cortex-btn-light">Settings</a>
        </div>
    </header>

    <div class="cortex-dashboard-grid">
        <a href="<?php echo admin_url('admin.php?page=cortex_quizzes'); ?>" class="cortex-dashboard-card">
            <div class="cortex-card-icon">📝</div>
            <h3>Quizzes</h3>
            <p>Create and manage advanced quizzes with 11 question types, timers, and lead capture.</p>
            <div class="cortex-card-footer">
                <span>Manage Quizzes</span>
                <i class="dashicons dashicons-arrow-right-alt2"></i>
            </div>
        </a>

        <a href="<?php echo admin_url('admin.php?page=cortex_markdown_snippets'); ?>" class="cortex-dashboard-card">
            <div class="cortex-card-icon">📄</div>
            <h3>Markdown</h3>
            <p>Write reusable markdown snippets with KaTeX support for beautiful math rendering.</p>
            <div class="cortex-card-footer">
                <span>Manage Snippets</span>
                <i class="dashicons dashicons-arrow-right-alt2"></i>
            </div>
        </a>

        <a href="<?php echo admin_url('admin.php?page=cortex_code_snippets'); ?>" class="cortex-dashboard-card">
            <div class="cortex-card-icon">💻</div>
            <h3>Code Snippets</h3>
            <p>Save and embed syntax-highlighted code using Prism.js or Highlight.js.</p>
            <div class="cortex-card-footer">
                <span>Manage Code</span>
                <i class="dashicons dashicons-arrow-right-alt2"></i>
            </div>
        </a>

        <a href="<?php echo admin_url('admin.php?page=cortex_results'); ?>" class="cortex-dashboard-card">
            <div class="cortex-card-icon">📊</div>
            <h3>Results</h3>
            <p>View quiz attempts, scores, and captured lead data with deep analytics.</p>
            <div class="cortex-card-footer">
                <span>View Analytics</span>
                <i class="dashicons dashicons-arrow-right-alt2"></i>
            </div>
        </a>
    </div>
</div>
