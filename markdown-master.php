<?php
/**
 * Plugin Name:       Markdown Master
 * Plugin URI:        https://infinitydecoder.com/markdown-master
 * Description:       Production-grade quiz and survey engine with 11 question types, markdown rendering, code snippets, question banks, lead capture, and comprehensive analytics. Built for security, performance, and extensibility.
 * Version:           2.0.0
 * Requires at least: 5.8
 * Requires PHP:      8.0
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
define( 'MM_VERSION', '2.0.0' );
define( 'MM_PLUGIN_FILE', __FILE__ );
define( 'MM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MM_INCLUDES', MM_PLUGIN_DIR . 'includes/' );
define( 'MM_ADMIN', MM_PLUGIN_DIR . 'admin/' );
define( 'MM_PUBLIC', MM_PLUGIN_DIR . 'public/' );
define( 'MM_ASSETS', MM_PLUGIN_DIR . 'assets/' );

/**
 * Check PHP and WordPress version requirements
 */
function mm_check_requirements() {
    $php_version = '8.0';
    $wp_version = '5.8';
    
    if ( version_compare( PHP_VERSION, $php_version, '<' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die(
            sprintf(
                /* translators: 1: Required PHP version, 2: Current PHP version */
                esc_html__( 'Markdown Master requires PHP %1$s or higher. Your server is running PHP %2$s. Please upgrade PHP or contact your hosting provider.', 'markdown-master' ),
                esc_html( $php_version ),
                esc_html( PHP_VERSION )
            ),
            esc_html__( 'Plugin Activation Error', 'markdown-master' ),
            array( 'back_link' => true )
        );
    }
    
    global $wp_version;
    if ( version_compare( $wp_version, $wp_version, '<' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die(
            sprintf(
                /* translators: 1: Required WordPress version, 2: Current WordPress version */
                esc_html__( 'Markdown Master requires WordPress %1$s or higher. You are running WordPress %2$s. Please upgrade WordPress.', 'markdown-master' ),
                esc_html( $wp_version ),
                esc_html( $wp_version )
            ),
            esc_html__( 'Plugin Activation Error', 'markdown-master' ),
            array( 'back_link' => true )
        );
    }
}
register_activation_hook( __FILE__, 'mm_check_requirements' );

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
 * Load critical classes that are needed during activation and runtime.
 */
$core_classes = array(
    'class-mm-activator.php',
    'class-mm-security.php',
    'class-mm-cache.php',
    'class-mm-quiz.php',
    'class-mm-question-bank.php',
    'class-mm-lead-capture.php',
    'class-mm-markdown.php',
    'class-mm-markdown-snippets.php',
    'class-mm-snippet.php',
    'class-mm-math-renderer.php',
    'class-mm-highlighter.php',
);

foreach ( $core_classes as $class_file ) {
    $filepath = MM_INCLUDES . $class_file;
    if ( file_exists( $filepath ) ) {
        require_once $filepath;
    }
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
