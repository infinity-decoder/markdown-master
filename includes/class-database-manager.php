<?php

if (!defined('ABSPATH')) {
    exit;
}

class Database_Manager {

    public static function install() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $quiz_table = $wpdb->prefix . "markdown_master_quizzes";
        $response_table = $wpdb->prefix . "markdown_master_responses";

        $sql = "
            CREATE TABLE IF NOT EXISTS $quiz_table (
                id INT AUTO_INCREMENT PRIMARY KEY,
                content TEXT NOT NULL
            ) $charset_collate;
            
            CREATE TABLE IF NOT EXISTS $response_table (
                id INT AUTO_INCREMENT PRIMARY KEY,
                quiz_id INT NOT NULL,
                user_name VARCHAR(255) NOT NULL,
                user_email VARCHAR(255) NOT NULL,
                score INT NOT NULL
            ) $charset_collate;
        ";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
