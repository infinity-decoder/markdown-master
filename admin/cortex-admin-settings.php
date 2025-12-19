<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
}

if ( ! class_exists( 'Cortex_Settings' ) ) {
    if ( file_exists( CORTEX_INCLUDES . 'class-cortex-settings.php' ) ) {
        require_once CORTEX_INCLUDES . 'class-cortex-settings.php';
    }
}

$settings_model = new Cortex_Settings();
$settings = $settings_model->get_all();

// Handle save
if ( isset( $_POST['cortex_settings_submit'] ) ) {
    check_admin_referer( 'cortex_settings_save', 'cortex_settings_nonce' );

    $new_data = [
        'show_answers' => sanitize_text_field( $_POST['show_answers'] ?? $settings['show_answers'] ),
        'theme' => sanitize_text_field( $_POST['theme'] ?? $settings['theme'] ),
        'timer_enabled' => isset( $_POST['timer_enabled'] ) ? boolval( $_POST['timer_enabled'] ) : false,
        'randomize_questions' => isset( $_POST['randomize_questions'] ) ? boolval( $_POST['randomize_questions'] ) : false,
        'max_attempts' => intval( $_POST['max_attempts'] ?? 0 ),
    ];

    $updated = $settings_model->update( $new_data );
    if ( $updated ) {
        $settings = $settings_model->get_all(); // refresh
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'cortex' ) . '</p></div>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to save settings or no changes made.', 'cortex' ) . '</p></div>';
    }
}
?>
<div class="cortex-wrapper">
    <h1><?php esc_html_e( 'Settings', 'cortex' ); ?></h1>

    <div class="cortex-settings-layout">
        <!-- Sidebar Navigation -->
        <nav class="cortex-tabs-nav">
            <a href="#tab-general" class="cortex-tab-item active" onclick="openTab(event, 'tab-general')">
                <?php esc_html_e( 'General', 'cortex' ); ?>
            </a>
            <a href="#tab-defaults" class="cortex-tab-item" onclick="openTab(event, 'tab-defaults')">
                <?php esc_html_e( 'Quiz Defaults', 'cortex' ); ?>
            </a>
            <a href="#tab-appearance" class="cortex-tab-item" onclick="openTab(event, 'tab-appearance')">
                <?php esc_html_e( 'Appearance', 'cortex' ); ?>
            </a>
            <a href="#tab-data" class="cortex-tab-item" onclick="openTab(event, 'tab-data')">
                <?php esc_html_e( 'Data & Privacy', 'cortex' ); ?>
            </a>
            <a href="#tab-advanced" class="cortex-tab-item" onclick="openTab(event, 'tab-advanced')">
                <?php esc_html_e( 'Advanced', 'cortex' ); ?>
            </a>
        </nav>

        <!-- Main Content -->
        <div class="cortex-settings-content">
            <form method="post" action="">
                <?php wp_nonce_field( 'cortex_settings_save', 'cortex_settings_nonce' ); ?>

                <!-- Tab: General -->
                <div id="tab-general" class="cortex-tab-content" style="display: block;">
                    <div class="cortex-card">
                        <h2 class="cortex-card-title cortex-mb-4"><?php esc_html_e( 'General Settings', 'cortex' ); ?></h2>
                        
                        <div class="cortex-form-group">
                            <label class="cortex-label" for="show_answers"><?php esc_html_e( 'Answer Reveal Behavior', 'cortex' ); ?></label>
                            <select name="show_answers" id="show_answers" class="cortex-input">
                                <option value="end" <?php selected( $settings['show_answers'] ?? 'end', 'end' ); ?>><?php esc_html_e( 'Show results at the end', 'cortex' ); ?></option>
                                <option value="instant" <?php selected( $settings['show_answers'] ?? 'end', 'instant' ); ?>><?php esc_html_e( 'Show immediate feedback after each question', 'cortex' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'Determines when the user sees the correct answer.', 'cortex' ); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Tab: Defaults -->
                <div id="tab-defaults" class="cortex-tab-content" style="display: none;">
                    <div class="cortex-card">
                        <h2 class="cortex-card-title cortex-mb-4"><?php esc_html_e( 'Quiz Configuration Defaults', 'cortex' ); ?></h2>
                        <p class="description cortex-mb-4"><?php esc_html_e( 'These settings apply as defaults for new quizzes, but can be overridden per quiz.', 'cortex' ); ?></p>

                        <div class="cortex-form-group">
                            <label class="cortex-label"><?php esc_html_e( 'Question Randomization', 'cortex' ); ?></label>
                            <label class="cortex-toggle-switch">
                                <input type="checkbox" name="randomize_questions" value="1" <?php checked( ! empty( $settings['randomize_questions'] ), true ); ?>>
                                <span class="cortex-slider round"></span>
                            </label>
                            <span style="font-size:13px; margin-left:8px; color:var(--cortex-text-muted);"><?php esc_html_e( 'Shuffle questions automatically', 'cortex' ); ?></span>
                        </div>

                        <div class="cortex-form-group">
                            <label class="cortex-label"><?php esc_html_e( 'Timer Support', 'cortex' ); ?></label>
                            <label class="cortex-toggle-switch">
                                <input type="checkbox" name="timer_enabled" value="1" <?php checked( ! empty( $settings['timer_enabled'] ), true ); ?>>
                                <span class="cortex-slider round"></span>
                            </label>
                            <span style="font-size:13px; margin-left:8px; color:var(--cortex-text-muted);"><?php esc_html_e( 'Enable timer features', 'cortex' ); ?></span>
                        </div>

                        <div class="cortex-form-group">
                            <label class="cortex-label" for="max_attempts"><?php esc_html_e( 'Maximum Attempts', 'cortex' ); ?></label>
                            <input type="number" name="max_attempts" id="max_attempts" class="cortex-input" style="width: 100px;" value="<?php echo esc_attr( intval( $settings['max_attempts'] ?? 0 ) ); ?>" min="0">
                            <p class="description"><?php esc_html_e( 'Set to 0 for unlimited attempts.', 'cortex' ); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Tab: Appearance -->
                <div id="tab-appearance" class="cortex-tab-content" style="display: none;">
                    <div class="cortex-card">
                        <h2 class="cortex-card-title cortex-mb-4"><?php esc_html_e( 'Visual Styles', 'cortex' ); ?></h2>

                        <div class="cortex-form-group">
                            <label class="cortex-label" for="theme"><?php esc_html_e( 'Global Theme Preset', 'cortex' ); ?></label>
                            <select name="theme" id="theme" class="cortex-input">
                                <option value="default" <?php selected( $settings['theme'] ?? 'default', 'default' ); ?>><?php esc_html_e( 'Standard (Default)', 'cortex' ); ?></option>
                                <option value="compact" <?php selected( $settings['theme'] ?? 'default', 'compact' ); ?>><?php esc_html_e( 'Compact / Dense', 'cortex' ); ?></option>
                                <option value="spacious" <?php selected( $settings['theme'] ?? 'default', 'spacious' ); ?>><?php esc_html_e( 'Modern Spacious', 'cortex' ); ?></option>
                                <option value="dark" <?php selected( $settings['theme'] ?? 'default', 'dark' ); ?>><?php esc_html_e( 'Dark Mode (Experimental)', 'cortex' ); ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Tab: Data -->
                <div id="tab-data" class="cortex-tab-content" style="display: none;">
                    <div class="cortex-card">
                        <h2 class="cortex-card-title cortex-mb-4"><?php esc_html_e( 'Data & Privacy', 'cortex' ); ?></h2>
                        <div class="notice notice-info inline"><p><?php esc_html_e( 'Data retention and privacy settings coming soon.', 'cortex' ); ?></p></div>
                    </div>
                </div>

                <!-- Tab: Advanced -->
                <div id="tab-advanced" class="cortex-tab-content" style="display: none;">
                    <div class="cortex-card">
                        <h2 class="cortex-card-title cortex-mb-4"><?php esc_html_e( 'Advanced Configuration', 'cortex' ); ?></h2>
                        <div class="notice notice-warning inline"><p><?php esc_html_e( 'Experimental API features coming in version 2.0.', 'cortex' ); ?></p></div>
                    </div>
                </div>

                <div class="cortex-form-actions" style="margin-top: 20px;">
                    <button type="submit" name="cortex_settings_submit" class="cortex-btn cortex-btn-primary cortex-btn-large">
                        <?php esc_html_e( 'Save All Settings', 'cortex' ); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script type="text/javascript">
function openTab(evt, tabName) {
    var i, tabcontent, tablinks;
    
    // Hide all tab content
    tabcontent = document.getElementsByClassName("cortex-tab-content");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
    }

    // Deactivate all tab links
    tablinks = document.getElementsByClassName("cortex-tab-item");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].className = tablinks[i].className.replace(" active", "");
    }

    // Show current tab and activate link
    document.getElementById(tabName).style.display = "block";
    if (evt) {
        evt.currentTarget.className += " active";
    } else {
        // Fallback or auto-open
        var link = document.querySelector('a[href="#' + tabName + '"]');
        if (link) link.className += " active";
    }

    // Optional: Update URL hash without scroll
    if(history.pushState) {
        history.pushState(null, null, '#' + tabName);
    }
    else {
        location.hash = '#' + tabName;
    }
    evt.preventDefault();
}

// Auto-open tab from hash
document.addEventListener("DOMContentLoaded", function() {
    var hash = window.location.hash.substring(1);
    if (hash && document.getElementById(hash)) {
        openTab(null, hash);
    }
});
</script>
<style>
/* Local overrides or specific styles for settings page if needed */
.cortex-card-title { font-size: 18px; margin-top: 0; border-bottom: 1px solid var(--cortex-border-color); padding-bottom: 15px; margin-bottom: 20px; }
.cortex-toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; vertical-align: middle; }
.cortex-toggle-switch input { opacity: 0; width: 0; height: 0; }
.cortex-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
.cortex-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
input:checked + .cortex-slider { background-color: var(--cortex-primary); }
input:checked + .cortex-slider:before { transform: translateX(20px); }

/* Notice fixes */
.notice { margin-left: 0 !important; margin-right: 0 !important; }
</style>
