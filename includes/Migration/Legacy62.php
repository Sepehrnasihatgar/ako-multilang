<?php
/**
 * Moves data from the private Cedar 6.2 plugin to the v1 model.
 *
 * @package Cedar\Multilang
 */

namespace Cedar\Multilang\Migration;

use Cedar\Multilang\Content\Links;
use Cedar\Multilang\Content\Taxonomies;
use Cedar\Multilang\Languages\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Reads 6.2's page/post pairs (options `cedar_multilang_pairs`,
 * `cedar_multilang_post_pairs` and post meta `page_lang`) and links them.
 * Read-only on the old data: nothing from 6.2 is modified or deleted.
 */
final class Legacy62 {

	const STATUS_OPTION = 'cedar_ml_legacy62';

	/**
	 * Safety cap per run so a request never times out.
	 */
	const MAX_PAIRS_PER_RUN = 500;

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_run' ) );
	}

	/**
	 * Runs once, for an administrator, when 6.2 data exists.
	 */
	public static function maybe_run() {
		if ( ! current_user_can( 'manage_options' ) || false !== get_option( self::STATUS_OPTION, false ) ) {
			return;
		}

		$pairs = self::legacy_pairs();
		if ( ! $pairs ) {
			return;
		}

		self::ensure_languages();

		$linked = 0;
		foreach ( array_slice( $pairs, 0, self::MAX_PAIRS_PER_RUN ) as $pair ) {
			$en = absint( $pair['en'] );
			$fa = absint( $pair['fa'] );
			if ( ! $en || ! $fa || ! get_post( $en ) || ! get_post( $fa ) ) {
				continue;
			}
			if ( ! Taxonomies::supports( get_post_type( $en ) ) ) {
				continue;
			}
			if ( true === Links::set_language( $en, 'en' ) && true === Links::set_language( $fa, 'fa' ) && true === Links::link( $en, $fa ) ) {
				++$linked;
			}
		}

		add_option(
			self::STATUS_OPTION,
			array(
				'migrated_at' => time(),
				'pairs'       => $linked,
				'notice'      => 1,
			),
			'',
			false
		);
	}

	/**
	 * Valid pairs from both 6.2 options.
	 *
	 * @return array<int,array{en:int,fa:int}>
	 */
	private static function legacy_pairs() {
		$pairs = array();
		foreach ( array( 'cedar_multilang_pairs', 'cedar_multilang_post_pairs' ) as $option ) {
			$stored = get_option( $option, array() );
			if ( ! is_array( $stored ) ) {
				continue;
			}
			foreach ( $stored as $pair ) {
				if ( is_array( $pair ) && ! empty( $pair['en'] ) && ! empty( $pair['fa'] ) ) {
					$pairs[] = array(
						'en' => absint( $pair['en'] ),
						'fa' => absint( $pair['fa'] ),
					);
				}
			}
		}
		return $pairs;
	}

	/**
	 * Makes sure English and Farsi exist (6.2 only knew these two).
	 */
	private static function ensure_languages() {
		if ( ! Registry::exists( 'en' ) ) {
			Registry::add(
				array(
					'code'     => 'en',
					'locale'   => 'en_US',
					'hreflang' => 'en-US',
					'name'     => 'English',
					'native'   => 'English',
					'dir'      => 'ltr',
				)
			);
		}
		if ( ! Registry::exists( 'fa' ) ) {
			Registry::add(
				array(
					'code'     => 'fa',
					'locale'   => 'fa_IR',
					'hreflang' => 'fa-IR',
					'name'     => 'Farsi',
					'native'   => 'فارسی',
					'dir'      => 'rtl',
				)
			);
		}
	}
}
