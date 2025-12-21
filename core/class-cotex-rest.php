<?php
namespace Cotex\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Rest
 *
 * Handles REST API endpoints.
 */
class Rest {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		register_rest_route( 'cotex/v1', '/modules', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'toggle_module' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );
	}

	/**
	 * Check permissions.
	 */
	public function permissions_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Toggle module status.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function toggle_module( $request ) {
		$slug   = $request->get_param( 'slug' );
		$active = $request->get_param( 'active' ); // boolean-ish

		if ( ! $slug ) {
			return new \WP_REST_Response( [ 'success' => false, 'message' => 'Missing slug' ], 400 );
		}

		$modules = cotex()->modules;
		$success = false;

		if ( filter_var( $active, FILTER_VALIDATE_BOOLEAN ) ) {
			$success = $modules->activate_module( $slug );
			$message = $success ? 'Module activated' : 'Failed to activate';
		} else {
			$success = $modules->deactivate_module( $slug );
			$message = $success ? 'Module deactivated' : 'Failed to deactivate';
		}

		return new \WP_REST_Response( [
			'success' => $success,
			'message' => $message,
			'active'  => in_array( $slug, $modules->get_active(), true ),
		], 200 );
	}
}
