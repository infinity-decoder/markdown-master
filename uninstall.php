<?php
// Exit if accessed directly
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Global WP database
global $wpdb;

// Delete custom database tables
$tables = [
    $wpdb->prefix . 'mm_quizzes',
    $wpdb->prefix . 'mm_quiz_questions',
    $wpdb->prefix . 'mm_quiz_answers',
    $wpdb->prefix . 'mm_quiz_attempts',
    $wpdb->prefix . 'mm_quiz_results',
    $wpdb->prefix . 'mm_notes',
    $wpdb->prefix . 'mm_code_snippets',
    $wpdb->prefix . 'mm_settings',
];

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// Delete plugin options
delete_option( 'mm_settings' );
delete_option( 'mm_version' );

// Delete user meta related to quiz attempts (if any)
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'mm_quiz_%'" );
