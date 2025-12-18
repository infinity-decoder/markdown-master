<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Markdown Renderer with Math and Code Support
 * 
 * Enhanced with KaTeX for LaTeX math and Prism.js for syntax highlighting.
 * 
 * @package MarkdownMaster
 * @since 2.0.0
 */
class MM_Markdown {

    protected $parsedown;

    public function __construct() {
        // Load Parsedown if available
        $parsedown_file = MM_PLUGIN_DIR . 'vendor/parsedown/Parsedown.php';
        if ( file_exists( $parsedown_file ) ) {
            require_once $parsedown_file;
            $this->parsedown = new Parsedown();
            // Enable GitHub-Flavored Markdown features
            $this->parsedown->setSafeMode( true ); // Security: escape HTML by default
        }
    }

    /**
     * Render markdown to HTML with math and code support
     * 
     * @param string $text Markdown text
     * @return string Rendered HTML
     */
    public function render_markdown( $text ) {
        if ( empty( $text ) ) {
            return '';
        }

        // If Parsedown is available, use it
        if ( $this->parsedown ) {
            $html = $this->parsedown->text( $text );
        } else {
            // Fallback: simple nl2br
            $html = nl2br( esc_html( $text ) );
        }

        // Detect and prepare for math rendering
        if ( class_exists( 'MM_Math_Renderer' ) && MM_Math_Renderer::has_math( $html ) ) {
            MM_Math_Renderer::maybe_enqueue( $html );
            $html = MM_Math_Renderer::prepare_math( $html );
        }

        // Detect and prepare for code highlighting
        if ( class_exists( 'MM_Highlighter' ) ) {
            MM_Highlighter::maybe_enqueue( $html );
        }

        return $html;
    }

    /**
     * Render with caching
     * 
     * @param string $text Markdown text
     * @param string $cache_key Cache key
     * @return string Rendered HTML
     */
    public function render_with_cache( $text, $cache_key = null ) {
        if ( null === $cache_key ) {
            $cache_key = 'md_' . md5( $text );
        }

        $cached = MM_Cache::get( $cache_key, MM_Cache::GROUP_MARKDOWN );

        if ( false !== $cached ) {
            // Still need to enqueue assets even from cache
            if ( class_exists( 'MM_Math_Renderer' ) ) {
                MM_Math_Renderer::maybe_enqueue( $cached );
            }
            if ( class_exists( 'MM_Highlighter' ) ) {
                MM_Highlighter::maybe_enqueue( $cached );
            }
            return $cached;
        }

        $html = $this->render_markdown( $text );
        MM_Cache::set( $cache_key, $html, MM_Cache::get_ttl(), MM_Cache::GROUP_MARKDOWN );

        return $html;
    }
}
