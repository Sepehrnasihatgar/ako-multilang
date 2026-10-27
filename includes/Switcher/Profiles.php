<?php
/**
 * Saved switcher designs ("Header switcher", "Footer switcher", …).
 *
 * @package Ako\Multilang
 */

namespace Ako\Multilang\Switcher;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Stores profiles in the option `ako_ml_switchers` as
 * `array( 'profiles' => array( id => profile ) )`. Every value is
 * allow-listed; unknown keys are dropped.
 */
final class Profiles {

	const OPTION = 'ako_ml_switchers';

	const STYLES     = array( 'link', 'inline', 'segmented', 'dropdown', 'floating' );
	const SHOW       = array( 'code', 'native', 'both' );
	const MOBILE     = array( 'same', 'code', 'icon' );
	const MISSING    = array( 'home', 'hide', 'disabled' );
	const STYLING    = array( 'ako', 'inherit' );
	const SHAPES     = array( 'pill', 'rounded', 'square' );
	const LINK_LOOKS = array( 'plain', 'outline', 'soft', 'solid' );

	/**
	 * Maximum number of saved switchers.
	 */
	const MAX = 20;

	/**
	 * Per-request cache.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Values of a new switcher.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'id'       => 'header',
			'name'     => __( 'Header switcher', 'ako-multilang' ),
			'style'    => 'dropdown',
			'show'     => 'code',
			'icon'     => true,
			'mobile'   => 'same',
			'missing'  => 'home',
			'styling'  => 'ako',
			'accent'   => '',
			'shape'    => 'pill',
			'linkLook' => 'outline',
		);
	}

	/**
	 * All profiles, keyed by ID. A site without saved profiles gets one
	 * default profile so shortcodes and blocks work out of the box.
	 *
	 * @return array<string,array>
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$stored   = get_option( self::OPTION, array() );
			$profiles = array();

			if ( is_array( $stored ) && isset( $stored['profiles'] ) && is_array( $stored['profiles'] ) ) {
				foreach ( $stored['profiles'] as $raw ) {
					$clean = is_array( $raw ) ? self::sanitize( $raw ) : null;
					if ( is_array( $clean ) ) {
						$profiles[ $clean['id'] ] = $clean;
					}
				}
			}

			if ( ! $profiles ) {
				$default                    = self::defaults();
				$profiles[ $default['id'] ] = $default;
			}

			self::$cache = $profiles;
		}
		return self::$cache;
	}

	/**
	 * One profile, or null.
	 *
	 * @param string $id Profile ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		$profiles = self::all();
		$id       = sanitize_key( (string) $id );
		return isset( $profiles[ $id ] ) ? $profiles[ $id ] : null;
	}

	/**
	 * The profile to use when none is named: the first saved one.
	 *
	 * @return array
	 */
	public static function first() {
		$profiles = self::all();
		return reset( $profiles );
	}

	/**
	 * Applies overrides (shortcode attributes, template tag args) to a
	 * profile. Invalid values are ignored, so the saved value stays.
	 *
	 * @param array $profile   Valid profile.
	 * @param array $overrides Raw overrides (style, show).
	 * @return array
	 */
	public static function with_overrides( array $profile, array $overrides ) {
		$allowed = array(
			'style' => self::STYLES,
			'show'  => self::SHOW,
		);
		foreach ( $allowed as $key => $values ) {
			if ( isset( $overrides[ $key ] ) && in_array( $overrides[ $key ], $values, true ) ) {
				$profile[ $key ] = $overrides[ $key ];
			}
		}
		return $profile;
	}

	/**
	 * Validates one profile against the allow-lists.
	 *
	 * @param array $raw Raw values.
	 * @return array|WP_Error
	 */
	public static function sanitize( array $raw ) {
		$clean = self::defaults();

		$id = isset( $raw['id'] ) ? sanitize_key( (string) $raw['id'] ) : '';
		if ( '' === $id || strlen( $id ) > 40 ) {
			return new WP_Error( 'ako_ml_invalid_switcher', __( 'Use a short ID with letters, numbers, and hyphens.', 'ako-multilang' ) );
		}
		$clean['id'] = $id;

		if ( isset( $raw['name'] ) ) {
			$name          = sanitize_text_field( (string) $raw['name'] );
			$clean['name'] = '' === $name ? $id : mb_substr( $name, 0, 60 );
		}

		$choices = array(
			'style'    => self::STYLES,
			'show'     => self::SHOW,
			'mobile'   => self::MOBILE,
			'missing'  => self::MISSING,
			'styling'  => self::STYLING,
			'shape'    => self::SHAPES,
			'linkLook' => self::LINK_LOOKS,
		);
		foreach ( $choices as $key => $allowed ) {
			if ( isset( $raw[ $key ] ) && in_array( $raw[ $key ], $allowed, true ) ) {
				$clean[ $key ] = $raw[ $key ];
			}
		}

		if ( isset( $raw['icon'] ) ) {
			$clean['icon'] = (bool) $raw['icon'];
		}

		if ( isset( $raw['accent'] ) ) {
			$color           = sanitize_hex_color( (string) $raw['accent'] );
			$clean['accent'] = $color ? $color : '';
		}

		return $clean;
	}

	/**
	 * Creates or replaces one profile.
	 *
	 * @param array $raw Raw values (must include `id`).
	 * @return array|WP_Error Saved profile.
	 */
	public static function save( array $raw ) {
		$profile = self::sanitize( $raw );
		if ( is_wp_error( $profile ) ) {
			return $profile;
		}

		$profiles = self::stored();
		if ( ! isset( $profiles[ $profile['id'] ] ) && count( $profiles ) >= self::MAX ) {
			return new WP_Error( 'ako_ml_too_many_switchers', __( 'You’ve reached the maximum number of switchers.', 'ako-multilang' ) );
		}

		$profiles[ $profile['id'] ] = $profile;
		self::write( $profiles );
		return $profile;
	}

	/**
	 * Deletes one profile.
	 *
	 * @param string $id Profile ID.
	 * @return true|WP_Error
	 */
	public static function delete( $id ) {
		$id       = sanitize_key( (string) $id );
		$profiles = self::stored();
		if ( ! isset( $profiles[ $id ] ) ) {
			return new WP_Error( 'ako_ml_unknown_switcher', __( 'That switcher doesn’t exist.', 'ako-multilang' ), array( 'status' => 404 ) );
		}
		unset( $profiles[ $id ] );
		self::write( $profiles );
		return true;
	}

	/**
	 * Replaces every profile (settings import). Invalid entries are skipped;
	 * nothing changes if none is valid.
	 *
	 * @param array $raw_profiles Raw profiles.
	 * @return int Number of profiles saved.
	 */
	public static function replace_all( array $raw_profiles ) {
		$profiles = array();
		foreach ( array_slice( $raw_profiles, 0, self::MAX ) as $raw ) {
			$clean = is_array( $raw ) ? self::sanitize( $raw ) : null;
			if ( is_array( $clean ) ) {
				$profiles[ $clean['id'] ] = $clean;
			}
		}
		if ( $profiles ) {
			self::write( $profiles );
		}
		return count( $profiles );
	}

	/**
	 * A free ID based on a name ("Footer switcher" → "footer-switcher-2").
	 *
	 * @param string $name Name.
	 * @return string
	 */
	public static function unique_id( $name ) {
		$base = substr( sanitize_title( $name ), 0, 32 );
		$base = '' === $base ? 'switcher' : $base;
		$id   = $base;
		$n    = 2;
		while ( isset( self::all()[ $id ] ) ) {
			$id = $base . '-' . $n;
			++$n;
		}
		return $id;
	}

	/**
	 * Saved profiles only (without the implicit default).
	 *
	 * @return array<string,array>
	 */
	private static function stored() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) || ! isset( $stored['profiles'] ) || ! is_array( $stored['profiles'] ) ) {
			// Keep the implicit default once the first change is saved.
			return self::all();
		}
		$out = array();
		foreach ( $stored['profiles'] as $raw ) {
			$clean = is_array( $raw ) ? self::sanitize( $raw ) : null;
			if ( is_array( $clean ) ) {
				$out[ $clean['id'] ] = $clean;
			}
		}
		return $out;
	}

	/**
	 * Persists profiles. Autoloaded: the floating switcher is checked on
	 * every front-end request.
	 *
	 * @param array $profiles Profiles keyed by ID.
	 */
	private static function write( array $profiles ) {
		update_option( self::OPTION, array( 'profiles' => array_values( $profiles ) ), true );
		self::$cache = null;
	}

	/**
	 * Clears the per-request cache.
	 */
	public static function flush() {
		self::$cache = null;
	}
}
