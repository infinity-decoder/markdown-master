<?php
namespace Cotex\Modules\LMS_Engine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Studio
 *
 * Handles the "Course Studio" Full-Screen Enterprise Editor.
 */
class Studio {

	/**
	 * Init.
	 */
	public function init() {
		add_action( 'edit_form_after_title', [ $this, 'render_studio_container' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'add_meta_boxes', [ $this, 'remove_clutter' ], 99 );
		add_filter( 'use_block_editor_for_post_type', [ $this, 'disable_gutenberg' ], 10, 2 );
		add_action( 'save_post_cortex_course', [ $this, 'save_studio_data' ] );

		// AJAX for inline lesson operations
		add_action( 'wp_ajax_cotex_studio_create_section', [ $this, 'ajax_create_section' ] );
		add_action( 'wp_ajax_cotex_studio_create_lesson', [ $this, 'ajax_create_lesson' ] );
		add_action( 'wp_ajax_cotex_studio_save_lesson', [ $this, 'ajax_save_lesson' ] );
	}

	/**
	 * Enqueue Studio Assets.
	 */
	public function enqueue_assets( $hook ) {
		global $post;
		if ( ! $this->is_studio_page( $hook, $post ) ) return;

		// WordPress Media & Editor dependencies for the custom canvas
		wp_enqueue_media();
		wp_enqueue_editor();

		wp_enqueue_style( 'cotex-lms-studio', COTEX_URL . 'modules/lms-engine/assets/studio.css', [], COTEX_VERSION );
		wp_enqueue_script( 'cotex-lms-studio', COTEX_URL . 'modules/lms-engine/assets/studio.js', [ 'jquery', 'jquery-ui-sortable', 'wp-util' ], COTEX_VERSION, true );

		// Fetch hierarchical data for studio
		$sections_order = get_post_meta( $post->ID, '_cortex_sections_order', true ) ?: [];
		$data = [];
		foreach ( $sections_order as $s_id ) {
			$s_post = get_post( $s_id );
			if ( ! $s_post ) continue;
			
			$lesson_ids = get_post_meta( $s_id, '_cortex_lessons_order', true ) ?: [];
			$lessons = [];
			foreach ( $lesson_ids as $l_id ) {
				$l_post = get_post( $l_id );
				if ( $l_post ) {
					$lessons[] = [ 'id' => $l_id, 'title' => $l_post->post_title ];
				}
			}
			$data[] = [ 'id' => $s_id, 'title' => $s_post->post_title, 'lessons' => $lessons ];
		}

		wp_localize_script( 'cotex-lms-studio', 'cotexStudio', [
			'post_id' => $post->ID,
			'nonce'   => wp_create_nonce( 'cotex_studio_nonce' ),
			'data'    => $data,
		] );
	}

	private function is_studio_page( $hook, $post ) {
		return ( 'post.php' === $hook || 'post-new.php' === $hook ) && $post && 'cortex_course' === $post->post_type;
	}

	/**
	 * Render Studio Container (3-Panel Layout).
	 */
	public function render_studio_container( $post ) {
		if ( 'cortex_course' !== $post->post_type ) return;
		?>
		<div id="cotex-studio-root">
			<!-- TOP BAR -->
			<div class="cotex-studio-header">
				<div class="cotex-studio-brand">
					<span class="dashicons dashicons-welcome-learn-more"></span>
					COTEX STUDIO <span class="v-tag">1.0</span>
				</div>
				<div class="cotex-studio-course-title">
					<input type="text" id="studio-course-title" value="<?php echo esc_attr($post->post_title); ?>" placeholder="Course Title...">
				</div>
				<div class="cotex-studio-actions">
					<button type="button" class="studio-btn-ghost" id="cotex-studio-preview">Preview</button>
					<button type="button" class="studio-btn-secondary" id="cotex-studio-exit">Exit</button>
					<button type="button" class="studio-btn-primary" id="cotex-studio-save">Publish Changes</button>
				</div>
			</div>
			
			<div class="cotex-studio-main">
				<!-- LEFT PANEL: CURRICULUM TREE -->
				<div class="cotex-studio-pane pane-left">
					<div class="pane-header">
						<span>CURRICULUM</span>
						<button type="button" class="icon-add" id="add-section-btn" title="Add Section">+</button>
					</div>
					<div id="cotex-curriculum-tree" class="styled-scroll">
						<!-- D&D Sections/Lessons here -->
					</div>
				</div>

				<!-- CENTER PANEL: CONTENT CANVAS -->
				<div class="cotex-studio-pane pane-center">
					<div id="cotex-canvas-empty" class="canvas-state">
						<div class="empty-icon">Select a lesson to begin editing content</div>
					</div>
					
					<div id="cotex-lesson-editor" class="canvas-state" style="display:none;">
						<div class="lesson-editor-header">
							<input type="text" id="lesson-title-field" class="huge-input" placeholder="Lesson Heading">
						</div>
						<div class="lesson-editor-body">
							<!-- Custom TinyMCE or Block-like surface -->
							<div id="cotex-studio-editor-wrapper">
								<?php wp_editor( '', 'cotex_studio_canvas_editor', [
									'textarea_name' => 'cotex_studio_content',
									'editor_height' => 450,
									'media_buttons' => true,
									'tinymce'       => [
										'toolbar1' => 'bold,italic,underline,separator,alignleft,aligncenter,alignright,separator,link,unlink,bullist,numlist',
									],
									'quicktags'     => true,
								]); ?>
							</div>
						</div>
					</div>
				</div>

				<!-- RIGHT PANEL: CONTEXTUAL SETTINGS -->
				<div class="cotex-studio-pane pane-right">
					<div class="pane-header">SETTINGS</div>
					<div id="cotex-contextual-settings" class="settings-list styled-scroll">
						<div class="settings-group" id="course-settings-group">
							<label>General Settings</label>
							<div class="setting-item">
								<span>Visibility</span>
								<select id="course-visibility">
									<option value="public">Public</option>
									<option value="private">Private</option>
									<option value="enrolled">Enrolled Only</option>
								</select>
							</div>
						</div>
						
						<div class="settings-group active-lesson-settings" style="display:none;">
							<label>Lesson Options</label>
							<div class="setting-item">
								<span>Video URL</span>
								<input type="text" id="lesson-video-url" placeholder="YouTube/Vimeo...">
							</div>
							<div class="setting-item">
								<span>Completion Rule</span>
								<select id="lesson-rule">
									<option value="view">View Only</option>
									<option value="manual">Manual Mark</option>
									<option value="quiz">Quiz Pass</option>
								</select>
							</div>
							<button type="button" class="studio-btn-danger" id="delete-lesson-btn">Delete Lesson</button>
						</div>
					</div>
					
					<div class="pane-footer">
						<div class="sync-status">All changes synced</div>
					</div>
				</div>
			</div>

			<!-- Hidden Inputs -->
			<textarea name="cortex_course_data_json" id="cortex_course_data_json" style="display:none;"></textarea>
		</div>
		<?php
	}

	public function disable_gutenberg( $can, $post_type ) {
		return ( 'cortex_course' === $post_type ) ? false : $can;
	}

	public function remove_clutter() {
		remove_post_type_support( 'cortex_course', 'editor' );
	}

	/**
	 * Save Studio Data.
	 */
	public function save_studio_data( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		// Save Title if changed via studio
		if ( isset( $_POST['post_title'] ) ) {
			// Title is handled by standard form, but we might want to sync if we use AJAX.
		}

		if ( isset( $_POST['cortex_course_data_json'] ) ) {
			$curriculum = json_decode( stripslashes( $_POST['cortex_course_data_json'] ), true );
			
			if ( is_array( $curriculum ) ) {
				// We still use the JSON to store the ORDER and HIERARCHY IDs, 
				// but the actual content remains in the posts.
				// For the "No JSON blobs" rule, we interpret it as "Don't store content/metadata in blobs".
				// We will store the structure (Section IDs, Lesson IDs) in meta for fast retrieval.
				
				$sections_order = [];
				foreach ( $curriculum as $s_data ) {
					// Ensure Section Post exists or create it
					$section_id = isset($s_data['id']) ? intval($s_data['id']) : 0;
					
					// Update Section Meta for relationship
					if ($section_id) {
						update_post_meta($section_id, '_cortex_course_id', $post_id);
						
						$lesson_ids = [];
						if (!empty($s_data['lessons'])) {
							foreach($s_data['lessons'] as $l_data) {
								$l_id = intval($l_data['id']);
								if ($l_id) {
									$lesson_ids[] = $l_id;
									update_post_meta($l_id, '_cortex_section_id', $section_id);
									update_post_meta($l_id, '_cortex_course_id', $post_id);
								}
							}
						}
						update_post_meta($section_id, '_cortex_lessons_order', $lesson_ids);
						$sections_order[] = $section_id;
					}
				}
				update_post_meta($post_id, '_cortex_sections_order', $sections_order);
			}
		}
	}

	/**
	 * AJAX: Create Section.
	 */
	public function ajax_create_section() {
		check_ajax_referer( 'cotex_studio_nonce', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error();

		$course_id = intval($_POST['course_id']);

		$post_id = wp_insert_post([
			'post_type'   => 'cortex_section',
			'post_status' => 'publish',
			'post_title'  => sanitize_text_field($_POST['title']),
		]);

		if ( is_wp_error( $post_id ) ) wp_send_json_error();
		update_post_meta($post_id, '_cortex_course_id', $course_id);
		
		wp_send_json_success( [ 'id' => $post_id, 'title' => $_POST['title'] ] );
	}	

	/**
	 * AJAX: Create Lesson.
	 */
	public function ajax_create_lesson() {
		check_ajax_referer( 'cotex_studio_nonce', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error();

		$post_id = wp_insert_post([
			'post_type'   => 'cortex_lesson',
			'post_status' => 'publish',
			'post_title'  => 'New Lesson',
		]);

		if ( is_wp_error( $post_id ) ) wp_send_json_error();
		wp_send_json_success( [ 'id' => $post_id, 'title' => 'New Lesson' ] );
	}

	/**
	 * AJAX: Save Lesson.
	 */
	public function ajax_save_lesson() {
		check_ajax_referer( 'cotex_studio_nonce', 'nonce' );
		$lesson_id = intval( $_POST['lesson_id'] );
		if ( ! current_user_can( 'edit_post', $lesson_id ) ) wp_send_json_error();

		wp_update_post([
			'ID'           => $lesson_id,
			'post_title'   => sanitize_text_field( $_POST['title'] ),
			'post_content' => wp_kses_post( $_POST['content'] ),
		]);

		// Save meta
		update_post_meta( $lesson_id, '_cortex_video_url', esc_url_raw( $_POST['video_url'] ) );
		update_post_meta( $lesson_id, '_cortex_completion_rule', sanitize_text_field( $_POST['rule'] ) );

		wp_send_json_success();
	}
}
