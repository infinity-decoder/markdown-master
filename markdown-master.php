<?php
/**
 * Plugin Name:       Markdown Master
 * Plugin URI:        https://infinitydecoder.com
 * Description:       A powerful plugin to create quizzes, markdown notes, and code snippets with import/export and analytics.
 * Version:           1.0.0
 * Author:            Infinity Decoder
 * Author URI:        https://infinitydecoder.com
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       markdown-master
 * Domain Path:       /languages
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Define plugin constants
 */
define( 'MM_VERSION', '1.0.0' );
define( 'MM_PLUGIN_FILE', __FILE__ );
define( 'MM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MM_INCLUDES', MM_PLUGIN_DIR . 'includes/' );
define( 'MM_ADMIN', MM_PLUGIN_DIR . 'admin/' );
define( 'MM_PUBLIC', MM_PLUGIN_DIR . 'public/' );
define( 'MM_ASSETS', MM_PLUGIN_DIR . 'assets/' );

/**
 * Autoloader for classes
 */
spl_autoload_register( function ( $class ) {
    if ( strpos( $class, 'MM_' ) === 0 ) {
        $filename = 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
        $filepath = MM_INCLUDES . $filename;
        if ( file_exists( $filepath ) ) {
            require_once $filepath;
        }
    }
});

/**
 * Activation and Deactivation Hooks
 */
function mm_activate_plugin() {
    require_once MM_INCLUDES . 'class-mm-activator.php';
    MM_Activator::activate();
}
register_activation_hook( __FILE__, 'mm_activate_plugin' );

function mm_deactivate_plugin() {
    require_once MM_INCLUDES . 'class-mm-deactivator.php';
    MM_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'mm_deactivate_plugin' );

/**
 * Initialize the plugin
 */
function mm_run_plugin() {
    require_once MM_INCLUDES . 'class-mm-loader.php';
    $plugin = new MM_Loader();
    $plugin->run();
}
add_action( 'plugins_loaded', 'mm_run_plugin' );
