<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin class for Markdown Master - Quizzes & Questions manager
 *
 * Place this file at: markdown-master/includes/class-mm-admin.php
 *
 * Provides:
 * - Admin menu (Dashboard, Quizzes, Results, Settings)
 * - Quizzes list (WP_List_Table)
 * - Quiz create/edit form
 * - Questions manager (AJAX add/edit/delete, CSV import)
 * - Export hooks (uses MM_Quiz and MM_Import_Export if available)
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

if ( ! class_exists( 'MM_Quiz' ) ) {
    // Ensure model is loaded
    if ( file_exists( MM_INCLUDES . 'class-mm-quiz.php' ) ) {
        require_once MM_INCLUDES . 'class-mm-quiz.php';
    }
}

class MM_Admin_Quizzes_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'mm_quiz',
            'plural'   => 'mm_quizzes',
            'ajax'     => false
        ] );
    }

    public function get_columns() {
        return [
            'cb'          => '<input type="checkbox" />',
            'id'          => __( 'ID', 'markdown-master' ),
            'title'       => __( 'Title', 'markdown-master' ),
            'description' => __( 'Description', 'markdown-master' ),
            'questions'   => __( 'Questions', 'markdown-master' ),
            'attempts'    => __( 'Attempts', 'markdown-master' ),
            'actions'     => __( 'Actions', 'markdown-master' ),
        ];
    }

    public function column_cb( $item ) {
        return sprintf( '<input type="checkbox" name="bulk[]" value="%d" />', $item['id'] );
    }

    protected function column_default( $item, $column_name ) {
        return isset( $item[ $column_name ] ) ? $item[ $column_name ] : '';
    }

    public function prepare_items() {
        global $wpdb;
        $quiz_table     = $wpdb->prefix . 'mm_quizzes';
        $questions_table = $wpdb->prefix . 'mm_questions';
        $attempts_table  = $wpdb->prefix . 'mm_attempts';

        $rows = $wpdb->get_results( "SELECT * FROM {$quiz_table} ORDER BY id DESC", ARRAY_A );
        $data = [];

        if ( $rows ) {
            foreach ( $rows as $row ) {
                $qcount = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$questions_table} WHERE quiz_id = %d", $row['id'] ) );
                $acount = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$attempts_table} WHERE quiz_id = %d", $row['id'] ) );

                $actions = sprintf(
                    '<a href="%s">%s</a> | <a href="%s">%s</a> | <a href="%s">%s</a> | <a href="%s" onclick="return confirm(\'%s\')">%s</a>',
                    esc_url( add_query_arg( [ 'page' => 'mm_quizzes', 'action' => 'edit', 'id' => $row['id'] ], admin_url( 'admin.php' ) ) ),
                    esc_html__( 'Edit', 'markdown-master' ),
                    esc_url( add_query_arg( [ 'page' => 'mm_quizzes', 'action' => 'manage', 'id' => $row['id'] ], admin_url( 'admin.php' ) ) ),
                    esc_html__( 'Manage Questions', 'markdown-master' ),
                    esc_url( add_query_arg( [ 'page' => 'mm_quizzes', 'action' => 'export', 'id' => $row['id'] ], admin_url( 'admin.php' ) ) ),
                    esc_html__( 'Export', 'markdown-master' ),
                    esc_url( wp_nonce_url( add_query_arg( [ 'page' => 'mm_quizzes', 'action' => 'delete', 'id' => $row['id'] ], admin_url( 'admin.php' ) ), 'mm_delete_quiz_' . $row['id'] ) ),
                    esc_html__( 'Are you sure you want to delete this quiz?', 'markdown-master' ),
                    esc_html__( 'Delete', 'markdown-master' )
                );

                $data[] = [
                    'id'          => intval( $row['id'] ),
                    'title'       => esc_html( $row['title'] ),
                    'description' => esc_html( wp_trim_words( $row['description'], 15 ) ),
                    'questions'   => $qcount,
                    'attempts'    => $acount,
                    'actions'     => $actions,
                ];
            }
        }

        $this->items = $data;
        $this->_column_headers = [ $this->get_columns(), [], [] ];
    }
}

class MM_Admin_Results_Table extends WP_List_Table {

    protected $quiz_id = 0;

    public function __construct( $quiz_id = 0 ) {
        parent::__construct( [
            'singular' => 'mm_attempt',
            'plural'   => 'mm_attempts',
            'ajax'     => false
        ] );
        $this->quiz_id = intval( $quiz_id );
    }

    public function get_columns() {
        return [
            'cb'            => '<input type="checkbox" />',
            'student_name'  => __( 'Student', 'markdown-master' ),
            'student_roll'  => __( 'Roll No', 'markdown-master' ),
            'student_class' => __( 'Class', 'markdown-master' ),
            'student_section'=> __( 'Section', 'markdown-master' ),
            'student_school' => __( 'School', 'markdown-master' ),
            'obtained_marks' => __( 'Obtained', 'markdown-master' ),
            'total_marks'    => __( 'Total', 'markdown-master' ),
            'created_at'     => __( 'Date', 'markdown-master' ),
            'actions'        => __( 'Actions', 'markdown-master' ),
        ];
    }

    public function column_cb( $item ) {
        return sprintf( '<input type="checkbox" name="bulk_attempts[]" value="%d" />', $item['id'] );
    }

    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'student_name':
                return esc_html( $item['student_name'] );
            case 'student_roll':
                return esc_html( $item['student_roll'] );
            case 'student_class':
                return esc_html( $item['student_class'] );
            case 'student_section':
                return esc_html( $item['student_section'] );
            case 'student_school':
                return esc_html( $item['student_school'] );
            case 'obtained_marks':
                return esc_html( $item['obtained_marks'] );
            case 'total_marks':
                return esc_html( $item['total_marks'] );
            case 'created_at':
                return esc_html( $item['created_at'] );
            case 'actions':
                $pdf_url = wp_nonce_url( add_query_arg( [ 'page' => 'mm_results', 'action' => 'pdf', 'attempt_id' => $item['id'] ], admin_url( 'admin.php' ) ), 'mm_pdf_' . $item['id'] );
                $view_url = esc_url( add_query_arg( [ 'page' => 'mm_results', 'action' => 'view', 'attempt_id' => $item['id'] ], admin_url( 'admin.php' ) ) );
                return sprintf( '<a href="%s" target="_blank">%s</a> | <a href="%s">%s</a>',
                    esc_url( $pdf_url ), esc_html__( 'Export PDF', 'markdown-master' ),
                    esc_url( $view_url ), esc_html__( 'View', 'markdown-master' )
                );
            default:
                return '';
        }
    }

    public function prepare_items() {
        global $wpdb;
        $attempts_table = $wpdb->prefix . 'mm_attempts';

        // Pagination
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ( $current_page - 1 ) * $per_page;

        if ( $this->quiz_id <= 0 ) {
            $this->items = [];
            $this->set_pagination_args( [ 'total_items' => 0, 'per_page' => $per_page ] );
            return;
        }

        $total_items = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$attempts_table} WHERE quiz_id = %d", $this->quiz_id ) );
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$attempts_table} WHERE quiz_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d", $this->quiz_id, $per_page, $offset ) );

        $this->items = $rows;
        $this->set_pagination_args( [ 'total_items' => $total_items, 'per_page' => $per_page ] );
    }
}

/**
 * Main Admin class
 */
class MM_Admin {

    protected $model;

    public function __construct() {
        if ( ! class_exists( 'MM_Quiz' ) ) {
            if ( file_exists( MM_INCLUDES . 'class-mm-quiz.php' ) ) {
                require_once MM_INCLUDES . 'class-mm-quiz.php';
            } else {
                // Model missing — admin pages should show error
            }
        }
        $this->model = new MM_Quiz();

        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_init', [ $this, 'maybe_process_import' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // AJAX actions for Questions manager (admin only)
        add_action( 'wp_ajax_mm_get_questions', [ $this, 'ajax_get_questions' ] );
        add_action( 'wp_ajax_mm_add_question', [ $this, 'ajax_add_question' ] );
        add_action( 'wp_ajax_mm_update_question', [ $this, 'ajax_update_question' ] );
        add_action( 'wp_ajax_mm_delete_question', [ $this, 'ajax_delete_question' ] );

        // Admin post handlers
        add_action( 'admin_post_mm_save_quiz', [ $this, 'handle_save_quiz' ] );
        add_action( 'admin_post_mm_delete_quiz', [ $this, 'handle_delete_quiz' ] );
        add_action( 'admin_post_mm_import_questions', [ $this, 'handle_import_questions' ] );
    }

    public function register_menu() {
        add_menu_page(
            __( 'Markdown Master', 'markdown-master' ),
            __( 'Markdown Master', 'markdown-master' ),
            'manage_options',
            'markdown-master',
            [ $this, 'render_dashboard' ],
            'dashicons-welcome-learn-more',
            26
        );

        add_submenu_page(
            'markdown-master',
            __( 'Quizzes', 'markdown-master' ),
            __( 'Quizzes', 'markdown-master' ),
            'manage_options',
            'mm_quizzes',
            [ $this, 'render_quizzes_page' ]
        );

        add_submenu_page(
            'markdown-master',
            __( 'Results', 'markdown-master' ),
            __( 'Results', 'markdown-master' ),
            'manage_options',
            'mm_results',
            [ $this, 'render_results_page' ]
        );

        add_submenu_page(
            'markdown-master',
            __( 'Settings', 'markdown-master' ),
            __( 'Settings', 'markdown-master' ),
            'manage_options',
            'mm_settings',
            [ $this, 'render_settings_page' ]
        );
    }

    public function enqueue_assets( $hook ) {
        // Only load on our plugin pages
        $allowed_pages = array( 'toplevel_page_markdown-master', 'admin.php' );
        $screen = get_current_screen();
        $is_our_page = false;

        // Basic detection: plugin pages use admin.php?page=...
        if ( isset( $_GET['page'] ) && in_array( $_GET['page'], array( 'mm_quizzes', 'mm_results', 'mm_settings', 'markdown-master' ), true ) ) {
            $is_our_page = true;
        }

        if ( ! $is_our_page ) {
            return;
        }

        // CSS
        wp_enqueue_style( 'mm-admin-css', MM_PLUGIN_URL . 'assets/admin/css/mm-admin.css', array(), MM_VERSION );

        // JS (depends on jQuery)
        wp_enqueue_script( 'mm-admin-js', MM_PLUGIN_URL . 'assets/admin/js/mm-admin.js', array( 'jquery' ), MM_VERSION, true );

        // Localize
        wp_localize_script( 'mm-admin-js', 'MM_Admin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'mm_admin_nonce' ),
            'strings'  => array(
                'confirm_delete_question' => __( 'Delete this question? This cannot be undone.', 'markdown-master' ),
                'add_question'            => __( 'Add Question', 'markdown-master' ),
                'update_question'         => __( 'Update Question', 'markdown-master' ),
            ),
        ) );
    }

    /* -------------------------
     * Pages
     * ------------------------ */

    public function render_dashboard() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Markdown Master Dashboard', 'markdown-master' ); ?></h1>
            <p><?php esc_html_e( 'Manage quizzes, questions, results and settings.', 'markdown-master' ); ?></p>
            <p>
                <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=mm_quizzes' ) ); ?>"><?php esc_html_e( 'Manage Quizzes', 'markdown-master' ); ?></a>
                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=mm_results' ) ); ?>"><?php esc_html_e( 'View Results', 'markdown-master' ); ?></a>
                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=mm_settings' ) ); ?>"><?php esc_html_e( 'Settings', 'markdown-master' ); ?></a>
            </p>
        </div>
        <?php
    }

    public function render_quizzes_page() {
        $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';
        $id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

        if ( $action === 'edit' && $id ) {
            $this->render_quiz_form( $id );
            return;
        }
        if ( $action === 'new' ) {
            $this->render_quiz_form();
            return;
        }
        if ( $action === 'manage' && $id ) {
            $this->render_manage_questions( $id );
            return;
        }
        if ( $action === 'export' && $id ) {
            // Trigger export using Import/Export class if exists
            if ( file_exists( MM_INCLUDES . 'class-mm-import-export.php' ) ) {
                require_once MM_INCLUDES . 'class-mm-import-export.php';
                $ie = new MM_Import_Export();
                $path = $ie->export_quiz_to_csv( $id );
                if ( ! is_wp_error( $path ) ) {
                    // Stream file
                    header( 'Content-Type: text/csv' );
                    header( 'Content-Disposition: attachment; filename="' . basename( $path ) . '"' );
                    readfile( $path );
                    exit;
                } else {
                    echo '<div class="notice notice-error"><p>' . esc_html( $path->get_error_message() ) . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html__( 'Export support not installed (missing import-export class).', 'markdown-master' ) . '</p></div>';
            }
        }

        // Default: list view
        echo '<div class="wrap"><h1>' . esc_html__( 'Quizzes', 'markdown-master' ) . ' <a href="' . esc_url( add_query_arg( [ 'page' => 'mm_quizzes', 'action' => 'new' ], admin_url( 'admin.php' ) ) ) . '" class="page-title-action">' . esc_html__( 'Add New', 'markdown-master' ) . '</a></h1>';
        $table = new MM_Admin_Quizzes_Table();
        $table->prepare_items();
        $table->display();
        echo '</div>';
    }

    public function render_quiz_form( $id = 0 ) {
        $quiz = array(
            'id' => 0,
            'title' => '',
            'description' => '',
            'shuffle' => 0,
            'time_limit' => 0,
            'attempts_allowed' => 0,
            'show_answers' => 0,
        );
        if ( $id > 0 ) {
            $q = $this->model->get_quiz( $id );
            if ( $q ) {
                $quiz = wp_parse_args( $q, $quiz );
                // ensure scalar fields exist
                $quiz['shuffle'] = isset( $quiz['shuffle'] ) ? (int) $quiz['shuffle'] : 0;
                $quiz['time_limit'] = isset( $quiz['time_limit'] ) ? (int) $quiz['time_limit'] : 0;
                $quiz['attempts_allowed'] = isset( $quiz['attempts_allowed'] ) ? (int) $quiz['attempts_allowed'] : 0;
                $quiz['show_answers'] = isset( $quiz['show_answers'] ) ? (int) $quiz['show_answers'] : 0;
            }
        }
        ?>
        <div class="wrap">
            <h1><?php echo $id ? esc_html__( 'Edit Quiz', 'markdown-master' ) : esc_html__( 'Create New Quiz', 'markdown-master' ); ?></h1>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'mm_save_quiz_nonce', 'mm_save_quiz_nonce_field' ); ?>
                <input type="hidden" name="action" value="mm_save_quiz">
                <input type="hidden" name="id" value="<?php echo esc_attr( $quiz['id'] ); ?>"/>

                <table class="form-table">
                    <tr>
                        <th><label for="title"><?php esc_html_e( 'Title', 'markdown-master' ); ?></label></th>
                        <td><input type="text" id="title" name="title" value="<?php echo esc_attr( $quiz['title'] ); ?>" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="description"><?php esc_html_e( 'Description', 'markdown-master' ); ?></label></th>
                        <td><textarea id="description" name="description" rows="4" class="large-text"><?php echo esc_textarea( $quiz['description'] ); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Shuffle Questions', 'markdown-master' ); ?></th>
                        <td><input type="checkbox" name="shuffle" value="1" <?php checked( 1, intval( $quiz['shuffle'] ), true ); ?>></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Time Limit (minutes)', 'markdown-master' ); ?></th>
                        <td><input type="number" name="time_limit" value="<?php echo esc_attr( intval( $quiz['time_limit'] ) ); ?>" min="0"></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Attempts Allowed', 'markdown-master' ); ?></th>
                        <td><input type="number" name="attempts_allowed" value="<?php echo esc_attr( intval( $quiz['attempts_allowed'] ) ); ?>" min="0"></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Show Answers', 'markdown-master' ); ?></th>
                        <td><input type="checkbox" name="show_answers" value="1" <?php checked( 1, intval( $quiz['show_answers'] ), true ); ?>></td>
                    </tr>
                </table>

                <?php submit_button( $id ? __( 'Update Quiz', 'markdown-master' ) : __( 'Create Quiz', 'markdown-master' ) ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Questions manager UI for a quiz.
     */
    public function render_manage_questions( $quiz_id ) {
        $quiz_id = (int) $quiz_id;
        $quiz = $this->model->get_quiz( $quiz_id );
        if ( ! $quiz ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Quiz not found.', 'markdown-master' ) . '</p></div>';
            return;
        }

        // server-side fetch as fallback (JS will refresh)
        $questions = $this->model->get_questions( $quiz_id );
        ?>
        <div class="wrap mm-questions-wrap">
            <h1><?php echo sprintf( esc_html__( 'Manage Questions: %s', 'markdown-master' ), esc_html( $quiz['title'] ) ); ?></h1>

            <p>
                <a class="button button-primary mm-add-question-btn" href="#"><?php esc_html_e( 'Add Question', 'markdown-master' ); ?></a>
                <a class="button" href="<?php echo esc_url( add_query_arg( [ 'page' => 'mm_quizzes' ], admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Back to Quizzes', 'markdown-master' ); ?></a>
            </p>

            <h2><?php esc_html_e( 'Import Questions (CSV)', 'markdown-master' ); ?></h2>
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'mm_import_questions_nonce', 'mm_import_questions_nonce_field' ); ?>
                <input type="hidden" name="action" value="mm_import_questions">
                <input type="hidden" name="quiz_id" value="<?php echo esc_attr( $quiz_id ); ?>">
                <input type="file" name="mm_import_file" accept=".csv" required>
                <input type="submit" class="button" value="<?php esc_attr_e( 'Import CSV', 'markdown-master' ); ?>">
                <p class="description"><?php esc_html_e( 'CSV columns: question_text,type,option_1,option_2,...,correct_option_index (1-based).', 'markdown-master' ); ?></p>
            </form>

            <hr>

            <table class="widefat striped" id="mm-questions-table" data-quiz-id="<?php echo esc_attr( $quiz_id ); ?>">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ID', 'markdown-master' ); ?></th>
                        <th><?php esc_html_e( 'Question', 'markdown-master' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'markdown-master' ); ?></th>
                        <th><?php esc_html_e( 'Options', 'markdown-master' ); ?></th>
                        <th><?php esc_html_e( 'Points', 'markdown-master' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'markdown-master' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $questions ) ) : ?>
                        <?php foreach ( $questions as $q ) : ?>
                            <tr data-qid="<?php echo esc_attr( $q['id'] ); ?>">
                                <td><?php echo esc_html( $q['id'] ); ?></td>
                                <td><?php echo wp_kses_post( wp_trim_words( $q['question_text'], 25 ) ); ?></td>
                                <td><?php echo esc_html( $q['type'] ); ?></td>
                                <td>
                                    <?php
                                    $opts = $q['options'];
                                    if ( is_array( $opts ) ) {
                                        echo esc_html( implode( ' | ', array_map( 'trim', $opts ) ) );
                                    } else {
                                        echo esc_html( (string) $opts );
                                    }
                                    ?>
                                </td>
                                <td><?php echo esc_html( intval( $q['points'] ) ); ?></td>
                                <td>
                                    <a href="#" class="mm-edit-question" data-qid="<?php echo esc_attr( $q['id'] ); ?>"><?php esc_html_e( 'Edit', 'markdown-master' ); ?></a>
                                    |
                                    <a href="#" class="mm-delete-question" data-qid="<?php echo esc_attr( $q['id'] ); ?>"><?php esc_html_e( 'Delete', 'markdown-master' ); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="6"><?php esc_html_e( 'No questions yet. Click "Add Question" to create one.', 'markdown-master' ); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Modal: Add / Edit Question -->
        <div id="mm-question-modal" class="mm-modal" aria-hidden="true" style="display:none;">
            <div class="mm-modal-inner">
                <h2 id="mm-modal-title"><?php esc_html_e( 'Add Question', 'markdown-master' ); ?></h2>
                <form id="mm-question-form">
                    <?php wp_nonce_field( 'mm_save_question_nonce', 'mm_save_question_nonce_field' ); ?>
                    <input type="hidden" name="action" value="mm_add_question">
                    <input type="hidden" name="quiz_id" value="<?php echo esc_attr( $quiz_id ); ?>">
                    <input type="hidden" name="question_id" value="">
                    <table class="form-table">
                        <tr>
                            <th><label><?php esc_html_e( 'Question Text', 'markdown-master' ); ?></label></th>
                            <td><textarea name="question_text" rows="3" class="large-text" required></textarea></td>
                        </tr>
                        <tr>
                            <th><label><?php esc_html_e( 'Type', 'markdown-master' ); ?></label></th>
                            <td>
                                <select name="type">
                                    <option value="single"><?php esc_html_e( 'Single choice', 'markdown-master' ); ?></option>
                                    <option value="multiple"><?php esc_html_e( 'Multiple choice', 'markdown-master' ); ?></option>
                                    <option value="text"><?php esc_html_e( 'Text (short)', 'markdown-master' ); ?></option>
                                    <option value="textarea"><?php esc_html_e( 'Text (long)', 'markdown-master' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr class="mm-options-row">
                            <th><label><?php esc_html_e( 'Options (one per line)', 'markdown-master' ); ?></label></th>
                            <td>
                                <div id="mm-options-wrapper">
                                    <textarea name="options_text" rows="4" class="large-text" placeholder="<?php esc_attr_e( 'Option A\nOption B\nOption C', 'markdown-master' ); ?>"></textarea>
                                    <p class="description"><?php esc_html_e( 'For single/multiple choice, provide options one per line. For text fields leave blank.', 'markdown-master' ); ?></p>
                                </div>
                            </td>
                        </tr>
                        <tr class="mm-correct-row">
                            <th><label><?php esc_html_e( 'Correct Answer(s)', 'markdown-master' ); ?></label></th>
                            <td>
                                <input type="text" name="correct_answer_text" class="regular-text" placeholder="<?php esc_attr_e( 'For single choice: the exact option text or its line number (1-based). For multiple: comma-separated indexes or exact options', 'markdown-master' ); ?>">
                                <p class="description"><?php esc_html_e( 'You may provide the correct option index (1-based) or the exact text. For multiple: separate by comma.', 'markdown-master' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label><?php esc_html_e( 'Points', 'markdown-master' ); ?></label></th>
                            <td><input type="number" name="points" value="1" min="0" class="small-text"></td>
                        </tr>
                        <tr>
                            <th><label><?php esc_html_e( 'Image (optional)', 'markdown-master' ); ?></label></th>
                            <td><input type="url" name="image" placeholder="<?php esc_attr_e( 'https://...', 'markdown-master' ); ?>" class="regular-text"></td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary" id="mm-modal-submit"><?php esc_html_e( 'Save Question', 'markdown-master' ); ?></button>
                        <button type="button" class="button" id="mm-modal-cancel"><?php esc_html_e( 'Cancel', 'markdown-master' ); ?></button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    public function render_results_page() {
        global $wpdb;
        $quiz_table = $wpdb->prefix . 'mm_quizzes';

        $quiz_id = isset( $_GET['quiz_id'] ) ? intval( $_GET['quiz_id'] ) : 0;

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Quiz Results', 'markdown-master' ); ?></h1>

            <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
                <input type="hidden" name="page" value="mm_results">
                <label for="filter_quiz"><?php esc_html_e( 'Select Quiz:', 'markdown-master' ); ?></label>
                <select id="filter_quiz" name="quiz_id">
                    <option value="0"><?php esc_html_e( '-- Select Quiz --', 'markdown-master' ); ?></option>
                    <?php
                    $quizzes = $this->model->get_quizzes( 100, 0 );
                    foreach ( $quizzes as $q ) {
                        printf( '<option value="%1$d"%2$s>%3$s</option>',
                            intval( $q['id'] ),
                            selected( $quiz_id, intval( $q['id'] ), false ),
                            esc_html( $q['title'] )
                        );
                    }
                    ?>
                </select>

                <input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'markdown-master' ); ?>">
            </form>

            <?php
            if ( $quiz_id > 0 ) {
                $table = new MM_Admin_Results_Table( $quiz_id );
                $table->prepare_items();
                $table->display();
            } else {
                echo '<p class="description">' . esc_html__( 'Select a quiz to view attempts.', 'markdown-master' ) . '</p>';
            }
            ?>
        </div>
        <?php
    }

    public function render_settings_page() {
        // We keep settings simple until Settings class is used (Phase 6)
        $opts = get_option( 'mm_settings', array() );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Markdown Master Settings', 'markdown-master' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'mm_settings_group' );
                do_settings_sections( 'mm_settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /* -------------------------
     * AJAX handlers (Questions manager)
     * ------------------------ */

    public function ajax_get_questions() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized', 403 );
        }
        check_ajax_referer( 'mm_admin_nonce', 'nonce' );

        $quiz_id = isset( $_REQUEST['quiz_id'] ) ? intval( $_REQUEST['quiz_id'] ) : 0;
        if ( $quiz_id <= 0 ) {
            wp_send_json_error( 'invalid_quiz' );
        }

        $questions = $this->model->get_questions( $quiz_id );
        wp_send_json_success( $questions );
    }

    public function ajax_add_question() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized', 403 );
        }
        check_ajax_referer( 'mm_admin_nonce', 'nonce' );

        $quiz_id = isset( $_POST['quiz_id'] ) ? intval( $_POST['quiz_id'] ) : 0;
        if ( $quiz_id <= 0 ) {
            wp_send_json_error( 'invalid_quiz' );
        }

        $qtext = isset( $_POST['question_text'] ) ? wp_kses_post( wp_unslash( $_POST['question_text'] ) ) : '';
        $type  = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : 'single';
        $points = isset( $_POST['points'] ) ? intval( $_POST['points'] ) : 1;
        $image = isset( $_POST['image'] ) ? esc_url_raw( $_POST['image'] ) : null;

        // Options: either 'options_text' (lines) or options[] form
        $options = array();
        if ( ! empty( $_POST['options_text'] ) ) {
            $raw = wp_unslash( $_POST['options_text'] );
            $lines = preg_split( "/\r\n|\n|\r/", $raw );
            foreach ( $lines as $ln ) {
                $ln = trim( $ln );
                if ( $ln !== '' ) $options[] = wp_kses_post( $ln );
            }
        } elseif ( isset( $_POST['options'] ) && is_array( $_POST['options'] ) ) {
            foreach ( $_POST['options'] as $o ) {
                $o = trim( wp_unslash( $o ) );
                if ( $o !== '' ) $options[] = wp_kses_post( $o );
            }
        }

        // Correct answer value — accept either indexes (1-based) or exact text
        $correct_raw = isset( $_POST['correct_answer_text'] ) ? trim( wp_unslash( $_POST['correct_answer_text'] ) ) : '';
        $correct = null;
        if ( $correct_raw !== '' ) {
            // if numeric indexes provided
            if ( preg_match( '/^[\d,\s]+$/', $correct_raw ) ) {
                $parts = array_filter( array_map( 'trim', explode( ',', $correct_raw ) ) );
                $vals = array();
                foreach ( $parts as $p ) {
                    $idx = intval( $p );
                    if ( $idx > 0 && isset( $options[ $idx - 1 ] ) ) {
                        $vals[] = $options[ $idx - 1 ];
                    }
                }
                $correct = ( count( $vals ) === 1 ) ? $vals[0] : $vals;
            } else {
                // match by exact option text(s)
                $parts = array_filter( array_map( 'trim', explode( ',', $correct_raw ) ) );
                if ( count( $parts ) === 1 ) {
                    $correct = $parts[0];
                } else {
                    $correct = $parts;
                }
            }
        }

        $question_data = array(
            'question_text'  => $qtext,
            'type'           => $type,
            'options'        => $options,
            'correct_answer' => $correct,
            'points'         => $points,
            'image'          => $image,
        );

        $qid = $this->model->add_question( $quiz_id, $question_data );
        if ( ! $qid ) {
            wp_send_json_error( 'insert_failed' );
        }

        $newq = $this->model->get_questions( $quiz_id );
        // return single question data
        $single = null;
        foreach ( $newq as $q ) {
            if ( intval( $q['id'] ) === intval( $qid ) ) {
                $single = $q;
                break;
            }
        }

        wp_send_json_success( $single );
    }

    public function ajax_update_question() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized', 403 );
        }
        check_ajax_referer( 'mm_admin_nonce', 'nonce' );

        $question_id = isset( $_POST['question_id'] ) ? intval( $_POST['question_id'] ) : 0;
        if ( $question_id <= 0 ) {
            wp_send_json_error( 'invalid_question' );
        }

        $qtext = isset( $_POST['question_text'] ) ? wp_kses_post( wp_unslash( $_POST['question_text'] ) ) : '';
        $type  = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : 'single';
        $points = isset( $_POST['points'] ) ? intval( $_POST['points'] ) : 1;
        $image = isset( $_POST['image'] ) ? esc_url_raw( $_POST['image'] ) : null;

        $options = array();
        if ( ! empty( $_POST['options_text'] ) ) {
            $raw = wp_unslash( $_POST['options_text'] );
            $lines = preg_split( "/\r\n|\n|\r/", $raw );
            foreach ( $lines as $ln ) {
                $ln = trim( $ln );
                if ( $ln !== '' ) $options[] = wp_kses_post( $ln );
            }
        } elseif ( isset( $_POST['options'] ) && is_array( $_POST['options'] ) ) {
            foreach ( $_POST['options'] as $o ) {
                $o = trim( wp_unslash( $o ) );
                if ( $o !== '' ) $options[] = wp_kses_post( $o );
            }
        }

        $correct_raw = isset( $_POST['correct_answer_text'] ) ? trim( wp_unslash( $_POST['correct_answer_text'] ) ) : '';
        $correct = null;
        if ( $correct_raw !== '' ) {
            if ( preg_match( '/^[\d,\s]+$/', $correct_raw ) ) {
                $parts = array_filter( array_map( 'trim', explode( ',', $correct_raw ) ) );
                $vals = array();
                foreach ( $parts as $p ) {
                    $idx = intval( $p );
                    if ( $idx > 0 && isset( $options[ $idx - 1 ] ) ) {
                        $vals[] = $options[ $idx - 1 ];
                    }
                }
                $correct = ( count( $vals ) === 1 ) ? $vals[0] : $vals;
            } else {
                $parts = array_filter( array_map( 'trim', explode( ',', $correct_raw ) ) );
                $correct = ( count( $parts ) === 1 ) ? $parts[0] : $parts;
            }
        }

        $data = array(
            'question_text'  => $qtext,
            'type'           => $type,
            'options'        => $options,
            'correct_answer' => $correct,
            'points'         => $points,
            'image'          => $image,
        );

        $ok = $this->model->update_question( $question_id, $data );
        if ( ! $ok ) {
            wp_send_json_error( 'update_failed' );
        }

        $quiz_row = $this->db_get_quiz_id_by_question( $question_id );
        $quiz_id = $quiz_row ? intval( $quiz_row ) : 0;
        $updated = $this->model->get_questions( $quiz_id );
        $single = null;
        foreach ( $updated as $q ) {
            if ( intval( $q['id'] ) === $question_id ) {
                $single = $q;
                break;
            }
        }
        wp_send_json_success( $single );
    }

    protected function db_get_quiz_id_by_question( $question_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mm_questions';
        return $wpdb->get_var( $wpdb->prepare( "SELECT quiz_id FROM {$table} WHERE id = %d", $question_id ) );
    }

    public function ajax_delete_question() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized', 403 );
        }
        check_ajax_referer( 'mm_admin_nonce', 'nonce' );

        $question_id = isset( $_POST['question_id'] ) ? intval( $_POST['question_id'] ) : 0;
        if ( $question_id <= 0 ) {
            wp_send_json_error( 'invalid_question' );
        }
        $ok = $this->model->delete_question( $question_id );
        if ( ! $ok ) {
            wp_send_json_error( 'delete_failed' );
        }
        wp_send_json_success();
    }

    /* -------------------------
     * Admin Post handlers (quiz save/delete, CSV import)
     * ------------------------ */

    public function handle_save_quiz() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'markdown-master' ) );
        }
        check_admin_referer( 'mm_save_quiz_nonce', 'mm_save_quiz_nonce_field' );

        $id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
        $title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $description = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';
        $shuffle = isset( $_POST['shuffle'] ) ? 1 : 0;
        $time_limit = isset( $_POST['time_limit'] ) ? intval( $_POST['time_limit'] ) : 0;
        $attempts_allowed = isset( $_POST['attempts_allowed'] ) ? intval( $_POST['attempts_allowed'] ) : 0;
        $show_answers = isset( $_POST['show_answers'] ) ? 1 : 0;

        $settings = array(
            'shuffle' => $shuffle,
            'time_limit' => $time_limit,
            'attempts_allowed' => $attempts_allowed,
            'show_answers' => $show_answers,
        );

        if ( $id > 0 ) {
            $this->model->update_quiz( $id, array(
                'title' => $title,
                'description' => $description,
                'settings' => $settings,
                'shuffle' => $shuffle,
                'time_limit' => $time_limit,
                'attempts_allowed' => $attempts_allowed,
                'show_answers' => $show_answers,
            ) );
        } else {
            $this->model->create_quiz( array(
                'title' => $title,
                'description' => $description,
                'settings' => $settings,
                'shuffle' => $shuffle,
                'time_limit' => $time_limit,
                'attempts_allowed' => $attempts_allowed,
                'show_answers' => $show_answers,
            ) );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=mm_quizzes&mm_msg=' . urlencode( __( 'Quiz saved.', 'markdown-master' ) ) ) );
        exit;
    }

    public function handle_delete_quiz() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'markdown-master' ) );
        }
        $id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
        if ( $id <= 0 ) {
            wp_safe_redirect( admin_url( 'admin.php?page=mm_quizzes' ) );
            exit;
        }
        if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'mm_delete_quiz_' . $id ) ) {
            wp_die( __( 'Nonce verification failed.', 'markdown-master' ) );
        }

        $this->model->delete_quiz( $id );

        wp_safe_redirect( admin_url( 'admin.php?page=mm_quizzes&mm_msg=' . urlencode( __( 'Quiz deleted.', 'markdown-master' ) ) ) );
        exit;
    }

    /**
     * Handle CSV import posted from the manage questions UI.
     * Expect: quiz_id, file (CSV)
     */
    public function handle_import_questions() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'markdown-master' ) );
        }
        check_admin_referer( 'mm_import_questions_nonce', 'mm_import_questions_nonce_field' );

        $quiz_id = isset( $_POST['quiz_id'] ) ? intval( $_POST['quiz_id'] ) : 0;
        if ( $quiz_id <= 0 ) {
            wp_safe_redirect( admin_url( 'admin.php?page=mm_quizzes&mm_msg=' . urlencode( __( 'Invalid quiz selected', 'markdown-master' ) ) ) );
            exit;
        }

        if ( ! isset( $_FILES['mm_import_file'] ) || empty( $_FILES['mm_import_file']['tmp_name'] ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=mm_quizzes&action=manage&id=' . $quiz_id . '&mm_msg=' . urlencode( __( 'No file uploaded', 'markdown-master' ) ) ) );
            exit;
        }

        $tmp = $_FILES['mm_import_file']['tmp_name'];
        $fh = fopen( $tmp, 'r' );
        if ( ! $fh ) {
            wp_safe_redirect( admin_url( 'admin.php?page=mm_quizzes&action=manage&id=' . $quiz_id . '&mm_msg=' . urlencode( __( 'Unable to open file', 'markdown-master' ) ) ) );
            exit;
        }

        $header = fgetcsv( $fh );
        if ( ! $header ) {
            fclose( $fh );
            wp_safe_redirect( admin_url( 'admin.php?page=mm_quizzes&action=manage&id=' . $quiz_id . '&mm_msg=' . urlencode( __( 'Invalid CSV header', 'markdown-master' ) ) ) );
            exit;
        }
        $header = array_map( 'trim', $header );

        $inserted = 0;
        while ( ( $row = fgetcsv( $fh ) ) !== false ) {
            $row = array_map( 'trim', $row );
            $data = array_combine( $header, $row );

            // Expected fields: question_text, type, option_1...option_n, correct_option_index (1-based), points(optional)
            $qtext = isset( $data['question_text'] ) ? $data['question_text'] : '';
            $type  = isset( $data['type'] ) ? $data['type'] : 'single';
            $points = isset( $data['points'] ) ? intval( $data['points'] ) : 1;

            $options = array();
            foreach ( $data as $k => $v ) {
                if ( preg_match( '/^option_\\d+$/', $k ) && $v !== '' ) {
                    $options[] = wp_kses_post( $v );
                }
            }

            $correct = null;
            if ( isset( $data['correct_option_index'] ) && $data['correct_option_index'] !== '' ) {
                $idx = intval( $data['correct_option_index'] );
                if ( $idx > 0 && isset( $options[ $idx - 1 ] ) ) {
                    $correct = $options[ $idx - 1 ];
                }
            } elseif ( isset( $data['correct_answer'] ) ) {
                $correct = $data['correct_answer'];
            }

            $qdata = array(
                'question_text' => $qtext,
                'type' => $type,
                'options' => $options,
                'correct_answer' => $correct,
                'points' => $points,
            );

            $qid = $this->model->add_question( $quiz_id, $qdata );
            if ( $qid ) $inserted++;
        }

        fclose( $fh );

        wp_safe_redirect( admin_url( 'admin.php?page=mm_quizzes&action=manage&id=' . $quiz_id . '&mm_msg=' . urlencode( sprintf( _n( '%d question imported', '%d questions imported', $inserted, 'markdown-master' ), $inserted ) ) ) );
        exit;
    }

    /**
     * Called on admin_init to process maybe immediate tasks (placeholder).
     */
    public function maybe_process_import() {
        // reserved if you want to hook imports differently
    }
}

new MM_Admin();
