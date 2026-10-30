<?php
/**
 * "Language · Ako" in the block editor (sidebar panel) and the classic
 * editor (metabox). Both render the same React panel.
 *
 * @package AkoMultilang
 */

namespace AkoMultilang\Admin;

use AkoMultilang\Content\Taxonomies;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the panel only for enabled post types and users who can edit the post.
 */
final class Editor {

	const BLOCK_HANDLE   = 'akoml-editor';
	const CLASSIC_HANDLE = 'akoml-classic';

	/**
	 * Hooks into both editors.
	 */
	public static function register() {
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue_block_editor' ) );
		add_action( 'add_meta_boxes', array( self::class, 'add_meta_box' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_classic' ) );
	}

	/**
	 * Whether the current screen edits a post Ako handles.
	 *
	 * @return bool
	 */
	private static function is_supported_post_screen() {
		$screen = get_current_screen();
		return $screen && 'post' === $screen->base && Taxonomies::supports( $screen->post_type );
	}

	/**
	 * Block editor sidebar panel (not the site editor or widgets screen).
	 */
	public static function enqueue_block_editor() {
		if ( ! self::is_supported_post_screen() ) {
			return;
		}
		self::enqueue( 'editor', self::BLOCK_HANDLE );
	}

	/**
	 * Classic editor metabox. `__back_compat_meta_box` hides it in the block
	 * editor, which shows the sidebar panel instead.
	 *
	 * @param string  $post_type Post type.
	 * @param WP_Post $post      Post.
	 */
	public static function add_meta_box( $post_type, $post ) {
		if ( ! $post instanceof WP_Post || ! Taxonomies::supports( $post_type ) ) {
			return;
		}
		add_meta_box(
			'akoml-language',
			__( 'Language · Ako', 'ako-multilang' ),
			array( self::class, 'render_meta_box' ),
			$post_type,
			'side',
			'default',
			array( '__back_compat_meta_box' => true )
		);
	}

	/**
	 * Metabox root; the panel is rendered by the classic bundle.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_meta_box( $post ) {
		printf(
			'<div id="akoml-classic" data-post-id="%d"></div>',
			(int) $post->ID
		);
		printf(
			'<noscript><p>%s</p></noscript>',
			esc_html__( 'Ako Multilang needs JavaScript to show translations.', 'ako-multilang' )
		);
	}

	/**
	 * Loads the classic bundle on post.php / post-new.php when the block
	 * editor is not used.
	 *
	 * @param string $hook_suffix Admin page hook.
	 */
	public static function enqueue_classic( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) || ! self::is_supported_post_screen() ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
			return;
		}
		self::enqueue( 'classic', self::CLASSIC_HANDLE );
	}

	/**
	 * Enqueues one built bundle with its dependencies, translations and styles.
	 *
	 * @param string $entry  Build folder (editor|classic).
	 * @param string $handle Script and style handle.
	 */
	private static function enqueue( $entry, $handle ) {
		$asset_file = AKOML_PATH . 'build/' . $entry . '/index.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}
		$asset = include $asset_file;

		wp_enqueue_script(
			$handle,
			AKOML_URL . 'build/' . $entry . '/index.js',
			$asset['dependencies'],
			$asset['version'],
			array( 'in_footer' => true )
		);
		wp_set_script_translations( $handle, 'ako-multilang', AKOML_PATH . 'languages' );

		wp_enqueue_style( $handle, AKOML_URL . 'build/' . $entry . '/index.css', array(), $asset['version'] );
		wp_style_add_data( $handle, 'rtl', 'replace' );
	}
}
