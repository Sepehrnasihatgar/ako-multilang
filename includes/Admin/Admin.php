<?php
/**
 * Admin menu, screens and assets.
 *
 * @package Cedar\Multilang
 */

namespace Cedar\Multilang\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * One top-level "Cedar" menu. Each submenu renders the same React root with a
 * different screen; scripts and styles load only on these screens.
 */
final class Admin {

	const SLUG = 'cedar-multilang';

	const SCRIPT = 'cedar-ml-admin';

	/**
	 * Hook suffixes of Cedar screens, keyed by screen ID.
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
		add_filter( 'plugin_action_links_' . plugin_basename( CEDAR_ML_FILE ), array( self::class, 'action_links' ) );
	}

	/**
	 * Screens in menu order: id => [ menu slug, title, capability ].
	 *
	 * @return array<string,array{slug:string,title:string,cap:string}>
	 */
	public static function screens() {
		return array(
			'translations' => array(
				'slug'  => self::SLUG,
				'title' => __( 'Translations', 'cedar-multilang' ),
				'cap'   => 'edit_posts',
			),
			'languages'    => array(
				'slug'  => self::SLUG . '-languages',
				'title' => __( 'Languages', 'cedar-multilang' ),
				'cap'   => 'manage_options',
			),
		);
	}

	/**
	 * Registers the menu and submenus.
	 */
	public static function menu() {
		add_menu_page(
			__( 'Cedar Multilang', 'cedar-multilang' ),
			__( 'Cedar', 'cedar-multilang' ),
			'edit_posts',
			self::SLUG,
			array( self::class, 'render' ),
			self::menu_icon(),
			81
		);

		foreach ( self::screens() as $id => $screen ) {
			$hook = add_submenu_page(
				self::SLUG,
				$screen['title'] . ' ‹ ' . __( 'Cedar Multilang', 'cedar-multilang' ),
				$screen['title'],
				$screen['cap'],
				$screen['slug'],
				array( self::class, 'render' )
			);
			if ( $hook ) {
				self::$hooks[ $id ] = $hook;
			}
		}
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

		if ( '' === $screen_id || ! current_user_can( $screens[ $screen_id ]['cap'] ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'cedar-multilang' ), 403 );
		}

		$nav = array();
		foreach ( $screens as $id => $screen ) {
			if ( current_user_can( $screen['cap'] ) ) {
				$nav[ $id ] = admin_url( 'admin.php?page=' . $screen['slug'] );
			}
		}

		$config = array(
			'screen'    => $screen_id,
			'version'   => CEDAR_ML_VERSION,
			'urls'      => $nav,
			'canManage' => current_user_can( 'manage_options' ),
		);

		echo '<div class="wrap cedar-ml-notices"><hr class="wp-header-end"></div>';
		printf(
			'<div id="cedar-ml-root" class="cedar-ml cedar-ml-root" data-config="%s"></div>',
			esc_attr( wp_json_encode( $config ) )
		);
		printf(
			'<noscript><div class="notice notice-error"><p>%s</p></div></noscript>',
			esc_html__( 'Cedar Multilang needs JavaScript to show this screen.', 'cedar-multilang' )
		);
	}

	/**
	 * Loads the admin app on Cedar screens only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public static function enqueue( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, self::$hooks, true ) ) {
			return;
		}

		$asset_file = CEDAR_ML_PATH . 'build/admin/index.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			add_action( 'admin_notices', array( self::class, 'missing_build_notice' ) );
			return;
		}
		$asset = include $asset_file;

		wp_enqueue_script(
			self::SCRIPT,
			CEDAR_ML_URL . 'build/admin/index.js',
			$asset['dependencies'],
			$asset['version'],
			array( 'in_footer' => true )
		);
		wp_set_script_translations( self::SCRIPT, 'cedar-multilang', CEDAR_ML_PATH . 'languages' );

		wp_enqueue_style(
			self::SCRIPT,
			CEDAR_ML_URL . 'build/admin/style-index.css',
			array(),
			$asset['version']
		);
		wp_style_add_data( self::SCRIPT, 'rtl', 'replace' );
	}

	/**
	 * Shown only in development checkouts where the app wasn't built.
	 */
	public static function missing_build_notice() {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Cedar Multilang’s admin files are missing. Reinstall the plugin, or run “npm run build” in a development copy.', 'cedar-multilang' )
		);
	}

	/**
	 * Adds a body class on Cedar screens (for the page canvas).
	 *
	 * @param string $classes Space-separated classes.
	 * @return string
	 */
	public static function body_class( $classes ) {
		if ( '' !== self::current_screen_id() ) {
			$classes .= ' cedar-ml-screen';
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
		if ( current_user_can( 'edit_posts' ) ) {
			array_unshift(
				$links,
				sprintf(
					'<a href="%s">%s</a>',
					esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ),
					esc_html__( 'Translations', 'cedar-multilang' )
				)
			);
		}
		return $links;
	}

	/**
	 * The Cedar mark as a data URI (WordPress tints it to match the admin color scheme).
	 *
	 * @return string
	 */
	private static function menu_icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><path fill="black" d="M16 5.5 10.5 12h3L9 17.5h3.5l-5 6h17l-5-6H23L18.5 12h3L16 5.5Z"/><path fill="black" d="M15.55 23.5h.9c.44 0 .8.36.8.8v1.9c0 .44-.36.8-.8.8h-.9a.8.8 0 0 1-.8-.8v-1.9c0-.44.36-.8.8-.8Z"/></svg>';
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encodes our own static SVG for the menu icon, as WordPress expects.
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}
}
