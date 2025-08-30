<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Data/model layer for quizzes, questions and attempts.
 * - All methods avoid echo/print; no output is produced here.
 * - JSON is used for complex fields. We auto-detect JSON vs serialized when reading.
 */
class MM_Quiz {

    /** @var wpdb */
    protected $db;

    /** @var string */
    protected $table_quizzes;
    protected $table_questions;
    protected $table_attempts;
    protected $table_attempt_answers;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table_quizzes         = $wpdb->prefix . 'mm_quizzes';
        $this->table_questions       = $wpdb->prefix . 'mm_questions';
        $this->table_attempts        = $wpdb->prefix . 'mm_attempts';
        $this->table_attempt_answers = $wpdb->prefix . 'mm_attempt_answers';
    }

    /* =========================
     * Helpers
     * ========================= */

    protected function now() {
        return current_time( 'mysql' );
    }

    protected function normalize_bool( $v ) {
        return (int) ( ! empty( $v ) );
    }

    protected function encode_data( $data ) {
        if ( is_string( $data ) ) {
            return $data;
        }
        return wp_json_encode( $data, JSON_UNESCAPED_UNICODE );
    }

    protected function decode_data( $data ) {
        if ( is_array( $data ) || is_object( $data ) ) {
            return $data;
        }
        if ( is_string( $data ) ) {
            $json = json_decode( $data, true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                return $json;
            }
            // Fallback for legacy serialized values.
            if ( is_serialized( $data ) ) {
                $maybe = @maybe_unserialize( $data );
                if ( is_array( $maybe ) || is_object( $maybe ) ) {
                    return $maybe;
                }
            }
        }
        return $data;
    }

    protected function clean_question_type( $type ) {
        $type = strtolower( sanitize_text_field( (string) $type ) );
        $allowed = array( 'single', 'multiple', 'text', 'textarea' );
        return in_array( $type, $allowed, true ) ? $type : 'single';
    }

    /* =========================
     * Quizzes
     * ========================= */

    /**
     * Create a quiz. Returns new quiz ID or 0 on failure.
     */
    public function create_quiz( array $data ) {
        $title            = isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '';
        $description      = isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : '';
        $settings         = isset( $data['settings'] ) ? $this->encode_data( $data['settings'] ) : $this->encode_data( array() );
        $shuffle          = isset( $data['shuffle'] ) ? $this->normalize_bool( $data['shuffle'] ) : 0;
        $time_limit       = isset( $data['time_limit'] ) ? intval( $data['time_limit'] ) : 0;
        $attempts_allowed = isset( $data['attempts_allowed'] ) ? intval( $data['attempts_allowed'] ) : 0;
        $show_answers     = isset( $data['show_answers'] ) ? $this->normalize_bool( $data['show_answers'] ) : 0;

        $inserted = $this->db->insert(
            $this->table_quizzes,
            array(
                'title'            => $title,
                'description'      => $description,
                'settings'         => $settings,
                'shuffle'          => $shuffle,
                'time_limit'       => $time_limit,
                'attempts_allowed' => $attempts_allowed,
                'show_answers'     => $show_answers,
                'created_at'       => $this->now(),
                'updated_at'       => $this->now(),
            ),
            array( '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s' )
        );

        if ( ! $inserted ) {
            return 0;
        }
        return (int) $this->db->insert_id;
    }

    /**
     * Update a quiz. Returns true/false.
     */
    public function update_quiz( $id, array $data ) {
        $id = (int) $id;
        if ( $id <= 0 ) {
            return false;
        }

        $fields = array();
        $formats = array();

        if ( array_key_exists( 'title', $data ) ) {
            $fields['title'] = sanitize_text_field( (string) $data['title'] );
            $formats[] = '%s';
        }
        if ( array_key_exists( 'description', $data ) ) {
            $fields['description'] = wp_kses_post( (string) $data['description'] );
            $formats[] = '%s';
        }
        if ( array_key_exists( 'settings', $data ) ) {
            $fields['settings'] = $this->encode_data( $data['settings'] );
            $formats[] = '%s';
        }
        if ( array_key_exists( 'shuffle', $data ) ) {
            $fields['shuffle'] = $this->normalize_bool( $data['shuffle'] );
            $formats[] = '%d';
        }
        if ( array_key_exists( 'time_limit', $data ) ) {
            $fields['time_limit'] = intval( $data['time_limit'] );
            $formats[] = '%d';
        }
        if ( array_key_exists( 'attempts_allowed', $data ) ) {
            $fields['attempts_allowed'] = intval( $data['attempts_allowed'] );
            $formats[] = '%d';
        }
        if ( array_key_exists( 'show_answers', $data ) ) {
            $fields['show_answers'] = $this->normalize_bool( $data['show_answers'] );
            $formats[] = '%d';
        }

        $fields['updated_at'] = $this->now();
        $formats[] = '%s';

        if ( empty( $fields ) ) {
            return false;
        }

        $updated = $this->db->update(
            $this->table_quizzes,
            $fields,
            array( 'id' => $id ),
            $formats,
            array( '%d' )
        );

        return $updated !== false;
    }

    /**
     * Delete a quiz and all related data (questions, attempts, attempt_answers).
     */
    public function delete_quiz( $id ) {
        $id = (int) $id;
        if ( $id <= 0 ) {
            return false;
        }

        // Delete per-question answers for attempts of this quiz.
        $attempt_ids = $this->db->get_col(
            $this->db->prepare(
                "SELECT id FROM {$this->table_attempts} WHERE quiz_id = %d",
                $id
            )
        );
        if ( ! empty( $attempt_ids ) ) {
            $in = implode( ',', array_map( 'absint', $attempt_ids ) );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $this->db->query( "DELETE FROM {$this->table_attempt_answers} WHERE attempt_id IN ({$in})" );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $this->db->query( "DELETE FROM {$this->table_attempts} WHERE id IN ({$in})" );
        }

        // Delete questions
        $this->db->delete( $this->table_questions, array( 'quiz_id' => $id ), array( '%d' ) );

        // Finally delete quiz
        $this->db->delete( $this->table_quizzes, array( 'id' => $id ), array( '%d' ) );

        return true;
    }

    /**
     * Fetch a quiz (optionally with its questions).
     * Returns associative array or null.
     */
    public function get_quiz( $id, $with_questions = false ) {
        $id = (int) $id;
        if ( $id <= 0 ) {
            return null;
        }

        $row = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->table_quizzes} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        if ( ! $row ) {
            return null;
        }

        $row['settings'] = $this->decode_data( $row['settings'] );

        if ( $with_questions ) {
            $row['questions'] = $this->get_questions( $id );
        }

        return $row;
    }

    /**
     * Get questions for a quiz (array of associative arrays).
     */
    public function get_questions( $quiz_id ) {
        $quiz_id = (int) $quiz_id;
        if ( $quiz_id <= 0 ) {
            return array();
        }
        $rows = $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->table_questions} WHERE quiz_id = %d ORDER BY id ASC",
                $quiz_id
            ),
            ARRAY_A
        ) ?: array();

        foreach ( $rows as &$r ) {
            $r['options']        = $this->decode_data( $r['options'] );
            $r['correct_answer'] = $this->decode_data( $r['correct_answer'] );
            $r['type']           = $this->clean_question_type( $r['type'] );
            $r['points']         = (int) $r['points'];
        }
        unset( $r );

        return $rows;
    }

    /* =========================
     * Questions
     * ========================= */

    /**
     * Add a question to a quiz. Returns new question ID or 0.
     */
    public function add_question( $quiz_id, array $q ) {
        $quiz_id = (int) $quiz_id;
        if ( $quiz_id <= 0 ) {
            return 0;
        }

        $question_text  = isset( $q['question_text'] ) ? wp_kses_post( $q['question_text'] ) : '';
        $type           = isset( $q['type'] ) ? $this->clean_question_type( $q['type'] ) : 'single';
        $options        = isset( $q['options'] ) ? $this->encode_data( $q['options'] ) : $this->encode_data( array() );
        $correct_answer = isset( $q['correct_answer'] ) ? $this->encode_data( $q['correct_answer'] ) : $this->encode_data( null );
        $points         = isset( $q['points'] ) ? intval( $q['points'] ) : 1;
        $image          = isset( $q['image'] ) ? sanitize_text_field( $q['image'] ) : null;

        $ok = $this->db->insert(
            $this->table_questions,
            array(
                'quiz_id'        => $quiz_id,
                'question_text'  => $question_text,
                'type'           => $type,
                'options'        => $options,
                'correct_answer' => $correct_answer,
                'points'         => $points,
                'image'          => $image,
                'created_at'     => $this->now(),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
        );

        if ( ! $ok ) {
            return 0;
        }
        return (int) $this->db->insert_id;
    }

    /**
     * Update a question. Returns true/false.
     */
    public function update_question( $question_id, array $q ) {
        $question_id = (int) $question_id;
        if ( $question_id <= 0 ) {
            return false;
        }

        $fields  = array();
        $formats = array();

        if ( array_key_exists( 'question_text', $q ) ) {
            $fields['question_text'] = wp_kses_post( (string) $q['question_text'] );
            $formats[] = '%s';
        }
        if ( array_key_exists( 'type', $q ) ) {
            $fields['type'] = $this->clean_question_type( $q['type'] );
            $formats[] = '%s';
        }
        if ( array_key_exists( 'options', $q ) ) {
            $fields['options'] = $this->encode_data( $q['options'] );
            $formats[] = '%s';
        }
        if ( array_key_exists( 'correct_answer', $q ) ) {
            $fields['correct_answer'] = $this->encode_data( $q['correct_answer'] );
            $formats[] = '%s';
        }
        if ( array_key_exists( 'points', $q ) ) {
            $fields['points'] = intval( $q['points'] );
            $formats[] = '%d';
        }
        if ( array_key_exists( 'image', $q ) ) {
            $fields['image'] = sanitize_text_field( (string) $q['image'] );
            $formats[] = '%s';
        }

        if ( empty( $fields ) ) {
            return false;
        }

        $updated = $this->db->update(
            $this->table_questions,
            $fields,
            array( 'id' => $question_id ),
            $formats,
            array( '%d' )
        );

        return $updated !== false;
    }

    /**
     * Delete a single question. Also removes per-question answers for this question.
     */
    public function delete_question( $question_id ) {
        $question_id = (int) $question_id;
        if ( $question_id <= 0 ) {
            return false;
        }
        // Clean attempt answers tied to this question.
        $this->db->delete( $this->table_attempt_answers, array( 'question_id' => $question_id ), array( '%d' ) );
        // Delete question.
        $this->db->delete( $this->table_questions, array( 'id' => $question_id ), array( '%d' ) );
        return true;
    }

    /* =========================
     * Attempts / Results
     * ========================= */

    /**
     * Record an attempt. Calculates score unless provided.
     * $attempt_data:
     *  - student_name, student_class, student_section, student_school, student_roll
     *  - answers (array: question_id => value or array of values)
     *  - obtained_marks (optional), total_marks (optional)
     *
     * Returns attempt ID or 0.
     */
    public function record_attempt( $quiz_id, array $attempt_data ) {
        $quiz_id = (int) $quiz_id;
        if ( $quiz_id <= 0 ) {
            return 0;
        }

        $answers = isset( $attempt_data['answers'] ) ? (array) $attempt_data['answers'] : array();

        // Compute score if not provided.
        $obtained = isset( $attempt_data['obtained_marks'] ) ? (float) $attempt_data['obtained_marks'] : null;
        $total    = isset( $attempt_data['total_marks'] ) ? (float) $attempt_data['total_marks'] : null;

        if ( $obtained === null || $total === null ) {
            $score = $this->compute_score( $quiz_id, $answers );
            $obtained = $score['obtained'];
            $total    = $score['total'];
        }

        $ok = $this->db->insert(
            $this->table_attempts,
            array(
                'quiz_id'        => $quiz_id,
                'student_name'   => isset( $attempt_data['student_name'] ) ? sanitize_text_field( $attempt_data['student_name'] ) : null,
                'student_class'  => isset( $attempt_data['student_class'] ) ? sanitize_text_field( $attempt_data['student_class'] ) : null,
                'student_section'=> isset( $attempt_data['student_section'] ) ? sanitize_text_field( $attempt_data['student_section'] ) : null,
                'student_school' => isset( $attempt_data['student_school'] ) ? sanitize_text_field( $attempt_data['student_school'] ) : null,
                'student_roll'   => isset( $attempt_data['student_roll'] ) ? sanitize_text_field( $attempt_data['student_roll'] ) : null,
                'obtained_marks' => $obtained,
                'total_marks'    => $total,
                'answers'        => $this->encode_data( $answers ),
                'created_at'     => $this->now(),
            ),
            array( '%d','%s','%s','%s','%s','%s','%f','%f','%s','%s' )
        );

        if ( ! $ok ) {
            return 0;
        }

        $attempt_id = (int) $this->db->insert_id;

        // Optional: write per-question breakdown
        if ( isset( $score ) && ! empty( $score['breakdown'] ) ) {
            foreach ( $score['breakdown'] as $qid => $row ) {
                $this->db->insert(
                    $this->table_attempt_answers,
                    array(
                        'attempt_id'     => $attempt_id,
                        'question_id'    => (int) $qid,
                        'answer'         => $this->encode_data( $row['answer'] ),
                        'is_correct'     => $row['is_correct'] ? 1 : 0,
                        'points_awarded' => (float) $row['points_awarded'],
                        'created_at'     => $this->now(),
                    ),
                    array( '%d','%d','%s','%d','%f','%s' )
                );
            }
        }

        return $attempt_id;
    }

    /**
     * Compute score server-side from answers.
     * Returns ['obtained'=>float,'total'=>float,'breakdown'=>[qid=>...]]
     */
    public function compute_score( $quiz_id, array $answers ) {
        $questions = $this->get_questions( $quiz_id );

        $obtained = 0.0;
        $total    = 0.0;
        $breakdown = array();

        foreach ( $questions as $q ) {
            $qid      = (int) $q['id'];
            $type     = $q['type'];
            $points   = (int) $q['points'];
            $total   += $points;

            $correct  = $q['correct_answer'];
            $given    = isset( $answers[ $qid ] ) ? $answers[ $qid ] : null;

            $row = array(
                'answer'         => $given,
                'is_correct'     => false,
                'points_awarded' => 0,
            );

            // Normalize multi-values to arrays of strings for comparison.
            $norm = function( $v ) {
                if ( is_array( $v ) ) {
                    return array_values( array_map( 'strval', $v ) );
                }
                if ( $v === null ) {
                    return array();
                }
                return array( (string) $v );
            };

            if ( $type === 'single' ) {
                // Correct when exactly equals one of the correct values.
                $corr = $norm( $correct );
                $giv  = $norm( $given );
                $is   = ( count( $giv ) === 1 && in_array( $giv[0], $corr, true ) );
                $row['is_correct'] = $is;
                $row['points_awarded'] = $is ? $points : 0;
            } elseif ( $type === 'multiple' ) {
                // Correct when sets match exactly.
                $corr = $norm( $correct );
                sort( $corr );
                $giv  = $norm( $given );
                sort( $giv );
                $is   = ( $corr === $giv );
                $row['is_correct'] = $is;
                $row['points_awarded'] = $is ? $points : 0;
            } else { // text / textarea
                // If a correct answer list exists, do case-insensitive match against any.
                $giv  = trim( (string) ( is_array( $given ) ? implode( ' ', $given ) : $given ) );
                $corr = $norm( $correct );
                if ( ! empty( $corr ) ) {
                    $match = false;
                    foreach ( $corr as $c ) {
                        if ( mb_strtolower( trim( $c ) ) === mb_strtolower( $giv ) ) {
                            $match = true;
                            break;
                        }
                    }
                    $row['is_correct'] = $match;
                    $row['points_awarded'] = $match ? $points : 0;
                } else {
                    // No correct key defined -> award 0 (subjective grading could be added later).
                    $row['is_correct'] = false;
                    $row['points_awarded'] = 0;
                }
            }

            $obtained += (float) $row['points_awarded'];
            $breakdown[ $qid ] = $row;
        }

        return array(
            'obtained'  => (float) $obtained,
            'total'     => (float) $total,
            'breakdown' => $breakdown,
        );
    }

    /**
     * Get attempts for a quiz.
     * $args = ['limit'=>int, 'offset'=>int, 'order'=>'DESC'|'ASC']
     */
    public function get_results( $quiz_id, array $args = array() ) {
        $quiz_id = (int) $quiz_id;
        if ( $quiz_id <= 0 ) {
            return array();
        }

        $limit  = isset( $args['limit'] )  ? max( 1, (int) $args['limit'] )  : 20;
        $offset = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;
        $order  = isset( $args['order'] )  ? strtoupper( $args['order'] )     : 'DESC';
        $order  = ( $order === 'ASC' ) ? 'ASC' : 'DESC';

        $sql = $this->db->prepare(
            "SELECT * FROM {$this->table_attempts} WHERE quiz_id = %d ORDER BY created_at {$order} LIMIT %d OFFSET %d",
            $quiz_id,
            $limit,
            $offset
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $this->db->get_results( $sql, ARRAY_A ) ?: array();

        foreach ( $rows as &$r ) {
            $r['answers'] = $this->decode_data( $r['answers'] );
        }
        unset( $r );

        return $rows;
    }

    /**
     * Convenience wrapper used in some admin code.
     */
    public function get_attempts_by_quiz( $quiz_id, $limit = 20, $offset = 0 ) {
        return $this->get_results( $quiz_id, array( 'limit' => $limit, 'offset' => $offset ) );
    }

    /**
     * Get a single attempt with decoded answers.
     * Returns associative array or null.
     */
    public function get_attempt( $attempt_id ) {
        $attempt_id = (int) $attempt_id;
        if ( $attempt_id <= 0 ) {
            return null;
        }
        $row = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->table_attempts} WHERE id = %d",
                $attempt_id
            ),
            ARRAY_A
        );
        if ( ! $row ) {
            return null;
        }
        $row['answers'] = $this->decode_data( $row['answers'] );
        return $row;
    }
}
