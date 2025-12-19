<?php
/**
 * Course Instructor Template Part
 *
 * @package Cortex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$author_id = get_the_author_meta( 'ID' );
?>

<div class="cortex-instructor-wrap cortex-p-6 cortex-bg-light cortex-rounded">
    <div class="cortex-flex cortex-gap-6 cortex-items-start">
        <div class="cortex-instructor-avatar">
            <?php echo get_avatar( $author_id, 100, '', '', array( 'class' => 'cortex-rounded-circle' ) ); ?>
        </div>
        <div class="cortex-instructor-info">
            <h4 class="cortex-instructor-name cortex-mb-2">
                <?php the_author(); ?>
            </h4>
            <div class="cortex-instructor-bio cortex-text-muted cortex-text-sm">
                <?php echo wp_kses_post( get_the_author_meta( 'description' ) ); ?>
            </div>
            
            <div class="cortex-instructor-meta cortex-flex cortex-gap-4 cortex-mt-4">
                 <div class="cortex-meta-item">
                     <span class="dashicons dashicons-welcome-learn-more"></span>
                     <strong>12</strong> <?php _e( 'Courses', 'cortex' ); ?>
                 </div>
                 <div class="cortex-meta-item">
                     <span class="dashicons dashicons-groups"></span>
                     <strong>250</strong> <?php _e( 'Students', 'cortex' ); ?>
                 </div>
            </div>
        </div>
    </div>
</div>
