<?php
/**
 * Cotex LMS Course Catalog
 */

require_once __DIR__ . '/app-shell-header.php';

$courses_query = new WP_Query([
	'post_type'      => 'cortex_course',
	'posts_per_page' => 12,
	'post_status'    => 'publish',
]);
?>

<div class="lms-page-container">
	<h1 class="lms-page-title">Available Courses</h1>
	
	<?php if ( $courses_query->have_posts() ) : ?>
		<div class="course-grid">
			<?php while ( $courses_query->have_posts() ) : $courses_query->the_post(); ?>
				<div class="course-card">
					<div class="course-card-thumb">
						<?php if ( has_post_thumbnail() ) : ?>
							<?php the_post_thumbnail('medium_large'); ?>
						<?php else : ?>
							<div style="width:100%; height:100%; background:rgba(47, 230, 214, 0.05); display:flex; align-items:center; justify-content:center; color:var(--lms-primary); font-family:var(--lms-font-heading); font-size:0.8rem;">
								UTILITY INTERFACE
							</div>
						<?php endif; ?>
					</div>
					<div class="course-card-content">
						<h3><?php the_title(); ?></h3>
						<p><?php echo wp_trim_words( get_the_excerpt(), 20 ); ?></p>
					</div>
					<div class="course-card-footer">
						<span class="user-info">By <?php echo get_the_author(); ?></span>
						<a href="<?php the_permalink(); ?>" class="lms-btn-action">View Course</a>
					</div>
				</div>
			<?php endwhile; wp_reset_postdata(); ?>
		</div>
	<?php else : ?>
		<div class="lms-empty-state">
			<p>No courses found in the catalog.</p>
		</div>
	<?php endif; ?>
</div>

<?php
require_once __DIR__ . '/app-shell-footer.php';
