<?php
/**
 * Uninstall: removes Ako's data only if the user turned on
 * "Delete Ako data on uninstall". Posts and pages are never touched.
 *
 * @package AkoMultilang
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$akoml_settings = get_option( 'akoml_settings', array() );

if ( ! is_array( $akoml_settings ) || empty( $akoml_settings['delete_on_uninstall'] ) ) {
	return;
}

foreach ( array( 'akoml_language', 'akoml_group' ) as $akoml_taxonomy ) {
	// Taxonomies are not registered during uninstall; register them briefly so term APIs work.
	register_taxonomy( $akoml_taxonomy, array(), array( 'public' => false ) );

	$akoml_terms = get_terms(
		array(
			'taxonomy'   => $akoml_taxonomy,
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);

	if ( is_array( $akoml_terms ) ) {
		foreach ( $akoml_terms as $akoml_term_id ) {
			wp_delete_term( (int) $akoml_term_id, $akoml_taxonomy );
		}
	}
}

foreach ( array( 'akoml_settings', 'akoml_languages', 'akoml_switchers', 'akoml_db_version', 'akoml_needs_setup', 'akoml_health', 'akoml_imports' ) as $akoml_option ) {
	delete_option( $akoml_option );
}

delete_metadata( 'user', 0, 'akoml_dismissed', '', true );
