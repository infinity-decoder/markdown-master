<?php
/**
 * Course Curriculum
 *
 * @package Cortex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Retrieve curriculum structure
$curriculum_json = get_post_meta( get_the_ID(), '_cortex_curriculum', true );
$curriculum = array();

if ( ! empty( $curriculum_json ) ) {
    $decoded = json_decode( $curriculum_json, true );
    if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
        $curriculum = $decoded;
    }
}

if ( empty( $curriculum ) ) {
    return;
}
?>

<div class="cortex-course-topics-wrap">
    <div class="cortex-course-topics-header cortex-flex cortex-justify-between cortex-align-center cortex-mb-24">
        <h4 class="cortex-fs-5 cortex-fw-bold cortex-color-black"><?php _e( 'Course Content', 'cortex' ); ?></h4>
        <!-- Expand/Collapse functionality placeholder -->
    </div>
    
    <div class="cortex-accordion cortex-course-topics-accordion">
        <?php foreach ( $curriculum as $index => $topic ) : ?>
            <div class="cortex-accordion-item cortex-border cortex-rounded cortex-mb-16">
                <!-- Header -->
                <div class="cortex-accordion-header cortex-p-20 cortex-bg-light-gray cortex-cursor-pointer cortex-flex cortex-justify-between cortex-align-center">
                    <div class="cortex-topic-title-wrap cortex-flex cortex-align-center">
                        <span class="dashicons dashicons-arrow-down-alt2 cortex-mr-12 cortex-color-text-primary"></span>
                        <h5 class="cortex-topic-title cortex-fs-6 cortex-fw-bold cortex-m-0">
                            <?php echo esc_html( $topic['title'] ); ?>
                        </h5>
                    </div>
                    <div class="cortex-topic-meta cortex-fs-7 cortex-text-muted">
                        <span><?php echo count( $topic['lessons'] ); ?> <?php _e( 'Lessons', 'cortex' ); ?></span>
                    </div>
                </div>

                <!-- Body -->
                <div class="cortex-accordion-body cortex-p-0" style="display:block;"> <!-- Assuming open by default for MVP -->
                    <div class="cortex-course-lessons">
                        <?php foreach ( $topic['lessons'] as $lesson ) : ?>
                            <div class="cortex-course-lesson cortex-flex cortex-justify-between cortex-align-center cortex-p-16 cortex-border-top">
                                <div class="cortex-lesson-title-wrap cortex-flex cortex-align-center">
                                    <span class="dashicons dashicons-media-text cortex-mr-12 cortex-text-muted"></span>
                                    <a href="#" class="cortex-lesson-title cortex-color-black cortex-text-decoration-none">
                                        <?php echo esc_html( $lesson['title'] ); ?>
                                    </a>
                                </div>
                                <div class="cortex-lesson-meta">
                                     <span class="cortex-lesson-duration cortex-fs-7 cortex-text-muted">
                                         <?php echo esc_html( $lesson['duration'] ); ?>
                                     </span>
                                     <span class="dashicons dashicons-lock cortex-ml-12 cortex-text-muted"></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
