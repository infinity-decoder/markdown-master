<?php
namespace Cotex\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Modules
 *
 * Handles registration, activation, and loading of modules.
 */
class Modules {

	/**
	 * Registered Modules.
	 *
	 * @var array
	 */
	private $registered_modules = [];

	/**
	 * Active Modules.
	 *
	 * @var array
	 */
	private $active_modules = [];

	/**
	 * Loaded Module Instances.
	 *
	 * @var array
	 */
	private $loaded_instances = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Load registry
		$this->registered_modules = include COTEX_PATH . 'config/modules.php';
		
		// Load active modules from DB
		$this->active_modules = get_option( COTEX_OPTION_MODULES, [] );
		if ( ! is_array( $this->active_modules ) ) {
			$this->active_modules = [];
		}

		$this->load_active_modules();
	}

	/**
	 * Load all active modules.
	 */
	private function load_active_modules() {
		foreach ( $this->registered_modules as $slug => $data ) {
			if ( in_array( $slug, $this->active_modules, true ) ) {
				$this->load_module( $slug, $data );
			}
		}
	}

	/**
	 * Load a single module.
	 *
	 * @param string $slug Module slug.
	 * @param array  $data Module data.
	 */
	private function load_module( $slug, $data ) {
		// Require module entry point
		// Convention: modules/{slug}/module.php
		// But wait, user prompt structure showed: modules/lms-engine/module.php
		$entry_file = COTEX_PATH . $data['path'] . '/module.php';
		
		if ( file_exists( $entry_file ) ) {
			require_once $entry_file;
		}

		// Instantiate Class
		$class_name = $data['class'];
		if ( class_exists( $class_name ) ) {
			$instance = new $class_name( $data );
			if ( $instance instanceof Abstract_Module ) {
				$instance->init();
				$this->loaded_instances[ $slug ] = $instance;
			}
		}
	}

	/**
	 * Get all registered modules.
	 *
	 * @return array
	 */
	public function get_registered() {
		return $this->registered_modules;
	}

	/**
	 * Get active modules list.
	 *
	 * @return array
	 */
	public function get_active() {
		return $this->active_modules;
	}

	/**
	 * Activate a module.
	 *
	 * @param string $slug
	 * @return bool
	 */
	public function activate_module( $slug ) {
		if ( ! isset( $this->registered_modules[ $slug ] ) ) {
			return false;
		}

		if ( ! in_array( $slug, $this->active_modules, true ) ) {
			$this->active_modules[] = $slug;
			update_option( COTEX_OPTION_MODULES, $this->active_modules );
		}
		
		return true;
	}

	/**
	 * Deactivate a module.
	 *
	 * @param string $slug
	 * @return bool
	 */
	public function deactivate_module( $slug ) {
		$key = array_search( $slug, $this->active_modules, true );
		if ( false !== $key ) {
			unset( $this->active_modules[ $key ] );
			update_option( COTEX_OPTION_MODULES, array_values( $this->active_modules ) );
			return true;
		}
		return false;
	}
}
