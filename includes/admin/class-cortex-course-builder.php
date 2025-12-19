<?php
/**
 * Cortex Course Builder
 *
 * Handles the backend course creation experience:
 * - Course Settings Meta Box
 * - Curriculum Builder Meta Box (React/JS root)
 * - Saving logic for course data
 *
 * @package Cortex
 * @subpackage Includes/Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cortex_Course_Builder {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_course_data' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_builder_assets' ) );
	}

	/**
	 * Register Meta Boxes.
	 */
	public function register_meta_boxes() {
		add_meta_box(
			'cortex_course_settings',
			__( 'Course Settings', 'cortex' ),
			array( $this, 'render_settings_box' ),
			'cortex_course',
			'normal',
			'high'
		);

		add_meta_box(
			'cortex_course_curriculum',
			__( 'Curriculum Builder', 'cortex' ),
			array( $this, 'render_curriculum_box' ),
			'cortex_course',
			'normal',
			'high'
		);
	}

    /**
     * Enqueue Assets for the Builder.
     */
    public function enqueue_builder_assets( $hook ) {
        global $post;
        if ( ! $post || 'cortex_course' !== $post->post_type ) {
            return;
        }

        // Ideally enqueue a JS file here that handles the drag-and-drop curriculum
        // wp_enqueue_script( 'cortex-course-builder', ... );
    }

	/**
	 * Render Settings Meta Box.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function render_settings_box( $post ) {
		wp_nonce_field( 'cortex_course_save', 'cortex_course_nonce' );

		$price = get_post_meta( $post->ID, '_price', true );
		$duration = get_post_meta( $post->ID, '_duration', true );
		$level = get_post_meta( $post->ID, '_level', true );
        $max_students = get_post_meta( $post->ID, '_max_students', true );

		?>
		<div class="cortex-meta-box-wrap">
			<p>
				<label for="cortex_price"><strong><?php _e( 'Price', 'cortex' ); ?></strong></label><br>
				<input type="text" id="cortex_price" name="_price" value="<?php echo esc_attr( $price ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Free', 'cortex' ); ?>">
                <p class="description"><?php _e( 'Leave empty for free course.', 'cortex' ); ?></p>
			</p>
			<p>
				<label for="cortex_duration"><strong><?php _e( 'Total Duration', 'cortex' ); ?></strong></label><br>
				<input type="text" id="cortex_duration" name="_duration" value="<?php echo esc_attr( $duration ); ?>" class="regular-text" placeholder="e.g. 10 Hours">
			</p>
            <p>
                <label for="cortex_level"><strong><?php _e( 'Difficulty Level', 'cortex' ); ?></strong></label><br>
                <select id="cortex_level" name="_level">
                    <option value="all_levels" <?php selected( $level, 'all_levels' ); ?>><?php _e( 'All Levels', 'cortex' ); ?></option>
                    <option value="beginner" <?php selected( $level, 'beginner' ); ?>><?php _e( 'Beginner', 'cortex' ); ?></option>
                    <option value="intermediate" <?php selected( $level, 'intermediate' ); ?>><?php _e( 'Intermediate', 'cortex' ); ?></option>
                    <option value="advanced" <?php selected( $level, 'advanced' ); ?>><?php _e( 'Advanced', 'cortex' ); ?></option>
                </select>
            </p>
            <p>
                <label for="cortex_max_students"><strong><?php _e( 'Max Students', 'cortex' ); ?></strong></label><br>
                <input type="number" id="cortex_max_students" name="_max_students" value="<?php echo esc_attr( $max_students ); ?>" class="small-text" min="0">
                <span class="description"><?php _e( '0 for unlimited.', 'cortex' ); ?></span>
            </p>
		</div>
		<?php
	}

	/**
	 * Render Curriculum Meta Box.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function render_curriculum_box( $post ) {
        // Retrieve existing curriculum JSON
        $curriculum = get_post_meta( $post->ID, '_cortex_curriculum', true );
		?>
		<div id="cortex-curriculum-root">
            <div class="cortex-empty-state">
                <p><?php _e( 'Curriculum Builder - Coming Soon (Phase 2)', 'cortex' ); ?></p>
                <p class="description"><?php _e( 'This area will contain the React/JS based drag-and-drop builder to organize Topic and Lessons.', 'cortex' ); ?></p>
                
                <textarea name="_cortex_curriculum" rows="5" class="widefat" placeholder="JSON Structure..."><?php echo esc_textarea( $curriculum ); ?></textarea>
            </div>
		</div>
		<?php
	}

	/**
	 * Save Meta Data.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_course_data( $post_id ) {
		if ( ! isset( $_POST['cortex_course_nonce'] ) || ! wp_verify_nonce( $_POST['cortex_course_nonce'], 'cortex_course_save' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save Settings
		$fields = array( '_price', '_duration', '_level', '_max_students', '_cortex_curriculum' );
		foreach ( $fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $post_id, $field, sanitize_text_field( $_POST[ $field ] ) ); // sanitization varies per field in prod
			}
		}
	}
}
