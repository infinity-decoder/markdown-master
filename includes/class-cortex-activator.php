<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles database creation & upgrades for Cortex.
 */
class Cortex_Activator {

    /**
     * Bump this when changing schema.
     * Stored in option 'cortex_schema_version'.
     */
    const SCHEMA_VERSION = '2.0.0';

    /**
     * Called on plugin activation.
     */
    public static function activate() {
        self::migrate_old_tables();
        self::create_or_upgrade_tables();
        update_option( 'cortex_schema_version', self::SCHEMA_VERSION );
        
        // Set default options
        $defaults = array(
            'cortex_allow_uninstall_data_deletion' => '1',
            'cortex_cache_ttl' => '3600',
            'cortex_math_renderer' => 'katex',
            'cortex_code_highlighter' => 'prism',
        );
        
        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( $key ) ) {
                add_option( $key, $value );
            }
        }

        // Migrate old options if they exist
        self::migrate_old_options();
    }

    /**
     * Silent upgrade on normal loads (called by loader if available).
     * Runs dbDelta only when stored version differs.
     */
    public static function maybe_upgrade() {
        $current = get_option( 'cortex_schema_version', '' );
        if ( version_compare( (string) $current, (string) self::SCHEMA_VERSION, '<' ) ) {
            self::create_or_upgrade_tables();
            update_option( 'cortex_schema_version', self::SCHEMA_VERSION );
        }
    }

    /**
     * Rename old mm_ tables to cortex_ tables if they exist.
     */
    private static function migrate_old_tables() {
        global $wpdb;
        $tables = array(
            'quizzes', 'questions', 'attempts', 'attempt_answers', 
            'question_bank', 'question_bank_items', 'lead_captures',
            'markdown_snippets', 'code_snippets'
        );

        foreach ( $tables as $table ) {
            $old_name = $wpdb->prefix . 'mm_' . $table;
            $new_name = $wpdb->prefix . 'cortex_' . $table;

            // Check if old exists and new does NOT exist
            if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $old_name ) ) === $old_name ) {
                if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $new_name ) ) !== $new_name ) {
                    $wpdb->query( "RENAME TABLE {$old_name} TO {$new_name}" );
                }
            }
        }
    }

    /**
     * Migrate old mm_ options to cortex_ options.
     */
    private static function migrate_old_options() {
        $map = array(
            'mm_allow_uninstall_data_deletion' => 'cortex_allow_uninstall_data_deletion',
            'mm_cache_ttl' => 'cortex_cache_ttl',
            'mm_math_renderer' => 'cortex_math_renderer',
            'mm_code_highlighter' => 'cortex_code_highlighter',
            'mm_schema_version' => 'cortex_schema_version',
        );

        foreach ( $map as $old => $new ) {
            $val = get_option( $old );
            if ( $val !== false && get_option( $new ) === false ) {
                update_option( $new, $val );
                // Optional: delete_option( $old ); // Keeping for safety for now
            }
        }
    }

    /**
     * Create or upgrade all plugin tables with dbDelta.
     */
    private static function create_or_upgrade_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        // Table names
        $table_quizzes              = $wpdb->prefix . 'cortex_quizzes';
        $table_questions            = $wpdb->prefix . 'cortex_questions';
        $table_attempts             = $wpdb->prefix . 'cortex_attempts';
        $table_attempt_answers      = $wpdb->prefix . 'cortex_attempt_answers';
        $table_question_bank        = $wpdb->prefix . 'cortex_question_bank';
        $table_question_bank_items  = $wpdb->prefix . 'cortex_question_bank_items';
        $table_lead_captures        = $wpdb->prefix . 'cortex_lead_captures';
        $table_markdown_snippets    = $wpdb->prefix . 'cortex_markdown_snippets';
        $table_code_snippets        = $wpdb->prefix . 'cortex_code_snippets';

        /**
         * TABLE 1: cortex_quizzes
         */
        $sql_quizzes = "CREATE TABLE {$table_quizzes} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            quiz_uuid VARCHAR(36) NOT NULL,
            title VARCHAR(255) NOT NULL DEFAULT '',
            description LONGTEXT NULL,
            settings LONGTEXT NULL,
            lead_fields LONGTEXT NULL,
            time_limit INT UNSIGNED NOT NULL DEFAULT 0,
            attempts_allowed INT UNSIGNED NOT NULL DEFAULT 0,
            show_answers TINYINT(1) NOT NULL DEFAULT 0,
            randomize_questions TINYINT(1) NOT NULL DEFAULT 0,
            randomize_answers TINYINT(1) NOT NULL DEFAULT 0,
            questions_per_page INT UNSIGNED NOT NULL DEFAULT 0,
            show_welcome_screen TINYINT(1) NOT NULL DEFAULT 0,
            welcome_content LONGTEXT NULL,
            scheduled_start DATETIME NULL,
            scheduled_end DATETIME NULL,
            require_login TINYINT(1) NOT NULL DEFAULT 0,
            required_role VARCHAR(50) NULL,
            max_total_attempts INT UNSIGNED NOT NULL DEFAULT 0,
            max_user_attempts INT UNSIGNED NOT NULL DEFAULT 0,
            pass_percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
            enable_lead_capture TINYINT(1) NOT NULL DEFAULT 0,
            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            UNIQUE KEY idx_quiz_uuid (quiz_uuid),
            KEY idx_created_at (created_at),
            KEY idx_created_by (created_by),
            KEY idx_scheduled (scheduled_start, scheduled_end)
        ) {$charset_collate};";

        /**
         * TABLE 2: cortex_questions
         */
        $sql_questions = "CREATE TABLE {$table_questions} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            quiz_id BIGINT UNSIGNED NOT NULL,
            question_bank_id BIGINT UNSIGNED NULL,
            question_text LONGTEXT NOT NULL,
            type VARCHAR(20) NOT NULL DEFAULT 'radio',
            options LONGTEXT NULL,
            correct_answer LONGTEXT NULL,
            points DECIMAL(10,2) NOT NULL DEFAULT 1,
            hint LONGTEXT NULL,
            allow_comment TINYINT(1) NOT NULL DEFAULT 0,
            question_order INT UNSIGNED NOT NULL DEFAULT 0,
            metadata LONGTEXT NULL,
            image VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            KEY idx_quiz_id (quiz_id),
            KEY idx_question_bank_id (question_bank_id),
            KEY idx_question_order (quiz_id, question_order),
            KEY idx_type (type)
        ) {$charset_collate};";

        /**
         * TABLE 3: cortex_attempts
         */
        $sql_attempts = "CREATE TABLE {$table_attempts} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            quiz_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            student_name VARCHAR(191) NULL,
            student_class VARCHAR(50) NULL,
            student_section VARCHAR(50) NULL,
            student_school VARCHAR(191) NULL,
            student_roll VARCHAR(50) NULL,
            obtained_marks DECIMAL(10,2) NOT NULL DEFAULT 0,
            total_marks DECIMAL(10,2) NOT NULL DEFAULT 0,
            result_tier VARCHAR(50) NULL,
            time_taken INT UNSIGNED NOT NULL DEFAULT 0,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            answers LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            KEY idx_quiz_id (quiz_id),
            KEY idx_user_id (user_id),
            KEY idx_created_at (created_at),
            KEY idx_result_tier (result_tier),
            KEY idx_quiz_user (quiz_id, user_id)
        ) {$charset_collate};";

        /**
         * TABLE 4: cortex_attempt_answers
         */
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
            KEY idx_question_id (question_id),
            KEY idx_is_correct (is_correct)
        ) {$charset_collate};";

        /**
         * TABLE 5: cortex_question_bank
         */
        $sql_question_bank = "CREATE TABLE {$table_question_bank} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL DEFAULT '',
            description TEXT NULL,
            question_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            KEY idx_created_by (created_by),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";

        /**
         * TABLE 6: cortex_question_bank_items
         */
        $sql_question_bank_items = "CREATE TABLE {$table_question_bank_items} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            bank_id BIGINT UNSIGNED NOT NULL,
            question_text LONGTEXT NOT NULL,
            type VARCHAR(20) NOT NULL DEFAULT 'radio',
            options LONGTEXT NULL,
            correct_answer LONGTEXT NULL,
            points DECIMAL(10,2) NOT NULL DEFAULT 1,
            hint LONGTEXT NULL,
            metadata LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            KEY idx_bank_id (bank_id),
            KEY idx_type (type)
        ) {$charset_collate};";

        /**
         * TABLE 7: cortex_lead_captures
         */
        $sql_lead_captures = "CREATE TABLE {$table_lead_captures} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            quiz_id BIGINT UNSIGNED NOT NULL,
            attempt_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(191) NULL,
            email VARCHAR(191) NULL,
            phone VARCHAR(50) NULL,
            custom_fields LONGTEXT NULL,
            consent_given TINYINT(1) NOT NULL DEFAULT 0,
            ip_address VARCHAR(45) NULL,
            created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            KEY idx_quiz_id (quiz_id),
            KEY idx_attempt_id (attempt_id),
            KEY idx_email (email),
            KEY idx_created_at (created_at),
            KEY idx_consent (consent_given)
        ) {$charset_collate};";

        /**
         * TABLE 8: cortex_markdown_snippets
         */
        $sql_markdown_snippets = "CREATE TABLE {$table_markdown_snippets} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL DEFAULT '',
            content LONGTEXT NOT NULL,
            rendered LONGTEXT NULL,
            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            KEY idx_created_by (created_by),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";

        /**
         * TABLE 9: cortex_code_snippets
         */
        $sql_code_snippets = "CREATE TABLE {$table_code_snippets} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL DEFAULT '',
            code LONGTEXT NOT NULL,
            language VARCHAR(50) NOT NULL DEFAULT 'text',
            highlight_engine VARCHAR(20) NOT NULL DEFAULT 'prism',
            show_copy_button TINYINT(1) NOT NULL DEFAULT 1,
            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            KEY idx_language (language),
            KEY idx_created_by (created_by),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";

        // Execute all table creations/upgrades
        dbDelta( $sql_quizzes );
        dbDelta( $sql_questions );
        dbDelta( $sql_attempts );
        dbDelta( $sql_attempt_answers );
        dbDelta( $sql_question_bank );
        dbDelta( $sql_question_bank_items );
        dbDelta( $sql_lead_captures );
        dbDelta( $sql_markdown_snippets );
        dbDelta( $sql_code_snippets );
    }
}
