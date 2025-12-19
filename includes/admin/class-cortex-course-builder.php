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
     * Render Curriculum Builder (JSON based for MVP)
     *
     * @param WP_Post $post Current post object.
     */
    public function render_course_builder( $post ) {
        $curriculum_json = get_post_meta( $post->ID, '_cortex_curriculum', true );
        ?>
        <div id="cortex-course-builder-app" class="cortex-p-20">
            <div class="cortex-builder-header cortex-flex cortex-justify-between cortex-align-center cortex-mb-20">
                <h2 class="cortex-fs-5 cortex-fw-bold"><?php _e( 'Course Curriculum', 'cortex' ); ?></h2>
                <button type="button" class="button button-primary" id="cortex-add-topic-btn">
                    <span class="dashicons dashicons-plus-alt2"></span> <?php _e( 'Add New Topic', 'cortex' ); ?>
                </button>
            </div>

            <!-- Builder Container -->
            <div class="cortex-builder-topics" id="cortex-builder-topics-container">
                <p class="description cortex-text-center cortex-p-20 cortex-bg-light cortex-rounded" style="border: 2px dashed #cbd5e1;">
                    <?php _e( 'Structure your course by adding topics and lessons.', 'cortex' ); ?>
                </p>
            </div>
            
            <!-- Hidden Input for saving JSON -->
            <textarea name="_cortex_curriculum" id="cortex_curriculum_json" class="widefat" rows="10" style="display:none;"><?php echo esc_textarea( $curriculum_json ); ?></textarea>
            
            <!-- Scripts (Inline for MVP, move to separate JS file in Prod) -->
            <script>
            jQuery(document).ready(function($) {
                var curriculum = <?php echo $curriculum_json ? $curriculum_json : '[]'; ?>;
                var $container = $('#cortex-builder-topics-container');
                var $input = $('#cortex_curriculum_json');

                function render() {
                    $container.empty();
                    if (curriculum.length === 0) {
                        $container.html('<p class="description cortex-text-center cortex-p-20">No topics yet. Click "Add New Topic" to start.</p>');
                        return;
                    }

                    curriculum.forEach(function(topic, tIndex) {
                        var $topic = $('<div class="cortex-builder-topic cortex-border cortex-rounded cortex-mb-16 cortex-bg-white"></div>');
                        
                        // Topic Header
                        var $header = $('<div class="cortex-topic-header cortex-p-12 cortex-bg-light-gray cortex-flex cortex-justify-between cortex-align-center cortex-cursor-move"></div>');
                        $header.html('<div class="cortex-flex cortex-align-center"><span class="dashicons dashicons-sort cortex-mr-8 cortex-text-muted"></span><strong>' + (topic.title || 'Untitled Topic') + '</strong></div>');
                        
                        var $actions = $('<div class="cortex-actions"></div>');
                        var $addLessonBtn = $('<button type="button" class="button button-small cortex-mr-4">' + '<?php _e( '+ Lesson', 'cortex' ); ?>' + '</button>');
                        $addLessonBtn.on('click', function() {
                            var lessonTitle = prompt('<?php _e( 'Lesson Title:', 'cortex' ); ?>');
                            if (lessonTitle) {
                                topic.lessons.push({ title: lessonTitle, type: 'lesson', duration: '10m' });
                                update();
                            }
                        });
                        
                        var $removeBtn = $('<button type="button" class="button button-link-delete button-small"><span class="dashicons dashicons-trash"></span></button>');
                        $removeBtn.on('click', function() {
                            if (confirm('Delete topic?')) {
                                curriculum.splice(tIndex, 1);
                                update();
                            }
                        });

                        $actions.append($addLessonBtn).append($removeBtn);
                        $header.append($actions);
                        $topic.append($header);

                        // Lessons List
                        var $lessons = $('<div class="cortex-topic-lessons cortex-p-12"></div>');
                        if (topic.lessons && topic.lessons.length > 0) {
                            topic.lessons.forEach(function(lesson, lIndex) {
                                var $lesson = $('<div class="cortex-builder-lesson cortex-flex cortex-justify-between cortex-align-center cortex-p-8 cortex-border-bottom cortex-bg-white"></div>');
                                $lesson.html('<span><span class="dashicons dashicons-media-text cortex-mr-8"></span>' + lesson.title + '</span>');
                                
                                var $lActions = $('<span class="dashicons dashicons-trash cortex-cursor-pointer cortex-text-danger" title="Delete"></span>');
                                $lActions.on('click', function() {
                                    topic.lessons.splice(lIndex, 1);
                                    update();
                                });

                                $lesson.append($lActions);
                                $lessons.append($lesson);
                            });
                        } else {
                            $lessons.append('<span class="description"><?php _e( 'No lessons in this topic.', 'cortex' ); ?></span>');
                        }
                        
                        $topic.append($lessons);
                        $container.append($topic);
                    });

                    $input.val(JSON.stringify(curriculum));
                }

                function update() {
                    render();
                }

                $('#cortex-add-topic-btn').on('click', function() {
                    var title = prompt('<?php _e( 'Topic Title:', 'cortex' ); ?>');
                    if (title) {
                        curriculum.push({ title: title, summary: '', lessons: [] });
                        update();
                    }
                });

                render();
            });
            </script>
            <style>
                .cortex-bg-light-gray { background-color: #f8fafc; }
                .cortex-bg-white { background-color: #fff; }
                .cortex-border { border: 1px solid #e2e8f0; }
                .cortex-rounded { border-radius: 6px; }
                .cortex-p-20 { padding: 20px; }
                .cortex-p-12 { padding: 12px; }
                .cortex-p-8 { padding: 8px; }
                .cortex-mb-16 { margin-bottom: 16px; }
                .cortex-mr-8 { margin-right: 8px; }
                .cortex-mr-4 { margin-right: 4px; }
                .cortex-text-muted { color: #64748b; }
                .cortex-text-danger { color: #ef4444; }
                .cortex-flex { display: flex; }
                .cortex-justify-between { justify-content: space-between; }
                .cortex-align-center { align-items: center; }
                .cortex-cursor-pointer { cursor: pointer; }
            </style>
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
