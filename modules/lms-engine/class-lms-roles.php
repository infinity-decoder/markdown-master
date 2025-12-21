<?php
namespace Cotex\Modules\LMS_Engine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Roles
 *
 * Manages LMS User Roles and Capabilities.
 */
class Roles {

	/**
	 * Init.
	 */
	public function init() {
		add_action( 'init', [ $this, 'register_roles' ] );
		add_action( 'admin_init', [ $this, 'add_caps' ] );
	}

	/**
	 * Register Roles.
	 */
	public function register_roles() {
		// Student
		add_role( 'cortex_student', 'Student', [
			'read' => true,
			'view_cortex_course' => true,
		]);

		// Instructor
		add_role( 'cortex_instructor', 'Instructor', [
			'read' => true,
			'upload_files' => true,
			'delete_posts' => true,
			'edit_posts' => true,
			'edit_published_posts' => true,
			'publish_posts' => true,
			'manage_cortex_course' => true,
		]);
	}

	/**
	 * Add Capabilities to Admin.
	 */
	public function add_caps() {
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'manage_cortex_course' );
			$admin->add_cap( 'view_cortex_course' );
		}
	}
}
