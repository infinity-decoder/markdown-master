<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
}

// Load required classes
if ( ! class_exists( 'MM_Results' ) ) {
    require_once MM_INCLUDES . 'class-mm-results.php';
}
if ( ! class_exists( 'MM_Quiz' ) ) {
    require_once MM_INCLUDES . 'class-mm-quiz.php';
}
if ( ! class_exists( 'MM_Import_Export' ) ) {
    require_once MM_INCLUDES . 'class-mm-import-export.php';
}

$results = new MM_Results();
$quiz_model = new MM_Quiz();
$ix = new MM_Import_Export();

// Handle export request (download)
if ( isset( $_GET['mm_action'] ) && $_GET['mm_action'] === 'export_results' && ! empty( $_GET['quiz_id'] ) ) {
    if ( ! check_admin_referer( 'mm_export_results_' . intval( $_GET['quiz_id'] ), 'mm_export_nonce' ) ) {
        wp_die( __( 'Security check failed', 'markdown-master' ) );
    }
    $path = $ix->export_results_for_quiz( intval( $_GET['quiz_id'] ) );
    if ( is_wp_error( $path ) ) {
        $export_message = $path->get_error_message();
    } else {
        // Provide download link
        $upload_base = mm_get_upload_base();
        $download_url = str_replace( $upload_base['path'], $upload_base['url'], $path );
        echo '<script>location.href="' . esc_url( $download_url ) . '";</script>';
        exit;
    }
}

// Filters / pagination input
$paged = max( 1, intval( $_GET['paged'] ?? 1 ) );
$per_page = 25;
$filter_args = [
    'quiz_id' => isset( $_GET['quiz_id'] ) ? intval( $_GET['quiz_id'] ) : 0,
    'user_name' => sanitize_text_field( $_GET['user_name'] ?? '' ),
    'user_email' => sanitize_text_field( $_GET['user_email'] ?? '' ),
    'user_class' => sanitize_text_field( $_GET['user_class'] ?? '' ),
    'user_section' => sanitize_text_field( $_GET['user_section'] ?? '' ),
    'min_score' => isset( $_GET['min_score'] ) ? floatval( $_GET['min_score'] ) : '',
    'max_score' => isset( $_GET['max_score'] ) ? floatval( $_GET['max_score'] ) : '',
    'page' => $paged,
    'per_page' => $per_page,
    'order_by' => in_array( $_GET['order_by'] ?? '', [ 'score', 'completed_at' ], true ) ? $_GET['order_by'] : 'completed_at',
    'order' => in_array( strtoupper( $_GET['order'] ?? 'DESC' ), [ 'ASC', 'DESC' ], true ) ? strtoupper( $_GET['order'] ?? 'DESC' ) : 'DESC',
];

// Fetch attempts
$attempts = $results->get_attempts( $filter_args );
$total_items = $results->count_attempts( $filter_args );
$total_pages = (int) ceil( $total_items / $per_page );

// Fetch quizzes for filter dropdown
$all_quizzes = $quiz_model->get_all_quizzes( [ 'per_page' => 1000, 'page' => 1 ] );

?>
<div class="wrap mm-results-page">
    <h1><?php esc_html_e( 'Quiz Results', 'markdown-master' ); ?></h1>

    <form method="get" class="mm-results-filters" style="margin-bottom:20px;">
        <input type="hidden" name="page" value="mm_results">
        <label>
            <?php esc_html_e( 'Quiz', 'markdown-master' ); ?>
            <select name="quiz_id">
                <option value="0"><?php esc_html_e( 'All Quizzes', 'markdown-master' ); ?></option>
                <?php foreach ( $all_quizzes as $q ): ?>
                    <option value="<?php echo intval( $q->id ); ?>" <?php selected( $filter_args['quiz_id'], intval( $q->id ) ); ?>><?php echo esc_html( $q->title ); ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <?php esc_html_e( 'Name', 'markdown-master' ); ?>
            <input type="text" name="user_name" value="<?php echo esc_attr( $filter_args['user_name'] ); ?>">
        </label>

        <label>
            <?php esc_html_e( 'Email', 'markdown-master' ); ?>
            <input type="text" name="user_email" value="<?php echo esc_attr( $filter_args['user_email'] ); ?>">
        </label>

        <label>
            <?php esc_html_e( 'Class', 'markdown-master' ); ?>
            <input type="text" name="user_class" value="<?php echo esc_attr( $filter_args['user_class'] ); ?>">
        </label>

        <label>
            <?php esc_html_e( 'Section', 'markdown-master' ); ?>
            <input type="text" name="user_section" value="<?php echo esc_attr( $filter_args['user_section'] ); ?>">
        </label>

        <label>
            <?php esc_html_e( 'Min Score', 'markdown-master' ); ?>
            <input type="number" step="0.01" name="min_score" value="<?php echo esc_attr( $filter_args['min_score'] ); ?>">
        </label>

        <label>
            <?php esc_html_e( 'Max Score', 'markdown-master' ); ?>
            <input type="number" step="0.01" name="max_score" value="<?php echo esc_attr( $filter_args['max_score'] ); ?>">
        </label>

        <label>
            <?php esc_html_e( 'Order By', 'markdown-master' ); ?>
            <select name="order_by">
                <option value="completed_at" <?php selected( $filter_args['order_by'], 'completed_at' ); ?>><?php esc_html_e( 'Completed At', 'markdown-master' ); ?></option>
                <option value="score" <?php selected( $filter_args['order_by'], 'score' ); ?>><?php esc_html_e( 'Score', 'markdown-master' ); ?></option>
            </select>
        </label>

        <label>
            <?php esc_html_e( 'Order', 'markdown-master' ); ?>
            <select name="order">
                <option value="DESC" <?php selected( $filter_args['order'], 'DESC' ); ?>><?php esc_html_e( 'DESC', 'markdown-master' ); ?></option>
                <option value="ASC" <?php selected( $filter_args['order'], 'ASC' ); ?>><?php esc_html_e( 'ASC', 'markdown-master' ); ?></option>
            </select>
        </label>

        <button class="button" type="submit"><?php esc_html_e( 'Filter', 'markdown-master' ); ?></button>
    </form>

    <?php if ( empty( $attempts ) ): ?>
        <p><?php esc_html_e( 'No attempts found for the selected filters.', 'markdown-master' ); ?></p>
    <?php else: ?>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Attempt ID', 'markdown-master' ); ?></th>
                    <th><?php esc_html_e( 'Quiz', 'markdown-master' ); ?></th>
                    <th><?php esc_html_e( 'User', 'markdown-master' ); ?></th>
                    <th><?php esc_html_e( 'Class / Section', 'markdown-master' ); ?></th>
                    <th><?php esc_html_e( 'Score', 'markdown-master' ); ?></th>
                    <th><?php esc_html_e( 'Started', 'markdown-master' ); ?></th>
                    <th><?php esc_html_e( 'Completed', 'markdown-master' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'markdown-master' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $attempts as $a ): 
                    // attempt->quiz_id may be available; fetch quiz title
                    $quiz_obj = $quiz_model->get_quiz( $a->quiz_id );
                    $quiz_title = $quiz_obj ? $quiz_obj->title : esc_html__( 'Unknown Quiz', 'markdown-master' );
                ?>
                    <tr>
                        <td><?php echo intval( $a->id ); ?></td>
                        <td><?php echo esc_html( $quiz_title ); ?></td>
                        <td><?php echo esc_html( $a->user_name ) . '<br><small>' . esc_html( $a->user_email ) . '</small>'; ?></td>
                        <td><?php echo esc_html( $a->user_class ) . ' / ' . esc_html( $a->user_section ); ?></td>
                        <td><?php echo esc_html( number_format( floatval( $a->score ), 2 ) ); ?></td>
                        <td><?php echo esc_html( $a->started_at ); ?></td>
                        <td><?php echo esc_html( $a->completed_at ); ?></td>
                        <td>
                            <a class="button" href="<?php echo add_query_arg( array( 'page' => 'mm_results', 'view_attempt' => intval( $a->id ), '_wpnonce' => wp_create_nonce( 'mm_view_attempt_' . intval( $a->id ) ) ), admin_url( 'admin.php' ) ); ?>"><?php esc_html_e( 'View', 'markdown-master' ); ?></a>

                            <?php if ( $a->quiz_id ): ?>
                                <?php $export_nonce = wp_create_nonce( 'mm_export_results_' . intval( $a->quiz_id ) ); ?>
                                <a class="button" href="<?php echo add_query_arg( array( 'page' => 'mm_results', 'mm_action' => 'export_results', 'quiz_id' => intval( $a->quiz_id ), 'mm_export_nonce' => $export_nonce ), admin_url( 'admin.php' ) ); ?>"><?php esc_html_e( 'Export Quiz Results', 'markdown-master' ); ?></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php
        // Pagination links
        $base_url = remove_query_arg( array( 'paged', '_wpnonce' ) );
        $page_links = paginate_links( array(
            'base' => add_query_arg( 'paged', '%#%' ),
            'format' => '',
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
            'total' => $total_pages,
            'current' => $paged,
        ) );
        if ( $page_links ) {
            echo '<div class="tablenav"><div class="tablenav-pages">' . $page_links . '</div></div>';
        }
        ?>

    <?php endif; ?>

    <!-- View single attempt (modal-like) -->
    <?php
    if ( isset( $_GET['view_attempt'] ) ) {
        $view_id = intval( $_GET['view_attempt'] );
        if ( check_admin_referer( 'mm_view_attempt_' . $view_id ) ) {
            $attempt = $results->get_attempt( $view_id );
            if ( $attempt ) {
                $per_question = $results->get_results_for_attempt( $view_id );
                echo '<h2>' . sprintf( esc_html__( 'Attempt #%d - %s', 'markdown-master' ), $attempt->id, esc_html( $attempt->user_name ) ) . '</h2>';
                echo '<p>' . esc_html__( 'Score:', 'markdown-master' ) . ' ' . esc_html( $attempt->score ) . '</p>';
                echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Question', 'markdown-master' ) . '</th><th>' . esc_html__( 'Answer Given', 'markdown-master' ) . '</th><th>' . esc_html__( 'Correct', 'markdown-master' ) . '</th></tr></thead><tbody>';
                foreach ( $per_question as $pq ) {
                    $given = is_serialized( $pq->given_answer ) ? maybe_unserialize( $pq->given_answer ) : $pq->given_answer;
                    echo '<tr>';
                    echo '<td>' . esc_html( $pq->question ?? '' ) . '</td>';
                    echo '<td><pre style="white-space:pre-wrap;">' . esc_html( is_array( $given ) ? json_encode( $given ) : $given ) . '</pre></td>';
                    echo '<td>' . ( intval( $pq->is_correct ) ? '<span style="color:green;">&#10004;</span>' : '<span style="color:red;">&#10008;</span>' ) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<p>' . esc_html__( 'Attempt not found', 'markdown-master' ) . '</p>';
            }
        }
    }
    ?>
</div>
