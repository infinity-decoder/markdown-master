<?php
/**
 * Cortex Assignments Manager
 *
 * Handles logic for assignment submissions, grading, and retrival.
 *
 * @package Cortex
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cortex_Assignments {

	public function __construct() {
		add_action( 'init', array( $this, 'handle_frontend_submission' ) );
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
        add_action( 'admin_post_cortex_save_grade', array( $this, 'handle_grade_save' ) );
	}

    /**
     * Register Admin Menu
     */
    public function register_admin_menu() {
        add_submenu_page(
            'cortex',
            __( 'Submissions', 'cortex' ),
            __( 'Submissions', 'cortex' ),
            'manage_options',
            'cortex_submissions',
            array( $this, 'render_submissions_page' )
        );
    }

    /**
     * Render Submissions Page
     */
    public function render_submissions_page() {
        $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
        
        if ( $action === 'grade' && isset( $_GET['submission_id'] ) ) {
            $this->render_grading_form( intval( $_GET['submission_id'] ) );
        } else {
            require_once CORTEX_PLUGIN_DIR . 'includes/admin/class-cortex-submissions-list.php';
            $table = new Cortex_Submissions_List();
            $table->prepare_items();
            ?>
            <div class="wrap">
                <h1 class="wp-heading-inline"><?php _e( 'Assignment Submissions', 'cortex' ); ?></h1>
                <form method="post">
                    <?php $table->display(); ?>
                </form>
            </div>
            <?php
        }
    }

    /**
     * Render Grading Form
     */
    private function render_grading_form( $submission_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'cortex_submissions';
        $submission = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $submission_id ) );
        
        if ( ! $submission ) {
            echo '<div class="error"><p>' . __( 'Submission not found.', 'cortex' ) . '</p></div>';
            return;
        }

        $assignment_title = get_the_title( $submission->assignment_id );
        $user = get_userdata( $submission->user_id );
        $total_marks = get_post_meta( $submission->assignment_id, '_total_marks', true );
        
        ?>
        <div class="wrap">
            <h1><?php echo sprintf( __( 'Grade Submission: %s', 'cortex' ), esc_html( $assignment_title ) ); ?></h1>
            <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
                <h3><?php echo sprintf( __( 'Student: %s', 'cortex' ), esc_html( $user->display_name ) ); ?></h3>
                
                <div class="cortex-submission-content" style="background:#f9f9f9; padding:15px; border:1px solid #ddd; border-radius:4px; margin-bottom:20px;">
                    <strong><?php _e( 'Answer:', 'cortex' ); ?></strong>
                    <p><?php echo nl2br( esc_html( $submission->content ) ); ?></p>
                    
                    <?php 
                    $attachments = json_decode( $submission->attachments, true );
                    if ( ! empty( $attachments ) ) {
                        // Display attachments logic here
                        echo '<p><strong>' . __( 'Attachments:', 'cortex' ) . '</strong> ' . count( $attachments ) . ' files</p>';
                    }
                    ?>
                </div>

                <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
                    <input type="hidden" name="action" value="cortex_save_grade">
                    <input type="hidden" name="submission_id" value="<?php echo esc_attr( $submission->id ); ?>">
                    <?php wp_nonce_field( 'cortex_save_grade_nonce', 'nonce' ); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="grade"><?php _e( 'Grade / Marks', 'cortex' ); ?></label></th>
                            <td>
                                <input type="number" name="grade" id="grade" value="<?php echo esc_attr( $submission->grade ); ?>" max="<?php echo esc_attr( $total_marks ); ?>" step="0.01" class="small-text">
                                <span class="description"><?php echo sprintf( __( 'Out of %s', 'cortex' ), esc_html( $total_marks ) ); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="feedback"><?php _e( 'Feedback', 'cortex' ); ?></label></th>
                            <td>
                                <textarea name="feedback" id="feedback" rows="5" class="large-text"><?php echo esc_textarea( $submission->instructor_feedback ); ?></textarea>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Grade', 'cortex' ); ?>">
                        <a href="<?php echo admin_url( 'admin.php?page=cortex_submissions' ); ?>" class="button"><?php _e( 'Cancel', 'cortex' ); ?></a>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Handle Grade Saving
     */
    public function handle_grade_save() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cortex_save_grade_nonce' ) ) {
            wp_die( 'Security check failed' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Permission denied' );
        }

        $submission_id = intval( $_POST['submission_id'] );
        $grade = floatval( $_POST['grade'] );
        $feedback = sanitize_textarea_field( $_POST['feedback'] );

        global $wpdb;
        $table = $wpdb->prefix . 'cortex_submissions';
        
        $wpdb->update(
            $table,
            array(
                'grade' => $grade,
                'instructor_feedback' => $feedback,
                'status' => 'graded',
                'updated_at' => current_time( 'mysql' )
            ),
            array( 'id' => $submission_id )
        );

        wp_redirect( admin_url( 'admin.php?page=cortex_submissions&msg=graded' ) );
        exit;
    }

    /**
     * Handle frontend form submission
     */
    public function handle_frontend_submission() {
        if ( ! isset( $_POST['cortex_assignment_nonce'] ) || ! wp_verify_nonce( $_POST['cortex_assignment_nonce'], 'cortex_assignment_submit' ) ) {
            return;
        }

        if ( ! is_user_logged_in() ) {
            return;
        }

        $assignment_id = intval( $_POST['assignment_id'] );
        $content = sanitize_textarea_field( $_POST['content'] );
        $user_id = get_current_user_id();

        // Handle File Uploads
        $attachments = array(); 
        if ( ! empty( $_FILES['attachments']['name'][0] ) ) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            $files = $_FILES['attachments'];
            $count = count( $files['name'] );

            for ( $i = 0; $i < $count; $i++ ) {
                if ( $files['error'][ $i ] !== 0 ) {
                    continue;
                }

                $file = array(
                    'name'     => $files['name'][ $i ],
                    'type'     => $files['type'][ $i ],
                    'tmp_name' => $files['tmp_name'][ $i ],
                    'error'    => $files['error'][ $i ],
                    'size'     => $files['size'][ $i ]
                );

                $upload_overrides = array( 'test_form' => false );
                $movefile = wp_handle_upload( $file, $upload_overrides );

                if ( $movefile && ! isset( $movefile['error'] ) ) {
                    $attachments[] = array(
                        'url' => $movefile['url'],
                        'file' => $movefile['file'],
                        'name' => $files['name'][ $i ]
                    );
                }
            }
        }

        // Save to DB
        global $wpdb;
        $table = $wpdb->prefix . 'cortex_submissions';
        
        // Find course ID from assignment parent (assuming hierarchy or meta)
        // For MVP, we pass it or lookup. Let's assume passed or 0.
        $course_id = 0; // TODO: Lookup parent course

        $result = $wpdb->insert(
            $table,
            array(
                'assignment_id' => $assignment_id,
                'user_id' => $user_id,
                'course_id' => $course_id,
                'content' => $content,
                'attachments' => json_encode( $attachments ),
                'status' => 'pending',
                'created_at' => current_time( 'mysql' )
            ),
            array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
        );

        if ( $result ) {
            // Redirect to avoid resubmission
            wp_redirect( get_permalink( $assignment_id ) . '?success=1' );
            exit;
        }
    }

	/**
	 * Get submission for a user
	 */
	public static function get_submission( $assignment_id, $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'cortex_submissions';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE assignment_id = %d AND user_id = %d", $assignment_id, $user_id ) );
		return $row;
	}
}
