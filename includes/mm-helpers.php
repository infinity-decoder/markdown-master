<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Helper functions for Markdown Master
 */

/**
 * mm_generate_shortcode
 * @param string $type 'quiz'|'note'|'code'
 * @param int $id
 * @return string
 */
function mm_generate_shortcode( $type, $id ) {
    $id = intval( $id );
    $type = sanitize_key( $type );
    if ( $type === 'quiz' ) {
        return '[mm_quiz id="' . $id . '"]';
    } elseif ( $type === 'note' ) {
        return '[mm_note id="' . $id . '"]';
    } elseif ( $type === 'code' ) {
        return '[mm_code id="' . $id . '"]';
    }
    return '';
}

/**
 * mm_get_upload_base
 * Return plugin upload folder URL and path
 */
function mm_get_upload_base() {
    $up = wp_upload_dir();
    $dir = trailingslashit( $up['basedir'] ) . 'markdown-master/';
    $url = trailingslashit( $up['baseurl'] ) . 'markdown-master/';
    return [ 'path' => $dir, 'url' => $url ];
}

/**
 * mm_write_file
 * Write contents to a file within plugin upload dir and return path
 */
function mm_write_file( $filename, $contents ) {
    $base = mm_get_upload_base();
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
