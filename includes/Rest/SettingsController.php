<?php
/**
 * REST: /akoml/v1/settings and /akoml/v1/system
 *
 * @package AkoMultilang
 */

namespace AkoMultilang\Rest;

use AkoMultilang\Content\Taxonomies;
use AkoMultilang\Import\Importer;
use AkoMultilang\Languages\Registry;
use AkoMultilang\Rtl\Compat;
use AkoMultilang\Seo\Health;
use AkoMultilang\Seo\Integrations;
use AkoMultilang\Settings;
use AkoMultilang\Setup\Wizard;
use AkoMultilang\Switcher\Profiles;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Settings, settings export/import and the About screen's system status.
 * Site owners only.
 */
final class SettingsController {

	/**
	 * Export file format version.
	 */
	const EXPORT_FORMAT = 1;

	/**
	 * Post types that never get a language.
	 */
	const EXCLUDED_TYPES = array( 'attachment', 'elementor_library' );

	/**
	 * Registers the routes.
	 */
	public static function register_routes() {
		$manage = array( self::class, 'can_manage' );

		register_rest_route(
			Rest::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'index' ),
					'permission_callback' => $manage,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( self::class, 'update' ),
					'permission_callback' => $manage,
					'args'                => array(
						'modules'             => array(
							'type'                 => 'object',
							'additionalProperties' => false,
							'properties'           => array(
								'seo'      => array( 'type' => 'boolean' ),
								'rtl'      => array( 'type' => 'boolean' ),
								'switcher' => array( 'type' => 'boolean' ),
								'import'   => array( 'type' => 'boolean' ),
							),
						),
						'post_types'          => array(
							'type'     => 'array',
							'maxItems' => 100,
							'items'    => array(
								'type'    => 'string',
								'pattern' => '^[a-z0-9_-]{1,20}$',
							),
						),
						'rtl_compat'          => array(
							'type'                 => 'object',
							'additionalProperties' => array( 'type' => 'boolean' ),
						),
						'delete_on_uninstall' => array( 'type' => 'boolean' ),
					),
				),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/settings/export',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'export' ),
				'permission_callback' => $manage,
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/settings/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'import' ),
				'permission_callback' => $manage,
				'args'                => array(
					'data' => array(
						'type'     => 'object',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/system',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'system' ),
				'permission_callback' => $manage,
			)
		);
	}

	/**
	 * Settings are site-wide.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * GET /settings
	 *
	 * @return \WP_REST_Response
	 */
	public static function index() {
		return rest_ensure_response( self::payload() );
	}

	/**
	 * PUT /settings
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function update( WP_REST_Request $request ) {
		$settings = Settings::get();

		if ( is_array( $request['modules'] ) ) {
			$settings['modules'] = array_merge( $settings['modules'], $request['modules'] );
		}
		if ( is_array( $request['post_types'] ) ) {
			$allowed = wp_list_pluck( self::post_types(), 'slug' );
			foreach ( $request['post_types'] as $post_type ) {
				if ( ! in_array( $post_type, $allowed, true ) ) {
					return new WP_Error( 'akoml_invalid_post_type', __( 'That content type isn’t available.', 'ako-multilang' ), array( 'status' => 400 ) );
				}
			}
			$settings['post_types'] = $request['post_types'];
		}
		if ( is_array( $request['rtl_compat'] ) ) {
			$settings['rtl_compat'] = array_merge( $settings['rtl_compat'], $request['rtl_compat'] );
		}
		if ( null !== $request['delete_on_uninstall'] ) {
			$settings['delete_on_uninstall'] = $request['delete_on_uninstall'];
		}

		Settings::update( $settings );
		return rest_ensure_response( self::payload() );
	}

	/**
	 * The Settings screen's data.
	 *
	 * @return array
	 */
	private static function payload() {
		$settings = Settings::get();
		$compat   = Compat::status();

		$rtl_compat = array();
		foreach ( $compat as $integration ) {
			$rtl_compat[ $integration['key'] ] = $integration['enabled'];
		}

		return array(
			'settings'     => array(
				'modules'             => $settings['modules'],
				'post_types'          => $settings['post_types'],
				'rtl_compat'          => $rtl_compat,
				'delete_on_uninstall' => $settings['delete_on_uninstall'],
			),
			'postTypes'    => self::post_types(),
			'integrations' => $compat,
			'counts'       => array(
				'linked'    => Health::linked_count(),
				'languages' => count( Registry::all() ),
			),
		);
	}

	/**
	 * Content types that can be linked across languages.
	 *
	 * @return array<int,array{slug:string,label:string,builtin:bool,plugin:string}>
	 */
	private static function post_types() {
		$out = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			if ( in_array( $type->name, self::EXCLUDED_TYPES, true ) || ! $type->show_ui ) {
				continue;
			}
			$out[] = array(
				'slug'    => $type->name,
				'label'   => $type->labels->name,
				'builtin' => (bool) $type->_builtin,
				'plugin'  => 'product' === $type->name && class_exists( 'WooCommerce' ) ? 'WooCommerce' : '',
			);
		}
		return $out;
	}

	/**
	 * GET /settings/export — languages, switchers and settings as one file.
	 * Links between pages stay out: they belong to this site's content.
	 *
	 * @return \WP_REST_Response
	 */
	public static function export() {
		return rest_ensure_response(
			array(
				'plugin'    => 'ako-multilang',
				'format'    => self::EXPORT_FORMAT,
				'version'   => AKOML_VERSION,
				'exported'  => gmdate( 'c' ),
				'settings'  => Settings::get(),
				'languages' => array(
					'default'   => Registry::default_code(),
					'languages' => array_values( Registry::all() ),
				),
				'switchers' => array_values( Profiles::all() ),
			)
		);
	}

	/**
	 * POST /settings/import — replaces settings, languages and switchers.
	 * Every value is validated again; links between pages are kept, and
	 * languages that pages already use are never removed.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function import( WP_REST_Request $request ) {
		$data = (array) $request['data'];
		if ( ! isset( $data['plugin'], $data['format'] ) || 'ako-multilang' !== $data['plugin'] || self::EXPORT_FORMAT !== (int) $data['format'] ) {
			return new WP_Error( 'akoml_invalid_import', __( 'This isn’t a Ako settings file.', 'ako-multilang' ), array( 'status' => 400 ) );
		}

		if ( isset( $data['languages']['languages'] ) && is_array( $data['languages']['languages'] ) ) {
			$result = self::import_languages( $data['languages'] );
			if ( is_wp_error( $result ) ) {
				return Rest::with_status( $result, 400 );
			}
		}

		if ( isset( $data['switchers'] ) && is_array( $data['switchers'] ) ) {
			Profiles::replace_all( $data['switchers'] );
		}

		if ( isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
			// "Delete data on uninstall" is only ever switched on by hand.
			$settings                        = Settings::sanitize( $data['settings'] );
			$settings['delete_on_uninstall'] = Settings::get()['delete_on_uninstall'];
			Settings::update( $settings );
		}

		return rest_ensure_response( self::payload() );
	}

	/**
	 * Languages from a file, keeping every language pages already use.
	 *
	 * @param array $imported { default, languages }.
	 * @return true|WP_Error
	 */
	private static function import_languages( array $imported ) {
		$list  = array();
		$codes = array();
		foreach ( array_slice( $imported['languages'], 0, 100 ) as $raw ) {
			$language = is_array( $raw ) ? Registry::sanitize_language( $raw ) : null;
			if ( is_array( $language ) && ! in_array( $language['code'], $codes, true ) ) {
				$list[]  = $language;
				$codes[] = $language['code'];
			}
		}
		if ( ! $list ) {
			return new WP_Error( 'akoml_invalid_import', __( 'The file has no valid languages.', 'ako-multilang' ) );
		}

		$tagged = Wizard::has_tagged_posts();
		foreach ( Registry::all() as $code => $language ) {
			if ( ! in_array( $code, $codes, true ) && $tagged && self::language_in_use( $code ) ) {
				$list[]  = $language;
				$codes[] = $code;
			}
		}

		$default_code = isset( $imported['default'] ) ? sanitize_key( (string) $imported['default'] ) : '';
		if ( ( $tagged && '' !== Registry::default_code() ) || ! in_array( $default_code, $codes, true ) ) {
			$default_code = in_array( Registry::default_code(), $codes, true ) ? Registry::default_code() : $codes[0];
		}

		return Registry::save( $list, $default_code );
	}

	/**
	 * Whether any post has this language.
	 *
	 * @param string $code Language code.
	 * @return bool
	 */
	private static function language_in_use( $code ) {
		$term = get_term_by( 'slug', $code, Taxonomies::LANGUAGE );
		return $term && $term->count > 0;
	}

	/**
	 * GET /system — the About screen.
	 *
	 * @return \WP_REST_Response
	 */
	public static function system() {
		return rest_ensure_response(
			array(
				'version' => AKOML_VERSION,
				'update'  => self::update_status(),
				'checks'  => self::checks(),
			)
		);
	}

	/**
	 * Update information WordPress already fetched (no request of our own).
	 *
	 * @return array{version:string,notice:string,url:string}|null
	 */
	private static function update_status() {
		$file    = plugin_basename( AKOML_FILE );
		$updates = get_site_transient( 'update_plugins' );
		if ( ! is_object( $updates ) || empty( $updates->response[ $file ] ) ) {
			return null;
		}
		$update = $updates->response[ $file ];
		if ( empty( $update->new_version ) || version_compare( $update->new_version, AKOML_VERSION, '<=' ) ) {
			return null;
		}

		return array(
			'version' => (string) $update->new_version,
			'notice'  => isset( $update->upgrade_notice ) ? wp_strip_all_tags( (string) $update->upgrade_notice ) : '',
			'url'     => current_user_can( 'update_plugins' )
				? wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( $file ) ), 'upgrade-plugin_' . $file )
				: '',
		);
	}

	/**
	 * System status rows. Status: ok, warning or none (informational).
	 *
	 * @return array<int,array{key:string,label:string,value:string,status:string,title?:string,message?:string}>
	 */
	private static function checks() {
		$checks = array(
			array(
				'key'    => 'wordpress',
				'label'  => __( 'WordPress', 'ako-multilang' ),
				'value'  => (string) get_bloginfo( 'version' ),
				'status' => 'ok',
			),
			self::php_check(),
			array(
				'key'    => 'theme',
				'label'  => __( 'Theme', 'ako-multilang' ),
				'value'  => (string) wp_get_theme()->get( 'Name' ),
				'status' => 'ok',
			),
		);

		$checks[] = defined( 'ELEMENTOR_VERSION' )
			? array(
				'key'    => 'builder',
				'label'  => __( 'Page builder', 'ako-multilang' ),
				'value'  => 'Elementor ' . self::short_version( ELEMENTOR_VERSION ),
				'status' => 'ok',
			)
			: array(
				'key'    => 'builder',
				'label'  => __( 'Page builder', 'ako-multilang' ),
				'value'  => __( 'Block editor', 'ako-multilang' ),
				'status' => 'ok',
			);

		$seo = null;
		foreach ( Integrations::status() as $plugin ) {
			if ( $plugin['active'] ) {
				$seo = $plugin;
				break;
			}
		}
		$checks[] = array(
			'key'    => 'seo',
			'label'  => __( 'SEO plugin', 'ako-multilang' ),
			'value'  => $seo ? $seo['name'] . ' ' . self::short_version( $seo['version'] ) : __( 'None. Ako adds hreflang itself.', 'ako-multilang' ),
			'status' => 'ok',
		);

		$cache    = self::cache_plugin();
		$checks[] = array(
			'key'    => 'cache',
			'label'  => __( 'Cache', 'ako-multilang' ),
			/* translators: %s: Cache plugin name. */
			'value'  => $cache ? sprintf( __( '%s · cache-safe', 'ako-multilang' ), $cache ) : __( 'No page cache', 'ako-multilang' ),
			'status' => $cache ? 'ok' : 'none',
		);

		$imports = get_option( Importer::STATUS_OPTION, array() );
		foreach ( Importer::sources() as $key => $source ) {
			if ( ! $source->is_active() ) {
				continue;
			}
			$imported = is_array( $imports ) && isset( $imports[ $key ] );
			$checks[] = $imported
				? array(
					'key'    => $key,
					'label'  => $source->name(),
					'value'  => __( 'Still active. You can deactivate it.', 'ako-multilang' ),
					'status' => 'none',
				)
				: array(
					'key'     => $key,
					'label'   => $source->name(),
					'value'   => __( 'Active. Import its links first.', 'ako-multilang' ),
					'status'  => 'warning',
					/* translators: %s: Plugin name, e.g. "WPML". */
					'title'   => sprintf( __( '%s is still active', 'ako-multilang' ), $source->name() ),
					'message' => sprintf(
						/* translators: %s: Plugin name, e.g. "WPML". */
						__( 'Both plugins add language tags while %s is active. Import its links from Translations, then deactivate it.', 'ako-multilang' ),
						$source->name()
					),
				);
		}

		return $checks;
	}

	/**
	 * PHP row, using the result of WordPress's own PHP check (the Dashboard
	 * fetches and caches it; Ako never makes the request itself).
	 *
	 * @return array
	 */
	private static function php_check() {
		$response = get_site_transient( 'php_check_' . md5( PHP_VERSION ) );
		$outdated = is_array( $response ) && ( empty( $response['is_secure'] ) || ! empty( $response['is_lower_than_future_minimum'] ) );
		$version  = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;

		$check = array(
			'key'    => 'php',
			'label'  => __( 'PHP', 'ako-multilang' ),
			'value'  => (string) PHP_VERSION,
			'status' => $outdated ? 'warning' : 'ok',
		);
		if ( $outdated ) {
			$recommended = ! empty( $response['recommended_version'] ) ? (string) $response['recommended_version'] : '';

			/* translators: %s: PHP version, e.g. "7.4". */
			$check['value'] = sprintf( __( '%s (update recommended)', 'ako-multilang' ), $version );
			/* translators: %s: PHP version, e.g. "7.4". */
			$check['title']   = sprintf( __( 'PHP %s no longer gets security updates', 'ako-multilang' ), $version );
			$check['message'] = '' === $recommended
				? __( 'Ako keeps working, but your site is safer on a newer PHP version. Ask your host. It’s usually one click.', 'ako-multilang' )
				/* translators: %s: Recommended PHP version, e.g. "8.3". */
				: sprintf( __( 'Ako keeps working, but your site is safer on PHP %s or newer. Ask your host. It’s usually one click.', 'ako-multilang' ), $recommended );
		}
		return $check;
	}

	/**
	 * Name of an active page cache plugin, or ''.
	 *
	 * @return string
	 */
	private static function cache_plugin() {
		$known = array(
			'WP Rocket'        => defined( 'WP_ROCKET_VERSION' ),
			'LiteSpeed Cache'  => defined( 'LSCWP_V' ),
			'W3 Total Cache'   => defined( 'W3TC_VERSION' ),
			'WP Super Cache'   => defined( 'WPCACHEHOME' ),
			'WP Fastest Cache' => class_exists( 'WpFastestCache' ),
			'WP-Optimize'      => defined( 'WPO_VERSION' ),
			'Cache Enabler'    => class_exists( 'Cache_Enabler' ),
		);
		foreach ( $known as $name => $active ) {
			if ( $active ) {
				return $name;
			}
		}
		return '';
	}

	/**
	 * "3.25.4" → "3.25".
	 *
	 * @param string $version Version.
	 * @return string
	 */
	private static function short_version( $version ) {
		$parts = explode( '.', (string) $version );
		return implode( '.', array_slice( $parts, 0, 2 ) );
	}
}
