<?php
namespace Cotex\Modules\LMS_Engine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_UI
 *
 * Handles styling of the List Tables and other default WP Admin screens for LMS.
 */
class Admin_UI {

	/**
	 * Init.
	 */
	public function init() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_filter( 'admin_body_class', [ $this, 'add_body_class' ] );
	}

	/**
	 * Enqueue Assets for List Tables.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'edit.php' !== $hook ) {
			return;
		}

		$post_type = isset( $_GET['post_type'] ) ? $_GET['post_type'] : '';
		if ( ! in_array( $post_type, [ 'cortex_course', 'cortex_section', 'cortex_lesson' ] ) ) {
			return;
		}

		wp_enqueue_style( 'cotex-lms-list-tables', COTEX_URL . 'modules/lms-engine/assets/list-tables.css', [], COTEX_VERSION );
	}

	/**
	 * Add Body Class for specific styling.
	 */
	public function add_body_class( $classes ) {
		$screen = get_current_screen();
		if ( $screen && in_array( $screen->post_type, [ 'cortex_course', 'cortex_section', 'cortex_lesson' ] ) ) {
			$classes .= ' cotex-lms-admin ';
		}
		return $classes;
	}
}
