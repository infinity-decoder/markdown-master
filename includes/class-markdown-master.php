<?php

if (!defined('ABSPATH')) {
    exit;
}

class Markdown_Master {
    
    public function __construct() {
        $this->load_dependencies();
        $this->initialize_components();
    }

    // Load required classes
    private function load_dependencies() {
        require_once MARKDOWN_MASTER_PLUGIN_DIR . 'includes/class-markdown-parser.php';
        require_once MARKDOWN_MASTER_PLUGIN_DIR . 'includes/class-syntax-highlighter.php';
        require_once MARKDOWN_MASTER_PLUGIN_DIR . 'includes/class-shortcode-handler.php';
    }

    // Initialize core components
    private function initialize_components() {
        new Markdown_Parser();
        new Syntax_Highlighter();
        new Shortcode_Handler();
    }

    // Actions to perform on activation
    public static function activate() {
        flush_rewrite_rules();
    }

    // Actions to perform on deactivation
    public static function deactivate() {
        flush_rewrite_rules();
    }
}
