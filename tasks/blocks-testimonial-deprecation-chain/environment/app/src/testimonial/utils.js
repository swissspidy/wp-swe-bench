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
 * Star characters for a whole-star rating.
 *
 * @param {number} rating Rating 1–5.
 * @return {string} Stars.
 */
export const stars = ( rating ) => '★'.repeat( rating ) + '☆'.repeat( 5 - rating );
