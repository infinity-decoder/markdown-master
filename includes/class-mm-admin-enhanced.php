<?php
/**
 * Modern Admin Dashboard and Enhanced UI for Markdown Master 2.0
 * 
 * Adds modern card-based interface for Question Banks and Markdown Snippets
 * 
 * @package MarkdownMaster
 * @since 2.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MM_Admin_Enhanced {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_enhanced_menus' ), 20 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_modern_assets' ) );
    }

    /**
     * Add enhanced admin menu items
     */
    public function add_enhanced_menus() {
        // Question Banks submenu
        add_submenu_page(
            'markdown-master',
            __( 'Question Banks', 'markdown-master' ),
            __( 'Question Banks', 'markdown-master' ),
            'manage_options',
            'mm_question_banks',
            array( $this, 'render_question_banks_page' )
        );

        // Markdown Snippets submenu
        add_submenu_page(
            'markdown-master',
            __( 'Markdown Snippets', 'markdown-master' ),
            __( 'Markdown Snippets', 'markdown-master' ),
            'manage_options',
            'mm_markdown_snippets',
            array( $this, 'render_markdown_snippets_page' )
        );

        // Lead Captures submenu
        add_submenu_page(
            'markdown-master',
            __( 'Lead Captures', 'markdown-master' ),
            __( 'Lead Captures', 'markdown-master' ),
            'manage_options',
            'mm_lead_captures',
            array( $this, 'render_lead_captures_page' )
        );
    }

    /**
     * Enqueue modern admin assets
     */
    public function enqueue_modern_assets( $hook ) {
        // Check if we're on our pages
        $our_pages = array( 'mm_question_banks', 'mm_markdown_snippets', 'mm_lead_captures', 'markdown-master' );
        
        if ( isset( $_GET['page'] ) && in_array( $_GET['page'], $our_pages, true ) ) {
            wp_enqueue_style( 'mm-admin-modern', MM_PLUGIN_URL . 'assets/css/mm-admin.css', array(), MM_VERSION );
            wp_enqueue_script( 'mm-admin-modern', MM_PLUGIN_URL . 'assets/js/mm-admin.js', array( 'jquery' ), MM_VERSION, true );
        }
    }

    /**
     * Render Question Banks page with card layout
     */
    public function render_question_banks_page() {
        $bank_model = new MM_Question_Bank();
        $banks = $bank_model->get_all_banks();

        ?>
        <div class="wrap mm-admin-dashboard">
            <div class="mm-dashboard-header">
                <h1><?php esc_html_e( 'Question Banks', 'markdown-master' ); ?></h1>
                <a href="#" class="mm-btn mm-btn-primary mm-btn-large" id="mm-add-bank">
                    <?php esc_html_e( '+ Create Bank', 'markdown-master' ); ?>
                </a>
            </div>

            <?php if ( empty( $banks ) ) : ?>
                <div class="mm-empty-state">
                    <div class="mm-empty-icon">📦</div>
                    <h2 class="mm-empty-title"><?php esc_html_e( 'No Question Banks Yet', 'markdown-master' ); ?></h2>
                    <p class="mm-empty-description">
                        <?php esc_html_e( 'Question banks let you create reusable question libraries that can be imported into multiple quizzes.', 'markdown-master' ); ?>
                    </p>
                    <a href="#" class="mm-btn mm-btn-primary" id="mm-create-first-bank">
                        <?php esc_html_e( 'Create Your First Bank', 'markdown-master' ); ?>
                    </a>
                </div>
            <?php else : ?>
                <div class="mm-filter-bar">
                    <input type="search" placeholder="<?php esc_attr_e( 'Search banks...', 'markdown-master' ); ?>">
                    <select>
                        <option value="all"><?php esc_html_e( 'All Banks', 'markdown-master' ); ?></option>
                        <option value="recent"><?php esc_html_e( 'Recently Updated', 'markdown-master' ); ?></option>
                    </select>
                </div>

                <div class="mm-cards-grid">
                    <?php foreach ( $banks as $bank ) : ?>
                        <div class="mm-card" data-status="published">
                            <div class="mm-card-header">
                                <div>
                                    <h3 class="mm-card-title">
                                        <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'mm_question_banks', 'action' => 'edit', 'id' => $bank['id'] ), admin_url( 'admin.php' ) ) ); ?>">
                                            <?php echo esc_html( $bank['name'] ); ?>
                                        </a>
                                    </h3>
                                </div>
                            </div>

                            <div class="mm-card-body">
                                <?php if ( ! empty( $bank['description'] ) ) : ?>
                                    <p class="mm-card-description"><?php echo esc_html( $bank['description'] ); ?></p>
                                <?php endif; ?>

                                <div class="mm-card-meta">
                                    <div class="mm-meta-item">
                                        <span class="mm-meta-icon">📝</span>
                                        <span class="mm-meta-value"><?php echo esc_html( $bank['question_count'] ); ?></span>
                                        <?php esc_html_e( 'questions', 'markdown-master' ); ?>
                                    </div>
                                    <div class="mm-meta-item">
                                        <span class="mm-meta-icon">📅</span>
                                        <?php echo esc_html( human_time_diff( strtotime( $bank['created_at'] ), current_time( 'timestamp' ) ) ); ?>
                                        <?php esc_html_e( 'ago', 'markdown-master' ); ?>
                                    </div>
                                </div>
                            </div>

                            <div class="mm-card-footer">
                                <div class="mm-card-actions">
                                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'mm_question_banks', 'action' => 'edit', 'id' => $bank['id'] ), admin_url( 'admin.php' ) ) ); ?>" class="mm-card-action primary">
                                        <?php esc_html_e( 'Manage', 'markdown-master' ); ?>
                                    </a>
                                    <a href="#" class="mm-card-action secondary" onclick="return confirm('<?php esc_attr_e( 'Delete this bank?', 'markdown-master' ); ?>')">
                                        <?php esc_html_e( 'Delete', 'markdown-master' ); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render Markdown Snippets page with card layout
     */
    public function render_markdown_snippets_page() {
        $snippet_model = new MM_Markdown_Snippets();
        $snippets = $snippet_model->get_all_snippets();

        ?>
        <div class="wrap mm-admin-dashboard">
            <div class="mm-dashboard-header">
                <h1><?php esc_html_e( 'Markdown Snippets', 'markdown-master' ); ?></h1>
                <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'mm_markdown_snippets', 'action' =>'create' ), admin_url( 'admin.php' ) ) ); ?>" class="mm-btn mm-btn-primary mm-btn-large">
                    <?php esc_html_e( '+ New Snippet', 'markdown-master' ); ?>
                </a>
            </div>

            <?php if ( empty( $snippets ) ) : ?>
                <div class="mm-empty-state">
                    <div class="mm-empty-icon">📄</div>
                    <h2 class="mm-empty-title"><?php esc_html_e( 'No Markdown Snippets Yet', 'markdown-master' ); ?></h2>
                    <p class="mm-empty-description">
                        <?php esc_html_e( 'Create reusable markdown content that can be embedded anywhere with a shortcode.', 'markdown-master' ); ?>
                    </p>
                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'mm_markdown_snippets', 'action' => 'create' ), admin_url( 'admin.php' ) ) ); ?>" class="mm-btn mm-btn-primary">
                        <?php esc_html_e( 'Create First Snippet', 'markdown-master' ); ?>
                    </a>
                </div>
            <?php else : ?>
                <div class="mm-filter-bar">
                    <input type="search" placeholder="<?php esc_attr_e( 'Search snippets...', 'markdown-master' ); ?>">
                </div>

                <div class="mm-cards-grid">
                    <?php foreach ( $snippets as $snippet ) : ?>
                        <div class="mm-card">
                            <div class="mm-card-header">
                                <h3 class="mm-card-title">
                                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'mm_markdown_snippets', 'action' => 'edit', 'id' => $snippet['id'] ), admin_url( 'admin.php' ) ) ); ?>">
                                        <?php echo esc_html( $snippet['title'] ); ?>
                                    </a>
                                </h3>
                                <span class="mm-card-uuid">#<?php echo esc_html( $snippet['id'] ); ?></span>
                            </div>

                            <div class="mm-card-body">
                                <p class="mm-card-description">
                                    <?php echo esc_html( wp_trim_words( $snippet['content'], 20 ) ); ?>
                                </p>

                                <div class="mm-card-meta">
                                    <div class="mm-meta-item">
                                        <span class="mm-meta-icon">🔖</span>
                                        <code>[mm-markdown id="<?php echo esc_attr( $snippet['id'] ); ?>"]</code>
                                    </div>
                                </div>
                            </div>

                            <div class="mm-card-footer">
                                <div class="mm-card-actions">
                                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'mm_markdown_snippets', 'action' => 'edit', 'id' => $snippet['id'] ), admin_url( 'admin.php' ) ) ); ?>" class="mm-card-action primary">
                                        <?php esc_html_e( 'Edit', 'markdown-master' ); ?>
                                    </a>
                                    <button class="mm-card-action secondary mm-copy-uuid" data-uuid="[mm-markdown id=&quot;<?php echo esc_attr( $snippet['id'] ); ?>&quot;]">
                                        <?php esc_html_e( 'Copy Shortcode', 'markdown-master' ); ?>
                                    </button>
                                </div>
                                <span class="mm-card-date">
                                    <?php echo esc_html( human_time_diff( strtotime( $snippet['created_at'] ), current_time( 'timestamp' ) ) ); ?>
                                    <?php esc_html_e( 'ago', 'markdown-master' ); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render Lead Captures page with stats and cards
     */
    public function render_lead_captures_page() {
        $lead_model = new MM_Lead_Capture();
        global $wpdb;

        // Get stats
        $total_leads = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mm_lead_captures" );
        $leads_today = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mm_lead_captures WHERE DATE(created_at) = CURDATE()" );
        $leads_week = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mm_lead_captures WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)" );

        // Get recent leads
        $recent_leads = $wpdb->get_results(
            "SELECT l.*, q.title as quiz_title 
             FROM {$wpdb->prefix}mm_lead_captures l
             LEFT JOIN {$wpdb->prefix}mm_quizzes q ON l.quiz_id = q.id
             ORDER BY l.created_at DESC 
             LIMIT 50",
            ARRAY_A
        );

        ?>
        <div class="wrap mm-admin-dashboard">
            <div class="mm-dashboard-header">
                <h1><?php esc_html_e( 'Lead Captures', 'markdown-master' ); ?></h1>
                <a href="#" class="mm-btn mm-btn-primary" id="mm-export-leads-csv">
                    <?php esc_html_e( 'Export CSV', 'markdown-master' ); ?>
                </a>
            </div>

            <!-- Stats Cards -->
            <div class="mm-stats-grid">
                <div class="mm-stat-card primary">
                    <div class="mm-stat-header">
                        <div>
                            <div class="mm-stat-value"><?php echo esc_html( number_format( $total_leads ) ); ?></div>
                            <div class="mm-stat-label"><?php esc_html_e( 'Total Leads', 'markdown-master' ); ?></div>
                        </div>
                        <div class="mm-stat-icon">👥</div>
                    </div>
                </div>

                <div class="mm-stat-card success">
                    <div class="mm-stat-header">
                        <div>
                            <div class="mm-stat-value"><?php echo esc_html( number_format( $leads_today ) ); ?></div>
                            <div class="mm-stat-label"><?php esc_html_e( 'Today', 'markdown-master' ); ?></div>
                        </div>
                        <div class="mm-stat-icon">📈</div>
                    </div>
                </div>

                <div class="mm-stat-card warning">
                    <div class="mm-stat-header">
                        <div>
                            <div class="mm-stat-value"><?php echo esc_html( number_format( $leads_week ) ); ?></div>
                            <div class="mm-stat-label"><?php esc_html_e( 'This Week', 'markdown-master' ); ?></div>
                        </div>
                        <div class="mm-stat-icon">📊</div>
                    </div>
                </div>
            </div>

            <!-- Leads Table -->
            <div class="mm-form-container">
                <h2><?php esc_html_e( 'Recent Captures', 'markdown-master' ); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Name', 'markdown-master' ); ?></th>
                            <th><?php esc_html_e( 'Email', 'markdown-master' ); ?></th>
                            <th><?php esc_html_e( 'Quiz', 'markdown-master' ); ?></th>
                            <th><?php esc_html_e( 'Phone', 'markdown-master' ); ?></th>
                            <th><?php esc_html_e( 'Date', 'markdown-master' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $recent_leads ) ) : ?>
                            <?php foreach ( $recent_leads as $lead ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $lead['name'] ); ?></td>
                                    <td><a href="mailto:<?php echo esc_attr( $lead['email'] ); ?>"><?php echo esc_html( $lead['email'] ); ?></a></td>
                                    <td><?php echo esc_html( $lead['quiz_title'] ); ?></td>
                                    <td><?php echo esc_html( $lead['phone'] ?: '—' ); ?></td>
                                    <td><?php echo esc_html( human_time_diff( strtotime( $lead['created_at'] ), current_time( 'timestamp' ) ) ); ?> <?php esc_html_e( 'ago', 'markdown-master' ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="5" style="text-align:center;"><?php esc_html_e( 'No leads captured yet.', 'markdown-master' ); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
}

// Initialize enhanced admin
new MM_Admin_Enhanced();
