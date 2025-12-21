<?php
namespace Cotex\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract Module Class
 *
 * All modules must extend this class.
 */
abstract class Abstract_Module {

	/**
	 * Module Data
	 *
	 * @var array
	 */
	protected $module_data;

	/**
	 * Constructor.
	 *
	 * @param array $module_data Module registry data.
	 */
	public function __construct( $module_data = [] ) {
		$this->module_data = $module_data;
	}

	/**
	 * Initialize the module.
	 *
	 * Only called if the module is ACTIVE.
	 */
	public function init() {
		// Override in child class to add hooks.
	}

	/**
	 * Helper to get module slug.
	 *
	 * @return string
	 */
	public function get_slug() {
		return isset( $this->module_data['slug'] ) ? $this->module_data['slug'] : '';
	}

	/**
	 * Helper to get module path.
	 *
	 * @return string
	 */
	public function get_path() {
		return isset( $this->module_data['path'] ) ? COTEX_PATH . $this->module_data['path'] : '';
	}

	/**
	 * Helper to get module URL.
	 *
	 * @return string
	 */
	public function get_url() {
		return isset( $this->module_data['path'] ) ? COTEX_URL . $this->module_data['path'] : '';
	}
}
