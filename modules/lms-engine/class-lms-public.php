<?php
namespace Cotex\Modules\LMS_Engine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Public_View
 *
 * Handles Template Overrides and Enqueuing Assets for Frontend.
 */
class Public_View {

	/**
	 * Init.
	 */
	public function init() {
		add_filter( 'template_include', [ $this, 'load_templates' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Load Custom Templates.
	 */
	public function load_templates( $template ) {
		if ( is_singular( 'cortex_course' ) ) {
			$custom = COTEX_PATH . 'modules/lms-engine/public/templates/single-course.php';
			if ( file_exists( $custom ) ) {
				return $custom;
			}
		} elseif ( is_singular( 'cortex_lesson' ) ) {
			$custom = COTEX_PATH . 'modules/lms-engine/public/templates/single-lesson.php';
			if ( file_exists( $custom ) ) {
				return $custom;
			}
		}
		return $template;
	}

	/**
	 * Enqueue Assets.
	 */
	public function enqueue_assets() {
		if ( is_singular( 'cortex_course' ) || is_singular( 'cortex_lesson' ) ) {
			wp_enqueue_style( 'cotex-lms-frontend', COTEX_URL . 'modules/lms-engine/assets/frontend.css', [], COTEX_VERSION );
			wp_enqueue_script( 'cotex-lms-frontend', COTEX_URL . 'modules/lms-engine/assets/frontend.js', [ 'jquery' ], COTEX_VERSION, true );
			
			global $post;
			// Allow finding parent course for lesson (naive approach: passed via GET or inferred?)
			// For robustness, lessons should store parent ID or we look it up.
			// Simplified: We assume we know the course context.
			// If viewing a lesson directly, we might need a way to know WHICH course it belongs to if reused.
			// For now, let's assume one-to-one or use a URL param ?course_id=X
			
			$course_id = 0;
			if ( is_singular( 'cortex_course' ) ) {
				$course_id = $post->ID;
			} else if ( isset( $_GET['course_id'] ) ) {
				$course_id = intval( $_GET['course_id'] );
			}

			wp_localize_script( 'cotex-lms-frontend', 'cotexLmsData', [
				'ajaxurl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'cortex_lms_nonce' ),
				'course_id' => $course_id,
				'post_id'   => $post->ID,
			] );
		}
	}
}
