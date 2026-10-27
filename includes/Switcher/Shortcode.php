<?php
/**
 * [ako_switcher] and the legacy [lang_switch] shortcode from Cedar 6.2.
 *
 * @package Ako\Multilang
 */

namespace Ako\Multilang\Switcher;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcodes return the shared renderer's output.
 */
final class Shortcode {

	/**
	 * Registers the shortcodes.
	 */
	public static function register() {
		add_shortcode( 'ako_switcher', array( self::class, 'render' ) );

		// Sites upgrading from 6.2 keep working; another plugin's [lang_switch] wins.
		if ( ! shortcode_exists( 'lang_switch' ) ) {
			add_shortcode( 'lang_switch', array( self::class, 'render_legacy' ) );
		}
	}

	/**
	 * [ako_switcher id="header" type="segmented" show="code"]
	 *
	 * `type` (alias `style`) and `show` override the saved switcher; every
	 * value is validated against the same allow-lists as the settings screen.
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'    => '',
				'type'  => '',
				'style' => '',
				'show'  => '',
			),
			is_array( $atts ) ? $atts : array(),
			'ako_switcher'
		);

		$overrides = array();
		$style     = '' !== $atts['type'] ? $atts['type'] : $atts['style'];
		if ( '' !== $style ) {
			$overrides['style'] = sanitize_key( $style );
		}
		if ( '' !== $atts['show'] ) {
			$overrides['show'] = sanitize_key( $atts['show'] );
		}

		return Renderer::render( sanitize_key( $atts['id'] ), $overrides, 'shortcode' );
	}

	/**
	 * [lang_switch] from Cedar 6.2: a link to the other language.
	 *
	 * @return string
	 */
	public static function render_legacy() {
		$id = Profiles::get( 'legacy' ) ? 'legacy' : '';
		return Renderer::render(
			$id,
			'' === $id ? array(
				'style' => 'link',
				'show'  => 'native',
			) : array(),
			'shortcode'
		);
	}
}
