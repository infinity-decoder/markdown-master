<?php
namespace Cotex\Modules\LMS_Engine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Builder
 *
 * Handles the Course Builder UI.
 */
class Builder {

	/**
	 * Init.
	 */
	public function init() {
		add_action( 'add_meta_boxes', [ $this, 'add_builder_metabox' ] );
		add_action( 'save_post_cortex_course', [ $this, 'save_course_data' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Add Meta Box.
	 */
	public function add_builder_metabox() {
		add_meta_box(
			'cortex_course_builder',
			'Course Curriculum',
			[ $this, 'render_builder' ],
			'cortex_course',
			'normal',
			'high'
		);
	}

	/**
	 * Enqueue Assets.
	 */
	public function enqueue_assets( $hook ) {
		global $post;
		
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		
		if ( ! $post || 'cortex_course' !== $post->post_type ) {
			return;
		}

		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'cotex-lms-builder', COTEX_URL . 'modules/lms-engine/assets/builder.js', [ 'jquery', 'jquery-ui-sortable' ], COTEX_VERSION, true );
		wp_enqueue_style( 'cotex-lms-builder', COTEX_URL . 'modules/lms-engine/assets/builder.css', [], COTEX_VERSION );

		// Localize
		wp_localize_script( 'cotex-lms-builder', 'cotexLmsVars', [
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'post_id' => $post->ID,
		] );
	}

	/**
	 * Render Builder HTML.
	 */
	public function render_builder( $post ) {
		// Retrieve existing data
		$sections = get_post_meta( $post->ID, '_cortex_course_sections', true );
		if ( ! is_array( $sections ) ) {
			$sections = [];
		}
		
		// Template
		?>
		<div id="cotex-builder-app">
			<div class="cotex-builder-actions">
				<button type="button" class="button button-primary" id="cotex-add-section">+ Add Section</button>
			</div>
			
			<div id="cotex-sections-container">
				<?php if ( empty( $sections ) ) : ?>
					<div class="cotex-empty-state">Start by adding a section.</div>
				<?php else : ?>
					<?php foreach ( $sections as $s_index => $section ) : ?>
						<div class="cotex-section" data-index="<?php echo esc_attr( $s_index ); ?>">
							<div class="cotex-section-header">
								<span class="dashicons dashicons-move handle"></span>
								<input type="text" name="cotex_sections[<?php echo $s_index; ?>][title]" value="<?php echo esc_attr( $section['title'] ); ?>" placeholder="Section Title" class="cotex-input-clean">
								<button type="button" class="cotex-icon-btn remove-section">&times;</button>
							</div>
							<div class="cotex-lessons-list">
								<?php 
								if ( ! empty( $section['lessons'] ) ) : 
									foreach ( $section['lessons'] as $l_index => $lesson_id ) : 
										$lesson = get_post( $lesson_id );
										if ( ! $lesson ) continue;
								?>
									<div class="cotex-builder-lesson">
										<span class="dashicons dashicons-menu handle"></span>
										<span class="lesson-title"><?php echo esc_html( $lesson->post_title ); ?></span>
										<input type="hidden" name="cotex_sections[<?php echo $s_index; ?>][lessons][]" value="<?php echo esc_attr( $lesson->ID ); ?>">
										<a href="<?php echo get_edit_post_link( $lesson->ID ); ?>" target="_blank" class="edit-link">Edit</a>
										<button type="button" class="remove-lesson">&times;</button>
									</div>
								<?php endforeach; endif; ?>
							</div>
							<div class="cotex-section-footer">
								<button type="button" class="button button-secondary add-lesson-btn" data-section="<?php echo $s_index; ?>">+ Add Lesson</button>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
			
			<!-- Hidden Input for State Management assistance if needed, but we used named inputs above -->
			<p class="description">Drag and drop to reorder sections and lessons. Changes are saved when you Update the course.</p>
		</div>

		<!-- Simplistic Lesson Picker Modal (Hidden) -->
		<div id="cotex-lesson-picker-modal" style="display:none;">
			<div class="cotex-modal-content">
				<h3>Select a Lesson</h3>
				<input type="text" id="cotex-lesson-search" placeholder="Search lessons...">
				<ul id="cotex-lesson-results"></ul>
				<button type="button" id="cotex-close-modal">Close</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Save Data.
	 */
	public function save_course_data( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['cotex_sections'] ) ) {
			$sections = $_POST['cotex_sections'];
			// Sanitize
			$clean_sections = [];
			foreach ( $sections as $s ) {
				$clean_s = [
					'title'   => sanitize_text_field( $s['title'] ),
					'lessons' => isset( $s['lessons'] ) ? array_map( 'intval', $s['lessons'] ) : [],
				];
				$clean_sections[] = $clean_s;
			}
			update_post_meta( $post_id, '_cortex_course_sections', $clean_sections );
		}
	}
}
