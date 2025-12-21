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
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => 'cotex', // Submenu of Cotex
			'query_var'           => true,
			'rewrite'             => [ 'slug' => 'courses' ],
			'capability_type'     => 'post', // Simplification for now, strictly should use 'cortex_course'
			'has_archive'         => true,
			'hierarchical'        => false,
			'menu_position'       => null,
			'supports'            => [ 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'custom-fields' ],
			'show_in_rest'        => true, // Block Editor support
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
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => 'cotex',
			'query_var'           => true,
			'rewrite'             => [ 'slug' => 'lessons' ],
			'capability_type'     => 'post',
			'has_archive'         => false,
			'hierarchical'        => false, // We'll manage hierarchy via Meta/Builder, not parents
			'menu_position'       => null,
			'supports'            => [ 'title', 'editor', 'author', 'thumbnail', 'comments' ], // Comments for discussion
			'show_in_rest'        => true,
		]);
	}
}
