/**
 * Mapping between [acme_events] shortcode attributes and block attributes.
 */
export const MAX_LIMIT = 20;
export const LAYOUTS = [ 'list', 'grid' ];

const toBool = ( value ) =>
	[ '1', 'yes', 'true', 'on' ].includes( String( value ).trim().toLowerCase() );

/**
 * Block attributes for the named attributes of an [acme_events] shortcode.
 *
 * @param {Object} named Named shortcode attributes (strings).
 * @return {Object} Block attributes.
 */
export function attributesFromShortcode( named = {} ) {
	const attributes = {};
	if ( named.limit !== undefined ) {
		const limit = parseInt( named.limit, 10 );
		attributes.limit = Math.min( MAX_LIMIT, Math.max( 1, Math.abs( isNaN( limit ) ? 0 : limit ) ) );
	}
	if ( named.category !== undefined ) {
		attributes.category = String( named.category );
	}
	if ( named.show_past !== undefined ) {
		attributes.showPast = toBool( named.show_past );
	}
	if ( named.layout !== undefined ) {
		attributes.layout = LAYOUTS.includes( named.layout ) ? named.layout : 'list';
	}
	if ( named.title !== undefined ) {
		attributes.title = String( named.title );
	}
	if ( named.show_venue !== undefined ) {
		attributes.showVenue = toBool( named.show_venue );
	}
	return attributes;
}
