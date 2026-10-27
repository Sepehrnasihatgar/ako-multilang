<?php
/**
 * The one renderer behind the block, shortcode, menu item, PHP tag and the
 * floating switcher.
 *
 * @package Cedar\Multilang
 */

namespace Cedar\Multilang\Switcher;

defined( 'ABSPATH' ) || exit;

/**
 * Renders a switcher through templates/switcher.php (overridable by themes at
 * `cedar-multilang/switcher.php`) and passes the result through wp_kses() with
 * a strict allow-list, so even an overridden template can't print scripts.
 */
final class Renderer {

	const STYLE_HANDLE  = 'cedar-ml-switcher';
	const SCRIPT_HANDLE = 'cedar-ml-switcher';

	/**
	 * Registers front-end assets and the floating switcher.
	 */
	public static function register() {
		add_action( 'init', array( self::class, 'register_assets' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_style' ) );
		add_action( 'wp_footer', array( self::class, 'print_floating' ) );
	}

	/**
	 * Registers the stylesheet (< 3 KB) and the keyboard enhancement (< 1 KB).
	 */
	public static function register_assets() {
		wp_register_style( self::STYLE_HANDLE, CEDAR_ML_URL . 'assets/switcher.css', array(), CEDAR_ML_VERSION );
		wp_register_script(
			self::SCRIPT_HANDLE,
			CEDAR_ML_URL . 'assets/switcher.js',
			array(),
			CEDAR_ML_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Loads the stylesheet in <head> (no flash of unstyled switcher) plus each
	 * profile's accent color as custom properties.
	 */
	public static function enqueue_style() {
		wp_enqueue_style( self::STYLE_HANDLE );
		wp_add_inline_style( self::STYLE_HANDLE, self::profile_css() );
	}

	/**
	 * Accent colors as `--cedar-switcher-accent` overrides, one rule per profile.
	 *
	 * @return string
	 */
	public static function profile_css() {
		$css = '';
		foreach ( Profiles::all() as $profile ) {
			if ( '' === $profile['accent'] || 'inherit' === $profile['styling'] ) {
				continue;
			}
			$css .= sprintf(
				'.cedar-switcher[data-switcher="%1$s"]{--cedar-switcher-accent:%2$s;--cedar-switcher-on-accent:%3$s;--cedar-switcher-focus-ring:%2$s}',
				sanitize_key( $profile['id'] ),
				sanitize_hex_color( $profile['accent'] ),
				self::is_light( $profile['accent'] ) ? '#181815' : '#ffffff'
			);
		}
		return $css;
	}

	/**
	 * Whether text on this color should be dark (WCAG relative luminance).
	 *
	 * @param string $hex Hex color.
	 * @return bool
	 */
	private static function is_light( $hex ) {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$channels = array();
		foreach ( array( 0, 2, 4 ) as $offset ) {
			$c          = hexdec( substr( $hex, $offset, 2 ) ) / 255;
			$channels[] = $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		}
		$luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
		return $luminance > 0.4;
	}

	/**
	 * Renders a switcher.
	 *
	 * @param string $id        Profile ID ('' for the first profile).
	 * @param array  $overrides Profile values to override (e.g. from shortcode attributes).
	 * @param string $context   block|shortcode|menu|php|footer.
	 * @return string Safe HTML ('' when there's nothing to switch to).
	 */
	public static function render( $id = '', array $overrides = array(), $context = 'php' ) {
		$profile = '' === $id ? Profiles::first() : Profiles::get( $id );
		if ( ! $profile ) {
			return '';
		}
		$profile = Profiles::with_overrides( $profile, $overrides );

		$items  = Items::for_request( $profile );
		$others = array_filter(
			$items,
			static function ( $item ) {
				return ! $item['current'];
			}
		);
		if ( ! $others ) {
			return '';
		}

		if ( in_array( $profile['style'], array( 'dropdown', 'floating' ), true ) ) {
			wp_enqueue_script( self::SCRIPT_HANDLE );
		}

		$template = locate_template( 'cedar-multilang/switcher.php' );
		if ( '' === $template ) {
			$template = CEDAR_ML_PATH . 'templates/switcher.php';
		}

		ob_start();
		load_template(
			$template,
			false,
			array(
				'profile' => $profile,
				'items'   => $items,
				'context' => $context,
			)
		);
		$html = (string) ob_get_clean();

		/**
		 * Filters the switcher markup (still passed through wp_kses()).
		 *
		 * @param string $html    Markup.
		 * @param array  $profile Switcher profile.
		 * @param string $context Where it renders.
		 */
		$html = (string) apply_filters( 'cedar_ml_switcher_markup', $html, $profile, $context );

		return wp_kses( $html, self::allowed_html() );
	}

	/**
	 * Prints the first floating switcher (bottom corner, every page).
	 */
	public static function print_floating() {
		foreach ( Profiles::all() as $profile ) {
			if ( 'floating' === $profile['style'] ) {
				echo self::render( $profile['id'], array(), 'footer' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Passed through wp_kses() in render().
				return;
			}
		}
	}

	/**
	 * Tags and attributes the switcher may contain.
	 *
	 * @return array
	 */
	public static function allowed_html() {
		$common = array(
			'class'       => true,
			'aria-hidden' => true,
		);
		$svg    = array(
			'class'           => true,
			'width'           => true,
			'height'          => true,
			'viewbox'         => true,
			'fill'            => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
			'aria-hidden'     => true,
			'focusable'       => true,
		);

		return array(
			'nav'     => $common + array(
				'aria-label'    => true,
				'data-switcher' => true,
			),
			'div'     => $common,
			'ul'      => $common + array( 'role' => true ),
			'li'      => $common,
			'a'       => $common + array(
				'href'         => true,
				'hreflang'     => true,
				'lang'         => true,
				'dir'          => true,
				'aria-current' => true,
				'aria-label'   => true,
			),
			'span'    => $common + array(
				'lang'          => true,
				'dir'           => true,
				'aria-disabled' => true,
				'aria-current'  => true,
				'aria-label'    => true,
				'data-tooltip'  => true,
			),
			'details' => $common,
			'summary' => $common + array( 'aria-label' => true ),
			'svg'     => $svg,
			'path'    => array( 'd' => true ),
			'circle'  => array(
				'cx' => true,
				'cy' => true,
				'r'  => true,
			),
		);
	}
}
