<?php
/**
 * Plugin bootstrap: wires every module to WordPress hooks.
 *
 * @package AkoMultilang
 */

namespace AkoMultilang;

use AkoMultilang\Admin\Admin;
use AkoMultilang\Admin\Editor;
use AkoMultilang\Admin\Notices;
use AkoMultilang\Content\Links;
use AkoMultilang\Content\Taxonomies;
use AkoMultilang\Rtl\Compat;
use AkoMultilang\Rtl\Locale;
use AkoMultilang\Rest\Rest;
use AkoMultilang\Seo\Hreflang;
use AkoMultilang\Seo\Integrations;
use AkoMultilang\Setup\Wizard;
use AkoMultilang\Switcher\Block;
use AkoMultilang\Switcher\MenuItem;
use AkoMultilang\Switcher\Renderer;
use AkoMultilang\Switcher\Shortcode;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class. Nothing runs at file load; everything hangs off hooks.
 */
final class Plugin {

	/**
	 * Option that stores the installed data version (for migrations).
	 */
	const DB_VERSION_OPTION = 'akoml_db_version';

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
			Compat::register();
		}

		if ( ! empty( $settings['modules']['seo'] ) ) {
			Hreflang::register();
			Integrations::register();
		}

		if ( ! empty( $settings['modules']['switcher'] ) ) {
			Renderer::register();
			Shortcode::register();
			MenuItem::register();
			Block::register();
		}

		Rest::register();

		if ( is_admin() ) {
			Admin::register();
			Editor::register();
			Notices::register();
		}

		/**
		 * Fires once Ako Multilang has registered its hooks.
		 *
		 * Pro and other extensions should attach to this action.
		 */
		do_action( 'akoml_loaded' );
	}

	/**
	 * Activation: register taxonomies so terms work immediately and remember
	 * that setup should be offered (via a dismissible notice, never a redirect).
	 */
	public static function activate() {
		Taxonomies::register();

		if ( false === get_option( self::DB_VERSION_OPTION, false ) ) {
			add_option( self::DB_VERSION_OPTION, self::DB_VERSION, '', false );
			add_option( Wizard::NEEDS_SETUP_OPTION, 1, '', false );
		}
	}

	/**
	 * Deactivation: nothing to clean up. Data is removed only by uninstall.php,
	 * and only when the user opted in.
	 */
	public static function deactivate() {}
}
