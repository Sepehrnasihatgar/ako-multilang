<?php
/**
 * REST: /akoml/v1/switchers
 *
 * @package AkoMultilang
 */

namespace AkoMultilang\Rest;

use AkoMultilang\Switcher\Profiles;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Lists switchers for anyone who edits content (the block needs the list);
 * creating, changing and deleting them is for site owners.
 */
final class SwitchersController {

	const ID_ROUTE = '(?P<id>[a-z0-9_-]{1,40})';

	/**
	 * Registers the routes.
	 */
	public static function register_routes() {
		register_rest_route(
			Rest::NAMESPACE,
			'/switchers',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'index' ),
					'permission_callback' => array( self::class, 'can_read' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( self::class, 'create' ),
					'permission_callback' => array( self::class, 'can_manage' ),
					'args'                => array_merge(
						self::profile_args(),
						array(
							'name' => array(
								'type'              => 'string',
								'required'          => true,
								'minLength'         => 1,
								'maxLength'         => 60,
								'sanitize_callback' => 'sanitize_text_field',
							),
						)
					),
				),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/switchers/' . self::ID_ROUTE,
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( self::class, 'update' ),
					'permission_callback' => array( self::class, 'can_manage' ),
					'args'                => self::profile_args(),
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
	 * Editors can see the list (for the block).
	 *
	 * @return bool
	 */
	public static function can_read() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Switchers are site design: site owners only.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Request schema: every choice is an enum.
	 *
	 * @return array
	 */
	private static function profile_args() {
		$enum = static function ( $values ) {
			return array(
				'type' => 'string',
				'enum' => $values,
			);
		};

		return array(
			'name'     => array(
				'type'              => 'string',
				'maxLength'         => 60,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'style'    => $enum( Profiles::STYLES ),
			'show'     => $enum( Profiles::SHOW ),
			'mobile'   => $enum( Profiles::MOBILE ),
			'missing'  => $enum( Profiles::MISSING ),
			'styling'  => $enum( Profiles::STYLING ),
			'shape'    => $enum( Profiles::SHAPES ),
			'linkLook' => $enum( Profiles::LINK_LOOKS ),
			'icon'     => array( 'type' => 'boolean' ),
			'accent'   => array(
				'type'    => 'string',
				'pattern' => '^(#[0-9a-fA-F]{3}|#[0-9a-fA-F]{6})?$',
			),
		);
	}

	/**
	 * Only the documented fields, from a request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	private static function fields( WP_REST_Request $request ) {
		$out = array();
		foreach ( array_keys( self::profile_args() ) as $key ) {
			if ( null !== $request[ $key ] ) {
				$out[ $key ] = $request[ $key ];
			}
		}
		return $out;
	}

	/**
	 * GET /switchers
	 *
	 * @return \WP_REST_Response
	 */
	public static function index() {
		return rest_ensure_response( array( 'switchers' => array_values( Profiles::all() ) ) );
	}

	/**
	 * POST /switchers — a new switcher with a free ID based on its name.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function create( WP_REST_Request $request ) {
		$fields       = self::fields( $request );
		$fields['id'] = Profiles::unique_id( (string) $request['name'] );

		$saved = Profiles::save( $fields );
		if ( is_wp_error( $saved ) ) {
			return Rest::with_status( $saved, 400 );
		}

		$response = rest_ensure_response(
			array(
				'switcher'  => $saved,
				'switchers' => array_values( Profiles::all() ),
			)
		);
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * PUT /switchers/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function update( WP_REST_Request $request ) {
		$current = Profiles::get( (string) $request['id'] );
		if ( ! $current ) {
			return new WP_Error( 'akoml_unknown_switcher', __( 'That switcher doesn’t exist.', 'ako-multilang' ), array( 'status' => 404 ) );
		}

		$saved = Profiles::save( array_merge( $current, self::fields( $request ), array( 'id' => $current['id'] ) ) );
		if ( is_wp_error( $saved ) ) {
			return Rest::with_status( $saved, 400 );
		}
		return rest_ensure_response(
			array(
				'switcher'  => $saved,
				'switchers' => array_values( Profiles::all() ),
			)
		);
	}

	/**
	 * DELETE /switchers/{id} — the last switcher can't be deleted.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function delete( WP_REST_Request $request ) {
		if ( count( Profiles::all() ) < 2 ) {
			return new WP_Error( 'akoml_last_switcher', __( 'Keep at least one switcher.', 'ako-multilang' ), array( 'status' => 400 ) );
		}
		$result = Profiles::delete( (string) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'switchers' => array_values( Profiles::all() ) ) );
	}
}
