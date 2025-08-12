<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MM_Import_Export
 * Provide CSV/TXT/Excel import and export helpers for quizzes and results.
 * Note: PhpSpreadsheet is optional. If not available, CSV export still works.
 */
class MM_Import_Export {

    protected $quiz;
    protected $upload_dir;

    public function __construct() {
        if ( ! class_exists( 'MM_Quiz' ) ) {
            require_once MM_INCLUDES . 'class-mm-quiz.php';
        }
        $this->quiz = new MM_Quiz();
        $wp_up = wp_upload_dir();
        $this->upload_dir = trailingslashit( $wp_up['basedir'] ) . 'markdown-master/';
        if ( ! file_exists( $this->upload_dir ) ) {
            wp_mkdir_p( $this->upload_dir );
        }
    }

    /**
     * Import quiz from CSV file.
     * Expected CSV columns:
     * quiz_title, quiz_description, question_text, question_type, option_1, option_2, option_3, option_4, correct_option_index, question_image, option_images_json
     *
     * This will create one quiz and multiple questions.
     */
    public function import_quiz_from_csv( $file_path ) {
        if ( ! file_exists( $file_path ) ) {
            return new WP_Error( 'file_missing', 'File not found' );
        }

        $fh = fopen( $file_path, 'r' );
        if ( ! $fh ) {
            return new WP_Error( 'file_open', 'Unable to open file' );
        }

        $header = fgetcsv( $fh );
        if ( ! $header ) {
            fclose( $fh );
            return new WP_Error( 'invalid_csv', 'CSV header missing' );
        }

        // Normalize header
        $header = array_map( 'trim', $header );

        $quiz_id = null;
        while ( ( $row = fgetcsv( $fh ) ) !== false ) {
            $row = array_map( 'trim', $row );
            $data = array_combine( $header, $row );

            // Create quiz row first time
            if ( ! $quiz_id ) {
                $quiz_id = $this->quiz->create_quiz( [
                    'title' => isset( $data['quiz_title'] ) ? $data['quiz_title'] : 'Imported Quiz',
                    'description' => isset( $data['quiz_description'] ) ? $data['quiz_description'] : '',
                    'type' => 'mcq',
                    'settings' => [],
                ] );
            }

            // Question
            $question_type = isset( $data['question_type'] ) && $data['question_type'] ? $data['question_type'] : 'mcq';
            $q_id = $this->quiz->add_question( $quiz_id, [
                'question' => isset( $data['question_text'] ) ? $data['question_text'] : '',
                'image' => isset( $data['question_image'] ) ? $data['question_image'] : '',
                'type' => $question_type,
                'options' => [],
                'correct_answer' => null,
            ] );

            // Options
            $options = [];
            for ( $i = 1; $i <= 8; $i++ ) {
                $key = 'option_' . $i;
                if ( isset( $data[ $key ] ) && $data[ $key ] !== '' ) {
                    $options[] = $data[ $key ];
                }
            }
            if ( ! empty( $options ) ) {
                // store options JSON in question row
                $this->quiz->update_question( $q_id, [ 'options' => $options ] );

                // Add answer rows
                foreach ( $options as $idx => $opt ) {
                    $is_correct = 0;
                    if ( isset( $data['correct_option_index'] ) && intval( $data['correct_option_index'] ) - 1 === $idx ) {
                        $is_correct = 1;
                    }
                    $this->quiz->add_answer_option( $q_id, $opt, '', $is_correct );
                }
            }
        }

        fclose( $fh );

        if ( ! $quiz_id ) {
            return new WP_Error( 'no_quiz', 'No quiz data found in CSV' );
        }
        return $quiz_id;
    }

    /**
     * Export a single quiz to CSV
     */
    public function export_quiz_to_csv( $quiz_id ) {
        $quiz_id = intval( $quiz_id );
        if ( ! $quiz_id ) {
            return new WP_Error( 'invalid', 'Invalid quiz id' );
        }

        $quiz = $this->quiz->get_quiz( $quiz_id );
        if ( ! $quiz ) {
            return new WP_Error( 'not_found', 'Quiz not found' );
        }

        $filename = 'mm-quiz-' . $quiz_id . '-' . date( 'Ymd-His' ) . '.csv';
        $filepath = $this->upload_dir . $filename;

        $fh = fopen( $filepath, 'w' );
        if ( ! $fh ) {
            return new WP_Error( 'write_error', 'Unable to create export file' );
        }

        // header
        $header = [ 'quiz_title', 'quiz_description', 'question_text', 'question_type', 'option_1', 'option_2', 'option_3', 'option_4', 'correct_option_index', 'question_image' ];
        fputcsv( $fh, $header );

        $questions = $this->quiz->get_questions_by_quiz( $quiz_id );
        foreach ( $questions as $q ) {
            $answers = $this->quiz->get_answers_by_question( $q->id );
            $row = [
                $quiz->title,
                $quiz->description,
                $q->question,
                $q->type,
            ];
            // first 4 options
            for ( $i = 0; $i < 4; $i++ ) {
                $row[] = isset( $answers[ $i ] ) ? $answers[ $i ]->answer_text : '';
            }
            // find correct index (1-based)
            $correct_index = '';
            foreach ( $answers as $idx => $a ) {
                if ( intval( $a->is_correct ) === 1 ) {
                    $correct_index = $idx + 1;
                    break;
                }
            }
            $row[] = $correct_index;
            $row[] = isset( $q->image ) ? $q->image : '';

            fputcsv( $fh, $row );
        }

        fclose( $fh );

        return $filepath;
    }

    /**
     * Export all quizzes into a single CSV
     */
    public function export_all_quizzes_csv() {
        $filename = 'mm-quizzes-all-' . date( 'Ymd-His' ) . '.csv';
        $filepath = $this->upload_dir . $filename;
        $fh = fopen( $filepath, 'w' );
        if ( ! $fh ) {
            return new WP_Error( 'write_error', 'Unable to create export file' );
        }

        $header = [ 'quiz_id', 'quiz_title', 'question_id', 'question_text', 'question_type', 'answer_id', 'answer_text', 'is_correct' ];
        fputcsv( $fh, $header );

        global $wpdb;
        $quizzes = $wpdb->get_results( "SELECT id FROM {$wpdb->prefix}mm_quizzes" );
        foreach ( $quizzes as $q ) {
            $quiz_id = intval( $q->id );
            $questions = $this->quiz->get_questions_by_quiz( $quiz_id );
            foreach ( $questions as $ques ) {
                $answers = $this->quiz->get_answers_by_question( $ques->id );
                if ( empty( $answers ) ) {
                    fputcsv( $fh, [ $quiz_id, '', $ques->id, $ques->question, $ques->type, '', '', '' ] );
                } else {
                    foreach ( $answers as $a ) {
                        fputcsv( $fh, [ $quiz_id, '', $ques->id, $ques->question, $ques->type, $a->id, $a->answer_text, $a->is_correct ] );
                    }
                }
            }
        }

        fclose( $fh );
        return $filepath;
    }

    /**
     * Export results for a quiz (attempts + per-question correctness) to Excel (if PhpSpreadsheet exists) or CSV
     */
    public function export_results_for_quiz( $quiz_id ) {
        $quiz_id = intval( $quiz_id );
        if ( ! $quiz_id ) {
            return new WP_Error( 'invalid', 'Invalid quiz id' );
        }

        // gather data
        global $wpdb;
        $attempts_table = $wpdb->prefix . 'mm_quiz_attempts';
        $results_table = $wpdb->prefix . 'mm_quiz_results';

        $sql = $wpdb->prepare( "SELECT a.* FROM {$attempts_table} a WHERE quiz_id = %d ORDER BY completed_at DESC", $quiz_id );
        $attempts = $wpdb->get_results( $sql );

        $rows = [];
        foreach ( $attempts as $att ) {
            $res = $wpdb->get_results( $wpdb->prepare( "SELECT r.*, q.question FROM {$results_table} r LEFT JOIN {$wpdb->prefix}mm_quiz_questions q ON q.id = r.question_id WHERE r.attempt_id = %d", $att->id ) );
            foreach ( $res as $r ) {
                $rows[] = [
                    'attempt_id' => $att->id,
                    'user_name' => $att->user_name,
                    'user_email' => $att->user_email,
                    'user_class' => $att->user_class,
                    'user_section' => $att->user_section,
                    'score' => $att->score,
                    'completed_at' => $att->completed_at,
                    'question' => $r->question,
                    'given_answer' => is_serialized( $r->given_answer ) ? maybe_unserialize( $r->given_answer ) : $r->given_answer,
                    'is_correct' => $r->is_correct,
                ];
            }
        }

        // Prefer PhpSpreadsheet if available
        if ( class_exists( '\PhpOffice\PhpSpreadsheet\Spreadsheet' ) ) {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $cols = [ 'Attempt ID', 'Name', 'Email', 'Class', 'Section', 'Score', 'Completed At', 'Question', 'Given Answer', 'Is Correct' ];
            $sheet->fromArray( $cols, NULL, 'A1' );
            $rownum = 2;
            foreach ( $rows as $r ) {
                $sheet->setCellValue( "A{$rownum}", $r['attempt_id'] );
                $sheet->setCellValue( "B{$rownum}", $r['user_name'] );
                $sheet->setCellValue( "C{$rownum}", $r['user_email'] );
                $sheet->setCellValue( "D{$rownum}", $r['user_class'] );
                $sheet->setCellValue( "E{$rownum}", $r['user_section'] );
                $sheet->setCellValue( "F{$rownum}", $r['score'] );
                $sheet->setCellValue( "G{$rownum}", $r['completed_at'] );
                $sheet->setCellValue( "H{$rownum}", $r['question'] );
                $sheet->setCellValue( "I{$rownum}", is_array( $r['given_answer'] ) ? json_encode( $r['given_answer'] ) : $r['given_answer'] );
                $sheet->setCellValue( "J{$rownum}", $r['is_correct'] );
                $rownum++;
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet );
            $filename = 'mm-quiz-results-' . $quiz_id . '-' . date( 'Ymd-His' ) . '.xlsx';
            $filepath = $this->upload_dir . $filename;
            $writer->save( $filepath );
            return $filepath;
        }

        // Fallback: CSV
        $filename = 'mm-quiz-results-' . $quiz_id . '-' . date( 'Ymd-His' ) . '.csv';
        $filepath = $this->upload_dir . $filename;
        $fh = fopen( $filepath, 'w' );
        if ( ! $fh ) {
            return new WP_Error( 'write_error', 'Unable to create results file' );
        }

        $header = [ 'Attempt ID', 'Name', 'Email', 'Class', 'Section', 'Score', 'Completed At', 'Question', 'Given Answer', 'Is Correct' ];
        fputcsv( $fh, $header );
        foreach ( $rows as $r ) {
            fputcsv( $fh, [
                $r['attempt_id'],
                $r['user_name'],
                $r['user_email'],
                $r['user_class'],
                $r['user_section'],
                $r['score'],
                $r['completed_at'],
                $r['question'],
                is_array( $r['given_answer'] ) ? json_encode( $r['given_answer'] ) : $r['given_answer'],
                $r['is_correct'],
            ] );
        }
        fclose( $fh );
        return $filepath;
    }
}
