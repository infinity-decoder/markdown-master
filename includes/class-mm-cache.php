<?php
/**
 * Caching Layer for Markdown Master
 * 
 * Provides abstract caching interface with support for:
 * - WordPress Transients API
 * - Object Cache (if available)
 * - Cache groups for organized invalidation
 * - Automatic expiration
 * 
 * @package MarkdownMaster
 * @since 2.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MM_Cache {

    /**
     * Cache groups
     */
    const GROUP_QUIZ = 'mm_quiz';
    const GROUP_QUESTION = 'mm_question';
    const GROUP_MARKDOWN = 'mm_markdown';
    const GROUP_CODE = 'mm_code';
    const GROUP_RESULT = 'mm_result';
    
    /**
     * Default cache TTL (1 hour)
     */
    const DEFAULT_TTL = 3600;

    /**
     * Get cached value
     * 
     * @param string $key Cache key
     * @param string $group Cache group
     * @return mixed|false Cached value or false if not found
     */
    public static function get( $key, $group = '' ) {
        $cache_key = self::build_key( $key, $group );
        
        // Try object cache first (if available, e.g., Redis, Memcached)
        if ( function_exists( 'wp_cache_get' ) ) {
            $value = wp_cache_get( $cache_key, $group );
            if ( false !== $value ) {
                return $value;
            }
        }
        
        // Fallback to transients
        return get_transient( $cache_key );
    }

    /**
     * Set cached value
     * 
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $expiration Expiration time in seconds
     * @param string $group Cache group
     * @return bool True on success
     */
    public static function set( $key, $value, $expiration = self::DEFAULT_TTL, $group = '' ) {
        $cache_key = self::build_key( $key, $group );
        
        // Set in object cache if available
        if ( function_exists( 'wp_cache_set' ) ) {
            wp_cache_set( $cache_key, $value, $group, $expiration );
        }
        
        // Also set in transients for persistence
        return set_transient( $cache_key, $value, $expiration );
    }

    /**
     * Delete cached value
     * 
     * @param string $key Cache key
     * @param string $group Cache group
     * @return bool True on success
     */
    public static function delete( $key, $group = '' ) {
        $cache_key = self::build_key( $key, $group );
        
        // Delete from object cache
        if ( function_exists( 'wp_cache_delete' ) ) {
            wp_cache_delete( $cache_key, $group );
        }
        
        // Delete from transients
        return delete_transient( $cache_key );
    }

    /**
     * Flush entire cache group
     * 
     * @param string $group Cache group to flush
     * @return bool True on success
     */
    public static function flush_group( $group ) {
        global $wpdb;
        
        // For transients, delete all matching keys
        $pattern = '_transient_' . $group . '%';
        $sql = $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $pattern
        );
        $wpdb->query( $sql );
        
        // Also delete timeout transients
        $timeout_pattern = '_transient_timeout_' . $group . '%';
        $sql = $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $timeout_pattern
        );
        $wpdb->query( $sql );
        
        // Object cache flush (if using persistent object cache)
        if ( function_exists( 'wp_cache_flush_group' ) ) {
            wp_cache_flush_group( $group );
        }
        
        return true;
    }

    /**
     * Flush all plugin caches
     * 
     * @return bool True on success
     */
    public static function flush_all() {
        self::flush_group( self::GROUP_QUIZ );
        self::flush_group( self::GROUP_QUESTION );
        self::flush_group( self::GROUP_MARKDOWN );
        self::flush_group( self::GROUP_CODE );
        self::flush_group( self::GROUP_RESULT );
        
        return true;
    }

    /**
     * Build cache key
     * 
     * @param string $key Base key
     * @param string $group Group prefix
     * @return string Full cache key
     */
    private static function build_key( $key, $group = '' ) {
        if ( empty( $group ) ) {
            return 'mm_' . $key;
        }
        return $group . '_' . $key;
    }

    /**
     * Get quiz cache key
     * 
     * @param int|string $quiz_id Quiz ID or UUID
     * @return string Cache key
     */
    public static function get_quiz_key( $quiz_id ) {
        return 'quiz_' . sanitize_text_field( $quiz_id );
    }

    /**
     * Get question cache key
     * 
     * @param int $question_id Question ID
     * @return string Cache key
     */
    public static function get_question_key( $question_id ) {
        return 'question_' . absint( $question_id );
    }

    /**
     * Get quiz questions cache key
     * 
     * @param int $quiz_id Quiz ID
     * @return string Cache key
     */
    public static function get_quiz_questions_key( $quiz_id ) {
        return 'quiz_questions_' . absint( $quiz_id );
    }

    /**
     * Get markdown cache key (based on content hash)
     * 
     * @param string $content Markdown content
     * @return string Cache key
     */
    public static function get_markdown_key( $content ) {
        return 'md_' . md5( $content );
    }

    /**
     * Get TTL from settings or use default
     * 
     * @return int TTL in seconds
     */
    public static function get_ttl() {
        $ttl = get_option( 'mm_cache_ttl', self::DEFAULT_TTL );
        return absint( $ttl );
    }

    /**
     * Invalidate quiz cache
     * Called when quiz is updated/deleted
     * 
     * @param int $quiz_id Quiz ID
     * @param string $quiz_uuid Quiz UUID
     * @return bool True on success
     */
    public static function invalidate_quiz( $quiz_id, $quiz_uuid = '' ) {
        self::delete( self::get_quiz_key( $quiz_id ), self::GROUP_QUIZ );
        self::delete( self::get_quiz_questions_key( $quiz_id ), self::GROUP_QUIZ );
        
        if ( ! empty( $quiz_uuid ) ) {
            self::delete( self::get_quiz_key( $quiz_uuid ), self::GROUP_QUIZ );
        }
        
        return true;
    }

    /**
     * Invalidate question cache
     * Called when question is updated/deleted
     * 
     * @param int $question_id Question ID
     * @param int $quiz_id Quiz ID (to invalidate quiz questions cache)
     * @return bool True on success
     */
    public static function invalidate_question( $question_id, $quiz_id = 0 ) {
        self::delete( self::get_question_key( $question_id ), self::GROUP_QUESTION );
        
        if ( $quiz_id > 0 ) {
            self::delete( self::get_quiz_questions_key( $quiz_id ), self::GROUP_QUIZ );
        }
        
        return true;
    }

    /**
     * Remember a value for the duration of the request
     * Uses static variable (not persistent)
     * 
     * @param string $key Key
     * @param mixed $value Value
     * @return bool True
     */
    public static function remember( $key, $value ) {
        static $mem_cache = array();
        $mem_cache[ $key ] = $value;
        return true;
    }

    /**
     * Recall a remembered value
     * 
     * @param string $key Key
     * @return mixed|null Value or null if not found
     */
    public static function recall( $key ) {
        static $mem_cache = array();
        return $mem_cache[ $key ] ?? null;
    }
}
