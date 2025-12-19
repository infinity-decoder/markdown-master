<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin class for Cortex - Quizzes & Questions manager
 *
 * Place this file at: cortex/includes/class-cortex-admin.php
 *
 * Provides:
 * - Admin menu (Dashboard, Quizzes, Results, Settings)
 * - Quizzes list (WP_List_Table)
 * - Quiz create/edit form
 * - Questions manager (AJAX add/edit/delete, CSV import)
 * - Export hooks (uses Cortex_Quiz and Cortex_Import_Export if available)
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

if ( ! class_exists( 'Cortex_Quiz' ) ) {
    // Ensure model is loaded
    if ( file_exists( CORTEX_INCLUDES . 'class-cortex-quiz.php' ) ) {
        require_once CORTEX_INCLUDES . 'class-cortex-quiz.php';
    }
}

class Cortex_Admin_Quizzes_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'cortex_quiz',
            'plural'   => 'cortex_quizzes',
            'ajax'     => false
        ] );
    }

    public function get_columns() {
        return [
            'cb'          => '<input type="checkbox" />',
            'id'          => __( 'ID', 'cortex' ),
            'title'       => __( 'Title', 'cortex' ),
            'description' => __( 'Description', 'cortex' ),
            'questions'   => __( 'Questions', 'cortex' ),
            'attempts'    => __( 'Attempts', 'cortex' ),
            'actions'     => __( 'Actions', 'cortex' ),
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
        $quiz_table     = $wpdb->prefix . 'cortex_quizzes';
        $questions_table = $wpdb->prefix . 'cortex_questions';
        $attempts_table  = $wpdb->prefix . 'cortex_attempts';

        $rows = $wpdb->get_results( "SELECT * FROM {$quiz_table} ORDER BY id DESC", ARRAY_A );
        $data = [];

        if ( $rows ) {
            foreach ( $rows as $row ) {
                $qcount = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$questions_table} WHERE quiz_id = %d", $row['id'] ) );
                $acount = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$attempts_table} WHERE quiz_id = %d", $row['id'] ) );

                $actions = sprintf(
                    '<a href="%s">%s</a> | <a href="%s">%s</a> | <a href="%s">%s</a> | <a href="%s" onclick="return confirm(\'%s\')">%s</a>',
                    esc_url( add_query_arg( [ 'page' => 'cortex_quizzes', 'action' => 'edit', 'id' => $row['id'] ], admin_url( 'admin.php' ) ) ),
                    esc_html__( 'Edit', 'cortex' ),
                    esc_url( add_query_arg( [ 'page' => 'cortex_quizzes', 'action' => 'manage', 'id' => $row['id'] ], admin_url( 'admin.php' ) ) ),
                    esc_html__( 'Manage Questions', 'cortex' ),
                    esc_url( add_query_arg( [ 'page' => 'cortex_quizzes', 'action' => 'export', 'id' => $row['id'] ], admin_url( 'admin.php' ) ) ),
                    esc_html__( 'Export', 'cortex' ),
                    esc_url( wp_nonce_url( add_query_arg( [ 'page' => 'cortex_quizzes', 'action' => 'delete', 'id' => $row['id'] ], admin_url( 'admin.php' ) ), 'cortex_delete_quiz_' . $row['id'] ) ),
                    esc_html__( 'Are you sure you want to delete this quiz?', 'cortex' ),
                    esc_html__( 'Delete', 'cortex' )
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

class Cortex_Admin_Results_Table extends WP_List_Table {

    protected $quiz_id = 0;

    public function __construct( $quiz_id = 0 ) {
        parent::__construct( [
            'singular' => 'cortex_attempt',
            'plural'   => 'cortex_attempts',
            'ajax'     => false
        ] );
        $this->quiz_id = intval( $quiz_id );
    }

    public function get_columns() {
        return [
            'cb'            => '<input type="checkbox" />',
            'student_name'  => __( 'Student', 'cortex' ),
            'student_roll'  => __( 'Roll No', 'cortex' ),
            'student_class' => __( 'Class', 'cortex' ),
            'student_section'=> __( 'Section', 'cortex' ),
            'student_school' => __( 'School', 'cortex' ),
            'obtained_marks' => __( 'Obtained', 'cortex' ),
            'total_marks'    => __( 'Total', 'cortex' ),
            'created_at'     => __( 'Date', 'cortex' ),
            'actions'        => __( 'Actions', 'cortex' ),
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
                $pdf_url = wp_nonce_url( add_query_arg( [ 'page' => 'cortex_results', 'action' => 'pdf', 'attempt_id' => $item['id'] ], admin_url( 'admin.php' ) ), 'cortex_pdf_' . $item['id'] );
                $view_url = esc_url( add_query_arg( [ 'page' => 'cortex_results', 'action' => 'view', 'attempt_id' => $item['id'] ], admin_url( 'admin.php' ) ) );
                return sprintf( '<a href="%s" target="_blank">%s</a> | <a href="%s">%s</a>',
                    esc_url( $pdf_url ), esc_html__( 'Export PDF', 'cortex' ),
                    esc_url( $view_url ), esc_html__( 'View', 'cortex' )
                );
            default:
                return '';
        }
    }

    public function prepare_items() {
        global $wpdb;
        $attempts_table = $wpdb->prefix . 'cortex_attempts';

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
class Cortex_Admin {

    protected $model;

    public function __construct() {
        if ( ! class_exists( 'Cortex_Quiz' ) ) {
            if ( file_exists( CORTEX_INCLUDES . 'class-cortex-quiz.php' ) ) {
                require_once CORTEX_INCLUDES . 'class-cortex-quiz.php';
            }
        }
        $this->model = new Cortex_Quiz();
    }

    public function init_hooks() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_init', [ $this, 'maybe_process_import' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // AJAX actions for Questions manager (admin only)
        add_action( 'wp_ajax_cortex_get_questions', [ $this, 'ajax_get_questions' ] );
        add_action( 'wp_ajax_cortex_add_question', [ $this, 'ajax_add_question' ] );
        add_action( 'wp_ajax_cortex_update_question', [ $this, 'ajax_update_question' ] );
        add_action( 'wp_ajax_cortex_delete_question', [ $this, 'ajax_delete_question' ] );

        // Admin post handlers
        add_action( 'admin_post_cortex_save_quiz', [ $this, 'handle_save_quiz' ] );
        add_action( 'admin_post_cortex_delete_quiz', [ $this, 'handle_delete_quiz' ] );
        add_action( 'admin_post_cortex_save_markdown_snippet', [ $this, 'handle_save_markdown_snippet' ] );
        add_action( 'admin_post_cortex_delete_markdown_snippet', [ $this, 'handle_delete_markdown_snippet' ] );
        add_action( 'admin_post_cortex_save_code_snippet', [ $this, 'handle_save_code_snippet' ] );
        add_action( 'admin_post_cortex_delete_code_snippet', [ $this, 'handle_delete_code_snippet' ] );
        add_action( 'admin_post_cortex_import_questions', [ $this, 'handle_import_questions' ] );
    }

    public function register_menu() {
        add_menu_page(
            __( 'Cortex', 'cortex' ),
            __( 'Cortex', 'cortex' ),
            'manage_options',
            'cortex',
            [ $this, 'render_dashboard' ],
            'dashicons-welcome-learn-more',
            26
        );

        // Submenu: Dashboard (re-registering same slug for first item usually hides the duplicate)
        add_submenu_page(
            'cortex',
            __( 'Dashboard', 'cortex' ),
            __( 'Dashboard', 'cortex' ),
            'manage_options',
            'cortex',
            [ $this, 'render_dashboard' ]
        );

        add_submenu_page(
            'cortex',
            __( 'Quizzes', 'cortex' ),
            __( 'Quizzes', 'cortex' ),
            'manage_options',
            'cortex_quizzes',
            [ $this, 'render_quizzes_page' ]
        );

        add_submenu_page(
            'cortex',
            __( 'Results', 'cortex' ),
            __( 'Results', 'cortex' ),
            'manage_options',
            'cortex_results',
            [ $this, 'render_results_page' ]
        );

        add_submenu_page(
            'cortex',
            __( 'Question Banks', 'cortex' ),
            __( 'Question Banks', 'cortex' ),
            'manage_options',
            'cortex_question_banks',
            [ $this, 'render_question_banks_page' ]
        );

        add_submenu_page(
            'cortex',
            __( 'Markdown Snippets', 'cortex' ),
            __( 'Markdown Snippets', 'cortex' ),
            'manage_options',
            'cortex_markdown_snippets',
            [ $this, 'render_markdown_snippets_page' ]
        );

        add_submenu_page(
            'cortex',
            __( 'Code Snippets', 'cortex' ),
            __( 'Code Snippets', 'cortex' ),
            'manage_options',
            'cortex_code_snippets',
            [ $this, 'render_code_snippets_page' ]
        );

        add_submenu_page(
            'cortex',
            __( 'Lead Captures', 'cortex' ),
            __( 'Lead Captures', 'cortex' ),
            'manage_options',
            'cortex_lead_captures',
            [ $this, 'render_lead_captures_page' ]
        );

        add_submenu_page(
            'cortex',
            __( 'Settings', 'cortex' ),
            __( 'Settings', 'cortex' ),
            'manage_options',
            'cortex_settings',
            [ $this, 'render_settings_page' ]
        );
    }

    public function enqueue_assets( $hook ) {
        // Only load on our plugin pages
        $allowed_pages = array( 'toplevel_page_cortex', 'admin.php' );
        $screen = get_current_screen();
        $is_our_page = false;

        // Basic detection: plugin pages use admin.php?page=...
        if ( isset( $_GET['page'] ) && in_array( $_GET['page'], array( 'cortex_quizzes', 'cortex_results', 'cortex_settings', 'cortex' ), true ) ) {
            $is_our_page = true;
        }

        if ( ! $is_our_page ) {
            return;
        }

        // CSS
        wp_enqueue_style( 'cortex-admin-css', CORTEX_PLUGIN_URL . 'assets/css/cortex-admin.css', array(), CORTEX_VERSION );
        wp_enqueue_style( 'cortex-admin-modern', CORTEX_PLUGIN_URL . 'assets/css/cortex-admin-modern.css', array(), CORTEX_VERSION );

        // JS (depends on jQuery, Underscore, wp-util)
        wp_enqueue_script( 'cortex-admin-js', CORTEX_PLUGIN_URL . 'assets/js/cortex-admin.js', array( 'jquery', 'jquery-ui-sortable', 'underscore', 'wp-util' ), CORTEX_VERSION, true );

        // Localize
        wp_localize_script( 'cortex-admin-js', 'Cortex_Admin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'cortex_admin_nonce' ),
            'quiz_id'  => isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0,
            'strings'  => array(
                'confirm_delete_question' => __( 'Delete this question? This cannot be undone.', 'cortex' ),
                'add_question'            => __( 'Add Question', 'cortex' ),
                'update_question'         => __( 'Update Question', 'cortex' ),
                'remove_option'           => __( 'Remove Option', 'cortex' ),
                'remove_pair'             => __( 'Remove Pair', 'cortex' ),
                'saving'                  => __( 'Saving...', 'cortex' ),
                'saved'                   => __( 'Saved', 'cortex' ),
                'error'                   => __( 'Error saving', 'cortex' ),
            ),
        ) );
    }

    /* -------------------------
     * Pages
     * ------------------------ */

    public function render_dashboard() {
        if ( file_exists( CORTEX_ADMIN . 'cortex-admin-dashboard.php' ) ) {
            include CORTEX_ADMIN . 'cortex-admin-dashboard.php';
        } else {
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'Cortex Dashboard', 'cortex' ); ?></h1>
                <p><?php esc_html_e( 'Manage quizzes, questions, results and settings.', 'cortex' ); ?></p>
                <p>
                    <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=cortex_quizzes' ) ); ?>"><?php esc_html_e( 'Manage Quizzes', 'cortex' ); ?></a>
                    <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cortex_results' ) ); ?>"><?php esc_html_e( 'View Results', 'cortex' ); ?></a>
                    <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cortex_settings' ) ); ?>"><?php esc_html_e( 'Settings', 'cortex' ); ?></a>
                </p>
            </div>
            <?php
        }
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
            if ( file_exists( CORTEX_INCLUDES . 'class-cortex-import-export.php' ) ) {
                require_once CORTEX_INCLUDES . 'class-cortex-import-export.php';
                $ie = new Cortex_Import_Export();
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
                echo '<div class="notice notice-error"><p>' . esc_html__( 'Export support not installed (missing import-export class).', 'cortex' ) . '</p></div>';
            }
        }

        // Default: list view
        echo '<div class="wrap"><h1>' . esc_html__( 'Quizzes', 'cortex' ) . ' <a href="' . esc_url( add_query_arg( [ 'page' => 'cortex_quizzes', 'action' => 'new' ], admin_url( 'admin.php' ) ) ) . '" class="page-title-action">' . esc_html__( 'Add New', 'cortex' ) . '</a></h1>';
        $table = new Cortex_Admin_Quizzes_Table();
        $table->prepare_items();
        $table->display();
        echo '</div>';
    }

    public function render_quiz_form( $id = 0 ) {
        $quiz = array(
            'id' => 0,
            'title' => '',
            'description' => '',
            'randomize_questions' => 0,
            'require_login' => 0,
            'show_answers' => 0,
            'time_limit' => 0,
            'attempts_allowed' => 0,
            'enable_lead_capture' => 0,
            'lead_fields' => array(),
        );

        if ( $id > 0 ) {
            $q = $this->model->get_quiz( $id );
            if ( $q ) {
                $quiz = array_merge( $quiz, $q );
            }
        }

        include CORTEX_ADMIN . 'cortex-admin-quiz-form.php';
    }

    /**
     * Questions manager UI for a quiz.
     */
    public function render_manage_questions( $quiz_id ) {
        $quiz_id = (int) $quiz_id;
        $quiz = $this->model->get_quiz( $quiz_id );
        if ( ! $quiz ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Quiz not found.', 'cortex' ) . '</p></div>';
            return;
        }

        // server-side fetch as fallback (JS will refresh)
        $questions = $this->model->get_questions( $quiz_id );
        ?>
        <div class="wrap cortex-questions-wrap">
            <h1><?php echo sprintf( esc_html__( 'Manage Questions: %s', 'cortex' ), esc_html( $quiz['title'] ) ); ?></h1>

            <p>
                <a class="button button-primary cortex-add-question-btn" href="#"><?php esc_html_e( 'Add Question', 'cortex' ); ?></a>
                <a class="button" href="<?php echo esc_url( add_query_arg( [ 'page' => 'cortex_quizzes' ], admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Back to Quizzes', 'cortex' ); ?></a>
            </p>

            <h2><?php esc_html_e( 'Import Questions (CSV)', 'cortex' ); ?></h2>
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'cortex_import_questions_nonce', 'cortex_import_questions_nonce_field' ); ?>
                <input type="hidden" name="action" value="cortex_import_questions">
                <input type="hidden" name="quiz_id" value="<?php echo esc_attr( $quiz_id ); ?>">
                <input type="file" name="cortex_import_file" accept=".csv" required>
                <input type="submit" class="button" value="<?php esc_attr_e( 'Import CSV', 'cortex' ); ?>">
                <p class="description"><?php esc_html_e( 'CSV columns: question_text,type,option_1,option_2,...,correct_option_index (1-based).', 'cortex' ); ?></p>
            </form>

            <hr>

            <table class="widefat striped" id="cortex-questions-table" data-quiz-id="<?php echo esc_attr( $quiz_id ); ?>">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ID', 'cortex' ); ?></th>
                        <th><?php esc_html_e( 'Question', 'cortex' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'cortex' ); ?></th>
                        <th><?php esc_html_e( 'Options', 'cortex' ); ?></th>
                        <th><?php esc_html_e( 'Points', 'cortex' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'cortex' ); ?></th>
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
                                    <a href="#" class="cortex-edit-question" data-qid="<?php echo esc_attr( $q['id'] ); ?>"><?php esc_html_e( 'Edit', 'cortex' ); ?></a>
                                    |
                                    <a href="#" class="cortex-delete-question" data-qid="<?php echo esc_attr( $q['id'] ); ?>"><?php esc_html_e( 'Delete', 'cortex' ); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="6"><?php esc_html_e( 'No questions yet. Click "Add Question" to create one.', 'cortex' ); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Modal: Add / Edit Question -->
        <div id="cortex-question-modal" class="cortex-modal" aria-hidden="true" style="display:none;">
            <div class="cortex-modal-inner">
                <h2 id="cortex-modal-title"><?php esc_html_e( 'Add Question', 'cortex' ); ?></h2>
                <form id="cortex-question-form">
                    <?php wp_nonce_field( 'cortex_save_question_nonce', 'cortex_save_question_nonce_field' ); ?>
                    <input type="hidden" name="action" value="cortex_add_question">
                    <input type="hidden" name="quiz_id" value="<?php echo esc_attr( $quiz_id ); ?>">
                    <input type="hidden" name="question_id" value="">
                    <table class="form-table">
                        <tr>
                            <th><label><?php esc_html_e( 'Question Text', 'cortex' ); ?></label></th>
                            <td><textarea name="question_text" rows="3" class="large-text" required></textarea></td>
                        </tr>
                        <tr>
                            <th><label><?php esc_html_e( 'Type', 'cortex' ); ?></label></th>
                            <td>
                                <select name="type">
                                    <option value="single"><?php esc_html_e( 'Single choice', 'cortex' ); ?></option>
                                    <option value="multiple"><?php esc_html_e( 'Multiple choice', 'cortex' ); ?></option>
                                    <option value="text"><?php esc_html_e( 'Text (short)', 'cortex' ); ?></option>
                                    <option value="textarea"><?php esc_html_e( 'Text (long)', 'cortex' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr class="cortex-options-row">
                            <th><label><?php esc_html_e( 'Options (one per line)', 'cortex' ); ?></label></th>
                            <td>
                                <div id="cortex-options-wrapper">
                                    <textarea name="options_text" rows="4" class="large-text" placeholder="<?php esc_attr_e( 'Option A\nOption B\nOption C', 'cortex' ); ?>"></textarea>
                                    <p class="description"><?php esc_html_e( 'For single/multiple choice, provide options one per line. For text fields leave blank.', 'cortex' ); ?></p>
                                </div>
                            </td>
                        </tr>
                        <tr class="cortex-correct-row">
                            <th><label><?php esc_html_e( 'Correct Answer(s)', 'cortex' ); ?></label></th>
                            <td>
                                <input type="text" name="correct_answer_text" class="regular-text" placeholder="<?php esc_attr_e( 'For single choice: the exact option text or its line number (1-based). For multiple: comma-separated indexes or exact options', 'cortex' ); ?>">
                                <p class="description"><?php esc_html_e( 'You may provide the correct option index (1-based) or the exact text. For multiple: separate by comma.', 'cortex' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label><?php esc_html_e( 'Points', 'cortex' ); ?></label></th>
                            <td><input type="number" name="points" value="1" min="0" class="small-text"></td>
                        </tr>
                        <tr>
                            <th><label><?php esc_html_e( 'Image (optional)', 'cortex' ); ?></label></th>
                            <td><input type="url" name="image" placeholder="<?php esc_attr_e( 'https://...', 'cortex' ); ?>" class="regular-text"></td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary" id="cortex-modal-submit"><?php esc_html_e( 'Save Question', 'cortex' ); ?></button>
                        <button type="button" class="button" id="cortex-modal-cancel"><?php esc_html_e( 'Cancel', 'cortex' ); ?></button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    public function render_results_page() {
        global $wpdb;
        $quiz_table = $wpdb->prefix . 'cortex_quizzes';

        $quiz_id = isset( $_GET['quiz_id'] ) ? intval( $_GET['quiz_id'] ) : 0;

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Quiz Results', 'cortex' ); ?></h1>

            <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
                <input type="hidden" name="page" value="cortex_results">
                <label for="filter_quiz"><?php esc_html_e( 'Select Quiz:', 'cortex' ); ?></label>
                <select id="filter_quiz" name="quiz_id">
                    <option value="0"><?php esc_html_e( '-- Select Quiz --', 'cortex' ); ?></option>
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

                <input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'cortex' ); ?>">
            </form>

            <?php
            if ( $quiz_id > 0 ) {
                $table = new Cortex_Admin_Results_Table( $quiz_id );
                $table->prepare_items();
                $table->display();
            } else {
                echo '<p class="description">' . esc_html__( 'Select a quiz to view attempts.', 'cortex' ) . '</p>';
            }
            ?>
        </div>
        <?php
    }

    public function render_settings_page() {
        // We keep settings simple until Settings class is used (Phase 6)
        $opts = get_option( 'cortex_settings', array() );
        ?>
        <div class="wrap cortex-admin-wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Cortex Settings', 'cortex' ); ?></h1>
            <hr class="wp-header-end">
            <form method="post" action="options.php">
                <?php
                settings_fields( 'cortex_settings_group' );
                do_settings_sections( 'cortex_settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render Question Banks page
     */
    public function render_question_banks_page() {
        if ( ! class_exists( 'Cortex_Question_Bank' ) ) {
             require_once CORTEX_INCLUDES . 'class-cortex-question-bank.php';
        }
        $bank_model = new Cortex_Question_Bank();
        $banks = $bank_model->get_all_banks();

        ?>
        <div class="wrap cortex-admin-dashboard">
            <div class="cortex-dashboard-header">
                <h1><?php esc_html_e( 'Question Banks', 'cortex' ); ?></h1>
                <a href="#" class="cortex-btn cortex-btn-primary cortex-btn-large" id="cortex-add-bank">
                    <?php esc_html_e( '+ Create Bank', 'cortex' ); ?>
                </a>
            </div>

            <?php if ( empty( $banks ) ) : ?>
                <div class="cortex-empty-state">
                    <div class="cortex-empty-icon">📦</div>
                    <h2 class="cortex-empty-title"><?php esc_html_e( 'No Question Banks Yet', 'cortex' ); ?></h2>
                    <p class="cortex-empty-description">
                        <?php esc_html_e( 'Question banks let you create reusable question libraries that can be imported into multiple quizzes.', 'cortex' ); ?>
                    </p>
                    <a href="#" class="cortex-btn cortex-btn-primary" id="cortex-create-first-bank">
                        <?php esc_html_e( 'Create Your First Bank', 'cortex' ); ?>
                    </a>
                </div>
            <?php else : ?>
                <div class="cortex-filter-bar">
                    <input type="search" placeholder="<?php esc_attr_e( 'Search banks...', 'cortex' ); ?>">
                    <select>
                        <option value="all"><?php esc_html_e( 'All Banks', 'cortex' ); ?></option>
                        <option value="recent"><?php esc_html_e( 'Recently Updated', 'cortex' ); ?></option>
                    </select>
                </div>

                <div class="cortex-cards-grid">
                    <?php foreach ( $banks as $bank ) : ?>
                        <div class="cortex-card" data-status="published">
                            <div class="cortex-card-header">
                                <div>
                                    <h3 class="cortex-card-title">
                                        <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'cortex_question_banks', 'action' => 'edit', 'id' => $bank['id'] ), admin_url( 'admin.php' ) ) ); ?>">
                                            <?php echo esc_html( $bank['name'] ); ?>
                                        </a>
                                    </h3>
                                </div>
                            </div>

                            <div class="cortex-card-body">
                                <?php if ( ! empty( $bank['description'] ) ) : ?>
                                    <p class="cortex-card-description"><?php echo esc_html( $bank['description'] ); ?></p>
                                <?php endif; ?>

                                <div class="cortex-card-meta">
                                    <div class="cortex-meta-item">
                                        <span class="cortex-meta-icon">📝</span>
                                        <span class="cortex-meta-value"><?php echo esc_html( $bank['question_count'] ); ?></span>
                                        <?php esc_html_e( 'questions', 'cortex' ); ?>
                                    </div>
                                    <div class="cortex-meta-item">
                                        <span class="cortex-meta-icon">📅</span>
                                        <?php echo isset($bank['created_at']) ? esc_html( human_time_diff( strtotime( $bank['created_at'] ), current_time( 'timestamp' ) ) ) . ' ' . esc_html__('ago', 'cortex') : ''; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="cortex-card-footer">
                                <div class="cortex-card-actions">
                                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'cortex_question_banks', 'action' => 'edit', 'id' => $bank['id'] ), admin_url( 'admin.php' ) ) ); ?>" class="cortex-card-action primary">
                                        <?php esc_html_e( 'Manage', 'cortex' ); ?>
                                    </a>
                                    <a href="#" class="cortex-card-action secondary" onclick="return confirm('<?php esc_attr_e( 'Delete this bank?', 'cortex' ); ?>')">
                                        <?php esc_html_e( 'Delete', 'cortex' ); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render Markdown Snippets page
     */
    public function render_markdown_snippets_page() {
        if ( ! class_exists( 'Cortex_Markdown_Snippets' ) ) {
             require_once CORTEX_INCLUDES . 'class-cortex-markdown-snippets.php';
        }
        $snippet_model = new Cortex_Markdown_Snippets();
        $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';
        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

        if ( 'create' === $action || 'edit' === $action ) {
            $snippet = ( 'edit' === $action && $id > 0 ) ? $snippet_model->get_snippet( $id ) : null;
            $title = $snippet ? sprintf( __( 'Edit Snippet: %s', 'cortex' ), $snippet['title'] ) : __( 'Create New Markdown Snippet', 'cortex' );
            ?>
            <div class="wrap cortex-admin-dashboard">
                <div class="cortex-dashboard-header">
                    <h1><?php echo esc_html( $title ); ?></h1>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=cortex_markdown_snippets' ) ); ?>" class="cortex-btn cortex-btn-secondary">
                        <?php esc_html_e( 'Back to List', 'cortex' ); ?>
                    </a>
                </div>

                <div class="cortex-card cortex-form-card">
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( 'cortex_save_markdown_snippet', 'cortex_snippet_nonce' ); ?>
                        <input type="hidden" name="action" value="cortex_save_markdown_snippet">
                        <input type="hidden" name="snippet_id" value="<?php echo esc_attr( $id ); ?>">

                        <div class="cortex-form-group">
                            <label for="cortex_snippet_title"><?php esc_html_e( 'Snippet Title', 'cortex' ); ?></label>
                            <input type="text" id="cortex_snippet_title" name="title" class="cortex-input" value="<?php echo $snippet ? esc_attr( $snippet['title'] ) : ''; ?>" required placeholder="<?php esc_attr_e( 'Enter a descriptive title...', 'cortex' ); ?>">
                        </div>

                        <div class="cortex-form-group">
                            <label for="cortex_snippet_content"><?php esc_html_e( 'Markdown Content', 'cortex' ); ?></label>
                            <textarea id="cortex_snippet_content" name="content" class="cortex-textarea cortex-code-editor" rows="15" required placeholder="<?php esc_attr_e( 'Write your markdown here... Use $$ for math or standard markdown.', 'cortex' ); ?>"><?php echo $snippet ? esc_textarea( $snippet['content'] ) : ''; ?></textarea>
                            <p class="description"><?php esc_html_e( 'Supports GitHub Flavored Markdown and KaTeX ($$ Math $$).', 'cortex' ); ?></p>
                        </div>

                        <div class="cortex-form-actions">
                            <button type="submit" class="cortex-btn cortex-btn-primary cortex-btn-large">
                                <?php $snippet ? esc_html_e( 'Update Snippet', 'cortex' ) : esc_html_e( 'Create Snippet', 'cortex' ); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php
            return;
        }

        $snippets = $snippet_model->get_all_snippets();
        ?>
        <div class="wrap cortex-admin-dashboard">
            <div class="cortex-dashboard-header">
                <h1><?php esc_html_e( 'Markdown Snippets', 'cortex' ); ?></h1>
                <div class="cortex-header-actions">
                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'cortex_markdown_snippets', 'action' => 'create' ), admin_url( 'admin.php' ) ) ); ?>" class="cortex-btn cortex-btn-primary cortex-btn-large">
                        <?php esc_html_e( '+ New Snippet', 'cortex' ); ?>
                    </a>
                </div>
            </div>

            <?php if ( empty( $snippets ) ) : ?>
                <div class="cortex-empty-state">
                    <div class="cortex-empty-icon">📄</div>
                    <h2 class="cortex-empty-title"><?php esc_html_e( 'No Markdown Snippets Yet', 'cortex' ); ?></h2>
                    <p class="cortex-empty-description">
                        <?php esc_html_e( 'Create reusable markdown content that can be embedded anywhere with a shortcode.', 'cortex' ); ?>
                    </p>
                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'cortex_markdown_snippets', 'action' => 'create' ), admin_url( 'admin.php' ) ) ); ?>" class="cortex-btn cortex-btn-primary">
                        <?php esc_html_e( 'Create First Snippet', 'cortex' ); ?>
                    </a>
                </div>
            <?php else : ?>
                <div class="cortex-filter-bar">
                    <div class="cortex-search-wrapper">
                        <span class="dashicons dashicons-search"></span>
                        <input type="search" id="cortex-snippet-search" placeholder="<?php esc_attr_e( 'Search snippets...', 'cortex' ); ?>">
                    </div>
                </div>

                <div class="cortex-cards-grid">
                    <?php foreach ( $snippets as $snippet ) : ?>
                        <div class="cortex-card" data-title="<?php echo esc_attr( strtolower( $snippet['title'] ) ); ?>">
                            <div class="cortex-card-header">
                                <h3 class="cortex-card-title">
                                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'cortex_markdown_snippets', 'action' => 'edit', 'id' => $snippet['id'] ), admin_url( 'admin.php' ) ) ); ?>">
                                        <?php echo esc_html( $snippet['title'] ); ?>
                                    </a>
                                </h3>
                                <span class="cortex-card-id">#<?php echo esc_html( $snippet['id'] ); ?></span>
                            </div>

                            <div class="cortex-card-body">
                                <p class="cortex-card-preview">
                                    <?php echo esc_html( wp_trim_words( $snippet['content'], 15 ) ); ?>
                                </p>

                                <div class="cortex-shortcode-box">
                                    <code>[cortex-markdown id="<?php echo esc_attr( $snippet['id'] ); ?>"]</code>
                                    <button class="cortex-copy-btn" data-clipboard-text='[cortex-markdown id="<?php echo esc_attr( $snippet['id'] ); ?>"]' title="<?php esc_attr_e( 'Copy Shortcode', 'cortex' ); ?>">
                                        <span class="dashicons dashicons-clipboard"></span>
                                    </button>
                                </div>
                            </div>

                            <div class="cortex-card-footer">
                                <div class="cortex-card-actions">
                                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'cortex_markdown_snippets', 'action' => 'edit', 'id' => $snippet['id'] ), admin_url( 'admin.php' ) ) ); ?>" class="cortex-action-link">
                                        <span class="dashicons dashicons-edit"></span> <?php esc_html_e( 'Edit', 'cortex' ); ?>
                                    </a>
                                    <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => 'cortex_markdown_snippets', 'action' => 'delete', 'id' => $snippet['id'] ), admin_url( 'admin-post.php' ) ), 'cortex_delete_markdown_snippet_' . $snippet['id'] ) ); ?>" class="cortex-action-link delete" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this snippet?', 'cortex' ); ?>')">
                                        <span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Delete', 'cortex' ); ?>
                                    </a>
                                </div>
                                <span class="cortex-card-date">
                                    <?php echo isset($snippet['created_at']) ? esc_html( human_time_diff( strtotime( $snippet['created_at'] ), current_time( 'timestamp' ) ) ) . ' ' . esc_html__('ago', 'cortex') : ''; ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const search = document.getElementById('cortex-snippet-search');
                if (search) {
                    search.addEventListener('input', function(e) {
                        const term = e.target.value.toLowerCase();
                        document.querySelectorAll('.cortex-card').forEach(card => {
                            const title = card.getAttribute('data-title') || '';
                            card.style.display = title.includes(term) ? '' : 'none';
                        });
                    });
                }
                document.querySelectorAll('.cortex-copy-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const text = this.getAttribute('data-clipboard-text');
                        navigator.clipboard.writeText(text).then(() => {
                            const icon = this.querySelector('.dashicons');
                            icon.classList.remove('dashicons-clipboard');
                            icon.classList.add('dashicons-yes');
                            setTimeout(() => {
                                icon.classList.remove('dashicons-yes');
                                icon.classList.add('dashicons-clipboard');
                            }, 2000);
                        });
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * Render Code Snippets page
     */
    public function render_code_snippets_page() {
        if ( ! class_exists( 'Cortex_Snippet' ) ) {
             require_once CORTEX_INCLUDES . 'class-cortex-snippet.php';
        }
        $snippet_model = new Cortex_Snippet();
        $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';
        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

        if ( 'create' === $action || 'edit' === $action ) {
            $snippet = ( 'edit' === $action && $id > 0 ) ? $snippet_model->get_snippet( $id ) : null;
            $title = $snippet ? sprintf( __( 'Edit Code Snippet: %s', 'cortex' ), $snippet->title ) : __( 'Create New Code Snippet', 'cortex' );
            
            $languages = array(
                'text'       => 'Plain Text',
                'php'        => 'PHP',
                'javascript' => 'JavaScript',
                'css'        => 'CSS',
                'html'       => 'HTML',
                'sql'        => 'SQL',
                'python'     => 'Python',
                'bash'       => 'Bash/Shell',
                'markdown'   => 'Markdown',
                'json'       => 'JSON'
            );
            ?>
            <div class="wrap cortex-admin-dashboard">
                <div class="cortex-dashboard-header">
                    <h1><?php echo esc_html( $title ); ?></h1>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=cortex_code_snippets' ) ); ?>" class="cortex-btn cortex-btn-secondary">
                        <?php esc_html_e( 'Back to List', 'cortex' ); ?>
                    </a>
                </div>

                <div class="cortex-card cortex-form-card">
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( 'cortex_save_code_snippet', 'cortex_snippet_nonce' ); ?>
                        <input type="hidden" name="action" value="cortex_save_code_snippet">
                        <input type="hidden" name="snippet_id" value="<?php echo esc_attr( $id ); ?>">

                        <div class="cortex-form-group">
                            <label for="cortex_snippet_title"><?php esc_html_e( 'Snippet Title', 'cortex' ); ?></label>
                            <input type="text" id="cortex_snippet_title" name="title" class="cortex-input" value="<?php echo $snippet ? esc_attr( $snippet->title ) : ''; ?>" required placeholder="<?php esc_attr_e( 'Enter a descriptive title...', 'cortex' ); ?>">
                        </div>

                        <div class="cortex-row">
                            <div class="cortex-col-6">
                                <div class="cortex-form-group">
                                    <label for="cortex_snippet_language"><?php esc_html_e( 'Language', 'cortex' ); ?></label>
                                    <select id="cortex_snippet_language" name="language" class="cortex-input" required>
                                        <?php foreach ( $languages as $lang_code => $lang_name ) : ?>
                                            <option value="<?php echo esc_attr( $lang_code ); ?>" <?php selected( $snippet ? $snippet->language : '', $lang_code ); ?>><?php echo esc_html( $lang_name ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="cortex-col-6">
                                <div class="cortex-form-group">
                                    <label for="cortex_show_copy_button"><?php esc_html_e( 'Options', 'cortex' ); ?></label>
                                    <div class="cortex-checkbox-wrapper">
                                        <label>
                                            <input type="checkbox" name="show_copy_button" value="1" <?php checked( $snippet ? $snippet->show_copy_button : 1, 1 ); ?>>
                                            <?php esc_html_e( 'Show Copy Button', 'cortex' ); ?>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="cortex-form-group">
                            <label for="cortex_snippet_code"><?php esc_html_e( 'Code Content', 'cortex' ); ?></label>
                            <textarea id="cortex_snippet_code" name="code" class="cortex-textarea cortex-code-editor" rows="15" required placeholder="<?php esc_attr_e( 'Paste your code here...', 'cortex' ); ?>"><?php echo $snippet ? esc_textarea( $snippet->code ) : ''; ?></textarea>
                        </div>

                        <div class="cortex-form-actions">
                            <button type="submit" class="cortex-btn cortex-btn-primary cortex-btn-large">
                                <?php $snippet ? esc_html_e( 'Update Snippet', 'cortex' ) : esc_html_e( 'Create Snippet', 'cortex' ); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php
            return;
        }

        $snippets = $snippet_model->get_all_snippets();
        ?>
        <div class="wrap cortex-admin-dashboard">
            <div class="cortex-dashboard-header">
                <h1><?php esc_html_e( 'Code Snippets', 'cortex' ); ?></h1>
                <div class="cortex-header-actions">
                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'cortex_code_snippets', 'action' => 'create' ), admin_url( 'admin.php' ) ) ); ?>" class="cortex-btn cortex-btn-primary cortex-btn-large">
                        <?php esc_html_e( '+ New Snippet', 'cortex' ); ?>
                    </a>
                </div>
            </div>

            <?php if ( empty( $snippets ) ) : ?>
                <div class="cortex-empty-state">
                    <div class="cortex-empty-icon">💻</div>
                    <h2 class="cortex-empty-title"><?php esc_html_e( 'No Code Snippets Yet', 'cortex' ); ?></h2>
                    <p class="cortex-empty-description">
                        <?php esc_html_e( 'Save and embed syntax-highlighted code snippets anywhere using a simple shortcode.', 'cortex' ); ?>
                    </p>
                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'cortex_code_snippets', 'action' => 'create' ), admin_url( 'admin.php' ) ) ); ?>" class="cortex-btn cortex-btn-primary">
                        <?php esc_html_e( 'Create First Snippet', 'cortex' ); ?>
                    </a>
                </div>
            <?php else : ?>
                <div class="cortex-filter-bar">
                    <div class="cortex-search-wrapper">
                        <span class="dashicons dashicons-search"></span>
                        <input type="search" id="cortex-code-search" placeholder="<?php esc_attr_e( 'Search code snippets...', 'cortex' ); ?>">
                    </div>
                </div>

                <div class="cortex-cards-grid">
                    <?php foreach ( $snippets as $snippet ) : ?>
                        <div class="cortex-card" data-title="<?php echo esc_attr( strtolower( $snippet->title ) ); ?>">
                            <div class="cortex-card-header">
                                <h3 class="cortex-card-title">
                                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'cortex_code_snippets', 'action' => 'edit', 'id' => $snippet->id ), admin_url( 'admin.php' ) ) ); ?>">
                                        <?php echo esc_html( $snippet->title ); ?>
                                    </a>
                                </h3>
                                <span class="cortex-card-tag"><?php echo esc_html( strtoupper( $snippet->language ) ); ?></span>
                            </div>

                            <div class="cortex-card-body">
                                <div class="cortex-code-preview-wrap">
                                    <pre class="cortex-code-preview"><code><?php echo esc_html( wp_trim_words( $snippet->code, 10 ) ); ?></code></pre>
                                </div>

                                <div class="cortex-shortcode-box">
                                    <code>[cortex-code id="<?php echo esc_attr( $snippet->id ); ?>"]</code>
                                    <button class="cortex-copy-btn" data-clipboard-text='[cortex-code id="<?php echo esc_attr( $snippet->id ); ?>"]' title="<?php esc_attr_e( 'Copy Shortcode', 'cortex' ); ?>">
                                        <span class="dashicons dashicons-clipboard"></span>
                                    </button>
                                </div>
                            </div>

                            <div class="cortex-card-footer">
                                <div class="cortex-card-actions">
                                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'cortex_code_snippets', 'action' => 'edit', 'id' => $snippet->id ), admin_url( 'admin.php' ) ) ); ?>" class="cortex-action-link">
                                        <span class="dashicons dashicons-edit"></span> <?php esc_html_e( 'Edit', 'cortex' ); ?>
                                    </a>
                                    <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => 'cortex_code_snippets', 'action' => 'delete', 'id' => $snippet->id ), admin_url( 'admin-post.php' ) ), 'cortex_delete_code_snippet_' . $snippet->id ) ); ?>" class="cortex-action-link delete" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this snippet?', 'cortex' ); ?>')">
                                        <span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Delete', 'cortex' ); ?>
                                    </a>
                                </div>
                                <span class="cortex-card-date">
                                    <?php echo isset($snippet->created_at) ? esc_html( human_time_diff( strtotime( $snippet->created_at ), current_time( 'timestamp' ) ) ) . ' ' . esc_html__('ago', 'cortex') : ''; ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const search = document.getElementById('cortex-code-search');
                if (search) {
                    search.addEventListener('input', function(e) {
                        const term = e.target.value.toLowerCase();
                        document.querySelectorAll('.cortex-card').forEach(card => {
                            const title = card.getAttribute('data-title') || '';
                            card.style.display = title.includes(term) ? '' : 'none';
                        });
                    });
                }
            });
        </script>
        <?php
    }
    public function render_lead_captures_page() {
        if ( ! class_exists( 'Cortex_Lead_Capture' ) ) {
             require_once CORTEX_INCLUDES . 'class-cortex-lead-capture.php';
        }
        global $wpdb;

        // Get stats
        $table_leads = $wpdb->prefix . 'cortex_lead_captures';
        $total_leads = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_leads}" );
        $leads_today = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_leads} WHERE DATE(created_at) = CURDATE()" );
        $leads_week = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_leads} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)" );

        // Get recent leads
        $recent_leads = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.*, q.title as quiz_title 
                 FROM {$table_leads} l
                 LEFT JOIN {$wpdb->prefix}cortex_quizzes q ON l.quiz_id = q.id
                 ORDER BY l.created_at DESC 
                 LIMIT 50"
            ),
            ARRAY_A
        );

        ?>
        <div class="wrap cortex-admin-dashboard">
            <div class="cortex-dashboard-header">
                <h1><?php esc_html_e( 'Lead Captures', 'cortex' ); ?></h1>
                <a href="#" class="cortex-btn cortex-btn-primary" id="cortex-export-leads-csv">
                    <?php esc_html_e( 'Export CSV', 'cortex' ); ?>
                </a>
            </div>

            <!-- Stats Cards -->
            <div class="cortex-stats-grid">
                <div class="cortex-stat-card primary">
                    <div class="cortex-stat-header">
                        <div>
                            <div class="cortex-stat-value"><?php echo esc_html( number_format( $total_leads ) ); ?></div>
                            <div class="cortex-stat-label"><?php esc_html_e( 'Total Leads', 'cortex' ); ?></div>
                        </div>
                        <div class="cortex-stat-icon">👥</div>
                    </div>
                </div>

                <div class="cortex-stat-card success">
                    <div class="cortex-stat-header">
                        <div>
                            <div class="cortex-stat-value"><?php echo esc_html( number_format( $leads_today ) ); ?></div>
                            <div class="cortex-stat-label"><?php esc_html_e( 'Today', 'cortex' ); ?></div>
                        </div>
                        <div class="cortex-stat-icon">📈</div>
                    </div>
                </div>

                <div class="cortex-stat-card warning">
                    <div class="cortex-stat-header">
                        <div>
                            <div class="cortex-stat-value"><?php echo esc_html( number_format( $leads_week ) ); ?></div>
                            <div class="cortex-stat-label"><?php esc_html_e( 'This Week', 'cortex' ); ?></div>
                        </div>
                        <div class="cortex-stat-icon">📊</div>
                    </div>
                </div>
            </div>

            <!-- Leads Table -->
            <div class="cortex-form-container">
                <h2><?php esc_html_e( 'Recent Captures', 'cortex' ); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Name', 'cortex' ); ?></th>
                            <th><?php esc_html_e( 'Email', 'cortex' ); ?></th>
                            <th><?php esc_html_e( 'Quiz', 'cortex' ); ?></th>
                            <th><?php esc_html_e( 'Date', 'cortex' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $recent_leads ) ) : ?>
                            <?php foreach ( $recent_leads as $lead ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $lead['name'] ); ?></td>
                                    <td><a href="mailto:<?php echo esc_attr( $lead['email'] ); ?>"><?php echo esc_html( $lead['email'] ); ?></a></td>
                                    <td><?php echo esc_html( $lead['quiz_title'] ); ?></td>
                                    <td><?php echo esc_html( human_time_diff( strtotime( $lead['created_at'] ), current_time( 'timestamp' ) ) ); ?> <?php esc_html_e( 'ago', 'cortex' ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="4" style="text-align:center;"><?php esc_html_e( 'No leads captured yet.', 'cortex' ); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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
        check_ajax_referer( 'cortex_admin_nonce', 'nonce' );

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
        check_ajax_referer( 'cortex_admin_nonce', 'nonce' );

        $quiz_id = isset( $_POST['quiz_id'] ) ? intval( $_POST['quiz_id'] ) : 0;
        if ( $quiz_id <= 0 ) {
            wp_send_json_error( 'invalid_quiz' );
        }

        // Use security layer for initial sanitization
        $raw_data = $_POST;
        $sanitized_data = Cortex_Security::sanitize_question_input( $raw_data );

        // Extract complex fields if they exist as arrays in $_POST (bypass sanitize_question_input's JSON-only handling)
        if ( isset( $raw_data['options'] ) && is_array( $raw_data['options'] ) ) {
             $sanitized_data['options'] = array_map( 'wp_kses_post', $raw_data['options'] );
        }

        if ( isset( $raw_data['correct_answer'] ) ) {
            if ( is_array( $raw_data['correct_answer'] ) ) {
                $sanitized_data['correct_answer'] = array_map( 'sanitize_text_field', $raw_data['correct_answer'] );
            } else {
                $sanitized_data['correct_answer'] = sanitize_text_field( $raw_data['correct_answer'] );
            }
        }

        if ( isset( $raw_data['metadata'] ) && is_array( $raw_data['metadata'] ) ) {
            // metadata should be sanitized recursively
            $sanitized_data['metadata'] = $this->sanitize_metadata( $raw_data['metadata'] );
        }

        $qid = $this->model->add_question( $quiz_id, $sanitized_data );
        if ( ! $qid ) {
            wp_send_json_error( 'insert_failed' );
        }

        wp_send_json_success( $this->model->get_question( $qid ) );
    }

    public function ajax_update_question() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized', 403 );
        }
        check_ajax_referer( 'cortex_admin_nonce', 'nonce' );

        $question_id = isset( $_POST['question_id'] ) ? intval( $_POST['question_id'] ) : 0;
        if ( $question_id <= 0 ) {
            wp_send_json_error( 'invalid_question' );
        }

        // Use security layer
        $raw_data = $_POST;
        $sanitized_data = Cortex_Security::sanitize_question_input( $raw_data );

        // Extract complex fields
        if ( isset( $raw_data['options'] ) && is_array( $raw_data['options'] ) ) {
             $sanitized_data['options'] = array_map( 'wp_kses_post', $raw_data['options'] );
        }

        if ( isset( $raw_data['correct_answer'] ) ) {
            if ( is_array( $raw_data['correct_answer'] ) ) {
                $sanitized_data['correct_answer'] = array_map( 'sanitize_text_field', $raw_data['correct_answer'] );
            } else {
                $sanitized_data['correct_answer'] = sanitize_text_field( $raw_data['correct_answer'] );
            }
        }

        if ( isset( $raw_data['metadata'] ) && is_array( $raw_data['metadata'] ) ) {
            $sanitized_data['metadata'] = $this->sanitize_metadata( $raw_data['metadata'] );
        }

        $ok = $this->model->update_question( $question_id, $sanitized_data );
        if ( ! $ok ) {
            wp_send_json_error( 'update_failed' );
        }

        wp_send_json_success( $this->model->get_question( $question_id ) );
    }

    /**
     * Recursive metadata sanitization
     */
    protected function sanitize_metadata( $data ) {
        if ( ! is_array( $data ) ) {
            return wp_kses_post( $data );
        }
        
        $clean = array();
        foreach ( $data as $key => $value ) {
            $clean[ sanitize_text_field( $key ) ] = $this->sanitize_metadata( $value );
        }
        return $clean;
    }

    protected function db_get_quiz_id_by_question( $question_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'cortex_questions';
        return $wpdb->get_var( $wpdb->prepare( "SELECT quiz_id FROM {$table} WHERE id = %d", $question_id ) );
    }

    public function ajax_delete_question() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized', 403 );
        }
        check_ajax_referer( 'cortex_admin_nonce', 'nonce' );

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
            wp_die( __( 'Unauthorized', 'cortex' ) );
        }
        
        // Match the nonce name from the form
        check_admin_referer( 'cortex_save_quiz', 'cortex_quiz_nonce' );

        $id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
        
        // Collect and sanitize data
        $data = array(
            'title'               => isset( $_POST['quiz_title'] ) ? sanitize_text_field( wp_unslash( $_POST['quiz_title'] ) ) : '',
            'description'         => isset( $_POST['quiz_description'] ) ? wp_kses_post( wp_unslash( $_POST['quiz_description'] ) ) : '',
            'time_limit'          => isset( $_POST['quiz_timer'] ) ? intval( $_POST['quiz_timer'] ) : 0,
            'attempts_allowed'    => isset( $_POST['quiz_attempt_limit'] ) ? intval( $_POST['quiz_attempt_limit'] ) : 0,
            'randomize_questions' => isset( $_POST['randomize_questions'] ) ? 1 : 0,
            'require_login'       => isset( $_POST['require_login'] ) ? 1 : 0,
            'show_answers'        => isset( $_POST['show_answers'] ) ? 1 : 0,
            'enable_lead_capture' => isset( $_POST['enable_lead_capture'] ) ? 1 : 0,
        );

        // Handle dynamic lead fields
        $lead_fields = array();
        if ( isset( $_POST['lead_fields'] ) && is_array( $_POST['lead_fields'] ) ) {
            foreach ( $_POST['lead_fields'] as $field ) {
                if ( ! empty( $field['label'] ) ) {
                    $label = sanitize_text_field( $field['label'] );
                    $lead_fields[] = array(
                        'id'       => sanitize_title( $label ), // Generate a slug-like ID
                        'label'    => $label,
                        'type'     => sanitize_text_field( $field['type'] ?? 'text' ),
                        'required' => isset( $field['required'] ) ? 1 : 0,
                    );
                }
            }
        }
        $data['lead_fields'] = $lead_fields;

        // Settings array for extra flexibility
        $data['settings'] = array(
            'last_saved_by' => get_current_user_id(),
        );

        if ( $id > 0 ) {
            $this->model->update_quiz( $id, $data );
            $msg = __( 'Quiz updated successfully.', 'cortex' );
        } else {
            $id = $this->model->create_quiz( $data );
            $msg = __( 'Quiz created successfully.', 'cortex' );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=cortex_quizzes&action=edit&id=' . $id . '&cortex_msg=' . urlencode( $msg ) ) );
        exit;
    }

    public function handle_delete_quiz() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'cortex' ) );
        }
        $id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
        if ( $id <= 0 ) {
            wp_safe_redirect( admin_url( 'admin.php?page=cortex_quizzes' ) );
            exit;
        }
        if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'cortex_delete_quiz_' . $id ) ) {
            wp_die( __( 'Nonce verification failed.', 'cortex' ) );
        }

        $this->model->delete_quiz( $id );

        wp_safe_redirect( admin_url( 'admin.php?page=cortex_quizzes&cortex_msg=' . urlencode( __( 'Quiz deleted.', 'cortex' ) ) ) );
        exit;
    }

    /**
     * Handle CSV import posted from the manage questions UI.
     * Expect: quiz_id, file (CSV)
     */
    public function handle_import_questions() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'cortex' ) );
        }
        check_admin_referer( 'cortex_import_questions_nonce', 'cortex_import_questions_nonce_field' );

        $quiz_id = isset( $_POST['quiz_id'] ) ? intval( $_POST['quiz_id'] ) : 0;
        if ( $quiz_id <= 0 ) {
            wp_safe_redirect( admin_url( 'admin.php?page=cortex_quizzes&cortex_msg=' . urlencode( __( 'Invalid quiz selected', 'cortex' ) ) ) );
            exit;
        }

        if ( ! isset( $_FILES['cortex_import_file'] ) || empty( $_FILES['cortex_import_file']['tmp_name'] ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=cortex_quizzes&action=manage&id=' . $quiz_id . '&cortex_msg=' . urlencode( __( 'No file uploaded', 'cortex' ) ) ) );
            exit;
        }

        $tmp = $_FILES['cortex_import_file']['tmp_name'];
        $fh = fopen( $tmp, 'r' );
        if ( ! $fh ) {
            wp_safe_redirect( admin_url( 'admin.php?page=cortex_quizzes&action=manage&id=' . $quiz_id . '&cortex_msg=' . urlencode( __( 'Unable to open file', 'cortex' ) ) ) );
            exit;
        }

        $header = fgetcsv( $fh );
        if ( ! $header ) {
            fclose( $fh );
            wp_safe_redirect( admin_url( 'admin.php?page=cortex_quizzes&action=manage&id=' . $quiz_id . '&cortex_msg=' . urlencode( __( 'Invalid CSV header', 'cortex' ) ) ) );
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

        wp_safe_redirect( admin_url( 'admin.php?page=cortex_quizzes&action=manage&id=' . $quiz_id . '&cortex_msg=' . urlencode( sprintf( _n( '%d question imported', '%d questions imported', $inserted, 'cortex' ), $inserted ) ) ) );
        exit;
    }

    public function handle_save_markdown_snippet() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'cortex' ) );
        }
        check_admin_referer( 'cortex_save_markdown_snippet', 'cortex_snippet_nonce' );

        $id = isset( $_POST['snippet_id'] ) ? absint( $_POST['snippet_id'] ) : 0;
        $data = array(
            'title'   => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
            'content' => isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '',
        );

        if ( ! class_exists( 'Cortex_Markdown_Snippets' ) ) {
             require_once CORTEX_INCLUDES . 'class-cortex-markdown-snippets.php';
        }
        $snippet_model = new Cortex_Markdown_Snippets();

        if ( $id > 0 ) {
            $snippet_model->update_snippet( $id, $data );
            $msg = __( 'Markdown snippet updated.', 'cortex' );
        } else {
            $id = $snippet_model->create_snippet( $data );
            $msg = __( 'Markdown snippet created.', 'cortex' );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=cortex_markdown_snippets&action=edit&id=' . $id . '&cortex_msg=' . urlencode( $msg ) ) );
        exit;
    }

    public function handle_delete_markdown_snippet() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'cortex' ) );
        }
        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        check_admin_referer( 'cortex_delete_markdown_snippet_' . $id );

        if ( ! class_exists( 'Cortex_Markdown_Snippets' ) ) {
             require_once CORTEX_INCLUDES . 'class-cortex-markdown-snippets.php';
        }
        $snippet_model = new Cortex_Markdown_Snippets();
        $snippet_model->delete_snippet( $id );

        wp_safe_redirect( admin_url( 'admin.php?page=cortex_markdown_snippets&cortex_msg=' . urlencode( __( 'Snippet deleted.', 'cortex' ) ) ) );
        exit;
    }

    public function handle_save_code_snippet() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'cortex' ) );
        }
        check_admin_referer( 'cortex_save_code_snippet', 'cortex_snippet_nonce' );

        $id = isset( $_POST['snippet_id'] ) ? absint( $_POST['snippet_id'] ) : 0;
        $data = array(
            'title'            => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
            'code'             => isset( $_POST['code'] ) ? wp_unslash( $_POST['code'] ) : '',
            'language'         => isset( $_POST['language'] ) ? sanitize_text_field( $_POST['language'] ) : 'text',
            'show_copy_button' => isset( $_POST['show_copy_button'] ) ? 1 : 0,
        );

        if ( ! class_exists( 'Cortex_Snippet' ) ) {
             require_once CORTEX_INCLUDES . 'class-cortex-snippet.php';
        }
        $snippet_model = new Cortex_Snippet();

        if ( $id > 0 ) {
            $snippet_model->update_snippet( $id, $data );
            $msg = __( 'Code snippet updated.', 'cortex' );
        } else {
            $id = $snippet_model->create_snippet( $data );
            $msg = __( 'Code snippet created.', 'cortex' );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=cortex_code_snippets&action=edit&id=' . $id . '&cortex_msg=' . urlencode( $msg ) ) );
        exit;
    }

    public function handle_delete_code_snippet() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'cortex' ) );
        }
        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        check_admin_referer( 'cortex_delete_code_snippet_' . $id );

        if ( ! class_exists( 'Cortex_Snippet' ) ) {
             require_once CORTEX_INCLUDES . 'class-cortex-snippet.php';
        }
        $snippet_model = new Cortex_Snippet();
        $snippet_model->delete_snippet( $id );

        wp_safe_redirect( admin_url( 'admin.php?page=cortex_code_snippets&cortex_msg=' . urlencode( __( 'Snippet deleted.', 'cortex' ) ) ) );
        exit;
    }

    /**
     * Called on admin_init to process maybe immediate tasks (placeholder).
     */
    public function maybe_process_import() {
        // reserved if you want to hook imports differently
    }
}

// Removed redundant global instantiation: new Cortex_Admin();
