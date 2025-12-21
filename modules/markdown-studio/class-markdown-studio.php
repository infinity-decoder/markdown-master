<?php
namespace Cotex\Modules\Markdown_Studio;

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
		// Register CPT for Markdown Docs (implied by shortcode ID)
		add_action( 'init', [ $this, 'register_cpt' ] );
		
		// Admin Editor Assets
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		
		// Shortcode
		add_shortcode( 'cortex-markdown', [ $this, 'render_shortcode' ] );

		// Save Post Hook to compile markdown (optional optimization)
		// For now, we render on-the-fly or save to meta.
	}

	/**
	 * Register CPT.
	 */
	public function register_cpt() {
		register_post_type( 'cortex_markdown', [
			'labels' => [
				'name'          => 'Markdown Docs',
				'singular_name' => 'Markdown Doc',
			],
			'public'      => false,
			'show_ui'     => true,
			'show_in_menu' => 'cotex', // Submenu of Cotex
			'supports'    => [ 'title', 'editor' ], // We will replace editor with ours
			'capability_type' => 'post',
		] );
	}

	/**
	 * Enqueue Admin Assets (EasyMDE).
	 */
	public function enqueue_admin_assets( $hook ) {
		global $post;
		if ( ! $post || 'cortex_markdown' !== $post->post_type ) {
			return;
		}

		if ( 'post.php' === $hook || 'post-new.php' === $hook ) {
			wp_enqueue_script( 'cotex-easymde', $this->get_url() . '/assets/easymde.min.js', [], '2.18.0', true );
			wp_enqueue_style( 'cotex-easymde', $this->get_url() . '/assets/easymde.min.css', [], '2.18.0' );
			
			// Init script
			wp_add_inline_script( 'cotex-easymde', "
				jQuery(document).ready(function($){
					var easyMDE = new EasyMDE({ element: $('#content')[0] });
				});
			" );
		}
	}

	/**
	 * Render Shortcode.
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts( [
			'id' => 0,
		], $atts );

		if ( ! $atts['id'] ) {
			return '';
		}

		$post = get_post( $atts['id'] );
		if ( ! $post || 'cortex_markdown' !== $post->post_type ) {
			return '';
		}

		// Require Parsedown
		if ( ! class_exists( 'Parsedown' ) ) {
			$parsedown_file = __DIR__ . '/Parsedown.php';
			if ( file_exists( $parsedown_file ) ) {
				require_once $parsedown_file;
			} else {
				return $post->post_content; // Fallback to raw if parser missing
			}
		}

		if ( class_exists( 'Parsedown' ) ) {
			$parsedown = new \Parsedown();
			$html = $parsedown->text( $post->post_content );
		} else {
			$html = wpautop( $post->post_content );
		}

		return '<div class="cotex-markdown-content">' . $html . '</div>';
	}
}
