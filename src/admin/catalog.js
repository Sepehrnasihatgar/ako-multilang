/**
 * Searching the language catalog (Languages screen and setup).
 */

/**
 * Catalog entries matching a search: prefix matches on the English name,
 * native name or locale first, then the plainest name ("Deutsch" before
 * "Deutsch (Schweiz)").
 *
 * @param {Array}  catalog Catalog entries.
 * @param {string} text    Search text.
 * @param {number} [limit] Maximum results.
 * @return {Array} Entries.
 */
export function searchCatalog( catalog, text, limit = 6 ) {
	const needle = text.trim().toLowerCase();
	if ( ! needle ) {
		return [];
	}
	const scored = [];
	catalog.forEach( ( entry ) => {
		const fields = [ entry.name, entry.native, entry.locale ].map(
			( value ) => value.toLowerCase()
		);
		if ( fields.some( ( value ) => value.startsWith( needle ) ) ) {
			scored.push( [ 0, entry ] );
		} else if ( fields.some( ( value ) => value.includes( needle ) ) ) {
			scored.push( [ 1, entry ] );
		}
	} );
	return scored
		.sort(
			( a, b ) =>
				a[ 0 ] - b[ 0 ] || a[ 1 ].native.length - b[ 1 ].native.length
		)
		.slice( 0, limit )
		.map( ( item ) => item[ 1 ] );
}
