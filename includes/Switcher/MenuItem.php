<?php
/**
 * The switcher as a menu item (Appearance → Menus and the Navigation block).
 *
 * @package Ako\Multilang
 */

namespace Ako\Multilang\Switcher;

defined( 'ABSPATH' ) || exit;

/**
 * A switcher is stored as a custom link whose URL is `#ako-switcher-{id}`;
 * on output that link is replaced with the rendered switcher.
 */
final class MenuItem {

	const URL_PATTERN = '/^#ako-switcher-([a-z0-9_-]{1,40})$/';

	/**
	 * Hooks into menus.
	 */
	public static function register() {
		add_action( 'admin_head-nav-menus.php', array( self::class, 'add_meta_box' ) );
		add_filter( 'walker_nav_menu_start_el', array( self::class, 'replace_classic_item' ), 10, 2 );
		add_filter( 'render_block_core/navigation-link', array( self::class, 'replace_block_item' ), 10, 2 );
	}

	/**
	 * Adds the "Language switcher" box to Appearance → Menus.
	 */
	public static function add_meta_box() {
		add_meta_box(
			'ako-ml-menu-switcher',
			__( 'Language switcher', 'ako-multilang' ),
			array( self::class, 'render_meta_box' ),
			'nav-menus',
			'side',
			'default'
		);
	}

	/**
	 * Same markup as core's menu item boxes, so core's nav-menu.js adds the
	 * items (with its own nonce) when "Add to Menu" is pressed.
	 */
	public static function render_meta_box() {
		$index = -1;
		?>
		<div id="ako-ml-switcher-items" class="posttypediv">
			<div class="tabs-panel tabs-panel-active">
				<ul class="categorychecklist form-no-clear">
					<?php foreach ( Profiles::all() as $profile ) : ?>
						<li>
							<label class="menu-item-title">
								<input type="checkbox" class="menu-item-checkbox" name="menu-item[<?php echo (int) $index; ?>][menu-item-object-id]" value="<?php echo (int) $index; ?>" />
								<?php echo esc_html( $profile['name'] ); ?>
							</label>
							<input type="hidden" class="menu-item-type" name="menu-item[<?php echo (int) $index; ?>][menu-item-type]" value="custom" />
							<input type="hidden" class="menu-item-title" name="menu-item[<?php echo (int) $index; ?>][menu-item-title]" value="<?php esc_attr_e( 'Language switcher', 'ako-multilang' ); ?>" />
							<input type="hidden" class="menu-item-url" name="menu-item[<?php echo (int) $index; ?>][menu-item-url]" value="<?php echo esc_attr( '#ako-switcher-' . $profile['id'] ); ?>" />
						</li>
						<?php --$index; ?>
					<?php endforeach; ?>
				</ul>
			</div>
			<p class="button-controls wp-clearfix">
				<span class="add-to-menu">
					<input type="submit" class="button submit-add-to-menu right" value="<?php esc_attr_e( 'Add to Menu', 'ako-multilang' ); ?>" name="add-ako-ml-switcher-menu-item" id="submit-ako-ml-switcher-items" />
					<span class="spinner"></span>
				</span>
			</p>
		</div>
		<?php
	}

	/**
	 * Classic menus: swap the link for the switcher.
	 *
	 * @param string   $item_output Item HTML.
	 * @param \WP_Post $item        Menu item.
	 * @return string
	 */
	public static function replace_classic_item( $item_output, $item ) {
		if ( isset( $item->url ) && preg_match( self::URL_PATTERN, (string) $item->url, $match ) ) {
			return Renderer::render( $match[1], array(), 'menu' );
		}
		return $item_output;
	}

	/**
	 * Navigation block: swap a custom link item for the switcher.
	 *
	 * @param string $content Block HTML.
	 * @param array  $block   Parsed block.
	 * @return string
	 */
	public static function replace_block_item( $content, $block ) {
		$url = isset( $block['attrs']['url'] ) ? (string) $block['attrs']['url'] : '';
		if ( preg_match( self::URL_PATTERN, $url, $match ) ) {
			$switcher = Renderer::render( $match[1], array(), 'menu' );
			return '' === $switcher ? '' : '<li class="wp-block-navigation-item">' . $switcher . '</li>';
		}
		return $content;
	}
}
