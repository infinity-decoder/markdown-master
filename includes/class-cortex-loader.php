<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cortex_Loader {

    protected $admin;
    protected $frontend;
    protected $lms;

    public function __construct() {
        // Load admin & frontend classes
        require_once CORTEX_INCLUDES . 'class-cortex-admin.php';
        require_once CORTEX_INCLUDES . 'class-cortex-frontend.php';
        
        // Load LMS Module
        if ( file_exists( CORTEX_INCLUDES . 'class-cortex-lms.php' ) ) {
            require_once CORTEX_INCLUDES . 'class-cortex-lms.php';
        }
    }

    public function run() {
        if ( is_admin() ) {
            $this->admin = new Cortex_Admin();
            $this->admin->init_hooks();
        } else {
            $this->frontend = new Cortex_Frontend();
            $this->frontend->init_hooks();
        }

        // Initialize LMS Module (Needed in both Admin & Frontend for CPTs)
        if ( class_exists( 'Cortex_LMS' ) ) {
            $this->lms = new Cortex_LMS();
        }
    }
}
