<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Frontend class for Cortex
 * - Registers [cortex_quiz id="123"]
 * - Renders student info form + questions
 * - Handles submission, scoring, DB write
 * - Displays results and (optionally) a PDF link (admin-only export)
 *
 * Place this file at: cortex/includes/class-cortex-frontend.php
 */

class Cortex_Frontend {

    public function __construct() {
        // Intentionally empty — loader will call init_hooks()
    }

    /**
     * Required by loader. Register all public hooks here.
     */
    public function init_hooks() {
        // [cortex_quiz] is now a legacy alias for [cortex-quiz]
        add_shortcode( 'cortex_quiz', [ $this, 'render_legacy_shortcode' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function enqueue_assets() {
        // Sync with Cortex_Shortcodes assets
        wp_enqueue_style( 'cortex-quiz', CORTEX_PLUGIN_URL . 'assets/css/cortex-public.css', array(), CORTEX_VERSION );
        wp_enqueue_script( 'cortex-quiz', CORTEX_PLUGIN_URL . 'assets/js/cortex-public.js', array( 'jquery' ), CORTEX_VERSION, true );
        
        wp_localize_script( 'cortex-quiz', 'Cortex_Public', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'cortex_public_nonce' ),
        ) );
    }

    /**
     * Legacy shortcode handler: [cortex_quiz id="123"]
     */
    public function render_legacy_shortcode( $atts ) {
        if ( class_exists( 'Cortex_Shortcodes' ) ) {
            $shortcodes = new Cortex_Shortcodes();
            return $shortcodes->render_quiz_shortcode( $atts );
        }
        return '<div class="cortex-error">Shortcode engine missing.</div>';
    }
}
