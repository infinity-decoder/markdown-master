<?php
/**
 * Lead Capture for Markdown Master
 * 
 * GDPR-compliant lead capture system for quizzes.
 * Stores contact information with explicit consent.
 * 
 * @package MarkdownMaster
 * @since 2.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MM_Lead_Capture {

    /** @var wpdb */
    protected $db;

    /** @var string */
    protected $table_leads;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table_leads = $wpdb->prefix . 'mm_lead_captures';
    }

    /**
     * Capture lead data
     * 
     * @param int $quiz_id Quiz ID
     * @param int $attempt_id Attempt ID
     * @param array $data Lead data (sanitized externally via MM_Security)
     * @return int Lead ID or 0 on failure
     */
    public function capture_lead( $quiz_id, $attempt_id, $data ) {
        $quiz_id = absint( $quiz_id );
        $attempt_id = absint( $attempt_id );

        if ( $quiz_id <= 0 || $attempt_id <= 0 ) {
            return 0;
        }

        // Consent is REQUIRED for GDPR compliance
        if ( ! isset( $data['consent_given'] ) || 1 !== absint( $data['consent_given'] ) ) {
            return 0; // No lead captured without consent
        }

        // Validate email if provided
        if ( isset( $data['email'] ) && ! is_email( $data['email'] ) ) {
            return 0; // Invalid email
        }

        $custom_fields = isset( $data['custom_fields'] ) ? wp_json_encode( $data['custom_fields'] ) : '{}';

        $insert_data = array(
            'quiz_id'       => $quiz_id,
            'attempt_id'    => $attempt_id,
            'name'          => isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : null,
            'email'         => isset( $data['email'] ) ? sanitize_email( $data['email'] ) : null,
            'phone'         => isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : null,
            'custom_fields' => $custom_fields,
            'consent_given' => 1,
            'ip_address'    => isset( $data['ip_address'] ) ? $data['ip_address'] : MM_Security::get_user_ip(),
            'created_at'    => current_time( 'mysql' ),
        );

        $formats = array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' );

        $ok = $this->db->insert( $this->table_leads, $insert_data, $formats );

        if ( ! $ok ) {
            return 0;
        }

        return (int) $this->db->insert_id;
    }

    /**
     * Get leads for a quiz
     * 
     * @param int $quiz_id Quiz ID
     * @param array $args Query arguments
     * @return array Leads
     */
    public function get_quiz_leads( $quiz_id, $args = array() ) {
        $quiz_id = absint( $quiz_id );
        if ( $quiz_id <= 0 ) {
            return array();
        }

        $limit = isset( $args['limit'] ) ? absint( $args['limit'] ) : 20;
        $offset = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;
        $search = isset( $args['search'] ) ? sanitize_text_field( $args['search'] ) : '';

        $where = 'quiz_id = %d';
        $params = array( $quiz_id );

        if ( ! empty( $search ) ) {
            $where .= ' AND (name LIKE %s OR email LIKE %s)';
            $search_like = '%' . $this->db->esc_like( $search ) . '%';
            $params[] = $search_like;
            $params[] = $search_like;
        }

        $params[] = $limit;
        $params[] = $offset;

        $sql = "SELECT * FROM {$this->table_leads} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";

        $sql = $this->db->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $this->db->get_results( $sql, ARRAY_A );

        if ( ! $rows ) {
            return array();
        }

        // Decode custom fields
        foreach ( $rows as &$r ) {
            $r['custom_fields'] = json_decode( $r['custom_fields'], true );
        }
        unset( $r );

        return $rows;
    }

    /**
     * Get single lead
     * 
     * @param int $lead_id Lead ID
     * @return array|null Lead data
     */
    public function get_lead( $lead_id ) {
        $lead_id = absint( $lead_id );
        if ( $lead_id <= 0 ) {
            return null;
        }

        $row = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->table_leads} WHERE id = %d",
                $lead_id
            ),
            ARRAY_A
        );

        if ( ! $row ) {
            return null;
        }

        $row['custom_fields'] = json_decode( $row['custom_fields'], true );
        return $row;
    }

    /**
     * Delete lead
     * GDPR "Right to be Forgotten"
     * 
     * @param int $lead_id Lead ID
     * @return bool True on success
     */
    public function delete_lead( $lead_id ) {
        $lead_id = absint( $lead_id );
        if ( $lead_id <= 0 ) {
            return false;
        }

        return (bool) $this->db->delete( $this->table_leads, array( 'id' => $lead_id ), array( '%d' ) );
    }

    /**
     * Delete all leads for a quiz
     * 
     * @param int $quiz_id Quiz ID
     * @return bool True on success
     */
    public function delete_quiz_leads( $quiz_id ) {
        $quiz_id = absint( $quiz_id );
        if ( $quiz_id <= 0 ) {
            return false;
        }

        return (bool) $this->db->delete( $this->table_leads, array( 'quiz_id' => $quiz_id ), array( '%d' ) );
    }

    /**
     * Export leads to CSV
     * GDPR data portability
     * 
     * @param int $quiz_id Quiz ID
     * @return string CSV content
     */
    public function export_leads_csv( $quiz_id ) {
        $leads = $this->get_quiz_leads( $quiz_id, array( 'limit' => 9999 ) );

        if ( empty( $leads ) ) {
            return '';
        }

        // CSV headers
        $csv = "ID,Name,Email,Phone,Consent Given,IP Address,Created At,Custom Fields\n";

        foreach ( $leads as $lead ) {
            $custom = ! empty( $lead['custom_fields'] ) ? wp_json_encode( $lead['custom_fields'] ) : '';
            
            $csv .= sprintf(
                "%d,%s,%s,%s,%s,%s,%s,%s\n",
                $lead['id'],
                '"' . str_replace( '"', '""', $lead['name'] ) . '"',
                '"' . str_replace( '"', '""', $lead['email'] ) . '"',
                '"' . str_replace( '"', '""', $lead['phone'] ) . '"',
                $lead['consent_given'] ? 'Yes' : 'No',
                $lead['ip_address'],
                $lead['created_at'],
                '"' . str_replace( '"', '""', $custom ) . '"'
            );
        }

        return $csv;
    }

    /**
     * Get lead count for quiz
     * 
     * @param int $quiz_id Quiz ID
     * @return int Lead count
     */
    public function get_quiz_lead_count( $quiz_id ) {
        $quiz_id = absint( $quiz_id );
        if ( $quiz_id <= 0 ) {
            return 0;
        }

        return (int) $this->db->get_var(
            $this->db->prepare(
                "SELECT COUNT(*) FROM {$this->table_leads} WHERE quiz_id = %d",
                $quiz_id
            )
        );
    }

    /**
     * Anonymize lead data
     * GDPR compliance - removes PII but keeps statistical data
     * 
     * @param int $lead_id Lead ID
     * @return bool True on success
     */
    public function anonymize_lead( $lead_id ) {
        $lead_id = absint( $lead_id );
        if ( $lead_id <= 0 ) {
            return false;
        }

        $updated = $this->db->update(
            $this->table_leads,
            array(
                'name'          => '[Anonymized]',
                'email'         => '[Anonymized]',
                'phone'         => '[Anonymized]',
                'custom_fields' => '{}',
                'ip_address'    => '0.0.0.0',
            ),
            array( 'id' => $lead_id ),
            array( '%s', '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );

        return $updated !== false;
    }

    /**
     * Check if email already exists for quiz (prevent duplicates)
     * 
     * @param int $quiz_id Quiz ID
     * @param string $email Email address
     * @return bool True if exists
     */
    public function email_exists_for_quiz( $quiz_id, $email ) {
        $quiz_id = absint( $quiz_id );
        $email = sanitize_email( $email );

        if ( $quiz_id <= 0 || empty( $email ) ) {
            return false;
        }

        $count = $this->db->get_var(
            $this->db->prepare(
                "SELECT COUNT(*) FROM {$this->table_leads} WHERE quiz_id = %d AND email = %s",
                $quiz_id,
                $email
            )
        );

        return $count > 0;
    }
}
