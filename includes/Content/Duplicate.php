<?php
/**
 * "Duplicate as draft": copies a post into a new draft in another language.
 *
 * @package AkoMultilang
 */

namespace AkoMultilang\Content;

use AkoMultilang\Languages\Registry;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Creates the draft, copies content, meta, terms and featured image, then
 * links it. Every check runs before anything is written. Capability checks
 * belong to the caller.
 */
final class Duplicate {

	/**
	 * Meta never copied: editing state, history and caches that must be
	 * regenerated for the new post, and Ako's own data.
	 */
	const SKIPPED_META = array(
		'_edit_lock',
		'_edit_last',
		'_wp_old_slug',
		'_wp_old_date',
		'_wp_trash_meta_status',
		'_wp_trash_meta_time',
		'_wp_desired_post_slug',
		'_encloseme',
		'_pingme',
		'_elementor_css',
		'_elementor_element_cache',
		'_elementor_page_assets',
	);

	/**
	 * Creates a draft copy of $source_id in $code and links it.
	 *
	 * @param int    $source_id Post to copy.
	 * @param string $code      Target language code.
	 * @return int|WP_Error New post ID.
	 */
	public static function create( $source_id, $code ) {
		$source = get_post( absint( $source_id ) );
		if ( ! $source || ! in_array( $source->post_status, array( 'publish', 'future', 'draft', 'pending', 'private' ), true ) ) {
			return new WP_Error( 'akoml_invalid_post', __( 'That page doesn’t exist.', 'ako-multilang' ) );
		}
		if ( ! Taxonomies::supports( $source->post_type ) ) {
			return new WP_Error( 'akoml_unsupported_type', __( 'Ako isn’t enabled for this content type.', 'ako-multilang' ) );
		}

		$code = sanitize_key( (string) $code );
		if ( ! Registry::exists( $code ) ) {
			return new WP_Error( 'akoml_unknown_language', __( 'That language isn’t set up yet.', 'ako-multilang' ) );
		}

		$source_lang = Links::get_language( $source->ID );
		if ( ( null === $source_lang ? Registry::default_code() : $source_lang ) === $code ) {
			return new WP_Error( 'akoml_same_language', __( 'Both pages are in the same language.', 'ako-multilang' ) );
		}

		$translations = Links::get_translations( $source->ID );
		if ( isset( $translations[ $code ] ) ) {
			return new WP_Error( 'akoml_language_taken', __( 'This page already has a translation in that language. Unlink it first.', 'ako-multilang' ) );
		}

		// wp_insert_post() expects slashed data; block markup and JSON contain backslashes.
		$new_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'      => $source->post_type,
					'post_status'    => 'draft',
					'post_author'    => get_current_user_id(),
					'post_title'     => $source->post_title,
					'post_content'   => $source->post_content,
					'post_excerpt'   => $source->post_excerpt,
					'post_parent'    => self::translated_parent( $source->post_parent, $code ),
					'menu_order'     => $source->menu_order,
					'comment_status' => $source->comment_status,
					'ping_status'    => $source->ping_status,
					'post_password'  => $source->post_password,
				)
			),
			true
		);
		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		self::copy_meta( $source->ID, $new_id );
		self::copy_terms( $source, $new_id );

		$linked = Links::link_as( $source->ID, $new_id, $code );
		if ( is_wp_error( $linked ) ) {
			// Never leave an orphan copy behind.
			wp_delete_post( $new_id, true );
			return $linked;
		}

		/**
		 * Fires after a translation draft was created by duplication.
		 *
		 * @param int    $new_id    New draft ID.
		 * @param int    $source_id Source post ID.
		 * @param string $code      Language code of the draft.
		 */
		do_action( 'akoml_duplicated', $new_id, $source->ID, $code );

		return $new_id;
	}

	/**
	 * The parent's translation in $code, so page hierarchies carry over.
	 *
	 * @param int    $parent_id Source parent ID.
	 * @param string $code      Language code.
	 * @return int
	 */
	private static function translated_parent( $parent_id, $code ) {
		if ( ! $parent_id ) {
			return 0;
		}
		$translations = Links::get_translations( $parent_id );
		return isset( $translations[ $code ] ) ? (int) $translations[ $code ] : 0;
	}

	/**
	 * Copies post meta (SEO fields, builder layouts, featured image, …).
	 *
	 * @param int $from Source ID.
	 * @param int $to   Target ID.
	 */
	private static function copy_meta( $from, $to ) {
		/**
		 * Filters the meta keys that are not copied when duplicating.
		 *
		 * @param string[] $keys    Meta keys.
		 * @param int      $from    Source post ID.
		 */
		$skipped = (array) apply_filters( 'akoml_duplicate_skipped_meta_keys', self::SKIPPED_META, $from );

		foreach ( get_post_meta( $from ) as $key => $values ) {
			if ( in_array( $key, $skipped, true ) ) {
				continue;
			}
			foreach ( $values as $value ) {
				// add_post_meta() expects slashed data; maybe_unserialize() restores arrays.
				add_post_meta( $to, $key, wp_slash( maybe_unserialize( $value ) ) );
			}
		}
	}

	/**
	 * Copies taxonomy terms, except Ako's own.
	 *
	 * @param \WP_Post $source Source post.
	 * @param int      $to     Target ID.
	 */
	private static function copy_terms( $source, $to ) {
		foreach ( get_object_taxonomies( $source->post_type ) as $taxonomy ) {
			if ( in_array( $taxonomy, array( Taxonomies::LANGUAGE, Taxonomies::GROUP ), true ) ) {
				continue;
			}
			$term_ids = wp_get_object_terms( $source->ID, $taxonomy, array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $term_ids ) && $term_ids ) {
				wp_set_object_terms( $to, array_map( 'intval', $term_ids ), $taxonomy, false );
			}
		}
	}
}
