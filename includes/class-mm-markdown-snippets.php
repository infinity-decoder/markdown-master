<?php
/**
 * Markdown Snippets Management for Markdown Master
 * 
 * Reusable markdown content with caching and rendering.
 * 
 * @package MarkdownMaster
 * @since 2.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MM_Markdown_Snippets {

    /** @var wpdb */
    protected $db;

    /** @var string */
    protected $table;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table = $wpdb->prefix . 'mm_markdown_snippets';
    }

    /**
     * Create markdown snippet
     * 
     * @param array $data Snippet data
     * @return int Snippet ID or 0
     */
    public function create_snippet( $data ) {
        $content = isset( $data['content'] ) ? $data['content'] : '';
        
        // Render markdown
        $markdown = new MM_Markdown();
        $rendered = $markdown->render_markdown( $content );

        $insert_data = array(
            'title'      => isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '',
            'content'    => $content,
            'rendered'   => $rendered,
            'created_by' => get_current_user_id(),
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
        );

        $formats = array( '%s', '%s', '%s', '%d', '%s', '%s' );

        $ok = $this->db->insert( $this->table, $insert_data, $formats );

        if ( ! $ok ) {
            return 0;
        }

        return (int) $this->db->insert_id;
    }

    /**
     * Update markdown snippet
     * 
     * @param int $id Snippet ID
     * @param array $data Snippet data
     * @return bool True on success
     */
    public function update_snippet( $id, $data ) {
        $id = absint( $id );
        if ( $id <= 0 ) {
            return false;
        }

        $fields = array();
        $formats = array();

        if ( isset( $data['title'] ) ) {
            $fields['title'] = sanitize_text_field( $data['title'] );
            $formats[] = '%s';
        }

        if ( isset( $data['content'] ) ) {
            $fields['content'] = $data['content'];
            $formats[] = '%s';
            
            // Re-render markdown
            $markdown = new MM_Markdown();
            $fields['rendered'] = $markdown->render_markdown( $data['content'] );
            $formats[] = '%s';
        }

        if ( empty( $fields ) ) {
            return false;
        }

        $fields['updated_at'] = current_time( 'mysql' );
        $formats[] = '%s';

        $updated = $this->db->update(
            $this->table,
            $fields,
            array( 'id' => $id ),
            $formats,
            array( '%d' )
        );

        // Clear cache
        MM_Cache::delete( 'md_snippet_' . $id, MM_Cache::GROUP_MARKDOWN );

        return $updated !== false;
    }

    /**
     * Delete snippet
     * 
     * @param int $id Snippet ID
     * @return bool True on success
     */
    public function delete_snippet( $id ) {
        $id = absint( $id );
        if ( $id <= 0 ) {
            return false;
        }

        MM_Cache::delete( 'md_snippet_' . $id, MM_Cache::GROUP_MARKDOWN );

        return (bool) $this->db->delete( $this->table, array( 'id' => $id ), array( '%d' ) );
    }

    /**
     * Get snippet
     * 
     * @param int $id Snippet ID
     * @return array|null Snippet data
     */
    public function get_snippet( $id ) {
        $id = absint( $id );
        if ( $id <= 0 ) {
            return null;
        }

        $cache_key = 'md_snippet_' . $id;
        $row = MM_Cache::get( $cache_key, MM_Cache::GROUP_MARKDOWN );

        if ( false === $row ) {
            $row = $this->db->get_row(
                $this->db->prepare(
                    "SELECT * FROM {$this->table} WHERE id = %d",
                    $id
                ),
                ARRAY_A
            );

            if ( $row ) {
                MM_Cache::set( $cache_key, $row, MM_Cache::get_ttl(), MM_Cache::GROUP_MARKDOWN );
            }
        }

        return $row;
    }

    /**
     * Get all snippets
     * 
     * @param array $args Query arguments
     * @return array Snippets
     */
    public function get_all_snippets( $args = array() ) {
        $limit = isset( $args['limit'] ) ? absint( $args['limit'] ) : 20;
        $offset = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;
        $search = isset( $args['search'] ) ? sanitize_text_field( $args['search'] ) : '';

        $where = '1=1';
        $params = array();

        if ( ! empty( $search ) ) {
            $where .= ' AND (title LIKE %s OR content LIKE %s)';
            $search_like = '%' . $this->db->esc_like( $search ) . '%';
            $params[] = $search_like;
            $params[] = $search_like;
        }

        $params[] = $limit;
        $params[] = $offset;

        $sql = "SELECT * FROM {$this->table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";

        if ( ! empty( $params ) ) {
            $sql = $this->db->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $this->db->get_results( $sql, ARRAY_A );

        return $rows ? $rows : array();
    }

    /**
     * Get rendered HTML for snippet
     * 
     * @param int $id Snippet ID
     * @return string Rendered HTML
     */
    public function get_rendered( $id ) {
        $snippet = $this->get_snippet( $id );
        
        if ( ! $snippet ) {
            return '';
        }

        return $snippet['rendered'];
    }

    /**
     * Refresh rendered content (re-process markdown)
     * Useful after markdown library updates
     * 
     * @param int $id Snippet ID
     * @return bool True on success
     */
    public function refresh_rendered( $id ) {
        $snippet = $this->get_snippet( $id );
        
        if ( ! $snippet ) {
            return false;
        }

        $markdown = new MM_Markdown();
        $rendered = $markdown->render_markdown( $snippet['content'] );

        $updated = $this->db->update(
            $this->table,
            array( 'rendered' => $rendered ),
            array( 'id' => $id ),
            array( '%s' ),
            array( '%d' )
        );

        MM_Cache::delete( 'md_snippet_' . $id, MM_Cache::GROUP_MARKDOWN );

        return $updated !== false;
    }
}
