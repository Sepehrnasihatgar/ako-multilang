<?php
/**
 * Which languages a switcher shows on the current page, and where each goes.
 *
 * @package AkoMultilang
 */

namespace AkoMultilang\Switcher;

use AkoMultilang\Content\Links;
use AkoMultilang\Content\Taxonomies;
use AkoMultilang\Languages\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the list of switcher items for the current request. Only published
 * translations are linked; visitors never land on a draft or a 404.
 */
final class Items {

	/**
	 * Items for the current request.
	 *
	 * @param array $profile Switcher profile (uses `missing`).
	 * @return array<int,array{code:string,hreflang:string,name:string,native:string,dir:string,url:string,current:bool,available:bool}>
	 */
	public static function for_request( array $profile ) {
		$languages = Registry::all();
		if ( count( $languages ) < 2 ) {
			return array();
		}

		$default      = Registry::default_code();
		$current      = $default;
		$current_url  = home_url( '/' );
		$translations = array();

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof \WP_Post && Taxonomies::supports( $post->post_type ) ) {
				$language     = Links::get_language( $post->ID );
				$current      = $language ? $language : $default;
				$current_url  = (string) get_permalink( $post );
				$translations = Links::get_translations( $post->ID );
			}
		}

		$items = array();
		foreach ( $languages as $code => $language ) {
			$item = array(
				'code'      => $code,
				'hreflang'  => $language['hreflang'],
				'name'      => $language['name'],
				'native'    => $language['native'],
				'dir'       => $language['dir'],
				'url'       => '',
				'current'   => $code === $current,
				'available' => true,
			);

			if ( $item['current'] ) {
				$item['url'] = $current_url;
			} elseif ( isset( $translations[ $code ] ) && 'publish' === get_post_status( $translations[ $code ] ) ) {
				$item['url'] = (string) get_permalink( $translations[ $code ] );
			} elseif ( 'hide' === $profile['missing'] ) {
				continue;
			} elseif ( 'disabled' === $profile['missing'] ) {
				$item['available'] = false;
			} else {
				$item['url'] = self::language_home( $code );
			}

			$items[] = $item;
		}

		/**
		 * Filters the switcher items before rendering.
		 *
		 * @param array $items   Items.
		 * @param array $profile Switcher profile.
		 */
		return (array) apply_filters( 'akoml_switcher_items', $items, $profile );
	}

	/**
	 * A language's home: the published translation of the static front page,
	 * or the site home.
	 *
	 * @param string $code Language code.
	 * @return string
	 */
	public static function language_home( $code ) {
		$front = 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;
		if ( $front ) {
			$translations = Links::get_translations( $front );
			if ( isset( $translations[ $code ] ) && 'publish' === get_post_status( $translations[ $code ] ) ) {
				return (string) get_permalink( $translations[ $code ] );
			}
		}
		return home_url( '/' );
	}
}
