<?php
/**
 * Reads WPML's translation groups.
 *
 * @package Cedar\Multilang
 */

namespace Cedar\Multilang\Import;

defined( 'ABSPATH' ) || exit;

/**
 * WPML keeps one row per translated element in `{prefix}icl_translations`
 * (trid = group, element_type = "post_{type}") and its languages in
 * `{prefix}icl_languages`.
 */
final class Wpml extends Source {

	/**
	 * Source key.
	 *
	 * @return string
	 */
	public function key() {
		return 'wpml';
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function name() {
		return 'WPML';
	}

	/**
	 * Whether WPML is active.
	 *
	 * @return bool
	 */
	public function is_active() {
		return defined( 'ICL_SITEPRESS_VERSION' );
	}

	/**
	 * Translations table name.
	 *
	 * @return string
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'icl_translations';
	}

	/**
	 * Whether WPML's tables exist.
	 *
	 * @return bool
	 */
	public function has_data() {
		return self::table_exists( self::table() );
	}

	/**
	 * Active WPML languages.
	 *
	 * @return array<int,array{code:string,locale:string}>
	 */
	public function languages() {
		global $wpdb;
		$table = $wpdb->prefix . 'icl_languages';
		if ( ! self::table_exists( $table ) ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Reads WPML's own table; the name is built from $wpdb->prefix only.
		$rows = $wpdb->get_results( "SELECT code, default_locale FROM `{$table}` WHERE active = 1 ORDER BY id", ARRAY_A );

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'code'   => strtolower( (string) $row['code'] ),
				'locale' => (string) $row['default_locale'],
			);
		}
		return $out;
	}

	/**
	 * WPML's default language.
	 *
	 * @return string
	 */
	public function default_language() {
		$settings = get_option( 'icl_sitepress_settings', array() );
		return is_array( $settings ) && ! empty( $settings['default_language'] ) ? strtolower( (string) $settings['default_language'] ) : '';
	}

	/**
	 * `post_{type}` element types and their placeholders.
	 *
	 * @param string[] $post_types Post types.
	 * @return array{0:string[],1:string}
	 */
	private static function element_types( array $post_types ) {
		$types = array();
		foreach ( $post_types as $post_type ) {
			$types[] = 'post_' . sanitize_key( $post_type );
		}
		return array( $types, implode( ', ', array_fill( 0, count( $types ), '%s' ) ) );
	}

	/**
	 * Groups with at least two posts.
	 *
	 * @param string[] $post_types Post types.
	 * @return int
	 */
	public function count_groups( array $post_types ) {
		global $wpdb;
		if ( ! $post_types || ! $this->has_data() ) {
			return 0;
		}
		list( $types, $placeholders ) = self::element_types( $post_types );
		$table                        = self::table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- WPML's table; values go through prepare().
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM ( SELECT trid FROM `{$table}` WHERE element_type IN ( {$placeholders} ) AND element_id IS NOT NULL GROUP BY trid HAVING COUNT(*) > 1 ) AS cedar_groups",
				$types
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
		if ( ! $post_types || ! $this->has_data() ) {
			return array();
		}
		list( $types, $placeholders ) = self::element_types( $post_types );
		$table                        = self::table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- WPML's table; values go through prepare().
		$trids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT trid FROM `{$table}` WHERE element_type IN ( {$placeholders} ) AND element_id IS NOT NULL GROUP BY trid HAVING COUNT(*) > 1 ORDER BY trid LIMIT %d OFFSET %d",
				array_merge( $types, array( absint( $limit ), absint( $offset ) ) )
			)
		);
		if ( ! $trids ) {
			return array();
		}
		$trids = array_map( 'absint', $trids );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT trid, element_id, language_code FROM `{$table}` WHERE element_type IN ( {$placeholders} ) AND element_id IS NOT NULL AND trid IN ( " . implode( ', ', array_fill( 0, count( $trids ), '%d' ) ) . ' )',
				array_merge( $types, $trids )
			),
			ARRAY_A
		);
		// phpcs:enable

		$groups = array_fill_keys( $trids, array() );
		foreach ( (array) $rows as $row ) {
			$groups[ (int) $row['trid'] ][ strtolower( (string) $row['language_code'] ) ] = (int) $row['element_id'];
		}
		return array_values( $groups );
	}
}
