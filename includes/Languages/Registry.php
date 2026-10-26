<?php
/**
 * The site's languages.
 *
 * @package Cedar\Multilang
 */

namespace Cedar\Multilang\Languages;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Stores languages in the `cedar_ml_languages` option:
 *
 *     array(
 *         'default'   => 'en',
 *         'languages' => array(
 *             array( 'code' => 'en', 'locale' => 'en_US', 'hreflang' => 'en-US',
 *                    'name' => 'English', 'native' => 'English', 'dir' => 'ltr' ),
 *             …
 *         ),
 *     )
 *
 * Order in the array is the order shown in the switcher.
 */
final class Registry {

	const OPTION = 'cedar_ml_languages';

	/**
	 * Language codes: 2–3 lowercase letters, optional "-" + 2–4 alphanumerics (e.g. "pt-br").
	 */
	const CODE_PATTERN = '/^[a-z]{2,3}(-[a-z0-9]{2,4})?$/';

	/**
	 * WordPress locales: "en", "en_US", "de_DE_formal", "pt_BR".
	 */
	const LOCALE_PATTERN = '/^[a-z]{2,3}(_[A-Z]{2})?(_[a-z0-9]+)?$/';

	/**
	 * hreflang values: language, optionally followed by script or region.
	 */
	const HREFLANG_PATTERN = '/^[a-z]{2,3}(-[A-Za-z]{2,4})?$/';

	/**
	 * Per-request cache.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Returns the stored structure, sanitized.
	 *
	 * @return array{default:string,languages:array<int,array>}
	 */
	private static function data() {
		if ( null === self::$cache ) {
			$stored    = get_option( self::OPTION, array() );
			$languages = array();

			if ( is_array( $stored ) && isset( $stored['languages'] ) && is_array( $stored['languages'] ) ) {
				foreach ( $stored['languages'] as $raw ) {
					$clean = is_array( $raw ) ? self::sanitize_language( $raw ) : null;
					if ( is_array( $clean ) ) {
						$languages[ $clean['code'] ] = $clean;
					}
				}
			}

			$default = ( is_array( $stored ) && isset( $stored['default'] ) ) ? sanitize_key( $stored['default'] ) : '';
			if ( ! isset( $languages[ $default ] ) ) {
				$default = $languages ? (string) array_key_first( $languages ) : '';
			}

			self::$cache = array(
				'default'   => $default,
				'languages' => $languages,
			);
		}
		return self::$cache;
	}

	/**
	 * All languages, keyed by code, in switcher order.
	 *
	 * @return array<string,array>
	 */
	public static function all() {
		return self::data()['languages'];
	}

	/**
	 * One language by code.
	 *
	 * @param string $code Language code.
	 * @return array|null
	 */
	public static function get( $code ) {
		$languages = self::all();
		$code      = sanitize_key( (string) $code );
		return isset( $languages[ $code ] ) ? $languages[ $code ] : null;
	}

	/**
	 * Whether a language code is registered.
	 *
	 * @param string $code Language code.
	 * @return bool
	 */
	public static function exists( $code ) {
		return null !== self::get( $code );
	}

	/**
	 * The default language code ('' when no languages exist yet).
	 *
	 * @return string
	 */
	public static function default_code() {
		return self::data()['default'];
	}

	/**
	 * Validates and normalizes one language definition.
	 *
	 * @param array $raw Raw language.
	 * @return array|WP_Error
	 */
	public static function sanitize_language( array $raw ) {
		$code = isset( $raw['code'] ) ? strtolower( sanitize_text_field( (string) $raw['code'] ) ) : '';
		if ( ! preg_match( self::CODE_PATTERN, $code ) ) {
			return new WP_Error( 'cedar_ml_invalid_code', __( 'Use a language code like “ar” or “pt-br”.', 'cedar-multilang' ) );
		}

		$locale = isset( $raw['locale'] ) ? sanitize_text_field( (string) $raw['locale'] ) : '';
		if ( ! preg_match( self::LOCALE_PATTERN, $locale ) ) {
			return new WP_Error( 'cedar_ml_invalid_locale', __( 'Use a WordPress locale like “ar” or “fa_IR”.', 'cedar-multilang' ) );
		}

		$hreflang = isset( $raw['hreflang'] ) && '' !== $raw['hreflang'] ? sanitize_text_field( (string) $raw['hreflang'] ) : $code;
		if ( ! preg_match( self::HREFLANG_PATTERN, $hreflang ) ) {
			return new WP_Error( 'cedar_ml_invalid_hreflang', __( 'Use a code like “ar” or “ar-SA”.', 'cedar-multilang' ) );
		}

		$name   = isset( $raw['name'] ) ? sanitize_text_field( (string) $raw['name'] ) : '';
		$native = isset( $raw['native'] ) ? sanitize_text_field( (string) $raw['native'] ) : '';
		if ( '' === $name ) {
			$name = strtoupper( $code );
		}
		if ( '' === $native ) {
			$native = $name;
		}

		$dir = ( isset( $raw['dir'] ) && 'rtl' === $raw['dir'] ) ? 'rtl' : 'ltr';

		return array(
			'code'     => $code,
			'locale'   => $locale,
			'hreflang' => $hreflang,
			'name'     => $name,
			'native'   => $native,
			'dir'      => $dir,
		);
	}

	/**
	 * Replaces the whole language list.
	 *
	 * @param array  $languages List of raw language arrays, in display order.
	 * @param string $default   Default language code.
	 * @return true|WP_Error
	 */
	public static function save( array $languages, $default ) {
		$clean = array();
		foreach ( $languages as $raw ) {
			$language = is_array( $raw ) ? self::sanitize_language( $raw ) : new WP_Error( 'cedar_ml_invalid_language', __( 'Invalid language.', 'cedar-multilang' ) );
			if ( is_wp_error( $language ) ) {
				return $language;
			}
			if ( isset( $clean[ $language['code'] ] ) ) {
				return new WP_Error( 'cedar_ml_duplicate_language', __( 'Each language can only be added once.', 'cedar-multilang' ) );
			}
			$clean[ $language['code'] ] = $language;
		}

		$default = sanitize_key( (string) $default );
		if ( $clean && ! isset( $clean[ $default ] ) ) {
			return new WP_Error( 'cedar_ml_invalid_default', __( 'The default language must be one of your languages.', 'cedar-multilang' ) );
		}

		update_option(
			self::OPTION,
			array(
				'default'   => $default,
				'languages' => array_values( $clean ),
			),
			true
		);
		self::$cache = null;

		/**
		 * Fires after the language list changed.
		 *
		 * @param array  $languages Sanitized languages keyed by code.
		 * @param string $default   Default language code.
		 */
		do_action( 'cedar_ml_languages_saved', $clean, $default );

		return true;
	}

	/**
	 * Adds one language at the end of the list.
	 *
	 * @param array $raw Raw language.
	 * @return true|WP_Error
	 */
	public static function add( array $raw ) {
		$languages   = array_values( self::all() );
		$languages[] = $raw;
		$default     = self::default_code();
		if ( '' === $default && isset( $raw['code'] ) ) {
			$default = $raw['code'];
		}
		return self::save( $languages, $default );
	}

	/**
	 * Clears the per-request cache.
	 */
	public static function flush() {
		self::$cache = null;
	}
}
