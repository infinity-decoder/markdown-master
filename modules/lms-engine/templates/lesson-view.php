<?php
/**
 * Cotex LMS Lesson View
 */

require_once __DIR__ . '/app-shell-header.php';

$lesson_id = get_the_ID();
$course_id = get_post_meta( $lesson_id, '_cortex_course_id', true );

// Fetch hierarchy for sidebar
$sections_order = get_post_meta( $course_id, '_cortex_sections_order', true ) ?: [];

// Progress
$user_id = get_current_user_id();
$is_completed = get_user_meta( $user_id, "_cortex_lesson_completed_{$lesson_id}", true );
?>

<div class="lms-lesson-view">
	<!-- SIDEBAR NAV -->
	<aside class="lms-lesson-sidebar styled-scroll">
		<div class="sidebar-header">
			<a href="<?php echo get_permalink($course_id); ?>" class="back-to-course">&larr; Back to Course</a>
			<h3>Curriculum</h3>
		</div>
		<div class="sidebar-tree">
			<?php foreach ( $sections_order as $s_id ) : ?>
				<?php 
				$s_post = get_post( $s_id );
				$l_ids = get_post_meta( $s_id, '_cortex_lessons_order', true ) ?: [];
				?>
				<div class="sidebar-section">
					<div class="section-label"><?php echo esc_html($s_post->post_title); ?></div>
					<div class="section-lessons">
						<?php foreach ( $l_ids as $l_id ) : ?>
							<?php 
							$l_post = get_post( $l_id ); 
							$l_active = ($l_id == $lesson_id) ? 'active' : '';
							$l_done = get_user_meta($user_id, "_cortex_lesson_completed_{$l_id}", true) ? 'done' : '';
							?>
							<a href="<?php echo get_permalink($l_id); ?>" class="lesson-link <?php echo $l_active; ?> <?php echo $l_done; ?>">
								<span class="status-indicator"></span>
								<span class="l-title"><?php echo esc_html($l_post->post_title); ?></span>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</aside>

	<!-- CONTENT CANVAS -->
	<div class="lms-lesson-content styled-scroll">
		<div class="lesson-wrapper">
			<div class="lesson-top-bar">
				<div class="progress-bar-container">
					<div class="progress-fill" style="width: 45%;"></div>
				</div>
				<span class="progress-text">45% Completed</span>
			</div>

			<article class="lesson-main">
				<?php 
				$video_url = get_post_meta($lesson_id, '_cortex_video_url', true);
				if ( $video_url ) : 
				?>
					<div class="lesson-media">
						<div class="video-container">
							<?php echo wp_oembed_get( $video_url ); ?>
						</div>
					</div>
				<?php endif; ?>

				<h1 class="lesson-title"><?php the_title(); ?></h1>
				
				<div class="lesson-text lms-typography">
					<?php the_content(); ?>
				</div>

				<div class="lesson-footer-actions">
					<button class="lms-btn-action mark-complete-btn <?php echo $is_completed ? 'completed' : ''; ?>" data-id="<?php echo $lesson_id; ?>">
						<?php echo $is_completed ? 'Completed ✓' : 'Mark as Complete'; ?>
					</button>
					
					<div class="nav-links">
						<!-- Logic for next/prev would go here -->
						<a href="#" class="lms-btn-secondary">Next Lesson &rarr;</a>
					</div>
				</div>
			</article>
		</div>
	</div>
</div>

<style>
.lms-lesson-view { display: flex; height: calc(100vh - 70px); overflow: hidden; }

/* Sidebar */
.lms-lesson-sidebar { width: 350px; background: #0e1635; border-right: 1px solid var(--lms-border); display: flex; flex-direction: column; }
.sidebar-header { padding: 30px; border-bottom: 1px solid var(--lms-border); }
.back-to-course { color: var(--lms-primary); text-decoration: none; font-size: 0.85rem; display: block; margin-bottom: 20px; font-weight: 700; }
.sidebar-header h3 { font-family: var(--lms-font-heading); font-size: 1rem; color: var(--lms-text-primary); margin: 0; }

.sidebar-tree { flex: 1; padding: 20px 0; }
.sidebar-section { margin-bottom: 25px; }
.section-label { padding: 0 30px 10px 30px; font-size: 0.75rem; font-weight: 700; color: var(--lms-text-secondary); text-transform: uppercase; letter-spacing: 1px; }

.lesson-link { display: flex; align-items: center; padding: 12px 30px; text-decoration: none; color: var(--lms-text-secondary); font-size: 0.9rem; transition: all 0.2s; border-left: 3px solid transparent; }
.lesson-link:hover { background: rgba(255,255,255,0.03); color: var(--lms-text-primary); }
.lesson-link.active { background: rgba(47, 230, 214, 0.05); color: var(--lms-primary); border-left-color: var(--lms-primary); }
.lesson-link.done .status-indicator { background: var(--lms-success); box-shadow: 0 0 10px var(--lms-success); }

.status-indicator { width: 8px; height: 8px; border-radius: 50%; background: rgba(255,255,255,0.1); margin-right: 15px; }

/* Content */
.lms-lesson-content { flex: 1; background: var(--lms-bg); overflow-y: auto; padding: 60px 0; }
.lesson-wrapper { max-width: 900px; margin: 0 auto; width: 90%; }

.lesson-top-bar { display: flex; align-items: center; gap: 20px; margin-bottom: 40px; }
.progress-bar-container { flex: 1; height: 6px; background: rgba(255,255,255,0.05); border-radius: 10px; overflow: hidden; }
.progress-fill { height: 100%; background: var(--lms-primary); }
.progress-text { font-size: 0.8rem; color: var(--lms-text-secondary); }

.lesson-media { margin-bottom: 50px; border-radius: 12px; overflow: hidden; background: #000; box-shadow: 0 20px 50px rgba(0,0,0,0.4); }
.video-container { position: relative; padding-bottom: 56.25%; height: 0; }
.video-container iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }

.lesson-title { font-family: var(--lms-font-heading); color: var(--lms-primary); font-size: 2.5rem; margin-bottom: 30px; }
.lesson-text { font-size: 1.1rem; line-height: 1.8; color: var(--lms-text-primary); }

.lesson-footer-actions { margin-top: 60px; padding-top: 40px; border-top: 1px solid var(--lms-border); display: flex; justify-content: space-between; align-items: center; }
.lms-btn-action.completed { background: var(--lms-success); pointer-events: none; }
</style>

<?php
require_once __DIR__ . '/app-shell-footer.php';
