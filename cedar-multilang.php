<?php
/**
 * Plugin Name:       Cedar Multilang
 * Description:       Link your pages across languages with hreflang, right-to-left support, and a language switcher. Your URLs stay the same.
 * Version:           1.0.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Sepehr Nasihatgar
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cedar-multilang
 *
 * @package Cedar\Multilang
 */

defined( 'ABSPATH' ) || exit;

define( 'CEDAR_ML_VERSION', '1.0.0' );
define( 'CEDAR_ML_FILE', __FILE__ );
define( 'CEDAR_ML_PATH', plugin_dir_path( __FILE__ ) );
define( 'CEDAR_ML_URL', plugin_dir_url( __FILE__ ) );

/**
 * PSR-4 autoloader for the Cedar\Multilang namespace (includes/).
 *
 * @param string $class_name Fully qualified class name.
 */
function cedar_ml_autoload( $class_name ) {
	$prefix = 'Cedar\\Multilang\\';
	if ( 0 !== strpos( $class_name, $prefix ) ) {
		return;
	}

	$relative = substr( $class_name, strlen( $prefix ) );
	$file     = CEDAR_ML_PATH . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';

	if ( is_readable( $file ) ) {
		require_once $file;
	}
}
spl_autoload_register( 'cedar_ml_autoload' );

require_once CEDAR_ML_PATH . 'includes/functions.php';

register_activation_hook( __FILE__, array( 'Cedar\\Multilang\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Cedar\\Multilang\\Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Cedar\\Multilang\\Plugin', 'boot' ) );
