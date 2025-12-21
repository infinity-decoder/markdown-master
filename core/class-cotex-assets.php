<?php
namespace Cotex\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Assets
 *
 * Manages plugin assets.
 */
class Assets {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
	}

	/**
	 * Enqueue admin assets.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Load only on specific pages needed if possible, for now global admin for simplicity
		// Ideally check $hook or get_current_screen()
		
		wp_register_style( 'cotex-admin', COTEX_URL . 'assets/admin/css/cotex-admin.css', [], COTEX_VERSION );
		wp_register_script( 'cotex-admin', COTEX_URL . 'assets/admin/js/cotex-admin.js', [ 'jquery' ], COTEX_VERSION, true );

		// Localize script for API
		wp_localize_script( 'cotex-admin', 'cotexVars', [
			'root'  => esc_url_raw( rest_url() ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
		] );
	}
}
