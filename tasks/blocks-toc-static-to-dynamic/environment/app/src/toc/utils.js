/**
 * Heading helpers shared by the editor UI and save().
 */

/**
 * Plain text of a heading's (rich text) content.
 *
 * @param {string|Object} content Heading content (HTML string or RichTextData).
 * @return {string} Text without markup, whitespace collapsed.
 */
export function headingText( content ) {
	const html = String( content ?? '' );
	const doc = document.implementation.createHTMLDocument( '' );
	doc.body.innerHTML = html;
	return ( doc.body.textContent || '' ).replace( /\s+/g, ' ' ).trim();
}

/**
 * Anchor slug for a heading text.
 *
 * Accents are dropped ("Café" → "cafe"), everything that is not a latin letter
 * or digit becomes a dash, and texts without any usable character get "heading".
 * Public links point at these anchors, so don't change the scheme.
 *
 * @param {string} text Heading text.
 * @return {string} Slug.
 */
export function slugify( text ) {
	const slug = String( text )
		.normalize( 'NFKD' )
		.replace( /[̀-ͯ]/g, '' )
		.toLowerCase()
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /^-+|-+$/g, '' );
	return slug || 'heading';
}

/**
 * Make an anchor unique among the ones already used.
 *
 * @param {string}   base Slug.
 * @param {Set}      used Anchors already in use (mutated).
 * @return {string} Unique anchor.
 */
export function uniqueAnchor( base, used ) {
	let anchor = base;
	let i = 2;
	while ( used.has( anchor ) ) {
		anchor = `${ base }-${ i }`;
		i++;
	}
	used.add( anchor );
	return anchor;
}

/**
 * Flatten a block tree into [ { block, parents } ] in document order.
 *
 * @param {Object[]} blocks  Blocks.
 * @param {string[]} parents Names of the ancestor blocks.
 * @return {Object[]} Flat list.
 */
export function flatten( blocks, parents = [] ) {
	return blocks.flatMap( ( block ) => [
		{ block, parents },
		...flatten( block.innerBlocks || [], [ ...parents, block.name ] ),
	] );
}
