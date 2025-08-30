<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Markdown Master - Activator (DB creation & migrations)
 * Creates/updates:
 *  - {$wpdb->prefix}mm_quizzes
 *  - {$wpdb->prefix}mm_questions
 *  - {$wpdb->prefix}mm_attempts
 *  - {$wpdb->prefix}mm_attempt_answers
 *
 * IMPORTANT:
 *  - No output/echo here (prevents "unexpected output during activation")
 *  - Keep versions in sync when altering schema
 */
class MM_Activator {

    const DB_VERSION_OPTION = 'mm_db_version';
    const DB_VERSION        = '1.0.0';

    /**
     * Plugin activation entrypoint
     */
    public static function activate() {
        self::create_or_upgrade_tables();
        // Store current version (for future migrations)
        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }

    /**
     * Safe to call on upgrades too (e.g., from admin_init if versions differ)
     */
    public static function create_or_upgrade_tables() {
        global $wpdb;

        // Load dbDelta
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $table_quizzes  = $wpdb->prefix . 'mm_quizzes';
        $table_questions = $wpdb->prefix . 'mm_questions';
        $table_attempts  = $wpdb->prefix . 'mm_attempts';
        $table_attempt_answers = $wpdb->prefix . 'mm_attempt_answers';

        // NOTE: dbDelta is picky: keep PRIMARY KEY and index definitions exactly formatted.
        $sql_quizzes = "
CREATE TABLE {$table_quizzes} (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL,
  description LONGTEXT NULL,
  settings LONGTEXT NULL,
  shuffle TINYINT(1) NOT NULL DEFAULT 0,
  time_limit INT(11) NOT NULL DEFAULT 0,
  attempts_allowed INT(11) NOT NULL DEFAULT 0,
  show_answers TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
  updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id)
) {$charset_collate};";

        $sql_questions = "
CREATE TABLE {$table_questions} (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  quiz_id BIGINT(20) UNSIGNED NOT NULL,
  question_text LONGTEXT NOT NULL,
  type VARCHAR(32) NOT NULL DEFAULT 'single',
  options LONGTEXT NULL,
  correct_answer LONGTEXT NULL,
  points DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  image VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
  updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id),
  KEY quiz_id (quiz_id)
) {$charset_collate};";

        $sql_attempts = "
CREATE TABLE {$table_attempts} (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  quiz_id BIGINT(20) UNSIGNED NOT NULL,
  student_name VARCHAR(191) NOT NULL,
  student_class VARCHAR(191) NULL,
  student_section VARCHAR(191) NULL,
  student_school VARCHAR(191) NULL,
  student_roll VARCHAR(191) NULL,
  obtained_marks DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total_marks DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  answers LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id),
  KEY quiz_id (quiz_id)
) {$charset_collate};";

        $sql_attempt_answers = "
CREATE TABLE {$table_attempt_answers} (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  attempt_id BIGINT(20) UNSIGNED NOT NULL,
  question_id BIGINT(20) UNSIGNED NOT NULL,
  answer LONGTEXT NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  points_awarded DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY  (id),
  KEY attempt_id (attempt_id),
  KEY question_id (question_id)
) {$charset_collate};";

        // Run dbDelta (can take an array)
        dbDelta( $sql_quizzes );
        dbDelta( $sql_questions );
        dbDelta( $sql_attempts );
        dbDelta( $sql_attempt_answers );
    }

    /**
     * Optionally call this on admin_init to apply migrations if version changed.
     */
    public static function maybe_upgrade() {
        $stored = get_option( self::DB_VERSION_OPTION, '' );
        if ( version_compare( $stored, self::DB_VERSION, '<' ) ) {
            self::create_or_upgrade_tables();
            update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
        }
    }
}
