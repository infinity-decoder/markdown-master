<?php
/**
 * Course Lead Info (Title, Category, Rating)
 *
 * @package Cortex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$course_id = get_the_ID();
$author_id = get_the_author_meta( 'ID' );
?>
<div class="cortex-course-header-hgroup cortex-mb-24">
    <div class="cortex-course-meta cortex-flex cortex-flex-wrap cortex-mb-12">
        <?php 
        $cats = get_the_terms( $course_id, 'course_category' );
        if ( $cats && ! is_wp_error( $cats ) ) : ?>
            <div class="cortex-meta-item cortex-flex cortex-align-center cortex-mr-16">
                 <span class="cortex-icon-folder cortex-mr-4"></span>
                 <span class="cortex-color-text-primary"><?php echo esc_html( $cats[0]->name ); ?></span>
            </div>
        <?php endif; ?>

        <!-- Wishlist / Share placeholder -->
    </div>

    <!-- Title -->
    <h1 class="cortex-course-title cortex-fs-4 cortex-fw-bold cortex-color-black cortex-mb-16">
        <?php the_title(); ?>
    </h1>
    
    <!-- Meta Row -->
    <div class="cortex-course-meta-info cortex-flex cortex-align-center cortex-flex-wrap cortex-gap-16 cortex-fs-7">
        
        <!-- Author -->
        <div class="cortex-meta-author cortex-flex cortex-align-center">
            <div class="cortex-avatar cortex-mr-8">
                 <?php echo get_avatar( $author_id, 32, '', '', array( 'class' => 'cortex-rounded-circle' ) ); ?>
            </div>
            <div class="cortex-author-info">
                <span class="cortex-text-muted"><?php _e( 'By', 'cortex' ); ?></span>
                <span class="cortex-color-black cortex-fw-medium"><?php the_author(); ?></span>
            </div>
        </div>

        <!-- Rating -->
        <div class="cortex-meta-rating cortex-flex cortex-align-center">
            <div class="cortex-rating-stars cortex-mr-8">
                 <span class="dashicons dashicons-star-filled cortex-color-warning"></span>
                 <span class="dashicons dashicons-star-filled cortex-color-warning"></span>
                 <span class="dashicons dashicons-star-filled cortex-color-warning"></span>
                 <span class="dashicons dashicons-star-filled cortex-color-warning"></span>
                 <span class="dashicons dashicons-star-half cortex-color-warning"></span>
            </div>
            <span class="cortex-rating-count cortex-fw-bold cortex-mr-4">4.8</span>
            <span class="cortex-rating-total cortex-text-muted">(12)</span>
        </div>
        
        <!-- Update Date -->
        <div class="cortex-meta-date cortex-text-muted">
           <?php _e( 'Last updated', 'cortex' ); ?> <?php the_modified_date(); ?>
        </div>
    </div>
</div>
