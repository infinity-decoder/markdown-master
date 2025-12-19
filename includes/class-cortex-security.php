<?php
/**
 * Security Layer for Cortex
 * 
 * Centralized security utilities for input validation, sanitization, and output escaping.
 * All user-submitted data MUST pass through these methods.
 * 
 * Security Principles:
 * 1. Never trust user input
 * 2. Validate input type and format
 * 3. Sanitize on input
 * 4. Escape on output
 * 5. Use prepared statements for all DB queries
 * 6. Verify nonces for state-changing operations
 * 7. Check capabilities before allowing actions
 * 
 * @package MarkdownMaster
 * @since 2.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cortex_Security {

    /**
     * Whitelist of allowed HTML tags for quiz/markdown content
     * Used with wp_kses()
     */
    private static $allowed_html_tags = array(
        'p' => array(),
        'br' => array(),
        'strong' => array(),
        'em' => array(),
        'u' => array(),
        's' => array(),
        'code' => array( 'class' => array() ),
        'pre' => array( 'class' => array() ),
        'ul' => array(),
        'ol' => array(),
        'li' => array(),
        'blockquote' => array(),
        'h1' => array(),
        'h2' => array(),
        'h3' => array(),
        'h4' => array(),
        'h5' => array(),
        'h6' => array(),
        'a' => array(
            'href' => array(),
            'title' => array(),
            'target' => array(),
            'rel' => array(),
        ),
        'img' => array(
            'src' => array(),
            'alt' => array(),
            'title' => array(),
            'width' => array(),
            'height' => array(),
        ),
        'table' => array(),
        'thead' => array(),
        'tbody' => array(),
        'tr' => array(),
        'th' => array(),
        'td' => array(),
        'div' => array( 'class' => array() ),
        'span' => array( 'class' => array() ),
    );

    /**
     * Verify nonce or die with error
     * 
     * @param string $action Nonce action name
     * @param string $nonce_field Nonce field name (default: '_wpnonce')
     * @param string $query_arg Query arg name for nonce (default: '_wpnonce')
     * @return bool True if nonce is valid
     */
    public static function verify_nonce_or_die( $action, $nonce_field = '_wpnonce', $query_arg = '_wpnonce' ) {
        $nonce = '';
        
        // Try POST first
        if ( isset( $_POST[ $nonce_field ] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) );
        }
        // Then GET
        elseif ( isset( $_GET[ $query_arg ] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_GET[ $query_arg ] ) );
        }
        // Then check headers for AJAX requests
        elseif ( ! empty( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) );
        }
        
        if ( ! wp_verify_nonce( $nonce, $action ) ) {
            wp_die(
                esc_html__( 'Security check failed. Please refresh and try again.', 'cortex' ),
                esc_html__( 'Security Error', 'cortex' ),
                array( 'response' => 403 )
            );
        }
        
        return true;
    }

    /**
     * Check if current user has capability, die if not
     * 
     * @param string $capability Required capability
     * @param string $message Optional custom error message
     * @return bool True if user has capability
     */
    public static function check_permission_or_die( $capability, $message = '' ) {
        if ( ! current_user_can( $capability ) ) {
            $error_message = $message ? $message : __( 'You do not have permission to perform this action.', 'cortex' );
            
            wp_die(
                esc_html( $error_message ),
                esc_html__( 'Permission Denied', 'cortex' ),
                array( 'response' => 403 )
            );
        }
        
        return true;
    }

    /**
     * Sanitize quiz input data
     * Deep sanitization for quiz creation/update
     * 
     * @param array $data Quiz data array
     * @return array Sanitized data
     */
    public static function sanitize_quiz_input( $data ) {
        $clean = array();
        
        // Text fields
        if ( isset( $data['title'] ) ) {
            $clean['title'] = sanitize_text_field( $data['title'] );
        }
        
        if ( isset( $data['required_role'] ) ) {
            $clean['required_role'] = sanitize_text_field( $data['required_role'] );
        }
        
        // HTML fields (limited tags)
        if ( isset( $data['description'] ) ) {
            $clean['description'] = wp_kses( $data['description'], self::$allowed_html_tags );
        }
        
        if ( isset( $data['welcome_content'] ) ) {
            $clean['welcome_content'] = wp_kses( $data['welcome_content'], self::$allowed_html_tags );
        }
        
        // Numeric fields
        $numeric_fields = array(
            'time_limit',
            'attempts_allowed',
            'max_total_attempts',
            'max_user_attempts',
            'questions_per_page',
        );
        
        foreach ( $numeric_fields as $field ) {
            if ( isset( $data[ $field ] ) ) {
                $clean[ $field ] = absint( $data[ $field ] );
            }
        }
        
        // Decimal fields
        if ( isset( $data['pass_percentage'] ) ) {
            $clean['pass_percentage'] = floatval( $data['pass_percentage'] );
            // Ensure 0-100 range
            $clean['pass_percentage'] = max( 0, min( 100, $clean['pass_percentage'] ) );
        }
        
        // Boolean fields
        $boolean_fields = array(
            'show_answers',
            'randomize_questions',
            'randomize_answers',
            'show_welcome_screen',
            'require_login',
            'enable_lead_capture',
        );
        
        foreach ( $boolean_fields as $field ) {
            if ( isset( $data[ $field ] ) ) {
                $clean[ $field ] = (bool) $data[ $field ] ? 1 : 0;
            }
        }
        
        // DateTime fields
        if ( isset( $data['scheduled_start'] ) && ! empty( $data['scheduled_start'] ) ) {
            $clean['scheduled_start'] = sanitize_text_field( $data['scheduled_start'] );
            // Validate datetime format
            if ( ! self::validate_datetime( $clean['scheduled_start'] ) ) {
                $clean['scheduled_start'] = null;
            }
        }
        
        if ( isset( $data['scheduled_end'] ) && ! empty( $data['scheduled_end'] ) ) {
            $clean['scheduled_end'] = sanitize_text_field( $data['scheduled_end'] );
            if ( ! self::validate_datetime( $clean['scheduled_end'] ) ) {
                $clean['scheduled_end'] = null;
            }
        }
        
        // JSON fields (validate and re-encode)
        if ( isset( $data['settings'] ) ) {
            if ( is_array( $data['settings'] ) ) {
                $clean['settings'] = wp_json_encode( $data['settings'] );
            } elseif ( is_string( $data['settings'] ) ) {
                // Decode and re-encode to validate
                $decoded = json_decode( $data['settings'], true );
                $clean['settings'] = ( null !== $decoded ) ? wp_json_encode( $decoded ) :'{}';
            }
        }
        
        return $clean;
    }

    /**
     * Sanitize question input data
     * 
     * @param array $data Question data
     * @return array Sanitized data
     */
    public static function sanitize_question_input( $data ) {
        $clean = array();
        
        // Question text - allow limited HTML for math/code
        if ( isset( $data['question_text'] ) ) {
            $clean['question_text'] = wp_kses( $data['question_text'], self::$allowed_html_tags );
        }
        
        // Question type - strict whitelist
        $allowed_types = array(
            'radio', 'checkbox', 'dropdown', 'text', 'short_text',
            'number', 'date', 'banner', 'fill_blank', 'matching', 'sequence'
        );
        
        if ( isset( $data['type'] ) ) {
            $type = sanitize_text_field( $data['type'] );
            $clean['type'] = in_array( $type, $allowed_types, true ) ? $type : 'radio';
        }
        
        // Hint
        if ( isset( $data['hint'] ) ) {
            $clean['hint'] = wp_kses( $data['hint'], self::$allowed_html_tags );
        }
        
        // Numeric fields
        if ( isset( $data['quiz_id'] ) ) {
            $clean['quiz_id'] = absint( $data['quiz_id'] );
        }
        
        if ( isset( $data['question_bank_id'] ) ) {
            $clean['question_bank_id'] = absint( $data['question_bank_id'] );
        }
        
        if ( isset( $data['question_order'] ) ) {
            $clean['question_order'] = absint( $data['question_order'] );
        }
        
        if ( isset( $data['points'] ) ) {
            $clean['points'] = floatval( $data['points'] );
            $clean['points'] = max( 0, $clean['points'] ); // No negative points
        }
        
        // Boolean
        if ( isset( $data['allow_comment'] ) ) {
            $clean['allow_comment'] = (bool) $data['allow_comment'] ? 1 : 0;
        }
        
        // JSON fields
        if ( isset( $data['options'] ) ) {
            if ( is_array( $data['options'] ) ) {
                // Sanitize array values
                $clean['options'] = wp_json_encode( array_map( 'sanitize_text_field', $data['options'] ) );
            } elseif ( is_string( $data['options'] ) ) {
                $decoded = json_decode( $data['options'], true );
                if ( is_array( $decoded ) ) {
                    $clean['options'] = wp_json_encode( array_map( 'sanitize_text_field', $decoded ) );
                }
            }
        }
        
        if ( isset( $data['correct_answer'] ) ) {
            if ( is_array( $data['correct_answer'] ) ) {
                $clean['correct_answer'] = wp_json_encode( array_map( 'sanitize_text_field', $data['correct_answer'] ) );
            } elseif ( is_string( $data['correct_answer'] ) ) {
                $clean['correct_answer'] = sanitize_text_field( $data['correct_answer'] );
            }
        }
        
        if ( isset( $data['metadata'] ) ) {
            if ( is_array( $data['metadata'] ) ) {
                // Deep sanitize metadata based on type or just use standard sanitization
                $clean['metadata'] = wp_json_encode( self::sanitize_answers_array( $data['metadata'] ) );
            } elseif ( is_string( $data['metadata'] ) ) {
                $decoded = json_decode( $data['metadata'], true );
                if ( is_array( $decoded ) ) {
                    $clean['metadata'] = wp_json_encode( self::sanitize_answers_array( $decoded ) );
                } else {
                    $clean['metadata'] = '{}';
                }
            }
        }
        
        // Image URL
        if ( isset( $data['image'] ) ) {
            $clean['image'] = esc_url_raw( $data['image'] );
        }
        
        return $clean;
    }

    /**
     * Sanitize quiz attempt/submission data
     * 
     * @param array $data Attempt data
     * @return array Sanitized data
     */
    public static function sanitize_attempt_input( $data ) {
        $clean = array();
        
        if ( isset( $data['quiz_id'] ) ) {
            $clean['quiz_id'] = absint( $data['quiz_id'] );
        }
        
        if ( isset( $data['user_id'] ) ) {
            $clean['user_id'] = absint( $data['user_id'] );
        }
        
        // Student info - text fields
        $text_fields = array( 'student_name', 'student_class', 'student_section', 'student_school', 'student_roll' );
        foreach ( $text_fields as $field ) {
            if ( isset( $data[ $field ] ) ) {
                $clean[ $field ] = sanitize_text_field( $data[ $field ] );
            }
        }
        
        // Scores
        if ( isset( $data['obtained_marks'] ) ) {
            $clean['obtained_marks'] = floatval( $data['obtained_marks'] );
        }
        
        if ( isset( $data['total_marks'] ) ) {
            $clean['total_marks'] = floatval( $data['total_marks'] );
        }
        
        if ( isset( $data['time_taken'] ) ) {
            $clean['time_taken'] = absint( $data['time_taken'] );
        }
        
        // Result tier
        $allowed_tiers = array( 'high', 'medium', 'low' );
        if ( isset( $data['result_tier'] ) ) {
            $tier = sanitize_text_field( $data['result_tier'] );
            $clean['result_tier'] = in_array( $tier, $allowed_tiers, true ) ? $tier : 'medium';
        }
        
        // IP and User Agent (for security/analytics)
        if ( isset( $data['ip_address'] ) ) {
            $clean['ip_address'] = self::sanitize_ip( $data['ip_address'] );
        }
        
        if ( isset( $data['user_agent'] ) ) {
            $clean['user_agent'] = sanitize_text_field( substr( $data['user_agent'], 0, 500 ) );
        }
        
        // Answers - JSON encode if array
        if ( isset( $data['answers'] ) ) {
            if ( is_array( $data['answers'] ) ) {
                // Deep sanitize answers
                $clean['answers'] = wp_json_encode( self::sanitize_answers_array( $data['answers'] ) );
            } elseif ( is_string( $data['answers'] ) ) {
                $decoded = json_decode( $data['answers'], true );
                if ( is_array( $decoded ) ) {
                    $clean['answers'] = wp_json_encode( self::sanitize_answers_array( $decoded ) );
                }
            }
        }
        
        return $clean;
    }

    /**
     * Sanitize answers array recursively
     * 
     * @param array $answers Answers array
     * @return array Sanitized answers
     */
    private static function sanitize_answers_array( $answers ) {
        $clean = array();
        
        foreach ( $answers as $key => $value ) {
            $key = sanitize_text_field( $key );
            
            if ( is_array( $value ) ) {
                $clean[ $key ] = self::sanitize_answers_array( $value );
            } else {
                // Allow some HTML in text answers (for code/math)
                $clean[ $key ] = wp_kses( $value, self::$allowed_html_tags );
            }
        }
        
        return $clean;
    }

    /**
     * Sanitize lead capture data
     * GDPR compliant
     * 
     * @param array $data Lead data
     * @return array Sanitized data
     */
    public static function sanitize_lead_input( $data ) {
        $clean = array();
        
        if ( isset( $data['quiz_id'] ) ) {
            $clean['quiz_id'] = absint( $data['quiz_id'] );
        }
        
        if ( isset( $data['attempt_id'] ) ) {
            $clean['attempt_id'] = absint( $data['attempt_id'] );
        }
        
        if ( isset( $data['name'] ) ) {
            $clean['name'] = sanitize_text_field( $data['name'] );
        }
        
        if ( isset( $data['email'] ) ) {
            $clean['email'] = sanitize_email( $data['email'] );
        }
        
        if ( isset( $data['phone'] ) ) {
            $clean['phone'] = sanitize_text_field( $data['phone'] );
        }
        
        // Boolean - must be explicitly true
        $clean['consent_given'] = ( isset( $data['consent_given'] ) && true === $data['consent_given'] ) ? 1 : 0;
        
        if ( isset( $data['ip_address'] ) ) {
            $clean['ip_address'] = self::sanitize_ip( $data['ip_address'] );
        }
        
        if ( isset( $data['custom_fields'] ) && is_array( $data['custom_fields'] ) ) {
            $sanitized_fields = array();
            foreach ( $data['custom_fields'] as $key => $value ) {
                $sanitized_fields[ sanitize_text_field( $key ) ] = sanitize_text_field( $value );
            }
            $clean['custom_fields'] = wp_json_encode( $sanitized_fields );
        }
        
        return $clean;
    }

    /**
     * Sanitize IP address
     * 
     * @param string $ip IP address
     * @return string|null Sanitized IP or null
     */
    public static function sanitize_ip( $ip ) {
        $ip = filter_var( $ip, FILTER_VALIDATE_IP );
        return $ip ? $ip : null;
    }

    /**
     * Get current user IP address
     * Checks proxy headers but validates them
     * 
     * @return string IP address
     */
    public static function get_user_ip() {
        $ip = '';
        
        // Check for proxy headers (in order of reliability)
        $headers = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        );
        
        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
                
                // X-Forwarded-For can contain multiple IPs, take first
                if ( false !== strpos( $ip, ',' ) ) {
                    $ips = explode( ',', $ip );
                    $ip = trim( $ips[0] );
                }
                
                // Validate IP
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }
        
        return $ip ? $ip : '0.0.0.0';
    }

    /**
     * Get user agent
     * 
     * @return string User agent (truncated to 500 chars)
     */
    public static function get_user_agent() {
        if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
            return sanitize_text_field( substr( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ), 0, 500 ) );
        }
        return '';
    }

    /**
     * Validate datetime string
     * 
     * @param string $datetime DateTime string
     * @return bool True if valid
     */
    public static function validate_datetime( $datetime ) {
        if ( empty( $datetime ) ) {
            return false;
        }
        
        $d = \DateTime::createFromFormat( 'Y-m-d H:i:s', $datetime );
        return $d && $d->format( 'Y-m-d H:i:s' ) === $datetime;
    }

    /**
     * Escape output for HTML display
     * Wrapper for esc_html with optional allowed tags
     * 
     * @param string $text Text to escape
     * @param bool $allow_tags Whether to allow HTML tags
     * @return string Escaped text
     */
    public static function esc_output( $text, $allow_tags = false ) {
        if ( $allow_tags ) {
            return wp_kses( $text, self::$allowed_html_tags );
        }
        return esc_html( $text );
    }

    /**
     * Rate limit check for quiz submissions
     * Prevents spam submissions
     * 
     * @param string $key Unique key (e.g., quiz_id + IP)
     * @param int $limit Max attempts
     * @param int $window Time window in seconds
     * @return bool True if under limit, false if over
     */
    public static function check_rate_limit( $key, $limit = 5, $window = 3600 ) {
        $transient_key = 'cortex_rate_' . md5( $key );
        $attempts = get_transient( $transient_key );
        
        if ( false === $attempts ) {
            // First attempt
            set_transient( $transient_key, 1, $window );
            return true;
        }
        
        if ( $attempts >= $limit ) {
            return false; // Over limit
        }
        
        // Increment
        set_transient( $transient_key, $attempts + 1, $window );
        return true;
    }

    /**
     * Generate secure random token
     * For CSRF protection, temporary links, etc.
     * 
     * @param int $length Token length
     * @return string Random token
     */
    public static function generate_token( $length = 32 ) {
        return bin2hex( random_bytes( $length / 2 ) );
    }

    /**
     * Sanitize HTML content
     * Context-aware HTML filtering
     * 
     * @param string $html HTML content
     * @param string $context Context (quiz, markdown, code)
     * @return string Sanitized HTML
     */
    public static function sanitize_html( $html, $context = 'quiz' ) {
        // All contexts use the same whitelist for now
        // Can be extended per context if needed
        return wp_kses( $html, self::$allowed_html_tags );
    }

    /**
     * Verify AJAX referer
     * For AJAX requests
     * 
     * @param string $action Action name
     * @return bool True if valid
     */
    public static function verify_ajax_referer( $action ) {
        check_ajax_referer( $action, 'nonce' );
        return true;
    }
}
