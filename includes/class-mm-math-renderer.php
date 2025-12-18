<?php
/**
 * Math Renderer for Markdown Master
 * 
 * Wrapper for KaTeX library to render LaTeX math expressions.
 * 
 * @package MarkdownMaster
 * @since 2.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MM_Math_Renderer {

    /**
     * Enqueue KaTeX assets
     */
    public static function enqueue_assets() {
        $katex_url = MM_PLUGIN_URL . 'assets/libs/katex/';
        
        wp_enqueue_style(
            'mm-katex',
            $katex_url . 'katex.min.css',
            array(),
            '0.16.9'
        );
        
        wp_enqueue_script(
            'mm-katex',
            $katex_url . 'katex.min.js',
            array(),
            '0.16.9',
            true
        );
        
        wp_enqueue_script(
            'mm-katex-auto-render',
            $katex_url . 'auto-render.min.js',
            array( 'mm-katex' ),
            '0.16.9',
            true
        );
        
        // Initialize auto-render on page load
        wp_add_inline_script(
            'mm-katex-auto-render',
            "document.addEventListener('DOMContentLoaded', function() {
                renderMathInElement(document.body, {
                    delimiters: [
                        {left: '$$', right: '$$', display: true},
                        {left: '$', right: '$', display: false},
                        {left: '\\\\[', right: '\\\\]', display: true},
                        {left: '\\\\(', right: '\\\\)', display: false}
                    ],
                    throwOnError: false
                });
            });"
        );
    }

    /**
     * Check if content contains math expressions
     * 
     * @param string $content Content to check
     * @return bool True if math found
     */
    public static function has_math( $content ) {
        // Check for common LaTeX delimiters
        $patterns = array(
            '/\$\$.+?\$\$/s',       // $$...$$
            '/\$.+?\$/',             // $...$
            '/\\\\\[.+?\\\\\]/s',    // \[...\]
            '/\\\\\(.+?\\\\\)/',     // \(...\)
        );
        
        foreach ( $patterns as $pattern ) {
            if ( preg_match( $pattern, $content ) ) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Wrap math expressions for rendering
     * This prepares content for KaTeX auto-render
     * 
     * @param string $content Content with math
     * @return string Prepared content
     */
    public static function prepare_math( $content ) {
        // Content is already in LaTeX format, just ensure it's preserved
        // KaTeX auto-render will handle it on frontend
        return $content;
    }

    /**
     * Conditionally enqueue KaTeX only if math is detected
     * 
     * @param string $content Content to check
     */
    public static function maybe_enqueue( $content ) {
        static $enqueued = false;
        
        if ( ! $enqueued && self::has_math( $content ) ) {
            self::enqueue_assets();
            $enqueued = true;
        }
    }

    /**
     * Render math in content (server-side fallback)
     * Note: KaTeX is primarily client-side, but this provides a wrapper
     * 
     * @param string $content Content with math
     * @return string Content with math wrapped
     */
    public static function render( $content ) {
        // Enqueue assets for client-side rendering
        self::maybe_enqueue( $content );
        
        // Return content as-is; KaTeX will render client-side
        return $content;
    }
}
