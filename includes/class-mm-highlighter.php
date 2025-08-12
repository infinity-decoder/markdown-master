<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MM_Highlighter
 * Wrap code with pre/code and language class for highlight.js (front-end loads highlight.js)
 */
class MM_Highlighter {

    public function __construct() {
        // no-op for now; front-end enqueues the highlight.js library in MM_Frontend
    }

    /**
     * Render code block HTML
     */
    public function render_code( $code, $language = 'text' ) {
        $lang = sanitize_text_field( $language );
        // Escape code but preserve entities
        $escaped = htmlspecialchars( $code, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8' );
        $html = '<pre class="mm-code-block"><code class="language-' . esc_attr( $lang ) . '">' . $escaped . '</code></pre>';

        // Add a copy button (JS can hook onto .mm-copy-code)
        $html .= '<div><button class="mm-copy-code" data-lang="' . esc_attr( $lang ) . '">' . esc_html__( 'Copy', 'markdown-master' ) . '</button></div>';
        return $html;
    }
}
