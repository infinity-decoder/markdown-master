<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Cortex_Keyword_Detector
 *
 * Detect custom inline keywords at the start and end of a line and convert to markdown/code blocks.
 *
 * Rules implemented:
 * - Lines wrapped like: @@code some code @@code  -> converted to a code block
 * - Lines wrapped like: @@md some markdown @@md  -> converted to rendered markdown
 * - Existing fenced blocks (``` or ~~~) are untouched (Parsedown handles them)
 */
class Cortex_Keyword_Detector {

    public function __construct() {
        add_filter( 'the_content', [ $this, 'detect_keywords_in_content' ], 9 ); // run before other markdown renderers
    }

    public function detect_keywords_in_content( $content ) {
        if ( empty( $content ) || ! is_string( $content ) ) {
            return $content;
        }

        // 1) Handle @@code ... @@code on single lines (treat inner text as raw code)
        $content = preg_replace_callback( '/@@code\s*(.*?)\s*@@code/s', function( $m ) {
            $inner = $m[1];
            $inner = trim( $inner );
            $inner_esc = htmlspecialchars( $inner, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8' );
            return "<pre class=\"cortex-inline-code\"><code>{$inner_esc}</code></pre>";
        }, $content );

        // 2) Handle @@md ... @@md (render inner via our markdown renderer)
        $content = preg_replace_callback( '/@@md\s*(.*?)\s*@@md/s', function( $m ) {
            $inner = $m[1];
            // use our markdown renderer
            if ( ! class_exists( 'Cortex_Markdown' ) ) {
                require_once CORTEX_INCLUDES . 'class-cortex-markdown.php';
            }
            $md = new Cortex_Markdown();
            return $md->render_markdown( $inner );
        }, $content );

        return $content;
    }
}

// initialize detector
add_action( 'init', function() {
    if ( ! class_exists( 'Cortex_Keyword_Detector' ) ) {
        require_once CORTEX_INCLUDES . 'class-cortex-keyword-detector.php';
    }
    // instantiate once
    if ( class_exists( 'Cortex_Keyword_Detector' ) && ! isset( $GLOBALS['cortex_keyword_detector'] ) ) {
        $GLOBALS['cortex_keyword_detector'] = new Cortex_Keyword_Detector();
    }
} );
