<?php
/**
 * Uninstall: removes Cedar's data only if the user turned on
 * "Delete Cedar data on uninstall". Posts and pages are never touched.
 *
 * @package Cedar\Multilang
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$cedar_ml_settings = get_option( 'cedar_ml_settings', array() );

if ( ! is_array( $cedar_ml_settings ) || empty( $cedar_ml_settings['delete_on_uninstall'] ) ) {
	return;
}

foreach ( array( 'cedar_ml_language', 'cedar_ml_group' ) as $cedar_ml_taxonomy ) {
	// Taxonomies are not registered during uninstall; register them briefly so term APIs work.
	register_taxonomy( $cedar_ml_taxonomy, array(), array( 'public' => false ) );

	$cedar_ml_terms = get_terms(
		array(
			'taxonomy'   => $cedar_ml_taxonomy,
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);

	if ( is_array( $cedar_ml_terms ) ) {
		foreach ( $cedar_ml_terms as $cedar_ml_term_id ) {
			wp_delete_term( (int) $cedar_ml_term_id, $cedar_ml_taxonomy );
		}
	}
}

foreach ( array( 'cedar_ml_settings', 'cedar_ml_languages', 'cedar_ml_switchers', 'cedar_ml_db_version', 'cedar_ml_needs_setup', 'cedar_ml_legacy62', 'cedar_ml_health' ) as $cedar_ml_option ) {
	delete_option( $cedar_ml_option );
}

delete_metadata( 'user', 0, 'cedar_ml_dismissed', '', true );
