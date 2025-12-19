<?php
/**
 * Template for displaying single lesson (Focus Mode)
 *
 * @package Cortex
 * @subpackage Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// We do NOT call get_header() standard to avoid site navigation distraction
// Instead we build a minimal header or include assets manually if needed, 
// OR we use a specialized header if the theme supports it.
// For now, we'll include wp_head() and build a custom wrapper.

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
    <style>
        /* Minimal Reset for Focus Mode */
        body { margin: 0; padding: 0; background: #f8fafc; font-family: var(--cortex-font-sans, sans-serif); }
        .cortex-focus-mode { display: flex; height: 100vh; overflow: hidden; }
        .cortex-focus-sidebar { width: 350px; background: #fff; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; overflow-y: auto; }
        .cortex-focus-content { flex: 1; overflow-y: auto; padding: 40px; }
        .cortex-lesson-video { margin-bottom: 30px; background: #000; border-radius: 8px; overflow: hidden; }
        .cortex-focus-header { padding: 20px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; }
        .cortex-back-link { text-decoration: none; color: #64748b; font-size: 14px; display: flex; align-items: center; gap: 5px; }
        .cortex-focus-sidebar .cortex-accordion-header { padding: 15px 20px; background: #f1f5f9; font-weight: 600; font-size: 14px; }
        .cortex-focus-sidebar .cortex-lesson-item { padding: 12px 20px; font-size: 14px; color: #334155; border-bottom: 1px solid #f1f5f9; cursor: pointer; display: block; text-decoration: none; }
        .cortex-focus-sidebar .cortex-lesson-item:hover { background: #f8fafc; }
        .cortex-focus-sidebar .cortex-lesson-item.current { background: #eff6ff; border-left: 3px solid #3b82f6; color: #1e293b; font-weight: 500; }
        .cortex-complete-actions { margin-top: 40px; padding: 20px; background: #fff; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
    </style>
</head>

<body <?php body_class(); ?>>

<?php 
// Context
$lesson_id = get_the_ID();
// We need the Course ID. In Tutor, lessons are children of courses OR linked via meta. 
// For our implementation, we need to find which course this lesson belongs to.
// Assuming we store '_cortex_course_id' on the lesson, OR we passed it via query var, OR we look up the parent if hierarchical with posts.
// Phase 1 choice was: CPT 'cortex_lesson'. We need to link it.
// Let's assume for now we look up the course via a relationship meta '_cortex_course_id'.
$course_id = get_post_meta( $lesson_id, '_cortex_course_id', true );

// If not direct meta, maybe we find it via query string if coming from course page? e.g. ?course_id=123
if ( ! $course_id && isset( $_GET['course_id'] ) ) {
    $course_id = intval( $_GET['course_id'] );
}

// Fallback logic in a real app would be robust.

// Get basic Nav
$prev_lesson = '#'; // TODO: Calc via Player Class
$next_lesson = '#'; 
?>

<div class="cortex-focus-mode">
    
    <!-- Left Sidebar: Curriculum -->
    <div class="cortex-focus-sidebar">
        <div class="cortex-focus-header">
            <a href="<?php echo $course_id ? get_permalink( $course_id ) : home_url(); ?>" class="cortex-back-link">
                <span class="dashicons dashicons-arrow-left-alt2"></span> <?php _e( 'Back to Course', 'cortex' ); ?>
            </a>
        </div>
        
        <div class="cortex-focus-curriculum">
             <?php 
             // We can reuse the curriculum template part but simplified, or render custom here.
             // Ideally reusing reusing template part 'single/course/curriculum' but passing args to highlight current.
             // For now, let's render it simply using the Course ID.
             if ( $course_id ) {
                 // Get curriculum
                 $curriculum_json = get_post_meta( $course_id, '_cortex_curriculum', true );
                 $curriculum = json_decode( $curriculum_json, true );
                 
                 if ( $curriculum ) {
                     foreach ( $curriculum as $section ) {
                         echo '<div class="cortex-accordion-header">' . esc_html( $section['title'] ) . '</div>';
                         foreach ( $section['lessons'] as $l ) {
                             // This is placeholder logic from Phase 1 curriculum array structure
                             // In real DB it would be IDs.
                             // Assuming $l has 'id' or we match by title?
                             // Phase 1 dummy data had title/type/duration.
                             // We'll match loosely for this template draft.
                             $is_current = ( get_the_title() === $l['title'] ); 
                             $class = $is_current ? 'current' : '';
                             echo '<a href="#" class="cortex-lesson-item ' . $class . '">';
                             echo '<span class="dashicons dashicons-media-text" style="font-size:14px; margin-right:5px;"></span> ';
                             echo esc_html( $l['title'] );
                             echo '</a>';
                         }
                     }
                 } else {
                     echo '<div class="cortex-p-4 text-muted">' . __( 'No curriculum data.', 'cortex' ) . '</div>';
                 }
             } else {
                 echo '<div class="cortex-p-4">' . __( 'Course Context Missing.', 'cortex' ) . '</div>';
             }
             ?>
        </div>
    </div>

    <!-- Main Content -->
    <div class="cortex-focus-content">
        <?php while ( have_posts() ) : the_post(); ?>
            <div class="cortex-container-max" style="max-width: 900px; margin: 0 auto;">
                
                <header class="cortex-lesson-header cortex-mb-6">
                    <h1 class="cortex-text-3xl cortex-font-bold"><?php the_title(); ?></h1>
                </header>
                
                <?php 
                $video_url = get_post_meta( get_the_ID(), '_video_url', true );
                if ( $video_url ) : 
                ?>
                    <div class="cortex-lesson-video cortext-aspect-video">
                        <!-- Video Embed Logic (Plyr wrapper) -->
                        <div class="cortex-video-player" data-src="<?php echo esc_url( $video_url ); ?>">
                             <!-- Placeholder for video player -->
                             <iframe src="<?php echo esc_url( $video_url ); ?>" width="100%" height="500" frameborder="0" allowfullscreen></iframe>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="cortex-lesson-body cortex-prose cortex-mb-12">
                    <?php the_content(); ?>
                </div>
                
                <div class="cortex-complete-actions">
                    <a href="<?php echo $prev_lesson; ?>" class="cortex-btn cortex-btn-outline"><?php _e( 'Previous', 'cortex' ); ?></a>
                    
                    <form class="cortex-mark-complete-form" data-lesson-id="<?php echo get_the_ID(); ?>" data-course-id="<?php echo $course_id; ?>">
                        <button type="submit" class="cortex-btn cortex-btn-primary cortex-btn-lg">
                            <span class="dashicons dashicons-yes"></span> <?php _e( 'Mark as Complete', 'cortex' ); ?>
                        </button>
                    </form>
                    
                    <a href="<?php echo $next_lesson; ?>" class="cortex-btn cortex-btn-outline"><?php _e( 'Next', 'cortex' ); ?></a>
                </div>

            </div>
        <?php endwhile; ?>
    </div>

</div>

<?php wp_footer(); ?>
</body>
</html>
