<?php
/**
 * The [akoml_switcher] shortcode.
 *
 * @package AkoMultilang
 */

namespace AkoMultilang\Switcher;

defined( 'ABSPATH' ) || exit;

/**
 * The shortcode returns the shared renderer's output.
 */
final class Shortcode {

	/**
	 * Registers the shortcode.
	 */
	public static function register() {
		add_shortcode( 'akoml_switcher', array( self::class, 'render' ) );
	}

	/**
	 * [akoml_switcher id="header" type="segmented" show="code"]
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
			'akoml_switcher'
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
}
