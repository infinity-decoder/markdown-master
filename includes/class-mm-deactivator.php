<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MM_Deactivator {

    public static function deactivate() {
        // You could clear scheduled cron jobs here if needed in future
        // Example: wp_clear_scheduled_hook( 'mm_some_cron_event' );
    }
}
