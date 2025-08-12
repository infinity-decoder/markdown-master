<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MM_Results
 * Fetches and filters quiz attempts/results and provides ranking.
 */
class MM_Results {

    protected $wpdb;
    protected $table_attempts;
    protected $table_results;
    protected $table_quizzes;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_attempts = $wpdb->prefix . 'mm_quiz_attempts';
        $this->table_results = $wpdb->prefix . 'mm_quiz_results';
        $this->table_quizzes = $wpdb->prefix . 'mm_quizzes';
    }

    /**
     * Get attempts with filters, pagination and search
     *
     * $args = [
     *   quiz_id => int,
     *   user_name => string,
     *   user_email => string,
     *   user_class => string,
     *   user_section => string,
     *   min_score => float,
     *   max_score => float,
     *   page => int,
     *   per_page => int,
     *   order_by => 'score'|'completed_at',
     *   order => 'ASC'|'DESC'
     * ]
     */
    public function get_attempts( $args = [] ) {
        $defaults = [
            'page' => 1,
            'per_page' => 25,
            'order_by' => 'completed_at',
            'order' => 'DESC',
        ];
        $args = wp_parse_args( $args, $defaults );

        $where_clauses = [];
        $params = [];

        if ( ! empty( $args['quiz_id'] ) ) {
            $where_clauses[] = 'quiz_id = %d';
            $params[] = intval( $args['quiz_id'] );
        }
        if ( ! empty( $args['user_name'] ) ) {
            $where_clauses[] = 'user_name LIKE %s';
            $params[] = '%' . $this->wpdb->esc_like( $args['user_name'] ) . '%';
        }
        if ( ! empty( $args['user_email'] ) ) {
            $where_clauses[] = 'user_email LIKE %s';
            $params[] = '%' . $this->wpdb->esc_like( $args['user_email'] ) . '%';
        }
        if ( ! empty( $args['user_class'] ) ) {
            $where_clauses[] = 'user_class = %s';
            $params[] = sanitize_text_field( $args['user_class'] );
        }
        if ( ! empty( $args['user_section'] ) ) {
            $where_clauses[] = 'user_section = %s';
            $params[] = sanitize_text_field( $args['user_section'] );
        }
        if ( isset( $args['min_score'] ) && $args['min_score'] !== '' ) {
            $where_clauses[] = 'score >= %f';
            $params[] = floatval( $args['min_score'] );
        }
        if ( isset( $args['max_score'] ) && $args['max_score'] !== '' ) {
            $where_clauses[] = 'score <= %f';
            $params[] = floatval( $args['max_score'] );
        }

        $where_sql = '';
        if ( ! empty( $where_clauses ) ) {
            $where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
        }

        $order_by = in_array( $args['order_by'], [ 'score', 'completed_at' ], true ) ? $args['order_by'] : 'completed_at';
        $order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        $offset = ( $args['page'] - 1 ) * $args['per_page'];

        // Build prepared SQL
        // Prepare requires the exact number of placeholders; merge numeric params + limits
        $sql_base = "SELECT * FROM {$this->table_attempts} {$where_sql} ORDER BY {$order_by} {$order} LIMIT %d OFFSET %d";
        $params_for_prepare = $params;
        $params_for_prepare[] = intval( $args['per_page'] );
        $params_for_prepare[] = intval( $offset );

        $sql = call_user_func_array( [ $this->wpdb, 'prepare' ], array_merge( [ $sql_base ], $this->normalize_prepare_params( $params_for_prepare ) ) );
        $rows = $this->wpdb->get_results( $sql );

        return $rows;
    }

    /**
     * Count attempts for pagination
     */
    public function count_attempts( $args = [] ) {
        $where_clauses = [];
        $params = [];

        if ( ! empty( $args['quiz_id'] ) ) {
            $where_clauses[] = 'quiz_id = %d';
            $params[] = intval( $args['quiz_id'] );
        }
        if ( ! empty( $args['user_name'] ) ) {
            $where_clauses[] = 'user_name LIKE %s';
            $params[] = '%' . $this->wpdb->esc_like( $args['user_name'] ) . '%';
        }
        if ( ! empty( $args['user_email'] ) ) {
            $where_clauses[] = 'user_email LIKE %s';
            $params[] = '%' . $this->wpdb->esc_like( $args['user_email'] ) . '%';
        }
        if ( ! empty( $args['user_class'] ) ) {
            $where_clauses[] = 'user_class = %s';
            $params[] = sanitize_text_field( $args['user_class'] );
        }
        if ( ! empty( $args['user_section'] ) ) {
            $where_clauses[] = 'user_section = %s';
            $params[] = sanitize_text_field( $args['user_section'] );
        }
        if ( isset( $args['min_score'] ) && $args['min_score'] !== '' ) {
            $where_clauses[] = 'score >= %f';
            $params[] = floatval( $args['min_score'] );
        }
        if ( isset( $args['max_score'] ) && $args['max_score'] !== '' ) {
            $where_clauses[] = 'score <= %f';
            $params[] = floatval( $args['max_score'] );
        }

        $where_sql = '';
        if ( ! empty( $where_clauses ) ) {
            $where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
        }

        $sql_base = "SELECT COUNT(*) FROM {$this->table_attempts} {$where_sql}";
        $sql = call_user_func_array( [ $this->wpdb, 'prepare' ], array_merge( [ $sql_base ], $this->normalize_prepare_params( $params ) ) );
        return (int) $this->wpdb->get_var( $sql );
    }

    /**
     * Get per-question results for an attempt
     */
    public function get_results_for_attempt( $attempt_id ) {
        return $this->wpdb->get_results( $this->wpdb->prepare( "SELECT * FROM {$this->table_results} WHERE attempt_id = %d", intval( $attempt_id ) ) );
    }

    /**
     * Get ranking for a quiz with limit
     */
    public function get_ranking( $quiz_id, $limit = 50 ) {
        $quiz_id = intval( $quiz_id );
        $limit = intval( $limit );
        if ( ! $quiz_id ) {
            return [];
        }
        $sql = $this->wpdb->prepare( "SELECT * FROM {$this->table_attempts} WHERE quiz_id = %d AND completed_at IS NOT NULL ORDER BY score DESC, completed_at ASC LIMIT %d", $quiz_id, $limit );
        return $this->wpdb->get_results( $sql );
    }

    /**
     * Helper: ensures prepare params array is zero-indexed and strings are properly passed
     */
    protected function normalize_prepare_params( $params ) {
        // mysqli_prepare requires separate parameters in call_user_func_array, but we already build that
        return $params;
    }
}
