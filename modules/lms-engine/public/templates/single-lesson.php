<?php
/**
 * Template: Single Lesson
 */

// Infer Course ID if not passed
$course_id = isset( $_GET['course_id'] ) ? intval( $_GET['course_id'] ) : 0;
// Fallback: If no course_id, we can't show sidebar correctly usually.
// Ideally usage is Strict.

$user_id = get_current_user_id();
$sections = [];
$progress_obj = new \Cotex\Modules\LMS_Engine\Progress();
$progress_pct = 0;

if ( $course_id ) {
	$sections = get_post_meta( $course_id, '_cortex_course_sections', true );
	$progress_pct = $progress_obj->get_course_progress_percentage( $user_id, $course_id );
}

$is_complete = $progress_obj->is_lesson_complete( $user_id, $course_id, get_the_ID() );

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php the_title(); ?> - <?php echo get_the_title( $course_id ); ?></title>
	<?php wp_head(); ?>
	<style>html { margin-top: 0 !important; }</style>
</head>
<body <?php body_class(); ?>>

<div class="cotex-lms-wrap">
	<!-- Sidebar Curriculum (Same as Course) -->
	<div class="cotex-lms-sidebar">
		<?php if( $course_id ): ?>
			<h2 class="cotex-course-title">
				<a href="<?php echo get_permalink( $course_id ); ?>" style="color:inherit; text-decoration:none;">
					<?php echo get_the_title( $course_id ); ?>
				</a>
			</h2>
		<?php endif; ?>

		<div class="cotex-progress-bar">
			<div class="cotex-progress-fill" style="width: <?php echo esc_attr( $progress_pct ); ?>%;"></div>
		</div>

		<div class="cotex-curriculum">
			<?php if ( ! empty( $sections ) ) : foreach ( $sections as $s ) : ?>
				<div class="cotex-curriculum-section">
					<div class="cotex-section-title"><?php echo esc_html( $s['title'] ); ?></div>
					<?php 
					if ( ! empty( $s['lessons'] ) ) : 
						foreach ( $s['lessons'] as $l_id ) : 
							$l = get_post( $l_id );
							if ( ! $l ) continue;
							
							$l_complete = $progress_obj->is_lesson_complete( $user_id, $course_id, $l_id );
							$d_active = ( get_the_ID() == $l_id ) ? 'active' : '';
							$link = add_query_arg( 'course_id', $course_id, get_permalink( $l_id ) );
					?>
					<a href="<?php echo esc_url( $link ); ?>" class="cotex-lesson-link <?php echo $d_active; ?> <?php echo $l_complete ? 'completed' : ''; ?>">
						<?php echo esc_html( $l->post_title ); ?>
					</a>
					<?php endforeach; endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<!-- Main Content -->
	<div class="cotex-lms-content">
		<div class="cotex-lesson-header">
			<h1 class="cotex-lesson-title"><?php the_title(); ?></h1>
		</div>
		
		<div class="cotex-content-body">
			<?php the_content(); ?>

			<div class="cotex-lesson-actions">
				<a href="<?php echo get_permalink( $course_id ); ?>" class="cotex-btn cotex-btn-secondary">&larr; Back to Course</a>
				
				<?php if ( ! $is_complete ) : ?>
					<button id="cotex-complete-lesson" class="cotex-btn">Mark Complete</button>
				<?php else : ?>
					<button class="cotex-btn" disabled style="background:#8CE65A; cursor:default;">Completed ✓</button>
					<!-- Next Lesson Logic Could Go Here -->
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

<?php wp_footer(); ?>
</body>
</html>
