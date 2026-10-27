<?php
/**
 * Uninstall: removes Ako's data only if the user turned on
 * "Delete Ako data on uninstall". Posts and pages are never touched.
 *
 * @package Ako\Multilang
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$ako_ml_settings = get_option( 'ako_ml_settings', array() );

if ( ! is_array( $ako_ml_settings ) || empty( $ako_ml_settings['delete_on_uninstall'] ) ) {
	return;
}

foreach ( array( 'ako_ml_language', 'ako_ml_group' ) as $ako_ml_taxonomy ) {
	// Taxonomies are not registered during uninstall; register them briefly so term APIs work.
	register_taxonomy( $ako_ml_taxonomy, array(), array( 'public' => false ) );

	$ako_ml_terms = get_terms(
		array(
			'taxonomy'   => $ako_ml_taxonomy,
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);

	if ( is_array( $ako_ml_terms ) ) {
		foreach ( $ako_ml_terms as $ako_ml_term_id ) {
			wp_delete_term( (int) $ako_ml_term_id, $ako_ml_taxonomy );
		}
	}
}

foreach ( array( 'ako_ml_settings', 'ako_ml_languages', 'ako_ml_switchers', 'ako_ml_db_version', 'ako_ml_needs_setup', 'ako_ml_legacy62', 'ako_ml_health', 'ako_ml_imports' ) as $ako_ml_option ) {
	delete_option( $ako_ml_option );
}

delete_metadata( 'user', 0, 'ako_ml_dismissed', '', true );
