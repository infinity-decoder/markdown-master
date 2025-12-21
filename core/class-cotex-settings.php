<?php
namespace Cotex\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings
 *
 * Handles global plugin settings.
 */
class Settings {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Register settings
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Register global settings.
	 */
	public function register_settings() {
		register_setting( COTEX_OPTION_SETTINGS, COTEX_OPTION_SETTINGS );
	}

	/**
	 * Get a setting value.
	 *
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$options = get_option( COTEX_OPTION_SETTINGS, [] );
		return isset( $options[ $key ] ) ? $options[ $key ] : $default;
	}
}
