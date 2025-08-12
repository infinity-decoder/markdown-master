<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MM_Quiz
 * Handles CRUD for quizzes, questions and answers.
 */
class MM_Quiz {

    protected $wpdb;
    protected $table_quizzes;
    protected $table_questions;
    protected $table_answers;
    protected $table_attempts;
    protected $table_results;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_quizzes = $wpdb->prefix . 'mm_quizzes';
        $this->table_questions = $wpdb->prefix . 'mm_quiz_questions';
        $this->table_answers = $wpdb->prefix . 'mm_quiz_answers';
        $this->table_attempts = $wpdb->prefix . 'mm_quiz_attempts';
        $this->table_results = $wpdb->prefix . 'mm_quiz_results';
    }

    /* ---------------------------
     * Quiz CRUD
     * ---------------------------*/

    public function create_quiz( $data ) {
        $defaults = [
            'title' => '',
            'description' => '',
            'type' => 'mcq',
            'settings' => [],
            'created_by' => get_current_user_id(),
        ];
        $data = wp_parse_args( $data, $defaults );

        $inserted = $this->wpdb->insert(
            $this->table_quizzes,
            [
                'title' => sanitize_text_field( $data['title'] ),
                'description' => wp_kses_post( $data['description'] ),
                'type' => sanitize_text_field( $data['type'] ),
                'settings' => maybe_serialize( $data['settings'] ),
                'created_by' => intval( $data['created_by'] ),
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s', '%d', '%s' ]
        );

        if ( $inserted ) {
            return $this->wpdb->insert_id;
        }

        return false;
    }

    public function update_quiz( $quiz_id, $data ) {
        $data_db = [];
        if ( isset( $data['title'] ) ) {
            $data_db['title'] = sanitize_text_field( $data['title'] );
        }
        if ( isset( $data['description'] ) ) {
            $data_db['description'] = wp_kses_post( $data['description'] );
        }
        if ( isset( $data['type'] ) ) {
            $data_db['type'] = sanitize_text_field( $data['type'] );
        }
        if ( isset( $data['settings'] ) ) {
            $data_db['settings'] = maybe_serialize( $data['settings'] );
        }

        if ( empty( $data_db ) ) {
            return false;
        }

        $where = [ 'id' => intval( $quiz_id ) ];
        $format = array_fill( 0, count( $data_db ), '%s' );
        return (bool) $this->wpdb->update( $this->table_quizzes, $data_db, $where, $format, [ '%d' ] );
    }

    public function delete_quiz( $quiz_id ) {
        $quiz_id = intval( $quiz_id );
        if ( ! $quiz_id ) {
            return false;
        }

        // Delete children: questions, answers, attempts, results
        $this->wpdb->delete( $this->table_quizzes, [ 'id' => $quiz_id ], [ '%d' ] );
        $questions = $this->get_questions_by_quiz( $quiz_id );
        foreach ( $questions as $q ) {
            $this->wpdb->delete( $this->table_answers, [ 'question_id' => $q->id ], [ '%d' ] );
        }
        $this->wpdb->delete( $this->table_questions, [ 'quiz_id' => $quiz_id ], [ '%d' ] );

        $attempts = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT id FROM {$this->table_attempts} WHERE quiz_id = %d", $quiz_id ) );
        foreach ( $attempts as $a ) {
            $this->wpdb->delete( $this->table_results, [ 'attempt_id' => $a->id ], [ '%d' ] );
        }
        $this->wpdb->delete( $this->table_attempts, [ 'quiz_id' => $quiz_id ], [ '%d' ] );

        return true;
    }

    public function get_quiz( $quiz_id ) {
        $quiz_id = intval( $quiz_id );
        if ( ! $quiz_id ) {
            return null;
        }
        $quiz = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$this->table_quizzes} WHERE id = %d", $quiz_id ) );
        if ( $quiz ) {
            $quiz->settings = maybe_unserialize( $quiz->settings );
        }
        return $quiz;
    }

    public function get_all_quizzes( $args = [] ) {
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
            $sql = $this->wpdb->prepare( "SELECT * FROM {$this->table_quizzes} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d", array_merge( $params, [ $args['per_page'], $offset ] ) );
        } else {
            $sql = $this->wpdb->prepare( "SELECT * FROM {$this->table_quizzes} ORDER BY created_at DESC LIMIT %d OFFSET %d", $args['per_page'], $offset );
        }

        $rows = $this->wpdb->get_results( $sql );
        foreach ( $rows as $r ) {
            $r->settings = maybe_unserialize( $r->settings );
        }
        return $rows;
    }

    /* ---------------------------
     * Question & Answer CRUD
     * ---------------------------*/

    public function add_question( $quiz_id, $data ) {
        $defaults = [
            'question' => '',
            'image' => '',
            'type' => 'mcq',
            'options' => [],
            'correct_answer' => null,
        ];
        $data = wp_parse_args( $data, $defaults );

        $inserted = $this->wpdb->insert(
            $this->table_questions,
            [
                'quiz_id' => intval( $quiz_id ),
                'question' => wp_kses_post( $data['question'] ),
                'image' => sanitize_text_field( $data['image'] ),
                'type' => sanitize_text_field( $data['type'] ),
                'correct_answer' => maybe_serialize( $data['correct_answer'] ),
                'options' => maybe_serialize( $data['options'] ),
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        if ( $inserted ) {
            return $this->wpdb->insert_id;
        }

        return false;
    }

    public function update_question( $question_id, $data ) {
        $data_db = [];
        if ( isset( $data['question'] ) ) {
            $data_db['question'] = wp_kses_post( $data['question'] );
        }
        if ( isset( $data['image'] ) ) {
            $data_db['image'] = sanitize_text_field( $data['image'] );
        }
        if ( isset( $data['type'] ) ) {
            $data_db['type'] = sanitize_text_field( $data['type'] );
        }
        if ( isset( $data['options'] ) ) {
            $data_db['options'] = maybe_serialize( $data['options'] );
        }
        if ( array_key_exists( 'correct_answer', $data ) ) {
            $data_db['correct_answer'] = maybe_serialize( $data['correct_answer'] );
        }

        if ( empty( $data_db ) ) {
            return false;
        }

        return (bool) $this->wpdb->update( $this->table_questions, $data_db, [ 'id' => intval( $question_id ) ], null, [ '%d' ] );
    }

    public function delete_question( $question_id ) {
        $question_id = intval( $question_id );
        if ( ! $question_id ) {
            return false;
        }
        $this->wpdb->delete( $this->table_answers, [ 'question_id' => $question_id ], [ '%d' ] );
        return (bool) $this->wpdb->delete( $this->table_questions, [ 'id' => $question_id ], [ '%d' ] );
    }

    public function get_question( $question_id ) {
        $question = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$this->table_questions} WHERE id = %d", intval( $question_id ) ) );
        if ( $question ) {
            $question->options = maybe_unserialize( $question->options );
            $question->correct_answer = maybe_unserialize( $question->correct_answer );
        }
        return $question;
    }

    public function get_questions_by_quiz( $quiz_id ) {
        return $this->wpdb->get_results( $this->wpdb->prepare( "SELECT * FROM {$this->table_questions} WHERE quiz_id = %d ORDER BY id ASC", intval( $quiz_id ) ) );
    }

    /* ---------------------------
     * Answers (for MCQs optional)
     * ---------------------------*/

    public function add_answer_option( $question_id, $answer_text, $answer_image = '', $is_correct = 0 ) {
        $inserted = $this->wpdb->insert(
            $this->table_answers,
            [
                'question_id' => intval( $question_id ),
                'answer_text' => wp_kses_post( $answer_text ),
                'answer_image' => sanitize_text_field( $answer_image ),
                'is_correct' => intval( $is_correct ),
            ],
            [ '%d', '%s', '%s', '%d' ]
        );
        if ( $inserted ) {
            return $this->wpdb->insert_id;
        }
        return false;
    }

    public function get_answers_by_question( $question_id ) {
        return $this->wpdb->get_results( $this->wpdb->prepare( "SELECT * FROM {$this->table_answers} WHERE question_id = %d ORDER BY id ASC", intval( $question_id ) ) );
    }

    public function update_answer( $answer_id, $data ) {
        $data_db = [];
        if ( isset( $data['answer_text'] ) ) {
            $data_db['answer_text'] = wp_kses_post( $data['answer_text'] );
        }
        if ( isset( $data['answer_image'] ) ) {
            $data_db['answer_image'] = sanitize_text_field( $data['answer_image'] );
        }
        if ( isset( $data['is_correct'] ) ) {
            $data_db['is_correct'] = intval( $data['is_correct'] );
        }
        if ( empty( $data_db ) ) {
            return false;
        }
        return (bool) $this->wpdb->update( $this->table_answers, $data_db, [ 'id' => intval( $answer_id ) ] );
    }

    public function delete_answer( $answer_id ) {
        return (bool) $this->wpdb->delete( $this->table_answers, [ 'id' => intval( $answer_id ) ], [ '%d' ] );
    }

    /* ---------------------------
     * Attempts & Results (store attempt + per-question result)
     * ---------------------------*/

    public function create_attempt( $quiz_id, $user_data = [] ) {
        $defaults = [
            'user_id' => null,
            'user_name' => '',
            'user_email' => '',
            'user_class' => '',
            'user_section' => '',
            'started_at' => current_time( 'mysql' ),
        ];
        $data = wp_parse_args( $user_data, $defaults );

        $inserted = $this->wpdb->insert(
            $this->table_attempts,
            [
                'quiz_id' => intval( $quiz_id ),
                'user_id' => $data['user_id'] ? intval( $data['user_id'] ) : null,
                'user_name' => sanitize_text_field( $data['user_name'] ),
                'user_email' => sanitize_email( $data['user_email'] ),
                'user_class' => sanitize_text_field( $data['user_class'] ),
                'user_section' => sanitize_text_field( $data['user_section'] ),
                'score' => 0,
                'started_at' => $data['started_at'],
            ],
            [ '%d', '%d', '%s', '%s', '%s', '%s', '%f', '%s' ]
        );

        if ( $inserted ) {
            return $this->wpdb->insert_id;
        }

        return false;
    }

    public function finalize_attempt( $attempt_id, $score ) {
        return (bool) $this->wpdb->update( $this->table_attempts, [ 'score' => floatval( $score ), 'completed_at' => current_time( 'mysql' ) ], [ 'id' => intval( $attempt_id ) ], [ '%f', '%s' ], [ '%d' ] );
    }

    public function add_result_row( $attempt_id, $question_id, $given_answer, $is_correct ) {
        return (bool) $this->wpdb->insert(
            $this->table_results,
            [
                'attempt_id' => intval( $attempt_id ),
                'question_id' => intval( $question_id ),
                'given_answer' => maybe_serialize( $given_answer ),
                'is_correct' => intval( $is_correct ),
            ],
            [ '%d', '%d', '%s', '%d' ]
        );
    }

    public function get_attempt( $attempt_id ) {
        return $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$this->table_attempts} WHERE id = %d", intval( $attempt_id ) ) );
    }

    public function get_results_by_attempt( $attempt_id ) {
        $rows = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT * FROM {$this->table_results} WHERE attempt_id = %d", intval( $attempt_id ) ) );
        foreach ( $rows as $r ) {
            $r->given_answer = maybe_unserialize( $r->given_answer );
        }
        return $rows;
    }
}
