<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MM_Activator {

    /**
     * Run on plugin activation: create DB tables and default options.
     */
    public static function activate() {
        global $wpdb;

        // Ensure required WP function
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;

        /*
         * Tables:
         *  - {prefix}mm_quizzes
         *  - {prefix}mm_questions
         *  - {prefix}mm_attempts
         *  - {prefix}mm_attempt_answers
         *
         * Note: we use DATETIME fields with zero default to avoid compatibility issues
         * with older MySQL versions that may not support CURRENT_TIMESTAMP defaults.
         */

        $sql = "
        CREATE TABLE {$prefix}mm_quizzes (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            description LONGTEXT NULL,
            settings LONGTEXT NULL, /* serialized array: shuffle, time_limit, attempts_allowed, show_answers, etc. */
            created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id)
        ) {$charset_collate};

        CREATE TABLE {$prefix}mm_questions (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            quiz_id BIGINT(20) UNSIGNED NOT NULL,
            question_text LONGTEXT NOT NULL,
            question_type VARCHAR(50) NOT NULL DEFAULT 'mcq', /* mcq, checkbox, text, etc. */
            options LONGTEXT NULL, /* serialized array of options (if applicable) */
            correct_answer LONGTEXT NULL, /* serialized scalar or array */
            points FLOAT NOT NULL DEFAULT 1.0,
            created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY (id),
            KEY quiz_id (quiz_id)
        ) {$charset_collate};

        CREATE TABLE {$prefix}mm_attempts (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            quiz_id BIGINT(20) UNSIGNED NOT NULL,
            student_name VARCHAR(191) NULL,
            student_roll VARCHAR(191) NULL,
            student_class VARCHAR(191) NULL,
            student_section VARCHAR(191) NULL,
            student_school VARCHAR(191) NULL,
            obtained_marks FLOAT NOT NULL DEFAULT 0,
            total_marks FLOAT NOT NULL DEFAULT 0,
            meta LONGTEXT NULL, /* serialized/stored extra student data */
            created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY (id),
            KEY quiz_id (quiz_id)
        ) {$charset_collate};

        CREATE TABLE {$prefix}mm_attempt_answers (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            attempt_id BIGINT(20) UNSIGNED NOT NULL,
            question_id BIGINT(20) UNSIGNED NOT NULL,
            given_answer LONGTEXT NULL, /* serialized or scalar */
            is_correct TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY (id),
            KEY attempt_id (attempt_id),
            KEY question_id (question_id)
        ) {$charset_collate};
        ";

        // Run DB Delta to create/update tables
        dbDelta( $sql );

        // Set / update plugin default options (safely)
        $default_settings = array(
            'show_answers'         => 'end',   // 'end' or 'instant' or 'never'
            'theme'                => 'default',
            'timer_enabled'        => false,
            'randomize_questions'  => false,
            'max_attempts'         => 0,       // 0 => unlimited
        );

        if ( get_option( 'mm_version' ) === false ) {
            add_option( 'mm_version', '1.0' );
        } else {
            update_option( 'mm_version', '1.0' );
        }

        if ( get_option( 'mm_settings' ) === false ) {
            add_option( 'mm_settings', maybe_serialize( $default_settings ) );
        } else {
            // migrate existing if needed
            $existing = get_option( 'mm_settings' );
            if ( is_serialized( $existing ) ) {
                $existing = maybe_unserialize( $existing );
            }
            if ( ! is_array( $existing ) ) {
                $existing = (array) $existing;
            }
            $merged = array_merge( $default_settings, $existing );
            update_option( 'mm_settings', maybe_serialize( $merged ) );
        }
    }
}
