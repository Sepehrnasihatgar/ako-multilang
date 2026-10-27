<?php
/**
 * Setup steps: languages, switcher placement and the final summary.
 *
 * @package Cedar\Multilang
 */

namespace Cedar\Multilang\Setup;

use Cedar\Multilang\Content\Links;
use Cedar\Multilang\Content\Taxonomies;
use Cedar\Multilang\Languages\Catalog;
use Cedar\Multilang\Languages\Registry;
use Cedar\Multilang\Seo\Health;
use Cedar\Multilang\Seo\Hreflang;
use Cedar\Multilang\Switcher\Profiles;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Everything the setup screen changes goes through the same APIs as the
 * rest of Cedar; the wizard only adds a guided order.
 */
final class Wizard {

	/**
	 * Option set on first activation, removed when setup is finished.
	 */
	const NEEDS_SETUP_OPTION = 'cedar_ml_needs_setup';

	/**
	 * Placements offered in setup.
	 */
	const PLACEMENTS = array( 'menu', 'floating', 'manual' );

	/**
	 * Looks offered in setup.
	 */
	const LOOKS = array( 'link', 'segmented', 'dropdown', 'floating' );

	/**
	 * Menu location names Cedar prefers, in order.
	 */
	const PREFERRED_LOCATIONS = array( 'primary', 'main', 'menu-1', 'header', 'primary-menu', 'main-menu', 'top' );

	/**
	 * Whether setup hasn't been finished.
	 *
	 * @return bool
	 */
	public static function needs_setup() {
		return (bool) get_option( self::NEEDS_SETUP_OPTION, false );
	}

	/**
	 * Marks setup as finished.
	 */
	public static function complete() {
		delete_option( self::NEEDS_SETUP_OPTION );
	}

	/**
	 * Whether any post has a language yet (then the default can't change here).
	 *
	 * @return bool
	 */
	public static function has_tagged_posts() {
		$count = wp_count_terms(
			array(
				'taxonomy'   => Taxonomies::LANGUAGE,
				'hide_empty' => true,
			)
		);
		return ! is_wp_error( $count ) && (int) $count > 0;
	}

	/**
	 * Replaces the language list from catalog locales.
	 *
	 * @param string   $default_locale Default language locale.
	 * @param string[] $locales        Other languages' locales, in order.
	 * @return true|WP_Error
	 */
	public static function set_languages( $default_locale, array $locales ) {
		$current   = Registry::all();
		$by_locale = array();
		foreach ( $current as $language ) {
			$by_locale[ $language['locale'] ] = $language;
		}

		$locales = array_values( array_unique( array_merge( array( (string) $default_locale ), array_map( 'strval', $locales ) ) ) );
		$list    = array();
		$taken   = array();
		foreach ( $locales as $locale ) {
			if ( isset( $by_locale[ $locale ] ) ) {
				$language = $by_locale[ $locale ];
			} else {
				$known = Catalog::get( $locale );
				if ( ! $known ) {
					return new WP_Error( 'cedar_ml_unknown_locale', __( 'Pick languages from the list.', 'cedar-multilang' ), array( 'status' => 400 ) );
				}
				$code = Catalog::code_for( $locale, array_merge( $taken, array_keys( $current ) ) );
				if ( isset( $current[ $code ] ) ) {
					return new WP_Error( 'cedar_ml_duplicate_language', __( 'Each language can only be added once.', 'cedar-multilang' ), array( 'status' => 400 ) );
				}
				$language = array_merge( $known, array( 'code' => $code ) );
			}
			$taken[] = $language['code'];
			$list[]  = $language;
		}

		$default_code = $list[0]['code'];
		if ( '' !== Registry::default_code() && Registry::default_code() !== $default_code && self::has_tagged_posts() ) {
			return new WP_Error( 'cedar_ml_default_locked', __( 'Your pages already have languages, so the default can’t change here. Pages without a language would switch to the new default.', 'cedar-multilang' ), array( 'status' => 409 ) );
		}

		foreach ( array_keys( $current ) as $code ) {
			if ( ! in_array( $code, $taken, true ) ) {
				Links::detach_language( $code );
			}
		}

		return Registry::save( $list, $default_code );
	}

	/**
	 * Writing systems that point to one language, checked in order: the
	 * first match wins. Letters only Urdu, Farsi or Arabic use come first;
	 * other Arabic-script titles are settled in suggested_languages().
	 */
	const SCRIPTS = array(
		'ur'     => '/[\x{0679}\x{0688}\x{0691}\x{06BA}\x{06BE}\x{06D2}]/u',
		'fa_IR'  => '/[\x{067E}\x{0686}\x{0698}\x{06AF}\x{06A9}\x{06CC}]/u',
		'ar'     => '/[\x{0629}\x{0643}\x{0649}\x{064A}]/u',
		'arabic' => '/[\x{0600}-\x{06FF}]/u',
		'he_IL'  => '/[\x{0590}-\x{05FF}]/u',
		'el'     => '/[\x{0370}-\x{03FF}]/u',
		'th'     => '/[\x{0E00}-\x{0E7F}]/u',
		'hi_IN'  => '/[\x{0900}-\x{097F}]/u',
		'ja'     => '/[\x{3040}-\x{30FF}]/u',
		'ko_KR'  => '/[\x{AC00}-\x{D7AF}]/u',
		'zh_CN'  => '/[\x{4E00}-\x{9FFF}]/u',
	);

	/**
	 * Site locales written in Arabic script.
	 */
	const ARABIC_SCRIPT = array( 'ar', 'fa_IR', 'fa_AF', 'ur', 'ckb', 'ps', 'ug_CN', 'azb', 'skr', 'haz' );

	/**
	 * How many recent titles to read when looking for languages.
	 */
	const SCAN_LIMIT = 300;

	/**
	 * Languages the site seems to use besides its own, strongest first.
	 *
	 * - "content": titles written in another script (two or more pages).
	 * - "folder": a top-level page named after a language with pages under it, like /fa/.
	 * - "installed": a WordPress language pack installed on the site.
	 *
	 * Content and folder matches are likely enough to pre-select; installed
	 * packs are only offered.
	 *
	 * @return array<int,array{locale:string,reason:string,count:int}>
	 */
	public static function suggested_languages() {
		$site  = get_locale();
		$found = array();
		$add   = function ( $locale, $reason, $count ) use ( &$found, $site ) {
			if ( $locale === $site || ! Catalog::get( $locale ) || isset( $found[ $locale ] ) ) {
				return;
			}
			$found[ $locale ] = array(
				'locale' => $locale,
				'reason' => $reason,
				'count'  => (int) $count,
			);
		};

		$posts = get_posts(
			array(
				'post_type'              => Taxonomies::post_types(),
				'post_status'            => array( 'publish', 'draft', 'future', 'private' ),
				'numberposts'            => self::SCAN_LIMIT,
				'orderby'                => 'ID',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$counts = array();
		foreach ( $posts as $post ) {
			foreach ( self::SCRIPTS as $locale => $pattern ) {
				if ( preg_match( $pattern, $post->post_title ) ) {
					$counts[ $locale ] = isset( $counts[ $locale ] ) ? $counts[ $locale ] + 1 : 1;
					break;
				}
			}
		}
		// Titles in Arabic script without a telling letter ("خانه") go with the
		// Arabic-script language seen most; Farsi when none is clear.
		if ( isset( $counts['arabic'] ) ) {
			if ( ! in_array( $site, self::ARABIC_SCRIPT, true ) ) {
				$ar               = isset( $counts['ar'] ) ? $counts['ar'] : 0;
				$fa               = isset( $counts['fa_IR'] ) ? $counts['fa_IR'] : 0;
				$ur               = isset( $counts['ur'] ) ? $counts['ur'] : 0;
				$guess            = ( $ar > $fa && $ar >= $ur ) ? 'ar' : ( ( $ur > $fa ) ? 'ur' : 'fa_IR' );
				$counts[ $guess ] = ( isset( $counts[ $guess ] ) ? $counts[ $guess ] : 0 ) + $counts['arabic'];
			}
			unset( $counts['arabic'] );
		}
		arsort( $counts );
		foreach ( $counts as $locale => $count ) {
			if ( $count > 1 ) {
				$add( $locale, 'content', $count );
			}
		}

		$parents = array();
		foreach ( $posts as $post ) {
			if ( 'page' === $post->post_type && $post->post_parent ) {
				$parents[ $post->post_parent ] = isset( $parents[ $post->post_parent ] ) ? $parents[ $post->post_parent ] + 1 : 1;
			}
		}
		foreach ( $parents as $parent_id => $children ) {
			$parent = get_post( $parent_id );
			if ( $parent && 0 === (int) $parent->post_parent && preg_match( '/^[a-z]{2,3}$/', $parent->post_name ) ) {
				$locale = self::locale_for_code( $parent->post_name );
				if ( $locale ) {
					$add( $locale, 'folder', $children );
				}
			}
		}

		foreach ( get_available_languages() as $locale ) {
			$add( $locale, 'installed', 0 );
		}

		return array_values( $found );
	}

	/**
	 * The catalog locale for a short code: "de" → "de_DE", "fa" → "fa_IR".
	 *
	 * @param string $code Two- or three-letter code.
	 * @return string Locale, or '' when the catalog has none.
	 */
	public static function locale_for_code( $code ) {
		$code  = strtolower( (string) $code );
		$match = '';
		foreach ( array_keys( Catalog::LOCALES ) as $locale ) {
			$parts = explode( '_', $locale );
			if ( strtolower( $parts[0] ) !== $code ) {
				continue;
			}
			if ( $locale === $code || $locale === $code . '_' . strtoupper( $code ) ) {
				return $locale;
			}
			if ( '' === $match && 2 === count( $parts ) ) {
				$match = $locale;
			}
		}
		return $match;
	}

	/**
	 * The classic menu the switcher can join, or null.
	 *
	 * @return array{location:string,name:string,menu:int,menuName:string}|null
	 */
	public static function menu_location() {
		$registered = get_registered_nav_menus();
		$assigned   = get_nav_menu_locations();
		if ( ! $registered || ! $assigned ) {
			return null;
		}

		$order = array_unique( array_merge( array_intersect( self::PREFERRED_LOCATIONS, array_keys( $registered ) ), array_keys( $registered ) ) );
		foreach ( $order as $location ) {
			if ( empty( $assigned[ $location ] ) ) {
				continue;
			}
			$menu = wp_get_nav_menu_object( (int) $assigned[ $location ] );
			if ( $menu ) {
				return array(
					'location' => (string) $location,
					'name'     => (string) $registered[ $location ],
					'menu'     => (int) $menu->term_id,
					'menuName' => (string) $menu->name,
				);
			}
		}
		return null;
	}

	/**
	 * Applies the chosen look and placement to the first switcher.
	 *
	 * @param string $look      One of LOOKS.
	 * @param string $placement One of PLACEMENTS.
	 * @return array{placed:bool,id:string}|WP_Error
	 */
	public static function place_switcher( $look, $placement ) {
		$profile          = Profiles::first();
		$profile['style'] = 'floating' === $placement ? 'floating' : $look;
		if ( 'floating' === $profile['style'] && 'floating' !== $placement ) {
			$profile['style'] = 'dropdown';
		}
		// Match the look the setup tiles preview.
		if ( 'segmented' === $profile['style'] ) {
			$profile['show'] = 'code';
			$profile['icon'] = false;
		} elseif ( 'link' === $profile['style'] ) {
			$profile['show'] = 'native';
			$profile['icon'] = true;
		} else {
			$profile['show'] = 'code';
			$profile['icon'] = true;
		}

		$saved = Profiles::save( $profile );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		if ( 'menu' !== $placement ) {
			return array(
				'placed' => 'floating' === $placement,
				'id'     => $saved['id'],
			);
		}

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error( 'cedar_ml_menu_forbidden', __( 'You can’t edit menus on this site. Pick another placement.', 'cedar-multilang' ), array( 'status' => 403 ) );
		}
		$location = self::menu_location();
		if ( ! $location ) {
			return new WP_Error( 'cedar_ml_no_menu', __( 'Your theme has no menu to add the switcher to.', 'cedar-multilang' ), array( 'status' => 400 ) );
		}

		$url = '#cedar-switcher-' . $saved['id'];
		foreach ( (array) wp_get_nav_menu_items( $location['menu'] ) as $item ) {
			if ( isset( $item->url ) && $url === $item->url ) {
				return array(
					'placed' => true,
					'id'     => $saved['id'],
				);
			}
		}

		$item_id = wp_update_nav_menu_item(
			$location['menu'],
			0,
			array(
				'menu-item-title'  => __( 'Language switcher', 'cedar-multilang' ),
				'menu-item-url'    => $url,
				'menu-item-type'   => 'custom',
				'menu-item-status' => 'publish',
			)
		);
		if ( is_wp_error( $item_id ) ) {
			return $item_id;
		}

		return array(
			'placed' => true,
			'id'     => $saved['id'],
		);
	}

	/**
	 * Numbers and next steps for the last step.
	 *
	 * @return array
	 */
	public static function summary() {
		$health = Health::scan();

		$hreflang = 0;
		foreach ( self::group_ids() as $group_id ) {
			$indexable = 0;
			foreach ( Health::actual_members( $group_id ) as $post_id ) {
				if ( Hreflang::is_indexable( $post_id ) ) {
					++$indexable;
				}
			}
			if ( $indexable > 1 ) {
				$hreflang += $indexable * $indexable;
			}
		}

		$missing = null;
		foreach ( $health['missing'] as $entry ) {
			if ( null === $missing || $entry['count'] > $missing['count'] ) {
				$language = Registry::get( $entry['code'] );
				$missing  = array(
					'code'  => $entry['code'],
					'name'  => $language ? $language['name'] : $entry['code'],
					'count' => $entry['count'],
				);
			}
		}

		return array(
			'languages' => count( Registry::all() ),
			'linked'    => $health['linked'],
			'hreflang'  => $hreflang,
			'switchers' => self::placed_switchers(),
			'missing'   => $missing,
			'oneWay'    => $health['oneWayAll'],
		);
	}

	/**
	 * Switchers Cedar can see on the site: menu items plus a floating button.
	 *
	 * @return int
	 */
	private static function placed_switchers() {
		$count = 0;
		foreach ( Profiles::all() as $profile ) {
			if ( 'floating' === $profile['style'] ) {
				$count = 1;
				break;
			}
		}

		foreach ( wp_get_nav_menus() as $menu ) {
			foreach ( (array) wp_get_nav_menu_items( $menu->term_id ) as $item ) {
				if ( isset( $item->url ) && 0 === strpos( (string) $item->url, '#cedar-switcher-' ) ) {
					++$count;
				}
			}
		}
		return $count;
	}

	/**
	 * Every group term ID.
	 *
	 * @return int[]
	 */
	private static function group_ids() {
		$ids = get_terms(
			array(
				'taxonomy'   => Taxonomies::GROUP,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		return is_wp_error( $ids ) ? array() : array_map( 'intval', $ids );
	}
}
