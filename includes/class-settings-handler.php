<?php

if (!defined('ABSPATH')) exit;

class Settings_Handler {
    
    public function __construct() {
        add_action('admin_post_create_markdown', [$this, 'save_markdown']);
        add_action('admin_post_create_quiz', [$this, 'save_quiz']);
    }

    public function save_markdown() {
        if (!isset($_POST['markdown_nonce']) || !wp_verify_nonce($_POST['markdown_nonce'], 'create_markdown')) {
            wp_die(__('Security check failed.'));
        }

        global $wpdb;
        $markdown_table = $wpdb->prefix . "markdown_master_markdown";
        $wpdb->insert($markdown_table, ['content' => wp_kses_post($_POST['markdown_content'])]);

        wp_redirect(admin_url('admin.php?page=markdown-master&markdown_saved=true'));
        exit;
    }

    public function save_quiz() {
        if (!isset($_POST['quiz_nonce']) || !wp_verify_nonce($_POST['quiz_nonce'], 'create_quiz')) {
            wp_die(__('Security check failed.'));
        }

        global $wpdb;
        $quiz_table = $wpdb->prefix . "markdown_master_quizzes";
        $wpdb->insert($quiz_table, ['content' => wp_kses_post($_POST['quiz_content'])]);

        wp_redirect(admin_url('admin.php?page=markdown-master&quiz_saved=true'));
        exit;
    }
}
