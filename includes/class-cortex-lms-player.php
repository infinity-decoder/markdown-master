<?php
/**
 * Cortex LMS Player Logic
 *
 * Handles lesson navigation, progress tracking, and AJAX actions.
 *
 * @package Cortex
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cortex_LMS_Player {

	public function __construct() {
		add_action( 'wp_ajax_cortex_mark_complete', array( $this, 'ajax_mark_complete' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_player_scripts' ) );
	}

    /**
     * Enqueue Player Assets (JS/CSS)
     */
    public function enqueue_player_scripts() {
        if ( is_singular( 'cortex_lesson' ) ) {
            // Enqueue Plyr or local JS
             wp_enqueue_script( 'cortex-lms-player', CORTEX_PLUGIN_URL . 'assets/js/cortex-lms-player.js', array( 'jquery' ), CORTEX_VERSION, true );
             
             wp_localize_script( 'cortex-lms-player', 'Cortex_Player', array(
                 'ajax_url' => admin_url( 'admin-ajax.php' ),
                 'nonce'    => wp_create_nonce( 'cortex_player_nonce' ),
             ));
        }
    }

	/**
	 * Mark Lesson as Complete via AJAX
	 */
	public function ajax_mark_complete() {
		check_ajax_referer( 'cortex_player_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please login to track progress.', 'cortex' ) ) );
		}

		$lesson_id = isset( $_POST['lesson_id'] ) ? intval( $_POST['lesson_id'] ) : 0;
		$course_id = isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : 0;
        $user_id   = get_current_user_id();

		if ( ! $lesson_id || ! $course_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'cortex' ) ) );
		}

		// Save to cortex_course_progress table
        global $wpdb;
        $table_name = $wpdb->prefix . 'cortex_course_progress';
        
        // Check existence
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $table_name WHERE user_id = %d AND lesson_id = %d AND course_id = %d",
            $user_id, $lesson_id, $course_id
        ));
        
        if ( ! $exists ) {
            $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'course_id' => $course_id,
                    'lesson_id' => $lesson_id,
                    'status' => 'completed',
                    'completed_at' => current_time( 'mysql' )
                ),
                array( '%d', '%d', '%d', '%s', '%s' )
            );
        }

        // Calculate Course Progress %
        $this->update_enrollment_progress( $user_id, $course_id );

		wp_send_json_success( array( 'message' => __( 'Lesson Completed!', 'cortex' ) ) );
	}
    
    /**
     * Update Enrollment Progress calculated from completed lessons
     */
    private function update_enrollment_progress( $user_id, $course_id ) {
        // Simple calculation: (Completed Lessons / Total Lessons) * 100
        // Total lessons needs to be fetched from Course Curriculum Meta
        // For now, this is a placeholder implementation
        
        // In real app:
        // $total_lessons = count( Cortex_Course::get_all_lesson_ids( $course_id ) );
        // $completed = $wpdb->get_var(...)
        // $percent = ...
        // $wpdb->update( $enrollment_table, ... )
    }
}
