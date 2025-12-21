<?php
namespace Cotex\Modules\LMS_Engine;

use Cotex\Core\Abstract_Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Module
 */
class Module extends Abstract_Module {

	/**
	 * Components.
	 */
	public $templates;
	public $admin_ui;

	/**
	 * Init hooks.
	 */
	public function init() {
		// Load Components
		$this->load_components();

		// Init Components
		$this->cpt->init();
		$this->roles->init();
		// $this->builder->init(); // Disable old builder
		$this->studio->init();
		$this->progress->init();
		$this->templates->init();
		$this->admin_ui->init();

		// Access Control Hook
		add_action( 'template_redirect', [ $this, 'restrict_access' ] );
	}

	/**
	 * Load Component Classes.
	 */
	private function load_components() {
		require_once __DIR__ . '/class-lms-cpt.php';
		require_once __DIR__ . '/class-lms-roles.php';
		require_once __DIR__ . '/class-lms-builder.php';
		require_once __DIR__ . '/class-lms-studio.php';
		require_once __DIR__ . '/class-lms-progress.php';
		require_once __DIR__ . '/class-lms-templates.php';
		require_once __DIR__ . '/class-lms-admin-ui.php';
		
		$this->cpt        = new CPT();
		$this->roles      = new Roles();
		$this->builder    = new Builder();
		$this->studio     = new Studio();
		$this->progress   = new Progress();
		$this->templates  = new Templates();
		$this->admin_ui    = new Admin_UI();
	}

	/**
	 * Restrict Access to Lessons.
	 */
	public function restrict_access() {
		if ( ! is_singular( 'cortex_lesson' ) ) {
			return;
		}

		// Allow admins/editors
		if ( current_user_can( 'edit_posts' ) ) {
			return;
		}

		global $post;
		$user_id = get_current_user_id();
		
		// In future: check enrollment in parent course.
		// For now, strict "Logged In" check.
		if ( ! $user_id ) {
			wp_die( 
				'<h1>Access Denied</h1><p>You must be enrolled to view this lesson.</p>', 
				'Access Denied', 
				[ 'response' => 403, 'back_link' => true ] 
			);
		}
	}
}
