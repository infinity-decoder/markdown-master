<?php
namespace Cotex\Modules\LMS_Engine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Templates
 *
 * Handles custom routing and template loading for the LMS.
 * Bypasses theme templates to provide a premium SaaS experience.
 */
class Templates {

	/**
	 * Init.
	 */
	public function init() {
		add_filter( 'template_include', [ $this, 'intercept_templates' ], 99 );
		add_action( 'init', [ $this, 'add_rewrite_rules' ] );
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scoped_assets' ] );
	}

	/**
	 * Add custom rewrite rules for LMS virtual pages.
	 */
	public function add_rewrite_rules() {
		add_rewrite_rule( '^lms/catalog/?$', 'index.php?cotex_lms_page=catalog', 'top' );
		add_rewrite_rule( '^lms/dashboard/?$', 'index.php?cotex_lms_page=dashboard', 'top' );
		add_rewrite_rule( '^lms/certificates/?$', 'index.php?cotex_lms_page=certificates', 'top' );
		
		// flush_rewrite_rules(); // CAUTION: Don't run on every init in production.
	}

	/**
	 * Register custom query variables.
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'cotex_lms_page';
		return $vars;
	}

	/**
	 * Intercept templates for Courses, Lessons, and Virtual Pages.
	 */
	public function intercept_templates( $template ) {
		$lms_page = get_query_var( 'cotex_lms_page' );

		if ( ! empty( $lms_page ) ) {
			switch ( $lms_page ) {
				case 'catalog':
					return $this->get_template_path( 'course-catalog.php' );
				case 'dashboard':
					return $this->get_template_path( 'student-progress.php' );
				case 'certificates':
					return $this->get_template_path( 'certificate-view.php' );
			}
		}

		if ( is_singular( 'cortex_course' ) ) {
			return $this->get_template_path( 'course-overview.php' );
		}

		if ( is_singular( 'cortex_section' ) ) {
			// Sections don't have their own front view, pluralize or redirect to course?
			// Usually sections are just part of the course overview.
			return $template;
		}

		if ( is_singular( 'cortex_lesson' ) ) {
			return $this->get_template_path( 'lesson-view.php' );
		}

		return $template;
	}

	/**
	 * Resolve template path.
	 */
	private function get_template_path( $file ) {
		$path = COTEX_PATH . 'modules/lms-engine/templates/' . $file;
		return file_exists( $path ) ? $path : error_log("Cotex LMS: Template missing: $path");
	}

	/**
	 * Enqueue App Shell and Scoped Assets.
	 */
	public function enqueue_scoped_assets() {
		if ( $this->is_lms_screen() ) {
			wp_enqueue_style( 'cotex-lms-app', COTEX_URL . 'modules/lms-engine/assets/lms-app.css', [], COTEX_VERSION );
			wp_enqueue_script( 'cotex-lms-app', COTEX_URL . 'modules/lms-engine/assets/lms-app.js', [ 'jquery' ], COTEX_VERSION, true );
			
			wp_localize_script( 'cotex-lms-app', 'cotexLms', [
				'restUrl' => esc_url_raw( rest_url( 'cotex/v1' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			]);
		}
	}

	/**
	 * Helper to check if we are on an LMS frontend screen.
	 */
	public function is_lms_screen() {
		return ! empty( get_query_var( 'cotex_lms_page' ) ) || is_singular( [ 'cortex_course', 'cortex_lesson' ] );
	}
}
