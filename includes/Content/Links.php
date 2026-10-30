<?php
/**
 * The core API: a post's language and its translations.
 *
 * Capability checks belong to the caller (REST controllers, admin screens);
 * this class validates data and keeps the model consistent.
 *
 * @package AkoMultilang
 */

namespace AkoMultilang\Content;

use AkoMultilang\Languages\Registry;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Language assignment and translation groups.
 *
 * A group is one `akoml_group` term. Its members are stored in term meta
 * `akoml_members` as `array( language_code => post_id )` — IDs, never URLs.
 */
final class Links {

	const MEMBERS_META = 'akoml_members';

	/**
	 * Language code of a post, or null.
	 *
	 * @param int $post_id Post ID.
	 * @return string|null
	 */
	public static function get_language( $post_id ) {
		$terms = get_the_terms( absint( $post_id ), Taxonomies::LANGUAGE );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return null;
		}
		$code = $terms[0]->slug;
		return Registry::exists( $code ) ? $code : null;
	}

	/**
	 * Sets (or clears, with '') a post's language.
	 *
	 * If the post is in a group and another member already uses the new
	 * language, the post is first removed from that group.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $code    Language code, or '' to clear.
	 * @return true|WP_Error
	 */
	public static function set_language( $post_id, $code ) {
		$post = self::valid_post( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$code = sanitize_key( (string) $code );

		if ( '' === $code ) {
			self::unlink( $post->ID );
			wp_delete_object_term_relationships( $post->ID, Taxonomies::LANGUAGE );
			return true;
		}

		if ( ! Registry::exists( $code ) ) {
			return new WP_Error( 'akoml_unknown_language', __( 'That language isn’t set up yet.', 'ako-multilang' ) );
		}

		$members = self::get_translations( $post->ID );
		if ( isset( $members[ $code ] ) && (int) $members[ $code ] !== $post->ID ) {
			self::unlink( $post->ID );
		}

		$term = self::ensure_language_term( $code );
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		$result = wp_set_object_terms( $post->ID, array( (int) $term ), Taxonomies::LANGUAGE, false );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$group_id = self::get_group_id( $post->ID );
		if ( $group_id ) {
			self::write_members( $group_id, self::rebuild_members( $group_id ) );
		}

		return true;
	}

	/**
	 * Translations of a post: `array( code => post_id )`, including the post itself.
	 * Only existing posts with a registered language are returned.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,int>
	 */
	public static function get_translations( $post_id ) {
		$post_id  = absint( $post_id );
		$group_id = self::get_group_id( $post_id );

		if ( ! $group_id ) {
			$code = self::get_language( $post_id );
			return $code ? array( $code => $post_id ) : array();
		}

		$members = self::read_members( $group_id );
		$out     = array();

		foreach ( Registry::all() as $code => $language ) {
			if ( isset( $members[ $code ] ) && get_post( $members[ $code ] ) ) {
				$out[ $code ] = (int) $members[ $code ];
			}
		}

		return $out;
	}

	/**
	 * Links $target to $source's group (creating one if needed).
	 *
	 * Both posts must exist, share a post type, have different languages,
	 * and $target's language must not already be taken in the group.
	 *
	 * @param int $source_id Post already in (or starting) the group.
	 * @param int $target_id Post to add.
	 * @return true|WP_Error
	 */
	public static function link( $source_id, $target_id ) {
		$source = self::valid_post( $source_id );
		$target = self::valid_post( $target_id );
		if ( is_wp_error( $source ) ) {
			return $source;
		}
		if ( is_wp_error( $target ) ) {
			return $target;
		}
		if ( $source->ID === $target->ID ) {
			return new WP_Error( 'akoml_same_post', __( 'A page can’t be its own translation.', 'ako-multilang' ) );
		}
		if ( $source->post_type !== $target->post_type ) {
			return new WP_Error( 'akoml_type_mismatch', __( 'Only content of the same type can be linked.', 'ako-multilang' ) );
		}

		$source_lang = self::get_language( $source->ID );
		$target_lang = self::get_language( $target->ID );
		if ( ! $source_lang || ! $target_lang ) {
			return new WP_Error( 'akoml_missing_language', __( 'Set a language on both pages first.', 'ako-multilang' ) );
		}
		if ( $source_lang === $target_lang ) {
			return new WP_Error( 'akoml_same_language', __( 'Both pages are in the same language.', 'ako-multilang' ) );
		}

		$members = self::get_translations( $source->ID );
		if ( isset( $members[ $target_lang ] ) && $members[ $target_lang ] !== $target->ID ) {
			return new WP_Error( 'akoml_language_taken', __( 'This page already has a translation in that language. Unlink it first.', 'ako-multilang' ) );
		}

		// A post belongs to one group only: detach the target from any other group.
		$target_group = self::get_group_id( $target->ID );
		$source_group = self::get_group_id( $source->ID );
		if ( $target_group && $target_group !== $source_group ) {
			self::unlink( $target->ID );
		}

		if ( ! $source_group ) {
			$source_group = self::create_group();
			if ( is_wp_error( $source_group ) ) {
				return $source_group;
			}
			wp_set_object_terms( $source->ID, array( $source_group ), Taxonomies::GROUP, false );
		}

		$result = wp_set_object_terms( $target->ID, array( $source_group ), Taxonomies::GROUP, false );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		self::write_members( $source_group, self::rebuild_members( $source_group ) );

		/**
		 * Fires after two posts were linked.
		 *
		 * @param int $source_id Source post ID.
		 * @param int $target_id Target post ID.
		 * @param int $group_id  Group term ID.
		 */
		do_action( 'akoml_linked', $source->ID, $target->ID, $source_group );

		return true;
	}

	/**
	 * Makes $target_id the $code translation of $source_id, assigning
	 * languages where missing: the source gets the default language, the
	 * target gets $code. Everything is validated before anything is written.
	 *
	 * @param int    $source_id Source post ID.
	 * @param int    $target_id Target post ID.
	 * @param string $code      Language the target is a translation into.
	 * @return true|WP_Error
	 */
	public static function link_as( $source_id, $target_id, $code ) {
		$source = self::valid_post( $source_id );
		$target = self::valid_post( $target_id );
		if ( is_wp_error( $source ) ) {
			return $source;
		}
		if ( is_wp_error( $target ) ) {
			return $target;
		}
		if ( $source->ID === $target->ID ) {
			return new WP_Error( 'akoml_same_post', __( 'A page can’t be its own translation.', 'ako-multilang' ) );
		}
		if ( $source->post_type !== $target->post_type ) {
			return new WP_Error( 'akoml_type_mismatch', __( 'Only content of the same type can be linked.', 'ako-multilang' ) );
		}

		$code = sanitize_key( (string) $code );
		if ( ! Registry::exists( $code ) ) {
			return new WP_Error( 'akoml_unknown_language', __( 'That language isn’t set up yet.', 'ako-multilang' ) );
		}

		$source_lang = self::get_language( $source->ID );
		$target_lang = self::get_language( $target->ID );

		if ( null !== $target_lang && $target_lang !== $code ) {
			$found = Registry::get( $target_lang );
			return new WP_Error(
				'akoml_wrong_language',
				/* translators: %s: Language name, e.g. "Arabic". */
				sprintf( __( 'That page is in %s. Pick a page in this language.', 'ako-multilang' ), $found ? $found['name'] : $target_lang )
			);
		}
		if ( ( null === $source_lang ? Registry::default_code() : $source_lang ) === $code ) {
			return new WP_Error( 'akoml_same_language', __( 'Both pages are in the same language.', 'ako-multilang' ) );
		}

		$members = self::get_translations( $source->ID );
		if ( isset( $members[ $code ] ) && $members[ $code ] !== $target->ID ) {
			return new WP_Error( 'akoml_language_taken', __( 'This page already has a translation in that language. Unlink it first.', 'ako-multilang' ) );
		}

		if ( null === $source_lang ) {
			$set = self::set_language( $source->ID, Registry::default_code() );
			if ( is_wp_error( $set ) ) {
				return $set;
			}
		}
		if ( null === $target_lang ) {
			$set = self::set_language( $target->ID, $code );
			if ( is_wp_error( $set ) ) {
				return $set;
			}
		}

		return self::link( $source->ID, $target->ID );
	}

	/**
	 * Removes a post from its group. The rest of the group stays linked;
	 * a group left with fewer than two members is deleted.
	 *
	 * @param int $post_id Post ID.
	 * @return true
	 */
	public static function unlink( $post_id ) {
		$post_id  = absint( $post_id );
		$group_id = self::get_group_id( $post_id );
		if ( ! $group_id ) {
			return true;
		}

		wp_remove_object_terms( $post_id, array( $group_id ), Taxonomies::GROUP );

		$members = self::rebuild_members( $group_id );
		if ( count( $members ) < 2 ) {
			foreach ( $members as $remaining ) {
				wp_remove_object_terms( $remaining, array( $group_id ), Taxonomies::GROUP );
			}
			wp_delete_term( $group_id, Taxonomies::GROUP );
		} else {
			self::write_members( $group_id, $members );
		}

		/**
		 * Fires after a post left its translation group.
		 *
		 * @param int $post_id  Post ID.
		 * @param int $group_id Former group term ID.
		 */
		do_action( 'akoml_unlinked', $post_id, $group_id );

		return true;
	}

	/**
	 * Dissolves a post's whole group: every member is unlinked from every other.
	 * Posts and their languages are kept.
	 *
	 * @param int $post_id Any member of the group.
	 * @return true
	 */
	public static function dissolve( $post_id ) {
		$group_id = self::get_group_id( $post_id );
		if ( ! $group_id ) {
			return true;
		}

		foreach ( self::rebuild_members( $group_id ) as $member_id ) {
			self::unlink( $member_id );
		}
		return true;
	}

	/**
	 * Unlinks every post in a language from its group, e.g. before the
	 * language is removed. The posts keep their language term, so they stay
	 * out of other languages' lists and come back if the language is re-added.
	 *
	 * @param string $code Language code.
	 * @return int Number of posts unlinked.
	 */
	public static function detach_language( $code ) {
		$code  = sanitize_key( (string) $code );
		$count = 0;

		$group_ids = get_terms(
			array(
				'taxonomy'   => Taxonomies::GROUP,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		if ( is_wp_error( $group_ids ) ) {
			return 0;
		}

		foreach ( $group_ids as $group_id ) {
			$members = self::read_members( (int) $group_id );
			if ( isset( $members[ $code ] ) ) {
				self::unlink( $members[ $code ] );
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Members of many groups at once, primed with one meta query.
	 *
	 * @param int[] $group_ids Group term IDs.
	 * @return array<int,array<string,int>> Group ID => members.
	 */
	public static function members_of_groups( array $group_ids ) {
		$group_ids = array_values( array_unique( array_filter( array_map( 'absint', $group_ids ) ) ) );
		if ( ! $group_ids ) {
			return array();
		}

		update_meta_cache( 'term', $group_ids );

		$out = array();
		foreach ( $group_ids as $group_id ) {
			$out[ $group_id ] = self::read_members( $group_id );
		}
		return $out;
	}

	/**
	 * Group term ID of a post, or 0.
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	public static function get_group_id( $post_id ) {
		$terms = get_the_terms( absint( $post_id ), Taxonomies::GROUP );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return 0;
		}
		return (int) $terms[0]->term_id;
	}

	/**
	 * Validates a post ID against supported post types.
	 *
	 * @param int $post_id Post ID.
	 * @return \WP_Post|WP_Error
	 */
	private static function valid_post( $post_id ) {
		$post = get_post( absint( $post_id ) );
		if ( ! $post ) {
			return new WP_Error( 'akoml_invalid_post', __( 'That page doesn’t exist.', 'ako-multilang' ) );
		}
		if ( ! Taxonomies::supports( $post->post_type ) ) {
			return new WP_Error( 'akoml_unsupported_type', __( 'Ako isn’t enabled for this content type.', 'ako-multilang' ) );
		}
		return $post;
	}

	/**
	 * Term ID for a language code, created on first use.
	 *
	 * @param string $code Language code.
	 * @return int|WP_Error
	 */
	private static function ensure_language_term( $code ) {
		$term = get_term_by( 'slug', $code, Taxonomies::LANGUAGE );
		if ( $term ) {
			return (int) $term->term_id;
		}
		$created = wp_insert_term( $code, Taxonomies::LANGUAGE, array( 'slug' => $code ) );
		return is_wp_error( $created ) ? $created : (int) $created['term_id'];
	}

	/**
	 * Creates an empty group term.
	 *
	 * @return int|WP_Error
	 */
	private static function create_group() {
		$name    = 'ako-group-' . wp_generate_uuid4();
		$created = wp_insert_term( $name, Taxonomies::GROUP, array( 'slug' => $name ) );
		return is_wp_error( $created ) ? $created : (int) $created['term_id'];
	}

	/**
	 * Members of a group from term meta.
	 *
	 * @param int $group_id Group term ID.
	 * @return array<string,int>
	 */
	private static function read_members( $group_id ) {
		$raw = get_term_meta( $group_id, self::MEMBERS_META, true );
		$out = array();
		if ( is_array( $raw ) ) {
			foreach ( $raw as $code => $post_id ) {
				$code = sanitize_key( (string) $code );
				if ( '' !== $code && absint( $post_id ) ) {
					$out[ $code ] = absint( $post_id );
				}
			}
		}
		return $out;
	}

	/**
	 * Rebuilds a group's member map from its term relationships (source of truth).
	 *
	 * @param int $group_id Group term ID.
	 * @return array<string,int>
	 */
	private static function rebuild_members( $group_id ) {
		$post_ids = get_objects_in_term( $group_id, Taxonomies::GROUP );
		$members  = array();

		if ( is_array( $post_ids ) ) {
			foreach ( $post_ids as $post_id ) {
				$code = self::get_language( (int) $post_id );
				if ( $code && ! isset( $members[ $code ] ) ) {
					$members[ $code ] = (int) $post_id;
				}
			}
		}

		return $members;
	}

	/**
	 * Writes a group's member map.
	 *
	 * @param int               $group_id Group term ID.
	 * @param array<string,int> $members  Members.
	 */
	private static function write_members( $group_id, array $members ) {
		update_term_meta( $group_id, self::MEMBERS_META, $members );
	}
}
