<?php
/**
 * REST: /akoml/v1/setup and /akoml/v1/import
 *
 * @package AkoMultilang
 */

namespace AkoMultilang\Rest;

use AkoMultilang\Content\Links;
use AkoMultilang\Import\Importer;
use AkoMultilang\Setup\Matcher;
use AkoMultilang\Setup\Wizard;
use AkoMultilang\Switcher\Profiles;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * The setup screen and the WPML / Polylang importers. Site owners only.
 */
final class SetupController {

	/**
	 * Registers the routes.
	 */
	public static function register_routes() {
		$manage = array( self::class, 'can_manage' );

		register_rest_route(
			Rest::NAMESPACE,
			'/setup',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'state' ),
				'permission_callback' => $manage,
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/setup/languages',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( self::class, 'languages' ),
				'permission_callback' => $manage,
				'args'                => array(
					'default'   => array(
						'type'     => 'string',
						'required' => true,
						'pattern'  => '^[a-z]{2,3}(_[A-Z]{2})?(_[a-z0-9]+)?$',
					),
					'languages' => array(
						'type'     => 'array',
						'required' => true,
						'maxItems' => 100,
						'items'    => array(
							'type'    => 'string',
							'pattern' => '^[a-z]{2,3}(_[A-Z]{2})?(_[a-z0-9]+)?$',
						),
					),
				),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/setup/suggestions',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'suggestions' ),
				'permission_callback' => $manage,
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/setup/pairs',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'pairs' ),
				'permission_callback' => $manage,
				'args'                => array(
					'pairs' => array(
						'type'     => 'array',
						'required' => true,
						'minItems' => 1,
						'maxItems' => Matcher::MAX_SUGGESTIONS,
						'items'    => array(
							'type'                 => 'object',
							'additionalProperties' => false,
							'properties'           => array(
								'source'   => array(
									'type'     => 'integer',
									'minimum'  => 1,
									'required' => true,
								),
								'target'   => array(
									'type'     => 'integer',
									'minimum'  => 1,
									'required' => true,
								),
								'language' => array(
									'type'     => 'string',
									'pattern'  => '^[a-z]{2,3}(-[a-z0-9]{2,4})?$',
									'required' => true,
								),
							),
						),
					),
				),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/setup/switcher',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'switcher' ),
				'permission_callback' => $manage,
				'args'                => array(
					'look'      => array(
						'type'     => 'string',
						'enum'     => Wizard::LOOKS,
						'required' => true,
					),
					'placement' => array(
						'type'     => 'string',
						'enum'     => Wizard::PLACEMENTS,
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/setup/summary',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'summary' ),
				'permission_callback' => $manage,
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/setup/complete',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'complete' ),
				'permission_callback' => $manage,
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/import',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'sources' ),
				'permission_callback' => $manage,
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/import/(?P<source>wpml|polylang)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'import' ),
				'permission_callback' => $manage,
				'args'                => array(
					'offset' => array(
						'type'    => 'integer',
						'minimum' => 0,
						'default' => 0,
					),
					'limit'  => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => Importer::MAX_BATCH,
						'default' => 25,
					),
				),
			)
		);
	}

	/**
	 * Setup changes site-wide settings.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * GET /setup — everything the steps need up front.
	 *
	 * @return \WP_REST_Response
	 */
	public static function state() {
		$menu    = Wizard::menu_location();
		$profile = Profiles::first();

		return rest_ensure_response(
			array(
				'languages'     => LanguagesController::payload(),
				'siteLocale'    => get_locale(),
				'suggested'     => Wizard::suggested_languages(),
				'defaultLocked' => Wizard::has_tagged_posts(),
				'sources'       => Importer::summary(),
				'menu'          => $menu ? array(
					'name'     => $menu['name'],
					'menuName' => $menu['menuName'],
				) : null,
				'canEditMenus'  => current_user_can( 'edit_theme_options' ),
				'switcher'      => array(
					'id'    => $profile['id'],
					'style' => $profile['style'],
				),
				'needsSetup'    => Wizard::needs_setup(),
			)
		);
	}

	/**
	 * PUT /setup/languages
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function languages( WP_REST_Request $request ) {
		$result = Wizard::set_languages( (string) $request['default'], (array) $request['languages'] );
		if ( is_wp_error( $result ) ) {
			return Rest::with_status( $result, 400 );
		}
		return rest_ensure_response( LanguagesController::payload() );
	}

	/**
	 * GET /setup/suggestions
	 *
	 * @return \WP_REST_Response
	 */
	public static function suggestions() {
		return rest_ensure_response( Matcher::suggest() );
	}

	/**
	 * POST /setup/pairs — links the pairs the site owner kept checked.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function pairs( WP_REST_Request $request ) {
		$linked = 0;
		$errors = array();
		foreach ( (array) $request['pairs'] as $pair ) {
			$source = (int) $pair['source'];
			$target = (int) $pair['target'];
			if ( ! current_user_can( 'edit_post', $source ) || ! current_user_can( 'edit_post', $target ) ) {
				$errors[] = array(
					'source'  => $source,
					'target'  => $target,
					'message' => __( 'You can’t edit one of these pages.', 'ako-multilang' ),
				);
				continue;
			}
			$result = Links::link_as( $source, $target, (string) $pair['language'] );
			if ( is_wp_error( $result ) ) {
				$errors[] = array(
					'source'  => $source,
					'target'  => $target,
					'message' => $result->get_error_message(),
				);
				continue;
			}
			++$linked;
		}

		return rest_ensure_response(
			array(
				'linked' => $linked,
				'errors' => $errors,
			)
		);
	}

	/**
	 * POST /setup/switcher
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function switcher( WP_REST_Request $request ) {
		$result = Wizard::place_switcher( (string) $request['look'], (string) $request['placement'] );
		if ( is_wp_error( $result ) ) {
			return Rest::with_status( $result, 400 );
		}
		return rest_ensure_response( $result );
	}

	/**
	 * GET /setup/summary
	 *
	 * @return \WP_REST_Response
	 */
	public static function summary() {
		return rest_ensure_response( Wizard::summary() );
	}

	/**
	 * POST /setup/complete
	 *
	 * @return \WP_REST_Response
	 */
	public static function complete() {
		Wizard::complete();
		return rest_ensure_response( array( 'needsSetup' => false ) );
	}

	/**
	 * GET /import — sources with data.
	 *
	 * @return \WP_REST_Response
	 */
	public static function sources() {
		return rest_ensure_response( Importer::summary() );
	}

	/**
	 * POST /import/{source} — one batch.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function import( WP_REST_Request $request ) {
		$source = Importer::source( (string) $request['source'] );
		if ( ! $source || ! $source->has_data() ) {
			return new WP_Error( 'akoml_import_unavailable', __( 'There’s nothing to import from this plugin.', 'ako-multilang' ), array( 'status' => 404 ) );
		}
		$result = Importer::run( $source, (int) $request['offset'], (int) $request['limit'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}
}
