<?php

if (!defined('ABSPATH')) {
    exit;
}

class Syntax_Highlighter {

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_syntax_highlighter']);
    }

    public function enqueue_syntax_highlighter() {
        wp_enqueue_script('highlightjs', MARKDOWN_MASTER_PLUGIN_URL . 'assets/vendor/highlight.min.js', [], null, true);
        wp_enqueue_style('highlightjs-style', MARKDOWN_MASTER_PLUGIN_URL . 'assets/css/styles.css');
    }

    public function highlight_code($code, $language = 'plaintext') {
        return '<pre><code class="language-' . esc_attr($language) . '">' . esc_html($code) . '</code></pre>';
    }
}
