<?php
/**
 * The hreflang links and the <html lang dir> attributes.
 *
 * @package AkoMultilang
 */

namespace AkoMultilang\Seo;

use AkoMultilang\Content\Links;
use AkoMultilang\Content\Taxonomies;
use AkoMultilang\Languages\Registry;
use AkoMultilang\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Outputs a complete, reciprocal hreflang cluster on singular views.
 */
final class Hreflang {

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'wp_head', array( __CLASS__, 'print_links' ), 1 );
		add_filter( 'language_attributes', array( __CLASS__, 'language_attributes' ), 20, 2 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	/**
	 * Language of the current singular view, or null.
	 *
	 * @return array|null Language definition.
	 */
	public static function current_language() {
		if ( ! is_singular() ) {
			return null;
		}
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post || ! Taxonomies::supports( $post->post_type ) ) {
			return null;
		}
		$code = Links::get_language( $post->ID );
		return $code ? Registry::get( $code ) : null;
	}

	/**
	 * The hreflang cluster for a post: `array( hreflang => url )`, x-default last.
	 * Empty when fewer than two indexable versions exist.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,string>
	 */
	public static function cluster( $post_id ) {
		$links = array();

		foreach ( Links::get_translations( $post_id ) as $code => $member_id ) {
			if ( ! self::is_indexable( $member_id ) ) {
				continue;
			}
			$language = Registry::get( $code );
			$url      = get_permalink( $member_id );
			if ( $language && $url ) {
				$links[ $code ] = array(
					'hreflang' => $language['hreflang'],
					'url'      => $url,
				);
			}
		}

		if ( count( $links ) < 2 ) {
			return array();
		}

		$out = array();
		foreach ( $links as $link ) {
			$out[ $link['hreflang'] ] = $link['url'];
		}

		$x_default = Settings::get()['x_default'];
		if ( '' === $x_default || ! Registry::exists( $x_default ) ) {
			$x_default = Registry::default_code();
		}
		if ( isset( $links[ $x_default ] ) ) {
			$out['x-default'] = $links[ $x_default ]['url'];
		}

		/**
		 * Filters the hreflang cluster before output.
		 *
		 * @param array<string,string> $out     hreflang => URL.
		 * @param int                  $post_id Current post ID.
		 */
		return (array) apply_filters( 'akoml_hreflang_links', $out, $post_id );
	}

	/**
	 * Whether a post should appear in hreflang (published, public, not noindex).
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_indexable( $post_id ) {
		$post = get_post( $post_id );
		$ok   = $post && 'publish' === $post->post_status && ! post_password_required( $post ) && is_post_type_viewable( $post->post_type );

		/**
		 * Filters whether a post is indexable. SEO integrations use this to
		 * honor their own noindex settings.
		 *
		 * @param bool $ok      Whether the post is indexable.
		 * @param int  $post_id Post ID.
		 */
		return (bool) apply_filters( 'akoml_is_indexable', $ok, (int) $post_id );
	}

	/**
	 * Whether another multilingual plugin already prints hreflang.
	 *
	 * @return bool
	 */
	private static function another_plugin_handles_hreflang() {
		$handled = defined( 'POLYLANG_VERSION' ) || defined( 'ICL_SITEPRESS_VERSION' );

		/**
		 * Filters whether Ako should stay silent because hreflang is handled elsewhere.
		 *
		 * @param bool $handled Whether hreflang is handled by another plugin.
		 */
		return (bool) apply_filters( 'akoml_hreflang_handled_elsewhere', $handled );
	}

	/**
	 * Prints `<link rel="alternate" hreflang>` tags.
	 */
	public static function print_links() {
		if ( ! is_singular() || self::another_plugin_handles_hreflang() ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id || ! self::is_indexable( $post_id ) ) {
			return;
		}

		foreach ( self::cluster( $post_id ) as $hreflang => $url ) {
			printf(
				'<link rel="alternate" hreflang="%1$s" href="%2$s" />' . "\n",
				esc_attr( $hreflang ),
				esc_url( $url )
			);
		}
	}

	/**
	 * Sets `lang` and `dir` on <html> for the current page's language.
	 *
	 * @param string $output  Attributes string built by WordPress.
	 * @param string $doctype 'html' or 'xhtml'.
	 * @return string
	 */
	public static function language_attributes( $output, $doctype = 'html' ) {
		$language = self::current_language();
		if ( ! $language ) {
			return $output;
		}

		$output = (string) preg_replace( '/\s*(xml:)?lang="[^"]*"/', '', $output );
		$output = (string) preg_replace( '/\s*dir="[^"]*"/', '', $output );

		$attributes = sprintf( 'dir="%s" lang="%s"', esc_attr( $language['dir'] ), esc_attr( $language['hreflang'] ) );
		if ( 'xhtml' === $doctype ) {
			$attributes .= sprintf( ' xml:lang="%s"', esc_attr( $language['hreflang'] ) );
		}

		return trim( $attributes . ' ' . trim( $output ) );
	}

	/**
	 * Adds `ako-lang-{code}` to <body> for theme authors.
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public static function body_class( $classes ) {
		$language = self::current_language();
		if ( $language ) {
			$classes[] = 'ako-lang-' . sanitize_html_class( $language['code'] );
		}
		return $classes;
	}
}
