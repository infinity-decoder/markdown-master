<?php
/**
 * Code Highlighter for Cortex
 * 
 * Wrapper for Prism.js library with conditional loading and language support.
 * 
 * @package MarkdownMaster
 * @since 2.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cortex_Highlighter {

    /**
     * Supported languages mapping
     */
    const LANGUAGES = array(
        'php'        => 'prism-php.min.js',
        'javascript' => 'prism-javascript.min.js',
        'js'         => 'prism-javascript.min.js',
        'python'     => 'prism-python.min.js',
        'py'         => 'prism-python.min.js',
        'css'        => 'prism-css.min.js',
        'sql'        => 'prism-sql.min.js',
    );

    /**
     * Enqueue Prism.js assets
     * 
     * @param array $languages Languages to load
     */
    public static function enqueue_assets( $languages = array() ) {
        $prism_url = CORTEX_PLUGIN_URL . 'assets/libs/prism/';
        
        // Core Prism
        wp_enqueue_style(
            'cortex-prism',
            $prism_url . 'prism.css',
            array(),
            '1.29.0'
        );
        
        wp_enqueue_script(
            'cortex-prism',
            $prism_url . 'prism.js',
            array(),
            '1.29.0',
            true
        );
        
        // Language-specific components
        if ( ! empty( $languages ) ) {
            foreach ( $languages as $lang ) {
                $lang = strtolower( sanitize_text_field( $lang ) );
                
                if ( isset( self::LANGUAGES[ $lang ] ) ) {
                    $handle = 'cortex-prism-' . $lang;
                    $file = self::LANGUAGES[ $lang ];
                    
                    wp_enqueue_script(
                        $handle,
                        $prism_url . $file,
                        array( 'cortex-prism' ),
                        '1.29.0',
                        true
                    );
                }
            }
        }
    }

    /**
     * Detect languages in content
     * 
     * @param string $content Content to check
     * @return array Detected languages
     */
    public static function detect_languages( $content ) {
        $languages = array();
        
        // Match code blocks with language specification
        // Matches: <code class="language-php">, class="lang-python", etc.
        preg_match_all( '/class=["\'](?:language-|lang-)([a-z0-9]+)["\']/i', $content, $matches );
        
        if ( ! empty( $matches[1] ) ) {
            $languages = array_unique( array_map( 'strtolower', $matches[1] ) );
        }
        
        return $languages;
    }

    /**
     * Conditionally enqueue Prism based on content
     * 
     * @param string $content Content to check
     */
    public static function maybe_enqueue( $content ) {
        static $enqueued = false;
        
        if ( ! $enqueued ) {
            $languages = self::detect_languages( $content );
            
            if ( ! empty( $languages ) || strpos( $content, '<code' ) !== false ) {
                self::enqueue_assets( $languages );
                $enqueued = true;
            }
        }
    }

    /**
     * Wrap code in Prism-compatible HTML
     * 
     * @param string $code Code content
     * @param string $language Language identifier
     * @return string Wrapped HTML
     */
    public static function wrap_code( $code, $language = 'text' ) {
        $language = strtolower( sanitize_text_field( $language ) );
        $code_escaped = esc_html( $code );
        
        return sprintf(
            '<pre><code class="language-%s">%s</code></pre>',
            esc_attr( $language ),
            $code_escaped
        );
    }

    /**
     * Legacy render_code method for backward compatibility
     */
    public function render_code( $code, $language = 'text' ) {
        return self::wrap_code( $code, $language );
    }

    /**
     * Get list of supported languages
     * 
     * @return array Language list
     */
    public static function get_supported_languages() {
        return array_keys( self::LANGUAGES );
    }

    /**
     * Check if language is supported
     * 
     * @param string $language Language identifier
     * @return bool True if supported
     */
    public static function is_supported( $language ) {
        $language = strtolower( sanitize_text_field( $language ) );
        return isset( self::LANGUAGES[ $language ] );
    }
}
