<?php
/**
 * Hidden taxonomies that store a post's language and its translation group.
 *
 * @package AkoMultilang
 */

namespace AkoMultilang\Content;

use AkoMultilang\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Registers `akoml_language` and `akoml_group`. Neither has a UI,
 * REST route, query var or rewrite rule — they never create URLs.
 */
final class Taxonomies {

	const LANGUAGE = 'akoml_language';
	const GROUP    = 'akoml_group';

	/**
	 * Registers both taxonomies for every enabled post type.
	 */
	public static function register() {
		$args = array(
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => false,
			'show_in_menu'       => false,
			'show_in_nav_menus'  => false,
			'show_in_rest'       => false,
			'show_tagcloud'      => false,
			'show_admin_column'  => false,
			'hierarchical'       => false,
			'query_var'          => false,
			'rewrite'            => false,
		);

		$post_types = self::post_types();

		register_taxonomy( self::LANGUAGE, $post_types, $args );
		register_taxonomy( self::GROUP, $post_types, $args );
	}

	/**
	 * Enabled post types that actually exist.
	 *
	 * @return string[]
	 */
	public static function post_types() {
		$enabled = Settings::get()['post_types'];

		/**
		 * Filters the post types Ako links across languages.
		 *
		 * @param string[] $post_types Post type slugs.
		 */
		$enabled = (array) apply_filters( 'akoml_post_types', $enabled );

		return array_values( array_filter( array_map( 'sanitize_key', $enabled ), 'post_type_exists' ) );
	}

	/**
	 * Whether a post type is handled by Ako.
	 *
	 * @param string $post_type Post type slug.
	 * @return bool
	 */
	public static function supports( $post_type ) {
		return in_array( $post_type, self::post_types(), true );
	}
}
