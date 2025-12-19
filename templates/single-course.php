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

<div class="cortex-lms-wrap cortex-course-single">
    <div class="cortex-container">
        
        <?php while ( have_posts() ) : the_post(); ?>
            
            <?php do_action( 'cortex_course/single/before/wrap' ); ?>

            <!-- Course Header / Lead Info -->
            <div class="cortex-course-header">
                <?php Cortex_Template_Loader::get_template_part( 'single/course/lead-info' ); ?>
            </div>

            <div class="cortex-row cortex-mt-8">
                <!-- Main Content -->
                <div class="cortex-col-8">
                    
                    <!-- Featured Image / Video -->
                    <div class="cortex-course-thumbnail cortex-mb-6">
                        <?php if ( has_post_thumbnail() ) : ?>
                            <?php the_post_thumbnail( 'large', array( 'class' => 'cortex-img-fluid cortex-rounded' ) ); ?>
                        <?php endif; ?>
                    </div>

                    <!-- Tabs -->
                    <div class="cortex-course-tabs">
                        <ul class="cortex-nav cortex-nav-tabs">
                            <li class="cortex-nav-item">
                                <a href="#cortex-course-overview" class="cortex-nav-link active"><?php _e( 'Overview', 'cortex' ); ?></a>
                            </li>
                            <li class="cortex-nav-item">
                                <a href="#cortex-course-curriculum" class="cortex-nav-link"><?php _e( 'Curriculum', 'cortex' ); ?></a>
                            </li>
                            <li class="cortex-nav-item">
                                <a href="#cortex-course-instructor" class="cortex-nav-link"><?php _e( 'Instructor', 'cortex' ); ?></a>
                            </li>
                        </ul>

                        <div class="cortex-tab-content cortex-mt-6">
                            <!-- Overview Tab -->
                            <div id="cortex-course-overview" class="cortex-tab-pane active">
                                <div class="cortex-course-content">
                                    <?php the_content(); ?>
                                </div>
                            </div>

                            <!-- Curriculum Tab -->
                            <div id="cortex-course-curriculum" class="cortex-tab-pane">
                                <?php Cortex_Template_Loader::get_template_part( 'single/course/curriculum' ); ?>
                            </div>
                            
                            <!-- Instructor Tab -->
                             <div id="cortex-course-instructor" class="cortex-tab-pane">
                                <?php Cortex_Template_Loader::get_template_part( 'single/course/instructors' ); ?>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Sidebar -->
                <div class="cortex-col-4">
                    <div class="cortex-course-sidebar cortex-sticky">
                         <!-- Enrollment Box -->
                         <div class="cortex-card cortex-enrollment-box">
                             <div class="cortex-card-body">
                                 <div class="cortex-course-price cortex-mb-4">
                                     <span class="cortex-text-2xl cortex-font-bold">
                                         <?php echo esc_html( get_post_meta( get_the_ID(), '_price', true ) ?: 'Free' ); ?>
                                     </span>
                                 </div>
                                 <button class="cortex-btn cortex-btn-primary cortex-btn-block cortex-btn-lg">
                                     <?php _e( 'Enroll Course', 'cortex' ); ?>
                                 </button>
                                 <div class="cortex-course-meta-list cortex-mt-6">
                                     <!-- Meta details like duration, level etc -->
                                     <div class="cortex-meta-item">
                                         <span class="cortex-icon-clock"></span> <?php _e( 'Duration', 'cortex' ); ?>: <span><?php echo esc_html( get_post_meta( get_the_ID(), '_duration', true ) ); ?></span>
                                     </div>
                                 </div>
                             </div>
                         </div>
                    </div>
                </div>
            </div>

            <?php do_action( 'cortex_course/single/after/wrap' ); ?>

        <?php endwhile; ?>

    </div>
</div>

<?php get_footer(); ?>
