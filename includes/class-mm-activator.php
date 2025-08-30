<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles database creation & upgrades for Markdown Master.
 */
class MM_Activator {

    /**
     * Bump this when changing schema.
     * Stored in option 'mm_schema_version'.
     */
    const SCHEMA_VERSION = '1.0.0';

    /**
     * Called on plugin activation.
     */
    public static function activate() {
        self::create_or_upgrade_tables();
        update_option( 'mm_schema_version', self::SCHEMA_VERSION );
    }

    /**
     * Silent upgrade on normal loads (called by loader if available).
     * Runs dbDelta only when stored version differs.
     */
    public static function maybe_upgrade() {
        $current = get_option( 'mm_schema_version', '' );
        if ( version_compare( (string) $current, (string) self::SCHEMA_VERSION, '<' ) ) {
            self::create_or_upgrade_tables();
            update_option( 'mm_schema_version', self::SCHEMA_VERSION );
        }
    }

    /**
     * Create or upgrade all plugin tables with dbDelta.
     */
    private static function create_or_upgrade_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $table_quizzes          = $wpdb->prefix . 'mm_quizzes';
        $table_questions        = $wpdb->prefix . 'mm_questions';
        $table_attempts         = $wpdb->prefix . 'mm_attempts';
        $table_attempt_answers  = $wpdb->prefix . 'mm_attempt_answers';

        // Quizzes
        $sql_quizzes = "CREATE TABLE {$table_quizzes} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL DEFAULT '',
            description LONGTEXT NULL,
            settings LONGTEXT NULL,
            shuffle TINYINT(1) NOT NULL DEFAULT 0,
            time_limit INT NOT NULL DEFAULT 0,
            attempts_allowed INT NOT NULL DEFAULT 0,
            show_answers TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";

        // Questions
        $sql_questions = "CREATE TABLE {$table_questions} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            quiz_id BIGINT UNSIGNED NOT NULL,
            question_text LONGTEXT NOT NULL,
            type VARCHAR(20) NOT NULL DEFAULT 'single',
            options LONGTEXT NULL,
            correct_answer LONGTEXT NULL,
            points INT NOT NULL DEFAULT 1,
            image VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            KEY idx_quiz_id (quiz_id)
        ) {$charset_collate};";

        // Attempts
        $sql_attempts = "CREATE TABLE {$table_attempts} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            quiz_id BIGINT UNSIGNED NOT NULL,
            student_name VARCHAR(191) NULL,
            student_class VARCHAR(50) NULL,
            student_section VARCHAR(50) NULL,
            student_school VARCHAR(191) NULL,
            student_roll VARCHAR(50) NULL,
            obtained_marks DECIMAL(10,2) NOT NULL DEFAULT 0,
            total_marks DECIMAL(10,2) NOT NULL DEFAULT 0,
            answers LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            KEY idx_quiz_id (quiz_id),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";

        // Attempt answers (optional per-question row)
        $sql_attempt_answers = "CREATE TABLE {$table_attempt_answers} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            attempt_id BIGINT UNSIGNED NOT NULL,
            question_id BIGINT UNSIGNED NOT NULL,
            answer LONGTEXT NULL,
            is_correct TINYINT(1) NOT NULL DEFAULT 0,
            points_awarded DECIMAL(10,2) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            KEY idx_attempt_id (attempt_id),
            KEY idx_question_id (question_id)
        ) {$charset_collate};";

        // dbDelta can receive multiple statements.
        dbDelta( $sql_quizzes );
        dbDelta( $sql_questions );
        dbDelta( $sql_attempts );
        dbDelta( $sql_attempt_answers );
    }
}
