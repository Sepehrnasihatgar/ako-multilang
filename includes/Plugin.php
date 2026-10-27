<?php
/**
 * Plugin bootstrap: wires every module to WordPress hooks.
 *
 * @package Ako\Multilang
 */

namespace Ako\Multilang;

use Ako\Multilang\Admin\Admin;
use Ako\Multilang\Admin\Editor;
use Ako\Multilang\Admin\Notices;
use Ako\Multilang\Content\Links;
use Ako\Multilang\Content\Taxonomies;
use Ako\Multilang\Migration\Legacy62;
use Ako\Multilang\Rtl\Compat;
use Ako\Multilang\Rtl\Locale;
use Ako\Multilang\Rest\Rest;
use Ako\Multilang\Seo\Hreflang;
use Ako\Multilang\Seo\Integrations;
use Ako\Multilang\Setup\Wizard;
use Ako\Multilang\Switcher\Block;
use Ako\Multilang\Switcher\MenuItem;
use Ako\Multilang\Switcher\Renderer;
use Ako\Multilang\Switcher\Shortcode;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class. Nothing runs at file load; everything hangs off hooks.
 */
final class Plugin {

	/**
	 * Option that stores the installed data version (for migrations).
	 */
	const DB_VERSION_OPTION = 'ako_ml_db_version';

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

		Legacy62::register();
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
		do_action( 'ako_ml_loaded' );
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
