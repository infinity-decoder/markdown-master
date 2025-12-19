<?php
/**
 * Template for displaying single course
 *
 * @package Cortex
 * @subpackage Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header(); ?>

<?php do_action( 'cortex_course/single/before/wrap' ); ?>

<div class="cortex-wrap-parent cortex-course-details-page cortex-page-wrap">
    <div class="cortex-container">
        
        <?php while ( have_posts() ) : the_post(); ?>
            
            <!-- Lead Info -->
            <?php Cortex_Template_Loader::get_template_part( 'single/course/lead-info' ); ?>

            <div class="cortex-row cortex-gx-xl-5">
                
                <!-- Main Content -->
                <main class="cortex-col-xl-8">
                    
                    <!-- Video / Thumbnail -->
                    <div class="cortex-course-thumbnail">
                        <?php if ( get_post_meta( get_the_ID(), '_video_url', true ) ) : ?>
                            <!-- Video Placeholder -->
                            <div class="cortex-ratio cortex-ratio-16x9">
                                <iframe src="<?php echo esc_url( get_post_meta( get_the_ID(), '_video_url', true ) ); ?>" allowfullscreen></iframe>
                            </div>
                        <?php elseif ( has_post_thumbnail() ) : ?>
                            <?php the_post_thumbnail( 'large', array( 'class' => 'cortex-img-fluid cortex-rounded' ) ); ?>
                        <?php endif; ?>
                    </div>

                    <?php do_action( 'cortex_course/single/before/inner-wrap' ); ?>

                    <!-- Tabs -->
                    <div class="cortex-course-details-tab cortex-mt-32">
                        <div class="cortex-tab cortex-pt-24">
                            
                            <!-- Tab Nav (Simplified for now, can be refactored to separate file later) -->
                            <div class="cortex-course-tabs-nav cortex-mb-4 cortex-border-bottom">
                                <ul class="cortex-nav cortex-nav-tabs">
                                    <li><a href="#overview" class="active"><?php _e( 'Overview', 'cortex' ); ?></a></li>
                                    <li><a href="#curriculum"><?php _e( 'Curriculum', 'cortex' ); ?></a></li>
                                    <li><a href="#instructors"><?php _e( 'Instructors', 'cortex' ); ?></a></li>
                                </ul>
                            </div>

                            <!-- Tab: Overview -->
                            <div id="overview" class="cortex-tab-item is-active">
                                <div class="cortex-course-content cortex-prose">
                                    <?php the_content(); ?>
                                </div>
                            </div>
                            
                            <!-- Tab: Curriculum -->
                            <div id="curriculum" class="cortex-tab-item">
                                <?php Cortex_Template_Loader::get_template_part( 'single/course/curriculum' ); ?>
                            </div>

                            <!-- Tab: Instructors -->
                             <div id="instructors" class="cortex-tab-item">
                                <?php Cortex_Template_Loader::get_template_part( 'single/course/instructors' ); ?>
                            </div>

                        </div>
                    </div>

                    <?php do_action( 'cortex_course/single/after/inner-wrap' ); ?>
                </main>

                <!-- Sidebar -->
                <aside class="cortex-col-xl-4">
                    <div class="cortex-single-course-sidebar cortex-mt-40 cortex-mt-xl-0">
                        <?php do_action( 'cortex_course/single/before/sidebar' ); ?>
                        
                        <!-- Enrollment Box -->
                        <?php Cortex_Template_Loader::get_template_part( 'single/course/course-entry-box' ); ?>

                        <div class="cortex-single-course-sidebar-more cortex-mt-24">
                            <!-- Course Tags, Requirements, etc. -->
                            <?php if ( has_tag() ) : ?>
                                <div class="cortex-course-tags cortex-mt-4">
                                    <h4 class="cortex-fs-6 cortex-fw-bold"><?php _e( 'Tags', 'cortex' ); ?></h4>
                                    <?php the_tags( '<div class="cortex-tag-list">', '', '</div>' ); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php do_action( 'cortex_course/single/after/sidebar' ); ?>
                    </div>
                </aside>

            </div>

        <?php endwhile; ?>

    </div>
</div>

<?php do_action( 'cortex_course/single/after/wrap' ); ?>

<?php get_footer(); ?>
