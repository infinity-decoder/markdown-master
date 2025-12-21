<?php
namespace Cotex\Modules\LMS_Engine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Progress
 *
 * Manages User Progress.
 */
class Progress {

	/**
	 * Init.
	 */
	public function init() {
		add_action( 'wp_ajax_cortex_complete_lesson', [ $this, 'ajax_complete_lesson' ] );
	}

	/**
	 * Mark Lesson Complete (AJAX).
	 */
	public function ajax_complete_lesson() {
		check_ajax_referer( 'cortex_lms_nonce', 'nonce' );

		$user_id   = get_current_user_id();
		$lesson_id = sanitize_text_field( $_POST['lesson_id'] ); // Supports UUID
		$course_id = intval( $_POST['course_id'] );

		if ( ! $user_id || ! $lesson_id || ! $course_id ) {
			wp_send_json_error( 'Invalid data' );
		}

		// Save Progress
		$this->mark_lesson_complete( $user_id, $course_id, $lesson_id );

		// Calculate new percentage
		$percentage = $this->get_course_progress_percentage( $user_id, $course_id );

		wp_send_json_success( [
			'percentage' => $percentage,
			'message'    => 'Lesson Complete',
		] );
	}

	/**
	 * Mark lesson complete helper.
	 */
	public function mark_lesson_complete( $user_id, $course_id, $lesson_id ) {
		$progress = get_user_meta( $user_id, "_cortex_progress_{$course_id}", true );
		if ( ! is_array( $progress ) ) {
			$progress = [];
		}

		if ( ! in_array( $lesson_id, $progress ) ) {
			$progress[] = $lesson_id;
			update_user_meta( $user_id, "_cortex_progress_{$course_id}", $progress );
		}
	}

	/**
	 * Get Course Progress Percentage.
	 */
	public function get_course_progress_percentage( $user_id, $course_id ) {
		// Get all lessons in course (Unified Schema)
		$sections = get_post_meta( $course_id, '_cortex_course_data', true );
		$total_lessons = 0;
		if ( is_array( $sections ) ) {
			foreach ( $sections as $s ) {
				if ( ! empty( $s['lessons'] ) ) {
					$total_lessons += count( $s['lessons'] );
				}
			}
		}

		if ( 0 === $total_lessons ) {
			return 0; // Prevent div by zero
		}

		$progress = get_user_meta( $user_id, "_cortex_progress_{$course_id}", true );
		if ( ! is_array( $progress ) ) {
			$progress = [];
		}

		$completed_count = count( $progress );
		$percentage = ( $completed_count / $total_lessons ) * 100;
		
		return round( $percentage );
	}

	/**
	 * Check if lesson is complete.
	 */
	public function is_lesson_complete( $user_id, $course_id, $lesson_id ) {
		$progress = get_user_meta( $user_id, "_cortex_progress_{$course_id}", true );
		return is_array( $progress ) && in_array( $lesson_id, $progress );
	}
}
