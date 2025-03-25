<?php

if (!defined('ABSPATH')) {
    exit;
}

class Admin_Menu {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_menu']);
    }

    public function register_admin_menu() {
        add_menu_page(
            'Markdown Master',
            'Markdown Master',
            'manage_options',
            'markdown-master',
            [$this, 'render_admin_page'],
            'dashicons-editor-code',
            20
        );
    }

    public function render_admin_page() {
        require_once MARKDOWN_MASTER_PLUGIN_DIR . 'admin/settings-page.php';
    }
}
