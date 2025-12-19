<?php
/**
 * Course Entry Box (Sidebar Enrollment/Price)
 *
 * @package Cortex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$course_id = get_the_ID();
$price = get_post_meta( $course_id, '_price', true );
$duration = get_post_meta( $course_id, '_duration', true );
$level = get_post_meta( $course_id, '_level', true );
$is_enrolled = false; // Placeholder logic
?>

<div class="cortex-card cortex-enroll-box cortex-mb-32">
    <!-- Thumbnail if sticky logic requires it here (Tutor sometimes puts it in sidebar for mobile/sticky) -->
    
    <div class="cortex-card-body cortex-p-32">
        
        <!-- Price -->
        <div class="cortex-course-price cortex-mb-24">
             <span class="cortex-fs-3 cortex-fw-bold cortex-color-black">
                 <?php echo $price ? esc_html( $price ) : __( 'Free', 'cortex' ); ?>
             </span>
        </div>

        <!-- Call to Action -->
        <div class="cortex-course-cta cortex-mb-24">
            <?php if ( $is_enrolled ) : ?>
                <a href="#" class="cortex-btn cortex-btn-primary cortex-btn-lg cortex-btn-block">
                    <?php _e( 'Continue Learning', 'cortex' ); ?>
                </a>
            <?php else : ?>
                <button class="cortex-btn cortex-btn-primary cortex-btn-lg cortex-btn-block">
                    <?php _e( 'Enroll Course', 'cortex' ); ?>
                </button>
                <div class="cortex-text-center cortex-mt-12">
                     <span class="cortex-fs-7 cortex-text-muted"><?php _e( '30-Day Money-Back Guarantee', 'cortex' ); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Meta List -->
        <div class="cortex-course-features">
            <h4 class="cortex-fs-6 cortex-fw-bold cortex-mb-16"><?php _e( 'This course includes:', 'cortex' ); ?></h4>
            <ul class="cortex-ul-list cortex-text-muted cortex-fs-7">
                <li class="cortex-flex cortex-align-center cortex-mb-12">
                    <span class="cortex-icon-clock cortex-mr-8 dashicons dashicons-clock"></span>
                    <span><?php echo esc_html( $duration ?: 'Self-paced' ); ?></span>
                </li>
                <li class="cortex-flex cortex-align-center cortex-mb-12">
                    <span class="cortex-icon-level cortex-mr-8 dashicons dashicons-chart-bar"></span>
                    <span><?php echo esc_html( ucfirst( $level ?: 'All Levels' ) ); ?></span>
                </li>
                <li class="cortex-flex cortex-align-center cortex-mb-12">
                    <span class="cortex-icon-certificate cortex-mr-8 dashicons dashicons-awards"></span>
                    <span><?php _e( 'Certificate of completion', 'cortex' ); ?></span>
                </li>
            </ul>
        </div>
        
    </div>
</div>
