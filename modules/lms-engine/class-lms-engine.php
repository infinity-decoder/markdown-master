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
	 * Init hooks.
	 */
	public function init() {
		add_action( 'init', [ $this, 'register_cpts' ] );
		add_action( 'template_redirect', [ $this, 'restrict_access' ] );
	}

	/**
	 * Register Course and Lesson CPTs.
	 */
	public function register_cpts() {
		// Courses
		register_post_type( 'cortex_course', [
			'labels' => [
				'name'          => 'Courses',
				'singular_name' => 'Course',
			],
			'public'      => true,
			'show_ui'     => true,
			'show_in_menu' => 'cotex',
			'supports'    => [ 'title', 'editor', 'thumbnail', 'author' ],
			'has_archive' => true,
			'rewrite'     => [ 'slug' => 'courses' ],
		] );

		// Lessons
		register_post_type( 'cortex_lesson', [
			'labels' => [
				'name'          => 'Lessons',
				'singular_name' => 'Lesson',
			],
			'public'      => true,
			'show_ui'     => true,
			'show_in_menu' => 'cotex',
			'supports'    => [ 'title', 'editor', 'attributes' ], // Attributes for Parent (Course)
			'rewrite'     => [ 'slug' => 'lessons' ],
		] );
	}

	/**
	 * Restrict Access to Lessons.
	 *
	 * Only enrolled users can view lessons (mock logic).
	 */
	public function restrict_access() {
		if ( ! is_singular( 'cortex_lesson' ) ) {
			return;
		}

		$lesson_id = get_the_ID();
		
		// In a real scenario, we check if the user bought the parent course.
		// For now, we just check if they are logged in.
		if ( ! is_user_logged_in() ) {
			wp_die( 'You must be logged in to view this lesson.', 'Access Denied', [ 'response' => 403 ] );
		}
	}
}
