<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Markdown Master - DB Model
 * Provides CRUD for quizzes, questions, attempts and simple scoring.
 *
 * Storage format:
 *  - options (question options): JSON string (array of strings)
 *  - correct_answer: JSON string (scalar or array)
 *  - answers (attempt): JSON object { question_id: scalar|array }
 *
 * All methods avoid echo/print and use $wpdb->prepare().
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
     * QUIZZES
     * ======================= */

    public function create_quiz( array $data ) {
        $now = current_time( 'mysql' );

        $defaults = [
            'title'            => '',
            'description'      => '',
            'settings'         => [],
            'shuffle'          => 0,
            'time_limit'       => 0,
            'attempts_allowed' => 0,
            'show_answers'     => 0,
        ];
        $data = wp_parse_args( $data, $defaults );

        $insert = [
            'title'            => sanitize_text_field( $data['title'] ),
            'description'      => wp_kses_post( $data['description'] ),
            'settings'         => wp_json_encode( is_array( $data['settings'] ) ? $data['settings'] : [] ),
            'shuffle'          => (int) ! empty( $data['shuffle'] ),
            'time_limit'       => (int) $data['time_limit'],
            'attempts_allowed' => (int) $data['attempts_allowed'],
            'show_answers'     => (int) ! empty( $data['show_answers'] ),
            'created_at'       => $now,
            'updated_at'       => $now,
        ];

        $ok = $this->db->insert(
            $this->table_quizzes,
            $insert,
            [ '%s','%s','%s','%d','%d','%d','%d','%s','%s' ]
        );

        return $ok ? (int) $this->db->insert_id : 0;
    }

    public function update_quiz( int $id, array $data ) {
        if ( $id <= 0 ) return false;

        $now = current_time( 'mysql' );

        $fields = [];
        $formats = [];

        if ( isset( $data['title'] ) ) {
            $fields['title'] = sanitize_text_field( $data['title'] );
            $formats[] = '%s';
        }
        if ( isset( $data['description'] ) ) {
            $fields['description'] = wp_kses_post( $data['description'] );
            $formats[] = '%s';
        }
        if ( isset( $data['settings'] ) ) {
            $fields['settings'] = wp_json_encode( is_array( $data['settings'] ) ? $data['settings'] : [] );
            $formats[] = '%s';
        }
        foreach ( [ 'shuffle','time_limit','attempts_allowed','show_answers' ] as $k ) {
            if ( isset( $data[ $k ] ) ) {
                $fields[ $k ] = (int) $data[ $k ];
                $formats[] = '%d';
            }
        }

        $fields['updated_at'] = $now;
        $formats[] = '%s';

        if ( empty( $fields ) ) return false;

        return false !== $this->db->update(
            $this->table_quizzes,
            $fields,
            [ 'id' => $id ],
            $formats,
            [ '%d' ]
        );
    }

    public function delete_quiz( int $id ) {
        if ( $id <= 0 ) return false;

        // Delete questions
        $qs = $this->db->get_col( $this->db->prepare(
            "SELECT id FROM {$this->table_questions} WHERE quiz_id = %d",
            $id
        ) );
        if ( $qs ) {
            foreach ( $qs as $qid ) {
                $this->delete_question( (int) $qid, false ); // silent
            }
        }

        // Delete attempts and their answers
        $attempts = $this->db->get_col( $this->db->prepare(
            "SELECT id FROM {$this->table_attempts} WHERE quiz_id = %d",
            $id
        ) );
        if ( $attempts ) {
            foreach ( $attempts as $aid ) {
                $this->db->delete( $this->table_attempt_answers, [ 'attempt_id' => (int) $aid ], [ '%d' ] );
            }
            $this->db->query( $this->db->prepare(
                "DELETE FROM {$this->table_attempts} WHERE quiz_id = %d",
                $id
            ) );
        }

        // Finally delete the quiz
        return false !== $this->db->delete( $this->table_quizzes, [ 'id' => $id ], [ '%d' ] );
    }

    public function get_quiz( int $id, bool $with_questions = false ) {
        if ( $id <= 0 ) return null;

        $row = $this->db->get_row( $this->db->prepare(
            "SELECT * FROM {$this->table_quizzes} WHERE id = %d",
            $id
        ), ARRAY_A );

        if ( ! $row ) {
            return null;
        }

        // decode settings if JSON
        $row['settings'] = $this->maybe_decode( $row['settings'] );

        if ( $with_questions ) {
            $row['questions'] = $this->get_questions( $id );
        }

        return $row;
    }

    public function get_quizzes( int $limit = 50, int $offset = 0 ) {
        $limit  = max( 1, $limit );
        $offset = max( 0, $offset );
        $rows = $this->db->get_results( $this->db->prepare(
            "SELECT * FROM {$this->table_quizzes} ORDER BY id DESC LIMIT %d OFFSET %d",
            $limit, $offset
        ), ARRAY_A );

        if ( ! $rows ) return [];

        foreach ( $rows as &$r ) {
            $r['settings'] = $this->maybe_decode( $r['settings'] );
        }
        return $rows;
    }

    /* =========================
     * QUESTIONS
     * ======================= */

    public function add_question( int $quiz_id, array $data ) {
        if ( $quiz_id <= 0 ) return 0;

        $now = current_time( 'mysql' );

        $defaults = [
            'question_text'  => '',
            'type'           => 'single', // single|multiple|text|textarea
            'options'        => [],       // array of strings for single/multiple
            'correct_answer' => null,     // scalar or array
            'points'         => 1.0,
            'image'          => null,
        ];
        $data = wp_parse_args( $data, $defaults );

        $insert = [
            'quiz_id'        => (int) $quiz_id,
            'question_text'  => wp_kses_post( $data['question_text'] ),
            'type'           => sanitize_key( $data['type'] ),
            'options'        => wp_json_encode( is_array( $data['options'] ) ? array_values( array_map( 'wp_kses_post', $data['options'] ) ) : [] ),
            'correct_answer' => wp_json_encode( $data['correct_answer'] ),
            'points'         => floatval( $data['points'] ),
            'image'          => $data['image'] ? esc_url_raw( $data['image'] ) : null,
            'created_at'     => $now,
            'updated_at'     => $now,
        ];

        $ok = $this->db->insert(
            $this->table_questions,
            $insert,
            [ '%d','%s','%s','%s','%s','%f','%s','%s','%s' ]
        );

        return $ok ? (int) $this->db->insert_id : 0;
    }

    public function update_question( int $question_id, array $data ) {
        if ( $question_id <= 0 ) return false;

        $now    = current_time( 'mysql' );
        $fields = [];
        $fmts   = [];

        if ( isset( $data['question_text'] ) ) {
            $fields['question_text'] = wp_kses_post( $data['question_text'] );
            $fmts[] = '%s';
        }
        if ( isset( $data['type'] ) ) {
            $fields['type'] = sanitize_key( $data['type'] );
            $fmts[] = '%s';
        }
        if ( array_key_exists( 'options', $data ) ) {
            $fields['options'] = wp_json_encode( is_array( $data['options'] ) ? array_values( array_map( 'wp_kses_post', $data['options'] ) ) : [] );
            $fmts[] = '%s';
        }
        if ( array_key_exists( 'correct_answer', $data ) ) {
            $fields['correct_answer'] = wp_json_encode( $data['correct_answer'] );
            $fmts[] = '%s';
        }
        if ( isset( $data['points'] ) ) {
            $fields['points'] = floatval( $data['points'] );
            $fmts[] = '%f';
        }
        if ( array_key_exists( 'image', $data ) ) {
            $fields['image'] = $data['image'] ? esc_url_raw( $data['image'] ) : null;
            $fmts[] = '%s';
        }

        $fields['updated_at'] = $now; $fmts[] = '%s';

        if ( empty( $fields ) ) return false;

        return false !== $this->db->update(
            $this->table_questions,
            $fields,
            [ 'id' => $question_id ],
            $fmts,
            [ '%d' ]
        );
    }

    public function delete_question( int $question_id, bool $delete_attempt_rows = true ) {
        if ( $question_id <= 0 ) return false;

        if ( $delete_attempt_rows ) {
            // Optional: clear per-question attempt rows (if used)
            $this->db->delete( $this->table_attempt_answers, [ 'question_id' => $question_id ], [ '%d' ] );
        }
        return false !== $this->db->delete( $this->table_questions, [ 'id' => $question_id ], [ '%d' ] );
    }

    public function get_questions( int $quiz_id ) {
        if ( $quiz_id <= 0 ) return [];

        $rows = $this->db->get_results( $this->db->prepare(
            "SELECT * FROM {$this->table_questions} WHERE quiz_id = %d ORDER BY id ASC",
            $quiz_id
        ), ARRAY_A );

        if ( ! $rows ) return [];

        foreach ( $rows as &$r ) {
            $r['options']        = $this->maybe_decode( $r['options'] );
            $r['correct_answer'] = $this->maybe_decode( $r['correct_answer'] );
        }

        return $rows;
    }

    /* =========================
     * ATTEMPTS & SCORING
     * ======================= */

    /**
     * Record an attempt and return the attempt id.
     * $answers: array like [ question_id => scalar|string|array ]
     * If $score_now is true, compute score server-side using stored correct answers.
     */
    public function record_attempt( int $quiz_id, array $student, array $answers, bool $score_now = true ) {
        if ( $quiz_id <= 0 ) return 0;

        $now = current_time( 'mysql' );

        $student_defaults = [
            'student_name'    => '',
            'student_class'   => '',
            'student_section' => '',
            'student_school'  => '',
            'student_roll'    => '',
        ];
        $student = wp_parse_args( $student, $student_defaults );

        $obtained = 0.0;
        $total    = 0.0;

        if ( $score_now ) {
            $score = $this->compute_score( $quiz_id, $answers );
            $obtained = $score['obtained'];
            $total    = $score['total'];
        } else {
            // Compute total as sum of points anyway (for consistency)
            $questions = $this->get_questions( $quiz_id );
            foreach ( $questions as $q ) {
                $total += floatval( $q['points'] );
            }
        }

        $insert = [
            'quiz_id'        => $quiz_id,
            'student_name'   => sanitize_text_field( $student['student_name'] ),
            'student_class'  => sanitize_text_field( $student['student_class'] ),
            'student_section'=> sanitize_text_field( $student['student_section'] ),
            'student_school' => sanitize_text_field( $student['student_school'] ),
            'student_roll'   => sanitize_text_field( $student['student_roll'] ),
            'obtained_marks' => $obtained,
            'total_marks'    => $total,
            'answers'        => wp_json_encode( $answers ),
            'created_at'     => $now,
        ];

        $ok = $this->db->insert(
            $this->table_attempts,
            $insert,
            [ '%d','%s','%s','%s','%s','%s','%f','%f','%s','%s' ]
        );

        $attempt_id = $ok ? (int) $this->db->insert_id : 0;

        // Optionally populate per-question rows (for analytics)
        if ( $attempt_id && $score_now ) {
            $this->save_per_question_breakdown( $attempt_id, $quiz_id, $answers );
        }

        return $attempt_id;
    }

    /**
     * Compute score server-side.
     */
    public function compute_score( int $quiz_id, array $answers ) {
        $questions = $this->get_questions( $quiz_id );

        $obtained = 0.0;
        $total    = 0.0;

        foreach ( $questions as $q ) {
            $points   = floatval( $q['points'] );
            $total   += $points;

            $qid      = (int) $q['id'];
            $correct  = $q['correct_answer'];
            $type     = $q['type'];

            $given = $answers[ $qid ] ?? null;

            // Normalize
            if ( $type === 'multiple' ) {
                $correct_set = is_array( $correct ) ? array_values( array_map( 'strval', $correct ) ) : [];
                $given_set   = is_array( $given )   ? array_values( array_map( 'strval', $given ) )   : [];
                sort( $correct_set );
                sort( $given_set );
                if ( $correct_set === $given_set ) {
                    $obtained += $points;
                }
            } elseif ( $type === 'single' ) {
                if ( (string) $given !== '' && (string) $given === (string) ( is_array( $correct ) ? reset( $correct ) : $correct ) ) {
                    $obtained += $points;
                }
            } else {
                // text / textarea — simple exact (case-insensitive) match if correct answer provided
                $correct_text = is_array( $correct ) ? (string) reset( $correct ) : (string) $correct;
                if ( $correct_text !== '' && is_string( $given ) ) {
                    if ( mb_strtolower( trim( $given ) ) === mb_strtolower( trim( $correct_text ) ) ) {
                        $obtained += $points;
                    }
                }
            }
        }

        return [
            'obtained' => (float) $obtained,
            'total'    => (float) $total,
        ];
    }

    protected function save_per_question_breakdown( int $attempt_id, int $quiz_id, array $answers ) {
        $questions = $this->get_questions( $quiz_id );
        if ( ! $questions ) return;

        foreach ( $questions as $q ) {
            $qid     = (int) $q['id'];
            $type    = $q['type'];
            $points  = floatval( $q['points'] );
            $correct = $q['correct_answer'];

            $given = $answers[ $qid ] ?? null;

            $is_correct = 0;

            if ( $type === 'multiple' ) {
                $c = is_array( $correct ) ? array_values( array_map( 'strval', $correct ) ) : [];
                $g = is_array( $given )   ? array_values( array_map( 'strval', $given ) )   : [];
                sort( $c ); sort( $g );
                $is_correct = ( $c === $g ) ? 1 : 0;
            } elseif ( $type === 'single' ) {
                $right = is_array( $correct ) ? (string) reset( $correct ) : (string) $correct;
                $is_correct = ( (string) $given !== '' && (string) $given === $right ) ? 1 : 0;
            } else {
                $right = is_array( $correct ) ? (string) reset( $correct ) : (string) $correct;
                if ( $right !== '' && is_string( $given ) ) {
                    $is_correct = ( mb_strtolower( trim( $given ) ) === mb_strtolower( trim( $right ) ) ) ? 1 : 0;
                }
            }

            $this->db->insert(
                $this->table_attempt_answers,
                [
                    'attempt_id'     => $attempt_id,
                    'question_id'    => $qid,
                    'answer'         => wp_json_encode( $given ),
                    'is_correct'     => $is_correct,
                    'points_awarded' => $is_correct ? $points : 0.0,
                ],
                [ '%d','%d','%s','%d','%f' ]
            );
        }
    }

    public function get_attempt( int $attempt_id ) {
        if ( $attempt_id <= 0 ) return null;

        $row = $this->db->get_row( $this->db->prepare(
            "SELECT * FROM {$this->table_attempts} WHERE id = %d",
            $attempt_id
        ), ARRAY_A );

        if ( ! $row ) return null;

        $row['answers'] = $this->maybe_decode( $row['answers'] );
        return $row;
    }

    /**
     * Fetch attempts for a quiz (returns array of stdClass like $wpdb->get_results()).
     * If $limit < 0 → no LIMIT.
     */
    public function get_attempts_by_quiz( int $quiz_id, int $limit = 20, int $offset = 0 ) {
        if ( $quiz_id <= 0 ) return [];

        if ( $limit < 0 ) {
            $sql = $this->db->prepare(
                "SELECT * FROM {$this->table_attempts} WHERE quiz_id = %d ORDER BY created_at DESC",
                $quiz_id
            );
        } else {
            $limit  = max( 1, $limit );
            $offset = max( 0, $offset );
            $sql = $this->db->prepare(
                "SELECT * FROM {$this->table_attempts} WHERE quiz_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $quiz_id, $limit, $offset
            );
        }

        return $this->db->get_results( $sql );
    }

    /* =========================
     * HELPERS
     * ======================= */

    /**
     * Decode JSON if possible; fallback to maybe_unserialize for legacy rows.
     */
    protected function maybe_decode( $raw ) {
        if ( is_array( $raw ) || is_object( $raw ) ) {
            return $raw;
        }
        if ( ! is_string( $raw ) ) {
            return $raw;
        }
        $raw = trim( $raw );
        if ( $raw === '' ) {
            return [];
        }
        $decoded = json_decode( $raw, true );
        if ( json_last_error() === JSON_ERROR_NONE ) {
            return $decoded;
        }
        // Fallback: some older code saved serialized arrays
        $maybe = maybe_unserialize( $raw );
        if ( is_array( $maybe ) || is_object( $maybe ) ) {
            return $maybe;
        }
        return $raw;
    }
}
