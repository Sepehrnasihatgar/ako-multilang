<?php
/**
 * REST: /akoml/v1/seo
 *
 * @package AkoMultilang
 */

namespace AkoMultilang\Rest;

use AkoMultilang\Content\Links;
use AkoMultilang\Content\Overview;
use AkoMultilang\Content\Taxonomies;
use AkoMultilang\Languages\Catalog;
use AkoMultilang\Languages\Registry;
use AkoMultilang\Seo\Health;
use AkoMultilang\Seo\Hreflang;
use AkoMultilang\Seo\Integrations;
use AkoMultilang\Settings;
use WP_Error;
use WP_Query;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * SEO settings, the "what search engines see" preview and health checks.
 * Site owners only.
 */
final class SeoController {

	/**
	 * Registers the routes.
	 */
	public static function register_routes() {
		register_rest_route(
			Rest::NAMESPACE,
			'/seo',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'index' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( self::class, 'update' ),
					'permission_callback' => array( self::class, 'can_manage' ),
					'args'                => array(
						'x_default'    => array(
							'type'    => 'string',
							'pattern' => '^([a-z]{2,3}(-[a-z0-9]{2,4})?)?$',
						),
						'sitemap'      => array( 'type' => 'boolean' ),
						'og_locale'    => array( 'type' => 'boolean' ),
						'skip_noindex' => array( 'type' => 'boolean' ),
						'hreflang'     => array(
							'type'                 => 'object',
							'additionalProperties' => array(
								'type'    => 'string',
								'pattern' => '^[a-z]{2,3}(-[A-Za-z]{2,4})?$',
							),
						),
					),
				),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/seo/preview',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'preview' ),
				'permission_callback' => array( self::class, 'can_manage' ),
				'args'                => array(
					'post' => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					),
				),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/seo/scan',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'scan' ),
				'permission_callback' => array( self::class, 'can_manage' ),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/seo/fix',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'fix' ),
				'permission_callback' => array( self::class, 'can_manage' ),
				'args'                => array(
					'check' => array(
						'type'     => 'string',
						'enum'     => array( 'one-way' ),
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * SEO settings are site-wide.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * GET /seo
	 *
	 * @return \WP_REST_Response
	 */
	public static function index() {
		return rest_ensure_response( self::payload( Health::latest() ) );
	}

	/**
	 * PUT /seo — settings and per-language hreflang values.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function update( WP_REST_Request $request ) {
		$x_default = $request['x_default'];
		if ( null !== $x_default && '' !== $x_default && ! Registry::exists( $x_default ) ) {
			return new WP_Error( 'akoml_unknown_language', __( 'That language isn’t set up yet.', 'ako-multilang' ), array( 'status' => 400 ) );
		}

		if ( is_array( $request['hreflang'] ) ) {
			foreach ( $request['hreflang'] as $code => $value ) {
				if ( ! Registry::exists( $code ) ) {
					return new WP_Error( 'akoml_unknown_language', __( 'That language isn’t set up yet.', 'ako-multilang' ), array( 'status' => 400 ) );
				}
				$result = Registry::update( $code, array( 'hreflang' => (string) $value ) );
				if ( is_wp_error( $result ) ) {
					return Rest::with_status( $result, 400 );
				}
			}
		}

		$settings = Settings::get();
		foreach ( array( 'x_default', 'sitemap', 'og_locale', 'skip_noindex' ) as $key ) {
			if ( null !== $request[ $key ] ) {
				$settings[ $key ] = $request[ $key ];
			}
		}
		Settings::update( $settings );

		return rest_ensure_response( self::payload( Health::latest() ) );
	}

	/**
	 * GET /seo/preview — the tags a page prints, built by the same code that
	 * prints them on the front end.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function preview( WP_REST_Request $request ) {
		$post = get_post( (int) $request['post'] );
		if ( ! $post || ! Taxonomies::supports( $post->post_type ) ) {
			return new WP_Error( 'akoml_invalid_post', __( 'That page doesn’t exist.', 'ako-multilang' ), array( 'status' => 404 ) );
		}

		$code     = Links::get_language( $post->ID );
		$language = $code ? Registry::get( $code ) : Registry::get( Registry::default_code() );
		$cluster  = Hreflang::is_indexable( $post->ID ) ? Hreflang::cluster( $post->ID ) : array();

		$alternates = array();
		foreach ( Links::get_translations( $post->ID ) as $member_code => $member_id ) {
			$other = Registry::get( $member_code );
			if ( $member_code !== $code && $other && Hreflang::is_indexable( $member_id ) ) {
				$alternates[] = Integrations::og_locale( $other['locale'] );
			}
		}

		return rest_ensure_response(
			array(
				'path'       => (string) wp_make_link_relative( get_permalink( $post ) ),
				'lang'       => $language ? $language['hreflang'] : '',
				'dir'        => $language ? $language['dir'] : 'ltr',
				'links'      => $cluster,
				'ogLocale'   => $language ? Integrations::og_locale( $language['locale'] ) : '',
				'alternates' => $alternates,
				'indexable'  => Hreflang::is_indexable( $post->ID ),
				'members'    => count( Links::get_translations( $post->ID ) ),
			)
		);
	}

	/**
	 * POST /seo/scan
	 *
	 * @return \WP_REST_Response
	 */
	public static function scan() {
		return rest_ensure_response( self::payload( Health::scan() ) );
	}

	/**
	 * POST /seo/fix — repairs one-way links, then rescans.
	 *
	 * @return \WP_REST_Response
	 */
	public static function fix() {
		$fixed            = Health::repair_one_way();
		$payload          = self::payload( Health::scan() );
		$payload['fixed'] = $fixed;
		return rest_ensure_response( $payload );
	}

	/**
	 * Everything the SEO screen shows.
	 *
	 * @param array $health Health result.
	 * @return array
	 */
	private static function payload( array $health ) {
		$settings  = Settings::get();
		$languages = array();
		foreach ( Registry::all() as $code => $language ) {
			$languages[] = array(
				'code'     => $code,
				'name'     => $language['name'],
				'native'   => $language['native'],
				'hreflang' => $language['hreflang'],
				'options'  => self::hreflang_options( $language ),
				'default'  => Registry::default_code() === $code,
			);
		}

		return array(
			'settings'  => array(
				'x_default'    => $settings['x_default'],
				'sitemap'      => (bool) $settings['sitemap'],
				'og_locale'    => (bool) $settings['og_locale'],
				'skip_noindex' => (bool) $settings['skip_noindex'],
			),
			'languages' => $languages,
			'plugins'   => Integrations::status(),
			'sitemap'   => Integrations::sitemap_provider(),
			'health'    => $health,
			'pages'     => self::sample_pages(),
		);
	}

	/**
	 * Sensible hreflang choices for a language: plain, with its locale's
	 * region, and the current value.
	 *
	 * @param array $language Language.
	 * @return string[]
	 */
	private static function hreflang_options( array $language ) {
		$base    = explode( '-', $language['hreflang'] )[0];
		$options = array( $base, Catalog::hreflang_for( $language['locale'] ), $language['hreflang'] );
		return array_values( array_unique( $options ) );
	}

	/**
	 * Recently updated linked pages for the preview picker.
	 *
	 * @return array<int,array{id:int,title:string}>
	 */
	private static function sample_pages() {
		$query = new WP_Query(
			array(
				'post_type'              => Taxonomies::post_types(),
				'post_status'            => 'publish',
				'posts_per_page'         => 20,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Admin-only; indexed taxonomy lookup.
				'tax_query'              => array(
					array(
						'taxonomy' => Taxonomies::GROUP,
						'operator' => 'EXISTS',
					),
					array(
						'taxonomy' => Taxonomies::LANGUAGE,
						'field'    => 'slug',
						'terms'    => array( Registry::default_code() ),
					),
				),
			)
		);

		$out = array();
		foreach ( $query->posts as $post ) {
			$summary = Overview::post_summary( $post );
			$out[]   = array(
				'id'    => $summary['id'],
				'title' => $summary['title'],
			);
		}
		return $out;
	}
}
