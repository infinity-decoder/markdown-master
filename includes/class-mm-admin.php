<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MM_Admin {

    public function init_hooks() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    /**
     * Register admin menu pages
     */
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
            __( 'Dashboard', 'markdown-master' ),
            __( 'Dashboard', 'markdown-master' ),
            'manage_options',
            'markdown-master',
            [ $this, 'render_dashboard' ]
        );

        add_submenu_page(
            'markdown-master',
            __( 'Quizzes', 'markdown-master' ),
            __( 'Quizzes', 'markdown-master' ),
            'manage_options',
            'mm-quizzes',
            [ $this, 'render_placeholder' ]
        );

        add_submenu_page(
            'markdown-master',
            __( 'Notes', 'markdown-master' ),
            __( 'Notes', 'markdown-master' ),
            'manage_options',
            'mm-notes',
            [ $this, 'render_placeholder' ]
        );

        add_submenu_page(
            'markdown-master',
            __( 'Code Snippets', 'markdown-master' ),
            __( 'Code Snippets', 'markdown-master' ),
            'manage_options',
            'mm-snippets',
            [ $this, 'render_placeholder' ]
        );

        add_submenu_page(
            'markdown-master',
            __( 'Results', 'markdown-master' ),
            __( 'Results', 'markdown-master' ),
            'manage_options',
            'mm-results',
            [ $this, 'render_placeholder' ]
        );

        add_submenu_page(
            'markdown-master',
            __( 'Settings', 'markdown-master' ),
            __( 'Settings', 'markdown-master' ),
            'manage_options',
            'mm-settings',
            [ $this, 'render_placeholder' ]
        );
    }

    /**
     * Enqueue admin CSS and JS
     */
    public function enqueue_admin_assets( $hook ) {
        if ( strpos( $hook, 'markdown-master' ) === false && strpos( $hook, 'mm-' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'mm-admin',
            MM_ASSETS . 'css/mm-admin.css',
            [],
            MM_VERSION
        );

        wp_enqueue_script(
            'mm-admin',
            MM_ASSETS . 'js/mm-admin.js',
            [ 'jquery' ],
            MM_VERSION,
            true
        );
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard() {
        include MM_ADMIN . 'mm-admin-dashboard.php';
    }

    /**
     * Temporary placeholder for menu items not yet implemented
     */
    public function render_placeholder() {
        echo '<div class="wrap"><h1>' . esc_html__( 'Coming Soon', 'markdown-master' ) . '</h1></div>';
    }
}
