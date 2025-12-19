<?php
/**
 * Cortex Certificates Manager
 *
 * Handles certificate generation, public viewing, and validation.
 *
 * @package Cortex
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cortex_Certificates {

	public function __construct() {
        // Rewrite rules for certificate viewing /verification
        // URL Structure: /certificate/{hash}
        add_action( 'init', array( $this, 'add_rewrite_rules' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'handle_certificate_request' ) );
	}

    /**
     * Add Rewrite Rules for nice certificate URLs
     */
    public function add_rewrite_rules() {
        add_rewrite_rule( '^certificate/([^/]*)/?', 'index.php?cortex_cert_hash=$matches[1]', 'top' );
    }

    /**
     * Add Query Vars
     */
    public function add_query_vars( $vars ) {
        $vars[] = 'cortex_cert_hash';
        return $vars;
    }

    /**
     * Handle Certificate Request
     */
    public function handle_certificate_request() {
        $hash = get_query_var( 'cortex_cert_hash' );
        if ( empty( $hash ) ) {
            return;
        }

        // Look up certificate
        global $wpdb;
        $table = $wpdb->prefix . 'cortex_user_certificates';
        $cert = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE unique_hash = %s", $hash ) );

        if ( ! $cert ) {
            global $wp_query;
            $wp_query->set_404();
            status_header( 404 );
            get_template_part( 404 );
            exit;
        }

        // Load Certificate Template
        $this->render_certificate( $cert );
        exit;
    }

    /**
     * Render the Certificate HTML
     */
    private function render_certificate( $cert ) {
        $template_id = $cert->template_id;
        $user = get_userdata( $cert->user_id );
        $course_title = get_the_title( $cert->course_id );
        
        // Get Template Content
        $post_template = get_post( $template_id );
        $content = $post_template ? $post_template->post_content : '';
        
        // If template empty, use default
        if ( empty( $content ) ) {
            $content = $this->get_default_template_content();
        }

        // Replace Variables
        $variables = array(
            '{student_name}' => $user->display_name,
            '{course_name}'  => $course_title,
            '{date}'         => date_i18n( get_option( 'date_format' ), strtotime( $cert->issued_at ) ),
            '{hash}'         => $cert->unique_hash
        );

        foreach ( $variables as $key => $val ) {
            $content = str_replace( $key, $val, $content );
        }

        // Include CSS for print
        echo '<!DOCTYPE html><html><head>';
        echo '<title>' . sprintf( __( 'Certificate - %s', 'cortex' ), $user->display_name ) . '</title>';
        echo '<style>
            body { margin: 0; padding: 0; background: #e2e8f0; font-family: "Merriweather", serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
            .cortex-cert-wrapper { width: 1122px; height: 793px; background: #fff; position: relative; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); padding: 50px; box-sizing: border-box; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; border: 20px solid #3b82f6; }
            @media print { 
                body { background: none; } 
                .cortex-cert-wrapper { box-shadow: none; border: 20px solid #3b82f6; width: 100%; height: 100%; page-break-after: always; }
                @page { size: landscape; margin: 0; }
            }
        </style>';
        echo '</head><body>';
        echo '<div class="cortex-cert-wrapper">';
        echo apply_filters( 'the_content', $content );
        echo '</div>';
        echo '<script>window.print();</script>';
        echo '</body></html>';
    }

    private function get_default_template_content() {
        return '
        <h1 style="font-size: 60px; margin-bottom: 20px; color: #1e293b;">Certificate of Completion</h1>
        <p style="font-size: 24px; color: #64748b;">This is to certify that</p>
        <h2 style="font-size: 40px; margin: 20px 0; color: #000; border-bottom: 2px solid #cbd5e1; display: inline-block; padding-bottom: 10px;">{student_name}</h2>
        <p style="font-size: 24px; color: #64748b;">has successfully completed the course</p>
        <h3 style="font-size: 30px; margin: 20px 0; color: #3b82f6;">{course_name}</h3>
        <p style="font-size: 18px; color: #94a3b8; margin-top: 50px;">Issued on {date}</p>
        <p style="font-size: 12px; color: #cbd5e1; margin-top: 100px;">Verification ID: {hash}</p>
        ';
    }
}
