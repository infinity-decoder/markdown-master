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

// Fallback / Empty State
if ( empty( $curriculum ) ) {
    // Optional: Show a message or nothing. 
    // For now we show nothing if empty to avoid clutter.
    if ( current_user_can( 'edit_post', get_the_ID() ) ) {
        echo '<div class="cortex-alert cortex-alert-info">' . __( 'No curriculum found. Please arrange topics in the Course Builder.', 'cortex' ) . '</div>';
    }
    return;
}
?>

<div class="cortex-curriculum-wrap">
    <h3 class="cortex-mb-6"><?php _e( 'Course Content', 'cortex' ); ?></h3>
    
    <div class="cortex-accordion">
        <?php foreach ( $curriculum as $index => $topic ) : ?>
            <div class="cortex-accordion-item">
                <div class="cortex-accordion-header cortex-p-4 cortex-bg-light cortex-border-bottom cortex-flex cortex-justify-between cortex-cursor-pointer">
                    <span class="cortex-topic-title cortex-font-medium"><?php echo esc_html( $topic['title'] ); ?></span>
                    <span class="cortex-topic-meta cortex-text-sm"><?php echo count( $topic['lessons'] ); ?> <?php _e( 'Lessons', 'cortex' ); ?></span>
                </div>
                <div class="cortex-accordion-body cortex-p-0">
                    <ul class="cortex-lesson-list">
                        <?php foreach ( $topic['lessons'] as $lesson ) : ?>
                            <li class="cortex-lesson-item cortex-p-3 cortex-border-bottom cortex-flex cortex-justify-between cortex-items-center">
                                <div class="cortex-flex cortex-items-center cortex-gap-2">
                                    <span class="dashicons dashicons-<?php echo $lesson['type'] === 'video' ? 'video-alt3' : 'media-text'; ?> cortex-text-muted"></span>
                                    <span class="cortex-lesson-title"><?php echo esc_html( $lesson['title'] ); ?></span>
                                </div>
                                <span class="cortex-lesson-duration cortex-text-xs cortex-text-muted"><?php echo esc_html( $lesson['duration'] ); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
