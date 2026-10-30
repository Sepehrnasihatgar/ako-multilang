<?php
/**
 * Data for the Translations screen: one row per source page, one column per
 * other language.
 *
 * @package AkoMultilang
 */

namespace AkoMultilang\Content;

use AkoMultilang\Languages\Registry;
use AkoMultilang\Rest\Rest;
use AkoMultilang\Seo\Health;
use AkoMultilang\Seo\Hreflang;
use WP_Post;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the paginated overview. Content without a language belongs to the
 * default language (the "source"). Only posts the current user can edit are
 * listed (WP_Query `perm => editable`).
 */
final class Overview {

	const FILTERS = array( 'all', 'missing', 'review', 'drafts' );

	/**
	 * IDs per query chunk, keeps IN() clauses small.
	 */
	const CHUNK = 500;

	/**
	 * Runs the overview query.
	 *
	 * @param string $post_type Enabled post type.
	 * @param string $filter    One of self::FILTERS.
	 * @param string $search    Title (or exact slug) search.
	 * @param int    $page      1-based page.
	 * @param int    $per_page  Rows per page.
	 * @return array
	 */
	public static function query( $post_type, $filter, $search, $page, $per_page ) {
		$default = Registry::default_code();
		$targets = array_values( array_diff( array_keys( Registry::all() ), array( $default ) ) );

		$source_ids = self::source_ids( $post_type );
		$index      = self::index( $source_ids, $targets );

		$counts   = array(
			'all'     => count( $source_ids ),
			'missing' => 0,
			'review'  => 0,
			'drafts'  => 0,
		);
		$coverage = array_fill_keys( $targets, 0 );

		foreach ( $index as $entry ) {
			if ( $entry['missing'] ) {
				++$counts['missing'];
			}
			if ( $entry['draft'] ) {
				++$counts['drafts'];
			}
			if ( $entry['review'] ) {
				++$counts['review'];
			}
			foreach ( $entry['translations'] as $code => $unused ) {
				++$coverage[ $code ];
			}
		}

		$ids = $source_ids;
		if ( '' !== $search ) {
			$ids = array_values( array_intersect( $ids, self::search_ids( $post_type, $search ) ) );
		}
		if ( 'all' !== $filter ) {
			$keys = array(
				'missing' => 'missing',
				'review'  => 'review',
				'drafts'  => 'draft',
			);
			$key  = $keys[ $filter ];
			$ids  = array_values(
				array_filter(
					$ids,
					static function ( $id ) use ( $index, $key ) {
						return $index[ $id ][ $key ];
					}
				)
			);
		}

		$total    = count( $ids );
		$pages    = max( 1, (int) ceil( $total / $per_page ) );
		$page     = min( max( 1, $page ), $pages );
		$page_ids = array_slice( $ids, ( $page - 1 ) * $per_page, $per_page );

		return array(
			'rows'          => self::rows( $page_ids, $index ),
			'total'         => $total,
			'page'          => $page,
			'total_pages'   => $pages,
			'counts'        => $counts,
			'coverage'      => $coverage,
			'groups_exist'  => self::groups_exist(),
			'source_total'  => count( $source_ids ),
			'default'       => $default,
			'target_codes'  => $targets,
			'languages'     => self::languages(),
			'filter'        => $filter,
			'search'        => $search,
			'post_type'     => $post_type,
			'post_type_tag' => self::type_label( $post_type ),
			'health'        => self::health_summary(),
		);
	}

	/**
	 * Source-language post IDs of a type, most recently updated first.
	 *
	 * @param string $post_type Post type.
	 * @param array  $extra     Extra WP_Query args.
	 * @return int[]
	 */
	public static function source_ids( $post_type, array $extra = array() ) {
		$default = Registry::default_code();

		$query = new WP_Query(
			array_merge(
				array(
					'post_type'              => $post_type,
					'post_status'            => Rest::CONTENT_STATUSES,
					'perm'                   => 'editable',
					'fields'                 => 'ids',
					'posts_per_page'         => -1,
					'orderby'                => 'modified',
					'order'                  => 'DESC',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Admin-only; indexed taxonomy lookup.
					'tax_query'              => array(
						'relation' => 'OR',
						array(
							'taxonomy' => Taxonomies::LANGUAGE,
							'field'    => 'slug',
							'terms'    => array( $default ),
						),
						array(
							'taxonomy' => Taxonomies::LANGUAGE,
							'operator' => 'NOT EXISTS',
						),
					),
				),
				$extra
			)
		);

		return array_map( 'intval', $query->posts );
	}

	/**
	 * IDs matching a title search, plus an exact slug match.
	 *
	 * @param string $post_type Post type.
	 * @param string $search    Search text.
	 * @return int[]
	 */
	private static function search_ids( $post_type, $search ) {
		$by_title = self::source_ids(
			$post_type,
			array(
				's'              => $search,
				'search_columns' => array( 'post_title' ),
			)
		);

		$slug    = sanitize_title( $search );
		$by_slug = '' === $slug ? array() : self::source_ids( $post_type, array( 'post_name__in' => array( $slug ) ) );

		return array_values( array_unique( array_merge( $by_title, $by_slug ) ) );
	}

	/**
	 * For each source ID: its live translations and whether it is missing
	 * any or has unpublished parts.
	 *
	 * @param int[]    $source_ids Source IDs.
	 * @param string[] $targets    Non-default language codes.
	 * @return array<int,array{translations:array<string,int>,missing:bool,draft:bool,review:bool}>
	 */
	private static function index( array $source_ids, array $targets ) {
		$group_of = array();
		foreach ( array_chunk( $source_ids, self::CHUNK ) as $chunk ) {
			$terms = wp_get_object_terms(
				$chunk,
				Taxonomies::GROUP,
				array(
					'fields'                 => 'all_with_object_id',
					'update_term_meta_cache' => false,
				)
			);
			if ( is_wp_error( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$group_of[ (int) $term->object_id ] = (int) $term->term_id;
			}
		}

		$members = Links::members_of_groups( array_values( $group_of ) );
		$review  = array_flip( Health::review_group_ids() );

		// Groups that need review show their real pages, so nobody adds a
		// duplicate translation because the stored list is out of date.
		foreach ( array_keys( $review ) as $group_id ) {
			if ( isset( $members[ $group_id ] ) ) {
				$members[ $group_id ] = Health::actual_members( $group_id );
			}
		}

		// Every translation ID referenced by these groups.
		$translation_ids = array();
		foreach ( $members as $map ) {
			foreach ( $targets as $code ) {
				if ( isset( $map[ $code ] ) ) {
					$translation_ids[] = $map[ $code ];
				}
			}
		}

		$alive  = self::ids_with_status( $translation_ids, Rest::CONTENT_STATUSES );
		$drafts = array_merge(
			self::ids_with_status( $translation_ids, Rest::DRAFT_STATUSES ),
			self::ids_with_status( $source_ids, Rest::DRAFT_STATUSES )
		);
		$alive  = array_flip( $alive );
		$drafts = array_flip( $drafts );

		$index = array();
		foreach ( $source_ids as $id ) {
			$translations = array();
			$draft        = isset( $drafts[ $id ] );

			$in_review = isset( $group_of[ $id ], $review[ $group_of[ $id ] ] );

			if ( isset( $group_of[ $id ], $members[ $group_of[ $id ] ] ) ) {
				foreach ( $targets as $code ) {
					$member = isset( $members[ $group_of[ $id ] ][ $code ] ) ? $members[ $group_of[ $id ] ][ $code ] : 0;
					if ( $member && isset( $alive[ $member ] ) ) {
						$translations[ $code ] = $member;
						$draft                 = $draft || isset( $drafts[ $member ] );
					}
				}
			}

			$index[ $id ] = array(
				'translations' => $translations,
				'missing'      => count( $translations ) < count( $targets ),
				'draft'        => $draft,
				'review'       => $in_review,
			);
		}

		return $index;
	}

	/**
	 * Subset of IDs whose status is in $statuses (enabled post types).
	 *
	 * @param int[]    $ids      Post IDs.
	 * @param string[] $statuses Statuses.
	 * @return int[]
	 */
	private static function ids_with_status( array $ids, array $statuses ) {
		$out = array();
		foreach ( array_chunk( array_values( array_unique( $ids ) ), self::CHUNK ) as $chunk ) {
			$query = new WP_Query(
				array(
					'post_type'              => Taxonomies::post_types(),
					'post__in'               => $chunk,
					'post_status'            => $statuses,
					'fields'                 => 'ids',
					'posts_per_page'         => count( $chunk ),
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
			$out   = array_merge( $out, array_map( 'intval', $query->posts ) );
		}
		return $out;
	}

	/**
	 * Row data for one page of results.
	 *
	 * @param int[] $page_ids Source IDs on this page.
	 * @param array $index    Index from self::index().
	 * @return array
	 */
	private static function rows( array $page_ids, array $index ) {
		$all_ids = $page_ids;
		foreach ( $page_ids as $id ) {
			$all_ids = array_merge( $all_ids, array_values( $index[ $id ]['translations'] ) );
		}
		if ( $all_ids ) {
			_prime_post_caches( $all_ids, false, false );
		}

		$rows = array();
		foreach ( $page_ids as $id ) {
			$post = get_post( $id );
			if ( ! $post ) {
				continue;
			}

			$row                 = self::post_summary( $post );
			$row['modified']     = self::relative_date( $post );
			$row['review']       = $index[ $id ]['review'];
			$row['translations'] = array();
			foreach ( $index[ $id ]['translations'] as $code => $translation_id ) {
				$translation = get_post( $translation_id );
				if ( $translation ) {
					$row['translations'][ $code ] = self::post_summary( $translation );
				}
			}
			$rows[] = $row;
		}
		return $rows;
	}

	/**
	 * Everything the editor panel needs about one post.
	 *
	 * @param WP_Post $post Post.
	 * @return array
	 */
	public static function detail( WP_Post $post ) {
		$translations = array();
		foreach ( Links::get_translations( $post->ID ) as $code => $member_id ) {
			$member = get_post( $member_id );
			if ( $member && $member->ID !== $post->ID ) {
				$translations[ $code ] = self::post_summary( $member );
			}
		}

		return array(
			'id'           => (int) $post->ID,
			'postType'     => $post->post_type,
			'status'       => $post->post_status,
			'language'     => Links::get_language( $post->ID ),
			'default'      => Registry::default_code(),
			'languages'    => self::languages(),
			'translations' => $translations,
			'hreflang'     => array_keys( Hreflang::cluster( $post->ID ) ),
		);
	}

	/**
	 * The fields the admin app needs about one post.
	 *
	 * @param WP_Post $post Post.
	 * @return array
	 */
	public static function post_summary( WP_Post $post ) {
		$title = trim( wp_strip_all_tags( $post->post_title ) );

		return array(
			'id'       => (int) $post->ID,
			'title'    => '' === $title ? __( '(no title)', 'ako-multilang' ) : $title,
			'path'     => self::path( $post ),
			'status'   => $post->post_status,
			'canEdit'  => current_user_can( 'edit_post', $post->ID ),
			'editUrl'  => (string) get_edit_post_link( $post->ID, 'raw' ),
			'viewUrl'  => 'publish' === $post->post_status ? (string) get_permalink( $post ) : '',
			'language' => Links::get_language( $post->ID ),
		);
	}

	/**
	 * Site-relative path, also for drafts (built from the slug).
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	private static function path( WP_Post $post ) {
		if ( 'publish' === $post->post_status ) {
			$path = (string) wp_make_link_relative( get_permalink( $post ) );
			return '/' === $path ? $path : untrailingslashit( $path );
		}
		if ( '' === $post->post_name ) {
			return '';
		}
		$uri = is_post_type_hierarchical( $post->post_type ) ? get_page_uri( $post ) : $post->post_name;
		return '/' . trim( (string) $uri, '/' );
	}

	/**
	 * "2 hours ago", "Yesterday", "3 days ago", then a short date.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	private static function relative_date( WP_Post $post ) {
		$then = (int) get_post_timestamp( $post, 'modified' );
		$diff = time() - $then;

		if ( $diff < DAY_IN_SECONDS ) {
			/* translators: %s: Human-readable time difference, e.g. "2 hours". */
			return sprintf( __( '%s ago', 'ako-multilang' ), human_time_diff( $then ) );
		}
		if ( $diff < 2 * DAY_IN_SECONDS ) {
			return __( 'Yesterday', 'ako-multilang' );
		}
		if ( $diff < WEEK_IN_SECONDS ) {
			$days = (int) floor( $diff / DAY_IN_SECONDS );
			/* translators: %d: Number of days. */
			return sprintf( _n( '%d day ago', '%d days ago', $days, 'ako-multilang' ), $days );
		}
		return (string) wp_date( _x( 'M j', 'short date format', 'ako-multilang' ), $then );
	}

	/**
	 * The SEO health card: score and one-way count from the last scan.
	 *
	 * @return array{score:int|null,oneWay:int,scannedAt:int}|null
	 */
	private static function health_summary() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return null;
		}
		$health = Health::latest();
		return array(
			'score'     => $health['score'],
			'oneWay'    => (int) $health['oneWayAll'],
			'scannedAt' => (int) $health['scannedAt'],
		);
	}

	/**
	 * Public fields of every language, for column headers.
	 *
	 * @return array<int,array{code:string,name:string,native:string,dir:string}>
	 */
	public static function languages() {
		$out = array();
		foreach ( Registry::all() as $language ) {
			$out[] = array(
				'code'   => $language['code'],
				'name'   => $language['name'],
				'native' => $language['native'],
				'dir'    => $language['dir'],
			);
		}
		return $out;
	}

	/**
	 * Whether any translation group exists (for the first-run state).
	 *
	 * @return bool
	 */
	private static function groups_exist() {
		$ids = get_terms(
			array(
				'taxonomy'   => Taxonomies::GROUP,
				'hide_empty' => false,
				'fields'     => 'ids',
				'number'     => 1,
			)
		);
		return ! is_wp_error( $ids ) && ! empty( $ids );
	}

	/**
	 * Singular label of a post type ("Page").
	 *
	 * @param string $post_type Post type.
	 * @return string
	 */
	private static function type_label( $post_type ) {
		$object = get_post_type_object( $post_type );
		return $object ? $object->labels->singular_name : $post_type;
	}

	/**
	 * Post types for the content-type menu: enabled ones with their source
	 * count, and other public types marked as off.
	 *
	 * @return array<int,array{name:string,label:string,count:int,enabled:bool}>
	 */
	public static function types() {
		$enabled = Taxonomies::post_types();
		$out     = array();

		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $object ) {
			if ( 'attachment' === $object->name ) {
				continue;
			}
			$is_enabled = in_array( $object->name, $enabled, true );
			if ( $is_enabled && ! current_user_can( $object->cap->edit_posts ) ) {
				continue;
			}

			$count = 0;
			if ( $is_enabled ) {
				$query = new WP_Query(
					array(
						'post_type'              => $object->name,
						'post_status'            => Rest::CONTENT_STATUSES,
						'perm'                   => 'editable',
						'fields'                 => 'ids',
						'posts_per_page'         => 1,
						'update_post_meta_cache' => false,
						'update_post_term_cache' => false,
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Admin-only; indexed taxonomy lookup.
						'tax_query'              => array(
							'relation' => 'OR',
							array(
								'taxonomy' => Taxonomies::LANGUAGE,
								'field'    => 'slug',
								'terms'    => array( Registry::default_code() ),
							),
							array(
								'taxonomy' => Taxonomies::LANGUAGE,
								'operator' => 'NOT EXISTS',
							),
						),
					)
				);
				$count = (int) $query->found_posts;
			}

			$out[] = array(
				'name'    => $object->name,
				'label'   => $object->labels->name,
				'count'   => $count,
				'enabled' => $is_enabled,
			);
		}

		// Enabled types first, in their configured order.
		usort(
			$out,
			static function ( $a, $b ) use ( $enabled ) {
				$pa = array_search( $a['name'], $enabled, true );
				$pb = array_search( $b['name'], $enabled, true );
				$pa = false === $pa ? PHP_INT_MAX : $pa;
				$pb = false === $pb ? PHP_INT_MAX : $pb;
				return $pa <=> $pb;
			}
		);

		return $out;
	}
}
