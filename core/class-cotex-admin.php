<?php
namespace Cotex\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin
 *
 * Handles Admin Menu and Dashboard.
 */
class Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Run early to ensure main menu exists before CPTs try to attach
		add_action( 'admin_menu', [ $this, 'add_menu_pages' ], 5 );
	}

	/**
	 * Add menu pages.
	 */
	public function add_menu_pages() {
		// Main Menu Item
		add_menu_page(
			'Cotex',
			'Cotex',
			'manage_options',
			'cotex',
			[ $this, 'render_dashboard' ],
			'dashicons-superhero',
			25
		);

		// Explicitly add Dashboard as the first submenu to preserve the main link
		add_submenu_page(
			'cotex',
			'Dashboard',
			'Dashboard',
			'manage_options',
			'cotex', // Same slug as parent
			[ $this, 'render_dashboard' ]
		);
	}

	/**
	 * Render the main dashboard.
	 */
	public function render_dashboard() {
		// Fetch registered modules
		$modules = cotex()->modules->get_registered();
		$active  = cotex()->modules->get_active();
		
		// Enqueue our assets for this page
		wp_enqueue_style( 'cotex-admin' );
		wp_enqueue_script( 'cotex-admin' );

		// In a real scenario, we'd use a template engine or include a file
		include COTEX_PATH . 'templates/admin/dashboard.php';
	}
}
