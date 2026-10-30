<?php
/**
 * Admin menu, screens and assets.
 *
 * @package AkoMultilang
 */

namespace AkoMultilang\Admin;

use AkoMultilang\Settings;
use AkoMultilang\Setup\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * One top-level "Ako" menu. Each submenu renders the same React root with a
 * different screen; scripts and styles load only on these screens.
 */
final class Admin {

	const SLUG = 'ako-multilang';

	const SCRIPT = 'akoml-admin';

	/**
	 * Hook suffixes of Ako screens, keyed by screen ID.
	 *
	 * @var array<string,string>
	 */
	private static $hooks = array();

	/**
	 * Hooks into the admin.
	 */
	public static function register() {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_filter( 'admin_body_class', array( self::class, 'body_class' ) );
		add_action( 'admin_head', array( self::class, 'hide_setup_entry' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( AKOML_FILE ), array( self::class, 'action_links' ) );
	}

	/**
	 * Screens in menu order: id => [ menu slug, title, capability ].
	 *
	 * @return array<string,array{slug:string,title:string,cap:string}>
	 */
	public static function screens() {
		$screens = array(
			'translations' => array(
				'slug'  => self::SLUG,
				'title' => __( 'Translations', 'ako-multilang' ),
				'cap'   => 'edit_posts',
			),
			'languages'    => array(
				'slug'  => self::SLUG . '-languages',
				'title' => __( 'Languages', 'ako-multilang' ),
				'cap'   => 'manage_options',
			),
			'switcher'     => array(
				'slug'  => self::SLUG . '-switcher',
				'title' => __( 'Switcher', 'ako-multilang' ),
				'cap'   => 'manage_options',
			),
		);

		$screens['seo'] = array(
			'slug'  => self::SLUG . '-seo',
			'title' => __( 'SEO', 'ako-multilang' ),
			'cap'   => 'manage_options',
		);

		$screens['settings'] = array(
			'slug'  => self::SLUG . '-settings',
			'title' => __( 'Settings', 'ako-multilang' ),
			'cap'   => 'manage_options',
		);
		$screens['about']    = array(
			'slug'  => self::SLUG . '-about',
			'title' => __( 'About', 'ako-multilang' ),
			'cap'   => 'manage_options',
		);

		if ( empty( Settings::get()['modules']['seo'] ) ) {
			unset( $screens['seo'] );
		}
		if ( empty( Settings::get()['modules']['switcher'] ) ) {
			unset( $screens['switcher'] );
		}
		return $screens;
	}

	/**
	 * Registers the menu and submenus.
	 */
	public static function menu() {
		add_menu_page(
			__( 'Ako Multilang', 'ako-multilang' ),
			__( 'Ako', 'ako-multilang' ),
			'edit_posts',
			self::SLUG,
			array( self::class, 'render' ),
			self::menu_icon(),
			81
		);

		foreach ( self::screens() as $id => $screen ) {
			$hook = add_submenu_page(
				self::SLUG,
				$screen['title'] . ' ‹ ' . __( 'Ako Multilang', 'ako-multilang' ),
				$screen['title'],
				$screen['cap'],
				$screen['slug'],
				array( self::class, 'render' )
			);
			if ( $hook ) {
				self::$hooks[ $id ] = $hook;
			}
		}

		// Setup has no menu entry: it's reached from notices and the Translations screen.
		$hook = add_submenu_page(
			self::SLUG,
			__( 'Set up Ako', 'ako-multilang' ),
			__( 'Set up Ako', 'ako-multilang' ),
			'manage_options',
			self::SLUG . '-setup',
			array( self::class, 'render' )
		);
		if ( $hook ) {
			self::$hooks['setup'] = $hook;
		}
	}

	/**
	 * Hides the setup entry from the menu once the page title is set.
	 */
	public static function hide_setup_entry() {
		remove_submenu_page( self::SLUG, self::SLUG . '-setup' );
	}

	/**
	 * The screen ID for the current admin page, or ''.
	 *
	 * @return string
	 */
	private static function current_screen_id() {
		$wp_screen = get_current_screen();
		if ( ! $wp_screen ) {
			return '';
		}
		$id = array_search( $wp_screen->id, self::$hooks, true );
		return false === $id ? '' : (string) $id;
	}

	/**
	 * Prints the app root. All UI is rendered by the admin app.
	 */
	public static function render() {
		$screen_id = self::current_screen_id();
		$screens   = self::screens();
		$cap       = 'setup' === $screen_id ? 'manage_options' : ( isset( $screens[ $screen_id ] ) ? $screens[ $screen_id ]['cap'] : '' );

		if ( '' === $cap || ! current_user_can( $cap ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'ako-multilang' ), 403 );
		}

		$nav = array();
		foreach ( $screens as $id => $screen ) {
			if ( current_user_can( $screen['cap'] ) ) {
				$nav[ $id ] = admin_url( 'admin.php?page=' . $screen['slug'] );
			}
		}

		$settings = Settings::get();
		$config   = array(
			'screen'     => $screen_id,
			'version'    => AKOML_VERSION,
			'urls'       => $nav,
			'setupUrl'   => admin_url( 'admin.php?page=' . self::SLUG . '-setup' ),
			'needsSetup' => Wizard::needs_setup(),
			'modules'    => $settings['modules'],
			'canManage'  => current_user_can( 'manage_options' ),
			'siteName'   => wp_strip_all_tags( get_bloginfo( 'name' ) ),
		);

		echo '<div class="wrap akoml-notices"><hr class="wp-header-end"></div>';
		printf(
			'<div id="akoml-root" class="ako-ml akoml-root" data-config="%s"></div>',
			esc_attr( wp_json_encode( $config ) )
		);
		printf(
			'<noscript><div class="notice notice-error"><p>%s</p></div></noscript>',
			esc_html__( 'Ako Multilang needs JavaScript to show this screen.', 'ako-multilang' )
		);
	}

	/**
	 * Loads the admin app on Ako screens only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public static function enqueue( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, self::$hooks, true ) ) {
			return;
		}

		$asset_file = AKOML_PATH . 'build/admin/index.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			add_action( 'admin_notices', array( self::class, 'missing_build_notice' ) );
			return;
		}
		$asset = include $asset_file;

		wp_enqueue_script(
			self::SCRIPT,
			AKOML_URL . 'build/admin/index.js',
			$asset['dependencies'],
			$asset['version'],
			array( 'in_footer' => true )
		);
		wp_set_script_translations( self::SCRIPT, 'ako-multilang', AKOML_PATH . 'languages' );

		wp_enqueue_style(
			self::SCRIPT,
			AKOML_URL . 'build/admin/index.css',
			array(),
			$asset['version']
		);
		wp_style_add_data( self::SCRIPT, 'rtl', 'replace' );

		// The designer and setup preview the real front-end stylesheet.
		if ( in_array( array_search( $hook_suffix, self::$hooks, true ), array( 'switcher', 'setup' ), true ) ) {
			wp_enqueue_style( 'akoml-switcher' );
		}
	}

	/**
	 * Shown only in development checkouts where the app wasn't built.
	 */
	public static function missing_build_notice() {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Ako Multilang’s admin files are missing. Reinstall the plugin, or run “npm run build” in a development copy.', 'ako-multilang' )
		);
	}

	/**
	 * Adds a body class on Ako screens (for the page canvas).
	 *
	 * @param string $classes Space-separated classes.
	 * @return string
	 */
	public static function body_class( $classes ) {
		if ( '' !== self::current_screen_id() ) {
			$classes .= ' akoml-screen';
		}
		return $classes;
	}

	/**
	 * Plugin row links.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		if ( current_user_can( 'manage_options' ) ) {
			$link = Wizard::needs_setup()
				? array( 'setup', __( 'Start setup', 'ako-multilang' ) )
				: array( 'settings', __( 'Settings', 'ako-multilang' ) );
			array_unshift(
				$links,
				sprintf(
					'<a href="%s">%s</a>',
					esc_url( admin_url( 'admin.php?page=' . self::SLUG . '-' . $link[0] ) ),
					esc_html( $link[1] )
				)
			);
		} elseif ( current_user_can( 'edit_posts' ) ) {
			array_unshift(
				$links,
				sprintf(
					'<a href="%s">%s</a>',
					esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ),
					esc_html__( 'Translations', 'ako-multilang' )
				)
			);
		}
		return $links;
	}

	/**
	 * The Ako favicon mark (two short lines of dots) as a data URI. WordPress
	 * tints it to match the admin color scheme.
	 *
	 * @return string
	 */
	private static function menu_icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><path fill="black" d="M2 12.902a4 4 0 1 0 8 0a4 4 0 1 0 -8 0ZM11.2 12.902a2.8 2.8 0 1 0 5.6 0a2.8 2.8 0 1 0 -5.6 0ZM18 12.902a2.8 2.8 0 1 0 5.6 0a2.8 2.8 0 1 0 -5.6 0ZM8.4 19.098a2.8 2.8 0 1 0 5.6 0a2.8 2.8 0 1 0 -5.6 0ZM15.2 19.098a2.8 2.8 0 1 0 5.6 0a2.8 2.8 0 1 0 -5.6 0ZM22 19.098a4 4 0 1 0 8 0a4 4 0 1 0 -8 0Z"/></svg>';
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encodes our own static SVG for the menu icon, as WordPress expects.
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}
}
