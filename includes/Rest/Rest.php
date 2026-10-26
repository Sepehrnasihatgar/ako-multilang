<?php
/**
 * REST API bootstrap (namespace cedar/v1).
 *
 * @package Cedar\Multilang
 */

namespace Cedar\Multilang\Rest;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Registers every controller. Each route declares its own permission_callback
 * and argument schema; authentication is the standard cookie + X-WP-Nonce.
 */
final class Rest {

	const NAMESPACE = 'cedar/v1';

	/**
	 * Statuses shown in Cedar's lists (never trash or auto-draft).
	 */
	const CONTENT_STATUSES = array( 'publish', 'future', 'draft', 'pending', 'private' );

	/**
	 * Statuses that count as "not published yet".
	 */
	const DRAFT_STATUSES = array( 'future', 'draft', 'pending' );

	/**
	 * Hooks route registration.
	 */
	public static function register() {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/**
	 * Registers all routes.
	 */
	public static function register_routes() {
		LanguagesController::register_routes();
		LinksController::register_routes();
	}

	/**
	 * Adds an HTTP status to an error that has none.
	 *
	 * @param WP_Error $error  Error.
	 * @param int      $status HTTP status.
	 * @return WP_Error
	 */
	public static function with_status( WP_Error $error, $status ) {
		$data = $error->get_error_data();
		if ( ! is_array( $data ) || ! isset( $data['status'] ) ) {
			$error->add_data( array( 'status' => (int) $status ) );
		}
		return $error;
	}
}
