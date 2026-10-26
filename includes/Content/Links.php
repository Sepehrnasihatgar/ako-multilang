<?php
/**
 * The core API: a post's language and its translations.
 *
 * Capability checks belong to the caller (REST controllers, admin screens);
 * this class validates data and keeps the model consistent.
 *
 * @package Cedar\Multilang
 */

namespace Cedar\Multilang\Content;

use Cedar\Multilang\Languages\Registry;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Language assignment and translation groups.
 *
 * A group is one `cedar_ml_group` term. Its members are stored in term meta
 * `cedar_ml_members` as `array( language_code => post_id )` — IDs, never URLs.
 */
final class Links {

	const MEMBERS_META = 'cedar_ml_members';

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
			return new WP_Error( 'cedar_ml_unknown_language', __( 'That language isn’t set up yet.', 'cedar-multilang' ) );
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
			return new WP_Error( 'cedar_ml_same_post', __( 'A page can’t be its own translation.', 'cedar-multilang' ) );
		}
		if ( $source->post_type !== $target->post_type ) {
			return new WP_Error( 'cedar_ml_type_mismatch', __( 'Only content of the same type can be linked.', 'cedar-multilang' ) );
		}

		$source_lang = self::get_language( $source->ID );
		$target_lang = self::get_language( $target->ID );
		if ( ! $source_lang || ! $target_lang ) {
			return new WP_Error( 'cedar_ml_missing_language', __( 'Set a language on both pages first.', 'cedar-multilang' ) );
		}
		if ( $source_lang === $target_lang ) {
			return new WP_Error( 'cedar_ml_same_language', __( 'Both pages are in the same language.', 'cedar-multilang' ) );
		}

		$members = self::get_translations( $source->ID );
		if ( isset( $members[ $target_lang ] ) && $members[ $target_lang ] !== $target->ID ) {
			return new WP_Error( 'cedar_ml_language_taken', __( 'This page already has a translation in that language. Unlink it first.', 'cedar-multilang' ) );
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
		do_action( 'cedar_ml_linked', $source->ID, $target->ID, $source_group );

		return true;
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
		do_action( 'cedar_ml_unlinked', $post_id, $group_id );

		return true;
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
			return new WP_Error( 'cedar_ml_invalid_post', __( 'That page doesn’t exist.', 'cedar-multilang' ) );
		}
		if ( ! Taxonomies::supports( $post->post_type ) ) {
			return new WP_Error( 'cedar_ml_unsupported_type', __( 'Cedar isn’t enabled for this content type.', 'cedar-multilang' ) );
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
		$name    = 'cedar-group-' . wp_generate_uuid4();
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
