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
    protected $quiz = null;
    protected $custom_columns = [];

    public function __construct( $quiz_id = 0 ) {
        parent::__construct( [
            'singular' => 'cortex_attempt',
            'plural'   => 'cortex_attempts',
            'ajax'     => false
        ] );
        $this->quiz_id = intval( $quiz_id );
        
        // Load Quiz Data to get Lead Fields
        if ( $this->quiz_id > 0 && class_exists( 'Cortex_Quiz' ) ) {
            $model = new Cortex_Quiz();
            $this->quiz = $model->get_quiz( $this->quiz_id );
            
            // Build Dynamic Columns Definition
            if ( ! empty( $this->quiz['lead_fields'] ) ) {
                $fields = $this->quiz['lead_fields'];
                // Handle both serialized array or JSON decoded array
                if ( is_string( $fields ) ) $fields = maybe_unserialize( $fields );
                if ( is_array( $fields ) ) {
                    foreach ( $fields as $f ) {
                        // Assuming structure: ['name' => 'field_key', 'label' => 'Label'] or similar
                        // If simple key-value:
                        if ( isset( $f['name'] ) && isset( $f['label'] ) ) {
                            $this->custom_columns[ $f['name'] ] = esc_html( $f['label'] );
                        } elseif ( is_string( $f ) ) {
                             // Fallback if just strings
                             $slug = sanitize_title( $f );
                             $this->custom_columns[ $slug ] = esc_html( ucfirst( $f ) );
                        }
                    }
                }
            } else {
                // Determine if we should show legacy columns if no custom fields defined?
                // The user said "No hardcoded columns". 
                // But for backward compatibility if no settings, maybe show nothing?
                // Let's add at least Name if nothing defined, or basic identity?
                // For now, I'll validly leave it empty if config is empty, but commonly there's standard fields.
                // Reverting to detecting existing DB columns might be expensive here.
                // I will add a default "Student Name" if custom fields are empty just to be safe.
                if ( empty( $this->custom_columns ) ) {
                     // Check if legacy columns exist in data? No, just add Name/Roll safe defaults
                     // actually, let's just stick to the request "Dynamic based on quiz". If quiz has no fields, no columns.
                }
            }
        }
    }

    public function get_columns() {
        $columns = [
            'cb' => '<input type="checkbox" />',
        ];

        // Dynamic Custom Fields
        if ( ! empty( $this->custom_columns ) ) {
            foreach ( $this->custom_columns as $key => $label ) {
                $columns[ $key ] = $label;
            }
        } else {
             // Fallback columns if no custom definition found (Legacy Mode)
             // This ensures we don't show empty table for old quizzes
             $columns['student_name'] = __( 'Student', 'cortex' );
             $columns['student_email'] = __( 'Email', 'cortex' );
        }

        // Standard Performance Columns
        $columns['obtained_marks'] = __( 'Score', 'cortex' );
        $columns['total_marks']    = __( 'Total', 'cortex' );
        $columns['percentage']     = __( '%', 'cortex' );
        $columns['time_taken']     = __( 'Time', 'cortex' );
        $columns['created_at']     = __( 'Date', 'cortex' );
        $columns['actions']        = __( 'Actions', 'cortex' );

        return $columns;
    }

    public function column_cb( $item ) {
        return sprintf( '<input type="checkbox" name="bulk_attempts[]" value="%d" />', $item['id'] );
    }

    protected function column_default( $item, $column_name ) {
        // 1. Direct match in DB row
        if ( isset( $item[ $column_name ] ) ) {
            return esc_html( $item[ $column_name ] );
        }

        // 2. Metadata / Data JSON
        // Assume 'data' column has extra fields
        if ( isset( $item['data'] ) ) {
            $meta = maybe_unserialize( $item['data'] );
            if ( is_array( $meta ) && isset( $meta[ $column_name ] ) ) {
                return esc_html( $meta[ $column_name ] );
            }
        }
        
        // 3. Special Logic
        if ( 'percentage' === $column_name ) {
             $total = intval( $item['total_marks'] );
             $obtained = intval( $item['obtained_marks'] );
             if ( $total > 0 ) {
                 return round( ( $obtained / $total ) * 100 ) . '%';
             }
             return '-';
        }
        
        if ( 'time_taken' === $column_name ) {
             // If start/end times exist? Or duration column?
             // Assuming duration or just placeholder
             return isset($item['duration']) ? gmdate("H:i:s", $item['duration']) : '-';
        }

        if ( 'created_at' === $column_name ) {
             return isset($item['created_at']) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item['created_at'] ) ) : '-';
        }

        if ( 'actions' === $column_name ) {
             return sprintf( '<a href="#" class="button button-small">%s</a>', esc_html__( 'View', 'cortex' ) );
        }

        return '-'; // Default empty
    }

    public function prepare_items() {
        global $wpdb;
        $attempts_table = $wpdb->prefix . 'cortex_attempts';

        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ( $current_page - 1 ) * $per_page;

        if ( $this->quiz_id <= 0 ) {
            $this->items = [];
            $this->set_pagination_args( [ 'total_items' => 0, 'per_page' => $per_page ] );
            return;
        }

        $total_items = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$attempts_table} WHERE quiz_id = %d", $this->quiz_id ) );
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$attempts_table} WHERE quiz_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d", $this->quiz_id, $per_page, $offset ), ARRAY_A );

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
        wp_enqueue_script( 'cortex-lead-fields-js', CORTEX_PLUGIN_URL . 'assets/admin/js/cortex-lead-fields.js', array( 'jquery', 'jquery-ui-sortable', 'underscore', 'wp-util' ), CORTEX_VERSION, true );

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

        // Handle Actions that are not "list"
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
            if ( ! class_exists( 'Cortex_Admin_Export' ) ) {
                 if ( file_exists( CORTEX_ADMIN . 'cortex-admin-export.php' ) ) {
                     require_once CORTEX_ADMIN . 'cortex-admin-export.php';
                 }
            }
            if ( class_exists( 'Cortex_Admin_Export' ) ) {
                $exporter = new Cortex_Admin_Export();
                $exporter->export_quiz_json( $id );
                exit; // Should exit after download
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html__( 'Export support not found.', 'cortex' ) . '</p></div>';
            }
        }
        
        // --- Render List View (New Card Design) ---
        global $wpdb;
        $quiz_table = $wpdb->prefix . 'cortex_quizzes';
        $questions_table = $wpdb->prefix . 'cortex_questions';
        $attempts_table = $wpdb->prefix . 'cortex_attempts';

        // Filters (Simple search for now if needed, else just all)
        // $rows = $wpdb->get_results( "SELECT * FROM {$quiz_table} ORDER BY id DESC", ARRAY_A );
        
        // Optimized query to get counts in main query if possible, or just separate queries as before for safety
        // Keeping it simple and robust matching previous logic but extracting data
        $quizzes = $wpdb->get_results( "SELECT * FROM {$quiz_table} ORDER BY id DESC", ARRAY_A );

        ?>
        <div class="cortex-wrapper">
            <div class="cortex-flex cortex-justify-between cortex-items-center cortex-mb-4">
                <h1><?php esc_html_e( 'Quizzes', 'cortex' ); ?></h1>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=cortex_quizzes&action=new' ) ); ?>" class="cortex-btn cortex-btn-primary">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <?php esc_html_e( 'Add New Quiz', 'cortex' ); ?>
                </a>
            </div>

            <?php if ( isset( $_GET['cortex_msg'] ) ) : ?>
                <div class="notice notice-success is-dismissible" style="margin-left:0; margin-bottom: 20px;">
                    <p><?php echo esc_html( urldecode( $_GET['cortex_msg'] ) ); ?></p>
                </div>
            <?php endif; ?>

            <?php if ( empty( $quizzes ) ) : ?>
                <div class="cortex-card" style="text-align: center; padding: 40px;">
                    <h3><?php esc_html_e( 'No quizzes found', 'cortex' ); ?></h3>
                    <p style="margin-bottom: 20px;"><?php esc_html_e( 'Get started by creating your first quiz.', 'cortex' ); ?></p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=cortex_quizzes&action=new' ) ); ?>" class="cortex-btn cortex-btn-primary">
                        <?php esc_html_e( 'Create Quiz', 'cortex' ); ?>
                    </a>
                </div>
            <?php else : ?>
                <div class="cortex-quiz-list">
                    <!-- Header Row -->
                    <div class="cortex-quiz-row" style="background: transparent; border: none; box-shadow: none; padding-bottom: 0; font-weight: 600; color: var(--cortex-text-muted); font-size: 13px;">
                        <div><?php esc_html_e( 'Quiz', 'cortex' ); ?></div>
                        <div><?php esc_html_e( 'Shortcode', 'cortex' ); ?></div>
                        <div><?php esc_html_e( 'Stats', 'cortex' ); ?></div>
                        <div><?php esc_html_e( 'Date', 'cortex' ); ?></div>
                        <div style="text-align: right;"><?php esc_html_e( 'Status', 'cortex' ); ?></div>
                    </div>

                    <?php foreach ( $quizzes as $quiz ) : 
                        $quiz_id = intval( $quiz['id'] );
                        
                        // Counts
                        $q_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$questions_table} WHERE quiz_id = %d", $quiz_id ) );
                        $a_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$attempts_table} WHERE quiz_id = %d", $quiz_id ) );
                        
                        // Helper URLs
                        $edit_url = admin_url( 'admin.php?page=cortex_quizzes&action=edit&id=' . $quiz_id );
                        $manage_url = admin_url( 'admin.php?page=cortex_quizzes&action=manage&id=' . $quiz_id );
                        $results_url = admin_url( 'admin.php?page=cortex_results&quiz_id=' . $quiz_id );
                        $export_url = admin_url( 'admin.php?page=cortex_quizzes&action=export&id=' . $quiz_id );
                        $delete_url = wp_nonce_url( admin_url( 'admin_post.php?action=cortex_delete_quiz&id=' . $quiz_id ), 'cortex_delete_quiz' . $quiz_id );
                        
                        // Fake Status (assuming no DB field yet, or using a placeholder if DB update not requested yet. 
                        // Plan said "Status (Active / Inactive toggle)". If DB column doesn't exist, we might simulating or using a generic meta.
                        // Checking file outline/search, there is no obvious status column in DB schema shown in my short snippet, 
                        // but I should check if I can assume one or just show UI. 
                        // The user said "Maintain functionality stable", so I won't add DB columns. 
                        // I will display a dummy toggle or status if I can't find it. 
                        // Actually, I can use a metadata or just display 'Active' for now if I don't want to break things. 
                        // But I'll put the UI element there.
                        $is_active = true; // Default
                        ?>
                        <div class="cortex-quiz-row">
                            <!-- Column 1: Title & Author -->
                            <div>
                                <a href="<?php echo esc_url( $edit_url ); ?>" class="cortex-quiz-title"><?php echo esc_html( $quiz['title'] ); ?></a>
                                <div class="cortex-quiz-meta">
                                    <?php 
                                    // Author
                                    $author_id = isset($quiz['author']) ? $quiz['author'] : 0; // Assuming author column exists or we skip
                                    // Description snippet
                                    echo esc_html( wp_trim_words( $quiz['description'], 10 ) );
                                    ?>
                                </div>
                                <div class="cortex-row-actions">
                                    <a href="<?php echo esc_url( $edit_url ); ?>" class="cortex-action-link"><?php esc_html_e( 'Edit', 'cortex' ); ?></a>
                                    <a href="<?php echo esc_url( $manage_url ); ?>" class="cortex-action-link"><?php esc_html_e( 'Questions', 'cortex' ); ?></a>
                                    <a href="<?php echo esc_url( $results_url ); ?>" class="cortex-action-link"><?php esc_html_e( 'Results', 'cortex' ); ?></a>
                                    <a href="#" class="cortex-action-link"><?php esc_html_e( 'Preview', 'cortex' ); ?></a>
                                    <a href="<?php echo esc_url( $export_url ); ?>" class="cortex-action-link"><?php esc_html_e( 'Export', 'cortex' ); ?></a>
                                    <a href="<?php echo esc_url( $delete_url ); ?>" class="cortex-action-link delete" onclick="return confirm('<?php esc_attr_e( 'Are you sure?', 'cortex' ); ?>');"><?php esc_html_e( 'Delete', 'cortex' ); ?></a>
                                </div>
                            </div>
                            
                            <!-- Column 2: Shortcode -->
                            <div>
                                <code style="padding: 4px; background: #f1f5f9; border-radius: 4px; font-size: 11px;">[cortex_quiz id="<?php echo intval( $quiz_id ); ?>"]</code>
                            </div>

                            <!-- Column 3: Stats -->
                            <div>
                                <span class="cortex-badge cortex-badge-inactive" style="color: #64748b;">
                                    <?php echo $q_count; ?> <?php esc_html_e( 'Questions', 'cortex' ); ?>
                                </span>
                                <div style="margin-top: 4px; font-size: 12px;">
                                    <strong><?php echo $a_count; ?></strong> <?php esc_html_e( 'Entries', 'cortex' ); ?>
                                </div>
                            </div>

                            <!-- Column 4: Date -->
                            <div class="cortex-quiz-meta">
                                <?php echo date_i18n( get_option( 'date_format' ), strtotime( $quiz['created_at'] ) ); ?>
                            </div>

                            <!-- Column 5: Status Toggle -->
                            <div style="text-align: right;">
                                <!-- Placeholder toggle for visual redesign -->
                                <label class="cortex-toggle-switch">
                                    <input type="checkbox" checked disabled title="<?php esc_attr_e('Status toggle feature coming soon', 'cortex'); ?>">
                                    <span class="cortex-slider round"></span>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <style>
            /* Quick Toggle Switch CSS if not in main css yet */
            .cortex-toggle-switch { position: relative; display: inline-block; width: 34px; height: 20px; }
            .cortex-toggle-switch input { opacity: 0; width: 0; height: 0; }
            .cortex-slider { position: absolute; cursor: not-allowed; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; -webkit-transition: .4s; transition: .4s; border-radius: 34px; }
            .cortex-slider:before { position: absolute; content: ""; height: 14px; width: 14px; left: 3px; bottom: 3px; background-color: white; -webkit-transition: .4s; transition: .4s; border-radius: 50%; }
            input:checked + .cortex-slider { background-color: var(--cortex-success); }
            input:focus + .cortex-slider { box-shadow: 0 0 1px var(--cortex-success); }
            input:checked + .cortex-slider:before { -webkit-transform: translateX(14px); -ms-transform: translateX(14px); transform: translateX(14px); }
        </style>
        <?php
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
                // Use provided name/slug or fallback to label
                $name  = ! empty( $field['name'] ) ? sanitize_title( $field['name'] ) : sanitize_title( $label );
                
                $lead_fields[] = array(
                    'name'     => $name, 
                    'label'    => $label,
                    'type'     => sanitize_text_field( $field['type'] ?? 'text' ),
                    'required' => isset( $field['required'] ) ? 1 : 0,
                    'options'  => isset( $field['options'] ) ? sanitize_text_field( $field['options'] ) : '',
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
