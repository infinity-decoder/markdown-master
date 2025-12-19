<?php
/**
 * Cortex LMS Module Core Class
 *
 * Bootstraps the Learning Management System module.
 *
 * @package Cortex
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cortex_LMS {

	/**
	 * Template Loader instance.
	 *
	 * @var Cortex_Template_Loader
	 */
	protected $template_loader;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Load required files.
	 */
	private function load_dependencies() {
		// Post Types
		if ( file_exists( CORTEX_INCLUDES . 'cortex-lms-post-types.php' ) ) {
			require_once CORTEX_INCLUDES . 'cortex-lms-post-types.php';
		}

		// Template Loader
		if ( file_exists( CORTEX_INCLUDES . 'class-cortex-template-loader.php' ) ) {
			require_once CORTEX_INCLUDES . 'class-cortex-template-loader.php';
			$this->template_loader = new Cortex_Template_Loader();
		}

        // Admin Course Builder
        if ( is_admin() && file_exists( CORTEX_PLUGIN_DIR . 'includes/admin/class-cortex-course-builder.php' ) ) {
            require_once CORTEX_PLUGIN_DIR . 'includes/admin/class-cortex-course-builder.php';
        }
        
        // Player Logic
        if ( file_exists( CORTEX_INCLUDES . 'class-cortex-lms-player.php' ) ) {
            require_once CORTEX_INCLUDES . 'class-cortex-lms-player.php';
        }
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        // Template Loading
        if ( isset( $this->template_loader ) ) {
            add_filter( 'template_include', array( $this->template_loader, 'filter_template' ) );
        }
        
        // Initialize Player
        if ( class_exists( 'Cortex_LMS_Player' ) ) {
            new Cortex_LMS_Player();
        }

        // Initialize Assignments
        if ( file_exists( CORTEX_INCLUDES . 'class-cortex-assignments.php' ) ) {
            require_once CORTEX_INCLUDES . 'class-cortex-assignments.php';
            new Cortex_Assignments();
        }

        // Initialize Certificates
        if ( file_exists( CORTEX_INCLUDES . 'class-cortex-certificates.php' ) ) {
            require_once CORTEX_INCLUDES . 'class-cortex-certificates.php';
            new Cortex_Certificates();
        }

		// Admin Enqueue (Specific to LMS pages)
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        
        // Initialize Admin components
        if ( is_admin() && class_exists( 'Cortex_Course_Builder' ) ) {
            new Cortex_Course_Builder();
        }
	}

	/**
	 * Enqueue Admin Assets.
	 */
	public function enqueue_admin_assets() {
		$screen = get_current_screen();
        if ( ! $screen ) return;

        // Check if we are on LMS post types
        if ( in_array( $screen->post_type, array( 'cortex_course', 'cortex_lesson', 'cortex_assignment' ) ) ) {
             // Enqueue global LMS admin styles
             // wp_enqueue_style( 'cortex-lms-admin', ... );
        }
	}
}
