<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Frontend class for Markdown Master
 * - Registers [mm_quiz id="123"]
 * - Renders student info form + questions
 * - Handles submission, scoring, DB write
 * - Displays results and (optionally) a PDF link (admin-only export)
 *
 * Place this file at: markdown-master/includes/class-mm-frontend.php
 */

class MM_Frontend {

    public function __construct() {
        // Intentionally empty — loader will call init_hooks()
    }

    /**
     * Required by loader. Register all public hooks here.
     */
    public function init_hooks() {
        // [mm_quiz] is now a legacy alias for [mm-quiz]
        add_shortcode( 'mm_quiz', [ $this, 'render_legacy_shortcode' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function enqueue_assets() {
        // Sync with MM_Shortcodes assets
        wp_enqueue_style( 'mm-quiz', MM_PLUGIN_URL . 'assets/css/mm-public.css', array(), MM_VERSION );
        wp_enqueue_script( 'mm-quiz', MM_PLUGIN_URL . 'assets/js/mm-public.js', array( 'jquery' ), MM_VERSION, true );
        
        wp_localize_script( 'mm-quiz', 'mmQuiz', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'mm_quiz_ajax' ),
        ) );
    }

    /**
     * Legacy shortcode handler: [mm_quiz id="123"]
     */
    public function render_legacy_shortcode( $atts ) {
        if ( class_exists( 'MM_Shortcodes' ) ) {
            $shortcodes = new MM_Shortcodes();
            return $shortcodes->render_quiz_shortcode( $atts );
        }
        return '<div class="mm-error">Shortcode engine missing.</div>';
    }
}
