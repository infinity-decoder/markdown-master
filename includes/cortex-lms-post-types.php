<?php
/**
 * Register Custom Post Types for Cortex LMS
 *
 * @package Cortex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function cortex_lms_register_post_types() {

	// 1. Course CPT
	$labels_course = array(
		'name'               => __( 'Courses', 'cortex' ),
		'singular_name'      => __( 'Course', 'cortex' ),
		'menu_name'          => __( 'Courses', 'cortex' ),
		'add_new'            => __( 'Add New', 'cortex' ),
		'add_new_item'       => __( 'Add New Course', 'cortex' ),
		'edit_item'          => __( 'Edit Course', 'cortex' ),
		'new_item'           => __( 'New Course', 'cortex' ),
		'view_item'          => __( 'View Course', 'cortex' ),
		'all_items'          => __( 'All Courses', 'cortex' ),
		'search_items'       => __( 'Search Courses', 'cortex' ),
		'not_found'          => __( 'No courses found', 'cortex' ),
		'not_found_in_trash' => __( 'No courses found in Trash', 'cortex' ),
	);

	$args_course = array(
		'labels'              => $labels_course,
		'public'              => true,
		'has_archive'         => true,
		'publicly_queryable'  => true,
		'show_ui'             => true,
		'show_in_menu'        => 'cortex', // Submenu of Cortex
		'show_in_nav_menus'   => true,
		'capability_type'     => 'post',
		'map_meta_cap'        => true,
		'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author' ),
		'rewrite'             => array( 'slug' => 'courses' ),
		'menu_icon'           => 'dashicons-welcome-learn-more',
	);

	register_post_type( 'cortex_course', $args_course );

	// 2. Lesson CPT
	$labels_lesson = array(
		'name'               => __( 'Lessons', 'cortex' ),
		'singular_name'      => __( 'Lesson', 'cortex' ),
		'menu_name'          => __( 'Lessons', 'cortex' ),
		'add_new'            => __( 'Add New', 'cortex' ),
		'add_new_item'       => __( 'Add New Lesson', 'cortex' ),
		'edit_item'          => __( 'Edit Lesson', 'cortex' ),
	);

	$args_lesson = array(
		'labels'              => $labels_lesson,
		'public'              => true,
		'publicly_queryable'  => true,
		'show_ui'             => true,
		'show_in_menu'        => 'cortex',
		'capability_type'     => 'post',
		'map_meta_cap'        => true,
		'supports'            => array( 'title', 'editor', 'thumbnail', 'comments' ), // Comments for discussion
		'rewrite'             => array( 'slug' => 'lessons' ),
	);

	register_post_type( 'cortex_lesson', $args_lesson );

	// 3. Assignment CPT
	$labels_assignment = array(
		'name'               => __( 'Assignments', 'cortex' ),
		'singular_name'      => __( 'Assignment', 'cortex' ),
		'menu_name'          => __( 'Assignments', 'cortex' ),
		'add_new_item'       => __( 'Add New Assignment', 'cortex' ),
		'edit_item'          => __( 'Edit Assignment', 'cortex' ),
	);

	$args_assignment = array(
		'labels'              => $labels_assignment,
		'public'              => true,
		'publicly_queryable'  => true,
		'show_ui'             => true,
		'show_in_menu'        => 'cortex',
		'capability_type'     => 'post',
		'map_meta_cap'        => true,
		'supports'            => array( 'title', 'editor', 'thumbnail' ),
		'rewrite'             => array( 'slug' => 'assignments' ),
	);

	register_post_type( 'cortex_assignment', $args_assignment );
}
add_action( 'init', 'cortex_lms_register_post_types' );
