<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cortex_Loader {

    protected $admin;
    protected $frontend;

    public function __construct() {
        // Load admin & frontend classes
        require_once CORTEX_INCLUDES . 'class-cortex-admin.php';
        require_once CORTEX_INCLUDES . 'class-cortex-frontend.php';
    }

    public function run() {
        if ( is_admin() ) {
            $this->admin = new Cortex_Admin();
            $this->admin->init_hooks();
        } else {
            $this->frontend = new Cortex_Frontend();
            $this->frontend->init_hooks();
        }
    }
}
