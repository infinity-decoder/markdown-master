<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MM_Frontend {

    public function init_hooks() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_public_assets' ] );
    }

    /**
     * Enqueue public CSS and JS
     */
    public function enqueue_public_assets() {
        // Main public styles
        wp_enqueue_style(
            'mm-public',
            MM_PUBLIC . 'css/mm-public.css',
            [],
            MM_VERSION
        );

        // Highlight.js theme
        wp_enqueue_style(
            'highlight-js-theme',
            MM_PUBLIC . 'css/highlight.css',
            [],
            MM_VERSION
        );

        // Highlight.js script
        wp_enqueue_script(
            'highlight-js',
            MM_PUBLIC . 'js/highlight.js',
            [],
            MM_VERSION,
            true
        );

        // Main public JS
        wp_enqueue_script(
            'mm-public',
            MM_PUBLIC . 'js/mm-public.js',
            [ 'jquery' ],
            MM_VERSION,
            true
        );

        // Initialize highlighting
        wp_add_inline_script(
            'highlight-js',
            'document.addEventListener("DOMContentLoaded",function(){if(window.hljs){hljs.highlightAll();}});'
        );
    }
}
