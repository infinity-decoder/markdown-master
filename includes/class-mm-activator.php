<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MM_Activator {

    public static function activate() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        // Quizzes Table
        $sql_quizzes = "CREATE TABLE {$wpdb->prefix}mm_quizzes (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            type VARCHAR(50) NOT NULL, -- mcq, short, survey
            settings LONGTEXT NULL, -- JSON
            created_by BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Questions Table
        $sql_questions = "CREATE TABLE {$wpdb->prefix}mm_quiz_questions (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            quiz_id BIGINT(20) UNSIGNED NOT NULL,
            question TEXT NOT NULL,
            image VARCHAR(255) NULL,
            type VARCHAR(50) NOT NULL, -- mcq, short
            correct_answer LONGTEXT NULL,
            options LONGTEXT NULL, -- JSON for MCQs
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY quiz_id (quiz_id)
        ) $charset_collate;";

        // Answers Table
        $sql_answers = "CREATE TABLE {$wpdb->prefix}mm_quiz_answers (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            question_id BIGINT(20) UNSIGNED NOT NULL,
            answer_text TEXT NULL,
            answer_image VARCHAR(255) NULL,
            is_correct TINYINT(1) DEFAULT 0,
            PRIMARY KEY  (id),
            KEY question_id (question_id)
        ) $charset_collate;";

        // Attempts Table
        $sql_attempts = "CREATE TABLE {$wpdb->prefix}mm_quiz_attempts (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            quiz_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NULL,
            user_name VARCHAR(255) NULL,
            user_email VARCHAR(255) NULL,
            user_class VARCHAR(255) NULL,
            user_section VARCHAR(255) NULL,
            score FLOAT DEFAULT 0,
            started_at DATETIME NULL,
            completed_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY quiz_id (quiz_id)
        ) $charset_collate;";

        // Results Table
        $sql_results = "CREATE TABLE {$wpdb->prefix}mm_quiz_results (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            attempt_id BIGINT(20) UNSIGNED NOT NULL,
            question_id BIGINT(20) UNSIGNED NOT NULL,
            given_answer TEXT NULL,
            is_correct TINYINT(1) DEFAULT 0,
            PRIMARY KEY  (id),
            KEY attempt_id (attempt_id)
        ) $charset_collate;";

        // Notes Table
        $sql_notes = "CREATE TABLE {$wpdb->prefix}mm_notes (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            content LONGTEXT NOT NULL,
            created_by BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Code Snippets Table
        $sql_snippets = "CREATE TABLE {$wpdb->prefix}mm_code_snippets (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            code LONGTEXT NOT NULL,
            language VARCHAR(50) NOT NULL,
            created_by BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Settings Table
        $sql_settings = "CREATE TABLE {$wpdb->prefix}mm_settings (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            option_name VARCHAR(255) NOT NULL,
            option_value LONGTEXT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY option_name (option_name)
        ) $charset_collate;";

        // Run all table creations
        dbDelta($sql_quizzes);
        dbDelta($sql_questions);
        dbDelta($sql_answers);
        dbDelta($sql_attempts);
        dbDelta($sql_results);
        dbDelta($sql_notes);
        dbDelta($sql_snippets);
        dbDelta($sql_settings);

        // Add default plugin options
        add_option( 'mm_version', MM_VERSION );
        add_option( 'mm_settings', json_encode([
            'show_answers'      => 'end', // or 'instant'
            'theme'             => 'default',
            'timer_enabled'     => false,
            'randomize_questions' => false,
            'max_attempts'      => 0
        ]) );
    }
}
