<?php
/**
 * Template tags for theme developers.
 *
 * @package Ako\Multilang
 */

use Ako\Multilang\Switcher\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * Returns a language switcher's HTML.
 *
 * @param string $id   Switcher ID from Ako → Switcher ('' for the first one).
 * @param array  $args Optional overrides: style, show.
 * @return string
 */
function ako_ml_get_switcher( $id = '', $args = array() ) {
	$overrides = array();
	foreach ( array( 'style', 'show' ) as $key ) {
		if ( isset( $args[ $key ] ) ) {
			$overrides[ $key ] = sanitize_key( (string) $args[ $key ] );
		}
	}
	return Renderer::render( sanitize_key( (string) $id ), $overrides, 'php' );
}

/**
 * Prints a language switcher: `<?php ako_ml_switcher( 'header' ); ?>`.
 *
 * @param string $id   Switcher ID ('' for the first one).
 * @param array  $args Optional overrides: style, show.
 */
function ako_ml_switcher( $id = '', $args = array() ) {
	echo ako_ml_get_switcher( $id, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Passed through wp_kses() in Renderer::render().
}
