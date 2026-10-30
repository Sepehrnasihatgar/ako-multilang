<?php
/**
 * REST: /akoml/v1/links
 *
 * @package AkoMultilang
 */

namespace AkoMultilang\Rest;

use AkoMultilang\Content\Duplicate;
use AkoMultilang\Content\Links;
use AkoMultilang\Content\Overview;
use AkoMultilang\Content\Taxonomies;
use AkoMultilang\Languages\Registry;
use WP_Error;
use WP_Query;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Overview, link, unlink and link candidates. Every write checks `edit_post`
 * on each post it touches.
 */
final class LinksController {

	/**
	 * Registers the routes.
	 */
	public static function register_routes() {
		$post_type_arg = array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => array( self::class, 'resolve_post_type' ),
			'validate_callback' => array( self::class, 'validate_post_type' ),
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/links',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'index' ),
				'permission_callback' => array( self::class, 'can_list' ),
				'args'                => array(
					'post_type' => $post_type_arg,
					'filter'    => array(
						'type'    => 'string',
						'enum'    => Overview::FILTERS,
						'default' => 'all',
					),
					'search'    => array(
						'type'              => 'string',
						'default'           => '',
						'maxLength'         => 200,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'page'      => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
					'per_page'  => array(
						'type'    => 'integer',
						'default' => 20,
						'minimum' => 1,
						'maximum' => 100,
					),
				),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/links/candidates',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'candidates' ),
				'permission_callback' => array( self::class, 'can_list' ),
				'args'                => array(
					'post_type' => $post_type_arg,
					'language'  => array(
						'type'              => 'string',
						'required'          => true,
						'validate_callback' => array( self::class, 'validate_language' ),
					),
					'search'    => array(
						'type'              => 'string',
						'default'           => '',
						'maxLength'         => 200,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'skip'      => array(
						'type'    => 'integer',
						'default' => 0,
						'minimum' => 0,
					),
				),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/links/(?P<id>\d+)/language',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( self::class, 'set_language' ),
				'permission_callback' => array( self::class, 'can_edit' ),
				'args'                => array(
					'language' => array(
						'type'              => 'string',
						'required'          => true,
						'validate_callback' => array( self::class, 'validate_language_or_empty' ),
					),
				),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/links/(?P<id>\d+)/duplicate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'duplicate' ),
				'permission_callback' => array( self::class, 'can_duplicate' ),
				'args'                => array(
					'language' => array(
						'type'              => 'string',
						'required'          => true,
						'validate_callback' => array( self::class, 'validate_language' ),
					),
				),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/links/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'show' ),
					'permission_callback' => array( self::class, 'can_edit' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( self::class, 'link' ),
					'permission_callback' => array( self::class, 'can_link' ),
					'args'                => array(
						'target'   => array(
							'type'     => 'integer',
							'required' => true,
							'minimum'  => 1,
						),
						'language' => array(
							'type'              => 'string',
							'required'          => true,
							'validate_callback' => array( self::class, 'validate_language' ),
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( self::class, 'unlink' ),
					'permission_callback' => array( self::class, 'can_unlink' ),
					'args'                => array(
						'scope' => array(
							'type'    => 'string',
							'enum'    => array( 'post', 'group' ),
							'default' => 'post',
						),
					),
				),
			)
		);
	}

	/**
	 * Empty means "the first enabled post type".
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function resolve_post_type( $value ) {
		$value = is_string( $value ) ? sanitize_key( $value ) : '';
		if ( '' === $value ) {
			$enabled = Taxonomies::post_types();
			$value   = $enabled ? $enabled[0] : '';
		}
		return $value;
	}

	/**
	 * Accepts enabled post types only (empty resolves to the first one).
	 *
	 * @param mixed $value Value.
	 * @return true|WP_Error
	 */
	public static function validate_post_type( $value ) {
		if ( is_string( $value ) && Taxonomies::supports( self::resolve_post_type( $value ) ) ) {
			return true;
		}
		return new WP_Error( 'rest_invalid_param', __( 'Ako isn’t enabled for this content type.', 'ako-multilang' ), array( 'status' => 400 ) );
	}

	/**
	 * Accepts registered language codes only.
	 *
	 * @param mixed $value Value.
	 * @return true|WP_Error
	 */
	public static function validate_language( $value ) {
		if ( is_string( $value ) && Registry::exists( $value ) ) {
			return true;
		}
		return new WP_Error( 'rest_invalid_param', __( 'That language isn’t set up yet.', 'ako-multilang' ), array( 'status' => 400 ) );
	}

	/**
	 * Accepts a registered language code, or '' to clear the language.
	 *
	 * @param mixed $value Value.
	 * @return true|WP_Error
	 */
	public static function validate_language_or_empty( $value ) {
		return '' === $value ? true : self::validate_language( $value );
	}

	/**
	 * Reading or changing one post requires editing it.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function can_edit( WP_REST_Request $request ) {
		return current_user_can( 'edit_post', (int) $request['id'] );
	}

	/**
	 * Duplicating requires editing the source and creating posts of its type.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function can_duplicate( WP_REST_Request $request ) {
		$post = get_post( (int) $request['id'] );
		if ( ! $post || ! current_user_can( 'edit_post', $post->ID ) ) {
			return false;
		}
		$object = get_post_type_object( $post->post_type );
		return $object && current_user_can( $object->cap->create_posts );
	}

	/**
	 * Listing requires editing rights for the requested post type.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function can_list( WP_REST_Request $request ) {
		$object = get_post_type_object( (string) $request['post_type'] );
		return $object && current_user_can( $object->cap->edit_posts );
	}

	/**
	 * Linking changes both posts, so both must be editable. A target that
	 * leaves another group can leave one post there on its own, and that
	 * post must be editable too.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function can_link( WP_REST_Request $request ) {
		$id     = (int) $request['id'];
		$target = (int) $request['target'];
		if ( ! current_user_can( 'edit_post', $id ) || ! current_user_can( 'edit_post', $target ) ) {
			return false;
		}
		if ( Links::get_group_id( $target ) === Links::get_group_id( $id ) ) {
			return true;
		}
		return self::can_edit_all( self::affected_members( $target, false ) );
	}

	/**
	 * Every post whose links change must be editable: the whole group, or
	 * the one member left behind when an unlink leaves it on its own.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function can_unlink( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		return current_user_can( 'edit_post', $id )
			&& self::can_edit_all( self::affected_members( $id, 'group' === $request['scope'] ) );
	}

	/**
	 * Other posts whose links change when $post_id leaves its group: the
	 * rest of the group for a group unlink, otherwise only a member that
	 * would be left on its own.
	 *
	 * @param int  $post_id     Post leaving the group.
	 * @param bool $whole_group Whether the whole group is unlinked.
	 * @return int[]
	 */
	private static function affected_members( $post_id, $whole_group ) {
		$group_id = Links::get_group_id( $post_id );
		if ( ! $group_id ) {
			return array();
		}
		$members = get_objects_in_term( $group_id, Taxonomies::GROUP );
		$others  = is_array( $members ) ? array_diff( array_map( 'absint', $members ), array( (int) $post_id ) ) : array();
		return ( $whole_group || count( $others ) < 2 ) ? $others : array();
	}

	/**
	 * Whether the current user can edit every post.
	 *
	 * @param int[] $post_ids Post IDs.
	 * @return bool
	 */
	private static function can_edit_all( array $post_ids ) {
		foreach ( $post_ids as $post_id ) {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * GET /links
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function index( WP_REST_Request $request ) {
		$data          = Overview::query(
			(string) $request['post_type'],
			(string) $request['filter'],
			trim( (string) $request['search'] ),
			(int) $request['page'],
			(int) $request['per_page']
		);
		$data['types'] = Overview::types();

		return rest_ensure_response( $data );
	}

	/**
	 * GET /links/candidates — pages that could become the translation in
	 * `language`: already in that language, or without a language yet.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function candidates( WP_REST_Request $request ) {
		$args = array(
			'post_type'              => (string) $request['post_type'],
			'post_status'            => Rest::CONTENT_STATUSES,
			'perm'                   => 'editable',
			'posts_per_page'         => 20,
			'orderby'                => 'modified',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Admin-only; indexed taxonomy lookup.
			'tax_query'              => array(
				'relation' => 'OR',
				array(
					'taxonomy' => Taxonomies::LANGUAGE,
					'field'    => 'slug',
					'terms'    => array( (string) $request['language'] ),
				),
				array(
					'taxonomy' => Taxonomies::LANGUAGE,
					'operator' => 'NOT EXISTS',
				),
			),
		);

		if ( $request['skip'] ) {
			$args['post__not_in'] = array( (int) $request['skip'] ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- A single ID.
		}

		$search = trim( (string) $request['search'] );
		if ( '' !== $search ) {
			$args['s']              = $search;
			$args['search_columns'] = array( 'post_title' );
		}

		$query = new WP_Query( $args );
		$out   = array();
		foreach ( $query->posts as $post ) {
			$summary           = Overview::post_summary( $post );
			$summary['linked'] = Links::get_group_id( $post->ID ) > 0;
			$out[]             = $summary;
		}

		return rest_ensure_response( $out );
	}

	/**
	 * PUT /links/{id} — makes `target` the `language` translation of `id`.
	 * A target already in another language is refused, never silently changed.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function link( WP_REST_Request $request ) {
		$source_id = (int) $request['id'];

		$result = Links::link_as( $source_id, (int) $request['target'], (string) $request['language'] );
		if ( is_wp_error( $result ) ) {
			return Rest::with_status( $result, 400 );
		}

		return rest_ensure_response( array( 'translations' => Links::get_translations( $source_id ) ) );
	}

	/**
	 * GET /links/{id} — language, translations and hreflang of one post.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function show( WP_REST_Request $request ) {
		$post = get_post( (int) $request['id'] );
		if ( ! $post || ! Taxonomies::supports( $post->post_type ) ) {
			return new WP_Error( 'akoml_unsupported_type', __( 'Ako isn’t enabled for this content type.', 'ako-multilang' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( Overview::detail( $post ) );
	}

	/**
	 * PUT /links/{id}/language — sets (or clears, with '') a post's language.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function set_language( WP_REST_Request $request ) {
		$id     = (int) $request['id'];
		$result = Links::set_language( $id, (string) $request['language'] );
		if ( is_wp_error( $result ) ) {
			return Rest::with_status( $result, 400 );
		}
		return rest_ensure_response( Overview::detail( get_post( $id ) ) );
	}

	/**
	 * POST /links/{id}/duplicate — creates a linked draft copy in `language`.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function duplicate( WP_REST_Request $request ) {
		$id     = (int) $request['id'];
		$new_id = Duplicate::create( $id, (string) $request['language'] );
		if ( is_wp_error( $new_id ) ) {
			return Rest::with_status( $new_id, 400 );
		}

		$response = rest_ensure_response(
			array(
				'created' => Overview::post_summary( get_post( $new_id ) ),
				'detail'  => Overview::detail( get_post( $id ) ),
			)
		);
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * DELETE /links/{id} — removes one post from its group (`scope=post`) or
	 * dissolves the whole group (`scope=group`). Posts are never deleted.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function unlink( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( ! get_post( $id ) ) {
			return new WP_Error( 'akoml_invalid_post', __( 'That page doesn’t exist.', 'ako-multilang' ), array( 'status' => 404 ) );
		}

		if ( 'group' === $request['scope'] ) {
			Links::dissolve( $id );
		} else {
			Links::unlink( $id );
		}

		return rest_ensure_response( array( 'translations' => Links::get_translations( $id ) ) );
	}
}
