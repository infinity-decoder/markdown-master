<?php
/**
 * Plugin Name:       Cortex
 * Plugin URI:        https://infinitydecoder.com/cortex
 * Description:       Production-grade quiz and survey engine with 11 question types, markdown rendering, code snippets, question banks, lead capture, and comprehensive analytics. Built for security, performance, and extensibility.
 * Version:           2.0.0
 * Requires at least: 5.8
 * Requires PHP:      8.0
 * Author:            Infinity Decoder
 * Author URI:        https://infinitydecoder.com
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       cortex
 * Domain Path:       /languages
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Define plugin constants
 */
define( 'CORTEX_VERSION', '2.0.0' );
define( 'CORTEX_PLUGIN_FILE', __FILE__ );
define( 'CORTEX_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CORTEX_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CORTEX_INCLUDES', CORTEX_PLUGIN_DIR . 'includes/' );
define( 'CORTEX_ADMIN', CORTEX_PLUGIN_DIR . 'admin/' );
define( 'CORTEX_PUBLIC', CORTEX_PLUGIN_DIR . 'public/' );
define( 'CORTEX_ASSETS', CORTEX_PLUGIN_DIR . 'assets/' );

/**
 * Check PHP and WordPress version requirements
 */
function cortex_check_requirements() {
    $php_version = '8.0';
    $wp_version = '5.8';
    
    if ( version_compare( PHP_VERSION, $php_version, '<' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die(
            sprintf(
                /* translators: 1: Required PHP version, 2: Current PHP version */
                esc_html__( 'Cortex requires PHP %1$s or higher. Your server is running PHP %2$s. Please upgrade PHP or contact your hosting provider.', 'cortex' ),
                esc_html( $php_version ),
                esc_html( PHP_VERSION )
            ),
            esc_html__( 'Plugin Activation Error', 'cortex' ),
            array( 'back_link' => true )
        );
    }
    
    global $wp_version;
    if ( version_compare( $wp_version, $wp_version, '<' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die(
            sprintf(
                /* translators: 1: Required WordPress version, 2: Current WordPress version */
                esc_html__( 'Cortex requires WordPress %1$s or higher. You are running WordPress %2$s. Please upgrade WordPress.', 'cortex' ),
                esc_html( $wp_version ),
                esc_html( $wp_version )
            ),
            esc_html__( 'Plugin Activation Error', 'cortex' ),
            array( 'back_link' => true )
        );
    }
}
register_activation_hook( __FILE__, 'cortex_check_requirements' );

/**
 * Autoloader for classes named Cortex_*
 * Fallback: if autoloader cannot find a file, other code may require it explicitly.
 */
spl_autoload_register( function ( $class ) {
    if ( strpos( $class, 'Cortex_' ) === 0 ) {
        $filename = 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
        $filepath = CORTEX_INCLUDES . $filename;
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
    'class-cortex-activator.php',
    'class-cortex-security.php',
    'class-cortex-cache.php',
    'class-cortex-quiz.php',
    'class-cortex-question-bank.php',
    'class-cortex-lead-capture.php',
    'class-cortex-markdown.php',
    'class-cortex-markdown-snippets.php',
    'class-cortex-snippet.php',
    'class-cortex-math-renderer.php',
    'class-cortex-highlighter.php',
);

foreach ( $core_classes as $class_file ) {
    $filepath = CORTEX_INCLUDES . $class_file;
    if ( file_exists( $filepath ) ) {
        require_once $filepath;
    }
}

/**
 * Activation and Deactivation Hooks
 * - Keep wrapper functions to require files and call the class methods so it works regardless of autoloader state.
 */

function cortex_activate_plugin() {
    // Class file included above via require_once, but keep safe-check here
    if ( ! class_exists( 'Cortex_Activator' ) && file_exists( CORTEX_INCLUDES . 'class-cortex-activator.php' ) ) {
        require_once CORTEX_INCLUDES . 'class-cortex-activator.php';
    }
    if ( class_exists( 'Cortex_Activator' ) ) {
        Cortex_Activator::activate();
    }
}
register_activation_hook( __FILE__, 'cortex_activate_plugin' );

function cortex_deactivate_plugin() {
    if ( ! class_exists( 'Cortex_Deactivator' ) && file_exists( CORTEX_INCLUDES . 'class-cortex-deactivator.php' ) ) {
        require_once CORTEX_INCLUDES . 'class-cortex-deactivator.php';
    }
    if ( class_exists( 'Cortex_Deactivator' ) ) {
        Cortex_Deactivator::deactivate();
    }
}
register_deactivation_hook( __FILE__, 'cortex_deactivate_plugin' );

/**
 * Initialize the plugin (load loader which wires admin / frontend)
 * We hook on plugins_loaded so other plugins and WP core are available.
 */
function cortex_run_plugin() {
    // Ensure loader exists
    if ( file_exists( CORTEX_INCLUDES . 'class-cortex-loader.php' ) ) {
        require_once CORTEX_INCLUDES . 'class-cortex-loader.php';
        // Loader will instantiate admin/frontend and call init_hooks()
        if ( class_exists( 'Cortex_Loader' ) ) {
            $plugin = new Cortex_Loader();
            // optionally trigger activator's maybe_upgrade if exists
            if ( method_exists( 'Cortex_Activator', 'maybe_upgrade' ) ) {
                // silent upgrade (no output)
                Cortex_Activator::maybe_upgrade();
            }
            $plugin->run();
        }
    } else {
        // If loader missing, fail silently (no output). Admin notice logic can be added to inform the admin.
    }
}
add_action( 'plugins_loaded', 'cortex_run_plugin' );
