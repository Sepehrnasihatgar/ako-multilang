<?php
/**
 * Suggests page pairs that look like translations of each other.
 *
 * @package AkoMultilang
 */

namespace AkoMultilang\Setup;

use AkoMultilang\Content\Links;
use AkoMultilang\Content\Overview;
use AkoMultilang\Content\Taxonomies;
use AkoMultilang\Languages\Registry;
use AkoMultilang\Rest\Rest;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Finds unlinked pages that belong to another language — by their language,
 * a language folder ("/fa/about") or a language suffix ("about-fa") — and
 * pairs each with the default-language page whose slug or title matches.
 * Nothing is written; the site owner reviews every pair.
 */
final class Matcher {

	/**
	 * Posts read per content type.
	 */
	const MAX_POSTS = 2000;

	/**
	 * Fuzzy comparisons per content type and language.
	 */
	const MAX_FUZZY = 250000;

	/**
	 * Suggestions returned.
	 */
	const MAX_SUGGESTIONS = 50;

	/**
	 * Suggested pairs, best first.
	 *
	 * @return array{pairs:array,total:int}
	 */
	public static function suggest() {
		$default_code = Registry::default_code();
		$markers      = self::markers( $default_code );
		if ( '' === $default_code || ! $markers ) {
			return array(
				'pairs' => array(),
				'total' => 0,
			);
		}

		$pairs = array();
		foreach ( Taxonomies::post_types() as $post_type ) {
			$pairs = array_merge( $pairs, self::suggest_for_type( $post_type, $default_code, $markers ) );
		}

		usort(
			$pairs,
			function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			}
		);

		$out = array();
		foreach ( array_slice( $pairs, 0, self::MAX_SUGGESTIONS ) as $pair ) {
			$out[] = array(
				'source'     => Overview::post_summary( get_post( $pair['source'] ) ),
				'target'     => Overview::post_summary( get_post( $pair['target'] ) ),
				'language'   => $pair['language'],
				'confidence' => $pair['score'] >= 95 ? 'high' : ( $pair['score'] >= 80 ? 'medium' : 'low' ),
			);
		}

		return array(
			'pairs' => $out,
			'total' => count( $pairs ),
		);
	}

	/**
	 * Path markers of each non-default language: "fa", "fa-ir" => "fa".
	 *
	 * @param string $default_code Default language code.
	 * @return array<string,string>
	 */
	private static function markers( $default_code ) {
		$markers = array();
		foreach ( Registry::all() as $code => $language ) {
			if ( $code === $default_code ) {
				continue;
			}
			$markers[ $code ]                               = $code;
			$markers[ strtolower( $language['hreflang'] ) ] = $code;
			$markers[ str_replace( '_', '-', strtolower( $language['locale'] ) ) ] = $code;
		}
		return $markers;
	}

	/**
	 * Pairs for one content type.
	 *
	 * @param string               $post_type Post type.
	 * @param string               $default_code   Default language code.
	 * @param array<string,string> $markers   Path markers.
	 * @return array<int,array{source:int,target:int,language:string,score:float}>
	 */
	private static function suggest_for_type( $post_type, $default_code, array $markers ) {
		$query = new WP_Query(
			array(
				'post_type'              => $post_type,
				'post_status'            => Rest::CONTENT_STATUSES,
				'perm'                   => 'editable',
				'posts_per_page'         => self::MAX_POSTS,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
			)
		);

		$sources = array();
		$targets = array();
		foreach ( $query->posts as $post ) {
			if ( Links::get_group_id( $post->ID ) ) {
				continue;
			}
			$entry = self::classify( $post, $default_code, $markers );
			if ( null === $entry ) {
				continue;
			}
			if ( $entry['language'] === $default_code ) {
				$sources[] = $entry;
			} else {
				$targets[ $entry['language'] ][] = $entry;
			}
		}

		$pairs = array();
		foreach ( $targets as $code => $language_targets ) {
			$pairs = array_merge( $pairs, self::match( $sources, $language_targets, $code ) );
		}
		return $pairs;
	}

	/**
	 * Language and comparable key of one post, or null to ignore it.
	 *
	 * @param \WP_Post             $post    Post.
	 * @param string               $default_code Default language code.
	 * @param array<string,string> $markers Path markers.
	 * @return array{id:int,language:string,key:string,slug:string,title:string}|null
	 */
	private static function classify( $post, $default_code, array $markers ) {
		$uri = is_post_type_hierarchical( $post->post_type ) ? get_page_uri( $post ) : $post->post_name;
		$uri = strtolower( trim( urldecode( (string) $uri ), '/' ) );
		if ( '' === $uri ) {
			return null;
		}
		$segments = explode( '/', $uri );
		$language = Links::get_language( $post->ID );
		$first    = $segments[0];

		if ( isset( $markers[ $first ] ) ) {
			// The language's own root page ("/fa") isn't a translation of anything.
			if ( 1 === count( $segments ) ) {
				return null;
			}
			array_shift( $segments );
			$language = null === $language ? $markers[ $first ] : $language;
		} else {
			$last = end( $segments );
			foreach ( $markers as $marker => $code ) {
				$suffix = '-' . $marker;
				if ( strlen( $last ) > strlen( $suffix ) && substr( $last, -strlen( $suffix ) ) === $suffix ) {
					$segments[ count( $segments ) - 1 ] = substr( $last, 0, -strlen( $suffix ) );
					$language                           = null === $language ? $code : $language;
					break;
				}
			}
		}

		return array(
			'id'       => (int) $post->ID,
			'language' => null === $language ? $default_code : $language,
			'key'      => implode( '/', $segments ),
			'slug'     => (string) end( $segments ),
			'title'    => self::lower( wp_strip_all_tags( $post->post_title ) ),
		);
	}

	/**
	 * Best unique pairs between sources and one language's targets.
	 *
	 * @param array  $sources Source entries.
	 * @param array  $targets Target entries.
	 * @param string $code    Target language.
	 * @return array<int,array{source:int,target:int,language:string,score:float}>
	 */
	private static function match( array $sources, array $targets, $code ) {
		$by_key  = array();
		$by_slug = array();
		foreach ( $sources as $source ) {
			$by_key[ $source['key'] ]     = $source['id'];
			$by_slug[ $source['slug'] ][] = $source['id'];
		}

		$fuzzy      = count( $sources ) * count( $targets ) <= self::MAX_FUZZY;
		$candidates = array();
		foreach ( $targets as $target ) {
			if ( isset( $by_key[ $target['key'] ] ) ) {
				$candidates[] = array( $by_key[ $target['key'] ], $target['id'], 100.0 );
				continue;
			}
			if ( isset( $by_slug[ $target['slug'] ] ) && 1 === count( $by_slug[ $target['slug'] ] ) ) {
				$candidates[] = array( $by_slug[ $target['slug'] ][0], $target['id'], 95.0 );
				continue;
			}
			if ( ! $fuzzy ) {
				continue;
			}
			foreach ( $sources as $source ) {
				similar_text( $source['slug'], $target['slug'], $slug_score );
				similar_text( $source['title'], $target['title'], $title_score );
				$score = max( $slug_score, $title_score );
				if ( $score >= 60 ) {
					$candidates[] = array( $source['id'], $target['id'], min( 94.0, (float) $score ) );
				}
			}
		}

		usort(
			$candidates,
			function ( $a, $b ) {
				return $b[2] <=> $a[2];
			}
		);

		$pairs        = array();
		$used_sources = array();
		$used_targets = array();
		foreach ( $candidates as $candidate ) {
			list( $source_id, $target_id, $score ) = $candidate;
			if ( isset( $used_sources[ $source_id ] ) || isset( $used_targets[ $target_id ] ) ) {
				continue;
			}
			$used_sources[ $source_id ] = true;
			$used_targets[ $target_id ] = true;

			$pairs[] = array(
				'source'   => $source_id,
				'target'   => $target_id,
				'language' => $code,
				'score'    => $score,
			);
		}
		return $pairs;
	}

	/**
	 * Lowercase, multibyte-safe when available.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function lower( $text ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
	}
}
