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
 * Autoloader for classes named MM_*
 * Fallback: if autoloader cannot find a file, other code may require it explicitly.
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
 * Ensure core include files exist and are loadable.
 * Using require_once here is safe because files are idempotent and won't produce output.
 * These are small, essential classes used during activation and basic operations.
 */
if ( file_exists( MM_INCLUDES . 'class-mm-activator.php' ) ) {
    require_once MM_INCLUDES . 'class-mm-activator.php';
}
if ( file_exists( MM_INCLUDES . 'class-mm-quiz.php' ) ) {
    require_once MM_INCLUDES . 'class-mm-quiz.php';
}

/**
 * Activation and Deactivation Hooks
 * - Keep wrapper functions to require files and call the class methods so it works regardless of autoloader state.
 */

function mm_activate_plugin() {
    // Class file included above via require_once, but keep safe-check here
    if ( ! class_exists( 'MM_Activator' ) && file_exists( MM_INCLUDES . 'class-mm-activator.php' ) ) {
        require_once MM_INCLUDES . 'class-mm-activator.php';
    }
    if ( class_exists( 'MM_Activator' ) ) {
        MM_Activator::activate();
    }
}
register_activation_hook( __FILE__, 'mm_activate_plugin' );

function mm_deactivate_plugin() {
    if ( ! class_exists( 'MM_Deactivator' ) && file_exists( MM_INCLUDES . 'class-mm-deactivator.php' ) ) {
        require_once MM_INCLUDES . 'class-mm-deactivator.php';
    }
    if ( class_exists( 'MM_Deactivator' ) ) {
        MM_Deactivator::deactivate();
    }
}
register_deactivation_hook( __FILE__, 'mm_deactivate_plugin' );

/**
 * Initialize the plugin (load loader which wires admin / frontend)
 * We hook on plugins_loaded so other plugins and WP core are available.
 */
function mm_run_plugin() {
    // Ensure loader exists
    if ( file_exists( MM_INCLUDES . 'class-mm-loader.php' ) ) {
        require_once MM_INCLUDES . 'class-mm-loader.php';
        // Loader will instantiate admin/frontend and call init_hooks()
        if ( class_exists( 'MM_Loader' ) ) {
            $plugin = new MM_Loader();
            // optionally trigger activator's maybe_upgrade if exists
            if ( method_exists( 'MM_Activator', 'maybe_upgrade' ) ) {
                // silent upgrade (no output)
                MM_Activator::maybe_upgrade();
            }
            $plugin->run();
        }
    } else {
        // If loader missing, fail silently (no output). Admin notice logic can be added to inform the admin.
    }
}
add_action( 'plugins_loaded', 'mm_run_plugin' );
