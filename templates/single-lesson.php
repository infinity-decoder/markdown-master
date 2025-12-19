<?php
/**
 * Single Lesson Template (Spotlight Mode)
 *
 * @package Cortex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$course_id = get_post_meta( get_the_ID(), '_course_id', true );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
    <style>
        body { margin: 0; padding: 0; overflow-x: hidden; }
        .cortex-spotlight-wrapper { display: flex; height: 100vh; overflow: hidden; font-family: 'Inter', sans-serif; background: #fff; }
        .cortex-spotlight-sidebar { width: 350px; background: #f8fafc; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; overflow-y: auto; flex-shrink: 0; }
        .cortex-spotlight-content { flex-grow: 1; overflow-y: auto; display: flex; flex-direction: column; background: #fff; }
        .cortex-spotlight-header { height: 60px; background: #fff; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; flex-shrink: 0; }
        .cortex-lesson-video { background: #000; width: 100%; aspect-ratio: 16/9; display: flex; justify-content: center; align-items: center; }
        .cortex-lesson-body { max-width: 900px; margin: 0 auto; padding: 40px; width: 100%; box-sizing: border-box; }
        @media (max-width: 768px) {
            .cortex-spotlight-wrapper { flex-direction: column; height: auto; overflow: auto; }
            .cortex-spotlight-sidebar { width: 100%; height: auto; max-height: 300px; }
        }
    </style>
</head>
<body <?php body_class(); ?>>

<div class="cortex-spotlight-wrapper">
    
    <!-- Sidebar: Curriculum -->
    <aside class="cortex-spotlight-sidebar">
        <div class="cortex-p-20 cortex-border-bottom">
            <h5 class="cortex-m-0 cortex-fs-6 cortex-fw-bold">
                <a href="<?php echo get_permalink( $course_id ); ?>" class="cortex-text-decoration-none cortex-color-black">
                     <span class="dashicons dashicons-arrow-left-alt2 cortex-mr-8"></span>
                     <?php _e( 'Back to Course', 'cortex' ); ?>
                </a>
            </h5>
        </div>
        <div class="cortex-course-topics-accordion">
             <!-- Reuse Curriculum Template Partial -->
             <?php 
             // Temporarily force post data to course ID for the partial to work
             global $post;
             $original_post = $post;
             $post = get_post( $course_id );
             setup_postdata( $post );
             
             Cortex_Template_Loader::get_template_part( 'single/course/curriculum' ); 
             
             wp_reset_postdata();
             $post = $original_post;
             ?>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="cortex-spotlight-content">
        
        <!-- Header -->
        <header class="cortex-spotlight-header">
             <h1 class="cortex-fs-5 cortex-fw-bold cortex-m-0"><?php the_title(); ?></h1>
             <div class="cortex-actions">
                 <a href="#" class="cortex-btn cortex-btn-outline-primary cortex-btn-sm"><?php _e( 'Previous', 'cortex' ); ?></a>
                 <a href="#" class="cortex-btn cortex-btn-outline-primary cortex-btn-sm cortex-ml-8"><?php _e( 'Next', 'cortex' ); ?></a>
             </div>
        </header>

        <!-- Video Player -->
        <?php if ( get_post_meta( get_the_ID(), '_video_url', true ) ) : ?>
            <div class="cortex-lesson-video">
                <iframe src="<?php echo esc_url( get_post_meta( get_the_ID(), '_video_url', true ) ); ?>" width="100%" height="100%" frameborder="0" allowfullscreen></iframe>
            </div>
        <?php endif; ?>

        <!-- Text Content -->
        <div class="cortex-lesson-body cortex-prose">
            <?php while ( have_posts() ) : the_post(); ?>
                <?php the_content(); ?>
            <?php endwhile; ?>
            
            <div class="cortex-mt-40 cortex-pt-24 cortex-border-top cortex-flex cortex-justify-between cortex-align-center">
                 <button id="cortex-mark-complete" class="cortex-btn cortex-btn-success cortex-btn-lg" data-lesson-id="<?php the_ID(); ?>" data-course-id="<?php echo esc_attr( $course_id ); ?>">
                     <span class="dashicons dashicons-yes cortex-mr-8"></span>
                     <?php _e( 'Mark as Complete', 'cortex' ); ?>
                 </button>
            </div>
        </div>

    </main>

</div>

<?php 
// Enqueue Player Scripts
wp_footer(); 
?>
</body>
</html>
