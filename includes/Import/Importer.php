<?php
/**
 * Imports translation links from WPML or Polylang, in batches.
 *
 * @package Cedar\Multilang
 */

namespace Cedar\Multilang\Import;

use Cedar\Multilang\Content\Links;
use Cedar\Multilang\Content\Taxonomies;
use Cedar\Multilang\Languages\Catalog;
use Cedar\Multilang\Languages\Registry;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the other plugin's languages to Cedar (matched by locale), then links
 * each group. Never moves a page that is already linked elsewhere in Cedar,
 * never changes a language Cedar already knows, never touches the other
 * plugin's data. Safe to run again: existing links are kept.
 */
final class Importer {

	/**
	 * Option with the last import per source (not autoloaded).
	 */
	const STATUS_OPTION = 'cedar_ml_imports';

	/**
	 * Largest batch per request.
	 */
	const MAX_BATCH = 50;

	/**
	 * Available sources.
	 *
	 * @return array<string,Source>
	 */
	public static function sources() {
		return array(
			'wpml'     => new Wpml(),
			'polylang' => new Polylang(),
		);
	}

	/**
	 * One source by key.
	 *
	 * @param string $key Source key.
	 * @return Source|null
	 */
	public static function source( $key ) {
		$sources = self::sources();
		return isset( $sources[ $key ] ) ? $sources[ $key ] : null;
	}

	/**
	 * Every source with data, for the admin app.
	 *
	 * @return array<int,array{key:string,name:string,active:bool,groups:int,imported:array|null}>
	 */
	public static function summary() {
		$status = get_option( self::STATUS_OPTION, array() );
		$out    = array();
		foreach ( self::sources() as $key => $source ) {
			if ( ! $source->has_data() ) {
				continue;
			}
			$out[] = array(
				'key'      => $key,
				'name'     => $source->name(),
				'active'   => $source->is_active(),
				'groups'   => $source->count_groups( Taxonomies::post_types() ),
				'imported' => is_array( $status ) && isset( $status[ $key ] ) ? $status[ $key ] : null,
			);
		}
		return $out;
	}

	/**
	 * Adds the source's languages to Cedar where missing.
	 *
	 * @param Source $source Source.
	 * @return array<string,string> Their code => Cedar code.
	 */
	public static function map_languages( Source $source ) {
		$map         = array();
		$theirs      = $source->languages();
		$their_first = $source->default_language();

		// Their default first, so it becomes Cedar's default on an empty site.
		usort(
			$theirs,
			function ( $a, $b ) use ( $their_first ) {
				return (int) ( $b['code'] === $their_first ) - (int) ( $a['code'] === $their_first );
			}
		);

		foreach ( $theirs as $language ) {
			$code = self::find_language( $language );
			if ( '' === $code ) {
				$code = self::add_language( $language );
			}
			if ( '' !== $code ) {
				$map[ $language['code'] ] = $code;
			}
		}
		return $map;
	}

	/**
	 * Cedar code for a source language (same locale, else same code), or ''.
	 *
	 * @param array{code:string,locale:string} $language Source language.
	 * @return string
	 */
	private static function find_language( array $language ) {
		foreach ( Registry::all() as $code => $ours ) {
			if ( $ours['locale'] === $language['locale'] ) {
				return $code;
			}
		}
		return Registry::exists( $language['code'] ) ? $language['code'] : '';
	}

	/**
	 * Adds a source language to Cedar.
	 *
	 * @param array{code:string,locale:string} $language Source language.
	 * @return string Cedar code, or '' if it can't be added.
	 */
	private static function add_language( array $language ) {
		$known = Catalog::get( $language['locale'] );
		$taken = array_keys( Registry::all() );
		$code  = preg_match( Registry::CODE_PATTERN, $language['code'] ) && ! in_array( $language['code'], $taken, true )
			? $language['code']
			: Catalog::code_for( $language['locale'], $taken );

		$result = Registry::add(
			array(
				'code'     => $code,
				'locale'   => $language['locale'],
				'hreflang' => $known ? $known['hreflang'] : Catalog::hreflang_for( $language['locale'] ),
				'name'     => $known ? $known['name'] : '',
				'native'   => $known ? $known['native'] : '',
				'dir'      => $known ? $known['dir'] : 'ltr',
			)
		);
		return true === $result ? $code : '';
	}

	/**
	 * Imports one batch of groups.
	 *
	 * @param Source $source Source.
	 * @param int    $offset Groups already processed.
	 * @param int    $limit  Batch size.
	 * @return array{processed:int,total:int,linked:int,skipped:array,done:bool}|WP_Error
	 */
	public static function run( Source $source, $offset, $limit ) {
		$post_types = Taxonomies::post_types();
		$total      = $source->count_groups( $post_types );
		$map        = self::map_languages( $source );
		if ( ! $map ) {
			return new WP_Error( 'cedar_ml_import_languages', __( 'Cedar couldn’t read the languages from this plugin. Nothing was changed.', 'cedar-multilang' ), array( 'status' => 500 ) );
		}

		$limit   = max( 1, min( self::MAX_BATCH, absint( $limit ) ) );
		$groups  = $source->groups( $post_types, absint( $offset ), $limit );
		$linked  = 0;
		$skipped = array();

		foreach ( $groups as $group ) {
			$result = self::import_group( $group, $map );
			if ( true === $result ) {
				++$linked;
			} else {
				$skipped[] = $result;
			}
		}

		$processed = absint( $offset ) + count( $groups );
		$done      = count( $groups ) < $limit || $processed >= $total;

		if ( $done ) {
			$status = get_option( self::STATUS_OPTION, array() );
			$status = is_array( $status ) ? $status : array();

			$status[ $source->key() ] = array(
				'at'     => time(),
				'groups' => $total,
			);
			update_option( self::STATUS_OPTION, $status, false );
		}

		return array(
			'processed' => $processed,
			'total'     => $total,
			'linked'    => $linked,
			'skipped'   => $skipped,
			'done'      => $done,
		);
	}

	/**
	 * Links one group.
	 *
	 * @param array<string,int>    $group Their code => post ID.
	 * @param array<string,string> $map   Their code => Cedar code.
	 * @return true|array{reason:string,pages:array} True, or why it was skipped.
	 */
	private static function import_group( array $group, array $map ) {
		$members = array();
		$pages   = array();
		$reason  = 'missing';

		foreach ( $group as $their_code => $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post || 'trash' === $post->post_status || 'auto-draft' === $post->post_status ) {
				continue;
			}
			$pages[] = array(
				'id'    => $post->ID,
				'title' => get_the_title( $post ),
			);
			if ( ! isset( $map[ $their_code ] ) || ! Taxonomies::supports( $post->post_type ) ) {
				continue;
			}
			$code    = $map[ $their_code ];
			$current = Links::get_language( $post->ID );
			if ( null !== $current && $current !== $code ) {
				$reason = 'conflict';
				continue;
			}
			$members[ $code ] = $post->ID;
		}

		if ( count( $members ) < 2 ) {
			return array(
				'reason' => $reason,
				'pages'  => $pages,
			);
		}

		// A page already linked to a different group in Cedar stays where it is.
		$groups = array_unique( array_filter( array_map( array( Links::class, 'get_group_id' ), $members ) ) );
		if ( count( $groups ) > 1 ) {
			return array(
				'reason' => 'conflict',
				'pages'  => $pages,
			);
		}

		// Grow the existing group if there is one; otherwise start from the default language.
		$default   = Registry::default_code();
		$source_id = isset( $members[ $default ] ) ? $members[ $default ] : reset( $members );
		if ( $groups ) {
			$existing = reset( $groups );
			foreach ( $members as $post_id ) {
				if ( Links::get_group_id( $post_id ) === $existing ) {
					$source_id = $post_id;
					break;
				}
			}
		}
		foreach ( $members as $code => $post_id ) {
			if ( null === Links::get_language( $post_id ) ) {
				$set = Links::set_language( $post_id, $code );
				if ( is_wp_error( $set ) ) {
					return array(
						'reason' => 'error',
						'pages'  => $pages,
					);
				}
			}
		}
		foreach ( $members as $post_id ) {
			if ( $post_id !== $source_id ) {
				$result = Links::link( $source_id, $post_id );
				if ( is_wp_error( $result ) ) {
					return array(
						'reason' => 'conflict',
						'pages'  => $pages,
					);
				}
			}
		}
		return true;
	}
}
