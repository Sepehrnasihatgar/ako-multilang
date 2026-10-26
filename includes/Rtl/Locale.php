<?php
/**
 * Switches the WordPress locale to the language of the page being viewed.
 *
 * @package Cedar\Multilang
 */

namespace Cedar\Multilang\Rtl;

use Cedar\Multilang\Seo\Hreflang;

defined( 'ABSPATH' ) || exit;

/**
 * On singular front-end views, switches to the page language's locale so
 * that `is_rtl()`, the theme's rtl.css and translation files all match.
 */
final class Locale {

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'wp', array( __CLASS__, 'switch_locale' ), 1 );
	}

	/**
	 * Switches locale for the current request (front end only).
	 */
	public static function switch_locale() {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_feed() ) {
			return;
		}

		$language = Hreflang::current_language();
		if ( ! $language ) {
			return;
		}

		if ( determine_locale() !== $language['locale'] ) {
			switch_to_locale( $language['locale'] );
		}

		// The locale's language pack may not be installed; direction must still be right.
		global $wp_locale;
		if ( $wp_locale instanceof \WP_Locale ) {
			$wp_locale->text_direction = $language['dir'];
		}
	}
}
