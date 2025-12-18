<?php
// Exit if accessed directly
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Check if user wants to delete data
$delete_data = get_option( 'mm_allow_uninstall_data_deletion', '1' );

if ( '1' === $delete_data ) {
    global $wpdb;

    // Delete custom database tables
    $tables = array(
        $wpdb->prefix . 'mm_quizzes',
        $wpdb->prefix . 'mm_questions',
        $wpdb->prefix . 'mm_attempts',
        $wpdb->prefix . 'mm_attempt_answers',
        $wpdb->prefix . 'mm_question_bank',
        $wpdb->prefix . 'mm_question_bank_items',
        $wpdb->prefix . 'mm_lead_captures',
        $wpdb->prefix . 'mm_markdown_snippets',
        $wpdb->prefix . 'mm_code_snippets',
    );

    foreach ( $tables as $table ) {
        $wpdb->query( "DROP TABLE IF EXISTS {$table}"  ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    // Delete plugin options
    delete_option( 'mm_schema_version' );
    delete_option( 'mm_allow_uninstall_data_deletion' );
    delete_option( 'mm_cache_ttl' );
    delete_option( 'mm_math_renderer' );
    delete_option( 'mm_code_highlighter' );

    // Delete user meta related to quiz attempts
    $wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'mm_%'" );

    // Clear all transients (cache)
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mm_%'" );
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_mm_%'" );
}
