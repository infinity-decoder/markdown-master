<?php
/**
 * Cotex LMS Course Overview
 */

require_once __DIR__ . '/app-shell-header.php';

$course_id = get_the_ID();
$author_id = get_post_field( 'post_author', $course_id );

// Fetch curriculum
$sections_order = get_post_meta( $course_id, '_cortex_sections_order', true ) ?: [];
?>

<div class="lms-page-container">
	<div class="lms-course-hero">
		<div class="hero-content">
			<h1 class="lms-course-title"><?php the_title(); ?></h1>
			<div class="course-meta">
				<span class="instructor">Instructor: <strong><?php echo get_the_author_meta('display_name', $author_id); ?></strong></span>
				<span class="updated">Last Updated: <?php echo get_the_modified_date(); ?></span>
			</div>
			<div class="hero-actions" style="margin-top: 30px;">
				<a href="#" class="lms-btn-action" style="font-size: 1.1rem; padding: 15px 40px;">Enroll in Course</a>
			</div>
		</div>
		<div class="hero-image">
			<?php if ( has_post_thumbnail() ) : the_post_thumbnail('large'); endif; ?>
		</div>
	</div>

	<div class="lms-content-split">
		<div class="lms-main-content">
			<section class="lms-section-box">
				<h2 class="section-heading">Description</h2>
				<div class="lms-typography">
					<?php the_content(); ?>
				</div>
			</section>

			<section class="lms-section-box">
				<h2 class="section-heading">Curriculum</h2>
				<div class="lms-curriculum-list">
					<?php if ( ! empty( $sections_order ) ) : ?>
						<?php foreach ( $sections_order as $s_id ) : ?>
							<?php 
							$s_post = get_post( $s_id );
							if ( ! $s_post ) continue;
							$lesson_ids = get_post_meta( $s_id, '_cortex_lessons_order', true ) ?: [];
							?>
							<div class="lms-curriculum-section">
								<div class="section-title"><?php echo esc_html( $s_post->post_title ); ?></div>
								<div class="section-lessons">
									<?php foreach ( $lesson_ids as $l_id ) : ?>
										<?php $l_post = get_post( $l_id ); ?>
										<div class="lesson-row">
											<span class="dashicons dashicons-video-alt3"></span>
											<span class="title"><?php echo esc_html( $l_post->post_title ); ?></span>
											<span class="status-icon dashicons dashicons-lock"></span>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endforeach; ?>
					<?php else : ?>
						<p>No curriculum added yet.</p>
					<?php endif; ?>
				</div>
			</section>
		</div>

		<div class="lms-sidebar-content">
			<div class="lms-sticky-card">
				<h3>Quick Stats</h3>
				<ul class="stats-list">
					<li><strong>Duration:</strong> 12 Hours</li>
					<li><strong>Lessons:</strong> <?php 
						$count = 0; 
						foreach($sections_order as $sid) $count += count(get_post_meta($sid, '_cortex_lessons_order', true) ?: []);
						echo $count;
					?></li>
					<li><strong>Level:</strong> Advanced</li>
				</ul>
			</div>
		</div>
	</div>
</div>

<style>
.lms-course-hero {
    display: flex;
    gap: 50px;
    background: var(--lms-surface);
    padding: 60px;
    border-radius: 20px;
    border: 1px solid var(--lms-border);
    margin-bottom: 50px;
}
.hero-content { flex: 1.5; }
.hero-image { flex: 1; border-radius: 12px; overflow: hidden; border: 1px solid var(--lms-border); }
.hero-image img { width: 100%; height: 100%; object-fit: cover; }
.lms-course-title { font-family: var(--lms-font-heading); color: var(--lms-primary); font-size: 2.8rem; margin: 0 0 20px 0; }
.course-meta { color: var(--lms-text-secondary); font-size: 0.9rem; display: flex; gap: 30px; }

.lms-content-split { display: grid; grid-template-columns: 1fr 350px; gap: 50px; }
.lms-section-box { background: var(--lms-surface); padding: 40px; border-radius: 12px; border: 1px solid var(--lms-border); margin-bottom: 30px; }
.section-heading { font-family: var(--lms-font-heading); font-size: 1.2rem; margin-bottom: 25px; border-bottom: 1px solid var(--lms-border); padding-bottom: 15px; }

.lms-curriculum-section { margin-bottom: 20px; border: 1px solid var(--lms-border); border-radius: 8px; overflow: hidden; }
.lms-curriculum-section .section-title { background: rgba(255,255,255,0.03); padding: 15px 25px; font-weight: 700; color: var(--lms-text-primary); border-bottom: 1px solid var(--lms-border); }
.lesson-row { padding: 12px 25px; display: flex; align-items: center; gap: 15px; border-bottom: 1px solid var(--lms-border); transition: background 0.2s; cursor: pointer; }
.lesson-row:last-child { border-bottom: none; }
.lesson-row:hover { background: rgba(255,255,255,0.02); }
.lesson-row .title { flex: 1; font-size: 0.95rem; }
.lesson-row .status-icon { font-size: 16px; color: var(--lms-text-secondary); }

.lms-sticky-card { position: sticky; top: 120px; background: var(--lms-surface); padding: 30px; border-radius: 12px; border: 1px solid var(--lms-border); }
.stats-list { list-style: none; padding: 0; margin: 0; }
.stats-list li { padding: 15px 0; border-bottom: 1px solid var(--lms-border); display: flex; justify-content: space-between; font-size: 0.9rem; }
.stats-list li:last-child { border-bottom: none; }
</style>

<?php
require_once __DIR__ . '/app-shell-footer.php';
