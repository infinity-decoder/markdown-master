<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MM_Quiz
 *
 * Responsible for managing quizzes, questions and attempts.
 * This class uses the plugin's DB tables: mm_quizzes, mm_questions, mm_attempts, mm_attempt_answers.
 */
class MM_Quiz {

    /** @var wpdb */
    protected $wpdb;

    protected $table_quizzes;
    protected $table_questions;
    protected $table_attempts;
    protected $table_attempt_answers;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $prefix = $wpdb->prefix;
        $this->table_quizzes = $prefix . 'mm_quizzes';
        $this->table_questions = $prefix . 'mm_questions';
        $this->table_attempts = $prefix . 'mm_attempts';
        $this->table_attempt_answers = $prefix . 'mm_attempt_answers';
    }

    /**
     * Create a quiz and (optionally) its questions.
     *
     * $data = [
     *   'title' => 'Quiz title',
     *   'description' => '...',
     *   'settings' => [ 'shuffle' => 0, 'time_limit' => 0, 'attempts_allowed' => 0, 'show_answers' => 'end' ],
     *   'questions' => [
     *       [ 'question_text' => '...', 'question_type' => 'mcq', 'options' => [...], 'correct_answer' => ..., 'points' => 1 ],
     *       ...
     *   ]
     * ]
     *
     * @param array $data
     * @return int|WP_Error quiz id on success or WP_Error on failure
     */
    public function create_quiz( $data = array() ) {
        $title = isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '';
        $description = isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : '';
        $settings = isset( $data['settings'] ) ? $data['settings'] : array();

        $inserted = $this->wpdb->insert(
            $this->table_quizzes,
            array(
                'title'       => $title,
                'description' => $description,
                'settings'    => maybe_serialize( $settings ),
                'created_at'  => current_time( 'mysql' ),
                'updated_at'  => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s', '%s' )
        );

        if ( false === $inserted ) {
            return new WP_Error( 'db_insert_error', __( 'Could not create quiz (DB error).', 'markdown-master' ) );
        }

        $quiz_id = (int) $this->wpdb->insert_id;

        // Insert questions if provided
        if ( ! empty( $data['questions'] ) && is_array( $data['questions'] ) ) {
            foreach ( $data['questions'] as $q ) {
                $this->add_question( $quiz_id, $q );
            }
        }

        return $quiz_id;
    }

    /**
     * Update an existing quiz. Does not automatically delete questions not present in $data.
     *
     * $data can contain 'title','description','settings','questions' (questions as array with possible 'id' to update).
     *
     * @param int $quiz_id
     * @param array $data
     * @return bool|WP_Error
     */
    public function update_quiz( $quiz_id, $data = array() ) {
        $quiz_id = intval( $quiz_id );
        if ( $quiz_id <= 0 ) {
            return new WP_Error( 'invalid_id', __( 'Invalid quiz ID.', 'markdown-master' ) );
        }

        $fields = array();
        $formats = array();

        if ( isset( $data['title'] ) ) {
            $fields['title'] = sanitize_text_field( $data['title'] );
            $formats[] = '%s';
        }
        if ( isset( $data['description'] ) ) {
            $fields['description'] = wp_kses_post( $data['description'] );
            $formats[] = '%s';
        }
        if ( isset( $data['settings'] ) ) {
            $fields['settings'] = maybe_serialize( $data['settings'] );
            $formats[] = '%s';
        }

        $fields['updated_at'] = current_time( 'mysql' );
        $formats[] = '%s';

        if ( ! empty( $fields ) ) {
            $where = array( 'id' => $quiz_id );
            $where_format = array( '%d' );
            $updated = $this->wpdb->update( $this->table_quizzes, $fields, $where, $formats, $where_format );
            if ( false === $updated ) {
                return new WP_Error( 'db_update_error', __( 'Could not update quiz.', 'markdown-master' ) );
            }
        }

        // Handle questions (if provided). Questions with 'id' will be updated; without 'id' will be created.
        if ( ! empty( $data['questions'] ) && is_array( $data['questions'] ) ) {
            foreach ( $data['questions'] as $q ) {
                if ( ! empty( $q['id'] ) ) {
                    $this->update_question( intval( $q['id'] ), $q );
                } else {
                    $this->add_question( $quiz_id, $q );
                }
            }
        }

        return true;
    }

    /**
     * Delete a quiz and all its related questions, attempts and attempt answers.
     *
     * @param int $quiz_id
     * @return bool
     */
    public function delete_quiz( $quiz_id ) {
        $quiz_id = intval( $quiz_id );
        if ( $quiz_id <= 0 ) {
            return false;
        }

        // Delete attempt answers related to attempts of this quiz
        $attempts = $this->get_attempts_by_quiz( $quiz_id, -1, 0 ); // all
        if ( ! empty( $attempts ) ) {
            foreach ( $attempts as $a ) {
                $this->wpdb->delete( $this->table_attempt_answers, array( 'attempt_id' => $a->id ), array( '%d' ) );
            }
            // Delete attempts themselves
            $this->wpdb->delete( $this->table_attempts, array( 'quiz_id' => $quiz_id ), array( '%d' ) );
        }

        // Delete questions
        $this->wpdb->delete( $this->table_questions, array( 'quiz_id' => $quiz_id ), array( '%d' ) );

        // Delete quiz
        $this->wpdb->delete( $this->table_quizzes, array( 'id' => $quiz_id ), array( '%d' ) );

        return true;
    }

    /**
     * Add a question to a quiz.
     *
     * $qdata = [
     *   'question_text' => '...',
     *   'question_type' => 'mcq',
     *   'options'       => [...], // optional
     *   'correct_answer'=> ...,
     *   'points'        => 1.0
     * ]
     *
     * @param int $quiz_id
     * @param array $qdata
     * @return int|WP_Error inserted question id
     */
    public function add_question( $quiz_id, $qdata = array() ) {
        $quiz_id = intval( $quiz_id );
        if ( $quiz_id <= 0 ) {
            return new WP_Error( 'invalid_quiz', __( 'Invalid quiz ID when adding question.', 'markdown-master' ) );
        }

        $question_text = isset( $qdata['question_text'] ) ? wp_kses_post( $qdata['question_text'] ) : '';
        $question_type = isset( $qdata['question_type'] ) ? sanitize_key( $qdata['question_type'] ) : 'mcq';
        $options = isset( $qdata['options'] ) ? $qdata['options'] : array();
        $correct_answer = isset( $qdata['correct_answer'] ) ? $qdata['correct_answer'] : null;
        $points = isset( $qdata['points'] ) ? floatval( $qdata['points'] ) : 1.0;

        $inserted = $this->wpdb->insert(
            $this->table_questions,
            array(
                'quiz_id'       => $quiz_id,
                'question_text' => $question_text,
                'question_type' => $question_type,
                'options'       => maybe_serialize( $options ),
                'correct_answer'=> maybe_serialize( $correct_answer ),
                'points'        => $points,
                'created_at'    => current_time( 'mysql' ),
                'updated_at'    => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s' )
        );

        if ( false === $inserted ) {
            return new WP_Error( 'db_insert_error', __( 'Could not insert question.', 'markdown-master' ) );
        }

        return (int) $this->wpdb->insert_id;
    }

    /**
     * Update an existing question.
     *
     * @param int $question_id
     * @param array $qdata
     * @return bool|WP_Error
     */
    public function update_question( $question_id, $qdata = array() ) {
        $question_id = intval( $question_id );
        if ( $question_id <= 0 ) {
            return new WP_Error( 'invalid_question_id', __( 'Invalid question ID.', 'markdown-master' ) );
        }

        $fields = array();
        $formats = array();

        if ( isset( $qdata['question_text'] ) ) {
            $fields['question_text'] = wp_kses_post( $qdata['question_text'] );
            $formats[] = '%s';
        }
        if ( isset( $qdata['question_type'] ) ) {
            $fields['question_type'] = sanitize_key( $qdata['question_type'] );
            $formats[] = '%s';
        }
        if ( isset( $qdata['options'] ) ) {
            $fields['options'] = maybe_serialize( $qdata['options'] );
            $formats[] = '%s';
        }
        if ( array_key_exists( 'correct_answer', $qdata ) ) {
            $fields['correct_answer'] = maybe_serialize( $qdata['correct_answer'] );
            $formats[] = '%s';
        }
        if ( isset( $qdata['points'] ) ) {
            $fields['points'] = floatval( $qdata['points'] );
            $formats[] = '%f';
        }

        $fields['updated_at'] = current_time( 'mysql' );
        $formats[] = '%s';

        if ( empty( $fields ) ) {
            return true;
        }

        $where = array( 'id' => $question_id );
        $where_format = array( '%d' );

        $updated = $this->wpdb->update( $this->table_questions, $fields, $where, $formats, $where_format );
        if ( false === $updated ) {
            return new WP_Error( 'db_update_error', __( 'Could not update question.', 'markdown-master' ) );
        }

        return true;
    }

    /**
     * Delete a question and related attempt-answers.
     *
     * @param int $question_id
     * @return bool
     */
    public function delete_question( $question_id ) {
        $question_id = intval( $question_id );
        if ( $question_id <= 0 ) {
            return false;
        }

        // Delete attempt answers referencing this question
        $this->wpdb->delete( $this->table_attempt_answers, array( 'question_id' => $question_id ), array( '%d' ) );

        // Delete question
        $this->wpdb->delete( $this->table_questions, array( 'id' => $question_id ), array( '%d' ) );

        return true;
    }

    /**
     * Record an attempt: evaluate and save both attempt meta and per-question answers.
     *
     * $student = [ 'name' => '', 'roll' => '', 'class' => '', 'section' => '', 'school' => '' ]
     * $answers = [ question_id => given_answer, ... ] where given_answer can be scalar or array
     *
     * @param int $quiz_id
     * @param array $student
     * @param array $answers
     * @return array|WP_Error [ 'attempt_id' => int, 'score' => float, 'total' => float ]
     */
    public function record_attempt( $quiz_id, $student = array(), $answers = array() ) {
        $quiz_id = intval( $quiz_id );
        if ( $quiz_id <= 0 ) {
            return new WP_Error( 'invalid_quiz', __( 'Invalid quiz ID.', 'markdown-master' ) );
        }

        // Normalize student fields
        $student_name = isset( $student['name'] ) ? sanitize_text_field( $student['name'] ) : '';
        $student_roll = isset( $student['roll'] ) ? sanitize_text_field( $student['roll'] ) : '';
        $student_class = isset( $student['class'] ) ? sanitize_text_field( $student['class'] ) : '';
        $student_section = isset( $student['section'] ) ? sanitize_text_field( $student['section'] ) : '';
        $student_school = isset( $student['school'] ) ? sanitize_text_field( $student['school'] ) : '';

        // Fetch questions for the quiz
        $questions = $this->get_questions( $quiz_id );
        if ( empty( $questions ) ) {
            return new WP_Error( 'no_questions', __( 'Quiz has no questions.', 'markdown-master' ) );
        }

        // Build a quick map of questions by id
        $map = array();
        $total_marks = 0.0;
        foreach ( $questions as $q ) {
            $map[ intval( $q['id'] ) ] = $q;
            $total_marks += floatval( $q['points'] ?? 1.0 );
        }

        $score = 0.0;

        // Evaluate each provided answer
        foreach ( $answers as $qid_raw => $given ) {
            $qid = intval( $qid_raw );
            if ( ! isset( $map[ $qid ] ) ) {
                continue;
            }
            $question = $map[ $qid ];

            $correct_raw = $question['correct_answer'];
            // Ensure correct is unserialized
            $correct = maybe_unserialize( $correct_raw );

            $is_correct = 0;
            $points = floatval( $question['points'] ?? 1.0 );

            // Normalize given answer format
            // If incoming $given is an array of checked values, keep as array
            // If scalar, compare directly
            if ( is_array( $correct ) ) {
                // Normalize both to simple arrays of strings and compare ignoring order
                $expected = array_map( 'strval', $correct );
                $given_arr = is_array( $given ) ? array_map( 'strval', $given ) : array_map( 'strval', array( $given ) );
                sort( $expected ); sort( $given_arr );
                if ( $expected === $given_arr ) {
                    $is_correct = 1;
                }
            } else {
                // scalar comparison (string)
                if ( is_array( $given ) ) {
                    // if question expects scalar but got array, not correct
                    $is_correct = 0;
                } else {
                    if ( strval( $correct ) === strval( $given ) ) {
                        $is_correct = 1;
                    }
                }
            }

            if ( $is_correct ) {
                $score += $points;
            }
        }

        // Insert attempt record
        $inserted = $this->wpdb->insert(
            $this->table_attempts,
            array(
                'quiz_id'       => $quiz_id,
                'student_name'  => $student_name,
                'student_roll'  => $student_roll,
                'student_class' => $student_class,
                'student_section'=> $student_section,
                'student_school'=> $student_school,
                'obtained_marks'=> $score,
                'total_marks'   => $total_marks,
                'meta'          => maybe_serialize( $student ),
                'created_at'    => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s' )
        );

        if ( false === $inserted ) {
            return new WP_Error( 'db_insert_error', __( 'Could not record attempt.', 'markdown-master' ) );
        }

        $attempt_id = (int) $this->wpdb->insert_id;

        // Insert per-question answers records
        foreach ( $answers as $qid_raw => $given ) {
            $qid = intval( $qid_raw );
            if ( ! isset( $map[ $qid ] ) ) {
                continue;
            }
            $question = $map[ $qid ];
            $correct_raw = $question['correct_answer'];
            $correct = maybe_unserialize( $correct_raw );
            $is_correct = 0;

            if ( is_array( $correct ) ) {
                $expected = array_map( 'strval', $correct );
                $given_arr = is_array( $given ) ? array_map( 'strval', $given ) : array_map( 'strval', array( $given ) );
                sort( $expected ); sort( $given_arr );
                if ( $expected === $given_arr ) {
                    $is_correct = 1;
                }
            } else {
                if ( ! is_array( $given ) && strval( $correct ) === strval( $given ) ) {
                    $is_correct = 1;
                }
            }

            $this->wpdb->insert(
                $this->table_attempt_answers,
                array(
                    'attempt_id'  => $attempt_id,
                    'question_id' => $qid,
                    'given_answer'=> maybe_serialize( $given ),
                    'is_correct'  => $is_correct,
                    'created_at'  => current_time( 'mysql' ),
                ),
                array( '%d', '%d', '%s', '%d', '%s' )
            );
        }

        return array(
            'attempt_id' => $attempt_id,
            'score'      => $score,
            'total'      => $total_marks,
        );
    }

    /**
     * Get quiz row (with unserialized settings and optional questions).
     *
     * @param int $quiz_id
     * @param bool $with_questions
     * @return array|null
     */
    public function get_quiz( $quiz_id, $with_questions = true ) {
        $quiz_id = intval( $quiz_id );
        if ( $quiz_id <= 0 ) {
            return null;
        }

        $row = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$this->table_quizzes} WHERE id = %d", $quiz_id ), ARRAY_A );

        if ( ! $row ) {
            return null;
        }

        $row['settings'] = maybe_unserialize( $row['settings'] );
        if ( $with_questions ) {
            $row['questions'] = $this->get_questions( $quiz_id );
        }

        return $row;
    }

    /**
     * Get questions for a quiz (returns array of arrays with unserialized fields).
     *
     * @param int $quiz_id
     * @return array
     */
    public function get_questions( $quiz_id ) {
        $quiz_id = intval( $quiz_id );
        if ( $quiz_id <= 0 ) {
            return array();
        }

        $rows = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT * FROM {$this->table_questions} WHERE quiz_id = %d ORDER BY id ASC", $quiz_id ), ARRAY_A );
        if ( empty( $rows ) ) {
            return array();
        }

        foreach ( $rows as &$r ) {
            $r['options'] = maybe_unserialize( $r['options'] );
            $r['correct_answer'] = maybe_unserialize( $r['correct_answer'] );
        }

        return $rows;
    }

    /**
     * Return attempts for a quiz with basic pagination support.
     *
     * @param int $quiz_id
     * @param int $limit (-1 for all)
     * @param int $offset
     * @return array|object[] (rows)
     */
    public function get_attempts_by_quiz( $quiz_id, $limit = 25, $offset = 0 ) {
        $quiz_id = intval( $quiz_id );
        if ( $quiz_id <= 0 ) {
            return array();
        }

        if ( $limit <= 0 ) {
            // return all
            return $this->wpdb->get_results( $this->wpdb->prepare( "SELECT * FROM {$this->table_attempts} WHERE quiz_id = %d ORDER BY created_at DESC", $quiz_id ) );
        }

        return $this->wpdb->get_results( $this->wpdb->prepare( "SELECT * FROM {$this->table_attempts} WHERE quiz_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d", $quiz_id, intval( $limit ), intval( $offset ) ) );
    }

    /**
     * Get a single attempt with answers.
     *
     * @param int $attempt_id
     * @return array|null
     */
    public function get_attempt( $attempt_id ) {
        $attempt_id = intval( $attempt_id );
        if ( $attempt_id <= 0 ) {
            return null;
        }

        $row = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$this->table_attempts} WHERE id = %d", $attempt_id ), ARRAY_A );
        if ( ! $row ) {
            return null;
        }

        $row['meta'] = maybe_unserialize( $row['meta'] );
        $row['answers'] = $this->get_attempt_answers( $attempt_id );

        return $row;
    }

    /**
     * Get attempt answers.
     *
     * @param int $attempt_id
     * @return array
     */
    public function get_attempt_answers( $attempt_id ) {
        $attempt_id = intval( $attempt_id );
        if ( $attempt_id <= 0 ) {
            return array();
        }

        $rows = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT * FROM {$this->table_attempt_answers} WHERE attempt_id = %d ORDER BY id ASC", $attempt_id ), ARRAY_A );

        foreach ( $rows as &$r ) {
            $r['given_answer'] = maybe_unserialize( $r['given_answer'] );
        }

        return $rows;
    }

    /**
     * Get aggregated results / summary for a quiz.
     *
     * Returns array with total attempts, avg_score, best_score, worst_score, attempts_rows (optionally limited).
     *
     * @param int $quiz_id
     * @param int $limit
     * @return array
     */
    public function get_results( $quiz_id, $limit = 100 ) {
        $quiz_id = intval( $quiz_id );
        if ( $quiz_id <= 0 ) {
            return array();
        }

        $total_attempts = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT COUNT(*) FROM {$this->table_attempts} WHERE quiz_id = %d", $quiz_id ) );
        $avg_score = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT AVG(obtained_marks) FROM {$this->table_attempts} WHERE quiz_id = %d", $quiz_id ) );
        $best_score = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT MAX(obtained_marks) FROM {$this->table_attempts} WHERE quiz_id = %d", $quiz_id ) );
        $worst_score = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT MIN(obtained_marks) FROM {$this->table_attempts} WHERE quiz_id = %d", $quiz_id ) );

        $attempts_rows = $this->get_attempts_by_quiz( $quiz_id, $limit );

        return array(
            'total_attempts' => intval( $total_attempts ),
            'avg_score'      => floatval( $avg_score ),
            'best_score'     => floatval( $best_score ),
            'worst_score'    => floatval( $worst_score ),
            'attempts'       => $attempts_rows,
        );
    }
}
