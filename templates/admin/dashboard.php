<div class="cotex-wrap">
    <div class="cotex-header">
        <div>
            <h1 class="cotex-title">Cotex</h1>
            <p class="cotex-subtitle">Enterprise Modular Architecture v<?php echo COTEX_VERSION; ?></p>
        </div>
        <div>
            <!-- Global Settings Button could go here -->
        </div>
    </div>

    <div class="cotex-grid">
        <?php foreach ( $modules as $slug => $data ) : 
            $is_active = in_array( $slug, $active, true );
        ?>
        <div class="cotex-card <?php echo $is_active ? 'active' : ''; ?>">
            <div class="cotex-card-header">
                <div>
                    <h3 class="cotex-module-name"><?php echo esc_html( $data['name'] ); ?></h3>
                </div>
                <div class="cotex-status-badge">
                    <?php echo $is_active ? 'Active' : 'Inactive'; ?>
                </div>
            </div>
            
            <p class="cotex-module-desc"><?php echo esc_html( $data['description'] ); ?></p>
            
            <div class="cotex-footer">
                <label class="cotex-toggle">
                    <input type="checkbox" class="cotex-toggle-input" data-slug="<?php echo esc_attr( $slug ); ?>" <?php checked( $is_active ); ?>>
                    <span class="cotex-slider"></span>
                </label>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
