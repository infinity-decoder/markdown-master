<?php
/**
 * Enhanced Quiz Model for Cortex 2.0
 * 
 * Comprehensive quiz management with:
 * - UUID-based public identifiers
 * - 11 question types with factory pattern
 * - Advanced scoring engine with partial credit
 * - Attempt validation and limits
 * - Randomization support
 * - Result tier calculation
 * 
 * @package MarkdownMaster
 * @since 2.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cortex_Quiz {

    /** @var wpdb */
    protected $db;

    /** @var string */
    protected $table_quizzes;
    protected $table_questions;
    protected $table_attempts;
    protected $table_attempt_answers;

    /**
     * Supported question types
     */
    const QUESTION_TYPES = array(
        'radio',       // Single choice (radio buttons)
        'checkbox',    // Multiple choice (checkboxes)
        'dropdown',     // Single choice (dropdown)
        'text',        // Long text answer
        'short_text',  // Short text input
        'number',      // Numerical input
        'date',        // Date picker
        'banner',      // Informational only (no answer)
        'fill_blank',  // Fill in the blank(s)
        'matching',    // Match two lists
        'sequence',    // Order items correctly
    );

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table_quizzes         = $wpdb->prefix . 'cortex_quizzes';
        $this->table_questions       = $wpdb->prefix . 'cortex_questions';
        $this->table_attempts        = $wpdb->prefix . 'cortex_attempts';
        $this->table_attempt_answers = $wpdb->prefix . 'cortex_attempt_answers';
    }

    /* =========================
     * Helper Methods
     * ========================= */

    /**
     * Get current MySQL datetime
     */
    protected function now() {
        return current_time( 'mysql' );
    }

    /**
     * Normalize boolean value
     */
    protected function normalize_bool( $v ) {
        return (int) ( ! empty( $v ) );
    }

    /**
     * Encode data to JSON
     */
    protected function encode_data( $data ) {
        if ( is_string( $data ) ) {
            return $data;
        }
        return wp_json_encode( $data, JSON_UNESCAPED_UNICODE );
    }

    /**
     * Decode JSON/serialized data
     */
    protected function decode_data( $data ) {
        if ( is_array( $data ) || is_object( $data ) ) {
            return $data;
        }
        if ( is_string( $data ) && ! empty( $data ) ) {
            $json = json_decode( $data, true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                return $json;
            }
            // Fallback for legacy serialized values
            if ( is_serialized( $data ) ) {
                $maybe = @maybe_unserialize( $data );
                if ( is_array( $maybe ) || is_object( $maybe ) ) {
                    return $maybe;
                }
            }
        }
        return $data;
    }

    /**
     * Validate and clean question type
     */
    protected function clean_question_type( $type ) {
        $type = strtolower( sanitize_text_field( (string) $type ) );
        return in_array( $type, self::QUESTION_TYPES, true ) ? $type : 'radio';
    }

    /**
     * Generate unique quiz UUID
     * Uses WordPress built-in UUID generator
     * 
     * @return string UUID
     */
    public function generate_quiz_uuid() {
        // Ensure uniqueness by checking database
        do {
            $uuid = wp_generate_uuid4();
            $exists = $this->db->get_var(
                $this->db->prepare(
                    "SELECT COUNT(*) FROM {$this->table_quizzes} WHERE quiz_uuid = %s",
                    $uuid
                )
            );
        } while ( $exists > 0 );

        return $uuid;
    }

    /* =========================
     * Quiz CRUD
     * ========================= */

    /**
     * Create a quiz
     * 
     * @param array $data Quiz data (sanitized externally via Cortex_Security)
     * @return int Quiz ID or 0 on failure
     */
    public function create_quiz( array $data ) {
        // Generate UUID
        $quiz_uuid = $this->generate_quiz_uuid();

        // Prepare data with defaults
        $insert_data = array(
            'quiz_uuid'           => $quiz_uuid,
            'title'               => isset( $data['title'] ) ? $data['title'] : '',
            'description'         => isset( $data['description'] ) ? $data['description'] : '',
            'settings'            => isset( $data['settings'] ) ? $this->encode_data( $data['settings'] ) : '{}',
            'lead_fields'         => isset( $data['lead_fields'] ) ? $this->encode_data( $data['lead_fields'] ) : '[]',
            'time_limit'          => isset( $data['time_limit'] ) ? absint( $data['time_limit'] ) : 0,
            'attempts_allowed'    => isset( $data['attempts_allowed'] ) ? absint( $data['attempts_allowed'] ) : 0,
            'show_answers'        => isset( $data['show_answers'] ) ? $this->normalize_bool( $data['show_answers'] ) : 0,
            'randomize_questions' => isset( $data['randomize_questions'] ) ? $this->normalize_bool( $data['randomize_questions'] ) : 0,
            'randomize_answers'   => isset( $data['randomize_answers'] ) ? $this->normalize_bool( $data['randomize_answers'] ) : 0,
            'questions_per_page'  => isset( $data['questions_per_page'] ) ? absint( $data['questions_per_page'] ) : 0,
            'show_welcome_screen' => isset( $data['show_welcome_screen'] ) ? $this->normalize_bool( $data['show_welcome_screen'] ) : 0,
            'welcome_content'     => isset( $data['welcome_content'] ) ? $data['welcome_content'] : '',
            'scheduled_start'     => isset( $data['scheduled_start'] ) ? $data['scheduled_start'] : null,
            'scheduled_end'       => isset( $data['scheduled_end'] ) ? $data['scheduled_end'] : null,
            'require_login'       => isset( $data['require_login'] ) ? $this->normalize_bool( $data['require_login'] ) : 0,
            'required_role'       => isset( $data['required_role'] ) ? $data['required_role'] : null,
            'max_total_attempts'  => isset( $data['max_total_attempts'] ) ? absint( $data['max_total_attempts'] ) : 0,
            'max_user_attempts'   => isset( $data['max_user_attempts'] ) ? absint( $data['max_user_attempts'] ) : 0,
            'pass_percentage'     => isset( $data['pass_percentage'] ) ? floatval( $data['pass_percentage'] ) : 0,
            'enable_lead_capture' => isset( $data['enable_lead_capture'] ) ? $this->normalize_bool( $data['enable_lead_capture'] ) : 0,
            'created_by'          => get_current_user_id(),
            'created_at'          => $this->now(),
            'updated_at'          => $this->now(),
        );

        $formats = array(
            '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d',
            '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%f', '%d',
            '%d', '%s', '%s'
        );

        $inserted = $this->db->insert( $this->table_quizzes, $insert_data, $formats );

        if ( ! $inserted ) {
            return 0;
        }

        $quiz_id = (int) $this->db->insert_id;

        // Clear cache
        Cortex_Cache::invalidate_quiz( $quiz_id, $quiz_uuid );

        return $quiz_id;
    }

    /**
     * Update a quiz
     * 
     * @param int $id Quiz ID
     * @param array $data Quiz data (sanitized externally)
     * @return bool True on success
     */
    public function update_quiz( $id, array $data ) {
        $id = absint( $id );
        if ( $id <= 0 ) {
            return false;
        }

        // Build dynamic update fields
        $fields = array();
        $formats = array();

        $allowed_fields = array(
            'title', 'description', 'settings', 'lead_fields', 'time_limit', 'attempts_allowed',
            'show_answers', 'randomize_questions', 'randomize_answers', 'questions_per_page',
            'show_welcome_screen', 'welcome_content', 'scheduled_start', 'scheduled_end',
            'require_login', 'required_role', 'max_total_attempts', 'max_user_attempts',
            'pass_percentage', 'enable_lead_capture'
        );

        foreach ( $allowed_fields as $field ) {
            if ( array_key_exists( $field, $data ) ) {
                if ( in_array( $field, array( 'settings', 'lead_fields' ), true ) ) {
                    $fields[ $field ] = $this->encode_data( $data[ $field ] );
                    $formats[] = '%s';
                } elseif ( in_array( $field, array( 'pass_percentage' ), true ) ) {
                    $fields[ $field ] = floatval( $data[ $field ] );
                    $formats[] = '%f';
                } elseif ( in_array( $field, array( 'title', 'description', 'welcome_content', 'scheduled_start', 'scheduled_end', 'required_role' ), true ) ) {
                    $fields[ $field ] = $data[ $field ];
                    $formats[] = '%s';
                } else {
                    $fields[ $field ] = absint( $data[ $field ] );
                    $formats[] = '%d';
                }
            }
        }

        if ( empty( $fields ) ) {
            return false;
        }

        $fields['updated_at'] = $this->now();
        $formats[] = '%s';

        $updated = $this->db->update(
            $this->table_quizzes,
            $fields,
            array( 'id' => $id ),
            $formats,
            array( '%d' )
        );

        // Get UUID for cache invalidation
        $quiz_uuid = $this->db->get_var(
            $this->db->prepare(
                "SELECT quiz_uuid FROM {$this->table_quizzes} WHERE id = %d",
                $id
            )
        );

        Cortex_Cache::invalidate_quiz( $id, $quiz_uuid );

        return $updated !== false;
    }

    /**
     * Delete a quiz and all related data
     * 
     * @param int $id Quiz ID
     * @return bool True on success
     */
    public function delete_quiz( $id ) {
        $id = absint( $id );
        if ( $id <= 0 ) {
            return false;
        }

        // Get UUID before deletion
        $quiz_uuid = $this->db->get_var(
            $this->db->prepare(
                "SELECT quiz_uuid FROM {$this->table_quizzes} WHERE id = %d",
                $id
            )
        );

        // Delete attempt answers for this quiz's attempts
        $attempt_ids = $this->db->get_col(
            $this->db->prepare(
                "SELECT id FROM {$this->table_attempts} WHERE quiz_id = %d",
                $id
            )
        );

        if ( ! empty( $attempt_ids ) ) {
            $ids_in = implode( ',', array_map( 'absint', $attempt_ids ) );
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $this->db->query( "DELETE FROM {$this->table_attempt_answers} WHERE attempt_id IN ({$ids_in})" );
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $this->db->query( "DELETE FROM {$this->table_attempts} WHERE id IN ({$ids_in})" );
        }

        // Delete lead captures (if exists)
        $table_leads = $this->db->prefix . 'cortex_lead_captures';
        $this->db->delete( $table_leads, array( 'quiz_id' => $id ), array( '%d' ) );

        // Delete questions
        $this->db->delete( $this->table_questions, array( 'quiz_id' => $id ), array( '%d' ) );

        // Delete quiz
        $this->db->delete( $this->table_quizzes, array( 'id' => $id ), array( '%d' ) );

        // Clear cache
        Cortex_Cache::invalidate_quiz( $id, $quiz_uuid );

        return true;
    }

    /**
     * Get quiz by ID or UUID
     * 
     * @param int|string $identifier Quiz ID or UUID
     * @param bool $with_questions Include questions
     * @return array|null Quiz data or null
     */
    public function get_quiz( $identifier, $with_questions = false ) {
        // Determine if identifier is UUID or ID
        if ( is_numeric( $identifier ) ) {
            $id = absint( $identifier );
            $cache_key = Cortex_Cache::get_quiz_key( $id );
            
            $row = Cortex_Cache::get( $cache_key, Cortex_Cache::GROUP_QUIZ );
            
            if ( false === $row ) {
                $row = $this->db->get_row(
                    $this->db->prepare(
                        "SELECT * FROM {$this->table_quizzes} WHERE id = %d",
                        $id
                    ),
                    ARRAY_A
                );
                
                if ( $row ) {
                    Cortex_Cache::set( $cache_key, $row, Cortex_Cache::get_ttl(), Cortex_Cache::GROUP_QUIZ );
                }
            }
        } else {
            // UUID
            $uuid = sanitize_text_field( $identifier );
            $cache_key = Cortex_Cache::get_quiz_key( $uuid );
            
            $row = Cortex_Cache::get( $cache_key, Cortex_Cache::GROUP_QUIZ );
            
            if ( false === $row ) {
                $row = $this->db->get_row(
                    $this->db->prepare(
                        "SELECT * FROM {$this->table_quizzes} WHERE quiz_uuid = %s",
                        $uuid
                    ),
                    ARRAY_A
                );
                
                if ( $row ) {
                    Cortex_Cache::set( $cache_key, $row, Cortex_Cache::get_ttl(), Cortex_Cache::GROUP_QUIZ );
                }
            }
        }

        if ( ! $row ) {
            return null;
        }

        // Decode JSON fields
        $row['settings'] = $this->decode_data( $row['settings'] );
        $row['lead_fields'] = $this->decode_data( $row['lead_fields'] );

        if ( $with_questions ) {
            $row['questions'] = $this->get_questions( $row['id'] );
        }

        return $row;
    }

    /* =========================
     * Questions
     * ========================= */

    /**
     * Get questions for a quiz
     * 
     * @param int $quiz_id Quiz ID
     * @param bool $randomize Whether to randomize order
     * @return array Questions array
     */
    public function get_questions( $quiz_id, $randomize = false ) {
        $quiz_id = absint( $quiz_id );
        if ( $quiz_id <= 0 ) {
            return array();
        }

        $cache_key = Cortex_Cache::get_quiz_questions_key( $quiz_id );
        $rows = Cortex_Cache::get( $cache_key, Cortex_Cache::GROUP_QUIZ );

        if ( false === $rows ) {
            $rows = $this->db->get_results(
                $this->db->prepare(
                    "SELECT * FROM {$this->table_questions} WHERE quiz_id = %d ORDER BY question_order ASC, id ASC",
                    $quiz_id
                ),
                ARRAY_A
            );

            if ( ! $rows ) {
                $rows = array();
            }

            // Decode JSON fields
            foreach ( $rows as &$r ) {
                $r['options'] = $this->decode_data( $r['options'] );
                $r['correct_answer'] = $this->decode_data( $r['correct_answer'] );
                $r['metadata'] = $this->decode_data( $r['metadata'] );
                $r['type'] = $this->clean_question_type( $r['type'] );
            }
            unset( $r );

            Cortex_Cache::set( $cache_key, $rows, Cortex_Cache::get_ttl(), Cortex_Cache::GROUP_QUIZ );
        }

        // Randomize if requested
        if ( $randomize && ! empty( $rows ) ) {
            shuffle( $rows );
        }

        return $rows;
    }

    /**
     * Get a single question by ID
     * 
     * @param int $question_id
     * @return array|null
     */
    public function get_question( $question_id ) {
        $question_id = absint( $question_id );
        if ( $question_id <= 0 ) {
            return null;
        }

        $row = $this->db->get_row(
            $this->db->prepare( "SELECT * FROM {$this->table_questions} WHERE id = %d", $question_id ),
            ARRAY_A
        );

        if ( ! $row ) {
            return null;
        }

        // Decode JSON fields
        $row['options'] = $this->decode_data( $row['options'] );
        $row['correct_answer'] = $this->decode_data( $row['correct_answer'] );
        $row['metadata'] = $this->decode_data( $row['metadata'] );
        $row['type'] = $this->clean_question_type( $row['type'] );

        return $row;
    }

    /**
     * Add question to quiz
     * 
     * @param int $quiz_id Quiz ID
     * @param array $data Question data (sanitized externally)
     * @return int Question ID or 0
     */
    public function add_question( $quiz_id, array $data ) {
        $quiz_id = absint( $quiz_id );
        if ( $quiz_id <= 0 ) {
            return 0;
        }

        $insert_data = array(
            'quiz_id'          => $quiz_id,
            'question_bank_id' => isset( $data['question_bank_id'] ) ? absint( $data['question_bank_id'] ) : null,
            'question_text'    => isset( $data['question_text'] ) ? $data['question_text'] : '',
            'type'             => isset( $data['type'] ) ? $this->clean_question_type( $data['type'] ) : 'radio',
            'options'          => isset( $data['options'] ) ? $this->encode_data( $data['options'] ) : '[]',
            'correct_answer'   => isset( $data['correct_answer'] ) ? $this->encode_data( $data['correct_answer'] ) : 'null',
            'points'           => isset( $data['points'] ) ? floatval( $data['points'] ) : 1,
            'hint'             => isset( $data['hint'] ) ? $data['hint'] : '',
            'allow_comment'    => isset( $data['allow_comment'] ) ? $this->normalize_bool( $data['allow_comment'] ) : 0,
            'question_order'   => isset( $data['question_order'] ) ? absint( $data['question_order'] ) : 0,
            'metadata'         => isset( $data['metadata'] ) ? $this->encode_data( $data['metadata'] ) : '{}',
            'image'            => isset( $data['image'] ) ? esc_url_raw( $data['image'] ) : null,
            'created_at'       => $this->now(),
        );

        $formats = array( '%d', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%d', '%d', '%s', '%s', '%s' );

        $ok = $this->db->insert( $this->table_questions, $insert_data, $formats );

        if ( ! $ok ) {
            return 0;
        }

        $question_id = (int) $this->db->insert_id;

        // Clear cache
        Cortex_Cache::invalidate_question( $question_id, $quiz_id );

        return $question_id;
    }

    /**
     * Update question
     * 
     * @param int $question_id Question ID
     * @param array $data Question data
     * @return bool True on success
     */
    public function update_question( $question_id, array $data ) {
        $question_id = absint( $question_id );
        if ( $question_id <= 0 ) {
            return false;
        }

        // Get quiz ID for cache invalidation
        $quiz_id = $this->db->get_var(
            $this->db->prepare(
                "SELECT quiz_id FROM {$this->table_questions} WHERE id = %d",
                $question_id
            )
        );

        $fields = array();
        $formats = array();

        $allowed_fields = array(
            'question_text', 'type', 'options', 'correct_answer', 'points',
            'hint', 'allow_comment', 'question_order', 'metadata', 'image'
        );

        foreach ( $allowed_fields as $field ) {
            if ( array_key_exists( $field, $data ) ) {
                if ( in_array( $field, array( 'options', 'correct_answer', 'metadata' ), true ) ) {
                    $fields[ $field ] = $this->encode_data( $data[ $field ] );
                    $formats[] = '%s';
                } elseif ( 'type' === $field ) {
                    $fields[ $field ] = $this->clean_question_type( $data[ $field ] );
                    $formats[] = '%s';
                } elseif ( 'points' === $field ) {
                    $fields[ $field ] = floatval( $data[ $field ] );
                    $formats[] = '%f';
                } elseif ( in_array( $field, array( 'allow_comment', 'question_order' ), true ) ) {
                    $fields[ $field ] = absint( $data[ $field ] );
                    $formats[] = '%d';
                } else {
                    $fields[ $field ] = $data[ $field ];
                    $formats[] = '%s';
                }
            }
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

        Cortex_Cache::invalidate_question( $question_id, $quiz_id );

        return $updated !== false;
    }

    /**
     * Delete question
     * 
     * @param int $question_id Question ID
     * @return bool True on success
     */
    public function delete_question( $question_id ) {
        $question_id = absint( $question_id );
        if ( $question_id <= 0 ) {
            return false;
        }

        // Get quiz ID for cache
        $quiz_id = $this->db->get_var(
            $this->db->prepare(
                "SELECT quiz_id FROM {$this->table_questions} WHERE id = %d",
                $question_id
            )
        );

        // Delete attempt answers
        $this->db->delete( $this->table_attempt_answers, array( 'question_id' => $question_id ), array( '%d' ) );

        // Delete question
        $this->db->delete( $this->table_questions, array( 'id' => $question_id ), array( '%d' ) );

        Cortex_Cache::invalidate_question( $question_id, $quiz_id );

        return true;
    }

    /* =========================
     * Attempts & Scoring
     * ========================= */

    /**
     * Check if quiz is currently available based on schedule
     * 
     * @param array $quiz Quiz data
     * @return array ['available' => bool, 'message' => string]
     */
    public function check_availability( $quiz ) {
        $now = current_time( 'mysql' );

        if ( ! empty( $quiz['scheduled_start'] ) && $now < $quiz['scheduled_start'] ) {
            return array(
                'available' => false,
                'message'   => sprintf(
                    __( 'This quiz will be available starting %s', 'cortex' ),
                    mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $quiz['scheduled_start'] )
                ),
            );
        }

        if ( ! empty( $quiz['scheduled_end'] ) && $now > $quiz['scheduled_end'] ) {
            return array(
                'available' => false,
                'message'   => __( 'This quiz has ended and is no longer available.', 'cortex' ),
            );
        }

        return array( 'available' => true, 'message' => '' );
    }

    /**
     * Check attempt limits
     * 
     * @param int $quiz_id Quiz ID
     * @param array $quiz Quiz data
     * @param int $user_id User ID (0 for guest)
     * @return array ['allowed' => bool, 'message' => string]
     */
    public function check_attempt_limits( $quiz_id, $quiz, $user_id = 0 ) {
        // Check total attempts
        if ( $quiz['max_total_attempts'] > 0 ) {
            $total_attempts = $this->db->get_var(
                $this->db->prepare(
                    "SELECT COUNT(*) FROM {$this->table_attempts} WHERE quiz_id = %d",
                    $quiz_id
                )
            );

            if ( $total_attempts >= $quiz['max_total_attempts'] ) {
                return array(
                    'allowed' => false,
                    'message' => __( 'This quiz has reached its maximum number of attempts.', 'cortex' ),
                );
            }
        }

        // Check user attempts
        if ( $quiz['max_user_attempts'] > 0 && $user_id > 0 ) {
            $user_attempts = $this->db->get_var(
                $this->db->prepare(
                    "SELECT COUNT(*) FROM {$this->table_attempts} WHERE quiz_id = %d AND user_id = %d",
                    $quiz_id,
                    $user_id
                )
            );

            if ( $user_attempts >= $quiz['max_user_attempts'] ) {
                return array(
                    'allowed' => false,
                    'message' => sprintf(
                        __( 'You have reached the maximum number of attempts (%d) for this quiz.', 'cortex' ),
                        $quiz['max_user_attempts']
                    ),
                );
            }
        }

        return array( 'allowed' => true, 'message' => '' );
    }

    /**
     * Record quiz attempt
     * 
     * @param int $quiz_id Quiz ID
     * @param array $attempt_data Attempt data (sanitized externally)
     * @return int Attempt ID or 0
     */
    public function record_attempt( $quiz_id, array $attempt_data ) {
        $quiz_id = absint( $quiz_id );
        if ( $quiz_id <= 0 ) {
            return 0;
        }

        $answers = isset( $attempt_data['answers'] ) ? (array) $attempt_data['answers'] : array();

        // Compute score
        $score = $this->compute_score( $quiz_id, $answers );

        // Calculate result tier
        $quiz = $this->get_quiz( $quiz_id );
        $result_tier = $this->calculate_result_tier( $score['obtained'], $score['total'], $quiz );

        $insert_data = array(
            'quiz_id'         => $quiz_id,
            'user_id'         => isset( $attempt_data['user_id'] ) ? absint( $attempt_data['user_id'] ) : get_current_user_id(),
            'student_name'    => isset( $attempt_data['student_name'] ) ? $attempt_data['student_name'] : null,
            'student_class'   => isset( $attempt_data['student_class'] ) ? $attempt_data['student_class'] : null,
            'student_section' => isset( $attempt_data['student_section'] ) ? $attempt_data['student_section'] : null,
            'student_school'  => isset( $attempt_data['student_school'] ) ? $attempt_data['student_school'] : null,
            'student_roll'    => isset( $attempt_data['student_roll'] ) ? $attempt_data['student_roll'] : null,
            'obtained_marks'  => $score['obtained'],
            'total_marks'     => $score['total'],
            'result_tier'     => $result_tier,
            'time_taken'      => isset( $attempt_data['time_taken'] ) ? absint( $attempt_data['time_taken'] ) : 0,
            'ip_address'      => isset( $attempt_data['ip_address'] ) ? $attempt_data['ip_address'] : Cortex_Security::get_user_ip(),
            'user_agent'      => isset( $attempt_data['user_agent'] ) ? $attempt_data['user_agent'] : Cortex_Security::get_user_agent(),
            'answers'         => $this->encode_data( $answers ),
            'created_at'      => $this->now(),
        );

        $formats = array(
            '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s',
            '%d', '%s', '%s', '%s', '%s'
        );

        $ok = $this->db->insert( $this->table_attempts, $insert_data, $formats );

        if ( ! $ok ) {
            return 0;
        }

        $attempt_id = (int) $this->db->insert_id;

        // Record per-question answers
        if ( ! empty( $score['breakdown'] ) ) {
            foreach ( $score['breakdown'] as $qid => $row ) {
                $this->db->insert(
                    $this->table_attempt_answers,
                    array(
                        'attempt_id'     => $attempt_id,
                        'question_id'    => absint( $qid ),
                        'answer'         => $this->encode_data( $row['answer'] ),
                        'is_correct'     => $row['is_correct'] ? 1 : 0,
                        'points_awarded' => floatval( $row['points_awarded'] ),
                        'created_at'     => $this->now(),
                    ),
                    array( '%d', '%d', '%s', '%d', '%f', '%s' )
                );
            }
        }

        return $attempt_id;
    }

    /**
     * Compute score with support for all question types
     * 
     * @param int $quiz_id Quiz ID
     * @param array $answers User answers [question_id => answer]
     * @return array ['obtained' => float, 'total' => float, 'breakdown' => array]
     */
    public function compute_score( $quiz_id, array $answers ) {
        $questions = $this->get_questions( $quiz_id );

        $obtained = 0.0;
        $total = 0.0;
        $breakdown = array();

        foreach ( $questions as $q ) {
            $qid = (int) $q['id'];
            $type = $q['type'];
            $points = floatval( $q['points'] );
            $total += $points;

            $correct = $q['correct_answer'];
            $given = isset( $answers[ $qid ] ) ? $answers[ $qid ] : null;

            $row = array(
                'answer'         => $given,
                'is_correct'     => false,
                'points_awarded' => 0,
            );

            // Banner type has no scoring
            if ( 'banner' === $type ) {
                $row['is_correct'] = true;
                $row['points_awarded'] = $points;
                $obtained += $points;
                $breakdown[ $qid ] = $row;
                continue;
            }

            // Score based on question type
            switch ( $type ) {
                case 'radio':
                case 'dropdown':
                    // Single choice - exact match
                    $is_correct = ( $given === $correct || ( is_array( $correct ) && in_array( $given, $correct, true ) ) );
                    $row['is_correct'] = $is_correct;
                    $row['points_awarded'] = $is_correct ? $points : 0;
                    break;

                case 'checkbox':
                    // Multiple choice - all correct answers must be selected
                    $given_arr = is_array( $given ) ? $given : array( $given );
                    $correct_arr = is_array( $correct ) ? $correct : array( $correct );
                    
                    sort( $given_arr );
                    sort( $correct_arr );
                    
                    $is_correct = ( $given_arr === $correct_arr );
                    
                    // Partial credit: award points proportional to correct selections
                    if ( ! $is_correct && ! empty( $correct_arr ) ) {
                        $correct_count = count( array_intersect( $given_arr, $correct_arr ) );
                        $total_correct = count( $correct_arr );
                        $penalty = count( array_diff( $given_arr, $correct_arr ) ); // Wrong selections
                        
                        $partial = max( 0, ( $correct_count - $penalty ) / $total_correct );
                        $row['points_awarded'] = $points * $partial;
                    } else {
                        $row['is_correct'] = $is_correct;
                        $row['points_awarded'] = $is_correct ? $points : 0;
                    }
                    break;

                case 'text':
                case 'short_text':
                    // Text answers - case-insensitive comparison
                    $given_text = trim( (string) $given );
                    $is_correct = false;
                    
                    if ( is_array( $correct ) ) {
                        foreach ( $correct as $c ) {
                            if ( mb_strtolower( trim( $c ) ) === mb_strtolower( $given_text ) ) {
                                $is_correct = true;
                                break;
                            }
                        }
                    } else {
                        $is_correct = ( mb_strtolower( trim( $correct ) ) === mb_strtolower( $given_text ) );
                    }
                    
                    $row['is_correct'] = $is_correct;
                    $row['points_awarded'] = $is_correct ? $points : 0;
                    break;

                case 'number':
                    // Numerical answer - exact or range
                    $given_num = floatval( $given );
                    $is_correct = ( abs( $given_num - floatval( $correct ) ) < 0.01 ); // Allow small floating point error
                    $row['is_correct'] = $is_correct;
                    $row['points_awarded'] = $is_correct ? $points : 0;
                    break;

                case 'date':
                    // Date comparison
                    $is_correct = ( trim( $given ) === trim( $correct ) );
                    $row['is_correct'] = $is_correct;
                    $row['points_awarded'] = $is_correct ? $points : 0;
                    break;

                case 'fill_blank':
                    // Fill in the blank
                    $metadata = $q['metadata'];
                    // Use correct_answer array if possible, fallback to metadata blanks
                    $correct_arr = is_array($q['correct_answer']) ? $q['correct_answer'] : (isset($metadata['blanks']) ? $metadata['blanks'] : array());
                    $given_arr = is_array( $given ) ? $given : array( $given );
                    
                    $correct_count = 0;
                    $total_blanks = count( $correct_arr );
                    
                    foreach ( $correct_arr as $idx => $blank_correct ) {
                        $given_blank = isset( $given_arr[ $idx ] ) ? trim( $given_arr[ $idx ] ) : '';
                        if ( mb_strtolower( $given_blank ) === mb_strtolower( trim( $blank_correct ) ) ) {
                            $correct_count++;
                        }
                    }
                    
                    if ( $total_blanks > 0 ) {
                        $row['points_awarded'] = $points * ( $correct_count / $total_blanks );
                        $row['is_correct'] = ( $correct_count === $total_blanks );
                    }
                    break;

                case 'matching':
                    // Matching pairs - metadata contains correct pairs
                    $metadata = $q['metadata'];
                    $correct_pairs = isset( $metadata['pairs'] ) ? $metadata['pairs'] : array();
                    $given_arr = is_array( $given ) ? $given : array();
                    
                    $correct_count = 0;
                    $total_pairs = count( $correct_pairs );
                    
                    foreach ( $correct_pairs as $left => $right ) {
                        if ( isset( $given_arr[ $left ] ) && $given_arr[ $left ] === $right ) {
                            $correct_count++;
                        }
                    }
                    
                    if ( $total_pairs > 0 ) {
                        $row['points_awarded'] = $points * ( $correct_count / $total_pairs );
                        $row['is_correct'] = ( $correct_count === $total_pairs );
                    }
                    break;
                
                case 'sequence':
                    // Ordered list - items must be in correct order
                    $metadata = $q['metadata'];
                    $correct_order = isset( $metadata['order'] ) ? $metadata['order'] : array();
                    $given_arr = is_array( $given ) ? $given : array();
                    
                    $correct_count = 0;
                    $total_items = count( $correct_order );
                    
                    foreach ( $correct_order as $idx => $val ) {
                        if ( isset( $given_arr[ $idx ] ) && $given_arr[ $idx ] === $val ) {
                            $correct_count++;
                        }
                    }
                    
                    if ( $total_items > 0 ) {
                        $row['points_awarded'] = $points * ( $correct_count / $total_items );
                        $row['is_correct'] = ( $correct_count === $total_items );
                    }
                    break;

                default:
                    // Unknown type - no points
                    $row['points_awarded'] = 0;
                    break;
            }

            $obtained += floatval( $row['points_awarded'] );
            $breakdown[ $qid ] = $row;
        }

        return array(
            'obtained'  => round( $obtained, 2 ),
            'total'     => round( $total, 2 ),
            'breakdown' => $breakdown,
        );
    }

    /**
     * Calculate result tier based on score and pass percentage
     * 
     * @param float $obtained Obtained marks
     * @param float $total Total marks
     * @param array $quiz Quiz data
     * @return string Tier: 'high', 'medium', or 'low'
     */
    public function calculate_result_tier( $obtained, $total, $quiz ) {
        if ( $total <= 0 ) {
            return 'medium';
        }

        $percentage = ( $obtained / $total ) * 100;
        $pass_percentage = isset( $quiz['pass_percentage'] ) ? floatval( $quiz['pass_percentage'] ) : 60;

        // High: >= 80% or >= (pass% + 20%)
        $high_threshold = max( 80, $pass_percentage + 20 );
        
        if ( $percentage >= $high_threshold ) {
            return 'high';
        }

        // Low: < pass%
        if ( $percentage < $pass_percentage ) {
            return 'low';
        }

        // Medium: between pass% and high threshold
        return 'medium';
    }

    /**
     * Get quiz results/attempts
     * 
     * @param int $quiz_id Quiz ID
     * @param array $args Query arguments
     * @return array Attempts
     */
    public function get_results( $quiz_id, array $args = array() ) {
        $quiz_id = absint( $quiz_id );
        if ( $quiz_id <= 0 ) {
            return array();
        }

        $limit = isset( $args['limit'] ) ? absint( $args['limit'] ) : 20;
        $offset = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;
        $order = isset( $args['order'] ) && 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

        $sql = $this->db->prepare(
            "SELECT * FROM {$this->table_attempts} WHERE quiz_id = %d ORDER BY created_at {$order} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $quiz_id,
            $limit,
            $offset
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $this->db->get_results( $sql, ARRAY_A );

        if ( ! $rows ) {
            return array();
        }

        foreach ( $rows as &$r ) {
            $r['answers'] = $this->decode_data( $r['answers'] );
        }
        unset( $r );

        return $rows;
    }

    /**
     * Get single attempt
     * 
     * @param int $attempt_id Attempt ID
     * @return array|null Attempt data
     */
    public function get_attempt( $attempt_id ) {
        $attempt_id = absint( $attempt_id );
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

    /**
     * Randomize question answers
     * 
     * @param array $question Question data
     * @return array Question with randomized options
     */
    public function randomize_answers( $question ) {
        if ( ! isset( $question['options'] ) || ! is_array( $question['options'] ) ) {
            return $question;
        }

        // Only randomize for radio, checkbox, dropdown
        if ( ! in_array( $question['type'], array( 'radio', 'checkbox', 'dropdown' ), true ) ) {
            return $question;
        }

        $options = $question['options'];
        shuffle( $options );
        $question['options'] = $options;

        return $question;
    }
}
