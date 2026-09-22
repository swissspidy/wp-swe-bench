/**
 * Transforms: legacy [callout] shortcodes → callout block.
 */
import { createBlock } from '@wordpress/blocks';

import { getDefaultType } from './types';

function paragraphsFromText( text ) {
	const chunks = String( text || '' )
		.split( /\n\s*\n/ )
		.map( ( chunk ) => chunk.trim() )
		.filter( Boolean );
	return ( chunks.length ? chunks : [ '' ] ).map( ( chunk ) =>
		createBlock( 'core/paragraph', { content: chunk } )
	);
}

const transforms = {
	from: [
		{
			type: 'shortcode',
			tag: 'callout',
			transform( { named = {} }, match ) {
				return createBlock(
					'acme/callout',
					{
						type: named.type || getDefaultType(),
						title: named.title || '',
					},
					paragraphsFromText( match?.shortcode?.content )
				);
			},
		},
	],
};

export default transforms;
