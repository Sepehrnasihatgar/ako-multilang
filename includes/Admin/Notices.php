<?php
/**
 * Dashboard and Plugins screen notices.
 *
 * @package Cedar\Multilang
 */

namespace Cedar\Multilang\Admin;

use Cedar\Multilang\Migration\Legacy62;
use Cedar\Multilang\Setup\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * At most one Cedar notice at a time, only for site owners, only on the
 * Dashboard and Plugins screens, and each can be dismissed for good.
 */
final class Notices {

	/**
	 * User meta with dismissed notice keys.
	 */
	const DISMISSED_META = 'cedar_ml_dismissed';

	/**
	 * Notices that can be dismissed.
	 */
	const KEYS = array( 'setup', 'legacy62' );

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'admin_notices', array( self::class, 'render' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'admin_post_cedar_ml_dismiss', array( self::class, 'dismiss' ) );
	}

	/**
	 * The notice for the current screen: [ key, variant ] or null.
	 *
	 * @return array{0:string,1:string}|null
	 */
	private static function current() {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins' ), true ) || ! current_user_can( 'manage_options' ) ) {
			return null;
		}

		$dismissed = self::dismissed();
		$legacy    = get_option( Legacy62::STATUS_OPTION, array() );
		if ( is_array( $legacy ) && ! empty( $legacy['notice'] ) && ! in_array( 'legacy62', $dismissed, true ) ) {
			return array( 'legacy62', 'success' );
		}
		if ( Wizard::needs_setup() && ! in_array( 'setup', $dismissed, true ) ) {
			return array( 'setup', 'plugins' === $screen->id ? 'start' : 'continue' );
		}
		return null;
	}

	/**
	 * Keys the current user dismissed.
	 *
	 * @return string[]
	 */
	private static function dismissed() {
		$dismissed = get_user_meta( get_current_user_id(), self::DISMISSED_META, true );
		return is_array( $dismissed ) ? $dismissed : array();
	}

	/**
	 * Loads the notice styles when a notice is shown.
	 */
	public static function enqueue() {
		if ( self::current() ) {
			wp_enqueue_style( 'cedar-ml-notices', CEDAR_ML_URL . 'assets/notices.css', array(), CEDAR_ML_VERSION );
		}
	}

	/**
	 * Prints the notice.
	 */
	public static function render() {
		$notice = self::current();
		if ( ! $notice ) {
			return;
		}
		list( $key, $variant ) = $notice;

		$setup_url = admin_url( 'admin.php?page=' . Admin::SLUG . '-setup' );
		$action    = null;

		if ( 'legacy62' === $key ) {
			$legacy = get_option( Legacy62::STATUS_OPTION, array() );
			$pairs  = isset( $legacy['pairs'] ) ? (int) $legacy['pairs'] : 0;
			$title  = __( 'Your Cedar 6.2 data was moved over', 'cedar-multilang' );
			$text   = sprintf(
				/* translators: %d: Number of linked page pairs. */
				_n( '%d page pair now uses the new format. Your [lang_switch] shortcodes keep working.', '%d page pairs now use the new format. Your [lang_switch] shortcodes keep working.', $pairs, 'cedar-multilang' ),
				$pairs
			);
		} elseif ( 'start' === $variant ) {
			$title  = __( 'Cedar is active — set it up in about 2 minutes', 'cedar-multilang' );
			$text   = __( 'Pick your languages, connect existing translations and add a switcher. Nothing changes on your site until you finish.', 'cedar-multilang' );
			$action = array( $setup_url, __( 'Start setup', 'cedar-multilang' ) );
		} else {
			$title  = __( 'Cedar is almost ready', 'cedar-multilang' );
			$text   = __( 'You haven’t finished setup. Hreflang and the switcher turn on once two pages are linked.', 'cedar-multilang' );
			$action = array( $setup_url, __( 'Continue setup', 'cedar-multilang' ) );
		}

		$dismiss_url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'cedar_ml_dismiss',
					'notice' => $key,
				),
				admin_url( 'admin-post.php' )
			),
			'cedar_ml_dismiss_' . $key
		);
		?>
		<div class="notice cedar-ml-notice cedar-ml-notice--<?php echo esc_attr( 'success' === $variant ? 'success' : 'info' ); ?>">
			<span class="cedar-ml-notice__mark" aria-hidden="true">
				<svg width="20" height="20" viewBox="0 0 32 32" focusable="false"><path fill="currentColor" d="M16 5.5 10.5 12h3L9 17.5h3.5l-5 6h17l-5-6H23L18.5 12h3L16 5.5Z"/><path fill="currentColor" d="M15.55 23.5h.9c.44 0 .8.36.8.8v1.9c0 .44-.36.8-.8.8h-.9a.8.8 0 0 1-.8-.8v-1.9c0-.44.36-.8.8-.8Z"/></svg>
			</span>
			<div class="cedar-ml-notice__copy">
				<p class="cedar-ml-notice__title"><?php echo esc_html( $title ); ?></p>
				<p class="cedar-ml-notice__text"><?php echo esc_html( $text ); ?></p>
			</div>
			<?php if ( $action ) : ?>
				<a class="cedar-ml-notice__action" href="<?php echo esc_url( $action[0] ); ?>"><?php echo esc_html( $action[1] ); ?></a>
			<?php endif; ?>
			<a class="cedar-ml-notice__dismiss" href="<?php echo esc_url( $dismiss_url ); ?>">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M18 6 6 18M6 6l12 12"/></svg>
				<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice', 'cedar-multilang' ); ?></span>
			</a>
		</div>
		<?php
	}

	/**
	 * Handles a dismiss link (admin-post.php), then returns to the screen.
	 */
	public static function dismiss() {
		$key = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : '';
		if ( ! in_array( $key, self::KEYS, true ) ) {
			wp_die( esc_html__( 'Unknown notice.', 'cedar-multilang' ), 400 );
		}
		check_admin_referer( 'cedar_ml_dismiss_' . $key );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'cedar-multilang' ), 403 );
		}

		$dismissed = self::dismissed();
		if ( ! in_array( $key, $dismissed, true ) ) {
			$dismissed[] = $key;
			update_user_meta( get_current_user_id(), self::DISMISSED_META, $dismissed );
		}

		$referer = wp_get_referer();
		wp_safe_redirect( $referer ? $referer : admin_url() );
		exit;
	}
}
