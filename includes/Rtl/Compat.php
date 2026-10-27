<?php
/**
 * Small RTL fixes for page builders and popular themes.
 *
 * @package Cedar\Multilang
 */

namespace Cedar\Multilang\Rtl;

use Cedar\Multilang\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Each integration is one small stylesheet in assets/rtl/, loaded only on
 * right-to-left pages, only when its builder or theme is in use and only
 * when it's switched on in Settings (on by default when detected).
 */
final class Compat {

	/**
	 * Known integrations: key => [ name, type (plugin|theme) ].
	 *
	 * @return array<string,array{name:string,type:string}>
	 */
	public static function known() {
		return array(
			'elementor'       => array(
				'name' => 'Elementor',
				'type' => 'plugin',
			),
			'hello-elementor' => array(
				'name' => 'Hello Elementor',
				'type' => 'theme',
			),
			'astra'           => array(
				'name' => 'Astra',
				'type' => 'theme',
			),
			'generatepress'   => array(
				'name' => 'GeneratePress',
				'type' => 'theme',
			),
			'kadence'         => array(
				'name' => 'Kadence',
				'type' => 'theme',
			),
		);
	}

	/**
	 * Hooks (only when the RTL module is on).
	 */
	public static function register() {
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ), 20 );
	}

	/**
	 * Whether an integration's plugin or theme is in use.
	 *
	 * @param string $key Integration key.
	 * @return bool
	 */
	public static function detected( $key ) {
		if ( 'elementor' === $key ) {
			return defined( 'ELEMENTOR_VERSION' );
		}
		$known = self::known();
		return isset( $known[ $key ] ) && 'theme' === $known[ $key ]['type'] && get_template() === $key;
	}

	/**
	 * Whether an integration is switched on: the saved choice, or "on" when
	 * detected and never changed.
	 *
	 * @param string $key Integration key.
	 * @return bool
	 */
	public static function enabled( $key ) {
		$saved = Settings::get()['rtl_compat'];
		return isset( $saved[ $key ] ) ? (bool) $saved[ $key ] : self::detected( $key );
	}

	/**
	 * Integrations for the Settings screen.
	 *
	 * @return array<int,array{key:string,name:string,type:string,detected:bool,enabled:bool}>
	 */
	public static function status() {
		$out = array();
		foreach ( self::known() as $key => $integration ) {
			// Only integrations whose fixes ship (and were tested) get a toggle.
			if ( ! is_readable( CEDAR_ML_PATH . 'assets/rtl/' . $key . '.css' ) ) {
				continue;
			}
			$out[] = array(
				'key'      => $key,
				'name'     => $integration['name'],
				'type'     => $integration['type'],
				'detected' => self::detected( $key ),
				'enabled'  => self::enabled( $key ),
			);
		}
		return $out;
	}

	/**
	 * Loads the fixes for the current right-to-left page.
	 */
	public static function enqueue() {
		if ( ! is_rtl() ) {
			return;
		}
		foreach ( array_keys( self::known() ) as $key ) {
			if ( ! self::detected( $key ) || ! self::enabled( $key ) ) {
				continue;
			}
			$file = 'assets/rtl/' . $key . '.css';
			if ( is_readable( CEDAR_ML_PATH . $file ) ) {
				wp_enqueue_style( 'cedar-ml-rtl-' . $key, CEDAR_ML_URL . $file, array(), CEDAR_ML_VERSION );
			}
		}
	}
}
