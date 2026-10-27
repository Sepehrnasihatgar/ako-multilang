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
	const PREFERRED_LOCATIONS = array( 'primary', 'main', 'menu-1', 'menu_1', 'main_menu', 'header', 'primary-menu', 'main-menu', 'header-menu' );

	/**
	 * Words in a location's slug or name that mean it isn't the main menu.
	 */
	const SECONDARY_LOCATION = '/footer|mobile|social|top[ _-]?bar|topbar|secondary|legal|utility|off[ _-]?canvas|sidebar/i';

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
	 * The menu the switcher can join, or null: a classic menu location, or
	 * the header's Navigation block on block themes.
	 *
	 * @return array{type:string,location:string,name:string,menu:int,menuName:string}|null
	 */
	public static function menu_location() {
		if ( wp_is_block_theme() ) {
			return self::block_navigation();
		}

		$registered = get_registered_nav_menus();
		$assigned   = get_nav_menu_locations();
		if ( ! $registered || ! $assigned ) {
			return null;
		}

		// Known main-menu slugs first, then other locations, then footer,
		// mobile, social and top-bar menus last.
		$order     = array_unique( array_merge( array_intersect( self::PREFERRED_LOCATIONS, array_keys( $registered ) ), array_keys( $registered ) ) );
		$main      = array();
		$secondary = array();
		foreach ( $order as $location ) {
			if ( preg_match( self::SECONDARY_LOCATION, $location . ' ' . $registered[ $location ] ) ) {
				$secondary[] = $location;
			} else {
				$main[] = $location;
			}
		}
		foreach ( array_merge( $main, $secondary ) as $location ) {
			if ( empty( $assigned[ $location ] ) ) {
				continue;
			}
			$menu = wp_get_nav_menu_object( (int) $assigned[ $location ] );
			if ( $menu ) {
				return array(
					'type'     => 'classic',
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
	 * The header's Navigation block on a block theme. `menu` is the
	 * wp_navigation post it shows, or 0 when it shows WordPress's fallback
	 * (a menu is then created when the switcher is added).
	 *
	 * @return array{type:string,location:string,name:string,menu:int,menuName:string}|null
	 */
	private static function block_navigation() {
		$part = get_block_template( get_stylesheet() . '//header', 'wp_template_part' );
		if ( ! $part || ! is_string( $part->content ) ) {
			return null;
		}
		// Themes often keep the header in a pattern (Twenty Twenty-Five does).
		$blocks     = resolve_pattern_blocks( parse_blocks( $part->content ) );
		$navigation = self::find_block( $blocks, 'core/navigation' );
		if ( ! $navigation ) {
			return null;
		}

		$menu = 0;
		if ( ! empty( $navigation['attrs']['ref'] ) ) {
			$menu = (int) $navigation['attrs']['ref'];
		} else {
			// Without a ref, the block shows the most recent published menu.
			$latest = get_posts(
				array(
					'post_type'   => 'wp_navigation',
					'post_status' => 'publish',
					'numberposts' => 1,
					'orderby'     => 'date',
					'order'       => 'DESC',
					'fields'      => 'ids',
				)
			);
			$menu   = $latest ? (int) $latest[0] : 0;
		}
		$post = $menu ? get_post( $menu ) : null;
		if ( $menu && ( ! $post || 'wp_navigation' !== $post->post_type ) ) {
			return null;
		}

		return array(
			'type'     => 'block',
			'location' => 'header',
			'name'     => __( 'Header menu', 'cedar-multilang' ),
			'menu'     => $menu,
			'menuName' => $post ? (string) $post->post_title : __( 'Header', 'cedar-multilang' ),
		);
	}

	/**
	 * First block of a type in a parsed tree.
	 *
	 * @param array  $blocks Parsed blocks.
	 * @param string $name   Block name.
	 * @return array|null
	 */
	private static function find_block( array $blocks, $name ) {
		foreach ( $blocks as $block ) {
			if ( isset( $block['blockName'] ) && $name === $block['blockName'] ) {
				return $block;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = self::find_block( $block['innerBlocks'], $name );
				if ( $found ) {
					return $found;
				}
			}
		}
		return null;
	}

	/**
	 * Adds the switcher block to the end of a block theme's header menu.
	 *
	 * @param array  $location From block_navigation().
	 * @param string $id       Switcher ID.
	 * @return true|WP_Error
	 */
	private static function add_to_block_navigation( array $location, $id ) {
		$menu = (int) $location['menu'];
		if ( ! $menu ) {
			// Turn WordPress's fallback (the page list) into a saved menu first.
			if ( ! class_exists( 'WP_Navigation_Fallback' ) ) {
				return new WP_Error( 'cedar_ml_no_menu', __( 'Your theme has no menu to add the switcher to.', 'cedar-multilang' ), array( 'status' => 400 ) );
			}
			$fallback = \WP_Navigation_Fallback::get_fallback();
			if ( ! $fallback instanceof \WP_Post ) {
				return new WP_Error( 'cedar_ml_no_menu', __( 'Your theme has no menu to add the switcher to.', 'cedar-multilang' ), array( 'status' => 400 ) );
			}
			$menu = (int) $fallback->ID;
		}

		if ( ! current_user_can( 'edit_post', $menu ) ) {
			return new WP_Error( 'cedar_ml_menu_forbidden', __( 'You can’t edit menus on this site. Pick another placement.', 'cedar-multilang' ), array( 'status' => 403 ) );
		}

		$post = get_post( $menu );
		if ( false !== strpos( (string) $post->post_content, '<!-- wp:cedar-multilang/switcher' ) ) {
			return true;
		}

		$block  = serialize_block(
			array(
				'blockName'    => 'cedar-multilang/switcher',
				'attrs'        => array( 'switcher' => sanitize_key( $id ) ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
		$result = wp_update_post(
			array(
				'ID'           => $menu,
				'post_content' => rtrim( (string) $post->post_content ) . "\n\n" . $block,
			),
			true
		);
		return is_wp_error( $result ) ? $result : true;
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

		if ( 'block' === $location['type'] ) {
			$added = self::add_to_block_navigation( $location, $saved['id'] );
			if ( is_wp_error( $added ) ) {
				return $added;
			}
			return array(
				'placed' => true,
				'id'     => $saved['id'],
			);
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
		foreach ( get_posts(
			array(
				'post_type'   => 'wp_navigation',
				'post_status' => 'publish',
				'numberposts' => 50,
				's'           => 'wp:cedar-multilang/switcher',
			)
		) as $navigation ) {
			$count += substr_count( (string) $navigation->post_content, '<!-- wp:cedar-multilang/switcher' );
		}
		foreach ( Profiles::all() as $profile ) {
			if ( 'floating' === $profile['style'] ) {
				++$count;
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
