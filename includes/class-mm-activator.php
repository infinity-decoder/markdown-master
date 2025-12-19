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
    const SCHEMA_VERSION = '2.0.0';

    /**
     * Called on plugin activation.
     */
    public static function activate() {
        self::create_or_upgrade_tables();
        update_option( 'mm_schema_version', self::SCHEMA_VERSION );
        
        // Set default options
        $defaults = array(
            'mm_allow_uninstall_data_deletion' => '1',
            'mm_cache_ttl' => '3600',
            'mm_math_renderer' => 'katex',
            'mm_code_highlighter' => 'prism',
        );
        
        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( $key ) ) {
                add_option( $key, $value );
            }
        }
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
     * 
     * Security Notes:
     * - All LONGTEXT fields will be sanitized on input and escaped on output
     * - UUID fields use WordPress wp_generate_uuid4() for collision-safe generation
     * - Foreign key relationships enforced at application level (not DB level for MyISAM compatibility)
     * - All user-facing IDs use UUIDs to prevent enumeration attacks
     */
    private static function create_or_upgrade_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        // Table names
        $table_quizzes              = $wpdb->prefix . 'mm_quizzes';
        $table_questions            = $wpdb->prefix . 'mm_questions';
        $table_attempts             = $wpdb->prefix . 'mm_attempts';
        $table_attempt_answers      = $wpdb->prefix . 'mm_attempt_answers';
        $table_question_bank        = $wpdb->prefix . 'mm_question_bank';
        $table_question_bank_items  = $wpdb->prefix . 'mm_question_bank_items';
        $table_lead_captures        = $wpdb->prefix . 'mm_lead_captures';
        $table_markdown_snippets    = $wpdb->prefix . 'mm_markdown_snippets';

        /**
         * TABLE 1: mm_quizzes
         * Core quiz configuration with UUID for public identification
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
         * TABLE 2: mm_questions
         * Quiz questions with support for 11 question types
         * Metadata field stores type-specific data as JSON
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
         * TABLE 3: mm_attempts
         * Quiz submission attempts with user tracking
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
         * TABLE 4: mm_attempt_answers
         * Individual question answers for detailed analytics
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
         * TABLE 5: mm_question_bank
         * Reusable question collections
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
         * TABLE 6: mm_question_bank_items
         * Questions stored in question banks for import
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
         * TABLE 7: mm_lead_captures
         * GDPR-compliant lead capture data
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
         * TABLE 8: mm_markdown_snippets
         * Reusable markdown content with caching
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

        // Code snippets table already exists, upgrade it with new columns
        $table_code_snippets = $wpdb->prefix . 'mm_code_snippets';
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
