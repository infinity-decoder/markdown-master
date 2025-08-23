<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin class for Markdown Master plugin
 * - Quizzes list (WP_List_Table)
 * - Results list (WP_List_Table)
 * - Settings (WP Settings API)
 * - CSV export and per-student PDF export
 *
 * Place this file at: markdown-master/includes/class-mm-admin.php
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Quizzes list table
 */
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
            'id'          => __( 'Quiz ID', 'markdown-master' ),
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
        if ( isset( $item[ $column_name ] ) ) {
            return $item[ $column_name ];
        }
        return '';
    }

    public function prepare_items() {
        global $wpdb;
        $quiz_table      = $wpdb->prefix . 'mm_quizzes';
        $questions_table = $wpdb->prefix . 'mm_questions';
        $attempts_table  = $wpdb->prefix . 'mm_attempts';

        $rows = $wpdb->get_results( "SELECT * FROM {$quiz_table} ORDER BY id DESC", ARRAY_A );
        $data = [];

        if ( $rows ) {
            foreach ( $rows as $row ) {
                $qcount = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$questions_table} WHERE quiz_id = %d", $row['id'] ) );
                $acount = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$attempts_table} WHERE quiz_id = %d", $row['id'] ) );

                $actions = sprintf(
                    '<a href="%s">%s</a> | <a href="%s" onclick="return confirm(\'%s\')">%s</a> | <a href="%s">%s</a>',
                    esc_url( add_query_arg( [ 'page' => 'mm_quizzes', 'action' => 'edit', 'id' => $row['id'] ], admin_url( 'admin.php' ) ) ),
                    esc_html__( 'Edit', 'markdown-master' ),
                    esc_url( wp_nonce_url( add_query_arg( [ 'page' => 'mm_quizzes', 'action' => 'delete', 'id' => $row['id'] ], admin_url( 'admin.php' ) ), 'mm_delete_quiz_' . $row['id'] ) ),
                    esc_html__( 'Are you sure you want to delete this quiz?', 'markdown-master' ),
                    esc_html__( 'Delete', 'markdown-master' ),
                    esc_url( add_query_arg( [ 'page' => 'mm_quizzes', 'action' => 'export', 'id' => $row['id'] ], admin_url( 'admin.php' ) ) ),
                    esc_html__( 'Export', 'markdown-master' )
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

/**
 * Results list table (attempts)
 */
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
            'cb'              => '<input type="checkbox" />',
            'student_name'    => __( 'Student Name', 'markdown-master' ),
            'student_roll'    => __( 'Roll No', 'markdown-master' ),
            'student_class'   => __( 'Class', 'markdown-master' ),
            'student_section' => __( 'Section', 'markdown-master' ),
            'student_school'  => __( 'School', 'markdown-master' ),
            'obtained_marks'  => __( 'Obtained Marks', 'markdown-master' ),
            'total_marks'     => __( 'Total Marks', 'markdown-master' ),
            'created_at'      => __( 'Date', 'markdown-master' ),
            'actions'         => __( 'Actions', 'markdown-master' ),
        ];
    }

    public function column_cb( $item ) {
        return sprintf( '<input type="checkbox" name="bulk_attempts[]" value="%d" />', $item->id );
    }

    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'student_name':
                return esc_html( $item->student_name );
            case 'student_roll':
                return esc_html( $item->student_roll );
            case 'student_class':
                return esc_html( $item->student_class );
            case 'student_section':
                return esc_html( $item->student_section );
            case 'student_school':
                return esc_html( $item->student_school );
            case 'obtained_marks':
                return esc_html( $item->obtained_marks );
            case 'total_marks':
                return esc_html( $item->total_marks );
            case 'created_at':
                return esc_html( $item->created_at );
            case 'actions':
                $pdf_url  = wp_nonce_url( add_query_arg( [ 'page' => 'mm_results', 'action' => 'pdf', 'attempt_id' => $item->id ], admin_url( 'admin.php' ) ), 'mm_pdf_' . $item->id );
                $view_url = esc_url( add_query_arg( [ 'page' => 'mm_results', 'action' => 'view', 'attempt_id' => $item->id ], admin_url( 'admin.php' ) ) );
                return sprintf(
                    '<a href="%s" target="_blank">%s</a> | <a href="%s">%s</a>',
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

        $per_page     = 20;
        $current_page = $this->get_pagenum();
        $offset       = ( $current_page - 1 ) * $per_page;

        if ( $this->quiz_id <= 0 ) {
            $this->items = [];
            $this->set_pagination_args( [ 'total_items' => 0, 'per_page' => $per_page ] );
            return;
        }

        $total_items = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$attempts_table} WHERE quiz_id = %d", $this->quiz_id ) );
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$attempts_table} WHERE quiz_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $this->quiz_id,
                $per_page,
                $offset
            )
        );

        $this->items = $rows;
        $this->set_pagination_args( [ 'total_items' => $total_items, 'per_page' => $per_page ] );
    }
}

/**
 * Main admin class (quizzes, results, settings)
 */
class MM_Admin {

    /**
     * Constructor must be light — loader calls ->init_hooks()
     */
    public function __construct() {
        // Intentionally empty. Do not hook here — loader calls init_hooks()
    }

    /**
     * Required by loader. Register all admin hooks here.
     */
    public function init_hooks() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );

        // Admin-post action handlers
        add_action( 'admin_post_mm_save_quiz',        [ $this, 'handle_save_quiz' ] );
        add_action( 'admin_post_mm_delete_quiz',      [ $this, 'handle_delete_quiz' ] );
        add_action( 'admin_post_mm_export_results',   [ $this, 'handle_export_results' ] );
        add_action( 'admin_post_mm_export_attempt_pdf', [ $this, 'handle_export_attempt_pdf' ] );
    }

    public function register_menu() {
        add_menu_page(
            __( 'Markdown Master', 'markdown-master' ),
            __( 'Markdown Master', 'markdown-master' ),
            'manage_options',
            'markdown-master',
            [ $this, 'render_dashboard' ],
            'dashicons-welcome-write-blog',
            25
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

    public function register_settings() {
        register_setting( 'mm_settings_group', 'mm_settings', [ $this, 'sanitize_settings' ] );

        add_settings_section(
            'mm_defaults',
            __( 'Quiz Defaults', 'markdown-master' ),
            function () { echo '<p>' . __( 'Set defaults for new quizzes.', 'markdown-master' ) . '</p>'; },
            'mm_settings'
        );

        add_settings_field( 'mm_default_shuffle',        __( 'Shuffle Questions', 'markdown-master' ), [ $this, 'field_default_shuffle' ],      'mm_settings', 'mm_defaults' );
        add_settings_field( 'mm_default_time_limit',     __( 'Default Time Limit (minutes)', 'markdown-master' ), [ $this, 'field_default_time_limit' ], 'mm_settings', 'mm_defaults' );
        add_settings_field( 'mm_default_attempts',       __( 'Default Attempts Allowed', 'markdown-master' ), [ $this, 'field_default_attempts' ], 'mm_settings', 'mm_defaults' );
        add_settings_field( 'mm_default_show_answers',   __( 'Show Answers By Default', 'markdown-master' ), [ $this, 'field_default_show_answers' ], 'mm_settings', 'mm_defaults' );
        add_settings_field( 'mm_enable_pdf',             __( 'Enable PDF Export', 'markdown-master' ), [ $this, 'field_enable_pdf' ],          'mm_settings', 'mm_defaults' );
    }

    public function sanitize_settings( $input ) {
        $out = [];
        $out['shuffle']          = isset( $input['shuffle'] ) ? ( $input['shuffle'] ? 1 : 0 ) : 0;
        $out['time_limit']       = isset( $input['time_limit'] ) ? intval( $input['time_limit'] ) : 0;
        $out['attempts_allowed'] = isset( $input['attempts_allowed'] ) ? intval( $input['attempts_allowed'] ) : 0;
        $out['show_answers']     = isset( $input['show_answers'] ) ? sanitize_text_field( $input['show_answers'] ) : 'end';
        $out['enable_pdf']       = isset( $input['enable_pdf'] ) ? ( $input['enable_pdf'] ? 1 : 0 ) : 0;
        return $out;
    }

    // Settings callbacks
    public function field_default_shuffle() {
        $opts = $this->get_settings();
        $val = isset( $opts['shuffle'] ) ? $opts['shuffle'] : 0;
        echo '<input type="checkbox" name="mm_settings[shuffle]" value="1" ' . checked( 1, $val, false ) . '>';
    }
    public function field_default_time_limit() {
        $opts = $this->get_settings();
        $val = isset( $opts['time_limit'] ) ? intval( $opts['time_limit'] ) : 0;
        echo '<input type="number" name="mm_settings[time_limit]" value="' . esc_attr( $val ) . '" min="0">';
    }
    public function field_default_attempts() {
        $opts = $this->get_settings();
        $val = isset( $opts['attempts_allowed'] ) ? intval( $opts['attempts_allowed'] ) : 0;
        echo '<input type="number" name="mm_settings[attempts_allowed]" value="' . esc_attr( $val ) . '" min="0">';
        echo '<p class="description">' . __( '0 means unlimited attempts.', 'markdown-master' ) . '</p>';
    }
    public function field_default_show_answers() {
        $opts = $this->get_settings();
        $val = isset( $opts['show_answers'] ) ? $opts['show_answers'] : 'end';
        echo '<select name="mm_settings[show_answers]">';
        echo '<option value="end"' . selected( 'end', $val, false ) . '>' . esc_html__( 'Show at end of quiz', 'markdown-master' ) . '</option>';
        echo '<option value="instant"' . selected( 'instant', $val, false ) . '>' . esc_html__( 'Show answers immediately', 'markdown-master' ) . '</option>';
        echo '<option value="never"' . selected( 'never', $val, false ) . '>' . esc_html__( 'Never show answers', 'markdown-master' ) . '</option>';
        echo '</select>';
    }
    public function field_enable_pdf() {
        $opts = $this->get_settings();
        $val = isset( $opts['enable_pdf'] ) ? $opts['enable_pdf'] : 0;
        echo '<input type="checkbox" name="mm_settings[enable_pdf]" value="1" ' . checked( 1, $val, false ) . '>';
        echo '<p class="description">' . __( 'Enable per-student PDF export (requires Dompdf installed in vendor/)', 'markdown-master' ) . '</p>';
    }

    public function get_settings() {
        $opts = get_option( 'mm_settings', array() );
        if ( is_string( $opts ) && function_exists( 'is_serialized' ) && is_serialized( $opts ) ) {
            $opts = maybe_unserialize( $opts );
        }
        if ( ! is_array( $opts ) ) {
            $opts = (array) $opts;
        }
        return $opts;
    }

    /***********************
     * Render Pages
     ************************/

    public function render_dashboard() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Markdown Master Dashboard', 'markdown-master' ); ?></h1>
            <p><?php esc_html_e( 'Manage quizzes, results and settings from here.', 'markdown-master' ); ?></p>
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
        $id     = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

        if ( $action === 'edit' && $id ) {
            $this->render_quiz_form( $id );
            return;
        }
        if ( $action === 'new' ) {
            $this->render_quiz_form();
            return;
        }
        if ( $action === 'export' && $id ) {
            // Soft redirect to admin-post export if you wire one; otherwise just show notice.
            echo '<div class="notice notice-info"><p>' . esc_html__( 'Use Import/Export page to export full quiz data.', 'markdown-master' ) . '</p></div>';
        }

        echo '<div class="wrap"><h1>' . esc_html__( 'Quizzes', 'markdown-master' ) . ' <a href="' . esc_url( add_query_arg( [ 'page' => 'mm_quizzes', 'action' => 'new' ], admin_url( 'admin.php' ) ) ) . '" class="page-title-action">' . esc_html__( 'Add New', 'markdown-master' ) . '</a></h1>';
        $table = new MM_Admin_Quizzes_Table();
        $table->prepare_items();
        $table->display();
        echo '</div>';
    }

    /**
     * Render quiz add/edit form (keeps simple fields)
     */
    public function render_quiz_form( $id = 0 ) {
        global $wpdb;
        $quiz_table = $wpdb->prefix . 'mm_quizzes';

        $quiz = [
            'id'               => 0,
            'title'            => '',
            'description'      => '',
            'settings'         => maybe_serialize( [] ),
            // Legacy columns support (if present in schema)
            'shuffle'          => 0,
            'time_limit'       => 0,
            'attempts_allowed' => 0,
            'show_answers'     => 0,
        ];

        if ( $id > 0 ) {
            $q = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$quiz_table} WHERE id = %d", $id ), ARRAY_A );
            if ( $q ) {
                $quiz = array_merge( $quiz, $q );
            }
        }

        // Merge serialized settings into surface fields for editing
        $settings = [];
        if ( isset( $quiz['settings'] ) ) {
            $maybe = is_string( $quiz['settings'] ) ? maybe_unserialize( $quiz['settings'] ) : $quiz['settings'];
            if ( is_string( $maybe ) ) {
                $maybe = json_decode( $maybe, true );
            }
            if ( is_array( $maybe ) ) {
                $settings = $maybe;
            }
        }
        $quiz['shuffle']          = isset( $settings['shuffle'] ) ? (int) $settings['shuffle'] : (int) $quiz['shuffle'];
        $quiz['time_limit']       = isset( $settings['time_limit'] ) ? (int) $settings['time_limit'] : (int) $quiz['time_limit'];
        $quiz['attempts_allowed'] = isset( $settings['attempts_allowed'] ) ? (int) $settings['attempts_allowed'] : (int) $quiz['attempts_allowed'];
        $quiz['show_answers']     = isset( $settings['show_answers'] ) ? (int) $settings['show_answers'] : (int) $quiz['show_answers'];

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

            <hr>

            <h2><?php esc_html_e( 'Questions', 'markdown-master' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Question manager will be available in next steps: add, edit, import/export questions.', 'markdown-master' ); ?></p>
        </div>
        <?php
    }

    /**
     * Results page: filter by quiz, show attempts table with export buttons
     */
    public function render_results_page() {
        global $wpdb;
        $quiz_table = $wpdb->prefix . 'mm_quizzes';

        if ( isset( $_GET['mm_msg'] ) ) {
            $msg = sanitize_text_field( $_GET['mm_msg'] );
            echo '<div class="notice notice-success"><p>' . esc_html( $msg ) . '</p></div>';
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Quiz Results', 'markdown-master' ); ?></h1>

            <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
                <input type="hidden" name="page" value="mm_results">
                <label for="filter_quiz"><?php esc_html_e( 'Select Quiz:', 'markdown-master' ); ?></label>
                <select id="filter_quiz" name="quiz_id">
                    <option value="0"><?php esc_html_e( '-- Select Quiz --', 'markdown-master' ); ?></option>
                    <?php
                    $current_q = isset( $_GET['quiz_id'] ) ? intval( $_GET['quiz_id'] ) : 0;
                    $quizzes = $wpdb->get_results( "SELECT id, title FROM {$quiz_table} ORDER BY id DESC" );
                    foreach ( $quizzes as $q ) {
                        printf(
                            '<option value="%1$d"%2$s>%3$s</option>',
                            intval( $q->id ),
                            selected( $current_q, intval( $q->id ), false ),
                            esc_html( $q->title )
                        );
                    }
                    ?>
                </select>

                <input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'markdown-master' ); ?>">
                <?php if ( isset( $_GET['quiz_id'] ) && intval( $_GET['quiz_id'] ) > 0 ) : ?>
                    <form method="post" style="display:inline;" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="mm_export_results">
                        <input type="hidden" name="quiz_id" value="<?php echo esc_attr( intval( $_GET['quiz_id'] ) ); ?>">
                        <?php wp_nonce_field( 'mm_export_results', 'mm_export_results_nonce' ); ?>
                        <button type="submit" class="button"><?php esc_html_e( 'Export CSV', 'markdown-master' ); ?></button>
                    </form>
                <?php endif; ?>
            </form>

            <?php
            $quiz_id = isset( $_GET['quiz_id'] ) ? intval( $_GET['quiz_id'] ) : 0;
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

    /**********************
     * Action Handlers
     **********************/

    public function handle_save_quiz() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'markdown-master' ) );
        }
        check_admin_referer( 'mm_save_quiz_nonce', 'mm_save_quiz_nonce_field' );

        global $wpdb;
        $quiz_table = $wpdb->prefix . 'mm_quizzes';

        $id               = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
        $title            = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $description      = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';
        $shuffle          = isset( $_POST['shuffle'] ) ? 1 : 0;
        $time_limit       = isset( $_POST['time_limit'] ) ? intval( $_POST['time_limit'] ) : 0;
        $attempts_allowed = isset( $_POST['attempts_allowed'] ) ? intval( $_POST['attempts_allowed'] ) : 0;
        $show_answers     = isset( $_POST['show_answers'] ) ? 1 : 0;

        // Store (and keep backward compatible with existing "settings" usage)
        $settings = maybe_serialize( [
            'shuffle'          => $shuffle,
            'time_limit'       => $time_limit,
            'attempts_allowed' => $attempts_allowed,
            'show_answers'     => $show_answers,
        ] );

        if ( $id > 0 ) {
            $wpdb->update(
                $quiz_table,
                [
                    'title'       => $title,
                    'description' => $description,
                    'settings'    => $settings,
                    'updated_at'  => current_time( 'mysql' ),
                ],
                [ 'id' => $id ],
                [ '%s', '%s', '%s', '%s' ],
                [ '%d' ]
            );
        } else {
            $wpdb->insert(
                $quiz_table,
                [
                    'title'       => $title,
                    'description' => $description,
                    'settings'    => $settings,
                    'created_at'  => current_time( 'mysql' ),
                    'updated_at'  => current_time( 'mysql' ),
                ],
                [ '%s', '%s', '%s', '%s', '%s' ]
            );
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

        if ( class_exists( 'MM_Quiz' ) ) {
            $model = new MM_Quiz();
            $model->delete_quiz( $id );
        } else {
            global $wpdb;
            $wpdb->delete( $wpdb->prefix . 'mm_quizzes', [ 'id' => $id ], [ '%d' ] );
            $wpdb->delete( $wpdb->prefix . 'mm_questions', [ 'quiz_id' => $id ], [ '%d' ] );
            $attempts = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}mm_attempts WHERE quiz_id = %d", $id ) );
            if ( $attempts ) {
                foreach ( $attempts as $a ) {
                    $wpdb->delete( $wpdb->prefix . 'mm_attempt_answers', [ 'attempt_id' => $a ], [ '%d' ] );
                }
                $wpdb->delete( $wpdb->prefix . 'mm_attempts', [ 'quiz_id' => $id ], [ '%d' ] );
            }
        }

        wp_safe_redirect( admin_url( 'admin.php?page=mm_quizzes&mm_msg=' . urlencode( __( 'Quiz deleted.', 'markdown-master' ) ) ) );
        exit;
    }

    /**
     * Export CSV of attempts for a quiz
     */
    public function handle_export_results() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'markdown-master' ) );
        }
        check_admin_referer( 'mm_export_results', 'mm_export_results_nonce' );

        $quiz_id = isset( $_POST['quiz_id'] ) ? intval( $_POST['quiz_id'] ) : 0;
        if ( $quiz_id <= 0 ) {
            wp_safe_redirect( admin_url( 'admin.php?page=mm_results&mm_msg=' . urlencode( __( 'Invalid quiz selected', 'markdown-master' ) ) ) );
            exit;
        }

        if ( class_exists( 'MM_Quiz' ) ) {
            $model   = new MM_Quiz();
            $results = $model->get_attempts_by_quiz( $quiz_id, -1, 0 ); // all
        } else {
            global $wpdb;
            $results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mm_attempts WHERE quiz_id = %d ORDER BY created_at DESC", $quiz_id ) );
        }

        $filename = 'mm_results_quiz_' . $quiz_id . '_' . date( 'Y-m-d_H-i-s' ) . '.csv';
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . $filename );

        $output = fopen( 'php://output', 'w' );
        fputcsv( $output, [ 'Student Name', 'Roll No', 'Class', 'Section', 'School', 'Obtained Marks', 'Total Marks', 'Date' ] );

        if ( $results ) {
            foreach ( $results as $r ) {
                $name   = isset( $r->student_name ) ? $r->student_name : ( isset( $r['student_name'] ) ? $r['student_name'] : '' );
                $roll   = isset( $r->student_roll ) ? $r->student_roll : ( isset( $r['student_roll'] ) ? $r['student_roll'] : '' );
                $class  = isset( $r->student_class ) ? $r->student_class : ( isset( $r['student_class'] ) ? $r['student_class'] : '' );
                $section= isset( $r->student_section ) ? $r->student_section : ( isset( $r['student_section'] ) ? $r['student_section'] : '' );
                $school = isset( $r->student_school ) ? $r->student_school : ( isset( $r['student_school'] ) ? $r['student_school'] : '' );
                $obt    = isset( $r->obtained_marks ) ? $r->obtained_marks : ( isset( $r['obtained_marks'] ) ? $r['obtained_marks'] : 0 );
                $total  = isset( $r->total_marks ) ? $r->total_marks : ( isset( $r['total_marks'] ) ? $r['total_marks'] : 0 );
                $date   = isset( $r->created_at ) ? $r->created_at : ( isset( $r['created_at'] ) ? $r['created_at'] : '' );
                fputcsv( $output, [ $name, $roll, $class, $section, $school, $obt, $total, $date ] );
            }
        }

        fclose( $output );
        exit;
    }

    /**
     * Export a single attempt to PDF (admin-only)
     */
    public function handle_export_attempt_pdf() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'markdown-master' ) );
        }
        $attempt_id = isset( $_REQUEST['attempt_id'] ) ? intval( $_REQUEST['attempt_id'] ) : 0;
        if ( $attempt_id <= 0 ) {
            wp_safe_redirect( admin_url( 'admin.php?page=mm_results&mm_msg=' . urlencode( __( 'Invalid attempt id', 'markdown-master' ) ) ) );
            exit;
        }

        $nonce_check = $_REQUEST['_wpnonce'] ?? '';
        if ( empty( $nonce_check ) || ! wp_verify_nonce( $nonce_check, 'mm_pdf_' . $attempt_id ) ) {
            wp_die( __( 'Nonce check failed.', 'markdown-master' ) );
        }

        if ( ! class_exists( 'MM_Quiz' ) ) {
            $model_file = dirname( __FILE__ ) . '/class-mm-quiz.php';
            if ( file_exists( $model_file ) ) {
                require_once $model_file;
            }
        }
        if ( ! class_exists( 'MM_Quiz' ) ) {
            wp_die( __( 'Quiz model not found.', 'markdown-master' ) );
        }

        $model   = new MM_Quiz();
        $attempt = $model->get_attempt( $attempt_id );
        if ( ! $attempt ) {
            wp_die( __( 'Attempt not found.', 'markdown-master' ) );
        }

        $quiz = $model->get_quiz( intval( $attempt['quiz_id'] ), true );
        if ( ! $quiz ) {
            wp_die( __( 'Quiz not found.', 'markdown-master' ) );
        }

        $autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
        if ( ! file_exists( $autoload ) ) {
            wp_die( __( 'Dompdf (vendor) is not installed. Install dompdf via composer to enable PDF export.', 'markdown-master' ) );
        }
        require_once $autoload;
        if ( ! class_exists( 'Dompdf\\Dompdf' ) ) {
            wp_die( __( 'Dompdf class not found. Ensure dompdf is installed correctly.', 'markdown-master' ) );
        }

        ob_start();
        ?>
        <html><head><meta charset="utf-8"><title><?php echo esc_html( $quiz['title'] ); ?></title></head><body>
        <h1><?php echo esc_html( $quiz['title'] ); ?></h1>
        <p><strong><?php esc_html_e( 'Student:', 'markdown-master' ); ?></strong> <?php echo esc_html( $attempt['student_name'] ); ?></p>
        <p><strong><?php esc_html_e( 'Roll:', 'markdown-master' ); ?></strong> <?php echo esc_html( $attempt['student_roll'] ); ?></p>
        <p><strong><?php esc_html_e( 'Score:', 'markdown-master' ); ?></strong> <?php echo esc_html( $attempt['obtained_marks'] . ' / ' . $attempt['total_marks'] ); ?></p>
        <hr>
        <?php
        $answers = $attempt['answers'];
        foreach ( $quiz['questions'] as $q ) {
            $qid     = intval( $q['id'] );
            $qtext   = wp_strip_all_tags( $q['question_text'] );
            $correct = maybe_unserialize( $q['correct_answer'] );
            $given   = isset( $answers[ $qid ] ) ? $answers[ $qid ] : null;
            echo '<div style="margin-bottom:10px;">';
            echo '<div><strong>' . esc_html( $qtext ) . '</strong></div>';
            echo '<div>' . esc_html__( 'Student Answer:', 'markdown-master' ) . ' ' . ( is_array( $given ) ? esc_html( implode( ', ', (array) $given ) ) : esc_html( (string) $given ) ) . '</div>';
            echo '<div>' . esc_html__( 'Correct Answer:', 'markdown-master' ) . ' ' . ( is_array( $correct ) ? esc_html( implode( ', ', (array) $correct ) ) : esc_html( (string) $correct ) ) . '</div>';
            echo '</div>';
        }
        ?>
        </body></html>
        <?php
        $html = ob_get_clean();

        try {
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml( $html );
            $dompdf->setPaper( 'A4', 'portrait' );
            $dompdf->render();
            $output = $dompdf->output();

            header( 'Content-Type: application/pdf' );
            header( 'Content-Disposition: attachment; filename="mm_attempt_' . $attempt_id . '.pdf"' );
            echo $output;
            exit;
        } catch ( \Exception $e ) {
            wp_die( __( 'PDF generation failed: ', 'markdown-master' ) . $e->getMessage() );
        }
    }
}

// Important: instantiate admin (loader expects init_hooks())
new MM_Admin();
