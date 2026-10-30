<?php
/**
 * Reads Polylang's translation groups.
 *
 * @package AkoMultilang
 */

namespace AkoMultilang\Import;

defined( 'ABSPATH' ) || exit;

/**
 * Polylang stores languages as terms of the `language` taxonomy (locale in
 * the serialized description) and each group as a `post_translations` term
 * whose description is a serialized [ code => post ID ] map. Read with SQL so
 * it works while Polylang is deactivated (its taxonomies aren't registered).
 */
final class Polylang extends Source {

	/**
	 * Source key.
	 *
	 * @return string
	 */
	public function key() {
		return 'polylang';
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function name() {
		return 'Polylang';
	}

	/**
	 * Whether Polylang is active.
	 *
	 * @return bool
	 */
	public function is_active() {
		return defined( 'POLYLANG_VERSION' );
	}

	/**
	 * Whether Polylang languages exist.
	 *
	 * @return bool
	 */
	public function has_data() {
		return (bool) $this->languages();
	}

	/**
	 * Polylang languages in their order.
	 *
	 * @return array<int,array{code:string,locale:string}>
	 */
	public function languages() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Polylang's taxonomy isn't registered while it's inactive.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.slug, tt.description FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = %s ORDER BY t.term_group, t.term_id",
				'language'
			),
			ARRAY_A
		);

		$out = array();
		foreach ( (array) $rows as $row ) {
			$data   = self::read_array( $row['description'] );
			$code   = strtolower( sanitize_key( $row['slug'] ) );
			$locale = is_array( $data ) && ! empty( $data['locale'] ) ? (string) $data['locale'] : $code;
			$out[]  = array(
				'code'   => $code,
				'locale' => $locale,
			);
		}
		return $out;
	}

	/**
	 * Polylang's default language.
	 *
	 * @return string
	 */
	public function default_language() {
		$options = get_option( 'polylang', array() );
		return is_array( $options ) && ! empty( $options['default_lang'] ) ? strtolower( (string) $options['default_lang'] ) : '';
	}

	/**
	 * Groups with at least two posts of the given types.
	 *
	 * @param string[] $post_types Post types.
	 * @return int
	 */
	public function count_groups( array $post_types ) {
		global $wpdb;
		if ( ! $post_types ) {
			return 0;
		}
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Core tables; values go through prepare().
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM ( SELECT tt.term_taxonomy_id FROM {$wpdb->term_taxonomy} tt INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE tt.taxonomy = %s AND p.post_type IN ( " . implode( ', ', array_fill( 0, count( $post_types ), '%s' ) ) . ' ) GROUP BY tt.term_taxonomy_id HAVING COUNT(*) > 1 ) AS ako_groups',
				array_merge( array( 'post_translations' ), array_map( 'sanitize_key', $post_types ) )
			)
		);
		// phpcs:enable
	}

	/**
	 * One page of groups.
	 *
	 * @param string[] $post_types Post types.
	 * @param int      $offset     Offset.
	 * @param int      $limit      Limit.
	 * @return array<int,array<string,int>>
	 */
	public function groups( array $post_types, $offset, $limit ) {
		global $wpdb;
		if ( ! $post_types ) {
			return array();
		}
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Core tables; values go through prepare().
		$descriptions = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT tt.description FROM {$wpdb->term_taxonomy} tt INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE tt.taxonomy = %s AND p.post_type IN ( " . implode( ', ', array_fill( 0, count( $post_types ), '%s' ) ) . ' ) GROUP BY tt.term_taxonomy_id HAVING COUNT(*) > 1 ORDER BY tt.term_taxonomy_id LIMIT %d OFFSET %d',
				array_merge( array( 'post_translations' ), array_map( 'sanitize_key', $post_types ), array( absint( $limit ), absint( $offset ) ) )
			)
		);
		// phpcs:enable

		$groups = array();
		foreach ( (array) $descriptions as $description ) {
			$data  = self::read_array( $description );
			$group = array();
			foreach ( (array) $data as $code => $post_id ) {
				if ( is_string( $code ) && absint( $post_id ) ) {
					$group[ strtolower( sanitize_key( $code ) ) ] = absint( $post_id );
				}
			}
			$groups[] = $group;
		}
		return $groups;
	}
}
