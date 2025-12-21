<?php
/**
 * Cotex LMS Student Progress Dashboard
 */

require_once __DIR__ . '/app-shell-header.php';

$user_id = get_current_user_id();

// Placeholder query for "Enrolled" courses.
// In a real system, we'd query a pivot table or user meta like '_cortex_enrolled_courses'
$enrolled_query = new WP_Query([
	'post_type'      => 'cortex_course',
	'posts_per_page' => 5,
	'post_status'    => 'publish',
]);
?>

<div class="lms-page-container">
	<header class="dashboard-header">
		<h1 class="lms-page-title">Learning Dashboard</h1>
		<div class="user-stats">
			<div class="stat-pill">
				<span class="val">3</span>
				<span class="lab">Active Courses</span>
			</div>
			<div class="stat-pill">
				<span class="val">85%</span>
				<span class="lab">Avg. Completion</span>
			</div>
		</div>
	</header>

	<div class="dashboard-sections">
		<section class="db-section">
			<h2 class="section-heading">My Courses</h2>
			<div class="enrolled-list">
				<?php if ( $enrolled_query->have_posts() ) : ?>
					<?php while ( $enrolled_query->have_posts() ) : $enrolled_query->the_post(); ?>
						<div class="enrolled-item">
							<div class="item-thumb">
								<?php the_post_thumbnail('thumbnail'); ?>
							</div>
							<div class="item-info">
								<h3><?php the_title(); ?></h3>
								<div class="item-progress">
									<div class="progress-bar"><div class="fill" style="width: 65%;"></div></div>
									<span>65% Done</span>
								</div>
							</div>
							<div class="item-actions">
								<a href="<?php the_permalink(); ?>" class="lms-btn-action">Resume Learning</a>
							</div>
						</div>
					<?php endwhile; wp_reset_postdata(); ?>
				<?php else : ?>
					<p>You are not enrolled in any courses yet.</p>
				<?php endif; ?>
			</div>
		</section>

		<aside class="db-sidebar">
			<div class="lms-section-box">
				<h3 class="section-heading">Quick Analytics</h3>
				<div class="chart-placeholder" style="height: 200px; display:flex; align-items:center; justify-content:center; background: rgba(255,255,255,0.02); border-radius: 10px; border: 1px dashed var(--lms-border); color: var(--lms-text-secondary); font-size: 0.8rem;">
					Activity Heatmap
				</div>
			</div>
		</aside>
	</div>
</div>

<style>
.dashboard-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 50px; }
.user-stats { display: flex; gap: 20px; }
.stat-pill { background: var(--lms-surface); padding: 15px 30px; border-radius: 12px; border: 1px solid var(--lms-border); text-align: center; }
.stat-pill .val { display: block; font-family: var(--lms-font-heading); color: var(--lms-primary); font-size: 1.5rem; }
.stat-pill .lab { font-size: 0.75rem; color: var(--lms-text-secondary); text-transform: uppercase; letter-spacing: 1px; }

.dashboard-sections { display: grid; grid-template-columns: 1fr 350px; gap: 50px; }

.enrolled-item { display: flex; align-items: center; gap: 25px; padding: 25px; background: var(--lms-surface); border: 1px solid var(--lms-border); border-radius: 12px; margin-bottom: 20px; }
.item-thumb { width: 80px; height: 80px; border-radius: 8px; overflow: hidden; background: #000; }
.item-thumb img { width: 100%; height: 100%; object-fit: cover; }
.item-info { flex: 1; }
.item-info h3 { font-family: var(--lms-font-heading); font-size: 1.1rem; margin: 0 0 10px 0; }

.item-progress { display: flex; align-items: center; gap: 15px; }
.item-progress .progress-bar { flex: 1; height: 4px; background: rgba(255,255,255,0.05); border-radius: 5px; }
.item-progress .fill { height: 100%; background: var(--lms-primary); border-radius: 5px; box-shadow: 0 0 10px var(--lms-primary); }
.item-progress span { font-size: 0.8rem; color: var(--lms-text-secondary); }
</style>

<?php
require_once __DIR__ . '/app-shell-footer.php';
