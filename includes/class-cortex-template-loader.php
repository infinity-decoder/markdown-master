<?php
/**
 * Cortex Template Loader
 *
 * Handles loading of templates from the plugin directory,
 * with support for overrides from the theme directory.
 *
 * @package Cortex
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cortex_Template_Loader {

	/**
	 * Filter the template path.
	 *
	 * @param string $template Template path.
	 * @return string
	 */
	public function filter_template( $template ) {
		$post_type = get_post_type();

		if ( 'cortex_course' === $post_type ) {
			if ( is_single() ) {
				$template = $this->get_template_path( 'single-course.php' );
			} elseif ( is_post_type_archive( 'cortex_course' ) ) {
				$template = $this->get_template_path( 'archive-course.php' );
			}
		} elseif ( 'cortex_lesson' === $post_type && is_single() ) {
			$template = $this->get_template_path( 'single-lesson.php' );
		} elseif ( 'cortex_assignment' === $post_type && is_single() ) {
			$template = $this->get_template_path( 'single-assignment.php' );
		}

		return $template;
	}

	/**
	 * Get the path to a template file, prioritizing the theme override.
	 *
	 * @param string $template_name Template file name (e.g., 'single-course.php').
	 * @return string Absolute path to the template.
	 */
	public function get_template_path( $template_name ) {
		// 1. Check Theme: wp-content/themes/my-theme/cortex/single-course.php
		$theme_template = locate_template( array( 'cortex/' . $template_name ) );
		if ( $theme_template ) {
			return $theme_template;
		}

		// 2. Check Plugin: wp-content/plugins/cortex/templates/single-course.php
		$plugin_template = CORTEX_PLUGIN_DIR . 'templates/' . $template_name;
		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}

		return '';
	}

	/**
	 * Load a template part.
	 *
	 * @param string $slug generic slug.
	 * @param string $name specialized name.
	 * @param array  $args arguments to pass to the template.
	 */
	public static function get_template_part( $slug, $name = null, $args = array() ) {
		// Normalize slug
		$template_name = $slug;
		if ( $name ) {
			$template_name .= '-' . $name;
		}
		$template_name .= '.php';

		// Look for template
		$template_path = '';
		
		// Check theme
		$theme_template = locate_template( array( 'cortex/' . $template_name ) );
		if ( $theme_template ) {
			$template_path = $theme_template;
		} else {
			// Check plugin
			if ( file_exists( CORTEX_PLUGIN_DIR . 'templates/' . $template_name ) ) {
				$template_path = CORTEX_PLUGIN_DIR . 'templates/' . $template_name;
			}
		}

		if ( $template_path ) {
			if ( ! empty( $args ) && is_array( $args ) ) {
				extract( $args );
			}
			include $template_path;
		}
	}
}
