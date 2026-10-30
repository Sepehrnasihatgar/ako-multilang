<?php
/**
 * Plugin Name:       Ako Multilang
 * Description:       Link your pages across languages with hreflang, right-to-left support, and a language switcher. Your URLs stay the same.
 * Version:           1.0.2
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Sepehr Nasihatgar
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ako-multilang
 *
 * @package AkoMultilang
 */

defined( 'ABSPATH' ) || exit;

define( 'AKOML_VERSION', '1.0.2' );
define( 'AKOML_FILE', __FILE__ );
define( 'AKOML_PATH', plugin_dir_path( __FILE__ ) );
define( 'AKOML_URL', plugin_dir_url( __FILE__ ) );

/**
 * PSR-4 autoloader for the AkoMultilang namespace (includes/).
 *
 * @param string $class_name Fully qualified class name.
 */
function akoml_autoload( $class_name ) {
	$prefix = 'AkoMultilang\\';
	if ( 0 !== strpos( $class_name, $prefix ) ) {
		return;
	}

	$relative = substr( $class_name, strlen( $prefix ) );
	$file     = AKOML_PATH . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';

	if ( is_readable( $file ) ) {
		require_once $file;
	}
}
spl_autoload_register( 'akoml_autoload' );

require_once AKOML_PATH . 'includes/functions.php';

register_activation_hook( __FILE__, array( 'AkoMultilang\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AkoMultilang\\Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'AkoMultilang\\Plugin', 'boot' ) );
