<?php
/**
 * REST: /cedar/v1/languages
 *
 * @package Cedar\Multilang
 */

namespace Cedar\Multilang\Rest;

use Cedar\Multilang\Content\Links;
use Cedar\Multilang\Content\Taxonomies;
use Cedar\Multilang\Languages\Catalog;
use Cedar\Multilang\Languages\Registry;
use WP_Error;
use WP_Query;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Lists, adds, edits, reorders and removes languages. Site owners only.
 */
final class LanguagesController {

	const CODE_ROUTE = '(?P<code>[a-z]{2,3}(?:-[a-z0-9]{2,4})?)';

	/**
	 * Registers the routes.
	 */
	public static function register_routes() {
		register_rest_route(
			Rest::NAMESPACE,
			'/languages',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'index' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( self::class, 'create' ),
					'permission_callback' => array( self::class, 'can_manage' ),
					'args'                => self::language_args( true ),
				),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/languages/order',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( self::class, 'reorder' ),
				'permission_callback' => array( self::class, 'can_manage' ),
				'args'                => array(
					'order' => array(
						'type'     => 'array',
						'required' => true,
						'maxItems' => 200,
						'items'    => array(
							'type'    => 'string',
							'pattern' => '^[a-z]{2,3}(-[a-z0-9]{2,4})?$',
						),
					),
				),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/languages/catalog',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'catalog' ),
				'permission_callback' => array( self::class, 'can_manage' ),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/languages/' . self::CODE_ROUTE,
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( self::class, 'update' ),
					'permission_callback' => array( self::class, 'can_manage' ),
					'args'                => self::language_args( false ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( self::class, 'delete' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
			)
		);
	}

	/**
	 * Languages are site-wide settings.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Request schema for one language.
	 *
	 * @param bool $creating Whether this is POST (locale required, code allowed).
	 * @return array
	 */
	private static function language_args( $creating ) {
		$args = array(
			'locale'   => array(
				'type'     => 'string',
				'required' => $creating,
				'pattern'  => '^[a-z]{2,3}(_[A-Z]{2})?(_[a-z0-9]+)?$',
			),
			'hreflang' => array(
				'type'      => 'string',
				'maxLength' => 12,
			),
			'name'     => array(
				'type'              => 'string',
				'maxLength'         => 64,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'native'   => array(
				'type'              => 'string',
				'maxLength'         => 64,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'dir'      => array(
				'type' => 'string',
				'enum' => array( 'ltr', 'rtl' ),
			),
		);

		if ( $creating ) {
			$args['code'] = array(
				'type'    => 'string',
				'pattern' => '^[a-z]{2,3}(-[a-z0-9]{2,4})?$',
			);
		}

		return $args;
	}

	/**
	 * GET /languages
	 *
	 * @return \WP_REST_Response
	 */
	public static function index() {
		return rest_ensure_response( self::payload() );
	}

	/**
	 * GET /languages/catalog
	 *
	 * @return \WP_REST_Response
	 */
	public static function catalog() {
		return rest_ensure_response( Catalog::all() );
	}

	/**
	 * POST /languages
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function create( WP_REST_Request $request ) {
		$locale = (string) $request['locale'];
		$known  = Catalog::get( $locale );
		$taken  = array_keys( Registry::all() );

		$language = array(
			'locale'   => $locale,
			'code'     => $request['code'] ? (string) $request['code'] : Catalog::code_for( $locale, $taken ),
			'hreflang' => $request['hreflang'] ? (string) $request['hreflang'] : ( $known ? $known['hreflang'] : Catalog::hreflang_for( $locale ) ),
			'name'     => $request['name'] ? (string) $request['name'] : ( $known ? $known['name'] : '' ),
			'native'   => $request['native'] ? (string) $request['native'] : ( $known ? $known['native'] : '' ),
			'dir'      => $request['dir'] ? (string) $request['dir'] : ( $known ? $known['dir'] : 'ltr' ),
		);

		if ( in_array( $language['code'], $taken, true ) ) {
			return new WP_Error( 'cedar_ml_duplicate_language', __( 'Each language can only be added once.', 'cedar-multilang' ), array( 'status' => 400 ) );
		}

		$result = Registry::add( $language );
		if ( is_wp_error( $result ) ) {
			return Rest::with_status( $result, 400 );
		}

		$response = rest_ensure_response( self::payload() );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * PUT /languages/{code}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function update( WP_REST_Request $request ) {
		$changes = array();
		foreach ( array( 'locale', 'hreflang', 'name', 'native', 'dir' ) as $key ) {
			if ( null !== $request[ $key ] ) {
				$changes[ $key ] = (string) $request[ $key ];
			}
		}

		$result = Registry::update( (string) $request['code'], $changes );
		if ( is_wp_error( $result ) ) {
			return Rest::with_status( $result, 400 );
		}
		return rest_ensure_response( self::payload() );
	}

	/**
	 * DELETE /languages/{code}
	 *
	 * Unlinks the language's posts first. Posts are never deleted.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function delete( WP_REST_Request $request ) {
		$code = (string) $request['code'];

		if ( ! Registry::exists( $code ) ) {
			return new WP_Error( 'cedar_ml_unknown_language', __( 'That language isn’t set up yet.', 'cedar-multilang' ), array( 'status' => 404 ) );
		}
		if ( Registry::default_code() === $code ) {
			return new WP_Error( 'cedar_ml_remove_default', __( 'The default language can’t be removed.', 'cedar-multilang' ), array( 'status' => 400 ) );
		}

		Links::detach_language( $code );

		$result = Registry::remove( $code );
		if ( is_wp_error( $result ) ) {
			return Rest::with_status( $result, 400 );
		}
		return rest_ensure_response( self::payload() );
	}

	/**
	 * PUT /languages/order
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function reorder( WP_REST_Request $request ) {
		$result = Registry::reorder( (array) $request['order'] );
		if ( is_wp_error( $result ) ) {
			return Rest::with_status( $result, 400 );
		}
		return rest_ensure_response( self::payload() );
	}

	/**
	 * Languages with their content counts.
	 *
	 * @return array
	 */
	public static function payload() {
		$default   = Registry::default_code();
		$languages = array();

		foreach ( Registry::all() as $code => $language ) {
			$language['count']   = self::count_content( $code, $code === $default );
			$language['default'] = ( $code === $default );
			$languages[]         = $language;
		}

		return array(
			'default'   => $default,
			'languages' => $languages,
		);
	}

	/**
	 * Number of posts (all enabled types) in a language. Content without a
	 * language counts toward the default language.
	 *
	 * @param string $code       Language code.
	 * @param bool   $is_default Whether this is the default language.
	 * @return int
	 */
	private static function count_content( $code, $is_default ) {
		$post_types = Taxonomies::post_types();
		if ( ! $post_types ) {
			return 0;
		}

		$tax_query = array(
			array(
				'taxonomy' => Taxonomies::LANGUAGE,
				'field'    => 'slug',
				'terms'    => array( $code ),
			),
		);
		if ( $is_default ) {
			$tax_query[]           = array(
				'taxonomy' => Taxonomies::LANGUAGE,
				'operator' => 'NOT EXISTS',
			);
			$tax_query['relation'] = 'OR';
		}

		$query = new WP_Query(
			array(
				'post_type'              => $post_types,
				'post_status'            => Rest::CONTENT_STATUSES,
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'tax_query'              => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Admin-only count on an indexed taxonomy.
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return (int) $query->found_posts;
	}
}
