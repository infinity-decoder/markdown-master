<?php
/**
 * Template: Single Course
 */

get_header(); // We might want to skip standard header if "Focus Mode" implies full takeover, but usually Courses keep site nav.
// Actually prompt said "Frontend must not look like WordPress". "Distraction-free".
// If I use get_header(), I get the theme's header. 
// Let's TRY to use a custom wrapper if possible, or just inject our CSS to override content.
// For "Focus Mode" in LMS, usually it means NO header/footer of the main site.
// Let's create a standalone HTML structure for this template.

$course_id = get_the_ID();
// Fetch hierarchical data from Meta (IDs only)
$sections_order = get_post_meta( $course_id, '_cortex_sections_order', true ) ?: [];
$sections = [];
foreach($sections_order as $s_id) {
	$s_post = get_post($s_id);
	if(!$s_post) continue;
	$l_ids = get_post_meta($s_id, '_cortex_lessons_order', true) ?: [];
	$lessons = [];
	foreach($l_ids as $l_id) {
		$l_post = get_post($l_id);
		if($l_post) $lessons[] = [ 'id' => $l_id, 'title' => $l_post->post_title, 'content' => $l_post->post_content ];
	}
	$sections[] = [ 'id' => $s_id, 'title' => $s_post->post_title, 'lessons' => $lessons ];
}

$user_id   = get_current_user_id();
$progress  = 0;

if ( $user_id ) {
	$progress_class = new \Cotex\Modules\LMS_Engine\Progress();
	$progress = $progress_class->get_course_progress_percentage( $user_id, $course_id );
}

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php the_title(); ?> - <?php bloginfo( 'name' ); ?></title>
	<?php wp_head(); ?>
	<style>
		/* Override WP Admin Bar if visible for students? Maybe not. */
		html { margin-top: 0 !important; }
	</style>
</head>
<body <?php body_class(); ?>>

<div class="cotex-lms-wrap">
	<!-- Sidebar Curriculum -->
	<div class="cotex-lms-sidebar">
		<h1 class="cotex-course-title"><?php the_title(); ?></h1>
		
		<?php if ( $user_id ) : ?>
		<div class="cotex-progress-bar">
			<div class="cotex-progress-fill" style="width: <?php echo esc_attr( $progress ); ?>%;"></div>
		</div>
		<?php endif; ?>

		<div class="cotex-curriculum">
			<?php if ( ! empty( $sections ) ) : foreach ( $sections as $s ) : ?>
				<div class="cotex-curriculum-section">
					<div class="cotex-section-title"><?php echo esc_html( isset($s['title']) ? $s['title'] : 'Section' ); ?></div>
					<?php 
					if ( ! empty( $s['lessons'] ) ) : 
						foreach ( $s['lessons'] as $l ) : 
							$l_id = isset($l['id']) ? $l['id'] : '';
							$l_title = isset($l['title']) ? $l['title'] : 'Untitled Lesson';
							
							if ( ! $l_id ) continue;

							$is_complete = false;
							if ( $user_id ) {
								$match = get_user_meta( $user_id, "_cortex_progress_{$course_id}", true );
								if( is_array($match) && in_array($l_id, $match) ) $is_complete = true;
							}
							
							// Link to Single Lesson View?
							// Since lessons are now virtual inside the Course JSON, we cannot use get_permalink($l_id).
							// We must use the Course URL with a query param ?lesson_id=...
							$link = add_query_arg( 'lesson_id', $l_id, get_permalink( $course_id ) );
					?>
					<a href="<?php echo esc_url( $link ); ?>" class="cotex-lesson-link <?php echo $is_complete ? 'completed' : ''; ?>">
						<?php echo esc_html( $l_title ); ?>
					</a>
					<?php endforeach; endif; ?>
				</div>
			<?php endforeach; endif; ?>
		</div>
	</div>

	<!-- Main Content -->
	<div class="cotex-lms-content">
		<?php 
		// Check if we are viewing a specific lesson
		$current_lesson_id = isset($_GET['lesson_id']) ? sanitize_text_field($_GET['lesson_id']) : '';
		
		if ( $current_lesson_id ) {
			// Find lesson data
			$current_lesson = null;
			foreach($sections as $s) {
				if(!empty($s['lessons'])) {
					foreach($s['lessons'] as $l) {
						if(isset($l['id']) && $l['id'] === $current_lesson_id) {
							$current_lesson = $l;
							break 2;
						}
					}
				}
			}

			if ( $current_lesson ) {
				// Render Lesson Content
				?>
				<div class="cotex-lesson-header">
					<h2 class="cotex-lesson-title"><?php echo esc_html( $current_lesson['title'] ); ?></h2>
				</div>
				<div class="cotex-content-body">
					<?php echo wp_kses_post( $current_lesson['content'] ); ?>
					
					<div class="cotex-lesson-actions">
						<a href="<?php echo get_permalink(); ?>" class="cotex-btn cotex-btn-secondary">&larr; Course Home</a>
						<?php 
						$is_complete = false;
						if($user_id) {
							$match = get_user_meta( $user_id, "_cortex_progress_{$course_id}", true );
							if( is_array($match) && in_array($current_lesson_id, $match) ) $is_complete = true;
						}
						
						if ( ! $is_complete ) : ?>
							<button id="cotex-complete-lesson" class="cotex-btn" data-lesson-id="<?php echo esc_attr($current_lesson_id); ?>">Mark Complete</button>
						<?php else : ?>
							<button class="cotex-btn" disabled style="background:#8CE65A; cursor:default;">Completed ✓</button>
						<?php endif; ?>
					</div>
				</div>
				<script>
					// Pass dynamic lesson ID to JS
					var cotexCurrentLessonId = "<?php echo esc_js($current_lesson_id); ?>";
				</script>
				<?php
			} else {
				echo '<p>Lesson not found.</p>';
			}

		} else {
			// Course Overview
		?>
		<div class="cotex-lesson-header">
			<h2 class="cotex-lesson-title">Course Overview</h2>
		</div>
		
		<div class="cotex-content-body">
			<?php if ( has_post_thumbnail() ) : ?>
				<div class="cotex-course-thumbnail">
					<?php the_post_thumbnail( 'large' ); ?>
				</div>
			<?php endif; ?>
			
			<?php the_content(); ?>

			<div class="cotex-lesson-actions">
				<?php if ( ! $user_id ) : ?>
					<p>Please <a href="<?php echo wp_login_url( get_permalink() ); ?>">Login</a> to enroll.</p>
				<?php else : ?>
					<p>Select a lesson from the sidebar to start learning.</p>
				<?php endif; ?>
			</div>
		</div>
		<?php } ?>
	</div>
</div>

<?php wp_footer(); ?>
</body>
</html>
