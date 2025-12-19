<?php
/**
 * Template for displaying course archive
 *
 * @package Cortex
 * @subpackage Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header(); ?>

<div class="cortex-lms-wrap cortex-archive-course">
    <!-- Header -->
    <div class="cortex-archive-header cortex-bg-light cortex-py-12 cortex-mb-12">
        <div class="cortex-container">
            <h1 class="cortex-archive-title"><?php post_type_archive_title(); ?></h1>
        </div>
    </div>

    <!-- Main Content -->
    <div class="cortex-container">
        <div class="cortex-row">
            
            <!-- Sidebar Filters -->
            <div class="cortex-col-3">
                <div class="cortex-archive-filters">
                    <h4><?php _e( 'Filters', 'cortex' ); ?></h4>
                    <div class="cortex-filter-group cortex-mb-4">
                        <label><?php _e( 'Category', 'cortex' ); ?></label>
                        <select class="cortex-form-select">
                            <option><?php _e( 'All Categories', 'cortex' ); ?></option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Course Grid -->
            <div class="cortex-col-9">
                <?php if ( have_posts() ) : ?>
                    <div class="cortex-grid cortex-grid-cols-2 cortex-gap-6">
                        <?php while ( have_posts() ) : the_post(); ?>
                            <div class="cortex-card cortex-course-card">
                                <a href="<?php the_permalink(); ?>" class="cortex-card-img-top">
                                    <?php if ( has_post_thumbnail() ) : ?>
                                        <?php the_post_thumbnail( 'medium_large' ); ?>
                                    <?php else : ?>
                                        <div class="cortex-placeholder-img"></div>
                                    <?php endif; ?>
                                </a>
                                <div class="cortex-card-body">
                                    <div class="cortex-card-meta cortex-mb-2">
                                        <span class="cortex-badge cortex-badge-sm cortex-badge-light"><?php _e( 'Beginner', 'cortex' ); ?></span>
                                    </div>
                                    <h3 class="cortex-card-title cortex-text-lg cortex-font-bold cortex-mb-2">
                                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                    </h3>
                                    <div class="cortex-course-instructor cortex-text-sm cortex-text-muted cortex-mb-4">
                                        <?php _e( 'By', 'cortex' ); ?> <?php the_author(); ?>
                                    </div>
                                    <div class="cortex-card-footer cortex-flex cortex-justify-between cortex-items-center cortex-pt-4 cortex-border-top">
                                        <span class="cortex-price cortex-font-bold cortex-text-primary">Free</span>
                                        <a href="<?php the_permalink(); ?>" class="cortex-btn cortex-btn-outline-primary cortex-btn-sm"><?php _e( 'Enroll', 'cortex' ); ?></a>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="cortex-pagination cortex-mt-12">
                        <?php 
                        the_posts_pagination( array(
                            'mid_size'  => 2,
                            'prev_text' => __( 'Previous', 'cortex' ),
                            'next_text' => __( 'Next', 'cortex' ),
                        ) ); 
                        ?>
                    </div>

                <?php else : ?>
                    <p><?php _e( 'No courses found.', 'cortex' ); ?></p>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<?php get_footer(); ?>
