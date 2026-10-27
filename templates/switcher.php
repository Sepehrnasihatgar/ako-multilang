<?php
/**
 * Language switcher markup.
 *
 * Themes can override this file by copying it to
 * `your-theme/cedar-multilang/switcher.php`. The output is still filtered
 * through wp_kses() with Cedar's allow-list.
 *
 * Available in $args:
 * - profile: the switcher settings (style, show, icon, mobile, styling, shape, linkLook, …).
 * - items:   languages to show, each with code, hreflang, name, native, dir,
 *            url, current and available.
 * - context: block|shortcode|menu|php|footer.
 *
 * @package Cedar\Multilang
 */

defined( 'ABSPATH' ) || exit;

$cedar_ml_profile = $args['profile'];
$cedar_ml_items   = $args['items'];

$cedar_ml_classes = array(
	'cedar-switcher',
	'cedar-switcher--' . $cedar_ml_profile['style'],
	'cedar-switcher--' . $cedar_ml_profile['shape'],
	'cedar-switcher--show-' . $cedar_ml_profile['show'],
	'cedar-switcher--sm-' . $cedar_ml_profile['mobile'],
);
if ( 'inherit' === $cedar_ml_profile['styling'] ) {
	$cedar_ml_classes[] = 'cedar-switcher--inherit';
}
if ( 'link' === $cedar_ml_profile['style'] ) {
	$cedar_ml_classes[] = 'cedar-switcher--look-' . $cedar_ml_profile['linkLook'];
}

$cedar_ml_globe   = '<svg class="cedar-switcher__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>';
$cedar_ml_chevron = '<svg class="cedar-switcher__chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m6 9 6 6 6-6"/></svg>';

/*
 * One language's label: optional globe, the native name (always read by
 * screen readers, hidden visually when only the code shows) and the code.
 */
$cedar_ml_label = static function ( $item, $with_icon ) use ( $cedar_ml_globe ) {
	$html  = $with_icon ? $cedar_ml_globe : '';
	$html .= sprintf(
		'<span class="cedar-switcher__name" lang="%1$s" dir="%2$s">%3$s</span>',
		esc_attr( $item['hreflang'] ),
		esc_attr( $item['dir'] ),
		esc_html( $item['native'] )
	);
	$html .= sprintf( '<span class="cedar-switcher__code" aria-hidden="true">%s</span>', esc_html( strtoupper( $item['code'] ) ) );
	return $html;
};

$cedar_ml_item = static function ( $item, $with_icon ) use ( $cedar_ml_label ) {
	$label = $cedar_ml_label( $item, $with_icon );

	if ( $item['current'] ) {
		return sprintf( '<span class="cedar-switcher__item is-current" aria-current="true">%s</span>', $label );
	}
	if ( ! $item['available'] ) {
		return sprintf(
			'<span class="cedar-switcher__item is-disabled" aria-disabled="true" data-tooltip="%1$s">%2$s<span class="cedar-switcher__sr">%3$s</span></span>',
			esc_attr__( 'Not translated yet', 'cedar-multilang' ),
			$label,
			esc_html__( '(not translated yet)', 'cedar-multilang' )
		);
	}
	return sprintf(
		'<a class="cedar-switcher__item" href="%1$s" hreflang="%2$s" lang="%2$s">%3$s</a>',
		esc_url( $item['url'] ),
		esc_attr( $item['hreflang'] ),
		$label
	);
};

$cedar_ml_current = null;
foreach ( $cedar_ml_items as $cedar_ml_candidate ) {
	if ( $cedar_ml_candidate['current'] ) {
		$cedar_ml_current = $cedar_ml_candidate;
	}
}
?>
<nav class="<?php echo esc_attr( implode( ' ', $cedar_ml_classes ) ); ?>" data-switcher="<?php echo esc_attr( $cedar_ml_profile['id'] ); ?>" aria-label="<?php esc_attr_e( 'Language', 'cedar-multilang' ); ?>">
<?php if ( in_array( $cedar_ml_profile['style'], array( 'dropdown', 'floating' ), true ) && $cedar_ml_current ) : ?>
	<details class="cedar-switcher__details">
		<summary class="cedar-switcher__toggle">
			<?php echo $cedar_ml_label( $cedar_ml_current, $cedar_ml_profile['icon'] ) . $cedar_ml_chevron; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above. ?>
		</summary>
		<ul class="cedar-switcher__menu">
			<?php foreach ( $cedar_ml_items as $cedar_ml_entry ) : ?>
				<li><?php echo $cedar_ml_item( $cedar_ml_entry, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above. ?></li>
			<?php endforeach; ?>
		</ul>
	</details>
<?php else : ?>
	<ul class="cedar-switcher__list">
		<?php foreach ( $cedar_ml_items as $cedar_ml_entry ) : ?>
			<?php
			if ( 'link' === $cedar_ml_profile['style'] && $cedar_ml_entry['current'] ) {
				continue;
			}
			?>
			<li><?php echo $cedar_ml_item( $cedar_ml_entry, $cedar_ml_profile['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above. ?></li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
</nav>
