<?php
/**
 * SEO health checks.
 *
 * @package Cedar\Multilang
 */

namespace Cedar\Multilang\Seo;

use Cedar\Multilang\Content\Links;
use Cedar\Multilang\Content\Overview;
use Cedar\Multilang\Content\Taxonomies;
use Cedar\Multilang\Languages\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Scans translation groups and stores the result (option
 * `cedar_ml_health`, not autoloaded) so screens can show it instantly.
 *
 * Checks:
 * - one-way: a group's stored member list disagrees with the posts actually
 *   in it (e.g. after a database edit or an interrupted import), so pages
 *   point at translations that don't point back. Fix: rebuild from the source
 *   of truth (term relationships).
 * - missing: published source pages without a version in a language.
 * - canonical: linked pages whose SEO-plugin canonical points elsewhere or
 *   that are noindex — they are left out of hreflang on purpose.
 */
final class Health {

	const OPTION = 'cedar_ml_health';

	/**
	 * Per-request cache of real members, keyed by group ID.
	 *
	 * @var array<int,array<string,int>>
	 */
	private static $members = array();

	/**
	 * Runs every check and stores the result.
	 *
	 * @return array
	 */
	public static function scan() {
		$one_way  = self::one_way_groups();
		$missing  = self::missing_by_language();
		$excluded = self::excluded_pages();
		$total    = self::linked_count();

		$broken = 0;
		foreach ( $one_way as $group ) {
			$broken += count( $group['posts'] );
		}

		$result = array(
			'scannedAt' => time(),
			'linked'    => $total,
			'score'     => $total ? (int) floor( 100 * max( 0, $total - $broken ) / $total ) : null,
			'oneWay'    => array_slice( $one_way, 0, 50 ),
			'oneWayAll' => count( $one_way ),
			'missing'   => $missing,
			'excluded'  => $excluded,
		);

		/**
		 * Filters the health result (add your own checks).
		 *
		 * @param array $result Result.
		 */
		$result = (array) apply_filters( 'cedar_ml_health_checks', $result );

		update_option( self::OPTION, $result, false );
		return $result;
	}

	/**
	 * Last stored result, scanning first if there is none.
	 *
	 * @return array
	 */
	public static function latest() {
		$stored = get_option( self::OPTION, null );
		return is_array( $stored ) && isset( $stored['scannedAt'] ) ? $stored : self::scan();
	}

	/**
	 * Groups whose stored members disagree with their real members.
	 *
	 * @return array<int,array{group:int,posts:array}>
	 */
	public static function one_way_groups() {
		$out = array();
		foreach ( self::group_ids() as $group_id ) {
			$stored = get_term_meta( $group_id, Links::MEMBERS_META, true );
			$stored = is_array( $stored ) ? array_map( 'absint', $stored ) : array();
			$actual = self::actual_members( $group_id );

			ksort( $stored );
			ksort( $actual );
			if ( $stored === $actual ) {
				continue;
			}

			$posts = array();
			foreach ( array_unique( array_merge( array_values( $stored ), array_values( $actual ) ) ) as $post_id ) {
				$post = get_post( $post_id );
				if ( $post ) {
					$summary             = Overview::post_summary( $post );
					$summary['language'] = Links::get_language( $post_id );
					$posts[]             = $summary;
				}
			}
			$out[] = array(
				'group' => (int) $group_id,
				'posts' => $posts,
			);
		}
		return $out;
	}

	/**
	 * Rebuilds every inconsistent group from its term relationships.
	 *
	 * @return int Number of groups repaired.
	 */
	public static function repair_one_way() {
		self::$members = array();
		$count         = 0;
		foreach ( self::one_way_groups() as $group ) {
			$actual = self::actual_members( $group['group'] );
			if ( count( $actual ) < 2 ) {
				foreach ( $actual as $post_id ) {
					wp_remove_object_terms( $post_id, array( $group['group'] ), Taxonomies::GROUP );
				}
				wp_delete_term( $group['group'], Taxonomies::GROUP );
			} else {
				update_term_meta( $group['group'], Links::MEMBERS_META, $actual );
			}
			++$count;
		}
		return $count;
	}

	/**
	 * Members of a group according to term relationships (one per language) —
	 * the source of truth when the stored list is out of date.
	 *
	 * @param int $group_id Group term ID.
	 * @return array<string,int>
	 */
	public static function actual_members( $group_id ) {
		if ( isset( self::$members[ $group_id ] ) ) {
			return self::$members[ $group_id ];
		}
		$members  = array();
		$post_ids = get_objects_in_term( $group_id, Taxonomies::GROUP );
		if ( is_array( $post_ids ) ) {
			sort( $post_ids );
			foreach ( $post_ids as $post_id ) {
				$code = Links::get_language( (int) $post_id );
				if ( $code && ! isset( $members[ $code ] ) && get_post( $post_id ) ) {
					$members[ $code ] = (int) $post_id;
				}
			}
		}
		self::$members[ $group_id ] = $members;
		return $members;
	}

	/**
	 * For each non-default language: published source pages without it, with
	 * up to three examples.
	 *
	 * @return array<int,array{code:string,count:int,examples:array}>
	 */
	private static function missing_by_language() {
		$default = Registry::default_code();
		$counts  = array();
		$samples = array();

		foreach ( Taxonomies::post_types() as $post_type ) {
			$sources = Overview::source_ids( $post_type, array( 'post_status' => 'publish' ) );
			foreach ( $sources as $source_id ) {
				$translations = Links::get_translations( $source_id );
				foreach ( array_keys( Registry::all() ) as $code ) {
					if ( $code === $default || isset( $translations[ $code ] ) ) {
						continue;
					}
					$counts[ $code ] = isset( $counts[ $code ] ) ? $counts[ $code ] + 1 : 1;
					if ( ! isset( $samples[ $code ] ) || count( $samples[ $code ] ) < 3 ) {
						$samples[ $code ][] = Overview::post_summary( get_post( $source_id ) );
					}
				}
			}
		}

		$out = array();
		foreach ( $counts as $code => $count ) {
			$out[] = array(
				'code'     => $code,
				'count'    => $count,
				'examples' => $samples[ $code ],
			);
		}
		return $out;
	}

	/**
	 * Linked, published pages left out of hreflang (noindex or canonical
	 * elsewhere), with up to five examples.
	 *
	 * @return array{count:int,examples:array}
	 */
	private static function excluded_pages() {
		$count    = 0;
		$examples = array();
		foreach ( self::group_ids() as $group_id ) {
			foreach ( self::actual_members( $group_id ) as $post_id ) {
				if ( 'publish' !== get_post_status( $post_id ) || Hreflang::is_indexable( $post_id ) ) {
					continue;
				}
				++$count;
				if ( count( $examples ) < 5 ) {
					$examples[] = Overview::post_summary( get_post( $post_id ) );
				}
			}
		}
		return array(
			'count'    => $count,
			'examples' => $examples,
		);
	}

	/**
	 * Number of posts that are in a translation group.
	 *
	 * @return int
	 */
	private static function linked_count() {
		$count = 0;
		foreach ( self::group_ids() as $group_id ) {
			$count += count( self::actual_members( $group_id ) );
		}
		return $count;
	}

	/**
	 * Every group term ID (meta primed).
	 *
	 * @return int[]
	 */
	private static function group_ids() {
		$ids = get_terms(
			array(
				'taxonomy'   => Taxonomies::GROUP,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		if ( is_wp_error( $ids ) || ! $ids ) {
			return array();
		}
		$ids = array_map( 'intval', $ids );
		update_meta_cache( 'term', $ids );
		return $ids;
	}

	/**
	 * Group IDs with problems, from the last scan (for the Translations
	 * "Needs review" filter — cheap enough to run on every list request).
	 *
	 * @return int[]
	 */
	public static function review_group_ids() {
		$latest = self::latest();
		return array_map( 'intval', wp_list_pluck( $latest['oneWay'], 'group' ) );
	}
}
