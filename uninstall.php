<?php
// Exit if accessed directly
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Check if user wants to delete data
$delete_data = get_option( 'cortex_allow_uninstall_data_deletion', '1' );

if ( '1' === $delete_data ) {
    global $wpdb;

    // Delete custom database tables
    $tables = array(
        $wpdb->prefix . 'cortex_quizzes',
        $wpdb->prefix . 'cortex_questions',
        $wpdb->prefix . 'cortex_attempts',
        $wpdb->prefix . 'cortex_attempt_answers',
        $wpdb->prefix . 'cortex_question_bank',
        $wpdb->prefix . 'cortex_question_bank_items',
        $wpdb->prefix . 'cortex_lead_captures',
        $wpdb->prefix . 'cortex_markdown_snippets',
        $wpdb->prefix . 'cortex_code_snippets',
    );

    foreach ( $tables as $table ) {
        $wpdb->query( "DROP TABLE IF EXISTS {$table}"  ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    // Delete plugin options
    delete_option( 'cortex_schema_version' );
    delete_option( 'cortex_allow_uninstall_data_deletion' );
    delete_option( 'cortex_cache_ttl' );
    delete_option( 'cortex_math_renderer' );
    delete_option( 'cortex_code_highlighter' );

    // Delete user meta related to quiz attempts
    $wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'cortex_%'" );

    // Clear all transients (cache)
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_cortex_%'" );
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_cortex_%'" );
}
