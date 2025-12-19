<?php
/**
 * Question Bank Management for Cortex
 * 
 * Provides reusable question collections that can be imported into quizzes.
 * Supports all question types and metadata.
 * 
 * @package MarkdownMaster
 * @since 2.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cortex_Question_Bank {

    /** @var wpdb */
    protected $db;

    /** @var string */
    protected $table_banks;
    protected $table_bank_items;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table_banks = $wpdb->prefix . 'cortex_question_bank';
        $this->table_bank_items = $wpdb->prefix . 'cortex_question_bank_items';
    }

    /**
     * Create a question bank
     * 
     * @param array $data Bank data (sanitized externally)
     * @return int Bank ID or 0 on failure
     */
    public function create_bank( $data ) {
        $insert_data = array(
            'title'       => isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '',
            'description' => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '',
            'question_count' => 0, // Will be updated as questions are added
            'created_by'  => get_current_user_id(),
            'created_at'  => current_time( 'mysql' ),
            'updated_at'  => current_time( 'mysql' ),
        );

        $formats = array( '%s', '%s', '%d', '%d', '%s', '%s' );

        $ok = $this->db->insert( $this->table_banks, $insert_data, $formats );

        if ( ! $ok ) {
            return 0;
        }

        return (int) $this->db->insert_id;
    }

    /**
     * Update question bank
     * 
     * @param int $bank_id Bank ID
     * @param array $data Bank data
     * @return bool True on success
     */
    public function update_bank( $bank_id, $data ) {
        $bank_id = absint( $bank_id );
        if ( $bank_id <= 0 ) {
            return false;
        }

        $fields = array();
        $formats = array();

        if ( isset( $data['title'] ) ) {
            $fields['title'] = sanitize_text_field( $data['title'] );
            $formats[] = '%s';
        }

        if ( isset( $data['description'] ) ) {
            $fields['description'] = sanitize_textarea_field( $data['description'] );
            $formats[] = '%s';
        }

        if ( empty( $fields ) ) {
            return false;
        }

        $fields['updated_at'] = current_time( 'mysql' );
        $formats[] = '%s';

        $updated = $this->db->update(
            $this->table_banks,
            $fields,
            array( 'id' => $bank_id ),
            $formats,
            array( '%d' )
        );

        return $updated !== false;
    }

    /**
     * Delete question bank and all its questions
     * 
     * @param int $bank_id Bank ID
     * @return bool True on success
     */
    public function delete_bank( $bank_id ) {
        $bank_id = absint( $bank_id );
        if ( $bank_id <= 0 ) {
            return false;
        }

        // Delete all questions in this bank
        $this->db->delete( $this->table_bank_items, array( 'bank_id' => $bank_id ), array( '%d' ) );

        // Delete bank
        $this->db->delete( $this->table_banks, array( 'id' => $bank_id ), array( '%d' ) );

        return true;
    }

    /**
     * Get question bank
     * 
     * @param int $bank_id Bank ID
     * @param bool $with_questions Include questions
     * @return array|null Bank data
     */
    public function get_bank( $bank_id, $with_questions = false ) {
        $bank_id = absint( $bank_id );
        if ( $bank_id <= 0 ) {
            return null;
        }

        $row = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->table_banks} WHERE id = %d",
                $bank_id
            ),
            ARRAY_A
        );

        if ( ! $row ) {
            return null;
        }

        if ( $with_questions ) {
            $row['questions'] = $this->get_bank_questions( $bank_id );
        }

        return $row;
    }

    /**
     * Get all question banks
     * 
     * @param array $args Query arguments
     * @return array Banks
     */
    public function get_all_banks( $args = array() ) {
        $limit = isset( $args['limit'] ) ? absint( $args['limit'] ) : 20;
        $offset = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;
        $search = isset( $args['search'] ) ? sanitize_text_field( $args['search'] ) : '';

        $where = '1=1';
        $params = array();

        if ( ! empty( $search ) ) {
            $where .= ' AND title LIKE %s';
            $params[] = '%' . $this->db->esc_like( $search ) . '%';
        }

        $params[] = $limit;
        $params[] = $offset;

        $sql = "SELECT * FROM {$this->table_banks} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";

        if ( ! empty( $params ) ) {
            $sql = $this->db->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $this->db->get_results( $sql, ARRAY_A );

        return $rows ? $rows : array();
    }

    /**
     * Add question to bank
     * 
     * @param int $bank_id Bank ID
     * @param array $question_data Question data (sanitized externally via Cortex_Security)
     * @return int Question ID or 0
     */
    public function add_question_to_bank( $bank_id, $question_data ) {
        $bank_id = absint( $bank_id );
        if ( $bank_id <= 0 ) {
            return 0;
        }

        // Encode JSON fields
        $options = isset( $question_data['options'] ) ? wp_json_encode( $question_data['options'] ) : '[]';
        $correct_answer = isset( $question_data['correct_answer'] ) ? wp_json_encode( $question_data['correct_answer'] ) : 'null';
        $metadata = isset( $question_data['metadata'] ) ? wp_json_encode( $question_data['metadata'] ) : '{}';

        $insert_data = array(
            'bank_id'        => $bank_id,
            'question_text'  => isset( $question_data['question_text'] ) ? $question_data['question_text'] : '',
            'type'           => isset( $question_data['type'] ) ? $question_data['type'] : 'radio',
            'options'        => $options,
            'correct_answer' => $correct_answer,
            'points'         => isset( $question_data['points'] ) ? floatval( $question_data['points'] ) : 1,
            'hint'           => isset( $question_data['hint'] ) ? $question_data['hint'] : '',
            'metadata'       => $metadata,
            'created_at'     => current_time( 'mysql' ),
        );

        $formats = array( '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s' );

        $ok = $this->db->insert( $this->table_bank_items, $insert_data, $formats );

        if ( ! $ok ) {
            return 0;
        }

        // Update question count in bank
        $this->update_question_count( $bank_id );

        return (int) $this->db->insert_id;
    }

    /**
     * Update question in bank
     * 
     * @param int $question_id Question ID
     * @param array $question_data Question data
     * @return bool True on success
     */
    public function update_bank_question( $question_id, $question_data ) {
        $question_id = absint( $question_id );
        if ( $question_id <= 0 ) {
            return false;
        }

        $fields = array();
        $formats = array();

        $allowed_fields = array( 'question_text', 'type', 'options', 'correct_answer', 'points', 'hint', 'metadata' );

        foreach ( $allowed_fields as $field ) {
            if ( isset( $question_data[ $field ] ) ) {
                if ( in_array( $field, array( 'options', 'correct_answer', 'metadata' ), true ) ) {
                    $fields[ $field ] = wp_json_encode( $question_data[ $field ] );
                    $formats[] = '%s';
                } elseif ( 'points' === $field ) {
                    $fields[ $field ] = floatval( $question_data[ $field ] );
                    $formats[] = '%f';
                } else {
                    $fields[ $field ] = $question_data[ $field ];
                    $formats[] = '%s';
                }
            }
        }

        if ( empty( $fields ) ) {
            return false;
        }

        $updated = $this->db->update(
            $this->table_bank_items,
            $fields,
            array( 'id' => $question_id ),
            $formats,
            array( '%d' )
        );

        return $updated !== false;
    }

    /**
     * Delete question from bank
     * 
     * @param int $question_id Question ID
     * @return bool True on success
     */
    public function delete_bank_question( $question_id ) {
        $question_id = absint( $question_id );
        if ( $question_id <= 0 ) {
            return false;
        }

        // Get bank ID for count update
        $bank_id = $this->db->get_var(
            $this->db->prepare(
                "SELECT bank_id FROM {$this->table_bank_items} WHERE id = %d",
                $question_id
            )
        );

        $deleted = $this->db->delete( $this->table_bank_items, array( 'id' => $question_id ), array( '%d' ) );

        if ( $deleted && $bank_id ) {
            $this->update_question_count( $bank_id );
        }

        return (bool) $deleted;
    }

    /**
     * Get questions from bank
     * 
     * @param int $bank_id Bank ID
     * @param array $args Query arguments
     * @return array Questions
     */
    public function get_bank_questions( $bank_id, $args = array() ) {
        $bank_id = absint( $bank_id );
        if ( $bank_id <= 0 ) {
            return array();
        }

        $limit = isset( $args['limit'] ) ? absint( $args['limit'] ) : 100;
        $offset = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

        $sql = $this->db->prepare(
            "SELECT * FROM {$this->table_bank_items} WHERE bank_id = %d ORDER BY id ASC LIMIT %d OFFSET %d",
            $bank_id,
            $limit,
            $offset
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $this->db->get_results( $sql, ARRAY_A );

        if ( ! $rows ) {
            return array();
        }

        // Decode JSON fields
        foreach ( $rows as &$r ) {
            $r['options'] = json_decode( $r['options'], true );
            $r['correct_answer'] = json_decode( $r['correct_answer'], true );
            $r['metadata'] = json_decode( $r['metadata'], true );
        }
        unset( $r );

        return $rows;
    }

    /**
     * Import questions from bank to quiz
     * 
     * @param int $quiz_id Quiz ID
     * @param int $bank_id Bank ID
     * @param array $question_ids Question IDs to import (empty = all)
     * @return int Number of questions imported
     */
    public function import_questions_to_quiz( $quiz_id, $bank_id, $question_ids = array() ) {
        $quiz_id = absint( $quiz_id );
        $bank_id = absint( $bank_id );

        if ( $quiz_id <= 0 || $bank_id <= 0 ) {
            return 0;
        }

        // Get questions
        $questions = $this->get_bank_questions( $bank_id );

        if ( empty( $questions ) ) {
            return 0;
        }

        // Filter by IDs if specified
        if ( ! empty( $question_ids ) ) {
            $question_ids = array_map( 'absint', $question_ids );
            $questions = array_filter( $questions, function( $q ) use ( $question_ids ) {
                return in_array( (int) $q['id'], $question_ids, true );
            });
        }

        $quiz_model = new Cortex_Quiz();
        $imported = 0;

        foreach ( $questions as $q ) {
            $question_data = array(
                'question_bank_id' => $bank_id,
                'question_text'    => $q['question_text'],
                'type'             => $q['type'],
                'options'          => $q['options'],
                'correct_answer'   => $q['correct_answer'],
                'points'           => $q['points'],
                'hint'             => $q['hint'],
                'metadata'         => $q['metadata'],
            );

            $question_id = $quiz_model->add_question( $quiz_id, $question_data );

            if ( $question_id > 0 ) {
                $imported++;
            }
        }

        return $imported;
    }

    /**
     * Update question count for a bank
     * 
     * @param int $bank_id Bank ID
     * @return bool True on success
     */
    protected function update_question_count( $bank_id ) {
        $bank_id = absint( $bank_id );
        if ( $bank_id <= 0 ) {
            return false;
        }

        $count = $this->db->get_var(
            $this->db->prepare(
                "SELECT COUNT(*) FROM {$this->table_bank_items} WHERE bank_id = %d",
                $bank_id
            )
        );

        $this->db->update(
            $this->table_banks,
            array( 'question_count' => absint( $count ) ),
            array( 'id' => $bank_id ),
            array( '%d' ),
            array( '%d' )
        );

        return true;
    }

    /**
     * Get total question count across all banks
     * 
     * @return int Total questions
     */
    public function get_total_questions_count() {
        return (int) $this->db->get_var( "SELECT COUNT(*) FROM {$this->table_bank_items}" );
    }
}
