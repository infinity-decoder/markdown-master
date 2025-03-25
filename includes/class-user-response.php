<?php

if (!defined('ABSPATH')) {
    exit;
}

class User_Response {

    public function __construct() {
        add_action('wp_ajax_download_quiz_results', [$this, 'download_quiz_results']);
    }

    public function download_quiz_results() {
        global $wpdb;
        $responses_table = $wpdb->prefix . "markdown_master_responses";
        $quiz_id = intval($_GET['quiz_id']);

        $responses = $wpdb->get_results($wpdb->prepare("SELECT * FROM $responses_table WHERE quiz_id = %d", $quiz_id), ARRAY_A);

        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=quiz_results.csv");

        $output = fopen("php://output", "w");
        fputcsv($output, ['Name', 'Email', 'Score']);

        foreach ($responses as $response) {
            fputcsv($output, [$response['user_name'], $response['user_email'], $response['score']]);
        }

        fclose($output);
        exit;
    }
}
