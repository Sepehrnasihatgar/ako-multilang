<?php
/**
 * Base class for reading another multilingual plugin's links.
 *
 * @package Cedar\Multilang
 */

namespace Cedar\Multilang\Import;

defined( 'ABSPATH' ) || exit;

/**
 * A source reads straight from the database, so it works even after the
 * other plugin is deactivated. Sources never write to the other plugin's data.
 */
abstract class Source {

	/**
	 * Source key (wpml|polylang).
	 *
	 * @return string
	 */
	abstract public function key();

	/**
	 * Display name.
	 *
	 * @return string
	 */
	abstract public function name();

	/**
	 * Whether the other plugin is currently active.
	 *
	 * @return bool
	 */
	abstract public function is_active();

	/**
	 * Whether its data exists in this database.
	 *
	 * @return bool
	 */
	abstract public function has_data();

	/**
	 * Its languages: list of [ code, locale ] in its own codes.
	 *
	 * @return array<int,array{code:string,locale:string}>
	 */
	abstract public function languages();

	/**
	 * Its default language code ('' if unknown).
	 *
	 * @return string
	 */
	abstract public function default_language();

	/**
	 * Number of translation groups with at least two posts of the given types.
	 *
	 * @param string[] $post_types Post types Cedar handles.
	 * @return int
	 */
	abstract public function count_groups( array $post_types );

	/**
	 * One page of groups: each is [ its language code => post ID ].
	 *
	 * @param string[] $post_types Post types Cedar handles.
	 * @param int      $offset     Groups to skip.
	 * @param int      $limit      Groups to return.
	 * @return array<int,array<string,int>>
	 */
	abstract public function groups( array $post_types, $offset, $limit );

	/**
	 * Whether a database table exists.
	 *
	 * @param string $table Full table name.
	 * @return bool
	 */
	protected static function table_exists( $table ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off schema check for another plugin's table.
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}

	/**
	 * Unserializes data another plugin stored, refusing anything that holds
	 * objects (it only ever stores arrays and scalars).
	 *
	 * @param string $value Stored value.
	 * @return mixed|null
	 */
	protected static function read_array( $value ) {
		if ( ! is_string( $value ) || ! is_serialized( $value ) || preg_match( '/(^|[;{])[OC]:\d+:"/', $value ) ) {
			return null;
		}
		$data = maybe_unserialize( $value );
		return is_array( $data ) ? $data : null;
	}
}
