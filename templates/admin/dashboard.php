<div class="cotex-wrap">
    <div class="cotex-header">
        <div class="cotex-brand">
            <h1 class="cotex-title">Cotex</h1>
            <p class="cotex-subtitle">Modular Intelligence System v<?php echo COTEX_VERSION; ?></p>
        </div>
        <div class="cotex-header-actions">
            <a href="#" class="cotex-btn">
                <span>Documentation</span>
                <span class="dashicons dashicons-external"></span>
            </a>
        </div>
    </div>

    <div class="cotex-grid">
        <?php foreach ( $modules as $slug => $data ) : 
            $is_active = in_array( $slug, $active, true );
            
            // Map icons based on slug
            $icon = 'dashicons-admin-plugins';
            switch($slug) {
                case 'lms-engine': $icon = 'dashicons-welcome-learn-more'; break;
                case 'quiz-engine': $icon = 'dashicons-clipboard'; break;
                case 'code-blocks': $icon = 'dashicons-editor-code'; break;
                case 'markdown-studio': $icon = 'dashicons-edit'; break;
            }
        ?>
        <div class="cotex-card <?php echo $is_active ? 'active' : ''; ?>">
            <div class="cotex-card-header">
                <div class="cotex-icon-box">
                    <span class="dashicons <?php echo $icon; ?>"></span>
                </div>
            </div>
            
            <h3 class="cotex-module-name"><?php echo esc_html( $data['name'] ); ?></h3>
            <p class="cotex-module-desc"><?php echo esc_html( $data['description'] ); ?></p>
            
            <div class="cotex-footer">
                <span class="cotex-status-text"><?php echo $is_active ? 'Active' : 'Offline'; ?></span>
                <label class="cotex-toggle">
                    <input type="checkbox" class="cotex-toggle-input" data-slug="<?php echo esc_attr( $slug ); ?>" <?php checked( $is_active ); ?>>
                    <span class="cotex-slider"></span>
                </label>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
