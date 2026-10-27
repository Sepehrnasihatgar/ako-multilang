/**
 * Link candidates: shared ordering and labels for every "link existing page" picker.
 */
import { __ } from '@wordpress/i18n';

/**
 * Best matches first: pages already in the language and free to link, then
 * pages without a language. Pages linked elsewhere come last and can't be
 * picked (linking would silently break their current group).
 *
 * @param {Array} list Candidates from the API (newest first).
 * @return {Array} Sorted candidates.
 */
export function sortCandidates( list ) {
	const rank = ( candidate ) => {
		if ( candidate.linked ) {
			return 2;
		}
		return candidate.language ? 0 : 1;
	};
	return [ ...list ].sort( ( a, b ) => rank( a ) - rank( b ) );
}

/**
 * Short note shown next to a candidate.
 *
 * @param {Object} candidate Candidate.
 * @return {string} Label.
 */
export function candidateMeta( candidate ) {
	if ( candidate.linked ) {
		return __( 'Linked elsewhere', 'cedar-multilang' );
	}
	if ( ! candidate.language ) {
		return __( 'No language', 'cedar-multilang' );
	}
	return 'publish' === candidate.status
		? __( 'Published', 'cedar-multilang' )
		: __( 'Draft', 'cedar-multilang' );
}
