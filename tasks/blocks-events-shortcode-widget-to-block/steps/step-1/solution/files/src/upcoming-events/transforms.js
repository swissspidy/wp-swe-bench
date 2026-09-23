/**
 * Transforms: from Shortcode blocks and from classic content ([acme_events] shortcodes).
 */
import { createBlock } from '@wordpress/blocks';
import { next } from '@wordpress/shortcode';

import metadata from './block.json';
import { attributesFromShortcode } from './attributes';

/**
 * All [acme_events] shortcodes in a text, or null when the text contains anything else.
 *
 * @param {string} text Shortcode block text.
 * @return {Array|null} Named attributes of each shortcode.
 */
function parseListingShortcodes( text = '' ) {
	const found = [];
	let rest = String( text );
	let index = 0;
	let match;
	while ( ( match = next( 'acme_events', rest, index ) ) ) {
		if ( rest.slice( index, match.index ).trim() !== '' ) {
			return null;
		}
		found.push( match.shortcode.attrs.named || {} );
		index = match.index + match.content.length;
	}
	if ( ! found.length || rest.slice( index ).trim() !== '' ) {
		return null;
	}
	return found;
}

const transforms = {
	from: [
		{
			type: 'block',
			blocks: [ 'core/shortcode' ],
			isMatch: ( { text } ) => parseListingShortcodes( text ) !== null,
			transform: ( { text } ) => {
				const blocks = parseListingShortcodes( text ).map( ( named ) =>
					createBlock( metadata.name, attributesFromShortcode( named ) )
				);
				return blocks.length === 1 ? blocks[ 0 ] : blocks;
			},
		},
		{
			type: 'shortcode',
			tag: 'acme_events',
			transform: ( { named } ) => createBlock( metadata.name, attributesFromShortcode( named ) ),
		},
	],
};

export default transforms;
