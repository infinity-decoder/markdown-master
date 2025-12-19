<?php
/**
 * Cortex Submissions List Table
 *
 * @package Cortex
 * @subpackage Includes/Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Cortex_Submissions_List extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( array(
			'singular' => __( 'Submission', 'cortex' ),
			'plural'   => __( 'Submissions', 'cortex' ),
			'ajax'     => false,
		) );
	}

	/**
	 * Fetch data.
	 */
	public function prepare_items() {
		global $wpdb;
		$table = $wpdb->prefix . 'cortex_submissions';

		$per_page = 20;
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Pagination
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		// Query
		$total_items = $wpdb->get_var( "SELECT COUNT(id) FROM {$table}" );
		$this->items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A );

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page ),
		) );
	}

	/**
	 * Columns.
	 */
	public function get_columns() {
		return array(
			'cb'            => '<input type="checkbox" />',
			'student'       => __( 'Student', 'cortex' ),
			'assignment'    => __( 'Assignment', 'cortex' ),
			'submitted_at'  => __( 'Submitted At', 'cortex' ),
			'status'        => __( 'Status', 'cortex' ),
			'grade'         => __( 'Grade', 'cortex' ),
			'actions'       => __( 'Actions', 'cortex' ),
		);
	}

	/**
	 * Sortable Columns.
	 */
	public function get_sortable_columns() {
		return array(
			'submitted_at' => array( 'created_at', true ),
			'status'       => array( 'status', false ),
		);
	}

	/**
	 * Column: Checkbox.
	 */
	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="submission[]" value="%s" />', $item['id'] );
	}

	/**
	 * Column: Student.
	 */
	protected function column_student( $item ) {
		$user = get_userdata( $item['user_id'] );
		$avatar = get_avatar( $item['user_id'], 32 );
		return sprintf( '<div style="display:flex;align-items:center;gap:10px;">%s <strong>%s</strong><br><small>%s</small></div>', $avatar, esc_html( $user->display_name ), esc_html( $user->user_email ) );
	}

	/**
	 * Column: Assignment.
	 */
	protected function column_assignment( $item ) {
		return sprintf( '<a href="%s" target="_blank"><strong>%s</strong></a>', get_edit_post_link( $item['assignment_id'] ), get_the_title( $item['assignment_id'] ) );
	}

	/**
	 * Column: Submitted At.
	 */
	protected function column_submitted_at( $item ) {
		return esc_html( $item['created_at'] );
	}

	/**
	 * Column: Status.
	 */
	protected function column_status( $item ) {
		$status = $item['status'];
		$class = ( $status === 'graded' ) ? 'success' : 'warning';
		return sprintf( '<span class="cortex-badge cortex-badge-%s">%s</span>', $class, ucfirst( $status ) );
	}

	/**
	 * Column: Grade.
	 */
	protected function column_grade( $item ) {
		if ( $item['status'] === 'pending' ) {
			return '-';
		}
		$total_marks = get_post_meta( $item['assignment_id'], '_total_marks', true );
		return sprintf( '<strong>%s</strong> / %s', esc_html( $item['grade'] ), esc_html( $total_marks ) );
	}

	/**
	 * Column: Actions.
	 */
	protected function column_actions( $item ) {
        // Simple Grade Action (In real app, opens modal)
        $url = add_query_arg( array(
            'page' => 'cortex_submissions',
            'action' => 'grade',
            'submission_id' => $item['id']
        ), admin_url( 'admin.php' ) );
        
		return sprintf( '<a href="%s" class="button button-small">%s</a>', esc_url( $url ), __( 'Grade', 'cortex' ) );
	}
}
