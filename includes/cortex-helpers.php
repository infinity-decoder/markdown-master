<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Helper functions for Cortex
 */

/**
 * cortex_generate_shortcode
 * @param string $type 'quiz'|'markdown'|'code'
 * @param int $id
 * @return string
 */
function cortex_generate_shortcode( $type, $id ) {
    $id = intval( $id );
    $type = sanitize_key( $type );
    if ( $type === 'quiz' ) {
        return '[cortex-quiz id="' . $id . '"]';
    } elseif ( $type === 'markdown' ) {
        return '[cortex-markdown id="' . $id . '"]';
    } elseif ( $type === 'code' ) {
        return '[cortex-code id="' . $id . '"]';
    }
    return '';
}

/**
 * cortex_get_upload_base
 * Return plugin upload folder URL and path
 */
function cortex_get_upload_base() {
    $up = wp_upload_dir();
    $dir = trailingslashit( $up['basedir'] ) . 'cortex/';
    $url = trailingslashit( $up['baseurl'] ) . 'cortex/';
    return [ 'path' => $dir, 'url' => $url ];
}

/**
 * cortex_write_file
 * Write contents to a file within plugin upload dir and return path
 */
function cortex_write_file( $filename, $contents ) {
    $base = cortex_get_upload_base();
    if ( ! file_exists( $base['path'] ) ) {
        wp_mkdir_p( $base['path'] );
    }
    $filepath = $base['path'] . $filename;
    $ok = file_put_contents( $filepath, $contents );
    if ( $ok === false ) {
        return false;
    }
    return $filepath;
}
