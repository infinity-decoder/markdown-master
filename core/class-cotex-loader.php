<?php
namespace Cotex\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Loader
 *
 * Bootstraps the plugin and handles autoconfiguration.
 */
class Loader {

	/**
	 * Instance of this class.
	 *
	 * @var Loader
	 */
	protected static $instance = null;

	/**
	 * Modules instance.
	 *
	 * @var Modules
	 */
	public $modules;

	/**
	 * Admin instance.
	 *
	 * @var Admin
	 */
	public $admin;

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	public $settings;

	/**
	 * Assets instance.
	 *
	 * @var Assets
	 */
	public $assets;

	/**
	 * REST instance.
	 *
	 * @var Rest
	 */
	public $rest;

	/**
	 * Return an instance of this class.
	 *
	 * @return Loader
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the plugin.
	 */
	private function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include required core files.
	 */
	private function includes() {
		require_once COTEX_PATH . 'config/constants.php';
		
		// Autoloader logic for Core classes
		spl_autoload_register( function ( $class ) {
			$prefix   = 'Cotex\\';
			$base_dir = COTEX_PATH;

			$len = strlen( $prefix );
			if ( strncmp( $prefix, $class, $len ) !== 0 ) {
				return;
			}

			$relative_class = substr( $class, $len );
			
			// Map namespaces to directories
			// Cotex\Core\Admin -> core/class-cotex-admin.php
			// Cotex\Modules\LMS_Engine\Module -> modules/lms-engine/module.php ?? 
			// Actually, let's stick to the prompt's Class Naming vs File Naming convention.
			// The prompt specified file names like `class-cotex-admin.php`.
			// So we need a custom mapper or strict adherence.

			// Simple mapper for Core
			if ( strpos( $relative_class, 'Core\\' ) === 0 ) {
				$parts = explode( '\\', $relative_class );
				$class_name = array_pop( $parts );
				$file_name = 'class-cotex-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';
				$file = $base_dir . 'core/' . $file_name;
				if ( file_exists( $file ) ) {
					require $file;
				}
			}
		} );

		// Explicitly load Abstract Module as it doesn't follow the Standard Class Naming convention used by Autoloader
		require_once COTEX_PATH . 'core/abstract-cotex-module.php';
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', [ $this, 'on_plugins_loaded' ] );
	}

	/**
	 * Load core components on plugins_loaded.
	 */
	public function on_plugins_loaded() {
		$this->settings = new Settings();
		$this->modules  = new Modules();
		$this->admin    = new Admin();
		$this->assets   = new Assets();
		$this->rest     = new Rest();
	}
}
