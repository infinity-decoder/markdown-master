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
        // Main menu
        add_menu_page(
            __( 'Markdown Master', 'markdown-master' ),
            __( 'Markdown Master', 'markdown-master' ),
            'manage_options',
            'markdown-master',
            [ $this, 'render_dashboard' ],
            'dashicons-welcome-write-blog',
            25
        );

        // Dashboard (same as main)
        add_submenu_page(
            'markdown-master',
            __( 'Dashboard', 'markdown-master' ),
            __( 'Dashboard', 'markdown-master' ),
            'manage_options',
            'markdown-master',
            [ $this, 'render_dashboard' ]
        );

        // Quizzes
        add_submenu_page(
            'markdown-master',
            __( 'Quizzes', 'markdown-master' ),
            __( 'Quizzes', 'markdown-master' ),
            'manage_options',
            'mm-quizzes',
            [ $this, 'render_quizzes' ]
        );

        // Notes
        add_submenu_page(
            'markdown-master',
            __( 'Notes', 'markdown-master' ),
            __( 'Notes', 'markdown-master' ),
            'manage_options',
            'mm-notes',
            [ $this, 'render_notes' ]
        );

        // Code Snippets
        add_submenu_page(
            'markdown-master',
            __( 'Code Snippets', 'markdown-master' ),
            __( 'Code Snippets', 'markdown-master' ),
            'manage_options',
            'mm-snippets',
            [ $this, 'render_snippets' ]
        );

        // Results
        add_submenu_page(
            'markdown-master',
            __( 'Results', 'markdown-master' ),
            __( 'Results', 'markdown-master' ),
            'manage_options',
            'mm-results',
            [ $this, 'render_results' ]
        );

        // Settings
        add_submenu_page(
            'markdown-master',
            __( 'Settings', 'markdown-master' ),
            __( 'Settings', 'markdown-master' ),
            'manage_options',
            'mm-settings',
            [ $this, 'render_settings' ]
        );

        // Import
        add_submenu_page(
            'markdown-master',
            __( 'Import', 'markdown-master' ),
            __( 'Import', 'markdown-master' ),
            'manage_options',
            'mm-import',
            [ $this, 'render_import' ]
        );

        // Export
        add_submenu_page(
            'markdown-master',
            __( 'Export', 'markdown-master' ),
            __( 'Export', 'markdown-master' ),
            'manage_options',
            'mm-export',
            [ $this, 'render_export' ]
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
     * Render quizzes page
     */
    public function render_quizzes() {
        include MM_ADMIN . 'mm-admin-quiz-form.php';
    }

    /**
     * Render notes page
     */
    public function render_notes() {
        include MM_ADMIN . 'mm-admin-note-form.php';
    }

    /**
     * Render snippets page
     */
    public function render_snippets() {
        include MM_ADMIN . 'mm-admin-snippet-form.php';
    }

    /**
     * Render results page
     */
    public function render_results() {
        include MM_ADMIN . 'mm-admin-results.php';
    }

    /**
     * Render settings page
     */
    public function render_settings() {
        include MM_ADMIN . 'mm-admin-settings.php';
    }

    /**
     * Render import page
     */
    public function render_import() {
        include MM_ADMIN . 'mm-admin-import.php';
    }

    /**
     * Render export page
     */
    public function render_export() {
        include MM_ADMIN . 'mm-admin-export.php';
    }
}
