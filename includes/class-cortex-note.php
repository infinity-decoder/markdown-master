<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Cortex_Note
 * CRUD for markdown notes.
 */
class Cortex_Note {

    protected $wpdb;
    protected $table;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'cortex_notes';
    }

    public function create_note( $data ) {
        $defaults = [
            'title' => '',
            'content' => '',
            'created_by' => get_current_user_id(),
        ];
        $data = wp_parse_args( $data, $defaults );

        $inserted = $this->wpdb->insert(
            $this->table,
            [
                'title' => sanitize_text_field( $data['title'] ),
                'content' => wp_kses_post( $data['content'] ),
                'created_by' => intval( $data['created_by'] ),
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%d', '%s' ]
        );

        if ( $inserted ) {
            return $this->wpdb->insert_id;
        }

        return false;
    }

    public function update_note( $note_id, $data ) {
        $data_db = [];
        if ( isset( $data['title'] ) ) {
            $data_db['title'] = sanitize_text_field( $data['title'] );
        }
        if ( isset( $data['content'] ) ) {
            $data_db['content'] = wp_kses_post( $data['content'] );
        }
        if ( empty( $data_db ) ) {
            return false;
        }
        return (bool) $this->wpdb->update( $this->table, $data_db, [ 'id' => intval( $note_id ) ] );
    }

    public function delete_note( $note_id ) {
        return (bool) $this->wpdb->delete( $this->table, [ 'id' => intval( $note_id ) ], [ '%d' ] );
    }

    public function get_note( $note_id ) {
        return $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", intval( $note_id ) ) );
    }

    public function get_all_notes( $args = [] ) {
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
