<?php
/**
 * Plugin bootstrap: wires every module to WordPress hooks.
 *
 * @package Cedar\Multilang
 */

namespace Cedar\Multilang;

use Cedar\Multilang\Admin\Admin;
use Cedar\Multilang\Admin\Editor;
use Cedar\Multilang\Content\Links;
use Cedar\Multilang\Content\Taxonomies;
use Cedar\Multilang\Migration\Legacy62;
use Cedar\Multilang\Rtl\Locale;
use Cedar\Multilang\Rest\Rest;
use Cedar\Multilang\Seo\Hreflang;
use Cedar\Multilang\Switcher\Block;
use Cedar\Multilang\Switcher\MenuItem;
use Cedar\Multilang\Switcher\Renderer;
use Cedar\Multilang\Switcher\Shortcode;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class. Nothing runs at file load; everything hangs off hooks.
 */
final class Plugin {

	/**
	 * Option that stores the installed data version (for migrations).
	 */
	const DB_VERSION_OPTION = 'cedar_ml_db_version';

	/**
	 * Current data version.
	 */
	const DB_VERSION = 1;

	/**
	 * Whether boot() already ran.
	 *
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * Registers all hooks. Called on `plugins_loaded`.
	 */
	public static function boot() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		// Late on init, so post types registered at the default priority exist.
		add_action( 'init', array( Taxonomies::class, 'register' ), 99 );

		// Keep groups consistent when content is permanently deleted.
		add_action( 'before_delete_post', array( Links::class, 'unlink' ) );

		$settings = Settings::get();

		if ( ! empty( $settings['modules']['rtl'] ) ) {
			Locale::register();
		}

		if ( ! empty( $settings['modules']['seo'] ) ) {
			Hreflang::register();
		}

		if ( ! empty( $settings['modules']['switcher'] ) ) {
			Renderer::register();
			Shortcode::register();
			MenuItem::register();
			Block::register();
		}

		Legacy62::register();
		Rest::register();

		if ( is_admin() ) {
			Admin::register();
			Editor::register();
		}

		/**
		 * Fires once Cedar Multilang has registered its hooks.
		 *
		 * Pro and other extensions should attach to this action.
		 */
		do_action( 'cedar_ml_loaded' );
	}

	/**
	 * Activation: register taxonomies so terms work immediately and remember
	 * that setup should be offered (via a dismissible notice, never a redirect).
	 */
	public static function activate() {
		Taxonomies::register();

		if ( false === get_option( self::DB_VERSION_OPTION, false ) ) {
			add_option( self::DB_VERSION_OPTION, self::DB_VERSION, '', false );
			add_option( 'cedar_ml_needs_setup', 1, '', false );
		}
	}

	/**
	 * Deactivation: nothing to clean up. Data is removed only by uninstall.php,
	 * and only when the user opted in.
	 */
	public static function deactivate() {}
}
