<?php

if (!defined('ABSPATH')) {
    exit;
}

class Markdown_Parser {

    public function __construct() {
        add_action('init', [$this, 'register_markdown_parser']);
    }

    public function register_markdown_parser() {
        require_once MARKDOWN_MASTER_PLUGIN_DIR . 'assets/vendor/markdown-it.min.js';
    }

    public function parse_markdown($content) {
        // Using JavaScript Markdown-It library for parsing
        ob_start();
        ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                let markdownIt = new markdownit();
                let content = `<?php echo esc_js($content); ?>`;
                document.getElementById("markdown-content").innerHTML = markdownIt.render(content);
            });
        </script>
        <div id="markdown-content"></div>
        <?php
        return ob_get_clean();
    }
}
