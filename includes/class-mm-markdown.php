<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MM_Markdown
 * Render markdown safely using Parsedown if available, otherwise fallback.
 */
class MM_Markdown {

    protected $parser;

    public function __construct() {
        // Prefer Parsedown (if installed in vendor)
        if ( class_exists( 'Parsedown' ) ) {
            $this->parser = new Parsedown();
            if ( method_exists( $this->parser, 'setSafeMode' ) ) {
                $this->parser->setSafeMode( true );
            }
        } else {
            $this->parser = null;
        }
    }

    /**
     * Render markdown text to HTML
     */
    public function render_markdown( $text ) {
        $text = (string) $text;

        // If parser exists, use it
        if ( $this->parser ) {
            // allow some HTML via safe mode and return
            $html = $this->parser->text( $text );
            return $html;
        }

        // Fallback: basic transformations
        // 1) preserve pre/code blocks
        $text = esc_html( $text );
        // 2) convert double line breaks to paragraphs
        $html = wpautop( $text );
        return $html;
    }
}
