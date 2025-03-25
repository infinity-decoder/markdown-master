<?php
/**
 * Plugin Name: Markdown Master
 * Plugin URI: https://infinitydecoder.net
 * Description: A Markdown and Code Writing Plugin for WordPress.
 * Version: 1.0.0
 * Author: Infinity Decoder
 * Author URI: https://infinitydecoder.net
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define Plugin Path
define('MARKDOWN_MASTER_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Load the Main Plugin Class
require_once MARKDOWN_MASTER_PLUGIN_DIR . 'includes/class-markdown-master.php';

// Enqueue Scripts Properly
function markdown_master_enqueue_scripts() {
    if (is_admin()) {
        wp_enqueue_script(
            'markdown-it', 
            plugin_dir_url(__FILE__) . 'assets/vendor/markdown-it.min.js', 
            array(), 
            '12.0.0', 
            true
        );
    }
}
add_action('admin_enqueue_scripts', 'markdown_master_enqueue_scripts');

// Activation Hook
function markdown_master_activate() {
    Markdown_Master::activate();
}
register_activation_hook(__FILE__, 'markdown_master_activate');

// Deactivation Hook
function markdown_master_deactivate() {
    Markdown_Master::deactivate();
}
register_deactivation_hook(__FILE__, 'markdown_master_deactivate');

// Initialize Plugin
function run_markdown_master() {
    new Markdown_Master();
}
add_action('plugins_loaded', 'run_markdown_master');
