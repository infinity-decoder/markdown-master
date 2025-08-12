<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MM_Loader {

    protected $admin;
    protected $frontend;

    public function __construct() {
        // Load admin & frontend classes
        require_once MM_INCLUDES . 'class-mm-admin.php';
        require_once MM_INCLUDES . 'class-mm-frontend.php';
    }

    public function run() {
        if ( is_admin() ) {
            $this->admin = new MM_Admin();
            $this->admin->init_hooks();
        } else {
            $this->frontend = new MM_Frontend();
            $this->frontend->init_hooks();
        }
    }
}
