<?php
namespace Cotex\Modules\LMS_Engine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CPT
 *
 * Manages Course and Lesson Custom Post Types.
 */
class CPT {

	/**
	 * Init.
	 */
	public function init() {
		add_action( 'init', [ $this, 'register_cpts' ] );
	}

	/**
	 * Register CPTs.
	 */
	public function register_cpts() {
		// Labels
		$labels_course = [
			'name'               => 'Courses',
			'singular_name'      => 'Course',
			'menu_name'          => 'Courses',
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New Course',
			'edit_item'          => 'Edit Course',
			'new_item'           => 'New Course',
			'view_item'          => 'View Course',
			'search_items'       => 'Search Courses',
			'not_found'          => 'No courses found',
			'not_found_in_trash' => 'No courses found in Trash',
		];

		// Course CPT
		register_post_type( 'cortex_course', [
			'labels'              => $labels_course,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => 'cotex',
			'query_var'           => true,
			'rewrite'             => [ 'slug' => 'courses' ],
			'capability_type'     => 'post',
			'has_archive'         => true,
			'supports'            => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
			'show_in_rest'        => true,
		]);

		// Section CPT (Intermediate)
		register_post_type( 'cortex_section', [
			'labels' => [
				'name'          => 'Sections',
				'singular_name' => 'Section',
			],
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'cotex',
			'supports'        => [ 'title' ],
			'show_in_rest'    => true,
			'hierarchical'    => false,
		]);

		// Lesson CPT
		$labels_lesson = [
			'name'               => 'Lessons',
			'singular_name'      => 'Lesson',
			'menu_name'          => 'Lessons',
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New Lesson',
			'edit_item'          => 'Edit Lesson',
			'new_item'           => 'New Lesson',
			'view_item'          => 'View Lesson',
			'search_items'       => 'Search Lessons',
		];

		register_post_type( 'cortex_lesson', [
			'labels'              => $labels_lesson,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => 'cotex',
			'query_var'           => true,
			'rewrite'             => [ 'slug' => 'lessons' ],
			'capability_type'     => 'post',
			'supports'            => [ 'title', 'editor', 'thumbnail' ],
			'show_in_rest'        => true,
		]);
	}
}
