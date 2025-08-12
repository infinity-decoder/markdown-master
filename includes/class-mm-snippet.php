<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MM_Snippet
 * CRUD for code snippets.
 */
class MM_Snippet {

    protected $wpdb;
    protected $table;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'mm_code_snippets';
    }

    public function create_snippet( $data ) {
        $defaults = [
            'title' => '',
            'code' => '',
            'language' => 'text',
            'created_by' => get_current_user_id(),
        ];
        $data = wp_parse_args( $data, $defaults );

        $inserted = $this->wpdb->insert(
            $this->table,
            [
                'title' => sanitize_text_field( $data['title'] ),
                'code' => $data['code'], // allow code raw; sanitize when rendering
                'language' => sanitize_text_field( $data['language'] ),
                'created_by' => intval( $data['created_by'] ),
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%d', '%s' ]
        );

        if ( $inserted ) {
            return $this->wpdb->insert_id;
        }
        return false;
    }

    public function update_snippet( $id, $data ) {
        $data_db = [];
        if ( isset( $data['title'] ) ) {
            $data_db['title'] = sanitize_text_field( $data['title'] );
        }
        if ( isset( $data['code'] ) ) {
            $data_db['code'] = $data['code'];
        }
        if ( isset( $data['language'] ) ) {
            $data_db['language'] = sanitize_text_field( $data['language'] );
        }
        if ( empty( $data_db ) ) {
            return false;
        }
        return (bool) $this->wpdb->update( $this->table, $data_db, [ 'id' => intval( $id ) ] );
    }

    public function delete_snippet( $id ) {
        return (bool) $this->wpdb->delete( $this->table, [ 'id' => intval( $id ) ], [ '%d' ] );
    }

    public function get_snippet( $id ) {
        return $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", intval( $id ) ) );
    }

    public function get_all_snippets( $args = [] ) {
        $defaults = [
            'per_page' => 20,
            'page' => 1,
            'search' => '',
        ];
        $args = wp_parse_args( $args, $defaults );
        $offset = ( $args['page'] - 1 ) * $args['per_page'];

        $where = '';
        $params = [];
        if ( ! empty( $args['search'] ) ) {
            $where = "WHERE title LIKE %s";
            $params[] = '%' . $this->wpdb->esc_like( $args['search'] ) . '%';
        }

        if ( ! empty( $where ) ) {
            $sql = $this->wpdb->prepare( "SELECT * FROM {$this->table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d", array_merge( $params, [ $args['per_page'], $offset ] ) );
        } else {
            $sql = $this->wpdb->prepare( "SELECT * FROM {$this->table} ORDER BY created_at DESC LIMIT %d OFFSET %d", $args['per_page'], $offset );
        }

        return $this->wpdb->get_results( $sql );
    }
}
