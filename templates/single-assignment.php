<?php
/**
 * Template for displaying single assignment
 *
 * @package Cortex
 * @subpackage Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$assignment_id = get_the_ID();
$user_id = get_current_user_id();
// In a real app we'd fetch the submission record for this user
// $submission = Cortex_Assignments::get_submission( $assignment_id, $user_id );
$submission = null; // Placeholder
?>

<div class="cortex-lms-wrap cortex-assignment-single">
    <div class="cortex-container cortex-py-12">
        <div class="cortex-row">
            <div class="cortex-col-8 cortex-offset-2">
                
                <?php while ( have_posts() ) : the_post(); ?>
                    
                    <div class="cortex-card">
                        <div class="cortex-card-header cortex-bg-light cortex-p-6">
                            <span class="cortex-badge cortex-badge-info cortex-mb-2"><?php _e( 'Assignment', 'cortex' ); ?></span>
                            <h1 class="cortex-text-2xl cortex-font-bold"><?php the_title(); ?></h1>
                            <div class="cortex-assignment-meta cortex-mt-4 cortex-text-sm cortex-text-muted cortex-flex cortex-gap-6">
                                <span>
                                    <span class="dashicons dashicons-calendar-alt"></span> 
                                    <?php _e( 'Deadline:', 'cortex' ); ?> 
                                    <strong><?php echo esc_html( get_post_meta( $assignment_id, '_deadline', true ) ?: 'No deadline' ); ?></strong>
                                </span>
                                <span>
                                    <span class="dashicons dashicons-awards"></span> 
                                    <?php _e( 'Total Marks:', 'cortex' ); ?> 
                                    <strong><?php echo esc_html( get_post_meta( $assignment_id, '_total_marks', true ) ?: '100' ); ?></strong>
                                </span>
                            </div>
                        </div>

                        <div class="cortex-card-body cortex-p-8">
                            <div class="cortex-assignment-content cortex-prose cortex-mb-8">
                                <?php the_content(); ?>
                            </div>

                            <hr class="cortex-my-8">

                            <?php if ( ! is_user_logged_in() ) : ?>
                                <div class="cortex-alert cortex-alert-warning">
                                    <?php _e( 'Please login to submit this assignment.', 'cortex' ); ?>
                                </div>
                            <?php else : ?>
                                
                                <h3 class="cortex-mb-4"><?php _e( 'Your Submission', 'cortex' ); ?></h3>

                                <?php if ( $submission ) : ?>
                                    <!-- Submission Status View -->
                                    <div class="cortex-submission-status">
                                        <div class="cortex-alert cortex-alert-<?php echo $submission->status === 'graded' ? 'success' : 'info'; ?>">
                                            <?php echo sprintf( __( 'Status: %s', 'cortex' ), ucfirst( $submission->status ) ); ?>
                                        </div>
                                        <?php if ( $submission->status === 'graded' ) : ?>
                                            <div class="cortex-grade-result cortex-mt-4">
                                                <strong><?php _e( 'Grade:', 'cortex' ); ?></strong> 
                                                <?php echo esc_html( $submission->grade ); ?> / <?php echo esc_html( get_post_meta( $assignment_id, '_total_marks', true ) ); ?>
                                                <div class="cortex-feedback cortex-mt-2 cortex-p-4 cortex-bg-light cortex-rounded">
                                                    <strong><?php _e( 'Instructor Feedback:', 'cortex' ); ?></strong><br>
                                                    <?php echo wp_kses_post( $submission->instructor_feedback ); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else : ?>
                                    <!-- Submission Form -->
                                    <form class="cortex-assignment-form" method="post" enctype="multipart/form-data">
                                        <?php wp_nonce_field( 'cortex_assignment_submit', 'cortex_assignment_nonce' ); ?>
                                        <input type="hidden" name="action" value="cortex_submit_assignment">
                                        <input type="hidden" name="assignment_id" value="<?php echo esc_attr( $assignment_id ); ?>">

                                        <div class="cortex-form-group cortex-mb-4">
                                            <label class="cortex-form-label"><?php _e( 'Assignment Content', 'cortex' ); ?></label>
                                            <textarea name="content" class="cortex-form-control cortex-w-full" rows="6" placeholder="<?php esc_attr_e( 'Write your answer here...', 'cortex' ); ?>"></textarea>
                                        </div>

                                        <div class="cortex-form-group cortex-mb-6">
                                            <label class="cortex-form-label"><?php _e( 'Attach Files', 'cortex' ); ?></label>
                                            <input type="file" name="attachments[]" class="cortex-form-control" multiple>
                                            <p class="cortex-text-xs cortex-text-muted cortex-mt-1"><?php _e( 'Allowed: PDF, Doc, Zip. Max 5MB.', 'cortex' ); ?></p>
                                        </div>

                                        <button type="submit" class="cortex-btn cortex-btn-primary cortex-btn-lg">
                                            <?php _e( 'Submit Assignment', 'cortex' ); ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>

                        </div>
                    </div>

                <?php endwhile; ?>

            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>
