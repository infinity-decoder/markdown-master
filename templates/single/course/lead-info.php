<?php
/**
 * Course Lead Info (Title, Category, Rating)
 *
 * @package Cortex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="cortex-course-lead-info">
    <div class="cortex-mb-2">
        <span class="cortex-badge cortex-badge-primary">
            <?php 
            $cats = get_the_terms( get_the_ID(), 'course_category' );
            if ( $cats && ! is_wp_error( $cats ) ) {
                echo esc_html( $cats[0]->name );
            } else {
                _e( 'Course', 'cortex' );
            }
            ?>
        </span>
    </div>
    
    <h1 class="cortex-course-title"><?php the_title(); ?></h1>
    
    <div class="cortex-course-meta cortex-flex cortex-items-center cortex-gap-4 cortex-mt-4 cortex-text-sm cortex-text-muted">
        <div class="cortex-meta-author cortex-flex cortex-items-center cortex-gap-2">
            <?php echo get_avatar( get_the_author_meta( 'ID' ), 24, '', '', array( 'class' => 'cortex-rounded-circle' ) ); ?>
            <span><?php _e( 'By', 'cortex' ); ?> <?php the_author(); ?></span>
        </div>
        
        <div class="cortex-meta-rating">
            <span class="dashicons dashicons-star-filled cortex-text-warning"></span>
            <span>4.8 (120 Reviews)</span> <!-- Placeholder for dynamic rating -->
        </div>
        
        <div class="cortex-meta-updated">
           <?php _e( 'Last updated', 'cortex' ); ?> <?php the_modified_date(); ?>
        </div>
    </div>
</div>
