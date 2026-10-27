<?php
/**
 * The Language Switcher block (dynamic, rendered by the shared renderer).
 *
 * @package Cedar\Multilang
 */

namespace Cedar\Multilang\Switcher;

defined( 'ABSPATH' ) || exit;

/**
 * Registers blocks/switcher/block.json with a PHP render callback.
 */
final class Block {

	const EDITOR_HANDLE = 'cedar-ml-switcher-block';

	/**
	 * Hooks block registration (after the switcher assets are registered).
	 */
	public static function register() {
		add_action( 'init', array( self::class, 'register_block' ), 20 );
		add_filter( 'register_block_type_args', array( self::class, 'allow_in_navigation' ), 10, 2 );
	}

	/**
	 * Lets the switcher go inside the Navigation block, where block themes
	 * keep their menus. Navigation only accepts the blocks it lists.
	 *
	 * @param array  $args       Block type arguments.
	 * @param string $block_type Block name.
	 * @return array
	 */
	public static function allow_in_navigation( $args, $block_type ) {
		if ( 'core/navigation' === $block_type && isset( $args['allowed_blocks'] ) && is_array( $args['allowed_blocks'] ) ) {
			$args['allowed_blocks'][] = 'cedar-multilang/switcher';
		}
		return $args;
	}

	/**
	 * Registers the editor script handle and the block type.
	 */
	public static function register_block() {
		$asset_file = CEDAR_ML_PATH . 'build/block/index.asset.php';
		if ( is_readable( $asset_file ) ) {
			$asset = include $asset_file;
			wp_register_script(
				self::EDITOR_HANDLE,
				CEDAR_ML_URL . 'build/block/index.js',
				$asset['dependencies'],
				$asset['version'],
				array( 'in_footer' => true )
			);
			wp_set_script_translations( self::EDITOR_HANDLE, 'cedar-multilang', CEDAR_ML_PATH . 'languages' );
		}

		register_block_type(
			CEDAR_ML_PATH . 'blocks/switcher',
			array( 'render_callback' => array( self::class, 'render' ) )
		);
	}

	/**
	 * Server render.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render( $attributes ) {
		$id = isset( $attributes['switcher'] ) ? sanitize_key( (string) $attributes['switcher'] ) : '';
		if ( '' !== $id && ! Profiles::get( $id ) ) {
			$id = '';
		}

		$html = Renderer::render( $id, array(), 'block' );
		if ( '' === $html ) {
			return '';
		}
		return sprintf( '<div %1$s>%2$s</div>', get_block_wrapper_attributes(), $html );
	}
}
