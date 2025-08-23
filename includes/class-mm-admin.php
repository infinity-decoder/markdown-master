<?php
/**
 * Admin Dashboard for Markdown Master (Quizzes)
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class MM_Admin_Quizzes_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'quiz',
            'plural'   => 'quizzes',
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        return [
            'id'          => 'Quiz ID',
            'title'       => 'Title',
            'description' => 'Description',
            'questions'   => 'Questions',
            'attempts'    => 'Attempts',
            'actions'     => 'Actions',
        ];
    }

    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'id':
            case 'title':
            case 'description':
            case 'questions':
            case 'attempts':
            case 'actions':
                return $item[$column_name];
            default:
                return '';
        }
    }

    public function prepare_items() {
        global $wpdb;
        $quiz_table     = $wpdb->prefix . 'mm_quizzes';
        $questions_table = $wpdb->prefix . 'mm_questions';
        $attempts_table  = $wpdb->prefix . 'mm_attempts';

        $rows = $wpdb->get_results("SELECT * FROM {$quiz_table}", ARRAY_A);
        $data = [];

        foreach ( $rows as $row ) {
            $questions_count = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$questions_table} WHERE quiz_id = %d", $row['id']
            ));
            $attempts_count = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$attempts_table} WHERE quiz_id = %d", $row['id']
            ));

            $actions = sprintf(
                '<a href="?page=mm_quizzes&action=edit&id=%d">Edit</a> | 
                 <a href="?page=mm_quizzes&action=delete&id=%d" onclick="return confirm(\'Are you sure?\')">Delete</a> | 
                 <a href="?page=mm_quizzes&action=export&id=%d">Export</a>',
                $row['id'], $row['id'], $row['id']
            );

            $data[] = [
                'id'          => $row['id'],
                'title'       => esc_html( $row['title'] ),
                'description' => esc_html( $row['description'] ),
                'questions'   => $questions_count,
                'attempts'    => $attempts_count,
                'actions'     => $actions,
            ];
        }

        $this->items = $data;
        $this->_column_headers = [$this->get_columns(), [], []];
    }
}


class MM_Admin {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menus' ] );
        add_action( 'admin_post_mm_save_quiz', [ $this, 'save_quiz' ] );
        add_action( 'admin_post_mm_delete_quiz', [ $this, 'delete_quiz' ] );
    }

    public function register_menus() {
        add_menu_page(
            'Markdown Master',
            'Markdown Master',
            'manage_options',
            'markdown-master',
            [ $this, 'dashboard_page' ],
            'dashicons-welcome-write-blog'
        );

        add_submenu_page(
            'markdown-master',
            'Quizzes',
            'Quizzes',
            'manage_options',
            'mm_quizzes',
            [ $this, 'quizzes_page' ]
        );
    }

    public function dashboard_page() {
        echo '<div class="wrap"><h1>Markdown Master Dashboard</h1>';
        echo '<p>Welcome to Markdown Master. Use the sections below to manage quizzes, markdown notes, and code snippets.</p>';
        echo '</div>';
    }

    public function quizzes_page() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';

        if ( $action === 'edit' ) {
            $this->render_quiz_form( intval($_GET['id']) );
        } elseif ( $action === 'new' ) {
            $this->render_quiz_form();
        } else {
            $this->render_quizzes_list();
        }
    }

    private function render_quizzes_list() {
        echo '<div class="wrap"><h1>Quizzes <a href="?page=mm_quizzes&action=new" class="page-title-action">Add New</a></h1>';

        $table = new MM_Admin_Quizzes_Table();
        $table->prepare_items();
        $table->display();

        echo '</div>';
    }

    private function render_quiz_form( $id = 0 ) {
        global $wpdb;
        $quiz_table = $wpdb->prefix . 'mm_quizzes';

        $quiz = [
            'id'              => 0,
            'title'           => '',
            'description'     => '',
            'shuffle'         => 0,
            'time_limit'      => 0,
            'attempts_allowed'=> 1,
            'show_answers'    => 0
        ];

        if ( $id > 0 ) {
            $row = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$quiz_table} WHERE id = %d", $id), ARRAY_A );
            if ( $row ) {
                $quiz = $row;
            }
        }

        ?>
        <div class="wrap">
            <h1><?php echo $id ? 'Edit Quiz' : 'Add New Quiz'; ?></h1>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="mm_save_quiz">
                <input type="hidden" name="id" value="<?php echo intval($quiz['id']); ?>">

                <table class="form-table">
                    <tr>
                        <th><label for="title">Title</label></th>
                        <td><input type="text" name="title" value="<?php echo esc_attr($quiz['title']); ?>" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="description">Description</label></th>
                        <td><textarea name="description" rows="4" class="large-text"><?php echo esc_textarea($quiz['description']); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="shuffle">Shuffle Questions?</label></th>
                        <td><input type="checkbox" name="shuffle" value="1" <?php checked($quiz['shuffle'], 1); ?>></td>
                    </tr>
                    <tr>
                        <th><label for="time_limit">Time Limit (minutes)</label></th>
                        <td><input type="number" name="time_limit" value="<?php echo esc_attr($quiz['time_limit']); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="attempts_allowed">Attempts Allowed</label></th>
                        <td><input type="number" name="attempts_allowed" value="<?php echo esc_attr($quiz['attempts_allowed']); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="show_answers">Show Answers?</label></th>
                        <td><input type="checkbox" name="show_answers" value="1" <?php checked($quiz['show_answers'], 1); ?>></td>
                    </tr>
                </table>

                <h2>Questions</h2>
                <p><a href="#" class="button">Add Question</a> 
                   <a href="#" class="button">Import Questions</a> 
                   <a href="#" class="button">Export Questions</a></p>
                <p><em>(Questions management will be implemented in Step 3 and Step 6)</em></p>

                <?php submit_button( $id ? 'Update Quiz' : 'Create Quiz' ); ?>
            </form>
        </div>
        <?php
    }

    public function save_quiz() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        global $wpdb;
        $quiz_table = $wpdb->prefix . 'mm_quizzes';

        $data = [
            'title'            => sanitize_text_field($_POST['title']),
            'description'      => sanitize_textarea_field($_POST['description']),
            'shuffle'          => isset($_POST['shuffle']) ? 1 : 0,
            'time_limit'       => intval($_POST['time_limit']),
            'attempts_allowed' => intval($_POST['attempts_allowed']),
            'show_answers'     => isset($_POST['show_answers']) ? 1 : 0,
        ];

        if ( intval($_POST['id']) > 0 ) {
            $wpdb->update($quiz_table, $data, ['id' => intval($_POST['id'])]);
        } else {
            $wpdb->insert($quiz_table, $data);
        }

        wp_redirect(admin_url('admin.php?page=mm_quizzes'));
        exit;
    }

    public function delete_quiz() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        global $wpdb;
        $quiz_table = $wpdb->prefix . 'mm_quizzes';
        $wpdb->delete($quiz_table, ['id' => intval($_GET['id'])]);

        wp_redirect(admin_url('admin.php?page=mm_quizzes'));
        exit;
    }
}

new MM_Admin();
