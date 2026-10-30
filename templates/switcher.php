<?php
/**
 * Language switcher markup.
 *
 * Themes can override this file by copying it to
 * `your-theme/ako-multilang/switcher.php`. The output is still filtered
 * through wp_kses() with Ako's allow-list.
 *
 * Available in $args:
 * - profile: the switcher settings (style, show, icon, mobile, styling, shape, linkLook, …).
 * - items:   languages to show, each with code, hreflang, name, native, dir,
 *            url, current and available.
 * - context: block|shortcode|menu|php|footer.
 *
 * @package AkoMultilang
 */

defined( 'ABSPATH' ) || exit;

$akoml_profile = $args['profile'];
$akoml_items   = $args['items'];

$akoml_classes = array(
	'ako-switcher',
	'ako-switcher--' . $akoml_profile['style'],
	'ako-switcher--' . $akoml_profile['shape'],
	'ako-switcher--show-' . $akoml_profile['show'],
	'ako-switcher--sm-' . $akoml_profile['mobile'],
);
if ( 'inherit' === $akoml_profile['styling'] ) {
	$akoml_classes[] = 'ako-switcher--inherit';
}
if ( 'link' === $akoml_profile['style'] ) {
	$akoml_classes[] = 'ako-switcher--look-' . $akoml_profile['linkLook'];
}

$akoml_globe   = '<svg class="ako-switcher__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>';
$akoml_chevron = '<svg class="ako-switcher__chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m6 9 6 6 6-6"/></svg>';

/*
 * One language's label: optional globe, the native name (always read by
 * screen readers, hidden visually when only the code shows) and the code.
 */
$akoml_label = static function ( $item, $with_icon ) use ( $akoml_globe ) {
	$html  = $with_icon ? $akoml_globe : '';
	$html .= sprintf(
		'<span class="ako-switcher__name" lang="%1$s" dir="%2$s">%3$s</span>',
		esc_attr( $item['hreflang'] ),
		esc_attr( $item['dir'] ),
		esc_html( $item['native'] )
	);
	$html .= sprintf( '<span class="ako-switcher__code" aria-hidden="true">%s</span>', esc_html( strtoupper( $item['code'] ) ) );
	return $html;
};

$akoml_item = static function ( $item, $with_icon ) use ( $akoml_label ) {
	$label = $akoml_label( $item, $with_icon );

	if ( $item['current'] ) {
		return sprintf( '<span class="ako-switcher__item is-current" aria-current="true">%s</span>', $label );
	}
	if ( ! $item['available'] ) {
		return sprintf(
			'<span class="ako-switcher__item is-disabled" aria-disabled="true" data-tooltip="%1$s">%2$s<span class="ako-switcher__sr">%3$s</span></span>',
			esc_attr__( 'Not translated yet', 'ako-multilang' ),
			$label,
			esc_html__( '(not translated yet)', 'ako-multilang' )
		);
	}
	return sprintf(
		'<a class="ako-switcher__item" href="%1$s" hreflang="%2$s" lang="%2$s">%3$s</a>',
		esc_url( $item['url'] ),
		esc_attr( $item['hreflang'] ),
		$label
	);
};

$akoml_current = null;
foreach ( $akoml_items as $akoml_candidate ) {
	if ( $akoml_candidate['current'] ) {
		$akoml_current = $akoml_candidate;
	}
}
?>
<nav class="<?php echo esc_attr( implode( ' ', $akoml_classes ) ); ?>" data-switcher="<?php echo esc_attr( $akoml_profile['id'] ); ?>" aria-label="<?php esc_attr_e( 'Language', 'ako-multilang' ); ?>">
<?php if ( in_array( $akoml_profile['style'], array( 'dropdown', 'floating' ), true ) && $akoml_current ) : ?>
	<details class="ako-switcher__details">
		<summary class="ako-switcher__toggle">
			<?php echo $akoml_label( $akoml_current, $akoml_profile['icon'] ) . $akoml_chevron; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above. ?>
		</summary>
		<div class="ako-switcher__menu" role="list">
			<?php foreach ( $akoml_items as $akoml_entry ) : ?>
				<div class="ako-switcher__entry" role="listitem"><?php echo $akoml_item( $akoml_entry, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above. ?></div>
			<?php endforeach; ?>
		</div>
	</details>
<?php else : ?>
	<div class="ako-switcher__list" role="list">
		<?php foreach ( $akoml_items as $akoml_entry ) : ?>
			<?php
			if ( 'link' === $akoml_profile['style'] && $akoml_entry['current'] ) {
				continue;
			}
			?>
			<div class="ako-switcher__entry" role="listitem"><?php echo $akoml_item( $akoml_entry, $akoml_profile['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above. ?></div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
</nav>
