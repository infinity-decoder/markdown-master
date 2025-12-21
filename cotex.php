<?php
/**
 * Plugin Name: Cotex
 * Description: Modular, enterprise-grade LMS, Quiz, Code, and Markdown system for WordPress.
 * Version: 1.0.0
 * Author: INFINITY DECODER
 * Text Domain: cotex
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define Plugin Constants
define( 'COTEX_VERSION', '1.0.0' );
define( 'COTEX_PATH', plugin_dir_path( __FILE__ ) );
define( 'COTEX_URL', plugin_dir_url( __FILE__ ) );
define( 'COTEX_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader
require_once COTEX_PATH . 'core/class-cotex-loader.php';

/**
 * Main instance of Cotex.
 *
 * Returns the main instance of Cotex to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return Cotex\Core\Loader
 */
function cotex() {
	return Cotex\Core\Loader::instance();
}

// Global start
cotex();
