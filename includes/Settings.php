<?php
/**
 * Typed access to the plugin settings option.
 *
 * @package AkoMultilang
 */

namespace AkoMultilang;

defined( 'ABSPATH' ) || exit;

/**
 * Reads, sanitizes and saves `akoml_settings`.
 */
final class Settings {

	const OPTION = 'akoml_settings';

	/**
	 * Allowed values for the "no translation" behavior.
	 */
	const FALLBACKS = array( 'hide', 'home', 'disabled' );

	/**
	 * Per-request cache.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'modules'             => array(
				'seo'      => true,
				'rtl'      => true,
				'switcher' => true,
				'import'   => false,
			),
			'post_types'          => array( 'page', 'post' ),
			'fallback'            => 'home',
			'x_default'           => '',
			'sitemap'             => true,
			'og_locale'           => true,
			'skip_noindex'        => true,
			'rtl_compat'          => array(),
			'delete_on_uninstall' => false,
		);
	}

	/**
	 * Returns the full, sanitized settings array.
	 *
	 * @return array
	 */
	public static function get() {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = self::sanitize( is_array( $stored ) ? $stored : array() );
		}
		return self::$cache;
	}

	/**
	 * Saves settings after sanitizing them.
	 *
	 * @param array $settings Raw settings.
	 * @return bool Whether the option changed.
	 */
	public static function update( array $settings ) {
		$clean       = self::sanitize( $settings );
		self::$cache = $clean;
		return update_option( self::OPTION, $clean, true );
	}

	/**
	 * Sanitizes a settings array against the defaults. Unknown keys are dropped.
	 *
	 * @param array $raw Raw settings.
	 * @return array
	 */
	public static function sanitize( array $raw ) {
		$defaults = self::defaults();
		$out      = $defaults;

		if ( isset( $raw['modules'] ) && is_array( $raw['modules'] ) ) {
			foreach ( array_keys( $defaults['modules'] ) as $module ) {
				if ( isset( $raw['modules'][ $module ] ) ) {
					$out['modules'][ $module ] = (bool) $raw['modules'][ $module ];
				}
			}
		}

		if ( isset( $raw['post_types'] ) && is_array( $raw['post_types'] ) ) {
			$post_types = array_unique( array_filter( array_map( 'sanitize_key', $raw['post_types'] ) ) );

			// Saves happen after wp_loaded, when every post type is registered; earlier
			// reads keep stored values so custom post types are not dropped.
			if ( did_action( 'wp_loaded' ) ) {
				$post_types = array_filter( $post_types, 'post_type_exists' );
			}

			$out['post_types'] = array_values( $post_types );
		}

		if ( isset( $raw['fallback'] ) && in_array( $raw['fallback'], self::FALLBACKS, true ) ) {
			$out['fallback'] = $raw['fallback'];
		}

		if ( isset( $raw['x_default'] ) ) {
			$out['x_default'] = sanitize_key( $raw['x_default'] );
		}

		foreach ( array( 'sitemap', 'og_locale', 'skip_noindex', 'delete_on_uninstall' ) as $flag ) {
			if ( isset( $raw[ $flag ] ) ) {
				$out[ $flag ] = (bool) $raw[ $flag ];
			}
		}

		if ( isset( $raw['rtl_compat'] ) && is_array( $raw['rtl_compat'] ) ) {
			$out['rtl_compat'] = array();
			$known             = Rtl\Compat::known();
			foreach ( $raw['rtl_compat'] as $key => $enabled ) {
				$key = sanitize_key( $key );
				if ( isset( $known[ $key ] ) ) {
					$out['rtl_compat'][ $key ] = (bool) $enabled;
				}
			}
		}

		return $out;
	}

	/**
	 * Clears the per-request cache (used after external option changes).
	 */
	public static function flush() {
		self::$cache = null;
	}
}
