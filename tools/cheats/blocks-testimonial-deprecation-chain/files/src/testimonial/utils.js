/**
 * Shared helpers.
 */

/**
 * Plain text version of rich text (for alt texts).
 *
 * @param {string} html Rich text.
 * @return {string} Text.
 */
export const stripTags = ( html ) => String( html || '' ).replace( /<[^>]+>/g, '' );

/**
 * Star characters for a whole-star rating (3.x markup).
 *
 * @param {number} rating Rating 1–5.
 * @return {string} Stars.
 */
export const stars = ( rating ) => '★'.repeat( rating ) + '☆'.repeat( 5 - rating );

/**
 * Normalise a rating from any saved format: a number (or numeric string) from 0 to 5,
 * rounded to the nearest half star. Anything else is 0 ("not rated").
 *
 * Keep in sync with acme_testimonials_normalize_rating() (PHP).
 *
 * @param {*} value Raw rating.
 * @return {number} Rating 0–5 in steps of 0.5.
 */
export function normalizeRating( value ) {
	const number = typeof value === 'number' ? value : parseFloat( String( value ?? '' ).trim() );
	if ( ! Number.isFinite( number ) ) {
		return 0;
	}
	return Math.round( Math.min( 5, Math.max( 0, number ) ) * 2 ) / 2;
}

/**
 * Rating as shown in the accessible label ("4", "4.5").
 *
 * @param {number} rating Normalised rating.
 * @return {string} Label value.
 */
export const formatRating = ( rating ) => String( rating );

/**
 * State of each of the five stars for a rating.
 *
 * @param {number} rating Normalised rating.
 * @return {string[]} 'full' | 'half' | 'empty' for each star.
 */
export function starStates( rating ) {
	return [ 1, 2, 3, 4, 5 ].map( ( star ) => {
		if ( rating >= star ) {
			return 'full';
		}
		return rating >= star - 0.5 ? 'half' : 'empty';
	} );
}

/**
 * Split a 1.x "Name, Role" author string at the first comma.
 *
 * @param {string} author Author (rich text).
 * @return {{authorName: string, authorRole: string}} Name and role.
 */
export function splitAuthor( author ) {
	const value = String( author || '' );
	const comma = value.indexOf( ',' );
	if ( comma === -1 ) {
		return { authorName: value.trim(), authorRole: '' };
	}
	return {
		authorName: value.slice( 0, comma ).trim(),
		authorRole: value.slice( comma + 1 ).trim(),
	};
}
