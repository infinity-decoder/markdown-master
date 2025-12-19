<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Cortex_Settings
 * Manage plugin settings via WP options.
 */
class Cortex_Settings {

    protected $option_key = 'cortex_settings';

    public function __construct() {
        // can be used later for hooks
    }

    public function get_all() {
        $settings = get_option( $this->option_key, false );
        if ( ! $settings ) {
            // fallback defaults
            $defaults = [
                'show_answers' => 'end',
                'theme' => 'default',
                'timer_enabled' => false,
                'randomize_questions' => false,
                'max_attempts' => 0,
            ];
            return $defaults;
        }

        if ( is_string( $settings ) ) {
            $decoded = json_decode( $settings, true );
            if ( is_array( $decoded ) ) {
                return $decoded;
            }
            // maybe it's stored as serialized array
            $maybe = maybe_unserialize( $settings );
            if ( is_array( $maybe ) ) {
                return $maybe;
            }
            return [];
        }

        return $settings;
    }

    public function get( $key, $default = null ) {
        $all = $this->get_all();
        return isset( $all[ $key ] ) ? $all[ $key ] : $default;
    }

    public function update( $data ) {
        // sanitize expected keys
        $current = $this->get_all();
        $allowed = [
            'show_answers',
            'theme',
            'timer_enabled',
            'randomize_questions',
            'max_attempts',
        ];
        foreach ( $data as $k => $v ) {
            if ( in_array( $k, $allowed, true ) ) {
                // basic sanitization
                if ( is_bool( $v ) ) {
                    $current[ $k ] = $v;
                } elseif ( in_array( $k, [ 'timer_enabled', 'randomize_questions' ], true ) ) {
                    $current[ $k ] = (bool) $v;
                } elseif ( $k === 'max_attempts' ) {
                    $current[ $k ] = intval( $v );
                } else {
                    $current[ $k ] = sanitize_text_field( $v );
                }
            }
        }
        return update_option( $this->option_key, $current );
    }
}
