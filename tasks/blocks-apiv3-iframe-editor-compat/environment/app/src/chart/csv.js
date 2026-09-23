/**
 * Minimal CSV parsing for the "Paste CSV" importer.
 *
 * Accepts "label,value" or "label;value" (European exports) per line, an
 * optional third column with a hex colour, and an optional header row.
 */

const HEX = /^#(?:[0-9a-f]{3}){1,2}$/i;

/**
 * Parse pasted CSV into a chart series.
 *
 * @param {string} text Pasted text.
 * @return {Array<{label: string, value: number, color?: string}>} Series.
 */
export function parseCsv( text ) {
	const series = [];
	String( text || '' )
		.split( /\r?\n/ )
		.map( ( line ) => line.trim() )
		.filter( Boolean )
		.forEach( ( line ) => {
			const delimiter = line.includes( ';' ) ? ';' : ',';
			const cells = line
				.split( delimiter )
				.map( ( cell ) => cell.trim().replace( /^"(.*)"$/, '$1' ) );
			// "1.234,5" style numbers when the delimiter is ";".
			const raw =
				delimiter === ';'
					? ( cells[ 1 ] || '' ).replace( /\./g, '' ).replace( ',', '.' )
					: cells[ 1 ];
			const value = parseFloat( raw );
			if ( ! cells[ 0 ] || Number.isNaN( value ) ) {
				// Header row or garbage.
				return;
			}
			const point = { label: cells[ 0 ], value };
			if ( cells[ 2 ] && HEX.test( cells[ 2 ] ) ) {
				point.color = cells[ 2 ];
			}
			series.push( point );
		} );
	return series;
}

/**
 * Serialize a series back to CSV (for the textarea).
 *
 * @param {Array} series Series.
 * @return {string} CSV.
 */
export function toCsv( series ) {
	return ( series || [] )
		.map( ( point ) =>
			[ point.label, point.value, point.color ].filter( ( v ) => v !== undefined && v !== '' ).join( ',' )
		)
		.join( '\n' );
}
