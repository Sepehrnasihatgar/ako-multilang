<?php
/**
 * The site's languages.
 *
 * @package Ako\Multilang
 */

namespace Ako\Multilang\Languages;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Stores languages in the option `ako_ml_languages`.
 *
 * Shape: `array( 'default' => 'en', 'languages' => array( array( 'code' => 'en',
 * 'locale' => 'en_US', 'hreflang' => 'en-US', 'name' => 'English',
 * 'native' => 'English', 'dir' => 'ltr' ), … ) )`.
 *
 * Order in the array is the order shown in the switcher.
 */
final class Registry {

	const OPTION = 'ako_ml_languages';

	/**
	 * Language codes: 2–3 lowercase letters, optional "-" + 2–4 alphanumerics (e.g. "pt-br").
	 */
	const CODE_PATTERN = '/^[a-z]{2,3}(-[a-z0-9]{2,4})?$/';

	/**
	 * WordPress locales: "en", "en_US", "de_DE_formal", "pt_BR".
	 */
	const LOCALE_PATTERN = '/^[a-z]{2,3}(_[A-Z]{2})?(_[a-z0-9]+)?$/';

	/**
	 * Valid hreflang values: language, optionally followed by script or region.
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

			$default_code = ( is_array( $stored ) && isset( $stored['default'] ) ) ? sanitize_key( $stored['default'] ) : '';
			if ( ! isset( $languages[ $default_code ] ) ) {
				$default_code = $languages ? (string) array_key_first( $languages ) : '';
			}

			self::$cache = array(
				'default'   => $default_code,
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
			return new WP_Error( 'ako_ml_invalid_code', __( 'Use a language code like “ar” or “pt-br”.', 'ako-multilang' ) );
		}

		$locale = isset( $raw['locale'] ) ? sanitize_text_field( (string) $raw['locale'] ) : '';
		if ( ! preg_match( self::LOCALE_PATTERN, $locale ) ) {
			return new WP_Error( 'ako_ml_invalid_locale', __( 'Use a WordPress locale like “ar” or “fa_IR”.', 'ako-multilang' ) );
		}

		$hreflang = isset( $raw['hreflang'] ) && '' !== $raw['hreflang'] ? sanitize_text_field( (string) $raw['hreflang'] ) : $code;
		if ( ! preg_match( self::HREFLANG_PATTERN, $hreflang ) ) {
			return new WP_Error( 'ako_ml_invalid_hreflang', __( 'Use a code like “ar” or “ar-SA”.', 'ako-multilang' ) );
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
	 * @param string $default_code Default language code.
	 * @return true|WP_Error
	 */
	public static function save( array $languages, $default_code ) {
		$clean = array();
		foreach ( $languages as $raw ) {
			$language = is_array( $raw ) ? self::sanitize_language( $raw ) : new WP_Error( 'ako_ml_invalid_language', __( 'Invalid language.', 'ako-multilang' ) );
			if ( is_wp_error( $language ) ) {
				return $language;
			}
			if ( isset( $clean[ $language['code'] ] ) ) {
				return new WP_Error( 'ako_ml_duplicate_language', __( 'Each language can only be added once.', 'ako-multilang' ) );
			}
			$clean[ $language['code'] ] = $language;
		}

		$default_code = sanitize_key( (string) $default_code );
		if ( $clean && ! isset( $clean[ $default_code ] ) ) {
			return new WP_Error( 'ako_ml_invalid_default', __( 'The default language must be one of your languages.', 'ako-multilang' ) );
		}

		update_option(
			self::OPTION,
			array(
				'default'   => $default_code,
				'languages' => array_values( $clean ),
			),
			true
		);
		self::$cache = null;

		/**
		 * Fires after the language list changed.
		 *
		 * @param array  $languages Sanitized languages keyed by code.
		 * @param string $default_code Default language code.
		 */
		do_action( 'ako_ml_languages_saved', $clean, $default_code );

		return true;
	}

	/**
	 * Adds one language at the end of the list.
	 *
	 * @param array $raw Raw language.
	 * @return true|WP_Error
	 */
	public static function add( array $raw ) {
		$languages    = array_values( self::all() );
		$languages[]  = $raw;
		$default_code = self::default_code();
		if ( '' === $default_code && isset( $raw['code'] ) ) {
			$default_code = $raw['code'];
		}
		return self::save( $languages, $default_code );
	}

	/**
	 * Updates one language in place. The code never changes: it identifies
	 * the language on every linked post.
	 *
	 * @param string $code Language code.
	 * @param array  $raw  New values (locale, hreflang, name, native, dir).
	 * @return true|WP_Error
	 */
	public static function update( $code, array $raw ) {
		$code      = sanitize_key( (string) $code );
		$languages = self::all();
		if ( ! isset( $languages[ $code ] ) ) {
			return new WP_Error( 'ako_ml_unknown_language', __( 'That language isn’t set up yet.', 'ako-multilang' ), array( 'status' => 404 ) );
		}

		$languages[ $code ] = array_merge( $languages[ $code ], $raw, array( 'code' => $code ) );
		return self::save( array_values( $languages ), self::default_code() );
	}

	/**
	 * Removes a language from the list. Callers unlink its posts first
	 * (see Links::detach_language()); posts themselves are never deleted.
	 *
	 * @param string $code Language code.
	 * @return true|WP_Error
	 */
	public static function remove( $code ) {
		$code      = sanitize_key( (string) $code );
		$languages = self::all();
		if ( ! isset( $languages[ $code ] ) ) {
			return new WP_Error( 'ako_ml_unknown_language', __( 'That language isn’t set up yet.', 'ako-multilang' ), array( 'status' => 404 ) );
		}
		if ( self::default_code() === $code ) {
			return new WP_Error( 'ako_ml_remove_default', __( 'The default language can’t be removed.', 'ako-multilang' ), array( 'status' => 400 ) );
		}

		unset( $languages[ $code ] );
		return self::save( array_values( $languages ), self::default_code() );
	}

	/**
	 * Saves a new display order.
	 *
	 * @param string[] $codes Every language code, exactly once, in the new order.
	 * @return true|WP_Error
	 */
	public static function reorder( array $codes ) {
		$languages = self::all();
		$codes     = array_map( 'sanitize_key', $codes );

		$sorted_new = $codes;
		$sorted_old = array_keys( $languages );
		sort( $sorted_new );
		sort( $sorted_old );
		if ( $sorted_new !== $sorted_old ) {
			return new WP_Error( 'ako_ml_invalid_order', __( 'The order must list every language once.', 'ako-multilang' ), array( 'status' => 400 ) );
		}

		$ordered = array();
		foreach ( $codes as $code ) {
			$ordered[] = $languages[ $code ];
		}
		return self::save( $ordered, self::default_code() );
	}

	/**
	 * Clears the per-request cache.
	 */
	public static function flush() {
		self::$cache = null;
	}
}
