<?php
/**
 * Uninstall Plugin
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete Options
delete_option( 'cotex_active_modules' );
delete_option( 'cotex_global_settings' );

// We could also delete CPT posts if desired, but usually safer to keep content.
// global $wpdb;
// $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type IN ('cortex_course', 'cortex_lesson', 'cortex_quiz', 'cortex_markdown');" );
