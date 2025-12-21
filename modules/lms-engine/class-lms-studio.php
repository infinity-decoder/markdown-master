<?php
namespace Cotex\Modules\LMS_Engine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Studio
 *
 * Handles the "Course Studio" Full-Screen Editor.
 */
class Studio {

	/**
	 * Init.
	 */
	public function init() {
		// Replace Standard Editor
		add_action( 'edit_form_after_title', [ $this, 'render_studio_container' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		
		// Remove standard metaboxes to clean up UI
		add_action( 'add_meta_boxes', [ $this, 'remove_clutter' ], 99 );
	}

	/**
	 * Enqueue Studio Assets.
	 */
	public function enqueue_assets( $hook ) {
		global $post;
		
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		
		if ( ! $post || 'cortex_course' !== $post->post_type ) {
			return;
		}

		// Disable Standard WP Editor stylings if possible, or override them
		wp_enqueue_style( 'cotex-lms-studio', COTEX_URL . 'modules/lms-engine/assets/studio.css', [], COTEX_VERSION );
		wp_enqueue_script( 'cotex-lms-studio', COTEX_URL . 'modules/lms-engine/assets/studio.js', [ 'jquery', 'jquery-ui-sortable' ], COTEX_VERSION, true );

		wp_localize_script( 'cotex-lms-studio', 'cotexStudio', [
			'post_id' => $post->ID,
			'data'    => get_post_meta( $post->ID, '_cortex_course_data', true ) ?: [], // Unified Data Model
		] );
	}

	/**
	 * Render Studio Container.
	 */
	public function render_studio_container( $post ) {
		if ( 'cortex_course' !== $post->post_type ) {
			return;
		}
		
		// This container will be expanded by CSS to cover the screen
		?>
		<div id="cotex-studio-root">
			<div class="cotex-studio-header">
				<div class="cotex-studio-brand">
					<span class="dashicons dashicons-welcome-learn-more"></span>
					COURSE STUDIO
				</div>
				<div class="cotex-studio-actions">
					<button type="button" class="cotex-btn-secondary" id="cotex-studio-exit">Exit</button>
					<button type="button" class="cotex-btn-primary" id="cotex-studio-save">Save Changes</button>
				</div>
			</div>
			
			<div class="cotex-studio-layout">
				<!-- Left Sidebar: Curriculum -->
				<div class="cotex-studio-sidebar">
					<h3>Curriculum</h3>
					<div id="cotex-curriculum-tree"></div>
					<button type="button" class="cotex-add-btn" id="add-section-btn">+ Add Section</button>
				</div>

				<!-- Main Canvas: Content Editor -->
				<div class="cotex-studio-canvas">
					<div id="cotex-canvas-placeholder">
						<div class="cotex-empty-art">
							<span class="dashicons dashicons-edit"></span>
						</div>
						<h2>Select a Lesson to Edit</h2>
						<p>Or add a new section to get started.</p>
					</div>
					<div id="cotex-lesson-editor" style="display:none;">
						<input type="text" id="lesson-title-input" placeholder="Lesson Title" class="cotex-big-input">
						<div class="cotex-editor-toolbar">
							<button type="button" data-cmd="bold"><b>B</b></button>
							<button type="button" data-cmd="italic"><i>I</i></button>
							<button type="button" data-cmd="video">Video</button>
						</div>
						<div id="lesson-content-area" contenteditable="true" class="cotex-content-editable"></div>
					</div>
				</div>
			</div>

			<!-- Hidden input to store JSON data for WP to save -->
			<textarea name="cortex_course_data_json" id="cortex_course_data_json" style="display:none;"></textarea>
		</div>
		<?php
	}

	/**
	 * Remove Clutter.
	 */
	public function remove_clutter() {
		// Remove standard editor, publish box, etc.
		// We will handle saving via our own button triggering WP save or AJAX.
		// For robustness, we keep the 'submitdiv' but hide it, and trigger click on 'Update'
		
		remove_post_type_support( 'cortex_course', 'editor' );
		
		// Remove other metaboxes if we want a pure experience
		// remove_meta_box( 'submitdiv', 'cortex_course', 'side' ); // Don't remove, we need it for actual form submission
	}
}
